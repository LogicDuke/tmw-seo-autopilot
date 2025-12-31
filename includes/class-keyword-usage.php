<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Usage {
    const SCHEMA_VERSION = 1;

    public static function maybe_upgrade(): void {
        $current = (int) get_option('tmwseo_keyword_usage_schema', 0);
        if ($current < self::SCHEMA_VERSION) {
            self::install();
            update_option('tmwseo_keyword_usage_schema', self::SCHEMA_VERSION, false);
        }
    }

    public static function install(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $usage_table = $wpdb->prefix . 'tmwseo_keyword_usage';
        $log_table   = $wpdb->prefix . 'tmwseo_keyword_usage_log';

        $sql = [];
        $sql[] = "CREATE TABLE {$usage_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword_hash CHAR(32) NOT NULL,
            keyword_text TEXT NOT NULL,
            category VARCHAR(64) NOT NULL,
            type VARCHAR(16) NOT NULL,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_used_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY keyword_hash (keyword_hash)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$log_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword_hash CHAR(32) NOT NULL,
            keyword_text TEXT NOT NULL,
            category VARCHAR(64) NOT NULL,
            type VARCHAR(16) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            post_type VARCHAR(32) NOT NULL,
            used_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY keyword_hash (keyword_hash),
            KEY used_at (used_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('tmwseo_keyword_usage_schema', self::SCHEMA_VERSION, false);
    }

    public static function record_usage(array $keywords, string $category, string $type, int $post_id, string $post_type): void {
        global $wpdb;
        $usage_table = $wpdb->prefix . 'tmwseo_keyword_usage';
        $log_table   = $wpdb->prefix . 'tmwseo_keyword_usage_log';

        $category = sanitize_key($category);
        $type     = sanitize_key($type);
        $post_id  = absint($post_id);
        $post_type = sanitize_key($post_type);

        $now = current_time('mysql');

        foreach ($keywords as $kw) {
            $keyword = trim((string) $kw);
            if ($keyword === '') {
                continue;
            }

            $hash = md5(strtolower($keyword) . '|' . $type . '|' . $category);

            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$usage_table} (keyword_hash, keyword_text, category, type, used_count, last_used_at)
                    VALUES (%s, %s, %s, %s, 1, %s)
                    ON DUPLICATE KEY UPDATE used_count = used_count + 1, keyword_text = VALUES(keyword_text), last_used_at = VALUES(last_used_at)",
                    $hash,
                    $keyword,
                    $category,
                    $type,
                    $now
                )
            );

            if ($post_id > 0) {
                $wpdb->insert(
                    $log_table,
                    [
                        'keyword_hash' => $hash,
                        'keyword_text' => $keyword,
                        'category'     => $category,
                        'type'         => $type,
                        'post_id'      => $post_id,
                        'post_type'    => $post_type,
                        'used_at'      => $now,
                    ],
                    ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
                );
            }
        }
    }

    public static function get_usage_counts(array $keywords, string $category, string $type): array {
        $stats = self::get_usage_stats($keywords, $category, $type);
        $counts = [];
        foreach ($stats as $kw => $row) {
            $counts[$kw] = (int) ($row['count'] ?? 0);
        }
        return $counts;
    }

    public static function was_used_recently(string $keyword, string $category, string $type, int $days): bool {
        global $wpdb;
        $usage_table = $wpdb->prefix . 'tmwseo_keyword_usage';

        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        $threshold = gmdate('Y-m-d H:i:s', time() - max(0, $days) * DAY_IN_SECONDS);
        $hash = md5(strtolower($keyword) . '|' . sanitize_key($type) . '|' . sanitize_key($category));

        $last_used = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT last_used_at FROM {$usage_table} WHERE keyword_hash = %s AND last_used_at >= %s",
                $hash,
                $threshold
            )
        );

        return !empty($last_used);
    }

    public static function get_usage_stats(array $keywords, string $category, string $type): array {
        global $wpdb;
        $usage_table = $wpdb->prefix . 'tmwseo_keyword_usage';

        $category = sanitize_key($category);
        $type     = sanitize_key($type);

        $hash_map = [];
        foreach ($keywords as $kw) {
            $keyword = trim((string) $kw);
            if ($keyword === '') {
                continue;
            }
            $hash = md5(strtolower($keyword) . '|' . $type . '|' . $category);
            $hash_map[$hash] = $keyword;
        }

        if (empty($hash_map)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($hash_map), '%s'));
        $sql          = "SELECT keyword_hash, used_count, last_used_at FROM {$usage_table} WHERE keyword_hash IN ({$placeholders})";
        $rows         = $wpdb->get_results($wpdb->prepare($sql, array_keys($hash_map)), ARRAY_A);

        $stats = [];
        foreach ((array) $rows as $row) {
            $hash = $row['keyword_hash'];
            if (!isset($hash_map[$hash])) {
                continue;
            }
            $kw = $hash_map[$hash];
            $stats[$kw] = [
                'count'     => (int) ($row['used_count'] ?? 0),
                'last_used' => $row['last_used_at'] ?? null,
            ];
        }

        foreach ($hash_map as $hash => $kw) {
            if (!isset($stats[$kw])) {
                $stats[$kw] = ['count' => 0, 'last_used' => null];
            }
        }

        return $stats;
    }

    public static function is_within_days(?string $date, int $days): bool {
        if (empty($date)) {
            return false;
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }
        $cutoff = time() - max(0, $days) * DAY_IN_SECONDS;
        return $timestamp >= $cutoff;
    }
}
