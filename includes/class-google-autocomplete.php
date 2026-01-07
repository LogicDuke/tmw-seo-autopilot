<?php
/**
 * Google Autocomplete client helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Google Autocomplete client class.
 *
 * @package TMW_SEO
 */
class Google_Autocomplete {
    protected $last_cached = false;

    /**
     * Handles was last cached.
     *
     * @return bool
     */
    public function was_last_cached(): bool {
        return $this->last_cached;
    }

    /**
     * Handles cache key.
     *
     * @param string $query
     * @param string $hl
     * @param string $gl
     * @return string
     */
    public static function cache_key(string $query, string $hl = 'en', string $gl = 'us'): string {
        return 'tmwseo_autosuggest_' . md5($hl . '|' . $gl . '|' . $query);
    }

    /**
     * Fetch Google Autocomplete suggestions with retry/backoff and caching.
     *
     * @param string $query
     * @param string $hl
     * @param string $gl
     * @param array $options
     * @return array|\WP_Error
     */
    public function fetch(string $query, string $hl = 'en', string $gl = 'us', array $options = []) {
        $query = trim($query);
        $hl = sanitize_text_field($hl ?: 'en');
        $gl = sanitize_text_field($gl ?: 'us');

        if ($query === '') {
            $this->last_cached = true;
            return [];
        }

        $cache_key = self::cache_key($query, $hl, $gl);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            $this->last_cached = true;
            return $cached;
        }

        $this->last_cached = false;

        $accept_language = sanitize_text_field((string) ($options['accept_language'] ?? ''));
        if ($accept_language === '') {
            $accept_language = self::locale_accept_language();
        }
        $timeout = (int) ($options['timeout'] ?? 12);
        $user_agent = (string) ($options['user_agent'] ?? sprintf('Mozilla/5.0 (compatible; TMWSEO-Autofill/1.0; +%s)', home_url('/')));
        $max_retries = max(0, (int) ($options['max_retries'] ?? 3));
        $backoff_ms = $options['backoff_ms'] ?? [300, 900, 1800];

        $endpoints = [
            'https://suggestqueries.google.com/complete/search',
            'https://clients1.google.com/complete/search',
            'https://www.google.com/complete/search',
        ];

        $last_error = null;
        foreach ($endpoints as $endpoint) {
            $attempt = 0;
            while ($attempt <= $max_retries) {
                $url = add_query_arg(
                    [
                        'client' => 'firefox',
                        'q'      => $query,
                        'hl'     => $hl,
                        'gl'     => $gl,
                    ],
                    $endpoint
                );

                $resp = wp_safe_remote_get(
                    $url,
                    [
                        'timeout'     => $timeout,
                        'redirection' => 3,
                        'headers'     => [
                            'User-Agent'      => $user_agent,
                            'Accept'          => 'application/json,text/plain,*/*',
                            'Accept-Language' => $accept_language,
                        ],
                    ]
                );

                if (is_wp_error($resp)) {
                    $last_error = new \WP_Error(
                        'tmwseo_google_autocomplete_request',
                        $resp->get_error_message(),
                        [
                            'error'     => $resp->get_error_message(),
                            'url'       => self::url_hint($url),
                            'retriable' => true,
                        ]
                    );
                    if ($attempt < $max_retries) {
                        self::sleep_backoff($backoff_ms, $attempt);
                        $attempt++;
                        continue;
                    }
                    return $last_error;
                }

                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
                if ($code < 200 || $code >= 300) {
                    $is_retriable = in_array($code, [429, 500, 502, 503, 504], true);
                    $last_error = new \WP_Error(
                        'tmwseo_google_autocomplete_http',
                        'Unexpected HTTP response',
                        [
                            'http_code'    => $code,
                            'body_snippet' => substr($body, 0, 200),
                            'url'          => self::url_hint($url),
                            'retriable'    => $is_retriable,
                        ]
                    );

                    if ($is_retriable && $attempt < $max_retries) {
                        self::sleep_backoff($backoff_ms, $attempt);
                        $attempt++;
                        continue;
                    }

                    break;
                }

                $json = json_decode($body, true);
                if (!is_array($json) || !isset($json[1]) || !is_array($json[1])) {
                    return new \WP_Error(
                        'tmwseo_google_autocomplete_parse',
                        'Invalid suggest response',
                        [
                            'http_code'    => $code,
                            'body_snippet' => substr($body, 0, 200),
                            'url'          => self::url_hint($url),
                            'retriable'    => false,
                        ]
                    );
                }

                $suggestions = array_values(array_unique(array_filter(array_map('strval', $json[1]), 'strlen')));
                set_transient($cache_key, $suggestions, 12 * HOUR_IN_SECONDS);
                return $suggestions;
            }
        }

        return $last_error ?: new \WP_Error(
            'tmwseo_google_autocomplete_failed',
            'Autocomplete request failed.',
            [
                'retriable' => false,
            ]
        );
    }

    /**
     * Handles locale to accept language.
     *
     * @return string
     */
    protected static function locale_accept_language(): string {
        $locale = get_locale();
        $locale = str_replace('_', '-', $locale ?: 'en-US');
        $primary = strtolower(substr($locale, 0, 2));
        return $locale . ',' . $primary . ';q=0.9,en;q=0.8';
    }

    /**
     * Sleep with exponential backoff + jitter.
     *
     * @param array $backoff_ms
     * @param int $attempt
     * @return void
     */
    protected static function sleep_backoff(array $backoff_ms, int $attempt): void {
        $base = (int) ($backoff_ms[min($attempt, count($backoff_ms) - 1)] ?? 300);
        $jitter = rand(0, 250);
        usleep((int) (($base + $jitter) * 1000));
    }

    /**
     * Handles url hint.
     *
     * @param string $url
     * @return string
     */
    protected static function url_hint(string $url): string {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        $path = $parts['path'] ?? '';
        return $parts['host'] . $path;
    }
}
