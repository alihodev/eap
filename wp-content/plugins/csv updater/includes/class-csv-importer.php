<?php
namespace Novano\CSVUpdater;

/**
 * Handles CSV file parsing and import process
 */
class CSV_Importer {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Background process instance
     *
     * @var CSV_Import_Process
     */
    private $background_process;

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
        $this->logger = new Logger();
        $this->background_process = new CSV_Import_Process();
        $this->config = get_option('csv_updater_options', [
            'max_products_per_batch' => 500,
            'batch_size' => 100
        ]);
    }

    /**
     * Parse and initiate CSV import
     *
     * @param string $file_path Path to uploaded CSV file
     * @return array Import summary
     */
    public function import_csv($file_path) {
        // Validate file
        if (!file_exists($file_path)) {
            $this->logger->error("CSV file not found: {$file_path}");
            return [
                'success' => false,
                'message' => 'CSV file not found',
                'total_rows' => 0
            ];
        }

        // Validate file is readable
        if (!is_readable($file_path)) {
            $this->logger->error("CSV file is not readable: {$file_path}");
            return [
                'success' => false,
                'message' => 'CSV file is not readable',
                'total_rows' => 0
            ];
        }

        // Open CSV file
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            $this->logger->error("Unable to open CSV file: {$file_path}");
            return [
                'success' => false,
                'message' => 'Unable to open CSV file',
                'total_rows' => 0
            ];
        }

        // Read and validate headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->logger->error("Unable to read CSV headers");
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Unable to read CSV headers',
                'total_rows' => 0
            ];
        }

        // Normalize headers (remove whitespace, lowercase)
        $headers = array_map(function($header) {
            return trim(strtolower(str_replace([' ', '[', ']'], ['_', '', ''], $header)));
        }, $headers);

        // Track import stats
        $import_stats = [
            'total_rows' => 0,
            'processed_rows' => 0,
            'skipped_rows' => 0,
            'success' => true,
            'message' => 'Import initiated'
        ];

        // Batch processing
        $batch = [];
        $batch_size = $this->config['batch_size'] ?? 100;

        // Process rows
        while (($row = fgetcsv($handle)) !== false) {
            $import_stats['total_rows']++;

            // Combine headers with row data
            $product_data = array_combine($headers, $row);

            // Validate product data (basic check)
            if (!$this->validate_product_data($product_data)) {
                $import_stats['skipped_rows']++;
                continue;
            }

            // Add to batch
            $batch[] = $product_data;

            // Dispatch batch when full
            if (count($batch) >= $batch_size) {
                $this->process_batch($batch);
                $import_stats['processed_rows'] += count($batch);
                $batch = []; // Reset batch
            }

            // Optional: Break if max products limit reached
            if ($import_stats['total_rows'] >= ($this->config['max_products_per_batch'] ?? 10000)) {
                break;
            }
        }

        // Process any remaining items in the batch
        if (!empty($batch)) {
            $this->process_batch($batch);
            $import_stats['processed_rows'] += count($batch);
        }

        // Close file
        fclose($handle);

        // Log import summary
        $this->logger->info("CSV Import Summary");
        $this->logger->info("Total Rows: {$import_stats['total_rows']}");
        $this->logger->info("Processed Rows: {$import_stats['processed_rows']}");
        $this->logger->info("Skipped Rows: {$import_stats['skipped_rows']}");

        return $import_stats;
    }

    /**
     * Process a batch of products
     *
     * @param array $batch Batch of product data
     */
    private function process_batch($batch) {
        // Add entire batch to background processing queue
        foreach ($batch as $product_data) {
            $this->background_process->push_to_queue($product_data);
        }

        // Dispatch the background process
        $this->background_process->save()->dispatch();
    }

    /**
     * Validate product data before processing
     *
     * @param array $product_data Product data row
     * @return bool
     */
    private function validate_product_data($product_data) {
        // Basic validation
        if (empty($product_data['code']) || empty($product_data['description'])) {
            $this->logger->warning("Skipping product - missing required fields");
            return false;
        }

        // Additional validation can be added here
        return true;
    }

    /**
     * Handle CSV file upload
     *
     * @param array $file_data $_FILES upload data
     * @return string|false Path to uploaded file or false on failure
     */
    public function handle_file_upload($file_data) {
        // Validate upload
        if (!isset($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            $this->logger->error("Invalid file upload");
            return false;
        }

        // Validate file type
        $allowed_types = ['text/csv', 'application/vnd.ms-excel'];
        if (!in_array($file_data['type'], $allowed_types)) {
            $this->logger->error("Invalid file type: {$file_data['type']}");
            return false;
        }

        // Validate file size (optional)
        $max_file_size = 100 * 1024 * 1024; // 100MB
        if ($file_data['size'] > $max_file_size) {
            $this->logger->error("File too large: {$file_data['size']} bytes");
            return false;
        }

        // Generate unique filename
        $upload_dir = wp_upload_dir();
        $destination = $upload_dir['path'] . '/csv-import-' . uniqid() . '.csv';

        // Move uploaded file
        if (move_uploaded_file($file_data['tmp_name'], $destination)) {
            $this->logger->info("CSV file uploaded: {$destination}");
            return $destination;
        }

        $this->logger->error("File upload failed");
        return false;
    }
}