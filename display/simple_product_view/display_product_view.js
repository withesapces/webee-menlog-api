jQuery(document).ready(function($) {
    const basePrice = parseFloat($('#custom_total_price').val());
    let currentPrice = basePrice;

    function updatePrice() {
        let additionalPrice = 0;
        $('.customizer-section').each(function() {
            $(this).find('.option-checkbox:checked').each(function() {
                const qty = parseInt($(this).closest('.customizer-option').find('.option-qty').val());
                additionalPrice += parseFloat($(this).data('price')) * qty;
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
            let selectedOptions = 0;

            // Compter le nombre total de quantités sélectionnées pour les cases cochées
            $(this).find('.option-checkbox:checked').each(function() {
                selectedOptions += parseInt($(this).closest('.customizer-option').find('.option-qty').val());
            });

            // Valider que le nombre total de quantités est compris entre le minimum et le maximum autorisés
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

        section.find('.option-checkbox').each(function() {
            const qty = $(this).closest('.customizer-option').find('.option-qty').val();
            $(this).prop('disabled', selectedOptions >= maxOptions && !$(this).is(':checked'));
        });
    }

    $('.customizer-section').each(function() {
        const section = $(this);
        const minOptions = parseInt(section.data('min'));

        // Cocher les options par défaut (minimum requis)
        section.find('.option-checkbox').slice(0, minOptions).prop('checked', true);

        // Afficher/masquer les champs de quantité en fonction de la case cochée
        section.find('.option-checkbox').each(function() {
            const isChecked = $(this).is(':checked');
            const qtyContainer = $(this).closest('.customizer-option').find('.option-qty-container');

            if (isChecked) {
                qtyContainer.show();
                const qtyInput = qtyContainer.find('.option-qty');
                qtyInput.prop('disabled', false); // Activer le champ de quantité
                if (qtyInput.val() === "0" || qtyInput.val() === "") {
                    qtyInput.val(1); // S'assurer que la quantité est au moins de 1
                }
            } else {
                qtyContainer.hide();
                qtyContainer.find('.option-qty').val(0).prop('disabled', true); // Réinitialiser la quantité à 0 et désactiver
            }
        });

        // Désactiver les options si nécessaire au chargement de la page
        updateCheckboxStates(section);

        section.find('.option-checkbox').change(function() {
            const qtyContainer = $(this).closest('.customizer-option').find('.option-qty-container');
            if ($(this).is(':checked')) {
                qtyContainer.show();
                const qtyInput = qtyContainer.find('.option-qty');
                qtyInput.prop('disabled', false); // Activer le champ de quantité
                if (qtyInput.val() === "0" || qtyInput.val() === "") {
                    qtyInput.val(1); // S'assurer que la quantité est au moins de 1
                }
            } else {
                qtyContainer.hide();
                qtyContainer.find('.option-qty').val(0).prop('disabled', true); // Réinitialiser à 0 et désactiver
            }

            updateCheckboxStates(section);
            updatePrice();
            toggleAddToCartButton();
        });
    });

    $('form.cart').on('submit', function(e) {
        // Désactiver les champs de quantité qui ne sont pas pertinents
        $('.option-qty-container').each(function() {
            const checkbox = $(this).closest('.customizer-option').find('.option-checkbox');
            if (!checkbox.is(':checked')) {
                $(this).find('.option-qty').prop('disabled', true); // Désactiver le champ de quantité pour éviter qu'il ne soit soumis
            }
        });

        if (!validateSelections()) {
            e.preventDefault();
            alert('Veuillez vérifier que vous avez sélectionné les options requises pour chaque section.');
        }
    });

    $('.option-qty').on('input', function() {
        const section = $(this).closest('.customizer-section');
        updatePrice();
        toggleAddToCartButton();
        updateCheckboxStates(section);
    });

    updatePrice();
    toggleAddToCartButton();
});
