<?php
/**
 * Content Generator helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Content Generator class.
 *
 * @package TMW_SEO
 */
class Content_Generator {
    const MIN_MODEL_WORDS = 600;
    const MIN_VIDEO_WORDS = 500;

    /**
     * Generates model.
     *
     * @param array $context
     * @return array
     */
    public static function generate_model(array $context): array {
        $model_id = (int) ($context['model_id'] ?? 0);
        $name     = $context['name'] ?? '';
        $tags     = $context['looks'] ?? [];
        $pair     = Keyword_Manager::competitor_pair(max(1, $model_id));
        $seed     = $name . '-' . $model_id;
        $layout   = absint(crc32($seed)) % 3;

        $base = self::base_context($name, $pair, $tags, $context);

        $intro_slug = self::pick_platform_template_slug('model-intros', $base['active_platforms'] ?? []);
        $faq_slug   = self::pick_platform_template_slug('model-faqs', $base['active_platforms'] ?? []);
        $intro      = Template_Engine::render(Template_Engine::pick($intro_slug, $seed), $base);
        $bio        = Template_Engine::render(Template_Engine::pick('model-bios', $seed, 1), $base);
        $intro      = '<p>' . $intro . '</p>';
        $bio        = '<p>' . $bio . '</p>';
        $focus_block = self::render_focus_blocks($base, $name);
        $kw_coverage = self::render_rankmath_keyword_coverage($base['rankmath_additional_keywords'] ?? [], $name);
        $comparison = self::build_platform_comparison($model_id, $name);
        $faqs_tpl   = Template_Engine::pick_faq($faq_slug, $seed, 5);
        $faqs_html  = self::render_faqs($faqs_tpl, $base, $base['longtail_keywords']);
        $longtail_block = self::render_longtail_section($base['longtail_keywords'], $name);

        $related = self::render_related($context, $name);

        $sections = [
            0 => [$intro, $focus_block, $bio, $kw_coverage, $longtail_block, $comparison, $faqs_html, $related],
            1 => [$intro, $focus_block, $comparison, $longtail_block, $bio, $kw_coverage, $faqs_html, $related],
            2 => [$intro, $focus_block, $bio, $kw_coverage, $faqs_html, $longtail_block, $comparison, $related],
        ];

        $content = implode("\n\n", $sections[$layout]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_MODEL_WORDS) {
            $content = self::pad_content($content, self::MIN_MODEL_WORDS - $word_count, $name, 'model', [
                $base['extra_focus_1'] ?? '',
                $base['extra_focus_2'] ?? '',
                $base['extra_focus_3'] ?? '',
                $base['extra_focus_4'] ?? '',
            ]);
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'model', $base['active_platforms'] ?? []);
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::MODEL_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('model-intros', $seed, 3), $base);
            $alt_intro = '<p>' . $alt_intro . '</p>';
            $content   = implode("\n\n", [$alt_intro, $bio, $comparison, $faqs_html, $related]);
        }

        $content = self::split_long_paragraphs($content);
        $final_word_count = str_word_count(strip_tags($content));
        if ($final_word_count < self::MIN_MODEL_WORDS) {
            $content = self::pad_content($content, self::MIN_MODEL_WORDS - $final_word_count + 20, $name, 'model', [
                $base['extra_focus_1'] ?? '',
                $base['extra_focus_2'] ?? '',
                $base['extra_focus_3'] ?? '',
                $base['extra_focus_4'] ?? '',
            ]);
        }

        return [
            'content' => wp_kses_post($content),
            'keywords' => $density['keywords'],
            'pair' => $pair,
        ];
    }

    /**
     * Generates video.
     *
     * @param array $context
     * @return array
     */
    public static function generate_video(array $context): array {
        $video_id = (int) ($context['video_id'] ?? 0);
        $name     = $context['name'] ?? '';
        $tags     = $context['looks'] ?? [];
        $pair     = Keyword_Manager::competitor_pair(max(1, $video_id));
        $seed     = $name . '-' . $video_id;

        $base = self::base_context($name, $pair, $tags, $context);

        $intro      = Template_Engine::render(Template_Engine::pick('video-templates', $seed), $base);
        $details    = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 1), $base);
        $intro      = '<p>' . $intro . '</p>';
        $details    = '<p>' . $details . '</p>';
        $comparison = self::build_platform_comparison((int) ($context['model_id'] ?? 0), $name);
        $faq_slug   = self::pick_platform_template_slug('model-faqs', $base['active_platforms'] ?? []);
        $faqs_tpl   = Template_Engine::pick_faq($faq_slug, $seed, 4);
        $faqs_html  = self::render_faqs($faqs_tpl, $base, $base['longtail_keywords']);

        $content = implode("\n\n", [$intro, $details, $comparison, $faqs_html]);
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < self::MIN_VIDEO_WORDS) {
            $content = self::pad_content($content, self::MIN_VIDEO_WORDS - $word_count, $name, 'video', [
                $base['extra_focus_1'] ?? '',
                $base['extra_focus_2'] ?? '',
                $base['extra_focus_3'] ?? '',
                $base['extra_focus_4'] ?? '',
            ]);
        }

        $density = Keyword_Manager::apply_density($content, $name, $pair, 'video', $base['active_platforms'] ?? []);
        $content = $density['content'];

        $similarity = Uniqueness_Checker::similarity_score($content, Core::VIDEO_PT);
        if ($similarity > 70) {
            $alt_intro = Template_Engine::render(Template_Engine::pick('video-templates', $seed, 5), $base);
            $alt_intro = '<p>' . $alt_intro . '</p>';
            $content   = implode("\n\n", [$alt_intro, $details, $comparison, $faqs_html]);
        }

        $content = self::split_long_paragraphs($content);

        return [
            'content' => wp_kses_post($content),
            'keywords' => $density['keywords'],
            'pair' => $pair,
        ];
    }

    /**
     * Handles base context.
     *
     * @param string $name
     * @param array $pair
     * @param array $tags
     * @param array $context
     * @return array
     */
    protected static function base_context(string $name, array $pair, array $tags, array $context): array {
        $safe_tags = Core::get_safe_model_tag_keywords($tags);
        $categories = Keyword_Library::categories_from_looks($tags);
        $seed_source = $context['model_id'] ?? $context['video_id'] ?? null;
        $seed = $seed_source !== null ? (string) $seed_source : (string) crc32($name);
        $post_id   = (int) ($context['model_id'] ?? $context['video_id'] ?? 0);
        $platform_id = (int) ($context['model_id'] ?? 0);
        $post_type = !empty($context['model_id']) ? Core::MODEL_PT : (!empty($context['video_id']) ? Core::VIDEO_PT : '');
        $extra = [];
        $locked = null;
        $locked_extras = [];

        if ($post_id > 0) {
            $locked = get_post_meta($post_id, '_tmwseo_extras_locked', true);
            $locked_extras = get_post_meta($post_id, '_tmwseo_extras_list', true);
        }

        if ($locked && is_array($locked_extras) && !empty($locked_extras)) {
            $extra = array_values(array_unique(array_filter(array_map('trim', $locked_extras), 'strlen')));
        } else {
            $extra = Keyword_Library::pick_multi($categories, 'extra', 10, $seed, [], 30, $post_id, $post_type);
            if ($post_id > 0) {
                update_post_meta($post_id, '_tmwseo_extras_list', $extra);
                update_post_meta($post_id, '_tmwseo_extras_locked', 1);
            }
        }
        if (count($extra) < 4) {
            $tag_based_keywords = array_map(function ($tag) use ($name) {
                return $name . ' ' . strtolower($tag) . ' cam';
            }, array_slice($safe_tags, 0, 4 - count($extra)));
            $extra = array_merge($extra, $tag_based_keywords);
        }
        $longtail = Keyword_Library::pick_multi($categories, 'longtail', 6, $seed, $extra, 30, $post_id, $post_type);

        $extra_focus_1 = $extra[0] ?? 'live cam model';
        $extra_focus_2 = $extra[1] ?? 'webcam model profile';
        $extra_focus_3 = $extra[2] ?? 'live webcam chat';
        $extra_focus_4 = $extra[3] ?? 'cam show highlights';

        if ($post_id > 0) {
            $stored_focus_1 = trim((string) get_post_meta($post_id, '_tmwseo_extra_focus_1', true));
            $stored_focus_2 = trim((string) get_post_meta($post_id, '_tmwseo_extra_focus_2', true));
            $stored_focus_3 = trim((string) get_post_meta($post_id, '_tmwseo_extra_focus_3', true));
            $stored_focus_4 = trim((string) get_post_meta($post_id, '_tmwseo_extra_focus_4', true));
            if ($stored_focus_1 !== '') {
                $extra_focus_1 = $stored_focus_1;
            }
            if ($stored_focus_2 !== '') {
                $extra_focus_2 = $stored_focus_2;
            }
            if ($stored_focus_3 !== '') {
                $extra_focus_3 = $stored_focus_3;
            }
            if ($stored_focus_4 !== '') {
                $extra_focus_4 = $stored_focus_4;
            }
            if ($stored_focus_1 === '' && $extra_focus_1 !== '') {
                update_post_meta($post_id, '_tmwseo_extra_focus_1', $extra_focus_1);
            }
            if ($stored_focus_2 === '' && $extra_focus_2 !== '') {
                update_post_meta($post_id, '_tmwseo_extra_focus_2', $extra_focus_2);
            }
            if ($stored_focus_3 === '' && $extra_focus_3 !== '') {
                update_post_meta($post_id, '_tmwseo_extra_focus_3', $extra_focus_3);
            }
            if ($stored_focus_4 === '' && $extra_focus_4 !== '') {
                update_post_meta($post_id, '_tmwseo_extra_focus_4', $extra_focus_4);
            }
        }

        $rankmath_additional_keywords = array_values(array_filter($extra, function ($kw) use ($extra_focus_1, $extra_focus_2, $extra_focus_3, $extra_focus_4) {
            $kw = strtolower(trim((string) $kw));
            if ($kw === '') {
                return false;
            }
            return $kw !== strtolower($extra_focus_1)
                && $kw !== strtolower($extra_focus_2)
                && $kw !== strtolower($extra_focus_3)
                && $kw !== strtolower($extra_focus_4);
        }));
        $rankmath_additional_keywords = array_slice($rankmath_additional_keywords, 0, 10);

        $safe_tags_slice = array_slice($safe_tags, 0, max(4, min(6, count($safe_tags))));
        $safe_tags_text  = !empty($safe_tags_slice) ? implode(', ', $safe_tags_slice) : 'live webcam shows';
        $active_platforms = $platform_id > 0 ? self::get_active_platform_names($platform_id) : [];
        $live_brand = 'LiveJasmin';
        if (!empty($active_platforms)) {
            $live_brand = in_array('LiveJasmin', $active_platforms, true) ? 'LiveJasmin' : $active_platforms[0];
        }
        $active_platforms_text = self::format_platform_list($active_platforms, $live_brand);
        return [
            'name'                 => $name,
            'platform_a'           => $active_platforms[0] ?? $live_brand,
            'platform_b'           => $active_platforms[1] ?? '',
            'live_brand'           => $live_brand,
            'site'                 => $context['site'] ?? get_bloginfo('name'),
            'tags'                 => $safe_tags_text,
            'safe_tags'            => $safe_tags,
            'categories'           => $categories,
            'extra_focus_1'        => $extra_focus_1,
            'extra_focus_2'        => $extra_focus_2,
            'extra_focus_3'        => $extra_focus_3,
            'extra_focus_4'        => $extra_focus_4,
            'extra_keywords'       => $extra,
            'extra_keywords_text'  => implode(', ', $extra),
            'longtail_keywords'    => $longtail,
            'rankmath_additional_keywords' => $rankmath_additional_keywords,
            'active_platforms'     => $active_platforms,
            'active_platforms_text' => $active_platforms_text,
            'cta_url'              => $context['brand_url'] ?? '',
        ];
    }

    /**
     * Renders focus blocks.
     *
     * @param array $base
     * @param string $name
     * @return string
     */
    protected static function render_focus_blocks(array $base, string $name): string {
        $focus1 = trim((string) ($base['extra_focus_1'] ?? ''));
        $focus2 = trim((string) ($base['extra_focus_2'] ?? ''));
        $focus3 = trim((string) ($base['extra_focus_3'] ?? ''));
        $focus4 = trim((string) ($base['extra_focus_4'] ?? ''));

        $blocks = [];

        if ($focus1 !== '') {
            $blocks[] = '<h2>Why watch ' . esc_html($name) . ' if you like ' . esc_html($focus1) . '</h2>';
            $blocks[] = '<p>Viewers who appreciate ' . esc_html($focus1) . ' enjoy the interactive, respectful pacing in each show. ' . esc_html($name) . ' brings ' . esc_html($focus1) . ' energy to every stream.</p>';
        }

        if ($focus2 !== '') {
            $blocks[] = '<h3>' . esc_html($focus2) . ' vibe and chat experience</h3>';
            $blocks[] = '<p>Expect chat that stays playful around ' . esc_html($focus2) . ' while keeping the spotlight on live reactions. The ' . esc_html($focus2) . ' atmosphere makes every session memorable.</p>';
        }

        if ($focus3 !== '') {
            $blocks[] = '<h3>Experience ' . esc_html($focus3) . ' with ' . esc_html($name) . '</h3>';
            $blocks[] = '<p>Fans seeking ' . esc_html($focus3) . ' find exactly that in ' . esc_html($name) . '\'s streams. The ' . esc_html($focus3) . ' format creates genuine connections.</p>';
        }

        if ($focus4 !== '') {
            $blocks[] = '<h3>' . esc_html($focus4) . ' - What to expect</h3>';
            $blocks[] = '<p>Sessions featuring ' . esc_html($focus4) . ' give viewers the authentic experience they\'re looking for. ' . esc_html($name) . ' delivers quality ' . esc_html($focus4) . ' content.</p>';
        }

        return implode("\n", $blocks);
    }

    /**
     * Renders related keyword coverage for RankMath.
     *
     * @param array $keywords
     * @param string $name
     * @return string
     */
    protected static function render_rankmath_keyword_coverage(array $keywords, string $name): string {
        $keywords = array_values(array_filter(array_map('trim', $keywords), 'strlen'));
        if (empty($keywords)) {
            return '';
        }

        $out  = '<h3>Related searches</h3>';
        $out .= '<p>People looking for ' . esc_html($name) . ' often search these topics:</p>';
        $out .= '<ul>';
        foreach ($keywords as $keyword) {
            $out .= '<li>' . esc_html($keyword) . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Renders faqs.
     *
     * @param array $faqs
     * @param array $base
     * @param array $longtail_keywords
     * @return string
     */
    protected static function render_faqs(array $faqs, array $base, array $longtail_keywords = []): string {
        $longtail_append = [];
        if (!empty($longtail_keywords)) {
            $lt = array_slice($longtail_keywords, 0, 1);
            foreach ($lt as $kw) {
                $longtail_append[] = [
                    'q' => sprintf('Does {name} include %s in live chat?', $kw),
                    'a' => sprintf('When viewers politely ask for %s, {name} blends it into the flow without derailing the vibe.', $kw),
                ];
            }
        }

        $faqs = array_merge($faqs, $longtail_append);
        $html = '<h2>FAQ</h2>';
        $used_questions = [];
        foreach ($faqs as $faq) {
            if (
                ((strpos($faq['q'], '{platform_a}') !== false || strpos($faq['a'], '{platform_a}') !== false) && empty($base['platform_a']))
                || ((strpos($faq['q'], '{platform_b}') !== false || strpos($faq['a'], '{platform_b}') !== false) && empty($base['platform_b']))
            ) {
                continue;
            }
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

    /**
     * Renders related.
     *
     * @param array $context
     * @param string $name
     * @return string
     */
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

    /**
     * Build platform comparison text based on model's actual platforms
     */
    public static function build_platform_comparison(int $model_id, string $name): string {
        $platforms = \TMW_SEO\Admin\Model_Platforms_Metabox::get_model_platforms($model_id);
        $confirmed = array_filter($platforms, function ($platform) {
            return !empty($platform['username']);
        });

        $profiles_html = '';
        if (!empty($confirmed)) {
            $links = [];
            foreach ($confirmed as $platform) {
                if (empty($platform['url_pattern'])) {
                    continue;
                }
                $username = (string) ($platform['username'] ?? '');
                if ($username === '') {
                    continue;
                }
                $url = str_replace('{username}', rawurlencode($username), $platform['url_pattern']);
                if ($url === '') {
                    continue;
                }
                $links[] = '<li><a href="' . esc_url($url) . '" rel="nofollow noopener" target="_blank">' . esc_html($platform['name']) . '</a></li>';
            }
            if (!empty($links)) {
                $profiles_html = '<h2>Official profiles</h2><ul>' . implode('', $links) . '</ul>';
            }
        }
        
        if (empty($confirmed)) {
            // Default: only mention LiveJasmin as primary
            return self::default_livejasmin_pitch($name);
        }

        $primary = Platform_Registry::get_primary_platform();
        $primary_slug = $primary['slug'];
        $platform_count = count($confirmed);

        // Check if model is on LiveJasmin (our primary)
        $is_on_primary = isset($confirmed[$primary_slug]);

        if ($platform_count < 2) {
            if ($is_on_primary) {
                return ($profiles_html !== '' ? $profiles_html . "\n" : '') . self::livejasmin_exclusive_pitch($name);
            }
            return ($profiles_html !== '' ? $profiles_html . "\n" : '') . self::describe_active_platforms($name, $confirmed);
        }

        if (!$is_on_primary) {
            // Model not on LiveJasmin - just describe where she IS
            return ($profiles_html !== '' ? $profiles_html . "\n" : '') . self::describe_active_platforms($name, $confirmed);
        }
        
        // Model is on LiveJasmin - compare with her other platforms
        $other_platforms = array_filter($confirmed, function ($slug) use ($primary_slug) {
            return $slug !== $primary_slug;
        }, ARRAY_FILTER_USE_KEY);
        
        if (empty($other_platforms)) {
            return ($profiles_html !== '' ? $profiles_html . "\n" : '') . self::livejasmin_exclusive_pitch($name);
        }
        
        return ($profiles_html !== '' ? $profiles_html . "\n" : '') . self::livejasmin_vs_others($name, $other_platforms);
    }

    protected static function default_livejasmin_pitch(string $name): string {
        return sprintf(
            '<h2>Watch %s on LiveJasmin</h2>' .
            '<p>%s performs live on LiveJasmin, the premium cam site known for HD quality streams and professional models. ' .
            'LiveJasmin offers private shows, cam2cam features, and a clean, ad-free viewing experience.</p>',
            esc_html($name),
            esc_html($name)
        );
    }

    protected static function livejasmin_exclusive_pitch(string $name): string {
        return sprintf(
            '<h2>%s - Exclusive on LiveJasmin</h2>' .
            '<p>You can find %s exclusively on LiveJasmin. She chose this platform for its premium quality, ' .
            'professional environment, and engaged audience. Experience her shows in crystal-clear HD with ' .
            'interactive features like private chat and cam2cam.</p>',
            esc_html($name),
            esc_html($name)
        );
    }

    protected static function describe_active_platforms(string $name, array $platforms): string {
        $platform_names = array_map(function ($p) {
            return $p['name'];
        }, $platforms);
        $list = implode(', ', array_slice($platform_names, 0, -1));
        if (count($platform_names) > 1) {
            $list .= ' and ' . end($platform_names);
        } else {
            $list = $platform_names[0] ?? 'various platforms';
        }
        
        return sprintf(
            '<h2>Where to Find %s</h2>' .
            '<p>%s is active on %s. Check her schedule on each platform to catch her live shows.</p>',
            esc_html($name),
            esc_html($name),
            esc_html($list)
        );
    }

    protected static function livejasmin_vs_others(string $name, array $other_platforms): string {
        $comparisons = [];
        
        foreach ($other_platforms as $platform) {
            $comparisons[] = self::get_platform_comparison_text($platform['name']);
        }
        
        $other_names = array_map(function ($p) {
            return $p['name'];
        }, $other_platforms);
        $others_list = implode(', ', $other_names);
        
        $comparison_text = implode(' ', array_slice($comparisons, 0, 2));
        
        return sprintf(
            '<h2>Why Watch %s on LiveJasmin vs %s</h2>' .
            '<p>While %s also performs on %s, her LiveJasmin shows offer the best experience. %s</p>' .
            '<p>LiveJasmin\'s premium features include HD streaming up to 1080p, minimal ads, ' .
            'and professional moderation that keeps chat respectful and focused on the performer.</p>',
            esc_html($name),
            esc_html($others_list),
            esc_html($name),
            esc_html($others_list),
            $comparison_text
        );
    }

    protected static function get_platform_comparison_text(string $platform_name): string {
        $comparisons = [
            'Chaturbate' => 'Chaturbate\'s public rooms can be chaotic with tip spam, while LiveJasmin keeps the focus on intimate interaction.',
            'Stripchat' => 'Stripchat offers free shows but with more distractions; LiveJasmin\'s private shows deliver undivided attention.',
            'BongaCams' => 'BongaCams has a large model selection, but LiveJasmin\'s curation means higher quality performers.',
            'CamSoda' => 'CamSoda is budget-friendly but LiveJasmin\'s HD quality and stable streams are worth the premium.',
            'MyFreeCams' => 'MyFreeCams focuses on American models; LiveJasmin offers a more international, diverse selection.',
            'OnlyFans' => 'OnlyFans provides on-demand content, but LiveJasmin delivers real-time interaction you can\'t get from pre-recorded clips.',
            'Fansly' => 'Fansly is great for subscription content; LiveJasmin excels at live, interactive experiences.',
        ];
        
        return $comparisons[$platform_name] ?? 'LiveJasmin offers a premium experience with HD quality and professional performers.';
    }

    /**
     * Renders longtail section.
     *
     * @param array $longtail_keywords
     * @param string $name
     * @return string
     */
    protected static function render_longtail_section(array $longtail_keywords, string $name): string {
        $items = array_slice(array_values(array_unique(array_filter($longtail_keywords))), 0, 3);
        if (empty($items)) {
            return '';
        }

        $out  = '<h3>Conversation cues inspired by fans</h3>';
        $out .= '<ul>';
        foreach ($items as $kw) {
            $out .= '<li>' . esc_html($kw) . '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Handles pad content.
     *
     * @param string $content
     * @param int $missing_words
     * @param string $name
     * @param string $type
     * @return string
     */
    protected static function pad_content(string $content, int $missing_words, string $name, string $type, array $focus_keywords = []): string {
        if ($missing_words < 10) {
            return $content;
        }

        $focus_keywords = array_values(array_filter(array_map('trim', $focus_keywords), 'strlen'));
        $primary_focus = $focus_keywords[0] ?? 'live cam sessions';
        $secondary_focus = $focus_keywords[1] ?? 'webcam model profile';

        $natural_additions = [
            "Community members consistently praise {$name} for maintaining authentic energy across sessions. Rather than following a rigid script, {$name} adapts to each room's vibe, creating experiences that feel personal rather than performative. This flexibility explains why regular viewers return for multiple shows.",

            "Live streaming creates spontaneous moments that recorded clips rarely capture. Real-time responses and the ability to shape the flow of a show help fans feel involved while watching {$name} perform.",

            "Viewers who discover {$name} through search often express surprise at how much more engaging live sessions feel. The ability to make requests, receive immediate acknowledgment, and influence the show's direction transforms passive viewing into active participation.",

            "Technical quality matters in adult entertainment, and reliable streaming with stable framerates, clear audio, and good lighting creates premium viewing experiences that keep audiences coming back.",

            "Regular attendees of {$name}'s shows develop rapport over time, with inside jokes and callbacks that create community. This ongoing relationship dynamic differs from one-way content feeds where creators post and subscribers consume without dialogue.",

            "The {$name} profile showcases everything fans love about interactive streaming. With attention to viewer preferences and a welcoming chat environment, {$name} creates sessions that feel personal and engaging for anyone seeking {$primary_focus}.",

            "Following {$name} means access to scheduled streams, surprise appearances, and the kind of authentic interaction that keeps viewers coming back. Each broadcast brings something unique to the {$secondary_focus} experience.",
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

    /**
     * Splits long paragraphs into shorter ones.
     *
     * @param string $html
     * @param int $max_chars
     * @return string
     */
    protected static function split_long_paragraphs(string $html, int $max_chars = 320): string {
        return preg_replace_callback('/<p>(.*?)<\/p>/s', function ($matches) use ($max_chars) {
            $text = trim(wp_strip_all_tags($matches[1]));
            if ($text === '') {
                return $matches[0];
            }
            if (mb_strlen($text) <= $max_chars) {
                return '<p>' . esc_html($text) . '</p>';
            }

            $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            if (empty($sentences)) {
                return '<p>' . esc_html($text) . '</p>';
            }

            $chunks = [];
            $current = '';
            $count = 0;
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if ($sentence === '') {
                    continue;
                }
                $candidate = $current === '' ? $sentence : $current . ' ' . $sentence;
                if (($count >= 2 && $current !== '') || (mb_strlen($candidate) > $max_chars && $current !== '')) {
                    $chunks[] = $current;
                    $current = $sentence;
                    $count = 1;
                    continue;
                }
                $current = $candidate;
                $count++;
            }
            if ($current !== '') {
                $chunks[] = $current;
            }

            $out = '';
            foreach ($chunks as $chunk) {
                $out .= '<p>' . esc_html($chunk) . '</p>';
            }

            return $out;
        }, $html);
    }

    /**
     * Returns active platform names for a model.
     *
     * @param int $model_id
     * @return array
     */
    public static function get_active_platform_names(int $model_id): array {
        $platforms = \TMW_SEO\Admin\Model_Platforms_Metabox::get_model_platforms($model_id);
        $active = [];
        foreach ($platforms as $platform) {
            if (empty($platform['username'])) {
                continue;
            }
            $name = trim((string) ($platform['name'] ?? ''));
            if ($name !== '') {
                $active[] = $name;
            }
        }
        return array_values(array_unique($active));
    }

    /**
     * Picks a template slug based on active platforms.
     *
     * @param string $base_slug
     * @param array $active_platforms
     * @return string
     */
    protected static function pick_platform_template_slug(string $base_slug, array $active_platforms): string {
        if (count($active_platforms) > 1) {
            $multi_slug = $base_slug . '-multi';
            if (!empty(Template_Engine::load($multi_slug))) {
                return $multi_slug;
            }
        }
        return $base_slug;
    }

    /**
     * Formats platform list for templates.
     *
     * @param array $platforms
     * @param string $fallback
     * @return string
     */
    protected static function format_platform_list(array $platforms, string $fallback): string {
        $platforms = array_values(array_filter(array_map('trim', $platforms), 'strlen'));
        if (empty($platforms)) {
            return $fallback;
        }
        if (count($platforms) === 1) {
            return $platforms[0];
        }
        $last = array_pop($platforms);
        return implode(', ', $platforms) . ' and ' . $last;
    }
}
