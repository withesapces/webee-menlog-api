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
                 * Suppression des PT2 > PT3 > PT4 si c'est un produit Formule
                 * Suppression des PT2 si c'est un produit Simple
                 */
                if ($this->isFormula($product)) {
                    // Dans le cas où le PT1 est une formule
                    // On est déjà dans la configuration PT1 > PT3
                
                    // Récupérer tous les produits de formule existants (PT4) dans la BDD qui sont reliés à cette combinaison PT1 > PT3 
                    $existing_formula_products = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d", $question_id), ARRAY_A);

                    // Récupérer le contenu de la colonne sku et id dans la BDD pour cette combinaison PT1 > PT3
                    // utilisatation d'un tableau associatif avec clé id et valeur sku
                    $formula_products_by_id = [];
                    foreach ($existing_formula_products as $formula_product) {
                        $formula_products_by_sku[$formula_product['id']] = $formula_product['sku'];
                    }
                
                    // Collecter les SKUs des PT4 actuels dans Menlog pour cette combinaison PT1 > PT3
                    // $question contient la question PT3 en cours
                    // $question['subProducts'] contient les skus PT4 de la question PT3 en cours
                    $new_formula_skus = [];
                    foreach ($question['subProducts'] as $subProductSku) {
                        // On récupère l'ensemble du soit-disant pt4 dans menlog
                        $subProduct = $this->get_product_by_sku($all_products, $subProductSku);
                        // Si c'est bien un PT4, on enregistre son SKU dans $new_formula_skus[]
                        if ($subProduct && $subProduct['productType'] == 4) {
                            $new_formula_skus[] = $subProduct['sku'];
                        }
                    }

                    // Maintenant pour la combinaison en cours PT1 > PT3
                    // Nous avons tous les skus PT4 et tous les id PT4 de la bdd
                    // Nous avons aussi tous les skus PT4 menlog 

                    // Le prochaine étape consiste à parcourir chaque PT4 BDD pour vérifier s'ils sont encore présent en BDD
                    // Si oui, on vérifie si PT3 associés sont encore en BDD ainsi que PT2
                    // Si non, on supprime PT2, PT3 et PT4
                    // Pour chaque sku PT4 BDD de cette combinaison PT1 > PT3
                    foreach ($formula_products_by_id as $existing_formula_id => $existing_formula_sku) {
                        // Récupérer le PT4 BDD complet via son ID pour vérifier ses sous-produits PT3 et PT2
                        $formula_product = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE id = %d", $existing_formula_id), ARRAY_A);
                
                        if ($formula_product) {
                            // On récupère l'ID du PT4 qu'on regarde
                            $formula_product_id = $formula_product['id'];
                
                            // Vérifier si le PT4 existe toujours dans Menlog
                            // Ici si on passe, PT1 > PT3 > PT4 existe encore.
                            if (in_array($existing_formula_sku, $new_formula_skus)) {
                                // On doit donc vérifier ce qu'il y a après le PT4. 
                                // Donc PT3 et PT2 puisque PT1 > PT3 > PT4 > PT3 > PT2

                                // On récupère toutes les questions PT3 BDD qui sont reliés au produit PT4 BDD
                                $existing_questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $formula_product_id), ARRAY_A);
                
                                // On va chercher les nouvelles questions PT3 pour ce PT4 dans menlogs
                                // Pour le PT4 en cours, on doit le chercher sur menlog et récupérer ses sub product
                                $new_question_skus = [];
                                $formula_product_menlog = $this->get_product_by_sku($all_products, $existing_formula_sku);

                                // Pour chaque SKU PT3 Menlog du PT4 Menlog, on récupère les SKU PT3 dans un tableau
                                foreach ($formula_product_menlog['subProducts'] as $PT4subQuestionSku) {
                                    // On récupère le PT3 Menlog appartennant à la combinaison PT1 > PT3 > PT4 > PT3
                                    $subQuestion = $this->get_product_by_sku($all_products, $PT4subQuestionSku);
                                    if ($subQuestion && $subQuestion['productType'] == 3) {
                                        $new_question_skus[] = $subQuestion['sku'];
                                    }
                                }
                
                                // Nous avons à présent tous les skus des PT3 menlog (PT1 > PT3 > PT4 > PT3)

                                // Supprimer les questions PT3 et leurs options PT2 qui ne sont plus présentes dans Menlog
                                // Dans cette chaine, on doit donc supprimer l'avant dernier PT3 et/ou l'avant dernier PT2 (PT1 > PT3 > PT4 > PT3 > PT2)
                                // Pour chaque PT1 > PT3 > PT4 > PT3 BDD...
                                foreach ($existing_questions as $existing_question) {

                                    // On récupère le SKU PT1 > PT3 > PT4 > PT3 BDD
                                    $existing_question_sku = $existing_question['sku'];
                                    // On récupère le ID PT1 > PT3 > PT4 > PT3 BDD
                                    $question_id_to_delete = $existing_question['id'];
                
                                    // Si le PT1 > PT3 > PT4 > PT3 BDD n'est pas dans le tableau des PT1 > PT3 > PT4 > PT3 Menlog, 
                                    // on doit supprimer le PT1 > PT3 > PT4 > PT3 BDD (donc aussi le PT2 de la fin)
                                    if (!in_array($existing_question_sku, $new_question_skus)) {
                                        // Supprimer les options PT2 associées au PT3
                                        $options_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d", $question_id_to_delete), ARRAY_A);
                                        foreach ($options_to_delete as $option_to_delete) {
                                            if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                                $this->message_erreur .= "Erreur : L'option PT2 avec ID '{$option_to_delete['id']}' n'a pas pu être supprimée pour la question PT3 avec SKU '{$existing_question_sku}' pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                            } else {
                                                $this->products_PT2_deleted++;
                                            }
                                        }
                
                                        // Supprimer la question PT3
                                        if (!$this->delete_question_for_formula($question_id_to_delete)) {
                                            $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$existing_question_sku}' n'a pas pu être supprimée pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                        } else {
                                            $this->products_PT3_deleted++;
                                        }
                                    } else {
                                        // Si le PT3 existe encore, supprimer uniquement les options PT2 qui n'existent plus dans PT1 > PT3 > PT4 > PT3 > PT2
                                        $options = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d", $question_id_to_delete), ARRAY_A);
                                        $existing_option_skus = array_column($options, 'sku');
                
                                        // On stock les SKUs menlog pour les PT2 dans la config actuelle PT1 > PT3 > PT4 > PT3 > PT2
                                        $new_option_skus = [];
                                        $formula_product_question_menlog = $this->get_product_by_sku($all_products, $existing_question_sku);
                                        foreach ($formula_product_question_menlog['subProducts'] as $nestedOptionSku) {
                                            $nestedOption = $this->get_product_by_sku($all_products, $nestedOptionSku);
                                            if ($nestedOption && $nestedOption['productType'] == 2) {
                                                $new_option_skus[] = $nestedOption['sku'];
                                            }
                                        }
                
                                        // Supprimer les options PT2 qui ne sont plus présentes dans Menlog
                                        // Pour chaque PT1 > PT3 > PT4 > PT3 > PT2 de la BDD...
                                        foreach ($existing_option_skus as $existing_option_sku) {
                                            // Si PT1 > PT3 > PT4 > PT3 > PT2 de la BDD n'existe plus, on le supprime
                                            if (!in_array($existing_option_sku, $new_option_skus)) {
                                                $option_to_delete = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d AND sku = %s", $question_id_to_delete, $existing_option_sku), ARRAY_A);
                                                if ($option_to_delete) {
                                                    if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                                        $error_message = $wpdb->last_error;
                                                        $this->message_erreur .= "Erreur : L'option PT2 avec SKU '{$existing_option_sku}' (ID: {$option_to_delete['id']}) n'a pas pu être supprimée pour la question PT3 avec SKU '{$existing_question_sku}' (Question ID: {$question_id_to_delete}) pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products(). Erreur SQL : {$error_message}\n";
                                                    } else {
                                                        $this->products_PT2_deleted++;
                                                    }
                                                }
                                            }
                                        }                                        
                                    }
                                }
                            } else {
                                // Si le PT4 n'existe plus dans Menlog, supprimer tout ce qui est lié : PT3 et PT2
                                $questions_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $formula_product_id), ARRAY_A);
                                foreach ($questions_to_delete as $question_to_delete) {
                                    $question_id_to_delete = $question_to_delete['id'];
                
                                    // Supprimer les options PT2
                                    $options_to_delete = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d", $question_id_to_delete), ARRAY_A);
                                    foreach ($options_to_delete as $option_to_delete) {
                                        if (!$this->delete_options_for_formulas($option_to_delete['id'])) {
                                            $this->message_erreur .= "Erreur : L'option PT2 avec ID '{$option_to_delete['id']}' n'a pas pu être supprimée pour la question PT3 avec SKU '{$question_to_delete['sku']}' pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                        } else {
                                            $this->products_PT2_deleted++;
                                        }
                                    }
                
                                    // Supprimer la question PT3
                                    if (!$this->delete_question_for_formula($question_id_to_delete)) {
                                        $this->message_erreur .= "Erreur : La question PT3 avec SKU '{$question_to_delete['sku']}' n'a pas pu être supprimée pour le PT4 avec SKU '{$existing_formula_sku}' dans la fonction process_sub_products().\n";
                                    } else {
                                        $this->products_PT3_deleted++;
                                    }
                                }
                
                                // Supprimer le produit de formule PT4
                                if (!$this->delete_formula_product($formula_product_id)) {
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
        if ($this->isFormula($product)) {
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
     * @param int $question_id 
     * @param array $option 
     */
    private function update_options_for_formulas($question_id, $option) {
        global $wpdb;
        // Effectue la mise à jour et retourne le nombre de lignes affectées
        $updated = $wpdb->update("{$wpdb->prefix}custom_options_for_formulas", [
            'question_id' => $question_id,
            'option_name' => $option['name'],
            'sku' => $option['sku'],
            'price' => $option['price'],
            'id_category' => $option['idCategory'],
            'description' => $option['description'],
        ], ['id' => $question_id]);
    
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
        // TODO : Vérifier si ça fonctionne pour un PT1 Formule
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
        // TODO : Vérifier si ça fonctionne pour un PT1 Formule
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
            $this->process_sub_products($product_id, $product_data['subProducts'], $menlogProducts);
        }

        if (!empty($updates)) {
            // TODO : Pour le débogage, savoir si on met à jour un PT1 Formule ou simple
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

// function create_custom_tables_on_admin_init() {
//     $plugin = new WooCommerce_API_Integration();
//     $plugin->create_custom_tables();
// }

// add_action('admin_init', 'create_custom_tables_on_admin_init');


add_action('woocommerce_before_add_to_cart_button', 'display_custom_questions_and_options', 15);

function display_custom_questions_and_options() {
    global $post, $wpdb;

    $product_id = $post->ID;
    $product = wc_get_product($product_id);
    $base_price = $product->get_price();

    if (strpos($product->get_name(), 'Formule') !== false) {
        return;
    }

    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
        $product_id
    ), ARRAY_A);

    if (!empty($questions)) {
        echo '<div class="product-customizer">';
        
        foreach ($questions as $question) {
            $question_id = esc_attr($question['id']);
            $question_text = esc_html($question['question_text']);
            $min = intval($question['min']);
            $max = intval($question['max']);

            echo "<div class='customizer-section' data-question-id='{$question_id}' data-min='{$min}' data-max='{$max}'>";
            echo "<h4>{$question_text}</h4>";
            
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}custom_options WHERE question_id = %d",
                $question['id']
            ), ARRAY_A);

            if (!empty($options)) {
                echo '<div class="customizer-options">';
                foreach ($options as $index => $option) {
                    $option_name = esc_html($option['option_name']);
                    $option_price = esc_html($option['price']);
                    $option_sku = esc_attr($option['sku']);
                    $checked = $index < $min ? 'checked' : '';

                    echo "<div class='customizer-option'>";
                    echo "<label class='customizer-option-label'>";
                    echo "<input type='checkbox' name='option_{$question_id}[{$option_sku}]' value='{$option_name}' class='option-checkbox' data-price='{$option_price}' {$checked} data-question='{$question_text}'>";
                    echo "<span class='customizer-option-name'>{$option_name}</span>";
                    echo "<span class='customizer-option-price'>+{$option_price} €</span>";
                    echo "</label>";
                    echo "</div>";
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<div class="product-total">Total : <span id="product-total-price">' . esc_html($base_price) . ' €</span></div>';
        echo '</div>';
        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }

    add_action('wp_footer', 'product_customizer_script');
}

function product_customizer_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        const basePrice = parseFloat($('#custom_total_price').val());
        let currentPrice = basePrice;

        function updatePrice() {
            let additionalPrice = 0;
            $('.customizer-section').each(function() {
                $(this).find('.option-checkbox:checked').each(function() {
                    additionalPrice += parseFloat($(this).data('price'));
                });
            });
            currentPrice = basePrice + additionalPrice;
            $('#product-total-price').text(currentPrice.toFixed(2) + ' €');
            $('#custom_total_price').val(currentPrice.toFixed(2));
        }

        function validateSelections() {
            let valid = true;
            $('.customizer-section').each(function() {
                const minOptions = parseInt($(this).data('min'));
                const selectedOptions = $(this).find('.option-checkbox:checked').length;
                if (selectedOptions < minOptions) {
                    valid = false;
                }
            });
            return valid;
        }

        function toggleAddToCartButton() {
            const isValid = validateSelections();
            const addToCartButton = $('button.single_add_to_cart_button');
            if (isValid) {
                addToCartButton.prop('disabled', false).removeClass('disabled');
            } else {
                addToCartButton.prop('disabled', true).addClass('disabled');
            }
        }

        $('.customizer-section').each(function() {
            const section = $(this);
            const minOptions = parseInt(section.data('min'));
            const maxOptions = parseInt(section.data('max'));

            section.find('.option-checkbox').slice(0, minOptions).prop('checked', true);

            section.find('.option-checkbox').change(function() {
                const selectedOptions = section.find('.option-checkbox:checked').length;
                section.find('.option-checkbox').prop('disabled', selectedOptions >= maxOptions);
                section.find('.option-checkbox:checked').prop('disabled', false);
                updatePrice();
                toggleAddToCartButton();
            });
        });

        $('form.cart').on('submit', function(e) {
            if (!validateSelections()) {
                e.preventDefault();
                alert('Veuillez vérifier que vous avez sélectionné les options requises pour chaque section.');
            }
        });

        updatePrice();
        toggleAddToCartButton();
    });
    </script>
    <?php
}

