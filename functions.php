<?php
/*
Plugin Name: WooCommerce API Integration
Description: Plugin pour intégrer les produits d'une API externe dans WooCommerce.
Version: 1.0
Author: Webee Digital
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

include_once plugin_dir_path(__FILE__) . 'display/formula_product_view/display_formula_product_view.php';
include_once plugin_dir_path(__FILE__) . 'display/simple_product_view/display_product_view.php';
include_once plugin_dir_path(__FILE__) . 'order_send/order_send.php';

function enqueue_display_formula_product_view_assets() {
    wp_enqueue_style(
        'display-formula-product-view-css',
        plugins_url('display/formula_product_view/display_formula_product_view.css', __FILE__),
        array(),
        '2.0.0',
        'all'
    );

    wp_enqueue_style(
        'display_product_view.css',
        plugins_url('display/simple_product_view/display_product_view.css', __FILE__),
        array(),
        '2.0.0',
        'all'
    );

    wp_enqueue_script(
        'display-formula-product-view-js',
        plugins_url('display/formula_product_view/display_formula_product_view.js', __FILE__),
        array('jquery'),
        '2.0.0',
        true
    );

    wp_enqueue_script(
        'display-product-view-js',
        plugins_url('display/simple_product_view/display_product_view.js', __FILE__),
        array('jquery'),
        '2.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'enqueue_display_formula_product_view_assets');

class WooCommerce_API_Integration {


    // -----------------------------
    // Propriétés de la classe
    // -----------------------------

        private $token;
        private $server = 'k8s.zybbo.com';
        private $rlog = 'rlog-staging';
        private $delivery = 'delivery-staging';
        private $uuidclient = 'U9mDG4tia5lfatO*wd5lA!';
        private $uuidmagasin = 'MfSBUAdui5Vg!tO*wd5lA!';
        private $username = 'yanna_dev';
        private $password = 'A33_DKq(QFJ1r*9;)';

        private $categories_created = 0;
        private $categories_updated = 0;
        private $categories_skipped = 0;
        private $categories_deleted = 0;
        private $products_created = 0;
        private $products_updated = 0;
        private $products_skipped = 0;
        private $products_deleted = 0;
        private $products_with_PT3 = 0;
        private $products_PT3_created = 0;
        private $products_PT3_updated = 0;
        private $products_PT3_skipped = 0;
        private $products_PT3_deleted = 0;
        private $products_PT2_created = 0;
        private $products_PT2_updated = 0;
        private $products_PT2_skipped = 0;
        private $products_PT2_deleted = 0;
        private $products_PT4_created = 0;
        private $products_PT4_updated = 0;
        private $products_PT4_skipped = 0;
        private $products_PT4_deleted = 0;
        private $products_PT3_PT4_created = 0;
        private $products_PT3_PT4_updated = 0;
        private $products_PT3_PT4_skipped = 0;
        private $products_PT3_PT4_deleted = 0;
        private $products_PT2_PT3_PT4_created = 0;
        private $products_PT2_PT3_PT4_updated = 0;
        private $products_PT2_PT3_PT4_skipped = 0;
        private $products_PT2_PT3_PT4_deleted = 0;
        private $category_updates = [];
        private $product_updates = [];
        private $message_erreur = '';

    /**
     * Constructeur
     */
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'create_custom_tables'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'import_menlog_from_api'));
    }

    // -----------------------------
    // Méthodes getter pour les propriétés privées
    // -----------------------------

        public function get_server() {
            return $this->server;
        }

        public function get_delivery() {
            return $this->delivery;
        }

        public function get_uuidclient() {
            return $this->uuidclient;
        }

        public function get_uuidmagasin() {
            return $this->uuidmagasin;
        }

        /**
         * Récupération du Token menlog
         * @return mixed
         */
        public function get_token() {
            $url = "https://{$this->server}/jwt/authenticate";
        
            $body = array(
                'username' => $this->username,
                'password' => $this->password,
            );
        
            $args = array(
                'body'    => json_encode($body),
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'method'  => 'POST',
            );
        
            $response = wp_remote_post($url, $args);
        
            if (is_wp_error($response)) {
                wp_die('Erreur lors de la récupération du token: ' . $response->get_error_message());
            }
        
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
        
            if (isset($data->token)) {
                return $data->token;
            } else {
                wp_die('Le token n\'a pas pu être récupéré.');
            }
        }


    // -----------------------------
    // Construction du plugin
    // -----------------------------


        public function create_custom_tables() {
            global $wpdb;
        
            $charset_collate = $wpdb->get_charset_collate();
        
            // Définir les requêtes SQL séparément
            $sql_questions = "
            CREATE TABLE {$wpdb->prefix}custom_questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT(20) UNSIGNED NOT NULL,
                sku VARCHAR(255) NOT NULL,
                question_text TEXT,
                price DECIMAL(10, 2),
                id_category VARCHAR(255),
                description TEXT,
                min INT,
                max INT,
                FOREIGN KEY (product_id) REFERENCES {$wpdb->prefix}posts(ID)
            ) $charset_collate;
            ";
        
            $sql_options = "
            CREATE TABLE {$wpdb->prefix}custom_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2),
                option_name TEXT,
                id_category VARCHAR(255),
                description TEXT,
                FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}custom_questions(id)
            ) $charset_collate;
            ";
        
            $sql_formula_products = "
            CREATE TABLE {$wpdb->prefix}custom_formula_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                alpha_code VARCHAR(255),
                price DECIMAL(10, 2),
                product_name TEXT,
                id_category VARCHAR(255),
                description TEXT,
                FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}custom_questions(id)
            ) $charset_collate;
            ";

            $sql_questions_for_formulas = "
            CREATE TABLE {$wpdb->prefix}custom_questions_for_formulas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                formula_product_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                question_text TEXT,
                price DECIMAL(10, 2),
                id_category VARCHAR(255),
                description TEXT,
                min INT,
                max INT,
                FOREIGN KEY (formula_product_id) REFERENCES {$wpdb->prefix}custom_formula_products(id)
            ) $charset_collate;
            ";

            $sql_options_for_formulas = "
            CREATE TABLE {$wpdb->prefix}custom_options_for_formulas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                formula_question_id INT NOT NULL,
                sku VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2),
                option_name TEXT,
                id_category VARCHAR(255),
                description TEXT,
                FOREIGN KEY (formula_question_id) REFERENCES {$wpdb->prefix}custom_questions_for_formulas(id)
            ) $charset_collate;
            ";
        
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Exécuter les requêtes SQL séparément et capturer les résultats
            $result_questions = dbDelta($sql_questions);
            $result_options = dbDelta($sql_options);
            $result_formula_products = dbDelta($sql_formula_products);
            $result_questions_for_formulas = dbDelta($sql_questions_for_formulas);
            $result_options_for_formulas = dbDelta($sql_options_for_formulas);
        
            // Vérifier si les tables existent
            $tables_exist = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}custom_questions'", ARRAY_N);
        
            // Message de débogage
            $debug_message = 'Les tables personnalisées ont été créées ou mises à jour.<br>';
            $debug_message .= '<pre>' . print_r($result_questions, true) . '</pre>';
            $debug_message .= '<pre>' . print_r($result_options, true) . '</pre>';
            $debug_message .= '<pre>' . print_r($result_formula_products, true) . '</pre>';
            $debug_message .= '<pre>' . print_r($result_questions_for_formulas, true) . '</pre>';
            $debug_message .= '<pre>' . print_r($result_options_for_formulas, true) . '</pre>';
            $debug_message .= '<pre>Tables existantes : ' . print_r($tables_exist, true) . '</pre>';
        
            //($debug_message);
        }
        
        public function add_admin_menu() {
            add_menu_page(
                'WooCommerce API Integration',
                'API Integration',
                'manage_options',
                'woocommerce-api-integration',
                array($this, 'admin_page'),
                'dashicons-update',
                6
            );
        }

        public function admin_page() {
            echo '<div class="wrap">';
            echo '<h1>WooCommerce API Integration</h1>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="action" value="import_products">';
            submit_button('Importer les produits de l\'API');
            echo '</form>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="action" value="delete_products">';
            submit_button('Supprimer tous les produits WooCommerce');
            echo '</form>';

            // Afficher les produits ayant plusieurs catégories
            echo '<h2>Produits dans plusieurs catégories</h2>';
            $this->display_products_in_multiple_categories();
            echo '</div>';
        }   
        
        private function display_products_in_multiple_categories() {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish',
            );
        
            $products = get_posts($args);
            $products_with_multiple_categories = array();
        
            foreach ($products as $product) {
                $product_id = $product->ID;
                $wc_product = wc_get_product($product_id);
                $categories = $wc_product->get_category_ids();
        
                if (count($categories) > 1) {
                    $products_with_multiple_categories[] = array(
                        'name' => $wc_product->get_name(),
                        'sku' => $wc_product->get_sku(),
                        'categories' => array_map(function($cat_id) {
                            $term = get_term($cat_id);
                            return $term->name;
                        }, $categories)
                    );
                }
            }
        
            if (!empty($products_with_multiple_categories)) {
                echo '<ul>';
                foreach ($products_with_multiple_categories as $product) {
                    echo '<li>';
                    echo '<strong>' . esc_html($product['name']) . ' (SKU: ' . esc_html($product['sku']) . ')</strong><br>';
                    echo 'Catégories : ' . implode(', ', $product['categories']);
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>Aucun produit dans plusieurs catégories.</p>';
            }
        }    

        private function delete_all_products() {
            // Récupérer tous les produits
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'any'
            );
        
            $products = get_posts($args);
        
            // Supprimer chaque produit
            foreach ($products as $product) {
                wp_delete_post($product->ID, true);
            }
        
            // Message de confirmation
            echo '<div class="updated"><p>Tous les produits ont été supprimés avec succès.</p></div>';
        }

    
    // -----------------------------
    // Section API Communication
    // -----------------------------




        /**
         * Récupération de tous les produits menlog pour les envoyer à import_menlog_from_api()
         * @return mixed
         */
        private function get_products() {
            $url = "https://{$this->server}/{$this->delivery}/{$this->uuidclient}/{$this->uuidmagasin}/check_products?token={$this->token}&nocache=true";
            
            $options = [
                'http' => [
                    'header' => "Authorization: Bearer {$this->token}\r\n",
                    'method' => 'GET'
                ]
            ];
            
            $context = stream_context_create($options);
        
            try {
                $result = file_get_contents($url, false, $context);
                
                if ($result === FALSE) {
                    throw new Exception('Error obtaining products: failed to retrieve data from API.');
                }
        
                $data = json_decode($result, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Error decoding JSON: ' . json_last_error_msg());
                }
        
                // Vérifier la réponse pour voir si l'authentification a échoué
                if (isset($data['message']) && strpos($data['message'], 'InvalidCredentials') !== false) {
                    throw new Exception('Error: Invalid credentials. Please check your token.');
                }
        
                // Vérifier si le code de retour est 401 (Unauthorized)
                if (isset($http_response_header) && strpos($http_response_header[0], '401') !== false) {
                    throw new Exception('Error 401: Unauthorized. Please check your token.');
                }
        
                return $data;
        
            } catch (Exception $e) {
                // Log l'erreur
                error_log("Erreur lors de la récupération des produits : " . $e->getMessage());
                
                // Envoyer un email avec les détails de l'erreur
                $this->envoyer_email_debug('Erreur lors de la récupération des produits', $e->getMessage());
        
                // Gérer l'erreur comme vous le souhaitez (par exemple, renvoyer une réponse vide ou une erreur)
                return array('error' => true, 'message' => $e->getMessage());
            }
        }

        /**
         * Permet de lancer le processus d'import suite au clic sur le bouton
         * Récupère tous les produits de menlog
         * Lance le processus des catégories
         * Lance le processus des produits (import_products)
         * @return void
         */
        public function import_menlog_from_api() {
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
                if ($_POST['action'] == 'import_products') {
                    $this->token = $this->get_token();
        
                    // Récupère tous les produits de Menlog (productType 1, 2, 3 et 4)
                    $products_data = $this->get_products();
        
                    // Si on a des produits, on commence les imports
                    if (!empty($products_data)) {
                        // On commence par importer les catégories
                        $this->import_categories($products_data['menu']['categories']);
                        // On importe ensuite les produits
                        $this->import_products($products_data['menu']['products']);
        
                        // Écrire les résultats dans un fichier texte
                        $this->write_import_results();
        
                        echo '<div class="updated"><p>Produits importés avec succès.</p></div>';
                    } else {
                        echo '<div class="updated"><p>Products_data est vide.</p></div>';
                    }
                } elseif ($_POST['action'] == 'delete_products') {
                    $this->delete_all_products();
                    echo '<div class="updated"><p>Tous les produits WooCommerce ont été supprimés.</p></div>';
                }
            }
        }
    


    // -----------------------------
    // Section Importation de Données
    // -----------------------------



        /**
         * Importe les catégories ou mets à jour les catégories existantes si nécessaire.
         *
         * @param array $categories Un tableau de catégories Menlog, chaque catégorie étant un tableau associatif contenant :
         *   - string 'name' : Le nom de la catégorie.
         *   - string 'idCategory' : Le slug de la catégorie.
         */
        private function import_categories($categories) {
            // Étape 1 : Créer un tableau des ID de catégories de Menlog à partir de la liste fournie par l'API
            $menlog_category_ids = array_column($categories, 'idCategory');

            // Étape 2 : Récupérer toutes les catégories WooCommerce avec un 'menlog_id_category'
            $existing_categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'meta_query' => array(
                    array(
                        'key' => 'menlog_id_category',
                        'compare' => 'EXISTS'
                    )
                ),
                'hide_empty' => false
            ));

            // Étape 3 : Supprimer les catégories qui n'existent plus dans l'API Menlog
            foreach ($existing_categories as $existing_category) {
                $existing_menlog_id = get_term_meta($existing_category->term_id, 'menlog_id_category', true);
                if (!in_array($existing_menlog_id, $menlog_category_ids)) {
                    // Supprimer la catégorie (sans supprimer les produits)
                    wp_delete_term($existing_category->term_id, 'product_cat');
                    $this->categories_deleted++;
                    $this->category_deletions[] = "Category '{$existing_category->name}' deleted because it no longer exists in Menlog.";
                }
            }

            // Étape 4 : Importer ou mettre à jour les catégories existantes
            foreach ($categories as $category) {
                $menlog_id_category = $category['idCategory'];
                
                // Rechercher la catégorie existante par l'ID Menlog stocké en tant que méta-donnée
                $existing_category = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'meta_query' => array(
                        array(
                            'key' => 'menlog_id_category',
                            'value' => $menlog_id_category,
                            'compare' => '='
                        )
                    ),
                    'hide_empty' => false,
                    'number' => 1
                ));

                $new_category_name = trim($category['name']);
                
                if (!empty($existing_category) && !is_wp_error($existing_category)) {
                    $existing_category = $existing_category[0]; // Obtenir le premier résultat

                    if ($existing_category->name !== $new_category_name) {
                        wp_update_term($existing_category->term_id, 'product_cat', array('name' => $new_category_name));
                        $this->categories_updated++;
                        $this->category_updates[] = "Category '{$existing_category->name}' updated to '{$new_category_name}'";
                    } else {
                        $this->categories_skipped++;
                    }

                    // Mettre à jour ou ajouter l'ID Menlog en tant que méta-donnée, si nécessaire
                    update_term_meta($existing_category->term_id, 'menlog_id_category', $menlog_id_category);
                
                } else {
                    // Rechercher par slug s'il n'y a pas de correspondance avec le méta
                    $existing_category_by_slug = get_term_by('slug', $menlog_id_category, 'product_cat');

                    if ($existing_category_by_slug) {
                        // Mettre à jour le nom si nécessaire
                        if ($existing_category_by_slug->name !== $new_category_name) {
                            wp_update_term($existing_category_by_slug->term_id, 'product_cat', array('name' => $new_category_name));
                            $this->categories_updated++;
                            $this->category_updates[] = "Category '{$existing_category_by_slug->name}' updated to '{$new_category_name}'";
                        } else {
                            $this->categories_skipped++;
                        }

                        // Ajouter l'ID Menlog en tant que méta-donnée
                        update_term_meta($existing_category_by_slug->term_id, 'menlog_id_category', $menlog_id_category);

                    } else {
                        // Créer une nouvelle catégorie si aucune correspondance n'est trouvée
                        $new_category = wp_insert_term($new_category_name, 'product_cat', array('slug' => $menlog_id_category));
                        if (!is_wp_error($new_category)) {
                            $this->categories_created++;
                            // Ajouter l'ID Menlog en tant que méta-donnée
                            update_term_meta($new_category['term_id'], 'menlog_id_category', $menlog_id_category);
                        }
                    }
                }
            }
        }

        
    
        /**
         * Importe les produits WooCommerce (productType 1) en ajoutant de nouveaux produits ou en mettant à jour les produits existants.
         * Met également en brouillon les produits qui ne sont plus présents sur Menlog.
         * 
         * @param array $products Un tableau de produits, chaque produit étant un tableau associatif contenant :
         *   - int 'productType' : Le type de produit (ici, productType 1).
         *   - string 'sku' : Le SKU du produit.
         *   - string 'alphaCode' : ??. 
         *   - float 'price' : Le prix du produit.
         *   - float 'priceWithoutTax' : Le prix du produit dans les taxes.
         *   - float 'vatRate' : Le pourcentage des taxes.
         *   - float 'fidRate' : ??. 
         *   - string 'name' : Le nom du produit.
         *   - string 'idCategory' : Le slug de la catégorie du produit.
         *   - string 'description' : La description du produit.
         *   - array 'subProducts' : Les sous-produits associés au produit.
         */
        private function import_products($products) {
            $menlog_skus_product_type_1 = [];
        
            // Parcourir les produits Menlog et traiter les productType 1
            foreach ($products as $product) {
                // Si le produit est un PT1, on continue, 
                // Sinon, on passe au produit menlog suivant
                if ($product['productType'] == 1) {
                    $menlog_skus_product_type_1[] = $product['sku'];
        
                    // Vérifier si le produit existe déjà par son SKU sur WooCommerce
                    $existing_product_id = wc_get_product_id_by_sku($product['sku']);
        
                    // Le produit n'existe pas sur WooCommerce, on peut le créer
                    if (!$existing_product_id) {
                        // Création du produit et récupération de son ID
                        $product_id = $this->insert_product($product);
        
                        // Vérifier si le produit a bien été créé et que l'ID est valide
                        if ($product_id) {
                            // Vérifier si le produit a des sous-produits
                            if (!empty($product['subProducts'])) {
                                // Il a des sous-prpduits, donc pour ce nouveau productType 1, on va lui ajouter ses sous-produits
                                // Pour ça, on envoit $product['subProducts'] qui contient à ce stade, les productType 3
                                // Dans le cas d'un produit simple : 3, 2
                                // Dans le cas d'un produit Formule Ecommerce : 3, 4, 3, 2
                                $this->process_sub_products($product_id, $product['subProducts'], $products, $product);
                                $this->products_with_PT3++;
                            }
                        } else {
                            // Gérer le cas où le produit n'a pas pu être créé
                            $this->message_erreur .= 'Erreur: Le produit dont le SKU doit être' . $product['sku'] .' n\'a pas pu être créé dans la fonction import_products().';
                        }
                    } else {
                        // Vérifier si la catégorie est différente et mettre à jour le produit existant si nécessaire
                        $this->update_product_with_category($existing_product_id, $product, $products);
                    }
                }
            }
        
            // Mettre en brouillon les produits qui ne sont plus présents sur Menlog
            $this->set_missing_products_to_draft($menlog_skus_product_type_1);
        }    

        /**
         * Le produit n'existe pas, donc on insère un produit
         * $product == tout le contenu d'un productType 1 de Menlog
         */
        private function insert_product($product) {
            $category_id = get_term_by('slug', $product['idCategory'], 'product_cat')->term_id;

            // Création d'un nouveau produit WooCommerce simple
            $wc_product = new WC_Product_Simple();
            $wc_product->set_name($product['name']);
            $wc_product->set_sku($product['sku']);
            $wc_product->set_regular_price($product['price']);
            $wc_product->set_description($product['description']);
            $wc_product->set_category_ids(array($category_id));
            $wc_product->save();

            // Ajouter les métadonnées par défaut pour la disponibilité
            update_post_meta($wc_product->get_id(), 'is_ephemeral', 'no'); // Le produit n'est pas éphémère par défaut
            update_post_meta($wc_product->get_id(), 'daily_quota', '50'); // Production quotidienne par défaut : 50 unités

            // Définir la disponibilité pour chaque jour de la semaine
            $days_of_week = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
            foreach ($days_of_week as $day) {
                update_post_meta($wc_product->get_id(), 'availability_' . $day, 'yes'); // Disponible chaque jour
            }

            // Incrémenter le compteur de produits créés
            $this->products_created++;
            
            return $wc_product->get_id();
        }


        /**
         * Met à jour un produit WooCommerce existant
         * Permet aussi à un produit d'avoir plusieurs catégories
         * 
         * @param int $product_id L'ID du produit WooCommerce.
         * @param array $product Les données du produit Menlog PT1 en cours de traitement.
         * @param array $menlogProducts Tous les produits de Menlog (pour passer à la fonction update_product())
         */
        private function update_product_with_category($product_id, $product, $menlogProducts) {
            // Récupérer le produit WooCommerce existant à partir de son ID
            $wc_product = wc_get_product($product_id);

            // Obtenir les IDs de catégorie actuels du produit WooCommerce
            $current_category_ids = $wc_product->get_category_ids();

            // Obtenir l'ID de la nouvelle catégorie à partir de son slug
            $new_category_id = get_term_by('slug', $product['idCategory'], 'product_cat')->term_id;

            // Ajouter la nouvelle catégorie si elle n'est pas déjà associée au produit
            if (!in_array($new_category_id, $current_category_ids)) {
                $current_category_ids[] = $new_category_id;
                $wc_product->set_category_ids($current_category_ids);
                $wc_product->save();
                $this->products_updated++;
                $this->product_updates[] = "Product '{$product['sku']}' updated: Added category '{$product['idCategory']}'";
            }

            // Mettre à jour le produit existant s'il y a d'autres modifications
            // $product contient le PT1 Menlog en cours de traitement
            $this->update_product($product_id, $product, $menlogProducts);
        }

        /**
         * Permet de mettre à jour un produit s'il existe déjà, ou de ne rien faire si aucune mise à jour n'est nécessaire.
         * 
         * @param int $product_id L'ID du produit à mettre à jour.
         * @param array $product_data Les nouvelles données du productType 1 Menlog, incluant :
         *   - string 'name' : Le nouveau nom du produit.
         *   - float 'price' : Le nouveau prix du produit.
         *   - string 'description' : La nouvelle description du produit.
         *   - string 'idCategory' : Le slug de la nouvelle catégorie du produit.
         * @param array $menlogProducts Tous les produits de Menlog
         */
        private function update_product($product_id, $product_data, $menlogProducts) {
            // Crée une instance de produit WooCommerce simple pour l'ID donné
            $wc_product = new WC_Product_Simple($product_id);

            // Récupère le nom actuel du produit WooCommerce
            $current_name = $wc_product->get_name();
            // Récupère le prix actuel du produit WooCommerce
            $current_price = $wc_product->get_regular_price();
            // Récupère la description actuelle du produit WooCommerce
            $current_description = $wc_product->get_description();
            // Récupère les IDs des catégories actuelles du produit WooCommerce
            $current_category_ids = $wc_product->get_category_ids();

            // Récupère l'ID de la catégorie correspondant au slug de catégorie fourni dans les données du produit
            $category_id = get_term_by('slug', $product_data['idCategory'], 'product_cat')->term_id;

            // Vérifie si le nom du produit a changé
            $is_name_different = $current_name !== $product_data['name'];
            // Vérifie si le prix du produit a changé (comparaison non stricte)
            $is_price_different = $current_price != $product_data['price'];
            // Vérifie si la description du produit a changé
            $is_description_different = $current_description !== $product_data['description'];
            // Vérifie si la catégorie du produit a changé ou si une nouvelle catégorie doit être ajoutée
            $is_category_different = !in_array($category_id, $current_category_ids);

            // Initialise un tableau pour garder trace des mises à jour effectuées
            $updates = [];
            // Met à jour le nom du produit si nécessaire et enregistre le changement
            if ($is_name_different) {
                $updates[] = "Name: '{$current_name}' to '{$product_data['name']}'";
                $wc_product->set_name($product_data['name']);
            }
            // Met à jour le prix du produit si nécessaire et enregistre le changement
            if ($is_price_different) {
                $updates[] = "Price: '{$current_price}' to '{$product_data['price']}'";
                $wc_product->set_regular_price($product_data['price']);
            }
            // Met à jour la description du produit si nécessaire et enregistre le changement
            if ($is_description_different) {
                $updates[] = "Description: '{$current_description}' to '{$product_data['description']}'";
                $wc_product->set_description($product_data['description']);
            }
            // Ajoute une nouvelle catégorie au produit si nécessaire et enregistre le changement
            if ($is_category_different) {
                $current_category_ids[] = $category_id;
                $updates[] = "Category added: '{$product_data['idCategory']}'";
                $wc_product->set_category_ids($current_category_ids);
            }

            // Vérifie et met à jour les sous-produits associés (productType 3) si le PT1 a des PT3 associés
            if (!empty($product_data['subProducts'])) {
                // Traite les sous-produits associés au produit principal PT1 en cours
                $this->process_sub_products($product_id, $product_data['subProducts'], $menlogProducts, $product_data);
            }

            if (!empty($updates)) {
                $this->products_updated++;
                $this->product_updates[] = "Product '{$product_data['sku']}' updated: " . implode('; ', $updates);
                $wc_product->save();
            } else {
                $this->products_skipped++;
            }
        }

        /**
         * Met les produits en brouillon s'ils ne sont plus présents sur Menlog.
         * 
         * @param array $menlog_skus_product_type_1 Liste des SKU des produits de type 1 présents sur Menlog.
         */
        private function set_missing_products_to_draft($menlog_skus_product_type_1) {
            // Récupérer tous les produits WooCommerce
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'compare' => 'EXISTS'
                    )
                )
            );

            $query = new WP_Query($args);

            // Parcours de tous les produits
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                $sku = $product->get_sku();

                // Si le produit WooCommerce n'est pas dans la liste des SKU de Menlog (PT1), le mettre en brouillon
                if (!in_array($sku, $menlog_skus_product_type_1)) {
                    $product->set_status('draft');
                    $product->save();

                    $this->products_deleted++;
                }
            }

            // Réinitialiser la requête globale de WordPress
            wp_reset_postdata();
        }

    

    // -----------------------------
    // Section Gestion des Sous-produits et Options
    // -----------------------------

        /**
         * Traite les sous-produits d'un produit principal (productType 1).
         * 
         * @param int $product_id L'ID du produit principal de type 1 en cours de traitement.
         * @param array $sub_products_sku Un tableau contenant tous les SKU sous-produits de type 3 (questions) associés à ce produit principal.
         * @param array $all_products Un tableau contenant tous les produits de Menlog.
         * @param array $product Produit de type 1 en cours de traitement
         */
        private function process_sub_products( $product_id, $sub_products_sku, $all_products, $product = null ) {
            global $wpdb;

            // Récupérer les questions existantes pour ce produit dans la BDD
            $existing_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $product_id), ARRAY_A);
            
            // Pour chaque sous-produit du productType 1
            // Donc pour chaque productType 3 Menlog du productType 1 en cours
            // Donc la combinaison (PT1 > PT3)
            foreach ( $sub_products_sku as $subProductSku ) {

                // Si trouvé, récupère tout le productType 3 (la question) Menlog
                // La variable contient alors tout le contenu de productType3
                $question = $this->get_product_by_sku( $all_products, $subProductSku );

                // S'il y a bien un productType 3, on continue
                // Dans la théorie, s'il n'y a rien, la fonction process_sub_products n'est pas appelée
                // mais on ajoute une sécurité supplémentaire
                if ( !empty($question) ) {

                    // Vérifier si la question existe déjà
                    // Donc on compare la PT3 de la BDD avec la PT3 Menlog
                    if (!empty($existing_questions)) {
                        $existing_question = array_filter($existing_questions, function($q) use ($question) {
                            return $q['sku'] === $question['sku'];
                        });
                    }

                    // Si la question (PT3) existe déjà, on met à jour ou on passe
                    // Sinon, nous ajoutons la question comme nouvelle entrée.
                    if (!empty($existing_question)) {
                        // Vérifie si la question existante n'est pas vide. Cela signifie que la question est déjà présente dans la base de données.

                        // Récupère le premier élément de l'array $existing_question pour obtenir les données actuelles de la question.
                        $existing_question_data = array_values($existing_question)[0];

                        // Récupère l'ID de la question existante pour l'utiliser lors de la mise à jour.
                        $question_id = $existing_question_data['id'];

                        // Vérifie si l'une des données importantes de la question a changé en comparant les valeurs actuelles et les nouvelles.
                        if (
                            $existing_question_data['question_text'] !== $question['name'] ||
                            $existing_question_data['price'] != $question['price'] ||  // Comparaison non stricte pour les valeurs numériques
                            $existing_question_data['id_category'] !== $question['idCategory'] ||
                            $existing_question_data['description'] !== $question['description'] ||
                            $existing_question_data['min'] != $question['min'] ||  // Comparaison non stricte pour les valeurs numériques
                            $existing_question_data['max'] != $question['max']  // Comparaison non stricte pour les valeurs numériques
                        ) {
                            // Met à jour la question existante avec les nouvelles données fournies.
                            // Ici, $question contient toute la question de Menlog
                            if (!$this->update_question($question_id, $question)) {
                                // Enregistre un message d'erreur si la mise à jour échoue
                                $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$question['sku']}' n'a pas pu être mise à jour pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                            } else {
                                $this->products_PT3_updated++;
                            }
                        } else {
                            $this->products_PT3_skipped++;
                        }
                    } else {
                        // Ajouter une nouvelle question
                        $question_id = $this->insert_question($product_id, $question);
                        if (!$question_id) {
                            // Enregistre un message d'erreur si l'insertion échoue
                            $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$question['sku']}' n'a pas pu être ajoutée pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                        } else {
                            $this->products_PT3_created++;
                        }
                    }

                    /**
                     * Suppression des PT2 > PT3 > PT4 si c'est un produit Formule qui ne sont plus dans Menlog
                     * Suppression des PT2 si c'est un produit Simple qui ne sont plus dans Menlog
                     */
                    if ($this->isFormula($product, $all_products)) {
                        // Le PT1 est une formule, pour chaque PT3 en cours de visionnage on doit donc supprimer tous les : 
                            // - PT4 qui ne sont plus sur Menlog
                            // - PT3 qui ne sont plus sur Menlog
                            // - PT2 qui ne sont plus sur Menlog
                        
                        $this->message_erreur .= "\n\nDébogage: Le produit est une formule. Traitement de la combinaison PT1 > PT3 pour le produit Menlog sku {$product['sku']}.\n";
                    
                        // Récupérer tous les produits de formule existants (PT4) dans la BDD qui sont reliés à cette combinaison PT1 > PT3 
                        $existing_formula_products = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d", $question_id), ARRAY_A);
                        $this->message_erreur .= "- Débogage: Récupération des produits de formule PT4 pour la question ID {$question_id}. Nombre trouvé: " . count($existing_formula_products) . ".\n";

                        // Récupérer le contenu de la colonne sku et id dans la BDD du PT4 pour cette combinaison PT1 > PT3
                        $this->message_erreur .= "- Débogage: Stockage des données PT4 BDD dans formula_products_by_id :\n";
                        $formula_products_by_id = [];
                        foreach ($existing_formula_products as $formula_product) {
                            $formula_products_by_id[$formula_product['id']] = $formula_product['sku'];
                            $this->message_erreur .= "-- Débogage: Produit PT4 trouvé dans BDD avec ID {$formula_product['id']} avec SKU {$formula_product['sku']}.\n";
                        }
                    
                        // Collecter les SKUs des PT4 actuels dans Menlog pour cette combinaison PT1 > PT3
                        $this->message_erreur .= "- Débogage: Stockage des données SKU des PT4 Menlog dans new_formula_skus :\n";
                        $new_formula_skus = [];
                        foreach ($question['subProducts'] as $subProductSku) {
                            $subProduct = $this->get_product_by_sku($all_products, $subProductSku);
                            if ($subProduct && $subProduct['productType'] == 4) {
                                $new_formula_skus[] = $subProduct['sku'];
                                $this->message_erreur .= "-- Débogage: Produit PT4 trouvé dans Menlog avec SKU {$subProduct['sku']}.\n";
                            } else {
                                $this->message_erreur .= "-- Débogage: Produit PT4 avec SKU {$subProductSku} introuvable ou non valide dans Menlog. Problème potentiel avec get_product_by_sku(). \n";
                            }
                        }

                        // A ce stade :
                            // formula_products_by_id contient tous les ID et SKU du PT4 BDD pour ce PT3 en cours
                            // new_formula_skus contient tous les SKU PT4 menlog pour ce PT3 en cours
                    
                        $this->message_erreur .= "- Débogage: Comparaison des produits PT4 en BDD et dans Menlog pour la combinaison PT1 > PT3 :\n";
                    
                        // Parcourir chaque PT4 BDD pour vérifier s'ils sont encore présents en BDD
                        // Dans cette boucle, on va chercher à supprimer PT4 > PT3 > PT2 qui ne sont plus dans Menlog
                        foreach ($formula_products_by_id as $existing_formula_id => $existing_formula_sku) {
                            $this->message_erreur .= "-- Débogage: Traitement du produit PT4 avec ID {$existing_formula_id} SKU {$existing_formula_sku}\n";
                            $formula_product = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE id = %d", $existing_formula_id), ARRAY_A);
                            
                            // Le PT4 a bien été retrouvé en BDD
                            if ($formula_product) {
                                $this->message_erreur .= "--- Débogage: On a bien récupéré le PT4 avec ID {$existing_formula_id} SKU {$existing_formula_sku} dans le BDD.\n";
                                $formula_product_id = $formula_product['id'];
                    
                                // Vérifier si le PT4 existe toujours dans Menlog
                                // existing_formula_sku correspond au sku PT4 BDD qu'on regarde
                                // new_formula_skus contient tous les PT4 Menlog pour ce PT3
                                if (in_array($existing_formula_sku, $new_formula_skus)) {
                                    // Le PT4 existe toujours dans Menlog, on va donc le garder
                                    // En revanche, on doit vérifier si le ou les PT3 existent toujours pour ce PT4
                                    $this->message_erreur .= "---- Débogage: Le produit PT4 avec SKU {$existing_formula_sku} existe toujours dans Menlog.\n";
                    
                                    // Récupérer toutes les questions PT3 BDD qui sont reliés au produit PT4 BDD
                                    $existing_questions_pt3_pt4 = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d", $formula_product_id), ARRAY_A);
                                    $this->message_erreur .= "---- Débogage: Récupération des questions PT3 dans la BDD pour le produit PT4 ID {$formula_product_id}. Nombre trouvé: " . count($existing_questions) . ".\n";
                    
                                    // Récupérer les nouvelles questions PT3 pour ce PT4 dans Menlog
                                    $new_question_skus = [];
                                    $formula_product_menlog = $this->get_product_by_sku($all_products, $existing_formula_sku);
                                    foreach ($formula_product_menlog['subProducts'] as $PT4subQuestionSku) {
                                        $subQuestion = $this->get_product_by_sku($all_products, $PT4subQuestionSku);
                                        if ($subQuestion && $subQuestion['productType'] == 3) {
                                            $new_question_skus[] = $subQuestion['sku'];
                                        }
                                    }
                                    $this->message_erreur .= "---- Débogage: Récupération des questions PT3 dans Menlog le produit PT4 ID {$formula_product_id}. Nombre trouvé: " . count($new_question_skus) . ".\n";
                    
                                    // Supprimer les questions PT3 et leurs options PT2 qui ne sont plus présentes dans Menlog
                                    foreach ($existing_questions_pt3_pt4 as $existing_question) {
                                        $existing_question_sku = $existing_question['sku'];
                                        $question_id_to_delete = $existing_question['id'];

                                        $this->message_erreur .= "----- Débogage: Traitement du produit PT3 avec ID {$question_id_to_delete} SKU {$existing_question_sku}\n";
                    
                                        // Si la question n'existe plus dans Menlog, on doit la supprimer de la BDD
                                        if (!in_array($existing_question_sku, $new_question_skus)) {
                                            $this->message_erreur .= "------ Débogage: La question PT3 avec SKU {$existing_question_sku} n'existe plus dans Menlog. Suppression en cours.\n";
                    
                                            // Supprimer les options PT2 associées au PT3
                                            $options_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d", $question_id_to_delete), ARRAY_A);
                                            foreach ($options_to_delete as $option_to_delete) {
                                                $this->message_erreur .= "------- Débogage: Traitement du produit PT2 avec ID {$option_to_delete['id']} SKU {$option_to_delete['sku']}\n";

                                                if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                                    $this->message_erreur .= "-------- Erreur : L'option PT2 avec ID '{$option_to_delete['id']}' n'a pas pu être supprimée pour la question PT3 avec SKU '{$existing_question_sku}' pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                                } else {
                                                    $this->products_PT2_PT3_PT4_deleted++;
                                                    $this->message_erreur .= "-------- Débogage: Option PT2 avec ID '{$option_to_delete['id']}' supprimée avec succès pour la question PT3 avec SKU '{$existing_question_sku}'.\n";
                                                }

                                                $this->message_erreur .= "------- Débogage: Traitement terminé du produit PT2 avec ID {$option_to_delete['id']} SKU {$option_to_delete['sku']}\n";
                                            }

                                            $this->message_erreur .= "------- Débogage: Suppression du produit PT3 avec ID {$question_id_to_delete} SKU {$existing_question_sku}\n";
                    
                                            // Supprimer la question PT3
                                            if (!$this->delete_question_for_formula($question_id_to_delete)) {
                                                $this->message_erreur .= "------- Erreur : La question PT3 avec SKU '{$existing_question_sku}' n'a pas pu être supprimée pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                            } else {
                                                $this->products_PT3_PT4_deleted++;
                                                $this->message_erreur .= "------- Débogage: La question PT3 avec SKU '{$existing_question_sku}' supprimée avec succès.\n";
                                            }
                                        } else {
                                            // La question existe encore dans la BDD, il y a donc juste le PT2 à vérifier
                                            $this->message_erreur .= "------ Débogage: La question PT3 avec SKU {$existing_question_sku} existe encore dans Menlog. Vérification des options PT2 en cours.\n";
                    
                                            // Supprimer uniquement les options PT2 qui n'existent plus dans Menlog
                                            $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d", $question_id_to_delete), ARRAY_A);
                                            $existing_option_skus = array_column($options, 'sku');

                                            $this->message_erreur .= "------ Débogage: Récupération des PT2 BDD pour ce PT3 Formule terminé. Nombre trouvé: " . count($existing_option_skus) . ".\n";
                                            $this->message_erreur .= "------ Débogage: Récupération des PTD Menlog pour ce PT3 Formule en cours : \n";
                    
                                            // Récupération des skus PT2 sur Menlog
                                            $new_option_skus = [];
                                            $formula_product_question_menlog = $this->get_product_by_sku($all_products, $existing_question_sku);
                                            foreach ($formula_product_question_menlog['subProducts'] as $nestedOptionSku) {
                                                $nestedOption = $this->get_product_by_sku($all_products, $nestedOptionSku);
                                                if ($nestedOption && $nestedOption['productType'] == 2) {
                                                    $new_option_skus[] = $nestedOption['sku'];
                                                    $this->message_erreur .= "------- Débogage: Option PT2 trouvée dans Menlog avec SKU {$nestedOption['sku']} pour la question PT3 avec SKU {$existing_question_sku}.\n";
                                                }
                                            }

                                            $this->message_erreur .= "------ Débogage: Récupération des PT2 Menlog pour ce PT3 Formule terminé. Nombre trouvé: " . count($new_option_skus) . ".\n";
                                            $this->message_erreur .= "------ Débogage: ";
                    
                                            foreach ($existing_option_skus as $existing_option_sku) {
                                                $this->message_erreur .= "------- Débogage: Traitement du produit PT2 avec SKU {$existing_option_sku}\n";

                                                if (!in_array($existing_option_sku, $new_option_skus)) {
                                                    $this->message_erreur .= "-------- Débogage: L'option PT2 avec SKU {$existing_option_sku} n'existe plus dans Menlog. Suppression en cours.\n";

                                                    $option_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d AND sku = %s", $question_id_to_delete, $existing_option_sku), ARRAY_A);
                                                    if ($option_to_delete) {
                                                        if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                                            $error_message = $wpdb->last_error;
                                                            $this->message_erreur .= "--------- Erreur : L'option PT2 avec SKU '{$existing_option_sku}' (ID: {$option_to_delete['id']}) n'a pas pu être supprimée pour la question PT3 avec SKU '{$existing_question_sku}' (Question ID: {$question_id_to_delete}) pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products(). Erreur SQL : {$error_message}\n";
                                                        } else {
                                                            $this->products_PT2_PT3_PT4_deleted++;
                                                            $this->message_erreur .= "--------- Débogage: Option PT2 avec SKU '{$existing_option_sku}' supprimée avec succès pour la question PT3 avec SKU '{$existing_question_sku}'.\n";
                                                        }
                                                    }
                                                } else {
                                                    $this->message_erreur .= "-------- Débogage: L'option PT2 avec SKU {$existing_option_sku} existe encore dans Menlog.\n";
                                                }
                                            }                                        
                                        }
                                    }
                                } else {
                                    $this->message_erreur .= "---- Débogage: Le produit PT4 avec SKU {$existing_formula_sku} n'existe plus dans Menlog. Suppression de tous les éléments associés en cours.\n";
                    
                                    // Supprimer tout ce qui est lié : PT3 et PT2
                                    $questions_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d", $formula_product_id), ARRAY_A);
                                    foreach ($questions_to_delete as $question_to_delete) {
                                        $this->message_erreur .= "----- Débogage: Le PT4 avec SKU {$existing_formula_sku} a dans sa BDD une question PT3 au sku {$question_to_delete['sku']} et à ID {$question_to_delete['id']}";
                                        $question_id_to_delete = $question_to_delete['id'];
                    
                                        // Supprimer les options PT2
                                        $options_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d", $question_id_to_delete), ARRAY_A);
                                        foreach ($options_to_delete as $option_to_delete) {
                                            $this->message_erreur .= "------ Débogage: Le PT3 au sku {$question_to_delete['sku']} et à ID {$question_to_delete['id']} a dans sa BDD une option PT2 au sku {$option_to_delete['sku']} et à ID {$option_to_delete['id']}\n";
                                            if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                                $this->message_erreur .= "------- Erreur : L'option PT2 avec ID '{$option_to_delete['id']}' n'a pas pu être supprimée pour la question PT3 avec SKU '{$question_to_delete['sku']}' pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                            } else {
                                                $this->products_PT2_PT3_PT4_deleted++;
                                                $this->message_erreur .= "------- Débogage: Option PT2 avec ID '{$option_to_delete['id']}' supprimée avec succès pour la question PT3 avec SKU '{$question_to_delete['sku']}'.\n";
                                            }
                                        }

                                        $this->message_erreur .= "----- Débogage: Tentative de suppression du PT3 avec SKU {$question_to_delete['sku']} et à ID {$question_to_delete['id']}.\n";
                    
                                        // Supprimer la question PT3
                                        if (!$this->delete_question_for_formula($question_id_to_delete)) {
                                            $this->message_erreur .= "------ Erreur : La question PT3 avec SKU '{$question_to_delete['sku']}' n'a pas pu être supprimée pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                        } else {
                                            $this->products_PT3_PT4_deleted++;
                                            $this->message_erreur .= "------ Débogage: La question PT3 avec SKU '{$question_to_delete['sku']}' supprimée avec succès.\n";
                                        }
                                    }

                                    $this->message_erreur .= "---- Débogage: Tentative de suppression du PT4 avec SKU {$existing_formula_sku}.\n";
                    
                                    // Supprimer le produit de formule PT4
                                    if (!$this->delete_formula_product($formula_product_id)) {
                                        $this->message_erreur .= "----- Erreur : Le produit de formule PT4 avec SKU '{$existing_formula_sku}' n'a pas pu être supprimé pour le PT1 dont l'ID est '{$product_id}' dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT4_deleted++;
                                        $this->message_erreur .= "----- Débogage: Le produit de formule PT4 avec SKU '{$existing_formula_sku}' supprimé avec succès.\n";
                                    }
                                }
                            } else {
                                $this->message_erreur .= "--- Erreur : Le produit PT4 avec ID '{$existing_formula_id}' n'a pas été trouvé dans la base de données.\n";
                            }
                        }
                    } else {
                        // Ici, PT1 n'est pas une formule

                        // Récupérer les options existantes pour cette question dans la BDD
                        $existing_options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d", $question_id), ARRAY_A);

                        // Récupération de toutes les valeurs dans la colonne sku de la table
                        $existing_option_skus = array_column($existing_options, 'sku');

                        // Collecter les SKUs des nouvelles options dans Menlog
                        $new_option_skus = [];

                        // Parcours des SKUs des PT3 de Menlog (donc des PT2)
                        foreach ($question['subProducts'] as $subProductSku) {
                            // Récupérer le sous-produit complet à partir de son SKU
                            $subProduct = $this->get_product_by_sku($all_products, $subProductSku);
                            if ($subProduct && $subProduct['productType'] == 2) {
                                $new_option_skus[] = $subProduct['sku'];
                            }
                        }

                        // Supprimer les options qui ne sont plus présentes dans Menlog
                        foreach ($existing_option_skus as $existing_option_sku) {
                            // Si le PT2 BDD n'est pas présent dans la liste des PT2 Menlog pour le PT3 en cours...
                            if (!in_array($existing_option_sku, $new_option_skus)) {
                                // On va chercher l'option dans la BDD
                                $option_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d AND sku = %s", $question_id, $existing_option_sku), ARRAY_A);
                                // Si on trouve l'option dans la BDD, on tente de la supprimer
                                if ($option_to_delete) {
                                    if (!$this->delete_option($option_to_delete['id'])) {
                                        // Enregistre un message d'erreur si la suppression échoue
                                        $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$existing_option_sku}' n'a pas pu être supprimée pour la question PT3 avec SKU '{$question['sku']}' pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT2_deleted++;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Ajout et modifications des sous-produits de chaque PT1 > PT3 (produit simple ou formule)
                    // Donc pour chaque productType 2 ou 4 du productType 3 de menlog
                    // $question le PT3 du PT1 en cours
                    // $question['subProducts'] contient les SKU (PT2 ou PT4) de la combinaison PT1 > PT3 en cours
                    foreach ($question['subProducts'] as $subQuestionSku) {
        
                        // Si trouvé, récupère tout le productType 2 ou 4 (l'option ou le produit de la formule)
                        $sub_product = $this->get_product_by_sku($all_products, $subQuestionSku);
            
                        // Si c'est == 2, alors PT1 est un produit simple (combinaison PT1 > PT3 > PT2)
                        // On doit donc ajouter les PT2
                        if ($sub_product['productType'] == 2) {
                            // On va ajouter / modifier un productType 2 pour le productType 3

                            // Vérifier si l'option existe déjà
                            $existing_option = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d AND sku = %s", $question_id, $sub_product['sku']), ARRAY_A);

                            if ($existing_option) {
                                // Vérifier si les données ont changé avant de mettre à jour
                                if (
                                    $existing_option['option_name'] !== $sub_product['name'] ||
                                    $existing_option['price'] != $sub_product['price'] ||  // Comparaison non stricte pour les valeurs numériques
                                    $existing_option['description'] !== $sub_product['description'] ||
                                    $existing_option['id_category'] !== $sub_product['idCategory']
                                ) {
                                    // Mettre à jour l'option existante
                                    if (!$this->update_option($existing_option['id'], $sub_product)) {
                                        // Enregistre un message d'erreur si la mise à jour échoue
                                        $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$sub_product['sku']}' n'a pas pu être mise à jour pour la question PT3 avec SKU '{$question['sku']}' pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT2_updated++;
                                    }
                                } else {
                                    // Le PT2 existe dans la BDD, mais pas besoin de le mettre à jour car c'est le même que dans Menlog
                                    $this->products_PT2_skipped++;
                                }
                            } else {
                                // Ajouter une nouvelle option
                                if (!$this->insert_option($question_id, $sub_product)) {
                                    // Enregistre un message d'erreur si l'insertion échoue
                                    $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$sub_product['sku']}' n'a pas pu être ajoutée pour la question PT3 avec SKU '{$question['sku']}' pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                                } else {
                                    $this->products_PT2_created++;
                                }
                            }
                        } 

                        // Sinon, si c'est == 4, alors PT1 est un produit formule (combinaison PT1 > PT3 > PT4 > PT3 > PT2)
                        // On doit donc ajouter PT4, puis les PT3, puis les PT2
                        elseif ($sub_product['productType'] == 4) {
                            // On va ajouter / modifier un PT4 pour la combinaison PT1 > PT3 en cours
                            // Donc on est dans PT1 > PT3 > PT4

                            // Vérifier si le PT4 existe déjà en BDD
                            $existing_pt4 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d AND sku = %s", $question_id, $sub_product['sku']), ARRAY_A);

                            // Si PT4 existe déjà
                            if ($existing_pt4) {
                                // On récupère l'ID du PT4 actuel dans la BDD
                                $formula_product_id = $existing_pt4['id'];

                                // On va chercher le PT4 complet dans Menlog
                                // $all_products contient tous les produits de menlog
                                // sub_product['sku'] contient le SKU du PT4 qu'on regarde
                                $formula_product_menlog = $this->get_product_by_sku($all_products, $sub_product['sku']);

                                // Vérifie si l'une des données importantes du PT4 a changé en comparant les valeurs actuelles et les nouvelles.
                                if (
                                    $existing_pt4['sku'] !== $formula_product_menlog['sku'] ||
                                    $existing_pt4['alpha_code'] !== $formula_product_menlog['alphaCode'] ||
                                    $existing_pt4['price'] != $formula_product_menlog['price'] ||  // Comparaison non stricte pour les valeurs numériques
                                    $existing_pt4['product_name'] !== $formula_product_menlog['name'] ||
                                    $existing_pt4['id_category'] !== $formula_product_menlog['idCategory'] ||
                                    $existing_pt4['description'] !== $formula_product_menlog['description']
                                ) {
                                    // Met à jour le PT4 existant avec les nouvelles données fournies.
                                    if (!$this->update_formula_product($formula_product_id, $formula_product_menlog)) {
                                        // Enregistre un message d'erreur si la mise à jour échoue
                                        $this->message_erreur .= "Erreur : Le PT4 avec SKU '{$formula_product_menlog['sku']}' n'a pas pu être mis à jour pour le produit dont l'ID est '{$formula_product_id}' dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT4_updated++;
                                    }
                                } else {
                                    // Aucune mise à jour nécessaire car les données n'ont pas changé
                                    $this->products_PT4_skipped++; // Assume skipping as PT4 data hasn't changed
                                }
                            } else {
                                // Sinon, PT4 n'existe pas pour la combinaison PT1 > PT3 en cours
                                // On insère le PT4 dans la BDD et on récupère son ID
                                $formula_product_id = $this->insert_formula_product($question_id, $sub_product);
                                if (!$formula_product_id) {
                                    $this->message_erreur .= "Erreur : Le produit de formule PT4 avec SKU '{$sub_product['sku']}' n'a pas pu être ajouté pour le PT3 avec SKU '{$question['sku']}' dans la fonction process_sub_products().\n";
                                } else {
                                    $this->products_PT4_created++;
                                }
                            }

                            // Ajout des PT3 puis des PT2 pour le PT4 qu'on regarde et/ou qu'on a inséré
                            // $sub_product contient tout le PT4 de Menlog
                            // $sub_product['subProducts'] contient tous les SKUs des PT3 (donc de PT1 > PT2 > PT4 > Tous les skus des PT3 associés au PT4)
                            // Pour chaque SKUs PT3 du PT4 qu'on regarde...
                            foreach ($sub_product['subProducts'] as $nestedQuestionSku) {
                                // On récupère le PT3 complet sur menlog de la combinaison PT1 > PT3 > PT4 > PT3
                                $nested_question = $this->get_product_by_sku($all_products, $nestedQuestionSku);

                                // Vérifier si la question existe déjà
                                // Donc PT1 > PT3 > PT4 > PT3 BDD
                                $existing_nested_question = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d AND sku = %s", $formula_product_id, $nested_question['sku']), ARRAY_A);

                                // Si le PT3 pour ce PT4 existe déjà on le met à jour ou on skip
                                // Sinon, on ajoute le PT3 dans la BDD
                                if ($existing_nested_question) {
                                    // Récupération de l'ID du PT3
                                    $nested_question_id = $existing_nested_question['id'];

                                    // Récupération du PT3 correspondant de menlog
                                    // $pt3_menlog contient donc l'ensemble du PT3 actuel sur menlog qui a ce sku
                                    $pt3_menlog = $this->get_product_by_sku($all_products, $existing_nested_question['sku']);

                                    // Vérifie si l'une des données importantes de la question a changé en comparant les valeurs actuelles du PT3 BDD et les nouvelles données du PT3 MEnlog.
                                    if (
                                        $existing_nested_question['question_text'] !== $pt3_menlog['name'] ||
                                        $existing_nested_question['price'] != $pt3_menlog['price'] ||  // Comparaison non stricte pour les valeurs numériques
                                        $existing_nested_question['id_category'] !== $pt3_menlog['idCategory'] ||
                                        $existing_nested_question['description'] !== $pt3_menlog['description'] ||
                                        $existing_nested_question['min'] != $pt3_menlog['min'] ||  // Comparaison non stricte pour les valeurs numériques
                                        $existing_nested_question['max'] != $pt3_menlog['max']  // Comparaison non stricte pour les valeurs numériques
                                    ) {
                                        if (!$this->update_question_for_formula($nested_question_id, $pt3_menlog)) {
                                        // Enregistre un message d'erreur si la mise à jour échoue
                                            $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$pt3_menlog['sku']}' n'a pas pu être mise à jour pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                                        } else {
                                            $this->products_PT3_PT4_updated++;
                                        }
                                    } else {
                                        $this->products_PT3_PT4_skipped++;
                                    }
                                } else {
                                    // Si le PT3 n'existe pas, on doit l'ajouter
                                    // Ajouter une nouvelle question PT3 pour le PT4
                                    $nested_question_id = $this->insert_question_for_formula($formula_product_id, $nested_question);
                                    if (!$nested_question_id) {
                                        $error_message = $wpdb->last_error;
                                        $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$nested_question['sku']}' n'a pas pu être ajoutée (id {$formula_product_id}) pour le PT4 avec SKU '{$sub_product['sku']}' dans la fonction process_sub_products(). Raison : {$error_message}\n";
                                    } else {
                                        $this->products_PT3_PT4_created++;
                                    }
                                }

                                // Ajout des PT2 pour chaque question PT3
                                // Donc le dernier élément de la combinaison PT1 > PT3 > PT4 > PT3 > PT2
                                // $nested_question['subProducts'] contient tous les SKUs PT2 du PT3 qui lui appartient au PT4 qui lui appartient au PT3 qui lui appartient au PT1
                                foreach ($nested_question['subProducts'] as $nestedOptionSku) {
                                    // On récupère le PT2 complet sur Menlog
                                    $nested_option = $this->get_product_by_sku($all_products, $nestedOptionSku);

                                    // Vérifier si l'option PT2 existe déjà
                                    $existing_nested_option = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d AND sku = %s", $nested_question_id, $nested_option['sku']), ARRAY_A);

                                    if ($existing_nested_option) {
                                        // Vérifier si les données ont changé avant de mettre à jour
                                        if (
                                            $existing_nested_option['option_name'] !== $nested_option['name'] ||
                                            $existing_nested_option['price'] != $nested_option['price'] ||  // Comparaison non stricte pour les valeurs numériques
                                            $existing_nested_option['description'] !== $nested_option['description'] ||
                                            $existing_nested_option['id_category'] !== $nested_option['idCategory']
                                        ) {
                                            // Mettre à jour l'option PT2 existante
                                            if (!$this->update_options_for_formulas($existing_nested_option['id'], $nested_option)) {
                                                $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$nested_option['sku']}' n'a pas pu être mise à jour pour la question PT3 avec SKU '{$nested_question['sku']}' pour le PT4 avec SKU '{$sub_product['sku']}' dans la fonction process_sub_products().\n";
                                            } else {
                                                $this->products_PT2_PT3_PT4_updated++;
                                            }
                                        } else {
                                            $this->products_PT2_PT3_PT4_skipped++;
                                        }
                                    } else {
                                        // Ajouter une nouvelle option PT2
                                        if (!$this->insert_options_for_formulas($nested_question_id, $nested_option)) {
                                            $error_message = $wpdb->last_error;
                                            $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$nested_option['sku']}' (Question ID: {$nested_question_id}) n'a pas pu être ajoutée pour la question PT3 avec SKU '{$nested_question['sku']}' pour le PT4 avec SKU '{$sub_product['sku']}' dans la fonction process_sub_products(). Erreur SQL : {$error_message}\n";
                                        } else {
                                            $this->products_PT2_PT3_PT4_created++;
                                        }
                                    }                                
                                }
                            }
                        }
                    }
                } else {
                    // S'il n'y a pas de productType3, normallement process_sub_products n'est pas lancée
                    // Donc si on tombe ici, il y a un problème
                    $this->message_erreur .= "Erreur : Le sous-produit PT3 avec SKU '{$subProductSku}' n'a pas été trouvé pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                }
            }

            // Suppression des PT3 qui ne sont plus dans Menlog (et des PT2 associés aux PT3)
            // Dans ce contexte un PT3 peut être associé à une seul PT1. Par exemple, PT3A est associé à PT1X via un lien. PT3A peut être associé à PT1Y, mais via un autre lien. Ainsi, les PT3 sont différents.
            if ($this->isFormula($product, $all_products)) {
                // Dans le cas où le produit en cours est une formule (PT1)
            
                // Pour chaque question PT3 associée au PT1 actuel...
                foreach ($existing_questions as $existing_question) {
                    // Vérifie si le SKU de la question PT3 actuelle n'est pas présent dans la liste des SKUs des sous-produits de Menlog

                    // La liste $sub_products_sku contient les SKUs des questions PT3 associées au produit principal.
                    // Exemple : $sub_products_sku = ['SKU123', 'SKU456', 'SKU789'];
                    if (!in_array($existing_question['sku'], $sub_products_sku)) {
                        $question_id = $existing_question['id'];
                        $question_sku = $existing_question['sku'];
                
                        // 1. Récupère tous les PT4 associés au PT3 actuel, pour les supprimer
                        $existing_formulas = $wpdb->get_results($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d",
                            $question_id
                        ), ARRAY_A);
                

                        // Pour chaque PT4 du PT3 actuel...
                        foreach ($existing_formulas as $formula) {
                            $formula_id = $formula['id'];
                
                            // 1.a. Récupère tous les PT3 associés au PT4 actuel, pour les supprimer
                            $formula_questions = $wpdb->get_results($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d",
                                $formula_id
                            ), ARRAY_A);
                
                            // Pour chaque PT3 du PT4 actuel
                            foreach ($formula_questions as $formula_question) {
                                $formula_question_id = $formula_question['id'];
                
                                // 1.a.i. Supprimer les PT2 associés au PT3 de formule actuel
                                $formula_options = $wpdb->get_results($wpdb->prepare(
                                    "SELECT id FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d",
                                    $formula_question_id
                                ), ARRAY_A);
                
                                // Pour chaque PT2 du PT3 du PT4 du PT3 du PT1....
                                foreach ($formula_options as $formula_option) {
                                    $formula_option_id = $formula_option['id'];
                
                                    // Suppression du PT2 de formule
                                    if (!$this->delete_options_for_formulas($formula_option_id)) {
                                        $this->message_erreur .= "Erreur : L'option PT2 (formule) avec ID '{$formula_option_id}' n'a pas pu être supprimée pour le PT3 (formule) avec ID '{$formula_question_id}' dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT2_PT3_PT4_deleted++;
                                    }
                                }
                
                                // Suppression du PT3 de formule
                                if (!$this->delete_question_for_formula($formula_question_id)) {
                                    $this->message_erreur .= "Erreur : La question PT3 (formule) avec ID '{$formula_question_id}' n'a pas pu être supprimée pour le PT4 avec ID '{$formula_id}' dans la fonction process_sub_products().\n";
                                } else {
                                    $this->products_PT3_PT4_deleted++;
                                }
                            }
                
                            // Suppression du PT4
                            if (!$this->delete_formula_product($formula_id)) {
                                $this->message_erreur .= "Erreur : Le PT4 avec ID '{$formula_id}' n'a pas pu être supprimé pour la question PT3 avec ID '{$question_id}' dans la fonction process_sub_products().\n";
                            } else {
                                $this->products_PT4_deleted++;
                            }
                        }

                        // Suppression du PT3
                        if (!$this->delete_question($question_id)) {
                            $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$question_sku}' n'a pas pu être supprimée pour le PT1 (formule) avec ID '{$product_id}' dans la fonction process_sub_products().\n";
                        } else {
                            $this->products_PT3_deleted++;
                        }
                    }
                }
            } else {
                // Dans le cas où le produit en cours est un produit simple

                // Pour chaque questions (PT3) de la BDD pour le produit en cours (PT1)
                foreach ($existing_questions as $existing_question) {
                    // Vérifie si le SKU de la question PT3 actuelle n'est pas présent dans la liste des SKUs des sous-produits de Menlog

                    // La liste $sub_products_sku contient les SKUs des questions PT3 associées au produit principal.
                    // Exemple : $sub_products_sku = ['SKU123', 'SKU456', 'SKU789'];
                    if (!in_array($existing_question['sku'], $sub_products_sku)) {

                        // Si le SKU de la question n'est pas dans la liste, elle doit être supprimée, car elle n'est plus associée au produit principal PT1
                        // Pour cela, on commence par récupérer toutes les options PT2 de le BDD associées à cette question PT3 puisque un PT2 est relié au PT3
                        $existing_options = $wpdb->get_results($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}custom_options WHERE question_id = %d", 
                            $existing_question['id']
                        ), ARRAY_A);

                        foreach ($existing_options as $option) {
                            // Supprime l'option PT2 de la base de données en utilisant son ID
                            // Exemple : Suppression de l'option avec ID 101 si elle est associée à la question actuelle
                            if (!$this->delete_option($option['id'])) {
                                // Enregistre un message d'erreur si la suppression échoue
                                $this->message_erreur .= "Erreur : L'option PT2 associée à la question PT3 avec SKU '{$existing_question['sku']}' n'a pas pu être supprimée pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                            }
                        }

                        // Supprime la question PT3 de la base de données, car elle n'est plus valide pour le produit principal PT1
                        // Exemple : Suppression de la question avec ID 200 qui a le SKU 'SKU101'
                        if (!$this->delete_question($existing_question['id'])) {
                            // Enregistre un message d'erreur si la suppression échoue
                            $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$existing_question['SKU']}' n'a pas pu être supprimée pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
                        } else {
                            $this->products_PT3_deleted++;
                        }
                    }
                }
            }
        }

        /**
         * Insère un productType 3 dans la BDD, dans la table prefix_custom_questions
         * 
         * @param int $product_id L'ID du produit WooCommerce.
         * @param array $question Tout le contenu de la question Menlog (productType 3).
         */
        private function insert_question($product_id, $question) {
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}custom_questions", [
                'product_id' => $product_id,
                'sku' => $question['sku'],
                'question_text' => $question['name'],
                'price' => $question['price'],
                'id_category' => $question['idCategory'],
                'description' => $question['description'],
                'min' => $question['min'],
                'max' => $question['max']
            ]);
            return $wpdb->insert_id;
        }

        /**
         * Met à jour un productType 3 dans la BDD.
         * 
         * @param int $question_id L'ID de la question dans la BDD.
         * @param array $question Tout le contenu de la question Menlog (productType 3).
         */
        private function update_question($question_id, $question) {
            global $wpdb;
            // Effectue la mise à jour et retourne le nombre de lignes affectées
            $updated = $wpdb->update("{$wpdb->prefix}custom_questions", [
                'question_text' => $question['name'],
                'price' => $question['price'],
                'id_category' => $question['idCategory'],
                'description' => $question['description'],
                'min' => $question['min'],
                'max' => $question['max']
            ], ['id' => $question_id]);
        
            // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
            return $updated !== false && $updated > 0;
        }
    
        /**
         * Supprime un productType 3 de la BDD.
         * 
         * @param int $question_id L'ID de la question dans la BDD.
         */
        private function delete_question($question_id) {
            global $wpdb;
            // Effectue la suppression et retourne le nombre de lignes affectées
            $deleted = $wpdb->delete("{$wpdb->prefix}custom_questions", ['id' => $question_id]);
            // Si $deleted est égal à false ou 0, cela signifie que la suppression a échoué
            return $deleted !== false && $deleted > 0;
        }
    

        /**
         * Insère une question PT3 dans la BDD pour un PT4
         * @param mixed $formula_product_id
         * @param mixed $question
         * @return mixed
         */
        private function insert_question_for_formula($formula_product_id, $question) {
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}custom_questions_for_formulas", [
                'formula_product_id' => $formula_product_id,
                'sku' => $question['sku'],
                'question_text' => $question['name'],
                'price' => $question['price'],
                'id_category' => $question['idCategory'],
                'description' => $question['description'],
                'min' => $question['min'],
                'max' => $question['max']
            ]);
            return $wpdb->insert_id;
        }

        /**
         * Met à jour un productType 3 dans la BDD associé à un PT4
         * 
         * @param int $question_id L'ID de la question dans la BDD.
         * @param array $question Tout le contenu de la question Menlog (productType 3).
         */
        private function update_question_for_formula($question_id, $question) {
            global $wpdb;
            // Effectue la mise à jour et retourne le nombre de lignes affectées
            $updated = $wpdb->update("{$wpdb->prefix}custom_questions_for_formulas", [
                'question_text' => $question['name'],
                'price' => $question['price'],
                'id_category' => $question['idCategory'],
                'description' => $question['description'],
                'min' => $question['min'],
                'max' => $question['max']
            ], ['id' => $question_id]);
        
            // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
            return $updated !== false && $updated > 0;
        }
    
        /**
         * Supprime un productType 3 de la BDD associé à un PT4
         * 
         * @param int $question_id L'ID de la question dans la BDD.
         */
        private function delete_question_for_formula($question_id) {
            global $wpdb;
            // Effectue la suppression et retourne le nombre de lignes affectées
            $deleted = $wpdb->delete("{$wpdb->prefix}custom_questions_for_formulas", ['id' => $question_id]);
            // Si $deleted est égal à false ou 0, cela signifie que la suppression a échoué
            return $deleted !== false && $deleted > 0;
        }

        /**
         * Insère une option PT2 dans la BDD pour un PT3 d'une formule
         * @param mixed $formula_question_id
         * @param mixed $option
         * @return bool
         */
        private function insert_options_for_formulas($formula_question_id, $option) {
            global $wpdb;
            $inserted = $wpdb->insert("{$wpdb->prefix}custom_options_for_formulas", [
                'formula_question_id' => $formula_question_id,
                'sku' => $option['sku'],
                'option_name' => $option['name'],
                'price' => $option['price'],
                'id_category' => $option['idCategory'],
                'description' => $option['description'],
            ]);

            // Retourne true si l'insertion a affecté au moins une ligne, false sinon
            return $inserted !== false && $wpdb->insert_id > 0;
        }

        /**
         * Met à jour un PT2 dans la BDD associé à un PT3 d'une formule
         * 
         * @param int $existing_nested_option_id 
         * @param array $option_menlog 
         */
        private function update_options_for_formulas($existing_nested_option_id, $option_menlog) {
            global $wpdb;
            // Effectue la mise à jour et retourne le nombre de lignes affectées
            $updated = $wpdb->update("{$wpdb->prefix}custom_options_for_formulas", [
                'option_name' => $option_menlog['name'],
                'sku' => $option_menlog['sku'],
                'price' => $option_menlog['price'],
                'id_category' => $option_menlog['idCategory'],
                'description' => $option_menlog['description'],
            ], ['id' => $existing_nested_option_id]);
        
            // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
            return $updated !== false && $updated > 0;
        }
    
        /**
         * Supprime un PT2 de la BDD associé à un PT3 d'une formule
         * 
         * @param int $option_id l'ID de l'option à supprimer
         */
        private function delete_options_for_formulas($option_id) {
            global $wpdb;
            // Effectue la suppression et retourne le nombre de lignes affectées
            $deleted = $wpdb->delete("{$wpdb->prefix}custom_options_for_formulas", ['id' => $option_id]);
            // Si $deleted est égal à false ou 0, cela signifie que la suppression a échoué
            return $deleted !== false && $deleted > 0;
        }

        /**
         * Insère un ProductType 2 dans la BDD associé à un ProductType 3 pour un produit simple
         * @param mixed $question_id l'id du productType 3
         * @param mixed $option le contenu de productType 2
         * @return void
         */
        private function insert_option($question_id, $option) {
            global $wpdb;
            // Effectue l'insertion de l'option dans la base de données
            $inserted = $wpdb->insert("{$wpdb->prefix}custom_options", [
                'question_id' => $question_id,
                'sku' => $option['sku'],
                'option_name' => $option['name'],
                'price' => $option['price'],
                'id_category' => $option['idCategory'],
                'description' => $option['description']
            ]);
        
            // Retourne true si l'insertion a affecté au moins une ligne, false sinon
            return $inserted !== false && $wpdb->insert_id > 0;
        }
    
        /**
         * Met à jour un productType 2 dans la BDD pour un produit simple
         * @param mixed $option_id
         * @param mixed $option
         * @return void
         */
        private function update_option($option_id, $option) {
            global $wpdb;
            // Effectue la mise à jour de l'option dans la base de données
            $updated = $wpdb->update("{$wpdb->prefix}custom_options", [
                'sku' => $option['sku'],
                'option_name' => $option['name'],
                'price' => $option['price'],
                'id_category' => $option['idCategory'],
                'description' => $option['description']
            ], ['id' => $option_id]);
        
            // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
            return $updated !== false && $updated > 0;
        }
    

        /**
         * Supprime un productType 2 de la BDD pour un produit simple
         * @param int $option_id l'ID de l'option à supprimer
         * @return void
         */
        private function delete_option($option_id) {
            global $wpdb;
            // Supprimer l'option de la table custom_options en utilisant l'ID
            $deleted = $wpdb->delete("{$wpdb->prefix}custom_options", ['id' => $option_id]);
        
            // Retourne true si la suppression a affecté au moins une ligne, false sinon
            return $deleted !== false && $deleted > 0;
        }  
    
        /**
         * Surpprime un PT4 de la BDD
         * @param mixed $formula_id l'ID du PT4 à supprimer
         * @return bool
         */
        private function delete_formula_product($formula_product_id) {
            global $wpdb;

            // Supprimer le PT4 de la table custom_formula_products en utilisant l'ID
            $deleted = $wpdb->delete("{$wpdb->prefix}custom_formula_products", ['id' => $formula_product_id]);

            // Retourne true si la suppression a affecté au moins une ligne, false sinon
            return $deleted !== false && $deleted > 0;
        }

        /**
         * Insert un PT4 dans la BDD
         * @param mixed $question_id
         * @param mixed $formula_product
         * @return mixed
         */
        private function insert_formula_product($question_id, $formula_product) {
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}custom_formula_products", [
                'question_id' => $question_id,
                'sku' => $formula_product['sku'],
                'alpha_code' => $formula_product['alphaCode'],
                'price' => $formula_product['price'],
                'product_name' => $formula_product['name'],
                'id_category' => $formula_product['idCategory'],
                'description' => $formula_product['description']
            ]);
            return $wpdb->insert_id;
        }

        /**
         * Met à jour un PT4 dans la BDD
         * @param mixed $formula_product_id
         * @param mixed $formula_product
         * @return bool
         */
        private function update_formula_product($formula_product_id, $formula_product) {
            global $wpdb;
            // Effectue la mise à jour de l'option dans la base de données
            $updated = $wpdb->update("{$wpdb->prefix}custom_formula_products", [
                'sku' => $formula_product['sku'],
                'alpha_code' => $formula_product['alphaCode'],
                'price' => $formula_product['price'],
                'product_name' => $formula_product['name'],
                'id_category' => $formula_product['idCategory'],
                'description' => $formula_product['description']
            ], ['id' => $formula_product_id]);
        
            // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
            return $updated !== false && $updated > 0;
        }


    
    // -----------------------------
    // Section Utilitaires
    // -----------------------------

        /**
         * Recherche un produit par son SKU parmi une liste de produits.
         *
         * @param array $products Liste de produits, chaque produit étant un tableau associatif contenant les détails du produit.
         * @param string $sku Le SKU du produit à rechercher.
         * @return array|null Le produit correspondant sous forme de tableau associatif s'il est trouvé, sinon null.
         */
        private function get_product_by_sku($products, $sku) {
            foreach ($products as $product) {
                if ($product['sku'] == $sku) {
                    return $product;
                }
            }
            return null;
        }


        /**
         * Écrit les résultats de l'importation dans un fichier texte.
         */
        private function write_import_results() {
            $file_path = plugin_dir_path(__FILE__) . 'import_results.txt';
            $content = "Catégories : {$this->categories_created} créées, {$this->categories_updated} mises à jour, {$this->categories_skipped} skipped, {$this->categories_deleted} deleted.\n";
            if (!empty($this->category_updates)) {
                $content .= "Détails des mises à jour des catégories:\n" . implode("\n", $this->category_updates) . "\n\n";
            }
            $content .= "Produits type 1 : 
            {$this->products_created} créés,
            {$this->products_updated} mis à jour, 
            {$this->products_skipped} skipped, 
            {$this->products_deleted} supprimés,
            {$this->products_with_PT3} produits type 1 on un ou plusieurs PT3\n\n";
            if (!empty($this->product_updates)) {
                $content .= "Détails des mises à jour des produits PT1:\n" . implode("\n", $this->product_updates) . "\n\n";
            }

            $content .= "Produits type 3 : 
            {$this->products_PT3_created} créés,
            {$this->products_PT3_updated} mis à jour, 
            {$this->products_PT3_skipped} skipped, 
            {$this->products_PT3_deleted} supprimés\n\n";

            $content .= "Produits type 2 : 
            {$this->products_PT2_created} créés,
            {$this->products_PT2_updated} mis à jour, 
            {$this->products_PT2_skipped} skipped, 
            {$this->products_PT2_deleted} supprimés\n\n";

            $content .= "Produits type 4 : 
            {$this->products_PT4_created} créés,
            {$this->products_PT4_updated} mis à jour, 
            {$this->products_PT4_skipped} skipped, 
            {$this->products_PT4_deleted} supprimés\n\n";

            $content .= "Produits type 3 du 4 : 
            {$this->products_PT3_PT4_created} créés,
            {$this->products_PT3_PT4_updated} mis à jour, 
            {$this->products_PT3_PT4_skipped} skipped, 
            {$this->products_PT3_PT4_deleted} supprimés\n\n";

            $content .= "Produits type 2 du 3 du 4 : 
            {$this->products_PT2_PT3_PT4_created} créés,
            {$this->products_PT2_PT3_PT4_updated} mis à jour, 
            {$this->products_PT2_PT3_PT4_skipped} skipped, 
            {$this->products_PT2_PT3_PT4_deleted} supprimés\n\n";


            $content .= "Détails des messages :\n";
            $content .= $this->message_erreur;
        
            file_put_contents($file_path, $content);
        }

        /**
         * Vérifie si le produit Menlog est un produit simple ou une formule
         * @param mixed $product Contient le PT1 Menlog en cours de traitement
         * @return bool
         */
        public function isFormula($product, $all_products) {
            // Vérifie si le produit a des sous-produits (PT3)
            // Si le PT1 de Menlog à des sous-produits PT3, on les regarde.
            if (!empty($product['subProducts'])) {
                // Il y a des sous-produits (PT3)
                // Pour chaque PT3, on le récupère sur Menlog
                foreach ($product['subProducts'] as $menlog_PT3) {
                    // On récupère le PT3 complet sur Menlog
                    $menlogè_PT3_suite = $this->get_product_by_sku($all_products, $menlog_PT3);

                    // On parcours chaque sous-produit du PT3, à la recherche d'un PT4 si c'est une formule
                    if (!empty($menlogè_PT3_suite['subProducts'])) {
                        foreach ($menlogè_PT3_suite['subProducts'] as $isFormula_subProduct) {
                            // On récupère le sous-produit du PT3 du PT1 qu'on regarde
                            $PT2_or_PT4 = $this->get_product_by_sku($all_products, $isFormula_subProduct);
                            if ($PT2_or_PT4['productType'] == 4) {
                                // On a un PT4, donc c'est une formule
                                return true;
                            } else {
                                return false;
                            }
                        }
                    }
                }
            }
            // Sinon, c'est un produit simple
            return false;
        }

        /**
         * Permet de tenter d'ajouter un client dans Menlog
         * @param mixed $customer
         * @return array
         */
        public function add_client($customer) {
            $token = $this->get_token();
        
            // Vérifier si le client est connecté
            if (is_user_logged_in()) {
                $uidclient = substr($customer->get_id(), 0, 22);
                $username = substr($customer->get_username(), 0, 100);
                $first_name = substr($customer->get_first_name(), 0, 20);
                $last_name = substr($customer->get_last_name(), 0, 29);
                $email = substr($customer->get_email(), 0, 100);
            } else {
                // Gérer les clients non connectés en utilisant les données du formulaire de paiement
                $order = WC()->checkout->get_checkout_fields();
                
                $email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
                if (!empty($email)) {
                    $guest_id = substr(md5($email), 0, 22);
                }
                $uidclient = $guest_id;
        
                $username = $uidclient; // Vous pouvez personnaliser cela selon vos besoins
                $first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';
                $last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';
            }
        
            // Récupérer les autres informations de facturation
            $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';
            $billing_address_1 = isset($_POST['billing_address_1']) ? sanitize_text_field($_POST['billing_address_1']) : '';
            $billing_address_2 = isset($_POST['billing_address_2']) ? sanitize_text_field($_POST['billing_address_2']) : '';
            $billing_postcode = isset($_POST['billing_postcode']) ? sanitize_text_field($_POST['billing_postcode']) : '';
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';
            $billing_country = isset($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : '';
        
            // Préparer les données du client
            $client_data = array(
                "refmenlog" => "",
                "uidclient" => $uidclient,
                "username" => $username,
                "prenom" => $first_name,
                "nom" => $last_name,
                "email" => $email,
                "tel" => substr($billing_phone, 0, 20),
                "mob" => "",
                "addr1" => substr($billing_address_1, 0, 100),
                "addr2" => substr($billing_address_2, 0, 100),
                "codepostal" => substr($billing_postcode, 0, 10),
                "ville" => substr($billing_city, 0, 100),
                "pays" => substr($billing_country, 0, 50),
                "dateanniv" => "",  // Si la date d'anniversaire est disponible, mettez-la ici au format 'DD.MM.YYYY'
                "typeimport" => 1
            );
        
            // Convertir les données en JSON
            $json_client_data = json_encode($client_data);
            if ($json_client_data === false) {
                $log_message = 'Erreur de formatage JSON: ' . json_last_error_msg();
                error_log($log_message);
                $this->envoyer_email_debug('Erreur de formatage JSON lors de l\'ajout d\'un client', $log_message);
                return array('error' => true, 'message' => 'Erreur de formatage JSON. Données non valides.');
            }
        
            $url = 'https://' . $this->server . '/' . $this->rlog . '/' . $this->uuidclient . '/' . $this->uuidmagasin . '/add_client?token=' . $token;
        
            // Log pour déboguer
            error_log("URL de la requête: " . $url);
            error_log("Données envoyées: " . json_encode($client_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
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
                CURLOPT_POSTFIELDS => $json_client_data,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
            ));
        
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
        
            curl_close($curl);
        
            // Log pour déboguer la réponse
            if ($response === false) {
                $log_message = "Erreur cURL: " . $curl_error;
                error_log($log_message);
                $this->envoyer_email_debug('Erreur cURL lors de l\'ajout d\'un client', $log_message);
                return array('error' => true, 'message' => 'Erreur de communication avec le serveur: ' . $curl_error);
            }
        
            error_log("Réponse de l'API: " . $response);
            error_log("Code HTTP de la réponse: " . $http_code);
        
            // Analyser la réponse JSON
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $log_message = 'Erreur de décodage JSON: ' . json_last_error_msg() . ". Réponse brute: " . $response;
                error_log($log_message);
                $this->envoyer_email_debug('Erreur de décodage JSON lors de l\'ajout d\'un client', $log_message);
                return array('error' => true, 'message' => 'Erreur: Réponse inattendue du serveur. Body: ' . $response);
            }
        
            // Gestion des erreurs spécifiques en fonction du code HTTP et du contenu de la réponse
            if ($http_code == 200) {
                // Le client passe en 200, tout va bien
                if (isset($data['error']) && $data['error'] == 0 && isset($data['status']) && $data['status'] == 'SUCCESS') {
                    return array(
                        'error' => false,
                        'message' => 'Client ajouté avec succès',
                        'uidclient' => $uidclient,
                        'username' => $username,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => substr($billing_phone, 0, 20),
                        'billing_address_1' => substr($billing_address_1, 0, 100),
                        'billing_address_2' => substr($billing_address_2, 0, 100),
                        'billing_postcode' => substr($billing_postcode, 0, 10),
                        'billing_city' => substr($billing_city, 0, 100),
                        'billing_country' => substr($billing_country, 0, 50),
                        'debug_info' => array( // Optionnel, pour le débogage
                            'http_code' => $http_code,
                            'response' => $response,
                        ),
                    );
                } 
                
                // Le client passe en code 200, mais est refusé par Menlog
                elseif (isset($data['error']) && $data['error'] == 1) {
                    // Dans le cas de l'erreur "Invalid body"
                    if (strpos($data['status'], 'Invalid body') !== false) {
                        $log_message = 'Erreur: Contenu du body invalide. ' . $data['status'];
                        error_log($log_message);
                        $this->envoyer_email_debug('Erreur de contenu du body lors de l\'ajout d\'un client', $log_message);
                        return array(
                            'error' => true,
                            'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
                        );
                    } 
                    
                    // Dans le cas de l'erreur "FAILED - GDSError"
                    elseif (strpos($data['status'], 'FAILED - GDSError') !== false) {
                        $log_message = 'Erreur: Données non conformes. ' . $data['status'];
                        error_log($log_message);
                        $this->envoyer_email_debug('Erreur de données non conformes lors de l\'ajout d\'un client', $log_message);
                        return array(
                            'error' => true,
                            'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
                        );
                    }
                }
            } 
            
            // Le client ne passe pas en code 400
            elseif ($http_code == 400) {
                // Le JSON envoyé à Menlog est mal formaté (manquement d'une virgule, accolade, etc)
                if (isset($data['message']) && strpos($data['message'], 'Invalid JSON') !== false) {
                    $log_message = 'Erreur: JSON invalide. ' . $data['message'];
                    error_log($log_message);
                    $this->envoyer_email_debug('Erreur JSON invalide lors de l\'ajout d\'un client', $log_message);
                    return array(
                        'error' => true,
                        'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
                    );
                }
            } 
            
            // Le client ne passe pas en code 401
            // Mise en place d'une récursion pour corriger le token et essayer une fois de plus
            elseif ($http_code == 401) {
                static $retry_count = 0;
                $max_retries = 1; // Limiter à une tentative de régénération du token

                $log_message = 'Erreur d\'authentification: ' . $data['message'];
                error_log($log_message);
                $this->envoyer_email_debug('Erreur d\'authentification lors de l\'ajout d\'un client', $log_message);

                // Nouvelle tentative
                if ($retry_count < $max_retries) {
                    $retry_count++;
                    // Tenter de régénérer un nouveau token
                    $new_token = $this->get_token();
                    if ($new_token) {
                        $this->token = $new_token; // Mise à jour du token
                        // Nouvelle tentative (récursion)
                        return $this->add_client($customer);
                    }
                }


                // Si l'erreur persiste après la tentative de correction
                return array(
                    'error' => true,
                    'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
                );

            } 
            
            // Le client ne passe pas en code 500
            elseif ($http_code == 500) {
                if (isset($data['message']) && strpos($data['message'], 'TIMEOUT') !== false) {
                    $log_message = 'Erreur de serveur: TIMEOUT. ' . $data['message'];
                    error_log($log_message);
                    $this->envoyer_email_debug('Erreur TIMEOUT lors de l\'ajout d\'un client', $log_message);

                    // TODO : Comprendre ce qu'il faut faire ici
                    // Actuellement, le client ne passe pas donc la commande n'est pas validée
                    return array(
                        'error' => true,
                        'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
                    );
                }
            }
        
            // Autres codes de retour non gérés
            // Dans ce cas, on ne peut pas apporter de modification. 
            // Le client doit recommencer
            $log_message = 'Erreur inattendue lors de l\'ajout du client. Code HTTP: ' . $http_code . '. Réponse: ' . json_encode($data);
            error_log($log_message);
            $this->envoyer_email_debug('Erreur inattendue lors de l\'ajout d\'un client', $log_message);
            return array(
                'error' => true,
                'message' => 'Une erreur s\'est produite lors de la validation de votre commande. Nous vous invitons à vérifier que toutes vos informations sont correctement saisies. Le paiement n\'a pas été effectué. Si le problème persiste, essayez de vider votre panier et de recommencer la commande. Merci de votre compréhension.'
            );
        }
        
        // Fonction pour envoyer un email de débogage
        private function envoyer_email_debug($sujet, $message) {
            $to = 'bauduffegabriel@gmail.com';
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($to, $sujet, $message, $headers);
        }        
        
        
                
        
}

