<?php
/**
 * Model Platforms Meta Box
 *
 * @package TMW_SEO
 */

namespace TMW_SEO\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use TMW_SEO\Platform_Registry;
use TMW_SEO\Core;

class Model_Platforms_Metabox {

    const META_KEY = '_tmwseo_model_platforms';
    const USERNAME_META_PREFIX = '_tmwseo_platform_username_';

    /**
     * Initialize metabox
     */
    public static function boot(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_' . Core::MODEL_PT, [__CLASS__, 'save_metabox'], 10, 2);
    }

    /**
     * Add the metabox
     */
    public static function add_metabox(): void {
        add_meta_box(
            'tmwseo_model_platforms',
            'Model Platforms',
            [__CLASS__, 'render_metabox'],
            Core::MODEL_PT,
            'side',
            'default'
        );
    }

    /**
     * Render the metabox
     *
     * @param \WP_Post $post Current post.
     */
    public static function render_metabox(\WP_Post $post): void {
        wp_nonce_field('tmwseo_model_platforms', 'tmwseo_platforms_nonce');

        $platforms = Platform_Registry::get_platforms();
        $active_platforms = get_post_meta($post->ID, self::META_KEY, true) ?: [];

        if (!is_array($active_platforms)) {
            $active_platforms = [];
        }

        echo '<p class="description">Select platforms where this model is active:</p>';
        echo '<div class="tmwseo-platforms-list" style="margin-top: 10px;">';

        foreach ($platforms as $slug => $platform) {
            $checked = in_array($slug, $active_platforms, true) ? 'checked' : '';
            $username = get_post_meta($post->ID, self::USERNAME_META_PREFIX . $slug, true);
            $primary_badge = !empty($platform['is_primary']) ? ' <span style="color: #0073aa; font-size: 10px;">(Primary)</span>' : '';

            echo '<div class="tmwseo-platform-item" style="margin-bottom: 12px; padding: 8px; background: #f9f9f9; border-radius: 4px;">';
            echo '<label style="display: flex; align-items: center; gap: 8px; font-weight: 600;">';
            echo '<input type="checkbox" name="tmwseo_platforms[]" value="' . esc_attr($slug) . '" ' . $checked . '>';
            echo '<span style="color: ' . esc_attr($platform['color']) . ';">' . esc_html($platform['name']) . '</span>';
            echo $primary_badge;
            echo '</label>';

            // Username field (shown when checked)
            echo '<div class="tmwseo-platform-username" style="margin-top: 6px; margin-left: 24px;">';
            echo '<input type="text" name="tmwseo_platform_username_' . esc_attr($slug) . '" ';
            echo 'value="' . esc_attr($username) . '" ';
            echo 'placeholder="Username on ' . esc_attr($platform['name']) . '" ';
            echo 'style="width: 100%; font-size: 12px;">';
            echo '</div>';

            echo '</div>';
        }

        echo '</div>';

        // Quick stats
        $count = count(array_filter($active_platforms));
        echo '<p style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">';
        echo '<strong>Active on:</strong> ' . $count . ' platform' . ($count !== 1 ? 's' : '');
        echo '</p>';
    }

    /**
     * Save metabox data
     *
     * @param int     $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_metabox(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['tmwseo_platforms_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['tmwseo_platforms_nonce'], 'tmwseo_model_platforms')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save active platforms
        $platforms = isset($_POST['tmwseo_platforms']) ? array_map('sanitize_key', $_POST['tmwseo_platforms']) : [];
        $valid_slugs = Platform_Registry::get_platform_slugs();
        $platforms = array_intersect($platforms, $valid_slugs);

        update_post_meta($post_id, self::META_KEY, $platforms);

        // Save usernames for each platform
        foreach ($valid_slugs as $slug) {
            $username_key = 'tmwseo_platform_username_' . $slug;
            if (isset($_POST[$username_key])) {
                $username = sanitize_text_field($_POST[$username_key]);
                if ($username !== '') {
                    update_post_meta($post_id, self::USERNAME_META_PREFIX . $slug, $username);
                } else {
                    delete_post_meta($post_id, self::USERNAME_META_PREFIX . $slug);
                }
            }
        }
    }

    /**
     * Get model's active platforms
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public static function get_model_platforms(int $post_id): array {
        $slugs = get_post_meta($post_id, self::META_KEY, true) ?: [];
        if (!is_array($slugs)) {
            $slugs = [];
        }

        $platforms = [];
        foreach ($slugs as $slug) {
            $platform = Platform_Registry::get_platform($slug);
            if ($platform) {
                $platform['username'] = get_post_meta($post_id, self::USERNAME_META_PREFIX . $slug, true);
                $platforms[$slug] = $platform;
            }
        }

        return $platforms;
    }

    /**
     * Check if model is on specific platform
     *
     * @param int    $post_id       Post ID.
     * @param string $platform_slug Platform slug.
     * @return bool
     */
    public static function model_is_on_platform(int $post_id, string $platform_slug): bool {
        $platforms = get_post_meta($post_id, self::META_KEY, true) ?: [];
        return in_array($platform_slug, (array) $platforms, true);
    }

    /**
     * Get model's username on a platform
     *
     * @param int    $post_id       Post ID.
     * @param string $platform_slug Platform slug.
     * @return string
     */
    public static function get_platform_username(int $post_id, string $platform_slug): string {
        return (string) get_post_meta($post_id, self::USERNAME_META_PREFIX . $platform_slug, true);
    }
}
