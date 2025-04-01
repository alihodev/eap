<?php 
/**
 * Add Media Library Integration to WooCommerce CSV Importer Lite
 * 
 * This file extends the WOO_CSV_Importer_Lite class with Media Library support
 */

// Modify the class to add Media Library support
class WOO_CSV_Importer_Lite_Media {
    
    /**
     * Add hooks and filters
     */
    public static function init() {
        // Add submenu page
        add_action('admin_menu', array('WOO_CSV_Importer_Lite_Media', 'add_submenu_page'));
        
        // Add AJAX handlers for image association
        add_action('wp_ajax_woo_csv_associate_images', array('WOO_CSV_Importer_Lite_Media', 'ajax_associate_images'));
        
        // Filter to modify image processing in main plugin
        add_filter('woo_csv_process_product_image', array('WOO_CSV_Importer_Lite_Media', 'use_media_library_image'), 10, 2);
        
        // Add scripts and styles
        add_action('admin_enqueue_scripts', array('WOO_CSV_Importer_Lite_Media', 'enqueue_scripts'));
    }
    
    /**
     * Add submenu page
     */
    public static function add_submenu_page() {
        add_submenu_page(
            'woo-csv-importer', 
            __('Image Manager', 'woo-csv-importer-lite'),
            __('Image Manager', 'woo-csv-importer-lite'),
            'manage_options',
            'woo-csv-importer-images',
            array('WOO_CSV_Importer_Lite_Media', 'render_image_manager_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if ('woo-commerce_page_woo-csv-importer-images' !== $hook) {
            return;
        }
        
        wp_enqueue_media();
        
        wp_enqueue_script(
            'woo-csv-image-manager',
            plugins_url('js/image-manager.js', __FILE__),
            array('jquery', 'media-upload'),
            '1.0.0',
            true
        );
        
        wp_localize_script('woo-csv-image-manager', 'woo_csv_images', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_csv_image_manager_nonce'),
            'text' => array(
                'select_image' => __('Select Image', 'woo-csv-importer-lite'),
                'use_this_image' => __('Use this image', 'woo-csv-importer-lite'),
                'saving' => __('Saving...', 'woo-csv-importer-lite'),
                'saved' => __('Saved!', 'woo-csv-importer-lite'),
                'error' => __('Error', 'woo-csv-importer-lite'),
            )
        ));
        
        wp_enqueue_style(
            'woo-csv-image-manager-style',
            plugins_url('css/image-manager.css', __FILE__),
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Render the image manager page
     */
    public static function render_image_manager_page() {
        // Check if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get the raw IDs from existing products
        $raw_ids = self::get_all_raw_ids();
        
        // Get the image mappings
        $image_mappings = get_option('woo_csv_image_mappings', array());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Product Image Manager', 'woo-csv-importer-lite'); ?></h2>
                <p><?php _e('Associate images from the Media Library with product IDs for CSV import.', 'woo-csv-importer-lite'); ?></p>
                
                <div class="notice notice-info">
                    <p><?php _e('Use this page to associate images from your Media Library with the Raw IDs used in your CSV file. This will replace the need to upload images to a specific folder structure.', 'woo-csv-importer-lite'); ?></p>
                </div>
                
                <table class="widefat striped image-manager-table">
                    <thead>
                        <tr>
                            <th><?php _e('Raw ID', 'woo-csv-importer-lite'); ?></th>
                            <th><?php _e('Current Image', 'woo-csv-importer-lite'); ?></th>
                            <th><?php _e('Actions', 'woo-csv-importer-lite'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($raw_ids)) : ?>
                            <tr>
                                <td colspan="3"><?php _e('No products with Raw IDs found. Import products first.', 'woo-csv-importer-lite'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($raw_ids as $raw_id) : ?>
                                <tr data-raw-id="<?php echo esc_attr($raw_id); ?>">
                                    <td><?php echo esc_html($raw_id); ?></td>
                                    <td class="image-preview">
                                        <?php 
                                        if (isset($image_mappings[$raw_id])) {
                                            echo wp_get_attachment_image($image_mappings[$raw_id], 'thumbnail');
                                        } else {
                                            _e('No image', 'woo-csv-importer-lite');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button select-image" data-raw-id="<?php echo esc_attr($raw_id); ?>">
                                            <?php _e('Select Image', 'woo-csv-importer-lite'); ?>
                                        </button>
                                        
                                        <?php if (isset($image_mappings[$raw_id])) : ?>
                                            <button type="button" class="button remove-image" data-raw-id="<?php echo esc_attr($raw_id); ?>">
                                                <?php _e('Remove', 'woo-csv-importer-lite'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <span class="status"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="bulk-actions">
                    <h3><?php _e('Bulk Image Upload', 'woo-csv-importer-lite'); ?></h3>
                    <p><?php _e('You can also upload a ZIP file containing images named with their corresponding Raw IDs (e.g., ABC123.jpg).', 'woo-csv-importer-lite'); ?></p>
                    
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="woo_csv_bulk_upload_images">
                        <?php wp_nonce_field('woo_csv_bulk_upload', 'woo_csv_bulk_upload_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="zip_file"><?php _e('ZIP File', 'woo-csv-importer-lite'); ?></label></th>
                                <td>
                                    <input type="file" name="zip_file" id="zip_file" accept=".zip" required>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Upload & Process Images', 'woo-csv-importer-lite')); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get all Raw IDs from existing products
     * 
     * @return array Array of Raw IDs
     */
    private static function get_all_raw_ids() {
        global $wpdb;
        
        $results = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = '_raw_id' 
            AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product') 
            ORDER BY meta_value ASC"
        );
        
        return $results;
    }
    
    /**
     * AJAX handler for associating images with Raw IDs
     */
    public static function ajax_associate_images() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'woo_csv_image_manager_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-csv-importer-lite')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-csv-importer-lite')));
        }
        
        $raw_id = isset($_POST['raw_id']) ? sanitize_text_field($_POST['raw_id']) : '';
        $action = isset($_POST['image_action']) ? sanitize_text_field($_POST['image_action']) : '';
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (empty($raw_id)) {
            wp_send_json_error(array('message' => __('Raw ID is required', 'woo-csv-importer-lite')));
        }
        
        // Get current mappings
        $image_mappings = get_option('woo_csv_image_mappings', array());
        
        if ($action === 'associate' && $attachment_id > 0) {
            // Associate image
            $image_mappings[$raw_id] = $attachment_id;
            
            // Update product image if product exists
            $product_id = self::find_product_by_raw_id($raw_id);
            if ($product_id) {
                set_post_thumbnail($product_id, $attachment_id);
            }
            
            $image = wp_get_attachment_image($attachment_id, 'thumbnail');
            $message = __('Image associated successfully', 'woo-csv-importer-lite');
        } else if ($action === 'remove') {
            // Remove association
            if (isset($image_mappings[$raw_id])) {
                unset($image_mappings[$raw_id]);
                
                // Remove product image if product exists
                $product_id = self::find_product_by_raw_id($raw_id);
                if ($product_id) {
                    delete_post_thumbnail($product_id);
                }
            }
            
            $image = '';
            $message = __('Image removed successfully', 'woo-csv-importer-lite');
        } else {
            wp_send_json_error(array('message' => __('Invalid action', 'woo-csv-importer-lite')));
        }
        
        // Save updated mappings
        update_option('woo_csv_image_mappings', $image_mappings);
        
        wp_send_json_success(array(
            'message' => $message,
            'image' => $image
        ));
    }
    
    /**
     * Find a product by its raw ID
     * 
     * @param string $raw_id The raw ID to search for
     * @return int|false Product ID if found, false otherwise
     */
    private static function find_product_by_raw_id($raw_id) {
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
     * Process bulk image upload from ZIP
     */
    public static function process_bulk_upload() {
        // Check nonce for security
        check_admin_referer('woo_csv_bulk_upload', 'woo_csv_bulk_upload_nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-csv-importer-lite'));
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('Error uploading ZIP file. Please try again.', 'woo-csv-importer-lite'));
        }
        
        // Get the ZIP file
        $zip_file = $_FILES['zip_file']['tmp_name'];
        
        // Check if it's a ZIP file
        $file_info = pathinfo($_FILES['zip_file']['name']);
        if ($file_info['extension'] !== 'zip') {
            wp_die(__('Please upload a valid ZIP file.', 'woo-csv-importer-lite'));
        }
        
        // Create temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/csv-importer-temp-' . time();
        wp_mkdir_p($temp_dir);
        
        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            wp_die(__('Could not open the ZIP file.', 'woo-csv-importer-lite'));
        }
        
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Process images
        $stats = array(
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0
        );
        
        $image_mappings = get_option('woo_csv_image_mappings', array());
        
        // Find all image files
        $files = glob($temp_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            
            // Use filename as raw_id
            $raw_id = $filename;
            
            // Upload to media library
            $attachment_id = self::upload_to_media_library($file, $raw_id);
            
            if ($attachment_id) {
                $image_mappings[$raw_id] = $attachment_id;
                
                // Update product image if product exists
                $product_id = self::find_product_by_raw_id($raw_id);
                if ($product_id) {
                    set_post_thumbnail($product_id, $attachment_id);
                }
                
                $stats['processed']++;
            } else {
                $stats['errors']++;
            }
        }
        
        // Save updated mappings
        update_option('woo_csv_image_mappings', $image_mappings);
        
        // Clean up temp directory
        WP_Filesystem();
        global $wp_filesystem;
        $wp_filesystem->rmdir($temp_dir, true);
        
        // Set up admin notice
        set_transient('woo_csv_bulk_image_results', $stats, 60);
        
        // Redirect back to the image manager page
        wp_redirect(admin_url('admin.php?page=woo-csv-importer-images'));
        exit;
    }
    
