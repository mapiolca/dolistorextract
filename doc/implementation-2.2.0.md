# Bilan de réalisation 2.2.0

Date : 2026-09-09. Racine : module externe `dolistorextract` uniquement.
Base initiale propre : `d31ae734401c2c09b1e4051038ba41db814aaa9c`.
Branche de travail : `codex/dolistorextract-2-2-0`. Aucun commit, push, déploiement ou envoi email réel effectué.

## Modifications et mécanismes natifs

| Domaine | Réalisation | Preuve / limite |
|---|---|---|
| Bienvenue | Demande unique par commande dans le trigger CREATE après les lignes et le message source ; transport après commit ; états pending/sending/sent/failed/uncertain | Tests de rollback, réimport, absence de rattrapage historique, concurrence, désactivation et reprises avec transport simulé |
| Courriels | Cinq modèles natifs `dolistore_extract`, `FormMail`, `Translate`, substitutions et `CMailFile` ; sélection par langue, anglais si inconnue | Préparation réelle des cinq messages avec bibliothèques natives ; aucun appel SMTP réel |
| Personnalisation | Ancien modèle livré reconnu strictement avant actualisation ; modèles/sélections personnalisés, zéro et vide conservés | Tests d’initialisation, réactivation simulée, modèle personnalisé et deuxième entité |
| Produits | Référence DoliStore normalisée prioritaire ; libellé natif courant ou dernier instantané ; anciennes liaisons contradictoires signalées | Tests SQL des quantités, totaux, recherches historiques, références distinctes et entités |
| Liens et listes | `getNomUrl(1)`, petit picto, infobulle Ajax native et résolution de l’objet ; `InvoiceRef`, statuts/origines traduits, titre de journal unique | Tests du lien et du hook d’accès ; revue des sources, pas de parcours d’instance déployée |
| Multicompany | Partage déclaré, filtres SQL d’entité, badges globe/classes natives et multiselect2 avec source commune | Sources et entités synthétiques ; module tiers Multicompany non installé localement |
| Droits | Appels directs à `hasRight()` ; refus des externes, de l’administrateur sans droit et des entités non autorisées | Tests de file, infobulles et hook ; comptes ERP réels restant à tester |
| Objets | Métadonnées/validation CommonObject, propriétés, relations, transactions et préfixes CRUD | Création de commande, création de ligne, montants, parent absent et référence trop longue testés avec les classes natives |
| Documents | Répertoire exclusivement propriétaire, retour natif absent/erroné refusé ; déplacement sans écrasement ; réconciliation ECM et `last_main_doc` rejouable | Tests fichiers réels temporaires + objets ECM natifs sur base synthétique : collision, déplacement, rejeu, interruption avant indexation |
| PDF | Pied mesuré et réservé avant contenu, pieds intermédiaires/final, découpage de lignes/notes longues, langue et montants natifs, logo de l’émetteur lorsque configuré | Deux PDF de 5 pages, texte intégral extrait et contrôle visuel ; variantes avec modules de pied tiers non testées |
| Agenda / Notifications | Codes CRUD et contexte, déclarations natives, résolution de l’objet ; liste Agenda paginée et filtre du bloc natif sur les droits de l’utilisateur | Lecture des contrats core ; automatisations Agenda/Notifications réelles non exécutées |
| Installation | ID 450032, migration de deux plages de droits, constantes et réglages conservés, nouvelle file et travail de reprise | Migrations de droits/modèles/colonne native rejouées sur SQL adapté ; cycle complet d’activation ERP restant à tester |
| Traductions | 427 clés dans chacun des catalogues FR/EN/ES/IT/DE, messages, permissions, statuts, interfaces et modèles | Parité, doublons, valeurs vides, formats, clés littérales/dynamiques connues, catalogues chargés par page et structure des cinq HTML contrôlés |
| Métadonnées | Descripteur, À propos issu du descripteur, Compatibilité, README et ChangeLog alignés 2.2.0 ; AGENT.md remplacé par AGENTS.md | Revue du diff ; `modulebuilder.txt` présent |

Les messages historiques déjà persistés et les données métier saisies conservent leur contenu original. Une analyse statique des traductions n’est pas une preuve de tous les états de chaque page en navigateur.

## Migrations livrées

