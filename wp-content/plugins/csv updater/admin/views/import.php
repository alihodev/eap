<?php
/**
 * Product Import page for CSV Updater
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>Import Products</h2>
        <form id="csv-import-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('csv_import_action', 'csv_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file">CSV File</label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv">
                        <p class="description">Select the CSV file to import products.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Start Import', 'primary', 'start_import'); ?>
        </form>
    </div>

    <div class="card">
        <h2>Import Log</h2>
        <div id="import-log">
            <!-- Import log will be displayed here -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#csv-import-form').on('submit', function(e) {
        e.preventDefault();
        // AJAX import handling will be implemented later
        console.log('Import form submitted');
    });
});
</script>