// Instanciation de la classe
new WooCommerce_API_Integration();


// Fonction d'activation du plugin
function activate_woocommerce_api_integration() {
    $plugin = new WooCommerce_API_Integration();
    $plugin->create_custom_tables();
}
register_activation_hook(__FILE__, 'activate_woocommerce_api_integration');


// Ajuster le prix du produit et ajouter les options comme méta-données au panier
add_filter('woocommerce_add_cart_item_data', 'add_custom_options_to_cart', 10, 3);
function add_custom_options_to_cart($cart_item_data, $product_id, $variation_id) {
    global $wpdb;
    $product = wc_get_product($product_id);
    $is_formula = strpos($product->get_name(), 'Formule') !== false;

    if (isset($_POST['custom_total_price'])) {
        $cart_item_data['custom_price'] = (float) sanitize_text_field($_POST['custom_total_price']);
    }

    if ($is_formula) {
        $cart_item_data['is_formula'] = true;
        $cart_item_data['formula_options'] = [];

        // Récupération des questions pour les formules
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
            $product_id
        ), ARRAY_A);

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $selected_formula_product_id = isset($_POST["formula_option_{$question_id}"]) ? $_POST["formula_option_{$question_id}"] : null;

            if ($selected_formula_product_id) {
                // Récupération du produit formule sélectionné
                $formula_product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE id = %d",
                    $selected_formula_product_id
                ), ARRAY_A);

                $cart_item_data['formula_options'][$question_id] = [
                    'question' => $question['question_text'],
                    'product' => $formula_product['product_name'],
                    'sku' => $formula_product['sku'],
                    'price' => $formula_product['price'],
                    'id_category' => $formula_product['id_category'],
                    'description' => $formula_product['description'],
                    'suboptions' => []
                ];

                // Récupération des sous-questions pour le produit formule sélectionné
                $sub_questions = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d",
                    $selected_formula_product_id
                ), ARRAY_A);

                foreach ($sub_questions as $sub_question) {
                    $sub_question_id = $sub_question['id'];
                    if (isset($_POST["option_{$sub_question_id}"])) {
                        foreach ($_POST["option_{$sub_question_id}"] as $option_sku => $option_name) {
                            $option = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d AND sku = %s",
                                $sub_question_id, $option_sku
                            ), ARRAY_A);

                            $cart_item_data['formula_options'][$question_id]['suboptions'][] = [
                                'question' => $sub_question['question_text'],
                                'option' => $option_name,
                                'sku' => $option_sku,
                                'price' => $option['price'],
                                'idCategory' => $option['id_category'],
                                'description' => $option['description'],
                                'productType' => 4, // Sous-item de formule
                            ];
                        }
                    }
                }
            }
        }
    }  else {
        // Gestion des options pour les produits non-formule
        $cart_item_data['custom_options'] = [];
        $cart_item_data['has_anniversary_plaque'] = false; // Initialisation par défaut

        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
            $product_id
        ), ARRAY_A);

        foreach ($questions as $question) {
            $question_id = $question['id'];
            if (isset($_POST["option_{$question_id}"])) {
                foreach ($_POST["option_{$question_id}"] as $option_sku => $option_name) {
                    $option = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d AND sku = %s",
                        $question_id, $option_sku
                    ), ARRAY_A);

                    // Vérifier si l'option contient "plaquette anniversaire"
                    if (preg_match('/plaque anniversaire/i', $option_name)) {
                        $cart_item_data['has_anniversary_plaque'] = true;
                        // Debugging to verify when this condition is met
                        error_log("Plaquette anniversaire détectée: " . $option_name);
                    }

                    $cart_item_data['custom_options'][] = [
                        'question' => $question['question_text'],
                        'option' => $option_name,
                        'sku' => $option_sku,
                        'price' => $option['price'],
                        'idCategory' => $option['id_category'],
                        'description' => $option['description'],
                        'productType' => 2, // Sous-item de produit simple
                    ];
                }
            }
        }
    }

    return $cart_item_data;
}



