<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class VideoSEO {
    public static function boot() {
        add_action('transition_post_status', [__CLASS__, 'on_transition'], 35, 3);
    }

    public static function on_transition($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Guard against re-entrancy (e.g. wp_update_post inside generation).
        static $processing = [];
        if (!empty($processing[$post->ID ?? 0])) {
            return;
        }

        // Do not interfere with importers/AJAX/REST requests.
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        // Extra safety: skip on LiveJasmin admin import page.
        if (is_admin() && isset($_GET['page']) && stripos((string) $_GET['page'], 'livejasmin') !== false) {
            return;
        }

        if (!$post instanceof \WP_Post) {
            return;
        }

        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) {
            return;
        }

        if (!Core::is_video_post_type($post->post_type)) {
            return;
        }

        $post_id = (int) $post->ID;

        $already_generated = get_post_meta($post_id, '_tmwseo_video_seo_done', true);
        if (!empty($already_generated)) {
            return;
        }

        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(
                sprintf(
                    '%s [VIDEO-SEO] transition to publish post#%d (%s)',
                    Core::TAG,
                    $post_id,
                    $post->post_type
                )
            );
        }

        $processing[$post_id] = true;
        self::generate_for_video($post_id, $post);
        update_post_meta($post_id, '_tmwseo_video_seo_done', gmdate('c'));
        unset($processing[$post_id]);
    }

    protected static function generate_for_video(int $post_id, \WP_Post $post): void {
        $raw_model_name = Core::get_video_model_name_raw($post);
        $model_name = Core::sanitize_sfw_text($raw_model_name, 'Live Cam Model');

        $manual = Core::resolve_video_manual_inputs($post, $model_name);

        $looks    = Core::first_looks( $post_id );
        $lj_title = get_the_title( $post_id );
        $csv_title_focus = Core::select_video_title_focus_csv( $post, $looks, $lj_title );
        $csv_focus       = $csv_title_focus['focus_keyword'] ?? '';
        $csv_title       = $csv_title_focus['seo_title'] ?? '';
        $video_extras    = Core::select_video_extras_csv( $post, $looks, $lj_title );

        if ( $csv_focus !== '' ) {
            update_post_meta( $post_id, 'rank_math_focus_keyword', $csv_focus );
        } elseif ($manual['manual_focus'] === '' && $manual['focus'] !== '') {
            update_post_meta($post_id, 'rank_math_focus_keyword', $manual['focus']);
        }

        $rm = Core::compose_rankmath_for_video(
            $post,
            [
                'name'              => $model_name,
                'highlights_count'  => 7,
                'focus'             => $csv_focus !== '' ? $csv_focus : $manual['focus'],
                'manual_title'      => $csv_title !== '' ? $csv_title : $manual['manual_title'],
                'csv_title'         => $csv_title,
                'csv_focus'         => $csv_focus,
                'csv_extras'        => $video_extras,
            ]
        );

        if ( $csv_title !== '' && ! get_post_meta( $post_id, '_tmwseo_video_title_locked', true ) ) {
            update_post_meta( $post_id, '_tmwseo_video_title_locked', 1 );
            wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => $csv_title,
                    'post_name'  => $post->post_name,
                ]
            );
            delete_post_meta( $post_id, '_tmwseo_video_title_locked' );
        } elseif ($manual['manual_title_raw'] === '') {
            Core::maybe_update_video_title( $post, $rm['focus'], $model_name );
        }

        Core::update_rankmath_meta( $post_id, $rm, true );

        Core::update_video_slug_from_manual_inputs($post, $manual['focus'], $manual['manual_title']);

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
                    '%s [RM-VIDEO] post#%d model_raw="%s" model_sfw="%s" final_title="%s" final_focus="%s" desc_contains_focus=%s',
                    Core::TAG,
                    $post_id,
                    $raw_model_name,
                    $model_name,
                    $rm['title'],
                    $rm['focus'],
                    strpos( $rm['desc'], $rm['focus'] ) !== false ? 'yes' : 'no'
                )
            );
        }
    }
}
