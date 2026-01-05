<?php
/**
 * Provider Video Template helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

use TMW_SEO\Core;

/**
 * Videotemplate class.
 *
 * @package TMW_SEO
 */
class VideoTemplate {
    /** VIDEO: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_video(array $c): array {
        $name  = $c['name'] ?? '';
        $title = sprintf('%s Private Show Recording | LiveJasmin Video', $name);
        $title = mb_substr($title, 0, 60);
        $meta  = sprintf(
            'Watch %s\'s LiveJasmin show recording. See why fans searching "%s OnlyFans" discover LiveJasmin offers better live experiences.',
            $name,
            $name
        );

        $content_payload = \TMW_SEO\Content_Generator::generate_video($c);

        $keywords = array_values(array_unique(array_merge([
            $name . ' video',
            $name . ' recording',
            $name . ' show',
            $name . ' cam',
        ], $content_payload['keywords'])));

        return [
            'title'    => $title,
            'meta'     => $meta,
            'keywords' => $keywords,
            'content'  => $content_payload['content'],
        ];
    }
}
