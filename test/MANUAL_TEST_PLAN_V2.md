# Plan de tests manuels — DolistoreExtract V2

## Installation

- Activer, désactiver puis réactiver le module.
- Vérifier la création des tables `dolistoreextract_order`, `dolistoreextract_order_line`, `dolistoreextract_invoice_batch` et `dolistoreextract_import_log`.
- Vérifier que les réglages existants sont conservés après désactivation/réactivation.
- Vérifier que `config_page_url` n’ouvre que `admin/setup.php`.

## Import IMAP

- Importer un mail DoliStore complet.
- Vérifier qu’aucune commande client Dolibarr native n’est créée.
- Vérifier qu’une Commande DoliStore est créée avec lignes, client final, montant source, montant facturable net et date de libération.
- Réimporter le même mail et vérifier l’absence de doublon par référence DoliStore, Message-ID ou hash.
- Importer une ligne sans service mappé et vérifier que la ligne reste archivée avec `fk_product` vide et un avertissement dans le journal.
- Vérifier que le fichier `.eml` source apparaît dans les fichiers joints de la commande.

## Travaux planifiés

- Sur une première activation, vérifier que les trois travaux DolistoreExtract sont présents et actifs dans l'interface native.
- Vérifier que les switches `Import IMAP automatique`, `Génération automatique de facture` et `Notification quotidienne` sont désactivés par défaut.
- Exécuter chacun des trois travaux depuis l'interface native avec son switch désactivé et vérifier un résultat réussi, sans traitement, avec un message explicite.
- Activer chaque switch séparément puis exécuter le travail correspondant depuis l'interface native.
- Désactiver manuellement les travaux, noter leur fréquence, priorité et prochaine exécution, puis réactiver le module : vérifier que leur statut repasse à actif sans modification des autres réglages.
- Répéter dans deux entités et vérifier l'indépendance des travaux et des trois switches métier.
- Avec les switches automatiques désactivés, vérifier que l'import manuel, la génération manuelle de facture et l'API forcée restent fonctionnels.
- Vérifier que l'option d'envoi automatique de facture n'ajoute aucun travail planifié et ne pilote que l'envoi après génération d'une facture.

## Facturation

- Configurer le tiers DoliStore, le seuil HT, le délai de libération et le statut de facture brouillon.
- Sur une nouvelle entité taxable, vérifier que le taux de TVA de vente par défaut est enregistré et présélectionné dans le Select2 natif.
- Sur une entité non assujettie, vérifier que le taux initial est `0 %`.
- Vérifier que le Select2 propose uniquement les taux de vente actifs du dictionnaire de l'entité.
- Tester une valeur avec code TVA, par exemple `20 (CODE)`, et vérifier que le code est conservé sur la ligne de facture native.
- Réactiver le module avec des valeurs existantes `0`, `5.5` et `20 (CODE)` et vérifier qu'elles ne sont pas remplacées.
- Désactiver dans le dictionnaire un taux déjà configuré et vérifier l'avertissement sans modification automatique de la constante.
- Tester deux entités avec des taux par défaut différents et vérifier l'indépendance des réglages.
- Lancer le CRON de facturation avec un montant inférieur au seuil.
- Lancer le CRON avec des commandes libérées au-dessus du seuil.
- Vérifier qu’une seule facture est créée pour le mois courant.
- Vérifier une ligne de facture par ligne DoliStore, avec description de traçabilité.
- Vérifier que les commandes passent en statut `Facturée` uniquement après création de facture.
- Vérifier que le lot reste en brouillon tant que le PDF natif n'est pas lisible, puis passe en succès avec un lien vers la facture.
- Simuler un échec de génération PDF, relancer le traitement et vérifier que le PDF est régénéré sans seconde facture.
- Tester un lot sans `fk_facture` avec une facture candidate unique et vérifier son rapprochement automatique.
- Tester un lot ambigu ou sans correspondance fiable et vérifier qu'aucune facture supplémentaire n'est créée.
- Tester les réglages de statut de facture `Brouillon` et `Validée`.
- Activer l’envoi email en environnement de test et vérifier l’envoi avec PDF joint.

## Liste des factures

- Vérifier qu'un seul titre `Factures DoliStore` est affiché.
- Vérifier que le bouton natif `+` de génération est sur la ligne de pagination.
- Vérifier dans le DOM que le sélecteur `#limit` appartient au formulaire de liste.
- Vérifier qu'un lot sans facture résoluble affiche un avertissement au lieu d'une cellule vide.

## Multicompany

- Créer/importer des commandes dans deux entités.
- Vérifier les filtres par entité et la colonne Environnement.
- Générer ou joindre des documents depuis une entité différente et vérifier que les fichiers restent dans l’entité propriétaire.
- Vérifier la présence de l’objet partageable dans les réglages Multicompany.

## Sécurité

- Tester un utilisateur lecture seule.
- Tester un utilisateur sans droit.
- Vérifier les actions sensibles sans token : import manuel, suppression, sauvegarde de notes, génération de facture.
- Vérifier l’API avec et sans droit API.
