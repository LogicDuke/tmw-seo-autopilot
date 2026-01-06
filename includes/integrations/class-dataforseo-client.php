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
    const API_ENDPOINT = 'https://api.dataforseo.com/v3/dataforseo_labs/google/bulk_keyword_difficulty/live';
    const MAX_BATCH = 1000;
    const CACHE_TTL = WEEK_IN_SECONDS; // 7 days cache.

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
     * Fetch bulk keyword difficulty scores.
     *
     * @param array  $keywords       Keyword list.
     * @param int    $location_code  Location code.
     * @param string $language_code  Language code.
     *
     * @return array|\WP_Error
     */
    public static function bulk_keyword_difficulty(array $keywords, int $location_code, string $language_code) {
        if (!self::is_configured()) {
            return new \WP_Error('tmwseo_dataforseo_missing_creds', 'DataForSEO is not configured.');
        }

        $login    = trim((string) get_option('tmwseo_dataforseo_login', ''));
        $password = trim((string) get_option('tmwseo_dataforseo_password', ''));
        $location_code = $location_code > 0 ? $location_code : 2840;
        $language_code = $language_code !== '' ? $language_code : 'en';

        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords), 'strlen')));
        if (empty($keywords)) {
            return [];
        }

        $results = [];
        $pending = [];
        foreach ($keywords as $keyword) {
            $cache_key = self::cache_key($keyword, $location_code, $language_code);
            $cached    = get_transient($cache_key);
            if ($cached !== false && is_array($cached)) {
                $results[$keyword] = $cached;
            } else {
                $pending[] = $keyword;
            }
        }

        if (empty($pending)) {
            return $results;
        }

        $batches = array_chunk($pending, self::MAX_BATCH);
        foreach ($batches as $batch) {
            $body = [
                [
                    'keywords'      => array_values($batch),
                    'location_code' => $location_code,
                    'language_code' => $language_code,
                ],
            ];

            $attempts = 0;
            $response = null;
            do {
                $attempts++;
                $response = wp_remote_post(
                    self::API_ENDPOINT,
                    [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
                            'Content-Type'  => 'application/json',
                            'Accept'        => 'application/json',
                        ],
                        'body'    => wp_json_encode($body),
                        'timeout' => 20,
                    ]
                );

                $status = (int) wp_remote_retrieve_response_code($response);
                if (in_array($status, [429, 503], true) && $attempts < 3) {
                    sleep($attempts); // simple backoff 1s,2s
                    continue;
                }
                break;
            } while ($attempts < 3);

            if (is_wp_error($response)) {
                return new \WP_Error('tmwseo_dataforseo_http', $response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body_raw = wp_remote_retrieve_body($response);
            $json = json_decode($body_raw, true);

            if ($status < 200 || $status >= 300) {
                $snippet = is_string($body_raw) ? substr($body_raw, 0, 200) : '';
                return new \WP_Error('tmwseo_dataforseo_status', sprintf('HTTP %d: %s', $status, $snippet));
            }

            if (!is_array($json)) {
                return new \WP_Error('tmwseo_dataforseo_json', 'Invalid JSON response from DataForSEO.');
            }

            $tasks = $json['tasks'] ?? [];
            foreach ($tasks as $task) {
                if (empty($task['result']) || !is_array($task['result'])) {
                    continue;
                }
                foreach ($task['result'] as $result_item) {
                    $items = $result_item['items'] ?? [];
                    if (!is_array($items)) {
                        continue;
                    }
                    foreach ($items as $item) {
                        $keyword = trim((string) ($item['keyword'] ?? ''));
                        if ($keyword === '') {
                            continue;
                        }
                        $kd = isset($item['keyword_difficulty']) ? (float) $item['keyword_difficulty'] : null;
                        if ($kd === null && isset($item['keyword_difficulty_index'])) {
                            $kd = (float) $item['keyword_difficulty_index'];
                        }
                        if ($kd === null) {
                            // Cache a negative result to avoid repeatedly requesting missing KD values.
                            $normalized = [
                                'kd' => null,
                                'competition_level' => null,
                                'last_updated' => time(),
                            ];
                            $results[$keyword] = $normalized;
                            set_transient(self::cache_key($keyword, $location_code, $language_code), $normalized, self::CACHE_TTL);
                            continue;
                        }
                        $normalized = [
                            'kd' => max(0, min(100, (int) round($kd))),
                            'competition_level' => isset($item['competition_level']) ? (string) $item['competition_level'] : null,
                            'last_updated' => time(),
                        ];
                        $results[$keyword] = $normalized;
                        set_transient(self::cache_key($keyword, $location_code, $language_code), $normalized, self::CACHE_TTL);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Cache key helper.
     *
     * @param string $keyword
     * @param int    $location_code
     * @param string $language_code
     * @return string
     */
    protected static function cache_key(string $keyword, int $location_code, string $language_code): string {
        return 'tmwseo_kd_' . md5(strtolower($keyword) . '|' . $location_code . '|' . strtolower($language_code));
    }
}
