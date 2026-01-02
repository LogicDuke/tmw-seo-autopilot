<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Pack_Builder {
    protected static $google_suggest_client;

    protected static function google_suggest_client(): Google_Suggest_Client {
        if (!self::$google_suggest_client) {
            self::$google_suggest_client = new Google_Suggest_Client();
        }

        return self::$google_suggest_client;
    }

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

        if (!preg_match('/(stream|live|video|creator|platform|model)/i', $display)) {
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

    protected static function make_indirect_seeds(string $category, array $seeds): array {
        $groups = [];

        foreach ($seeds as $seed) {
            $seed = trim((string) $seed);
            if ($seed === '') {
                continue;
            }

            $region_seed = preg_replace('/\blatina\b/i', 'latin american', $seed);
            $region_seed = preg_replace('/\bcolombian\b/i', 'colombia', (string) $region_seed);

            $indirect = [
                "{$region_seed} streaming creators",
                "{$region_seed} digital content industry",
            ];

            $groups[] = [
                'seed' => $seed,
                'indirect' => array_values(array_unique(array_filter(array_map('trim', $indirect), 'strlen'))),
            ];
        }

        $generic = [
            'streaming platform features',
            'live video creator tools',
            'creator economy trends',
            'digital content industry insights',
            'online video engagement tips',
        ];

        if (!empty($generic)) {
            $groups[] = [
                'seed' => $category,
                'indirect' => $generic,
            ];
        }

        return $groups;
    }

    protected static function build_efficient_queries(string $seed): array {
        $seed = trim($seed);
        if ($seed === '') {
            return [];
        }

        if (preg_match('/\b(cam|cams|webcam|live cam|cam girl|cam model|cam site)\b/i', $seed)) {
            return [$seed];
        }

        return [
            "{$seed} streaming platform",
            "{$seed} creator tools",
        ];
    }

    protected static function contextualize_keyword(string $seed, string $keyword): string {
        $seed = trim($seed);
        $keyword = trim($keyword);
        if ($seed === '' || $keyword === '') {
            return $keyword;
        }

        if (stripos($keyword, $seed) !== false) {
            return $keyword;
        }

        return trim($seed . ' ' . $keyword);
    }

    protected static function generate_manual_keywords(string $seed): array {
        $seed = trim($seed);
        if ($seed === '') {
            return [];
        }

        $templates = [
            '%s live cam',
            '%s webcam model',
            '%s live video',
            '%s streaming platform',
            '%s streaming tips',
            '%s live stream schedule',
            '%s creator tools',
            '%s webcam shows',
            '%s live cam shows',
            '%s cam model profile',
            '%s online video creators',
            '%s live chat room',
            '%s cam show highlights',
            '%s creator engagement',
            '%s streaming equipment tips',
            '%s live video creators',
            '%s webcam industry trends',
            '%s streaming growth trends',
            '%s platform features',
            '%s creator economy',
        ];

        $keywords = [];
        foreach ($templates as $template) {
            $keywords[] = sprintf($template, $seed);
        }

        $extensions = ['show', 'schedule', 'tips', 'guide', 'profile', 'highlights', 'fans', 'community', 'platform'];
        foreach ($extensions as $extension) {
            $keywords[] = sprintf('%s live cam %s', $seed, $extension);
            $keywords[] = sprintf('%s webcam model %s', $seed, $extension);
        }

        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords), 'strlen')));

        while (count($keywords) < 50) {
            $keywords[] = sprintf('%s live cam %d', $seed, count($keywords) + 1);
        }

        return array_slice($keywords, 0, 50);
    }

    public static function generate(string $category, array $seeds, string $gl, string $hl, int $per_seed = 10, string $provider = '', array &$run_state = null) {
        $provider = sanitize_text_field($provider ?: (string) get_option('tmwseo_keyword_provider', 'serper'));
        $allowed_providers = ['serper', 'google_suggest'];
        if (!in_array($provider, $allowed_providers, true)) {
            $provider = 'serper';
        }

        $api_key = '';
        if ($provider === 'serper') {
            $api_key = trim((string) get_option('tmwseo_serper_api_key', ''));
            if ($api_key === '') {
                return new \WP_Error('tmwseo_serper_missing', 'Serper API key missing', ['provider' => $provider]);
            }
        }

        if (!is_array($run_state)) {
            $run_state = [];
        }
        if (!isset($run_state['total_calls'])) {
            $run_state['total_calls'] = 0;
        }
        if (!isset($run_state['max_calls'])) {
            $run_state['max_calls'] = 25;
        }
        if (!isset($run_state['errors']) || !is_array($run_state['errors'])) {
            $run_state['errors'] = [];
        }

        $per_seed = max(1, min(50, $per_seed));
        $gl = sanitize_text_field($gl ?: 'us');
        $hl = sanitize_text_field($hl ?: 'en');
        $cam_required_regex = '/(stream|live|video|creator|platform|model)/i';
        $debug_enabled = (defined('TMWSEO_SERPER_DEBUG') && TMWSEO_SERPER_DEBUG)
            || (defined('TMWSEO_KW_DEBUG') && TMWSEO_KW_DEBUG);
        $max_calls = (int) $run_state['max_calls'];

        $keywords = [];
        $seed_groups = self::make_indirect_seeds($category, $seeds);
        $seed_target = max(1, $per_seed);

        foreach ($seed_groups as $group) {
            $seed = trim((string) ($group['seed'] ?? ''));
            $indirect_seeds = (array) ($group['indirect'] ?? []);
            if ($seed === '' || empty($indirect_seeds)) {
                continue;
            }

            $seed_suggestions_count = 0;
            $seed_accepted = 0;
            $seed_accepted_extra = 0;
            $seed_accepted_longtail = 0;
            $rejected_blacklist = 0;
            $rejected_cam = 0;
            $accepted_samples = [];
            $seed_seen = [];
            $used_queries = [];
            $seed_complete = false;
            foreach ($indirect_seeds as $indirect_seed) {
                $queries = self::build_efficient_queries($indirect_seed);
                $queries = array_values(array_unique(array_filter(array_map('trim', $queries), 'strlen')));
                if (empty($queries)) {
                    continue;
                }

                $per_query = max(3, (int) ceil($per_seed / max(1, count($queries))));
                $fetch_count = min(20, $per_query * 2);

                foreach ($queries as $query) {
                    $used_queries[] = $query;
                    $suggestions = [];
                    $used_external_call = false;
                    if ($provider === 'google_suggest') {
                        $cache_key = Google_Suggest_Client::cache_key($query, $hl, $gl);
                        $cache_hit = get_transient($cache_key);
                        if ($cache_hit === false && $run_state['total_calls'] >= $max_calls) {
                            return new \WP_Error(
                                'tmwseo_call_cap',
                                'Too many external requests in one run. Reduce seeds or Suggestions-per-seed.',
                                ['provider' => $provider, 'query' => $query]
                            );
                        }
                        $client = self::google_suggest_client();
                        $result = $client->fetch($query, $hl, $gl);
                        if (is_wp_error($result)) {
                            return new \WP_Error(
                                'tmwseo_provider_failed',
                                'Provider request failed',
                                [
                                    'provider' => $provider,
                                    'query'    => $query,
                                    'details'  => $result->get_error_message(),
                                ]
                            );
                        }
                        $suggestions = array_slice($result, 0, $fetch_count);
                        $used_external_call = !$client->was_last_cached();
                    } else {
                        if ($run_state['total_calls'] >= $max_calls) {
                            return new \WP_Error(
                                'tmwseo_call_cap',
                                'Too many external requests in one run. Reduce seeds or Suggestions-per-seed.',
                                ['provider' => $provider, 'query' => $query]
                            );
                        }
                        $used_external_call = true;
                        try {
                            $result = Serper_Client::search($api_key, $query, $gl, $hl, $fetch_count);
                        } catch (\Exception $e) {
                            Core::debug_log('[TMW-SERPER-ERROR] ' . $e->getMessage());
                            $errors = get_option('tmwseo_serper_error_log', []);
                            $errors[] = [
                                'time' => current_time('mysql'),
                                'query' => $query,
                                'error' => $e->getMessage(),
                            ];
                            update_option('tmwseo_serper_error_log', array_slice($errors, -20));
                            return new \WP_Error(
                                'tmwseo_provider_failed',
                                'Provider request failed',
                                [
                                    'provider' => $provider,
                                    'query'    => $query,
                                    'details'  => $e->getMessage(),
                                ]
                            );
                        }
                        if (!empty($result['error'])) {
                            $http_code = (int) ($result['http_code'] ?? 0);
                            $error_message = (string) ($result['error_message'] ?? $result['error']);
                            return new \WP_Error(
                                'tmwseo_provider_failed',
                                'Provider request failed',
                                [
                                    'provider'   => $provider,
                                    'query'      => $query,
                                    'details'    => $error_message,
                                    'http_code'  => $http_code,
                                ]
                            );
                        }

                        $data = $result['data'] ?? [];
                        $suggestions = Serper_Client::extract_suggestions($data);
                        $suggestions = array_slice($suggestions, 0, $fetch_count);
                    }

                    if ($used_external_call) {
                        $run_state['total_calls']++;
                    }
                    $seed_suggestions_count += count($suggestions);

                    foreach ($suggestions as $suggestion) {
                        ['display' => $display] = self::normalize_keyword($suggestion);
                        if ($display === '') {
                            continue;
                        }

                        $contextual = self::contextualize_keyword($seed, $display);
                        ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword($contextual);
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

                        if (!isset($seed_seen[$normalized])) {
                            $seed_seen[$normalized] = true;
                            $seed_accepted++;
                            $words = preg_split('/\s+/', trim($display));
                            $word_count = is_array($words) ? count(array_filter($words, 'strlen')) : 0;
                            if ($word_count >= 5) {
                                $seed_accepted_longtail++;
                            } elseif ($word_count >= 2) {
                                $seed_accepted_extra++;
                            }

                            if (count($accepted_samples) < 5) {
                                $accepted_samples[] = $display;
                            }
                        }

                        if (!isset($keywords[$normalized])) {
                            $keywords[$normalized] = $display;
                        }

                        if ($seed_accepted_extra >= $per_seed && $seed_accepted_longtail >= $per_seed) {
                            $seed_complete = true;
                            break;
                        }
                    }

                    if ($seed_accepted >= $seed_target) {
                        $seed_complete = true;
                    }

                    if ($seed_complete) {
                        break;
                    }
                }

                if ($seed_complete) {
                    break;
                }
            }

            if ($seed_accepted < 10) {
                $manual_keywords = self::generate_manual_keywords($seed);
                foreach ($manual_keywords as $manual_keyword) {
                    ['normalized' => $normalized, 'display' => $display] = self::normalize_keyword($manual_keyword);
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

                    if ($blacklisted || !preg_match($cam_required_regex, $display)) {
                        continue;
                    }

                    if (!isset($seed_seen[$normalized])) {
                        $seed_seen[$normalized] = true;
                        $seed_accepted++;
                    }

                    if (!isset($keywords[$normalized])) {
                        $keywords[$normalized] = $display;
                    }
                }
            }

            if ($debug_enabled) {
                error_log('[TMW-KW] Provider: ' . $provider . ' Seed: ' . $seed);
                error_log('[TMW-KW] Queries: ' . wp_json_encode($used_queries));
                error_log('[TMW-KW] Raw suggestions: ' . $seed_suggestions_count);
                error_log('[TMW-KW] Accepted: ' . $seed_accepted . ' Samples: ' . wp_json_encode($accepted_samples));
                error_log('[TMW-KW] Rejected blacklist: ' . $rejected_blacklist . ' Rejected cam-rule: ' . $rejected_cam);
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
    
    // NEW METHOD - OUTSIDE merge_write_csv
    public static function generate_from_autocomplete(string $category, array $user_seeds, int $limit = 50): array {
        // Load seeds
        $seeds_file = TMW_SEO_PATH . 'data/google-autocomplete-seeds.php';
        $all_seeds = file_exists($seeds_file) ? require $seeds_file : [];
        $category_seeds = $all_seeds[$category] ?? $all_seeds['general'] ?? [];
        
        // Merge with user seeds
        $seeds = array_merge($category_seeds, $user_seeds);
        $seeds = array_values(array_unique(array_filter($seeds)));
        
        if (empty($seeds)) {
            Core::debug_log('[AUTOCOMPLETE] No seeds for category: ' . $category);
            return ['extra' => [], 'longtail' => []];
        }
        
        Core::debug_log('[AUTOCOMPLETE] Category: ' . $category);
        Core::debug_log('[AUTOCOMPLETE] Seeds: ' . count($seeds));
        
        return ['extra' => [], 'longtail' => []];
    }
}
