<?php
/**
 * Admin UI handlers for TMW SEO Autopilot.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Admin UI and AJAX handlers.
 *
 * @package TMW_SEO
 */
class Admin {
    const TAG = '[TMW-SEO-UI]';
    const CAP = 'manage_options';
    /**
     * Registers admin hooks.
     *
     * @return void
     */
    public static function boot() {
        add_action('add_meta_boxes', [__CLASS__, 'meta_box']);
        add_action('add_meta_boxes', [__CLASS__, 'add_video_metabox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [Admin_UI::class, 'enqueue_admin_assets']);
        add_action('wp_ajax_tmw_seo_generate', [__CLASS__, 'ajax_generate']);
        add_action('wp_ajax_tmw_seo_rollback', [__CLASS__, 'ajax_rollback']);
        add_action('wp_ajax_tmw_get_bulk_models', [__CLASS__, 'ajax_get_bulk_models']);
        add_action('wp_ajax_tmw_bulk_process_batch', [__CLASS__, 'ajax_bulk_process_batch']);
        add_filter('bulk_actions-edit-model', [__CLASS__, 'bulk_action']);
        add_filter('handle_bulk_actions-edit-model', [__CLASS__, 'handle_bulk'], 10, 3);
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
        add_action('admin_init', [__CLASS__, 'redirect_tools_pages']);
        add_action('save_post', [__CLASS__, 'save_video_metabox'], 10, 2);
        add_action('admin_post_tmwseo_generate_now', [__CLASS__, 'handle_generate_now']);
        add_action('admin_post_tmwseo_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_post_tmwseo_save_integrations', [__CLASS__, 'handle_save_integrations']);
        add_action('admin_post_tmwseo_usage_reset', [__CLASS__, 'handle_usage_reset']);
        add_action('admin_notices', [__CLASS__, 'admin_notice']);
        add_action('wp_ajax_tmwseo_serper_test', [__CLASS__, 'ajax_serper_test']);
        add_action('wp_ajax_tmwseo_build_keyword_pack', [__CLASS__, 'ajax_build_keyword_pack']);
        add_action('wp_ajax_tmwseo_autofill_google_keywords', [__CLASS__, 'ajax_autofill_google_keywords']);
    }

    /**
     * Handles the `admin_enqueue_scripts` hook.
     *
     * @param string $hook Admin page hook.
     * @return void
     */
    public static function assets($hook) {
        if (strpos($hook, 'tmw-seo-autopilot') !== false) {
            wp_enqueue_style('tmw-seo-admin', TMW_SEO_URL . 'assets/admin.css', [], '0.8.0');
        }
    }

    /**
     * Handles the `add_meta_boxes` hook for models.
     *
     * @return void
     */
    public static function meta_box() {
        add_meta_box('tmw-seo-box', 'TMW SEO Autopilot', [__CLASS__, 'render_box'], 'model', 'side', 'high');
    }

    /**
     * Renders the model meta box.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public static function render_box($post) {
        wp_nonce_field('tmw_seo_box', 'tmw_seo_nonce');
        $openai_enabled = \TMW_SEO\Providers\OpenAI::is_enabled();
        $default_strategy = $openai_enabled ? 'openai' : 'template';
        $template_selected = $default_strategy === 'template' ? 'selected' : '';
        $openai_selected = $default_strategy === 'openai' ? 'selected' : '';

        echo '<p>Generate RankMath fields + intro/bio/FAQ for this model.</p>';
        echo '<p><label><input type="checkbox" id="tmw_seo_insert" checked> Insert content block</label></p>';
        echo '<p>Strategy: <select id="tmw_seo_strategy">';
        echo '<option value="template" ' . $template_selected . '>Template</option>';
        echo '<option value="openai" ' . $openai_selected . '>OpenAI (if configured)</option>';
        echo '</select></p>';
        echo '<p><button type="button" class="button button-primary" id="tmw_seo_generate_btn">Generate</button> <button type="button" class="button" id="tmw_seo_rollback_btn">Rollback</button></p>';
        ?>
        <script>
        (function($){
            $('#tmw_seo_generate_btn').on('click', function(){
                var data = {
                    action: 'tmw_seo_generate',
                    nonce: '<?php echo wp_create_nonce('tmw_seo_nonce'); ?>',
                    post_id: <?php echo (int)$post->ID; ?>,
                    insert: $('#tmw_seo_insert').is(':checked') ? 1 : 0,
                    strategy: $('#tmw_seo_strategy').val()
                };
                $(this).prop('disabled', true).text('Generating…');
                $.post(ajaxurl, data, function(resp){
                    alert(resp.data && resp.data.message ? resp.data.message : (resp.success ? 'Done' : 'Failed'));
                    location.reload();
                });
            });
            $('#tmw_seo_rollback_btn').on('click', function(){
                var data = {
                    action: 'tmw_seo_rollback',
                    nonce: '<?php echo wp_create_nonce('tmw_seo_nonce'); ?>',
                    post_id: <?php echo (int)$post->ID; ?>
                };
                $.post(ajaxurl, data, function(resp){
                    alert(resp.success ? 'Rollback complete' : 'Nothing to rollback');
                    location.reload();
                });
            });
        })(jQuery);
        </script>
        <?php
        }

    /**
     * Handles the `wp_ajax_tmw_get_bulk_models` hook.
     *
     * @return void
     */
    public static function ajax_get_bulk_models() {
        check_ajax_referer('tmw_bulk_generate', 'nonce');

        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $selection = sanitize_text_field($_POST['selection'] ?? 'no_content');

        $args = [
            'post_type'      => Core::MODEL_PT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if ($selection === 'no_content') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'rank_math_focus_keyword',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'rank_math_focus_keyword',
                    'value'   => '',
                    'compare' => '=',
                ],
            ];
        } elseif ($selection === 'range') {
            $start            = max(1, intval($_POST['range_start'] ?? 1));
            $end              = max($start, intval($_POST['range_end'] ?? 100));
            $args['post__in'] = range($start, $end);
        }

        $models = get_posts($args);

        wp_send_json_success(['models' => $models]);
    }

