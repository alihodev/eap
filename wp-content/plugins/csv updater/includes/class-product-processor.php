<?php
namespace Novano\CSVUpdater;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Handles product processing during CSV import with HPOS compatibility
 */
class Product_Processor {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Configuration options
     *
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param Logger $logger Logger instance
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->config = get_option('csv_updater_options', [
            'image_width' => 400,
            'image_height' => 400
        ]);
    }

    /**
     * Process individual product
     *
     * @param array $product_data Raw product data from CSV
     * @return bool
     */
    public function process_product($product_data) { 
        try {
            // Validate required fields
            if (empty($product_data['code']) || empty($product_data['description'])) {
                $this->logger->warning('Skipping product - missing required fields', $product_data);
                return false;
            }

            // Prepare product data
            $product_args = $this->prepare_product_args($product_data);

            // Get existing product by SKU
            $sku = 'EAP' . $product_data['code'];
            $existing_product_id = $this->get_product_id_by_sku($sku);

            if ($existing_product_id) {
                // Update existing product
                $product = wc_get_product($existing_product_id);
                
                if (!$product) {
                    $this->logger->error('Failed to retrieve existing product', [
                        'product_id' => $existing_product_id,
                        'sku' => $sku
                    ]);
                    return false;
                }

                // Update product properties
                foreach ($product_args as $prop => $value) {
                    $setter = 'set_' . $prop;
                    if (method_exists($product, $setter)) {
                        $product->$setter($value);
                    }
                }
                $product->save();
                
                $this->logger->info("Updated product: {$product_data['code']}");
            } else {
                // Create new product
                $product = new \WC_Product_Simple();
                
                // Set product properties
                foreach ($product_args as $prop => $value) {
                    $setter = 'set_' . $prop;
                    if (method_exists($product, $setter)) {
                        $product->$setter($value);
                    }
                }
                $product->save();
                
                $this->logger->info("Created new product: {$product_data['code']}");
            }

            // Handle product attributes
            $this->set_product_attributes($product, $product_data);

            // Handle product image
            $this->process_product_image($product, $product_data['code']);

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Product processing error: {$e->getMessage()}", [
                'product_code' => $product_data['code'],
                'error_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get product ID by SKU with HPOS compatibility
     *
     * @param string $sku Product SKU
     * @return int|null Product ID
     */
    private function get_product_id_by_sku($sku) {
        global $wpdb;

        // Use direct database query for maximum compatibility
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_sku' AND meta_value = %s 
                LIMIT 1",
                $sku
            )
        );

        return $product_id ? intval($product_id) : null;
    }

    /**
     * Prepare product arguments for WooCommerce
     *
     * @param array $product_data Raw product data
     * @return array
     */
    private function prepare_product_args($product_data) {
        // Basic price parsing, handling potential comma-separated decimal
        $price = str_replace(',', '.', $product_data['p_mark'] ?? 0);
        $price = floatval($price);

        return [
            'name' => $product_data['description'] ?? '',
            'description' => $product_data['description_2'] ?? '',
            'sku' => 'EAP' . $product_data['code'],
            'regular_price' => $price,
            'manage_stock' => true,
            'stock_quantity' => intval($product_data['qty'] ?? 0),
            'weight' => floatval($product_data['kg'] ?? 0),
            'tax_status' => !empty($product_data['vat']) ? 'taxable' : 'none',
            'status' => 'publish'
        ];
    }

    /**
     * Set product attributes
     *
     * @param \WC_Product $product Product object
     * @param array $product_data Raw product data
     */
    private function set_product_attributes($product, $product_data) {
        $attributes = [
            'Size' => $product_data['size'] ?? '',
            'Unit 1' => $product_data['unit1'] ?? '',
            'Unit 2' => $product_data['unit2'] ?? '',
            'Item Type' => $product_data['item_type'] ?? '',
            'Shelf Life' => $product_data['shelf_life'] ?? '',
            'Country' => $product_data['country'] ?? '',
            'Commodity Code' => $product_data['commodity_code'] ?? '',
            'Category Code' => $product_data['cat_code'] ?? '',
            'Type' => $product_data['type'] ?? '',
        ];

        $product_attributes = [];
        foreach ($attributes as $name => $value) {
            if (!empty($value)) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name($name);
                $attribute->set_options([$value]);
                $attribute->set_visible(true);
                $product_attributes[] = $attribute;
            }
        }

        $product->set_attributes($product_attributes);
        $product->save();
    }

    /**
     * Process and attach product image
     *
     * @param \WC_Product $product Product object
     * @param string $code Product code
     */
    private function process_product_image($product, $code) {
        $raw_image_path = WP_CONTENT_DIR . "/uploads/raw-images/{$code}.jpg";
        
        if (file_exists($raw_image_path)) {
            // Upload image to WordPress media library
            $upload = wp_upload_bits("{$code}.jpg", null, file_get_contents($raw_image_path));
            
            if (!$upload['error']) {
                // Resize image if needed
                $image_editor = wp_get_image_editor($upload['file']);
                if (!is_wp_error($image_editor)) {
                    $image_editor->resize(
                        $this->config['image_width'] ?? 400, 
                        $this->config['image_height'] ?? 400, 
                        false
                    );
                    $image_editor->save($upload['file']);
                }

                $attachment_id = $this->insert_attachment($upload['file'], $product->get_id());
                $product->set_image_id($attachment_id);
                $product->save();
                
                $this->logger->info("Attached image for product: {$code}");
                
                // Optional: Delete raw image after processing
                // unlink($raw_image_path);
            } else {
                $this->logger->error("Image upload failed for product: {$code}");
            }
        }
    }

    /**
     * Insert attachment to WordPress media library
     *
     * @param string $file File path
     * @param int $parent_post_id Parent product ID
     * @return int Attachment ID
     */
    private function insert_attachment($file, $parent_post_id = 0) {
        $wp_upload_dir = wp_upload_dir();
        $attachment = [
            'guid'           => $wp_upload_dir['url'] . '/' . basename($file),
            'post_mime_type' => wp_check_filetype(basename($file), null)['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment, $file, $parent_post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }
}