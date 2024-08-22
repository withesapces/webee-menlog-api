<?php
// Ce code permet d'envoyer une commande sur Menlog
// TODO : Que doit-on faire si la commande ne peut pas être envoyée ? Ca doit dépendre des codes d'erreur.
// TODO : Faire la gestion des erreurs

add_action('woocommerce_checkout_process', 'send_order_data_to_api');
function send_order_data_to_api() {
    // Vérifier le nonce pour la sécurité
    check_ajax_referer('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
    $api_integration = new WooCommerce_API_Integration();
    $prix_total = 0;
    $order_items = [];

    $customer = WC()->customer;

    // Test d'envoi du client
    $add_client_result = $api_integration->add_client($customer);
    if ($add_client_result['error']) {
        wc_add_notice($add_client_result['message'], 'error');
        
        // Log des informations de débogage
        if (isset($add_client_result['debug_info'])) {
            error_log("Débug add_client: " . print_r($add_client_result['debug_info'], true));
        }
        
        return;
    }

    $cart = WC()->cart;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $product_price = floatval($product->get_price());
        $prix_total += $product_price;
    
        $product_type = 1; // Par défaut, 1 pour un produit simple
        $category = wp_get_post_terms($product_id, 'product_cat');
        $id_category = ''; // Initialiser à une valeur vide
    
        if (!empty($category)) {
            $term_id = $category[0]->term_id; // Récupère l'identifiant de la catégorie
            $id_category = get_term_meta($term_id, 'menlog_id_category', true); // Récupère l'idCategory de Menlog
        }
    
        $item_data = array(
            "productType" => $product_type,
            "sku" => $product->get_sku(),  // Limiter à 12 caractères
            "name" => $product->get_name(),
            "price" => $product_price, // Prix du produit dans la BDD
            "quantity" => $cart_item['quantity'],
            "idCategory" => $id_category,
            "description" => $product->get_description(),
            "subItems" => array(), // Placeholder for subitems, if any
        );
    
        // Gestion des options pour les formules
        if (isset($cart_item['formula_options'])) {
            foreach ($cart_item['formula_options'] as $formula_option) {
                $sub_item_data = array(
                    "productType" => 4, // Type pour les options de formule
                    "sku" => $formula_option['sku'],
                    "name" => $formula_option['product'],
                    "price" => floatval($formula_option['price']),
                    "quantity" => 1,
                    "idCategory" => $formula_option['id_category'],
                    "description" => $formula_option['description'],
                    "subItems" => []
                );

                // Ajouter les sous-options, s'il y en a
                if (!empty($formula_option['suboptions'])) {
                    foreach ($formula_option['suboptions'] as $suboption) {
                        $sub_sub_item_data = array(
                            "productType" => 2, // Type pour les sous-options
                            "sku" => $suboption['sku'],
                            "name" => $suboption['option'],
                            "price" => floatval($suboption['price']),
                            "quantity" => 1,
                            "idCategory" => $suboption['idCategory'],
                            "description" => $suboption['description']
                        );
                        // Ajoute la sous-option dans les subItems de l'option principale
                        $sub_item_data['subItems'][] = $sub_sub_item_data;
                        $prix_total += floatval($suboption['price']);
                    }
                }

                // Ajoute l'option principale dans les subItems de l'élément principal
                $item_data['subItems'][] = $sub_item_data;
                $prix_total += floatval($formula_option['price']);
            }
        }

        // Gestion des options pour les produits non-formule
        if (isset($cart_item['custom_options'])) {
            foreach ($cart_item['custom_options'] as $custom_option) {
                $sub_item_data = array(
                    "productType" => 2, // Type pour les sous-options non-formule
                    "sku" => $custom_option['sku'],
                    "name" => $custom_option['option'],
                    "price" => floatval($custom_option['price']),
                    "quantity" => 1,
                    "idCategory" => $custom_option['idCategory'],
                    "description" => $custom_option['description']
                );
                $item_data['subItems'][] = $sub_item_data;
                $prix_total += floatval($custom_option['price']);
            }
        }
    
        $order_items[] = $item_data;
    }
    

    $pickup_date = WC()->session->get('pickup_date');
    $pickup_time = WC()->session->get('pickup_time');

    // Limitez les IDs générés à 12 caractères
    $order_id = substr("id_" . time(), 0, 12);
    $channel_order_display_id = substr("TK_" . time(), 0, 12);

    $order_data = array(
        "account" => $api_integration->get_uuidclient(),
        "location" => "Sandbox",
        "id" => $order_id,
        "channelOrderDisplayId" => $channel_order_display_id,
        "channelOrderId" => "",
        "source" => "ecommerce",
        "status" => "CONFIRMED",
        "orderType" => "PICKUP",
        "created_at" => current_time('mysql'),
        "pickupTime" => isset($pickup_date) && isset($pickup_time) ? $pickup_date . ' ' . $pickup_time : '',
        "deliveryTime" => "", // Laisser vide si non applicable
        "note" => "", // Ajouter une note si nécessaire
        "customer" => array(
            "id" => substr($customer->get_id(), 0, 12),
            "name" => $customer->get_first_name() . ' ' . $customer->get_last_name(),
            "phone" => $customer->get_billing_phone(),
            "phoneCode" => "", // Ajouter un code téléphonique si nécessaire
            "email" => $customer->get_billing_email(),
        ),
        "orderTotal" => array(
            "subtotal" => floatval(WC()->cart->get_total('')),
            "discount" => floatval($cart->get_discount_total()),
            "tax" => null,
            "deliveryFee" => floatval($cart->get_shipping_total()),
            "total" => floatval(WC()->cart->get_total('')),
        ),
        "payments" => array(
            array(
                "typereg" => "5",
                "montant" => floatval(WC()->cart->get_total('')),
            )
        ),
        "items" => $order_items,
    );

    // Convertir les données en JSON
    $json_order_data = json_encode($order_data, JSON_UNESCAPED_UNICODE);
    if ($json_order_data === false) {
        error_log('Erreur de formatage JSON: ' . json_last_error_msg());
        wc_add_notice(__('Erreur lors de la préparation des données de commande. Veuillez réessayer.'), 'error');
        return;
    }

    // Transmettre les données JSON à JavaScript via wp_localize_script
    wp_register_script('send_order_data_to_api', '');
    wp_localize_script('send_order_data_to_api', 'orderData', $json_order_data);
    wp_enqueue_script('send_order_data_to_api');

    $url = 'https://' . $api_integration->get_server() . '/' . $api_integration->get_delivery() . '/menlog/' . $api_integration->get_uuidclient() . '/' . $api_integration->get_uuidmagasin() . '?token=' . $api_integration->get_token();

    // Log pour déboguer
    error_log("URL de la requête: " . $url);
    error_log("Données de la commande envoyées: " . json_encode($order_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json_order_data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);

    curl_close($curl);

    if ($response === false) {
        error_log("Erreur cURL: " . $curl_error);
        wc_add_notice(__('Erreur lors de la communication avec l\'API. Veuillez réessayer.'), 'error');
        return;
    }

    error_log("Réponse de l'API: " . $response);
    error_log("Code HTTP de la réponse: " . $http_code);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Erreur de décodage JSON: ' . json_last_error_msg());
        wc_add_notice(__('Erreur inattendue lors de la réception des données. Veuillez réessayer.'), 'error');
        return;
    }

    if ($http_code == 200) {
        wc_add_notice(__('Commande envoyée avec succès.'), 'success');
    } else {
        wc_add_notice(__('Erreur lors de l\'envoi de la commande. Veuillez réessayer.'), 'error');
    }

    // Ajoutez un script JavaScript pour afficher les données dans la console du navigateur
    echo "
    <script type='text/javascript'>
        console.log('Order Data: ', " . $json_order_data . ");
    </script>";
}

