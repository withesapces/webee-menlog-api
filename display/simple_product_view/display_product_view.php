<?php

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
            echo "<p class='selection-info'>Sélectionnez entre {$min} et {$max} options.</p>";
            
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
                    $default_qty = $checked ? 1 : 1; // Initialiser à 1 si l'option est cochée
                    $qty_visibility = $checked ? '' : ' style="display: none;"';
                
                    echo "<div class='customizer-option'>";
                    echo "<label class='customizer-option-label'>";
                    echo "<input type='checkbox' name='option_{$question_id}[{$option_sku}][checked]' value='1' class='option-checkbox' data-price='{$option_price}' {$checked} data-question='{$question_text}'>";
                    echo "<span class='customizer-option-name'>{$option_name}</span>";
                    echo "<span class='customizer-option-price'>+{$option_price} €</span>";
                    echo "</label>";
                    echo "<div class='option-qty-container' {$qty_visibility}>";
                    echo "<label for='option_qty_{$option_sku}' class='option-qty-label'>Qte :</label>";
                    echo "<input id='option_qty_{$option_sku}' type='number' name='option_{$question_id}[{$option_sku}][qty]' value='{$default_qty}' min='1' class='option-qty' data-sku='{$option_sku}' data-price='{$option_price}'" . (!$checked ? " disabled" : "") . ">";
                    echo "</div>";
                    echo "</div>";
                }
                
      
                echo '</div>';
            }
            echo '</div>';
        }
        echo '<div class="product-total">Total : <span id="product-total-price">' . esc_html($base_price) . ' €</span></div>';
        echo '<div id="validation-message" style="color: red; display: none;"></div>';
        echo '</div>';
        echo '<input type="hidden" id="custom_total_price" name="custom_total_price" value="' . esc_attr($base_price) . '">';
    }
}