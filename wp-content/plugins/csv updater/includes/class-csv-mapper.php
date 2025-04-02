<?php
namespace Novano\CSVUpdater;

/**
 * CSV Column Mapping and Transformation
 */
class CSV_Mapper {
    /**
     * Default mapping configuration
     *
     * @var array
     */
    private $default_mapping = [
        'product_id' => 'code',
        'name' => 'description',
        'description' => 'description_2',
        'sku' => ['prefix' => 'EAP', 'column' => 'code'],
        'regular_price' => 'p_mark',
        'sale_price' => null,
        'stock_quantity' => 'qty',
        'weight' => 'kg',
        'dimensions' => [
            'length' => null,
            'width' => null,
            'height' => null
        ],
        'tax_status' => ['column' => 'vat', 'transform' => 'tax_status_map'],
        'categories' => 'cat_code',
        'tags' => null,
        'attributes' => [
            'size' => 'size',
            'unit1' => 'unit1',
            'unit2' => 'unit2',
            'item_type' => 'item_type',
            'shelf_life' => 'shelf_life',
            'country' => 'country'
        ]
    ];

    /**
     * Current mapping configuration
     *
     * @var array
     */
    private $mapping;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

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
        $required_keys = ['product_id', 'name', 'sku'];
        foreach ($required_keys as $key) {
            if (!isset($mapping[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Transform raw CSV data based on mapping
     *
     * @param array $row Raw CSV row data
     * @return array Transformed product data
     */
    public function transform_row($row) {
        $transformed = [];

        foreach ($this->mapping as $wc_field => $mapping_config) {
            // Skip if no mapping
            if ($mapping_config === null) continue;

            try {
                $transformed[$wc_field] = $this->get_mapped_value($row, $mapping_config);
            } catch (\Exception $e) {
                $this->logger->warning("Mapping error for $wc_field", [
                    'error' => $e->getMessage(),
                    'row' => $row
                ]);
            }
        }

        return $transformed;
    }

    /**
     * Get mapped value from CSV row
     *
     * @param array $row Raw CSV row
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
                $value = $row[strtolower($mapping_config['column'])] ?? null;
                return $value ? $mapping_config['prefix'] . $value : null;
            }

            // Handle transformations
            if (isset($mapping_config['transform'])) {
                $method = $mapping_config['transform'];
                $value = $row[strtolower($mapping_config['column'])] ?? null;
                return method_exists($this, $method) ? $this->$method($value) : $value;
            }

            // Handle attribute mappings
            if (isset($mapping_config['column'])) {
                return $row[strtolower($mapping_config['column'])] ?? null;
            }
        }

        return null;
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
     * Get current mapping configuration
     *
     * @return array
     */
    public function get_mapping() {
        return $this->mapping;
    }

    /**
     * Reset mapping to default
     *
     * @return bool
     */
    public function reset_to_default() {
        delete_option('csv_updater_column_mapping');
        $this->load_mapping();
        return true;
    }
}