# Plan de tests manuels — Refonte DolistoreExtract (Services + Commandes client natives)

Ce plan couvre le flux réel du module `dolistorextract` après migration Webhost -> Services Dolibarr + Commandes client.

## 1) Pré-requis généraux

- Module `dolistorextract` activé.
- Droits utilisateur test:
	- lecture module `dolistorextract`,
	- création commande client (`commande->creer`) pour les tests d’import natif,
	- création produit/service (`produit->creer`) pour les tests de création manuelle.
- Une boîte IMAP de test disponible avec mails Dolistore (ou mails de fixture comparables).
- Au moins une société/client test existante (ou possibilité de création via import).
- Extrafield service `iddolistore` disponible (utilisé pour mapping exact).
- Extrafield ligne de commande `dolistore_item_ref` présent (créé à l’init module).
- Vérifier les options admin:
	- `DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER` (on/off),
	- `DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR` (`block`, `skip`, `manual`).

---

## 2) Jeu de données minimal recommandé

Préparer au moins ces références Dolistore dans les mails:

- `DS-MAP-001`: item avec mapping exact via `product_extrafields.iddolistore`.
- `DS-REF-002`: item sans `iddolistore`, mais avec service existant `ref = DS-REF-002`.
- `DS-NOMAP-003`: item sans mapping exact.
- `DS-CAND-004`: item sans mapping exact mais avec services candidats (label/ref proches).
- `DS-REFUND-005`: item remboursé (attendu en ligne commande avec PU négatif).
- Une commande réutilisant une `order_ref` déjà importée (test doublon `ref_client`).

---

## 3) Cas de test détaillés

## TC01 — Mail DoliStore valide (flux nominal)

### Étapes
1. Ouvrir `mails.php`, sélectionner un mail Dolistore valide avec au moins un item mappé.
2. Lancer l’action d’import natif (`action=importnative`) depuis l’interface.

### Résultat attendu
- L’extraction est réalisée sans erreur bloquante.
- Le client/tiers est créé ou retrouvé.
- Une commande client est créée (ou retrouvée si déjà importée).
- Les logs affichent le traitement de commande et l’état final.

---

## TC02 — Service mappé via `iddolistore`

### Étapes
1. Créer/éditer un service Dolibarr avec `options_iddolistore = DS-MAP-001`.
2. Importer un mail contenant cet item.

### Résultat attendu
- Le service est trouvé via `iddolistore` sans fallback.
- La ligne commande est créée sur ce service.
- L’extrafield de ligne `dolistore_item_ref` contient la référence Dolistore.

---

## TC03 — Fallback de mapping par `ref`

### Étapes
1. S’assurer qu’aucun service n’a `iddolistore = DS-REF-002`.
2. Créer un service avec `ref = DS-REF-002`.
3. Importer un mail contenant `DS-REF-002`.

### Résultat attendu
- Le mapping via `iddolistore` échoue.
- Le fallback par `ref` est utilisé.
- La ligne commande est créée sur le service trouvé.

---

## TC04 — Service non mappé (option admin = `block`)

### Étapes
1. Régler `DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR = block`.
2. Importer un mail contenant au moins un item `DS-NOMAP-003`.

### Résultat attendu
- L’import de la commande est bloqué au premier item non mappé.
- Aucun import partiel de commande native n’est validé.
- Log explicite de blocage présent.

---

## TC05 — Service non mappé (option admin = `skip`)

### Étapes
1. Régler `DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR = skip`.
2. Importer un mail avec mix: items mappés + item non mappé.

### Résultat attendu
- Les lignes mappées sont importées.
- Les lignes non mappées sont ignorées.
- Logs d’avertissement indiquent les lignes ignorées.
- Message de fin cohérent “import avec lignes ignorées” si applicable.

---

## TC06 — Service non mappé (option admin = `manual`)

### Étapes
1. Régler `DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR = manual`.
2. Importer un mail contenant un item non mappé.

