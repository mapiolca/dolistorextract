# DolistoreExtract

Version **2.2.0** — module externe `dolistorextract` — Dolibarr **20+**, PHP **8.0+**, MySQL/MariaDB.

Le module archive les achats reçus par email DoliStore, envoie un message de bienvenue à chaque nouvel achat importé et prépare la facturation mensuelle avec l’objet natif `Facture`. Il fournit un tableau de bord, des commandes avec lignes et documents, des journaux et une API REST. L’interface et les modèles de bienvenue sont disponibles en français, anglais, espagnol, italien et allemand.

## Installation et mise à jour

1. Installer le répertoire `dolistorextract` dans une racine de modules externes configurée dans Dolibarr, puis activer le module.
2. Depuis sa roue de configuration unique, régler l’IMAP, le tiers DoliStore, les montants/TVA/commission, le délai de libération et les modèles de courriels. Vérifier l’onglet **Compatibilité**.
3. Attribuer les droits nécessaires à l’utilisateur chargé des imports et des travaux planifiés, y compris les droits natifs sur les tiers/contacts/produits et factures lorsque ces traitements les utilisent.
4. Pour une mise à jour depuis 2.1 ou une version antérieure, réactiver le module dans chaque entité concernée pour installer les nouvelles déclarations. Les valeurs existantes, y compris `0` et chaîne vide, les modèles personnalisés et les réglages des travaux planifiés sont conservés.
5. Dans les réglages, exécuter la migration des documents pour chaque entité possédant des archives dans l’ancien rangement. Les collisions sont refusées sans écrasement. La migration est rejouable, y compris après une interruption entre déplacement, indexation ECM et mise à jour de `last_main_doc`.

L’identifiant du module devient **450032**, dans la famille **Les Métiers du Bâtiment**. La migration conserve les attributions utilisateur/groupe des anciennes plages `104977–104984` et `10497601–10497608` vers `45003201–45003208`, sans modifier leur sens. Une collision avec un autre module bloque la migration des droits. La colonne native nullable `ref_ext` est ajoutée pour le validateur de relations Dolibarr 20 ; elle reste vide et ne remplace pas la référence DoliStore.

## Bienvenue après achat

Un nouvel achat importé intégralement produit une demande unique en file dans la transaction de l’archive. Le transport ne démarre qu’après validation de cette transaction. Un client déjà connu reçoit également son message pour ce nouvel achat. Un réimport d’une commande déjà archivée n’envoie rien ; la mise à jour ne crée aucune demande pour les commandes historiques. La création d’une coquille de commande par API ne constitue pas un achat importé.

La constante existante `DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU` reste prioritaire. Lorsqu’elle désactive l’envoi, les nouveaux achats ne sont pas mis en file et les demandes existantes restent suspendues. Leur traitement reprend lorsque l’envoi est réactivé.

Les modèles natifs ont le type exact `dolistore_extract`. La langue détectée choisit l’une des constantes par entité :

| Langue | Sélection |
|---|---|
| Français | `DOLISTOREXTRACT_EMAIL_TEMPLATE_FR` |
| Anglais | `DOLISTOREXTRACT_EMAIL_TEMPLATE_EN` |
| Espagnol | `DOLISTOREXTRACT_EMAIL_TEMPLATE_ES` |
| Italien | `DOLISTOREXTRACT_EMAIL_TEMPLATE_IT` |
| Allemand | `DOLISTOREXTRACT_EMAIL_TEMPLATE_DE` |

Une langue inconnue utilise l’anglais. Une sélection absente, supprimée, inactive, privée, de mauvaise langue ou de mauvais type produit une erreur explicite. Les modèles livrés utilisent le HTML français fourni en conservant sa structure, son logo, ses coordonnées et tous ses paragraphes ; les marqueurs `PRODUCTS_START/END` restent des commentaires et ne répètent pas le message. Les substitutions natives et historiques sont disponibles et les données textuelles sont échappées avant insertion HTML.

L’initialisation crée les modèles manquants par entité. Elle ne remplace un ancien contenu livré que si son sujet et son contenu sont reconnus comme inchangés. Un modèle personnalisé conserve son contenu et sa sélection ; le nouveau modèle apparaît séparément dans les choix natifs, avec le suffixe `(2.2)` si nécessaire.

### Transport, suivi et reprises

Le module utilise `FormMail`, `Translate`, les substitutions et `CMailFile`, avec le transport SMTP Dolibarr, l’expéditeur du modèle ou l’expéditeur Dolibarr configuré, et l’adresse de l’acheteur de l’archive.

Il s’agit d’une exception documentée à Notifications : ses abonnements fixes ne représentent pas le destinataire dynamique de chaque achat ni la file de reprise avec résultat SMTP incertain. Le trigger CRUD ne fait que mettre la demande en file ; aucun rendu de page n’envoie spontanément un message. Les notifications CRUD natives restent configurables séparément pour d’autres destinataires ; éviter d’y configurer une seconde bienvenue pour les mêmes achats.

| État | Traitement |
|---|---|
| En attente | Demande prête pour le prochain traitement autorisé. |
| En cours | Réservée par un seul processus ; les autres processus l’ignorent. |
| Envoyé | Aucun renvoi automatique. |
| Échec certain | Échec avant le transport : trois reprises prévues à 1 h, 6 h et 24 h après le premier échec. |
| Résultat incertain | Transport commencé sans confirmation fiable, ou traitement interrompu depuis 15 minutes : aucune reprise automatique. |

