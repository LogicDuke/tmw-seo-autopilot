<?php
/**
 * Keyword Library helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Keyword Library class.
 *
 * @package TMW_SEO
 */
class Keyword_Library {
    protected static $cache = [];
    protected static $blacklist = ['leak', 'download', 'nude', 'torrent', 'teen'];

    /**
     * Returns the uploads base directory.
     * @return string
     */
    public static function uploads_base_dir(): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'tmwseo-keywords';
    }

    /**
     * Returns the plugin base directory.
     * @return string
     */
    public static function plugin_base_dir(): string {
        return trailingslashit(TMW_SEO_PATH) . 'data/keywords';
    }

    /**
     * Ensures dirs and placeholders.
     * @return void
     */
    public static function ensure_dirs_and_placeholders(): void {
        $categories = self::categories();
        $types      = ['extra', 'longtail', 'competitor'];

        $bases = [self::uploads_base_dir(), self::plugin_base_dir()];

        foreach ($bases as $base) {
            foreach ($categories as $category) {
                $dir = trailingslashit($base) . $category;
                if (!is_dir($dir)) {
                    wp_mkdir_p($dir);
                }

                $placeholder_rows = [
                    ['keyword', 'word_count', 'type', 'source_seed', 'category', 'timestamp', 'competition', 'cpc', 'tmw_kd'],
                ];

                foreach ($types as $type) {
                    $file = trailingslashit($dir) . "{$type}.csv";
                    if (!file_exists($file)) {
                        $fh = fopen($file, 'w');
                        if ($fh) {
                            foreach ($placeholder_rows as $row) {
                                fputcsv($fh, $row);
                            }
                            fclose($fh);
                        }
                    }
                }
            }
        }
    }

    /**
     * Handles categories.
     * @return array
     */
    public static function categories(): array {
        return [
            'general',
            'livejasmin',
            'compare-platforms',
            'roleplay',
            'cosplay',
            'chatty',
            'dance',
            'glamour',
            'romantic',
            'dominant',
            'fitness',
            'outdoor',
            'uniforms',
            'couples',
            'fetish-lite',
            'petite',
            'curvy',
            'athletic',
            'big-boobs',
            'big-butt',
            'asian',
            'latina',
            'ebony',
            'interracial',
            'white',
            'blonde',
            'brunette',
            'redhead',
            'tattoo-piercing',
        ];
    }

    /**
     * Handles categories from looks.
     *
     * @param array $looks
     * @return array
     */
    public static function categories_from_looks(array $looks): array {
        $looks = array_map('strtolower', array_map('trim', $looks));
        $looks = array_values(array_filter($looks, 'strlen'));

        $explicit_blocklist = ['pussy', 'asshole', 'anal', 'cum', 'dick', 'cock'];
        foreach ($looks as $look) {
            foreach ($explicit_blocklist as $blocked) {
                if (strpos($look, $blocked) !== false) {
                    return ['general'];
                }
            }
        }

        $map = [
            'uniform'       => 'uniforms',
            'uniforms'      => 'uniforms',
            'cosplay'       => 'cosplay',
            'roleplay'      => 'roleplay',
            'chatty'        => 'chatty',
            'glamour'       => 'glamour',
            'dance'         => 'dance',
            'outdoor'       => 'outdoor',
            'public'        => 'outdoor',
            'fitness'       => 'fitness',
            'gym'           => 'fitness',
            'athletic'      => 'athletic',
            'big tits'      => 'big-boobs',
            'big boobs'     => 'big-boobs',
            'big breasts'   => 'big-boobs',
            'big ass'       => 'big-butt',
            'big butt'      => 'big-butt',
            'big booty'     => 'big-butt',
            'tattoo'        => 'tattoo-piercing',
            'tattooed'      => 'tattoo-piercing',
            'piercing'      => 'tattoo-piercing',
            'piercings'     => 'tattoo-piercing',
            'asian'         => 'asian',
            'ebony'         => 'ebony',
            'latina'        => 'latina',
            'latin'         => 'latina',
            'petite'        => 'petite',
            'curvy'         => 'curvy',
            'bbw'           => 'curvy',
            'couples'       => 'couples',
            'couple'        => 'couples',
            'dominant'      => 'dominant',
            'romantic'      => 'romantic',
            'redhead'       => 'redhead',
            'blonde'        => 'blonde',
            'brunette'      => 'brunette',
            'brown hair'    => 'brunette',
        ];

        $categories = [];
        foreach ($looks as $look) {
            if (isset($map[$look])) {
                $categories[] = $map[$look];
            }
        }

        $categories = array_values(array_unique(array_filter($categories)));
        if (!in_array('general', $categories, true)) {
            $categories[] = 'general';
        }

        return $categories;
    }

    /**
     * Handles categories from safe tags.
     *
     * @param array $safe_tags
     * @return array
     */
    public static function categories_from_safe_tags(array $safe_tags): array {
        $safe_tags = array_map('strtolower', array_map('trim', $safe_tags));
        foreach ($safe_tags as $tag) {
            if ($tag !== '' && strpos($tag, 'teen') !== false) {
                return ['general'];
            }
        }

        $matches = self::categories_from_looks($safe_tags);

        return array_slice($matches, 0, 3);
    }

    /**
     * Handles load.
     *
     * @param string $category
     * @param string $type
     * @return array
     */
    public static function load(string $category, string $type): array {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);
        $cache_key = $category . ':' . $type;

        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $uploads_path = trailingslashit(self::uploads_base_dir()) . "{$category}/{$type}.csv";
        $plugin_path  = trailingslashit(self::plugin_base_dir()) . "{$category}/{$type}.csv";

        $path = file_exists($uploads_path) ? $uploads_path : $plugin_path;
        $ver  = file_exists($path) ? (int) filemtime($path) : 0;

        $transient_key = 'tmwseo_kw_' . md5($cache_key . '|' . $ver);
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            self::$cache[$cache_key] = $cached;
            return $cached;
        }

        $rows = self::parse_csv_rows($path);
        $keywords = array_map(function ($row) {
            return $row['keyword'] ?? '';
        }, $rows);
        $keywords = array_filter($keywords, 'strlen');

        $keywords = array_values(array_unique($keywords));
        self::$cache[$cache_key] = $keywords;
        set_transient($transient_key, $keywords, DAY_IN_SECONDS);

        return $keywords;
    }

    /**
     * Loads rows.
     *
     * @param string $category
     * @param string $type
     * @return array
     */
    public static function load_rows(string $category, string $type): array {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);

        $uploads_path = trailingslashit(self::uploads_base_dir()) . "{$category}/{$type}.csv";
        $plugin_path  = trailingslashit(self::plugin_base_dir()) . "{$category}/{$type}.csv";

        $path = file_exists($uploads_path) ? $uploads_path : $plugin_path;

        return self::parse_csv_rows($path);
    }

    /**
     * Handles pick.
     *
     * @param string $category
     * @param string $type
     * @param int $count
     * @param string $seed
     * @return array
     */
    public static function pick(string $category, string $type, int $count, string $seed): array {
        $keywords = self::load($category, $type);
        if (empty($keywords)) {
            return [];
        }

        $count = max(0, $count);
        $scored = [];
        foreach ($keywords as $kw) {
            $hash = crc32($seed . '|' . $kw);
            $scored[] = ['kw' => $kw, 'score' => $hash];
        }

        usort($scored, function ($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        $picked = array_slice(array_column($scored, 'kw'), 0, $count);
        return array_values(array_unique($picked));
    }

    /**
     * Picks multi.
     *
     * @param array $categories
     * @param string $type
     * @param int $count
     * @param string $seed
     * @param array $used_on_page
     * @param int $cooldown_days
     * @param int $post_id
     * @param string $post_type
     * @return array
     */
    public static function pick_multi(array $categories, string $type, int $count, string $seed, array $used_on_page = [], int $cooldown_days = 30, int $post_id = 0, string $post_type = ''): array {
        $categories = array_values(array_unique(array_filter(array_map('sanitize_key', $categories))));
        $primary_category = $categories[0] ?? 'general';
        if (!in_array('general', $categories, true)) {
            $categories[] = 'general';
        }

        $pool_sources = [];
        foreach ($categories as $cat) {
            foreach (self::load($cat, $type) as $kw) {
                $pool_sources[$kw] = $pool_sources[$kw] ?? $cat;
            }
        }

        if (empty($pool_sources)) {
            return [];
        }

        $used_lookup = [];
        foreach ($used_on_page as $used_kw) {
            $key = strtolower(trim((string) $used_kw));
            if ($key !== '') {
                $used_lookup[$key] = true;
            }
        }

        $pool = [];
        foreach ($pool_sources as $kw => $source_cat) {
            $key = strtolower(trim($kw));
            if ($key === '' || isset($used_lookup[$key])) {
                continue;
            }
            $pool[$kw] = $source_cat;
        }

        if (empty($pool)) {
            return [];
        }

        $cooldown_days = (int) apply_filters('tmwseo_keyword_cooldown_days', $cooldown_days);
        $usage_stats   = Keyword_Usage::get_usage_stats(array_keys($pool), $primary_category, $type);

        $candidates = [];
        foreach ($pool as $kw => $source_cat) {
            $stats = $usage_stats[$kw] ?? ['count' => 0, 'last_used' => null];
            $recent_block = false;
            if ($cooldown_days > 0 && !empty($stats['last_used'])) {
                $recent_block = Keyword_Usage::is_within_days($stats['last_used'], $cooldown_days);
            }

            $hash = crc32($seed . '|' . $kw);
            $candidates[] = [
                'kw'        => $kw,
                'count'     => (int) $stats['count'],
                'last_used' => $stats['last_used'] ?: null,
                'recent'    => $recent_block,
                'score'     => $hash,
                'category'  => $source_cat,
            ];
        }

        $filtered = array_filter($candidates, function ($item) {
            return empty($item['recent']);
        });
        if (empty($filtered)) {
            $filtered = $candidates; // Allow reuse if pool is too small.
        }

        usort($filtered, function ($a, $b) {
            if ($a['count'] === $b['count']) {
                $a_time = $a['last_used'] ? strtotime($a['last_used']) : 0;
                $b_time = $b['last_used'] ? strtotime($b['last_used']) : 0;
                if ($a_time === $b_time) {
                    return $a['score'] <=> $b['score'];
                }
                return $a_time <=> $b_time;
            }
            return $a['count'] <=> $b['count'];
        });

        $picked = array_slice(array_column($filtered, 'kw'), 0, $count);
        $picked = array_values(array_unique($picked));

        if (!empty($picked)) {
            Keyword_Usage::record_usage($picked, $primary_category, $type, $post_id, $post_type ?: '');
        }

        return $picked;
    }

    /**
     * Sanitizes keyword.
     *
     * @param string $keyword
     * @return string
     */
    protected static function sanitize_keyword(string $keyword): string {
        $keyword = strip_tags($keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword);
        $keyword = trim($keyword);
        return $keyword;
    }

    /**
     * Handles parse csv rows.
     *
     * @param string $path
     * @return array
     */
    protected static function parse_csv_rows(string $path): array {
        $rows = [];
        if (!file_exists($path)) {
            return $rows;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            return $rows;
        }

        $header_map = [];
        $first_row  = fgetcsv($fh);
        $data_rows  = [];

        // Allow legacy single-column CSVs while supporting extended metadata headers.
        if ($first_row !== false && is_array($first_row)) {
            $normalized_header = array_map(function ($col) {
                return strtolower(trim((string) $col));
            }, $first_row);
            $normalized_header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($normalized_header[0] ?? ''));

            foreach ($normalized_header as $index => $col) {
                if ($col === 'keyword' || $col === 'phrase') {
                    $header_map['keyword'] = $index;
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

        $keyword_index = $header_map['keyword'] ?? 0;

        foreach ($data_rows as $row) {
            $raw_keyword = isset($row[$keyword_index]) ? self::sanitize_keyword((string) $row[$keyword_index]) : '';
            if ($raw_keyword === '') {
                continue;
            }

            if (self::is_blacklisted($raw_keyword)) {
                continue;
            }

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
            $tmw_kd      = $tmw_kd_raw !== '' ? (int) round((float) $tmw_kd_raw) : Keyword_Difficulty_Proxy::score($raw_keyword, $competition, $cpc);

            $rows[] = [
                'keyword'     => $raw_keyword,
                'competition' => $competition,
                'cpc'         => $cpc,
                'tmw_kd'      => max(0, min(100, $tmw_kd)),
            ];
        }

        return $rows;
    }

    /**
     * Checks whether blacklisted.
     *
     * @param string $keyword
     * @return bool
     */
    protected static function is_blacklisted(string $keyword): bool {
        foreach (self::$blacklist as $banned) {
            if (stripos($keyword, $banned) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handles flush cache.
     * @return void
     */
    public static function flush_cache(): void {
        global $wpdb;
        self::$cache = [];
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_tmwseo_kw_') . '%',
                $wpdb->esc_like('_transient_timeout_tmwseo_kw_') . '%'
            )
        );
    }
}
