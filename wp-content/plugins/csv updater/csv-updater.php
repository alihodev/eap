<?php
/**
 * Plugin Name: CSV Updater
 * Description: Sync data from local machine to online catalogue
 * Version: 1.2.0
 * Author: Novano
 * Text Domain: csv-updater
 * 
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * 
 * WC requires at least: 6.0
 * WC tested up to: 8.x
 * WC compatibility: HPOS
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CSV_UPDATER_VERSION', '1.2.0');
define('CSV_UPDATER_PATH', plugin_dir_path(__FILE__));
define('CSV_UPDATER_URL', plugin_dir_url(__FILE__));
define('CSV_UPDATER_BASENAME', plugin_basename(__FILE__));

// Require autoloader
require_once CSV_UPDATER_PATH . 'includes/class-autoloader.php';

// Plugin initialization
function csv_updater_init() {
    // Initialize main plugin class
    $core = \Novano\CSVUpdater\Core::get_instance();
    $core->init();
}
add_action('plugins_loaded', 'csv_updater_init');

// Activation hook
function csv_updater_activate() {
    // Perform activation tasks
    // Create necessary directories
    wp_mkdir_p(CSV_UPDATER_PATH . 'logs');
    wp_mkdir_p(WP_CONTENT_DIR . '/uploads/raw-images');
    wp_mkdir_p(WP_CONTENT_DIR . '/uploads/imported-products');

    // Set default options if not already set
    $default_options = [
        'log_retention_days' => 30,
        'max_products_per_batch' => 500,
        'image_width' => 400,
        'image_height' => 400,
        'batch_size' => 100
    ];

    // Only set defaults if option doesn't exist
    if (false === get_option('csv_updater_options')) {
        update_option('csv_updater_options', $default_options);
    }
}
register_activation_hook(__FILE__, 'csv_updater_activate');

// Deactivation hook
function csv_updater_deactivate() {
    // Cleanup tasks
    // Remove scheduled events
    wp_clear_scheduled_hook('csv_updater_background_import');
}
register_deactivation_hook(__FILE__, 'csv_updater_deactivate');

// Ensure HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables', 
            __FILE__, 
            true
        );
    }
});

// Logging cleanup hook
function csv_updater_daily_logging_cleanup() {
    $logger = new \Novano\CSVUpdater\Logger();
    $logger->purge_old_logs();
}
add_action('csv_updater_daily_logging_cleanup', 'csv_updater_daily_logging_cleanup');

// Schedule daily logging cleanup if not already scheduled
if (!wp_next_scheduled('csv_updater_daily_logging_cleanup')) {
    wp_schedule_event(time(), 'daily', 'csv_updater_daily_logging_cleanup');
}
// In csv-updater.php
function csv_updater_register_background_process() {
    // Use Core::get_instance() instead of undefined $core variable
    $core = \Novano\CSVUpdater\Core::get_instance();
    
    // Register the background import action
    add_action('csv_updater_background_import', [$core, 'background_import_handler'], 10, 2);
}
add_action('plugins_loaded', 'csv_updater_register_background_process');