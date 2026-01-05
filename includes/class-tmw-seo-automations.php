<?php
/**
 * Tmw Seo Automations helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO; if (!defined('ABSPATH')) exit;

/**
 * Automations class.
 *
 * @package TMW_SEO
 */
class Automations {
    const TAG = '[TMW-SEO-AUTO]';

    /**
     * Registers automation hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 20, 3);
    }

    /**
     * Checks whether video.
     *
     * @param \WP_Post $post
     * @return bool
     */
    protected static function is_video(\WP_Post $post): bool {
        return in_array($post->post_type, Core::video_post_types(), true);
    }

    /**
     * Handles the `transition_post_status` hook.
     *
     * @param string  $new New status.
     * @param string  $old Old status.
     * @param \WP_Post $post Post object.
     * @return void
     */
    public static function on_transition($new, $old, \WP_Post $post) {
        if (!self::is_video($post)) return;
        if ($new !== 'publish' || $old === 'publish') return;
        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) return;

        $already_generated = get_post_meta($post->ID, '_tmwseo_video_seo_generated', true);
        if (!empty($already_generated)) return;

        $existing_focus = get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if (!empty($existing_focus)) {
            update_post_meta($post->ID, '_tmwseo_video_seo_generated', 'existing_focus');
            update_post_meta($post->ID, '_tmwseo_video_seo_done', 'existing_focus');
            return;
        }

        self::run($post->ID, 'transition');
    }

    /**
     * Runs generation for the published video.
     *
     * @param int    $post_ID Post ID.
     * @param string $source Trigger source.
     * @return void
     */
    protected static function run(int $post_ID, string $source) {
        if (get_transient('_tmwseo_running_'.$post_ID)) return; // debounce
        set_transient('_tmwseo_running_'.$post_ID, 1, 15);

        $res = Core::generate_for_video($post_ID, ['strategy'=>'template']);
        Core::debug_log(self::TAG." {$source} video#{$post_ID} => ".json_encode($res));
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
