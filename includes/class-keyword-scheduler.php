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
        $batch_size    = 100;
        $state         = get_option('tmwseo_last_metrics_refresh', []);
        $offset        = (int) ($state['offset'] ?? 0);
        $location_code = (int) get_option('tmwseo_dataforseo_location_code', 2840);
        $language_code = (string) get_option('tmwseo_dataforseo_language_code', 'en');

        if (!class_exists(DataForSEO_Client::class) || !DataForSEO_Client::is_enabled()) {
            update_option('tmwseo_last_metrics_refresh', [
                'timestamp' => current_time('mysql'),
                'processed' => 0,
                'offset'    => $offset,
            ]);
            return;
        }

        $client = new DataForSEO_Client();
        $files  = self::load_keyword_files();

        $all_refs   = [];
        $file_store = [];

        foreach ($files as $file) {
            $file_data = self::read_keyword_file($file['source_path'], $file['target_path']);
            if ($file_data === null) {
                continue;
            }

            $file_key = $file_data['target_path'];
            $file_store[$file_key] = $file_data;

            foreach ($file_data['rows'] as $index => $row) {
                $keyword_index = $file_data['indexes']['keyword'];
                $keyword = isset($row[$keyword_index]) ? Keyword_Library::sanitize_keyword((string) $row[$keyword_index]) : '';
                if ($keyword === '') {
                    continue;
                }
                $all_refs[] = [
                    'file_key'   => $file_key,
                    'row_index'  => $index,
                    'keyword'    => $keyword,
                ];
            }
        }

        $total_rows = count($all_refs);
        if ($total_rows === 0) {
            update_option('tmwseo_last_metrics_refresh', [
                'timestamp' => current_time('mysql'),
                'processed' => 0,
                'offset'    => 0,
            ]);
            return;
        }

        $offset = $total_rows > 0 ? $offset % $total_rows : 0;
        $refs   = array_slice($all_refs, $offset, $batch_size);
        if (count($refs) < $batch_size && $total_rows > $batch_size) {
            $refs = array_merge($refs, array_slice($all_refs, 0, $batch_size - count($refs)));
        }

        $keywords = array_map(function ($ref) {
            return $ref['keyword'];
        }, $refs);

        $volume_data = $client->search_volume($keywords, $location_code, $language_code);
        if (is_wp_error($volume_data)) {
            $volume_data = [];
        }

        $kd_data = $client->resolve_keyword_difficulty($keywords, $location_code, $language_code);
        if (is_wp_error($kd_data)) {
            $kd_data = [];
        }

        foreach ($refs as $ref) {
            $file_key = $ref['file_key'];
            $keyword  = $ref['keyword'];

            if (!isset($file_store[$file_key])) {
                continue;
            }

            $file_data = &$file_store[$file_key];
            $indexes   = $file_data['indexes'];
            $row_index = $ref['row_index'];

            $volume_row = $volume_data[$keyword] ?? [];
            $kd_row     = $kd_data[$keyword] ?? [];

            self::ensure_column($file_data, 'search_volume');
            self::ensure_column($file_data, 'tmw_kd');
            self::ensure_column($file_data, 'competition_level');
            self::ensure_column($file_data, 'competition');
            self::ensure_column($file_data, 'kd_keyword_used');
            self::ensure_column($file_data, 'tmw_kd_source');

            $file_data['rows'][$row_index][$file_data['indexes']['search_volume']] = isset($volume_row['search_volume']) ? (int) $volume_row['search_volume'] : $file_data['rows'][$row_index][$file_data['indexes']['search_volume']] ?? '';

            if (isset($volume_row['competition_level'])) {
                $file_data['rows'][$row_index][$file_data['indexes']['competition_level']] = $volume_row['competition_level'];
                $file_data['rows'][$row_index][$file_data['indexes']['competition']] = Keyword_Difficulty_Proxy::normalize_competition($volume_row['competition_level']);
            }

            if (isset($volume_row['competition'])) {
                $file_data['rows'][$row_index][$file_data['indexes']['competition']] = $volume_row['competition'];
            }

            if (isset($volume_row['cpc']) && self::column_exists($file_data, 'cpc')) {
                $file_data['rows'][$row_index][$file_data['indexes']['cpc']] = $volume_row['cpc'];
            }

            if (isset($kd_row['kd'])) {
                $file_data['rows'][$row_index][$file_data['indexes']['tmw_kd']] = $kd_row['kd'];
            }

            if (isset($kd_row['kd_keyword_used'])) {
                $file_data['rows'][$row_index][$file_data['indexes']['kd_keyword_used']] = $kd_row['kd_keyword_used'];
            }

            if (isset($kd_row['kd_source'])) {
                $file_data['rows'][$row_index][$file_data['indexes']['tmw_kd_source']] = $kd_row['kd_source'];
            }
        }

        foreach ($file_store as $file_data) {
            self::write_keyword_file($file_data);
        }

        $processed = count($refs);
        $new_offset = ($offset + $processed) % $total_rows;

        update_option('tmwseo_last_metrics_refresh', [
            'timestamp' => current_time('mysql'),
            'processed' => $processed,
            'offset'    => $new_offset,
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
     * Resolve keyword CSV file locations.
     */
    protected static function load_keyword_files(): array {
        $files = [];
        foreach (Keyword_Library::categories() as $category) {
            foreach (['extra', 'longtail', 'competitor'] as $type) {
                $uploads_path = trailingslashit(Keyword_Library::uploads_base_dir()) . "{$category}/{$type}.csv";
                $plugin_path  = trailingslashit(Keyword_Library::plugin_base_dir()) . "{$category}/{$type}.csv";

                $source_path = file_exists($uploads_path) ? $uploads_path : $plugin_path;
                if (!file_exists($source_path)) {
                    continue;
                }

                $files[] = [
                    'category'    => $category,
                    'type'        => $type,
                    'source_path' => $source_path,
                    'target_path' => $uploads_path,
                ];
            }
        }

        return $files;
    }

    /**
     * Load a keyword CSV with header indexes.
     */
    protected static function read_keyword_file(string $source_path, string $target_path): ?array {
        if (!file_exists($source_path)) {
            return null;
        }

        $fh = fopen($source_path, 'r');
        if (!$fh) {
            return null;
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return null;
        }

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            $rows[] = $row;
        }
        fclose($fh);

        $indexes = self::map_header_indexes($header);
        if (!isset($indexes['keyword'])) {
            $indexes['keyword'] = 0;
        }

        return [
            'header'      => $header,
            'rows'        => $rows,
            'indexes'     => $indexes,
            'target_path' => $target_path,
        ];
    }

    /**
     * Map header names to indexes.
     */
    protected static function map_header_indexes(array $header): array {
        $indexes = [];
        foreach ($header as $index => $col) {
            $normalized = strtolower(trim((string) $col));
            $normalized = preg_replace('/^\xEF\xBB\xBF/', '', (string) $normalized);

            if ($normalized === 'keyword' || $normalized === 'phrase') {
                $indexes['keyword'] = $index;
            }
            if ($normalized === 'search_volume') {
                $indexes['search_volume'] = $index;
            }
            if ($normalized === 'tmw_kd' || $normalized === 'tmw_kd%') {
                $indexes['tmw_kd'] = $index;
            }
            if ($normalized === 'competition_level') {
                $indexes['competition_level'] = $index;
            }
            if ($normalized === 'competition') {
                $indexes['competition'] = $index;
            }
            if ($normalized === 'kd_keyword_used') {
                $indexes['kd_keyword_used'] = $index;
            }
            if ($normalized === 'tmw_kd_source' || $normalized === 'kd_source') {
                $indexes['tmw_kd_source'] = $index;
            }
            if ($normalized === 'cpc') {
                $indexes['cpc'] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Ensure a column exists in the file structure and return its index.
     */
    protected static function ensure_column(array &$file_data, string $column): int {
        if (isset($file_data['indexes'][$column])) {
            return $file_data['indexes'][$column];
        }

        $file_data['header'][] = $column;
        $new_index = count($file_data['header']) - 1;
        $file_data['indexes'][$column] = $new_index;

        foreach ($file_data['rows'] as &$row) {
            $row[$new_index] = $row[$new_index] ?? '';
        }
        unset($row);

        return $new_index;
    }

    /**
     * Check if a column exists.
     */
    protected static function column_exists(array $file_data, string $column): bool {
        return isset($file_data['indexes'][$column]);
    }

    /**
     * Write updated keyword data back to the uploads path.
     */
    protected static function write_keyword_file(array $file_data): void {
        $target_path = $file_data['target_path'];
        wp_mkdir_p(dirname($target_path));

        $fh = fopen($target_path, 'w');
        if (!$fh) {
            return;
        }

        fputcsv($fh, $file_data['header']);

        foreach ($file_data['rows'] as $row) {
            if (count($row) < count($file_data['header'])) {
                $row = array_pad($row, count($file_data['header']), '');
            }
            fputcsv($fh, $row);
        }

        fclose($fh);
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
