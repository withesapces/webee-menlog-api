jQuery(document).ready(function($) {
    var basePrice = parseFloat($('#custom_total_price').val());

    // Gestion de la sélection des produits PT4
    $('.formula-option input[type="checkbox"]').change(function() {
        var $section = $(this).closest('.formula-section'); // Section PT3 actuelle
        var min = parseInt($section.data('min')); // Minimum de produits PT4 à sélectionner
        var max = parseInt($section.data('max')); // Maximum de produits PT4 à sélectionner

        var selectedCount = $section.find('.formula-option > input[type="checkbox"]:checked').length;

        if (selectedCount > max) {
            $(this).prop('checked', false);
            alert('Vous ne pouvez sélectionner que ' + max + ' options au maximum.');
        } else {
            if ($(this).hasClass('pt4-checkbox')) { 
                if ($(this).is(':checked')) {
                    $(this).closest('.formula-option').find('.formula-suboptions').slideDown();
                } else {
                    $(this).closest('.formula-option').find('.formula-suboptions').slideUp();
                    $(this).closest('.formula-option').find('.formula-suboption input[type="checkbox"]').prop('checked', false);
                }
            }
        }

        if (selectedCount >= max) {
            $section.find('.formula-option > input[type="checkbox"]:not(:checked)').closest('.formula-option').slideUp();
        } else {
            $section.find('.formula-option').slideDown();
        }

        validateSelections($section, min, max);
        updateTotalPrice();  // Recalcule le prix uniquement après un changement
    });

    // Gestion de la sélection des sous-options PT2
    $('.formula-suboption input[type="checkbox"]').change(function() {
        var $subsection = $(this).closest('.formula-suboption');
        var $pt4Option = $(this).closest('.formula-option');
        var min = parseInt($subsection.data('min'));
        var max = parseInt($subsection.data('max'));

        var selectedSubCount = $subsection.find('input[type="checkbox"]:checked').length;

        if (selectedSubCount > max) {
            $(this).prop('checked', false);
            alert('Vous ne pouvez sélectionner que ' + max + ' sous-options.');
        }

        validateSubOptions($subsection, min, max);
        updateTotalPrice();  // Recalcule le prix uniquement après un changement
    });

    // Fonction pour valider les sélections PT4
    function validateSelections($section, min, max) {
        var selectedCount = $section.find('.formula-option > input[type="checkbox"]:checked').length;
        var $pt4Info = $section.find('.pt4-selection-info');

        if (selectedCount < min) {
            $pt4Info.css('color', 'red').text('Vous devez sélectionner au moins ' + min + ' options.');
        } else if (selectedCount <= max) {
            $pt4Info.css('color', 'green').text('Sélection des PT4 valide.');
        }

        validateGlobal();
    }

    // Fonction pour valider les sous-options PT2
    function validateSubOptions($subsection, min, max) {
        var selectedSubCount = $subsection.find('input[type="checkbox"]:checked').length;
        var $pt2Info = $subsection.find('.pt2-selection-info');

        if (selectedSubCount < min) {
            $pt2Info.css('color', 'red').text('Vous devez sélectionner au moins ' + min + ' sous-options.');
        } else if (selectedSubCount <= max) {
            $pt2Info.css('color', 'green').text('Sélection de sous-options valide.');
        }
    }

    // Fonction pour valider toutes les sections (global)
    function validateGlobal() {
        var isValid = true;
        var globalMessage = '';

        $('.formula-section').each(function() {
            var $section = $(this);
            var min = parseInt($section.data('min'));
            var max = parseInt($section.data('max'));
            var selectedCount = $section.find('.formula-option > input[type="checkbox"]:checked').length;

            if (selectedCount < min) {
                isValid = false;
                globalMessage += 'Vous devez sélectionner au moins ' + min + ' options pour la question "' + $section.find('h4').text() + '". ';
            }
        });

        if (!isValid) {
            $('#global-validation-message').text(globalMessage).show();
        } else {
            $('#global-validation-message').hide();
        }
    }

    // Fonction pour mettre à jour le prix total
    function updateTotalPrice() {
        var totalPrice = basePrice;  // On commence avec le prix de base
        console.log('Prix de base : ' + basePrice);
    
        // Parcourt toutes les sections et ajoute les prix des produits PT4 sélectionnés
        $('.formula-section').each(function() {
            var $section = $(this);
    
            // PT4 : Ajouter le prix de chaque produit PT4 sélectionné
            $section.find('.pt4-checkbox:checked').each(function() {
                var productId = $(this).attr('id');  // Utilisation de l'ID du produit PT4
                var price = parseFloat($(`#${productId}`).data('price')) || 0;  // Accès direct à l'élément PT4 par son ID
                totalPrice += price;
    
                // Débogage pour chaque option PT4
                console.log('Ajout du prix du PT4 sélectionné (ID: ' + productId + ') : ' + price + ', Total intermédiaire : ' + totalPrice);
    
                // Sous-options PT2 : Ajouter le prix des sous-options PT2 sélectionnées uniquement une fois
                var uniqueSubOptions = new Set(); // Utilisation d'un Set pour éviter le double comptage
    
                // Parcourir les sous-options PT2 associées au PT4
                $(this).closest('.formula-option').find('.formula-suboption input[type="checkbox"]:checked').each(function() {
                    var suboptionId = $(this).attr('id');  // Utilisation de l'ID de la sous-option PT2
                    if (!uniqueSubOptions.has(suboptionId)) {
                        uniqueSubOptions.add(suboptionId);
                        var suboptionPrice = parseFloat($(this).data('price')) || 0;
                        totalPrice += suboptionPrice;
    
                        // Débogage pour chaque sous-option PT2
                        console.log('Ajout du prix de la sous-option PT2 sélectionnée (ID: ' + suboptionId + ') : ' + suboptionPrice + ', Total intermédiaire : ' + totalPrice);
                    }
                });
            });
        });
    
        // Mise à jour de l'affichage du prix total
        $('#formula-total-price').text(totalPrice.toFixed(2) + ' €');
        $('#custom_total_price').val(totalPrice.toFixed(2));
    
        // Débogage du prix final
        console.log('Prix total mis à jour : ' + totalPrice.toFixed(2) + ' €');
    }
    

    // Initialisation : calculer le prix total et vérifier les sélections au chargement de la page
    updateTotalPrice();
    validateGlobal();
});
