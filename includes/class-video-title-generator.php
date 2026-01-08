<?php
/**
 * Video title generator.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Video Title Generator class.
 *
 * @package TMW_SEO
 */
class Video_Title_Generator {
    const TAG = '[TMW-OPENAI-TITLE]';

    /**
     * Boot hooks.
     *
     * @return void
     */
    public static function boot(): void {
        add_action('wp_ajax_tmwseo_generate_title_suggestions', [__CLASS__, 'ajax_generate_title_suggestions']);
        add_action('wp_ajax_tmwseo_apply_video_title', [__CLASS__, 'ajax_apply_video_title']);
    }

    /**
     * Generate title suggestions.
     *
     * @param int $post_id Post ID.
     * @return array|\WP_Error
     */
    public static function generate_title_suggestions(int $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return new \WP_Error('tmwseo_invalid_post', 'Invalid video post.');
        }
        if (!Core::is_video_post_type($post->post_type)) {
            return new \WP_Error('tmwseo_invalid_post_type', 'Title generation is only available for videos.');
        }

        $original_title = (string) $post->post_title;
        $model_name = Core::get_video_model_name($post);
        if ($model_name === '') {
            return new \WP_Error('tmwseo_missing_model', 'Model name is required for title suggestions.');
        }
        $tags = Core::first_looks($post_id);
        $tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tags), 'strlen')));
        $keywords = get_post_meta($post_id, '_tmwseo_video_tag_keywords', true);
        $keywords = is_array($keywords) ? $keywords : [];
        $keywords = array_values(array_unique(array_filter(array_map('sanitize_text_field', $keywords), 'strlen')));

        $prompt = self::build_prompt($original_title, $model_name, $tags, $keywords);

        $service = new OpenAI_Service();
        $response = $service->complete($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 200,
        ]);

        if (is_wp_error($response)) {
            Core::debug_log(self::TAG . ' generation error: ' . $response->get_error_message());
            return $response;
        }

        $titles = self::parse_title_suggestions($response);
        if (count($titles) < 5) {
            Core::debug_log(self::TAG . ' malformed response: ' . $response);
            return new \WP_Error('tmwseo_openai_malformed', 'OpenAI returned malformed title suggestions.');
        }

        $titles = array_slice($titles, 0, 5);
        update_post_meta($post_id, '_tmwseo_title_suggestions', wp_json_encode($titles));

        if (get_post_meta($post_id, '_tmwseo_original_title', true) === '') {
            update_post_meta($post_id, '_tmwseo_original_title', $original_title);
        }

        return $titles;
    }

    /**
     * Get cached suggestions.
     *
     * @param int $post_id Post ID.
     * @return array|null
     */
    public static function get_cached_suggestions(int $post_id): ?array {
        $raw = get_post_meta($post_id, '_tmwseo_title_suggestions', true);
        if (!$raw) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Apply selected title.
     *
     * @param int          $post_id Post ID.
     * @param int|string   $selection Index or custom title.
     * @return string|\WP_Error
     */
    public static function apply_selected_title(int $post_id, $selection) {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return new \WP_Error('tmwseo_invalid_post', 'Invalid video post.');
        }
        if (!Core::is_video_post_type($post->post_type)) {
            return new \WP_Error('tmwseo_invalid_post_type', 'Title updates are only available for videos.');
        }

        $title = '';
        $selected_meta = $selection;

        if (is_numeric($selection)) {
            $index = (int) $selection;
            $suggestions = self::get_cached_suggestions($post_id);
            if (!$suggestions || !isset($suggestions[$index])) {
                return new \WP_Error('tmwseo_missing_suggestion', 'Selected title suggestion is unavailable.');
            }
            $title = (string) $suggestions[$index];
            $selected_meta = $index;
        } elseif (is_string($selection)) {
            $title = $selection;
            $selected_meta = 'custom';
        }

        $title = sanitize_text_field($title);
        if ($title === '') {
            return new \WP_Error('tmwseo_empty_title', 'Title cannot be empty.');
        }

        if (get_post_meta($post_id, '_tmwseo_original_title', true) === '') {
            update_post_meta($post_id, '_tmwseo_original_title', $post->post_title);
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
        ], true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        update_post_meta($post_id, '_tmwseo_title_selected', $selected_meta);

        return $title;
    }

    /**
     * AJAX handler for generating title suggestions.
     *
     * @return void
     */
    public static function ajax_generate_title_suggestions(): void {
        check_ajax_referer('tmwseo_admin_nonce', 'nonce');

        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'No permission to edit this video.'], 403);
        }

        $titles = self::generate_title_suggestions($post_id);
        if (is_wp_error($titles)) {
            wp_send_json_error(['message' => $titles->get_error_message()], 500);
        }

        wp_send_json_success(['titles' => $titles]);
    }

    /**
     * AJAX handler for applying a selected title.
     *
     * @return void
     */
    public static function ajax_apply_video_title(): void {
        check_ajax_referer('tmwseo_admin_nonce', 'nonce');

        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'No permission to edit this video.'], 403);
        }

        $selection = $_POST['selection'] ?? '';
        $selection = is_string($selection) ? wp_unslash($selection) : $selection;

        $title = self::apply_selected_title($post_id, $selection);
        if (is_wp_error($title)) {
            wp_send_json_error(['message' => $title->get_error_message()], 500);
        }

        wp_send_json_success(['new_title' => $title]);
    }

    /**
     * Build OpenAI prompt.
     *
     * @param string $original_title Original title.
     * @param string $model_name Model name.
     * @param array  $tags Tags.
     * @param array  $keywords Keywords.
     * @return string
     */
    protected static function build_prompt(string $original_title, string $model_name, array $tags, array $keywords): string {
        $tags_text = !empty($tags) ? implode(', ', $tags) : 'cam model video';
        $keywords_text = !empty($keywords) ? implode(', ', $keywords) : 'exclusive live highlights';

        return sprintf(
            "Generate 5 SEO-optimized video titles for a cam model video.\n\n" .
            "Original filename/title: %s\n" .
            "Model name: %s\n" .
            "Video tags: %s\n" .
            "Keywords to consider: %s\n\n" .
            "Requirements for each title:\n" .
            "- Maximum 60 characters\n" .
            "- Must include the model name\n" .
            "- SEO-friendly and click-worthy\n" .
            "- Use power words like: Exclusive, Live, Hot, Private, Intimate, Stunning, Must-See\n" .
            "- Variety: make each suggestion different in style/approach\n" .
            "- No clickbait or misleading titles\n" .
            "- Adult-appropriate but not explicit\n\n" .
            "Output format: Return exactly 5 titles, one per line, numbered 1-5. No explanations.\n\n" .
            "Example format:\n" .
            "1. Title one here\n" .
            "2. Title two here\n" .
            "3. Title three here\n" .
            "4. Title four here\n" .
            "5. Title five here",
            $original_title,
            $model_name,
            $tags_text,
            $keywords_text
        );
    }

    /**
     * Parse title suggestions from response.
     *
     * @param string $response Response string.
     * @return array
     */
    protected static function parse_title_suggestions(string $response): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($response));
        $titles = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^\d+\.?\s*/', '', $line);
            $line = trim($line);
            if ($line !== '') {
                $titles[] = $line;
            }
        }

        return array_values(array_unique($titles));
    }
}
