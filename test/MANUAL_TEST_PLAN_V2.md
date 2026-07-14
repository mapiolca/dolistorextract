# Plan de tests manuels — DolistoreExtract V2

## Installation

- Activer, désactiver puis réactiver le module.
- Vérifier que la famille et l'éditeur affichés dans la liste et l'onglet À propos sont `Les Métiers du Bâtiment`, sans ancienne identité éditeur.
- Vider le cache Dolibarr et celui du navigateur, puis vérifier que le favicon DoliStore est net et suffisamment grand dans la tuile du module, la liste des modules, les fiches et les menus.
- Vérifier la création des tables `dolistoreextract_order`, `dolistoreextract_order_line`, `dolistoreextract_invoice_batch` et `dolistoreextract_import_log`.
- Vérifier que les réglages existants sont conservés après désactivation/réactivation.
- Vérifier que `config_page_url` n’ouvre que `admin/setup.php`.

## Réglages

- Ouvrir l'onglet Compatibilité en français et vérifier que le titre et l'onglet affichent `Compatibilité`, sans clé ni libellé anglais résiduel.
- Vérifier que chaque fonctionnalité affiche son état dans un badge Dolibarr natif contenant le texte `Disponible` ou `Indisponible`, avec la raison renseignée pour les fonctionnalités indisponibles.
- Depuis chacun des onglets `Paramètres`, `Commandes DoliStore`, `Facturation` et `Courriels/IMAP`, enregistrer un réglage et vérifier que l'onglet d'origine reste actif.
- Dans l'onglet Facturation, tester un champ texte, le Select2 de TVA, une zone de texte, le statut de facture et chaque switch natif.
- Dans l'onglet Courriels/IMAP, tester un champ texte, un modèle d'email et le switch d'import automatique.
- Vérifier que la catégorie native de modèles de courriels est affichée comme `Commandes Dolistore` en français et `DoliStore orders` en anglais, sans clé de traduction brute.
- Sur une première activation, vérifier la création et la présélection des modèles `fr_FR` et `en_US` de type `dolistore_extract`, avec les sujets et corps HTML attendus.
- Modifier le contenu des deux modèles, réactiver le module et vérifier que les personnalisations et les sélections existantes sont conservées sans doublon.
- Vérifier que chaque Select2 de l'onglet Courriels/IMAP ne propose que les modèles publics et actifs de la langue correspondante, et que le lien de gestion ouvre la page native filtrée sur `dolistore_extract`.
- Supprimer ou désactiver un modèle sélectionné et vérifier qu'un avertissement est affiché sans remplacement automatique de la constante.
- Répéter l'activation dans deux entités et vérifier que les modèles et leurs constantes restent indépendants.
- Vérifier qu'un message de succès ou d'erreur reste affiché sur l'onglet d'origine après la redirection.
- Rafraîchir la page après une sauvegarde classique et vérifier que le navigateur ne propose pas de renvoyer le formulaire.
- Vérifier dans le DOM que les formulaires concernés contiennent le token CSRF et le champ caché `mode`, et que leur URL d'action conserve ce mode.
- Appeler `admin/setup.php?mode=invalide` et vérifier le retour propre sur l'onglet `Paramètres`.

## Import IMAP

- Importer un mail DoliStore complet.
- Vérifier qu’aucune commande client Dolibarr native n’est créée.
- Vérifier qu’une Commande DoliStore est créée avec lignes, client final, montant source, montant facturable net et date de libération.
- Réimporter le même mail et vérifier l'absence de doublon par référence DoliStore, Message-ID ou hash.
- Vérifier qu'un import réussi n'envoie aucun courriel de bienvenue au client final.
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
- Sans modèle public et actif `facture_send`, vérifier que le Select2 est vide et que le faux choix « Modèle de facture par défaut de Dolibarr » n'apparaît plus.
- Sur une première activation, vérifier la création et la présélection du modèle `DoliStore Extract - Facture des ventes DoliStore`, son type `facture_send`, sa langue `fr_FR`, son sujet, son corps HTML, son statut public et actif, ainsi que l'activation de la pièce jointe.
- Vérifier que ce modèle possède `defaultfortype = 0` et ne devient pas le modèle par défaut des autres factures clients Dolibarr.
- Personnaliser le libellé et le contenu du modèle, réactiver le module et vérifier l'absence de doublon et d'écrasement.
- Sélectionner un autre modèle public `facture_send`, réactiver le module et vérifier que cette sélection reste inchangée.
- Sur une nouvelle entité taxable, vérifier que le taux de TVA de vente par défaut est enregistré et présélectionné dans le Select2 natif.
- Sur une entité non assujettie, vérifier que le taux initial est `0 %`.
- Vérifier que le Select2 propose uniquement les taux de vente actifs du dictionnaire de l'entité.
- Créer plusieurs modèles de courriels natifs Dolibarr actifs et publics de type `facture_send`, puis vérifier qu'ils sont proposés dans le Select2 du module avec leur langue et que les modèles privés ne le sont pas.
- Sélectionner un modèle, enregistrer le réglage et vérifier que l'onglet Facturation reste actif.
- Vérifier que le lien de gestion ouvre la page native des modèles de courriels, filtrée sur `facture_send`.
- Envoyer automatiquement une facture et vérifier l'application du sujet, du contenu HTML ou texte, de la langue, de l'expéditeur et des substitutions natives du modèle sélectionné.
- Supprimer, privatiser ou désactiver le modèle sélectionné et vérifier le Select2 vide ou la sélection indisponible, l'avertissement dans les réglages et l'échec explicite de l'envoi automatique sans utilisation d'un autre modèle.
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
- Activer l'envoi email en environnement de test et vérifier le destinataire configuré, le sujet `Facture des ventes DoliStore`, le corps HTML et le PDF joint.
- Activer dans la configuration Agenda native l'événement automatique `BILL_SENTBYMAIL`, envoyer automatiquement une facture DoliStore et vérifier la création d'un unique événement natif lié à la facture, avec expéditeur, destinataire, sujet et pièce jointe.
- Désactiver `MAIN_AGENDA_ACTIONAUTO_BILL_SENTBYMAIL`, envoyer une autre facture et vérifier que l'email reste envoyé sans création forcée d'événement Agenda par le module.
- Répéter dans deux entités et vérifier que les modèles créés et les constantes de sélection sont indépendants.

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
