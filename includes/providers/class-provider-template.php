<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

/**
 * Thin wrapper that delegates to dedicated video/model template classes.
 * This keeps the original public API (Template::generate_video / generate_model)
 * so the rest of the plugin continues to work unchanged.
 */

// Make sure the split template classes are loaded.
require_once __DIR__ . '/class-provider-video-template.php';
require_once __DIR__ . '/class-provider-model-template.php';

class Template {

    /**
     * VIDEO: returns ['title','meta','keywords'=>[5],'content']
     *
     * @param array $ctx
     * @return array
     */
    public function generate_video(array $ctx): array {
        $video = new VideoTemplate();
        return $video->generate_video($ctx);
    }

    /**
     * MODEL: returns ['title','meta','keywords'=>[5],'content']
     *
     * @param array $ctx
     * @return array
     */
    public function generate_model(array $ctx): array {
        $model = new ModelTemplate();
        return $model->generate_model($ctx);
    }
}
