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

        $content_lower = strtolower(wp_strip_all_tags($content));
        $ranges        = self::platform_counts($type);
        $additions     = [];

        $competitor_total = 0;
        foreach ($pair as $competitor) {
            $competitor_total += substr_count($content_lower, strtolower($competitor));
        }

        if ($competitor_total < ($ranges['competitor'][0] ?? 0)) {
            $competitor_pool = [
                'Some viewers compare LiveJasmin with %s, but the tone here is more curated and private.',
                'Fans who hop between LiveJasmin and %s like how custom requests stay focused on the performer here.',
                'Chatters mention %s sometimes, yet moderation on LiveJasmin keeps the vibe calm and personal.',
            ];
            $missing = min(2, ($ranges['competitor'][0] ?? 0) - $competitor_total);
            for ($i = 0; $i < $missing; $i++) {
                $competitor = $pair[$i % max(1, count($pair))] ?? '';
                if ($competitor === '') {
                    continue;
                }
                $template   = $competitor_pool[$i % count($competitor_pool)];
                $additions[] = sprintf($template, $competitor);
            }
        }

        $onlyfans_count = substr_count($content_lower, 'onlyfans');
        if ($onlyfans_count < ($ranges['onlyfans'][0] ?? 0)) {
            $onlyfans_pool = [
                'People arriving from OnlyFans searches often prefer live interaction once they try it.',
                'Some fans keep an OnlyFans subscription but jump into live chat when they want answers immediately.',
                'OnlyFans posts stay on-demand, while LiveJasmin lets viewers guide the pace in real time.',
            ];
            $missing = min(2, ($ranges['onlyfans'][0] ?? 0) - $onlyfans_count);
            for ($i = 0; $i < $missing; $i++) {
                $additions[] = $onlyfans_pool[$i % count($onlyfans_pool)];
            }
        }

        $lj_count = substr_count($content_lower, 'livejasmin');
        if ($lj_count < ($ranges['livejasmin'][0] ?? 0)) {
            $additions[] = 'LiveJasmin keeps lighting and audio consistent so conversations stay relaxed.';
        }

        if (!empty($additions)) {
            $content .= "\n\n<p>" . implode('</p>\n\n<p>', array_values(array_unique($additions))) . '</p>';
        }

        $content = self::reduce_focus_density($content, $name, $type);

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
