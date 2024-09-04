<?php
// Ajoute une action avant le bouton d'ajout au panier de WooCommerce
add_action('woocommerce_before_add_to_cart_button', 'display_custom_questions_and_options_for_formula', 15);

function display_custom_questions_and_options_for_formula() {
    global $post, $wpdb;

    $product_id = $post->ID;
    if (strpos(wc_get_product($product_id)->get_name(), 'Formule') === false) {
        return;
    }

    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_questions WHERE product_id = %d", $product_id
    ), ARRAY_A);

    if (!empty($questions)) {
        $base_price = wc_get_product($product_id)->get_price();
        echo '<div class="formula-builder">';

        foreach ($questions as $question) {
            $question_id = esc_attr($question['id']);
            $question_text = esc_html($question['question_text']);
            $min = intval($question['min']);
            $max = intval($question['max']);

            echo "<div class='formula-section card mb-4' data-question-id='{$question_id}' data-min='{$min}' data-max='{$max}'>";
            echo "<div class='card-header bg-primary text-white'><h4 class='mb-0'>{$question_text}</h4></div>";
            echo "<div class='card-body'>";
            echo "<p class='selection-info pt4-selection-info mb-3' style='color: red;'>Vous devez sélectionner au moins {$min} produits. Sélection actuelle : 0.</p>";

            $formula_products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}custom_formula_products WHERE question_id = %d", $question_id
            ), ARRAY_A);

            if (!empty($formula_products)) {
                echo "<div class='formula-options row'>";
                foreach ($formula_products as $product) {
                    $product_id = esc_attr($product['id']);
                    $product_name = esc_html($product['product_name']);
                    $product_price = esc_html($product['price']);

                    echo "<div class='formula-option col-md-6 mb-3' data-product-id='{$product_id}' data-price='{$product_price}' id='formula-option-{$product_id}'>";
                    echo "<div class='card h-100'>";
                    echo "<div class='card-body'>";
                    echo "<div class='form-check'>";
                    echo "<input type='checkbox' class='form-check-input pt4-checkbox' name='formula_option_{$question_id}[]' id='formula_option_{$product_id}' value='{$product_id}'>";
                    echo "<label class='form-check-label' for='formula_option_{$product_id}'>";
                    echo "<span class='formula-option-name'>{$product_name}</span>";
                    echo "<span class='formula-option-price badge bg-success float-end'>+{$product_price} €</span>";
                    echo "</label>";
                    echo "</div>";
                    echo "<div class='mt-2 d-flex align-items-center qty-wrapper' style='display: none;'>";
                    echo "<button type='button' class='btn btn-outline-secondary qty-decrease' disabled>-</button>";  // Bouton pour diminuer la quantité
                    echo "<input type='number' class='form-control pt4-qty mx-2' name='formula_option_qty_{$product_id}' min='1' value='1' readonly style='width: 60px;'>";  // Ajout de 'readonly'
                    echo "<button type='button' class='btn btn-outline-secondary qty-increase'>+</button>";  // Bouton pour augmenter la quantité
                    echo "</div>";     

                    $nested_questions = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}custom_questions_for_formulas WHERE formula_product_id = %d", $product_id
                    ), ARRAY_A);

                    if (!empty($nested_questions)) {
                        echo "<div class='formula-suboptions mt-3' style='display:none;'>";
                        foreach ($nested_questions as $nested_question) {
                            $nested_question_id = esc_attr($nested_question['id']);
                            $nested_question_text = esc_html($nested_question['question_text']);
                            $nested_min = intval($nested_question['min']);
                            $nested_max = intval($nested_question['max']);

                            echo "<div class='formula-suboption' data-question-id='{$nested_question_id}' data-min='{$nested_min}' data-max='{$nested_max}'>";
                            echo "<h5 class='mt-3'>{$nested_question_text}</h5>";
                            echo "<p class='selection-info pt2-selection-info mb-2'>Vous pouvez sélectionner entre {$nested_min} et {$nested_max} options. Sélection actuelle : 0.</p>";

                            $nested_options = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}custom_options_for_formulas WHERE formula_question_id = %d", $nested_question_id
                            ), ARRAY_A);

                            if (!empty($nested_options)) {
                                echo '<div class="formula-suboption-choices">';
                                foreach ($nested_options as $option) {
                                    $option_id = esc_attr($option['id']);
                                    $option_name = esc_html($option['option_name']);
                                    $option_price = esc_html($option['price']);

                                    echo "<div class='formula-suboption-choice form-check'>";
                                    echo "<input type='checkbox' class='form-check-input formula-suboption-checkbox' name='suboption_{$nested_question_id}[]' id='suboption_{$option_id}' value='{$option_id}' data-price='{$option_price}'>";
                                    echo "<label class='form-check-label' for='suboption_{$option_id}'>";
                                    echo "<span class='formula-suboption-name'>{$option_name}</span>";
                                    echo "<span class='formula-suboption-price badge bg-info float-end'>+{$option_price} €</span>";
                                    echo "</label>";
                                    echo "<div class='mt-2 d-flex align-items-center qty-wrapper-pt2' style='display: none;'>";
                                    echo "<button type='button' class='btn btn-outline-secondary qty-decrease-pt2'>-</button>";
                                    echo "<input type='number' class='form-control pt2-qty mx-2' name='suboption_qty_{$option_id}' min='0' value='1' readonly style='width: 60px;'>";
                                    echo "<button type='button' class='btn btn-outline-secondary qty-increase-pt2'>+</button>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
            }
            echo "</div>";
            echo "</div>";
        }

        echo '<div class="formula-total card mt-4">';
        echo '<div class="card-body text-center">';
        echo '<p class="card-text">Votre total : <span id="formula-total-price" class="h2 text-primary">' . esc_html($base_price) . ' €</span></p>';
        echo '</div>';
        echo '</div>';

        echo '<div id="global-validation-message" class="alert alert-danger mt-3" style="display: none;"></div>';

        echo '</div>';

        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }
}


