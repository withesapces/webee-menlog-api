<?php

add_action('woocommerce_checkout_process', 'send_order_data_to_api');

function send_order_data_to_api() {
    // Vérifier le nonce pour la sécurité
    check_ajax_referer('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');

    $cart = WC()->cart;
    $customer = WC()->customer;

    $pickup_date = WC()->session->get('pickup_date');
    $pickup_time = WC()->session->get('pickup_time');

    $data = array(
        "account" => "{{refclient}}",
        "location" => "Plateforme Ecommerce",
        "source" => "ecommerce",
        "status" => "PENDING", // Nous utilisons PENDING car la commande n'est pas encore confirmée
        "orderType" => "PICKUP",
        "created_at" => current_time('mysql'),
        "pickupTime" => null, // Date de retrait de la commande par un livreur.
        "deliveryTime" => isset($pickup_time) && isset($pickup_date) ? $pickup_date . $pickup_time : '', // Date de livraison/retrait en magasin de la commande.
        "customer" => array(
            "name" => $customer->get_first_name() . ' ' . $customer->get_last_name(),
            "phone" => $customer->get_billing_phone(),
            "email" => $customer->get_billing_email(),
        ),
        "orderTotal" => array(
            "subtotal" => floatval($cart->get_subtotal()),
            "discount" => floatval($cart->get_discount_total()),
            "tax" => floatval($cart->get_total_tax()),
            "deliveryFee" => floatval($cart->get_shipping_total()),
            "montant" => floatval(WC()->cart->get_total('')),
        ),
        "payments" => array(
            array(
                "typereg" => "5",
                "montant" => floatval(WC()->cart->get_total('')),
            )
        ),
        "items" => array(),
    );

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();

        $product_type = 1; // Par défaut, 1 pour un produit simple
        $category = wp_get_post_terms($product_id, 'product_cat');
        $id_category = !empty($category) ? $category[0]->term_id : '';

        $item_data = array(
            "productType" => $product_type,
            "sku" => $product->get_sku(),
            "name" => $product->get_name(),
            "price" => $cart_item['line_total'],
            "quantity" => $cart_item['quantity'],
            "idCategory" => $id_category,
            "description" => $product->get_description(),
            "subItems" => array(),
        );

        if (isset($cart_item['formula_options'])) {
            foreach ($cart_item['formula_options'] as $formula_option) {
                $sub_item = array(
                    "productType" => 4,
                    "sku" => $formula_option['sku'],
                    "name" => $formula_option['product'],
                    "price" => floatval($formula_option['price']),
                    "quantity" => 1,
                    "id_category" => isset($formula_option['id_category']) ? $formula_option['id_category'] : '',
                    "description" => isset($formula_option['description']) ? $formula_option['description'] : '',
                );
                $item_data['subItems'][] = $sub_item;

                if (!empty($formula_option['suboptions'])) {
                    foreach ($formula_option['suboptions'] as $suboption) {
                        $sub_item = array(
                            "productType" => 2,
                            "sku" => $suboption['sku'],
                            "name" => $suboption['option'],
                            "price" => floatval($suboption['price']),
                            "quantity" => 1,
                            "idCategory" => isset($suboption['idCategory']) ? $suboption['idCategory'] : '',
                            "description" => isset($suboption['description']) ? $suboption['description'] : '',
                        );
                        $item_data['subItems'][] = $sub_item;
                    }
                }
            }
        } elseif (isset($cart_item['custom_options'])) {
            foreach ($cart_item['custom_options'] as $custom_option) {
                $sub_item = array(
                    "productType" => 2,
                    "sku" => $custom_option['sku'],
                    "name" => $custom_option['option'],
                    "price" => floatval($custom_option['price']),
                    "quantity" => 1,
                    "idCategory" => isset($custom_option['idCategory']) ? $custom_option['idCategory'] : '',
                    "description" => isset($custom_option['description']) ? $custom_option['description'] : '',
                );
                $item_data['subItems'][] = $sub_item;
            }
        }

        $data['items'][] = $item_data;

    }

    wp_send_json($data);

    if (true) { // Remplacez par votre condition
        wc_add_notice(__('Erreur lors de l\'envoi des données de commande à l\'API. Veuillez réessayer.'), 'error');
        wp_send_json_error($data); // Pour AJAX
        wp_die(__('Erreur lors de l\'envoi des données de commande à l\'API.'), __('Erreur'), array('response' => 500)); // Pour non-AJAX
    }

    // Envoyer les données de la commande sous forme de JSON
}
