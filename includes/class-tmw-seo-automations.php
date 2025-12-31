<?php
namespace TMW_SEO; if (!defined('ABSPATH')) exit;

class Automations {
    const TAG = '[TMW-SEO-AUTO]';

    public static function boot() {
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 20, 3);
    }

    protected static function is_video(\WP_Post $post): bool {
        return in_array($post->post_type, Core::video_post_types(), true);
    }

    public static function on_transition($new, $old, \WP_Post $post) {
        if (!self::is_video($post)) return;
        if ($new !== 'publish' || $old === 'publish') return;
        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) return;

        $already_generated = get_post_meta($post->ID, '_tmwseo_video_seo_generated', true);
        if (!empty($already_generated)) return;

        $existing_focus = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if (!empty($existing_focus)) {
            update_post_meta($post->ID, '_tmwseo_video_seo_generated', 'existing_focus');
            return;
        }

        self::run($post->ID, 'transition');
    }

    protected static function run(int $post_ID, string $source) {
        if (get_transient('_tmwseo_running_'.$post_ID)) return; // debounce
        set_transient('_tmwseo_running_'.$post_ID, 1, 15);

        $res = Core::generate_for_video($post_ID, ['strategy'=>'template']);
        error_log(self::TAG." {$source} video#{$post_ID} => ".json_encode($res));
        if (is_admin()) {
            $msg = $res['ok'] ? 'Generated SEO & content' : 'Skipped: '.$res['message'];
            update_post_meta($post_ID, '_tmwseo_last_message', $msg);
        }
        if (!empty($res['ok'])) {
            update_post_meta($post_ID, '_tmwseo_video_seo_generated', gmdate('c'));
        }
        delete_transient('_tmwseo_running_'.$post_ID);
    }
}
