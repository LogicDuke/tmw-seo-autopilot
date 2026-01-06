<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Aggregates stats for the dashboard UI.
 */
class Dashboard_Stats {
    const CACHE_KEY = 'tmwseo_dashboard_stats';

    public static function get_total_keywords(): int {
        $stats = self::load_cached();
        return (int) ($stats['total_keywords'] ?? 0);
    }

    public static function get_average_kd(): float {
        $stats = self::load_cached();
        return (float) ($stats['average_kd'] ?? 0);
    }

    public static function get_total_search_volume(): int {
        $stats = self::load_cached();
        return (int) ($stats['total_volume'] ?? 0);
    }

    public static function get_kd_distribution(): array {
        $stats = self::load_cached();
        return (array) ($stats['kd_distribution'] ?? []);
    }

    public static function get_category_distribution(): array {
        $stats = self::load_cached();
        return (array) ($stats['category_distribution'] ?? []);
    }

    public static function get_top_opportunities(int $limit = 20): array {
        $stats = self::load_cached();
        $top = (array) ($stats['top_opportunities'] ?? []);
        return array_slice($top, 0, $limit);
    }

    public static function get_recent_activity(int $limit = 10): array {
        $log = get_option('tmwseo_activity_log', []);
        return array_slice((array) $log, 0, $limit);
    }

    public static function get_api_credits_remaining(): ?float {
        $stats = self::load_cached();
        return isset($stats['api_credits']) ? (float) $stats['api_credits'] : null;
    }

    /**
     * Build stats and cache.
     */
    public static function build(): array {
        $keywords = self::collect_keywords();
        $total = count($keywords);
        $total_volume = 0;
        $total_kd = 0;
        $kd_count = 0;
        $distribution = array_fill_keys(array_keys(KD_Filter::buckets()), 0);
        $category_counts = [];
        $opportunities = [];

        foreach ($keywords as $row) {
            $total_volume += (int) ($row['search_volume'] ?? 0);
            $kd = KD_Filter::normalize_kd_value($row['tmw_kd'] ?? null);
            if ($kd !== null) {
                $total_kd += $kd;
                $kd_count++;
                $bucket = KD_Filter::bucket_label($kd);
                if (!isset($distribution[$bucket])) {
                    $distribution[$bucket] = 0;
                }
                $distribution[$bucket]++;
            }
            $category_counts[$row['category']] = ($category_counts[$row['category']] ?? 0) + 1;
            $row['opportunity'] = KD_Filter::calculate_opportunity_score($row);
            $opportunities[] = $row;
        }

        usort($opportunities, function ($a, $b) {
            return $b['opportunity'] <=> $a['opportunity'];
        });

        $average_kd = $kd_count > 0 ? $total_kd / $kd_count : 0;

        $stats = [
            'total_keywords'       => $total,
            'total_volume'         => $total_volume,
            'average_kd'           => round($average_kd, 2),
            'kd_distribution'      => $distribution,
            'category_distribution'=> $category_counts,
            'top_opportunities'    => array_slice($opportunities, 0, 20),
            'api_credits'          => null,
        ];

        set_transient(self::CACHE_KEY, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    protected static function load_cached(): array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return (array) $cached;
        }
        return self::build();
    }

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
                                'keyword'        => $row[0],
                                'word_count'     => $row[1] ?? 0,
                                'type'           => $type,
                                'source_seed'    => $row[3] ?? '',
                                'category'       => $category,
                                'timestamp'      => $row[5] ?? '',
                                'competition'    => $row[6] ?? '',
                                'cpc'            => $row[7] ?? '',
                                'tmw_kd'         => $row[8] ?? '',
                                'search_volume'  => $row[9] ?? '',
                            ];
                        }
                    }
                    fclose($fh);
                }
            }
        }
        return $keywords;
    }
}
