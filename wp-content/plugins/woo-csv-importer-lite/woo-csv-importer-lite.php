<?php
/**
 * Plugin Name: WooCommerce CSV Importer Lite
 * Plugin URI: 
 * Description: A simple plugin to import and update WooCommerce products from CSV files
 * Version: 1.0.0
 * Author: 
 * Author URI: 
 * Text Domain: woo-csv-importer-lite
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
require_once plugin_dir_path(__FILE__) . 'media-integration.php';
// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    // Display admin notice if WooCommerce is not active
    add_action('admin_notices', function() {
        ?>
        <div class="error">
            <p><?php _e('WooCommerce CSV Importer Lite requires WooCommerce to be installed and activated.', 'woo-csv-importer-lite'); ?></p>
        </div>
        <?php
    });
    return;
}

class WOO_CSV_Importer_Lite {
    
    /**
     * Plugin instance.
     */
    private static $instance = null;
    
    /**
     * Hardcoded plugin settings
     */
    private $settings = [
        // Image dimensions for resizing
        'image_width' => 400,
        'image_height' => 400,
        
        // CSV field mapping (CSV column => WooCommerce field)
        'field_mapping' => [
            'title' => 'Description',
            'sku' => 'Barcode1 [E]', 
            'stock_quantity' => 'Qty',
            'weight' => 'KG'
        ],
        
        // Unique identifier field name in CSV
        'raw_id_field' => 'Code'
    ];
    
    /**
     * Source folder for initial image uploads
     */
    private $source_image_folder;
    
    /**
     * Target folder for processed product images
     */
    private $target_image_folder;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Set up folder paths
        $upload_dir = wp_upload_dir();
        $this->source_image_folder = $upload_dir['basedir'] . '/product-images';
        $this->target_image_folder = $upload_dir['basedir'] . '/products';
        
        // Create required directories if they don't exist
        if (!file_exists($this->source_image_folder)) {
            wp_mkdir_p($this->source_image_folder);
        }
        if (!file_exists($this->target_image_folder)) {
            wp_mkdir_p($this->target_image_folder);
        }
        
        // Register admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add action for handling form submission
        add_action('admin_post_process_csv_import', array($this, 'process_csv_import'));
    }
    
    /**
     * Get plugin instance.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('CSV Importer', 'woo-csv-importer-lite'),
            __('CSV Importer', 'woo-csv-importer-lite'),
            'manage_options',
            'woo-csv-importer',
            array($this, 'render_import_page'),
            'dashicons-database-import',
            56
        );
    }
    
    /**
     * Render the product import page
     */
    public function render_import_page() {
        // Check if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Display import results if available
        $import_results = get_transient('woo_csv_import_results');
        if ($import_results) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 sprintf(__('Import completed. Processed %d products (%d created, %d updated).', 'woo-csv-importer-lite'), 
                         $import_results['total'], $import_results['created'], $import_results['updated']) . 
                 '</p></div>';
            delete_transient('woo_csv_import_results');
        }
        
        // Display import form
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Import WooCommerce Products', 'woo-csv-importer-lite'); ?></h2>
                <p><?php _e('Upload a CSV file to import or update WooCommerce products.', 'woo-csv-importer-lite'); ?></p>
                <p><?php _e('The CSV file should have a header row with column names. The plugin uses the "rawid" column to identify products.', 'woo-csv-importer-lite'); ?></p>
                
                <h3><?php _e('Current Field Mapping', 'woo-csv-importer-lite'); ?></h3>
                <p><?php _e('The plugin is configured to map these CSV columns to WooCommerce fields:', 'woo-csv-importer-lite'); ?></p>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('WooCommerce Field', 'woo-csv-importer-lite'); ?></th>
                            <th><?php _e('CSV Column', 'woo-csv-importer-lite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->settings['field_mapping'] as $woo_field => $csv_field) : ?>
                        <tr>
                            <td><?php echo esc_html($woo_field); ?></td>
                            <td><?php echo esc_html($csv_field); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h3><?php _e('Image Handling', 'woo-csv-importer-lite'); ?></h3>
                <p><?php _e('Product images should be uploaded to:', 'woo-csv-importer-lite'); ?> <code><?php echo esc_html($this->source_image_folder); ?>/[rawid].jpg</code></p>
                <p><?php _e('Images will be resized to:', 'woo-csv-importer-lite'); ?> <?php echo esc_html($this->settings['image_width']); ?>×<?php echo esc_html($this->settings['image_height']); ?> <?php _e('pixels', 'woo-csv-importer-lite'); ?></p>
                
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="process_csv_import">
                    <?php wp_nonce_field('woo_csv_import_nonce', 'woo_csv_import_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="csv_file"><?php _e('CSV File', 'woo-csv-importer-lite'); ?></label></th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Import Products', 'woo-csv-importer-lite')); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Process the CSV import
     */
    public function process_csv_import() {
        // Check nonce for security
        check_admin_referer('woo_csv_import_nonce', 'woo_csv_import_nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-csv-importer-lite'));
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('Error uploading file. Please try again.', 'woo-csv-importer-lite'));
        }
        
        // Get the CSV file
        $csv_file = $_FILES['csv_file']['tmp_name'];
        
        // Check if it's a CSV file
        $file_info = pathinfo($_FILES['csv_file']['name']);
        if ($file_info['extension'] !== 'csv') {
            wp_die(__('Please upload a valid CSV file.', 'woo-csv-importer-lite'));
        }
        
        // Open the CSV file
        $file_handle = fopen($csv_file, 'r');
        if (!$file_handle) {
            wp_die(__('Unable to open CSV file.', 'woo-csv-importer-lite'));
        }
        
        // Get headers
        $headers = fgetcsv($file_handle);
        if (!$headers) {
            fclose($file_handle);
            wp_die(__('Could not process CSV file headers.', 'woo-csv-importer-lite'));
        }
        
        // Check if rawid field exists
        if (!in_array($this->settings['raw_id_field'], $headers)) {
            fclose($file_handle);
            wp_die(sprintf(__('The CSV file must contain a "%s" column.', 'woo-csv-importer-lite'), $this->settings['raw_id_field']));
        }
        
        // Import stats
        $stats = array(
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );
        
        // Process each row in the CSV
        while (($row = fgetcsv($file_handle)) !== false) {
            // Map CSV columns to their values
            $data = array_combine($headers, $row);
            $stats['total']++;
            
            // Get the raw ID for this product
            $raw_id = isset($data[$this->settings['raw_id_field']]) ? $data[$this->settings['raw_id_field']] : null;
            
            if (empty($raw_id)) {
                $stats['errors']++;
                continue;
            }
            
            // Try to find an existing product with this raw ID
            $existing_product_id = $this->find_product_by_raw_id($raw_id);
            
            // Create or update the product
            $result = $this->create_or_update_product($data, $existing_product_id);
            
            if ($result['success']) {
                if ($existing_product_id) {
                    $stats['updated']++;
                } else {
                    $stats['created']++;
                }
                
                // Process the product image if it exists
                $product_id = $result['product_id'];
                $this->process_product_image($raw_id, $product_id);
            } else {
                $stats['errors']++;
            }
        }
        
        fclose($file_handle);
        
        // Store import results in a transient
        set_transient('woo_csv_import_results', $stats, 60);
        
        // Redirect back to the import page
        wp_redirect(admin_url('admin.php?page=woo-csv-importer'));
        exit;
    }
    
    /**
     * Find a product by its raw ID
     * 
     * @param string $raw_id The raw ID to search for
     * @return int|false Product ID if found, false otherwise
     */
    private function find_product_by_raw_id($raw_id) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_raw_id',
                    'value' => $raw_id
                )
            ),
            'fields' => 'ids',
            'posts_per_page' => 1
        );
        
        $products = get_posts($args);
        
        return !empty($products) ? $products[0] : false;
    }
    
    /**
     * Create or update a WooCommerce product
     * 
     * @param array $data The product data from CSV
     * @param int|false $existing_product_id Existing product ID or false
     * @return array Result info with success status and product ID
     */
    private function create_or_update_product($data, $existing_product_id) {
        // Prepare product data
        $product_data = array(
            'post_type' => 'product',
            'post_status' => 'publish'
        );
        
        // Map fields according to settings
        foreach ($this->settings['field_mapping'] as $woo_field => $csv_field) {
            if (isset($data[$csv_field])) {
                switch ($woo_field) {
                    case 'title':
                        $product_data['post_title'] = $data[$csv_field];
                        break;
                    
                    case 'description':
                        $product_data['post_content'] = $data[$csv_field];
                        break;
                    
                    case 'short_description':
                        $product_data['post_excerpt'] = $data[$csv_field];
                        break;
                    
                    case 'SKU':
                        $product_data['post_excerpt'] = $data[$csv_field];
                        break;
                    
                    // Other fields will be handled as meta after product creation
                }
            }
        }
        
        // Start transaction to ensure all updates are atomic
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            if ($existing_product_id) {
                // Update existing product
                $product_data['ID'] = $existing_product_id;
                $product_id = wp_update_post($product_data, true);
            } else {
                // Create new product
                $product_id = wp_insert_post($product_data, true);
            }
            
            // Check for errors
            if (is_wp_error($product_id)) {
                $wpdb->query('ROLLBACK');
                return array('success' => false, 'message' => $product_id->get_error_message());
            }
            
            // Set product type to simple
            wp_set_object_terms($product_id, 'simple', 'product_type');
            
            // Store raw ID as meta
            update_post_meta($product_id, '_raw_id', $data[$this->settings['raw_id_field']]);
            
            // Store raw data
            update_post_meta($product_id, '_raw_data', serialize($data));
            
            // Update product meta fields
            $this->update_product_meta($product_id, $data);
            
            // Process categories if available
            if (isset($data[$this->settings['field_mapping']['categories']])) {
                $this->process_product_terms($product_id, $data[$this->settings['field_mapping']['categories']], 'product_cat');
            }
            
            // Process tags if available
            if (isset($data[$this->settings['field_mapping']['tags']])) {
                $this->process_product_terms($product_id, $data[$this->settings['field_mapping']['tags']], 'product_tag');
            }
            
            $wpdb->query('COMMIT');
            
            return array('success' => true, 'product_id' => $product_id);
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Update product meta fields
     * 
     * @param int $product_id The product ID
     * @param array $data The product data from CSV
     */
    private function update_product_meta($product_id, $data) {
        // Product price fields
        if (isset($data[$this->settings['field_mapping']['regular_price']])) {
            update_post_meta($product_id, '_regular_price', wc_format_decimal($data[$this->settings['field_mapping']['regular_price']]));
            update_post_meta($product_id, '_price', wc_format_decimal($data[$this->settings['field_mapping']['regular_price']]));
        }
        
        if (isset($data[$this->settings['field_mapping']['sale_price']])) {
            update_post_meta($product_id, '_sale_price', wc_format_decimal($data[$this->settings['field_mapping']['sale_price']]));
            
            // Update _price if sale price is lower than regular price
            $regular_price = get_post_meta($product_id, '_regular_price', true);
            $sale_price = wc_format_decimal($data[$this->settings['field_mapping']['sale_price']]);
            
            if ($sale_price && $sale_price < $regular_price) {
                update_post_meta($product_id, '_price', $sale_price);
            }
        }
        
        // SKU
        if (isset($data[$this->settings['field_mapping']['sku']])) {
            update_post_meta($product_id, '_sku', wc_clean($data[$this->settings['field_mapping']['sku']]));
        }
        
        // Stock quantity
        if (isset($data[$this->settings['field_mapping']['stock_quantity']])) {
            $stock = wc_stock_amount($data[$this->settings['field_mapping']['stock_quantity']]);
            update_post_meta($product_id, '_stock', $stock);
            update_post_meta($product_id, '_stock_status', ($stock > 0) ? 'instock' : 'outofstock');
            update_post_meta($product_id, '_manage_stock', 'yes');
        }
        
        // Dimensions
        $dimensions_fields = array('weight', 'length', 'width', 'height');
        foreach ($dimensions_fields as $field) {
            if (isset($data[$this->settings['field_mapping'][$field]])) {
                update_post_meta($product_id, '_' . $field, wc_format_decimal($data[$this->settings['field_mapping'][$field]]));
            }
        }
    }
    
    /**
     * Process product terms (categories or tags)
     * 
     * @param int $product_id The product ID
     * @param string $terms_string Comma-separated terms
     * @param string $taxonomy The taxonomy (product_cat or product_tag)
     */
    private function process_product_terms($product_id, $terms_string, $taxonomy) {
        $terms = array_map('trim', explode(',', $terms_string));
        $term_ids = array();
        
        foreach ($terms as $term_name) {
            if (empty($term_name)) {
                continue;
            }
            
            // Check if term exists
            $existing_term = term_exists($term_name, $taxonomy);
            
            if ($existing_term) {
                $term_ids[] = $existing_term['term_id'];
            } else {
                // Create new term
                $new_term = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($new_term)) {
                    $term_ids[] = $new_term['term_id'];
                }
            }
        }
        
        // Set terms for product
        if (!empty($term_ids)) {
            wp_set_object_terms($product_id, $term_ids, $taxonomy);
        }
    }
    
    /**
     * Process product image
     * 
     * @param string $raw_id The raw ID
     * @param int $product_id The product ID
     * @return bool Success or failure
     */
    private function process_product_image($raw_id, $product_id) {
        // Source image path
        $source_image = $this->source_image_folder . '/' . $raw_id . '.jpg';
        
        // Check if source image exists
        if (!file_exists($source_image)) {
            return false;
        }
        
        // Create target directory if it doesn't exist
        $target_dir = $this->target_image_folder . '/' . $product_id;
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Target image path
        $target_image = $target_dir . '/' . $raw_id . '.jpg';
        
        // Resize and save the image
        $image = wp_get_image_editor($source_image);
        if (!is_wp_error($image)) {
            $image->resize($this->settings['image_width'], $this->settings['image_height'], true);
            $image->save($target_image);
            
            // Prepare file for WordPress attachment
            $filetype = wp_check_filetype(basename($target_image), null);
            $wp_upload_dir = wp_upload_dir();
            
            $attachment = array(
                'guid' => $wp_upload_dir['url'] . '/' . basename($target_image),
                'post_mime_type' => $filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($target_image)),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            // Insert attachment into WordPress media library
            $attach_id = wp_insert_attachment($attachment, $target_image, $product_id);
            
            // Generate metadata for the attachment
            $attach_data = wp_generate_attachment_metadata($attach_id, $target_image);
            wp_update_attachment_metadata($attach_id, $attach_data);
            
            // Set as product image
            set_post_thumbnail($product_id, $attach_id);
            
            return true;
        }
        
        return false;
    }
}

// Initialize the plugin
function woo_csv_importer_lite_init() {
    WOO_CSV_Importer_Lite::get_instance();
}
add_action('plugins_loaded', 'woo_csv_importer_lite_init');