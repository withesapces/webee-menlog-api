jQuery(document).ready(function($) {
    // Ajouter les styles CSS dans le document
    const style = `
        .input-error {
            border-color: red;
        }

        .error-message {
            color: red;
            font-size: 0.875em;
            margin-top: 5px;
            display: block;
        }
    `;

    // Ajouter le style au head
    $('<style type="text/css">' + style + '</style>').appendTo('head');

    // Liste des champs à vérifier
    const fieldsToCheck = [
        '#billing_first_name',
        '#billing_last_name',
        '#billing_company',
        '#billing_address_1',
        '#billing_address_2',
        '#billing_city',
        '#billing_postcode',
        '#billing_phone',
        '#billing_email',
        '#shipping_first_name',
        '#shipping_last_name',
        '#shipping_company',
        '#shipping_address_1',
        '#shipping_address_2',
        '#shipping_city',
        '#shipping_postcode',
        '#order_comments' // Ajout des notes de commande
    ];

    // Fonction pour enlever les caractères spéciaux et ajouter de la validation visuelle
    function removeSpecialChars(event) {
        const regex = /[^a-zA-Z0-9\s\-_@\.]/g;
        const inputValue = $(this).val();
        const cleanValue = inputValue.replace(regex, '');

        if (inputValue !== cleanValue) {
            $(this).val(cleanValue);
            $(this).addClass('input-error'); // Ajoute une classe d'erreur au champ
            if (!$(this).next('.error-message').length) {
                $(this).after('<span class="error-message">Les caractères spéciaux ne sont pas autorisés.</span>');
            }
        } else {
            $(this).removeClass('input-error'); // Supprime la classe d'erreur si le champ est valide
            $(this).next('.error-message').remove(); // Supprime le message d'erreur
        }
    }

    // Appliquer la fonction sur chaque champ
    fieldsToCheck.forEach(function(selector) {
        $(document).on('input', selector, removeSpecialChars);
    });
});
