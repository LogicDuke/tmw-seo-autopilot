<?php
/**
 * Image meta generator
 *
 * Fills ALT, Title, Caption and Description for attachments
 * when they are used as featured images on video/model posts.
 */

namespace TMW_SEO\Media;

if (!defined('ABSPATH')) {
    exit;
}

class Image_Meta_Generator {

    /**
     * Main entry point – called by our boot class.
     *
     * @param int      $attachment_id
     * @param \WP_Post $parent_post
     */
    public static function generate_for_featured_image(int $attachment_id, \WP_Post $parent_post): void {
        // Only for real image attachments.
        $attachment = get_post($attachment_id);
        if (
            !$attachment ||
            'attachment' !== $attachment->post_type ||
            0 !== strpos((string) get_post_mime_type($attachment_id), 'image/')
        ) {
            return;
        }

        $already_generated = (bool) get_post_meta($attachment_id, '_tmw_image_meta_generated', true);

        // Collect existing values so we never overwrite manual edits.
        $current_alt       = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $current_title     = $attachment->post_title;
        $current_caption   = $attachment->post_excerpt;
        $current_content   = $attachment->post_content;

        // If we ran before and the user has kept any of the fields, keep respecting them.
        if ($already_generated && ($current_alt || $current_title || $current_caption || $current_content)) {
            return;
        }

        $meta = self::build_meta_text($attachment, $parent_post);

        $update = ['ID' => $attachment_id];

        if (empty($current_title) && !empty($meta['title'])) {
            $update['post_title'] = $meta['title'];
        }

        if (empty($current_caption) && !empty($meta['caption'])) {
            $update['post_excerpt'] = $meta['caption'];
        }

        if (empty($current_content) && !empty($meta['description'])) {
            $update['post_content'] = $meta['description'];
        }

        // Only call wp_update_post if we have something to change.
        if (count($update) > 1) {
            wp_update_post($update);
        }

        if (empty($current_alt) && !empty($meta['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $meta['alt']);
        }

        // Mark as generated so we don't keep touching it.
        update_post_meta($attachment_id, '_tmw_image_meta_generated', 1);
    }

    /**
     * Build non-explicit, SEO-friendly text based on context.
     *
     * @param \WP_Post $attachment
     * @param \WP_Post $parent_post
     * @return array{alt:string,title:string,caption:string,description:string}
     */
    protected static function build_meta_text(\WP_Post $attachment, \WP_Post $parent_post): array {
        $site_name   = get_bloginfo('name');
        $post_title  = trim(strip_tags($parent_post->post_title));
        $file_title  = preg_replace('/\.[^.]+$/', '', $attachment->post_name);

        // Fallback if somehow the parent has no title yet.
        $base = $post_title ?: ucwords(str_replace(['-', '_'], ' ', $file_title));

        // Keep everything PG-13 and non-explicit.
        $alt   = sprintf('%s – preview image on %s', $base, $site_name);
        $title = sprintf('%s – featured image', $base);

        if ($parent_post->post_type === 'video') {
            $caption = sprintf(
                'Preview thumbnail for the video “%s” on %s.',
                $base,
                $site_name
            );
            $description = sprintf(
                'Automatic preview image for the video “%s”, showcasing a friendly live cam moment on %s. '
                . 'This image is used as the main thumbnail for the video page.',
                $base,
                $site_name
            );
        } elseif ($parent_post->post_type === 'model') {
            $caption = sprintf(
                'Profile image for %s on %s.',
                $base,
                $site_name
            );
            $description = sprintf(
                'Automatic profile image description for %s on %s. '
                . 'This picture represents the model on the main profile page and in related listings.',
                $base,
                $site_name
            );
        } else {
            // Generic fallback for any other post type.
            $caption = sprintf(
                'Featured image for “%s” on %s.',
                $base,
                $site_name
            );
            $description = sprintf(
                'Automatic description for the featured image attached to “%s” on %s.',
                $base,
                $site_name
            );
        }

        return [
            'alt'         => $alt,
            'title'       => $title,
            'caption'     => $caption,
            'description' => $description,
        ];
    }
}
