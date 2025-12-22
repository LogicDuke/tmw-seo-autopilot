<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Core {
    const TAG = '[TMW-SEO-GEN]';
    const POST_TYPE = 'model';
    const MODEL_PT = 'model';
    const VIDEO_PT = 'video';

    public static function video_post_types(): array {
        $opt = get_option('tmwseo_video_pts');
        if (is_array($opt) && !empty($opt)) {
            return array_values(array_unique(array_filter($opt)));
        }
        $guessed = self::guess_video_post_types();
        update_option('tmwseo_video_pts', $guessed, false);
        return $guessed;
    }

    public static function guess_video_post_types(): array {
        $candidates = [];
        foreach (['video', 'videos'] as $def) {
            if (post_type_exists($def)) $candidates[] = $def;
        }
        global $wp_post_types;
        if (is_array($wp_post_types)) {
            foreach ($wp_post_types as $slug => $pt) {
                if (empty($pt) || !is_object($pt) || !$pt->public || $pt->_builtin) continue;
                $label = strtolower($pt->labels->name . ' ' . $pt->labels->singular_name . ' ' . $slug);
                if (preg_match('#\b(video|videos|clip|clips|movie|movies)\b#', $label)) {
                    $candidates[] = $slug;
                }
            }
        }
        $candidates = apply_filters('tmw_seo_video_post_types', $candidates);
        return array_values(array_unique(array_filter($candidates)));
    }

    /** Defaults via constants (wp-config) or sane fallbacks */
    public static function brand_order(): array {
        $order = defined('TMW_SEO_BRAND_ORDER') ? TMW_SEO_BRAND_ORDER : 'jasmin,myc,lpr,joy,lsa';
        return array_values(array_filter(array_map('trim', explode(',', strtolower($order)))));
    }

    public static function subaff_pattern(): string {
        return defined('TMW_SEO_SUBAFF_PATTERN') ? TMW_SEO_SUBAFF_PATTERN : '{slug}-{brand}-{postId}';
    }

    public static function default_og(): string {
        return defined('TMW_SEO_DEFAULT_OG') ? TMW_SEO_DEFAULT_OG : '';
    }

    /** Public API */
    public static function generate_for_video(int $video_id, array $args = []): array {
        $args = wp_parse_args($args, [
            'strategy' => 'template',
        ]);
        $post = get_post($video_id);
        if (!$post || !in_array($post->post_type, self::video_post_types(), true)) {
            return ['ok' => false, 'message' => 'Not a video'];
        }

        $name = self::detect_model_name_from_video($post);
        if (!$name) {
            error_log(self::TAG . " abort: video#{$post->ID} '{$post->post_title}' has no detectable model name");
            return ['ok' => false, 'message' => 'No model name detected'];
        }

        $model_id = self::ensure_model_exists($name);
        $ctx_video = self::build_ctx_video($video_id, $model_id, $name, $args);
        $ctx_model = self::build_ctx_model($model_id, $name, array_merge($args, ['video_id' => $video_id]));

        $provider = self::provider($args['strategy']);
        $payload_video = $provider->generate_video($ctx_video);
        $payload_model = $provider->generate_model($ctx_model);

        self::write_all($video_id, $payload_video, 'VIDEO', true, $ctx_video);
        self::write_all($model_id, $payload_model, 'MODEL', true, $ctx_model);
        
        $brand_url = self::affiliate_url($name, '', $post->ID, $post->post_name);
        $model_url = get_permalink($model_id);
        $highlights_count = $ctx_video['highlights_count'] ?? 7;
        $rm_video = self::compose_rankmath_for_video($post, [
            'name' => $name,
            'slug' => $post->post_name,
            'brand_url' => $brand_url,
            'model_url' => $model_url,
            'highlights_count' => $highlights_count,
        ]);
        self::update_rankmath_meta($post->ID, $rm_video);

        $rm_model = self::compose_rankmath_for_model(get_post($model_id), [
            'name' => $name,
        ]);
        self::update_rankmath_meta($model_id, $rm_model);

        self::link_video_to_model($video_id, $model_id);
        self::link_model_to_video($model_id, $video_id);

        error_log(self::TAG . " generated video#$video_id & model#$model_id for {$name}");
        return ['ok' => true, 'video' => $payload_video, 'model' => $payload_model, 'model_id' => $model_id];
    }

    /** Manual model generation for admin / CLI compatibility */
    public static function generate_and_write(int $post_id, array $args = []): array {
        $args = wp_parse_args($args, [
            'strategy' => 'template',
            'insert_content' => true,
            'dry_run' => false,
        ]);
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::MODEL_PT) {
            return ['ok' => false, 'message' => 'Invalid post or type'];
        }
        $ctx = self::build_ctx_model($post_id, $post->post_title, $args);
        $provider = self::provider($args['strategy']);
        $payload = $provider->generate_model($ctx);
        if (empty($payload['title'])) {
            return ['ok' => false, 'message' => 'Generator returned empty payload'];
        }
        if ($args['dry_run']) {
            return ['ok' => true, 'payload' => $payload, 'dry_run' => true];
        }
        self::write_all($post_id, $payload, 'MODEL', !empty($args['insert_content']), $ctx);
        $rm_model = self::compose_rankmath_for_model($post, [
            'name' => $post->post_title,
        ]);
        self::update_rankmath_meta($post_id, $rm_model);
        return ['ok' => true, 'payload' => $payload];
    }

    public static function rollback(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) {
            return ['ok' => false, 'message' => 'Post not found'];
        }
        $type = in_array($post->post_type, self::video_post_types(), true) ? 'VIDEO' : 'MODEL';
        $prev = get_post_meta($post_id, "_tmwseo_prev_{$type}", true);
        if (!$prev && $type === 'MODEL') {
            $prev = get_post_meta($post_id, '_tmwseo_prev', true);
        }
        if (!$prev) {
            return ['ok' => false, 'message' => 'No previous values stored'];
        }
        update_post_meta($post_id, 'rank_math_title', $prev['rank_math_title'] ?? '');
        update_post_meta($post_id, 'rank_math_description', $prev['rank_math_description'] ?? '');
        update_post_meta($post_id, 'rank_math_focus_keyword', $prev['rank_math_focus_keyword'] ?? '');
        if (isset($prev['post_content'])) {
            $start = "<!-- TMWSEO:{$type}:START -->";
            $end = "<!-- TMWSEO:{$type}:END -->";
            $clean = preg_replace("#{$start}.*?{$end}#s", '', $prev['post_content']);
            wp_update_post(['ID' => $post_id, 'post_content' => $clean]);
        }
        delete_post_meta($post_id, "_tmwseo_prev_{$type}");
        delete_post_meta($post_id, '_tmwseo_prev');
        error_log(self::TAG . " rollback done for #$post_id");
        return ['ok' => true];
    }

    /** Ensure Model exists by exact post_title; returns ID */
    public static function ensure_model_exists(string $name): int {
        $model = get_page_by_title($name, OBJECT, self::MODEL_PT);
        if ($model) {
            return (int) $model->ID;
        }
        $id = wp_insert_post([
            'post_type' => self::MODEL_PT,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => '',
        ]);
        error_log(self::TAG . " created model#$id for {$name}");
        return (int) $id;
    }

    /** Detect model name from meta/tax/title */
    public static function detect_model_name_from_video(\WP_Post $post): string {
        // explicit overrides / common meta
        foreach (['tmwseo_model_name', 'awe_model_name', 'model_name', 'performer_name'] as $k) {
            $v = trim((string) get_post_meta($post->ID, $k, true));
            if ($v !== '') return $v;
        }
        // taxonomies
        foreach (['models', 'model', 'video_actors', 'actor', 'performer'] as $tax) {
            if (taxonomy_exists($tax)) {
                $names = wp_get_post_terms($post->ID, $tax, ['fields' => 'names']);
                if (!is_wp_error($names) && !empty($names)) {
                    $name = trim((string) $names[0]);
                    if ($name !== '') return $name;
                }
            }
        }
        // title patterns
        $t = wp_strip_all_tags($post->post_title);
        if (preg_match('/\bwith\s+([A-Z][\p{L}\']+(?:\s+[A-Z][\p{L}\']+){0,3})\b/u', $t, $m)) {
            return trim($m[1]);
        }
        $parts = preg_split('/\s*[—–\-:\|]\s*/u', $t, 2);
        if (!empty($parts[0])) {
            $first = trim($parts[0]);
            $first = preg_replace('/^\s*(?:intimate|private|live)?\s*(?:chat|video|clip|session)?\s*with\s+/i', '', $first);
            $first = trim($first);
            if ($first !== '') return $first;
        }
        if (preg_match('/\b([A-Z][\p{L}\']+\s+[A-Z][\p{L}\']+)\b/u', $t, $m2)) {
            return trim($m2[1]);
        }
        error_log(self::TAG . " abort: no model name for video#{$post->ID} title='{$t}'");
        return '';
    }

    /** Build CTA (LiveJasmin first, with fallbacks) */
    public static function affiliate_url(string $name, string $brand = '', int $post_id = 0, string $slug = ''): string {
        $brands = self::brand_order();
        if (!$brand) {
            $brand = $brands[0] ?? 'jasmin';
        }
        $psid = defined('TMW_SEO_PSID') ? TMW_SEO_PSID : 'Topmodels4u';
        $pstool = defined('TMW_SEO_PSTOOL') ? TMW_SEO_PSTOOL : '205_1';
        $prog = defined('TMW_SEO_PSPROGRAM') ? TMW_SEO_PSPROGRAM : 'revs';
        $handle = rawurlencode(preg_replace('/\s+/', '', $name));
        $sub = self::build_subaff($post_id, $brand, $slug ?: sanitize_title($name));
        return add_query_arg([
            'siteId' => $brand,
            'categoryName' => 'girl',
            'pageName' => 'freechat',
            'performerName' => $handle,
            'prm[psid]' => $psid,
            'prm[pstool]' => $pstool,
            'prm[psprogram]' => $prog,
            'prm[campaign_id]' => '',
            'subAffId' => $sub,
        ], 'https://ctwmsg.com/');
    }

    protected static function build_subaff(int $post_id, string $brand, string $slug): string {
        $pattern = self::subaff_pattern();
        $replacements = [
            '{slug}' => $slug ?: 'video',
            '{brand}' => $brand,
            '{postId}' => (string) $post_id,
            '{post_id}' => (string) $post_id,
        ];
        return strtr($pattern, $replacements);
    }

    /** Build contexts */
    protected static function build_ctx_video(int $video_id, int $model_id, string $name, array $args): array {
        $looks = self::first_looks($video_id);
        $hook = $looks[0] ?? 'highlights';
        $title = get_the_title($video_id);
        $slug = basename(get_permalink($video_id));
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $focus = sprintf('%s %s', $name, $hook);
        $extras = self::pick_extras($name, $looks, ['highlights', 'reel', 'live chat']);
        return [
            'video_id' => $video_id,
            'model_id' => $model_id,
            'name' => $name,
            'title' => $title,
            'slug' => $slug,
            'site' => $site,
            'hook' => $hook,
            'looks' => $looks,
            'focus' => $focus,
            'extras' => $extras,
            'model_permalink' => get_permalink($model_id),
            'video_permalink' => get_permalink($video_id),
            'brand_url' => self::affiliate_url($name, '', $video_id, $slug),
            'model_url' => get_permalink($model_id),
            'highlights_count' => 7,
            'deep_link' => self::deep_link_url('video'),
        ];
    }

    protected static function build_ctx_model(int $model_id, string $name, array $args): array {
        $looks = self::first_looks($model_id);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $slug = basename(get_permalink($model_id));
        $focus = $name;
        $extras = self::pick_extras($name, $looks, ['live chat', 'profile', 'schedule']);
        $video_id = (int) ($args['video_id'] ?? 0);
        return [
            'model_id' => $model_id,
            'video_id' => $video_id,
            'name' => $name,
            'slug' => $slug,
            'site' => $site,
            'looks' => $looks,
            'focus' => $focus,
            'extras' => $extras,
            'model_permalink' => get_permalink($model_id),
            'video_permalink' => $video_id ? get_permalink($video_id) : '',
            'brand_url' => self::affiliate_url($name, '', $model_id, $slug),
            'deep_link' => self::deep_link_url('model'),
        ];
    }

    public static function first_looks(int $post_id): array {
        $out = [];
        foreach (['video_tag', 'post_tag', 'models', 'category'] as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $names = wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
            if (!is_wp_error($names)) {
                $out = array_merge($out, $names);
            }
        }
        return array_values(array_unique(array_filter($out)));
    }

    protected static function pick_extras(string $name, array $looks, array $defaults): array {
        $choices = array_values(array_unique(array_merge($looks, $defaults)));
        $extras = [];
        foreach ($choices as $c) {
            if (strtolower($c) === strtolower($name)) {
                continue;
            }
            $extras[] = sprintf('%s %s', $name, trim($c));
            if (count($extras) >= 4) {
                break;
            }
        }
        while (count($extras) < 4) {
            $extras[] = $name . ' live chat';
        }
        return $extras;
    }

    /** Write RankMath + content; $type = MODEL|VIDEO */
    protected static function write_all(int $post_id, array $payload, string $type, bool $update_content = true, array $ctx = []): void {
        if (empty($payload['title'])) {
            return;
        }
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        $prev = [
            'rank_math_title' => get_post_meta($post_id, 'rank_math_title', true),
            'rank_math_description' => get_post_meta($post_id, 'rank_math_description', true),
            'rank_math_focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'post_content' => $post->post_content,
        ];
        update_post_meta($post_id, "_tmwseo_prev_{$type}", $prev);

        $keywords = $payload['keywords'] ?? ($payload['focus'] ?? []);
        $keywords = array_map('sanitize_text_field', (array) $keywords);

        update_post_meta($post_id, 'rank_math_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_description', sanitize_text_field($payload['meta'] ?? ''));
        update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));

        update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_facebook_description', sanitize_text_field($payload['meta'] ?? ''));
        update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_twitter_description', sanitize_text_field($payload['meta'] ?? ''));
        if (self::default_og()) {
            update_post_meta($post_id, 'rank_math_facebook_image', esc_url_raw(self::default_og()));
            update_post_meta($post_id, 'rank_math_twitter_image', esc_url_raw(self::default_og()));
        }

        if ($update_content && !empty($payload['content'])) {
            $block = wp_kses_post($payload['content']);
            $block .= self::internal_links_block($ctx, $type);
            $block .= self::cta_block($ctx, $post_id);

            $start = "<!-- TMWSEO:{$type}:START -->";
            $end = "<!-- TMWSEO:{$type}:END -->";
            $content = $post->post_content ?: '';
            $content = preg_replace("#{$start}.*?{$end}#s", '', $content);
            $content .= "\n{$start}\n" . $block . "\n{$end}\n";
            $content = preg_replace('#<h1>#i', '<h2>', $content);
            $content = preg_replace('#</h1>#i', '</h2>', $content);
            wp_update_post(['ID' => $post_id, 'post_content' => $content]);
        }

        self::update_featured_image_meta($post_id, $ctx['name'] ?? $post->post_title);
        }
    
    protected static function cta_block(array $ctx, int $post_id): string {
        $name = $ctx['name'] ?? get_the_title($post_id);
        if (!$name) {
            return '';
        }
        $brand = self::brand_order()[0] ?? 'jasmin';
        $slug = $ctx['slug'] ?? basename(get_permalink($post_id));
        $url = self::affiliate_url($name, $brand, $post_id, $slug);
        $label = sprintf('Join %s live chat on %s', $name, ucfirst($brand));
        return '<p class="tmwseo-cta"><a href="' . esc_url($url) . '" rel="sponsored nofollow noopener" target="_blank">' . esc_html($label) . '</a></p>';
    }

    protected static function internal_links_block(array $ctx, string $type): string {
        $links = '';
        if ($type === 'VIDEO' && !empty($ctx['model_permalink'])) {
            $links .= '<p class="tmwseo-link-model"><a href="' . esc_url($ctx['model_permalink']) . '">View ' . esc_html($ctx['name']) . ' profile</a></p>';
            if (!empty($ctx['deep_link'])) {
                $links .= '<p class="tmwseo-link-hub"><a href="' . esc_url($ctx['deep_link']) . '">Browse more live cam highlights</a></p>';
            }
        }
        if ($type === 'MODEL' && !empty($ctx['video_permalink'])) {
            $links .= '<p class="tmwseo-link-video"><a href="' . esc_url($ctx['video_permalink']) . '">Watch the latest video</a></p>';
        }
        if ($type === 'MODEL' && !empty($ctx['deep_link'])) {
            $links .= '<p class="tmwseo-link-hub"><a href="' . esc_url($ctx['deep_link']) . '">See more featured models</a></p>';
        }
        return $links;
    }
    
    protected static function update_featured_image_meta(int $post_id, string $name): void {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post) {
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id <= 0) {
            return;
        }

        // Legacy direct updates superseded by TMW_SEO\Image_Meta + Media\Image_Meta_Generator.
        \TMW_SEO\Media\Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
    }

    protected static function deep_link_url(string $type): string {
        if ($type === 'video') {
            foreach (self::video_post_types() as $pt) {
                $archive = get_post_type_archive_link($pt);
                if ($archive) {
                    return $archive;
                }
            }
            return home_url('/videos/');
        }
        $archive = get_post_type_archive_link(self::MODEL_PT);
        if ($archive) {
            return $archive;
        }
        return home_url('/models/');
    }

    public static function compose_rankmath_for_video(\WP_Post $post, array $ctx): array {
        $name = $ctx['name'];
        $focus = $name;
        $extras = [
            "$name live chat",
            "$name private show",
            "$name profile",
            "$name schedule",
        ];
        $num = $ctx['highlights_count'] ?? 7;
        $title = "$name — $num Must-See Highlights (Private Show)";
        $desc = "$name in a clean, quick reel with a direct jump to live chat. Teasers, schedule tips, and links on Top Models Webcam.";

        return [
            'focus' => $focus,
            'extras' => $extras,
            'title' => $title,
            'desc' => $desc,
        ];
    }

    public static function compose_rankmath_for_model(\WP_Post $post, array $ctx): array {
        $name = $ctx['name'];
        $focus = $name;
        $extras = [
            "$name live chat",
            "$name bio",
            "$name photos",
            "$name schedule",
        ];
        $title = "$name — Live Chat & Profile";
        $desc = "$name on Top Models Webcam. Photos, schedule tips, and live chat links. Follow $name for updates and teasers.";

        return [
            'focus' => $focus,
            'extras' => $extras,
            'title' => $title,
            'desc' => $desc,
        ];
    }

    public static function update_rankmath_meta(int $post_id, array $rm): void {
        $kw = array_filter(array_map('trim', array_merge([$rm['focus']], $rm['extras'])));
        update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $kw));
         update_post_meta($post_id, 'rank_math_title', $rm['title']);
        update_post_meta($post_id, 'rank_math_description', $rm['desc']);
        update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        error_log(self::TAG . " [RM] set focus='" . $rm['focus'] . "' extras=" . json_encode($rm['extras']) . " for post#$post_id");
    }

    /** Cross-links */
    protected static function link_video_to_model(int $video_id, int $model_id): void {
        update_post_meta($video_id, '_tmwseo_model_id', $model_id);
    }

    protected static function link_model_to_video(int $model_id, int $video_id): void {
        update_post_meta($model_id, '_tmwseo_latest_video_id', $video_id);
    }

    protected static function provider(string $strategy) {
        if ($strategy === 'openai' && Providers\OpenAI::is_enabled()) {
            return new Providers\OpenAI();
        }
        return new Providers\Template();
    }
}
