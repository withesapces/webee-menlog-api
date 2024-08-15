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