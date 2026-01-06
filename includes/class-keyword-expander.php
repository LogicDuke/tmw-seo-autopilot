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

        foreach ($seeds as $seed) {
            $expanded = self::expand_seed($seed, $per_seed);
            $all_keywords = array_merge($all_keywords, $expanded);
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
        $base_modifiers = [
            'best', 'top', 'hot', 'free', 'live', 'sexy', 'amateur', 'real',
        ];

        $platforms = ['livejasmin', 'chaturbate', 'stripchat'];

        $category_terms = [
            'uniforms'       => ['nurse cam girl', 'maid webcam', 'schoolgirl cam', 'secretary webcam', 'uniform cam'],
            'fetish-lite'    => ['feet cam girl', 'stockings webcam', 'lingerie cam', 'heels webcam', 'nylon cam'],
            'big-boobs'      => ['busty cam girl', 'big tits webcam', 'huge boobs cam', 'busty model webcam'],
            'big-butt'       => ['big ass cam girl', 'big booty webcam', 'thick ass cam', 'phat booty webcam'],
            'interracial'    => ['interracial cam', 'mixed couple webcam', 'interracial show cam'],
            'white'          => ['white cam girl', 'caucasian webcam', 'european cam girl'],
            'tattoo-piercing'=> ['tattooed cam girl', 'inked webcam', 'pierced cam girl', 'alt cam model'],
            'chatty'         => ['chatty cam girl', 'talkative webcam', 'friendly cam model'],
            'fitness'        => ['fitness cam girl', 'fit webcam model', 'athletic cam girl', 'gym cam'],
            'dominant'       => ['dominant cam girl', 'domme webcam', 'femdom cam', 'mistress cam'],
            'livejasmin'     => ['livejasmin models', 'livejasmin girls', 'livejasmin cam'],
            'roleplay'       => ['roleplay cam girl', 'fantasy webcam', 'roleplay show cam'],
            'cosplay'        => ['cosplay cam girl', 'anime webcam model', 'costume cam'],
            'dance'          => ['dancing cam girl', 'twerk webcam', 'stripper cam'],
            'glamour'        => ['glamour cam girl', 'elegant webcam', 'classy cam model'],
            'romantic'       => ['romantic cam girl', 'sensual webcam', 'girlfriend cam'],
            'outdoor'        => ['outdoor cam girl', 'public webcam', 'exhibitionist cam'],
            'couples'        => ['couple cam show', 'couples webcam', 'duo cam'],
            'petite'         => ['petite cam girl', 'small webcam model', 'tiny cam girl'],
            'curvy'          => ['curvy cam girl', 'thick webcam model', 'bbw cam'],
            'athletic'       => ['athletic cam girl', 'sporty webcam', 'fit cam model'],
            'asian'          => ['asian cam girl', 'japanese webcam', 'korean cam model'],
            'latina'         => ['latina cam girl', 'colombian webcam', 'brazilian cam'],
            'ebony'          => ['ebony cam girl', 'black webcam model', 'african cam'],
            'blonde'         => ['blonde cam girl', 'blonde webcam model'],
            'brunette'       => ['brunette cam girl', 'brunette webcam model'],
            'redhead'        => ['redhead cam girl', 'ginger webcam model'],
            'milf'           => ['milf cam girl', 'mature webcam model', 'cougar cam'],
            'teen'           => ['18 year old cam girl', 'young webcam model', 'college cam'],
            'toys'           => ['lovense cam girl', 'toy webcam show', 'vibrator cam'],
            'private-shows'  => ['private cam show', 'exclusive webcam', 'one on one cam'],
            'general'        => ['cam girls', 'webcam models', 'live cam girls'],
            'compare-platforms' => ['livejasmin vs chaturbate', 'best cam site', 'cam site comparison'],
        ];

        $terms = $category_terms[$category] ?? $category_terms['general'];
        $fallbacks = [];

        // Generate combinations: modifier + term + platform
        foreach ($terms as $term) {
            $fallbacks[] = $term; // Base term
            foreach ($base_modifiers as $mod) {
                $fallbacks[] = $mod . ' ' . $term;
            }
            foreach ($platforms as $platform) {
                $fallbacks[] = $term . ' ' . $platform;
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
