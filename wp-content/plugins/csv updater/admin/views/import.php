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
        <form id="csv-import-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('csv_import_action', 'csv_import_nonce'); ?>
            <input type="hidden" name="action" value="csv_updater_import">
            
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
        <h2>Import Status</h2>
        <div id="import-status">
            <!-- Import status will be displayed here -->
        </div>
    </div>

    <div class="card">
        <h2>Import Log</h2>
        <div id="import-log">
            <!-- Import log will be displayed here -->
        </div>
    </div>
</div>

<style>
#import-status {
    margin-bottom: 15px;
}
#import-log {
    max-height: 300px;
    overflow-y: auto;
    background-color: #f4f4f4;
    padding: 10px;
    border: 1px solid #ddd;
}
.import-error {
    color: red;
}
.import-success {
    color: green;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#csv-import-form').on('submit', function(e) {
        e.preventDefault();
        
        // Clear previous status and log
        $('#import-status').html('');
        $('#import-log').html('');
        
        // Create FormData object
        var formData = new FormData(this);
        
        // Show loading indicator
        $('#import-status').html('<p>Uploading and processing CSV...</p>');
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#import-status').html(
                        '<p class="import-success">' + 
                        response.data.message + 
                        '</p>'
                    );
                    
                    // Periodically check import status
                    checkImportStatus(response.data.import_id);
                } else {
                    $('#import-status').html(
                        '<p class="import-error">' + 
                        (response.data ? response.data.message : 'Unknown error occurred') + 
                        '</p>'
                    );
                }
            },
            error: function() {
                $('#import-status').html(
                    '<p class="import-error">Failed to start import. Please try again.</p>'
                );
            }
        });
    });

    function checkImportStatus(importId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'csv_updater_import_status',
                import_id: importId,
                nonce: '<?php echo wp_create_nonce('csv_updater_import_status_nonce'); ?>'
            },
            success: function(response) { 
                if (response.success) {
                    // Update status
                    $('#import-status').html(
                        '<p class="import-' + 
                        (response.data.status === 'completed' ? 'success' : 'progress') + 
                        '">' + response.data.message + '</p>'
                    );

                    // Update log
                    if (response.data.log) {
                        $('#import-log').html(response.data.log);
                    }

                    // Continue checking if not completed
                    if (response.data.status !== 'completed') {
                        setTimeout(function() {
                            checkImportStatus(importId);
                        }, 5000); // Check every 5 seconds
                    }
                } else {
                    $('#import-status').html(
                        '<p class="import-error">' + 
                        (response.data ? response.data.message : 'Failed to check import status') + 
                        '</p>'
                    );
                }
            },
            error: function() {
                $('#import-status').html(
                    '<p class="import-error">Failed to check import status. Please refresh the page.</p>'
                );
            }
        });
    }
});
</script>