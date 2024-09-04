<?php
/*
Plugin Name: Breakdance Image Alt Updater
Plugin URI: https://spidawerx.com
Description: Updates alt tags for images used in Breakdance layouts.
Version: 1.0
Author: Spidawerx
Author URI: https://spidawerx.com
Text Domain: breakdance-image-alt-updater
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
<?php
// Hook into the admin menu
add_action('admin_menu', 'bddev_add_settings_page');

function bddev_add_settings_page() {
    add_options_page(
        'Breakdance Image Alt Updater', // Page title
        'BD Image Alt Updater',         // Menu title
        'manage_options',               // Capability
        'bd-image-alt-updater',         // Menu slug
        'bddev_render_settings_page'    // Callback function
    );
}

function bddev_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Breakdance Image Alt Updater</h1>
        <form method="post" action="" id="bd-update-form">
            <?php
            wp_nonce_field('bddev_update_alt_nonce', 'bddev_update_alt_nonce_field');
            ?>
            <p>
                <label>
                    <input type="checkbox" id="backup-confirmation" />
                    I have created a backup of my database
                </label>
            </p>
            <p>Click the button below to update the alt tags for images in the Breakdance meta data.</p>
            <input type="submit" name="update_alt_tags" class="button-primary" id="update-button" value="Update Image Alt Tags" disabled />
        </form>
    </div>
    <script>
        document.getElementById('backup-confirmation').addEventListener('change', function() {
            document.getElementById('update-button').disabled = !this.checked;
        });
    </script>
    <?php
}

add_action('admin_init', 'bddev_check_update_alt_tags');

function bddev_check_update_alt_tags() {
    if (isset($_POST['update_alt_tags']) && check_admin_referer('bddev_update_alt_nonce', 'bddev_update_alt_nonce_field')) {
        updateImageAltTagsInBreakdanceData();
        add_action('admin_notices', 'bddev_update_success_notice');
    }
}

function bddev_update_success_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p>Image alt tags updated successfully!</p>
    </div>
    <?php
}

function updateImageAltTagsInBreakdanceData() {
    global $wpdb;

    $metaKey = '_breakdance_data';
    $imageMetaKey = '_wp_attachment_image_alt';

    $postMetaData = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s",
            $metaKey
        )
    );

    foreach ($postMetaData as $metaData) {
        $metaValue = json_decode($metaData->meta_value, true);

        if (isset($metaValue['tree_json_string'])) {
            $treeData = json_decode($metaValue['tree_json_string'], true);

            if ($treeData) {
                updateAltTagsRecursively($treeData['root'], $imageMetaKey, $wpdb);

                $metaValue['tree_json_string'] = json_encode($treeData);
                update_post_meta($metaData->post_id, $metaKey, wp_slash(json_encode($metaValue)));
            }
        }
    }
}

function updateAltTagsRecursively(&$node, $imageMetaKey, $wpdb) {
    // Check for Gallery element
    if (!empty($node['data']['type']) && $node['data']['type'] === 'EssentialElements\\Gallery') {
        if (!empty($node['data']['properties']['content']['content']['images'])) {
            foreach ($node['data']['properties']['content']['content']['images'] as &$image) {
                updateImageAlt($image['image'], $imageMetaKey, $wpdb);
            }
        }
    }

    // Check for Image element
    if (!empty($node['data']['properties']['content']['content']['image'])) {
        updateImageAlt($node['data']['properties']['content']['content']['image'], $imageMetaKey, $wpdb);
    }

    // Check for ImageBox element
    if (!empty($node['data']['type']) && $node['data']['type'] === 'EssentialElements\\ImageBox') {
        if (!empty($node['data']['properties']['content']['content']['image'])) {
            updateImageAlt($node['data']['properties']['content']['content']['image'], $imageMetaKey, $wpdb);
        }
    }

    // Check for ImageHoverCard element
    if (!empty($node['data']['type']) && $node['data']['type'] === 'EssentialElements\\ImageHoverCard') {
        if (!empty($node['data']['properties']['content']['image']['image'])) {
            updateImageAlt($node['data']['properties']['content']['image']['image'], $imageMetaKey, $wpdb);
        }
    }

    // Check for ImageWithZoom element
    if (!empty($node['data']['type']) && $node['data']['type'] === 'EssentialElements\\ImageWithZoom') {
        if (!empty($node['data']['properties']['content']['controls']['image'])) {
            updateImageAlt($node['data']['properties']['content']['controls']['image'], $imageMetaKey, $wpdb);
        }
    }

    // Check for ImageAccordion element
    if (!empty($node['data']['type']) && $node['data']['type'] === 'EssentialElements\\ImageAccordion') {
        if (!empty($node['data']['properties']['content']['content']['images'])) {
            foreach ($node['data']['properties']['content']['content']['images'] as &$accordionImage) {
                if (!empty($accordionImage['image'])) {
                    updateImageAlt($accordionImage['image'], $imageMetaKey, $wpdb);
                }
            }
        }
    }

    // Recursively process children
    if (!empty($node['children'])) {
        foreach ($node['children'] as &$child) {
            updateAltTagsRecursively($child, $imageMetaKey, $wpdb);
        }
    }
}

function updateImageAlt(&$image, $imageMetaKey, $wpdb) {
    if (!empty($image['id'])) {
        $imageId = $image['id'];
        $newAltText = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s",
                $imageId,
                $imageMetaKey
            )
        );
        if ($newAltText !== null && $newAltText !== '') {
            $image['alt'] = $newAltText;
        } else {
            // Remove the 'alt' key if the new alt text is empty or null
            unset($image['alt']);
        }
    }
}
