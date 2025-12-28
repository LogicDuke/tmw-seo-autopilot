<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

use TMW_SEO\Core;

class ModelTemplate {
    /** MODEL: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_model(array $c): array {
        $name   = isset($c['name']) ? $c['name'] : '';
        $focus  = $name !== '' ? $name . ' OnlyFans' : 'LiveJasmin model';
        $title  = sprintf('%s OnlyFans & LiveJasmin | Live Webcam Chat', $name);
        $title  = mb_substr($title, 0, 60);
        $meta   = sprintf(
            'Looking for %s on OnlyFans, Chaturbate, or Stripchat? Watch %s live on LiveJasmin for premium HD webcam shows.',
            $name,
            $name
        );

        $content_payload = \TMW_SEO\Content_Generator::generate_model($c);

        $keywords = array_values(array_unique(array_merge([
            $focus,
            $name . ' LiveJasmin',
            $name . ' webcam',
            $name . ' Chaturbate',
            $name . ' cam',
        ], $content_payload['keywords'])));

        return [
            'title'    => $title,
            'meta'     => $meta,
            'keywords' => $keywords,
            'content'  => $content_payload['content'],
        ];
    }

    /* helpers */
    protected function html(array $blocks): string {
        $out = '';
        foreach ($blocks as $b) {
            $tag = $b[0];
            $txt = $b[1] ?? '';
            $attrs = $b[2] ?? [];
            if ($tag === 'raw') {
                $out .= $txt;
                continue;
            }
            $attr_html = '';
            foreach ($attrs as $k => $v) {
                $attr_html .= ' ' . $k . '="' . esc_attr($v) . '"';
            }
            if ($tag === 'p') {
                $out .= '<p' . $attr_html . '>' . esc_html($txt) . '</p>';
            } elseif (in_array($tag, ['h2', 'h3'], true)) {
                $out .= '<' . $tag . $attr_html . '>' . esc_html($txt) . '</' . $tag . '>';
            }
        }
        return $out;
    }

    protected function faq_html(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['h3', $r[0]];
            $out[] = ['p', $r[1]];
        }
        return $out;
    }

    protected function mini_toc(): string {
        return '<nav class="tmw-mini-toc">
  <a href="#intro">Intro</a> · <a href="#highlights">Highlights</a> · <a href="#faq">FAQ</a>
</nav>';
    }

    protected function enforce_word_goal(string $content, string $focus, int $min = 900, int $max = 1200): string {
        // New behavior: do not aggressively pad with repeated paragraphs.
        // We accept whatever the base template produces.
        return $content;
    }

    protected function apply_density_guard(string $content, string $focus): string {
        // Old behavior: force at least 8 mentions of the focus keyword
        // by appending more paragraphs with the name.
        //
        // New behavior: leave content as-is so keyword density stays lower
        // and RankMath doesn't complain about over-optimization.
        return $content;
    }
}
