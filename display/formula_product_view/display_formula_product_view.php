<?php
// Ajoute une action avant le bouton d'ajout au panier de WooCommerce
add_action('woocommerce_before_add_to_cart_button', 'display_custom_questions_and_options_for_formula', 15);

function display_custom_questions_and_options_for_formula() {
    global $post, $wpdb;

    // Récupère l'ID du produit actuel (PT1)
    $product_id = $post->ID;

    // Vérifie si le produit contient "Formule" dans son nom
    if (strpos(wc_get_product($product_id)->get_name(), 'Formule') === false) {
        return; // Si ce n'est pas un produit Formule, on arrête.
    }

    // Récupère les questions PT3 associées au PT1 (Formule)
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $product_id
    ), ARRAY_A);

    // Si des questions existent
    if (!empty($questions)) {
        $base_price = wc_get_product($product_id)->get_price(); // Prix de base de la formule PT1
        echo '<div class="formula-builder">'; // Début du conteneur principal

        // Pour chaque question PT3
        foreach ($questions as $question) {
            $question_id = esc_attr($question['id']);
            $question_text = esc_html($question['question_text']);
            $min = intval($question['min']); // Nombre minimum de produits PT4 à sélectionner
            $max = intval($question['max']); // Nombre maximum de produits PT4 à sélectionner

            // Affiche la question PT3 avec ses contraintes de min et max
            echo "<div class='formula-section' data-question-id='{$question_id}' data-min='{$min}' data-max='{$max}'>";
            echo "<h4>{$question_text}</h4>";
            echo '<div class="selection-info pt4-selection-info"></div>';
            // echo "<p class='selection-info'>Sélectionnez entre {$min} et {$max} options.</p>";

            // Récupère les produits PT4 associés à cette question PT3
            $formula_products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d", $question_id
            ), ARRAY_A);

            // Si des produits PT4 existent, on les affiche
            if (!empty($formula_products)) {
                echo "<div class='formula-options'>"; // Conteneur des options PT4
                foreach ($formula_products as $product) {
                    $product_id = esc_attr($product['id']);
                    $product_name = esc_html($product['product_name']);
                    $product_price = esc_html($product['price']);

                    // Affiche un produit PT4 avec une case à cocher pour la sélection
                    echo "<div class='formula-option' data-product-id='{$product_id}' data-price='{$product_price}'>";
                    echo "<input type='checkbox' class='pt4-checkbox' name='formula_option_{$question_id}[]' id='formula_option_{$product_id}' value='{$product_id}' class='formula-option-checkbox'>";
                    echo "<label for='formula_option_{$product_id}' class='formula-option-label'>";
                    echo "<span class='formula-option-name'>{$product_name}</span>";
                    echo "<span class='formula-option-price'>+{$product_price} €</span>";
                    echo "</label>";

                    // Récupère les sous-questions PT3 associées à ce produit PT4
                    $nested_questions = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d", $product_id
                    ), ARRAY_A);

                    // Si des sous-questions existent, on les affiche
                    if (!empty($nested_questions)) {
                        echo "<div class='formula-suboptions' style='display:none;'>"; // Conteneur des sous-options PT2
                        foreach ($nested_questions as $nested_question) {
                            $nested_question_id = esc_attr($nested_question['id']);
                            $nested_question_text = esc_html($nested_question['question_text']);
                            $nested_min = intval($nested_question['min']); // Nombre minimum de sous-options PT2 à sélectionner
                            $nested_max = intval($nested_question['max']); // Nombre maximum de sous-options PT2 à sélectionner

                            // Affiche la sous-question PT3 avec ses contraintes
                            echo "<div class='formula-suboption' data-question-id='{$nested_question_id}' data-min='{$nested_min}' data-max='{$nested_max}'>";
                            echo "<h5>{$nested_question_text}</h5>";
                            echo '<div class="selection-info pt2-selection-info"></div>';
                            //echo "<p class='selection-info'>Sélectionnez entre {$nested_min} et {$nested_max} options.</p>";

                            // Récupère les options PT2 associées à cette sous-question PT3
                            $nested_options = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d", $nested_question_id
                            ), ARRAY_A);

                            if (!empty($nested_options)) {
                                echo '<div class="formula-suboption-choices">'; // Conteneur des options PT2
                                foreach ($nested_options as $option) {
                                    $option_id = esc_attr($option['id']);
                                    $option_name = esc_html($option['option_name']);
                                    $option_price = esc_html($option['price']);

                                    // Affiche une sous-option PT2 avec une case à cocher pour la sélection
                                    echo "<div class='formula-suboption-choice'>";
                                    echo "<input type='checkbox' name='suboption_{$nested_question_id}[]' id='suboption_{$option_id}' value='{$option_id}' data-price='{$option_price}' class='formula-suboption-checkbox'>";
                                    echo "<label for='suboption_{$option_id}' class='formula-suboption-label'>";
                                    echo "<span class='formula-suboption-name'>{$option_name}</span>";
                                    echo "<span class='formula-suboption-price'>+{$option_price} €</span>";
                                    echo "</label>";
                                    echo "</div>";
                                }
                                echo '</div>'; // Fin du conteneur des sous-options PT2
                            }
                            echo '</div>'; // Fin de la sous-question PT3
                        }
                        echo '</div>'; // Fin du conteneur des sous-options PT2
                    }
                    echo "</div>"; // Fin de l'option PT4
                }
                echo "</div>"; // Fin du conteneur des options PT4
            }
            echo "</div>"; // Fin de la section PT3
        }

        // Conteneur pour le prix total
        echo '<div class="formula-total">Total : <span id="formula-total-price">' . esc_html($base_price) . ' €</span></div>';

        // Conteneur pour le message d'erreur global
        echo '<div id="global-validation-message" style="color: red; display: none;"></div>';

        echo '</div>'; // Fin du conteneur principal

        // Champ caché pour stocker le prix total personnalisé
        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }
}