add_action('wp_head', 'product_customizer_style');
function product_customizer_style() {
    ?>
    <style>
        .product-customizer {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 32px;
            margin-bottom: 40px;
            font-family: 'Roboto', sans-serif;
        }

        .product-customizer h3 {
            font-size: 28px;
            color: #333;
            margin-bottom: 24px;
            text-align: center;
        }

        .customizer-section {
            margin-bottom: 32px;
        }

        .customizer-section h4 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }

        .customizer-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .customizer-option {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .customizer-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .customizer-option-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
            z-index: 1;
        }

        .customizer-option-label input[type="checkbox"] {
            display: none;
        }

        .customizer-option-label input[type="checkbox"] + .customizer-option-name::before {
            content: '';
            display: inline-block;
            width: 18px;
            height: 18px;
            margin-right: 10px;
            border: 2px solid #bdc3c7;
            border-radius: 4px;
            vertical-align: middle;
            transition: all 0.3s ease;
        }

        .customizer-option-label input[type="checkbox"]:checked + .customizer-option-name::before {
            background-color: #27ae60;
            border-color: #27ae60;
        }

        .customizer-option-label input[type="checkbox"]:checked + .customizer-option-name::after {
            content: '✔';
            color: #ffffff;
            position: absolute;
            left: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
        }

        .customizer-option-name {
            flex-grow: 1;
            font-size: 16px;
            color: #34495e;
            padding-left: 30px;
            position: relative;
        }

        .customizer-option-price {
            color: #27ae60;
            font-weight: bold;
            font-size: 14px;
        }

        .product-total {
            font-size: 24px;
            font-weight: bold;
            margin-top: 32px;
            text-align: right;
            color: #2c3e50;
            padding: 16px;
            background-color: #ecf0f1;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .product-customizer {
                padding: 20px;
            }

            .customizer-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php
}

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

        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
            $product_id
        ), ARRAY_A);

        foreach ($questions as $question) {
            $question_id = $question['id'];
            $selected_formula_product_id = isset($_POST["formula_option_{$question_id}"]) ? $_POST["formula_option_{$question_id}"] : null;

            if ($selected_formula_product_id) {
                $formula_product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE id = %d",
                    $selected_formula_product_id
                ), ARRAY_A);

                $cart_item_data['formula_options'][$question_id] = [
                    'question' => $question['question_text'],
                    'product' => $formula_product['product_name'],
                    'sku' => $formula_product['sku'],
                    'price' => $formula_product['price'],
                    'suboptions' => []
                ];

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
                                'price' => $option['price']
                            ];
                        }
                    }
                }
            }
        }
    } else {
        // Gestion des options pour les produits non-formule (inchangée)
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'option_') === 0) {
                foreach ($value as $option_name) {
                    $cart_item_data['custom_options'][] = sanitize_text_field($option_name);
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
            
            // Ajouter la valeur des suboptions derrière le produit, si elle existe
            if (!empty($formula_option['suboptions'])) {
                $suboption_values = [];
                foreach ($formula_option['suboptions'] as $suboption) {
                    $suboption_values[] = $suboption['option'];
                }
                $item_value .= ' (' . implode(', ', $suboption_values) . ')';
            }
    
            $item_data[] = [
                'key'   => $formula_option['question'],
                'value' => $item_value
            ];
        }
    } elseif (isset($cart_item['custom_options'])) {
        $item_data[] = [
            'key'   => 'Options',
            'value' => implode(', ', $cart_item['custom_options'])
        ];
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

add_action('woocommerce_before_add_to_cart_button', 'display_custom_questions_and_options_for_formula', 15);
function display_custom_questions_and_options_for_formula() {
    global $post, $wpdb;

    $product_id = $post->ID;
    $product = wc_get_product($product_id);
    $base_price = $product->get_price();

    if (strpos($product->get_name(), 'Formule') === false) {
        return;
    }

    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d",
        $product_id
    ), ARRAY_A);

    if (!empty($questions)) {
        echo '<div class="formula-builder">';
        
        foreach ($questions as $question) {
            $question_id = esc_attr($question['id']);
            $question_text = esc_html($question['question_text']);
            $min = intval($question['min']);
            $max = intval($question['max']);

            echo "<div class='formula-section pt3-section' data-question-id='{$question_id}' data-min='{$min}' data-max='{$max}'>";
            echo "<h4>{$question_text}</h4>";
            echo "<p class='selection-info'>Sélectionnez entre {$min} et {$max} options.</p>";
            
            $formula_products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d",
                $question_id
            ), ARRAY_A);

            if (!empty($formula_products)) {
                echo "<div class='formula-options'>";
                foreach ($formula_products as $formula_product) {
                    $formula_product_id = esc_attr($formula_product['id']);
                    $formula_product_name = esc_html($formula_product['product_name']);
                    $formula_product_price = esc_html($formula_product['price']);

                    echo "<div class='formula-option pt4-option' data-formula-id='{$formula_product_id}' data-price='{$formula_product_price}'>";
                    echo "<input type='radio' name='formula_option_{$question_id}' id='formula_option_{$formula_product_id}' value='{$formula_product_id}'>";
                    echo "<label for='formula_option_{$formula_product_id}' class='formula-option-label'>";
                    echo "<span class='formula-option-name'>{$formula_product_name}</span>";
                    echo "<span class='formula-option-price'>+{$formula_product_price} €</span>";
                    echo "</label>";

                    echo "<div class='formula-suboptions' style='display:none;'>";
                    echo "<button class='back-button'>Retour</button>";

                    $nested_questions = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d",
                        $formula_product_id
                    ), ARRAY_A);

                    if (!empty($nested_questions)) {
                        foreach ($nested_questions as $nested_question) {
                            $nested_question_id = esc_attr($nested_question['id']);
                            $nested_question_text = esc_html($nested_question['question_text']);
                            $nested_min = intval($nested_question['min']);
                            $nested_max = intval($nested_question['max']);

                            echo "<div class='formula-suboption' data-question-id='{$nested_question_id}' data-min='{$nested_min}' data-max='{$nested_max}'>";
                            echo "<h5>{$nested_question_text}</h5>";
                            echo "<p class='selection-info'>Sélectionnez entre {$nested_min} et {$nested_max} options.</p>";

                            $nested_options = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d",
                                $nested_question_id
                            ), ARRAY_A);

                            if (!empty($nested_options)) {
                                echo '<div class="formula-suboption-choices">';
                                foreach ($nested_options as $index => $option) {
                                    $option_name = esc_html($option['option_name']);
                                    $option_price = esc_html($option['price']);
                                    $option_sku = esc_attr($option['sku']);

                                    echo "<label class='formula-suboption-choice'>";
                                    echo "<input type='checkbox' name='option_{$nested_question_id}[{$option_sku}]' value='{$option_name}' data-price='{$option_price}'>";
                                    echo "<span class='formula-suboption-name'>{$option_name}</span>";
                                    echo "<span class='formula-suboption-price'>+{$option_price} €</span>";
                                    echo "</label>";
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<div class="formula-total">Total : <span id="formula-total-price">' . esc_html($base_price) . ' €</span></div>';
        echo '<div id="validation-message" style="color: red; display: none;"></div>';
        echo '</div>';
        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }
}

