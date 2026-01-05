<?php
/**
 * Google Suggest Client helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Google Suggest Client class.
 *
 * @package TMW_SEO
 */
class Google_Suggest_Client {
    protected $last_cached = false;

    /**
     * Handles cache key.
     *
     * @param string $query
     * @param string $hl
     * @param string $gl
     * @return string
     */
    public static function cache_key(string $query, string $hl = 'en', string $gl = 'us'): string {
        return 'tmwseo_suggest_' . md5($hl . '|' . $gl . '|' . $query);
    }

    /**
     * Handles was last cached.
     * @return bool
     */
    public function was_last_cached(): bool {
        return $this->last_cached;
    }

    /**
     * Handles fetch.
     *
     * @param string $query
     * @param string $hl
     * @param string $gl
     * @param array $options
     * @return mixed
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
        usleep(250000);

        $url = add_query_arg(
            [
                'client' => 'firefox',
                'q'      => $query,
                'hl'     => $hl,
                'gl'     => $gl,
            ],
            'https://suggestqueries.google.com/complete/search'
        );

        $accept_language = sanitize_text_field((string) ($options['accept_language'] ?? 'en-US,en;q=0.9'));
        $timeout = (int) ($options['timeout'] ?? 18);
        $user_agent = (string) ($options['user_agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36');

        $resp = wp_safe_remote_get(
            $url,
            [
                'timeout' => $timeout,
                'redirection' => 5,
                'headers' => [
                    'User-Agent'      => $user_agent,
                    'Accept'          => 'application/json,text/plain,*/*',
                    'Accept-Language' => $accept_language,
                ],
            ]
        );

        if (is_wp_error($resp)) {
            return new \WP_Error(
                'tmwseo_google_suggest_request',
                $resp->get_error_message(),
                [
                    'error' => $resp->get_error_message(),
                    'url'   => self::url_hint($url),
                ]
            );
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            $body = (string) wp_remote_retrieve_body($resp);
            return new \WP_Error(
                'tmwseo_google_suggest_http',
                'Unexpected HTTP response',
                [
                    'http_code'    => $code,
                    'body_snippet' => substr($body, 0, 300),
                    'url'          => self::url_hint($url),
                ]
            );
        }

        $body = (string) wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json[1]) || !is_array($json[1])) {
            return new \WP_Error(
                'tmwseo_google_suggest_parse',
                'Invalid suggest response',
                [
                    'http_code'    => $code,
                    'body_snippet' => substr($body, 0, 300),
                    'url'          => self::url_hint($url),
                ]
            );
        }

        $suggestions = array_values(array_unique(array_filter(array_map('strval', $json[1]), 'strlen')));
        set_transient($cache_key, $suggestions, 3 * DAY_IN_SECONDS);

        return $suggestions;
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
