<?php
/**
 * Provider Template helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

/**
 * Thin wrapper that delegates to dedicated video/model template classes.
 *
 * Keeps the original public API (Template::generate_video / generate_model).
 *
 * @package TMW_SEO
 */

// Make sure the split template classes are loaded.
require_once __DIR__ . '/class-provider-video-template.php';
require_once __DIR__ . '/class-provider-model-template.php';

/**
 * Template class.
 *
 * @package TMW_SEO
 */
class Template {

    /**
     * Generates video content using templates.
     *
     * @param array $ctx Template context.
     * @return array
     */
    public function generate_video(array $ctx): array {
        try {
            $video = new VideoTemplate();
            return $video->generate_video($ctx);
        } catch (\Throwable $e) {
            if (defined('TMW_DEBUG') && TMW_DEBUG) {
                error_log('[TMW-SEO] Template::generate_video error: ' . $e->getMessage());
            }
            return [
                'title' => '',
                'meta' => '',
                'keywords' => [],
                'content' => '',
            ];
        }
    }

    /**
     * Generates model content using templates.
     *
     * @param array $ctx Template context.
     * @return array
     */
    public function generate_model(array $ctx): array {
        try {
            $model = new ModelTemplate();
            return $model->generate_model($ctx);
        } catch (\Throwable $e) {
            if (defined('TMW_DEBUG') && TMW_DEBUG) {
                error_log('[TMW-SEO] Template::generate_model error: ' . $e->getMessage());
            }
            return [
                'title' => '',
                'meta' => '',
                'keywords' => [],
                'content' => '',
            ];
        }
    }
}
