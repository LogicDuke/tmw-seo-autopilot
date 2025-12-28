<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Uniqueness_Checker {
    /**
     * Compare candidate content against a random sample of existing posts.
     * Returns max similarity (0-100).
     */
    public static function similarity_score(string $content, string $post_type, int $limit = 12): float {
        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'post_status'    => 'publish',
            'orderby'        => 'rand',
            'fields'         => 'ids',
        ]);
        if (empty($posts)) {
            return 0.0;
        }
        $needle_tokens = self::tokenize($content);
        $max = 0.0;
        foreach ($posts as $post_id) {
            $haystack = get_post_field('post_content', $post_id);
            $hay_tokens = self::tokenize($haystack);
            if (empty($hay_tokens)) {
                continue;
            }
            $overlap = array_intersect($needle_tokens, $hay_tokens);
            $score = (count($overlap) / max(1, count($needle_tokens))) * 100;
            if ($score > $max) {
                $max = $score;
            }
        }
        return round($max, 2);
    }

    protected static function tokenize(string $text): array {
        $text = strtolower(strip_tags($text));
        $text = preg_replace('/[^a-z0-9]+/i', ' ', $text);
        $parts = preg_split('/\s+/', (string) $text);
        $parts = array_filter($parts, function ($p) {
            return strlen($p) > 3;
        });
        return array_values(array_unique($parts));
    }
}
