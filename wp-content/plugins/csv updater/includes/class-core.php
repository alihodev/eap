<?php

namespace Novano\CSVUpdater;

/**
 * Core plugin class
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
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->csv_importer = new CSV_Importer();
        $this->logger = new Logger();
        $this->init_hooks();
    }

    /**
     * Get singleton instance
     *
     * @return Core
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
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

            // AJAX handlers
            add_action('wp_ajax_csv_updater_import', [$this, 'handle_csv_import']);
            add_action('wp_ajax_csv_updater_import_status', [$this, 'check_import_status']);
        }
    }

    /**
     * Handle CSV import AJAX request
     */
    public function handle_csv_import()
    {
        // Verify nonce
        check_ajax_referer('csv_import_action', 'csv_import_nonce');

        // Get uploaded file
        $file = $_FILES['csv_file'] ?? null;

        // Validate file
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Invalid file upload']);
        }

        // Pass file to CSV_Importer for processing
        $csv_importer = new CSV_Importer();
        $file_path = $csv_importer->handle_file_upload($file);

        if ($file_path) {
            // Start import process
            $import_summary = $csv_importer->import_csv($file_path);
            wp_send_json_success([
                'message' => 'Import started',
                'import_id' => uniqid(),
                'summary' => $import_summary
            ]);
        } else {
            wp_send_json_error(['message' => 'File upload failed']);
        }
    }

    /**
     * Check import status AJAX request
     */
    public function check_import_status() { 
        // die('hereeeeeeeeeeee');
        // Verify nonce
        check_ajax_referer('csv_updater_import_status_nonce', 'nonce');

        // Get import ID from the request
        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';

        if (!$import_id) {
            wp_send_json_error(['message' => 'Invalid import ID']);
        }

        // Check the status of the import process
        // Replace this with your actual implementation to get the import status
        $status = 'in_progress'; // Example status
        $message = 'Import is in progress...'; // Example message
        $log = ''; // Example log data

        // Return the import status
        wp_send_json_success([
            'status' => $status,
            'message' => $message,
            'log' => $log
        ]);
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
            array($this, 'render_main_page'),
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
            array($this, 'render_import_page')
        );

        add_submenu_page(
            'csv-updater',
            __('Media Manager', 'csv-updater'),
            __('Media Manager', 'csv-updater'),
            'manage_options',
            'csv-updater-media',
            array($this, 'render_media_page')
        );

        add_submenu_page(
            'csv-updater',
            __('Settings', 'csv-updater'),
            __('Settings', 'csv-updater'),
            'manage_options',
            'csv-updater-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render main dashboard page
     */
    public function render_main_page()
    {
        // Main dashboard content
        include CSV_UPDATER_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render product import page
     */
    public function render_import_page()
    {
        // Import page content
        include CSV_UPDATER_PATH . 'admin/views/import.php';
    }

    /**
     * Render media manager page
     */
    public function render_media_page()
    {
        // Media manager content
        include CSV_UPDATER_PATH . 'admin/views/media-manager.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        // Settings page content
        include CSV_UPDATER_PATH . 'admin/views/settings.php';
    }

    /**
     * Register plugin settings
     */
    public function register_settings()
    {
        register_setting(
            'csv_updater_settings_group',
            'csv_updater_options',
            array($this, 'sanitize_settings')
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
        $new_input = array();

        // Sanitize and validate settings
        if (isset($input['max_products_per_batch'])) {
            $new_input['max_products_per_batch'] = absint($input['max_products_per_batch']);
        }

        if (isset($input['log_retention_days'])) {
            $new_input['log_retention_days'] = absint($input['log_retention_days']);
        }

        return $new_input;
    }

    // ... (previous AJAX methods remain the same as in the previous implementation)
}
