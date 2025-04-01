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
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->init_hooks();
    }

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
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin-specific hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
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
    public function register_settings() {
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
    public function sanitize_settings($input) {
        $new_input = [];
        
        // Sanitize and validate settings here
        
        return $new_input;
    }

    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        // Main dashboard content
        include CSV_UPDATER_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render product import page
     */
    public function render_import_page() {
        // Import page content
        include CSV_UPDATER_PATH . 'admin/views/import.php';
    }

    /**
     * Render media manager page
     */
    public function render_media_page() {
        // Media manager content
        include CSV_UPDATER_PATH . 'admin/views/media-manager.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Settings page content
        include CSV_UPDATER_PATH . 'admin/views/settings.php';
    }
}