add_action('wp_footer', 'formula_builder_script');
function formula_builder_script() {
    ?>
    <script>
        jQuery(document).ready(function($) {
            // Initialiser le prix de base au chargement de la page
            var basePrice = parseFloat($('#custom_total_price').val());

            $('.formula-option input[type="radio"]').change(function() {
                var $option = $(this).closest('.formula-option');
                var $section = $option.closest('.formula-section');
                
                $section.find('.formula-option').not($option).removeClass('selected').find('.formula-suboptions').slideUp();
                $section.find('.formula-option').not($option).find('input[type="radio"]').prop('checked', false);
                
                $option.addClass('selected');
                $option.find('.formula-suboptions').slideDown();

                if ($section.hasClass('pt3-section') && $option.hasClass('pt4-option')) {
                    $section.find('.pt4-option').not($option).hide();
                }

                updateTotal();
                validateSelections();
            });

            $('.back-button').click(function(e) {
                e.preventDefault();
                var $option = $(this).closest('.formula-option');
                var $section = $option.closest('.formula-section');
                
                $option.removeClass('selected');
                $option.find('.formula-suboptions').slideUp();
                $option.find('input[type="radio"]').prop('checked', false);
                
                $option.find('input[type="checkbox"]').prop('checked', false);

                if ($section.hasClass('pt3-section')) {
                    $section.find('.pt4-option').show();
                }

                updateTotal();
                validateSelections();
            });

            $('.formula-suboption-choice input[type="checkbox"]').change(function() {
                updateTotal();
                validateSelections();
            });

            function updateTotal() {
                var total = basePrice;

                $('.formula-section').each(function() {
                    var $selectedOption = $(this).find('.formula-option.selected');
                    if ($selectedOption.length) {
                        total += parseFloat($selectedOption.data('price'));
                        
                        $selectedOption.find('.formula-suboption-choice input[type="checkbox"]:checked').each(function() {
                            total += parseFloat($(this).data('price'));
                        });
                    }
                });

                // Mettre à jour l'affichage du total
                $('#formula-total-price').text(total.toFixed(2) + ' €');

                // Mettre à jour le prix affiché du produit WooCommerce
                $('.woocommerce-Price-amount.amount').text(total.toFixed(2) + ' €');

                // Mettre à jour le champ caché avec le nouveau total
                $('#custom_total_price').val(total.toFixed(2));
            }

            function validateSelections() {
                let isValid = true;
                let message = "";

                $('.formula-section').each(function() {
                    const $section = $(this);
                    const min = parseInt($section.data('min'));
                    const max = parseInt($section.data('max'));
                    const selected = $section.find('.formula-option.selected').length;

                    if (selected < min || selected > max) {
                        isValid = false;
                        message += `Sélectionnez entre ${min} et ${max} options pour "${$section.find('h4').text()}". `;
                    }

                    $section.find('.formula-suboption').each(function() {
                        const $suboption = $(this);
                        const subMin = parseInt($suboption.data('min'));
                        const subMax = parseInt($suboption.data('max'));
                        const subSelected = $suboption.find('input[type="checkbox"]:checked').length;

                        if (subSelected < subMin || subSelected > subMax) {
                            isValid = false;
                            message += `Sélectionnez entre ${subMin} et ${subMax} options pour "${$suboption.find('h5').text()}". `;
                        }
                    });
                });

                if (!isValid) {
                    $('#validation-message').text(message).show();
                    $('button.single_add_to_cart_button').prop('disabled', true);
                } else {
                    $('#validation-message').hide();
                    $('button.single_add_to_cart_button').prop('disabled', false);
                }
            }

            validateSelections();
        });

    </script>
    <?php
}



