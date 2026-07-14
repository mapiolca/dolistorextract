# Change log for DolistoreExtract

## Unreleased
- CHANGED : Refresh the module and DoliStore order pictograms from the official dolistore.com favicon.
- NEW : Create and preselect a per-entity native `facture_send` template for DoliStore invoices, with the generated PDF attached.
- FIX : Keep the invoice email Select2 empty when no public template is available and remove the implicit global-template fallback.
- NEW : Add per-entity native French and English email templates in the `Commandes Dolistore` category while keeping automatic customer welcome emails disabled.
- FIX : Replace the invoice email subject and body settings with native Dolibarr `facture_send` email templates.
- FIX : Keep the active configuration tab after saving a setting and prevent form resubmission on refresh.
- FIX : Initialize the DoliStore invoice VAT rate from the current entity and select active sales rates through the native VAT dictionary.
- FIX : Enable native scheduled jobs on module activation and gate their runtime actions with per-entity settings switches.
- FIX : Align the monthly invoice action with the native list toolbar and require a linked customer invoice plus a readable PDF before an invoice batch is successful.
- FIX : Reconcile orphan monthly invoice batches conservatively without creating duplicate customer invoices.
- FIX : Replace the legacy DoliStore invoice trigger with the CRUD `UPDATE` trigger context, fix menu permission evaluation and migrate legacy permission IDs.
- FIX : Align AGENT.md compliance for API payloads, document generation rights, native dates, notes, Agenda/Notifications declarations and cron metadata.
- FIX : Stop creating manual Agenda events during IMAP import; DoliStore order events are now exposed through native triggers.
- FIX : Display DoliStore order lines without grouping to preserve traceability.
- NEW : Add `README.md`, `modulebuilder.txt` and DoliStore notification substitutions.
- FIX : Align DoliStore list column selector dropdown position with native Dolibarr left/right classes.
- FIX : Restore native selectable columns on DoliStore orders, invoice batches and import logs lists.
- FIX : Restore the DoliStore order attached files block to native Dolibarr `showdocuments()` rendering.
- FIX : Align the DoliStore order Multicompany badge and document block with native Dolibarr rendering.
- FIX : Add native setup management for DoliStore order numbering and document models.
- FIX : Normalize the DoliStore order PDF model list before adding the standard fallback model.
- FIX : Load the DoliStore order PDF model parent before rendering the native document block.
- FIX : Use the DoliStore order document modulepart to load the native PDF model parent in the order card.
- FIX : Align DoliStore order attached files tab, PDF generation block and grouped lines with native Dolibarr rendering.
- FIX : Move DoliStore order transverse native blocks below lines and action buttons.
- FIX : Align the DoliStore order card banner and transverse blocks with native Dolibarr rendering.
- FIX : Replace pending DoliStore mailbox order text action with a native eye icon.
- FIX : Align DoliStore order list filter buttons and column selector with native Dolibarr list settings.
- FIX : Replace monthly dashboard summary tables with native Dolibarr line graphs.
- FIX : Align DoliStore business list tables and order lines with native Dolibarr table rendering.
- FIX : Prevent DoliStore order cards from trying to load a missing PDF document model.
- FIX : Display DoliStore orders still pending in the configured mailbox from the archived order list.
- FIX : Avoid PHP fatal errors from private SQL quote helpers colliding with Dolibarr `CommonObject::quote()`.
- NEW : V2 archive workflow for DoliStore orders.
    + Added `Commande DoliStore` business object, lines, invoice batches and import journal tables.
    + IMAP import now archives DoliStore orders instead of creating native customer orders.
    + Added monthly native customer invoice generation to the configured DoliStore thirdparty.
    + Added dashboard, order list/card tabs, mail source storage as attached `.eml`, compatibility settings page and granular permissions.
    + Added Multicompany sharing declaration and environment-aware order lists.

## Release 1.6
- FIX : Missing email in company and contact  - 1.6.4 - *18/12/2025* 
- FIX : change addWebmoduleSales item_price by item_price_total  - 1.6.3 - *17/12/2025* 
- FIX : Add more details on error message linked to import of orders - 1.6.2 - *22/08/2025* 
- FIX : Feedback refacto of DolistoreExtract module
    + Email is now only on the contact record
    + Postal address is now only on the company record - 1.6.1 - *01/08/2025*
- NEW : Redesign of the DolistoreExtract module
    + Removed the function that created a category for each module purchased by a third party
    + Added a function to list each purchased module in the sales list - 1.6.0 - *22/07/2025*
  
## Release 1.5
- NEW : Compat V20 & V21 & Merge modification about mail structure - 1.5.0 - *25/02/2025*

## Release 1.4
- FIX : Addition of a check to prevent several emails from the same order from being processed several times (DA025745) - 1.4.2 - *30/12/2024*
- FIX : Select user pour import init websale + ajout des dates d'achats dans msg d'erreurs - 1.4.1 - *25/09/2024*
- NEW : Ajout de la gestion de l'onglet vente d'un module + Script init onglet vente module - 1.4.0 - *17/09/2024*

## Release 1.3
- FIX : Warning PHP 8.2 - 1.3.1 - *25/03/2024*
- NEW : compatibility to v19 and php8 - 1.3 - *07/03/2024*
  Attention perte compatibilité inférieure V19

## Release 1.2
- FIX : changement de champ pour les prospects importés - 1.2.11 - *06/07/2023*
- FIX : tâche cron dolistorextract => fix gestion des erreurs - 1.2.10 - *28/03/2023*
- FIX : Import contact problem for V15  - 1.2.9 - *24/06/2022*
- FIX : everyday same thirdparty creation because of contact fetch return 2 if several matches, now if several matches we use the oldest thirdparty - 1.2.8 - *06/10/2021*
- FIX : fails to create thirdparty / contact when more than one third party name matches
  name from e-mail - 1.2.7 - *10/05/2021*
- no ChangeLog up to this point
