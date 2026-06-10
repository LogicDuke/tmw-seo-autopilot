<?php
/**
 * Tmw_Seo_Csv_Upload — bridge to the engine's CsvUpload validator.
 *
 * The autopilot's CSV upload handlers (keyword library uploads, Google
 * Keyword Planner imports) previously inspected only the filename
 * extension and treated everything ending in ".csv" as safe. An admin
 * (or anyone who phishes the admin's nonce) could upload an .htaccess,
 * .html, or double-extension payload by simply naming it ".csv".
 *
 * This bridge delegates to TMWSEO\Engine\Services\CsvUpload, which
 * content-sniffs via wp_check_filetype_and_ext (PHP finfo under the
 * hood). Fails CLOSED when the engine plugin isn't loaded — we reject
 * the upload outright rather than silently falling back to the broken
 * extension-only check.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tmw_Seo_Csv_Upload {

    private const ENGINE_VALIDATOR = '\\TMWSEO\\Engine\\Services\\CsvUpload';

    /**
     * Default size cap matches the engine's DEFAULT_MAX_BYTES so a
     * caller that forgets to pass one still gets a real ceiling rather
     * than the silent "0 = unlimited" the previous default produced.
     * Each autopilot call site SHOULD pass an explicit value so the cap
     * is documented at the upload site itself.
     */
    public const DEFAULT_MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Validate a $_FILES entry. Returns the same shape as the engine
     * validator: ['ok' => true, 'name' => ..., 'tmp' => ..., 'bytes' => ...]
     * on success, ['ok' => false, 'error' => ...] on rejection.
     */
    public static function validate($file, int $max_bytes = self::DEFAULT_MAX_BYTES): array {
        if (!class_exists(self::ENGINE_VALIDATOR)) {
            // Fail closed: refuse the upload rather than silently passing it
            // through with no content sniff. The autopilot needs the engine
            // for this validation; degrading to the old "trust the extension"
            // behaviour would re-introduce the audit issue.
            return ['ok' => false, 'error' => 'engine_not_loaded'];
        }
        return \TMWSEO\Engine\Services\CsvUpload::validate($file, $max_bytes);
    }
}
