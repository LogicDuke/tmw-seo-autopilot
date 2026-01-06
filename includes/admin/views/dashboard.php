<?php
if (!defined('ABSPATH')) exit;
use TMW_SEO\Dashboard_Stats;
$stats = Dashboard_Stats::build();
$recent = Dashboard_Stats::get_recent_activity();
$opportunities = Dashboard_Stats::get_top_opportunities(5);
?>
<div class="wrap">
    <h1><?php esc_html_e('TMW SEO Dashboard', 'tmwseo'); ?></h1>
    <div class="tmwseo-cards" style="display:flex;gap:16px;flex-wrap:wrap;">
        <div class="card" style="flex:1 1 200px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
            <h2><?php esc_html_e('Total Keywords', 'tmwseo'); ?></h2>
            <p><strong><?php echo esc_html(number_format($stats['total_keywords'] ?? 0)); ?></strong></p>
        </div>
        <div class="card" style="flex:1 1 200px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
            <h2><?php esc_html_e('Average KD', 'tmwseo'); ?></h2>
            <p><strong><?php echo esc_html($stats['average_kd'] ?? 0); ?>%</strong></p>
        </div>
        <div class="card" style="flex:1 1 200px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
            <h2><?php esc_html_e('Total Search Volume', 'tmwseo'); ?></h2>
            <p><strong><?php echo esc_html(number_format($stats['total_volume'] ?? 0)); ?></strong></p>
        </div>
        <div class="card" style="flex:1 1 200px;background:#fff;padding:16px;border:1px solid #ccd0d4;">
            <h2><?php esc_html_e('API Credits', 'tmwseo'); ?></h2>
            <p><strong><?php echo esc_html($stats['api_credits'] ?? __('N/A', 'tmwseo')); ?></strong></p>
        </div>
    </div>

    <h2><?php esc_html_e('Top Opportunities', 'tmwseo'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th><?php esc_html_e('Keyword', 'tmwseo'); ?></th><th><?php esc_html_e('Volume', 'tmwseo'); ?></th><th><?php esc_html_e('KD%', 'tmwseo'); ?></th><th><?php esc_html_e('Score', 'tmwseo'); ?></th></tr></thead>
        <tbody>
            <?php foreach ($opportunities as $row): ?>
            <tr>
                <td><?php echo esc_html($row['keyword'] ?? ''); ?></td>
                <td><?php echo esc_html($row['search_volume'] ?? ''); ?></td>
                <td><?php echo esc_html($row['tmw_kd'] ?? ''); ?></td>
                <td><?php echo esc_html($row['opportunity'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><?php esc_html_e('Recent Activity', 'tmwseo'); ?></h2>
    <ul>
        <?php foreach ($recent as $item): ?>
            <li><?php echo esc_html($item['action'] ?? ''); ?> — <?php echo esc_html($item['timestamp'] ?? ''); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
