<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Library {
    protected static $cache = [];
    protected static $blacklist = ['leak', 'download', 'nude', 'torrent'];

    protected static $category_map = [
        'roleplay' => ['roleplay', 'fantasy', 'story', 'character'],
        'chat'     => ['chat', 'interactive', 'talk', 'conversation'],
        'cosplay'  => ['cosplay', 'costume'],
        'couples'  => ['couple', 'duo'],
    ];

    public static function categories_from_safe_tags(array $safe_tags): array {
        $matches = [];
        foreach ($safe_tags as $tag) {
            $tag = strtolower((string) $tag);
            foreach (self::$category_map as $category => $needles) {
                foreach ($needles as $needle) {
                    if (strpos($tag, $needle) !== false) {
                        $matches[] = $category;
                        break 2;
                    }
                }
            }
            if (count($matches) >= 2) {
                break;
            }
        }

        $matches = array_values(array_unique($matches));
        if (!in_array('general', $matches, true)) {
            $matches[] = 'general';
        }

        return array_slice($matches, 0, 3);
    }

    public static function load(string $category, string $type): array {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);
        $cache_key = $category . ':' . $type;

        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $transient_key = 'tmwseo_kw_' . md5($cache_key);
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            self::$cache[$cache_key] = $cached;
            return $cached;
        }

        $path = trailingslashit(TMW_SEO_PATH) . "data/keywords/{$category}/{$type}.csv";
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

    public static function pick_multi(array $categories, string $type, int $count, string $seed): array {
        $pool = [];
        $categories = array_values(array_unique(array_filter(array_map('sanitize_key', $categories))));
        if (!in_array('general', $categories, true)) {
            $categories[] = 'general';
        }

        foreach ($categories as $cat) {
            $pool = array_merge($pool, self::load($cat, $type));
        }

        $pool = array_values(array_unique($pool));
        if (empty($pool)) {
            return [];
        }

        $scored = [];
        foreach ($pool as $kw) {
            $hash = crc32($seed . '|' . $kw);
            $scored[] = ['kw' => $kw, 'score' => $hash];
        }

        usort($scored, function ($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        $picked = array_slice(array_column($scored, 'kw'), 0, $count);
        return array_values(array_unique($picked));
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
}