- Nouvelle table `dolistoreextract_welcome` et clé unique `(entity, fk_order)` ; index de sélection des demandes dues. Aucun peuplement à partir des commandes historiques.
- Droits : `104977–104984` et `10497601–10497608` vers `45003201–45003208`, conservation des utilisateurs/groupes par entité et refus des collisions.
- Colonne standard nullable `dolistoreextract_order.ref_ext` : requise par le validateur FK de `CommonObject` en Dolibarr 20, qui la sélectionne même lors d’une recherche par identifiant. Elle reste réservée et vide, sans recopier `dolistore_order_ref`. Ajout avec `DDLDescTable` / `DDLAddField` avant la transaction DML ; tests d’ajout et de rejeu.
- Modèles de bienvenue et constantes ES/IT/DE ajoutés, FR/EN conservés ; sujets/contenus personnalisés non remplacés. Un modèle configuré devenu incompatible reste à corriger par l’administrateur.
- Migration documentaire dans les réglages, par entité : ancien `base/dolistoreextract_order/REF` vers `base/REF`. Préflight des collisions avant déplacement, pas d’écrasement, conservation des métadonnées ECM existantes et réparation après interruption. Un conflit est signalé ; aucun choix automatique entre fichiers concurrents.
- Réglages Agenda/Notifications/Multicompany et travaux existants conservés. Nouvelle déclaration `runWelcome` toutes les 15 minutes. Ancienne notification quotidienne non implémentée rendue indisponible ; aucune purge de sa configuration.

Les données historiques de ventes, prix, taxes, clients et factures ne sont pas fusionnées ou supprimées par ces migrations.

## Exception Notifications

Le destinataire est l’acheteur de chaque archive, distinct des abonnements et destinataires fixes du module Notifications. La file spécifique conserve en outre les résultats SMTP incertains et les délais de reprise. Elle réutilise les modèles et le transport natifs, reste déclenchée par un événement CRUD et n’envoie qu’après commit. Le résultat incertain requiert une vérification explicite de non-envoi avant reprise ; un simple échec de `sendfile()` ne constitue pas cette preuve.

## Contrôles exécutés

Depuis la racine du module, avec `DOLIBARR_TEST_ROOT` pointant sur les bibliothèques Dolibarr :

```sh
DOLIBARR_TEST_ROOT=/chemin/vers/dolibarr/htdocs php test/migrations.php
DOLIBARR_TEST_ROOT=/chemin/vers/dolibarr/htdocs DOLIBARR_TEST_VERSION=20.0.0 php test/native.php
DOLIBARR_TEST_ROOT=/chemin/vers/dolibarr/htdocs python3 test/check_translations.py
```

`DOLIBARR_TEST_VERSION` doit correspondre au code réellement utilisé ; ce paramètre ne sélectionne pas une version à lui seul. Les sorties synthétiques sont écrites dans `test/.tmp/`, exclu de Git et de la livraison.

Résultats :

- `test/migrations.php` : **45 assertions métier + 28 assertions produits/migrations**. Il inclut `business.php` ; ne pas compter ces 45 assertions une seconde fois.
- `test/native.php` : **54 assertions** passantes avec les bibliothèques du tag **20.0.0**, puis avec le checkout local **25.0.0-alpha**, sous PHP **8.5.7**. Préparation FormMail, HTML, traductions, PDF, validation d’objets, montants, migration documentaire/ECM et accès aux infobulles. Les deux exécutions reprennent les mêmes scénarios ; **127 assertions distinctes** au total avec la suite précédente.
- Dolibarr 20 a révélé et permis de corriger le besoin de `ref_ext`, la propagation du PDF par référence à travers le pied natif et la clé `Email`. Ses bibliothèques anciennes émettent de nombreux avis de dépréciation sous PHP 8.5 ; aucune modification du core pour les masquer. Le checkout 25 émet également un avis `get_class()` dans `CommonObject::isExistingObject()`.
- Contrôle des traductions : **427 × 5 clés**, sans doublon, valeur vide, incohérence de formats ou clé littérale non résolue ; contrôle effectué avec les catalogues natifs 20 et 25.
- PHP lint : **71 fichiers**, zéro erreur (sources du module, bibliothèque embarquée et tests, hors sorties temporaires).
- `git diff --check` : aucun défaut d’espacement signalé.
- Deux PDF synthétiques : pieds vides/longs, 45 lignes à libellés longs, longue note publique, accents FR/ES/IT/DE ; contrôle avec `pdfinfo`, `pdftotext` et aperçu. Marqueur de fin de note et pied final présents, 5 pages.
- Modèles HTML : DOM des cinq langues aux largeurs **390 px** et **1280 px**, UTF-8, 20 paragraphes et 4 sous-titres conservés, aucun débordement horizontal ; aperçus visuels français, anglais, espagnol, italien et allemand. Logo externe chargé ; liens et coordonnées identiques au modèle français.
- PHPStan : **non exécuté**, aucun binaire ni configuration de projet disponible. Aucun ignore, baseline ou dépendance ajouté pour contourner l’analyse.

