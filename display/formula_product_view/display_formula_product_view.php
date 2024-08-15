<?php

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