<?php
namespace Novano\CSVUpdater;

/**
 * Logging functionality for CSV Updater plugin
 */
class Logger {
    /**
     * Log levels
     */
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_DEBUG = 'DEBUG';

    /**
     * Log file directory
     *
     * @var string
     */
    private $log_dir;

    /**
     * Current log file name
     *
     * @var string
     */
    private $current_log_file;

    /**
     * Log retention days
     *
     * @var int
     */
    private $retention_days;

    /**
     * Constructor
     */
    public function __construct() {
        // Get plugin options
        $options = get_option('csv_updater_options', [
            'log_retention_days' => 30
        ]);
        $this->retention_days = $options['log_retention_days'] ?? 30;

        // Set log directory
        $this->log_dir = CSV_UPDATER_PATH . 'logs/';
        
        // Ensure log directory exists
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        // Create log file with current date and time
        $this->current_log_file = $this->log_dir . 'import_' . date('Y-m-d_H-i-s') . '.log';
    }

    /**
     * Write log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context (optional)
     */
    public function log($level, $message, $context = []) {
        // Prepare log entry
        $timestamp = date('Y-m-d H:i:s');
        $formatted_context = $context ? ' ' . json_encode($context) : '';
        $log_entry = "[{$timestamp}] [{$level}] {$message}{$formatted_context}\n";
        
        // Write to log file
        file_put_contents($this->current_log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Log informational message
     *
     * @param string $message Log message
     * @param array $context Additional context (optional)
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Additional context (optional)
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context (optional)
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Additional context (optional)
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Purge old log files
     */
    public function purge_old_logs() {
        $files = glob($this->log_dir . '*.log');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > ($this->retention_days * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }

    /**
     * Get current log file path
     *
     * @return string
     */
    public function get_current_log_file() {
        return $this->current_log_file;
    }

    /**
     * Get log file contents
     *
     * @param string|null $file_path Specific log file path (optional)
     * @return string Log file contents
     */
    public function get_log_contents($file_path = null) {
        $file = $file_path ?? $this->current_log_file;
        
        if (!file_exists($file)) {
            return 'Log file not found.';
        }

        return file_get_contents($file);
    }

    /**
     * List available log files
     *
     * @return array List of log files
     */
    public function list_log_files() {
        $log_files = glob($this->log_dir . '*.log');
        
        // Sort by modification time, newest first
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Format log files with additional metadata
        return array_map(function($file) {
            return [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }, $log_files);
    }
}