## Contrats natifs consultés

Sources consultées dans le dépôt Dolibarr local, sans modification de ses fichiers :

| Version | Commit | Contrats vérifiés |
|---|---|---|
| 20.0.0 | `697bf01970740a3339cd99cf055b4428fc5e051c` | `User::hasRight`, validation CommonObject/Validate, DDLDescTable/DDLAddField, FormMail, substitutions, getMultidirOutput, ECM, getNomUrl/tooltip, restrictedArea/checkSecureAccess, FormFile::showdocuments, getActionsListWhere et pied PDF par référence |
| 23.0.4 | `cb82037066c1c71f7c867482e9e92975222dfc14` | Contrats de résolution d’objet, infobulles, droits et composants concernés comparés au socle |
| 24.0.1 | `b7958385f00a92219f76a4dfea67dae93df97a25` | Comparaison des points d’extension et signatures concernés ; aucune nouvelle dépendance à une fonction réservée à cette version |
| Checkout local 25alpha | Non utilisé comme socle minimal | Exécution des tests natifs, avec paramètres synthétiques et transport non appelé |

L’ID **450032** a été recontrôlé dans la plage officielle `450000–450999`, les modules locaux et les dépôts publics identifiés de `mapiolca` : aucune collision identifiée. Cela ne certifie pas l’absence d’un module privé extérieur aux sources accessibles ; la migration refuse une plage de droits déjà utilisée.

## Validation encore nécessaire sur instance configurée

Aucune instance locale configurée, base MySQL/MariaDB de test ou installation Multicompany n’était disponible. Le navigateur distant n’a pas servi le patch : **aucune validation distante revendiquée**, aucun déploiement effectué. Les tests SQLite adaptent le dialecte uniquement dans les doubles de test ; ils ne remplacent pas les verrous et transactions réels MySQL.

À exécuter sur une instance de test servant effectivement ce code :

1. Installation/réactivation dans deux entités, avec droits partiels et administrateur sans droit ; conservation des constantes, modèles, attributions et planifications ; migration documentaire avec objets partagés.
2. Parcours FR/EN/ES/IT/DE du tableau de bord, commandes, fiches/lignes, contacts, notes, documents, Agenda, journaux, factures, emails sources et administration : résultats, listes vides, erreurs, confirmations, filtres, tri, colonnes et changement de limite. Confirmer dans le DOM que `#limit` a un parent formulaire, sans champ caché concurrent.
3. Infobulles Ajax en URL directe, accès refusés aux externes/entités non accessibles, documents consultables avec lecture seule et mutations refusées sans les droits nécessaires ; soumissions avec/sans token CSRF.
4. Achats neufs, client existant, réimport, rollback, exécutions simultanées réelles et langue inconnue avec serveur SMTP de capture ; confirmer absence de destinataires réels, reprises à 1/6/24 h et arrêt sur résultat incertain.
5. Modèles personnalisés/supprimés/incompatibles, désactivation configurée et maintien des sélections à zéro/vide ; rendu dans les clients de messagerie utilisés.
6. PDF avec logo réel, sociétés/détails de pied variés, HTML de pied, hooks tiers et génération depuis une entité de consultation différente ; téléversement classique et glisser-déposer natif, aperçu, suppression et liens anciens.
7. Agenda/Notifications natives configurables et sans doublon ; API REST authentifiée avec droits partiels et périmètre d’entité ; facturation mensuelle et reprise du PDF dans les deux entités.
8. Exécution avec **PHP 8.0** et l’outillage PHPStan du parc : PHP 8.0 n’est pas installé dans cet environnement.

Les liens publics, consentements juridiques, signatures et traitement d’images utilisateur EXIF ne sont pas introduits par cette version. Aucun test métier de ces domaines non concernés n’est revendiqué.

## Livraison et proposition de commit

Le diff local comprend les modifications fonctionnelles, les cinq modèles, les nouvelles classes/SQL, les migrations, tests et documentation. Les dépendances temporaires de tests ne font pas partie de la livraison.

Titre proposé : **Rétablir la bienvenue DoliStore et aligner le module 2.2.0 sur Dolibarr natif**.

Description proposée : restaurer la bienvenue transactionnelle en cinq langues et ses reprises, unifier les produits par référence DoliStore, corriger les listes/liens/infobulles et les traductions, renforcer droits/entités/documents/Agenda et préserver les configurations lors des migrations 450032/2.2.0. Validation : lint, 127 assertions distinctes, catalogues/HTML et bibliothèques natives 20/25. Validation ERP/Multicompany/SMTP et PHPStan restant à effectuer sur l’instance de test.
