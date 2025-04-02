<?php
namespace Novano\CSVUpdater;

/**
 * Core plugin class
 */
class Core {
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
     * Get singleton instance
     *
     * @return Core
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->csv_importer = new CSV_Importer();
        $this->logger = new Logger();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin-specific hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'create_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            
            // AJAX handlers
            add_action('wp_ajax_csv_updater_import', [$this, 'handle_csv_import']);
            add_action('wp_ajax_csv_updater_import_status', [$this, 'check_import_status']);
        }
    }

    // Rest of the class methods remain the same as in previous implementations
    // (create_admin_menu, render_main_page, handle_csv_import, etc.)
}