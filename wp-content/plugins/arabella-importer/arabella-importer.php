<?php
/**
 * Plugin Name: Arabella Importer
 * Description: Imports and updates WooCommerce products from a CSV file, manages media, and provides logging.
 * Version: 1.1
 * Author: Novano
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/*-------------------------------------------
  1. Register Admin Menus
-------------------------------------------*/
add_action('admin_menu', 'arabella_importer_create_menu');
function arabella_importer_create_menu()
{
    add_menu_page('Arabella Importer', 'Arabella Importer', 'manage_options', 'arabella-importer', 'arabella_importer_dashboard', 'dashicons-upload');
    add_submenu_page('arabella-importer', 'Media Manager', 'Media Manager', 'manage_options', 'arabella-media-manager', 'arabella_media_manager_page');
    add_submenu_page('arabella-importer', 'Settings', 'Settings', 'manage_options', 'arabella-settings', 'arabella_settings_page');
}

/*-------------------------------------------
  2. CSV Import Page
-------------------------------------------*/
function arabella_importer_dashboard()
{
    echo '<h1>Update Products</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('arabella_csv_upload', 'arabella_nonce');
    echo '<input type="file" name="arabella_csv" accept=".csv" required>';
    echo '<input type="submit" name="submit_csv" value="Upload CSV">';
    echo '</form>';

    if (isset($_POST['submit_csv']) && check_admin_referer('arabella_csv_upload', 'arabella_nonce')) {
        arabella_process_csv($_FILES['arabella_csv']['tmp_name']);
    }
}

function arabella_process_csv($csv_file)
{
    if (($handle = fopen($csv_file, 'r')) !== false) {
        $headers = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            arabella_process_row(array_combine($headers, $row));
        }
        fclose($handle);
        echo '<div class="updated"><p>CSV processed successfully!</p></div>';
    }
}

/*-------------------------------------------
  3. Media Manager
-------------------------------------------*/
function arabella_media_manager_page()
{
    echo '<div class="wrap"><h1>Media Manager</h1>';
    echo '<div class="wp-media-grid">';
    $source_folder = WP_CONTENT_DIR . '/upload/product-images/';
    if (is_dir($source_folder)) {
        $files = array_diff(scandir($source_folder), array('.', '..'));
        foreach ($files as $file) {
            $file_url = content_url('/upload/product-images/' . $file);
            echo '<div class="media-item"><img src="' . esc_url($file_url) . '" style="width:150px;height:auto;"><p>' . esc_html($file) . '</p></div>';
        }
    }
    echo '</div></div>';
}

/*-------------------------------------------
  4. Settings Page
-------------------------------------------*/
function arabella_settings_page()
{
    if (isset($_POST['arabella_save_settings']) && check_admin_referer('arabella_save_settings_nonce', 'arabella_save_settings')) {
        update_option('arabella_logging_enabled', isset($_POST['logging_enabled']) ? 1 : 0);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    echo '<h1>Arabella Importer Settings</h1><form method="post">';
    wp_nonce_field('arabella_save_settings_nonce', 'arabella_save_settings');
    echo '<label>Enable Logging: <input type="checkbox" name="logging_enabled" ' . checked(get_option('arabella_logging_enabled', 0), 1, false) . '></label>';
    echo '<input type="submit" name="arabella_save_settings" value="Save Settings">';
    echo '</form>';
}

/*-------------------------------------------
  5. CSV Processing & Product Handling
-------------------------------------------*/
function arabella_process_row($data)
{
    if (empty($data['rawid'])) return;
    $rawid = sanitize_text_field($data['rawid']);
    
    $product_id = arabella_find_product_by_rawid($rawid);
    if ($product_id) {
        wp_update_post(['ID' => $product_id, 'post_title' => $data['title'], 'post_content' => $data['description']]);
    } else {
        $product_id = wp_insert_post(['post_title' => $data['title'], 'post_content' => $data['description'], 'post_status' => 'publish', 'post_type' => 'product']);
        update_post_meta($product_id, '_arabella_rawid', $rawid);
    }
    arabella_process_image($rawid, $product_id);
}

function arabella_find_product_by_rawid($rawid)
{
    $args = ['post_type' => 'product', 'meta_query' => [['key' => '_arabella_rawid', 'value' => $rawid]]];
    $products = get_posts($args);
    return !empty($products) ? $products[0]->ID : false;
}

/*-------------------------------------------
  6. Image Processing
-------------------------------------------*/
function arabella_process_image($rawid, $product_id)
{
    $source = WP_CONTENT_DIR . "/upload/product-images/{$rawid}.jpg";
    if (!file_exists($source)) return;
    $target_folder = WP_CONTENT_DIR . "/upload/products/{$product_id}/";
    if (!file_exists($target_folder)) mkdir($target_folder, 0755, true);
    $target = $target_folder . "{$rawid}.jpg";
    copy($source, $target);
}
