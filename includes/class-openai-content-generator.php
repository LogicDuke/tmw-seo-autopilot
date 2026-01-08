<?php
/**
 * OpenAI content generator for model bios.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Content Generator class.
 *
 * @package TMW_SEO
 */
class OpenAI_Content_Generator {
    const TAG = '[TMW-OPENAI-CONTENT]';

    /**
     * Boot hooks.
     *
     * @return void
     */
    public static function boot(): void {
        add_action('wp_ajax_tmwseo_generate_model_content', [__CLASS__, 'ajax_generate_model_content']);
    }

    /**
     * Generate model content.
     *
     * @param int $post_id Post ID.
     * @return string|\WP_Error
     */
    public static function generate_model_content(int $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return new \WP_Error('tmwseo_invalid_post', 'Invalid model.');
        }
        if ($post->post_type !== Core::MODEL_PT) {
            return new \WP_Error('tmwseo_invalid_post_type', 'Content generation is only available for models.');
        }

        $model_name = trim((string) $post->post_title);
        if ($model_name === '') {
            return new \WP_Error('tmwseo_missing_name', 'Model name is required for content generation.');
        }
        $keywords = get_post_meta($post_id, '_tmwseo_extras_list', true);
        $keywords = is_array($keywords) ? $keywords : [];
        $keywords = array_values(array_unique(array_filter(array_map('sanitize_text_field', $keywords), 'strlen')));

        $looks = Core::first_looks($post_id);
        $tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', $looks), 'strlen')));

        $prompt = self::build_prompt($model_name, $keywords, $tags);

        $service = new OpenAI_Service();
        $response = $service->complete($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 500,
        ]);

        if (is_wp_error($response)) {
            Core::debug_log(self::TAG . ' generation error: ' . $response->get_error_message());
            return $response;
        }

        $content = wp_kses_post(trim($response));
        if ($content === '') {
            return new \WP_Error('tmwseo_empty_content', 'OpenAI returned empty content.');
        }

        update_post_meta($post_id, '_tmwseo_ai_content', $content);
        update_post_meta($post_id, '_tmwseo_ai_generated_at', current_time('mysql'));

        return $content;
    }

    /**
     * Check if content is locked.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function is_content_locked(int $post_id): bool {
        return (bool) get_post_meta($post_id, '_tmwseo_ai_content_locked', true);
    }

    /**
     * Lock content.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public static function lock_content(int $post_id): void {
        update_post_meta($post_id, '_tmwseo_ai_content_locked', 1);
    }

    /**
     * Unlock content.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public static function unlock_content(int $post_id): void {
        delete_post_meta($post_id, '_tmwseo_ai_content_locked');
    }

    /**
     * AJAX handler for generating model content.
     *
     * @return void
     */
    public static function ajax_generate_model_content(): void {
        check_ajax_referer('tmwseo_admin_nonce', 'nonce');

        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'No permission to edit this model.'], 403);
        }

        if (self::is_content_locked($post_id)) {
            wp_send_json_error(['message' => 'Content is locked and cannot be regenerated.'], 423);
        }

        $content = self::generate_model_content($post_id);
        if (is_wp_error($content)) {
            wp_send_json_error(['message' => $content->get_error_message()], 500);
        }

        $timestamp = get_post_meta($post_id, '_tmwseo_ai_generated_at', true);
        wp_send_json_success([
            'content' => $content,
            'timestamp' => $timestamp ? date('Y-m-d H:i', strtotime($timestamp)) : '',
        ]);
    }

    /**
     * Build OpenAI prompt.
     *
     * @param string $model_name Model name.
     * @param array  $keywords Keywords.
     * @param array  $tags Tags.
     * @return string
     */
    protected static function build_prompt(string $model_name, array $keywords, array $tags): string {
        $keywords_text = !empty($keywords) ? implode(', ', $keywords) : 'live cam model';
        $tags_text = !empty($tags) ? implode(', ', $tags) : 'webcam performer';

        return sprintf(
            "You are writing content for a cam model directory website.\n\n" .
            "Model Name: %s\n" .
            "Keywords to include naturally: %s\n" .
            "Model attributes: %s\n\n" .
            "Write a 2-paragraph introduction and bio for this cam model profile page.\n\n" .
            "Requirements:\n" .
            "- Paragraph 1 (intro): 3-4 sentences introducing the model, mention 2-3 keywords naturally\n" .
            "- Paragraph 2 (bio): 4-5 sentences about personality and show style, include remaining keywords\n" .
            "- Tone: Enticing, professional, adult-appropriate but not explicit\n" .
            "- Include all provided keywords at least once, woven naturally into the text\n" .
            "- Do NOT use phrases like \"Whether you're looking for\" or \"Look no further\"\n" .
            "- Do NOT use generic filler phrases\n" .
            "- Make it unique and specific to this model's attributes\n" .
            "- Total length: 150-200 words\n\n" .
            "Output format: Return only the two paragraphs, separated by a blank line. No headers, no labels.",
            $model_name,
            $keywords_text,
            $tags_text
        );
    }
}