add_action('wp_head', 'formula_builder_style');
function formula_builder_style() {
    ?>
    <style>
    .formula-builder {
        background-color: #ffffff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        padding: 32px;
        margin-bottom: 40px;
        font-family: 'Roboto', sans-serif;
    }

    .formula-builder h3 {
        font-size: 28px;
        color: #333;
        margin-bottom: 24px;
        text-align: center;
    }

    .formula-section {
        margin-bottom: 32px;
    }

    .formula-section h4 {
        font-size: 22px;
        color: #2c3e50;
        margin-bottom: 16px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e0e0e0;
    }

    .formula-options {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: center;
    }

    .formula-option {
        background-color: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 16px;
        transition: all 0.3s ease;
        width: calc(33.33% - 16px);
        min-width: 200px;
        cursor: pointer;
    }

    .formula-option:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .formula-option.selected {
        border-color: #3498db;
        background-color: #ebf5fb;
    }

    .formula-option-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .formula-option input[type="radio"] {
        display: none;
    }

    .formula-option-name {
        font-size: 18px;
        color: #34495e;
        margin-bottom: 8px;
    }

    .formula-option-price {
        color: #27ae60;
        font-weight: bold;
        font-size: 16px;
    }

    .formula-suboptions {
        margin-top: 16px;
        padding: 16px;
        background-color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .back-button {
        background-color: #3498db;
        color: #ffffff;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 16px;
    }

    .formula-suboption h5 {
        font-size: 18px;
        color: #7f8c8d;
        margin-bottom: 12px;
    }

    .formula-suboption-choice {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .formula-suboption-choice input[type="checkbox"] {
        margin-right: 8px;
    }

    .formula-suboption-name {
        flex-grow: 1;
        font-size: 16px;
        color: #34495e;
    }

    .formula-suboption-price {
        color: #27ae60;
        font-weight: bold;
    }

    .formula-total {
        font-size: 24px;
        font-weight: bold;
        margin-top: 32px;
        text-align: right;
        color: #2c3e50;
        padding: 16px;
        background-color: #ecf0f1;
        border-radius: 8px;
    }

    .selection-info {
        font-size: 14px;
        color: #666;
        margin-bottom: 10px;
    }

    #validation-message {
        margin-top: 20px;
        padding: 10px;
        background-color: #ffecec;
        border: 1px solid #f5aca6;
        border-radius: 4px;
    }

    .woocommerce-cart-form .product-name dl.variation {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            border-radius: 4px;
        }
        .woocommerce-cart-form .product-name dl.variation dt {
            font-weight: bold;
            margin-top: 5px;
        }
        .woocommerce-cart-form .product-name dl.variation dd {
            margin-left: 10px;
            margin-bottom: 5px;
        }

    @media (max-width: 768px) {
        .formula-builder {
            padding: 20px;
        }

        .formula-option {
            width: calc(50% - 16px);
        }
    }

    @media (max-width: 480px) {
        .formula-option {
            width: 100%;
        }
    }
    </style>
    <?php
}

?>