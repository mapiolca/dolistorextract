# DolistoreExtract

DolistoreExtract est un module externe Dolibarr installé dans `htdocs/custom/dolistorextract`.

## Rôle

Le module archive les emails de commande DoliStore dans un objet métier dédié, `Commande DoliStore`, avec lignes, montants, client final, tiers/contact liés et journal d'import. La V2 ne crée plus de commandes clientes Dolibarr natives à partir des emails.

## Installation

1. Copier le dossier dans `htdocs/custom/dolistorextract`.
2. Activer le module depuis l'administration Dolibarr.
3. Configurer l'IMAP, le tiers DoliStore, le seuil de facturation, la commission, le délai de libération et les options d'envoi.
4. Vérifier l'onglet `Compatibilité` dans la configuration du module.

## Travaux planifiés

Le module déclare trois tâches natives Dolibarr :

- `DolistoreCronImportLabel` : import IMAP des commandes DoliStore.
- `DolistoreCronInvoiceLabel` : génération mensuelle de facture DoliStore.
- `DolistoreCronDailyNotificationLabel` : notification quotidienne optionnelle.

Les trois travaux sont activés dans le module Dolibarr `Travaux planifiés` dès l'activation ou la réactivation de DolistoreExtract. Leur fréquence, leur priorité et leur prochaine exécution restent administrables dans l'interface native.

L'exécution métier est pilotée séparément, par entité, avec les switches `Import IMAP automatique`, `Génération automatique de facture` et `Notification quotidienne`. Ces trois options sont désactivées par défaut. L'envoi automatique de la facture reste une option secondaire de la facturation et ne crée pas un quatrième travail planifié. Les actions manuelles du module et l'API forcée restent utilisables lorsque les automatisations sont désactivées.

## Droits

Les droits sont granulaires : lecture, import, modification, suppression, génération de facture, configuration, API et export. Les administrateurs Dolibarr disposent de l'accès fonctionnel complet via le helper de droits du module, tout en conservant les contrôles d'entité, de token et de documents.

## Multicompany

Les commandes DoliStore sont filtrées avec `getEntity('dolistoreextract_order')`. Les listes affichent l'environnement lorsque plusieurs entités peuvent être visibles. Les documents utilisent le répertoire de l'entité propriétaire de l'objet via le calcul documentaire natif.

## Facturation

La facturation utilise l'objet natif `Facture`. Une facture mensuelle maximum est générée par entité pour le tiers DoliStore configuré, selon le seuil HT et les commandes libérées. Le taux de TVA est initialisé, lors de la première configuration de chaque entité, avec son taux de vente par défaut puis reste un réglage autonome sélectionné dans le dictionnaire TVA natif. Lors de l'activation, le module crée par entité un modèle public et actif `facture_send` intitulé `DoliStore Extract - Facture des ventes DoliStore`, avec le PDF en pièce jointe, puis le présélectionne uniquement si aucun modèle n'a déjà été choisi. Le sélecteur natif reste vide lorsqu'aucun modèle public et actif n'est disponible, et l'envoi automatique refuse alors de se rabattre sur le modèle global des factures Dolibarr. Les lignes restent détaillées par ligne DoliStore pour préserver la traçabilité. Le lot n'est déclaré réussi qu'après création de la facture, liaison des commandes et génération vérifiée du PDF natif. Une nouvelle tentative réutilise la facture déjà liée et ne crée pas de doublon. Après un envoi réussi, la facture appelle le trigger core `BILL_SENTBYMAIL` avec les métadonnées natives du courriel ; le module Agenda crée alors l'événement `ActionComm` lié à la facture lorsque `MAIN_AGENDA_ACTIONAUTO_BILL_SENTBYMAIL` est activé dans sa configuration native.

## Identité du module

Le module est publié dans la famille `Les Métiers du Bâtiment`. Son pictogramme reprend le favicon officiel de DoliStore ; les variantes livrées couvrent les tailles natives utilisées par les listes, fiches et tuiles de modules Dolibarr.

## Modèles de courriels des commandes Dolistore

Le module expose la catégorie native de modèles de courriels `dolistore_extract` sous le libellé `Commandes Dolistore`. Lors de l'activation dans une entité, un modèle public français et un modèle public anglais sont créés s'ils n'existent pas encore, puis présélectionnés lorsque le réglage correspondant est vide. Une réactivation conserve les modèles personnalisés et les sélections de l'administrateur. Ces modèles restent disponibles dans l'administration native Dolibarr, mais la V2 n'envoie pas automatiquement de courriel de bienvenue au client final.

## Limites

La notification quotidienne est déclarée mais désactivée tant que son contenu métier n'est pas configuré. Les anciens emails ou commandes clientes déjà créés par les versions historiques ne sont pas supprimés automatiquement.
