<?php

add_action('admin_init', 'restrict_product_creation');
function restrict_product_creation() {
    global $pagenow;

    // Vérifier si l'utilisateur est sur la page d'édition ou d'ajout de produit
    if ($pagenow == 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
        // Si l'utilisateur tente de créer un nouveau produit
        wp_redirect(admin_url('edit.php?post_type=product'));
        exit;
    }

    if ($pagenow == 'post.php' && isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['post'])) {
        $post_id = intval($_GET['post']);
        $post_type = get_post_type($post_id);

        // S'assurer qu'il s'agit bien d'un produit
        if ($post_type == 'product') {
            // L'utilisateur peut éditer le produit existant
            return;
        }
    }
}

add_action('admin_notices', 'restrict_product_creation_notice');
function restrict_product_creation_notice() {
    global $pagenow;

    if ($pagenow == 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'product') {
        echo '<div class="error"><p>La création de nouveaux produits est désactivée. Veuillez modifier un produit existant.</p></div>';
    }
}