// Afficher les options sélectionnées dans le panier
add_filter('woocommerce_get_item_data', 'display_custom_options_in_cart', 10, 2);
function display_custom_options_in_cart($item_data, $cart_item) {
    if (isset($cart_item['is_formula']) && $cart_item['is_formula']) {
        foreach ($cart_item['formula_options'] as $question_id => $formula_option) {
            $item_value = $formula_option['product'];
            
            if (!empty($formula_option['suboptions'])) {
                $suboption_values = [];
                foreach ($formula_option['suboptions'] as $suboption) {
                    $suboption_values[] = $suboption['option'];
                }
                // Convertir les sous-options en une chaîne de texte
                $item_value .= ' (' . implode(', ', $suboption_values) . ')';
            }
    
            $item_data[] = [
                'key'   => $formula_option['question'],
                'value' => $item_value
            ];
        }
    } elseif (isset($cart_item['custom_options'])) {
        // Convertir les options en chaîne de texte si elles sont dans un tableau
        $option_values = [];
        foreach ($cart_item['custom_options'] as $custom_option) {
            if (is_array($custom_option)) {
                $option_values[] = $custom_option['option'];
            } else {
                $option_values[] = $custom_option;
            }
        }
        // Ajouter les options seulement si le tableau $option_values n'est pas vide
        if (!empty($option_values)) {
            $item_data[] = [
                'key'   => 'Options',
                'value' => implode(', ', $option_values)
            ];
        }
    }
    return $item_data;
}


