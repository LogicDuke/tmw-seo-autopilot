<?php
/**
 * Internal keyword expansion without relying on external APIs.
 *
 * @package TMW_SEO_Autopilot
 */

namespace TMW_SEO;

defined('ABSPATH') || exit;

/**
 * Expands seed keywords using pattern-based generation.
 */
class Keyword_Expander {

    /**
     * Prefix patterns for keyword expansion.
     */
    private static array $prefixes = [
        'best', 'top', 'hot', 'free', 'live', 'real', 'amateur',
        'sexy', 'beautiful', 'gorgeous', 'stunning', 'cute',
        'young', 'mature', 'new', 'popular', 'trending',
    ];

    /**
     * Suffix patterns for keyword expansion.
     */
    private static array $suffixes = [
        'live', 'online', 'now', 'today', 'free', 'private',
        'show', 'chat', 'stream', 'cams', 'webcam', 'cam',
        'on livejasmin', 'on chaturbate', 'on stripchat',
        'private show', 'live show', 'cam show',
    ];

    /**
     * Platform variations to append.
     */
    private static array $platform_suffixes = [
        'livejasmin', 'chaturbate', 'stripchat', 'bongacams',
        'camsoda', 'myfreecams', 'flirt4free', 'cam4',
    ];

    /**
     * Expand a single seed into multiple keyword variations.
     *
     * @param string $seed     The seed phrase.
     * @param string $category The category for context.
     * @param int    $limit    Maximum variations to generate.
     * @return array Array of expanded keywords.
     */
    public static function expand_seed(string $seed, string $category = '', int $limit = 20): array {
        $keywords = [];
        $seed = strtolower(trim($seed));

        // 1. The seed itself is a keyword
        $keywords[] = $seed;

        // 2. Add prefix variations
        foreach (self::$prefixes as $prefix) {
            if (count($keywords) >= $limit) break;
            $variant = $prefix . ' ' . $seed;
            if (!in_array($variant, $keywords, true)) {
                $keywords[] = $variant;
            }
        }

        // 3. Add suffix variations
        foreach (self::$suffixes as $suffix) {
            if (count($keywords) >= $limit) break;
            // Skip if seed already ends with similar term
            if (str_contains($seed, $suffix)) continue;
            $variant = $seed . ' ' . $suffix;
            if (!in_array($variant, $keywords, true)) {
                $keywords[] = $variant;
            }
        }

        // 4. Add platform-specific variations (high value for SEO)
        foreach (self::$platform_suffixes as $platform) {
            if (count($keywords) >= $limit) break;
            if (str_contains($seed, $platform)) continue;
            $variant = $seed . ' ' . $platform;
            if (!in_array($variant, $keywords, true)) {
                $keywords[] = $variant;
            }
        }

        // 5. Combination variations (prefix + seed + platform)
        $priority_prefixes = ['best', 'top', 'hot', 'free'];
        $priority_platforms = ['livejasmin', 'chaturbate', 'stripchat'];
        foreach ($priority_prefixes as $prefix) {
            foreach ($priority_platforms as $platform) {
                if (count($keywords) >= $limit) break 2;
                if (str_contains($seed, $platform)) continue;
                $variant = $prefix . ' ' . $seed . ' ' . $platform;
                if (!in_array($variant, $keywords, true)) {
                    $keywords[] = $variant;
                }
            }
        }

        return array_slice($keywords, 0, $limit);
    }

    /**
     * Expand all seeds in a category.
     *
     * @param array  $seeds    Array of seed phrases.
     * @param string $category Category name.
     * @param int    $per_seed Max keywords per seed.
     * @return array All expanded keywords.
     */
    public static function expand_category(array $seeds, string $category, int $per_seed = 15): array {
        $all_keywords = [];

        foreach ($seeds as $seed) {
            $expanded = self::expand_seed($seed, $category, $per_seed);
            $all_keywords = array_merge($all_keywords, $expanded);
        }

        // Remove duplicates
        return array_unique($all_keywords);
    }

    /**
     * Get category-specific modifiers.
     *
     * @param string $category Category name.
     * @return array Additional modifiers for this category.
     */
    public static function get_category_modifiers(string $category): array {
        $modifiers = [
            'roleplay'    => ['fantasy', 'scenario', 'acting', 'pretend', 'story'],
            'cosplay'     => ['costume', 'anime', 'character', 'outfit', 'dressed as'],
            'chatty'      => ['talkative', 'conversation', 'friendly', 'social'],
            'dance'       => ['dancing', 'dancer', 'moves', 'twerk', 'strip'],
            'glamour'     => ['elegant', 'classy', 'sophisticated', 'luxury'],
            'romantic'    => ['sensual', 'intimate', 'loving', 'girlfriend'],
            'dominant'    => ['domme', 'mistress', 'bossy', 'commanding', 'findom'],
            'fitness'     => ['fit', 'gym', 'muscular', 'toned', 'athletic'],
            'outdoor'     => ['outside', 'public', 'nature', 'balcony', 'garden'],
            'uniforms'    => ['nurse', 'maid', 'schoolgirl', 'secretary', 'police'],
            'couples'     => ['couple', 'duo', 'pair', 'boyfriend', 'girlfriend'],
            'fetish-lite' => ['feet', 'stockings', 'heels', 'lingerie', 'leather'],
            'petite'      => ['small', 'tiny', 'slim', 'skinny', 'short'],
            'curvy'       => ['thick', 'voluptuous', 'bbw', 'plus size', 'chubby'],
            'athletic'    => ['fit', 'sporty', 'toned', 'muscular', 'gym'],
            'big-boobs'   => ['busty', 'large breasts', 'big tits', 'huge boobs'],
            'big-butt'    => ['big ass', 'thick booty', 'phat ass', 'juicy booty'],
            'asian'       => ['japanese', 'korean', 'chinese', 'filipina', 'thai'],
            'latina'      => ['colombian', 'mexican', 'brazilian', 'spanish', 'puerto rican'],
            'ebony'       => ['black', 'african', 'dark skin', 'chocolate'],
        ];

        return $modifiers[$category] ?? [];
    }
}
