<?php
/**
 * Provider Model Template helpers.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

use TMW_SEO\Core;

/**
 * Modeltemplate class.
 *
 * @package TMW_SEO
 */
class ModelTemplate {
    /**
     * Generates model content using templates.
     * MODEL: returns ['title','meta','keywords'=>[...],'content'].
     *
     * @param array $c Context array with: name, site, looks, focus, extras, model_id, etc.
     * @return array
     */
    public function generate_model(array $c): array {
        $name = $c['name'] ?? '';
        $site = $c['site'] ?? 'Top Models Webcam';

        // Generate SEO title (max ~60 chars).
        $title = sprintf('%s — Live Cam Model Profile & Schedule | %s', $name, $site);
        if (mb_strlen($title) > 60) {
            $title = sprintf('%s — Live Cam Model Profile & Schedule', $name);
        }
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 57) . '...';
        }

        // Generate meta description (140-160 chars).
        $meta = sprintf(
            '%s on %s. Profile, photos, schedule tips, and live chat links. Follow %s for highlights and updates.',
            $name,
            $site,
            $name
        );
        if (mb_strlen($meta) > 160) {
            $meta = mb_substr($meta, 0, 157) . '...';
        }

        // Generate content using Content_Generator.
        $content_payload = \TMW_SEO\Content_Generator::generate_model($c);

        // Build keywords array.
        $base_keywords = [
            $name,
            $name . ' live cam',
            $name . ' profile',
            $name . ' webcam',
            $name . ' schedule',
        ];

        $extra_keywords = isset($content_payload['keywords']) && is_array($content_payload['keywords'])
            ? $content_payload['keywords']
            : [];

        $keywords = array_values(array_unique(array_merge($base_keywords, $extra_keywords)));

        return [
            'title'    => $title,
            'meta'     => $meta,
            'keywords' => $keywords,
            'content'  => $content_payload['content'] ?? '',
        ];
    }

    /**
     * Handles html.
     *
     * @param array $blocks
     * @return string
     */
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

    /**
     * Handles faq html.
     *
     * @param array $rows
     * @return array
     */
    protected function faq_html(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['h3', $r[0]];
            $out[] = ['p', $r[1]];
        }
        return $out;
    }

    /**
     * Handles mini toc.
     * @return string
     */
    protected function mini_toc(): string {
        return '<nav class="tmw-mini-toc">
  <a href="#intro">Intro</a> · <a href="#highlights">Highlights</a> · <a href="#faq">FAQ</a>
</nav>';
    }

    /**
     * Handles enforce word goal.
     *
     * @param string $content
     * @param string $focus
     * @param int $min
     * @param int $max
     * @return string
     */
    protected function enforce_word_goal(string $content, string $focus, int $min = 900, int $max = 1200): string {
        // New behavior: do not aggressively pad with repeated paragraphs.
        // We accept whatever the base template produces.
        return $content;
    }

    /**
     * Applies density guard.
     *
     * @param string $content
     * @param string $focus
     * @return string
     */
    protected function apply_density_guard(string $content, string $focus): string {
        // Old behavior: force at least 8 mentions of the focus keyword
        // by appending more paragraphs with the name.
        //
        // New behavior: leave content as-is so keyword density stays lower
        // and RankMath doesn't complain about over-optimization.
        return $content;
    }
}