    /**
     * Upload a file to the WordPress Media Library
     * 
     * @param string $file Path to the file
     * @param string $raw_id The raw ID (used for attachment title)
     * @return int|false Attachment ID if successful, false otherwise
     */
    private static function upload_to_media_library($file, $raw_id) {
        // Check if file exists
        if (!file_exists($file)) {
            return false;
        }
        
        // Get file information
        $file_name = basename($file);
        $file_type = wp_check_filetype($file_name, null);
        
        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => "Product Image - " . $raw_id,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert the attachment
        $attach_id = wp_insert_attachment($attachment, $file);
        
        // Generate metadata for the attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Add raw_id as meta for reference
        update_post_meta($attach_id, '_woo_csv_raw_id', $raw_id);
        
        return $attach_id;
    }
    
    /**
     * Use Media Library image instead of file system image
     * 
     * @param bool $result The original result
     * @param array $args Arguments (raw_id and product_id)
     * @return bool Success or failure
     */
    public static function use_media_library_image($result, $args) {
        $raw_id = $args['raw_id'];
        $product_id = $args['product_id'];
        
        // Get the image mappings
        $image_mappings = get_option('woo_csv_image_mappings', array());
        
        // Check if we have a mapping for this raw_id
        if (isset($image_mappings[$raw_id])) {
            $attachment_id = $image_mappings[$raw_id];
            
            // Set as product image
            set_post_thumbnail($product_id, $attachment_id);
            
            return true;
        }
        
        // If no mapping found, continue with original method
        return $result;
    }
}

