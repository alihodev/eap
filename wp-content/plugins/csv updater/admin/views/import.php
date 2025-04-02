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
                        <p class="description">Select the CSV file to import products. Max file size: 100MB</p>
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
        <h2>Debug Information</h2>
        <div id="import-debug">
            <!-- Debugging information -->
        </div>
    </div>
</div>

<style>
#import-status, #import-debug {
    margin-bottom: 15px;
    padding: 10px;
    background-color: #f4f4f4;
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
        $('#import-debug').html('');
        
        // Create FormData object
        var formData = new FormData(this);
        
        // Debug: Log form data
        for (var pair of formData.entries()) {
            $('#import-debug').append('<p>Form Data: ' + pair[0] + ' - ' + pair[1] + '</p>');
        }
        
        // Show loading indicator
        $('#import-status').html('<p>Uploading and processing CSV...</p>');
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                        $('#import-debug').append('<p>Upload Progress: ' + Math.round(percentComplete * 100) + '%</p>');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#import-debug').append('<p>Raw Response: ' + JSON.stringify(response) + '</p>');
                
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
            error: function(xhr, status, error) {
                $('#import-status').html(
                    '<p class="import-error">Failed to start import.</p>'
                );
                $('#import-debug').append(
                    '<p>Error Details:</p>' +
                    '<p>Status: ' + status + '</p>' +
                    '<p>Error: ' + error + '</p>' +
                    '<p>Response Text: ' + xhr.responseText + '</p>'
                );
            }
        });
    });

    function checkImportStatus(importId) {
        // Similar to previous implementation
        // ... (keep the existing checkImportStatus function)
    }
});
</script>