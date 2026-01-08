<?php
/**
 * OpenAI service wrapper.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

use TMW_SEO\Providers\OpenAI;

/**
 * OpenAI Service class.
 *
 * @package TMW_SEO
 */
class OpenAI_Service {
    const TAG = '[TMW-OPENAI]';

    /**
     * @var string
     */
    protected $api_key;

    /**
     * @var string
     */
    protected $model;

    /**
     * @var int
     */
    protected $max_tokens;

    /**
     * OpenAI_Service constructor.
     */
    public function __construct() {
        $this->api_key = $this->resolve_api_key();
        $this->model = 'gpt-4o-mini';
        $this->max_tokens = 500;
    }

    /**
     * Check if API key is configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->api_key !== '';
    }

    /**
     * Complete a prompt.
     *
     * @param string $prompt Prompt string.
     * @param array  $options Optional overrides.
     * @return string|\WP_Error
     */
    public function complete(string $prompt, array $options = []) {
        if (!$this->is_configured()) {
            return new \WP_Error('tmwseo_openai_missing_key', 'OpenAI API key is not configured.');
        }

        $model = isset($options['model']) ? sanitize_text_field($options['model']) : $this->model;
        $max_tokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : $this->max_tokens;
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;

        $response = OpenAI::request([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ], 30);

        if (is_wp_error($response)) {
            error_log(self::TAG . ' request error: ' . $response->get_error_message());
            return new \WP_Error('tmwseo_openai_request_failed', 'OpenAI request failed. Please try again.');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($status === 429) {
            error_log(self::TAG . ' rate limit hit: ' . $body);
            return new \WP_Error('tmwseo_openai_rate_limited', 'OpenAI rate limit reached. Please wait and try again.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $payload['error']['message'] ?? 'Unexpected OpenAI error.';
            error_log(self::TAG . ' API error: ' . $message);
            return new \WP_Error('tmwseo_openai_api_error', 'OpenAI request failed: ' . $message);
        }

        $text = $payload['choices'][0]['message']['content'] ?? '';
        if ($text === '') {
            error_log(self::TAG . ' empty response body: ' . $body);
            return new \WP_Error('tmwseo_openai_empty_response', 'OpenAI did not return any content.');
        }

        return trim((string) $text);
    }

    /**
     * Resolve API key.
     *
     * @return string
     */
    protected function resolve_api_key(): string {
        if (defined('TMW_SEO_OPENAI')) {
            return (string) TMW_SEO_OPENAI;
        }

        if (defined('OPENAI_API_KEY')) {
            return (string) OPENAI_API_KEY;
        }

        return (string) get_option('tmwseo_openai_api_key', '');
    }
}
