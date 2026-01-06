<?php
/**
 * Shared admin UI shell for plugin pages.
 *
 * @package TMW_SEO
 */
namespace TMW_SEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin UI shell helpers.
 *
 * @package TMW_SEO
 */
class Admin_UI {
    /**
     * Renders the admin header and nav tabs.
     *
     * @param string $active_slug Active page slug.
     * @param string $title Page title.
     * @param string $subtitle Optional subtitle.
     * @return void
     */
    public static function render_header(string $active_slug, string $title, string $subtitle = ''): void {
        $tabs = self::tabs();
        ?>
        <div class="wrap tmwseo-admin">
            <div class="tmwseo-admin-hero">
                <div class="tmwseo-admin-brand">
                    <div class="tmwseo-admin-brand-title"><?php echo esc_html__('TMW SEO Autopilot', 'tmw-seo-autopilot'); ?></div>
                    <div class="tmwseo-admin-brand-subtitle"><?php echo esc_html__('Automate RankMath content + keyword workflows.', 'tmw-seo-autopilot'); ?></div>
                </div>
                <div class="tmwseo-admin-page">
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php if ($subtitle !== '') : ?>
                        <p class="description"><?php echo esc_html($subtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <h2 class="nav-tab-wrapper tmwseo-admin-tabs">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <?php
                    $url = admin_url('admin.php?page=' . $slug);
                    $active_class = $slug === $active_slug ? ' nav-tab-active' : '';
                    ?>
                    <a class="nav-tab<?php echo esc_attr($active_class); ?>" href="<?php echo esc_url($url); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
        <?php
    }

    /**
     * Closes the admin wrapper opened by render_header.
     *
     * @return void
     */
    public static function render_footer(): void {
        echo '</div>';
    }

    /**
     * Enqueues admin UI assets.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public static function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'tmw-seo-autopilot') === false) {
            return;
        }

        wp_enqueue_style('tmw-seo-admin-ui', TMW_SEO_URL . 'assets/admin-ui.css', [], '1.0.0');
    }

    /**
     * Returns the admin tab configuration.
     *
     * @return array
     */
    protected static function tabs(): array {
        return [
            'tmw-seo-autopilot'       => __('Dashboard', 'tmw-seo-autopilot'),
            'tmw-seo-keyword-packs'   => __('Keyword Packs', 'tmw-seo-autopilot'),
            'tmw-seo-keyword-usage'   => __('Keyword Usage', 'tmw-seo-autopilot'),
            'tmw-seo-usage'           => __('Usage / CSV Stats', 'tmw-seo-autopilot'),
            'tmw-seo-scheduled-actions' => __('Automations', 'tmw-seo-autopilot'),
            'tmw-seo-codex-reports'   => __('Codex Reports', 'tmw-seo-autopilot'),
            'tmw-seo-settings'        => __('Settings', 'tmw-seo-autopilot'),
            'tmw-seo-integrations'    => __('Integrations', 'tmw-seo-autopilot'),
        ];
    }
}
