<?php
/**
 * Plugin Name: CSV Updater
 * Description: Sync data from local machine to online catalogue
 * Version: 1.1.0
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
define('CSV_UPDATER_VERSION', '1.1.0');
define('CSV_UPDATER_PATH', plugin_dir_path(__FILE__));
define('CSV_UPDATER_URL', plugin_dir_url(__FILE__));
define('CSV_UPDATER_BASENAME', plugin_basename(__FILE__));

// Require autoloader
require_once CSV_UPDATER_PATH . 'includes/class-autoloader.php';

// Plugin initialization
function csv_updater_init() {
    // Initialize main plugin class
    \Novano\CSVUpdater\Core::get_instance();
}
add_action('plugins_loaded', 'csv_updater_init');

// Activation hook
function csv_updater_activate() {
    // Perform activation tasks
    // Create necessary directories
    wp_mkdir_p(CSV_UPDATER_PATH . 'logs');
    wp_mkdir_p(WP_CONTENT_DIR . '/uploads/raw-images');
}
register_activation_hook(__FILE__, 'csv_updater_activate');

// Deactivation hook
function csv_updater_deactivate() {
    // Cleanup tasks if needed
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