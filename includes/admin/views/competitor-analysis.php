<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('tmwseo_competitor_analysis');
?>
<div class="wrap">
    <h1><?php esc_html_e('Competitor Analysis', 'tmwseo'); ?></h1>
    <form id="tmwseo-competitor-form">
        <input type="hidden" name="action" value="tmwseo_analyze_competitor" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Competitor domain', 'tmwseo'); ?></th>
                <td><input type="text" name="domain" placeholder="stripchat.com" class="regular-text" /></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Max keywords', 'tmwseo'); ?></th>
                <td><input type="number" name="limit" value="500" min="1" max="1000" /></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Adult only', 'tmwseo'); ?></th>
                <td><label><input type="checkbox" name="adult" value="1" checked /> <?php esc_html_e('Filter adult-intent keywords', 'tmwseo'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Auto-categorize', 'tmwseo'); ?></th>
                <td><label><input type="checkbox" name="categorize" value="1" checked /> <?php esc_html_e('Attempt to match existing categories', 'tmwseo'); ?></label></td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" id="tmwseo-analyze-btn"><?php esc_html_e('Analyze Competitor', 'tmwseo'); ?></button></p>
    </form>
    <pre id="tmwseo-competitor-log" style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:320px;overflow:auto;"></pre>
</div>
<script>
(function($){
    $('#tmwseo-analyze-btn').on('click', function(){
        var data = $('#tmwseo-competitor-form').serialize();
        var $log = $('#tmwseo-competitor-log');
        $log.text('<?php echo esc_js(__('Fetching keywords…', 'tmwseo')); ?>');
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
