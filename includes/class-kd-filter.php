<?php
/**
 * KD Filter - Filters and prioritizes keywords by difficulty
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

class KD_Filter {

    /**
     * Get current KD settings
     *
     * @return array
     */
    public static function get_settings(): array {
        $mode = get_option('tmwseo_kd_mode', 'balanced');

        $presets = [
            'low_only' => [
                'min' => 0,
                'max' => 30,
                'low_priority' => 100,
            ],
            'low_priority' => [
                'min' => 0,
                'max' => 50,
                'low_priority' => 70,
            ],
            'balanced' => [
                'min' => 0,
                'max' => 70,
                'low_priority' => 50,
            ],
            'custom' => [
                'min' => (int) get_option('tmwseo_kd_min', 0),
                'max' => (int) get_option('tmwseo_kd_max', 40),
                'low_priority' => (int) get_option('tmwseo_kd_priority_low', 70),
            ],
        ];

        return $presets[$mode] ?? $presets['balanced'];
    }

    /**
     * Filter keywords by KD range
     *
     * @param array    $keywords Keywords to filter.
     * @param int|null $max_kd   Max KD allowed.
     * @param int|null $min_kd   Min KD allowed.
     * @return array
     */
    public static function filter_by_kd(array $keywords, ?int $max_kd = null, ?int $min_kd = null): array {
        $settings = self::get_settings();
        $max = $max_kd ?? $settings['max'];
        $min = $min_kd ?? $settings['min'];

        return array_filter($keywords, function ($kw) use ($min, $max) {
            $kd = is_array($kw) ? (int) ($kw['tmw_kd'] ?? 50) : 50;
            return $kd >= $min && $kd <= $max;
        });
    }

    /**
     * Sort keywords by KD (lowest first)
     *
     * @param array  $keywords Keywords to sort.
     * @param string $order    Sort order.
     * @return array
     */
    public static function sort_by_kd(array $keywords, string $order = 'asc'): array {
        usort($keywords, function ($a, $b) use ($order) {
            $kd_a = is_array($a) ? (int) ($a['tmw_kd'] ?? 50) : 50;
            $kd_b = is_array($b) ? (int) ($b['tmw_kd'] ?? 50) : 50;
            return $order === 'asc' ? $kd_a - $kd_b : $kd_b - $kd_a;
        });

        return $keywords;
    }

    /**
     * Select keywords with proper KD distribution
     * Returns a mix based on low_priority setting
     *
     * @param array $keywords Keywords to select from.
     * @param int   $count    Desired count.
     * @return array
     */
    public static function select_with_distribution(array $keywords, int $count): array {
        $settings = self::get_settings();
        $low_priority = $settings['low_priority'] / 100;

        // Split into low and other
        $low_kd = array_filter($keywords, function ($kw) {
            $kd = is_array($kw) ? (int) ($kw['tmw_kd'] ?? 50) : 50;
            return $kd <= 30;
        });

        $other = array_filter($keywords, function ($kw) {
            $kd = is_array($kw) ? (int) ($kw['tmw_kd'] ?? 50) : 50;
            return $kd > 30;
        });

        // Sort both by KD
        $low_kd = self::sort_by_kd(array_values($low_kd), 'asc');
        $other = self::sort_by_kd(array_values($other), 'asc');

        // Calculate distribution
        $low_count = (int) ceil($count * $low_priority);
        $other_count = $count - $low_count;

        // Select from each pool
        $selected = array_merge(
            array_slice($low_kd, 0, $low_count),
            array_slice($other, 0, $other_count)
        );

        // If not enough, fill from whatever is available
        if (count($selected) < $count) {
            $remaining = array_merge($low_kd, $other);
            $remaining = array_diff_key($remaining, $selected);
            $selected = array_merge($selected, array_slice($remaining, 0, $count - count($selected)));
        }

        return array_slice($selected, 0, $count);
    }

    /**
     * Get KD distribution stats for a keyword set
     *
     * @param array $keywords Keywords to analyze.
     * @return array
     */
    public static function get_distribution_stats(array $keywords): array {
        $stats = [
            'total' => count($keywords),
            'very_easy' => 0,  // 0-20
            'easy' => 0,       // 21-30
            'medium' => 0,     // 31-50
            'hard' => 0,       // 51-70
            'very_hard' => 0,  // 71-100
        ];

        foreach ($keywords as $kw) {
            $kd = is_array($kw) ? (int) ($kw['tmw_kd'] ?? 50) : 50;

            if ($kd <= 20) {
                $stats['very_easy']++;
            } elseif ($kd <= 30) {
                $stats['easy']++;
            } elseif ($kd <= 50) {
                $stats['medium']++;
            } elseif ($kd <= 70) {
                $stats['hard']++;
            } else {
                $stats['very_hard']++;
            }
        }

        return $stats;
    }
}
