<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Pack_Builder {
    public static function blacklist(): array {
        $list = [
            'leak',
            'torrent',
            'download',
            'nude',
            'free videos',
            'onlyfans leak',
            'mega',
            'rapidgator',
            'pornhub download',
            'teen',
        ];

        return apply_filters('tmwseo_keyword_builder_blacklist', $list);
    }

    public static function normalize(string $s): string {
        $s = wp_strip_all_tags($s);
        $s = str_replace(['"', "'", '“', '”', '‘', '’'], '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/([!?.,])\1+/', '$1', $s);
        $s = trim((string) $s);
        $s = strtolower($s);
        return $s;
    }

    public static function is_allowed(string $s): bool {
        $normalized = self::normalize($s);
        if ($normalized === '' || strlen($normalized) < 3) {
            return false;
        }

        if (strpos($normalized, 'teen') !== false) {
            return false;
        }

        foreach (self::blacklist() as $term) {
            $term = strtolower($term);
            if ($term !== '' && strpos($normalized, $term) !== false) {
                return false;
            }
        }

        return true;
    }

    public static function split_types(array $keywords): array {
        $buckets = [
            'extra'    => [],
            'longtail' => [],
        ];

        foreach ($keywords as $kw) {
            $words = preg_split('/\s+/', trim((string) $kw));
            $count = is_array($words) ? count(array_filter($words, 'strlen')) : 0;
            if ($count >= 5) {
                $buckets['longtail'][] = $kw;
            } elseif ($count >= 2) {
                $buckets['extra'][] = $kw;
            }
        }

        $buckets['extra']    = array_values(array_unique($buckets['extra']));
        $buckets['longtail'] = array_values(array_unique($buckets['longtail']));

        return $buckets;
    }

    public static function generate(string $category, array $seeds, string $gl, string $hl, int $per_seed = 10): array {
        $api_key = trim((string) get_option('tmwseo_serper_api_key', ''));
        if ($api_key === '') {
            return ['extra' => [], 'longtail' => []];
        }

        $per_seed = max(1, min(50, $per_seed));
        $gl = sanitize_text_field($gl ?: 'us');
        $hl = sanitize_text_field($hl ?: 'en');

        $keywords = [];
        foreach ($seeds as $seed) {
            $seed = trim((string) $seed);
            if ($seed === '') {
                continue;
            }

            $result = Serper_Client::search($api_key, $seed, $gl, $hl, 10);
            if (!empty($result['error'])) {
                continue;
            }

            $data = $result['data'] ?? [];
            $suggestions = Serper_Client::extract_suggestions($data);
            $suggestions = array_slice($suggestions, 0, $per_seed);

            foreach ($suggestions as $suggestion) {
                $norm = self::normalize($suggestion);
                if ($norm === '' || !self::is_allowed($norm)) {
                    continue;
                }
                $keywords[$norm] = $norm;
            }
        }

        return self::split_types(array_values($keywords));
    }

    public static function merge_write_csv(string $category, string $type, array $keywords, bool $append = false): int {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);

        $uploads = wp_upload_dir();
        $base    = trailingslashit($uploads['basedir']) . 'tmwseo-keywords';
        $dir     = trailingslashit($base) . $category;

        wp_mkdir_p($dir);

        $path = trailingslashit($dir) . "{$type}.csv";

        $existing = [];
        $existing_before = [];
        if (file_exists($path)) {
            $fh = fopen($path, 'r');
            if ($fh) {
                $first = true;
                while (($row = fgetcsv($fh)) !== false) {
                    $value = isset($row[0]) ? self::normalize($row[0]) : '';
                    if ($first && $value === 'keyword') {
                        $first = false;
                        continue;
                    }
                    $first = false;
                    if ($value !== '') {
                        $existing[$value] = $value;
                        $existing_before[$value] = $value;
                    }
                }
                fclose($fh);
            }
        }

        foreach ($keywords as $kw) {
            $kw = self::normalize($kw);
            if ($kw !== '') {
                $existing[$kw] = $kw;
            }
        }

        $final = array_values(array_unique($existing));
        sort($final);

        $new_only = array_values(array_diff($final, array_keys($existing_before)));

        $fh = fopen($path, $append ? 'a' : 'w');
        if ($fh) {
            if (!$append) {
                fputcsv($fh, ['keyword']);
                foreach ($final as $kw) {
                    fputcsv($fh, [$kw]);
                }
            } else {
                foreach ($new_only as $kw) {
                    fputcsv($fh, [$kw]);
                }
            }
            fclose($fh);
        }

        return count($final);
    }
}
