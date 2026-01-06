<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Handles scheduled keyword automation.
 */
class Keyword_Scheduler {
    /**
     * Register cron hooks.
     */
    public static function boot() {
        add_action('tmwseo_refresh_keyword_metrics', [__CLASS__, 'refresh_keyword_metrics']);
        add_action('tmwseo_discover_new_keywords', [__CLASS__, 'discover_new_keywords']);
    }

    /**
     * Refresh keyword metrics in batches.
     */
    public static function refresh_keyword_metrics() {
        $keywords = self::collect_keywords();
        $processed = 0;
        $client = class_exists(DataForSEO_Client::class) ? new DataForSEO_Client() : null;

        foreach ($keywords as $key => $row) {
            if ($processed >= 100) {
                break;
            }
            if ($client && method_exists($client, 'search_volume')) {
                $volume = $client->search_volume([$row['keyword']]);
                $difficulty = $client->resolve_keyword_difficulty([$row['keyword']]);
                $keywords[$key]['search_volume'] = $volume[0]['search_volume'] ?? $row['search_volume'];
                $keywords[$key]['tmw_kd'] = $difficulty[0]['tmw_kd'] ?? $row['tmw_kd'];
            }
            $processed++;
        }

        self::write_keywords($keywords);
        update_option('tmwseo_last_metrics_refresh', [
            'timestamp' => current_time('mysql'),
            'processed' => $processed,
        ]);
    }

    /**
     * Discover new keywords weekly using Google Suggest.
     */
    public static function discover_new_keywords() {
        $categories = Keyword_Library::categories();
        foreach ($categories as $category) {
            Keyword_Pack_Builder::autofill_category_keywords($category, 'extra', 20, true, false);
        }
        update_option('tmwseo_last_keyword_discovery', [
            'timestamp' => current_time('mysql'),
            'categories' => $categories,
        ]);
    }

    /**
     * Collect keywords from plugin CSVs.
     */
    protected static function collect_keywords(): array {
        $base = Keyword_Library::plugin_base_dir();
        $keywords = [];
        foreach (Keyword_Library::categories() as $category) {
            foreach (['extra', 'longtail', 'competitor'] as $type) {
                $file = trailingslashit($base) . $category . '/' . $type . '.csv';
                if (!file_exists($file)) {
                    continue;
                }
                if (($fh = fopen($file, 'r')) !== false) {
                    $header = fgetcsv($fh);
                    while (($row = fgetcsv($fh)) !== false) {
                        if (isset($row[0]) && $row[0] !== 'keyword') {
                            $keywords[] = [
                                'keyword' => $row[0],
                                'category' => $category,
                                'type' => $type,
                                'search_volume' => $row[9] ?? 0,
                                'tmw_kd' => $row[8] ?? 0,
                            ];
                        }
                    }
                    fclose($fh);
                }
            }
        }
        return $keywords;
    }

    /**
     * Write keywords back to CSV files.
     */
    protected static function write_keywords(array $keywords): void {
        $grouped = [];
        foreach ($keywords as $row) {
            $grouped[$row['category']][$row['type']][] = $row;
        }

        foreach ($grouped as $category => $types) {
            foreach ($types as $type => $rows) {
                $file = trailingslashit(Keyword_Library::plugin_base_dir()) . $category . '/' . $type . '.csv';
                $fh = fopen($file, 'w');
                if (!$fh) {
                    continue;
                }
                $columns = Keyword_Pack_Builder::csv_columns();
                fputcsv($fh, $columns);
                foreach ($rows as $row) {
                    $ordered = [];
                    foreach ($columns as $col) {
                        $ordered[$col] = $row[$col] ?? '';
                    }
                    fputcsv($fh, $ordered);
                }
                fclose($fh);
            }
        }
    }
}
