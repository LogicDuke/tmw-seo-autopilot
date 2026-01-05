<?php
/**
 * Uniqueness Checker helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Uniqueness Checker class.
 *
 * @package TMW_SEO
 */
class Uniqueness_Checker {
    /**
     * Handles similarity score.
     *
     * @param string $content
     * @param mixed $post_type
     * @param int $limit
     * @return float
     */
    public static function similarity_score(string $content, $post_type, int $limit = 12): float {
        $post_types = (array) $post_type;
        $posts = get_posts([
            'post_type'      => $post_types,
            'posts_per_page' => 200,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);
        if (empty($posts)) {
            return 0.0;
        }

        if (count($posts) > $limit) {
            shuffle($posts);
            $posts = array_slice($posts, 0, $limit);
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

    /**
     * Handles tokenize.
     *
     * @param string $text
     * @return array
     */
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
