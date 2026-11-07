# Accès administrateurs — correction du 2026-09-10

Les administrateurs disposent de tous les droits fonctionnels dans DolistoreExtract, conformément à la consigne explicite du projet. Cette règle remplace la décision initiale de refuser un administrateur lorsque `hasRight()` retourne faux. Les autres utilisateurs conservent leurs permissions granulaires.

## Diagnostic

Deux causes indépendantes pouvaient refuser un administrateur :

- `User::hasRight()` vérifie les droits attribués et n’accorde pas, à lui seul, tous les droits aux administrateurs dans les versions 20.0.0 et 23.0.4 consultées.
- L’évaluateur natif de Dolibarr 23.0.4 refuse `empty()` et l’accès à `$user->socid` dans une expression de menu. Remplacer seulement le contrôle de permission ne suffisait donc pas.

Les menus utilisent désormais `$user->admin || $user->hasRight(...)`, avec le champ natif `user => 0` pour les comptes internes. Les points d’entrée serveur conservent le refus des utilisateurs externes, y compris lorsqu’ils portent le rôle administrateur.

## Fichiers et mécanismes

- `AGENTS.md` : consigne administrateur mémorisée dans le projet ; aucun fichier global Codex modifié.
- `core/modules/modDolistorextract.class.php` : menus, export natif et déclaration de la propriété `license` affichée dans À propos.
- Pages métier, administration et `lib/dolistoreextract.lib.php` : même règle pour les accès, boutons, liens, notes, documents et Agenda.
- Classes métier, `actions_dolistorextract.class.php`, API, cron et modèle PDF : contrôles administrateur cohérents lors de l’exécution, y compris pour les opérations utilisées sur les objets natifs. Les modules dépendants doivent rester actifs.
- API : import explicite de `Luracast\Restler\RestException`, nécessaire pour rendre les refus sous forme d’erreurs API natives.
- `test/business.php`, `test/native.php`, `test/admin_access.php`, `test/menu_compatibility.php` : profils administrateur, sans droit, lecture seule, externe et entités inaccessibles.
- `README.md`, `ChangeLog.md`, bilan 2.2.0 et présent document : règle et procédure de mise à jour.

Les contrôles utilisent le rôle et `hasRight()` directement, sans wrapper. Les restrictions d’entité/partage, de disponibilité, de validation, de documents propriétaires, de transaction et de CSRF restent indépendantes du rôle. Les états d’envoi incertain continuent à exiger une vérification avant reprise. Aucun trigger, destinataire, abonnement, réglage Agenda/Notifications ou droit stocké n’est ajouté ou modifié par ce correctif.

## Vérifications

Commandes depuis la racine du module :

```sh
DOLIBARR_TEST_ROOT=/chemin/dolibarr/htdocs php test/migrations.php
DOLIBARR_TEST_ROOT=/chemin/dolibarr/htdocs php test/admin_access.php
DOLIBARR_TEST_ROOT=/chemin/dolibarr/htdocs python3 test/check_translations.py
DOLIBARR_TEST_ROOT=/chemin/sources/tag/htdocs DOLIBARR_TEST_VERSION=23.0.4 php test/menu_compatibility.php
```

Résultats sous PHP 8.5.7 :

- 50 assertions métier et 28 assertions produits/migrations passent avec un adaptateur SQLite et un transport simulé.
- 106 assertions avec les bibliothèques natives du checkout Dolibarr 25.0.0-alpha passent. Cette suite inclut `native.php`, qu’il ne faut pas compter une seconde fois. Elle vérifie notamment l’évaluation des menus, les infobulles, l’API, les créations/modifications d’objets et les limites des autres profils.
- 24 expressions de menus passent avec l’évaluateur réel du tag 20.0.0, puis avec celui du tag 23.0.4. Ces vérifications ciblées chargent `functions.lib.php` et `json.lib.php` de chaque tag dans un contexte synthétique ; ce ne sont pas des installations ERP complètes. Aucune protection de l’évaluateur n’a été modifiée.
- 427 clés dans chacune des cinq langues : contrôle des formats, doublons, valeurs vides, clés utilisées et structure des modèles de bienvenue réussi.
- Lint PHP : 73 fichiers contrôlés, zéro erreur. `git diff --check` ne signale aucun défaut d’espacement.
- PHPStan non exécuté : aucun exécutable disponible. Aucun ignore ni baseline ajouté. Les avis de dépréciation émis par CommonObject et Restler sous PHP 8.5 proviennent des bibliothèques natives et restent visibles.

Les contrôles métier et natifs totalisent 184 assertions ; les 24 évaluations ciblées sont répétées sur les deux tags et ne constituent pas 48 scénarios distincts.

Sources natives consultées en lecture seule : `htdocs/user/class/user.class.php`, `htdocs/core/lib/functions.lib.php`, `htdocs/exports/class/export.class.php`. Tags : 20.0.0 (`697bf01970740a3339cd99cf055b4428fc5e051c`) et 23.0.4 (`cb82037066c1c71f7c867482e9e92975222dfc14`).

## Déploiement et limites

Aucun déploiement, envoi réel, commit ou push effectué. Le navigateur distant n’a pas été validé ; rien ne prouve encore que l’instance sert ce code.

Après déploiement, réactiver le module dans les entités concernées pour actualiser les menus enregistrés en base. Aucun changement de schéma ni migration de droits n’est nécessaire pour cette correction ; l’identifiant 450032 et la version 2.2.0 restent inchangés. Vérifier avec un administrateur sans attribution individuelle, puis un compte en lecture seule. Confirmer les menus, la fiche, les documents, l’API et les restrictions Multicompany sur l’instance réelle. La configuration des autres modules Dolibarr et leurs contrôles core restent sous leur responsabilité.

## Proposition de commit

Titre : `Rétablit tous les droits DolistoreExtract pour les administrateurs`

Description : harmoniser les permissions du module pour les administrateurs et corriger les expressions de menus incompatibles avec Dolibarr 23 ; conserver droits granulaires, comptes internes, entités et CSRF ; mémoriser la règle du projet et couvrir menus, API, objets et bienvenue par les tests synthétiques. Lint et traductions vérifiés, évaluateurs natifs 20/23 testés ; PHPStan et validation sur l’instance distante non réalisés.
