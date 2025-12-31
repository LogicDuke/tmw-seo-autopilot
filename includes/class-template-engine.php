<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

/**
 * Lightweight template loader that reads arrays from /templates/*.php.
 * Templates are cached via static property and WordPress transients so
 * bulk generation does not repeatedly touch the filesystem.
 */
class Template_Engine {
    const CACHE_KEY_PREFIX = 'tmwseo_tpl_';

    /**
     * Load template library by slug (e.g., model-intros => templates/model-intros.php).
     */
    public static function load(string $slug): array {
        $slug      = str_replace(['..', '/'], '', $slug);
        $transient = self::CACHE_KEY_PREFIX . $slug;
        $cached    = get_transient($transient);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $path = trailingslashit(TMW_SEO_PATH) . 'templates/' . $slug . '.php';
        if (!file_exists($path)) {
            return [];
        }

        $templates = include $path;
        if (!is_array($templates)) {
            $templates = [];
        }

        set_transient($transient, $templates, DAY_IN_SECONDS);
        return $templates;
    }

    /**
     * Deterministic selection using crc32 of the seed.
     */
    public static function pick(string $slug, string $seed, int $offset = 0): string {
        $templates = self::load($slug);
        if (empty($templates)) {
            return '';
        }
        $index = absint((crc32($seed) + $offset) % count($templates));
        $template = $templates[$index];
        return is_string($template) ? $template : '';
    }

    /**
     * Specialized pick for FAQ entries (arrays with question/answer keys).
     */
    public static function pick_faq(string $slug, string $seed, int $count = 4, int $offset = 0): array {
        $templates = array_values(array_filter(self::load($slug), 'is_array'));
        if (empty($templates)) {
            return [];
        }
        $selected = [];
        for ($i = 0; $i < $count; $i++) {
            $index = absint((crc32($seed . 'faq-' . $i) + $offset) % count($templates));
            $selected[] = $templates[$index];
        }
        return $selected;
    }

    /**
     * Replace placeholders in a template string using a context array.
     */
    public static function render(string $template, array $context): string {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }
        return strtr($template, $replacements);
    }
}