// Add JavaScript for Media Library integration
function woo_csv_media_library_js() {
    ob_start();
    ?>
jQuery(document).ready(function($) {
    var file_frame;
    
    // Handle the Select Image button click
    $('.select-image').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var rawId = $button.data('raw-id');
        
        // If the media frame already exists, reopen it
        if (file_frame) {
            file_frame.open();
            return;
        }
        
        // Create a new media frame
        file_frame = wp.media({
            title: woo_csv_images.text.select_image,
            button: {
                text: woo_csv_images.text.use_this_image
            },
            multiple: false
        });
        
        // When an image is selected, run a callback
        file_frame.on('select', function() {
            var attachment = file_frame.state().get('selection').first().toJSON();
            
            // Set the status to "Saving..."
            $button.closest('tr').find('.status').text(woo_csv_images.text.saving);
            
            // Send AJAX request to associate the image
            $.post(woo_csv_images.ajax_url, {
                action: 'woo_csv_associate_images',
                nonce: woo_csv_images.nonce,
                raw_id: rawId,
                attachment_id: attachment.id,
                image_action: 'associate'
            }, function(response) {
                if (response.success) {
                    // Update the image preview
                    $button.closest('tr').find('.image-preview').html(response.data.image);
                    
                    // Add Remove button if it doesn't exist
                    if ($button.siblings('.remove-image').length === 0) {
                        $button.after('<button type="button" class="button remove-image" data-raw-id="' + rawId + '">' + 
                                     '<?php _e('Remove', 'woo-csv-importer-lite'); ?>' + '</button>');
                    }
                    
                    // Set the status to "Saved!"
                    $button.closest('tr').find('.status').text(woo_csv_images.text.saved).fadeIn().delay(2000).fadeOut();
                } else {
                    // Show error message
                    $button.closest('tr').find('.status').text(woo_csv_images.text.error + ': ' + response.data.message);
                }
            }).fail(function() {
                // Show error message
                $button.closest('tr').find('.status').text(woo_csv_images.text.error);
            });
        });
        
        // Open the frame
        file_frame.open();
    });
    
    // Handle the Remove Image button click
    $(document).on('click', '.remove-image', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var rawId = $button.data('raw-id');
        
        // Set the status to "Saving..."
        $button.closest('tr').find('.status').text(woo_csv_images.text.saving);
        
        // Send AJAX request to remove the association
        $.post(woo_csv_images.ajax_url, {
            action: 'woo_csv_associate_images',
            nonce: woo_csv_images.nonce,
            raw_id: rawId,
            image_action: 'remove'
        }, function(response) {
            if (response.success) {
                // Update the image preview
                $button.closest('tr').find('.image-preview').html('<?php _e('No image', 'woo-csv-importer-lite'); ?>');
                
                // Remove the button
                $button.remove();
                
                // Set the status to "Saved!"
                $button.closest('tr').find('.status').text(woo_csv_images.text.saved).fadeIn().delay(2000).fadeOut();
            } else {
                // Show error message
                $button.closest('tr').find('.status').text(woo_csv_images.text.error + ': ' + response.data.message);
            }
        }).fail(function() {
            // Show error message
            $button.closest('tr').find('.status').text(woo_csv_images.text.error);
        });
    });
});
    <?php
    return ob_get_clean();
}

