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

    protected static function csv_columns(): array {
        // Extended keyword metadata columns for KD reporting + audit trail.
        return ['keyword', 'word_count', 'type', 'source_seed', 'category', 'timestamp', 'competition', 'cpc', 'tmw_kd'];
    }

    protected static function keyword_word_count(string $keyword): int {
        // Count words in a normalized keyword string.
        $words = preg_split('/\s+/', trim($keyword));
        return is_array($words) ? count(array_filter($words, 'strlen')) : 0;
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
            if ($normalized !== '' && self::is_allowed($normalized, $display)) {
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

        return count($final);
    }

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
        $after_count  = self::merge_write_csv($category, $type, $rows, false);
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
    
    protected static function fetch_google_suggestions(string $query, string $hl, string $gl, int $limit, int $rate_limit_ms, float &$last_call, array &$errors): array {
        // Enforce a 500ms rate limit between outbound Google Suggest calls.
        $client = self::google_suggest_client();
        $backoff_key = 'tmwseo_google_suggest_backoff';
        $backoff_until = (int) get_transient($backoff_key);
        if ($backoff_until > time()) {
            $errors[] = sprintf('%s: Google rate-limit cooldown active.', $query);
            Core::debug_log(sprintf('[TMW-AUTOFILL] Backoff active for "%s".', $query));
            return [];
        }

        if ($rate_limit_ms > 0 && $last_call > 0) {
            $elapsed = (microtime(true) - $last_call) * 1000;
            if ($elapsed < $rate_limit_ms) {
                usleep((int) (($rate_limit_ms - $elapsed) * 1000));
            }
        }

        $result = $client->fetch($query, $hl, $gl);

        if (!$client->was_last_cached()) {
            $last_call = microtime(true);
        }

        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            $http_code = is_array($data) ? (int) ($data['http_code'] ?? 0) : 0;
            $message = $result->get_error_message();
            Core::debug_log(sprintf('[TMW-AUTOFILL] Google Suggest error for "%s": %s', $query, $message));

            // Retry after cooling off if Google responds with a rate-limit error.
            if ($http_code === 429) {
                $cooldown = 60;
                set_transient($backoff_key, time() + $cooldown, $cooldown);
                Core::debug_log('[TMW-AUTOFILL] Rate limit hit, entering cooldown.');
                $errors[] = sprintf('%s: Google rate limit hit; cooldown started.', $query);
                return [];
            }

            $errors[] = sprintf('%s: %s', $query, $message);
            return [];
        }

        return array_slice((array) $result, 0, $limit);
    }

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
        $rate_limit_ms = (int) ($options['rate_limit_ms'] ?? 500);

        $brands = ['livejasmin', 'chaturbate', 'stripchat'];
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
                $suggestions = self::fetch_google_suggestions($seed, $hl, $gl, $per_seed, $rate_limit_ms, $last_call, $summary['errors']);
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

                    $type = null;
                    foreach ($brands as $brand) {
                        if (stripos($display, $brand) !== false) {
                            $type = 'competitor';
                            break;
                        }
                    }

                    if (!$type) {
                        $type = $word_count >= 5 ? 'longtail' : 'extra';
                    }

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
                    $before = count(Keyword_Library::load($category, $type));
                    $after = self::merge_write_csv($category, $type, $rows, false);
                    $new_counts[$type] = max(0, $after - $before);
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
}
