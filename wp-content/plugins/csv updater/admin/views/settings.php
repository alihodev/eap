<?php
/**
 * Settings page for CSV Updater
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('csv_updater_options', [
    'log_retention_days' => 30,
    'max_products_per_batch' => 500,
    'image_width' => 400,
    'image_height' => 400,
]);
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('csv_updater_settings_group');
        do_settings_sections('csv_updater_settings_group');
        ?>
        
        <div class="card">
            <h2>Import Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="max_products_per_batch">Max Products per Batch</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="max_products_per_batch" 
                            name="csv_updater_options[max_products_per_batch]" 
                            value="<?php echo esc_attr($options['max_products_per_batch']); ?>" 
                            min="100" 
                            max="1000"
                        >
                        <p class="description">Number of products to process in a single batch (100-1000).</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Image Processing</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="image_width">Image Width</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="image_width" 
                            name="csv_updater_options[image_width]" 
                            value="<?php echo esc_attr($options['image_width']); ?>" 
                            min="100" 
                            max="2000"
                        >
                        <p class="description">Maximum width for imported product images.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="image_height">Image Height</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="image_height" 
                            name="csv_updater_options[image_height]" 
                            value="<?php echo esc_attr($options['image_height']); ?>" 
                            min="100" 
                            max="2000"
                        >
                        <p class="description">Maximum height for imported product images.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Logging</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="log_retention_days">Log Retention</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="log_retention_days" 
                            name="csv_updater_options[log_retention_days]" 
                            value="<?php echo esc_attr($options['log_retention_days']); ?>" 
                            min="1" 
                            max="365"
                        >
                        <p class="description">Number of days to retain log files (1-365).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Purge Logs</th>
                    <td>
                        <button type="button" id="purge-logs" class="button">Purge Log Files</button>
                        <p class="description">Manually delete all existing log files.</p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button('Save Settings', 'primary', 'submit', true); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#purge-logs').on('click', function() {
        if (confirm('Are you sure you want to delete all log files?')) {
            // AJAX call to purge logs will be implemented later
            console.log('Purge logs requested');
        }
    });
});
</script>