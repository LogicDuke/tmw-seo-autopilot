<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Handles competitor keyword distribution and density checks.
 */
class Keyword_Manager {
    protected static $competitor_map = [
        0 => ['Chaturbate', 'Stripchat'],
        1 => ['Stripchat', 'BongaCams'],
        2 => ['BongaCams', 'CamSoda'],
        3 => ['CamSoda', 'MyFreeCams'],
        4 => ['MyFreeCams', 'Chaturbate'],
    ];

    public static function competitor_pair(int $index): array {
        $bucket = (int) floor($index / 800);
        $pair   = self::$competitor_map[$bucket % count(self::$competitor_map)] ?? self::$competitor_map[0];
        return $pair;
    }

    public static function platform_counts(string $type): array {
        if ($type === 'video') {
            return [
                'livejasmin' => [3, 5],
                'onlyfans'   => [2, 3],
                'competitor' => [1, 2],
            ];
        }
        return [
            'livejasmin' => [4, 6],
            'onlyfans'   => [3, 5],
            'competitor' => [2, 4],
        ];
    }

    public static function apply_density(string $content, string $name, array $pair, string $type = 'model'): array {
        $keywords = array_filter(array_unique([
            $name,
            'live cam',
            'webcam highlights',
            'live streaming show',
        ]));

        // Safe mode: do not inject additional paragraphs or brand mentions.
        return [
            'content'  => $content,
            'keywords' => array_values($keywords),
        ];
    }

    protected static function fill_keyword(string $content_lower, string $content, string $needle, array $range): array {
        $count = substr_count($content_lower, strtolower($needle));
        $min   = $range[0];
        $max   = $range[1];
        $add   = [];
        if ($count < $min) {
            $needed = $min - $count;
            for ($i = 0; $i < $needed; $i++) {
                $add[] = sprintf('Looking for %s? %s is highlighted here for clarity.', $needle, $needle);
            }
        } elseif ($count > $max) {
            $add[] = sprintf('Keyword density warning for %s: currently %d mentions (target %d-%d).', $needle, $count, $min, $max);
        }
        return $add;
    }
}