// Modifier le prix affiché dans le panier
add_action('woocommerce_before_calculate_totals', 'update_cart_price');
function update_cart_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Mettre à jour le prix de chaque article dans le panier
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['custom_price'])) {
            // Mettre à jour le prix de l'article
            $cart_item['data']->set_price($cart_item['custom_price']);
        }
    }
}

// Ajouter les champs de texte pour chaque plaquette anniversaire sur la page de paiement
add_action('woocommerce_after_order_notes', 'add_anniversary_plaque_fields');
function add_anniversary_plaque_fields($checkout) {
    $count = 0;

    // Titre général pour inviter l'utilisateur à saisir le texte
    echo "<h4>Saisissez le texte à insérer sur les plaques anniversaire :</h4>";

    foreach (WC()->cart->get_cart() as $cart_item) {
        // Récupérer le nom du produit
        $product_name = $cart_item['data']->get_name();

        if (isset($cart_item['has_anniversary_plaque']) && $cart_item['has_anniversary_plaque']) {
            // Afficher le nom du produit
            echo "<strong>" . esc_html($product_name) . "</strong>";

            // Pour chaque quantité de ce produit, ajouter un champ
            for ($i = 1; $i <= $cart_item['quantity']; $i++) {
                $count++;
                woocommerce_form_field("anniversary_plaque_note_{$count}", array(
                    'type'        => 'text',
                    'class'       => array('form-row-wide'),
                    'label'       => __("Message pour la plaque anniversaire #{$i}"),
                    'placeholder' => __("Entrez le message pour la plaque anniversaire #{$i}"),
                    'required'    => true,  // Champ obligatoire
                ), $checkout->get_value("anniversary_plaque_note_{$count}"));
            }

            echo "<br>"; // Ajouter un espace entre les produits
        } 
    }
}