    /**
     * Handles the `wp_ajax_tmw_bulk_process_batch` hook.
     *
     * @return void
     */
    public static function ajax_bulk_process_batch() {
        check_ajax_referer('tmw_bulk_generate', 'nonce');

        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $batch            = array_map('intval', $_POST['batch'] ?? []);
        $strategy         = sanitize_text_field($_POST['strategy'] ?? 'template');
        $check_uniqueness = !empty($_POST['check_uniqueness']);
        $generate_schema  = !empty($_POST['generate_schema']);

        $allowed_strategies = ['template'];
        if ( \TMW_SEO\Providers\OpenAI::is_enabled() ) {
            $allowed_strategies[] = 'openai';
        }
        if ( ! in_array( $strategy, $allowed_strategies, true ) ) {
            $strategy = 'template';
        }

        $results = [
            'success'  => 0,
            'failed'   => 0,
            'skipped'  => 0,
            'messages' => [],
        ];

        foreach ($batch as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $results['skipped']++;
                $results['messages'][] = "⊘ Model #{$post_id}: Not found";
                continue;
            }

            if ($post->post_type !== Core::MODEL_PT) {
                $results['skipped']++;
                $results['messages'][] = "⊘ Model #{$post_id}: Wrong type";
                continue;
            }

            $result = Core::generate_and_write(
                $post_id,
                [
                    'strategy'       => $strategy,
                    'insert_content' => true,
                    'check_unique'   => $check_uniqueness,
                    'schema'         => $generate_schema,
                ]
            );

            if (!empty($result['ok'])) {
                $results['success']++;
                $results['messages'][] = '✓ Model #' . $post_id . ': ' . $post->post_title;
            } else {
                $results['failed']++;
                $results['messages'][] = '✗ Model #' . $post_id . ': ' . ($result['message'] ?? 'Unknown error');
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Handles the `wp_ajax_tmw_seo_generate` hook.
     *
     * @return void
     */
    public static function ajax_generate() {
        check_ajax_referer('tmw_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'No permission']);
        $post_id = (int)($_POST['post_id'] ?? 0);
        $default_strategy = \TMW_SEO\Providers\OpenAI::is_enabled() ? 'openai' : 'template';
        $strategy = sanitize_text_field($_POST['strategy'] ?? $default_strategy);
        $insert = !empty($_POST['insert']);
        $res = Core::generate_and_write($post_id, ['strategy' => $strategy, 'insert_content' => $insert]);
        if ($res['ok']) wp_send_json_success(['message' => 'SEO generated']);
        wp_send_json_error(['message' => $res['message'] ?? 'Error']);
    }

    /**
     * Handles the `wp_ajax_tmw_seo_rollback` hook.
     *
     * @return void
     */
    public static function ajax_rollback() {
        check_ajax_referer('tmw_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error();
        $post_id = (int)($_POST['post_id'] ?? 0);
        $res = Core::rollback($post_id);
        $res['ok'] ? wp_send_json_success() : wp_send_json_error();
    }

    /**
     * Handles the `wp_ajax_tmwseo_serper_test` hook.
     *
     * @return void
     */
    public static function ajax_serper_test() {
        check_ajax_referer('tmwseo_serper_test', 'nonce');
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $api_key = trim((string) get_option('tmwseo_serper_api_key', ''));
        if ($api_key === '') {
            wp_send_json_error(['message' => 'API key missing']);
        }

        $gl = sanitize_text_field((string) get_option('tmwseo_serper_gl', 'us'));
        $hl = sanitize_text_field((string) get_option('tmwseo_serper_hl', 'en'));

        $result = Serper_Client::search($api_key, 'tmw seo autopilot keyword test', $gl, $hl, 5);
        if (!empty($result['error'])) {
            wp_send_json_error(['message' => $result['error']]);
        }

        $keywords = Serper_Client::extract_keywords($result['data'] ?? []);
        wp_send_json_success([
            'message'  => 'Success',
            'keywords' => array_slice($keywords, 0, 5),
        ]);
    }

    /**
     * Handles the `wp_ajax_tmwseo_build_keyword_pack` hook.
     *
     * @return void
     */
    public static function ajax_build_keyword_pack() {
        check_ajax_referer('tmwseo_build_keyword_pack', 'nonce');
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $lock_key = 'tmwseo_serper_build_lock';
        if (get_transient($lock_key)) {
            wp_send_json_error(['message' => 'Build already running']);
        }

        set_transient($lock_key, 1, 30);

        $fail = function ($message) use ($lock_key) {
            delete_transient($lock_key);
            wp_send_json_error(['message' => $message]);
        };

        $categories = Keyword_Library::categories();
        $category   = sanitize_key($_POST['category'] ?? '');
        if (!in_array($category, $categories, true)) {
            $fail('Invalid category');
        }

        $seeds_input = $_POST['seeds'] ?? [];
        if (is_string($seeds_input)) {
            $seeds_input = preg_split('/\r?\n/', $seeds_input);
        }

        $seeds = [];
        foreach ((array) $seeds_input as $seed) {
            $seed = trim(sanitize_text_field((string) $seed));
            if ($seed !== '') {
                $seeds[] = $seed;
            }
        }

        if (empty($seeds)) {
            $fail('Please provide at least one seed.');
        }

        $provider = sanitize_text_field($_POST['provider'] ?? (string) get_option('tmwseo_keyword_provider', 'serper'));
        $allowed_providers = ['serper', 'google_suggest', 'semrush'];
        if (!in_array($provider, $allowed_providers, true)) {
            $provider = 'serper';
        }

        if ($provider === 'semrush') {
            $fail('Semrush integration is coming soon.');
        }

        if ($provider === 'serper') {
            $api_key = trim((string) get_option('tmwseo_serper_api_key', ''));
            if ($api_key === '') {
                $fail('Serper API key missing.');
            }
        }

        $gl             = sanitize_text_field($_POST['gl'] ?? (string) get_option('tmwseo_serper_gl', 'us'));
        $hl             = sanitize_text_field($_POST['hl'] ?? (string) get_option('tmwseo_serper_hl', 'en'));
        $per_seed       = max(1, min(50, (int) ($_POST['per_seed'] ?? 10)));
        $include_comp   = !empty($_POST['competitor']);
        $dry_run        = !empty($_POST['dry_run']);

        $run_state = [
            'max_calls' => 25,
        ];
        $build = Keyword_Pack_Builder::generate($category, $seeds, $gl, $hl, $per_seed, $provider, $run_state);
        if (is_wp_error($build)) {
            delete_transient($lock_key);
            $data = (array) $build->get_error_data();
            wp_send_json_error(
                [
                    'message'  => $build->get_error_message(),
                    'provider' => $data['provider'] ?? $provider,
                    'query'    => $data['query'] ?? '',
                    'details'  => $data['details'] ?? '',
                ],
                500
            );
        }

        $response = [
            'message' => $dry_run ? 'Dry run completed.' : 'Keyword packs built.',
            'preview' => $build,
        ];
        $debug_enabled = (defined('TMWSEO_SERPER_DEBUG') && TMWSEO_SERPER_DEBUG)
            || (defined('TMWSEO_KW_DEBUG') && TMWSEO_KW_DEBUG);
        if ($debug_enabled && isset($run_state['quality_report'])) {
            $response['quality_report'] = $run_state['quality_report'];
        }

        if (!$dry_run) {
            $counts = [];
            $counts['extra']    = Keyword_Pack_Builder::merge_write_csv($category, 'extra', $build['extra'] ?? []);
            $counts['longtail'] = Keyword_Pack_Builder::merge_write_csv($category, 'longtail', $build['longtail'] ?? []);

            if ($include_comp) {
                $comp_seeds = apply_filters('tmwseo_competitor_seeds', ['livejasmin vs chaturbate', 'livejasmin vs stripchat']);
                $comp_build = Keyword_Pack_Builder::generate($category, (array) $comp_seeds, $gl, $hl, $per_seed, $provider, $run_state);
                if (is_wp_error($comp_build)) {
                    delete_transient($lock_key);
                    $data = (array) $comp_build->get_error_data();
                    wp_send_json_error(
                        [
                            'message'  => $comp_build->get_error_message(),
                            'provider' => $data['provider'] ?? $provider,
                            'query'    => $data['query'] ?? '',
                            'details'  => $data['details'] ?? '',
                        ],
                        500
                    );
                }
                $comp_kw    = array_merge($comp_build['extra'] ?? [], $comp_build['longtail'] ?? []);
                $counts['competitor'] = Keyword_Pack_Builder::merge_write_csv($category, 'competitor', $comp_kw);
            }

            Keyword_Library::flush_cache();

            $uploads_base = Keyword_Library::uploads_base_dir();
            $response['paths']  = [
                'extra'      => trailingslashit($uploads_base) . "{$category}/extra.csv",
                'longtail'   => trailingslashit($uploads_base) . "{$category}/longtail.csv",
                'competitor' => trailingslashit($uploads_base) . "{$category}/competitor.csv",
            ];
            $response['counts'] = $counts;
        }

        delete_transient($lock_key);
        wp_send_json_success($response);
    }

    /**
     * Handles the `wp_ajax_tmwseo_autofill_google_keywords` hook.
     *
     * @return void
     */
    public static function ajax_autofill_google_keywords() {
        if (!check_ajax_referer('tmwseo_autofill_google_keywords', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce.', 'http_status' => 403], 403);
        }
        if (!current_user_can(self::CAP)) {
            wp_send_json_error(['message' => 'No permission.', 'http_status' => 403], 403);
        }

        // Endpoint: https://suggestqueries.google.com/complete/search
        // Root cause (from logs): prior multi-category batches exceeded PHP execution time; switch to seed cursor.
        // Batch size: 2 seeds (~10-20 suggestions) per call; retries on 429/503 with exponential backoff.
        $categories = Keyword_Library::categories();
        $category_index = max(0, (int) ($_POST['category_index'] ?? 0));
        $seed_offset = max(0, (int) ($_POST['seed_offset'] ?? 0));
        $dry_run    = !empty($_POST['dry_run']);

        if ($category_index >= count($categories)) {
            wp_send_json_success([
                'done'               => true,
                'cursor'             => [
                    'category_index' => $category_index,
                    'seed_offset'    => $seed_offset,
                ],
                'categories'         => [],
                'batch_keywords'     => 0,
                'total_categories'   => count($categories),
                'completed_categories' => count($categories),
                'errors'             => [],
            ]);
        }

        // Use saved locale settings for Google Autocomplete.
        $options = [
            'gl' => (string) get_option('tmwseo_serper_gl', 'us'),
            'hl' => (string) get_option('tmwseo_serper_hl', 'en'),
            'per_seed' => 10,
            'rate_limit_ms' => 200,
            'seed_batch_size' => 2,
            'accept_language' => 'en-US,en;q=0.9',
        ];

        $result = Keyword_Pack_Builder::autofill_google_autocomplete_batch(
            $categories,
            [
                'category_index' => $category_index,
                'seed_offset'    => $seed_offset,
            ],
            $dry_run,
            $options
        );

        if (!$dry_run) {
            Keyword_Library::flush_cache();
        }

        wp_send_json_success([
            'done'                => $result['done'] ?? false,
            'cursor'              => $result['cursor'] ?? [
                'category_index' => $category_index,
                'seed_offset'    => $seed_offset,
            ],
            'categories'          => $result['categories'] ?? [],
            'batch_keywords'      => $result['batch_keywords'] ?? 0,
            'completed_categories'=> $result['completed_categories'] ?? 0,
            'total_categories'    => count($categories),
            'errors'              => $result['errors'] ?? [],
        ]);
    }

    /**
     * Handles the `bulk_actions-edit-model` filter.
     *
     * @param array $actions Bulk action list.
     * @return array
     */
    public static function bulk_action($actions) {
        $actions['tmw_seo_generate_bulk'] = 'Generate SEO (TMW)';
        return $actions;
    }

    /**
     * Handles the `handle_bulk_actions-edit-model` filter.
     *
     * @param string $redirect Redirect URL.
     * @param string $doaction Action name.
     * @param array  $ids Selected post IDs.
     * @return string
     */
    public static function handle_bulk($redirect, $doaction, $ids) {
        if ($doaction !== 'tmw_seo_generate_bulk') return $redirect;
        $count = 0;
        foreach ($ids as $id) {
            $r = Core::generate_and_write((int)$id, ['strategy' => 'template', 'insert_content' => true]);
            if (!empty($r['ok'])) $count++;
        }
        return add_query_arg('tmw_seo_bulk_done', $count, $redirect);
    }

    /**
     * Registers the admin menu structure.
     *
     * @return void
     */
    public static function register_menu() {
        add_menu_page(
            'TMW SEO Autopilot',
            'TMW SEO Autopilot',
            self::CAP,
            'tmw-seo-autopilot',
            [__CLASS__, 'render_tools'],
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'tmw-seo-autopilot',
            'Dashboard',
            'Dashboard',
            self::CAP,
            'tmw-seo-autopilot',
            [__CLASS__, 'render_tools']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'TMW SEO Keyword Packs',
            'Keyword Packs',
            self::CAP,
            'tmw-seo-keyword-packs',
            [__CLASS__, 'render_keyword_packs']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'TMW SEO Keyword Usage',
            'Keyword Usage',
            self::CAP,
            'tmw-seo-keyword-usage',
            [__CLASS__, 'render_keyword_usage']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'TMW SEO Usage',
            'Usage / CSV Stats',
            self::CAP,
            'tmw-seo-usage',
            [__CLASS__, 'render_usage_dashboard']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'Automations / Scheduled Actions',
            'Automations',
            self::CAP,
            'tmw-seo-scheduled-actions',
            [__CLASS__, 'render_scheduled_actions']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'Codex Reports',
            'Codex Reports',
            self::CAP,
            'tmw-seo-codex-reports',
            [__CLASS__, 'render_codex_reports']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'TMW SEO Settings',
            'Settings',
            self::CAP,
            'tmw-seo-settings',
            [__CLASS__, 'render_settings']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'Integrations',
            'Integrations',
            self::CAP,
            'tmw-seo-integrations',
            [__CLASS__, 'render_integrations']
        );
        add_submenu_page(
            'tmw-seo-autopilot',
            'OpenAI Integration',
            'OpenAI Integration',
            self::CAP,
            'tmw-seo-openai',
            [__CLASS__, 'render_openai_integration']
        );

        remove_submenu_page('tmw-seo-autopilot', 'tmw-seo-autopilot');
        remove_submenu_page('tmw-seo-autopilot', 'tmw-seo-openai');
    }

    /**
     * Redirects legacy Tools URLs to the top-level menu.
     *
     * @return void
     */
    public static function redirect_tools_pages() {
        if (!is_admin()) {
            return;
        }

        global $pagenow;
        $page = sanitize_key($_GET['page'] ?? '');
        if ($pagenow === 'tools.php' && $page !== '' && strpos($page, 'tmw-seo-') === 0) {
            wp_safe_redirect(admin_url('admin.php?page=' . $page));
            exit;
        }
    }

    /**
     * Renders the scheduled actions placeholder page.
     *
     * @return void
     */
    public static function render_scheduled_actions() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        self::render_placeholder_page(
            'tmw-seo-scheduled-actions',
            'Automations / Scheduled Actions',
            'Scheduled actions management will live here. Hook status, queue health, and automation schedules will be surfaced in this view.'
        );
    }

    /**
     * Renders the Codex reports placeholder page.
     *
     * @return void
     */
    public static function render_codex_reports() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        self::render_placeholder_page(
            'tmw-seo-codex-reports',
            'Codex Reports',
            'Reporting dashboards are coming soon. This page will host Codex performance and content analytics.'
        );
    }

    /**
     * Renders the settings landing page.
     *
     * @return void
     */
    public static function render_settings() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        $keyword_packs_url = admin_url('admin.php?page=tmw-seo-keyword-packs');
        $dashboard_url     = admin_url('admin.php?page=tmw-seo-autopilot');
        ?>
        <?php Admin_UI::render_header('tmw-seo-settings', 'Settings', 'Configure global plugin preferences and defaults.'); ?>
            <div class="tmwseo-card">
                <h2>Current configuration</h2>
                <ul>
                    <li><a href="<?php echo esc_url($keyword_packs_url); ?>">Keyword provider selection</a></li>
                    <li><a href="<?php echo esc_url($dashboard_url); ?>">Dashboard controls</a></li>
                </ul>
            </div>
        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Renders the integrations admin page.
     *
     * @return void
     */
    public static function render_integrations() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        $serper_key = (string) get_option('tmwseo_serper_api_key', '');
        $serper_gl  = (string) get_option('tmwseo_serper_gl', 'us');
        $serper_hl  = (string) get_option('tmwseo_serper_hl', 'en');

        $openai_key_option = (string) get_option('tmwseo_openai_api_key', '');
        $openai_constant   = defined('TMW_SEO_OPENAI') ? (string) TMW_SEO_OPENAI : (defined('OPENAI_API_KEY') ? (string) OPENAI_API_KEY : '');
        $openai_active_key = $openai_key_option !== '' ? $openai_key_option : $openai_constant;

        $semrush_key = (string) get_option('tmwseo_semrush_api_key', '');

        $serper_connected  = $serper_key !== '';
        $openai_connected  = $openai_active_key !== '';
        $semrush_connected = $semrush_key !== '';

        $serper_mask  = $serper_connected ? self::mask_secret($serper_key) : '';
        $openai_mask  = $openai_connected ? self::mask_secret($openai_active_key) : '';
        $semrush_mask = $semrush_connected ? self::mask_secret($semrush_key) : '';

        $serper_nonce = wp_create_nonce('tmwseo_serper_test');
        ?>
        <?php Admin_UI::render_header('tmw-seo-integrations', 'Integrations', 'Connect keyword and content providers securely.'); ?>

            <div class="tmwseo-integrations-grid">
                <div class="tmwseo-card">
                    <h3>Serper</h3>
                    <p>Keyword provider for pack building and SERP signals.</p>
                    <span class="tmwseo-status-badge <?php echo $serper_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                        <?php echo $serper_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                    </span>
                    <div class="tmwseo-provider-actions">
                        <a class="button" href="#tmwseo-provider-serper">Configure</a>
                    </div>
                </div>

                <div class="tmwseo-card">
                    <h3>OpenAI</h3>
                    <p>Content provider for model and video generation.</p>
                    <span class="tmwseo-status-badge <?php echo $openai_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                        <?php echo $openai_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                    </span>
                    <div class="tmwseo-provider-actions">
                        <a class="button" href="#tmwseo-provider-openai">Configure</a>
                    </div>
                </div>

                <div class="tmwseo-card">
                    <h3>Semrush</h3>
                    <p>Future keyword provider for competitive insights.</p>
                    <span class="tmwseo-status-badge <?php echo $semrush_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                        <?php echo $semrush_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                    </span>
                    <div class="tmwseo-provider-actions">
                        <a class="button" href="#tmwseo-provider-semrush">Configure</a>
                    </div>
                </div>
            </div>

            <div class="tmwseo-card tmwseo-inline-form" id="tmwseo-provider-serper">
                <h3>Serper Settings</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tmwseo_integrations', 'tmwseo_integrations_nonce'); ?>
                    <input type="hidden" name="action" value="tmwseo_save_integrations">
                    <input type="hidden" name="provider" value="serper">
                    <p>
                        <span class="tmwseo-status-badge <?php echo $serper_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                            <?php echo $serper_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                        </span>
                        <?php if ($serper_connected) : ?>
                            <span class="tmwseo-masked"><?php echo esc_html($serper_mask); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($serper_connected) : ?>
                        <button type="button" class="button tmwseo-change-key" data-target="tmwseo-serper-key-field">Change API key</button>
                    <?php endif; ?>
                    <div id="tmwseo-serper-key-field" class="<?php echo $serper_connected ? 'tmwseo-hidden' : ''; ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="tmwseo_serper_api_key">API Key</label></th>
                                <td>
                                    <input type="password" name="tmwseo_serper_api_key" id="tmwseo_serper_api_key" class="regular-text" value="" autocomplete="new-password">
                                    <label><input type="checkbox" class="tmwseo-reveal" data-target="tmwseo_serper_api_key"> Reveal</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="tmwseo_serper_gl">gl (country)</label></th>
                            <td><input type="text" name="tmwseo_serper_gl" id="tmwseo_serper_gl" value="<?php echo esc_attr($serper_gl); ?>" class="regular-text" placeholder="us"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tmwseo_serper_hl">hl (language)</label></th>
                            <td><input type="text" name="tmwseo_serper_hl" id="tmwseo_serper_hl" value="<?php echo esc_attr($serper_hl); ?>" class="regular-text" placeholder="en"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Settings</button>
                        <button type="button" class="button" id="tmwseo-serper-test" data-nonce="<?php echo esc_attr($serper_nonce); ?>">Test Serper</button>
                        <?php if ($serper_connected) : ?>
                            <button type="submit" class="button" name="tmwseo_disconnect" value="1">Disconnect</button>
                        <?php endif; ?>
                        <span id="tmwseo-serper-result" style="margin-left:10px;"></span>
                    </p>
                </form>
            </div>

            <div class="tmwseo-card tmwseo-inline-form" id="tmwseo-provider-openai">
                <h3>OpenAI Settings</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tmwseo_integrations', 'tmwseo_integrations_nonce'); ?>
                    <input type="hidden" name="action" value="tmwseo_save_integrations">
                    <input type="hidden" name="provider" value="openai">
                    <p>
                        <span class="tmwseo-status-badge <?php echo $openai_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                            <?php echo $openai_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                        </span>
                        <?php if ($openai_connected) : ?>
                            <span class="tmwseo-masked"><?php echo esc_html($openai_mask); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($openai_connected) : ?>
                        <button type="button" class="button tmwseo-change-key" data-target="tmwseo-openai-key-field">Change API key</button>
                    <?php endif; ?>
                    <div id="tmwseo-openai-key-field" class="<?php echo $openai_connected ? 'tmwseo-hidden' : ''; ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="tmwseo_openai_api_key">API Key</label></th>
                                <td>
                                    <input type="password" name="tmwseo_openai_api_key" id="tmwseo_openai_api_key" class="regular-text" value="" autocomplete="new-password">
                                    <label><input type="checkbox" class="tmwseo-reveal" data-target="tmwseo_openai_api_key"> Reveal</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php if ($openai_constant !== '' && $openai_key_option === '') : ?>
                        <p class="description">Currently configured via wp-config.php constant.</p>
                    <?php endif; ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Settings</button>
                        <?php if ($openai_key_option !== '') : ?>
                            <button type="submit" class="button" name="tmwseo_disconnect" value="1">Disconnect</button>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <div class="tmwseo-card tmwseo-inline-form" id="tmwseo-provider-semrush">
                <h3>Semrush Settings</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('tmwseo_integrations', 'tmwseo_integrations_nonce'); ?>
                    <input type="hidden" name="action" value="tmwseo_save_integrations">
                    <input type="hidden" name="provider" value="semrush">
                    <p>
                        <span class="tmwseo-status-badge <?php echo $semrush_connected ? 'tmwseo-status-connected' : 'tmwseo-status-missing'; ?>">
                            <?php echo $semrush_connected ? esc_html__('Connected', 'tmw-seo-autopilot') : esc_html__('Not configured', 'tmw-seo-autopilot'); ?>
                        </span>
                        <?php if ($semrush_connected) : ?>
                            <span class="tmwseo-masked"><?php echo esc_html($semrush_mask); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($semrush_connected) : ?>
                        <button type="button" class="button tmwseo-change-key" data-target="tmwseo-semrush-key-field">Change API key</button>
                    <?php endif; ?>
                    <div id="tmwseo-semrush-key-field" class="<?php echo $semrush_connected ? 'tmwseo-hidden' : ''; ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="tmwseo_semrush_api_key">API Key</label></th>
                                <td>
                                    <input type="password" name="tmwseo_semrush_api_key" id="tmwseo_semrush_api_key" class="regular-text" value="" autocomplete="new-password">
                                    <label><input type="checkbox" class="tmwseo-reveal" data-target="tmwseo_semrush_api_key"> Reveal</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Settings</button>
                        <button type="button" class="button" id="tmwseo-semrush-test">Test connection</button>
                        <?php if ($semrush_connected) : ?>
                            <button type="submit" class="button" name="tmwseo_disconnect" value="1">Disconnect</button>
                        <?php endif; ?>
                        <span id="tmwseo-semrush-result" style="margin-left:10px;"></span>
                    </p>
                </form>
            </div>

            <script>
            (function($){
                $('.tmwseo-change-key').on('click', function(){
                    var target = $(this).data('target');
                    var $target = $('#' + target);
                    $target.removeClass('tmwseo-hidden');
                    $target.find('input[type="password"]').first().focus();
                });

                $('.tmwseo-reveal').on('change', function(){
                    var target = $(this).data('target');
                    var $input = $('#' + target);
                    $input.attr('type', this.checked ? 'text' : 'password');
                });

                $('#tmwseo-serper-test').on('click', function(){
                    var $btn = $(this);
                    var $out = $('#tmwseo-serper-result');
                    $btn.prop('disabled', true);
                    $out.text('Testing…');
                    $.post(ajaxurl, {action: 'tmwseo_serper_test', nonce: $btn.data('nonce')}, function(resp){
                        if (resp && resp.success) {
                            var preview = resp.data && resp.data.keywords ? resp.data.keywords.join(', ') : 'Success';
                            $out.text('Success: ' + preview);
                        } else {
                            var message = resp && resp.data && resp.data.message ? resp.data.message : 'Request failed';
                            $out.text('Error: ' + message);
                        }
                    }).fail(function(){
                        $out.text('Error: request failed');
                    }).always(function(){
                        $btn.prop('disabled', false);
                    });
                });

                $('#tmwseo-semrush-test').on('click', function(){
                    $('#tmwseo-semrush-result').text('Coming soon.');
                });
            })(jQuery);
            </script>

        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Renders the OpenAI integration placeholder page.
     *
     * @return void
     */
    public static function render_openai_integration() {
        if (!current_user_can(self::CAP)) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=tmw-seo-integrations'));
        exit;
    }

    /**
     * Renders a placeholder admin page.
     *
     * @param string $slug Page slug.
     * @param string $title Page title.
     * @param string $message Description text.
     * @return void
     */
    protected static function render_placeholder_page(string $slug, string $title, string $message) {
        ?>
        <?php Admin_UI::render_header($slug, $title); ?>
            <div class="tmwseo-card">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Normalizes admin values for hash usage.
     *
     * @param string $value Raw value.
     * @return string
     */
    protected static function normalize_for_hash_admin(string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return strtolower((string)$value);
    }

    /**
     * Handles the `admin_post_tmwseo_usage_reset` hook.
     *
     * @return void
     */
    public static function handle_usage_reset() {
        if (!current_user_can(self::CAP)) {
            wp_die('Access denied');
        }

        check_admin_referer('tmwseo_usage_reset');

        $enabled = (defined('TMWSEO_ENABLE_USAGE_RESET') && TMWSEO_ENABLE_USAGE_RESET) || apply_filters('tmwseo_enable_usage_reset', false);
        $redirect = add_query_arg('page', 'tmw-seo-usage', admin_url('admin.php'));

        if (!$enabled) {
            wp_safe_redirect(add_query_arg('reset_error', 'disabled', $redirect));
            exit;
        }

        $confirmation = isset($_POST['tmwseo_reset_confirm']) ? trim((string)$_POST['tmwseo_reset_confirm']) : '';
        if ($confirmation !== 'RESET') {
            wp_safe_redirect(add_query_arg('reset_error', 'confirm', $redirect));
            exit;
        }

        delete_option('tmwseo_used_video_title_keys');
        delete_option('tmwseo_used_video_seo_title_hashes');
        delete_option('tmwseo_used_video_focus_keyword_hashes');
        delete_option('tmwseo_used_video_title_focus_hashes');
        delete_option('tmwseo_lock_video_title_keys');

        update_option('tmwseo_used_video_title_keys', [], false);
        update_option('tmwseo_used_video_seo_title_hashes', [], false);
        update_option('tmwseo_used_video_focus_keyword_hashes', [], false);
        update_option('tmwseo_used_video_title_focus_hashes', [], false);

        wp_safe_redirect(add_query_arg('reset', '1', $redirect));
        exit;
    }

    /**
     * Prepares usage sets for admin display.
     *
     * @param array $raw Raw usage data.
     * @return array
     */
    protected static function prepare_used_set($raw): array {
        $set = [];
        if (is_array($raw)) {
            foreach ($raw as $k => $v) {
                if (is_string($v) && is_int($k)) {
                    $set[$v] = true;
                } else {
                    $set[(string)$k] = (bool)$v;
                }
            }
        }
        return $set;
    }

    /**
     * Renders the keyword usage dashboard.
     *
     * @return void
     */
    public static function render_usage_dashboard() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        $csv_path = Core::csv_path('video_seo_titles.csv');
        $rows     = Core::read_csv_assoc($csv_path);

        $valid_rows      = 0;
        $unique_titles   = [];
        $unique_focus    = [];
        $unique_pairs    = [];

        foreach ($rows as $row) {
            $seo_title = trim((string)($row['seo_title'] ?? ''));
            $focus     = trim((string)($row['focus_keyword'] ?? ''));

            if ($seo_title === '' || $focus === '') {
                continue;
            }

            $valid_rows++;

            $normalized_title = self::normalize_for_hash_admin($seo_title);
            $normalized_focus = self::normalize_for_hash_admin($focus);

            $title_hash             = md5($normalized_title);
            $focus_hash             = md5($normalized_focus);
            $pair_hash              = md5($normalized_title . '|' . $normalized_focus);
            $unique_titles[$title_hash] = true;
            $unique_focus[$focus_hash]  = true;
            $unique_pairs[$pair_hash]   = true;
        }

        $used_keys_set        = self::prepare_used_set(get_option('tmwseo_used_video_title_keys', []));
        $used_title_hashes    = self::prepare_used_set(get_option('tmwseo_used_video_seo_title_hashes', []));
        $used_focus_hashes    = self::prepare_used_set(get_option('tmwseo_used_video_focus_keyword_hashes', []));
        $used_pair_hashes     = self::prepare_used_set(get_option('tmwseo_used_video_title_focus_hashes', []));

        $unique_title_count = count($unique_titles);
        $unique_focus_count = count($unique_focus);
        $unique_pair_count  = count($unique_pairs);

        $used_keys_count   = count($used_keys_set);
        $used_title_count  = count($used_title_hashes);
        $used_focus_count  = count($used_focus_hashes);
        $used_pair_count   = count($used_pair_hashes);

        $remaining_titles = max(0, $unique_title_count - $used_title_count);
        $remaining_focus  = max(0, $unique_focus_count - $used_focus_count);
        $remaining_pairs  = max(0, $unique_pair_count - $used_pair_count);

        $reset_enabled = (defined('TMWSEO_ENABLE_USAGE_RESET') && TMWSEO_ENABLE_USAGE_RESET) || apply_filters('tmwseo_enable_usage_reset', false);

        $reset_success = !empty($_GET['reset']);
        $reset_error   = sanitize_text_field($_GET['reset_error'] ?? '');
        ?>
        <?php Admin_UI::render_header('tmw-seo-usage', 'Usage / CSV Stats', 'Monitor keyword usage health and CSV coverage.'); ?>

            <p style="max-width:760px;">This dashboard tracks how many video SEO titles and focus keywords from <code>data/video_seo_titles.csv</code> have been used. "Used" values come from the duplicate checker options and do not modify existing posts. Resetting only clears these trackers; it will <strong>not</strong> change post meta values like <code>_tmwseo_csv_video_title</code>.</p>

            <?php if ($reset_success) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Usage trackers cleared. Future posts may reuse CSV entries.', 'tmw-seo-autopilot'); ?></p></div>
            <?php endif; ?>

            <?php if ($reset_error === 'confirm') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Reset aborted: confirmation text did not match.', 'tmw-seo-autopilot'); ?></p></div>
            <?php elseif ($reset_error === 'disabled') : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Reset is disabled. Enable it via the constant or filter to proceed.', 'tmw-seo-autopilot'); ?></p></div>
            <?php endif; ?>

            <?php if ($remaining_pairs === 0 || $remaining_titles === 0 || $remaining_focus === 0) : ?>
                <div class="notice notice-error"><p><?php echo esc_html__('Video title pool exhausted. New posts may fall back to templates until you add more CSV entries or reset usage (if enabled).', 'tmw-seo-autopilot'); ?></p></div>
            <?php else : ?>
                <div class="notice notice-success"><p><?php echo sprintf(esc_html__('Pool healthy: %d unique title+focus pairs remaining.', 'tmw-seo-autopilot'), (int) $remaining_pairs); ?></p></div>
            <?php endif; ?>

            <h2>CSV Stats</h2>
            <table class="widefat striped" style="max-width:800px;">
                <tbody>
                    <tr>
                        <th scope="row">CSV Path</th>
                        <td><code><?php echo esc_html($csv_path); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">Total valid rows</th>
                        <td><?php echo (int) $valid_rows; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Unique SEO titles</th>
                        <td><?php echo (int) $unique_title_count; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Unique focus keywords</th>
                        <td><?php echo (int) $unique_focus_count; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Unique title+focus pairs</th>
                        <td><?php echo (int) $unique_pair_count; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:25px;">Usage Stats</h2>
            <table class="widefat striped" style="max-width:800px;">
                <tbody>
                    <tr>
                        <th scope="row">Used row keys</th>
                        <td><?php echo (int) $used_keys_count; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Used title hashes</th>
                        <td><?php echo (int) $used_title_count; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Used focus hashes</th>
                        <td><?php echo (int) $used_focus_count; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Used title+focus hashes</th>
                        <td><?php echo (int) $used_pair_count; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:25px;">Remaining Pool</h2>
            <table class="widefat striped" style="max-width:800px;">
                <tbody>
                    <tr>
                        <th scope="row">Remaining unique titles</th>
                        <td><?php echo (int) $remaining_titles; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Remaining unique focus keywords</th>
                        <td><?php echo (int) $remaining_focus; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Remaining unique pairs</th>
                        <td><?php echo (int) $remaining_pairs; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:25px;">Reset Usage Trackers</h2>
            <?php if (!$reset_enabled) : ?>
                <div class="notice notice-info"><p><?php echo esc_html("Reset is disabled by default. To enable, set define('TMWSEO_ENABLE_USAGE_RESET', true) or use the tmwseo_enable_usage_reset filter."); ?></p></div>
            <?php else : ?>
                <div class="notice notice-warning"><p><?php echo esc_html__('Resetting will only clear the usage tracker options. It will not change existing posts. Type RESET to confirm.', 'tmw-seo-autopilot'); ?></p></div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:800px;">
                    <?php wp_nonce_field('tmwseo_usage_reset'); ?>
                    <input type="hidden" name="action" value="tmwseo_usage_reset">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="tmwseo_reset_confirm">Confirmation</label></th>
                            <td>
                                <input type="text" name="tmwseo_reset_confirm" id="tmwseo_reset_confirm" value="" placeholder="Type RESET" class="regular-text" required>
                                <p class="description">Type <code>RESET</code> to clear usage trackers.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary" onclick="return confirm('This will clear usage trackers. Continue?');">Reset usage trackers</button>
                    </p>
                </form>
            <?php endif; ?>
        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Renders the keyword packs admin screen.
     *
     * @return void
     */
    public static function render_keyword_packs() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        $notices = [];
        $categories = Keyword_Library::categories();
        $types      = ['extra', 'longtail', 'competitor'];

        if (!empty($_POST['tmwseo_keyword_action'])) {
            check_admin_referer('tmwseo_keyword_packs');
            if (!current_user_can(self::CAP)) {
                return;
            }

            $action = sanitize_text_field($_POST['tmwseo_keyword_action']);

            if ($action === 'init_placeholders') {
                Keyword_Library::ensure_dirs_and_placeholders();
                Keyword_Library::flush_cache();
                $notices[] = ['type' => 'updated', 'text' => 'Folders and placeholder CSV files initialized.'];
            }

            if ($action === 'flush_cache') {
                Keyword_Library::flush_cache();
                $notices[] = ['type' => 'updated', 'text' => 'Keyword cache cleared.'];
            }

            if ($action === 'upload' && !empty($_FILES['tmwseo_csv']['tmp_name'])) {
                $category = sanitize_key($_POST['tmwseo_category'] ?? '');
                $type     = sanitize_key($_POST['tmwseo_type'] ?? '');

                if (!in_array($category, $categories, true)) {
                    $notices[] = ['type' => 'error', 'text' => 'Invalid category selected.'];
                } elseif (!in_array($type, ['extra', 'longtail', 'competitor'], true)) {
                    $notices[] = ['type' => 'error', 'text' => 'Invalid keyword type selected.'];
                } elseif (!is_uploaded_file($_FILES['tmwseo_csv']['tmp_name'])) {
                    $notices[] = ['type' => 'error', 'text' => 'Upload failed.'];
                } elseif (strtolower(pathinfo($_FILES['tmwseo_csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                    $notices[] = ['type' => 'error', 'text' => 'Please upload a CSV file.'];
                } else {
                    Keyword_Library::ensure_dirs_and_placeholders();
                    $dest_dir  = trailingslashit(Keyword_Library::uploads_base_dir()) . $category;
                    wp_mkdir_p($dest_dir);
                    $dest_path = trailingslashit($dest_dir) . "{$type}.csv";

                    if (move_uploaded_file($_FILES['tmwseo_csv']['tmp_name'], $dest_path)) {
                        Keyword_Library::flush_cache();
                        $count = count(Keyword_Library::load($category, $type));
                        $notices[] = ['type' => 'updated', 'text' => sprintf('Uploaded %s keywords for %s (%d entries).', esc_html($type), esc_html($category), (int) $count)];
                    } else {
                        $notices[] = ['type' => 'error', 'text' => 'Could not move uploaded file.'];
                    }
                }
            }

            if ($action === 'import_planner' && !empty($_FILES['tmwseo_planner_csv']['tmp_name'])) {
                $category = sanitize_key($_POST['tmwseo_planner_category'] ?? '');
                $type     = sanitize_key($_POST['tmwseo_planner_type'] ?? '');

                if (!in_array($category, $categories, true)) {
                    $notices[] = ['type' => 'error', 'text' => 'Invalid category selected.'];
                } elseif (!in_array($type, ['extra', 'longtail', 'competitor'], true)) {
                    $notices[] = ['type' => 'error', 'text' => 'Invalid keyword type selected.'];
                } elseif (!is_uploaded_file($_FILES['tmwseo_planner_csv']['tmp_name'])) {
                    $notices[] = ['type' => 'error', 'text' => 'Upload failed.'];
                } elseif (strtolower(pathinfo($_FILES['tmwseo_planner_csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                    $notices[] = ['type' => 'error', 'text' => 'Please upload a CSV file.'];
                } else {
                    Keyword_Library::ensure_dirs_and_placeholders();
                    $import = Keyword_Pack_Builder::import_keyword_planner_csv($category, $type, $_FILES['tmwseo_planner_csv']['tmp_name']);
                    Keyword_Library::flush_cache();
                    $notices[] = [
                        'type' => 'updated',
                        'text' => sprintf(
                            'Imported %d keywords for %s (%s).',
                            (int) $import['imported'],
                            esc_html($category),
                            esc_html($type)
                        ),
                    ];
                }
            }
        }

        $uploads_base = Keyword_Library::uploads_base_dir();
        $status_rows  = [];
        foreach ($categories as $cat) {
            foreach ($types as $type) {
                $upload_path = trailingslashit($uploads_base) . "{$cat}/{$type}.csv";
                $plugin_path = trailingslashit(Keyword_Library::plugin_base_dir()) . "{$cat}/{$type}.csv";
                $path        = file_exists($upload_path) ? $upload_path : $plugin_path;
                $exists      = file_exists($path);
                $modified    = $exists ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($path)) : '—';
                $count       = $exists ? count(Keyword_Library::load($cat, $type)) : 0;

                $status_rows[] = [
                    'category' => $cat,
                    'type'     => $type,
                    'path'     => $path,
                    'exists'   => $exists,
                    'modified' => $modified,
                    'count'    => $count,
                ];
            }
        }

        $serper_api_key = (string) get_option('tmwseo_serper_api_key', '');
        $serper_gl      = (string) get_option('tmwseo_serper_gl', 'us');
        $serper_hl      = (string) get_option('tmwseo_serper_hl', 'en');
        $provider_value = (string) get_option('tmwseo_keyword_provider', 'serper');
        $allowed_providers = ['serper', 'google_suggest', 'semrush'];
        if (!in_array($provider_value, $allowed_providers, true)) {
            $provider_value = 'serper';
        }
        $build_nonce    = wp_create_nonce('tmwseo_build_keyword_pack');
        $autofill_nonce = wp_create_nonce('tmwseo_autofill_google_keywords');
        $default_per    = 10;

        $status_category = sanitize_key($_GET['tmwseo_status_category'] ?? ($categories[0] ?? 'general'));
        if (!in_array($status_category, $categories, true)) {
            $status_category = $categories[0] ?? 'general';
        }
        $status_type = sanitize_key($_GET['tmwseo_status_type'] ?? 'extra');
        if (!in_array($status_type, $types, true)) {
            $status_type = 'extra';
        }
        $filter_easy_kd = !empty($_GET['tmwseo_kd_under_40']);
        $status_rows_preview = Keyword_Library::load_rows($status_category, $status_type);
        if ($filter_easy_kd) {
            $status_rows_preview = array_values(array_filter($status_rows_preview, function ($row) {
                return isset($row['tmw_kd']) && (int) $row['tmw_kd'] < 40;
            }));
        }

        ?>
        <?php Admin_UI::render_header('tmw-seo-keyword-packs', 'Keyword Packs', 'Build, upload, and manage keyword packs.'); ?>
            <?php foreach ($notices as $notice) : ?>
                <div class="<?php echo esc_attr($notice['type']); ?> notice"><p><?php echo esc_html($notice['text']); ?></p></div>
            <?php endforeach; ?>

            <?php if ($provider_value === 'serper' && $serper_api_key === '') : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php echo esc_html__('Serper is selected but not configured. Configure it on the Integrations page.', 'tmw-seo-autopilot'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tmw-seo-integrations')); ?>"><?php echo esc_html__('Go to Integrations', 'tmw-seo-autopilot'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
            <?php if ($provider_value === 'semrush') : ?>
                <div class="notice notice-info">
                    <p>
                        <?php echo esc_html__('Semrush support is coming soon. Keyword generation will be unavailable until it is enabled.', 'tmw-seo-autopilot'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2 class="title">Keyword Provider</h2>
            <p>Select which provider to use for keyword pack generation. Provider credentials live under Integrations.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:640px;">
                <?php wp_nonce_field('tmwseo_save_settings', 'tmwseo_settings_nonce'); ?>
                <input type="hidden" name="action" value="tmwseo_save_settings">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr(admin_url('admin.php?page=tmw-seo-keyword-packs')); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_keyword_provider">Keyword Provider</label></th>
                        <td>
                            <select name="tmwseo_keyword_provider" id="tmwseo_keyword_provider">
                                <option value="serper" <?php selected($provider_value, 'serper'); ?>>Serper</option>
                                <option value="google_suggest" <?php selected($provider_value, 'google_suggest'); ?>>Google Suggest</option>
                                <option value="semrush" <?php selected($provider_value, 'semrush'); ?>>Semrush (coming soon)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Provider Selection</button>
                </p>
            </form>

            <h2 class="title" style="margin-top:30px;">Build Keyword Packs</h2>
            <p>Use the selected provider to auto-build <code>extra.csv</code> and <code>longtail.csv</code> files inside your uploads folder. Results are cleaned, deduped, and blacklisted phrases are skipped.</p>
            <form id="tmwseo-build-form" style="max-width:760px;">
                <input type="hidden" name="tmwseo_build_nonce" id="tmwseo_build_nonce" value="<?php echo esc_attr($build_nonce); ?>">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_build_provider">Keyword Provider</label></th>
                        <td>
                            <select id="tmwseo_build_provider">
                                <option value="serper" <?php selected($provider_value, 'serper'); ?>>Serper</option>
                                <option value="google_suggest" <?php selected($provider_value, 'google_suggest'); ?>>Google Suggest</option>
                                <option value="semrush" <?php selected($provider_value, 'semrush'); ?>>Semrush (coming soon)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_build_category">Category</label></th>
                        <td>
                            <select id="tmwseo_build_category">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(ucfirst($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_build_seeds">Seeds (one per line)</label></th>
                        <td>
                            <textarea id="tmwseo_build_seeds" rows="5" class="large-text" placeholder="livejasmin tips&#10;best cam sites"></textarea>
                            <p class="description">Each seed triggers a Serper search; suggestions are sanitized and deduped.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_build_per_seed">Suggestions per seed</label></th>
                        <td><input type="number" id="tmwseo_build_per_seed" value="<?php echo (int) $default_per; ?>" min="1" max="50"></td>
                    </tr>
                    <tr>
                        <th scope="row">Optional</th>
                        <td>
                            <label><input type="checkbox" id="tmwseo_build_competitor" value="1"> Also write competitor.csv</label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="button" class="button button-primary" id="tmwseo-build-save">Generate &amp; Save</button>
                    <button type="button" class="button" id="tmwseo-build-preview">Dry Run Preview</button>
                    <span id="tmwseo-build-result" style="margin-left:10px;"></span>
                </p>
            </form>

            <h2 class="title" style="margin-top:30px;">Auto-Fill from Google Autocomplete</h2>
            <p>Fetch Google Autocomplete suggestions for all categories and auto-populate <code>extra.csv</code>, <code>longtail.csv</code>, and <code>competitor.csv</code> inside <code>wp-content/uploads/tmwseo-keywords</code>.</p>
            <div style="max-width:760px;">
                <div id="tmwseo-autofill-notice"></div>
                <input type="hidden" id="tmwseo-autofill-nonce" value="<?php echo esc_attr($autofill_nonce); ?>">
                <label><input type="checkbox" id="tmwseo-autofill-dry-run" value="1"> Preview without saving</label>
                <p class="submit">
                    <button type="button" class="button button-primary" id="tmwseo-autofill-start">Auto-Fill from Google Autocomplete</button>
                </p>
                <div id="tmwseo-autofill-progress" style="display:none; margin-top:10px;">
                    <progress id="tmwseo-autofill-bar" value="0" max="100" style="width:100%;"></progress>
                    <div id="tmwseo-autofill-status" style="margin-top:8px;"></div>
                    <ul id="tmwseo-autofill-log" style="margin-top:8px;"></ul>
                </div>
                <div id="tmwseo-autofill-summary" style="margin-top:10px; font-weight:600;"></div>
            </div>

            <h2 class="title">Status</h2>
            <p><strong>Uploads base:</strong> <?php echo esc_html($uploads_base); ?></p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Path</th>
                        <th>Exists</th>
                        <th>Last Modified</th>
                        <th>Keywords</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($row['category'])); ?></td>
                            <td><?php echo esc_html($row['type']); ?></td>
                            <td><code><?php echo esc_html($row['path']); ?></code></td>
                            <td><?php echo $row['exists'] ? 'Yes' : 'No'; ?></td>
                            <td><?php echo esc_html($row['modified']); ?></td>
                            <td><?php echo (int) $row['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:30px;">Keyword Status</h2>
            <form method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="tmw-seo-keyword-packs">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_status_category">Category</label></th>
                        <td>
                            <select name="tmwseo_status_category" id="tmwseo_status_category">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($status_category, $cat); ?>><?php echo esc_html(ucfirst($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_status_type">Type</label></th>
                        <td>
                            <select name="tmwseo_status_type" id="tmwseo_status_type">
                                <?php foreach ($types as $type) : ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($status_type, $type); ?>><?php echo esc_html($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Filters</th>
                        <td>
                            <label><input type="checkbox" name="tmwseo_kd_under_40" value="1" <?php checked($filter_easy_kd); ?>> Show only KD &lt; 40 (easy wins)</label>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary">Apply</button></p>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Competition</th>
                        <th>CPC</th>
                        <th>TMW KD%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($status_rows_preview)) : ?>
                        <tr><td colspan="4">No keywords found for this selection.</td></tr>
                    <?php else : ?>
                        <?php foreach (array_slice($status_rows_preview, 0, 200) as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['keyword'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['competition'] ?? Keyword_Difficulty_Proxy::DEFAULT_COMPETITION); ?></td>
                                <td><?php echo esc_html(number_format((float) ($row['cpc'] ?? Keyword_Difficulty_Proxy::DEFAULT_CPC), 2, '.', '')); ?></td>
                                <td><?php echo esc_html((int) ($row['tmw_kd'] ?? 0)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="title" style="margin-top:30px;">Upload CSV</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('tmwseo_keyword_packs'); ?>
                <input type="hidden" name="tmwseo_keyword_action" value="upload">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_category">Category</label></th>
                        <td>
                            <select name="tmwseo_category" id="tmwseo_category">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(ucfirst($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_type">Type</label></th>
                        <td>
                            <select name="tmwseo_type" id="tmwseo_type">
                                <option value="extra">extra</option>
                                <option value="longtail">longtail</option>
                                <option value="competitor">competitor</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_csv">CSV File</label></th>
                        <td><input type="file" name="tmwseo_csv" id="tmwseo_csv" accept=".csv" required></td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary">Upload</button></p>
            </form>

            <h2 class="title" style="margin-top:30px;">Import Keyword Planner Data</h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('tmwseo_keyword_packs'); ?>
                <input type="hidden" name="tmwseo_keyword_action" value="import_planner">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_planner_category">Category</label></th>
                        <td>
                            <select name="tmwseo_planner_category" id="tmwseo_planner_category">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html(ucfirst($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_planner_type">Type</label></th>
                        <td>
                            <select name="tmwseo_planner_type" id="tmwseo_planner_type">
                                <option value="extra">extra</option>
                                <option value="longtail">longtail</option>
                                <option value="competitor">competitor</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_planner_csv">Keyword Planner CSV</label></th>
                        <td>
                            <input type="file" name="tmwseo_planner_csv" id="tmwseo_planner_csv" accept=".csv" required>
                            <p class="description">Expected columns include Keyword, Competition, and Top of page bid.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary">Import Keyword Planner Data</button></p>
            </form>

            <h2 class="title" style="margin-top:30px;">Maintenance</h2>
            <form method="post">
                <?php wp_nonce_field('tmwseo_keyword_packs'); ?>
                <input type="hidden" name="tmwseo_keyword_action" value="init_placeholders">
                <p class="submit"><button type="submit" class="button">Create folders / placeholders</button></p>
            </form>
            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field('tmwseo_keyword_packs'); ?>
                <input type="hidden" name="tmwseo_keyword_action" value="flush_cache">
                <p class="submit"><button type="submit" class="button">Flush keyword cache</button></p>
            </form>
            <script>
            (function($){
                function tmwseoRunBuild(dryRun){
                    var seeds = ($('#tmwseo_build_seeds').val() || '').split(/\r?\n/).map(function(s){ return s.trim(); }).filter(function(s){ return s.length; });
                    var $out = $('#tmwseo-build-result');
                    var $btns = $('#tmwseo-build-save, #tmwseo-build-preview');

                    if (!seeds.length) {
                        $out.text('Please enter at least one seed.');
                        return;
                    }

                    $btns.prop('disabled', true);
                    $out.text(dryRun ? 'Previewing…' : 'Building…');

                    var data = {
                        action: 'tmwseo_build_keyword_pack',
                        nonce: $('#tmwseo_build_nonce').val(),
                        category: $('#tmwseo_build_category').val(),
                        seeds: seeds,
                        provider: $('#tmwseo_build_provider').val(),
                        per_seed: $('#tmwseo_build_per_seed').val(),
                        competitor: $('#tmwseo_build_competitor').is(':checked') ? 1 : 0,
                        dry_run: dryRun ? 1 : 0
                    };

                    $.post(ajaxurl, data, function(resp){
                        if (resp && resp.success) {
                            var msg = resp.data && resp.data.message ? resp.data.message : 'Success';

                            if (resp.data) {
                                if (resp.data.counts) {
                                    msg += ' | extra: ' + (resp.data.counts.extra || 0) + ' | longtail: ' + (resp.data.counts.longtail || 0);
                                    if (typeof resp.data.counts.competitor !== 'undefined') {
                                        msg += ' | competitor: ' + resp.data.counts.competitor;
                                    }
                                } else if (resp.data.preview) {
                                    var preview = resp.data.preview;
                                    var extraCount = preview.extra ? preview.extra.length : 0;
                                    var longCount = preview.longtail ? preview.longtail.length : 0;
                                    msg += ' | preview extra: ' + extraCount + ' | preview longtail: ' + longCount;
                                }
                            }

                            $out.text(msg);

                            if (!dryRun) {
                                setTimeout(function(){
                                    location.reload();
                                }, 800);
                            }
                        } else {
                            var message = resp && resp.data && resp.data.message ? resp.data.message : 'Request failed';
                            var detail = resp && resp.data && resp.data.details ? resp.data.details : '';
                            var provider = resp && resp.data && resp.data.provider ? resp.data.provider : '';
                            var query = resp && resp.data && resp.data.query ? resp.data.query : '';
                            var extra = '';
                            if (detail) {
                                extra += ' (' + detail + ')';
                            }
                            if (provider || query) {
                                extra += ' [' + [provider, query].filter(Boolean).join(' | ') + ']';
                            }
                            $out.text('Error: ' + message + extra);
                        }
                    }).fail(function(){
                        $out.text('Error: request failed');
                    }).always(function(){
                        $btns.prop('disabled', false);
                    });
                }

                $('#tmwseo-build-save').on('click', function(e){
                    e.preventDefault();
                    tmwseoRunBuild(false);
                });

                $('#tmwseo-build-preview').on('click', function(e){
                    e.preventDefault();
                    tmwseoRunBuild(true);
                });

                // Google Autocomplete auto-fill (cursor-based progress).
                var tmwseoAutofillCategories = <?php echo wp_json_encode($categories); ?>;

                function tmwseoRenderAutofillStatus(message) {
                    $('#tmwseo-autofill-status').text(message);
                }

                function tmwseoSetAutofillNotice(message, type) {
                    var $notice = $('#tmwseo-autofill-notice');
                    if (!message) {
                        $notice.empty();
                        return;
                    }
                    var noticeClass = type === 'error' ? 'notice notice-error' : 'notice notice-success';
                    $notice.html('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
                }

                function tmwseoAppendAutofillLog(message, isError) {
                    var $log = $('#tmwseo-autofill-log');
                    var $item = $('<li/>').text(message);
                    if (isError) {
                        $item.css('color', '#b32d2e');
                    }
                    $log.append($item);
                }

                function tmwseoFormatAutofillError(error) {
                    if (!error) {
                        return 'Unknown error';
                    }
                    if (typeof error === 'string') {
                        return error;
                    }
                    var message = error.message || 'Error';
                    var prefix = error.query ? (error.query + ': ') : '';
                    var details = [];
                    if (error.http_code) {
                        details.push('HTTP ' + error.http_code);
                    }
                    if (error.url) {
                        details.push(error.url);
                    }
                    if (error.snippet) {
                        details.push('Snippet: ' + error.snippet);
                    }
                    return prefix + message + (details.length ? ' (' + details.join(' | ') + ')' : '');
                }

                function tmwseoRunAutofillBatch(cursor, dryRun, state) {
                    var data = {
                        action: 'tmwseo_autofill_google_keywords',
                        nonce: $('#tmwseo-autofill-nonce').val(),
                        category_index: cursor.categoryIndex || 0,
                        seed_offset: cursor.seedOffset || 0,
                        dry_run: dryRun ? 1 : 0
                    };

                    tmwseoRenderAutofillStatus('Processing category ' + (cursor.categoryIndex + 1) + ' of ' + state.totalCategories + '...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: data
                    }).done(function(resp){
                        if (resp && resp.success) {
                            var payload = resp.data || {};
                            var categories = payload.categories || [];
                            categories.forEach(function(entry){
                                var name = entry.category ? entry.category : 'category';
                                var found = entry.found || 0;
                                var seedOffset = entry.seed_offset || 0;
                                var seedTotal = entry.seed_total || 0;
                                var seedStatus = seedTotal ? (' (seed ' + seedOffset + '/' + seedTotal + ')') : '';
                                tmwseoAppendAutofillLog('Processing ' + name + '... ' + found + ' keywords found' + seedStatus);
                            });

                            if (payload.errors && payload.errors.length) {
                                tmwseoSetAutofillNotice('Some categories returned errors. See log for details.', 'error');
                                payload.errors.forEach(function(error){
                                    tmwseoAppendAutofillLog('Error: ' + tmwseoFormatAutofillError(error), true);
                                });
                            } else {
                                tmwseoSetAutofillNotice('', '');
                            }

                            state.completed = payload.completed_categories || state.completed;
                            state.totalKeywords += payload.batch_keywords || 0;
                            var percent = state.totalCategories > 0 ? Math.round((state.completed / state.totalCategories) * 100) : 100;
                            $('#tmwseo-autofill-bar').val(percent);

                            if (payload.done) {
                                tmwseoRenderAutofillStatus('Completed.');
                                $('#tmwseo-autofill-summary').text('Completed! ' + state.totalCategories + ' categories, ' + state.totalKeywords + ' total keywords' + (dryRun ? ' (preview)' : '') + '.');
                                $('#tmwseo-autofill-start').prop('disabled', false);
                                if (!dryRun) {
                                    setTimeout(function(){
                                        location.reload();
                                    }, 1200);
                                }
                                return;
                            }

                            var nextCursor = payload.cursor || {};
                            tmwseoRunAutofillBatch({
                                categoryIndex: nextCursor.category_index || 0,
                                seedOffset: nextCursor.seed_offset || 0
                            }, dryRun, state);
                        } else {
                            var message = resp && resp.data && resp.data.message ? resp.data.message : 'Request failed';
                            var detail = resp && resp.data && resp.data.details ? resp.data.details : '';
                            var status = resp && resp.data && resp.data.http_status ? ('HTTP ' + resp.data.http_status + ' ') : '';
                            var extra = detail ? ' (' + detail + ')' : '';
                            tmwseoAppendAutofillLog('Error: ' + status + message + extra, true);
                            tmwseoSetAutofillNotice('Auto-fill failed: ' + status + message + extra, 'error');
                            tmwseoRenderAutofillStatus('Stopped due to error.');
                            $('#tmwseo-autofill-start').prop('disabled', false);
                        }
                    }).fail(function(jqXHR){
                        var statusText = jqXHR && jqXHR.status ? ('HTTP ' + jqXHR.status + ' ') : '';
                        var message = 'request failed';
                        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                            message = jqXHR.responseJSON.data.message;
                        } else if (jqXHR && jqXHR.responseText && jqXHR.responseText.trim().charAt(0) === '<') {
                            message = 'Server error (likely fatal). Check debug.log.';
                        }
                        tmwseoAppendAutofillLog('Error: ' + statusText + message, true);
                        tmwseoSetAutofillNotice('Auto-fill failed: ' + statusText + message, 'error');
                        tmwseoRenderAutofillStatus('Stopped due to error.');
                        $('#tmwseo-autofill-start').prop('disabled', false);
                    });
                }

                $('#tmwseo-autofill-start').on('click', function(e){
                    e.preventDefault();
                    var $btn = $(this);
                    var dryRun = $('#tmwseo-autofill-dry-run').is(':checked');
                    if (!tmwseoAutofillCategories.length) {
                        tmwseoRenderAutofillStatus('No categories available.');
                        return;
                    }

                    $btn.prop('disabled', true);
                    $('#tmwseo-autofill-log').empty();
                    $('#tmwseo-autofill-summary').text('');
                    tmwseoSetAutofillNotice('', '');
                    $('#tmwseo-autofill-progress').show();
                    $('#tmwseo-autofill-bar').val(0);

                    var state = {
                        totalCategories: tmwseoAutofillCategories.length,
                        completed: 0,
                        totalKeywords: 0
                    };

                    tmwseoRunAutofillBatch({categoryIndex: 0, seedOffset: 0}, dryRun, state);
                });
            })(jQuery);
            </script>
        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Renders the keyword usage admin screen.
     *
     * @return void
     */
    public static function render_keyword_usage() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        global $wpdb;
        $usage_table = $wpdb->prefix . 'tmwseo_keyword_usage';
        $log_table   = $wpdb->prefix . 'tmwseo_keyword_usage_log';

        $categories = array_merge(['all'], Keyword_Library::categories());
        $types      = ['all', 'extra', 'longtail', 'competitor'];

        $selected_category = sanitize_key($_GET['category'] ?? 'all');
        $selected_type     = sanitize_key($_GET['type'] ?? 'all');
        $limit             = max(10, min(500, intval($_GET['limit'] ?? 100)));

        $where = [];
        $params = [];
        if ($selected_category !== 'all') {
            $where[] = 'category = %s';
            $params[] = $selected_category;
        }
        if ($selected_type !== 'all') {
            $where[] = 'type = %s';
            $params[] = $selected_type;
        }
        $where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        if (!empty($_GET['tmwseo_export_usage']) && check_admin_referer('tmwseo_usage_export', 'tmwseo_usage_nonce')) {
            $sql = "SELECT keyword_text, type, category, post_id, post_type, used_at FROM {$log_table} {$where_sql} ORDER BY used_at DESC";
            $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="tmwseo-keyword-usage.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['keyword_text', 'type', 'category', 'post_id', 'post_type', 'used_at']);
            foreach ((array) $rows as $row) {
                fputcsv($output, [
                    $row['keyword_text'] ?? '',
                    $row['type'] ?? '',
                    $row['category'] ?? '',
                    $row['post_id'] ?? '',
                    $row['post_type'] ?? '',
                    $row['used_at'] ?? '',
                ]);
            }
            fclose($output);
            exit;
        }

        $sql = "SELECT keyword_text, category, type, used_count, last_used_at FROM {$usage_table} {$where_sql} ORDER BY used_count DESC, last_used_at DESC LIMIT %d";
        $params_with_limit = $params;
        $params_with_limit[] = $limit;
        $rows = $params_with_limit ? $wpdb->get_results($wpdb->prepare($sql, $params_with_limit), ARRAY_A) : $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);

        $export_url = add_query_arg([
            'page' => 'tmw-seo-keyword-usage',
            'category' => $selected_category,
            'type' => $selected_type,
            'tmwseo_export_usage' => 1,
        ], admin_url('admin.php'));

        ?>
        <?php Admin_UI::render_header('tmw-seo-keyword-usage', 'Keyword Usage', 'Track keyword usage counts across packs.'); ?>
            <form method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="tmw-seo-keyword-usage">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmwseo_usage_category">Category</label></th>
                        <td>
                            <select name="category" id="tmwseo_usage_category">
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($selected_category, $cat); ?>><?php echo esc_html(ucfirst($cat)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_usage_type">Type</label></th>
                        <td>
                            <select name="type" id="tmwseo_usage_type">
                                <?php foreach ($types as $t) : ?>
                                    <option value="<?php echo esc_attr($t); ?>" <?php selected($selected_type, $t); ?>><?php echo esc_html($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmwseo_usage_limit">Limit</label></th>
                        <td>
                            <input type="number" name="limit" id="tmwseo_usage_limit" value="<?php echo (int) $limit; ?>" min="10" max="500">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Filter</button>
                    <?php wp_nonce_field('tmwseo_usage_export', 'tmwseo_usage_nonce'); ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url($export_url, 'tmwseo_usage_export', 'tmwseo_usage_nonce')); ?>">Export CSV</a>
                </p>
            </form>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Used Count</th>
                        <th>Last Used</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="5">No keyword usage found.</td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['keyword_text'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['category'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['type'] ?? ''); ?></td>
                                <td><?php echo (int) ($row['used_count'] ?? 0); ?></td>
                                <td><?php echo esc_html($row['last_used_at'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Renders the main tools dashboard.
     *
     * @return void
     */
    public static function render_tools() {
        if (!current_user_can(self::CAP)) {
            return;
        }

        if (!empty($_POST['tmw_seo_run'])) {
            check_admin_referer('tmw_seo_tools');
            $limit = max(1, (int) $_POST['limit']);
            $q     = new \WP_Query([
                'post_type'      => Core::MODEL_PT,
                'posts_per_page' => $limit,
                'post_status'    => 'publish',
            ]);
            $done  = 0;
            while ($q->have_posts()) {
                $q->the_post();
                $r = Core::generate_and_write(get_the_ID(), ['strategy' => 'template', 'insert_content' => true]);
                if (!empty($r['ok'])) {
                    $done++;
                }
            }
            wp_reset_postdata();
            echo '<div class="updated"><p>Generated for ' . (int) $done . ' models.</p></div>';
        }

        $total_models = (int) (wp_count_posts(Core::MODEL_PT)->publish ?? 0);
        $total_videos = 0;
        foreach (Core::video_post_types() as $pt) {
            $total_videos += (int) (wp_count_posts($pt)->publish ?? 0);
        }

        $models_with_content = get_posts([
            'post_type'      => Core::MODEL_PT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'rank_math_focus_keyword',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $models_with_content_count   = count($models_with_content);
        $models_with_content_percent = $total_models > 0 ? round(($models_with_content_count / $total_models) * 100) : 0;

        $videos_with_content = 0;
        foreach (Core::video_post_types() as $pt) {
            $videos_with_content += count(get_posts([
                'post_type'      => $pt,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => 'rank_math_focus_keyword',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]));
        }
        $videos_with_content_percent = $total_videos > 0 ? round(($videos_with_content / $total_videos) * 100) : 0;

        $sample_models = get_posts([
            'post_type'      => Core::MODEL_PT,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'rand',
            'meta_query'     => [
                [
                    'key'     => 'rank_math_focus_keyword',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $word_counts       = [];
        $onlyfans_counts   = [];
        $livejasmin_counts = [];

        foreach ($sample_models as $model) {
            $content     = get_post_field('post_content', $model->ID);
            $word_count  = str_word_count(wp_strip_all_tags((string) $content));
            $word_counts[] = $word_count;

            $content_lower     = strtolower((string) $content);
            $onlyfans_counts[] = substr_count($content_lower, 'onlyfans');
            $livejasmin_counts[] = substr_count($content_lower, 'livejasmin');
        }

        $avg_words      = !empty($word_counts) ? round(array_sum($word_counts) / count($word_counts)) : 0;
        $avg_onlyfans   = !empty($onlyfans_counts) ? round(array_sum($onlyfans_counts) / count($onlyfans_counts), 1) : 0;
        $avg_livejasmin = !empty($livejasmin_counts) ? round(array_sum($livejasmin_counts) / count($livejasmin_counts), 1) : 0;

        $models_below_600 = 0;
        foreach ($word_counts as $wc) {
            if ($wc < 600) {
                $models_below_600++;
            }
        }
        $models_below_600_percent = !empty($word_counts) ? round(($models_below_600 / count($word_counts)) * 100) : 0;
        $models_missing_content = max(0, $total_models - $models_with_content_count);
        $videos_missing_content = max(0, $total_videos - $videos_with_content);
        $density_ok = ($avg_livejasmin >= 4 && $avg_livejasmin <= 6) && ($avg_onlyfans >= 3 && $avg_onlyfans <= 5);
        ?>

        <?php Admin_UI::render_header('tmw-seo-autopilot', 'Dashboard', 'Monitor coverage, quality, and automation health at a glance.'); ?>

            <div class="tmwseo-card-grid">
                <div class="tmwseo-card">
                    <h3>Content Coverage</h3>
                    <div class="tmwseo-kpi-value"><?php echo (int) $models_with_content_percent; ?>%</div>
                    <p><?php echo number_format_i18n($models_with_content_count); ?> of <?php echo number_format_i18n($total_models); ?> models have SEO content.</p>
                    <p><?php echo (int) $videos_with_content_percent; ?>% of videos have SEO content.</p>
                    <div class="tmwseo-progress"><span style="width:<?php echo (int) $models_with_content_percent; ?>%;"></span></div>
                </div>

                <div class="tmwseo-card">
                    <h3>Average Word Count</h3>
                    <div class="tmwseo-kpi-value"><?php echo number_format_i18n($avg_words); ?></div>
                    <p>Target: 600+ words per model.</p>
                    <?php if ($models_below_600_percent > 0) : ?>
                        <p class="tmwseo-alert"><?php echo (int) $models_below_600_percent; ?>% of sampled models are below 600 words.</p>
                    <?php endif; ?>
                </div>

                <div class="tmwseo-card">
                    <h3>Keyword Density</h3>
                    <p>LiveJasmin avg: <strong><?php echo esc_html($avg_livejasmin); ?>x</strong></p>
                    <p>OnlyFans avg: <strong><?php echo esc_html($avg_onlyfans); ?>x</strong></p>
                    <p class="<?php echo $density_ok ? 'tmwseo-text-good' : 'tmwseo-text-bad'; ?>">
                        <?php echo $density_ok ? esc_html__('Within target ranges.', 'tmw-seo-autopilot') : esc_html__('Warnings: density outside target range.', 'tmw-seo-autopilot'); ?>
                    </p>
                </div>

                <div class="tmwseo-card">
                    <h3>Automation Queue</h3>
                    <div class="tmwseo-kpi-value"><?php echo (int) $models_missing_content; ?></div>
                    <p><?php echo (int) $videos_missing_content; ?> videos missing SEO content.</p>
                    <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=tmw-seo-scheduled-actions')); ?>">View automations</a>
                </div>
            </div>

            <div class="tmwseo-card">
                <h3>Quick Actions</h3>
                <p>Jump into the most common workflows.</p>
                <div class="tmwseo-quick-actions">
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=tmw-seo-keyword-packs')); ?>">Build Keyword Packs</a>
                    <a class="button" href="#tmw-bulk-generator">Run Bulk Generation</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=tmw-seo-integrations')); ?>">Open Integrations</a>
                </div>
            </div>

            <div class="tmwseo-card">
                <h3>Recent Activity (Last 7 Days)</h3>
                <?php
                $recent_models = get_posts([
                    'post_type'      => Core::MODEL_PT,
                    'post_status'    => 'publish',
                    'posts_per_page' => 10,
                    'date_query'     => [
                        [
                            'after' => '7 days ago',
                        ],
                    ],
                    'meta_query'     => [
                        [
                            'key'     => 'rank_math_focus_keyword',
                            'compare' => 'EXISTS',
                        ],
                    ],
                ]);
                if (empty($recent_models)) :
                    ?>
                    <p>No models generated in the last 7 days.</p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Model</th>
                                <th>Date</th>
                                <th>Words</th>
                                <th>OnlyFans</th>
                                <th>LiveJasmin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_models as $model) :
                                $content = get_post_field('post_content', $model->ID);
                                $wc      = str_word_count(wp_strip_all_tags((string) $content));
                                $of      = substr_count(strtolower((string) $content), 'onlyfans');
                                $lj      = substr_count(strtolower((string) $content), 'livejasmin');
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($model->ID)); ?>">
                                            <?php echo esc_html($model->post_title); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(get_the_date('M j, Y', $model)); ?></td>
                                    <td><?php echo (int) $wc; ?></td>
                                    <td><?php echo (int) $of; ?>x</td>
                                    <td><?php echo (int) $lj; ?>x</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <hr>
            <div class="tmwseo-card" id="tmw-bulk-generator">
                <h3>Bulk Content Generator</h3>
                <form id="tmw-bulk-form">
                    <?php wp_nonce_field('tmw_bulk_generate', 'tmw_bulk_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th>Select Models</th>
                            <td>
                                <label>
                                    <input type="radio" name="selection" value="no_content" checked>
                                    All models without content
                                </label><br>
                                <label>
                                    <input type="radio" name="selection" value="all">
                                    All models (overwrite existing)
                                </label><br>
                                <label>
                                    <input type="radio" name="selection" value="range">
                                    ID Range:
                                    <input type="number" name="range_start" value="1" min="1" style="width:80px">
                                    to
                                    <input type="number" name="range_end" value="100" min="1" style="width:80px">
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Strategy</th>
                            <td>
                                <label>
                                    <input type="radio" name="strategy" value="template" checked>
                                    Standard Template (safe defaults)
                                </label><br>
                                <?php if (\TMW_SEO\Providers\OpenAI::is_enabled()) : ?>
                                    <label>
                                        <input type="radio" name="strategy" value="openai">
                                        OpenAI (when available)
                                    </label>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Options</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="check_uniqueness" checked>
                                    Check content uniqueness (slower but safer)
                                </label><br>
                                <label>
                                    <input type="checkbox" name="generate_schema" checked>
                                    Generate schema markup
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" id="tmw-bulk-start" class="button button-primary">
                            Start Bulk Generation
                        </button>
                        <button type="button" id="tmw-bulk-pause" class="button" style="display:none;">
                            Pause
                        </button>
                        <button type="button" id="tmw-bulk-cancel" class="button" style="display:none;">
                            Cancel
                        </button>
                    </p>
                </form>

                <div id="tmw-bulk-progress" style="display:none; margin-top:20px;">
                    <h3>Processing...</h3>
                    <progress id="tmw-progress-bar" max="100" value="0" style="width:100%; height:30px;"></progress>
                    <p id="tmw-progress-text">0 / 0 models processed</p>
                    <p id="tmw-progress-stats">Success: 0 | Failed: 0 | Skipped: 0</p>
                    <div id="tmw-progress-log" style="max-height:200px; overflow-y:auto; border:1px solid #ccc; padding:10px; margin-top:10px; font-family:monospace; font-size:12px; background:#f9f9f9;">
                    </div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                let processing = false;
                let paused = false;
                let cancelled = false;
                let totalModels = 0;
                let stats = {success: 0, failed: 0, skipped: 0};

                $('#tmw-bulk-start').on('click', function() {
                    if (processing) {
                        return;
                    }

                    processing = true;
                    paused = false;
                    cancelled = false;
                    stats = {success: 0, failed: 0, skipped: 0};

                    $('#tmw-bulk-start').prop('disabled', true);
                    $('#tmw-bulk-pause, #tmw-bulk-cancel').show();
                    $('#tmw-bulk-progress').show();
                    $('#tmw-progress-log').html('');

                    $.post(ajaxurl, {
                        action: 'tmw_get_bulk_models',
                        nonce: $('#tmw_bulk_nonce').val(),
                        selection: $('input[name="selection"]:checked').val(),
                        range_start: $('input[name="range_start"]').val(),
                        range_end: $('input[name="range_end"]').val()
                    }, function(response) {
                        if (response.success) {
                            totalModels = response.data.models.length;
                            processBatch(response.data.models, 0);
                        } else {
                            alert('Failed to get models: ' + response.data.message);
                            resetUI();
                        }
                    });
                });

                $('#tmw-bulk-pause').on('click', function() {
                    paused = !paused;
                    $(this).text(paused ? 'Resume' : 'Pause');
                });

                $('#tmw-bulk-cancel').on('click', function() {
                    if (confirm('Are you sure you want to cancel? Progress will be saved.')) {
                        cancelled = true;
                        resetUI();
                    }
                });

                function processBatch(allModels, startIndex) {
                    if (cancelled) {
                        log('❌ Cancelled by user');
                        return;
                    }

                    if (paused) {
                        setTimeout(function() {
                            processBatch(allModels, startIndex);
                        }, 500);
                        return;
                    }

                    if (startIndex >= allModels.length) {
                        log('✅ Complete! Success: ' + stats.success + ', Failed: ' + stats.failed + ', Skipped: ' + stats.skipped);
                        resetUI();
                        return;
                    }

                    let batch = allModels.slice(startIndex, startIndex + 50);

                    $.post(ajaxurl, {
                        action: 'tmw_bulk_process_batch',
                        nonce: $('#tmw_bulk_nonce').val(),
                        batch: batch,
                        strategy: $('input[name="strategy"]:checked').val(),
                        check_uniqueness: $('input[name="check_uniqueness"]').is(':checked'),
                        generate_schema: $('input[name="generate_schema"]').is(':checked')
                    }, function(response) {
                        if (response.success) {
                            stats.success += response.data.success;
                            stats.failed += response.data.failed;
                            stats.skipped += response.data.skipped;

                            if (response.data.messages) {
                                response.data.messages.forEach(function(msg) {
                                    log(msg);
                                });
                            }

                            updateProgress(startIndex + batch.length, allModels.length);
                            processBatch(allModels, startIndex + 50);
                        } else {
                            log('❌ Batch failed: ' + response.data.message);
                            processBatch(allModels, startIndex + 50);
                        }
                    }).fail(function() {
                        log('❌ Network error, retrying batch...');
                        setTimeout(function() {
                            processBatch(allModels, startIndex);
                        }, 2000);
                    });
                }

                function updateProgress(current, total) {
                    let percent = Math.round((current / total) * 100);
                    $('#tmw-progress-bar').val(percent);
                    $('#tmw-progress-text').text(current + ' / ' + total + ' models processed');
                    $('#tmw-progress-stats').text('Success: ' + stats.success + ' | Failed: ' + stats.failed + ' | Skipped: ' + stats.skipped);
                }

                function log(message) {
                    let timestamp = new Date().toLocaleTimeString();
                    $('#tmw-progress-log').append('[' + timestamp + '] ' + message + '<br>');
                    $('#tmw-progress-log').scrollTop($('#tmw-progress-log')[0].scrollHeight);
                }

                function resetUI() {
                    processing = false;
                    $('#tmw-bulk-start').prop('disabled', false).text('Start Bulk Generation');
                    $('#tmw-bulk-pause, #tmw-bulk-cancel').hide();
                }
            });
            </script>

            <div class="tmwseo-card">
                <h3>Quick Backfill</h3>
                <form method="post">
                    <?php wp_nonce_field('tmw_seo_tools'); ?>
                    <p>Run a quick backfill using the Template provider.</p>
                    <p><label>Limit <input type="number" name="limit" value="25" min="1" max="500"></label></p>
                    <p><button class="button button-primary" name="tmw_seo_run" value="1">Run Now</button></p>
                    <p>Optional OpenAI provider: define <code>OPENAI_API_KEY</code> in wp-config.php or set constant <code>TMW_SEO_OPENAI</code> with your key to enable.</p>
                </form>
            </div>

            <div class="tmwseo-card">
                <h3>Integration Settings (read-only)</h3>
                <?php
                echo '<table class="widefat"><tbody>';
                echo '<tr><th>Brand order</th><td>' . esc_html(implode(' → ', \TMW_SEO\Core::brand_order())) . '</td></tr>';
                echo '<tr><th>SUBAFF pattern</th><td>' . esc_html(\TMW_SEO\Core::subaff_pattern()) . '</td></tr>';
                $og = \TMW_SEO\Core::default_og();
                echo '<tr><th>Default OG image</th><td>' . ($og ? '<code>' . esc_url($og) . '</code>' : '<em>none</em>') . '</td></tr>';
                echo '</tbody></table>';
                ?>
            </div>

            <div class="tmwseo-card">
                <h3>Video Post Types</h3>
                <?php
                $pts = \TMW_SEO\Core::video_post_types();
                $all = get_post_types(['public' => true], 'objects');
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('tmwseo_save_settings', 'tmwseo_settings_nonce');
                echo '<input type="hidden" name="action" value="tmwseo_save_settings" />';
                echo '<table class="widefat"><thead><tr><th>Use</th><th>Slug</th><th>Label</th></tr></thead><tbody>';
                foreach ($all as $slug => $obj) {
                    $label   = $obj->labels->name . ' (' . $obj->labels->singular_name . ')';
                    $checked = in_array($slug, $pts, true) ? 'checked' : '';
                    echo '<tr><td><input type="checkbox" name="tmwseo_video_pts[]" value="' . esc_attr($slug) . '" ' . $checked . '></td><td><code>' . esc_html($slug) . '</code></td><td>' . esc_html($label) . '</td></tr>';
                }
                echo '</tbody></table><p><button class="button button-primary">Save Video Post Types</button></p></form>';
                ?>
            </div>

        <?php Admin_UI::render_footer(); ?>
        <?php
    }

    /**
     * Handles the `add_meta_boxes` hook for videos.
     *
     * @return void
     */
    public static function add_video_metabox() {
        foreach (\TMW_SEO\Core::video_post_types() as $pt) {
            add_meta_box('tmwseo_box', 'TMW SEO Autopilot', [__CLASS__, 'render_video_box'], $pt, 'side', 'high');
        }
    }

    /**
     * Renders the video meta box.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public static function render_video_box($post) {
        wp_nonce_field('tmwseo_box', 'tmwseo_box_nonce');
        $override = get_post_meta($post->ID, 'tmwseo_model_name', true);
        $last = get_post_meta($post->ID, '_tmwseo_last_message', true);
        echo '<p><label><strong>Model Name (override)</strong></label>';
        echo '<input type="text" class="widefat" name="tmwseo_model_name" value="' . esc_attr($override) . '" placeholder="e.g., Abby Murray"></p>';
        $url = wp_nonce_url(admin_url('admin-post.php?action=tmwseo_generate_now&post_id=' . $post->ID), 'tmwseo_generate_now_' . $post->ID);
        echo '<p><a href="' . esc_url($url) . '" class="button button-primary" style="width:100%;">Generate Now</a></p>';
        if ($last) echo '<p><em>Last run:</em> ' . esc_html($last) . '</p>';
    }

    /**
     * Handles the `save_post` hook for video meta box data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post Post object.
     * @return void
     */
    public static function save_video_metabox($post_id, $post) {
        if (!isset($_POST['tmwseo_box_nonce']) || !wp_verify_nonce($_POST['tmwseo_box_nonce'], 'tmwseo_box')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $val = isset($_POST['tmwseo_model_name']) ? sanitize_text_field(wp_unslash($_POST['tmwseo_model_name'])) : '';
        if ($val !== '') update_post_meta($post_id, 'tmwseo_model_name', $val); else delete_post_meta($post_id, 'tmwseo_model_name');
    }

    /**
     * Handles the `admin_post_tmwseo_generate_now` hook.
     *
     * @return void
     */
    public static function handle_generate_now() {
        $post_id = (int)($_GET['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_die('No permission');
        check_admin_referer('tmwseo_generate_now_' . $post_id);

        $post = get_post($post_id);
        if (!$post) {
            wp_die('Post not found');
        }

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            \TMW_SEO\Core::debug_log(
                sprintf(
                    '[TMW-SEO-ADMIN] Manual video generate_now for post #%d',
                    $post_id
                )
            );
        }

        if (\TMW_SEO\Core::is_video_post_type($post->post_type)) {
            $res = \TMW_SEO\Core::generate_for_video(
                $post_id,
                [
                    'strategy' => 'template',
                    'force'    => true,
                    'respect_manual' => true,
                    'preserve_title' => true,
                    'preserve_focus' => true,
                    'update_slug_from_manual_title' => true,
                ]
            );
        } else {
            $res = ['ok' => false, 'message' => 'Not a video post type'];
        }
        update_post_meta($post_id, '_tmwseo_last_message', $res['ok'] ? 'Generated via Manual Run' : 'Failed: ' . $res['message']);
        wp_safe_redirect(get_edit_post_link($post_id, ''));
        exit;
    }

    /**
     * Handles the `admin_post_tmwseo_save_settings` hook.
     *
     * @return void
     */
    public static function handle_save_settings() {
        if (!current_user_can(self::CAP)) wp_die('No permission');
        if (!isset($_POST['tmwseo_settings_nonce']) || !wp_verify_nonce($_POST['tmwseo_settings_nonce'], 'tmwseo_save_settings')) wp_die('Bad nonce');
        $redirect = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : admin_url('admin.php?page=tmw-seo-autopilot&saved=1');
        $pts = isset($_POST['tmwseo_video_pts']) ? (array) $_POST['tmwseo_video_pts'] : [];
        $pts = array_values(array_unique(array_map('sanitize_key', $pts)));
        update_option('tmwseo_video_pts', $pts, false);
        $provider   = isset($_POST['tmwseo_keyword_provider']) ? sanitize_text_field((string) $_POST['tmwseo_keyword_provider']) : 'serper';
        $allowed_providers = ['serper', 'google_suggest', 'semrush'];
        if (!in_array($provider, $allowed_providers, true)) {
            $provider = 'serper';
        }
        update_option('tmwseo_keyword_provider', $provider, false);

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handles the `admin_post_tmwseo_save_integrations` hook.
     *
     * @return void
     */
    public static function handle_save_integrations() {
        if (!current_user_can(self::CAP)) {
            wp_die('No permission');
        }
        check_admin_referer('tmwseo_integrations', 'tmwseo_integrations_nonce');

        $provider = sanitize_key($_POST['provider'] ?? '');
        $redirect = admin_url('admin.php?page=tmw-seo-integrations');
        $disconnect = !empty($_POST['tmwseo_disconnect']);

        if ($provider === 'serper') {
            if ($disconnect) {
                delete_option('tmwseo_serper_api_key');
            } else {
                $api_key = self::sanitize_secret($_POST['tmwseo_serper_api_key'] ?? '');
                if ($api_key !== '') {
                    self::update_option_noautoload('tmwseo_serper_api_key', $api_key);
                }
            }
            $serper_gl = sanitize_text_field((string) ($_POST['tmwseo_serper_gl'] ?? get_option('tmwseo_serper_gl', 'us')));
            $serper_hl = sanitize_text_field((string) ($_POST['tmwseo_serper_hl'] ?? get_option('tmwseo_serper_hl', 'en')));
            update_option('tmwseo_serper_gl', $serper_gl, false);
            update_option('tmwseo_serper_hl', $serper_hl, false);
        }

        if ($provider === 'openai') {
            if ($disconnect) {
                delete_option('tmwseo_openai_api_key');
            } else {
                $api_key = self::sanitize_secret($_POST['tmwseo_openai_api_key'] ?? '');
                if ($api_key !== '') {
                    self::update_option_noautoload('tmwseo_openai_api_key', $api_key);
                }
            }
        }

        if ($provider === 'semrush') {
            if ($disconnect) {
                delete_option('tmwseo_semrush_api_key');
            } else {
                $api_key = self::sanitize_secret($_POST['tmwseo_semrush_api_key'] ?? '');
                if ($api_key !== '') {
                    self::update_option_noautoload('tmwseo_semrush_api_key', $api_key);
                }
            }
        }

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Masks a secret value for display.
     *
     * @param string $value Secret value.
     * @return string
     */
    protected static function mask_secret(string $value): string {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $last = substr($trimmed, -4);
        $mask_len = max(8, strlen($trimmed) - 4);
        return str_repeat('•', $mask_len) . ' (last 4: ' . $last . ')';
    }

    /**
     * Sanitizes secret inputs without stripping printable characters.
     *
     * @param string $value Raw input.
     * @return string
     */
    protected static function sanitize_secret(string $value): string {
        $value = trim((string) wp_unslash($value));
        return preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    /**
     * Updates an option with autoload disabled.
     *
     * @param string $key Option key.
     * @param string $value Option value.
     * @return void
     */
    protected static function update_option_noautoload(string $key, string $value): void {
        if (get_option($key, null) === null) {
            add_option($key, $value, '', 'no');
            return;
        }
        update_option($key, $value, false);
    }

    /**
     * Handles the `admin_notices` hook.
     *
     * @return void
     */
    public static function admin_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') return;
        if (!in_array($screen->post_type ?? '', \TMW_SEO\Core::video_post_types(), true)) return;
        $post_id = get_the_ID();
        if (!$post_id) return;
        $msg = get_post_meta($post_id, '_tmwseo_last_message', true);
        if (!$msg) return;
        echo '<div class="notice notice-info is-dismissible"><p><strong>TMW SEO:</strong> ' . esc_html($msg) . '</p></div>';
    }
}
