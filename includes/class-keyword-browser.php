<?php
/**
 * Keyword Browser helpers.
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) exit;

/**
 * Provides keyword browsing utilities across CSV packs.
 */
class Keyword_Browser {
    /**
     * Get keywords with filtering, pagination, sorting.
     */
    public static function get_keywords(array $filters = [], int $limit = 50, int $offset = 0, string $sort_by = 'keyword', string $sort_order = 'asc'): array {
        $sort_by = in_array($sort_by, ['keyword', 'tmw_kd', 'search_volume', 'cpc', 'category', 'type'], true) ? $sort_by : 'keyword';
        $sort_order = strtolower($sort_order) === 'desc' ? 'desc' : 'asc';
        $limit  = max(1, $limit);
        $offset = max(0, $offset);

        $keywords = self::load_keywords($filters);

        usort($keywords, function ($a, $b) use ($sort_by, $sort_order) {
            $val_a = $a[$sort_by] ?? '';
            $val_b = $b[$sort_by] ?? '';

            if (is_numeric($val_a) && is_numeric($val_b)) {
                $cmp = (float) $val_a <=> (float) $val_b;
            } else {
                $cmp = strcasecmp((string) $val_a, (string) $val_b);
            }

            return $sort_order === 'desc' ? -$cmp : $cmp;
        });

        return array_slice($keywords, $offset, $limit);
    }

    /**
     * Get total count for pagination.
     */
    public static function get_total_count(array $filters = []): int {
        return count(self::load_keywords($filters));
    }

    /**
     * Get keywords in specific KD bucket.
     */
    public static function get_keywords_by_kd_bucket(string $bucket, int $limit = 20, int $offset = 0): array {
        $ranges = [
            'very-easy' => [0, 20],
            'easy'      => [21, 30],
            'medium'    => [31, 50],
            'hard'      => [51, 70],
            'very-hard' => [71, 100],
            'unscored'  => null,
        ];

        $bucket_key = strtolower(sanitize_key($bucket));
        $range = $ranges[$bucket_key] ?? null;

        $filters = [];
        if ($range === null) {
            $filters['kd_range'] = 'unscored';
        } else {
            $filters['kd_range'] = [$range[0], $range[1]];
        }

        return self::get_keywords($filters, $limit, $offset, 'tmw_kd', 'asc');
    }

    /**
     * Get category statistics for grid.
     */
    public static function get_category_stats(): array {
        $cached = get_transient('tmwseo_keyword_category_stats');
        if (is_array($cached)) {
            return $cached;
        }

        $stats = [];
        foreach (Keyword_Library::categories() as $category) {
            $count = 0;
            foreach (['extra', 'longtail', 'competitor'] as $type) {
                $count += count(Keyword_Library::load($category, $type));
            }
            $stats[] = [
                'category' => $category,
                'count'    => $count,
            ];
        }

        set_transient('tmwseo_keyword_category_stats', $stats, HOUR_IN_SECONDS);
        return $stats;
    }

    /**
     * Delete a keyword from CSV.
     */
    public static function delete_keyword(string $keyword, string $category, string $type): bool {
        $category = sanitize_key($category);
        $type     = sanitize_key($type);
        $keyword  = Keyword_Library::sanitize_keyword($keyword);
        if ($keyword === '') {
            return false;
        }

        $uploads_path = trailingslashit(Keyword_Library::uploads_base_dir()) . "{$category}/{$type}.csv";
        $plugin_path  = trailingslashit(Keyword_Library::plugin_base_dir()) . "{$category}/{$type}.csv";
        $path         = file_exists($uploads_path) ? $uploads_path : $plugin_path;

        if (!file_exists($path) || !is_readable($path) || !is_writable($path)) {
            return false;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            return false;
        }

        $rows   = [];
        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return false;
        }

        while (($row = fgetcsv($fh)) !== false) {
            $row_keyword = Keyword_Library::sanitize_keyword((string) ($row[0] ?? ''));
            if (strcasecmp($row_keyword, $keyword) !== 0) {
                $rows[] = $row;
            }
        }
        fclose($fh);

        $fh = fopen($path, 'w');
        if (!$fh) {
            return false;
        }

        fputcsv($fh, $header);
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        Keyword_Library::flush_cache();
        delete_transient('tmwseo_keyword_category_stats');
        return true;
    }

    /**
     * Search keywords.
     */
    public static function search_keywords(string $query, int $limit = 100): array {
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }

        return self::get_keywords(['search' => $query], $limit, 0, 'keyword', 'asc');
    }

    /**
     * Internal: load keywords applying filters.
     */
    protected static function load_keywords(array $filters): array {
        $categories = Keyword_Library::categories();
        $types      = ['extra', 'longtail', 'competitor'];

        $filter_category = isset($filters['category']) && $filters['category'] !== 'all'
            ? sanitize_key($filters['category'])
            : null;
        $filter_type = isset($filters['type']) && $filters['type'] !== 'all'
            ? sanitize_key($filters['type'])
            : null;
        $filter_search = isset($filters['search']) ? Keyword_Library::sanitize_keyword((string) $filters['search']) : '';
        $kd_filter     = $filters['kd_range'] ?? 'all';

        $categories_to_scan = $filter_category ? [$filter_category] : $categories;
        $types_to_scan      = $filter_type ? [$filter_type] : $types;

        $results = [];
        foreach ($categories_to_scan as $category) {
            foreach ($types_to_scan as $type) {
                $rows = Keyword_Library::load_rows($category, $type);
                foreach ($rows as $row) {
                    $keyword = $row['keyword'] ?? '';
                    if ($keyword === '') {
                        continue;
                    }

                    if ($filter_search !== '' && stripos($keyword, $filter_search) === false) {
                        continue;
                    }

                    if (!self::matches_kd_filter($row['tmw_kd'] ?? null, $kd_filter)) {
                        continue;
                    }

                    $results[] = array_merge($row, [
                        'category' => $category,
                        'type'     => $type,
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Validate KD filter match.
     */
    protected static function matches_kd_filter($value, $filter): bool {
        if ($filter === 'all' || $filter === '' || $filter === null) {
            return true;
        }

        if ($filter === 'unscored') {
            return $value === null || $value === '';
        }

        if (is_array($filter) && count($filter) === 2) {
            $min = (float) $filter[0];
            $max = (float) $filter[1];
            return $value !== null && $value !== '' && (float) $value >= $min && (float) $value <= $max;
        }

        $map = [
            'very_easy' => [0, 20],
            'easy'      => [21, 30],
            'medium'    => [31, 50],
            'hard'      => [51, 70],
            'very_hard' => [71, 100],
        ];

        $key = strtolower((string) $filter);
        if (isset($map[$key])) {
            [$min, $max] = $map[$key];
            return $value !== null && $value !== '' && (float) $value >= $min && (float) $value <= $max;
        }

        return true;
    }
}
