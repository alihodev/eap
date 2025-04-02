<?php
namespace Novano\CSVUpdater;

// Ensure WP_Background_Process is available
if (!class_exists('WP_Background_Process')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-background-process.php';
}

/**
 * Background CSV Import Process with Enhanced Logging and Error Handling
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
     * Import configuration
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->logger = new Logger();
        $this->product_processor = new Product_Processor($this->logger);
        $this->config = get_option('csv_updater_options', [
            'max_products_per_batch' => 500
        ]);
    }

    /**
     * Task processing method
     *
     * @param mixed $item Queue item to process
     * @return bool
     */
    protected function task($item) { 
        // Log start of processing
        $this->logger->info('Processing import item', ['item' => $item]);

        // Ensure item is an array with product data
        if (!is_array($item)) {
            $this->logger->error('Invalid item format for import', [
                'item_type' => gettype($item),
                'item_contents' => print_r($item, true)
            ]);
            return false;
        }

        try {
            // Validate required fields
            if (empty($item['name']) || empty($item['sku'])) {
                $this->logger->warning('Skipping product - missing required fields', $item);
                return false;
            }

            // Process individual product
            $result = $this->product_processor->process_product($item);
            
            if ($result === false) {
                $this->logger->error('Failed to process product', [
                    'product_data' => $item
                ]);
            } else {
                $this->logger->info('Processed product: ' . ($item['sku'] ?? 'Unknown SKU'));
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during product import', [
                'error' => $e->getMessage(),
                'product_data' => $item,
                'trace' => $e->getTraceAsString()
            ]);
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

        // Optional: Send completion notification
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

        // Use wp_mail to send notification
        wp_mail($to, $subject, $message);
    }

    /**
     * Override dispatch method to add more logging
     */
    public function dispatch() {
        $this->logger->info('Dispatching CSV Import Background Process');
        return parent::dispatch();
    }

    /**
     * Override push_to_queue to add logging
     *
     * @param mixed $data Queue item to add
     * @return $this
     */
    public function push_to_queue($data) {
        $this->logger->info('Pushing item to import queue', [
            'data' => is_array($data) ? array_keys($data) : gettype($data)
        ]);
        return parent::push_to_queue($data);
    }
}