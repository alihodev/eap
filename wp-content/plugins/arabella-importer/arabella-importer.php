<?php
/**
 * Plugin Name: Arabella Importer
 * Description: Imports and updates WooCommerce products from a custom CSV, manages media, and provides logging. If an image doesn't exist, it skips the image transfer.
 * Version: 1.0
 * Author: Novano
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*-------------------------------------------
  1. Register Admin Menus and Subpages
-------------------------------------------*/
add_action( 'admin_menu', 'arabella_importer_create_menu' );
function arabella_importer_create_menu() {
    add_menu_page(
        'Arabella Importer',
        'Arabella Importer',
        'manage_options',
        'arabella-importer',
        'arabella_importer_dashboard',
        'dashicons-upload'
    );
    add_submenu_page(
        'arabella-importer',
        'Update Products',
        'Update Products',
        'manage_options',
        'arabella-importer',
        'arabella_importer_dashboard'
    );
    add_submenu_page(
        'arabella-importer',
        'Media Manager',
        'Media Manager',
        'manage_options',
        'arabella-media-manager',
        'arabella_media_manager_page'
    );
    add_submenu_page(
        'arabella-importer',
        'Settings',
        'Settings',
        'manage_options',
        'arabella-settings',
        'arabella_settings_page'
    );
}

/*-------------------------------------------
  2. Admin Page Callbacks
-------------------------------------------*/ 
function arabella_importer_dashboard() {
    echo '<h1>Update Products</h1>';
    echo '<p>Upload your CSV file to update/create products.</p>';
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'arabella_csv_upload', 'arabella_nonce' ); ?>
        <input type="file" name="arabella_csv" accept=".csv" required>
        <input type="submit" name="submit_csv" value="Upload CSV">
    </form>
    <?php

    // Process CSV file on form submission.
    if ( isset( $_POST['submit_csv'] ) && check_admin_referer( 'arabella_csv_upload', 'arabella_nonce' ) ) {
        if ( ! empty( $_FILES['arabella_csv']['tmp_name'] ) ) {
            $csv_file = $_FILES['arabella_csv']['tmp_name'];
            if ( ( $handle = fopen( $csv_file, 'r' ) ) !== false ) {
                $headers = fgetcsv( $handle );
                while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                    $data = array_combine( $headers, $row );
                    arabella_importer_process_row( $data );
                }
                fclose( $handle );
                echo '<div class="updated"><p>CSV processed successfully!</p></div>';
            } else {
                echo '<div class="error"><p>Could not open CSV file.</p></div>';
            }
        }
    }
}

