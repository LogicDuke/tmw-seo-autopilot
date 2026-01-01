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
            'insecam',
            'cctv',
            'security camera',
            'home cameras',
            'street cameras',
            'earthcam',
            'traffic camera',
            'video conferencing',
            'streaming 4k',
            'best webcam for',
            'best webcam',
            '4k webcam',
            'logitech',
            'brio',
            'microsoft modern webcam',
            'streaming webcam',
            'webcam under',
            'webcam with light',
            'pornhub',
            'xvideos',
            'xhamster',
            'redtube',
            'xnxx',
            'indexxx',
            'camsurf',
            'joingy',
            'monkey',
            'roulette',
            'random video chat',
            'stranger chat',
            'login',
            'model center',
            'help center',
            'reddit',
            'r/',
            'search |',
        ];

        return apply_filters('tmwseo_keyword_builder_blacklist', $list);
    }

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

    public static function is_allowed(string $normalized, string $display): bool {
        if ($normalized === '' || strlen($normalized) < 3) {
            return false;
        }

        foreach (self::blacklist() as $term) {
            $term = strtolower($term);
            if ($term !== '' && strpos($normalized, $term) !== false) {
                return false;
            }
        }

        if (!preg_match('/(cam|cams|camming|webcam model|cam model|cam site|live cam|livejasmin|chaturbate|stripchat|myfreecams)/i', $display)) {
            return false;
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
        $cam_seed_regex = '/\b(cam|cams|webcam|live cam|cam girl|cam model|cam site)\b/i';
        $cam_required_regex = '/(cam|cams|camming|webcam model|cam model|cam site|live cam|livejasmin|chaturbate|stripchat|myfreecams)/i';
        $debug_enabled = defined('TMWSEO_SERPER_DEBUG') && TMWSEO_SERPER_DEBUG;

        $keywords = [];
        foreach ($seeds as $seed) {
            $seed = trim((string) $seed);
            if ($seed === '') {
                continue;
            }

            if ($category === 'general' && stripos($seed, 'webcam') !== false) {
                $seed = 'live cam sites ' . $seed;
            }

            if (preg_match($cam_seed_regex, $seed)) {
                $queries = [$seed];
            } else {
                $queries = [
                    "{$seed} cam",
                    "{$seed} cam girl",
                    "{$seed} cam girls",
                    "{$seed} live cam",
                    "{$seed} live cam girls",
                    "{$seed} webcam model",
                    "{$seed} webcam models",
                    "{$seed} cam model",
                    "{$seed} cam models",
                    "{$seed} cam site",
                    "{$seed} cam sites",
                ];
            }

            $queries = array_values(array_unique(array_filter(array_map('trim', $queries), 'strlen')));
            if (empty($queries)) {
                continue;
            }

            $per_query = max(3, (int) ceil($per_seed / max(1, count($queries))));
            $fetch_count = min(20, $per_query * 2);

            $seed_suggestions = [];
            foreach ($queries as $query) {
                $result = Serper_Client::search($api_key, $query, $gl, $hl, $fetch_count);
                if (!empty($result['error'])) {
                    continue;
                }

                $data = $result['data'] ?? [];
                $suggestions = Serper_Client::extract_suggestions($data);
                $suggestions = array_slice($suggestions, 0, $fetch_count);
                $seed_suggestions = array_merge($seed_suggestions, $suggestions);
            }

            $accepted = 0;
            $rejected_blacklist = 0;
            $rejected_cam = 0;
            $accepted_samples = [];
            foreach ($seed_suggestions as $suggestion) {
                ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword($suggestion);
                if ($normalized === '') {
                    continue;
                }

                $blacklisted = false;
                foreach (self::blacklist() as $term) {
                    $term = strtolower($term);
                    if ($term !== '' && strpos($normalized, $term) !== false) {
                        $blacklisted = true;
                        break;
                    }
                }

                if ($blacklisted) {
                    $rejected_blacklist++;
                    continue;
                }

                if (!preg_match($cam_required_regex, $display)) {
                    $rejected_cam++;
                    continue;
                }

                if (!isset($keywords[$normalized])) {
                    $keywords[$normalized] = $display;
                }
                $accepted++;
                if (count($accepted_samples) < 5) {
                    $accepted_samples[] = $display;
                }
            }

            if ($debug_enabled) {
                $raw_samples = array_slice($seed_suggestions, 0, 5);
                error_log('[TMW-SERPER] Seed: ' . $seed);
                error_log('[TMW-SERPER] Queries: ' . wp_json_encode($queries));
                error_log('[TMW-SERPER] Raw suggestions: ' . count($seed_suggestions) . ' Samples: ' . wp_json_encode($raw_samples));
                error_log('[TMW-SERPER] Accepted: ' . $accepted . ' Samples: ' . wp_json_encode($accepted_samples));
                error_log('[TMW-SERPER] Rejected blacklist: ' . $rejected_blacklist . ' Rejected cam-rule: ' . $rejected_cam);
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
                    $raw_value = isset($row[0]) ? $row[0] : '';
                    ['normalized' => $value, 'display' => $display] = self::normalize_keyword($raw_value);
                    if ($first && $value === 'keyword') {
                        $first = false;
                        continue;
                    }
                    $first = false;
                    if ($value !== '') {
                        $existing[$value] = $display;
                        $existing_before[$value] = $display;
                    }
                }
                fclose($fh);
            }
        }

        foreach ($keywords as $kw) {
            ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword((string) $kw);
            if ($normalized !== '' && self::is_allowed($normalized, $display)) {
                if (!isset($existing[$normalized])) {
                    $existing[$normalized] = $display;
                }
            }
        }

        $final = array_values($existing);
        sort($final);

        $new_only = array_values(array_diff($final, array_values($existing_before)));

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
