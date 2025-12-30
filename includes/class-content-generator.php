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

        $base = self::base_context($name, $pair, $tags, $context);

        $intro      = Template_Engine::render(Template_Engine::pick('model-intros', $seed), $base);
        $bio        = Template_Engine::render(Template_Engine::pick('model-bios', $seed, 1), $base);
        $comparison = Template_Engine::render(Template_Engine::pick('model-comparisons', $seed), $base);
        $faqs_tpl   = Template_Engine::pick_faq('model-faqs', $seed, 5);
        $faqs_html  = self::render_faqs($faqs_tpl, $base);

        $related = self::render_related($context, $name);

        $sections = [
            0 => [$intro, $bio, $comparison, $faqs_html, $related],
            1 => [$intro, $comparison, $bio, $faqs_html, $related],
            2 => [$intro, $bio, $faqs_html, $comparison, $related],
        ];

        $content = implode("\n\n", $sections[$layout]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_MODEL_WORDS) {
            $content = self::pad_content($content, self::MIN_MODEL_WORDS - $word_count, $name, 'model');
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'model');
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::MODEL_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('model-intros', $seed, 3), $base);
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

        $base = self::base_context($name, $pair, $tags, $context);

        $intro      = Template_Engine::render(Template_Engine::pick('video-templates', $seed), $base);
        $details    = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 1), $base);
        $comparison = Template_Engine::render(Template_Engine::pick('model-comparisons', $seed), $base);
        $faqs_tpl   = Template_Engine::pick_faq('model-faqs', $seed, 4);
        $faqs_html  = self::render_faqs($faqs_tpl, $base);

        $content = implode("\n\n", [$intro, $details, $comparison, $faqs_html]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_VIDEO_WORDS) {
            $content = self::pad_content($content, self::MIN_VIDEO_WORDS - $word_count, $name, 'video');
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'video');
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::VIDEO_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 5), $base);
            $content   = implode("\n\n", [$alt_intro, $details, $comparison, $faqs_html]);
        }

        return [
            'content' => wp_kses_post($content),
            'keywords' => $density['keywords'],
            'pair' => $pair,
        ];
    }

    protected static function base_context(string $name, array $pair, array $tags, array $context): array {
        $safe_tags = Core::get_safe_model_tag_keywords($tags);
        $tag_slice = array_slice($safe_tags, 0, max(4, min(6, count($safe_tags))));
        $tag_text  = !empty($tag_slice) ? implode(', ', $tag_slice) : 'live webcam shows';
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

    protected static function render_faqs(array $faqs, array $base): string {
        $html = '<h2>FAQ</h2>';
        $used_questions = [];
        foreach ($faqs as $faq) {
            $q = Template_Engine::render($faq['q'], $base);
            $q_key = strtolower(trim($q));
            if ($q_key === '' || isset($used_questions[$q_key])) {
                continue;
            }
            $used_questions[$q_key] = true;
            $a = Template_Engine::render($faq['a'], $base);
            $html .= '<h3>' . esc_html($q) . '</h3><p>' . esc_html($a) . '</p>';
        }
        return $html;
    }

    protected static function render_related(array $context, string $name): string {
        $out = '<h2>Related Content</h2>';

        $related_videos = [];
        if (!empty($context['model_id'])) {
            $related_videos = get_posts([
                'post_type'      => Core::video_post_types(),
                'posts_per_page' => 6,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'   => '_tmwseo_model_id',
                        'value' => (int) $context['model_id'],
                    ],
                ],
            ]);
        }

        $related_models = [];
        if (!empty($context['looks'])) {
            $related_models = get_posts([
                'post_type'      => Core::MODEL_PT,
                'posts_per_page' => 4,
                'post_status'    => 'publish',
                'post__not_in'   => !empty($context['model_id']) ? [(int) $context['model_id']] : [],
                'orderby'        => 'rand',
            ]);
        }

        if (!empty($related_videos)) {
            $out .= '<h3>More from ' . esc_html($name) . '</h3>';
            $out .= '<ul>';
            foreach (array_slice($related_videos, 0, 6) as $video) {
                $url   = get_permalink($video->ID);
                $title = get_the_title($video->ID);
                $safe_title = Core::sanitize_sfw_text((string) $title, '');
                if ($safe_title === '') {
                    continue;
                }
                $safe_title = Core::sanitize_sfw_text($safe_title, 'Watch now');
                $out  .= '<li><a href="' . esc_url($url) . '">' . esc_html($safe_title) . '</a></li>';
            }
            $out .= '</ul>';
        }

        if (!empty($related_models)) {
            $out .= '<h3>Similar Models</h3>';
            $out .= '<ul>';
            foreach ($related_models as $model) {
                $url   = get_permalink($model->ID);
                $title = get_the_title($model->ID);
                $safe_title = Core::sanitize_sfw_text((string) $title, '');
                if ($safe_title === '') {
                    continue;
                }
                $safe_title = Core::sanitize_sfw_text($safe_title, 'Watch now');
                $out  .= '<li><a href="' . esc_url($url) . '">' . esc_html($safe_title) . '</a></li>';
            }
            $out .= '</ul>';
        }

        return $out;
    }

    protected static function pad_content(string $content, int $missing_words, string $name, string $type): string {
        if ($missing_words < 50) {
            return $content;
        }

        $natural_additions = [
            "Community members consistently praise {$name} for maintaining authentic energy across sessions. Rather than following a rigid script, {$name} adapts to each room's vibe, creating experiences that feel personal rather than performative. This flexibility explains why regular viewers return for multiple shows.",

            "Live streaming creates spontaneous moments that recorded clips rarely capture. Real-time responses and the ability to shape the flow of a show help fans feel involved while watching {$name} perform.",

            "Viewers who discover {$name} through search often express surprise at how much more engaging live sessions feel. The ability to make requests, receive immediate acknowledgment, and influence the show's direction transforms passive viewing into active participation.",

            "Technical quality matters in adult entertainment, and reliable streaming with stable framerates, clear audio, and good lighting creates premium viewing experiences that keep audiences coming back.",

            "Regular attendees of {$name}'s shows develop rapport over time, with inside jokes and callbacks that create community. This ongoing relationship dynamic differs from one-way content feeds where creators post and subscribers consume without dialogue.",
        ];

        shuffle($natural_additions);

        $added         = '';
        $current_added = 0;

        foreach ($natural_additions as $addition) {
            if ($current_added >= $missing_words) {
                break;
            }

            $addition = str_replace('{$name}', $name, $addition);
            $added   .= "\n\n<p>" . $addition . '</p>';
            $current_added += str_word_count($addition);
        }

        return $content . $added;
    }
}
