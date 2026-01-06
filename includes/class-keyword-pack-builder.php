<?php
/**
 * Keyword Pack Builder helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Keyword Pack Builder class.
 *
 * @package TMW_SEO
 */
class Keyword_Pack_Builder {
    protected static $google_suggest_client;

    /**
     * Determine keyword type based on brand detection and length.
     *
     * @param string $keyword
     * @param string $category
     * @param int    $word_count
     * @return string
     */
    private static function determine_type(string $keyword, string $category, int $word_count): string {
        $primary = self::get_primary_brand_for_category($category);
        $brand   = self::find_brand($keyword);

        if ($brand !== '' && ($primary === '' || $brand !== $primary)) {
            return 'competitor';
        }

        return $word_count >= 5 ? 'longtail' : 'extra';
    }

    /**
     * Get minimum targets per category and type.
     *
     * @param string $category
     * @return array
     */
    private static function get_minimum_targets(string $category): array {
        $defaults = [
            'extra'      => 120,
            'longtail'   => 25,
            'competitor' => 60,
        ];

        if ($category === 'livejasmin') {
            return [
                'extra'      => 120,
                'longtail'   => 40,
                'competitor' => 120,
            ];
        }

        if (in_array($category, ['big-boobs', 'big-butt'], true)) {
            return [
                'extra'      => 80,
                'longtail'   => 40,
                'competitor' => 60,
            ];
        }

        return $defaults;
    }

    /**
     * Get list of platform brands.
     *
     * @return array
     */
    private static function get_platform_brands(): array {
        return [
            'livejasmin', 'chaturbate', 'stripchat', 'bongacams', 'camsoda', 'myfreecams', 'cam4', 'imlive',
            'streamate', 'flirt4free', 'jerkmate', 'cams.com',
        ];
    }

    /**
     * Get the primary brand for a category.
     *
     * @param string $category
     * @return string
     */
    private static function get_primary_brand_for_category(string $category): string {
        if ($category === 'livejasmin') {
            return 'livejasmin';
        }

        return '';
    }

    /**
     * Detect if a keyword mentions a platform brand.
     *
     * @param string $keyword
     * @return string
     */
    private static function find_brand(string $keyword): string {
        $keyword = strtolower($keyword);
        foreach (self::get_platform_brands() as $brand) {
            if (preg_match('/\b' . preg_quote($brand, '/') . '\b/i', $keyword)) {
                return $brand;
            }
        }

        return '';
    }

    /**
     * Handles google suggest client.
     * @return Google_Suggest_Client
     */
    protected static function google_suggest_client(): Google_Suggest_Client {
        if (!self::$google_suggest_client) {
            self::$google_suggest_client = new Google_Suggest_Client();
        }

        return self::$google_suggest_client;
    }

    /**
     * Handles csv columns.
     * @return array
     */
    protected static function csv_columns(): array {
        // Extended keyword metadata columns for KD reporting + audit trail.
        return ['keyword', 'word_count', 'type', 'source_seed', 'category', 'timestamp', 'competition', 'cpc', 'tmw_kd'];
    }

    /**
     * Handles keyword word count.
     *
     * @param string $keyword
     * @return int
     */
    protected static function keyword_word_count(string $keyword): int {
        // Count words in a normalized keyword string.
        $words = preg_split('/\s+/', trim($keyword));
        return is_array($words) ? count(array_filter($words, 'strlen')) : 0;
    }

    /**
     * Handles blacklist.
     * @return array
     */
    public static function blacklist(): array {
        $list = [
            // Existing blacklist items...

            // Hardware/Tech (NOT adult industry)
            'webcam review',
            'webcam for streaming',
            'webcam for gaming',
            'webcam for pc',
            'webcam for laptop',
            'webcam for zoom',
            'webcam settings',
            'webcam driver',
            'webcam software',
            'webcam test',
            'webcam quality',
            'webcam 4k',
            'webcam 1080p',
            'webcam hd',
            'webcam usb',
            'webcam with microphone',
            'webcam light',
            'webcam mount',
            'webcam stand',
            'webcam cover',
            'logitech',
            'razer',
            'elgato',
            'microsoft webcam',
            'camera model canon',
            'camera model nikon',
            'camera model sony',
            'dslr',

            // Security/Surveillance
            'security camera',
            'ip camera',
            'cctv',
            'surveillance',
            'baby monitor',
            'nanny cam',
            'doorbell camera',
            'dash cam',
            'dashcam',
            'body cam',
            'spy camera',
            'hidden camera',
            'trail cam',
            'game camera',
            'wildlife camera',

            // Travel/Tourism cams
            'live cam beach',
            'live cam airport',
            'live cam traffic',
            'live cam weather',
            'live cam city',
            'live cam zoo',
            'live cam aquarium',
            'earthcam',
            'webcam beach',
            'webcam ski',
            'webcam mountain',
            'webcam resort',
            'aruba',
            'hawaii cam',
            'florida cam',
            'times square cam',

            // Entertainment (wrong context)
            'movie cast',
            'film cast',
            'tv show',
            'netflix',
            'actress',
            'actor',
            'celebrity',
            'influencer',
            'youtuber',
            'twitch streamer',
            'gaming stream',

            // Animals
            'camel',
            'animal cam',
            'pet cam',
            'dog cam',
            'cat cam',
            'bird cam',
            'puppy cam',
            'kitten cam',

            // Random video chat (not adult)
            'omegle',
            'chatroulette',
            'chatrandom',
            'camsurf',
            'monkey app',
            'random chat',
            'stranger chat',
            'video call',
            'video conference',

            // Job/Career (wrong context)
            'model agency',
            'modeling agency',
            'fashion model',
            'runway model',
            'model portfolio',
            'model casting',
            'model audition',
            'become a model',
            'modeling tips',
            'model photography',

            // Piracy/Illegal
            'leak',
            'leaked',
            'torrent',
            'download free',
            'hack',
            'cracked',
            'free premium',
            'bypass',
            'free tokens',
            'token generator',
            'token hack',

            // Spam indicators
            'reddit',
            'r/',
            'forum',
            'discord',
            'telegram group',
            'login',
            'sign up free',
            'no credit card',
            'free trial',
        ];

        return apply_filters('tmwseo_keyword_builder_blacklist', $list);
    }

    /**
     * Normalizes keyword.
     *
     * @param string $s
     * @return array
     */
    public static function normalize_keyword(string $s): array {
        $display = wp_strip_all_tags($s);
        $display = str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $display);
        $display = preg_replace('/\s+/', ' ', $display);
        $display = trim((string) $display);
        $display = trim($display, "\"' ");

        $display = preg_replace('#https?://[^\s]+#i', '', $display);
        $display = preg_replace('/\b[\w-]+(?:\.[\w-]+)+(?:\/\S*)?/i', '', $display);

        if (strpos($display, ' | ') !== false) {
            $parts = explode(' | ', $display, 2);
            $display = $parts[0];
        }

        if (strpos($display, ' - ') !== false) {
            [$left, $right] = explode(' - ', $display, 2);
            $brands = ['kinkly', 'semrush', 'reddit', 'enforcity', 'pornhub', 'xvideos', 'xhamster', 'xnxx', 'redtube'];
            foreach ($brands as $brand) {
                if (stripos($right, $brand) !== false) {
                    $display = $left;
                    break;
                }
            }
        }

