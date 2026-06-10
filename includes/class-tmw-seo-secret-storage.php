<?php
/**
 * Tmw_Seo_Secret_Storage — thin bridge to the engine's Crypto service.
 *
 * The autopilot stores OpenAI, Serper, Semrush, and DataForSEO credentials
 * in top-level wp_options rows. Previously these were stored as plain text.
 * This class wraps every encrypted save / read so that:
 *   - When the tmw-seo-engine plugin is active, secrets are encrypted at
 *     rest via the engine's TMWSEO\Engine\Services\Crypto helper
 *     (sodium_crypto_secretbox keyed on AUTH_KEY).
 *   - When the engine isn't available, values pass through unchanged so
 *     the autopilot remains functional standalone (degraded posture, but
 *     no fatal errors). The engine is the typical deployment so this
 *     fallback exists only for safety.
 *
 * Back-compat with already-stored plain-text values is provided by
 * Crypto::decrypt() itself, which returns values without an "enc:" or
 * "b64:" sentinel unchanged. No migration script is required: the first
 * save through any of these helpers re-stores the value encrypted.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tmw_Seo_Secret_Storage {

    /**
     * Fully-qualified name of the engine's Crypto service. Resolved at
     * call time via class_exists() so the autopilot doesn't require the
     * engine to be loaded for non-secret code paths to work.
     */
    private const ENGINE_CRYPTO = '\\TMWSEO\\Engine\\Services\\Crypto';

    /**
     * Encrypt a secret for storage. If the engine isn't available, returns
     * the plaintext unchanged (so the value still saves, just unencrypted).
     */
    public static function encrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        if (class_exists(self::ENGINE_CRYPTO)) {
            return \TMWSEO\Engine\Services\Crypto::encrypt($value);
        }
        return $value;
    }

    /**
     * Decrypt a stored secret. Pass-through for legacy plain-text values
     * (Crypto::decrypt handles that) and for cases where the engine isn't
     * loaded.
     */
    public static function decrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        if (class_exists(self::ENGINE_CRYPTO)) {
            return \TMWSEO\Engine\Services\Crypto::decrypt($value);
        }
        return $value;
    }

    /**
     * Convenience: read + decrypt a top-level option in one call.
     */
    public static function get_option(string $option, string $default = ''): string {
        return self::decrypt((string) \get_option($option, $default));
    }

    /**
     * Convenience: encrypt + write a top-level option (never autoloaded).
     */
    public static function update_option(string $option, string $value): bool {
        return \update_option($option, self::encrypt($value), false);
    }
}
