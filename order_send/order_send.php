<?php

// Ce code permet d'envoyer une commande sur Menlog
add_action('woocommerce_checkout_order_processed', 'send_order_data_to_api', 10, 1);
function send_order_data_to_api($order_id) {
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
        
        if (isset($add_client_result['debug_info'])) {
            error_log("Débug add_client: " . print_r($add_client_result['debug_info'], true));
        }

        $error_message = $add_client_result['message'];

        // Afficher le message d'erreur à l'utilisateur
        throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
    }

    // Récupérer les informations du client
    $customer_id = $add_client_result['uidclient'];
    $customer_name = $add_client_result['first_name'] . ' ' . $add_client_result['last_name'];
    $customer_phone = $add_client_result['phone'];
    $customer_email = $add_client_result['email'];

    // Récupérer l'heure de la commande
    $order_time = current_time('mysql');

    $cart = WC()->cart;

    // Récupération de chaque produit et options (idem pour formule) du panier
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
        $product_price = custom_round($product_price);
    
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

        $prix_total += custom_round($item_data['price']*$cart_item['quantity']);
        error_log("prix_total = " . $prix_total . " pour le produit " . $product->get_name() . "\n\n");
    
        // Gestion des options pour les formules
        if (isset($cart_item['formula_options'])) {
            foreach ($cart_item['formula_options'] as $formula_option) {
                $sub_item_data = array(
                    "productType" => 4, // Type pour les options de formule
                    "sku" => $formula_option['sku'],
                    "name" => $formula_option['product'],
                    "price" => custom_round($formula_option['price']),
                    "quantity" => $formula_option['quantity'],
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
                            "price" => custom_round($suboption['price']),
                            "quantity" => $suboption['quantity'] * $formula_option['quantity'], // Multiplication des quantités
                            "idCategory" => $suboption['idCategory'],
                            "description" => $suboption['description']
                        );
                        // Ajoute la sous-option dans les subItems de l'option principale
                        $sub_item_data['subItems'][] = $sub_sub_item_data;
                        $prix_total += custom_round($suboption['price'] * $sub_sub_item_data['quantity']);
                        custom_round($item_data['price']*$cart_item['quantity']);
                    }
                }

                // Ajoute l'option principale dans les subItems de l'élément principal
                $item_data['subItems'][] = $sub_item_data;
                $prix_total += custom_round($formula_option['price']*$formula_option['quantity']);
            }
        }

        // Gestion des options pour les produits non-formule
        if (isset($cart_item['custom_options'])) {
            foreach ($cart_item['custom_options'] as $custom_option) {
                $sub_item_data = array(
                    "productType" => 2, // Type pour les sous-options non-formule
                    "sku" => $custom_option['sku'],
                    "name" => $custom_option['option'],
                    "price" => custom_round($custom_option['price']),
                    "quantity" => $custom_option['quantity'],
                    "idCategory" => $custom_option['idCategory'],
                    "description" => $custom_option['description']
                );
                $item_data['subItems'][] = $sub_item_data;
                $prix_total += custom_round($custom_option['price']*$cart_item['quantity']);
                error_log("prix_total = " . $prix_total . " pour l'option " . $custom_option['option'] . "\n\n");
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
    $custom_order_id = substr("id_" . time(), 0, 12);
    $channel_order_display_id = substr("TK_" . time(), 0, 12);

    error_log("Prix total avant construction de order_data: " . print_r($prix_total, true));

    $applied_coupons = WC()->cart->get_applied_coupons();
    $discount_total = 0;

    foreach ($applied_coupons as $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        $discount_type = $coupon->get_discount_type();
        $coupon_amount = $coupon->get_amount();
        $product_ids = $coupon->get_product_ids();  // Produits spécifiques auxquels le coupon s'applique
        $excluded_product_ids = $coupon->get_excluded_product_ids();  // Produits exclus du coupon
    
        error_log("Coupon appliqué: " . $coupon_code);
        error_log("Type de coupon: " . $discount_type);
        error_log("Montant du coupon: " . $coupon_amount);
        error_log("Produits ciblés par le coupon: " . print_r($product_ids, true));
        error_log("Produits exclus par le coupon: " . print_r($excluded_product_ids, true));
    
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['data']->get_id();
            $product_price = $cart_item['data']->get_price(); // Prix original du produit
            error_log("Produit dans le panier: " . $product_id);
            error_log("Prix du produit sans réduction : " . $product_price);
    
            // Si le produit est exclu du coupon, on saute l'itération
            if (in_array($product_id, $excluded_product_ids)) {
                error_log("Produit exclu du coupon: " . $product_id);
                continue;
            }
    
            // Si le coupon est un pourcentage et s'applique à un produit spécifique
            if ($discount_type == 'percent' && (empty($product_ids) || in_array($product_id, $product_ids))) {
                $product_discount = $product_price * ($coupon_amount / 100) * $cart_item['quantity'];
                $discount_total += $product_discount;
                error_log("Réduction appliquée (pourcentage) pour le produit " . $product_id . " coutant " . $product_price . ": " . $product_discount);
            }
            // Si le coupon est un montant fixe sur un produit spécifique
            elseif ($discount_type == 'fixed_product' && in_array($product_id, $product_ids)) {
                $product_discount = $coupon_amount * $cart_item['quantity'];
                $discount_total += $product_discount;
                error_log("Réduction appliquée (montant fixe) pour le produit " . $product_id . ": " . $product_discount);
            }
        }
    
        // Si le coupon est un montant fixe sur tout le panier
        if ($discount_type == 'fixed_cart') {
            $discount_total += $coupon_amount;
            error_log("Réduction appliquée (montant fixe sur tout le panier): " . $coupon_amount);
        }
    }
    
    $discount_total = round($discount_total, 2);
    error_log("Réduction totale calculée manuellement : " . $discount_total . "\n\n");
    

    // Génératoin de la requête
    $order_data = array(
        "account" => $api_integration->get_uuidclient(),
        "location" => "Sandbox",
        "id" => $custom_order_id,
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
            "subtotal" => (float)number_format(custom_round($prix_total), 2, '.', ''),
            "discount" => $discount_total,
            "tax" => null,
            "deliveryFee" => (float)number_format(custom_round(WC()->cart->get_shipping_total()), 2, '.', ''),
            "total" => (float)number_format(custom_round(WC()->cart->get_total('')), 2, '.', ''),
        ),
        "payments" => array(
            array(
                "typereg" => "5",
                "montant" => custom_round(WC()->cart->get_total('')),
            )
        ),
        "items" => $order_items,
    );

    // Convertir les données en JSON
    $json_order_data = json_encode($order_data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

    if ($json_order_data === false) {
        error_log('Erreur de formatage JSON: ' . json_last_error_msg());

        $error_message = 'Erreur lors de la préparation des données de commande. Veuillez réessayer.';

        // Afficher le message d'erreur à l'utilisateur
        throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
    }

    // Transmettre les données JSON à JavaScript via wp_add_inline_script
    wp_register_script('send_order_data_to_api', ''); // Enregistrez un script vide ou existant
    wp_add_inline_script('send_order_data_to_api', 'const orderData = ' . $json_order_data . ';', 'before');
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
    $log_info = "Client: {$customer_name}, Email: {$customer_email}, Téléphone: {$customer_phone}, Date de commande: {$order_time}, Données de la commande envoyées: " . json_encode($order_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($response === false) {
        $log_message = "Erreur cURL: {$curl_error}. {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur cURL', $log_message);

        $error_message = 'Erreur lors de la communication avec l\'API. Veuillez réessayer.';

        // Afficher le message d'erreur à l'utilisateur
        throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
    }

    error_log("Réponse de l'API: " . $response);
    error_log("Code HTTP de la réponse: " . $http_code);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $log_message = "Erreur de décodage JSON: " . json_last_error_msg() . ". {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur de décodage JSON', $log_message);

        $error_message = 'Erreur inattendue lors de la réception des données. Veuillez réessayer.';

        // Afficher le message d'erreur à l'utilisateur
        throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
    }

    // Dans le cas où nous avons un code de retour 200, tout est ok, ou presque
    if ($http_code == 200) {
        if ($data['success']) {
            wc_add_notice(__('Commande envoyée avec succès.'), 'success');
        } 
    } 
    
    // Dans le cas d'un code 400, 2 solution
    elseif ($http_code == 400) {
        // Ajout d'un log pour vérifier le contenu de l'erreur
        error_log('Debug - Contenu de $data[\'error\']: ' . print_r($data['error'], true));

        // Code 400 avec Bad ticket format
        if (isset($data['error']) && is_string($data['error']) && strpos($data['error'], 'Bad ticket format') !== false) {
            $log_message = "Erreur de format de ticket: " . $data['message'] . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de format de ticket', $log_message);
        
            $error_message = 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.';

            // Afficher le message d'erreur à l'utilisateur
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        }    
        
        // Code 400 avec BadRequest
        elseif (isset($data['error']['code']) && $data['error']['code'] === 'BadRequest') {
            // Extraire le message d'erreur à partir de $data['error']['message']
            $log_message = "Erreur de donnée essentielle manquante ou erronée: " . print_r($data['error']['message'], true) . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de donnée essentielle', $log_message);
        
            // Vérifier si 'message' est une chaîne ou un tableau et gérer les erreurs
            if (is_array($data['error']['message'])) {
                $missing_info = $data['error']['message'];
            } else {
                $missing_info = [$data['error']['message']]; // Assurez-vous que c'est un tableau
            }
        
            // Construire un message d'erreur pour WooCommerce
            $error_message = __('Une erreur s\'est produite lors de la validation de votre commande. Nous avons détecté un problème technique avec un ou plusieurs articles dans votre panier.', 'textdomain');
            $error_message .= '<br>' . __('Veuillez essayer les solutions suivantes :', 'textdomain');
            $error_message .= '<br>- ' . __('Vider votre panier et ajouter à nouveau les articles.', 'textdomain');
            $error_message .= '<br>- ' . __('Si le problème persiste, veuillez contacter notre support client.', 'textdomain');
            $error_message .= '<br><br>' . __('Le paiement n\'a pas été effectué. Nous vous prions de nous excuser pour la gêne occasionnée.', 'textdomain');

            // Afficher le message d'erreur à l'utilisateur
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        }
    } 
    
    // Dans le cas d'un code 500, 6 cas possibles
    elseif ($http_code == 500) {
        // Ajout d'un log pour vérifier le contenu de l'erreur
        error_log('Debug - Contenu de $data[\'error\']: ' . print_r($data['error'], true));

        // Vérifier si 'message' est défini et est une chaîne avant d'utiliser strpos
        $message = isset($data['error']['message']) && is_string($data['error']['message']) ? trim($data['error']['message']) : '';
        
        // Ajout d'un log pour voir le contenu exact de $message
        error_log('Debug - Contenu exact de $message: ' . $message);

        // Dans le cas d'un Timeout
        if (strpos($message, 'Timeout') !== false) {
            $error_message = 'Le serveur du magasin n\'est pas connecté. Votre commande a été annulée. Veuillez réessayer ultérieurement.';

            // Appeler l'API UPD_VT pour annuler la commande
            $upd_vt_response = annuler_commande_menlog($api_integration, $order_id);

            if ($upd_vt_response['success']) {
                $log_message = "Timeout - Le serveur du magasin est indisponible, la commande client a bien été annulée. La commande menlog a également été annulée et l'information sera transmise au logiciel de caisse à la fin du timeout : " . $message . ". {$log_info}";
                error_log($log_message);
                envoyer_email_debug('Timeout serveur magasin', $log_message);
            } else {
                $log_message = "Timeout - Le serveur du magasin est indisponible, la commande client a bien été annulée. La commande menlog n'a en revanche pas été annulée : " . $message . ". {$log_info}";
                error_log($log_message);
                envoyer_email_debug('Timeout serveur magasin', $log_message);
            }
            
            $error_message = 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.';
    
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        } 
        
        // Dans le cas d'une commande déjà importée
        // Souvent, c'est parce que le serveur était en TIMEOUT
        elseif (stripos($message, 'Commande déjà importée') !== false) {
            $log_message = "Ce numéro de commande existe déjà: " . $message . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Ce numéro de commande existe déjà.', $log_message);

            $error_message = 'Ce numéro de commande existe déjà. Le paiement n\'a pas été effectué. Si vous pensez qu\'il s\'agit d\'une erreur, veuillez contacter la boulangerie.';
    
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        } 
        
        // Dans le cas d'un produit qui n'existe plus sur menlog, mais encore sur BDD
        // (possible si produit modifié dans Menlog, mais maj pas encore passée)
        elseif (stripos($message, 'La catégorie comptable du tiers ou du produit') !== false) {
            $log_message = "La référence produit n'existe pas dans la caisse : " . $message . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('La référence produit n\'existe pas dans la caisse', $log_message);
            
            $error_message = 'Une erreur s\'est produite lors de la validation de votre commande. Il semble qu\'un des produits ne puisse pas être traité. Veuillez essayer de retirer les produits du panier un par un pour identifier celui qui pose problème, ou contactez notre support pour assistance. Le paiement n\'a pas été effectué.';
    
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        }
        
        // Dans le cas d'un problème de prix
        elseif (stripos($message, 'Diff px ttc/total') !== false) {
            $log_message = "Le total des lignes ne correspond pas au total de la vente: " . $message . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Le total des lignes ne correspond pas au total de la vente', $log_message);

            $error_message = 'Une erreur technique est survenue lors de la validation de votre commande en raison d\'informations incorrectes sur un ou plusieurs articles dans votre panier.';
            $error_message .= '<br>' . __('Pour résoudre ce problème, veuillez essayer les actions suivantes :', 'textdomain');
            $error_message .= '<br>- ' . __('Retirez les articles récemment ajoutés à votre panier et réessayez.', 'textdomain');
            $error_message .= '<br>- ' . __('Si le problème persiste, veuillez vider votre panier, puis ajouter les articles à nouveau un par un.', 'textdomain');
            $error_message .= '<br><br>' . __('Le paiement n\'a pas été effectué. Nous vous prions de nous excuser pour la gêne occasionnée.', 'textdomain');
            
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        } 
        
        // Dans le cas d'un problème de conversion
        elseif (stripos($message, 'conversion error from string') !== false) {
            $log_message = "Erreur de conversion de données en string/numeric. Ne pas utiliser de caractères spéciaux dans les libellés: " . $message . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur de conversion de données en string/numeric. Ne pas utiliser de caractères spéciaux dans les libellés.', $log_message);

            // Message d'erreur pour le client avec des actions concrètes
            $error_message = 'Une erreur technique est survenue lors de la validation de votre commande en raison d\'informations incorrectes sur un ou plusieurs articles dans votre panier.';
            $error_message .= '<br>' . __('Pour résoudre ce problème, veuillez essayer les actions suivantes :', 'textdomain');
            $error_message .= '<br>- ' . __('Retirez les articles récemment ajoutés à votre panier et réessayez.', 'textdomain');
            $error_message .= '<br>- ' . __('Vérifiez que vous n\'avez pas utilisé de caractères spéciaux (comme des accents ou symboles) dans les champs de texte (notes, messages personnalisés, etc.) des produits.', 'textdomain');
            $error_message .= '<br>- ' . __('Si le problème persiste, veuillez vider votre panier, puis ajouter les articles à nouveau un par un.', 'textdomain');
            $error_message .= '<br><br>' . __('Le paiement n\'a pas été effectué. Nous vous prions de nous excuser pour la gêne occasionnée.', 'textdomain');
            
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        } 
        
        // Dans le cas où une erreur interne n'est pas référencée, on ne passe pas la commande
        else {
            $log_message = "Erreur interne du serveur non spécifiée: " . $message . ". {$log_info}";
            error_log($log_message);
            envoyer_email_debug('Erreur interne du serveur', $log_message);
    
            $error_message = 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.';
    
            throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
        }
    } 
    
    // Dans le cas où l'erreur n'est pas référencée, on dit au client de recommencer et on ne passe pas la commande
    else {
        $log_message = "Erreur inattendue avec code HTTP " . $http_code . ": " . $data['message'] . ". {$log_info}";
        error_log($log_message);
        envoyer_email_debug('Erreur inattendue', $log_message);

        $error_message = 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.';

        // Afficher le message d'erreur à l'utilisateur
        throw new WC_Data_Exception('woocommerce_invalid_order', $error_message, 400);
    }

    if (strpos($order_id, 'id_') !== false) {
        $order_id = (int) str_replace('id_', '', $order_id); // Extraire uniquement la partie numérique
    }

    // Vérifier si l'ID de commande est valide
    if (!$order_id || !is_numeric($order_id)) {
        error_log("ID de commande non valide : " . print_r($order_id, true));
        return; // Arrêter l'exécution si l'ID n'est pas valide
    }

    // Récupérer la commande
    $order = wc_get_order($order_id);

    if (!$order) {
        error_log("Commande introuvable pour l'ID : " . $order_id);
        return; // Arrêter si la commande n'est pas trouvée
    }

    // Vérifier si le paiement a échoué
    if ($order->get_status() === 'failed') {
        // Lancer l'annulation via Menlog
        $api_integration = new WooCommerce_API_Integration();
        annuler_commande_menlog($api_integration, $order_id);
    }
}

// Fonction pour envoyer un email de débogage
function envoyer_email_debug($sujet, $message) {
    $to = 'bauduffegabriel@gmail.com';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $sujet, $message, $headers);
}

function custom_round($value) {
    return round((float)$value, 2);
}

function annuler_commande_menlog($api_integration, $order_id) {
    $url = 'https://' . $api_integration->get_server() . '/' . $api_integration->get_rlog() . '/' . $api_integration->get_uuidclient() . '/' . $api_integration->get_uuidmagasin() . '/upd_vt?token=' . $api_integration->get_token();

    $body = json_encode(array(
        'refsite' => $api_integration->get_refsite(),
        'uiddocext' => $order_id,
        'status' => -1,
    ));

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
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
        ),
    ));

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($http_code == 200) {
        error_log("Commande annulée avec succès via Menlog : " . $order_id);
        return array('success' => true);
    } else {
        error_log("Erreur lors de l'annulation de la commande : {$curl_error}");
        return array('success' => false, 'error' => $curl_error);
    }
}