// TODO : Meilleure vérification PT2 pour ne pas afficher le panier si encore des erreurs
jQuery(document).ready(function($) {

    var basePrice = parseFloat($('#custom_total_price').val());

    // Cache initialement le bouton ajouter au panier
    $('.single_add_to_cart_button').prop("disabled", true);

    // Fonction pour vérifier les erreurs et afficher/masquer le bouton "Ajouter au panier"
    function checkErrors() {
        var hasError = false;

        // Vérification des sections PT4
        $('.formula-section').each(function() {
            var $section = $(this);
            var min = parseInt($section.data('min'));  // Minimum requis pour cette section
            var max = parseInt($section.data('max'));  // Maximum autorisé pour cette section
            var totalQty = 0;

            // Calculer la quantité totale sélectionnée pour cette section
            $section.find('.pt4-checkbox:checked').each(function() {
                var qty = parseInt($(this).closest('.formula-option').find('.pt4-qty').val()) || 0;
                totalQty += qty;
            });

            // Vérifier si la quantité sélectionnée est en dehors des limites
            if (totalQty < min || totalQty > max) {
                hasError = true;
                console.log("Erreur dans la section avec min: " + min + " et max: " + max + ". Quantité actuelle: " + totalQty);
            }
        });

        // Si une erreur est détectée, cacher le bouton, sinon l'afficher
        if (hasError) {
            console.log("Des erreurs détectées, bouton désactivé et caché");
            $('button.single_add_to_cart_button').prop('disabled', true).css('visibility', 'hidden');
            var $globalMessage = $('#global-validation-message');
            $globalMessage.text("Il manque des informations, veuillez vérifier vos données avant de pouvoir ajouter l'article au panier.").slideDown();
        } else {
            console.log("Aucune erreur, bouton activé et visible");
            $('#global-validation-message').slideUp().text('');
            $('button.single_add_to_cart_button').prop('disabled', false).css('visibility', 'visible');
        }
    }

    function showErrorMessage(message) {
        var $globalMessage = $('#global-validation-message');
        $globalMessage.text(message).slideDown();
    }
    
    function hideErrorMessage() {
        $('#global-validation-message').slideUp().text('');
    }
    

    /**
     * Gestion de l'affichage de la quantité et activation/désactivation des options 
     * lorsque l'utilisateur coche ou décoche une option PT4.
     */
    $('.pt4-checkbox').change(function() {
        var $formulaOption = $(this).closest('.formula-option');
        
        if ($(this).is(':checked')) {
            // Afficher les champs de quantité et réactiver les boutons
            $formulaOption.find('.qty-wrapper').slideDown();
            $formulaOption.find('.pt4-qty').prop('disabled', false);
            $formulaOption.find('.qty-decrease').prop('disabled', true); // Désactiver le bouton "-" par défaut
        } else {
            // Masquer et désactiver les champs de quantité
            $formulaOption.find('.qty-wrapper').slideUp();
            $formulaOption.find('.pt4-qty').val(1).prop('disabled', true);
    
            // Réinitialiser les sous-options PT2
            $formulaOption.find('.formula-suboptions input[type="checkbox"]').prop('checked', false); // Décocher toutes les sous-options
            $formulaOption.find('.formula-suboptions .pt2-qty').val(0).prop('disabled', true); // Mettre les quantités à 0 et désactiver les inputs
            $formulaOption.find('.formula-suboptions .qty-wrapper-pt2').slideUp(); // Masquer les champs de quantité PT2
    
            // Réinitialiser les compteurs PT2
            $formulaOption.find('.formula-suboption').each(function() {
                updateSubOptions($(this)); // Mettre à jour chaque sous-option PT2
            });
        }
    
        checkErrors(); // Appel à une fonction pour vérifier les erreurs éventuelles
    });
    

    /**
     * Gérer la diminution de la quantité PT4 via le bouton "-".
     */
    $('.qty-decrease').click(function() {
        var $input = $(this).siblings('.pt4-qty');
        var currentVal = parseInt($input.val());
        
        if (currentVal > 1) {
            $input.val(currentVal - 1);
        }
        
        // Désactiver le bouton "-" si la quantité est égale à 1
        $(this).prop('disabled', currentVal <= 2);

        updateSectionData($(this).closest('.formula-section'));
        checkErrors();
    });

    /**
     * Gérer l'augmentation de la quantité PT4 via le bouton "+".
     */
    $('.qty-increase').click(function() {
        var $input = $(this).siblings('.pt4-qty');
        var currentVal = parseInt($input.val());
        $input.val(currentVal + 1);
        
        // Activer le bouton "-" si la quantité est supérieure à 1
        $(this).siblings('.qty-decrease').prop('disabled', false);

        updateSectionData($(this).closest('.formula-section'));
        checkErrors();
    });

    /**
     * Gérer la diminution de la quantité PT2 via le bouton "-".
     */
    $('.qty-decrease-pt2').click(function() {
        var $input = $(this).siblings('.pt2-qty');
        var currentVal = parseInt($input.val());
        var $subsection = $(this).closest('.formula-suboption'); // Récupérer la sous-section
        var min = parseInt($subsection.data('min')) || 1; // Récupérer le min ou utiliser 1 comme défaut

        if (currentVal > min) {
            $input.val(currentVal - 1);
        }

        // Désactiver le bouton "-" si la quantité est égale au minimum
        $(this).prop('disabled', currentVal - 1 <= min);

        // Mettre à jour les informations de sélection
        updateSubOptions($subsection);
        checkErrors();
    });
    

    /**
     * Gérer l'augmentation de la quantité PT2 via le bouton "+".
     */
    $('.qty-increase-pt2').click(function() {
        var $input = $(this).siblings('.pt2-qty');
        var currentVal = parseInt($input.val());
        var $subsection = $(this).closest('.formula-suboption'); // Récupérer la sous-section
        var max = parseInt($subsection.data('max')) || 10; // Récupérer le max ou utiliser 10 comme défaut

        if (currentVal < max) {
            $input.val(currentVal + 1);
        }

        // Désactiver le bouton "+" si la quantité atteint le maximum
        $(this).prop('disabled', currentVal + 1 >= max);

        // Activer le bouton "-" si la quantité est supérieure au minimum
        $(this).siblings('.qty-decrease-pt2').prop('disabled', false);

        // Mettre à jour les informations de sélection
        updateSubOptions($subsection);
        checkErrors();
    });

    

    /**
     * Gestion de la sélection des produits PT4.
     */
    $('.formula-option input[type="checkbox"]').change(function() {
        var $section = $(this).closest('.formula-section');
        var selectedCount = $section.find('.formula-option > input[type="checkbox"]:checked').length;
        var max = parseInt($section.data('max'));

        if (selectedCount > max) {
            $(this).prop('checked', false);
            alert('Vous ne pouvez sélectionner que ' + max + ' options au maximum.');
        } else {
            handlePT4Selection($(this), $section);
        }

        updateSectionData($section);
        checkErrors();
    });

    /**
     * Gérer les changements manuels dans l'input de quantité PT4.
     */
    $('.pt4-qty').on('change', function() {
        var $input = $(this);
        var currentVal = parseInt($input.val());
        
        if (currentVal < 1) {
            $input.val(1);
        }

        $input.siblings('.qty-decrease').prop('disabled', currentVal <= 1);

        updateSectionData($(this).closest('.formula-section'));
        checkErrors();
    });

    /**
     * Gestion de la sélection des sous-options PT2.
     */
    $('.formula-suboption input[type="checkbox"]').change(function() {
        var $subsection = $(this).closest('.formula-suboption');
        var max = parseInt($subsection.data('max'));
        var selectedSubCount = $subsection.find('input[type="checkbox"]:checked').length;

        if (selectedSubCount > max) {
            $(this).prop('checked', false);
            alert('Vous ne pouvez sélectionner que ' + max + ' sous-options.');
        }

        updateSubOptions($subsection);
        checkErrors();
    });

    /**
     * Fonction pour gérer la sélection PT4 et les sous-options PT2 associées.
     */
    function handlePT4Selection($checkbox, $section) {
        var $formulaOption = $checkbox.closest('.formula-option');

        if ($checkbox.hasClass('pt4-checkbox')) {
            if ($checkbox.is(':checked')) {
                $formulaOption.find('.formula-suboptions').slideDown();
                $formulaOption.find('.pt4-qty').val(1).prop('disabled', false).slideDown();
            } else {
                $formulaOption.find('.formula-suboptions').slideUp();
                $formulaOption.find('.formula-suboption input[type="checkbox"]').prop('checked', false);
                $formulaOption.find('.pt4-qty').val(0).prop('disabled', true).slideUp();
            }
        }
    }

    /**
     * Fonction pour mettre à jour les données d'une section : recalculer la quantité totale et valider les sélections.
     */
    function updateSectionData($section) {
        var min = parseInt($section.data('min'));
        var max = parseInt($section.data('max'));

        checkTotalSelectedQty($section, min, max);
        validateSelections($section, min, max);
        updateTotalPrice();
    }

    /**
     * Vérifie le nombre total de produits sélectionnés dans une section PT4 et désactive ou masque les options si nécessaire.
     */
    function checkTotalSelectedQty($section, min, max) {
        var totalQty = 0;

        // Compter toutes les quantités PT4 sélectionnées
        $section.find('.pt4-checkbox:checked').each(function() {
            var qty = parseInt($(this).closest('.formula-option').find('.pt4-qty').val()) || 0;
            totalQty += qty;
        });

        // Si la quantité totale dépasse le max, désactiver les options supplémentaires
        if (totalQty >= max) {
            $section.find('.qty-increase').hide();
            $section.find('.formula-option').each(function() {
                var checkbox = $(this).find('.pt4-checkbox');
                if (!checkbox.is(':checked')) {
                    $(this).hide();
                }
            });
        } else {
            $section.find('.qty-increase').show();
            $section.find('.formula-option').each(function() {
                var checkbox = $(this).find('.pt4-checkbox');
                if (!checkbox.is(':checked')) {
                    $(this).show();
                }
            });
        }

        if (totalQty === 1) {
            $section.find('.qty-decrease').hide();
        } else {
            $section.find('.qty-decrease').show();
        }
    }

    /**
     * Valide la sélection dans une section PT4 et met à jour le message d'état.
     */
    function validateSelections($section, min, max) {
        var totalQty = 0;
    
        $section.find('.pt4-checkbox:checked').each(function() {
            var qty = parseInt($(this).closest('.formula-option').find('.pt4-qty').val()) || 0;
            totalQty += qty;
        });
    
        var $pt4Info = $section.find('.pt4-selection-info');
        if (totalQty < min) {
            $pt4Info.css('color', 'red').text('Vous devez sélectionner au moins ' + min + ' produits. Sélection actuelle : ' + totalQty + '.');
        } else if (totalQty <= max) {
            $pt4Info.css('color', 'green').text('Sélection correcte. Total sélectionné : ' + totalQty + '.');
        } else {
            $pt4Info.css('color', 'red').text('Vous avez dépassé le maximum de ' + max + ' produits. Sélection actuelle : ' + totalQty + '.');
        }
    }

    /**
     * Valide la sélection des sous-options PT2 et met à jour l'affichage.
     */
    function updateSubOptions($subsection) {
        var selectedSubCount = 0;
        var max = parseInt($subsection.data('max'));
        var $pt2Info = $subsection.find('.pt2-selection-info');
        
        // Compter le nombre total de sous-options sélectionnées et leurs quantités
        $subsection.find('input[type="checkbox"]:checked').each(function() {
            var qty = parseInt($(this).closest('.formula-suboption-choice').find('.pt2-qty').val()) || 1;
            selectedSubCount += qty;
        });
    
        // Mettre à jour l'affichage de la sélection
        $pt2Info.text('Sélection : ' + selectedSubCount + ' / ' + max);
        $pt2Info.css('color', selectedSubCount <= max ? 'green' : 'red');
        
        // Afficher/Masquer les champs de quantité PT2 pour chaque sous-option sélectionnée
        $subsection.find('input[type="checkbox"]').each(function() {
            var $checkbox = $(this);
            var $qtyWrapper = $checkbox.closest('.formula-suboption-choice').find('.qty-wrapper-pt2');
    
            if ($checkbox.is(':checked')) {
                $qtyWrapper.slideDown();
                $qtyWrapper.find('.pt2-qty').prop('disabled', false);
                $qtyWrapper.find('.qty-decrease-pt2').prop('disabled', false);
            } else {
                $qtyWrapper.slideUp();
                $qtyWrapper.find('.pt2-qty').val(1).prop('disabled', true);
                $qtyWrapper.find('.qty-decrease-pt2').prop('disabled', true); 
            }
        });
    
        // Si la quantité sélectionnée atteint ou dépasse le maximum, masquer les autres options
        if (selectedSubCount >= max) {
            $subsection.find('input[type="checkbox"]').not(':checked').closest('.formula-suboption-choice').hide();
        } else {
            $subsection.find('.formula-suboption-choice').show();  // Afficher toutes les options si le max n'est pas atteint
        }
    }

    /**
     * Met à jour le prix total basé sur les options sélectionnées.
     */
    function updateTotalPrice() {
        var totalPrice = basePrice;
    
        // Boucle sur toutes les sections de la formule pour calculer le prix
        $('.formula-section').each(function() {
            var $section = $(this);
    
            // Gestion des options PT4 sélectionnées
            $section.find('.pt4-checkbox:checked').each(function() {
                var $option = $(this).closest('.formula-option');
                var price = parseFloat($option.data('price')) || 0;
                var qty = parseInt($option.find('.pt4-qty').val()) || 1;
                
                // Ajouter le prix du PT4 multiplié par sa quantité
                totalPrice += price * qty;
    
                // Gestion des sous-options PT2 associées
                $option.find('.formula-suboption input[type="checkbox"]:checked').each(function() {
                    var suboptionPrice = parseFloat($(this).data('price')) || 0;
                    var suboptionQty = parseInt($(this).closest('.formula-suboption').find('.pt2-qty').val()) || 1;
                    
                    // Multiplier également le prix des sous-options PT2 par la quantité du PT4
                    totalPrice += suboptionPrice * suboptionQty * qty;
                });
            });
        });
    
        // Mise à jour du prix total
        $('#formula-total-price').text(totalPrice.toFixed(2) + ' €');
        $('#custom_total_price').val(totalPrice.toFixed(2));
    }    
    

    // Initialisation : calculer le prix total et valider les sélections au chargement de la page
    updateTotalPrice();
    checkErrors();
});
