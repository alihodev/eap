<?php
namespace Novano\CSVUpdater;

/**
 * Autoloader for CSV Updater plugin
 */
class Autoloader {
    /**
     * Registered namespaces
     *
     * @var array
     */
    private static $namespaces = [
        'Novano\\CSVUpdater\\' => [
            'includes/',
            'admin/'
        ]
    ];

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
        // Check if class is in our namespaces
        $matched_namespace = null;
        foreach (array_keys(self::$namespaces) as $namespace) {
            if (strpos($class, $namespace) === 0) {
                $matched_namespace = $namespace;
                break;
            }
        }

        // If no matching namespace, return
        if ($matched_namespace === null) {
            return;
        }

        // Remove namespace prefix
        $relative_class = substr($class, strlen($matched_namespace));

        // Convert class name to file path
        $file_name = str_replace('\\', '/', strtolower(
            'class-' . preg_replace('/([a-z])([A-Z])/', '$1-$2', $relative_class) . '.php'
        ));

        // Try to find the file in registered paths
        foreach (self::$namespaces[$matched_namespace] as $path) {
            $full_path = CSV_UPDATER_PATH . $path . $file_name;
            
            if (file_exists($full_path)) {
                require_once $full_path;
                return;
            }
        }

        // Optional: Log autoload failure
        error_log("Could not load class: $class");
    }

    /**
     * Add additional namespace paths
     *
     * @param string $namespace Namespace prefix
     * @param array $paths Possible file paths
     */
    public static function add_namespace($namespace, $paths) {
        self::$namespaces[$namespace] = $paths;
    }
}