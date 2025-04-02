<?php
namespace Novano\CSVUpdater;

/**
 * CSV Column Mapping and Transformation for Product Import
 */
class CSV_Mapper {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Default mapping configuration
     *
     * @var array
     */
    private $default_mapping = [
        'name' => 'description',
        'description' => 'description_2',
        'sku' => ['prefix' => 'EAP', 'column' => 'code'],
        'regular_price' => 'p_mark',
        'stock_quantity' => 'qty',
        'weight' => 'kg',
        'tax_status' => ['column' => 'vat', 'transform' => 'tax_status_map'],
        'attributes' => [
            'size' => 'size',
            'unit1' => 'unit1',
            'unit2' => 'unit2',
            'item_type' => 'item_type',
            'shelf_life' => 'shelf_life',
            'country' => 'country',
            'category_code' => 'cat_code',
            'commodity_code' => 'commodity_code'
        ]
    ];

    /**
     * Current mapping configuration
     *
     * @var array
     */
    private $mapping;

    /**
     * Constructor
     *
     * @param Logger $logger Logger instance
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->load_mapping();
    }

    /**
     * Load mapping from database or use default
     */
    private function load_mapping() {
        $saved_mapping = get_option('csv_updater_column_mapping');
        $this->mapping = $saved_mapping ? json_decode($saved_mapping, true) : $this->default_mapping;
    }

    /**
     * Get current mapping configuration
     *
     * @return array
     */
    public function get_mapping() {
        return $this->mapping ?? $this->default_mapping;
    }

    /**
     * Transform row data based on mapping
     *
     * @param array $row Raw CSV row data
     * @return array Transformed product data
     */
    public function transform_row($row) {
        $transformed = [];

        // Normalize row keys (lowercase, remove special characters)
        $normalized_row = array_combine(
            array_map(function($key) {
                return strtolower(preg_replace('/[^\w\s]/', '', $key));
            }, array_keys($row)),
            $row
        );

        // Transform each mapped field
        foreach ($this->get_mapping() as $wc_field => $mapping_config) {
            try {
                $transformed[$wc_field] = $this->get_mapped_value($normalized_row, $mapping_config);
            } catch (\Exception $e) {
                $this->logger->warning("Mapping error for $wc_field", [
                    'error' => $e->getMessage(),
                    'row' => $row
                ]);
            }
        }

        // Additional data cleaning and validation
        $transformed = $this->clean_product_data($transformed);

        return $transformed;
    }

    // ... (rest of the previous implementation remains the same)


    /**
     * Get mapped value from normalized row
     *
     * @param array $row Normalized CSV row
     * @param mixed $mapping_config Mapping configuration
     * @return mixed
     */
    private function get_mapped_value($row, $mapping_config) {
        // Simple string mapping
        if (is_string($mapping_config)) {
            return $row[strtolower($mapping_config)] ?? null;
        }

        // Complex mapping
        if (is_array($mapping_config)) {
            // Handle prefixed values
            if (isset($mapping_config['prefix'])) {
                $column = strtolower($mapping_config['column']);
                $value = $row[$column] ?? null;
                return $value ? $mapping_config['prefix'] . $value : null;
            }

            // Handle transformations
            if (isset($mapping_config['transform'])) {
                $method = $mapping_config['transform'];
                $column = strtolower($mapping_config['column']);
                $value = $row[$column] ?? null;
                return method_exists($this, $method) ? $this->$method($value) : $value;
            }

            // Handle attribute mappings
            if (isset($mapping_config['column'])) {
                $column = strtolower($mapping_config['column']);
                return $row[$column] ?? null;
            }
        }

        return null;
    }

    /**
     * Clean and validate product data
     *
     * @param array $product_data Transformed product data
     * @return array Cleaned product data
     */
    private function clean_product_data($product_data) {
        // Remove null or empty values
        $product_data = array_filter($product_data, function($value) {
            return $value !== null && $value !== '';
        });

        // Sanitize and format values
        if (isset($product_data['regular_price'])) {
            // Replace comma with dot for price
            $product_data['regular_price'] = str_replace(',', '.', $product_data['regular_price']);
            $product_data['regular_price'] = floatval($product_data['regular_price']);
        }

        if (isset($product_data['stock_quantity'])) {
            $product_data['stock_quantity'] = intval($product_data['stock_quantity']);
        }

        if (isset($product_data['weight'])) {
            $product_data['weight'] = floatval($product_data['weight']);
        }

        return $product_data;
    }

    /**
     * Transform tax status
     *
     * @param mixed $vat VAT value
     * @return string
     */
    private function tax_status_map($vat) {
        return !empty($vat) ? 'taxable' : 'none';
    }

    /**
     * Save mapping configuration
     *
     * @param array $mapping New mapping configuration
     * @return bool
     */
    public function save_mapping($mapping) {
        // Validate mapping
        if (!$this->validate_mapping($mapping)) {
            $this->logger->error('Invalid CSV mapping configuration');
            return false;
        }

        // Save mapping
        update_option('csv_updater_column_mapping', json_encode($mapping));
        $this->mapping = $mapping;
        return true;
    }

    /**
     * Validate mapping configuration
     *
     * @param array $mapping Mapping to validate
     * @return bool
     */
    private function validate_mapping($mapping) {
        // Basic validation checks
        if (!is_array($mapping)) {
            return false;
        }

        // Ensure required keys exist
        $required_keys = ['name', 'sku'];
        foreach ($required_keys as $key) {
            if (!isset($mapping[$key])) {
                return false;
            }
        }

        return true;
    }
}