// Add styles for the image manager page
function woo_csv_media_library_css() {
    ob_start();
    ?>
.image-manager-table .image-preview {
    width: 100px;
}

.image-manager-table .image-preview img {
    max-width: 80px;
    max-height: 80px;
}

.image-manager-table .status {
    display: inline-block;
    margin-left: 10px;
    font-style: italic;
}

.bulk-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
    <?php
    return ob_get_clean();
}

// Initialize the integration
WOO_CSV_Importer_Lite_Media::init();

// Add action for bulk image upload
add_action('admin_post_woo_csv_bulk_upload_images', array('WOO_CSV_Importer_Lite_Media', 'process_bulk_upload'));

// Filter the main plugin's image processing
add_filter('woo_csv_process_product_image', function($result, $raw_id, $product_id) {
    return apply_filters('woo_csv_process_product_image', $result, array(
        'raw_id' => $raw_id,
        'product_id' => $product_id
    ));
}, 10, 3);

// Modify the main plugin's process_product_image method to use our filter
function modify_main_plugin_class() {
    global $woo_csv_importer_lite;
    
    if (isset($woo_csv_importer_lite) && method_exists($woo_csv_importer_lite, 'process_product_image')) {
        // Store original method
        $woo_csv_importer_lite->original_process_product_image = $woo_csv_importer_lite->process_product_image;
        
        // Replace with our version that checks for media library images first
        $woo_csv_importer_lite->process_product_image = function($raw_id, $product_id) use ($woo_csv_importer_lite) {
            // Apply filter first (to check media library)
            $result = apply_filters('woo_csv_process_product_image', false, array(
                'raw_id' => $raw_id,
                'product_id' => $product_id
            ));
            
            // If filter didn't handle it, use original method
            if (!$result) {
                return $woo_csv_importer_lite->original_process_product_image($raw_id, $product_id);
            }
            
            return $result;
        };
    }
}
add_action('plugins_loaded', 'modify_main_plugin_class', 20);

// Create JS and CSS files
function woo_csv_create_assets() {
    // Get upload directory
    $upload_dir = wp_upload_dir();
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Create js directory if it doesn't exist
    if (!file_exists($plugin_dir . 'js')) {
        wp_mkdir_p($plugin_dir . 'js');
    }
    
    // Create css directory if it doesn't exist
    if (!file_exists($plugin_dir . 'css')) {
        wp_mkdir_p($plugin_dir . 'css');
    }
    
    // Write JS file
    file_put_contents($plugin_dir . 'js/image-manager.js', woo_csv_media_library_js());
    
    // Write CSS file
    file_put_contents($plugin_dir . 'css/image-manager.css', woo_csv_media_library_css());
}
register_activation_hook(__FILE__, 'woo_csv_create_assets');