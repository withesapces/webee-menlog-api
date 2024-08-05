Un même productType2 (finalité, puisque option) peut appartenir à plusieurs productType3
Un même productType3 (question) peut appartenir à plusieurs productType1
Un même productType4 (produit d'une formule) peut appartenir à plusieurs productType3


Mise à jour d'un productType1 puis d'un productType3 :
- Déclenché via import_products() qui détecte que le produit existe déjà, n'existe pas, ou n'existe plus
- Si dans import_products(), productType 1 existe déjà, on lance update_product_with_category()
    - Dans update_product_with_category(), si le produit doit effectivement être mis à jour, on lance update_product()
        - Dans update_product(), on met à jour le produit. 
            - Dans update_product(), si le produit à des sous-produits (donc des ProductType3), on lance process_sub_products() pour voir s'il ne faut pas modifer des PT3 pour ce produit PT1.
                - Dans cette fonction process_sub_products(), on récupère tous les sku PT3 pour le PT1 qu'on est en train de regarder
                    - On parcours alors chaque SKU PT3 de ce PT3
                        - Pour chaque PT3, on le cherche dans Menlog
                            - Si le PT3 existe déjà, on lance update_question()
                                - On mets à jour si besoin le PT3
                            - Si le PT3 n'existe pas, on lance insert_question()
                                - On ajoute le PT3
                    - On parcours chaque ligne de la table des questions pour ce produit
                        - Pour chaque ligne, on cherche si le SKU existe encore pour ce produit
                            - Si non, on lance delete_question()
            - Dans update_product(), si le produit n'a pas de sous-produits (donc des ProductType3), on ne fait rien
- Si dans import_products(), productType 1 n'existe pas
    - On lance l'insert insert_product() pour insérer le ProductType1 comme produit WooCommerce
    - On récupère l'ID de ce nouveau produit.
        - Si on a l'ID, on regarde si ce PT1 à un ou plusieurs PT3
            - Si oui, on lance process_sub_products() pour ajouter le ou les PT3 pour ce PT1
    - On lance tout le processus pour l'ajouter et pour ajouter les PT3 si le PT1 en a
- Si dans import_products(), productType 1 n'existe plus
  - On lance set_missing_products_to_draft() car le produit n'existe plus dans Menlog ce qui mets en brouillon ce PT1
  - set_missing_products_to_draft() lance delete_related_questions() qui supprime le PT3 relié à ce PT1, s'il y en a