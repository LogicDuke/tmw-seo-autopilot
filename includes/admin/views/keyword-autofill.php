<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('tmwseo_autofill_keywords');
$categories = \TMW_SEO\Keyword_Library::categories();
?>
<div class="wrap">
    <h1><?php esc_html_e('Keyword Autofill', 'tmwseo'); ?></h1>
    <p><?php esc_html_e('Automatically populate keyword CSVs using curated seeds and Google Suggest.', 'tmwseo'); ?></p>
    <form id="tmwseo-autofill-form">
        <input type="hidden" name="action" value="tmwseo_autofill_keywords" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Category', 'tmwseo'); ?></th>
                <td>
                    <select name="category">
                        <option value="all"><?php esc_html_e('All Categories', 'tmwseo'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Keyword Type', 'tmwseo'); ?></th>
                <td>
                    <select name="type">
                        <option value="all"><?php esc_html_e('All', 'tmwseo'); ?></option>
                        <option value="longtail"><?php esc_html_e('Longtail', 'tmwseo'); ?></option>
                        <option value="competitor"><?php esc_html_e('Competitor', 'tmwseo'); ?></option>
                        <option value="extra"><?php esc_html_e('Extra', 'tmwseo'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Keywords per category', 'tmwseo'); ?></th>
                <td><input type="number" name="limit" value="200" max="1000" min="1" /></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Use DataForSEO enrichment', 'tmwseo'); ?></th>
                <td><label><input type="checkbox" name="enrich" value="1" checked /> <?php esc_html_e('Enrich metrics where possible', 'tmwseo'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Dry run', 'tmwseo'); ?></th>
                <td><label><input type="checkbox" name="dry_run" value="1" /> <?php esc_html_e('Preview only, do not save', 'tmwseo'); ?></label></td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" id="tmwseo-start-autofill"><?php esc_html_e('Start Autofill', 'tmwseo'); ?></button></p>
    </form>
    <div id="tmwseo-autofill-progress" class="notice notice-info" style="display:none;"></div>
    <pre id="tmwseo-autofill-log" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:320px;overflow:auto;"></pre>
</div>
<script>
(function($){
    $('#tmwseo-start-autofill').on('click', function(){
        var $log = $('#tmwseo-autofill-log');
        var data = $('#tmwseo-autofill-form').serialize();
        $log.text('<?php echo esc_js(__('Starting…', 'tmwseo')); ?>');
        $.post(ajaxurl, data, function(resp){
            if (resp && resp.success) {
                $log.text(JSON.stringify(resp.data, null, 2));
            } else {
                $log.text(resp && resp.data && resp.data.message ? resp.data.message : 'Error');
            }
        });
    });
})(jQuery);
</script>
