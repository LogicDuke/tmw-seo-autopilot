<?php
/**
 * Keyword Validator - Ensures keywords are relevant to adult webcam industry
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Keyword_Validator {

    /**
     * @var array|null
     */
    protected static $anchor_terms = null;

    /**
     * @var array|null
     */
    protected static $blacklist = null;

    /**
     * Load anchor terms from data file
     *
     * @return array
     */
    protected static function load_anchors(): array {
        if (self::$anchor_terms === null) {
            $file = TMW_SEO_PATH . 'data/industry-keyword-anchors.php';
            self::$anchor_terms = file_exists($file) ? require $file : [];
        }

        return self::$anchor_terms;
    }

    /**
     * Check if keyword contains at least one industry anchor term
     *
     * @param string $keyword Keyword to test.
     * @return bool
     */
    public static function has_industry_anchor(string $keyword): bool {
        $keyword_lower = strtolower(trim($keyword));
        $anchors = self::load_anchors();

        foreach ($anchors as $anchor) {
            if (strpos($keyword_lower, strtolower($anchor)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if keyword is blacklisted
     *
     * @param string $keyword Keyword to test.
     * @return bool
     */
    public static function is_blacklisted(string $keyword): bool {
        $keyword_lower = strtolower(trim($keyword));
        $blacklist = Keyword_Pack_Builder::blacklist();

        foreach ($blacklist as $term) {
            if (strpos($keyword_lower, strtolower($term)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full validation: must have anchor AND not be blacklisted
     *
     * @param string $keyword Keyword to test.
     * @return bool
     */
    public static function is_valid_industry_keyword(string $keyword): bool {
        if (strlen(trim($keyword)) < 5) {
            return false;
        }

        if (self::is_blacklisted($keyword)) {
            return false;
        }

        if (!self::has_industry_anchor($keyword)) {
            return false;
        }

        return true;
    }

    /**
     * Filter an array of keywords, keeping only valid ones
     *
     * @param array $keywords Keywords to filter.
     * @return array
     */
    public static function filter_keywords(array $keywords): array {
        return array_values(array_filter($keywords, function ($kw) {
            $keyword = is_array($kw) ? ($kw['keyword'] ?? '') : $kw;
            return self::is_valid_industry_keyword($keyword);
        }));
    }

    /**
     * Score keyword relevance (0-100)
     * Higher score = more relevant to adult webcam industry
     *
     * @param string $keyword Keyword to score.
     * @return int
     */
    public static function relevance_score(string $keyword): int {
        $score = 0;
        $keyword_lower = strtolower($keyword);

        // Platform mentions (high value)
        $platforms = ['livejasmin', 'chaturbate', 'stripchat', 'bongacams', 'camsoda', 'myfreecams', 'onlyfans'];
        foreach ($platforms as $platform) {
            if (strpos($keyword_lower, $platform) !== false) {
                $score += 30;
                break;
            }
        }

        // Core industry terms
        $core_terms = ['cam girl', 'cam model', 'webcam model', 'live cam', 'camgirl', 'private show'];
        foreach ($core_terms as $term) {
            if (strpos($keyword_lower, $term) !== false) {
                $score += 25;
                break;
            }
        }

        // Category/niche terms
        $niche_terms = ['latina', 'asian', 'ebony', 'blonde', 'brunette', 'redhead', 'milf', 'teen', 'mature', 'petite', 'curvy', 'bbw'];
        foreach ($niche_terms as $term) {
            if (strpos($keyword_lower, $term) !== false) {
                $score += 15;
                break;
            }
        }

        // Activity terms
        $activity_terms = ['live', 'chat', 'show', 'stream', 'broadcast', 'perform'];
        foreach ($activity_terms as $term) {
            if (strpos($keyword_lower, $term) !== false) {
                $score += 10;
                break;
            }
        }

        // Word count bonus (long-tail is good)
        $word_count = str_word_count($keyword);
        if ($word_count >= 4) {
            $score += 10;
        } elseif ($word_count >= 3) {
            $score += 5;
        }

        return min(100, $score);
    }
}
