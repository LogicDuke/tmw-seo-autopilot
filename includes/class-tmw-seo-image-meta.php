<?php
/**
 * TMW SEO – Image meta bootstrap
 *
 * Hooks into featured image changes for videos and models
 * and calls the media\Image_Meta_Generator helper.
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

use TMW_SEO\Media\Image_Meta_Generator;

class Image_Meta {

    public static function boot(): void {
        // When a featured image is set or changed.
        add_action('set_post_thumbnail', [__CLASS__, 'on_set_post_thumbnail'], 10, 2);

        // Safety net: when a video/model post is saved and already has a thumbnail.
        add_action('save_post_video', [__CLASS__, 'on_save_post_with_thumbnail'], 20, 3);
        add_action('save_post_model', [__CLASS__, 'on_save_post_with_thumbnail'], 20, 3);
    }

    /**
     * Fires when a thumbnail is selected on the edit screen.
     *
     * @param int $post_id
     * @param int $thumb_id
     */
    public static function on_set_post_thumbnail(int $post_id, int $thumb_id): void {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (!in_array($post->post_type, ['video', 'model'], true)) {
            return;
        }

        Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
    }

    /**
     * Backup hook – if the importer sets the thumbnail before we’re loaded.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public static function on_save_post_with_thumbnail(int $post_id, \WP_Post $post, bool $update): void {
        if (!in_array($post->post_type, ['video', 'model'], true)) {
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id > 0) {
            Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
        }
    }
}
