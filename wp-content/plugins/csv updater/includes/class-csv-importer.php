<?php
namespace Novano\CSVUpdater;

/**
 * Handles CSV file parsing and import process
 */
class CSV_Importer {
    // ... (previous methods remain the same)

    /**
     * Handle CSV file upload
     *
     * @param array $file_data $_FILES upload data
     * @return string|false Path to uploaded file or false on failure
     * @throws \Exception On upload failure
     */
    public function handle_file_upload($file_data) {
        // Detailed logging for debugging
        error_log('CSV Importer: Starting file upload');
        error_log('File data: ' . print_r($file_data, true));

        // Validate upload
        if (!isset($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            error_log('CSV Importer: Invalid file upload');
            throw new \Exception('Invalid file upload');
        }

        // Validate file type
        $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'application/csv', 'text/plain'];
        if (!in_array($file_data['type'], $allowed_types)) {
            error_log('CSV Importer: Invalid file type: ' . $file_data['type']);
            throw new \Exception('Invalid file type: ' . $file_data['type']);
        }

        // Validate file size (optional)
        $max_file_size = 100 * 1024 * 1024; // 100MB
        if ($file_data['size'] > $max_file_size) {
            error_log('CSV Importer: File too large: ' . $file_data['size'] . ' bytes');
            throw new \Exception('File too large: ' . $file_data['size'] . ' bytes');
        }

        // Generate unique filename
        $upload_dir = wp_upload_dir();
        $destination = $upload_dir['path'] . '/csv-import-' . uniqid() . '.csv';

        // Ensure upload directory exists
        if (!file_exists($upload_dir['path'])) {
            wp_mkdir_p($upload_dir['path']);
        }

        // Move uploaded file
        if (move_uploaded_file($file_data['tmp_name'], $destination)) {
            error_log('CSV Importer: File uploaded successfully to ' . $destination);
            return $destination;
        }

        // If move fails
        error_log('CSV Importer: File move failed');
        throw new \Exception('File upload failed');
    }

    // ... (rest of the class remains the same)
}