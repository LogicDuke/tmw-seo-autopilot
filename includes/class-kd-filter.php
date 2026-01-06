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
            $kd = is_array($kw)
                ? self::normalize_kd_value($kw['tmw_kd'] ?? null)
                : self::normalize_kd_value($kw);
            if ($kd === null) {
                return false;
            }
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
            $kd_a = is_array($a) ? self::normalize_kd_value($a['tmw_kd'] ?? null) : self::normalize_kd_value($a);
            $kd_b = is_array($b) ? self::normalize_kd_value($b['tmw_kd'] ?? null) : self::normalize_kd_value($b);
            $kd_a = $kd_a === null ? PHP_INT_MAX : $kd_a;
            $kd_b = $kd_b === null ? PHP_INT_MAX : $kd_b;
            return $order === 'asc' ? $kd_a - $kd_b : $kd_b - $kd_a;
        });

        return $keywords;
    }

    /**
     * Select keywords with KD-based distribution.
     *
     * Selects a mix of low-KD and higher-KD keywords based on configured
     * priority settings. Low-KD keywords are prioritized for newer sites.
     *
     * @since 1.2.0
     * @todo Integrate into keyword assignment workflow in class-keyword-assigner.php
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
            $kd = is_array($kw) ? self::normalize_kd_value($kw['tmw_kd'] ?? null) : self::normalize_kd_value($kw);
            return $kd !== null && $kd <= 30;
        });

        $other = array_filter($keywords, function ($kw) {
            $kd = is_array($kw) ? self::normalize_kd_value($kw['tmw_kd'] ?? null) : self::normalize_kd_value($kw);
            return $kd === null || $kd > 30;
        });

        // Sort both by KD
        $low_kd = self::sort_by_kd(array_values($low_kd), 'asc');
        $other = self::sort_by_kd(array_values($other), 'asc');

        // Select proportionally
        $low_count  = (int) ceil($count * $low_priority);
        $other_count = $count - $low_count;

        // Track selected keywords by their actual keyword string to avoid duplicates
        $selected = [];
        $selected_keywords = [];

        // Select from low KD pool
        foreach (array_slice($low_kd, 0, $low_count) as $kw) {
            $keyword_str = $kw['keyword'] ?? '';
            // Skip malformed entries with empty keyword
            if ($keyword_str === '') {
                continue;
            }
            if (!isset($selected_keywords[$keyword_str])) {
                $selected[] = $kw;
                $selected_keywords[$keyword_str] = true;
            }
        }
        
        // Select from other pool
        foreach (array_slice($other, 0, $other_count) as $kw) {
            $keyword_str = $kw['keyword'] ?? '';
            // Skip malformed entries with empty keyword
            if ($keyword_str === '') {
                continue;
            }
            if (!isset($selected_keywords[$keyword_str])) {
                $selected[] = $kw;
                $selected_keywords[$keyword_str] = true;
            }
        }

        // If not enough, fill from whatever is available (avoiding duplicates)
        if (count($selected) < $count) {
            $remaining = array_merge($low_kd, $other);
            foreach ($remaining as $kw) {
                if (count($selected) >= $count) {
                    break;
                }
                $keyword_str = $kw['keyword'] ?? '';
                // Skip malformed entries with empty keyword
                if ($keyword_str === '') {
                    continue;
                }
                if (!isset($selected_keywords[$keyword_str])) {
                    $selected[] = $kw;
                    $selected_keywords[$keyword_str] = true;
                }
            }
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
            'very_easy' => 0,   // 0-20
            'easy' => 0,        // 21-30
            'medium' => 0,      // 31-50
            'hard' => 0,        // 51-70
            'very_hard' => 0,   // 71-100
            'unscored' => 0,    // Missing KD
        ];

        foreach ($keywords as $kw) {
            $kd_source = is_array($kw) ? ($kw['tmw_kd_source'] ?? 'provided') : 'provided';
            $kd_value  = is_array($kw) ? ($kw['tmw_kd'] ?? null) : $kw;

            // If the CSV lacked KD, treat as unscored even if we estimated later.
            if ($kd_source === 'missing') {
                $stats['unscored']++;
                continue;
            }

            $kd = self::normalize_kd_value($kd_value);

            if ($kd === null) {
                $stats['unscored']++;
            } elseif ($kd <= 20) {
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

    /**
     * Normalize KD values from CSV or database into a 0-100 integer.
     *
     * @param mixed $value Raw KD value.
     * @return int|null
     */
    public static function normalize_kd_value($value): ?int {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            if (substr($value, -1) === '%') {
                $value = substr($value, 0, -1);
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric >= 0 && $numeric <= 1) {
            $numeric *= 100;
        }

        return (int) round(max(0, min(100, $numeric)));
    }

    /**
     * Calculate opportunity score for a keyword row.
     *
     * @param array $keyword Keyword data (expects search_volume, tmw_kd, and cpc).
     * @return float
     */
    public static function calculate_opportunity_score(array $keyword): float {
        $volume = (int) ($keyword['search_volume'] ?? 0);
        $kd     = (float) ($keyword['tmw_kd'] ?? 0);
        $cpc    = (float) ($keyword['cpc'] ?? 0);

        $base_score = min(100, ($volume >= 10000 ? 100 : ($volume / 100)));
        $kd_penalty = max(0, (100 - $kd) / 100);
        $cpc_bonus  = min($cpc * 5, 20);

        $opportunity = ($base_score * $kd_penalty) + $cpc_bonus;

        return round(min(100, max(0, $opportunity)), 2);
    }
}
