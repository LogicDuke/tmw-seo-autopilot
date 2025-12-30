<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

if (defined('WP_CLI') && WP_CLI) {
    class CLI {
        public static function boot() {
            \WP_CLI::add_command('tmw-seo generate', [__CLASS__, 'generate']);
            \WP_CLI::add_command('tmw-seo rollback', [__CLASS__, 'rollback']);
            \WP_CLI::add_command('tmw-seo keyword-packs status', [__CLASS__, 'keyword_packs_status']);
            \WP_CLI::add_command('tmw-seo keyword-packs flush', [__CLASS__, 'keyword_packs_flush']);
            \WP_CLI::add_command('tmw-seo keyword-packs init', [__CLASS__, 'keyword_packs_init']);
        }
        public static function generate($args, $assoc) {
            $pt = $assoc['post_type'] ?? Core::POST_TYPE;
            $limit = isset($assoc['limit']) ? (int)$assoc['limit'] : 100;
            $dry = !empty($assoc['dry-run']);
            $q = new \WP_Query(['post_type' => $pt, 'posts_per_page' => $limit, 'post_status' => 'publish']);
            $done = 0;
            while ($q->have_posts()) { $q->the_post();
                $r = Core::generate_and_write(get_the_ID(), ['dry_run' => $dry, 'strategy' => 'template', 'insert_content' => true]);
                if (!empty($r['ok'])) $done++;
            } \WP_CLI::success("Generated SEO for $done posts.");
        }
        public static function rollback($args, $assoc) {
            $id = (int)($assoc['post_id'] ?? 0);
            if (!$id) { \WP_CLI::error('Provide --post_id=ID'); return; }
            $r = Core::rollback($id);
            $r['ok'] ? \WP_CLI::success('Rollback complete') : \WP_CLI::error('Nothing to rollback');
        }

        public static function keyword_packs_status($args, $assoc) {
            $categories = ['general', 'roleplay', 'chat', 'cosplay', 'couples'];
            $types      = ['extra', 'longtail', 'competitor'];
            $uploads    = Keyword_Library::uploads_base_dir();
            $plugin     = Keyword_Library::plugin_base_dir();

            \WP_CLI::line('Uploads: ' . $uploads);
            foreach ($categories as $cat) {
                foreach ($types as $type) {
                    $upload_path = trailingslashit($uploads) . "{$cat}/{$type}.csv";
                    $plugin_path = trailingslashit($plugin) . "{$cat}/{$type}.csv";
                    $path        = file_exists($upload_path) ? $upload_path : $plugin_path;
                    $count       = count(Keyword_Library::load($cat, $type));
                    \WP_CLI::line(sprintf('%s/%s: %s (%d)', $cat, $type, $path, $count));
                }
            }
        }

        public static function keyword_packs_flush($args, $assoc) {
            Keyword_Library::flush_cache();
            \WP_CLI::success('Keyword cache flushed.');
        }

        public static function keyword_packs_init($args, $assoc) {
            Keyword_Library::ensure_dirs_and_placeholders();
            Keyword_Library::flush_cache();
            \WP_CLI::success('Keyword pack folders initialized.');
        }
    }
    CLI::boot();
}
