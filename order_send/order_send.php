<?php
// Ce code permet d'envoyer une commande sur Menlog
add_action('woocommerce_checkout_order_processed', 'send_order_data_to_api', 10, 1);
function send_order_data_to_api() {
    // Vérifier le nonce pour la sécurité
    check_ajax_referer('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
    $api_integration = new WooCommerce_API_Integration();
    $prix_total = 0;
    $order_items = [];
    $count = 0;
    $note_anniversaire = '';
    global $wpdb;

    $customer = WC()->customer;

    // Test d'envoi du client
    $add_client_result = $api_integration->add_client($customer);
    if ($add_client_result['error']) {
        wc_add_notice($add_client_result['message'], 'error');
        
        if (isset($add_client_result['debug_info'])) {
            error_log("Débug add_client: " . print_r($add_client_result['debug_info'], true));
        }
        
        return;
    }

    // Récupérer les informations du client
    $customer_id = $add_client_result['uidclient'];
    $customer_name = $add_client_result['first_name'] . ' ' . $add_client_result['last_name'];
    $customer_phone = $add_client_result['phone'];
    $customer_email = $add_client_result['email'];

    // Récupérer l'heure de la commande
    $order_time = current_time('mysql');

    $cart = WC()->cart;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $product_price = 0;
    
        $product_type = 1; // Par défaut, 1 pour un produit simple
        $category = wp_get_post_terms($product_id, 'product_cat');
        $id_category = ''; // Initialiser à une valeur vide
    
        if (!empty($category)) {
            $term_id = $category[0]->term_id; // Récupère l'identifiant de la catégorie
            $id_category = get_term_meta($term_id, 'menlog_id_category', true); // Récupère l'idCategory de Menlog
        }

        // Récupérer le prix du produit depuis la base de données en utilisant le SKU et la catégorie
        $product_sku = $product->get_sku();
        $product_id_query = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = '_sku'
            AND pm.meta_value = %s
            AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'product_cat'
                AND tt.term_id = %d
            )
            LIMIT 1",
            $product_sku, $term_id
        ));

        // Si un produit est trouvé, récupérer son prix
        if ($product_id_query) {
            $product_price = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta}
                WHERE post_id = %d
                AND meta_key = '_price'
                LIMIT 1",
                $product_id_query
            ));
        }

        // Assurez-vous que le prix est un nombre flottant
        $product_price = floatval($product_price);
    
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

        $prix_total += floatval($item_data['price']);
    
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

        // Ajouter les messages des plaques anniversaire aux notes
        for ($i = 1; $i <= $cart_item['quantity']; $i++) {
            $count++;
            $plaque_note = sanitize_text_field($_POST["anniversary_plaque_note_{$count}"] ?? '');
            if (!empty($plaque_note)) {
                $note_anniversaire .= "Produit: {$product->get_name()} - Plaque #{$i} : {$plaque_note}\n";
            }
        }

        $order_items[] = $item_data;
    }

    // Ajouter les notes de la commande si disponibles
    $order_notes = sanitize_text_field($_POST['order_comments'] ?? '');

    // Combiner les notes de commande et les notes des plaques anniversaire
    $notes_combined = trim($order_notes) . "\n" . trim($note_anniversaire);

    // Limiter à 2000 caractères
    $notes_combined = mb_substr($notes_combined, 0, 2000);
    

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
        "created_at" => $order_time,
        "pickupTime" => isset($pickup_date) && isset($pickup_time) ? $pickup_date . ' ' . $pickup_time : '',
        "deliveryTime" => "", // Laisser vide si non applicable
        "note" => $notes_combined, // Ajouter une note si nécessaire
        "customer" => array(
            "id" => $customer_id,
            "name" => $customer_name,
            "phone" => $customer_phone,
            "phoneCode" => "", // Ajouter un code téléphonique si nécessaire
            "email" => $customer_email,
        ),
        "orderTotal" => array(
            "subtotal" => floatval(WC()->cart->get_total('')),
            "discount" => floatval(WC()->cart->get_discount_total()),
            "tax" => null,
            "deliveryFee" => floatval(WC()->cart->get_shipping_total()),
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

    // Collecte des informations pour le log et l'email
    $log_info = "Client: {$customer_name}, Email: {$customer_email}, Téléphone: {$customer_phone}, Date de commande: {$order_time}";

    if ($response === false) {
        $log_message = "Erreur cURL: {$curl_error}. {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur cURL', $log_message);
        wc_add_notice(__('Erreur lors de la communication avec l\'API. Veuillez réessayer.'), 'error');
        return;
    }

    error_log("Réponse de l'API: " . $response);
    error_log("Code HTTP de la réponse: " . $http_code);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $log_message = "Erreur de décodage JSON: " . json_last_error_msg() . ". {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur de décodage JSON', $log_message);
        wc_add_notice(__('Erreur inattendue lors de la réception des données. Veuillez réessayer.'), 'error');
        return;
    }

    if ($http_code == 200) {
        if ($data['success']) {
            wc_add_notice(__('Commande envoyée avec succès.'), 'success');
        } else {
            $log_message = "Erreur avec code 200: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur lors de l\'envoi de la commande', $log_message);
            wc_add_notice(__('Erreur lors de l\'envoi de la commande: ') . $data['message'], 'error');
        }
    } elseif ($http_code == 400) {
        if (strpos($data['error'], 'Bad ticket format') !== false) {
            $log_message = "Erreur de format de ticket: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de format de ticket', $log_message);
            wc_add_notice(__('Erreur de format de ticket. Veuillez corriger le format et réessayer.'), 'error');
        } elseif (strpos($data['error'], 'Bad Request') !== false) {
            $log_message = "Erreur de donnée essentielle manquante ou erronée: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de donnée essentielle', $log_message);
            wc_add_notice(__('Une donnée essentielle est manquante ou erronée: ') . $data['message'], 'error');
        }
    } elseif ($http_code == 500) {
        if (strpos($data['message'], 'Timeout') !== false) {
            $log_message = "Timeout - Le serveur du magasin est indisponible: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Timeout serveur magasin', $log_message);
            wc_add_notice(__('Le serveur du magasin est indisponible. La commande sera traitée dès que possible.'), 'error');
        } elseif (strpos($data['message'], 'Commande déjà importée') !== false) {
            $log_message = "Commande déjà importée: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Commande déjà importée', $log_message);
            wc_add_notice(__('Cette commande a déjà été importée.'), 'error');
        } elseif (strpos($data['message'], 'La catégorie comptable du tiers ou du produit n\'est pas définie') !== false) {
            $log_message = "Catégorie comptable non définie: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Catégorie comptable non définie', $log_message);
            wc_add_notice(__('Erreur: La catégorie comptable du produit ou du tiers n\'est pas définie. Veuillez corriger et réessayer.'), 'error');
        } elseif (strpos($data['message'], 'Erreur : Diff px ttc/total') !== false) {
            $log_message = "Total des lignes incorrect: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Total des lignes incorrect', $log_message);
            wc_add_notice(__('Erreur: Le total des lignes ne correspond pas au total de la vente. Veuillez vérifier les montants et réessayer.'), 'error');
        } elseif (strpos($data['message'], 'conversion error from string') !== false) {
            $log_message = "Erreur de conversion de données: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de conversion de données', $log_message);
            wc_add_notice(__('Erreur de conversion des données. Veuillez vérifier les formats et réessayer.'), 'error');
        } else {
            $log_message = "Erreur interne du serveur non spécifiée: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur interne du serveur', $log_message);
            wc_add_notice(__('Erreur interne du serveur: ') . $data['message'], 'error');
        }
    } else {
        $log_message = "Erreur inattendue avec code HTTP " . $http_code . ": " . $data['message'] . ". {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur inattendue', $log_message);
        wc_add_notice(__('Erreur inattendue. Veuillez réessayer.'), 'error');
    }
}

// Fonction pour envoyer un email de débogage
function envoyer_email_debug($sujet, $message) {
    $to = 'bauduffegabriel@gmail.com';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $sujet, $message, $headers);
}