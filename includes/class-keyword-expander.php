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
     * @param string $seed  The seed phrase.
     * @param int    $limit Maximum variations to generate.
     * @return array Array of expanded keywords.
     */
    public static function expand_seed(string $seed, int $limit = 20): array {
        $keywords = [];
        $seen     = [];
        $seed     = strtolower(trim($seed));

        // Helper to add keyword if not seen
        $add_keyword = function (string $kw) use (&$keywords, &$seen, $limit): bool {
            if (count($keywords) >= $limit) {
                return false;
            }

            $key = strtolower($kw);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $keywords[] = $kw;
            }

            return true;
        };

        // 1. The seed itself is a keyword
        $add_keyword($seed);

        // 2. Add prefix variations
        foreach (self::$prefixes as $prefix) {
            if (count($keywords) >= $limit) break;
            $add_keyword($prefix . ' ' . $seed);
        }

        // 3. Add suffix variations
        foreach (self::$suffixes as $suffix) {
            if (count($keywords) >= $limit) break;
            // Skip if seed already ends with similar term
            if (strpos($seed, $suffix) !== false) continue;
            $add_keyword($seed . ' ' . $suffix);
        }

        // 4. Add platform-specific variations (high value for SEO)
        foreach (self::$platform_suffixes as $platform) {
            if (count($keywords) >= $limit) break;
            if (strpos($seed, $platform) !== false) continue;
            $add_keyword($seed . ' ' . $platform);
        }

        // 5. Combination variations (prefix + seed + platform)
        $priority_prefixes = ['best', 'top', 'hot', 'free'];
        $priority_platforms = ['livejasmin', 'chaturbate', 'stripchat'];
        foreach ($priority_prefixes as $prefix) {
            foreach ($priority_platforms as $platform) {
                if (count($keywords) >= $limit) break 2;
                if (strpos($seed, $platform) !== false) continue;
                $add_keyword($prefix . ' ' . $seed . ' ' . $platform);
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

        if ($category === 'livejasmin') {
            $livejasmin_seeds = [
                'livejasmin cam models',
                'livejasmin private show',
                'livejasmin live cams',
                'livejasmin cam girls',
            ];

            $seeds = array_merge($seeds, $livejasmin_seeds);
        }

        $seeds = array_values(array_unique(array_filter(array_map('trim', $seeds), 'strlen')));

        foreach ($seeds as $seed) {
            $expanded = self::expand_seed($seed, $per_seed);
            $all_keywords = array_merge($all_keywords, $expanded);

            if ($category === 'livejasmin') {
                $templates = [
                    'best %s on livejasmin',
                    '%s livejasmin private chat',
                    '%s livejasmin tips',
                    '%s livejasmin reviews',
                ];

                foreach ($templates as $template) {
                    $all_keywords[] = sprintf($template, $seed);
                }
            }
        }

        // Remove duplicates
        return array_unique($all_keywords);
    }

    /**
     * Get hardcoded fallback keywords for a category.
     * These are GUARANTEED to be valid and will be used if all other sources fail.
     *
     * @param string $category Category name.
     * @return array Fallback keywords.
     */
    public static function get_fallback_keywords(string $category): array {
        $platforms = ['livejasmin', 'chaturbate', 'stripchat'];

        $category_bases = [
            'uniforms'         => ['nurse', 'maid', 'schoolgirl', 'secretary'],
            'fetish-lite'      => ['feet', 'stockings', 'lingerie', 'heels', 'nylon'],
            'big-boobs'        => ['busty', 'big tits', 'huge boobs'],
            'big-butt'         => ['big ass', 'big booty', 'thick booty'],
            'interracial'      => ['interracial couple', 'mixed couple'],
            'white'            => ['white', 'caucasian', 'european'],
            'tattoo-piercing'  => ['tattooed', 'inked', 'pierced', 'alt'],
            'chatty'           => ['chatty', 'talkative', 'friendly'],
            'fitness'          => ['fitness', 'fit', 'athletic', 'gym'],
            'dominant'         => ['dominant', 'domme', 'femdom', 'mistress'],
            'livejasmin'       => ['livejasmin', 'livejasmin model', 'livejasmin girl'],
            'roleplay'         => ['roleplay', 'fantasy', 'scenario'],
            'cosplay'          => ['cosplay', 'anime', 'costume'],
            'dance'            => ['dancing', 'twerk', 'stripper'],
            'glamour'          => ['glamour', 'elegant', 'classy'],
            'romantic'         => ['romantic', 'sensual', 'girlfriend'],
            'outdoor'          => ['outdoor', 'public', 'exhibitionist'],
            'couples'          => ['couple', 'duo', 'pair'],
            'petite'           => ['petite', 'small', 'tiny'],
            'curvy'            => ['curvy', 'thick', 'bbw'],
            'athletic'         => ['athletic', 'sporty', 'muscular'],
            'asian'            => ['asian', 'japanese', 'korean'],
            'latina'           => ['latina', 'colombian', 'brazilian'],
            'ebony'            => ['ebony', 'black', 'african'],
            'blonde'           => ['blonde', 'fair hair'],
            'brunette'         => ['brunette', 'brown hair'],
            'redhead'          => ['redhead', 'ginger'],
            'milf'             => ['milf', 'mature', 'cougar'],
            'teen'             => ['18 year old', 'college', 'young'],
            'toys'             => ['lovense', 'toy', 'vibrator'],
            'private-shows'    => ['private', 'exclusive', 'one on one'],
            'compare-platforms'=> ['cam site comparison', 'best cam site', 'livejasmin vs chaturbate'],
            'general'          => ['cam girl', 'webcam model', 'live cam'],
        ];

        $bases = $category_bases[$category] ?? ['sexy', 'hot', 'beautiful'];
        $fallbacks = [];

        // ALWAYS append "cam girl" or platform to ensure industry relevance
        foreach ($bases as $base) {
            // Base + cam girl
            $fallbacks[] = $base . ' cam girl';
            $fallbacks[] = $base . ' webcam model';
            $fallbacks[] = $base . ' cam show';
            
            // Base + platform
            foreach ($platforms as $platform) {
                $fallbacks[] = $base . ' cam girl ' . $platform;
                $fallbacks[] = $base . ' ' . $platform;
            }
        }

        return array_unique($fallbacks);
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
