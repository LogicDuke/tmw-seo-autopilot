<?php
namespace TMW_SEO; if (!defined('ABSPATH')) exit;

class Automations {
    const TAG = '[TMW-SEO-AUTO]';

    public static function boot() {
        add_action('save_post', [__CLASS__, 'on_save'], 20, 3);
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 20, 3);
    }

    protected static function is_video(\WP_Post $post): bool {
        return in_array($post->post_type, Core::video_post_types(), true);
    }

    public static function on_save(int $post_ID, \WP_Post $post, bool $update) {
        if (!self::is_video($post)) return;
        if (Core::should_skip_request($post, 'automations_save_post')) return;
        if (defined('TMW_SEO_UPLOAD_DEBUG') && TMW_SEO_UPLOAD_DEBUG) {
            error_log(self::TAG . " save_post skipped to avoid wp_update_post during save hook for #{$post_ID}");
        }
        return;
    }

    public static function on_transition($new, $old, \WP_Post $post) {
        if (!self::is_video($post)) return;
        if ($new !== 'publish') return;
        if (Core::should_skip_request($post, 'automations_transition')) return;
        self::run($post->ID, 'transition');
    }

    protected static function run(int $post_ID, string $source) {
        static $processing = [];
        if (!empty($processing[$post_ID])) {
            return;
        }
        if (get_transient('_tmwseo_running_'.$post_ID)) return; // debounce
        set_transient('_tmwseo_running_'.$post_ID, 1, 15);

        $existing_focus = get_post_meta( $post_ID, 'rank_math_focus_keyword', true );
        if ( ! empty( $existing_focus ) ) {
            delete_transient('_tmwseo_running_'.$post_ID);
            return;
        }

        $processing[$post_ID] = true;
        $res = Core::generate_for_video($post_ID, ['strategy'=>'template']);
        error_log(self::TAG." {$source} video#{$post_ID} => ".json_encode($res));
        if (is_admin()) {
            $msg = $res['ok'] ? 'Generated SEO & content' : 'Skipped: '.$res['message'];
            update_post_meta($post_ID, '_tmwseo_last_message', $msg);
        }
        unset($processing[$post_ID]);
        delete_transient('_tmwseo_running_'.$post_ID);
    }
}
