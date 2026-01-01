<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Google_Suggest_Client {
    protected $last_cached = false;

    public static function cache_key(string $query, string $hl = 'en', string $gl = 'us'): string {
        return 'tmwseo_suggest_' . md5($hl . '|' . $gl . '|' . $query);
    }

    public function was_last_cached(): bool {
        return $this->last_cached;
    }

    public function fetch(string $query, string $hl = 'en', string $gl = 'us') {
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

        $resp = wp_remote_get(
            $url,
            [
                'timeout' => 12,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (WordPress; TMW SEO Autopilot)',
                    'Accept'     => 'application/json',
                ],
            ]
        );

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new \WP_Error('tmwseo_google_suggest_http', 'Unexpected HTTP response', ['http_code' => $code]);
        }

        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($json) || !isset($json[1]) || !is_array($json[1])) {
            return new \WP_Error('tmwseo_google_suggest_parse', 'Invalid suggest response', ['http_code' => $code]);
        }

        $suggestions = array_values(array_unique(array_filter(array_map('strval', $json[1]), 'strlen')));
        set_transient($cache_key, $suggestions, 12 * HOUR_IN_SECONDS);

        return $suggestions;
    }
}
