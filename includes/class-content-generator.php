<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Content_Generator {
    const MIN_MODEL_WORDS = 600;
    const MIN_VIDEO_WORDS = 500;

    public static function generate_model(array $context): array {
        $model_id = (int) ($context['model_id'] ?? 0);
        $name     = $context['name'] ?? '';
        $tags     = $context['looks'] ?? [];
        $pair     = Keyword_Manager::competitor_pair(max(1, $model_id));
        $seed     = $name . '-' . $model_id;
        $layout   = absint(crc32($seed)) % 3;

        $intro      = Template_Engine::render(Template_Engine::pick('model-intros', $seed), self::base_context($name, $pair, $tags, $context));
        $bio        = Template_Engine::render(Template_Engine::pick('model-bios', $seed, 1), self::base_context($name, $pair, $tags, $context));
        $comparison = Template_Engine::render(Template_Engine::pick('model-comparisons', $seed), self::base_context($name, $pair, $tags, $context));
        $faqs_tpl   = Template_Engine::pick_faq('model-faqs', $seed, 5);
        $faqs_html  = self::render_faqs($faqs_tpl, $name, $pair);

        $related = self::render_related($context, $name);

        $sections = [
            0 => [$intro, $bio, $comparison, $faqs_html, $related],
            1 => [$intro, $comparison, $bio, $faqs_html, $related],
            2 => [$intro, $bio, $faqs_html, $comparison, $related],
        ];

        $content = implode("\n\n", $sections[$layout]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_MODEL_WORDS) {
            $content .= self::pad_content($content, self::MIN_MODEL_WORDS - $word_count, $name, 'model');
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'model');
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::MODEL_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('model-intros', $seed, 3), self::base_context($name, $pair, $tags, $context));
            $content   = implode("\n\n", [$alt_intro, $bio, $comparison, $faqs_html, $related]);
        }

        return [
            'content' => wp_kses_post($content),
            'keywords' => $density['keywords'],
            'pair' => $pair,
        ];
    }

    public static function generate_video(array $context): array {
        $video_id = (int) ($context['video_id'] ?? 0);
        $name     = $context['name'] ?? '';
        $tags     = $context['looks'] ?? [];
        $pair     = Keyword_Manager::competitor_pair(max(1, $video_id));
        $seed     = $name . '-' . $video_id;

        $intro      = Template_Engine::render(Template_Engine::pick('video-templates', $seed), self::base_context($name, $pair, $tags, $context));
        $details    = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 1), self::base_context($name, $pair, $tags, $context));
        $comparison = Template_Engine::render(Template_Engine::pick('model-comparisons', $seed), self::base_context($name, $pair, $tags, $context));
        $faqs_tpl   = Template_Engine::pick_faq('model-faqs', $seed, 4);
        $faqs_html  = self::render_faqs($faqs_tpl, $name, $pair);

        $content = implode("\n\n", [$intro, $details, $comparison, $faqs_html]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_VIDEO_WORDS) {
            $content .= self::pad_content($content, self::MIN_VIDEO_WORDS - $word_count, $name, 'video');
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'video');
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::VIDEO_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 5), self::base_context($name, $pair, $tags, $context));
            $content   = implode("\n\n", [$alt_intro, $details, $comparison, $faqs_html]);
        }

        return [
            'content' => wp_kses_post($content),
            'keywords' => $density['keywords'],
            'pair' => $pair,
        ];
    }

    protected static function base_context(string $name, array $pair, array $tags, array $context): array {
        $tag_text = !empty($tags) ? implode(', ', array_slice($tags, 0, 6)) : 'live webcam shows';
        return [
            'name'         => $name,
            'platform_a'   => $pair[0] ?? 'Chaturbate',
            'platform_b'   => $pair[1] ?? 'Stripchat',
            'live_brand'   => 'LiveJasmin',
            'site'         => $context['site'] ?? get_bloginfo('name'),
            'tags'         => $tag_text,
            'cta_url'      => $context['brand_url'] ?? '',
        ];
    }

    protected static function render_faqs(array $faqs, string $name, array $pair): string {
        $html = '<h2>FAQ</h2>';
        foreach ($faqs as $faq) {
            $q = Template_Engine::render($faq['q'], [
                'name' => $name,
                'platform_a' => $pair[0] ?? 'Chaturbate',
                'platform_b' => $pair[1] ?? 'Stripchat',
                'live_brand' => 'LiveJasmin',
            ]);
            $a = Template_Engine::render($faq['a'], [
                'name' => $name,
                'platform_a' => $pair[0] ?? 'Chaturbate',
                'platform_b' => $pair[1] ?? 'Stripchat',
                'live_brand' => 'LiveJasmin',
            ]);
            $html .= '<h3>' . esc_html($q) . '</h3><p>' . esc_html($a) . '</p>';
        }
        return $html;
    }

    protected static function render_related(array $context, string $name): string {
        $related_videos = $context['related_videos'] ?? [];
        $related_models = $context['related_models'] ?? [];
        $out  = '<h2>Related Content</h2>';
        $out .= '<p>More from ' . esc_html($name) . ':</p>';
        if (!empty($related_videos)) {
            $out .= '<ul>';
            foreach (array_slice($related_videos, 0, 6) as $video) {
                $out .= '<li>' . esc_html($video) . '</li>';
            }
            $out .= '</ul>';
        }
        if (!empty($related_models)) {
            $out .= '<p>Similar Models:</p><ul>';
            foreach (array_slice($related_models, 0, 4) as $model) {
                $out .= '<li>' . esc_html($model) . '</li>';
            }
            $out .= '</ul>';
        }
        return $out;
    }

    protected static function pad_content(string $content, int $missing_words, string $name, string $type): string {
        $sentences = [];
        $synonyms = ['performer', 'creator', 'entertainer', 'webcam model', 'cam performer', 'star'];
        $shows     = ['show', 'session', 'performance', 'broadcast', 'stream', 'private show'];
        for ($i = 0; $i < $missing_words; $i += 25) {
            $sentences[] = sprintf(
                '%s keeps the %s focused on live interaction and steady pacing, reminding fans that LiveJasmin sessions feel more personal than static OnlyFans posts or crowded %s rooms.',
                $name,
                $synonyms[array_rand($synonyms)],
                $type === 'video' ? 'recorded Chaturbate clips' : 'Chaturbate'
            );
        }
        return "\n\n" . implode(' ', $sentences);
    }
}
