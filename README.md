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

La facturation utilise l'objet natif `Facture`. Une facture mensuelle maximum est générée par entité pour le tiers DoliStore configuré, selon le seuil HT et les commandes libérées. Le taux de TVA est initialisé, lors de la première configuration de chaque entité, avec son taux de vente par défaut puis reste un réglage autonome sélectionné dans le dictionnaire TVA natif. Les lignes restent détaillées par ligne DoliStore pour préserver la traçabilité. Le lot n'est déclaré réussi qu'après création de la facture, liaison des commandes et génération vérifiée du PDF natif. Une nouvelle tentative réutilise la facture déjà liée et ne crée pas de doublon.

## Limites

La notification quotidienne est déclarée mais désactivée tant que son contenu métier n'est pas configuré. Les anciens emails ou commandes clientes déjà créés par les versions historiques ne sont pas supprimés automatiquement.
