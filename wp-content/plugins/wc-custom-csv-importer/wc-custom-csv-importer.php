<?php
/*
Plugin Name: WooCommerce Custom CSV Importer
Description: Import and update WooCommerce products from a custom CSV file.
Version: 1.0
Author: Your Name
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define constants for the image URL base and prefix (assuming you will set the prefix dynamically or via settings).
define('WC_CSV_IMPORTER_IMAGE_BASE', site_url('/assets/product-images/'));
define('WC_CSV_IMPORTER_SKU_PREFIX', ''); // set this to your contact number prefix as needed

// Create a mapping array to match CSV columns to WooCommerce and custom fields.
function wc_csv_importer_get_mapping() {
    return array(
        'Code'              => 'code',              // This is used to build SKU: {prefix}{code}
        'Description'       => 'name',              // Product title
        'Description 2'     => 'short_description', // Product short description (or full description if preferred)
        'Qty'               => 'stock',             // Stock quantity
        'Size'              => 'size',              // Custom field: size attribute
        'KG'                => 'weight',            // Product weight
        'Price [C]'         => 'price',             // Product price
        'VAT'               => 'vat',               // Custom meta: VAT value
        'UNIT1'             => 'unit1',             // Custom meta
        'UNIT2'             => 'unit2',             // Custom meta
        'Barcode1 [E]'      => 'barcode1',          // Custom meta: primary barcode
        'Barcode3 [E]'      => 'barcode3',          // Custom meta
        'Barcode2 [C]'      => 'barcode2',          // Custom meta
        'Barcode4 [C]'      => 'barcode4',          // Custom meta
        '[A]'               => 'a_field',           // Custom meta field A
        '[S]'               => 's_field',           // Custom meta field S
        'Cat.Code'          => 'category_code',     // Possibly used to assign product categories
        'TYPE'              => 'type',              // Custom meta: product type
        'LC'                => 'lc',                // Custom meta
        'COMMENTS'          => 'comments',          // Custom meta: comments
        'Filter Object'     => 'filter_object',     // Custom meta: filter criteria
        'Min Stock'         => 'min_stock',         // Custom meta: minimum stock level
        'Pallet'            => 'pallet',            // Custom meta
        'Item Type'         => 'item_type',         // Custom meta
        'Shelf Life'        => 'shelf_life',        // Custom meta: shelf life details
        'Notes'             => 'notes',             // Custom meta: additional notes
        'P.Mark'            => 'p_mark',            // Custom meta: mark
        'Commodity Code'    => 'commodity_code',    // Custom meta: commodity code
        'Country'           => 'country',           // Custom meta: country of origin
        'COMMENTS2'         => 'comments2',         // Additional comments
        'COMMENTS3'         => 'comments3',         // Additional comments
        'COMMENTS4'         => 'comments4',         // Additional comments
        'COMMENTS5'         => 'comments5',         // Additional comments
        'Created at'        => 'created_at',        // Record created time
        'Updated at'        => 'updated_at',        // Record updated time
    );
}

// Register the admin menu with a custom icon.
add_action('admin_menu', 'wc_csv_importer_register_admin_menu');
function wc_csv_importer_register_admin_menu() {
    add_menu_page(
        'CSV Importer',                  // Page title
        'CSV Importer',                  // Menu title
        'manage_options',                // Capability
        'wc-csv-importer',               // Menu slug
        'wc_csv_importer_admin_page',    // Callback function
        'dashicons-upload',              // Icon from Dashicons (change as needed)
        56                               // Position in menu
    );
}

// The main admin page content
function wc_csv_importer_admin_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce CSV Importer</h1>
        <?php
        // Check if the form was submitted and process the CSV file.
        if (isset($_POST['upload_csv']) && check_admin_referer('wc_csv_importer', 'wc_csv_importer_nonce')) {
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $file = $_FILES['csv_file']['tmp_name'];
                // Process CSV file
                wc_csv_importer_process_csv_file($file);
            } else {
                echo '<div class="error notice"><p>Please upload a CSV file.</p></div>';
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wc_csv_importer', 'wc_csv_importer_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">CSV File</th>
                    <td>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </td>
                </tr>
            </table>
            <?php submit_button('Upload CSV', 'primary', 'upload_csv'); ?>
        </form>
    </div>
    <?php
}

// Process the CSV file: Parse, insert/update, and then build a comparison table.
function wc_csv_importer_process_csv_file($file) {
    $mapping = wc_csv_importer_get_mapping();
    $new_data = array(); // To store new data from CSV keyed by product code.
    $log_messages = array(); // To store logs for this run.
    if (($handle = fopen($file, "r")) !== FALSE) {
        
        
        $header = fgetcsv($handle, 1000, ",");
               
        // Normalize header names: trim and remove extra spaces.
        $header = array_map('trim', $header);
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $csv_row = array_combine($header, $row);
            // Map CSV row to our fields using our mapping array.
            $mapped_row = array();
            foreach ($mapping as $csv_field => $target_field) {
                $mapped_row[$target_field] = isset($csv_row[$csv_field]) ? trim($csv_row[$csv_field]) : '';
            }
            // Build image URL based on the 'code' field.
            $mapped_row['image_url'] = WC_CSV_IMPORTER_IMAGE_BASE . $mapped_row['code'] . '.png';
            
            // Build SKU using prefix and code.
            $mapped_row['sku'] = WC_CSV_IMPORTER_SKU_PREFIX . $mapped_row['code'];
            
            // Store by product code for later comparison.
            $new_data[$mapped_row['code']] = $mapped_row;
        }
        fclose($handle);
        
        // At this point, $new_data contains all CSV rows keyed by product code.
        // Next, you would compare $new_data with the existing WooCommerce products.
        // For example, query products by SKU (or by meta that holds the product code) and prepare a comparison array.
        // The following function is a placeholder for that comparison process.
        wc_csv_importer_display_comparison_table($new_data, $log_messages);
    } else {
        echo '<div class="error notice"><p>Unable to open the CSV file.</p></div>';
    }
}

// Display a comparison table between existing data and new CSV data.
// This function is a simplified example. You will need to query the existing products based on SKU or product code.
function wc_csv_importer_display_comparison_table($new_data, $log_messages) {
    // Retrieve existing products keyed by product code.
    // This is a simplified query: adjust the meta_query as needed to match your implementation.
    $existing_products = array(); // Example: [ '2000' => array('sku' => 'prefix2000', 'name' => 'Old Product', ...), ... ]
    
    // For demonstration, let's assume we retrieved some existing product data.
    // In practice, you would use WP_Query or get_posts() to retrieve these.
    // $existing_products = wc_csv_importer_get_existing_products();

    echo '<div class="wrap">';
    echo '<h2>Comparison Table</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>
            <th>Product Code</th>
            <th>Existing Data</th>
            <th>New Data</th>
          </tr></thead>';
    echo '<tbody>';
    

    var_dump($existing_products);
    die;
    // Loop through the new data to compare with existing data.
    foreach ($new_data as $code => $new_row) {
        // Check if the product exists.
        if (isset($existing_products[$code])) {
            $existing = $existing_products[$code];
            // Compare fields to see if there is any difference.
            // For simplicity, let's assume if the name is different then it's an update.
            $row_class = ($existing['name'] !== $new_row['name']) ? 'update-row' : 'no-change';
        } else {
            // New product.
            $existing = null;
            $row_class = 'new-product';
        }
        
        echo '<tr class="' . esc_attr($row_class) . '">';
        echo '<td>' . esc_html($code) . '</td>';
        echo '<td>';
        if ($existing) {
            echo 'SKU: ' . esc_html($existing['sku']) . '<br>';
            echo 'Name: ' . esc_html($existing['name']);
        } else {
            echo '<span style="color:red;">Missing</span>';
        }
        echo '</td>';
        echo '<td>';
        echo 'SKU: ' . esc_html($new_row['sku']) . '<br>';
        echo 'Name: ' . esc_html($new_row['name']);
        echo '</td>';
        echo '</tr>';
    }
    
    // Additionally, check for products in WooCommerce that are missing in the new CSV.
    // For each product in $existing_products not in $new_data, output a red row.
    foreach ($existing_products as $code => $existing) {
        if (!isset($new_data[$code])) {
            echo '<tr class="missing-product" style="background-color:#ffcccc;">';
            echo '<td>' . esc_html($code) . '</td>';
            echo '<td>';
            echo 'SKU: ' . esc_html($existing['sku']) . '<br>';
            echo 'Name: ' . esc_html($existing['name']);
            echo '</td>';
            echo '<td><span style="color:red;">Not found in CSV</span></td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Log notifications (here, simply outputting them).
    if (!empty($log_messages)) {
        echo '<h2>Log Messages</h2>';
        echo '<div class="log">';
        foreach ($log_messages as $log) {
            echo '<p>' . esc_html($log) . '</p>';
        }
        echo '</div>';
    }
    
    echo '</div>';
}
