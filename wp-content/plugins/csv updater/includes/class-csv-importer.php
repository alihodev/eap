<?php

namespace Novano\CSVUpdater;

/**
 * Handles CSV file parsing and import process with enhanced logging
 */
class CSV_Importer
{
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
     * CSV Mapper instance
     *
     * @var CSV_Mapper
     */
    private $csv_mapper;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new Logger();
        $this->background_process = new CSV_Import_Process();
        $this->csv_mapper = new CSV_Mapper($this->logger);
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
    public function import_csv($file_path)
    {
        $this->logger->info("Starting detailed CSV import from {$file_path}");

        // Validate file
        if (!file_exists($file_path)) {
            $this->logger->error("CRITICAL: CSV file does not exist: {$file_path}");
            return [
                'success' => false,
                'message' => 'CSV file not found',
                'total_rows' => 0,
                'processed_rows' => 0
            ];
        }

        // Validate file is readable
        if (!is_readable($file_path)) {
            $this->logger->error("CRITICAL: CSV file is not readable: {$file_path}");
            return [
                'success' => false,
                'message' => 'CSV file is not readable',
                'total_rows' => 0,
                'processed_rows' => 0
            ];
        }

        // Open CSV file
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            $this->logger->error("CRITICAL: Unable to open CSV file: {$file_path}");
            return [
                'success' => false,
                'message' => 'Unable to open CSV file',
                'total_rows' => 0,
                'processed_rows' => 0
            ];
        }

        // Read and validate headers
        $headers = fgetcsv($handle);
        if ($headers === false) {
            $this->logger->error("CRITICAL: Unable to read CSV headers");
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Unable to read CSV headers',
                'total_rows' => 0,
                'processed_rows' => 0
            ];
        }

        // Log headers for debugging
        $this->logger->info("CSV Headers: " . implode(', ', $headers));

        // Normalize headers (remove whitespace, lowercase)
        $headers = array_map(function ($header) {
            return trim(strtolower(str_replace([' ', '[', ']'], ['_', '', ''], $header)));
        }, $headers);
        
        // Log normalized headers
        $this->logger->info("Normalized Headers: " . implode(', ', $headers));

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
        $row_number = 1; // Start from 1 to account for header row
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                $this->logger->warning("Skipping empty row: {$row_number}");
                continue;
            }

            $import_stats['total_rows']++;

            // Combine headers with row data
            try {
                $product_data = array_combine($headers, $row);
            } catch (\Exception $e) {
                $this->logger->error("Error combining headers and row data", [
                    'row_number' => $row_number,
                    'headers_count' => count($headers),
                    'row_count' => count($row),
                    'error' => $e->getMessage()
                ]);
                $import_stats['skipped_rows']++;
                continue;
            }

            // Log raw product data for debugging
            $this->logger->debug("Raw Product Data (Row {$row_number})", $product_data);

            // Transform row using mapper
            $mapped_data = $this->csv_mapper->transform_row($product_data);

            // Log mapped data
            $this->logger->debug("Mapped Product Data (Row {$row_number})", $mapped_data);

            // Validate product data
            if (!$this->validate_product_data($mapped_data)) {
                $import_stats['skipped_rows']++;
                continue;
            }

            // Add to batch
            $batch[] = $mapped_data;

            // Dispatch batch when full
            if (count($batch) >= $batch_size) {
                $this->process_batch($batch);
                $import_stats['processed_rows'] += count($batch);
                $batch = []; // Reset batch
            }

            // Optional: Break if max products limit reached
            if ($import_stats['total_rows'] >= ($this->config['max_products_per_batch'] ?? 10000)) {
                $this->logger->info("Reached max products limit");
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
        $this->logger->info("CSV Import Summary", $import_stats);

        return $import_stats;
    }

    /**
     * Process a batch of products
     *
     * @param array $batch Batch of product data
     */
    private function process_batch($batch)
    {
        $this->logger->info("Processing batch of " . count($batch) . " products");

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
     * @param array $product_data Mapped product data
     * @return bool
     */
    private function validate_product_data($product_data)
    {
        // Basic validation
        if (empty($product_data['name']) || empty($product_data['sku'])) {
            $this->logger->warning("Skipping product - missing required fields", $product_data);
            return false;
        }

        return true;
    }

    /**
     * Handle CSV file upload
     *
     * @param array $file_data $_FILES upload data
     * @return string|false Path to uploaded file or false on failure
     */
    public function handle_file_upload($file_data)
    {
        // Additional logging for file upload
        $this->logger->info("File upload details", [
            'name' => $file_data['name'] ?? 'Unknown',
            'type' => $file_data['type'] ?? 'Unknown',
            'size' => $file_data['size'] ?? 'Unknown',
            'tmp_name' => $file_data['tmp_name'] ?? 'Unknown'
        ]);

        // Validate upload
        if (!isset($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            $this->logger->error("Invalid file upload: Not an uploaded file");
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
            
            // Additional file validation
            $file_contents = file_get_contents($destination);
            $this->logger->info("First 500 characters of file:", [
                'content' => substr($file_contents, 0, 500)
            ]);

            return $destination;
        }

        $this->logger->error("File upload failed");
        return false;
    }
}