<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

use TMW_SEO\Media\Image_Meta_Generator;

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

    public static function is_video_post_type(string $post_type): bool {
        $types = array_merge(self::video_post_types(), ['video', 'videos']);
        return in_array($post_type, array_unique($types), true);
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
            'force'    => false,
        ]);
        $force = ! empty( $args['force'] );
        $post = get_post($video_id);
        if (!$post || !self::is_video_post_type($post->post_type)) {
            return ['ok' => false, 'message' => 'Not a video'];
        }

        $already_done = get_post_meta($post->ID, '_tmwseo_video_seo_done', true);
        if (! $force && !empty($already_done)) {
            return ['ok' => false, 'message' => 'Skipped: SEO already generated.'];
        }

        $manual_focus = self::normalize_manual_focus_keyword(
            (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true)
        );
        if ($manual_focus === '') {
            return ['ok' => false, 'message' => 'Missing manual focus keyword.'];
        }

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
                sprintf(
                    '%s [RM-VIDEO] post#%d opts=%s existing_focus=%s',
                    self::TAG,
                    $video_id,
                    wp_json_encode( $args ),
                    $manual_focus !== '' ? 'yes' : 'no'
                )
            );
        }

        $name = self::detect_model_name_from_video($post);
        if (!$name) {
            error_log(self::TAG . " abort: video#{$post->ID} '{$post->post_title}' has no detectable model name");
            return ['ok' => false, 'message' => 'No model name detected'];
        }

        $model_id = self::ensure_model_exists($name);
        $ctx_video = self::build_ctx_video($video_id, $model_id, $name, $args);
        $ctx_model = self::build_ctx_model($model_id, $name, array_merge($args, ['video_id' => $video_id]));

        $highlights_count = $ctx_video['highlights_count'] ?? 7;

        $rm_video = self::compose_rankmath_for_video(
            $post,
            [
                'name'             => $name,
                'slug'             => $post->post_name,
                'brand_url'        => $ctx_video['brand_url'] ?? '',
                'model_url'        => $ctx_video['model_url'] ?? '',
                'highlights_count' => $highlights_count,
            ]
        );

        $focus_for_video = $manual_focus !== '' ? $manual_focus : $rm_video['focus'];

        self::maybe_update_video_title( $post, $focus_for_video, $name );

        $ctx_video['focus'] = $rm_video['focus'];
        if (!empty($rm_video['extras'])) {
            $ctx_video['extras'] = $rm_video['extras'];
        }

        $provider = self::provider($args['strategy']);
        $payload_video = $provider->generate_video($ctx_video);
        $payload_model = $provider->generate_model($ctx_model);

        $rm_model = self::compose_rankmath_for_model(get_post($model_id), [
            'name' => $name,
        ]);
        $payload_model['keywords'] = array_merge([$rm_model['focus']], $rm_model['extras'] ?? []);

        self::write_all($video_id, $payload_video, 'VIDEO', true, $ctx_video);
        self::write_all($model_id, $payload_model, 'MODEL', true, $ctx_model);

        self::update_rankmath_meta($post->ID, $rm_video, !$force, false);

        $looks         = self::first_looks( $video_id );
        $tag_keywords  = self::safe_model_tag_keywords( $looks );
        update_post_meta( $post->ID, '_tmwseo_video_tag_keywords', $tag_keywords );

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
                sprintf(
                    '%s [RM-VIDEO] post#%d focus="%s" extras=%s',
                    self::TAG,
                    $post->ID,
                    $rm_video['focus'],
                    wp_json_encode( $rm_video['extras'] ?? [] )
                )
            );
        }

        self::update_rankmath_meta($model_id, $rm_model);

        self::link_video_to_model($video_id, $model_id);
        self::link_model_to_video($model_id, $video_id);

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
                sprintf(
                    '%s [VIDEO] #%d focus="%s" title="%s" desc_contains_focus=%s',
                    self::TAG,
                    $post->ID,
                    $rm_video['focus'],
                    $rm_video['title'],
                    strpos( $rm_video['desc'], $rm_video['focus'] ) !== false ? 'yes' : 'no'
                )
            );
        }

        update_post_meta($post->ID, '_tmwseo_video_seo_done', gmdate('c'));
        error_log(self::TAG . " generated video#$video_id & model#$model_id for {$name}");
        return ['ok' => true, 'video' => $payload_video, 'model' => $payload_model, 'model_id' => $model_id];
    }

    /** Manual model generation for admin / CLI compatibility */
    public static function generate_and_write(int $post_id, array $args = []): array {
        $args = wp_parse_args($args, [
            'strategy' => Providers\OpenAI::is_enabled() ? 'openai' : 'template',
            'insert_content' => true,
            'dry_run' => false,
        ]);
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::MODEL_PT) {
            return ['ok' => false, 'message' => 'Invalid post or type'];
        }
        $ctx = self::build_ctx_model($post_id, $post->post_title, $args);
        // DEBUG: Admin AJAX generate funnels through here; default strategy previously forced Template and skipped the OpenAI model prompt.
        $provider = self::provider($args['strategy']);
        $payload = $provider->generate_model($ctx);
        $rm_model = self::compose_rankmath_for_model($post, [
            'name' => $post->post_title,
        ]);
        $payload['keywords'] = array_merge([$rm_model['focus']], $rm_model['extras'] ?? []);
        if (empty($payload['title'])) {
            return ['ok' => false, 'message' => 'Generator returned empty payload'];
        }
        if ($args['dry_run']) {
            return ['ok' => true, 'payload' => $payload, 'dry_run' => true];
        }
        self::write_all($post_id, $payload, 'MODEL', !empty($args['insert_content']), $ctx);
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
        return self::get_video_model_name($post);
    }

    public static function get_video_model_name(\WP_Post $post): string {
        $raw_name = self::get_video_model_name_raw($post);
        $safe_name = self::sanitize_sfw_text($raw_name, 'Live Cam Model');

        if (defined('TMW_DEBUG') && TMW_DEBUG && $raw_name === '') {
            error_log(self::TAG . " [MODEL] fallback name used for video#{$post->ID}");
        }

        return $safe_name;
    }

    public static function get_video_model_name_raw(\WP_Post $post): string {
        $raw_name = trim((string) get_post_meta($post->ID, 'tmwseo_model_name', true));

        if ($raw_name === '' && taxonomy_exists('models')) {
            $model_terms = wp_get_post_terms($post->ID, 'models', ['fields' => 'names']);
            if (!is_wp_error($model_terms) && !empty($model_terms)) {
                $raw_name = trim((string) $model_terms[0]);
            }
        }

        return $raw_name;
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
        $brand = ucfirst(self::brand_order()[0] ?? $site);
        $focus = self::normalize_manual_focus_keyword(
            (string) get_post_meta($video_id, 'rank_math_focus_keyword', true)
        );
        $extras = [
            'highlight reel',
            'stream recap',
            'fan favorites',
            'cozy watch party',
        ];
        return [
            'video_id' => $video_id,
            'model_id' => $model_id,
            'name' => $name,
            'title' => $title,
            'slug' => $slug,
            'site' => $site,
            'brand' => $brand,
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

    public static function video_focus(string $name): string {
        $safe_name = self::sanitize_sfw_text($name, 'Live Cam Model');
        return self::sanitize_sfw_text(sprintf('%s live cam highlights', $safe_name), 'Live Cam Model live cam highlights');
    }

    protected static function build_ctx_model(int $model_id, string $name, array $args): array {
        $looks = self::first_looks($model_id);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $slug = basename(get_permalink($model_id));
        $focus = $name;
        // Use the same extras as RankMath for the model context, when possible.
        $model_post = get_post( $model_id );
        $extras     = $model_post instanceof \WP_Post
            ? self::compute_model_extras( $model_post, [ 'name' => $name ] )
            : self::pick_extras($name, $looks, ['live chat', 'profile', 'schedule']);
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
        foreach (['video_tag', 'post_tag', 'models', 'category', 'livejasmin_tag'] as $tax) {
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
        $keywords = self::sanitize_sfw_keywords((array) $keywords, ['Live Cam Model']);
        $safe_title = self::sanitize_sfw_text((string) $payload['title'], 'Live Cam Model — Live Cam Highlights');
        $safe_desc  = self::sanitize_sfw_text((string) ($payload['meta'] ?? ''), 'Live cam highlights.');

        update_post_meta($post_id, 'rank_math_title', $safe_title);
        update_post_meta($post_id, 'rank_math_description', $safe_desc);
        update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));

        update_post_meta($post_id, 'rank_math_facebook_title', $safe_title);
        update_post_meta($post_id, 'rank_math_facebook_description', $safe_desc);
        update_post_meta($post_id, 'rank_math_twitter_title', $safe_title);
        update_post_meta($post_id, 'rank_math_twitter_description', $safe_desc);
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

        if (!in_array($post->post_type, array_merge(self::video_post_types(), [self::MODEL_PT]), true)) {
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id <= 0) {
            return;
        }

        // Image metadata is now handled centrally via Image_Meta.
        Image_Meta_Generator::generate_for_featured_image($thumb_id, $post);
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
        $manual_title_raw = trim((string) $post->post_title);
        $manual_title = self::sanitize_sfw_text(
            $manual_title_raw,
            $manual_title_raw !== '' ? $manual_title_raw : 'Live Cam Highlights'
        );
        $manual_title = self::normalize_sfw_phrase($manual_title);

        $focus = self::normalize_manual_focus_keyword(
            (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true)
        );

        $title = self::build_video_rankmath_title($focus, $manual_title);
        $desc  = self::build_video_rankmath_description($focus, $manual_title);

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
                sprintf(
                    '%s [RM-VIDEO-SAFE] post#%d focus="%s" title="%s"',
                    self::TAG,
                    $post->ID,
                    $focus,
                    $title
                )
            );
        }

        return [
            'focus'  => $focus,
            'extras' => [],
            'title'  => $title,
            'desc'   => $desc,
        ];
    }

    protected static function model_extra_keyword_pool(): array {
        return [
            'live cam',
            'live cam model',
            'live cam models',
            'live webcam model',
            'webcam model',
            'webcam models',
            'webcam model profile',
            'live cam highlights',
            'cam highlights',
            'cam show recap',
            'model profile',
            'cam stream clips',
            'live cam clips',
            'webcam stream highlights',
            'live webcam chat',
            'live webcam streaming',
            'webcam chat live',
            'live cam show',
            'live cam shows',
            'live cam performers',
            'live cam broadcast',
            'live cam profiles',
            'cam model online',
            'webcam model showcase',
            'cam highlights reel',
        ];
}

    public static function get_model_extra_keyword_pool(): array {
        return self::model_extra_keyword_pool();
    }

    protected static function model_random_extras(int $count = 4): array {
        $pool = self::model_extra_keyword_pool();
        shuffle($pool);
        return array_slice($pool, 0, $count);
    }

    /**
     * Build soft, non-explicit keywords from model tags.
     * Only uses an allow-list of safe descriptive tags (hair, style, vibe, role, etc.).
     */
    protected static function safe_model_tag_keywords(array $looks): array {
        if (empty($looks)) {
            return [];
        }

        // Map lowercased tag names to generic, soft-adult keyword phrases.
        $map = [
            'amateur'           => 'amateur cam girl',
            'asian'             => 'asian webcam model',
            'athletic'          => 'athletic webcam model',
            'auburn hair'       => 'auburn hair cam girl',
            'bbw'               => 'curvy bbw webcam model',
            'black hair'        => 'black hair webcam model',
            'blonde'            => 'blonde cam girl',
            'blond hair'        => 'blonde webcam model',
            'blue eyes'         => 'blue eyed cam girl',
            'brown eyes'        => 'brown eyed webcam model',
            'brown hair'        => 'brunette webcam model',
            'brunette'          => 'brunette cam girl',
            'cam girl'          => 'cam girl live',
            'cheerleader'       => 'cheerleader webcam model',
            'college girl'      => 'college girl cam model',
            'cosplay'           => 'cosplay webcam model',
            'curious'           => 'curious cam girl',
            'cute'              => 'cute cam girl',
            'dance'             => 'dancing webcam model',
            'ebony'             => 'ebony webcam model',
            'eye contact'       => 'eye contact on cam',
            'fire red hair'     => 'red hair cam model',
            'glamour'           => 'glamour cam model',
            'glasses'           => 'glasses webcam model',
            'green eyes'        => 'green eyed webcam model',
            'gym'               => 'fit gym cam girl',
            'homemade'          => 'homemade style webcam show',
            'hot'               => 'hot webcam model',
            'hot flirt'         => 'hot flirt cam girl',
            'housewife'         => 'housewife webcam model',
            'innocent'          => 'innocent cam girl',
            'jeans'             => 'jeans webcam show',
            'kitchen'           => 'kitchen cam show',
            'large build'       => 'curvy cam model',
            'latex'             => 'latex outfit cam model',
            'latin'             => 'latin webcam model',
            'latina'            => 'latina cam girl',
            'leather'           => 'leather outfit webcam model',
            'lesbian'           => 'lesbian webcam model',
            'lingerie'          => 'lingerie webcam model',
            'long hair'         => 'long hair webcam girl',
            'long nails'        => 'long nails cam girl',
            'maid'              => 'maid roleplay cam',
            'massage'           => 'massage webcam show',
            'mature'            => 'mature cam model',
            'milf'              => 'milf webcam model',
            'muscular'          => 'muscular webcam model',
            'nurse'             => 'nurse roleplay cam',
            'nylon'             => 'nylon stockings cam model',
            'office'            => 'office roleplay webcam',
            'outdoor'           => 'outdoor webcam show',
            'party'             => 'party webcam show',
            'petite'            => 'petite cam girl',
            'piercing'          => 'pierced webcam model',
            'pink hair'         => 'pink hair cam girl',
            'pool'              => 'poolside webcam show',
            'princess'          => 'princess roleplay cam',
            'public'            => 'public chat cam show',
            'pvc'               => 'pvc outfit cam model',
            'redhead'           => 'redhead cam girl',
            'roleplay'          => 'roleplay webcam show',
            'romantic'          => 'romantic cam model',
            'secretary'         => 'secretary roleplay cam',
            'sensual'           => 'sensual webcam show',
            'sexy'              => 'sexy webcam model',
            'short girl'        => 'short cam girl',
            'short hair'        => 'short hair webcam model',
            'shoulder lenght hair' => 'shoulder length hair cam girl',
            'shy'               => 'shy webcam girl',
            'skinny'            => 'slim webcam model',
            'smoking'           => 'smoking cam girl',
            'solo'              => 'solo webcam show',
            'sologirl'          => 'solo girl webcam',
            'stockings'         => 'stockings webcam model',
            'striptease'        => 'striptease cam show',
            'tall'              => 'tall webcam model',
            'tattoo'            => 'tattooed cam girl',
            'teacher'           => 'teacher roleplay webcam',
            'teasing'           => 'teasing cam girl',
            'uniform'           => 'uniform roleplay webcam',
            'white'             => 'white webcam model',
        ];

        $keywords = [];
        foreach ($looks as $raw) {
            $key = strtolower(trim($raw));
            if (isset($map[$key])) {
                $keywords[] = $map[$key];
            }
        }

        return self::sanitize_sfw_keywords(array_values(array_unique($keywords)));
    }

    public static function get_safe_model_tag_keywords(array $looks): array {
        return self::safe_model_tag_keywords($looks);
    }

    /**
     * Compute the extra keywords for a model post, based on safe tags and
     * the generic soft adult pool. Uses tags from the model itself and,
     * when available, from its latest linked video.
     */
    protected static function compute_model_extras( \WP_Post $post, array $ctx = [] ): array {
        // 1) Collect looks from model.
        $looks = self::first_looks( $post->ID );

        // 2) Also include looks from latest linked video, if any.
        $video_id = (int) get_post_meta( $post->ID, '_tmwseo_latest_video_id', true );
        if ( $video_id ) {
            $looks = array_merge( $looks, self::first_looks( $video_id ) );
        }

        $looks = array_values( array_unique( $looks ) );

        // 3) Build keywords.
        $tag_keywords = self::safe_model_tag_keywords( $looks );
        $generic      = self::sanitize_sfw_keywords( self::model_random_extras( 4 ) );

        $all_extras = array_values( array_unique( array_merge( $tag_keywords, $generic ) ) );
        $extras     = array_slice( $all_extras, 0, 4 );

        return $extras;
    }

    public static function compose_rankmath_for_model( \WP_Post $post, array $ctx ): array {
        $name  = self::sanitize_sfw_text($ctx['name'], 'Live Cam Model');
        $focus = $name; // focus keyword is ONLY the name

        $looks = self::first_looks( $post->ID );
        $video_id = (int) get_post_meta( $post->ID, '_tmwseo_latest_video_id', true );
        if ( $video_id > 0 ) {
            $looks = array_merge( $looks, self::first_looks( $video_id ) );
        }
        $looks = array_values( array_unique( $looks ) );

        $tag_keywords = self::safe_model_tag_keywords( $looks );
        $generic      = self::sanitize_sfw_keywords( self::model_random_extras( 4 ) );

        $all_extras = array_values( array_unique( array_merge( $tag_keywords, $generic ) ) );
        $extras     = array_slice( $all_extras, 0, 4 );

        if (class_exists(__NAMESPACE__ . '\\RankMath') && method_exists(RankMath::class, 'generate_model_snippet_title')) {
            $title = RankMath::generate_model_snippet_title($post);
        } else {
            $title = sprintf('%s — Live Cam Model Profile & Schedule', $name);
        }
        $desc  = sprintf(
            '%s on Top Models Webcam. Profile, photos, schedule tips, and live chat links. Follow %s for highlights and updates.',
            $name,
            $name
        );

        error_log(self::TAG . " [MODEL-EXTRAS] post#{$post->ID} looks=" . json_encode($looks) . " tag_kw=" . json_encode($tag_keywords) . " generic=" . json_encode($generic) . " extras=" . json_encode($extras));
        error_log(self::TAG . " [RM-MODEL] focus='{$focus}' extras=" . json_encode($extras) . " for post#{$post->ID}");

        return [
            'focus' => $focus,
            'extras' => $extras,
            'title' => $title,
            'desc'  => $desc,
        ];
    }

    public static function update_rankmath_meta(int $post_id, array $rm, bool $protect_manual = false, bool $preserve_focus = false): void {
        $existing_focus = trim((string) get_post_meta($post_id, 'rank_math_focus_keyword', true));
        $safe_title = self::sanitize_sfw_text((string) ($rm['title'] ?? ''), 'Live Cam Model — Live Cam Highlights');
        $safe_desc  = self::sanitize_sfw_text((string) ($rm['desc'] ?? ''), 'Live cam highlights.');

        $post = get_post($post_id);
        if ($post instanceof \WP_Post && self::is_video_post_type($post->post_type)) {
            $manual_focus = self::normalize_manual_focus_keyword($existing_focus);
            $kw = $manual_focus !== '' ? [$manual_focus] : [];
            if (!empty($kw)) {
                $kw = self::sanitize_sfw_keywords($kw, ['Live Cam Model']);
                update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $kw));
            }
        } elseif ( $preserve_focus && $existing_focus !== '' ) {
            $kw = array_filter(array_map('trim', explode(',', (string) $existing_focus)));
            $kw = self::sanitize_sfw_keywords($kw, ['Live Cam Model']);
            update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $kw));
        } else {
            $kw = array_filter(array_map('trim', array_merge([$rm['focus']], $rm['extras'] ?? [])));
            $kw = self::sanitize_sfw_keywords($kw, ['Live Cam Model']);
            update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $kw));
        }

        $existing_title = get_post_meta($post_id, 'rank_math_title', true);
        $existing_desc  = get_post_meta($post_id, 'rank_math_description', true);

        $should_update_title = !$protect_manual || $existing_title === '' || self::is_old_video_title($existing_title, $rm['focus']);
        $should_update_desc  = !$protect_manual || $existing_desc === '' || self::is_old_video_description($existing_desc, $rm['focus']);

        if ($should_update_title) {
            update_post_meta($post_id, 'rank_math_title', $safe_title);
        }

        if ($should_update_desc) {
            update_post_meta($post_id, 'rank_math_description', $safe_desc);
        }

        update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        $focus_for_log = $preserve_focus && $existing_focus !== '' ? $existing_focus : $rm['focus'];
        error_log(self::TAG . " [RM] set focus='" . $focus_for_log . "' extras=" . json_encode($rm['extras']) . " for post#$post_id");
    }

    public static function normalize_manual_focus_keyword(string $focus): string {
        $focus = trim($focus);
        if ($focus === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $focus);
        $first = $parts[0] ?? '';
        $first = self::sanitize_sfw_text($first, '');
        return trim($first);
    }

    protected static function build_video_rankmath_title(string $focus, string $manual_title): string {
        if ($focus === '') {
            return self::truncate_seo_text($manual_title, 60);
        }

        if (stripos($manual_title, $focus) === 0) {
            return self::truncate_seo_text($manual_title, 60);
        }

        return self::truncate_seo_text(sprintf('%s: %s', $focus, $manual_title), 60);
    }

    protected static function build_video_rankmath_description(string $focus, string $manual_title): string {
        $desc = sprintf(
            'Watch %s webcam highlights. %s — full recap and moments.',
            $focus,
            $manual_title
        );
        return self::truncate_seo_text($desc, 180);
    }

    protected static function truncate_seo_text(string $text, int $max): string {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $trimmed = mb_substr($text, 0, $max);
        $last_space = mb_strrpos($trimmed, ' ');
        if ($last_space !== false && $last_space > 20) {
            $trimmed = mb_substr($trimmed, 0, $last_space);
        }
        return rtrim($trimmed, " \t\n\r\0\x0B-—–,:;");
    }

    protected static function is_old_video_title(string $title, string $focus): bool {
        return stripos($title, $focus) !== false && stripos($title, 'featured moments') !== false;
    }

    protected static function is_old_video_description(string $desc, string $focus): bool {
        return stripos($desc, $focus) !== false && stripos($desc, 'quick reel') !== false;
    }

    protected static function build_video_post_title( \WP_Post $post, string $focus, string $model_name ): string {
        $original = trim( (string) $post->post_title );
        $focus    = trim( (string) $focus );

        if ( $original !== '' && stripos( $original, $focus ) !== false ) {
            return $original;
        }

        if ( $original !== '' ) {
            return sprintf( '%s — %s', $focus, $original );
        }

        return $focus;
    }

    public static function maybe_update_video_title( \WP_Post $post, string $focus, string $model_name ): void {
        $post_id = $post->ID;

        $post = get_post( $post_id );
        if (
            ! ( $post instanceof \WP_Post ) ||
            ! in_array( $post->post_type, self::video_post_types(), true ) ||
            $post->post_status !== 'publish'
        ) {
            return;
        }

        $original       = trim( (string) $post->post_title );
        $too_long       = mb_strlen( $original ) > 110;
        $has_brand_tail = stripos( $original, 'Only on Top-Models.Webcam' ) !== false;

        // If we've already adjusted this title once, never touch it again unless it's clearly legacy/too long.
        if ( get_post_meta( $post_id, '_tmwseo_video_title_locked', true ) && ! ( $too_long || $has_brand_tail ) ) {
            return;
        }

        $existing_focus = trim( (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) );
        if ( $existing_focus !== '' && ! $too_long && ! $has_brand_tail ) {
            return;
        }

        $rewrite = self::rewrite_sfw_video_title( $post );
        $focus   = self::sanitize_sfw_text( (string) $focus, 'Live Cam Model live cam highlights' );
        if ( $focus === '' || $rewrite['title'] === '' ) {
            return;
        }

        $new_title = self::sanitize_sfw_text( (string) $rewrite['title'], 'Live Cam Model — Live Cam Highlights' );

        // Keep titles reasonable for SERPs.
        if ( mb_strlen( $new_title ) > 110 ) {
            $new_title = trim( mb_substr( $new_title, 0, 107 ) ) . '…';
        }

        // Lock immediately to prevent re-entrancy when wp_update_post triggers save_post again.
        update_post_meta( $post_id, '_tmwseo_video_title_locked', 1 );

        if ( $new_title !== '' && $new_title !== $post->post_title ) {
            // Prevent transition recursion when updating the title.
            remove_action( 'transition_post_status', [ 'TMW_SEO\\VideoSEO', 'on_transition' ], 35 );
            wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => $new_title,
                    'post_name'  => $post->post_name,
                ]
            );
            add_action( 'transition_post_status', [ 'TMW_SEO\\VideoSEO', 'on_transition' ], 35, 3 );
        }

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
                sprintf(
                    '%s [VIDEO-H1] #%d type=%s old="%s" new="%s" focus="%s"',
                    self::TAG,
                    $post_id,
                    $post->post_type,
                    $post->post_title,
                    $new_title,
                    $focus
                )
            );
        }
    }

    public static function maybe_update_video_slug( \WP_Post $post, string $focus ): void {
        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log(self::TAG . " [VIDEO-SLUG] Skipping slug update for post#{$post->ID}");
        }
        return;

        if ( ! in_array( $post->post_type, self::video_post_types(), true ) ) {
            return;
        }

        $original_slug = (string) $post->post_name;
        $too_long      = mb_strlen( $original_slug ) > 80;

        if ( get_post_meta( $post->ID, '_tmwseo_slug_locked', true ) && ! $too_long ) {
            return;
        }

        $slug_focus = sanitize_title( $focus );
        if ( ! $slug_focus ) {
            return;
        }

        if ( strpos( $original_slug, $slug_focus ) !== false && ! $too_long ) {
            update_post_meta( $post->ID, '_tmwseo_slug_locked', 1 );
            return;
        }

        wp_update_post(
            [
                'ID'        => $post->ID,
                'post_name' => $slug_focus,
            ]
        );
        update_post_meta( $post->ID, '_tmwseo_slug_locked', 1 );
    }

    public static function sanitize_sfw_text(string $text, string $fallback = 'Live Cam Model'): string {
        $clean = wp_strip_all_tags($text);
        $clean = trim($clean);
        if ($clean === '') {
            return $fallback;
        }

        $blacklist = [
            'adult',
            'anal',
            'ass',
            'bdsm',
            'blowjob',
            'boobs',
            'boob',
            'bj',
            'bukkake',
            'butt',
            'cameltoe',
            'cock',
            'cum',
            'cumming',
            'cunt',
            'dick',
            'deepthroat',
            'erotic',
            'explicit',
            'fetish',
            'fuck',
            'fucking',
            'handjob',
            'hardcore',
            'horny',
            'hot',
            'milf',
            'naked',
            'nude',
            'orgasm',
            'orgy',
            'penis',
            'porn',
            'porno',
            'pussy',
            'sex',
            'sexual',
            'sexy',
            'strip',
            'striptease',
            'threesome',
            'tits',
            'xxx',
            'nsfw',
        ];

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $blacklist)) . ')\b/i';
        $clean = preg_replace($pattern, '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B-—–,:;|");

        if ($clean === '' || mb_strlen($clean) < 3) {
            return $fallback;
        }

        return $clean;
    }

    protected static function normalize_sfw_phrase(string $text): string {
        $text = preg_replace('/\s*[-–—]+\s*/', ' ', $text);
        $text = preg_replace('/\s*[:;|]+\s*/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text, " \t\n\r\0\x0B-—–,:;|");
    }

    protected static function video_category_fallback(int $post_id): string {
        if (!taxonomy_exists('video_category')) {
            return 'Live Cam';
        }
        $terms = wp_get_post_terms($post_id, 'video_category', ['fields' => 'names']);
        if (is_wp_error($terms) || empty($terms)) {
            return 'Live Cam';
        }
        return (string) $terms[0];
    }

    public static function rewrite_sfw_video_title(\WP_Post $post): array {
        $raw_title  = (string) $post->post_title;
        $sfw_phrase = self::sanitize_sfw_text($raw_title, '');
        $sfw_phrase = self::normalize_sfw_phrase($sfw_phrase);

        if ($sfw_phrase === '' || mb_strlen($sfw_phrase) < 8) {
            $fallback  = self::video_category_fallback((int) $post->ID);
            $sfw_phrase = self::sanitize_sfw_text($fallback, 'Live Cam');
            $sfw_phrase = self::normalize_sfw_phrase($sfw_phrase);
        }

        if ($sfw_phrase === '' || mb_strlen($sfw_phrase) < 8) {
            $sfw_phrase = 'Live Cam';
        }

        $suffixes = [
            'Live Cam Highlights',
            'Webcam Highlights',
            'Live Cam Moments',
            'Stream Highlights',
            'Cam Session Highlights',
        ];
        $seed  = absint($post->ID ?: crc32($raw_title));
        $suffix = $suffixes[$seed % count($suffixes)];

        $final_title = sprintf('%s — %s', $sfw_phrase, $suffix);

        $final_focus = strtolower($sfw_phrase . ' live cam highlights');
        $final_focus = self::sanitize_sfw_text($final_focus, 'Live Cam live cam highlights');
        $final_focus = strtolower($final_focus);

        return [
            'raw'   => $raw_title,
            'phrase' => $sfw_phrase,
            'title' => $final_title,
            'focus' => $final_focus,
        ];
    }

    protected static function sanitize_sfw_keywords(array $keywords, array $fallbacks = []): array {
        $out = [];
        foreach ($keywords as $keyword) {
            $clean = self::sanitize_sfw_text((string) $keyword, '');
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        $out = array_values(array_unique($out));

        if (empty($out) && !empty($fallbacks)) {
            foreach ($fallbacks as $fallback) {
                $clean = self::sanitize_sfw_text((string) $fallback, '');
                if ($clean !== '') {
                    $out[] = $clean;
                }
            }
            $out = array_values(array_unique($out));
        }

        return $out;
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
