<?php
namespace Novano\CSVUpdater;

/**
 * Autoloader for CSV Updater plugin
 */
class Autoloader {
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload class files
     *
     * @param string $class Class name to load
     */
    public static function autoload($class) {
        // Only autoload classes in our namespace
        if (strpos($class, 'Novano\CSVUpdater') !== 0) {
            return;
        }

        // Convert namespace to file path
        $class = str_replace('Novano\CSVUpdater\\', '', $class);
        $class = strtolower(str_replace('_', '-', $class));

        $possible_paths = [
            CSV_UPDATER_PATH . 'includes/class-' . $class . '.php',
            CSV_UPDATER_PATH . 'admin/class-' . $class . '.php',
            CSV_UPDATER_PATH . 'includes/' . $class . '.php',
            CSV_UPDATER_PATH . 'admin/' . $class . '.php',
        ];

        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    }
}

// Register the autoloader
Autoloader::register();