<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Serper_Client {
    public static function search(string $api_key, string $query, string $gl = 'us', string $hl = 'en', int $num = 10): array {
        $api_key = trim($api_key);
        $query   = trim($query);
        if ($api_key === '' || $query === '') {
            return ['error' => 'Missing API key or query'];
        }

        $body = [
            'q'  => $query,
            'gl' => $gl ?: 'us',
            'hl' => $hl ?: 'en',
            'num' => max(1, min(20, $num)),
        ];

        $resp = wp_remote_post(
            'https://google.serper.dev/search',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-KEY'    => $api_key,
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($resp)) {
            return ['error' => $resp->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return ['error' => 'HTTP ' . $code];
        }

        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($json)) {
            return ['error' => 'Invalid response'];
        }

        return ['data' => $json];
    }

    public static function extract_keywords(array $data): array {
        $keywords = [];

        if (!empty($data['relatedSearches']) && is_array($data['relatedSearches'])) {
            foreach ($data['relatedSearches'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $phrase = $item['query'] ?? ($item['text'] ?? '');
                $phrase = trim((string) $phrase);
                if ($phrase !== '') {
                    $keywords[] = $phrase;
                }
            }
        }

        if (!empty($data['peopleAlsoAsk']) && is_array($data['peopleAlsoAsk'])) {
            foreach ($data['peopleAlsoAsk'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $phrase = $item['question'] ?? '';
                $phrase = trim((string) $phrase);
                if ($phrase !== '') {
                    $keywords[] = $phrase;
                }
            }
        }

        return array_values(array_unique(array_filter($keywords, 'strlen')));
    }
}
