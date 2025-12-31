<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Library {
    protected static $cache = [];
    protected static $blacklist = ['leak', 'download', 'nude', 'torrent', 'teen'];

    public static function uploads_base_dir(): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'tmwseo-keywords';
    }

    public static function plugin_base_dir(): string {
        return trailingslashit(TMW_SEO_PATH) . 'data/keywords';
    }

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
                    ['keyword'],
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
     * Map raw looks/tags to canonical category slugs that match keyword folders.
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

        $keywords = [];
        if (file_exists($path)) {
            $fh = fopen($path, 'r');
            if ($fh) {
                $first = true;
                while (($row = fgetcsv($fh)) !== false) {
                    if (count($row) === 0) {
                        continue;
                    }
                    $value = isset($row[0]) ? self::sanitize_keyword($row[0]) : '';
                    if ($value === '') {
                        continue;
                    }
                    if ($first && strtolower($value) === 'keyword') {
                        $first = false;
                        continue;
                    }
                    $first = false;
                    if (self::is_blacklisted($value)) {
                        continue;
                    }
                    $keywords[] = $value;
                }
                fclose($fh);
            }
        }

        $keywords = array_values(array_unique($keywords));
        self::$cache[$cache_key] = $keywords;
        set_transient($transient_key, $keywords, DAY_IN_SECONDS);

        return $keywords;
    }

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

    protected static function sanitize_keyword(string $keyword): string {
        $keyword = strip_tags($keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword);
        $keyword = trim($keyword);
        return $keyword;
    }

    protected static function is_blacklisted(string $keyword): bool {
        foreach (self::$blacklist as $banned) {
            if (stripos($keyword, $banned) !== false) {
                return true;
            }
        }
        return false;
    }

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
