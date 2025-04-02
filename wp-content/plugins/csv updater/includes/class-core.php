<?php

namespace Novano\CSVUpdater;

/**
 * Core plugin class for CSV Updater
 */
class Core
{
    /**
     * Singleton instance
     *
     * @var Core
     */
    private static $instance = null;

    /**
     * CSV Importer instance
     *
     * @var CSV_Importer
     */
    private $csv_importer;

    /**
     * CSV Mapper instance
     *
     * @var CSV_Mapper
     */
    private $csv_mapper;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        // Initialize core components
        $this->logger = new Logger();
        $this->csv_importer = new CSV_Importer();
        $this->csv_mapper = new CSV_Mapper($this->logger);

        // Load plugin options
        $this->options = get_option('csv_updater_options', [
            'log_retention_days' => 30,
            'max_products_per_batch' => 500,
            'image_width' => 400,
            'image_height' => 400,
            'batch_size' => 100
        ]);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin
     *
     * @return self
     */
    public function init()
    {
        // Additional initialization logic
        $this->register_activation_hooks();
        return $this;
    }

    /**
     * Setup additional plugin features
     *
     * @return self
     */
    public function setup()
    {
        // Additional setup logic
        $this->register_background_processes();
        return $this;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Admin-specific hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'create_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'add_manual_import_trigger_menu']);


            // AJAX handlers
            add_action('wp_ajax_csv_updater_import', [$this, 'handle_csv_import']);
            add_action('wp_ajax_csv_updater_import_status', [$this, 'check_import_status']);
            add_action('wp_ajax_csv_updater_view_log', [$this, 'view_log_ajax']);
            add_action('wp_ajax_csv_updater_purge_logs', [$this, 'ajax_purge_logs']);
            add_action('wp_ajax_csv_updater_upload_raw_images', [$this, 'ajax_upload_raw_images']);
            add_action('wp_ajax_csv_updater_delete_raw_image', [$this, 'ajax_delete_raw_image']);
        }
    }

    /**
     * Register activation and deactivation hooks
     */
    private function register_activation_hooks()
    {
        // Create necessary directories
        wp_mkdir_p(CSV_UPDATER_PATH . 'logs');
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/raw-images');
        wp_mkdir_p(WP_CONTENT_DIR . '/uploads/imported-products');

        // Schedule cleanup events
        if (!wp_next_scheduled('csv_updater_daily_logging_cleanup')) {
            wp_schedule_event(time(), 'daily', 'csv_updater_daily_logging_cleanup');
        }
    }

    /**
     * Register background processing hooks
     */
    private function register_background_processes()
    {
        // Hook for background import
        add_action('csv_updater_background_import', [$this, 'background_import_handler'], 10, 2);
    }

    /**
     * Create admin menu
     */
    public function create_admin_menu()
    {
        // Main menu page
        add_menu_page(
            __('CSV Updater', 'csv-updater'),
            __('CSV Updater', 'csv-updater'),
            'manage_options',
            'csv-updater',
            [$this, 'render_main_page'],
            'dashicons-database-import',
            30
        );

        // Submenus
        add_submenu_page(
            'csv-updater',
            __('Import Products', 'csv-updater'),
            __('Import Products', 'csv-updater'),
            'manage_options',
            'csv-updater-import',
            [$this, 'render_import_page']
        );

        add_submenu_page(
            'csv-updater',
            __('Media Manager', 'csv-updater'),
            __('Media Manager', 'csv-updater'),
            'manage_options',
            'csv-updater-media',
            [$this, 'render_media_page']
        );

        add_submenu_page(
            'csv-updater',
            __('Settings', 'csv-updater'),
            __('Settings', 'csv-updater'),
            'manage_options',
            'csv-updater-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting(
            'csv_updater_settings_group',
            'csv_updater_options',
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Sanitize settings input
     *
     * @param array $input Raw input settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($input)
    {
        $new_input = [];

        // Sanitize and validate settings
        $new_input['log_retention_days'] = isset($input['log_retention_days'])
            ? absint($input['log_retention_days'])
            : 30;

        $new_input['max_products_per_batch'] = isset($input['max_products_per_batch'])
            ? absint($input['max_products_per_batch'])
            : 500;

        $new_input['image_width'] = isset($input['image_width'])
            ? absint($input['image_width'])
            : 400;

        $new_input['image_height'] = isset($input['image_height'])
            ? absint($input['image_height'])
            : 400;

        return $new_input;
    }

    /**
     * Render main dashboard page
     */
    public function render_main_page()
    {
        include CSV_UPDATER_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render product import page
     */
    public function render_import_page()
    {
        include CSV_UPDATER_PATH . 'admin/views/import.php';
    }

    /**
     * Render media manager page
     */
    public function render_media_page()
    {
        include CSV_UPDATER_PATH . 'admin/views/media-manager.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        include CSV_UPDATER_PATH . 'admin/views/settings.php';
    }

    /**
     * Handle CSV import AJAX request
     */
    public function handle_csv_import()
    {
        // Verify nonce
        check_ajax_referer('csv_import_action', 'csv_import_nonce');

        // Ensure user has proper capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Get uploaded file
        $file = $_FILES['csv_file'] ?? null;

        // Validate file
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->logger->error('Invalid file upload');
            wp_send_json_error(['message' => 'Invalid file upload']);
        }

        // Pass file to CSV_Importer for processing
        $file_path = $this->csv_importer->handle_file_upload($file);

        if ($file_path) {
            // Generate a unique import ID
            $import_id = 'import_' . uniqid();

            // Track recent imports
            $recent_imports = get_option('csv_updater_recent_imports', []);
            $recent_imports[] = $import_id;

            // Keep only last 5 imports
            $recent_imports = array_slice($recent_imports, -5);
            update_option('csv_updater_recent_imports', $recent_imports);

            // Store import details in transient
            $import_data = [
                'status' => 'in_progress',
                'file_path' => $file_path,
                'started_at' => current_time('mysql'),
                'total_rows' => 0,
                'processed_rows' => 0,
                'skipped_rows' => 0,
                'errors' => []
            ];
            set_transient($import_id, $import_data, WEEK_IN_SECONDS);

            // Start import process
            $this->start_background_import($import_id, $file_path);

            wp_send_json_success([
                'message' => 'Import started',
                'import_id' => $import_id
            ]);
        } else {
            $this->logger->error('File upload failed');
            wp_send_json_error(['message' => 'File upload failed']);
        }
    }

    /**
     * Start background import process
     *
     * @param string $import_id Unique import identifier
     ** @param string $file_path Path to CSV file
     */
    public function start_background_import($import_id, $file_path)
    {
        // Log the start of background import
        $this->logger->info("Scheduling background import", [
            'import_id' => $import_id,
            'file_path' => $file_path
        ]);

        // Schedule the import event
        wp_schedule_single_event(
            'csv_updater_background_import',
            [$import_id, $file_path]
        );

        // Trigger cron immediately if possible
        if (!defined('DOING_CRON') || !DOING_CRON) {
            spawn_cron();
        }
    }
    /**
     * Background import handler
     *
     * @param string $import_id Unique import identifier
     * @param string $file_path Path to CSV file
     */
    public function background_import_handler($import_id, $file_path)
    {
        // Retrieve import details
        $import_data = get_transient($import_id);

        if (!$import_data) {
            $this->logger->error("Import data not found for ID: {$import_id}");
            return;
        }

        try {
            // Perform CSV import
            $import_summary = $this->csv_importer->import_csv($file_path);

            // Update transient with final status
            $import_data['status'] = $import_summary['success'] ? 'completed' : 'failed';
            $import_data['total_rows'] = $import_summary['total_rows'];
            $import_data['processed_rows'] = $import_summary['processed_rows'];
            $import_data['skipped_rows'] = $import_summary['skipped_rows'];
            $import_data['completed_at'] = current_time('mysql');

            // Log the import summary
            $this->logger->info('Import Summary', $import_data);

            // Update the transient
            set_transient($import_id, $import_data, WEEK_IN_SECONDS);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            $import_data['status'] = 'failed';
            $import_data['error'] = $e->getMessage();

            $this->logger->error('Background import failed', [
                'import_id' => $import_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update the transient with error information
            set_transient($import_id, $import_data, WEEK_IN_SECONDS);
        }
    }

    /**
     * Check import status via AJAX
     */
    public function check_import_status()
    {
        // Verify nonce
        check_ajax_referer('csv_updater_import_status_nonce', 'nonce');

        // Get import ID from the request
        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';

        if (!$import_id) {
            wp_send_json_error(['message' => 'Invalid import ID']);
        }

        // Retrieve import data from transient
        $import_data = get_transient($import_id);

        if (!$import_data) {
            wp_send_json_error(['message' => 'Import data not found']);
        }

        // Prepare response
        $response = [
            'status' => $import_data['status'] ?? 'unknown',
            'message' => $this->get_import_status_message($import_data),
            'progress' => $this->calculate_import_progress($import_data),
            'log' => $this->get_import_log($import_id)
        ];

        wp_send_json_success($response);
    }

    /**
     * Get human-readable import status message
     *
     * @param array $import_data Import data
     * @return string
     */
    private function get_import_status_message($import_data)
    {
        switch ($import_data['status'] ?? 'unknown') {
            case 'in_progress':
                return sprintf(
                    'Import in progress... %d/%d rows processed',
                    $import_data['processed_rows'] ?? 0,
                    $import_data['total_rows'] ?? 0
                );
            case 'completed':
                return sprintf(
                    'Import completed. %d rows processed successfully (Skipped: %d).',
                    $import_data['processed_rows'] ?? 0,
                    $import_data['skipped_rows'] ?? 0
                );
            case 'failed':
                return 'Import failed. ' . ($import_data['error'] ?? 'Unknown error');
            default:
                return 'Unknown import status';
        }
    }

    /**
     * Calculate import progress percentage
     *
     * @param array $import_data Import data
     * @return float
     */
    private function calculate_import_progress($import_data)
    {
        $total_rows = $import_data['total_rows'] ?? 0;
        $processed_rows = $import_data['processed_rows'] ?? 0;

        if ($total_rows == 0) return 0;

        return round(($processed_rows / $total_rows) * 100, 2);
    }

    /**
     * Get import log contents
     *
     * @param string $import_id Import identifier
     * @return string
     */
    private function get_import_log($import_id)
    {
        // Find the most recent log file
        $log_files = glob(CSV_UPDATER_PATH . 'logs/import_*.log');

        if (empty($log_files)) {
            return 'No log files found.';
        }

        // Sort log files by modification time, newest first
        usort($log_files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Read the most recent log file
        $log_contents = file_get_contents($log_files[0]);

        // Truncate if too long
        return strlen($log_contents) > 5000
            ? substr($log_contents, -5000)
            : $log_contents;
    }
    /**
     * View log file via AJAX
     */
    public function view_log_ajax()
    {
        // Verify nonce
        check_ajax_referer('csv_updater_view_log_nonce', 'nonce');

        // Get log path
        $log_path = sanitize_text_field($_POST['log_path']);

        // Ensure log file exists and is within our logs directory
        if (strpos($log_path, CSV_UPDATER_PATH . 'logs/') !== 0) {
            wp_send_json_error('Invalid log file');
        }

        if (!file_exists($log_path)) {
            wp_send_json_error('Log file not found');
        }

        // Read log file contents
        $log_contents = file_get_contents($log_path);
        wp_send_json_success($log_contents);
    }

    /**
     * Purge log files via AJAX
     */
    public function ajax_purge_logs()
    {
        // Verify nonce
        check_ajax_referer('csv_updater_purge_logs_nonce', 'nonce');

        // Ensure user has proper capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Purge logs
        $log_files = glob(CSV_UPDATER_PATH . 'logs/import_*.log');
        $deleted_count = 0;

        foreach ($log_files as $file) {
            if (unlink($file)) {
                $deleted_count++;
            }
        }

        wp_send_json_success([
            'message' => sprintf('%d log files deleted', $deleted_count)
        ]);
    }

    /**
     * Handle raw image upload via AJAX
     */
    public function ajax_upload_raw_images()
    {
        // Verify nonce
        check_ajax_referer('raw_image_upload', 'raw_image_nonce');

        // Ensure user has proper capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Validate file upload
        if (empty($_FILES['raw_images'])) {
            wp_send_json_error('No files uploaded');
        }

        // Ensure raw images directory exists
        $upload_dir = WP_CONTENT_DIR . '/uploads/raw-images/';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        $uploaded_files = [];
        $error_files = [];

        // Process uploaded files
        $files = $_FILES['raw_images'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            // Skip any empty uploads
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Validate file
            $file_name = sanitize_file_name($files['name'][$i]);
            $file_tmp = $files['tmp_name'][$i];

            // Only allow image files
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array(mime_content_type($file_tmp), $allowed_types)) {
                $error_files[] = $file_name;
                continue;
            }

            // Move uploaded file
            $destination = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $uploaded_files[] = $file_name;
            } else {
                $error_files[] = $file_name;
            }
        }

        // Prepare response
        $response = [
            'uploaded' => $uploaded_files,
            'errors' => $error_files
        ];

        wp_send_json_success($response);
    }

    /**
     * Handle raw image deletion via AJAX
     */
    public function ajax_delete_raw_image()
    {
        // Verify nonce
        check_ajax_referer('delete_raw_image_nonce', 'nonce');

        // Ensure user has proper capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get filename
        $filename = sanitize_file_name($_POST['filename']);

        // Construct full path
        $file_path = WP_CONTENT_DIR . '/uploads/raw-images/' . $filename;

        // Validate file exists within our intended directory
        if (strpos($file_path, WP_CONTENT_DIR . '/uploads/raw-images/') !== 0) {
            wp_send_json_error('Invalid file path');
        }

        // Delete file
        if (file_exists($file_path) && unlink($file_path)) {
            wp_send_json_success('File deleted successfully');
        } else {
            wp_send_json_error('Failed to delete file');
        }
    }

    /**
     * Daily logging cleanup
     */
    public function daily_logging_cleanup()
    {
        // Purge old logs based on retention settings
        $retention_days = $this->options['log_retention_days'] ?? 30;
        $log_files = glob(CSV_UPDATER_PATH . 'logs/import_*.log');

        $now = time();
        foreach ($log_files as $file) {
            // Delete logs older than retention period
            if ($now - filemtime($file) > ($retention_days * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }

    /**
     * Import configuration mapping
     */
    public function get_import_mapping()
    {
        // Retrieve and return current import mapping
        return $this->csv_mapper->get_mapping();
    }

    /**
     * Update import configuration mapping
     *
     * @param array $mapping New mapping configuration
     * @return bool
     */
    public function update_import_mapping($mapping)
    {
        return $this->csv_mapper->save_mapping($mapping);
    }

    /**
     * Debugging method to check plugin status
     *
     * @return array
     */
    public function get_plugin_status()
    {
        return [
            'version' => CSV_UPDATER_VERSION,
            'options' => $this->options,
            'log_files' => glob(CSV_UPDATER_PATH . 'logs/import_*.log'),
            'raw_images' => glob(WP_CONTENT_DIR . '/uploads/raw-images/*'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => function_exists('WC') ? WC()->version : 'Not installed'
        ];
    }


    /**
     * Manual trigger for background import process
     */
    public function manual_background_import_trigger()
    {
        // Retrieve the most recent import details
        $recent_imports = get_option('csv_updater_recent_imports', []);

        if (empty($recent_imports)) {
            $this->logger->error('No recent imports found to trigger manually');
            return;
        }

        $recent_import_id = end($recent_imports);
        $import_data = get_transient($recent_import_id);

        if (!$import_data || empty($import_data['file_path'])) {
            $this->logger->error('No import file path found');
            return;
        }

        // Manually trigger the import
        $this->logger->info('Manually triggering background import', [
            'import_id' => $recent_import_id,
            'file_path' => $import_data['file_path']
        ]);

        $this->background_import_handler($recent_import_id, $import_data['file_path']);
    }



    // In includes/class-core.php, inside the Core class

    /**
     * Manually trigger background import for debugging
     *
     * @return bool
     */
    public function manually_trigger_background_import()
    {
        // Find the most recent import
        $recent_imports = get_option('csv_updater_recent_imports', []);

        if (empty($recent_imports)) {
            $this->logger->error('No recent imports to process');
            return false;
        }

        $recent_import_id = end($recent_imports);
        $import_data = get_transient($recent_import_id);

        if (!$import_data || empty($import_data['file_path'])) {
            $this->logger->error('No import file path found for manual processing');
            return false;
        }

        // Log the manual trigger attempt
        $this->logger->info('Manually triggering background import', [
            'import_id' => $recent_import_id,
            'file_path' => $import_data['file_path']
        ]);

        // Manually trigger background import
        try {
            $this->background_import_handler($recent_import_id, $import_data['file_path']);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Manual import trigger failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Add an admin menu item to manually trigger import (optional)
     */
    public function add_manual_import_trigger_menu()
    {
        add_submenu_page(
            'csv-updater',
            __('Manual Import Trigger', 'csv-updater'),
            __('Manual Import', 'csv-updater'),
            'manage_options',
            'csv-updater-manual-import',
            [$this, 'render_manual_import_page']
        );
    }

    /**
     * Render manual import trigger page
     */
    public function render_manual_import_page()
    {
?>
        <div class="wrap">
            <h1><?php _e('Manual Import Trigger', 'csv-updater'); ?></h1>
            <div class="card">
                <h2><?php _e('Debugging Tools', 'csv-updater'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('manual_import_trigger', 'manual_import_nonce'); ?>
                    <input type="submit" name="trigger_manual_import" class="button button-primary"
                        value="<?php _e('Manually Trigger Import', 'csv-updater'); ?>">
                </form>

                <?php
                // Handle form submission
                if (
                    isset($_POST['trigger_manual_import']) &&
                    check_admin_referer('manual_import_trigger', 'manual_import_nonce')
                ) {
                    $result = $this->manually_trigger_background_import();

                    if ($result) {
                        echo '<div class="notice notice-success"><p>' .
                            __('Manual import trigger initiated successfully.', 'csv-updater') .
                            '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' .
                            __('Failed to trigger manual import. Check logs for details.', 'csv-updater') .
                            '</p></div>';
                    }
                }
                ?>
            </div>
        </div>
<?php
    }
}
