<?php
/**
 * Settings page for CSV Updater with Mapping Configuration
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get CSV mapper
$csv_mapper = new \Novano\CSVUpdater\CSV_Mapper(new \Novano\CSVUpdater\Logger());
$current_mapping = $csv_mapper->get_mapping();

// Handle mapping updates
if (isset($_POST['update_mapping']) && check_admin_referer('csv_updater_mapping_nonce')) {
    $new_mapping = $_POST['csv_mapping'] ?? [];
    $csv_mapper->save_mapping($new_mapping);
    $current_mapping = $csv_mapper->get_mapping();
}

// Get current settings
$options = get_option('csv_updater_options', [
    'log_retention_days' => 30,
    'max_products_per_batch' => 500,
    'image_width' => 400,
    'image_height' => 400,
]);

// Available CSV columns (dynamically fetch from the last uploaded CSV or predefined)
$sample_columns = [
    'code', 'description', 'description_2', 'p_mark', 'qty', 'kg', 
    'vat', 'size', 'unit1', 'unit2', 'item_type', 'shelf_life', 
    'country', 'cat_code'
];

// WooCommerce product fields
$wc_fields = [
    'product_id' => 'Product ID',
    'name' => 'Product Name',
    'description' => 'Product Description',
    'sku' => 'SKU',
    'regular_price' => 'Regular Price',
    'sale_price' => 'Sale Price',
    'stock_quantity' => 'Stock Quantity',
    'weight' => 'Weight',
    'tax_status' => 'Tax Status',
    'categories' => 'Categories',
    'attributes' => 'Attributes'
];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>CSV Column Mapping</h2>
        <form method="post" action="">
            <?php wp_nonce_field('csv_updater_mapping_nonce'); ?>
            
            <table class="form-table csv-mapping-table">
                <thead>
                    <tr>
                        <th>WooCommerce Field</th>
                        <th>CSV Column</th>
                        <th>Transformation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wc_fields as $wc_key => $wc_label): ?>
                        <tr>
                            <td><?php echo esc_html($wc_label); ?></td>
                            <td>
                                <select name="csv_mapping[<?php echo esc_attr($wc_key); ?>]">
                                    <option value="">-- Not Mapped --</option>
                                    <?php foreach ($sample_columns as $column): ?>
                                        <option value="<?php echo esc_attr($column); ?>" 
                                            <?php 
                                            if (isset($current_mapping[$wc_key]) && 
                                                (is_string($current_mapping[$wc_key]) && $current_mapping[$wc_key] === $column)) 
                                                echo 'selected'; 
                                            ?>>
                                            <?php echo esc_html($column); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php if ($wc_key === 'sku'): ?>
                                    <input type="text" name="csv_mapping[<?php echo esc_attr($wc_key); ?>][prefix]" 
                                           placeholder="Prefix (e.g., EAP)" 
                                           value="<?php 
                                           echo isset($current_mapping[$wc_key]['prefix']) 
                                               ? esc_attr($current_mapping[$wc_key]['prefix']) 
                                               : ''; 
                                           ?>">
                                <?php elseif ($wc_key === 'tax_status'): ?>
                                    <select name="csv_mapping[<?php echo esc_attr($wc_key); ?>][transform]">
                                        <option value="">-- No Transformation --</option>
                                        <option value="tax_status_map" 
                                            <?php 
                                            echo (isset($current_mapping[$wc_key]['transform']) && 
                                                   $current_mapping[$wc_key]['transform'] === 'tax_status_map') 
                                                ? 'selected' : ''; 
                                            ?>>
                                            Map VAT to Tax Status
                                        </option>
                                    </select>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php submit_button('Save Mapping', 'primary', 'update_mapping'); ?>
        </form>
        
        <div class="mapping-help">
            <h3>Mapping Instructions</h3>
            <ul>
                <li>Select the corresponding CSV column for each WooCommerce field.</li>
                <li>Use the transformation options for special column handling.</li>
                <li>Leave a field unmapped if you don't want to import that specific data.</li>
            </ul>
        </div>
    </div>

    <!-- Rest of the existing settings remain the same -->
</div>

<style>
.csv-mapping-table {
    width: 100%;
}
.csv-mapping-table th, 
.csv-mapping-table td {
    padding: 10px;
    border: 1px solid #ddd;
}
.mapping-help {
    margin-top: 20px;
    background-color: #f4f4f4;
    padding: 15px;
    border-radius: 5px;
}
.mapping-help ul {
    list-style-type: disc;
    padding-left: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Optional: Add dynamic mapping UI interactions
    $('.csv-mapping-table select').on('change', function() {
        // Prevent same column from being mapped multiple times
        var selectedValue = $(this).val();
        $('.csv-mapping-table select').not(this).each(function() {
            $(this).find('option[value="' + selectedValue + '"]').prop('disabled', true);
        });
    });
});
</script>