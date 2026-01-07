<?php
if (!defined('ABSPATH')) exit;

use TMW_SEO\Keyword_Browser;
use TMW_SEO\Keyword_Library;

$categories = array_merge(['all' => __('All', 'tmw-seo-autopilot')], array_combine(Keyword_Library::categories(), Keyword_Library::categories()));
$types      = [
    'all'        => __('All', 'tmw-seo-autopilot'),
    'extra'      => 'extra',
    'longtail'   => 'longtail',
    'competitor' => 'competitor',
];
$kd_ranges = [
    'all'       => __('All', 'tmw-seo-autopilot'),
    'very_easy' => __('Very Easy (0-20)', 'tmw-seo-autopilot'),
    'easy'      => __('Easy (21-30)', 'tmw-seo-autopilot'),
    'medium'    => __('Medium (31-50)', 'tmw-seo-autopilot'),
    'hard'      => __('Hard (51-70)', 'tmw-seo-autopilot'),
    'very_hard' => __('Very Hard (71+)', 'tmw-seo-autopilot'),
    'unscored'  => __('Unscored', 'tmw-seo-autopilot'),
];

$selected_category = sanitize_key($_GET['category'] ?? 'all');
$selected_type     = sanitize_key($_GET['type'] ?? 'all');
$selected_kd       = sanitize_key($_GET['kd_range'] ?? 'all');
$search            = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$sort_by           = sanitize_key($_GET['sort'] ?? 'keyword');
$sort_order        = sanitize_key($_GET['order'] ?? 'asc');
$page              = max(1, (int) ($_GET['paged'] ?? 1));
$per_page          = min(500, max(10, (int) ($_GET['per_page'] ?? 50)));
$offset            = ($page - 1) * $per_page;

$allowed_params = ['page', 'category', 'type', 'kd_range', 's', 'per_page', 'sort', 'order', 'paged'];
$query_args     = array_intersect_key(
    [
        'page'     => 'tmw-seo-keyword-browser',
        'category' => $selected_category,
        'type'     => $selected_type,
        'kd_range' => $selected_kd,
        's'        => $search,
        'per_page' => $per_page,
        'sort'     => $sort_by,
        'order'    => $sort_order,
        'paged'    => $page,
    ],
    array_flip($allowed_params)
);

$filters = [
    'category' => $selected_category,
    'type'     => $selected_type,
    'kd_range' => $selected_kd,
    'search'   => $search,
];

$total_keywords = Keyword_Browser::get_total_count($filters);
$rows           = Keyword_Browser::get_keywords($filters, $per_page, $offset, $sort_by, $sort_order);
$total_pages    = max(1, (int) ceil($total_keywords / $per_page));
$nonce          = wp_create_nonce('tmwseo_keyword_browser');
?>
<div class="wrap tmwseo-keyword-browser">
    <h1><?php esc_html_e('Keyword Browser', 'tmw-seo-autopilot'); ?></h1>
    <form method="get" class="tmwseo-keyword-browser__filters">
        <input type="hidden" name="page" value="tmw-seo-keyword-browser" />
        <label>
            <span><?php esc_html_e('Category', 'tmw-seo-autopilot'); ?></span>
            <select name="category">
                <?php foreach ($categories as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_category, $key); ?>><?php echo esc_html(ucfirst($label)); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('Type', 'tmw-seo-autopilot'); ?></span>
            <select name="type">
                <?php foreach ($types as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_type, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('KD Range', 'tmw-seo-autopilot'); ?></span>
            <select name="kd_range">
                <?php foreach ($kd_ranges as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_kd, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="tmwseo-keyword-browser__search">
            <span class="screen-reader-text"><?php esc_html_e('Search keywords', 'tmw-seo-autopilot'); ?></span>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search keywords', 'tmw-seo-autopilot'); ?>" />
        </label>
        <label>
            <span><?php esc_html_e('Per page', 'tmw-seo-autopilot'); ?></span>
            <input type="number" name="per_page" value="<?php echo (int) $per_page; ?>" min="10" max="500" />
        </label>
        <button class="button button-primary" type="submit"><?php esc_html_e('Apply Filters', 'tmw-seo-autopilot'); ?></button>
        <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($query_args, ['action' => 'tmwseo_export_keywords']), admin_url('admin-ajax.php')), 'tmwseo_keyword_browser', 'nonce')); ?>"><?php esc_html_e('Export Filtered CSV', 'tmw-seo-autopilot'); ?></a>
    </form>

    <p class="tmwseo-keyword-browser__counts">
        <?php
        printf(
            /* translators: 1: visible count, 2: total count */
            esc_html__('Showing %1$s of %2$s keywords', 'tmw-seo-autopilot'),
            number_format_i18n(count($rows)),
            number_format_i18n($total_keywords)
        );
        ?>
    </p>

    <table class="widefat fixed striped tmwseo-keyword-browser__table">
        <thead>
            <tr>
                <th><?php esc_html_e('Keyword', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('Category', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('Type', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('KD%', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('Volume', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('CPC', 'tmw-seo-autopilot'); ?></th>
                <th><?php esc_html_e('Actions', 'tmw-seo-autopilot'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr><td colspan="7"><?php esc_html_e('No keywords found for this filter.', 'tmw-seo-autopilot'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['keyword'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['category'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['type'] ?? ''); ?></td>
                        <td><?php echo $row['tmw_kd'] !== null && $row['tmw_kd'] !== '' ? (int) $row['tmw_kd'] : '—'; ?></td>
                        <td><?php echo isset($row['search_volume']) && $row['search_volume'] !== null ? number_format_i18n((int) $row['search_volume']) : '—'; ?></td>
                        <td><?php echo isset($row['cpc']) && $row['cpc'] !== '' ? esc_html(number_format((float) $row['cpc'], 2)) : '—'; ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this keyword from the CSV?', 'tmw-seo-autopilot')); ?>');">
                                <?php wp_nonce_field('tmwseo_delete_keyword', 'tmwseo_delete_keyword_nonce'); ?>
                                <input type="hidden" name="action" value="tmwseo_delete_keyword" />
                                <input type="hidden" name="kw" value="<?php echo esc_attr($row['keyword'] ?? ''); ?>" />
                                <input type="hidden" name="category" value="<?php echo esc_attr($row['category'] ?? ''); ?>" />
                                <input type="hidden" name="type" value="<?php echo esc_attr($row['type'] ?? ''); ?>" />
                                <button class="button-link-delete" type="submit"><?php esc_html_e('Delete', 'tmw-seo-autopilot'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg(array_merge($query_args, ['paged' => '%#%'])),
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $total_pages,
                    'prev_text' => __('« Previous', 'tmw-seo-autopilot'),
                    'next_text' => __('Next »', 'tmw-seo-autopilot'),
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
