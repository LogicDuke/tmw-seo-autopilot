<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Keyword_Difficulty_Proxy {
    const DEFAULT_COMPETITION = 'medium';
    const DEFAULT_CPC = 0.50;

    /**
     * Normalize competition string to low/medium/high.
     */
    public static function normalize_competition($value): string {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return self::DEFAULT_COMPETITION;
        }
        if (strpos($value, 'low') !== false) {
            return 'low';
        }
        if (strpos($value, 'high') !== false) {
            return 'high';
        }
        if (strpos($value, 'med') !== false) {
            return 'medium';
        }
        return self::DEFAULT_COMPETITION;
    }

    /**
     * Normalize CPC to a float value.
     */
    public static function normalize_cpc($value): float {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        preg_match_all('/[0-9]*\.?[0-9]+/', $value, $matches);
        if (!empty($matches[0])) {
            $numbers = array_map('floatval', $matches[0]);
            return max($numbers);
        }

        return (float) self::DEFAULT_CPC;
    }

    /**
     * Calculate TMW KD% and allow external providers to override.
     */
    public static function score(string $keyword, $competition = null, $cpc = null): int {
        $competition = self::normalize_competition($competition);
        $cpc         = self::normalize_cpc($cpc);

        $provider = apply_filters('tmwseo_kd_provider', 'proxy', $keyword);
        if ($provider !== 'proxy') {
            $external = apply_filters('tmwseo_kd_value', null, $keyword, $competition, $cpc, $provider);
            if ($external !== null && $external !== '') {
                return self::clamp((int) round((float) $external));
            }
        }

        $base_score = self::base_score($competition);
        $cpc_bump   = self::cpc_bump($cpc);
        $discount   = self::longtail_discount($keyword);

        return self::clamp($base_score + $cpc_bump - $discount);
    }

    /**
     * Adjust KD using GSC performance signals (Phase 2).
     */
    public static function adjust_for_gsc(int $kd, int $impressions, float $avg_position): int {
        if ($impressions > 100 && $avg_position > 20) {
            $kd += 10;
        } elseif ($impressions > 100 && $avg_position < 10) {
            $kd -= 10;
        }

        return self::clamp($kd);
    }

    /**
     * Build a normalized keyword row for CSV output.
     */
    public static function build_row(string $keyword, $competition = null, $cpc = null): array {
        $competition = self::normalize_competition($competition);
        $cpc         = self::normalize_cpc($cpc);

        return [
            'keyword'     => $keyword,
            'competition' => $competition,
            'cpc'         => $cpc,
            'tmw_kd'      => self::score($keyword, $competition, $cpc),
        ];
    }

    protected static function base_score(string $competition): int {
        switch ($competition) {
            case 'low':
                return 25;
            case 'high':
                return 80;
            case 'medium':
            default:
                return 55;
        }
    }

    protected static function cpc_bump(float $cpc): int {
        if ($cpc <= 0.50) {
            return 0;
        }
        if ($cpc <= 1.00) {
            return 3;
        }
        if ($cpc <= 2.00) {
            return 6;
        }
        if ($cpc <= 5.00) {
            return 10;
        }
        return 15;
    }

    protected static function longtail_discount(string $keyword): int {
        $words = preg_split('/\s+/', trim($keyword));
        $count = is_array($words) ? count(array_filter($words, 'strlen')) : 0;

        if ($count <= 2) {
            return 0;
        }
        if ($count <= 4) {
            return 5;
        }
        if ($count <= 6) {
            return 10;
        }
        return 20;
    }

    protected static function clamp(int $value): int {
        return max(0, min(100, $value));
    }
}
