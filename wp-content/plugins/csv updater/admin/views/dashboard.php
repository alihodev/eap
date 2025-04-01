<?php
/**
 * Dashboard page for CSV Updater
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>CSV Updater Dashboard</h2>
        <p>Welcome to the CSV Updater plugin. Use the navigation to import products, manage media, or configure settings.</p>
        
        <div class="dashboard-stats">
            <h3>Quick Stats</h3>
            <ul>
                <?php
                // Retrieve import statistics
                $total_products = get_option('csv_updater_total_products', 0);
                $last_import_date = get_option('csv_updater_last_import_date', 'Never');
                $raw_images_count = count(glob(WP_CONTENT_DIR . '/uploads/raw-images/*.{jpg,jpeg,png,gif}', GLOB_BRACE));
                ?>
                <li>Total Products Imported: <strong><?php echo intval($total_products); ?></strong></li>
                <li>Last Import Date: <strong><?php echo esc_html($last_import_date); ?></strong></li>
                <li>Raw Images: <strong><?php echo intval($raw_images_count); ?></strong></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <h2>Recent Import Logs</h2>
        <?php
        // Retrieve recent log files
        $logger = new \Novano\CSVUpdater\Logger();
        $log_files = $logger->list_log_files();
        
        if (!empty($log_files)) {
            echo '<ul>';
            foreach (array_slice($log_files, 0, 5) as $log_file) {
                printf(
                    '<li><a href="#" class="view-log" data-log-path="%s">%s</a> (Size: %s, Modified: %s)</li>',
                    esc_attr($log_file['path']),
                    esc_html($log_file['filename']),
                    esc_html(size_format($log_file['size'])),
                    esc_html($log_file['modified'])
                );
            }
            echo '</ul>';
        } else {
            echo '<p>No log files found.</p>';
        }
        ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.view-log').on('click', function(e) {
        e.preventDefault();
        const logPath = $(this).data('log-path');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'csv_updater_view_log',
                log_path: logPath,
                nonce: '<?php echo wp_create_nonce('csv_updater_view_log_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Display log contents in a modal or lightbox
                    alert(response.data);
                } else {
                    alert('Failed to load log file');
                }
            }
        });
    });
});
</script>