### Résultat attendu
- Pas de création automatique de service/lien.
- La commande est marquée pour traitement manuel.
- Les propositions de mapping restent visibles dans l’interface `mails.php`.
- Log explicite “pending manual mapping”.

---

## TC07 — Proposition de candidats

### Étapes
1. Préparer un item `DS-CAND-004` sans mapping exact.
2. Créer des services dont `ref/label/description` sont proches.
3. Ouvrir le mail dans `mails.php`.

### Résultat attendu
- La section “Services candidats” apparaît.
- Les candidats sont classés et affichés.
- Aucune association automatique n’est effectuée.

---

## TC08 — Création manuelle d’un service

### Étapes
1. Depuis `mails.php`, sur un item non mappé, utiliser l’action “Créer le service”.
2. Soumettre une référence proposée.

### Résultat attendu
- Si droits `produit->creer` présents: service créé.
- Extrafield `iddolistore` renseigné sur le service créé.
- Message succès visible et mapping actif pour import ultérieur.
- Si droits absents: action refusée avec message d’erreur.

---

## TC09 — Association manuelle à un service existant

### Étapes
1. Depuis `mails.php`, choisir “Associer à un service existant”.
2. Sélectionner un service cible valide.

### Résultat attendu
- Si service cible valide: `options_iddolistore` est mis à jour.
- Les conflits sont correctement gérés:
	- service déjà lié à une autre référence,
	- référence déjà liée à un autre service.
- En cas de conflit, aucun écrasement silencieux.

---

## TC10 — Doublon commande via `ref_client`

### Étapes
1. Importer une commande Dolistore (order_ref X) une première fois.
2. Relancer l’import de la même order_ref X.

### Résultat attendu
- Détection du doublon via `commande.ref_client`.
- Pas de nouvelle commande créée.
- Log “already imported / skipped” affiché.

---

## TC11 — Remboursement (ligne à PU négatif)

### Étapes
1. Importer un mail/item marqué remboursé (`item_refunded` truthy).

### Résultat attendu
- La ligne commande est créée avec PU HT négatif.
- La description de ligne contient l’indication de remboursement.
- L’extrafield `dolistore_item_ref` reste renseigné.

---

## TC12 — Import manuel depuis `mails.php`

### Étapes
1. Ouvrir un mail Dolistore dans `mails.php`.
2. Cliquer “Importer en commande native”.

### Résultat attendu
- L’action déclenche `launchImportProcess` sur le mail sélectionné.
- Le journal natif d’import est affiché dans l’UI.
- Le résultat suit strictement les options admin configurées.

---

## TC13 — Import via cron

### Étapes
1. Configurer et exécuter la tâche cron du module (`dolistorextractCron::runImport`).
2. Vérifier un lot de mails mélangés (mappés / non mappés / doublons).

### Résultat attendu
- Les mêmes règles métier que l’import manuel sont appliquées.
- Le rapport cron contient les succès, warnings, erreurs.
- Les mails sont déplacés selon config IMAP (archive/erreur) si configurée.

---

## TC14 — Comportement option admin (matrice synthèse)

Pour un même mail contenant 1 item mappé + 1 non mappé:

- `block`:
	- attendu: pas d’import commande native, erreur de blocage.
- `skip`:
	- attendu: import partiel sur item mappé, warning sur item ignoré.
- `manual` (recommandé):
	- attendu: pas d’import natif automatique, item à traiter manuellement dans l’UI.

Tester aussi `DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER`:

- OFF: commande créée en brouillon.
- ON: commande validée automatiquement après création des lignes.

---

## 4) Checklist de validation finale

- [ ] Aucun service n’est créé automatiquement hors action manuelle explicite.
- [ ] Aucune association de mapping n’est faite automatiquement hors action manuelle explicite.
- [ ] Les permissions utilisateur bloquent correctement les actions non autorisées.
- [ ] Les logs UI + syslog sont cohérents avec le scénario.
- [ ] Les doublons de commande sont évités.
- [ ] Les remboursements créent bien une ligne négative.
- [ ] Le comportement cron est aligné avec l’import manuel.