function arabella_media_manager_page() {
    echo '<h1>Media Manager</h1>';
    // Display thumbnails from the source folder.
    $source_folder = WP_CONTENT_DIR . '/upload/product-images/';
    if ( is_dir( $source_folder ) ) {
        $files = scandir( $source_folder );
        foreach ( $files as $file ) {
            if ( in_array( $file, array( '.', '..' ) ) ) {
                continue;
            }
            $file_url = content_url( '/upload/product-images/' . $file );
            echo '<div style="display:inline-block;margin:10px;">';
            echo '<img src="' . esc_url( $file_url ) . '" style="max-width:150px;height:auto;">';
            echo '<p>' . esc_html( $file ) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<p>No media found.</p>';
    }
    // You can expand this page to handle multi-file uploads and deletions.
}

function arabella_settings_page() {
    if ( isset( $_POST['arabella_save_settings'] ) && check_admin_referer( 'arabella_save_settings_nonce', 'arabella_save_settings' ) ) {
        update_option( 'arabella_image_width', intval( $_POST['image_width'] ) );
        update_option( 'arabella_image_height', intval( $_POST['image_height'] ) );
        update_option( 'arabella_csv_mapping', wp_unslash( $_POST['csv_mapping'] ) );
        update_option( 'arabella_logging_enabled', isset( $_POST['logging_enabled'] ) ? 1 : 0 );
        update_option( 'arabella_debug_enabled', isset( $_POST['debug_enabled'] ) ? 1 : 0 );
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    $width         = get_option( 'arabella_image_width', 400 );
    $height        = get_option( 'arabella_image_height', 400 );
    $csv_mapping   = get_option( 'arabella_csv_mapping', '{"title": "csv_title", "description": "csv_description"}' );
    $logging       = get_option( 'arabella_logging_enabled', 0 );
    $debug_enabled = get_option( 'arabella_debug_enabled', 0 );
    ?>
    <h1>Arabella Importer Settings</h1>
    <form method="post">
        <?php wp_nonce_field( 'arabella_save_settings_nonce', 'arabella_save_settings' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="image_width">Image Width</label></th>
                <td><input type="number" name="image_width" id="image_width" value="<?php echo esc_attr( $width ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="image_height">Image Height</label></th>
                <td><input type="number" name="image_height" id="image_height" value="<?php echo esc_attr( $height ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="csv_mapping">CSV Mapping (JSON)</label></th>
                <td><textarea name="csv_mapping" id="csv_mapping" rows="5" cols="50"><?php echo esc_textarea( $csv_mapping ); ?></textarea></td>
            </tr>
            <tr>
                <th scope="row">Enable Logging</th>
                <td><input type="checkbox" name="logging_enabled" <?php checked( $logging, 1 ); ?>></td>
            </tr>
            <tr>
                <th scope="row">Enable Debug Mode</th>
                <td><input type="checkbox" name="debug_enabled" <?php checked( $debug_enabled, 1 ); ?>></td>
            </tr>
        </table>
        <input type="submit" name="arabella_save_settings" class="button-primary" value="Save Settings">
    </form>
    <?php
}

/*-------------------------------------------
  3. CSV Import Process and Product Handling
-------------------------------------------*/
function arabella_importer_process_row( $data ) {
    // Ensure rawid is provided.
    if ( empty( $data['rawid'] ) ) {
        arabella_importer_log( "Missing rawid in CSV row.", true );
        return;
    }
    $rawid = $data['rawid'];

    // Retrieve CSV mapping settings (stored as JSON).
    $mapping = json_decode( get_option( 'arabella_csv_mapping', '{}' ), true );
    if ( empty( $mapping ) ) {
        arabella_importer_log( "CSV mapping not set. Skipping row with rawid: $rawid", true );
        return;
    }

    // Map CSV data to WooCommerce fields.
    $product_data = array();
    foreach ( $mapping as $wc_field => $csv_field ) {
        if ( isset( $data[ $csv_field ] ) ) {
            $product_data[ $wc_field ] = sanitize_text_field( $data[ $csv_field ] );
        }
    }

    // Check for an existing product using a custom meta key.
    $args = array(
        'post_type'  => 'product',
        'meta_query' => array(
            array(
                'key'   => '_arabella_rawid',
                'value' => $rawid,
            ),
        ),
    );
    $existing = get_posts( $args );

    if ( ! empty( $existing ) ) {
        $product_id = $existing[0]->ID;
        $update_post = array(
            'ID'           => $product_id,
            'post_title'   => $product_data['title'],
            'post_content' => $product_data['description'],
        );
        wp_update_post( $update_post );
        // Update additional WooCommerce meta as needed.
    } else {
        $new_product = array(
            'post_title'   => $product_data['title'],
            'post_content' => $product_data['description'],
            'post_status'  => 'publish',
            'post_type'    => 'product',
        );
        $product_id = wp_insert_post( $new_product );
        // Set product type and additional meta here.
    }

    // Save raw CSV row data as product meta.
    update_post_meta( $product_id, '_arabella_rawid', $rawid );
    update_post_meta( $product_id, '_arabella_raw_data', maybe_serialize( $data ) );

    arabella_importer_log( "Processed product with rawid: $rawid (Product ID: $product_id)" );

    // Process the product image.
    arabella_importer_process_image( $rawid, $product_id );
}

/*-------------------------------------------
  4. Image Processing
-------------------------------------------*/
function arabella_importer_process_image( $rawid, $product_id ) {
    $source = WP_CONTENT_DIR . "/upload/product-images/{$rawid}.jpg";
    // Check if image exists; if not, skip processing.
    if ( ! file_exists( $source ) ) {
        arabella_importer_log( "Image for rawid: $rawid does not exist. Skipping image transfer." );
        return;
    }

    $target_folder = WP_CONTENT_DIR . "/upload/products/{$product_id}/";
    if ( ! file_exists( $target_folder ) ) {
        mkdir( $target_folder, 0755, true );
    }
    $target = $target_folder . "{$rawid}.jpg";

    // Use WP image editor for resizing.
    $editor = wp_get_image_editor( $source );
    if ( ! is_wp_error( $editor ) ) {
        $width  = get_option( 'arabella_image_width', 400 );
        $height = get_option( 'arabella_image_height', 400 );
        $editor->resize( $width, $height, true );
        $saved = $editor->save( $target );
        if ( ! is_wp_error( $saved ) ) {
            arabella_importer_log( "Processed image for rawid: $rawid and product: $product_id" );
        } else {
            arabella_importer_log( "Error saving resized image for rawid: $rawid", true );
        }
    } else {
        arabella_importer_log( "Failed to load image editor for rawid: $rawid", true );
    }
}

/*-------------------------------------------
  5. Logging Functionality
-------------------------------------------*/
function arabella_importer_log( $message, $error = false ) {
    if ( get_option( 'arabella_logging_enabled', 0 ) ) {
        $log_file = WP_CONTENT_DIR . '/upload/arabella_importer.log';
        $entry = date( 'Y-m-d H:i:s' ) . ( $error ? ' [ERROR] ' : ' ' ) . $message . "\n";
        file_put_contents( $log_file, $entry, FILE_APPEND );
    }
}
