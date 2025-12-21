<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class RankMath {
    public static function boot() {
        if (!self::is_rankmath_active()) {
            return;
        }

        add_filter('rank_math/frontend/title', [__CLASS__, 'filter_frontend_title'], 10, 1);
    }

    /**
     * Generate an SEO snippet title for model posts only.
     * Pattern: {Model} — {Number} {Sentiment} {PowerWord} Profile Highlights
     */
    public static function generate_model_snippet_title( \WP_Post $post ): string {
        $name = trim(get_the_title($post));
        if ($name === '') {
            return '';
        }

        $numbers = [3, 5, 7, 10];
        $power_words = [
            'Highlights',
            'Spotlight',
            'Profile Guide',
            'Insider Notes',
            'Quick Facts',
            'Key Moments',
            'Top Insights',
            'Focus Points',
            'Behind-the-Scenes',
            'Deep-Dive',
        ];
        $sentiments = [
            'Essential',
            'Trusted',
            'Favorite',
            'Exclusive',
            'Charming',
            'Elegant',
            'Inspiring',
            'Confident',
            'Dynamic',
            'Engaging',
        ];

        $seed = absint($post->ID ?: crc32($name));
        $number     = $numbers[$seed % count($numbers)];
        $power_word = $power_words[$seed % count($power_words)];
        $sentiment  = $sentiments[$seed % count($sentiments)];

        return sprintf('%s — %d %s %s Profile Highlights', $name, $number, $sentiment, $power_word);
    }

    public static function filter_frontend_title($title) {
        global $post;

        if (!self::should_inject_model_title($post)) {
            return $title;
        }

        $generated = self::generate_model_snippet_title($post);
        if ($generated === '') {
            return $title;
        }

        $meta_title      = get_post_meta($post->ID, 'rank_math_title', true);
        $legacy_default  = sprintf('%s — Live Cam Model Profile & Schedule', get_the_title($post));
        $has_manual_meta = $meta_title && $meta_title !== $legacy_default && $meta_title !== $generated;

        if ($has_manual_meta) {
            return $title;
        }

        return $generated;
    }

    protected static function should_inject_model_title($post): bool {
        if (!$post instanceof \WP_Post) {
            return false;
        }
        if (!is_singular(Core::MODEL_PT) && $post->post_type !== Core::MODEL_PT) {
            return false;
        }
        return true;
    }

    protected static function is_rankmath_active(): bool {
        return class_exists('RankMath') || class_exists('\\RankMath\\Helper') || defined('RANK_MATH_VERSION');
    }
}
