<?php
/**
 * Plugin Name: TMW SEO Autopilot
 * Description: Auto-fills RankMath SEO & content for Model/Video. Video-first flow → creates/updates Model. Optional OpenAI/Serper.
 * Version: 1.0.0
 * Author: The Milisofia Ltd
 * License: GPLv2 or later
 */
if (!defined('ABSPATH')) exit;

define('TMW_SEO_PATH', plugin_dir_path(__FILE__));
define('TMW_SEO_URL', plugin_dir_url(__FILE__));
define('TMW_SEO_TAG', '[TMW-SEO]');

require_once TMW_SEO_PATH . 'includes/class-tmw-seo.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-admin.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-cli.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-rankmath.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-videoseo.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-automations.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-image-meta.php';
require_once TMW_SEO_PATH . 'includes/class-template-engine.php';
require_once TMW_SEO_PATH . 'includes/class-keyword-library.php';
require_once TMW_SEO_PATH . 'includes/class-keyword-usage.php';
require_once TMW_SEO_PATH . 'includes/class-content-generator.php';
require_once TMW_SEO_PATH . 'includes/class-keyword-manager.php';
require_once TMW_SEO_PATH . 'includes/class-uniqueness-checker.php';
require_once TMW_SEO_PATH . 'includes/media/class-image-meta-generator.php';
require_once TMW_SEO_PATH . 'includes/class-tmw-seo-media.php';
require_once TMW_SEO_PATH . 'includes/providers/class-provider-template.php';
require_once TMW_SEO_PATH . 'includes/providers/class-provider-openai.php';

add_action('plugins_loaded', function () {
    \TMW_SEO\Keyword_Usage::maybe_upgrade();
    \TMW_SEO\Admin::boot();
    \TMW_SEO\RankMath::boot();
    \TMW_SEO\VideoSEO::boot();
    \TMW_SEO\Automations::boot();
    \TMW_SEO\Image_Meta::boot();
    \TMW_SEO\Media::boot();
});

register_activation_hook(__FILE__, function () {
    \TMW_SEO\Core::debug_log(TMW_SEO_TAG . ' activated v1.0.0');
    \TMW_SEO\Keyword_Library::ensure_dirs_and_placeholders();
    \TMW_SEO\Keyword_Usage::install();
    add_option('tmwseo_used_video_seo_title_hashes', [], '', 'no');
    add_option('tmwseo_used_video_focus_keyword_hashes', [], '', 'no');
    add_option('tmwseo_used_video_title_focus_hashes', [], '', 'no');
});
