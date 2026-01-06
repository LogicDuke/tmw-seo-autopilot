<?php
/**
 * Platform Registry - Manages supported cam platforms
 *
 * @package TMW_SEO
 */

namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

class Platform_Registry {

    /**
     * Get all supported platforms with their details
     *
     * @return array
     */
    public static function get_platforms(): array {
        return apply_filters('tmwseo_platforms', [
            'livejasmin' => [
                'name' => 'LiveJasmin',
                'slug' => 'livejasmin',
                'url_pattern' => 'https://www.livejasmin.com/en/chat/{username}',
                'affiliate_url' => 'https://ctwmsg.com/', // Your affiliate link base
                'priority' => 1, // Primary platform
                'color' => '#ff0066',
                'icon' => 'livejasmin-icon.svg',
                'is_primary' => true,
            ],
            'chaturbate' => [
                'name' => 'Chaturbate',
                'slug' => 'chaturbate',
                'url_pattern' => 'https://chaturbate.com/{username}',
                'affiliate_url' => '', // Add your affiliate URL
                'priority' => 2,
                'color' => '#f9a825',
                'icon' => 'chaturbate-icon.svg',
                'is_primary' => false,
            ],
            'stripchat' => [
                'name' => 'Stripchat',
                'slug' => 'stripchat',
                'url_pattern' => 'https://stripchat.com/{username}',
                'affiliate_url' => '',
                'priority' => 3,
                'color' => '#e91e63',
                'icon' => 'stripchat-icon.svg',
                'is_primary' => false,
            ],
            'bongacams' => [
                'name' => 'BongaCams',
                'slug' => 'bongacams',
                'url_pattern' => 'https://bongacams.com/{username}',
                'affiliate_url' => '',
                'priority' => 4,
                'color' => '#ff5722',
                'icon' => 'bongacams-icon.svg',
                'is_primary' => false,
            ],
            'camsoda' => [
                'name' => 'CamSoda',
                'slug' => 'camsoda',
                'url_pattern' => 'https://www.camsoda.com/{username}',
                'affiliate_url' => '',
                'priority' => 5,
                'color' => '#9c27b0',
                'icon' => 'camsoda-icon.svg',
                'is_primary' => false,
            ],
            'myfreecams' => [
                'name' => 'MyFreeCams',
                'slug' => 'myfreecams',
                'url_pattern' => 'https://www.myfreecams.com/#{username}',
                'affiliate_url' => '',
                'priority' => 6,
                'color' => '#2196f3',
                'icon' => 'myfreecams-icon.svg',
                'is_primary' => false,
            ],
            'flirt4free' => [
                'name' => 'Flirt4Free',
                'slug' => 'flirt4free',
                'url_pattern' => 'https://www.flirt4free.com/live/girls/{username}',
                'affiliate_url' => '',
                'priority' => 7,
                'color' => '#00bcd4',
                'icon' => 'flirt4free-icon.svg',
                'is_primary' => false,
            ],
            'cam4' => [
                'name' => 'CAM4',
                'slug' => 'cam4',
                'url_pattern' => 'https://www.cam4.com/{username}',
                'affiliate_url' => '',
                'priority' => 8,
                'color' => '#4caf50',
                'icon' => 'cam4-icon.svg',
                'is_primary' => false,
            ],
            'onlyfans' => [
                'name' => 'OnlyFans',
                'slug' => 'onlyfans',
                'url_pattern' => 'https://onlyfans.com/{username}',
                'affiliate_url' => '',
                'priority' => 9,
                'color' => '#00aeef',
                'icon' => 'onlyfans-icon.svg',
                'is_primary' => false,
                'is_subscription' => true, // Different model - subscription based
            ],
            'fansly' => [
                'name' => 'Fansly',
                'slug' => 'fansly',
                'url_pattern' => 'https://fansly.com/{username}',
                'affiliate_url' => '',
                'priority' => 10,
                'color' => '#1da1f2',
                'icon' => 'fansly-icon.svg',
                'is_primary' => false,
                'is_subscription' => true,
            ],
        ]);
    }

    /**
     * Get platform by slug
     *
     * @param string $slug Platform slug.
     * @return array|null
     */
    public static function get_platform(string $slug): ?array {
        $platforms = self::get_platforms();
        return $platforms[$slug] ?? null;
    }

    /**
     * Get primary platform
     *
     * @return array
     */
    public static function get_primary_platform(): array {
        $platforms = self::get_platforms();
        foreach ($platforms as $platform) {
            if (!empty($platform['is_primary'])) {
                return $platform;
            }
        }

        return $platforms['livejasmin'];
    }

    /**
     * Get platforms as checkbox options for admin
     *
     * @return array
     */
    public static function get_checkbox_options(): array {
        $platforms = self::get_platforms();
        $options = [];
        foreach ($platforms as $slug => $platform) {
            $options[$slug] = $platform['name'];
        }

        return $options;
    }

    /**
     * Get platform slugs only
     *
     * @return array
     */
    public static function get_platform_slugs(): array {
        return array_keys(self::get_platforms());
    }
}
