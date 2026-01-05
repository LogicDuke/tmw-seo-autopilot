<?php
/**
 * TMW SEO image meta bootstrap.
 *
 * Hooks into featured image changes for videos and models
 * and calls the media\Image_Meta_Generator helper.
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

use TMW_SEO\Media\Image_Meta_Generator;

/**
 * Image Meta class.
 *
 * @package TMW_SEO
 */
class Image_Meta {

    /**
     * Registers image meta hooks.
     *
     * @return void
     */
    public static function boot(): void {
        // When a featured image is set or changed.
        add_action('set_post_thumbnail', [__CLASS__, 'on_set_post_thumbnail'], 10, 2);

        // Safety net: when a video/model post is saved and already has a thumbnail.
        foreach ( Core::video_post_types() as $pt ) {
            add_action( "save_post_{$pt}", [ __CLASS__, 'on_save_post_with_thumbnail' ], 20, 3 );
        }
        add_action('save_post_' . Core::MODEL_PT, [__CLASS__, 'on_save_post_with_thumbnail'], 20, 3);
    }

    /**
     * Handles the `set_post_thumbnail` hook.
     *
     * @param int $post_id Post ID.
     * @param int $thumb_id Thumbnail attachment ID.
     * @return void
     */
    public static function on_set_post_thumbnail(int $post_id, int $thumb_id): void {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        if ( ! Core::is_video_post_type( $post->post_type ) && $post->post_type !== Core::MODEL_PT ) {
            return;
        }

        Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
    }

    /**
     * Handles the `save_post_{post_type}` hook for video/model posts.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post Post object.
     * @param bool     $update Whether this is an existing post.
     * @return void
     */
    public static function on_save_post_with_thumbnail(int $post_id, \WP_Post $post, bool $update): void {
        if ( ! Core::is_video_post_type( $post->post_type ) && $post->post_type !== Core::MODEL_PT ) {
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id > 0) {
            Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
        }
    }
}
