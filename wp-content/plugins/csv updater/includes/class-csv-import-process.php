<?php
namespace Novano\CSVUpdater;

// Ensure WP_Background_Process is available
if (!class_exists('WP_Background_Process')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-background-process.php';
}

/**
 * Background CSV Import Process
 */
class CSV_Import_Process extends \WP_Background_Process {
    /**
     * Action name for the background process
     *
     * @var string
     */
    protected $action = 'csv_import_process';

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Product Processor instance
     *
     * @var Product_Processor
     */
    private $product_processor;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->logger = new Logger();
        $this->product_processor = new Product_Processor($this->logger);
    }

    /**
     * Task processing method
     *
     * @param mixed $item Queue item to process
     * @return bool
     */
    protected function task($item) {
        // Ensure item is an array with product data
        if (!is_array($item)) {
            $this->logger->error('Invalid item format for import');
            return false;
        }

        try {
            // Process individual product
            $result = $this->product_processor->process_product($item);
            
            if ($result === false) {
                $this->logger->error('Failed to process product: ' . print_r($item, true));
            } else {
                $this->logger->info('Processed product: ' . $item['Code']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during product import: ' . $e->getMessage());
        }

        // Always return false to complete the task
        return false;
    }

    /**
     * Complete processing
     */
    protected function complete() {
        parent::complete();
        
        // Log completion
        $this->logger->info('CSV Import Process Completed');

        // Optional: Send email notification or trigger other actions
        $this->send_import_completion_notification();
    }

    /**
     * Send import completion notification
     */
    private function send_import_completion_notification() {
        $to = get_option('admin_email');
        $subject = 'CSV Import Completed';
        $message = "The CSV import process has been completed.\n";
        $message .= "Log file: " . $this->logger->get_current_log_file();

        wp_mail($to, $subject, $message);
    }
}