<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Admin {
    const TAG = '[TMW-SEO-UI]';
    public static function boot() {
        add_action('add_meta_boxes', [__CLASS__, 'meta_box']);
        add_action('add_meta_boxes', [__CLASS__, 'add_video_metabox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_tmw_seo_generate', [__CLASS__, 'ajax_generate']);
        add_action('wp_ajax_tmw_seo_rollback', [__CLASS__, 'ajax_rollback']);
        add_action('wp_ajax_tmw_get_bulk_models', [__CLASS__, 'ajax_get_bulk_models']);
        add_action('wp_ajax_tmw_bulk_process_batch', [__CLASS__, 'ajax_bulk_process_batch']);
        add_filter('bulk_actions-edit-model', [__CLASS__, 'bulk_action']);
        add_filter('handle_bulk_actions-edit-model', [__CLASS__, 'handle_bulk'], 10, 3);
        add_action('admin_menu', [__CLASS__, 'tools_page']);
        add_action('save_post', [__CLASS__, 'save_video_metabox'], 10, 2);
        add_action('admin_post_tmwseo_generate_now', [__CLASS__, 'handle_generate_now']);
        add_action('admin_post_tmwseo_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_notices', [__CLASS__, 'admin_notice']);
    }

    public static function assets($hook) {
        if (strpos($hook, 'tmw-seo-autopilot') !== false) {
            wp_enqueue_style('tmw-seo-admin', TMW_SEO_URL . 'assets/admin.css', [], '0.8.0');
        }
    }

    public static function meta_box() {
        add_meta_box('tmw-seo-box', 'TMW SEO Autopilot', [__CLASS__, 'render_box'], 'model', 'side', 'high');
    }

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

    public static function ajax_get_bulk_models() {
        check_ajax_referer('tmw_bulk_generate', 'nonce');

        if (!current_user_can('manage_options')) {
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

    public static function ajax_bulk_process_batch() {
        check_ajax_referer('tmw_bulk_generate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No permission']);
        }

        $batch            = array_map('intval', $_POST['batch'] ?? []);
        $strategy         = sanitize_text_field($_POST['strategy'] ?? 'template');
        $check_uniqueness = !empty($_POST['check_uniqueness']);
        $generate_schema  = !empty($_POST['generate_schema']);

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

            if ($strategy === 'hijacking') {
                $actual_strategy = ($post_id <= 1950) ? 'hijacking' : 'template';
            } elseif ($strategy === 'semrush') {
                $actual_strategy = ($post_id > 1950) ? 'semrush' : 'template';
            } else {
                $actual_strategy = 'template';
            }

            $result = Core::generate_and_write(
                $post_id,
                [
                    'strategy'       => $actual_strategy,
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

    public static function ajax_rollback() {
        check_ajax_referer('tmw_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error();
        $post_id = (int)($_POST['post_id'] ?? 0);
        $res = Core::rollback($post_id);
        $res['ok'] ? wp_send_json_success() : wp_send_json_error();
    }

    public static function bulk_action($actions) {
        $actions['tmw_seo_generate_bulk'] = 'Generate SEO (TMW)';
        return $actions;
    }

    public static function handle_bulk($redirect, $doaction, $ids) {
        if ($doaction !== 'tmw_seo_generate_bulk') return $redirect;
        $count = 0;
        foreach ($ids as $id) {
            $r = Core::generate_and_write((int)$id, ['strategy' => 'template', 'insert_content' => true]);
            if (!empty($r['ok'])) $count++;
        }
        return add_query_arg('tmw_seo_bulk_done', $count, $redirect);
    }

    public static function tools_page() {
        add_submenu_page('tools.php', 'TMW SEO Autopilot', 'TMW SEO Autopilot', 'manage_options', 'tmw-seo-autopilot', [__CLASS__, 'render_tools']);
    }

    public static function render_tools() {
        if (!current_user_can('manage_options')) {
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

        ?>
        <div class="wrap">
            <h1>TMW SEO Autopilot - Dashboard</h1>
            <?php
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
            ?>

            <div class="tmw-dashboard" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin:20px 0;">
                <div class="tmw-stat-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px;">
                    <h3 style="margin:0 0 15px 0; font-size:14px; text-transform:uppercase; color:#646970;">Content Coverage</h3>
                    <div style="font-size:36px; font-weight:600; color:#2271b1; margin-bottom:10px;">
                        <?php echo (int) $models_with_content_percent; ?>%
                    </div>
                    <p style="margin:0; color:#646970;">
                        <?php echo number_format_i18n($models_with_content_count); ?> of <?php echo number_format_i18n($total_models); ?> models have SEO content
                    </p>
                    <div style="background:#f0f0f1; height:8px; border-radius:4px; margin-top:10px; overflow:hidden;">
                        <div style="background:#2271b1; height:100%; width:<?php echo (int) $models_with_content_percent; ?>%;"></div>
                    </div>
                </div>

                <div class="tmw-stat-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px;">
                    <h3 style="margin:0 0 15px 0; font-size:14px; text-transform:uppercase; color:#646970;">Video Coverage</h3>
                    <div style="font-size:36px; font-weight:600; color:#2271b1; margin-bottom:10px;">
                        <?php echo (int) $videos_with_content_percent; ?>%
                    </div>
                    <p style="margin:0; color:#646970;">
                        <?php echo number_format_i18n($videos_with_content); ?> of <?php echo number_format_i18n($total_videos); ?> videos have SEO content
                    </p>
                    <div style="background:#f0f0f1; height:8px; border-radius:4px; margin-top:10px; overflow:hidden;">
                        <div style="background:#2271b1; height:100%; width:<?php echo (int) $videos_with_content_percent; ?>%;"></div>
                    </div>
                </div>

                <div class="tmw-stat-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px;">
                    <h3 style="margin:0 0 15px 0; font-size:14px; text-transform:uppercase; color:#646970;">Average Word Count</h3>
                    <div style="font-size:36px; font-weight:600; color:<?php echo $avg_words >= 600 ? '#00a32a' : '#d63638'; ?>; margin-bottom:10px;">
                        <?php echo number_format_i18n($avg_words); ?>
                    </div>
                    <p style="margin:0; color:#646970;">
                        Target: 600+ words (RankMath requirement)
                    </p>
                    <?php if ($models_below_600_percent > 0) : ?>
                        <p style="margin:10px 0 0 0; color:#d63638; font-size:12px;">
                            ⚠ <?php echo (int) $models_below_600_percent; ?>% of sampled models below 600 words
                        </p>
                    <?php endif; ?>
                </div>

                <div class="tmw-stat-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px;">
                    <h3 style="margin:0 0 15px 0; font-size:14px; text-transform:uppercase; color:#646970;">Keyword Density</h3>
                    <div style="margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span style="font-weight:600;">LiveJasmin:</span>
                            <span style="color:<?php echo $avg_livejasmin >= 4 && $avg_livejasmin <= 6 ? '#00a32a' : '#d63638'; ?>;">
                                <?php echo esc_html($avg_livejasmin); ?>x avg
                            </span>
                        </div>
                        <div style="background:#f0f0f1; height:6px; border-radius:3px; overflow:hidden;">
                            <div style="background:<?php echo $avg_livejasmin >= 4 && $avg_livejasmin <= 6 ? '#00a32a' : '#d63638'; ?>; height:100%; width:<?php echo min(100, ($avg_livejasmin / 6) * 100); ?>%;"></div>
                        </div>
                        <div style="font-size:11px; color:#646970; margin-top:3px;">Target: 4-6 mentions</div>
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span style="font-weight:600;">OnlyFans:</span>
                            <span style="color:<?php echo $avg_onlyfans >= 3 && $avg_onlyfans <= 5 ? '#00a32a' : '#d63638'; ?>;">
                                <?php echo esc_html($avg_onlyfans); ?>x avg
                            </span>
                        </div>
                        <div style="background:#f0f0f1; height:6px; border-radius:3px; overflow:hidden;">
                            <div style="background:<?php echo $avg_onlyfans >= 3 && $avg_onlyfans <= 5 ? '#00a32a' : '#d63638'; ?>; height:100%; width:<?php echo min(100, ($avg_onlyfans / 5) * 100); ?>%;"></div>
                        </div>
                        <div style="font-size:11px; color:#646970; margin-top:3px;">Target: 3-5 mentions</div>
                    </div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; padding:20px; margin:20px 0;">
                <h3 style="margin:0 0 15px 0;">Recent Activity (Last 7 Days)</h3>
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
                    <p style="color:#646970;">No models generated in the last 7 days.</p>
                <?php else : ?>
                    <table class="widefull" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="text-align:left;">Model</th>
                                <th style="text-align:left;">Date</th>
                                <th style="text-align:center;">Words</th>
                                <th style="text-align:center;">OnlyFans</th>
                                <th style="text-align:center;">LiveJasmin</th>
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
                                    <td style="text-align:center;">
                                        <span style="color:<?php echo $wc >= 600 ? '#00a32a' : '#d63638'; ?>;">
                                            <?php echo (int) $wc; ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="color:<?php echo $of >= 3 && $of <= 5 ? '#00a32a' : '#d63638'; ?>;">
                                            <?php echo (int) $of; ?>x
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="color:<?php echo $lj >= 4 && $lj <= 6 ? '#00a32a' : '#d63638'; ?>;">
                                            <?php echo (int) $lj; ?>x
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <hr>
            <h2>Bulk Content Generator</h2>
            <div id="tmw-bulk-generator">
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
                                    <input type="radio" name="strategy" value="hijacking" checked>
                                    OnlyFans Hijacking (mention OnlyFans 3-5x)
                                </label><br>
                                <label>
                                    <input type="radio" name="strategy" value="semrush">
                                    SEMrush Optimized (use search volume data)
                                </label><br>
                                <label>
                                    <input type="radio" name="strategy" value="template">
                                    Standard Template (no special focus)
                                </label>
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

            <hr>
            <h2>Quick Backfill</h2>
            <form method="post">
                <?php wp_nonce_field('tmw_seo_tools'); ?>
                <p>Run a quick backfill using Template provider.</p>
                <p><label>Limit <input type="number" name="limit" value="25" min="1" max="500"></label></p>
                <p><button class="button button-primary" name="tmw_seo_run" value="1">Run Now</button></p>
                <hr>
                <p>Optional OpenAI provider: define <code>OPENAI_API_KEY</code> in wp-config.php or set constant <code>TMW_SEO_OPENAI</code> with your key to enable.</p>
            </form>
            <?php
            echo '<hr><h2>Integration Settings (read-only)</h2><table class="widefat"><tbody>';
            echo '<tr><th>Brand order</th><td>' . esc_html(implode(' → ', \TMW_SEO\Core::brand_order())) . '</td></tr>';
            echo '<tr><th>SUBAFF pattern</th><td>' . esc_html(\TMW_SEO\Core::subaff_pattern()) . '</td></tr>';
            $og = \TMW_SEO\Core::default_og();
            echo '<tr><th>Default OG image</th><td>' . ($og ? '<code>' . esc_url($og) . '</code>' : '<em>none</em>') . '</td></tr>';
            echo '</tbody></table>';
            $pts = \TMW_SEO\Core::video_post_types();
            $all = get_post_types(['public' => true], 'objects');
            echo '<hr><h2>Video Post Types</h2>';
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
        <?php
    }

    public static function add_video_metabox() {
        foreach (\TMW_SEO\Core::video_post_types() as $pt) {
            add_meta_box('tmwseo_box', 'TMW SEO Autopilot', [__CLASS__, 'render_video_box'], $pt, 'side', 'high');
        }
    }

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

    public static function save_video_metabox($post_id, $post) {
        if (!isset($_POST['tmwseo_box_nonce']) || !wp_verify_nonce($_POST['tmwseo_box_nonce'], 'tmwseo_box')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $val = isset($_POST['tmwseo_model_name']) ? sanitize_text_field(wp_unslash($_POST['tmwseo_model_name'])) : '';
        if ($val !== '') update_post_meta($post_id, 'tmwseo_model_name', $val); else delete_post_meta($post_id, 'tmwseo_model_name');
    }

    public static function handle_generate_now() {
        $post_id = (int)($_GET['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) wp_die('No permission');
        check_admin_referer('tmwseo_generate_now_' . $post_id);

        $post = get_post($post_id);
        if (!$post) {
            wp_die('Post not found');
        }

        if ( defined( 'TMW_DEBUG' ) && TMW_DEBUG ) {
            error_log(
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

    public static function handle_save_settings() {
        if (!current_user_can('manage_options')) wp_die('No permission');
        if (!isset($_POST['tmwseo_settings_nonce']) || !wp_verify_nonce($_POST['tmwseo_settings_nonce'], 'tmwseo_save_settings')) wp_die('Bad nonce');
        $pts = isset($_POST['tmwseo_video_pts']) ? (array) $_POST['tmwseo_video_pts'] : [];
        $pts = array_values(array_unique(array_map('sanitize_key', $pts)));
        update_option('tmwseo_video_pts', $pts, false);
        wp_safe_redirect(admin_url('tools.php?page=tmw-seo-autopilot&saved=1'));
        exit;
    }

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
