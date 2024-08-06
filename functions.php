<?php
/*
Plugin Name: WooCommerce API Integration
Description: Plugin pour intégrer les produits d'une API externe dans WooCommerce.
Version: 1.0
Author: Votre Nom
*/

/**
 * TODO : Produits TYPE 2
 * - Ajout
 * - Modification 
 * - Suppression 
 * - Ne rien faire
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WooCommerce_API_Integration {
    private $token;
    private $server = 'k8s.zybbo.com';
    private $delivery = 'delivery-staging';
    private $uuidclient = 'U9mDG4tia5lfatO*wd5lA!';
    private $uuidmagasin = 'MfSBUAdui5Vg!tO*wd5lA!';
    private $username = 'yanna_dev';
    private $password = 'A33_DKq(QFJ1r*9;)';

    // Initialisation des compteurs pour le suivi
    private $categories_created = 0;
    private $categories_updated = 0;
    private $categories_skipped = 0;

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

    // Tableaux pour stocker les détails des mises à jour
    private $category_updates = [];
    private $product_updates = [];

    // Stocker les messages d'erreurs
    private $message_erreur = '';

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'create_custom_tables'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'import_menlog_from_api'));
    }

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
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Exécuter les requêtes SQL séparément et capturer les résultats
        $result_questions = dbDelta($sql_questions);
        $result_options = dbDelta($sql_options);
        $result_formula_products = dbDelta($sql_formula_products);
    
        // Vérifier si les tables existent
        $tables_exist = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}custom_questions'", ARRAY_N);
    
        // Message de débogage
        $debug_message = 'Les tables personnalisées ont été créées ou mises à jour.<br>';
        $debug_message .= '<pre>' . print_r($result_questions, true) . '</pre>';
        $debug_message .= '<pre>' . print_r($result_options, true) . '</pre>';
        $debug_message .= '<pre>' . print_r($result_formula_products, true) . '</pre>';
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

    private function get_token() {
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





    
    

    // Permet de lancer le processus d'import suite au clic sur le bouton
    // Récupère tous les produits de menlog
    // Lance le processus des catégories
    // Lance le processus des produits (import_products)
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
    

    /**
     * Importe les catégories ou mets à jour les catégories existantes si nécessaire.
     *
     * @param array $categories Un tableau de catégories Menlog, chaque catégorie étant un tableau associatif contenant :
     *   - string 'name' : Le nom de la catégorie.
     *   - string 'idCategory' : Le slug de la catégorie.
     */
    private function import_categories($categories) {
        foreach ($categories as $category) {
            $existing_category = get_term_by('slug', $category['idCategory'], 'product_cat');
            
            if ($existing_category) {
                // Correction de l'espace blanc potentiellement problématique
                $new_category_name = trim($category['name']);
                if ($existing_category->name !== $new_category_name) {
                    wp_update_term($existing_category->term_id, 'product_cat', array('name' => $new_category_name));
                    $this->categories_updated++;
                    $this->category_updates[] = "Category '{$existing_category->name}' updated to '{$new_category_name}'";
                } else {
                    $this->categories_skipped++;
                }
            } else {
                wp_insert_term($new_category_name, 'product_cat', array('slug' => $category['idCategory']));
                $this->categories_created++;
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

        $wc_product = new WC_Product_Simple();
        $wc_product->set_name($product['name']);
        $wc_product->set_sku($product['sku']);
        $wc_product->set_regular_price($product['price']);
        $wc_product->set_description($product['description']);
        $wc_product->set_category_ids(array($category_id));
        $wc_product->save();

        $this->products_created++;
        
        return $wc_product->get_id();
    }

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
                 * Suppression des PT2 > PT3 > PT4 si c'est un produit Formule
                 * Suppression des PT2 si c'est un produit Simple
                 */
                if ($this->isFormula($product)) {
                    // Dans le cas où le PT1 est une formule
                    // On est déjà dans la config PT1 > PT3

                    // TODO : Actuellement le code supprimer un PT4 uniquement s'il n'est plus dans Menlog. Mais que faire si c'est un ou plusieurs PT2 d'un PT3 du PT4 qui n'existe plus alors que le PT4 lui existe encore. Que faire si c'est un ou plusieurs PT3 du PT4 qui n'existe plus alors que le PT4 lui existe encore ? Actuellement, on supprime le PT4 et (PT3 > PT2) uniquement si le PT4 n'existe plus
                
                    // Récupérer tous les produits de la formule existants dans la BDD
                    $existing_formula_products = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d", $question_id), ARRAY_A);
                    $existing_formula_skus = array_column($existing_formula_products, 'sku');
                
                    // Collecter les SKUs des produits de formule actuels dans Menlog pour ce PT1
                    $new_formula_skus = [];
                    foreach ($question['subProducts'] as $subProductSku) {
                        $subProduct = $this->get_product_by_sku($all_products, $subProductSku);
                        if ($subProduct && $subProduct['productType'] == 4) {
                            $new_formula_skus[] = $subProduct['sku'];
                        }
                    }
                
                    // Supprimer les produits de formule qui ne sont plus présents dans Menlog
                    foreach ($existing_formula_skus as $existing_formula_sku) {
                        // Si le PT4 BDD n'est pas dans le liste des PT4 de Menlog
                        if (!in_array($existing_formula_sku, $new_formula_skus)) {
                            // Récupérer le produit de formule à supprimer
                            $formula_product_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE sku = %s", $existing_formula_sku), ARRAY_A);

                            // S'il y a bien un PT4 BDD, on avance car il faut supprimer les PT3 et PT2 associés à ce PT4
                            if ($formula_product_to_delete) {
                                $formula_product_id = $formula_product_to_delete['id'];
                
                                // Récupérer les questions PT3 associées à ce produit de formule (PT4) dans la BDD
                                $questions_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $formula_product_id), ARRAY_A);
                                
                                // Pour chaque 
                                foreach ($questions_to_delete as $question_to_delete) {
                                    $question_id_to_delete = $question_to_delete['id'];
                
                                    // Récupérer les options PT2 associées à cette question PT3 dans la BDD
                                    $options_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d", $question_id_to_delete), ARRAY_A);
                                    
                                    foreach ($options_to_delete as $option_to_delete) {
                                        // Supprime les options PT2
                                        if (!$this->delete_option($option_to_delete['id'])) {
                                            // Enregistre un message d'erreur si la suppression échoue
                                            $this->message_erreur .= "Erreur : L'option PT2 avec ID '{$option_to_delete['id']}' n'a pas pu être supprimée pour la question PT3 avec ID '{$question_id_to_delete}' pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                        } else {
                                            $this->products_PT2_deleted++;
                                        }
                                    }
                
                                    // Supprime la question PT3
                                    if (!$this->delete_question($question_id_to_delete)) {
                                        // Enregistre un message d'erreur si la suppression échoue
                                        $this->message_erreur .= "Erreur : La question PT3 avec ID '{$question_id_to_delete}' n'a pas pu être supprimée pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT3_deleted++;
                                    }
                                }
                
                                // Supprime le produit de formule (PT4) lui-même
                                if (!$this->delete_formula_product($formula_product_id)) {
                                    // Enregistre un message d'erreur si la suppression échoue
                                    $this->message_erreur .= "Erreur : Le produit de formule PT4 avec SKU '{$existing_formula_sku}' n'a pas pu être supprimé pour le PT1 dont l'ID est '{$product_id}' dans la fonction process_sub_products().\n";
                                } else {
                                    $this->products_PT4_deleted++;
                                }
                            }
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
                
    
                /**
                 * Ajout et modification de PT2 dans la partie produit simple
                 */
                // Pour chaque sous-produit du productType 3 de menlog
                // Donc pour chaque productType 2 ou 4 du productType 3 de menlog
                foreach ($question['subProducts'] as $subQuestionSku) {
    
                    // Si trouvé, récupère tout le productType 2 ou 4 (l'option ou le produit de la formule)
                    $sub_product = $this->get_product_by_sku($all_products, $subQuestionSku);
        
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
                    // elseif ($sub_product['productType'] == 4) {
                    //     $formula_product_id = $this->insert_formula_product($question_id, $sub_product);
        
                    //     foreach ($sub_product['subProducts'] as $nestedQuestionSku) {
                    //         $nested_question = $this->get_product_by_sku($all_products, $nestedQuestionSku);
                    //         $nested_question_id = $this->insert_question($formula_product_id, $nested_question);
        
                    //         foreach ($nested_question['subProducts'] as $nestedOptionSku) {
                    //             $nested_option = $this->get_product_by_sku($all_products, $nestedOptionSku);
                    //             $this->insert_option($nested_question_id, $nested_option);
                    //         }
                    //     }
                    // }
                }
            } else {
                // S'il n'y a pas de productType3, normallement process_sub_products n'est pas lancée
                // Donc si on tombe ici, il y a un problème
                $this->message_erreur .= "Erreur : Le sous-produit PT3 avec SKU '{$subProductSku}' n'a pas été trouvé pour le PT1 dont l'ID est '{$product_id} dans la fonction process_sub_products().\n";
            }
        }

        // Suppression des PT3 qui ne sont plus dans Menlog (et des PT2 associés aux PT3)
        // Dans ce contexte un PT3 peut être associé à une seul PT1. Par exemple, PT3A est associé à PT1X via un lien. PT3A peut être associé à PT1Y, mais via un autre lien. Ainsi, les PT3 sont différents.
        if($this->isFormula($product)) {
            // Dans le cas où le produit en cours est une formule
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
    

    private function get_products() {
        $url = "https://{$this->server}/{$this->delivery}/{$this->uuidclient}/{$this->uuidmagasin}/check_products?token={$this->token}&nocache=true";
        
        $options = [
            'http' => [
                'header' => "Authorization: Bearer {$this->token}\r\n",
                'method' => 'GET'
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            die('Error obtaining products');
        }

        return json_decode($result, true);
    }

    /**
     * Met à jour un produit WooCommerce existant
     * Permet aussi à un produit d'avoir plusieurs catégories
     * 
     * @param int $product_id L'ID du produit WooCommerce.
     * @param array $product Les données du produit Menlog.
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
        // TODO : Faire également la mise à jour d'une formule (4, 3, 2)
        if (!empty($product_data['subProducts'])) {
            // Traite les sous-produits associés au produit principal PT1 en cours
            $this->process_sub_products($product_id, $product_data['subProducts'], $menlogProducts);
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

    /**
     * Insère un ProductType 2 dans la BDD associé à un ProductType 3
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
     * Met à jour un productType 2 dans la BDD
     * @param mixed $question_id
     * @param mixed $option
     * @return void
     */
    private function update_option($question_id, $option) {
        global $wpdb;
        // Effectue la mise à jour de l'option dans la base de données
        $updated = $wpdb->update("{$wpdb->prefix}custom_options", [
            'question_id' => $question_id,
            'sku' => $option['sku'],
            'option_name' => $option['name'],
            'price' => $option['price'],
            'id_category' => $option['idCategory'],
            'description' => $option['description']
        ], ['id' => $option['id']]);
    
        // Retourne true si la mise à jour a affecté au moins une ligne, false sinon
        return $updated !== false && $updated > 0;
    }
    

    /**
     * Supprime un productType 2 de la BDD
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
        $content = "Catégories : {$this->categories_created} créées, {$this->categories_updated} mises à jour, {$this->categories_skipped} skipped.\n";
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

        $content .= "Détails des messages :\n";
        $content .= $this->message_erreur;
    
        file_put_contents($file_path, $content);
    }

    /**
     * Vérifie si le produit Menlog est un produit simple ou une formule
     * @param mixed $product
     * @return bool
     */
    public function isFormula($product) {
        // Vérifie si le produit a des sous-produits (PT3)
        if (!empty($product['subProducts'])) {
            // Il y a des sous-produits (PT3)
            // Pour chaque PT3, on cherche des PT4
            foreach ($product['subProducts'] as $isFormula_subProduct) {
                if (!empty($isFormula_subProduct['subProducts'])) {
                    // On a un PT4, donc c'est une formule
                    return true;
                }
            }
        }
        // Sinon, c'est un produit simple
        return false;
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


add_action('woocommerce_before_add_to_cart_button', 'display_custom_questions_and_options', 15);

function display_custom_questions_and_options() {
    global $post, $wpdb;

    // Obtenir l'ID du produit actuel
    $product_id = $post->ID;

    // Obtenir le produit WooCommerce actuel pour récupérer le prix
    $product = wc_get_product($product_id);
    $base_price = $product->get_price();

    // Récupérer les questions PT3 associées à ce produit
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
        $product_id
    ), ARRAY_A);

    // Si des questions existent, les afficher
    if (!empty($questions)) {
        echo '<div class="custom-questions">';
        foreach ($questions as $question) {
            $question_id = esc_attr($question['id']);
            $question_text = esc_html($question['question_text']);
            $min = intval($question['min']);
            $max = intval($question['max']);

            echo "<div class='question' data-question-id='{$question_id}' data-min='{$min}' data-max='{$max}'>";
            echo "<p><strong>{$question_text}</strong></p>";
            
            // Récupérer les options PT2 pour chaque question
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d",
                $question['id']
            ), ARRAY_A);

            // Si des options existent, les afficher
            if (!empty($options)) {
                echo '<div class="options">';
                foreach ($options as $index => $option) {
                    $option_name = esc_html($option['option_name']);
                    $option_price = esc_html($option['price']);
                    $option_sku = esc_attr($option['sku']);

                    // Ajouter un attribut pour sélectionner par défaut si l'index est inférieur au minimum
                    $checked = $index < $min ? 'checked' : '';

                    // Générer un bouton radio pour chaque option
                    echo '<label>';
                    echo "<input type='checkbox' name='option_{$question_id}[{$option_sku}]' value='{$option_name}' class='option-checkbox' data-price='{$option_price}' {$checked} data-question='{$question_text}'> {$option_name} (+{$option_price} €)";
                    echo '</label><br>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        // Champ caché pour transmettre le prix total
        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }

    // Ajout de JavaScript pour gérer la logique des minimum, maximum et mise à jour des prix
    ?>
    <script>
        jQuery(document).ready(function($) {
            const basePrice = parseFloat('<?php echo $base_price; ?>');
            let currentPrice = basePrice;

            // Fonction pour mettre à jour le prix total affiché
            function updatePrice() {
                let additionalPrice = 0;
                $('.custom-questions .question').each(function() {
                    $(this).find('.option-checkbox:checked').each(function() {
                        additionalPrice += parseFloat($(this).data('price'));
                    });
                });
                currentPrice = basePrice + additionalPrice;
                $('.woocommerce-Price-amount').text(currentPrice.toFixed(2) + ' €');

                // Mettre à jour le champ caché avec le nouveau prix total
                $('#custom_total_price').val(currentPrice.toFixed(2));
            }

            // Fonction pour valider si les sélections respectent les conditions de minimum
            function validateSelections() {
                let valid = true;
                $('.custom-questions .question').each(function() {
                    const questionDiv = $(this);
                    const minOptions = parseInt(questionDiv.data('min'));
                    const selectedOptions = questionDiv.find('.option-checkbox:checked').length;

                    if (selectedOptions < minOptions) {
                        valid = false;
                    }
                });
                return valid;
            }

            // Fonction pour afficher/masquer et activer/désactiver le bouton d'ajout au panier
            function toggleAddToCartButton() {
                const isValid = validateSelections();
                const addToCartButton = $('button.single_add_to_cart_button');

                if (isValid) {
                    addToCartButton.prop('disabled', false).show(); // Activer et afficher le bouton
                } else {
                    addToCartButton.prop('disabled', true).hide();  // Désactiver et masquer le bouton
                }
            }

            // Initialiser les questions et gérer les interactions
            $('.custom-questions .question').each(function() {
                const questionDiv = $(this);
                const minOptions = parseInt(questionDiv.data('min'));
                const maxOptions = parseInt(questionDiv.data('max'));

                // Sélectionner par défaut le nombre minimum d'options
                questionDiv.find('.option-checkbox').slice(0, minOptions).prop('checked', true);

                // Vérifier le nombre d'options sélectionnées après la sélection par défaut
                const selectedOptions = questionDiv.find('.option-checkbox:checked').length;

                // Activer/désactiver les cases à cocher en fonction du nombre d'options sélectionnées
                if (selectedOptions >= maxOptions) {
                    questionDiv.find('.option-checkbox:not(:checked)').prop('disabled', true);
                } else {
                    questionDiv.find('.option-checkbox:not(:checked)').prop('disabled', false);
                }

                questionDiv.find('.option-checkbox').change(function() {
                    const selectedOptions = questionDiv.find('.option-checkbox:checked').length;

                    // Activer/désactiver les cases à cocher en fonction du nombre d'options sélectionnées
                    if (selectedOptions >= maxOptions) {
                        questionDiv.find('.option-checkbox:not(:checked)').prop('disabled', true);
                    } else {
                        questionDiv.find('.option-checkbox:not(:checked)').prop('disabled', false);
                    }

                    // Mettre à jour le prix total en fonction des options sélectionnées
                    updatePrice();

                    // Mettre à jour l'affichage et l'état du bouton d'ajout au panier
                    toggleAddToCartButton();
                });
            });

            // Validation avant l'ajout au panier
            $('form.cart').on('submit', function(e) {
                if (!validateSelections()) {
                    e.preventDefault(); // Empêche la soumission du formulaire si la validation échoue
                    alert('Veuillez vérifier que vous avez sélectionné les options requises pour chaque question.');
                }
            });

            // Initialiser le prix et l'état du bouton d'ajout au panier lors du chargement de la page
            updatePrice();
            toggleAddToCartButton();
        });

    </script>
    <?php
}

// Ajuster le prix du produit et ajouter les options comme méta-données au panier
add_filter('woocommerce_add_cart_item_data', 'add_custom_options_to_cart', 10, 3);
function add_custom_options_to_cart($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['custom_total_price'])) {
        // Ajouter le prix personnalisé au panier
        $cart_item_data['custom_price'] = (float) sanitize_text_field($_POST['custom_total_price']);
    }

    // Ajouter les options sélectionnées comme méta-données
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'option_') === 0) {
            foreach ($value as $option_name) {
                $cart_item_data['custom_options'][] = sanitize_text_field($option_name);
            }
        }
    }

    return $cart_item_data;
}

// Modifier le prix affiché dans le panier
add_action('woocommerce_before_calculate_totals', 'update_cart_price');
function update_cart_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    // Mettre à jour le prix de chaque article dans le panier
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['custom_price'])) {
            $cart_item['data']->set_price($cart_item['custom_price']);
        }
    }
}

// Afficher les options sélectionnées dans le panier
add_filter('woocommerce_get_item_data', 'display_custom_options_in_cart', 10, 2);
function display_custom_options_in_cart($item_data, $cart_item) {
    if (isset($cart_item['custom_options'])) {
        $item_data[] = [
            'key'   => 'Options',
            'value' => implode(', ', $cart_item['custom_options'])
        ];
    }
    return $item_data;
}








?>


