<?php
/**
 * Product Import page for CSV Updater
 * 
 * File: wp-content/plugins/csv-updater/admin/views/import.php
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get current user
$current_user = wp_get_current_user();

// Check if user has appropriate capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('Sorry, you do not have sufficient permissions to access this page.', 'csv-updater'));
}

// Get recent import logs
$logger = new \Novano\CSVUpdater\Logger();
$recent_logs = $logger->list_log_files();

// Check for any existing raw image files
$raw_images_dir = WP_CONTENT_DIR . '/uploads/raw-images';
$raw_images = glob($raw_images_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
$raw_images_count = count($raw_images);

// Get existing import mapping with fallback
try {
    $csv_mapper = new \Novano\CSVUpdater\CSV_Mapper($logger);
    $current_mapping = $csv_mapper->get_mapping();
} catch (\Exception $e) {
    $current_mapping = []; // Fallback to empty mapping
    $logger->error('Failed to retrieve CSV mapping', [
        'error' => $e->getMessage()
    ]);
}

// Sample columns based on known CSV structure
$sample_columns = [
    'code', 'description', 'description_2', 'p_mark', 'qty', 'kg', 
    'vat', 'size', 'unit1', 'unit2', 'item_type', 'shelf_life', 
    'country', 'cat_code', 'commodity_code'
];

// Available WooCommerce fields
$wc_fields = [
    'name' => 'Product Name',
    'description' => 'Product Description',
    'sku' => 'SKU',
    'regular_price' => 'Regular Price',
    'stock_quantity' => 'Stock Quantity',
    'weight' => 'Weight',
    'tax_status' => 'Tax Status',
    'attributes' => 'Attributes'
];
?>

<div class="wrap csv-updater-import-page">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="card">
        <h2><?php _e('Column Mapping', 'csv-updater'); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field('csv_updater_mapping_nonce'); ?>
            
            <table class="form-table">
                <?php foreach ($wc_fields as $field_key => $field_label): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($field_label); ?></th>
                        <td>
                            <select name="csv_mapping[<?php echo esc_attr($field_key); ?>]">
                                <option value="">-- Select CSV Column --</option>
                                <?php foreach ($sample_columns as $column): ?>
                                    <option value="<?php echo esc_attr($column); ?>"
                                        <?php 
                                        // Check if this column is currently mapped
                                        $mapped_value = isset($current_mapping[$field_key]) 
                                            ? (is_array($current_mapping[$field_key]) 
                                                ? ($current_mapping[$field_key]['column'] ?? '') 
                                                : $current_mapping[$field_key]) 
                                            : '';
                                        selected($mapped_value, $column);
                                        ?>>
                                        <?php echo esc_html($column); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($field_key === 'sku'): ?>
                                <input type="text" 
                                       name="csv_mapping[<?php echo esc_attr($field_key); ?>][prefix]" 
                                       placeholder="Prefix (e.g., EAP)"
                                       value="<?php 
                                       echo isset($current_mapping[$field_key]['prefix']) 
                                           ? esc_attr($current_mapping[$field_key]['prefix']) 
                                           : 'EAP'; 
                                       ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button(__('Save Mapping', 'csv-updater'), 'primary', 'save_mapping'); ?>
        </form>
    </div>

    <div class="card">
        <h2><?php _e('Import Products', 'csv-updater'); ?></h2>
        
        <form id="csv-import-form" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('csv_import_action', 'csv_import_nonce'); ?>
            <input type="hidden" name="action" value="csv_updater_import">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php _e('CSV File', 'csv-updater'); ?></label>
                    </th>
                    <td>
                        <input type="file" 
                               name="csv_file" 
                               id="csv_file" 
                               accept=".csv"
                               class="regular-text"
                        >
                        <p class="description">
                            <?php _e('Select the CSV file to import products. Supported formats: .csv', 'csv-updater'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Import Options', 'csv-updater'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="update_existing" value="1">
                            <?php _e('Update existing products', 'csv-updater'); ?>
                        </label>
                        <p class="description">
                            <?php _e('If checked, products with matching SKU will be updated instead of skipped.', 'csv-updater'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Start Import', 'csv-updater'), 'primary', 'start_import'); ?>
        </form>
    </div>

    <div class="card">
        <h2><?php _e('Import Status', 'csv-updater'); ?></h2>
        <div id="import-status-container">
            <div id="import-status" class="import-status-placeholder">
                <?php _e('No import in progress', 'csv-updater'); ?>
            </div>
            <div id="import-progress" class="import-progress" style="display:none;">
                <div class="progress-bar">
                    <div class="progress-bar-inner"></div>
                </div>
                <span class="progress-percentage">0%</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2><?php _e('Import Log', 'csv-updater'); ?></h2>
        <div id="import-log" class="import-log">
            <?php _e('Import log will appear here', 'csv-updater'); ?>
        </div>
    </div>

    <div class="card">
        <h2><?php _e('Recent Import Logs', 'csv-updater'); ?></h2>
        <div class="recent-logs">
            <?php if (!empty($recent_logs)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Filename', 'csv-updater'); ?></th>
                            <th><?php _e('Size', 'csv-updater'); ?></th>
                            <th><?php _e('Date', 'csv-updater'); ?></th>
                            <th><?php _e('Actions', 'csv-updater'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_logs, 0, 5) as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['filename']); ?></td>
                                <td><?php echo esc_html(size_format($log['size'])); ?></td>
                                <td><?php echo esc_html($log['modified']); ?></td>
                                <td>
                                    <button class="button button-small view-log" 
                                            data-log-path="<?php echo esc_attr($log['path']); ?>">
                                        <?php _e('View', 'csv-updater'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No recent log files found.', 'csv-updater'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2><?php _e('Raw Images', 'csv-updater'); ?></h2>
        <div class="raw-images-summary">
            <p>
                <?php 
                printf(
                    __('You have %d raw image(s) in the upload directory.', 'csv-updater'), 
                    $raw_images_count
                ); 
                ?>
            </p>
            <?php if ($raw_images_count > 0): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=csv-updater-media')); ?>" class="button">
                    <?php _e('Manage Images', 'csv-updater'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.import-status-placeholder {
    background-color: #f8f9fa;
    padding: 15px;
    border: 1px solid #e9ecef;
    color: #6c757d;
}

.import-progress {
    margin-top: 10px;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-inner {
    width: 0;
    height: 100%;
    background-color: #28a745;
    transition: width 0.5s ease;
}

.progress-percentage {
    display: block;
    margin-top: 5px;
    text-align: right;
    font-weight: bold;
}

.import-log {
    max-height: 300px;
    overflow-y: auto;
    background-color: #f4f4f4;
    padding: 10px;
    font-family: monospace;
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Import form submission
    $('#csv-import-form').on('submit', function(e) {
        e.preventDefault();
        
        // Clear previous status and log
        $('#import-status').html('<?php _e('Starting import...', 'csv-updater'); ?>');
        $('#import-log').html('');
        $('#import-progress').show();
        
        // Create FormData object
        var formData = new FormData(this);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Start checking import status
                    checkImportStatus(response.data.import_id);
                } else {
                    // Handle import failure
                    $('#import-status')
                        .html(response.data.message || '<?php _e('Unknown error occurred', 'csv-updater'); ?>')
                        .addClass('error');
                }
            },
            error: function() {
                $('#import-status')
                    .html('<?php _e('Failed to start import. Please try again.', 'csv-updater'); ?>')
                    .addClass('error');
            }
        });
    });

    // Import status checking function
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
                    const data = response.data;
                    
                    // Update status display
                    $('#import-status').html(data.message);
                    
                    // Update progress bar
                    $('.progress-bar-inner').css('width', data.progress + '%');
                    $('.progress-percentage').text(data.progress + '%');
                    
                    // Update log
                    $('#import-log').html(data.log);
                    
                    // Continue checking if not completed
                    if (data.status === 'in_progress') {
                        setTimeout(function() {
                            checkImportStatus(importId);
                        }, 5000); // Check every 5 seconds
                    } else if (data.status === 'completed') {
                        // Final success state
                        $('#import-status')
                            .html('<?php _e('Import Completed Successfully', 'csv-updater'); ?>')
                            .addClass('success');
                        $('.progress-bar-inner').css('width', '100%');
                        $('.progress-percentage').text('100%');
                    } else {
                        // Handle failure
                        $('#import-status')
                            .html('<?php _e('Import Failed', 'csv-updater'); ?>: ' + data.message)
                            .addClass('error');
                    }
                } else {
                    // Handle AJAX error
                    $('#import-status')
                        .html('<?php _e('Failed to check import status', 'csv-updater'); ?>')
                        .addClass('error');
                }
            },
            error: function() {
                // Network or server error
                $('#import-status')
                    .html('<?php _e('Connection error. Retrying...', 'csv-updater'); ?>')
                    .addClass('error');
                
                // Retry after a delay
                setTimeout(function() {
                    checkImportStatus(importId);
                }, 10000); // 10 seconds
            }
        });
    }

    // View log functionality
    $('.view-log').on('click', function() {
        const logPath = $(this).data('log-path');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'csv_updater_view_log',
                log_path: logPath,
                nonce: '<?php echo wp_create_nonce('csv_upupdater_view_log_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Open log in a modal or new window
                    var logWindow = window.open('', 'Import Log', 'width=800,height=600');
                    logWindow.document.write('<pre>' + response.data + '</pre>');
                } else {
                    alert('<?php _e('Failed to load log file', 'csv-updater'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error loading log file', 'csv-updater'); ?>');
            }
        });
    });

    // Mapping form submission
    $('form[method="post"]').on('submit', function(e) {
        // Optional: Add client-side validation for mapping
        var isValid = true;
        
        $(this).find('select').each(function() {
            if ($(this).val() === '' && $(this).attr('name').includes('csv_mapping')) {
                alert('<?php _e('Please select a column for all mappings', 'csv-updater'); ?>');
                isValid = false;
                return false;
            }
        });

        return isValid;
    });
});
</script>