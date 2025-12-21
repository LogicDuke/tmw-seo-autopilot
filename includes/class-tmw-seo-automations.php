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
        if ($post->post_status !== 'publish') return;
        if (wp_is_post_autosave($post_ID) || wp_is_post_revision($post_ID)) return;
        self::run($post_ID, 'save_post');
    }

    public static function on_transition($new, $old, \WP_Post $post) {
        if (!self::is_video($post)) return;
        if ($new !== 'publish') return;
        self::run($post->ID, 'transition');
    }

    protected static function run(int $post_ID, string $source) {
        if (get_transient('_tmwseo_running_'.$post_ID)) return; // debounce
        set_transient('_tmwseo_running_'.$post_ID, 1, 15);

        $existing_focus = get_post_meta( $post_ID, 'rank_math_focus_keyword', true );
        if ( ! empty( $existing_focus ) ) {
            delete_transient('_tmwseo_running_'.$post_ID);
            return;
        }

        $res = Core::generate_for_video($post_ID, ['strategy'=>'template']);
        error_log(self::TAG." {$source} video#{$post_ID} => ".json_encode($res));
        if (is_admin()) {
            $msg = $res['ok'] ? 'Generated SEO & content' : 'Skipped: '.$res['message'];
            update_post_meta($post_ID, '_tmwseo_last_message', $msg);
        }
        delete_transient('_tmwseo_running_'.$post_ID);
    }
}