// Valider que les champs de texte pour les plaques anniversaire sont remplis
add_action('woocommerce_checkout_process', 'validate_anniversary_plaque_fields');
function validate_anniversary_plaque_fields() {
    $count = 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        // Récupérer le nom du produit
        $product_name = $cart_item['data']->get_name();

        if (isset($cart_item['has_anniversary_plaque']) && $cart_item['has_anniversary_plaque']) {
            // Pour chaque quantité de ce produit, valider le champ
            for ($i = 1; $i <= $cart_item['quantity']; $i++) {
                $count++;
                if (empty($_POST["anniversary_plaque_note_{$count}"])) {
                    wc_add_notice(__("Veuillez fournir un message pour la plaque anniversaire #{$i} du produit: {$product_name}."), 'error');
                }
            }
        }
    }
}




// Sauvegarder les valeurs des champs de texte
add_action('woocommerce_checkout_update_order_meta', 'save_anniversary_plaque_fields');
function save_anniversary_plaque_fields($order_id) {
    $count = 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        // Récupérer le nom du produit
        $product_name = $cart_item['data']->get_name();

        if (isset($cart_item['has_anniversary_plaque']) && $cart_item['has_anniversary_plaque']) {
            // Pour chaque quantité de ce produit, sauvegarder le champ
            for ($i = 1; $i <= $cart_item['quantity']; $i++) {
                $count++;
                if (!empty($_POST["anniversary_plaque_note_{$count}"])) {
                    update_post_meta($order_id, "anniversary_plaque_note_{$count}", sanitize_text_field($_POST["anniversary_plaque_note_{$count}"]));
                    update_post_meta($order_id, "anniversary_plaque_product_{$count}", $product_name);
                }
            }
        }
    }
}


?>