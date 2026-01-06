<?php
/**
 * DataForSEO client wrapper.
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * DataForSEO HTTP client.
 */
class DataForSEO_Client {
    const SEARCH_VOLUME_ENDPOINT = 'https://api.dataforseo.com/v3/keywords_data/google_ads/search_volume/live';
    const KEYWORD_DIFFICULTY_ENDPOINT = 'https://api.dataforseo.com/v3/dataforseo_labs/google/bulk_keyword_difficulty/live';
    const MAX_BATCH = 1000;
    const CACHE_TTL = DAY_IN_SECONDS * 30; // 30 days.

    /**
     * Whether credentials are configured and enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_option('tmwseo_dataforseo_enabled', false) && self::is_configured();
    }

    /**
     * Whether credentials are configured.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $login    = (string) get_option('tmwseo_dataforseo_login', '');
        $password = (string) get_option('tmwseo_dataforseo_password', '');

        return trim($login) !== '' && trim($password) !== '';
    }

    /**
     * Fetch Search Volume + CPC + competition.
     *
     * @param array  $keywords      Keywords list.
     * @param int    $location_code Location code.
     * @param string $language_code Language code.
     * @return array Map of keyword => metrics.
     */
    public function search_volume(array $keywords, int $location_code, string $language_code): array {
        $keywords = $this->normalize_keyword_list($keywords);
        if (empty($keywords) || !$this->has_credentials()) {
            return [];
        }

        $results = [];
        $pending = [];
        foreach ($keywords as $keyword) {
            $cache_key = $this->cache_key('sv', $keyword, $location_code, $language_code);
            $cached    = get_transient($cache_key);
            if ($cached !== false) {
                $results[$keyword] = $cached;
                continue;
            }
            $pending[] = $keyword;
        }

        if (!empty($pending)) {
            foreach (array_chunk($pending, self::MAX_BATCH) as $chunk) {
                $payload = [
                    [
                        'keywords' => array_values($chunk),
                        'location_code' => $location_code > 0 ? $location_code : 2840,
                        'language_code' => $language_code !== '' ? $language_code : 'en',
                        'include_adult_keywords' => true,
                    ],
                ];

                $response = $this->post(self::SEARCH_VOLUME_ENDPOINT, $payload);
                if (is_wp_error($response)) {
                    continue;
                }

                $tasks = $response['tasks'] ?? [];
                foreach ($tasks as $task) {
                    foreach (($task['result'] ?? []) as $result) {
                        foreach (($result['items'] ?? []) as $item) {
                            $keyword_text = trim((string) ($item['keyword'] ?? ''));
                            if ($keyword_text === '') {
                                continue;
                            }

                            $metrics = [
                                'search_volume' => $this->extract_volume($item),
                                'cpc' => $this->extract_cpc($item),
                                'competition_level' => $this->extract_competition_level($item),
                            ];

                            $results[$keyword_text] = $metrics;
                            set_transient($this->cache_key('sv', $keyword_text, $location_code, $language_code), $metrics, self::CACHE_TTL);
                        }
                    }
                }

                // Cache empty results to avoid re-querying.
                foreach ($chunk as $kw) {
                    if (!isset($results[$kw])) {
                        $empty_metrics = [
                            'search_volume' => null,
                            'cpc' => null,
                            'competition_level' => null,
                        ];
                        $results[$kw] = $empty_metrics;
                        set_transient($this->cache_key('sv', $kw, $location_code, $language_code), $empty_metrics, self::CACHE_TTL);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Resolve keyword difficulty with fallback heuristics.
     *
     * @param array  $keywords      Keywords.
     * @param int    $location_code Location code.
     * @param string $language_code Language code.
     * @return array Map keyword => difficulty data.
     */
    public function resolve_keyword_difficulty(array $keywords, int $location_code, string $language_code): array {
        $keywords = $this->normalize_keyword_list($keywords);
        if (empty($keywords) || !$this->has_credentials()) {
            return [];
        }

        $location_code = $location_code > 0 ? $location_code : 2840;
        $language_code = $language_code !== '' ? $language_code : 'en';

        $direct = $this->bulk_keyword_difficulty($keywords, $location_code, $language_code);
        $results = [];
        $fallback_candidates = [];

        foreach ($keywords as $keyword) {
            $direct_kd = $direct[$keyword]['kd'] ?? null;
            $results[$keyword] = [
                'keyword' => $keyword,
                'kd' => $direct_kd,
                'kd_keyword_used' => $keyword,
                'kd_source' => $direct_kd !== null ? 'dataforseo' : 'unknown',
            ];

            if ($direct_kd === null) {
                $fallback_candidates[$keyword] = $this->generate_fallback_candidates($keyword);
            }
        }

        $all_candidates = [];
        foreach ($fallback_candidates as $candidates) {
            foreach ($candidates as $candidate) {
                $all_candidates[$candidate] = true;
            }
        }

        $candidate_list = array_keys($all_candidates);
        $candidate_results = [];
        if (!empty($candidate_list)) {
            $candidate_results = $this->bulk_keyword_difficulty($candidate_list, $location_code, $language_code);
        }

        foreach ($fallback_candidates as $keyword => $candidates) {
            if (empty($candidates)) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $candidate_kd = $candidate_results[$candidate]['kd'] ?? null;
                if ($candidate_kd !== null) {
                    $results[$keyword]['kd'] = $candidate_kd;
                    $results[$keyword]['kd_keyword_used'] = $candidate;
                    $results[$keyword]['kd_source'] = $this->derive_kd_source($keyword, $candidate);
                    break;
                }
            }
        }

        // Cache any unresolved keywords with explicit null KD to avoid repeat lookups.
        foreach ($results as $keyword => $payload) {
            if ($payload['kd'] === null) {
                set_transient($this->cache_key('kd', $keyword, $location_code, $language_code), ['kd' => null], self::CACHE_TTL);
            }
        }

        return $results;
    }

    /**
     * Lightweight connection test.
     *
     * @param string $keyword
     * @param int    $location_code
     * @param string $language_code
     * @return array|\WP_Error
     */
    public function test_connection(string $keyword, int $location_code, string $language_code)
    {
        $payload = [
            [
                'keywords' => [$keyword],
                'location_code' => $location_code > 0 ? $location_code : 2840,
                'language_code' => $language_code !== '' ? $language_code : 'en',
            ],
        ];

        return $this->post(self::KEYWORD_DIFFICULTY_ENDPOINT, $payload);
    }

    /**
     * Raw bulk keyword difficulty call (no fallback).
     *
     * @param array  $keywords      Keywords.
     * @param int    $location_code Location code.
     * @param string $language_code Language code.
     * @return array Map keyword => ['kd' => ?]
     */
    public function bulk_keyword_difficulty(array $keywords, int $location_code, string $language_code): array {
        $keywords = $this->normalize_keyword_list($keywords);
        if (empty($keywords) || !$this->has_credentials()) {
            return [];
        }

        $results = [];
        $pending = [];
        foreach ($keywords as $keyword) {
            $cache_key = $this->cache_key('kd', $keyword, $location_code, $language_code);
            $cached    = get_transient($cache_key);
            if ($cached !== false) {
                $results[$keyword] = $cached;
            } else {
                $pending[] = $keyword;
            }
        }

        if (empty($pending)) {
            return $results;
        }

        foreach (array_chunk($pending, self::MAX_BATCH) as $chunk) {
            $payload = [
                [
                    'keywords' => array_values($chunk),
                    'location_code' => $location_code > 0 ? $location_code : 2840,
                    'language_code' => $language_code !== '' ? $language_code : 'en',
                ],
            ];

            $response = $this->post(self::KEYWORD_DIFFICULTY_ENDPOINT, $payload);
            if (is_wp_error($response)) {
                continue;
            }

            $tasks = $response['tasks'] ?? [];
            foreach ($tasks as $task) {
                foreach (($task['result'] ?? []) as $result) {
                    foreach (($result['items'] ?? []) as $item) {
                        $keyword_text = trim((string) ($item['keyword'] ?? ''));
                        if ($keyword_text === '') {
                            continue;
                        }
                        $kd_value = null;
                        if (isset($item['keyword_difficulty'])) {
                            $kd_value = (float) $item['keyword_difficulty'];
                        } elseif (isset($item['keyword_difficulty_index'])) {
                            $kd_value = (float) $item['keyword_difficulty_index'];
                        }

                        $normalized = [
                            'kd' => $kd_value === null ? null : (int) round(max(0, min(100, $kd_value))),
                        ];
                        $results[$keyword_text] = $normalized;
                        set_transient($this->cache_key('kd', $keyword_text, $location_code, $language_code), $normalized, self::CACHE_TTL);
                    }
                }
            }

            foreach ($chunk as $kw) {
                if (!isset($results[$kw])) {
                    $null_value = ['kd' => null];
                    $results[$kw] = $null_value;
                    set_transient($this->cache_key('kd', $kw, $location_code, $language_code), $null_value, self::CACHE_TTL);
                }
            }
        }

        return $results;
    }

    /**
     * Perform a POST request.
     *
     * @param string $endpoint Endpoint URL.
     * @param array  $payload  Payload.
     * @return array|\WP_Error
     */
    protected function post(string $endpoint, array $payload) {
        $login    = trim((string) get_option('tmwseo_dataforseo_login', ''));
        $password = trim((string) get_option('tmwseo_dataforseo_password', ''));

        $response = wp_remote_post(
            $endpoint,
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('tmwseo_dataforseo_http', $response->get_error_message());
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw    = wp_remote_retrieve_body($response);
        $decoded     = json_decode($body_raw, true);

        if ($status_code < 200 || $status_code >= 300) {
            $snippet = is_string($body_raw) ? substr($body_raw, 0, 200) : '';
            return new \WP_Error('tmwseo_dataforseo_status', sprintf('HTTP %d: %s', $status_code, $snippet));
        }

        if (!is_array($decoded)) {
            return new \WP_Error('tmwseo_dataforseo_json', 'Invalid JSON response from DataForSEO.');
        }

        $tasks = $decoded['tasks'] ?? [];
        if (empty($tasks) || !is_array($tasks)) {
            return new \WP_Error('tmwseo_dataforseo_missing_result', 'No tasks returned from DataForSEO.');
        }

        return $decoded;
    }

    /**
     * Normalize list of keywords.
     *
     * @param array $keywords
     * @return array
     */
    protected function normalize_keyword_list(array $keywords): array {
        return array_values(array_unique(array_filter(array_map('trim', $keywords), 'strlen')));
    }

    /**
     * Extract search volume.
     *
     * @param array $item
     * @return int|null
     */
    protected function extract_volume(array $item): ?int {
        if (isset($item['search_volume'])) {
            return (int) $item['search_volume'];
        }
        if (isset($item['keyword_info']['search_volume'])) {
            return (int) $item['keyword_info']['search_volume'];
        }
        if (isset($item['keyword_data']['search_volume'])) {
            return (int) $item['keyword_data']['search_volume'];
        }

        return null;
    }

    /**
     * Extract CPC.
     *
     * @param array $item
     * @return float|null
     */
    protected function extract_cpc(array $item): ?float {
        $cpc_fields = [
            'cpc',
            'avg_cpc',
            'keyword_info.cpc',
            'keyword_info.avg_cpc',
            'keyword_data.cpc',
            'keyword_data.avg_cpc',
        ];

        foreach ($cpc_fields as $field) {
            $value = $this->deep_get($item, $field);
            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * Extract competition level.
     *
     * @param array $item
     * @return string|null
     */
    protected function extract_competition_level(array $item): ?string {
        $value = $this->deep_get($item, 'competition_level');
        if (is_string($value) && $value !== '') {
            return strtolower($value);
        }

        $competition = $this->deep_get($item, 'competition');
        if ($competition !== null) {
            if (is_numeric($competition)) {
                $numeric = (float) $competition;
                if ($numeric < 0.34) {
                    return 'low';
                }
                if ($numeric < 0.67) {
                    return 'medium';
                }
                return 'high';
            }
            if (is_string($competition) && $competition !== '') {
                return strtolower($competition);
            }
        }

        return null;
    }

    /**
     * Retrieve nested array values using dot notation.
     *
     * @param array  $array
     * @param string $path
     * @return mixed|null
     */
    protected function deep_get(array $array, string $path) {
        $parts = explode('.', $path);
        $current = $array;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Build cache key.
     *
     * @param string $type
     * @param string $keyword
     * @param int    $location_code
     * @param string $language_code
     * @return string
     */
    protected function cache_key(string $type, string $keyword, int $location_code, string $language_code): string {
        return 'tmwseo_dataforseo_' . $type . '_' . md5(strtolower($keyword) . '|' . $location_code . '|' . strtolower($language_code));
    }

    /**
     * Generate fallback candidates.
     *
     * @param string $keyword
     * @return array
     */
    protected function generate_fallback_candidates(string $keyword): array {
        $candidates = [];
        $trimmed = trim($keyword);
        $words = preg_split('/\s+/', $trimmed);
        $word_count = is_array($words) ? count(array_filter($words, 'strlen')) : 0;

        if ($word_count >= 2 && $this->ends_with_models($trimmed)) {
            $first_word = $words[0] ?? '';
            if ($first_word !== '') {
                $candidates[] = $first_word;
            }
        }

        $cleaned = $this->strip_explicit_tokens($trimmed);
        if ($cleaned !== '' && $cleaned !== $trimmed) {
            $candidates[] = $cleaned;
        }

        $clean_words = preg_split('/\s+/', $cleaned);
        $clean_count = is_array($clean_words) ? count(array_filter($clean_words, 'strlen')) : 0;
        if ($clean_count >= 2 && $this->ends_with_models($cleaned)) {
            $first_word = $clean_words[0] ?? '';
            if ($first_word !== '') {
                $candidates[] = $first_word;
            }
        }

        return array_values(array_unique(array_filter($candidates, 'strlen')));
    }

    /**
     * Determine KD source label.
     *
     * @param string $original
     * @param string $used
     * @return string
     */
    protected function derive_kd_source(string $original, string $used): string {
        if ($original === $used) {
            return 'dataforseo';
        }

        $original_words = preg_split('/\s+/', strtolower($original));
        $used_lower = strtolower($used);

        if (!empty($original_words) && $used_lower === strtolower($original_words[0])) {
            return 'fallback_brand';
        }

        return 'fallback_cleaned';
    }

    /**
     * Check if keyword ends with model/models.
     *
     * @param string $keyword
     * @return bool
     */
    protected function ends_with_models(string $keyword): bool {
        return (bool) preg_match('/\bmodels?$/i', $keyword);
    }

    /**
     * Remove explicit tokens.
     *
     * @param string $keyword
     * @return string
     */
    protected function strip_explicit_tokens(string $keyword): string {
        $tokens = ['adult', 'sex', 'porn', 'xxx', 'nude', 'naked', 'erotic'];
        $pattern = '/\b(' . implode('|', array_map('preg_quote', $tokens)) . ')\b/i';
        $cleaned = preg_replace($pattern, '', $keyword);
        return trim(preg_replace('/\s+/', ' ', (string) $cleaned));
    }

    /**
     * Whether credentials exist.
     *
     * @return bool
     */
    protected function has_credentials(): bool {
        return self::is_configured();
    }
}
