# Journal des modifications de DolistoreExtract

## Version 2.2.0 — 2026-09-10

- Rétablissement de la bienvenue pour chaque nouvel achat importé : cinq modèles natifs FR/EN/ES/IT/DE conformes au modèle français fourni, file transactionnelle unique, envoi après commit, suivi fiche/journal/API et travail natif toutes les 15 minutes. Trois reprises à 1 h, 6 h et 24 h après un échec certain ; vérification manuelle obligatoire avant reprise d’un résultat incertain. Aucun rattrapage historique ni renvoi au réimport.
- Regroupement des produits par référence DoliStore normalisée, recherches sur les libellés historiques et actuels et signalement des rattachements contradictoires, sans altérer les lignes commerciales archivées.
- Interface traduite dans les cinq langues : liens et infobulles natifs, statuts et origines traduits, titre de journal unique, référence facture native, badges/filtres Multicompany, compteur de mails non lus et sélecteurs de bienvenue sans double échappement des accents. Tableaux, bordures, boutons de recherche, sélection des colonnes, totaux et pictogrammes suivent les composants et réglages natifs, sans espace réservé inutile ni pictogramme DoliStore doublonné.
- Filtres et pagination des listes et de l’Agenda corrigés : les valeurs vides, espacées ou laissées sur le choix vide natif sont ignorées, le statut 0 reste filtrable et les recherches par référence, produit, message et titre Agenda produisent des requêtes valides. Pagination et remise à zéro conservent les filtres indépendants des deux tableaux de commandes.
- Dans cette version, les administrateurs disposent de tous les droits fonctionnels du module : menus, pages, API, infobulles, documents, imports, exports et traitements planifiés. Les autres comptes conservent leurs droits granulaires ; les restrictions d’entité, de compte externe, d’activation et de CSRF restent appliquées. Les menus utilisent le sélecteur natif des utilisateurs internes et des expressions compatibles v20/v23. Validation native des champs, colonne standard ref_ext requise en v20, préfixes CRUD, conservation des réglages et verrous renforcés. Identifiant migré vers 450032 en préservant les attributions des deux anciennes plages ; ancienne notification quotidienne non implémentée signalée indisponible.
- Documents limités à l’entité propriétaire, migration conservatrice et rejouable du rangement et des index ECM, PDF avec en-têtes/pieds et textes longs. Après mise à jour : réactiver dans les entités concernées pour renouveler les menus sans réinitialiser les attributions, exécuter leur migration documentaire, vérifier les modèles sélectionnés et l’utilisateur des travaux planifiés. Les correctifs d’interface n’ajoutent aucune migration. Socle Dolibarr 20 / PHP 8.0 conservé ; validations réelles Multicompany et SMTP à effectuer sur une instance de test.

## Version 2.1.0 — 5 août 2026

- Ajout, par entité, d’une catégorie native configurable pour les nouveaux tiers créés depuis les commandes DoliStore, avec Select2, accès direct à la création d’une catégorie et avertissements non bloquants journalisés.

## Version 2.0.1 — 5 août 2026

- Correction de la traduction des libellés des événements Agenda natifs lors de la validation et de l'envoi des factures, y compris depuis les travaux planifiés.

## Version 2.0.0 — 14 juillet 2026

### Nouveau workflow DoliStore

- Les emails DoliStore sont désormais archivés dans un objet métier dédié `Commande DoliStore`, avec leurs lignes, montants, informations du client final et message source `.eml`.
- La V2 ne crée plus de commande client Dolibarr native à partir des achats reçus par email.
- Un tableau de bord, des listes filtrables, des fiches complètes, un journal d'import et une API REST permettent de consulter et d'exploiter les ventes archivées.

### Facturation mensuelle native

- Création d'une facture client Dolibarr mensuelle pour le tiers DoliStore configuré, à partir des commandes libérées et du seuil HT défini par entité.
- Détail des ventes conservé sur les lignes de facture, avec liaison entre la facture, son PDF natif, le lot mensuel et les commandes archivées.
- Gestion des factures brouillon ou validées, du taux de TVA natif de l'entité et de la réconciliation prudente des lots existants sans création de doublon.
- Envoi automatique optionnel de la facture avec un modèle de courriel Dolibarr `facture_send`, le PDF joint et un événement Agenda natif lié à la facture.

### Automatisation et intégrations Dolibarr

- Trois travaux planifiés natifs sont installés actifs : import IMAP, facturation mensuelle et notification quotidienne. Leur exécution métier reste pilotée par des switches indépendants dans les réglages de chaque entité.
- Les commandes DoliStore exposent leurs événements CRUD aux modules Agenda et Notifications, sans système d'événements parallèle.
- Les modèles de courriels français et anglais sont initialisés dans la catégorie native `Commandes Dolistore` sans écraser les personnalisations existantes.
- Les modèles de documents, la numérotation, les fichiers joints, les droits granulaires et les listes utilisent les composants natifs Dolibarr.

### Multicompany, compatibilité et identité

- Données, réglages, travaux planifiés, modèles de courriels et documents gérés par entité, avec partage Multicompany configurable et affichage de l'environnement d'origine.
- Compatibilité minimale portée à Dolibarr 20 et PHP 8.0, avec un onglet dédié présentant les fonctionnalités disponibles dans l'environnement courant.
- Module publié sous l'identité `Les Métiers du Bâtiment` avec le pictogramme officiel DoliStore.
- Les données historiques et les réglages existants sont conservés lors de l'activation, de la désactivation et de la réactivation du module.

## Release 1.6

- FIX : Missing email in company and contact — 1.6.4 — *18/12/2025*
- FIX : Change `addWebmoduleSales` item price to total item price — 1.6.3 — *17/12/2025*
- FIX : Add more details to import error messages — 1.6.2 — *22/08/2025*
- CHANGED : Customer email is stored only on the contact and postal address only on the third party — 1.6.1 — *01/08/2025*
- NEW : Redesign of the DoliStore sales tracking workflow — 1.6.0 — *22/07/2025*

## Release 1.5

- NEW : Dolibarr 20 and 21 compatibility and support for the updated email structure — 1.5.0 — *25/02/2025*

## Release 1.4

- FIX : Prevent duplicate processing of the same order email — 1.4.2 — *30/12/2024*
- FIX : Select the action user during web sales initialization and improve purchase-date errors — 1.4.1 — *25/09/2024*
- NEW : Add DoliStore module sales tracking — 1.4.0 — *17/09/2024*

## Release 1.3

- FIX : PHP 8.2 warnings — 1.3.1 — *25/03/2024*
- NEW : Dolibarr 19 and PHP 8 compatibility — 1.3.0 — *07/03/2024*

## Release 1.2

- FIX : Update the customer provenance field — 1.2.11 — *06/07/2023*
- FIX : Improve scheduled-task error handling — 1.2.10 — *28/03/2023*
- FIX : Correct contact import — 1.2.9 — *24/06/2022*
- FIX : Reuse the oldest matching third party when several contacts match — 1.2.8 — *06/10/2021*
- FIX : Handle multiple third parties sharing the same imported company name — 1.2.7 — *10/05/2021*
