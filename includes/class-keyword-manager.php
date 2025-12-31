<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Handles competitor keyword distribution and density checks.
 */
class Keyword_Manager {
    protected static $competitor_map = [
        0 => ['Chaturbate', 'Stripchat'],
        1 => ['Stripchat', 'BongaCams'],
        2 => ['BongaCams', 'CamSoda'],
        3 => ['CamSoda', 'MyFreeCams'],
        4 => ['MyFreeCams', 'Chaturbate'],
    ];

    public static function competitor_pair(int $index): array {
        $bucket = (int) floor($index / 800);
        $pair   = self::$competitor_map[$bucket % count(self::$competitor_map)] ?? self::$competitor_map[0];
        return $pair;
    }

    public static function platform_counts(string $type): array {
        if ($type === 'video') {
            return [
                'livejasmin' => [3, 5],
                'onlyfans'   => [2, 3],
                'competitor' => [1, 2],
            ];
        }
        return [
            'livejasmin' => [4, 6],
            'onlyfans'   => [3, 5],
            'competitor' => [2, 4],
        ];
    }

    /**
     * Determine whether a keyword needs filling. Returns keywords to add.
     */
    public static function fill_keyword(string $content, string $keyword, int $min, int $max): array {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $pattern = '/' . preg_quote($keyword, '/') . '/i';
        $count   = preg_match_all($pattern, $content, $matches);

        if ($count > $max) {
            return [];
        }

        if ($count >= $min) {
            return [];
        }

        return [$keyword];
    }

    public static function apply_density(string $content, string $name, array $pair, string $type = 'model'): array {
        $keywords = array_filter(array_unique([
            $name,
            'live cam',
            'webcam highlights',
            'live streaming show',
            'LiveJasmin',
            'OnlyFans',
            $pair[0] ?? '',
            $pair[1] ?? '',
        ]));

        $content = self::reduce_focus_density($content, $name, $type);

        $pattern      = $name !== '' ? '/' . preg_quote($name, '/') . '/i' : '';
        $current_hits = $pattern !== '' ? preg_match_all($pattern, $content, $matches) : 0;
        $target_min   = $type === 'video' ? 2 : 3;

        if ($pattern !== '' && $current_hits < $target_min) {
            $filler = sprintf(
                '\n\n<p>%s keeps conversations feeling live and personal.</p>',
                esc_html($name)
            );
            $content .= $filler;
        }

        return [
            'content'  => $content,
            'keywords' => array_values($keywords),
        ];
    }

    protected static function reduce_focus_density(string $content, string $name, string $type): string {
        $pattern = '/' . preg_quote($name, '/') . '/i';
        $total   = preg_match_all($pattern, $content, $matches);
        $allowed = $type === 'video' ? 11 : 13;

        if ($total <= $allowed || $name === '') {
            return $content;
        }

        $heading_pattern = '~<h[12][^>]*>.*?' . preg_quote($name, '~') . '.*?</h[12]>~is';
        $heading_hits    = 0;
        if (preg_match_all($heading_pattern, $content, $heading_matches)) {
            foreach ($heading_matches[0] as $heading) {
                $heading_hits += preg_match_all($pattern, $heading, $tmp);
            }
        }

        $segments   = preg_split('~(<h[12][^>]*>.*?</h[12]>)~is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $replacement_terms = ['the performer', 'this performer', 'the model', 'they', 'the streamer'];
        $replacement_index = 0;
        $processed  = '';
        $total_seen = $heading_hits;
        $body_kept  = 0;
        $allowed_body = max(0, $allowed - $heading_hits);

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match('~^<h[12][^>]*>~i', $segment)) {
                $processed .= $segment;
                continue;
            }

            $segment = preg_replace_callback($pattern, function ($match) use (&$total_seen, &$body_kept, $allowed_body, $allowed, $heading_hits, $replacement_terms, &$replacement_index) {
                $total_seen++;

                if ($total_seen === ($heading_hits + 1)) {
                    $body_kept++;
                    return $match[0];
                }

                if ($body_kept < $allowed_body && $total_seen <= $allowed) {
                    $body_kept++;
                    return $match[0];
                }

                $replacement = $replacement_terms[$replacement_index % count($replacement_terms)];
                $replacement_index++;
                return $replacement;
            }, $segment);

            $processed .= $segment;
        }

        return $processed;
    }
}
