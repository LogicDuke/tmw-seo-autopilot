<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class VideoSEO {
    public static function boot() {
        add_action('save_post', [__CLASS__, 'on_save'], 35, 3);
    }

    public static function on_save($post_id, $post, $update) {
        if (!$post instanceof \WP_Post) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if (!in_array($post->post_type, Core::video_post_types(), true)) {
            return;
        }

        $existing_focus = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($existing_focus)) {
            return;
        }

        self::generate_for_video($post_id, $post);
    }

    protected static function generate_for_video(int $post_id, \WP_Post $post): void {
        $model_name = trim((string) get_post_meta($post_id, 'tmwseo_model_name', true));

        if ($model_name === '') {
            $model_terms = wp_get_post_terms($post_id, 'models');
            if (!is_wp_error($model_terms) && !empty($model_terms)) {
                $model_name = $model_terms[0]->name ?? '';
            }
        }

        if ($model_name === '') {
            $model_name = $post->post_title;
        }

        $model_name = trim((string) $model_name);
        if ($model_name === '') {
            return;
        }

        $rm = Core::compose_rankmath_for_video(
            $post,
            [
                'name'              => $model_name,
                'highlights_count'  => 7,
            ]
        );

        Core::maybe_update_video_title( $post, $rm['focus'], $model_name );

        Core::maybe_update_video_slug( $post, $rm['focus'] );

        Core::update_rankmath_meta( $post_id, $rm, true );

        $raw_tags = [];
        foreach (['video_tag', 'post_tag', 'livejasmin_tag'] as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $terms = wp_get_post_terms($post_id, $tax);
            if (is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $raw_tags[] = $term->name;
            }
        }

        $tag_keywords = Core::get_safe_model_tag_keywords($raw_tags);
        update_post_meta($post_id, '_tmwseo_video_tag_keywords', $tag_keywords);

        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(
                sprintf(
                    '%s [RM-VIDEO] post#%d focus="%s" title="%s" desc_contains_focus=%s',
                    Core::TAG,
                    $post_id,
                    $rm['focus'],
                    $rm['title'],
                    strpos( $rm['desc'], $rm['focus'] ) !== false ? 'yes' : 'no'
                )
            );
        }
    }
}
