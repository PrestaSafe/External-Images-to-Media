<?php
/**
 * Plugin Name: External Images to Media
 * Plugin URI: https://example.com
 * Description: Télécharge et convertit les images externes en médias WordPress et met à jour les URLs dans les posts.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: external-images-to-media
 * Domain Path: /languages
 */
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

function external_images_to_media() {
    if (isset($_POST['external_images_to_media_process']) && check_admin_referer('external_images_to_media_process')) {
         // Remplacez cette valeur par l'URL de votre site
            $site_url = get_site_url();

            // Sélectionner les posts de la table wp_posts
            $args = array(
                'post_type' => array('post', 'page'),
                'posts_per_page' => -1,
            );
            $query = new WP_Query($args);

            // Chercher les URLs externes dans le champ post_content
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $post_content = get_the_content();

                    // Utiliser une expression régulière pour chercher les URLs d'images
                    preg_match_all('/<img[^>]+src="([^">]+)"/i', $post_content, $matches);
                    $image_urls = $matches[1];

                    foreach ($image_urls as $image_url) {
                        // Vérifier si l'image est externe
                        if (strpos($image_url, $site_url) !== 0) {
                            // Télécharger l'image
                            $image_data = file_get_contents($image_url);
                            $filename = basename($image_url);

                            // Convertir l'image en media et obtenir la nouvelle URL
                            $new_image_url = convert_to_media_and_get_url($filename, $image_data);

                            // Remplacer l'ancienne URL par la nouvelle URL dans le champ post_content
                            $post_content = str_replace($image_url, $new_image_url, $post_content);
                        }
                    }

                    // Mettre à jour le champ post_content dans la base de données
                    $updated_post = array(
                        'ID' => $post_id,
                        'post_content' => $post_content,
                    );
                    wp_update_post($updated_post);
                }
            }
    }
}

function convert_to_media_and_get_url($filename, $image_data) {
    // var_dump($filename);
    // var_dump($image_data);
    // die();
    // Télécharger l'image dans le dossier des médias de WordPress
    $upload = wp_upload_bits($filename, null, $image_data);

    if (!$upload['error']) {
        $file_path = $upload['file'];
        $file_url = $upload['url'];

        // Obtenir le type MIME de l'image
        $file_type = wp_check_filetype($file_path);

        // Préparer les arguments pour wp_insert_attachment()
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insérer l'image en tant qu'attachement et générer les métadonnées
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);

        // Mettre à jour les métadonnées de l'attachement
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        // Retourner la nouvelle URL de l'image
        return $file_url;
    } else {
        // Gérer l'erreur d'upload
        return null;
    }
}


function external_images_to_media_admin_menu() {
    add_menu_page(
        'External Images to Media',
        'External Images to Media',
        'manage_options',
        'external-images-to-media',
        'external_images_to_media_admin_page',
        'dashicons-images-alt2'
    );
}
add_action('admin_menu', 'external_images_to_media_admin_menu');

function external_images_to_media_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('External Images to Media', 'external-images-to-media'); ?></h1>
        <p><?php _e('Click the button below to download and convert external images to WordPress media and update the URLs in the posts.', 'external-images-to-media'); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('external_images_to_media_process'); ?>
            <input type="submit" name="external_images_to_media_process" class="button button-primary" value="<?php _e('Process External Images', 'external-images-to-media'); ?>">
            <?php external_images_to_media(); ?> 
        </form>
    </div>
    <?php
}

// Retirez l'action d'enregistrement qui exécute le script lors de l'activation du plugin :
// register_activation_hook(__FILE__, 'external_images_to_media');