Un retour négatif du transport est traité prudemment comme incertain : il ne prouve pas que le destinataire n’a rien reçu. La fiche affiche le suivi et permet une reprise avec le droit **Importer**, token CSRF et confirmation. Pour un résultat incertain, cette confirmation atteste la vérification de non-envoi. Le journal conserve les transitions ; `GET /orders/{id}` expose `welcome_delivery` sans destinataire, contenu ou secret de verrouillage.

## Travaux planifiés natifs

| Méthode | Fréquence initiale | Activation métier |
|---|---|---|
| `runImport` | Chaque heure | Réglage d’import automatique |
| `runInvoice` | Chaque jour | Réglage de facturation automatique |
| `runWelcome` | Toutes les 15 minutes | Bienvenue non désactivée |

Les travaux se gèrent dans **Travaux planifiés** : fréquence, état et utilisateur d’exécution restent administrables. Les deux premiers automatismes métier sont désactivés par défaut. La bienvenue est traitée après import, puis par `runWelcome` pour les demandes restantes et les reprises. Chaque passage traite au plus 50 demandes de l’entité courante. Les imports et facturations utilisent un verrou SQL ; la file réserve chaque demande par une mise à jour conditionnelle.

L’ancienne notification quotidienne sans traitement implémenté n’est plus proposée comme fonctionnalité utilisable ni installée comme nouveau travail. Son ancienne configuration est conservée ; un ancien travail encore présent indique son indisponibilité et peut être désactivé dans l’écran natif.

## Produits et historique

L’identité de regroupement est la référence DoliStore, après suppression des espaces usuels/insécables et normalisation de la casse. Elle est indépendante du libellé traduit et des anciens rattachements à plusieurs produits Dolibarr. Sans référence DoliStore, le produit lié sert d’identité ; sans identifiant fiable, chaque ligne reste distincte.

Les graphiques et regroupements additionnent quantités et montants sans modifier les lignes commerciales, prix ou taxes archivés. Ils affichent le libellé actuel du produit associé à la dernière liaison accessible ; à défaut, le dernier libellé DoliStore connu accompagné de sa référence. Les recherches incluent les libellés historiques et actuels. Les liaisons contradictoires sont signalées, jamais fusionnées de façon destructive.

## Droits, Multicompany et documents

Les administrateurs disposent de tous les droits fonctionnels du module, même sans attribution individuelle. Les contrôles utilisent directement le rôle administrateur ou `hasRight()` ; les autres utilisateurs restent soumis aux droits distincts de lecture, import, modification, suppression, génération de facture, configuration, API et export. Cette règle explicite du projet s’applique aussi aux menus, infobulles, documents et traitements planifiés. Les modules dépendants doivent être actifs ; les utilisateurs externes restent refusés et les restrictions d’entité, validations et tokens CSRF s’appliquent à tous. Aucun droit stocké ni contrôle du core n’est réécrit. Après déploiement de cette correction, réactiver le module dans les entités concernées pour actualiser les conditions des menus natifs.

Les commandes utilisent le partage `dolistoreextract_order`, déclaré dans les options Multicompany avec sa numérotation. Les filtres SQL limitent les objets et les relations accessibles. Les colonnes Environnement utilisent les badges avec globe et classes natives, ainsi qu’un filtre multiselect2 alimenté par les mêmes libellés d’entité.

Chaque archive utilise exclusivement `multidir_output[entity propriétaire]/REF`. Un répertoire absent ou un retour d’erreur de `getMultidirOutput()` bloque l’opération, sans repli sur l’entité de consultation. Les liens anciens avec le sous-répertoire `dolistoreextract_order/` sont résolus vers le nouveau rangement après migration. Le droit de lecture permet les aperçus et téléchargements ; génération/upload/modification et suppression restent soumis à leurs droits propres.

Les fiches conservent les blocs natifs Fichiers joints, Objets liés et derniers événements, ainsi que les onglets Notes, Fichiers joints et Événements/Agenda dans cet ordre. Les préfixes CRUD et la résolution native de l’objet servent aux infobulles Ajax, à l’Agenda et aux notifications. Le PDF standard respecte la langue de sortie, les montants natifs, l’entité émettrice et une zone de pied mesurée avant contenu, avec découpage des textes longs.

## Facturation et catégories

La facturation crée au plus une facture mensuelle par entité pour le tiers DoliStore configuré, à partir des commandes libérées et du seuil HT. Elle conserve le détail des lignes, utilise les arrondis natifs `MU`/`MT`, contrôle les droits natifs et réutilise une facture déjà liée lors d’une reprise. L’envoi optionnel utilise un modèle `facture_send` compatible et le PDF vérifié ; le trigger core `BILL_SENTBYMAIL` conserve l’intégration Agenda native.

Le réglage `DOLISTOREXTRACT_THIRDPARTY_CATEGORY_ID` choisit une catégorie native pour les nouveaux tiers uniquement (`0` désactive ce classement). Un échec de catégorisation produit un avertissement journalisé sans annuler l’achat importé.

## Vérifications et limites

Les commandes de test, la matrice de conformité et les validations à effectuer sur une instance configurée sont décrites dans [le bilan 2.2.0](doc/implementation-2.2.0.md). Les essais automatisés utilisent des données synthétiques, SQLite pour adapter le SQL de test et aucun destinataire réel. SQLite n’est pas une base supportée en production.

Les textes métier saisis et les anciens messages déjà persistés ne sont pas retraduits automatiquement. La validation sur Dolibarr 23.0.4/Multicompany, le SMTP de test et les clients de messagerie restent nécessaires après déploiement du code ; un aperçu HTML dans un navigateur ne prouve pas le rendu de tous les clients email.
