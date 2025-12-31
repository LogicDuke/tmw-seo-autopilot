<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Pool {
    public static function pool_dir(): string {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . 'tmwseo-keywords/';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    public static function load_category_keywords(string $slug): array {
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return [];
        }

        $path = trailingslashit(self::pool_dir()) . $slug . '.csv';
        if (!file_exists($path)) {
            return [];
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            return [];
        }

        $keywords    = [];
        $header_cols = [];
        $first_row   = fgetcsv($fh);
        if ($first_row !== false && is_array($first_row)) {
            $normalized = array_map(function ($col) {
                return strtolower(trim((string) $col));
            }, $first_row);
            foreach ($normalized as $index => $col) {
                if (in_array($col, ['keyword', 'phrase'], true)) {
                    $header_cols[] = $index;
                }
            }
            if (empty($header_cols)) {
                // Treat as data row.
                $data_rows = [$first_row];
            } else {
                $data_rows = [];
            }
        } else {
            $data_rows = [];
        }

        while (($row = fgetcsv($fh)) !== false) {
            $data_rows[] = $row;
        }
        fclose($fh);

        if (empty($header_cols)) {
            $header_cols = [0];
        }

        foreach ($data_rows as $row) {
            foreach ($header_cols as $col_index) {
                if (!isset($row[$col_index])) {
                    continue;
                }
                $value = trim((string) $row[$col_index]);
                if ($value === '') {
                    continue;
                }
                $keywords[] = $value;
            }
        }

        $keywords = array_values(array_unique(array_filter($keywords, 'strlen')));
        return $keywords;
    }

    public static function usage_log_path(): string {
        $path = trailingslashit(self::pool_dir()) . 'usage_log.csv';
        if (!file_exists($path)) {
            $fh = fopen($path, 'w');
            if ($fh) {
                fputcsv($fh, ['timestamp', 'keyword', 'category', 'post_type', 'post_id', 'url']);
                fclose($fh);
            }
        }
        return $path;
    }

    public static function is_used(string $keyword): bool {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '') {
            return false;
        }

        $path = self::usage_log_path();
        if (!file_exists($path)) {
            return false;
        }

        $fh = fopen($path, 'r');
        if (!$fh) {
            return false;
        }

        $used = false;
        // Skip header.
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            $logged = strtolower(trim((string) ($row[1] ?? '')));
            if ($logged !== '' && $logged === $keyword) {
                $used = true;
                break;
            }
        }
        fclose($fh);

        return $used;
    }

    public static function mark_used(string $keyword, string $category, string $post_type, int $post_id, string $url): void {
        $path = self::usage_log_path();
        $fh   = fopen($path, 'a');
        if (!$fh) {
            return;
        }

        if (flock($fh, LOCK_EX)) {
            fputcsv($fh, [
                gmdate('c'),
                $keyword,
                sanitize_title($category),
                sanitize_key($post_type),
                (int) $post_id,
                esc_url_raw($url),
            ]);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    }

    public static function pick_keywords(array $categories, int $count, string $post_type, int $post_id, string $url): array {
        $count      = max(0, $count);
        $categories = array_values(array_filter(array_unique(array_map('sanitize_title', $categories))));
        $categories = array_values(array_filter($categories));

        $selected = [];
        $seen     = [];

        foreach ($categories as $category) {
            $pool = self::load_category_keywords($category);
            foreach ($pool as $kw) {
                $norm = strtolower(trim($kw));
                if ($norm === '' || isset($seen[$norm]) || self::is_used($kw)) {
                    continue;
                }
                $seen[$norm] = [$kw, $category];
                if (count($selected) < $count) {
                    $selected[$norm] = [$kw, $category];
                }
            }
            if (count($selected) >= $count) {
                break;
            }
        }

        if (count($selected) < $count) {
            $generic_pool = self::load_category_keywords('generic');
            foreach ($generic_pool as $kw) {
                $norm = strtolower(trim($kw));
                if ($norm === '' || isset($seen[$norm]) || self::is_used($kw)) {
                    continue;
                }
                $seen[$norm] = [$kw, 'generic'];
                if (count($selected) < $count) {
                    $selected[$norm] = [$kw, 'generic'];
                }
            }
        }

        $final = [];
        foreach ($selected as $record) {
            $final[] = $record[0];
            self::mark_used($record[0], $record[1], $post_type, $post_id, $url);
            if (count($final) >= $count) {
                break;
            }
        }

        return $final;
    }
}
