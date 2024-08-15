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
        let message = "";

        $('.customizer-section').each(function() {
            const minOptions = parseInt($(this).data('min'));
            const maxOptions = parseInt($(this).data('max'));
            const selectedOptions = $(this).find('.option-checkbox:checked').length;

            if (selectedOptions < minOptions || selectedOptions > maxOptions) {
                valid = false;
                message += `Sélectionnez entre ${minOptions} et ${maxOptions} options pour "${$(this).find('h4').text()}". `;
            }
        });

        if (!valid) {
            $('#validation-message').text(message).show();
        } else {
            $('#validation-message').hide();
        }

        return valid;
    }

    function toggleAddToCartButton() {
        const isValid = validateSelections();
        const addToCartButton = $('button.single_add_to_cart_button');

        if (isValid) {
            addToCartButton.show().prop('disabled', false).removeClass('disabled');
        } else {
            addToCartButton.hide().prop('disabled', true).addClass('disabled');
        }
    }

    function updateCheckboxStates(section) {
        const selectedOptions = section.find('.option-checkbox:checked').length;
        const maxOptions = parseInt(section.data('max'));
        section.find('.option-checkbox').prop('disabled', selectedOptions >= maxOptions);
        section.find('.option-checkbox:checked').prop('disabled', false);
    }

    $('.customizer-section').each(function() {
        const section = $(this);
        const minOptions = parseInt(section.data('min'));

        // Cocher les options par défaut (minimum requis)
        section.find('.option-checkbox').slice(0, minOptions).prop('checked', true);

        // Désactiver les options si nécessaire au chargement de la page
        updateCheckboxStates(section);

        section.find('.option-checkbox').change(function() {
            updateCheckboxStates(section);
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