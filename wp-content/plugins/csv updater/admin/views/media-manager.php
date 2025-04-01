<?php
/**
 * Media Manager page for CSV Updater
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get raw images directory
$raw_images_dir = WP_CONTENT_DIR . '/uploads/raw-images';
$raw_images = glob($raw_images_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>Upload Raw Images</h2>
        <form id="raw-image-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('raw_image_upload', 'raw_image_nonce'); ?>
            
            <input type="file" name="raw_images[]" id="raw_images" multiple accept="image/*">
            <?php submit_button('Upload Images', 'primary', 'upload_raw_images'); ?>
        </form>
    </div>

    <div class="card">
        <h2>Raw Images (<?php echo count($raw_images); ?>)</h2>
        <div id="raw-images-grid" class="image-grid">
            <?php if (empty($raw_images)): ?>
                <p>No raw images found.</p>
            <?php else: ?>
                <?php foreach ($raw_images as $image): 
                    $filename = basename($image);
                    $image_url = content_url('/uploads/raw-images/' . $filename);
                ?>
                    <div class="image-item">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($filename); ?>">
                        <div class="image-actions">
                            <span><?php echo esc_html($filename); ?></span>
                            <button class="button delete-image" data-filename="<?php echo esc_attr($filename); ?>">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.image-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.image-item {
    width: 200px;
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
}
.image-item img {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
}
.image-actions {
    margin-top: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#raw-image-upload-form').on('submit', function(e) {
        e.preventDefault();
        // AJAX image upload will be implemented later
        console.log('Image upload form submitted');
    });

    $('.delete-image').on('click', function() {
        const filename = $(this).data('filename');
        // AJAX image deletion will be implemented later
        console.log('Delete image:', filename);
    });
});
</script>