        $display = rtrim($display, '.!,?:;');
        $display = preg_replace('/([!?.,])\1+/', '$1', $display);
        $display = preg_replace('/\s+/', ' ', $display);
        $display = trim($display);

        $normalized = strtolower($display);

        return [
            'normalized' => $normalized,
            'display'    => $display,
        ];
    }

    /**
     * Checks whether allowed.
     *
     * @param string $normalized
     * @param string $display
     * @return bool
     */
    public static function is_allowed(string $normalized, string $display): bool {
        // Use new validator
        return Keyword_Validator::is_valid_industry_keyword($display);
    }

    /**
     * Checks whether the normalized keyword matches the blacklist.
     *
     * @param string $normalized
     * @return bool
     */
    protected static function is_blacklisted(string $normalized): bool {
        foreach (self::blacklist() as $term) {
            $term = strtolower($term);
            if ($term !== '' && strpos($normalized, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if keyword has adult cam industry intent.
     *
     * @param string $keyword The keyword to check.
     * @return bool True if has adult intent.
     */
    private static function has_adult_intent(string $keyword): bool {
        $keyword_lower = strtolower($keyword);

        $intent_indicators = [
            // Platforms
            'livejasmin', 'chaturbate', 'stripchat', 'bongacams', 'camsoda',
            'myfreecams', 'flirt4free', 'cam4', 'streamate', 'imlive',
            // Core terms
            'cam girl', 'camgirl', 'webcam model', 'cam model', 'webcam girl',
            'live cam', 'cam show', 'webcam show', 'private show', 'cam site',
            'adult cam', 'sex cam', 'nude cam', 'xxx cam', 'porn cam',
            // Partial
            'webcam', ' cam', 'cam ',
        ];

        foreach ($intent_indicators as $indicator) {
            if (strpos($keyword_lower, $indicator) !== false) {
                return true;
            }
        }

        // Check if ends with "cam"
        if (substr($keyword_lower, -3) === 'cam' || substr($keyword_lower, -4) === ' cam') {
            return true;
        }

        return false;
    }

    /**
     * Checks for off-topic patterns like hardware/security webcams.
     *
     * @param string $normalized
     * @return bool
     */
    protected static function matches_offtopic_patterns(string $normalized): bool {
        $underage_terms = ['teen', 'minor', 'underage'];
        foreach ($underage_terms as $term) {
            if (strpos($normalized, $term) !== false) {
                return true;
            }
        }

        $hard_reject_terms = [
            'camera',
            'cctv',
            'security',
            'ip camera',
            'dash cam',
            'baby monitor',
            'doorbell camera',
            'earthcam',
            'traffic',
            'street cameras',
            'home cameras',
            'insecam',
            'zoom',
            'teams',
            'google meet',
            'video conferencing',
            'meeting',
            'driver',
            'software',
            'settings',
            'test',
            'usb',
            '4k',
            'logitech',
            'brio',
        ];

        foreach ($hard_reject_terms as $term) {
            if (strpos($normalized, $term) !== false) {
                return true;
            }
        }

        if (strpos($normalized, 'near me') !== false && preg_match('/\b(camera|webcam|security)\b/', $normalized)) {
            return true;
        }

        if (strpos($normalized, 'webcam') !== false && !preg_match('/\b(model|girl|cam|live cam|site|chat|show)\b/', $normalized)) {
            return true;
        }

        return false;
    }

    /**
     * Handles split types.
     *
     * @param array $keywords
     * @return array
     */
    public static function split_types(array $keywords): array {
        $buckets = [
            'extra'    => [],
            'longtail' => [],
        ];

        foreach ($keywords as $kw) {
            $keyword_text = is_array($kw) ? (string) ($kw['keyword'] ?? '') : (string) $kw;
            $category     = is_array($kw) ? sanitize_key((string) ($kw['category'] ?? '')) : '';
            $words        = preg_split('/\s+/', trim($keyword_text));
            $count        = is_array($words) ? count(array_filter($words, 'strlen')) : 0;
            ['normalized' => $normalized] = self::normalize_keyword($keyword_text);
            if ($normalized === '') {
                continue;
            }

            $type = self::determine_type($keyword_text, $category, $count);
            if ($type === 'competitor') {
                $buckets['competitor'][$normalized] = $kw;
            } elseif ($count >= 2) {
                $buckets[$type][$normalized] = $kw;
            }
        }

        $buckets['extra']      = array_values($buckets['extra']);
        $buckets['longtail']   = array_values($buckets['longtail']);
        $buckets['competitor'] = array_values($buckets['competitor'] ?? []);

        return $buckets;
    }

    /**
     * Handles make indirect seeds.
     *
     * @param string $category
     * @param array $seeds
     * @return array
     */
    protected static function make_indirect_seeds(string $category, array $seeds): array {
        $groups = [];

        foreach ($seeds as $seed) {
            $seed = trim((string) $seed);
            if ($seed === '') {
                continue;
            }

            $indirect = [$seed];
            if (stripos($seed, 'cam model') !== false) {
                $indirect[] = str_ireplace('cam model', 'cam girl', $seed);
                $indirect[] = str_ireplace('cam model', 'live cam', $seed);
            }
            if (stripos($seed, 'cam girl') !== false) {
                $indirect[] = str_ireplace('cam girl', 'cam model', $seed);
                $indirect[] = str_ireplace('cam girl', 'live cam', $seed);
            }
            if (stripos($seed, 'webcam model') !== false) {
                $indirect[] = str_ireplace('webcam model', 'cam model', $seed);
                $indirect[] = str_ireplace('webcam model', 'cam girl', $seed);
            }
            if (stripos($seed, 'live cam') !== false) {
                $indirect[] = str_ireplace('live cam', 'cam girl', $seed);
                $indirect[] = str_ireplace('live cam', 'cam model', $seed);
            }

            $groups[] = [
                'seed' => $seed,
                'indirect' => array_values(array_unique(array_filter(array_map('trim', $indirect), 'strlen'))),
            ];
        }

        return $groups;
    }

    /**
     * Builds efficient queries.
     *
     * @param string $seed
     * @return array
     */
    protected static function build_efficient_queries(string $seed): array {
        $seed = trim($seed);
        if ($seed === '') {
            return [];
        }

        return [$seed];
    }

    /**
     * Handles contextualize keyword.
     *
     * @param string $seed
     * @param string $keyword
     * @return string
     */
    protected static function contextualize_keyword(string $seed, string $keyword): string {
        $seed = trim($seed);
        $keyword = trim($keyword);
        if ($seed === '' || $keyword === '') {
            return $keyword;
        }

        return $keyword;
    }

    /**
     * Generates manual keywords.
     *
     * @param string $seed
     * @return array
     */
    protected static function generate_manual_keywords(string $seed): array {
        $seed = trim($seed);
        if ($seed === '') {
            return [];
        }

        $templates = [
            '%s live cam',
            '%s webcam model',
            '%s cam girl',
            '%s cam model',
            '%s cam chat',
            '%s cam show',
        ];

        if (stripos($seed, 'livejasmin') !== false) {
            $templates[] = '%s livejasmin model';
        }

        $keywords = [];
        foreach ($templates as $template) {
            $keywords[] = sprintf($template, $seed);
        }

        $extensions = ['show', 'chat', 'site', 'girls', 'models', 'near me', 'free'];
        foreach ($extensions as $extension) {
            $keywords[] = sprintf('%s cam girl %s', $seed, $extension);
            $keywords[] = sprintf('%s cam model %s', $seed, $extension);
            $keywords[] = sprintf('%s live cam %s', $seed, $extension);
        }

        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords), 'strlen')));

        while (count($keywords) < 50) {
            $keywords[] = sprintf('%s live cam %d', $seed, count($keywords) + 1);
        }

        return array_slice($keywords, 0, 50);
    }

    /**
     * Handles generate.
     *
     * @param string $category
     * @param array $seeds
     * @param string $gl
     * @param string $hl
     * @param int $per_seed
     * @param array $run_state
     * @return mixed
     */
    public static function generate(string $category, array $seeds, string $gl, string $hl, int $per_seed = 10, ?array &$run_state = null) {
        if (!is_array($run_state)) {
            $run_state = [];
        }

        $category = sanitize_key($category);
        $seeds    = self::get_seeds_for_category($category, $seeds);
        if (empty($seeds)) {
            return [];
        }

        $per_seed = max(1, min(50, $per_seed));
        $gl       = sanitize_text_field($gl ?: 'us');
        $hl       = sanitize_text_field($hl ?: 'en');
        $target   = max($per_seed * max(1, count($seeds)), 50);

        $all_keywords    = [];
        $validated_count = 0;

        // SOURCE 1: Internal pattern expansion (trusted but validated)
        $expanded = Keyword_Expander::expand_category($seeds, $category, 20);
        foreach ($expanded as $keyword) {
            if (self::is_trusted_expansion($keyword)) {
                $all_keywords[] = self::build_keyword_entry($keyword, $category, 'internal');
                $validated_count++;
            }
            // NO early break here - let other sources contribute
        }

        // SOURCE 2: Google Autocomplete (bonus)
        if ($validated_count < $target) {
            $remaining = $target - $validated_count;
            $autocomplete_keywords = self::fetch_autocomplete_keywords($seeds, $remaining, $hl, $gl);

            foreach ($autocomplete_keywords as $keyword) {
                if ($validated_count >= $target) {
                    break;
                }

                if (Keyword_Validator::is_valid_industry_keyword($keyword) && !self::keyword_exists($keyword, $all_keywords)) {
                    $all_keywords[] = self::build_keyword_entry($keyword, $category, 'autocomplete');
                    $validated_count++;
                }
            }
        }

        // SOURCE 3: Category modifier combinations (fallback)
        if ($validated_count < $target) {
            $modifiers  = Keyword_Expander::get_category_modifiers($category);
            $base_terms = ['cam girl', 'webcam model', 'live cam', 'cam show'];

            foreach ($modifiers as $modifier) {
                foreach ($base_terms as $base) {
                    if ($validated_count >= $target) {
                        break 2;
                    }

                    $keyword = $modifier . ' ' . $base;
                    if (!self::keyword_exists($keyword, $all_keywords)) {
                        $all_keywords[] = self::build_keyword_entry($keyword, $category, 'modifier');
                        $validated_count++;
                    }
                }
            }
        }

        $all_keywords = self::deduplicate_keywords($all_keywords);

        // PHASE 4: GUARANTEED FALLBACK - If still under target, use hardcoded keywords
        $effective_target = max($target, 20); // Ensure at least 20 keywords
        if (count($all_keywords) < $effective_target) {
            $fallbacks = Keyword_Expander::get_fallback_keywords($category);
            foreach ($fallbacks as $keyword) {
                if (count($all_keywords) >= $effective_target) break;
                if (!self::keyword_exists($keyword, $all_keywords)) {
                    // Fallback keywords are pre-validated, but still check blacklist
                    if (!Keyword_Validator::is_blacklisted($keyword)) {
                        $all_keywords[] = self::build_keyword_entry($keyword, $category, 'fallback');
                    }
                }
            }
        }

        // Log warning if still under effective target
        if (count($all_keywords) < $effective_target) {
            error_log("TMW SEO WARNING: Category '{$category}' has only " . count($all_keywords) . " keywords after all sources (target: {$effective_target}).");
        }

        $all_keywords = self::deduplicate_keywords($all_keywords);
        $all_keywords = array_slice($all_keywords, 0, $effective_target);

        $run_state['quality_report'] = [
            'accepted' => [
                'count'   => count($all_keywords),
                'samples' => array_slice(array_column($all_keywords, 'keyword'), 0, 10),
            ],
        ];

        return self::split_types($all_keywords);
    }

    /**
     * Merge curated and provided seeds for a category.
     *
     * @param string $category Category key.
     * @param array  $seeds    Provided seeds.
     * @return array Combined seeds.
     */
    protected static function get_seeds_for_category(string $category, array $seeds): array {
        $curated_file = TMW_SEO_PATH . 'data/curated-seeds.php';
        $curated      = file_exists($curated_file) ? (array) require $curated_file : [];
        $category_seeds = $curated[$category] ?? [];

        $merged = array_merge($seeds, (array) $category_seeds);

        return array_values(array_unique(array_filter(array_map(function ($seed) {
            return trim(sanitize_text_field((string) $seed));
        }, $merged), 'strlen')));
    }

    /**
     * Fetch Google autocomplete keywords for a set of seeds.
     *
     * @param array  $seeds   Seeds to query.
     * @param int    $limit   Maximum keywords to return.
     * @param string $hl      Language code.
     * @param string $gl      Geo code.
     * @return array Keyword suggestions.
     */
    private static function fetch_autocomplete_keywords(array $seeds, int $limit, string $hl, string $gl): array {
        $client = self::google_suggest_client();
        $results = [];
        $seen = [];
        $per_seed = max(1, (int) ceil($limit / max(1, count($seeds))));

        foreach ($seeds as $seed) {
            $suggestions = $client->fetch($seed, $hl, $gl);
            if (is_wp_error($suggestions)) {
                continue;
            }

            foreach (array_slice((array) $suggestions, 0, $per_seed) as $suggestion) {
                $normalized = trim((string) $suggestion);
                if ($normalized === '') {
                    continue;
                }

                $key = strtolower($normalized);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $results[] = $normalized;
                }

                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }

    /**
     * Check if keyword is a trusted internal expansion.
     * Requires at least one industry indicator OR adult intent.
     *
     * @param string $keyword The keyword to check.
     * @return bool True if trusted.
     */
    private static function is_trusted_expansion(string $keyword): bool {
        // Must not be blacklisted
        if (Keyword_Validator::is_blacklisted($keyword)) {
            return false;
        }

        $keyword_lower = strtolower($keyword);

        // Check for platform names (high trust)
        $platforms = [
            'livejasmin', 'chaturbate', 'stripchat', 'bongacams',
            'camsoda', 'myfreecams', 'flirt4free', 'cam4',
        ];
        foreach ($platforms as $platform) {
            if (strpos($keyword_lower, $platform) !== false) {
                return true;
            }
        }

        // Check for core industry terms (medium trust)
        $industry_terms = [
            'cam girl', 'camgirl', 'cam model', 'webcam model',
            'webcam girl', 'live cam', 'cam show', 'webcam show',
            'private show', 'cam site', 'adult cam', 'sex cam',
        ];
        foreach ($industry_terms as $term) {
            if (strpos($keyword_lower, $term) !== false) {
                return true;
            }
        }

        // Check for partial matches (low trust but acceptable)
        $partial_indicators = [
            'webcam', 'camgirl', 'livecam', ' cam ', 'cam ',
        ];
        foreach ($partial_indicators as $indicator) {
            if (strpos($keyword_lower, $indicator) !== false) {
                return true;
            }
        }
        // Also accept if ends with " cam"
        if (substr($keyword_lower, -4) === ' cam') {
            return true;
        }

        return false;
    }

    /**
     * Build a keyword entry array.
     *
     * @param string $keyword  The keyword.
     * @param string $category The category.
     * @param string $source   The source (internal/autocomplete/modifier).
     * @return array Keyword entry.
     */
    private static function build_keyword_entry(string $keyword, string $category, string $source): array {
        return [
            'keyword'  => $keyword,
            'category' => $category,
            'source'   => $source,
            'tmw_kd'   => self::estimate_difficulty($keyword),
        ];
    }

    /**
     * Check if keyword already exists in array.
     *
     * @param string $keyword  Keyword to check.
     * @param array  $keywords Existing keywords.
     * @return bool True if exists.
     */
    private static function keyword_exists(string $keyword, array $keywords): bool {
        $keyword_lower = strtolower($keyword);
        foreach ($keywords as $kw) {
            if (strtolower($kw['keyword'] ?? '') === $keyword_lower) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove duplicate keywords.
     *
     * @param array $keywords Keywords array.
     * @return array Deduplicated array.
     */
    private static function deduplicate_keywords(array $keywords): array {
        $seen = [];
        $unique = [];

        foreach ($keywords as $kw) {
            $key = strtolower($kw['keyword'] ?? '');
            if ($key && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $kw;
            }
        }

        return $unique;
    }

    /**
     * Estimate keyword difficulty (placeholder - integrate with KD API later).
     *
     * @param string $keyword The keyword.
     * @return int Estimated KD 0-100.
     */
    private static function estimate_difficulty(string $keyword): int {
        $word_count = str_word_count($keyword);

        $hash = crc32($keyword);
        $variation = abs($hash % 15); // 0-14 variation

        if ($word_count >= 5) return 10 + $variation;      // 10-24
        if ($word_count >= 4) return 20 + $variation;      // 20-34
        if ($word_count >= 3) return 30 + $variation;      // 30-44
        if ($word_count >= 2) return 40 + $variation;      // 40-54

        return 55 + $variation; // 55-69
    }

    /**
     * Ensure minimum coverage for autocomplete packs using internal expansions.
     *
     * @param string $category
     * @param array  $rows_by_type
     * @param array  $keyword_map
     * @param int    $initial_total
     * @return void
     */
    private static function ensure_minimum_google_rows(string $category, array &$rows_by_type, array &$keyword_map, int $initial_total): void {
        $targets = [
            'extra'      => 25,
            'longtail'   => 15,
            'competitor' => 5,
        ];

        $needs_fallback = false;
        foreach ($targets as $type => $min) {
            if (count($rows_by_type[$type] ?? []) < $min) {
                $needs_fallback = true;
                break;
            }
        }

        if (!$needs_fallback) {
            return;
        }

        $seeds     = self::get_seeds_for_category($category, []);
        $internal  = Keyword_Expander::expand_category($seeds, $category, 200);
        $fallbacks = Keyword_Expander::get_fallback_keywords($category);
        $pool      = array_merge($internal, $fallbacks);

        foreach ($pool as $kw) {
            ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword((string) $kw);
            if ($display === '' || Keyword_Validator::is_blacklisted($display)) {
                continue;
            }

            if ($normalized !== '' && isset($keyword_map[$normalized])) {
                continue;
            }

            $word_count = self::keyword_word_count($display);
            $type       = self::determine_type($display, $category, $word_count);

            $row = [
                'keyword'     => $display,
                'word_count'  => $word_count,
                'type'        => $type,
                'source_seed' => '',
                'category'    => $category,
                'timestamp'   => current_time('mysql'),
                'competition' => Keyword_Difficulty_Proxy::DEFAULT_COMPETITION,
                'cpc'         => Keyword_Difficulty_Proxy::DEFAULT_CPC,
                'tmw_kd'      => Keyword_Difficulty_Proxy::score($display, Keyword_Difficulty_Proxy::DEFAULT_COMPETITION, Keyword_Difficulty_Proxy::DEFAULT_CPC),
            ];

            if (!isset($rows_by_type[$type])) {
                $rows_by_type[$type] = [];
            }
            $rows_by_type[$type][] = $row;
            if ($normalized !== '') {
                $keyword_map[$normalized] = $row;
            }

            $all_met = true;
            foreach ($targets as $t => $min) {
                if (count($rows_by_type[$t] ?? []) < $min && $t !== 'competitor') {
                    $all_met = false;
                    break;
                }
            }
            if ($all_met && count($rows_by_type['competitor'] ?? []) >= $targets['competitor']) {
                break;
            }
        }

        Core::debug_log(sprintf('[TMW-KEYPACKS] %s: Google suggestions too low (%d). Filled with internal+fallback to reach targets.', $category, $initial_total));
        if (count($rows_by_type['competitor'] ?? []) < $targets['competitor']) {
            Core::debug_log(sprintf('[TMW-KEYPACKS] %s: competitor keywords still below target (%d/%d).', $category, count($rows_by_type['competitor'] ?? []), $targets['competitor']));
        }
    }

    /**
     * Ensure minimum targets for each type using internal expansion and fallbacks.
     *
     * @param string $category
     * @param array  $seeds
     * @param array  $rows_by_type
     * @param array  $keyword_map
     * @return void
     */
    private static function ensure_minimum_type_targets(string $category, array $seeds, array &$rows_by_type, array &$keyword_map): void {
        $targets = self::get_minimum_targets($category);
        $current = [
            'extra'      => count($rows_by_type['extra'] ?? []),
            'longtail'   => count($rows_by_type['longtail'] ?? []),
            'competitor' => count($rows_by_type['competitor'] ?? []),
        ];

        $needs_more = false;
        foreach ($targets as $type => $min) {
            if ($current[$type] < $min) {
                $needs_more = true;
                break;
            }
        }

        if (!$needs_more) {
            return;
        }

        if ($category === 'livejasmin' && !in_array('livejasmin', array_map('strtolower', $seeds), true)) {
            $seeds[] = 'livejasmin';
        }

        $seeds     = array_values(array_unique(array_filter(array_map('trim', $seeds), 'strlen')));
        $internal  = Keyword_Expander::expand_category($seeds, $category, 1200);
        $fallbacks = Keyword_Expander::get_fallback_keywords($category);
        $pool      = array_merge($internal, $fallbacks);

        foreach ($pool as $kw) {
            ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword((string) $kw);
            if ($display === '' || Keyword_Validator::is_blacklisted($display)) {
                continue;
            }

            if ($normalized !== '' && isset($keyword_map[$normalized])) {
                continue;
            }

            if (!self::is_allowed($normalized, $display)) {
                continue;
            }

            $word_count = self::keyword_word_count($display);
            $type       = self::determine_type($display, $category, $word_count);

            if ($current[$type] >= $targets[$type]) {
                continue;
            }

            $row = [
                'keyword'     => $display,
                'word_count'  => $word_count,
                'type'        => $type,
                'source_seed' => '',
                'category'    => $category,
                'timestamp'   => current_time('mysql'),
                'competition' => Keyword_Difficulty_Proxy::DEFAULT_COMPETITION,
                'cpc'         => Keyword_Difficulty_Proxy::DEFAULT_CPC,
                'tmw_kd'      => Keyword_Difficulty_Proxy::score($display, Keyword_Difficulty_Proxy::DEFAULT_COMPETITION, Keyword_Difficulty_Proxy::DEFAULT_CPC),
            ];

            $rows_by_type[$type][] = $row;
            if ($normalized !== '') {
                $keyword_map[$normalized] = $row;
            }
            $current[$type]++;

            $all_met = true;
            foreach ($targets as $target_type => $min) {
                if ($current[$target_type] < $min) {
                    $all_met = false;
                    break;
                }
            }

            if ($all_met) {
                break;
            }
        }
    }

    /**
     * Handles merge write csv.
     *
     * @param string $category
     * @param string $type
     * @param array $keywords
     * @param bool $append
     * @param bool $flush_cache
     * @return int
     */
    public static function merge_write_csv(string $category, string $type, array $keywords, bool $append = false, bool $flush_cache = true): int {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);

        $uploads = wp_upload_dir();
        $base    = trailingslashit($uploads['basedir']) . 'tmwseo-keywords';
        $dir     = trailingslashit($base) . $category;

        wp_mkdir_p($dir);

        $path = trailingslashit($dir) . "{$type}.csv";

        $existing        = [];
        $existing_before = [];
        if (file_exists($path)) {
            $fh = fopen($path, 'r');
            if ($fh) {
                $header_map = [];
                $first_row  = fgetcsv($fh);
                $data_rows  = [];

                // Detect headers so we can preserve competition/CPC/KD metadata when merging.
                if ($first_row !== false && is_array($first_row)) {
                    $normalized_header = array_map(function ($col) {
                        return strtolower(trim((string) $col));
                    }, $first_row);
                    $normalized_header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($normalized_header[0] ?? ''));

                    foreach ($normalized_header as $index => $col) {
                        if ($col === 'keyword' || $col === 'phrase') {
                            $header_map['keyword'] = $index;
                        }
                        if ($col === 'word_count' || $col === 'wordcount') {
                            $header_map['word_count'] = $index;
                        }
                        if ($col === 'type') {
                            $header_map['type'] = $index;
                        }
                        if ($col === 'source_seed' || $col === 'seed') {
                            $header_map['source_seed'] = $index;
                        }
                        if ($col === 'category') {
                            $header_map['category'] = $index;
                        }
                        if ($col === 'timestamp' || $col === 'created_at') {
                            $header_map['timestamp'] = $index;
                        }
                        if ($col === 'competition') {
                            $header_map['competition'] = $index;
                        }
                        if ($col === 'cpc') {
                            $header_map['cpc'] = $index;
                        }
                        if ($col === 'tmw_kd' || $col === 'tmw_kd%') {
                            $header_map['tmw_kd'] = $index;
                        }
                    }

                    if (empty($header_map)) {
                        $data_rows = [$first_row];
                    }
                }

                while (($row = fgetcsv($fh)) !== false) {
                    $data_rows[] = $row;
                }
                fclose($fh);

                foreach ($data_rows as $row) {
                    $keyword_index = $header_map['keyword'] ?? 0;
                    $raw_value     = isset($row[$keyword_index]) ? (string) $row[$keyword_index] : '';
                    ['normalized' => $value, 'display' => $display] = self::normalize_keyword($raw_value);
                    if ($value === '') {
                        continue;
                    }

                    $word_count = isset($header_map['word_count'], $row[$header_map['word_count']])
                        ? (int) $row[$header_map['word_count']]
                        : self::keyword_word_count($display);
                    $row_type = isset($header_map['type'], $row[$header_map['type']])
                        ? sanitize_key((string) $row[$header_map['type']])
                        : $type;
                    $source_seed = isset($header_map['source_seed'], $row[$header_map['source_seed']])
                        ? (string) $row[$header_map['source_seed']]
                        : '';
                    $row_category = isset($header_map['category'], $row[$header_map['category']])
                        ? sanitize_key((string) $row[$header_map['category']])
                        : $category;
                    $timestamp = isset($header_map['timestamp'], $row[$header_map['timestamp']])
                        ? (string) $row[$header_map['timestamp']]
                        : '';

                    $competition = isset($header_map['competition'], $row[$header_map['competition']])
                        ? $row[$header_map['competition']]
                        : Keyword_Difficulty_Proxy::DEFAULT_COMPETITION;
                    $cpc = isset($header_map['cpc'], $row[$header_map['cpc']])
                        ? $row[$header_map['cpc']]
                        : Keyword_Difficulty_Proxy::DEFAULT_CPC;
                    $tmw_kd_raw = isset($header_map['tmw_kd'], $row[$header_map['tmw_kd']])
                        ? $row[$header_map['tmw_kd']]
                        : '';

                    $competition = Keyword_Difficulty_Proxy::normalize_competition($competition);
                    $cpc         = Keyword_Difficulty_Proxy::normalize_cpc($cpc);
                    $tmw_kd      = $tmw_kd_raw !== '' ? (int) round((float) $tmw_kd_raw) : Keyword_Difficulty_Proxy::score($display, $competition, $cpc);
                    $tmw_kd      = max(0, min(100, $tmw_kd));

                    $existing[$value] = [
                        'keyword'     => $display,
                        'word_count'  => $word_count,
                        'type'        => $row_type ?: $type,
                        'source_seed' => $source_seed,
                        'category'    => $row_category ?: $category,
                        'timestamp'   => $timestamp,
                        'competition' => $competition,
                        'cpc'         => $cpc,
                        'tmw_kd'      => $tmw_kd,
                    ];
                    $existing_before[$value] = $existing[$value];
                }
            }
        }

        if (empty($keywords) && !empty($existing)) {
            return count($existing);
        }

        $existing_count = count($existing);
        if (empty($keywords) && $existing_count > 0 && !$append) {
            return $existing_count;
        }

        // Merge new keywords with defaults for competition/CPC and extended metadata.
        foreach ($keywords as $kw) {
            $raw_keyword = '';
            $competition = Keyword_Difficulty_Proxy::DEFAULT_COMPETITION;
            $cpc         = Keyword_Difficulty_Proxy::DEFAULT_CPC;
            $word_count  = null;
            $row_type    = $type;
            $source_seed = '';
            $row_category = $category;
            $timestamp   = '';

            if (is_array($kw)) {
                $raw_keyword = (string) ($kw['keyword'] ?? $kw['phrase'] ?? '');
                $word_count  = isset($kw['word_count']) ? (int) $kw['word_count'] : null;
                $row_type    = sanitize_key((string) ($kw['type'] ?? $row_type));
                $source_seed = (string) ($kw['source_seed'] ?? $source_seed);
                $row_category = sanitize_key((string) ($kw['category'] ?? $row_category));
                $timestamp   = (string) ($kw['timestamp'] ?? $timestamp);
                if (isset($kw['competition'])) {
                    $competition = $kw['competition'];
                }
                if (isset($kw['cpc'])) {
                    $cpc = $kw['cpc'];
                }
            } else {
                $raw_keyword = (string) $kw;
            }

            ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword($raw_keyword);
            $source_key = is_array($kw) ? sanitize_key((string) ($kw['source'] ?? '')) : '';
            $is_trusted_internal = in_array($source_key, ['internal', 'modifier', 'fallback'], true);
            $is_allowed_keyword = $normalized !== ''
                && (
                    $is_trusted_internal
                        ? (!Keyword_Validator::is_blacklisted($display) && self::has_adult_intent($normalized))
                        : self::is_allowed($normalized, $display)
                );

            if ($is_allowed_keyword) {
                if (!isset($existing[$normalized])) {
                    $competition = Keyword_Difficulty_Proxy::normalize_competition($competition);
                    $cpc         = Keyword_Difficulty_Proxy::normalize_cpc($cpc);
                    $word_count  = $word_count ?? self::keyword_word_count($display);
                    $row_type    = $row_type ?: $type;
                    $row_category = $row_category ?: $category;
                    $timestamp   = $timestamp !== '' ? $timestamp : current_time('mysql');
                    $existing[$normalized] = [
                        'keyword'     => $display,
                        'word_count'  => $word_count,
                        'type'        => $row_type,
                        'source_seed' => $source_seed,
                        'category'    => $row_category,
                        'timestamp'   => $timestamp,
                        'competition' => $competition,
                        'cpc'         => $cpc,
                        'tmw_kd'      => Keyword_Difficulty_Proxy::score($display, $competition, $cpc),
                    ];
                }
            }
        }

        $final = array_values($existing);
        usort($final, function ($a, $b) {
            return strcasecmp($a['keyword'], $b['keyword']);
        });

        $new_only = array_diff_key($existing, $existing_before);

        $fh = fopen($path, $append ? 'a' : 'w');
        if ($fh) {
            if (!$append) {
                fputcsv($fh, self::csv_columns());
                foreach ($final as $row) {
                    $row = array_merge(
                        [
                            'word_count'  => self::keyword_word_count($row['keyword']),
                            'type'        => $type,
                            'source_seed' => '',
                            'category'    => $category,
                            'timestamp'   => current_time('mysql'),
                        ],
                        $row
                    );
                    fputcsv($fh, [
                        $row['keyword'],
                        (int) $row['word_count'],
                        $row['type'],
                        $row['source_seed'],
                        $row['category'],
                        $row['timestamp'],
                        $row['competition'],
                        number_format((float) $row['cpc'], 2, '.', ''),
                        (int) $row['tmw_kd'],
                    ]);
                }
            } else {
                foreach ($new_only as $row) {
                    $row = array_merge(
                        [
                            'word_count'  => self::keyword_word_count($row['keyword']),
                            'type'        => $type,
                            'source_seed' => '',
                            'category'    => $category,
                            'timestamp'   => current_time('mysql'),
                        ],
                        $row
                    );
                    fputcsv($fh, [
                        $row['keyword'],
                        (int) $row['word_count'],
                        $row['type'],
                        $row['source_seed'],
                        $row['category'],
                        $row['timestamp'],
                        $row['competition'],
                        number_format((float) $row['cpc'], 2, '.', ''),
                        (int) $row['tmw_kd'],
                    ]);
                }
            }
            fclose($fh);
        }

        if ($flush_cache) {
            Keyword_Library::flush_cache();
        }

        return count($final);
    }

    /**
     * Handles import keyword planner csv.
     *
     * @param string $category
     * @param string $type
     * @param string $file_path
     * @return array
     */
    public static function import_keyword_planner_csv(string $category, string $type, string $file_path): array {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);

        // Parse Keyword Planner CSV and map competition/CPC fields for KD scoring.
        $result = [
            'imported' => 0,
            'skipped'  => 0,
            'total'    => 0,
        ];

        if (!is_readable($file_path)) {
            return $result;
        }

        $fh = fopen($file_path, 'r');
        if (!$fh) {
            return $result;
        }

        $header = fgetcsv($fh);
        if (!$header || !is_array($header)) {
            fclose($fh);
            return $result;
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $normalized = array_map(function ($col) {
            return strtolower(trim((string) $col));
        }, $header);

        $keyword_index = null;
        $competition_index = null;
        $bid_index = null;
        $bid_high_index = null;
        $bid_low_index = null;

        foreach ($normalized as $index => $col) {
            if ($keyword_index === null && strpos($col, 'keyword') !== false) {
                $keyword_index = $index;
            }
            if ($competition_index === null && $col === 'competition') {
                $competition_index = $index;
            }
            if (strpos($col, 'top of page bid') !== false) {
                if (strpos($col, 'high') !== false) {
                    $bid_high_index = $index;
                } elseif (strpos($col, 'low') !== false) {
                    $bid_low_index = $index;
                } else {
                    $bid_index = $index;
                }
            }
        }

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            $result['total']++;

            $raw_keyword = $keyword_index !== null ? (string) ($row[$keyword_index] ?? '') : '';
            if ($raw_keyword === '') {
                $result['skipped']++;
                continue;
            }

            $competition = $competition_index !== null ? (string) ($row[$competition_index] ?? '') : Keyword_Difficulty_Proxy::DEFAULT_COMPETITION;
            $competition = Keyword_Difficulty_Proxy::normalize_competition($competition);

            $bid_values = [];
            if ($bid_high_index !== null) {
                $bid_values[] = $row[$bid_high_index] ?? '';
            }
            if ($bid_low_index !== null) {
                $bid_values[] = $row[$bid_low_index] ?? '';
            }
            if ($bid_index !== null && empty($bid_values)) {
                $bid_values[] = $row[$bid_index] ?? '';
            }

            $cpc = Keyword_Difficulty_Proxy::DEFAULT_CPC;
            if (!empty($bid_values)) {
                $numeric = [];
                foreach ($bid_values as $bid_value) {
                    $clean = preg_replace('/[^0-9.\-]/', '', (string) $bid_value);
                    if ($clean !== '') {
                        $numeric[] = (float) $clean;
                    }
                }
                if (!empty($numeric)) {
                    $cpc = array_sum($numeric) / count($numeric);
                }
            }

            $rows[] = [
                'keyword'     => $raw_keyword,
                'competition' => $competition,
                'cpc'         => $cpc,
            ];
        }

        fclose($fh);

        $before_count = count(Keyword_Library::load($category, $type));
        $after_count  = self::merge_write_csv($category, $type, $rows, false, false);
        $result['imported'] = max(0, $after_count - $before_count);
        $result['skipped']  = max(0, $result['total'] - $result['imported']);

        Core::debug_log(sprintf(
            '[TMW-KD-IMPORT] Planner CSV import: %d total, %d imported, %d skipped (%s/%s).',
            (int) $result['total'],
            (int) $result['imported'],
            (int) $result['skipped'],
            $category,
            $type
        ));

        return $result;
    }
    
    protected static function fetch_google_suggestions(
        string $category,
        string $query,
        string $hl,
        string $gl,
        int $limit,
        int $rate_limit_ms,
        float &$last_call,
        array &$errors,
        int $max_retries = 3,
        string $accept_language = 'en-US,en;q=0.9'
    ): array {
        // Enforce a 150-300ms rate limit between outbound Google Suggest calls.
        $client = self::google_suggest_client();

        $attempt = 0;
        $backoff = [0.5, 1.0, 2.0];
        while ($attempt <= $max_retries) {
            if ($rate_limit_ms > 0 && $last_call > 0) {
                $elapsed = (microtime(true) - $last_call) * 1000;
                if ($elapsed < $rate_limit_ms) {
                    usleep((int) (($rate_limit_ms - $elapsed) * 1000));
                }
            }

            $result = $client->fetch(
                $query,
                $hl,
                $gl,
                [
                    'accept_language' => $accept_language,
                    'timeout'         => 18,
                ]
            );

            if (!$client->was_last_cached()) {
                $last_call = microtime(true);
            }

            if (!is_wp_error($result)) {
                return array_slice((array) $result, 0, $limit);
            }

            $data = $result->get_error_data();
            $http_code = is_array($data) ? (int) ($data['http_code'] ?? 0) : 0;
            $message = $result->get_error_message();
            $snippet = is_array($data) ? (string) ($data['body_snippet'] ?? '') : '';
            $url_hint = is_array($data) ? (string) ($data['url'] ?? '') : '';

            Core::debug_log(sprintf(
                '[TMW-KEYPACKS] Google Suggest error (%s/%s): %s (HTTP %d) %s %s',
                $category,
                $query,
                $message,
                $http_code,
                $url_hint,
                $snippet ? 'Snippet: ' . $snippet : ''
            ));

            if (in_array($http_code, [429, 503], true) && $attempt < $max_retries) {
                $delay = $backoff[min($attempt, count($backoff) - 1)];
                usleep((int) ($delay * 1000000));
                $attempt++;
                continue;
            }

            $snippet = $snippet !== '' ? wp_strip_all_tags($snippet) : '';
            if (in_array($http_code, [429, 503], true)) {
                $errors[] = [
                    'category'  => $category,
                    'query'     => $query,
                    'message'   => sprintf('Google rate limited (HTTP %d). Please try again later.', $http_code),
                    'http_code' => $http_code,
                    'url'       => $url_hint,
                    'snippet'   => $snippet,
                ];
            } else {
                $errors[] = [
                    'category'  => $category,
                    'query'     => $query,
                    'message'   => $message,
                    'http_code' => $http_code,
                    'url'       => $url_hint,
                    'snippet'   => $snippet,
                ];
            }
            return [];
        }

        return [];
    }

    /**
     * Handles autofill google autocomplete.
     *
     * @param array $categories
     * @param bool $dry_run
     * @param array $options
     * @return array
     */
    public static function autofill_google_autocomplete(array $categories, bool $dry_run = false, array $options = []): array {
        // Load seed phrases for all categories from the data file.
        $seeds_file = TMW_SEO_PATH . 'data/google-autocomplete-seeds.php';
        $all_seeds = file_exists($seeds_file) ? require $seeds_file : [];
        if (!is_array($all_seeds)) {
            return [
                'categories'     => [],
                'total_keywords' => 0,
                'errors'         => ['Seed file missing or invalid.'],
            ];
        }

        $gl = sanitize_text_field((string) ($options['gl'] ?? get_option('tmwseo_serper_gl', 'us')));
        $hl = sanitize_text_field((string) ($options['hl'] ?? get_option('tmwseo_serper_hl', 'en')));
        // Keep Google autocomplete suggestions to the requested 8-10 range.
        $per_seed = (int) ($options['per_seed'] ?? 10);
        $per_seed = max(8, min(10, $per_seed));
        $rate_limit_ms = (int) ($options['rate_limit_ms'] ?? 200);
        $accept_language = sanitize_text_field((string) ($options['accept_language'] ?? 'en-US,en;q=0.9'));

        $priority = ['extra' => 1, 'longtail' => 2, 'competitor' => 3];

        $summary = [
            'categories'     => [],
            'total_keywords' => 0,
            'errors'         => [],
        ];

        $last_call = 0.0;

        foreach ($categories as $category) {
            $category = sanitize_key($category);
            $seeds = $all_seeds[$category] ?? [];
            $seeds = array_values(array_unique(array_filter(array_map('trim', (array) $seeds), 'strlen')));
            if (empty($seeds)) {
                $summary['errors'][] = sprintf('%s: no seeds found.', $category);
                continue;
            }

            $keyword_map = [];
            foreach ($seeds as $seed) {
                $seed = sanitize_text_field($seed);
                if ($seed === '') {
                    continue;
                }

                // Pull suggestions from Google Autocomplete (with retry on 429s).
                $suggestions = self::fetch_google_suggestions($category, $seed, $hl, $gl, $per_seed, $rate_limit_ms, $last_call, $summary['errors'], 3, $accept_language);
                foreach ($suggestions as $suggestion) {
                    ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword((string) $suggestion);
                    if ($normalized === '' || !self::is_allowed($normalized, $display)) {
                        continue;
                    }

                    $word_count = self::keyword_word_count($display);
                    // Skip overly broad one-word phrases.
                    if ($word_count <= 1) {
                        continue;
                    }

                    $type = self::determine_type($display, $category, $word_count);

                    // Build full CSV row with KD proxy metadata.
                    $row = [
                        'keyword'     => $display,
                        'word_count'  => $word_count,
                        'type'        => $type,
                        'source_seed' => $seed,
                        'category'    => $category,
                        'timestamp'   => current_time('mysql'),
                        'competition' => Keyword_Difficulty_Proxy::DEFAULT_COMPETITION,
                        'cpc'         => Keyword_Difficulty_Proxy::DEFAULT_CPC,
                        'tmw_kd'      => Keyword_Difficulty_Proxy::score($display, Keyword_Difficulty_Proxy::DEFAULT_COMPETITION, Keyword_Difficulty_Proxy::DEFAULT_CPC),
                    ];

                    if (!isset($keyword_map[$normalized]) || $priority[$type] > $priority[$keyword_map[$normalized]['type']]) {
                        $keyword_map[$normalized] = $row;
                    }
                }
            }

            $rows_by_type = [
                'extra'      => [],
                'longtail'   => [],
                'competitor' => [],
            ];
            foreach ($keyword_map as $row) {
                $rows_by_type[$row['type']][] = $row;
            }

            $found_count = count($keyword_map);
            $initial_total = $found_count;
            self::ensure_minimum_google_rows($category, $rows_by_type, $keyword_map, $initial_total);
            self::ensure_minimum_type_targets($category, $seeds, $rows_by_type, $keyword_map);
            $found_count = count($keyword_map);
            $summary['total_keywords'] += $found_count;

            $category_entry = [
                'category' => $category,
                'found'    => $found_count,
                'counts'   => [
                    'extra'      => count($rows_by_type['extra']),
                    'longtail'   => count($rows_by_type['longtail']),
                    'competitor' => count($rows_by_type['competitor']),
                ],
            ];

            if (!$dry_run) {
                $new_counts = [];
                foreach ($rows_by_type as $type => $rows) {
                    if (!empty($rows)) {
                        $before = count(Keyword_Library::load($category, $type));
                        $after = self::merge_write_csv($category, $type, $rows, false, false);
                        $new_counts[$type] = max(0, $after - $before);
                    } else {
                        $new_counts[$type] = 0;
                    }
                }
                $category_entry['new_counts'] = $new_counts;
                Core::debug_log(sprintf('[TMW-AUTOFILL] %s: %d keywords processed.', $category, $found_count));
            } else {
                Core::debug_log(sprintf('[TMW-AUTOFILL] %s: %d keywords previewed.', $category, $found_count));
            }

            $summary['categories'][] = $category_entry;
        }

        return $summary;
    }

    /**
     * Handles autofill google autocomplete batch.
     *
     * @param array $categories
     * @param array $cursor
     * @param bool $dry_run
     * @param array $options
     * @return array
     */
    public static function autofill_google_autocomplete_batch(array $categories, array $cursor, bool $dry_run = false, array $options = []): array {
        $seeds_file = TMW_SEO_PATH . 'data/google-autocomplete-seeds.php';
        $all_seeds = file_exists($seeds_file) ? require $seeds_file : [];
        if (!is_array($all_seeds)) {
            return [
                'done'               => true,
                'cursor'             => $cursor,
                'categories'         => [],
                'batch_keywords'     => 0,
                'errors'             => ['Seed file missing or invalid.'],
                'completed_categories' => 0,
            ];
        }

        $gl = sanitize_text_field((string) ($options['gl'] ?? get_option('tmwseo_serper_gl', 'us')));
        $hl = sanitize_text_field((string) ($options['hl'] ?? get_option('tmwseo_serper_hl', 'en')));
        $per_seed = (int) ($options['per_seed'] ?? 10);
        $per_seed = max(8, min(10, $per_seed));
        $rate_limit_ms = (int) ($options['rate_limit_ms'] ?? 200);
        $accept_language = sanitize_text_field((string) ($options['accept_language'] ?? 'en-US,en;q=0.9'));
        $seed_batch_size = max(1, min(5, (int) ($options['seed_batch_size'] ?? 2)));

        $category_index = max(0, (int) ($cursor['category_index'] ?? 0));
        $seed_offset = max(0, (int) ($cursor['seed_offset'] ?? 0));
        $total_categories = count($categories);

        if ($category_index >= $total_categories) {
            return [
                'done'                => true,
                'cursor'              => $cursor,
                'categories'          => [],
                'batch_keywords'      => 0,
                'errors'              => [],
                'completed_categories' => $total_categories,
            ];
        }

        $category = sanitize_key($categories[$category_index]);
        $seeds = $all_seeds[$category] ?? [];
        $seeds = array_values(array_unique(array_filter(array_map('trim', (array) $seeds), 'strlen')));

        $summary = [
            'done'                => false,
            'cursor'              => $cursor,
            'categories'          => [],
            'batch_keywords'      => 0,
            'errors'              => [],
            'completed_categories' => $category_index,
        ];

        if (empty($seeds)) {
            $summary['errors'][] = sprintf('%s: no seeds found.', $category);
            $summary['categories'][] = [
                'category'       => $category,
                'found'          => 0,
                'seed_offset'    => $seed_offset,
                'seed_total'     => 0,
                'category_done'  => true,
            ];
            $summary['cursor'] = [
                'category_index' => $category_index + 1,
                'seed_offset'    => 0,
            ];
            $summary['completed_categories'] = $category_index + 1;
            $summary['done'] = ($category_index + 1) >= $total_categories;
            return $summary;
        }

        $seed_slice = array_slice($seeds, $seed_offset, $seed_batch_size);
        $last_call = 0.0;
        $keyword_map = [];

        $priority = ['extra' => 1, 'longtail' => 2, 'competitor' => 3];

        foreach ($seed_slice as $seed) {
            $seed = sanitize_text_field($seed);
            if ($seed === '') {
                continue;
            }

            $suggestions = self::fetch_google_suggestions($category, $seed, $hl, $gl, $per_seed, $rate_limit_ms, $last_call, $summary['errors'], 3, $accept_language);
            foreach ($suggestions as $suggestion) {
                ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword((string) $suggestion);
                if ($normalized === '' || !self::is_allowed($normalized, $display)) {
                    continue;
                }

                $word_count = self::keyword_word_count($display);
                if ($word_count <= 1) {
                    continue;
                }

                $type = self::determine_type($display, $category, $word_count);

                $row = [
                    'keyword'     => $display,
                    'word_count'  => $word_count,
                    'type'        => $type,
                    'source_seed' => $seed,
                    'category'    => $category,
                    'timestamp'   => current_time('mysql'),
                    'competition' => Keyword_Difficulty_Proxy::DEFAULT_COMPETITION,
                    'cpc'         => Keyword_Difficulty_Proxy::DEFAULT_CPC,
                    'tmw_kd'      => Keyword_Difficulty_Proxy::score($display, Keyword_Difficulty_Proxy::DEFAULT_COMPETITION, Keyword_Difficulty_Proxy::DEFAULT_CPC),
                ];

                if (!isset($keyword_map[$normalized]) || $priority[$type] > $priority[$keyword_map[$normalized]['type']]) {
                    $keyword_map[$normalized] = $row;
                }
            }
        }

        $rows_by_type = [
            'extra'      => [],
            'longtail'   => [],
            'competitor' => [],
        ];
        foreach ($keyword_map as $row) {
            $rows_by_type[$row['type']][] = $row;
        }

        $found_count = count($keyword_map);
        $initial_total = $found_count;
        self::ensure_minimum_google_rows($category, $rows_by_type, $keyword_map, $initial_total);
        self::ensure_minimum_type_targets($category, $seeds, $rows_by_type, $keyword_map);
        $found_count = count($keyword_map);
        $summary['batch_keywords'] = $found_count;

        $category_entry = [
            'category'      => $category,
            'found'         => $found_count,
            'seed_offset'   => $seed_offset + count($seed_slice),
            'seed_total'    => count($seeds),
            'category_done' => ($seed_offset + count($seed_slice)) >= count($seeds),
            'counts'        => [
                'extra'      => count($rows_by_type['extra']),
                'longtail'   => count($rows_by_type['longtail']),
                'competitor' => count($rows_by_type['competitor']),
            ],
        ];

        if (!$dry_run) {
            $new_counts = [];
            foreach ($rows_by_type as $type => $rows) {
                if (!empty($rows)) {
                    $before = count(Keyword_Library::load($category, $type));
                    $after = self::merge_write_csv($category, $type, $rows, false, false);
                    $new_counts[$type] = max(0, $after - $before);
                } else {
                    $new_counts[$type] = 0;
                }
            }
            $category_entry['new_counts'] = $new_counts;
            Core::debug_log(sprintf('[TMW-KEYPACKS] %s: %d keywords processed (seed %d/%d).', $category, $found_count, $category_entry['seed_offset'], $category_entry['seed_total']));
        } else {
            Core::debug_log(sprintf('[TMW-KEYPACKS] %s: %d keywords previewed (seed %d/%d).', $category, $found_count, $category_entry['seed_offset'], $category_entry['seed_total']));
        }

        $summary['categories'][] = $category_entry;

        if ($category_entry['category_done']) {
            $summary['cursor'] = [
                'category_index' => $category_index + 1,
                'seed_offset'    => 0,
            ];
            $summary['completed_categories'] = $category_index + 1;
        } else {
            $summary['cursor'] = [
                'category_index' => $category_index,
                'seed_offset'    => $category_entry['seed_offset'],
            ];
            $summary['completed_categories'] = $category_index;
        }

        $summary['done'] = $summary['completed_categories'] >= $total_categories;

        return $summary;
    }
}
