<?php
namespace TMW_SEO; if (!defined('ABSPATH')) exit;

class Automations {
    const TAG = '[TMW-SEO-AUTO]';
    const CRON_HOOK = 'tmwseo_generate_video_async';

    public static function boot() {
        // IMPORTANT:
        // Do NOT run on save_post — importers depend on clean save cycle.
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 20, 3);

        // Async worker
        add_action(self::CRON_HOOK, [__CLASS__, 'run_async'], 10, 1);
    }

    protected static function is_video(\WP_Post $post): bool {
        return in_array($post->post_type, Core::video_post_types(), true);
    }

    protected static function should_skip_runtime(): bool {
        // Never interfere with AJAX/REST/import requests.
        if (wp_doing_ajax()) return true;
        if (defined('REST_REQUEST') && REST_REQUEST) return true;
        if (defined('DOING_CRON') && DOING_CRON) return false; // cron is allowed
        if (!is_admin()) return true;

        // Extra safety: skip while on the LiveJasmin import admin page
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if ($page && stripos($page, 'livejasmin') !== false) return true;

        return false;
    }

    public static function on_transition($new, $old, \WP_Post $post) {
        if (!self::is_video($post)) return;

        // Only when it becomes publish (once)
        if ($new !== 'publish' || $old === 'publish') return;

        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) return;

        // If already has focus keyword, skip
        $existing_focus = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if (!empty($existing_focus)) return;

        // If we are inside import runtime, queue for later
        if (self::should_skip_runtime()) {
            self::queue($post->ID, 'transition_skip_runtime');
            return;
        }

        // Even outside import runtime: still queue to avoid touching publish request
        self::queue($post->ID, 'transition_queue');
    }

    protected static function queue(int $post_id, string $source): void {
        // Debounce per post
        if (get_transient('_tmwseo_running_'.$post_id)) return;
        set_transient('_tmwseo_running_'.$post_id, 1, 60);

        // Queue single event ~45s later
        if (!wp_next_scheduled(self::CRON_HOOK, [$post_id])) {
            wp_schedule_single_event(time() + 45, self::CRON_HOOK, [$post_id]);
        }

        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(self::TAG . " queued {$source} video#{$post_id}");
        }
    }

    public static function run_async(int $post_id): void {
        delete_transient('_tmwseo_running_'.$post_id);

        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) return;
        if (!in_array($post->post_type, Core::video_post_types(), true)) return;
        if ($post->post_status !== 'publish') return;

        $existing_focus = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($existing_focus)) return;

        $res = Core::generate_for_video($post_id, ['strategy' => 'template', 'force' => false]);

        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(self::TAG . " async video#{$post_id} => " . wp_json_encode($res));
        }
    }
}
