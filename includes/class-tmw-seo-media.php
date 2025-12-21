<?php
namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Media {
    const TAG = '[TMW-SEO-MEDIA]';

    /**
     * WPS LiveJasmin stores the "Main thumbnail" in custom meta fields.
     * Try the common keys here when featured image is missing.
     *
     * @var string[]
     */
    private const LIVEJASMIN_THUMB_META_KEYS = [
        'wps_lj_main_thumbnail',
        'wps_lj_main_thumb',
        'main_thumbnail',
    ];

    public static function boot() {
        if (!is_admin()) {
            return;
        }

        add_action('set_post_thumbnail', [__CLASS__, 'on_set_thumb'], 10, 3);
        add_action('add_attachment', [__CLASS__, 'on_add_attachment']);
        add_action('save_post_' . Core::VIDEO_PT, [__CLASS__, 'on_save_video'], 10, 3);

        // Also react when _thumbnail_id meta is written directly.
        add_action('added_post_meta', [__CLASS__, 'on_thumb_meta'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'on_thumb_meta'], 10, 4);
    }

    public static function on_set_thumb($post_id, $thumb_id, $meta = null) {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (!self::supports_post_type($post->post_type)) {
            return;
        }

        self::fill_attachment_fields((int) $thumb_id, $post);

        if (in_array($post->post_type, Core::video_post_types(), true)) {
            $url = wp_get_attachment_image_url($thumb_id, 'full');
            if ($url) {
                update_post_meta($post_id, 'rank_math_facebook_image', esc_url_raw($url));
                update_post_meta($post_id, 'rank_math_twitter_image', esc_url_raw($url));
            }
        }

        error_log(self::TAG . " set_post_thumbnail post#$post_id thumb#$thumb_id");
    }

    /**
     * Ensure attachment fields are filled whenever _thumbnail_id is set/updated
     * directly via post meta on ANY post type (models, videos, posts, pages, etc).
     *
     * This covers importers / plugins that bypass set_post_thumbnail().
     *
     * @param int    $meta_id
     * @param int    $object_id Post ID that owns the thumbnail.
     * @param string $meta_key
     * @param mixed  $meta_value Attachment ID stored in _thumbnail_id.
     */
    public static function on_thumb_meta($meta_id, $object_id, $meta_key, $meta_value) {
        // Only care about the featured image meta.
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        $post_id  = (int) $object_id;
        $thumb_id = (int) $meta_value;

        if ($post_id <= 0 || $thumb_id <= 0) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        // Avoid running on temporary auto-drafts.
        if ($post->post_status === 'auto-draft' || !self::supports_post_type($post->post_type)) {
            return;
        }

        // Re-use the existing logic that already knows how to build
        // alt/title/caption/description from the parent post context.
        self::fill_attachment_fields($thumb_id, $post);
    }

    public static function on_add_attachment($att_id) {
        $att = get_post($att_id);
        if ($att && 'attachment' === $att->post_type && empty($att->post_title)) {
            wp_update_post(['ID' => $att_id, 'post_title' => basename($att->guid)]);
        }
    }

    public static function on_save_video($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if (!is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }
        if (!$post || $post->post_type !== Core::VIDEO_PT) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if (!$thumb_id) {
            return;
        }

        self::fill_attachment_fields($thumb_id, $post);
    }

    private static function fill_attachment_fields(int $thumb_id, \WP_Post $parent_post): void {
        if (!$thumb_id || !$parent_post instanceof \WP_Post || !self::supports_post_type($parent_post->post_type)) {
            return;
        }

        $attachment = get_post($thumb_id);
        if (!$attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || !wp_attachment_is_image($attachment)) {
            return;
        }

        \TMW_SEO\Media\Image_Meta_Generator::generate_for_featured_image($thumb_id, $parent_post);

        if (in_array($parent_post->post_type, Core::video_post_types(), true)) {
            $url = wp_get_attachment_image_url($thumb_id, 'full');
            if ($url) {
                update_post_meta($parent_post->ID, 'rank_math_facebook_image', esc_url_raw($url));
                update_post_meta($parent_post->ID, 'rank_math_twitter_image', esc_url_raw($url));
            }
        }

        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(self::TAG . " filled thumbnail meta for post {$parent_post->ID} / attachment {$thumb_id}");
        }
    }

    private static function supports_post_type(string $post_type): bool {
        return in_array($post_type, array_merge([Core::MODEL_PT], Core::video_post_types()), true);
    }
}
