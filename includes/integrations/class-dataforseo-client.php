<?php
/**
 * Backward-compatible loader for the DataForSEO client.
 *
 * @package TMW_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load the updated client.
require_once __DIR__ . '/class-tmwseo-dataforseo-client.php';
