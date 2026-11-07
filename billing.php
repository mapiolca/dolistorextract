<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills'));
if (!isModEnabled('dolistorextract') || !empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'read'))) accessforbidden();

$object = new DolistoreOrder($db);
if (GETPOST('id', 'int') <= 0 || $object->fetch(GETPOST('id', 'int')) <= 0) accessforbidden();

llxHeader('', $langs->trans('DolistoreBilling'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'billing', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DolistoreReleaseDate').'</td><td>'.($object->release_date ? dol_print_date($object->release_date, 'day') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreInvoiceable').'</td><td>'.($object->isInvoiceable() ? yn(1) : yn(0)).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreBillableAmountHT').'</td><td class="right">'.price($object->billable_total_ht).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreLinkedInvoice').'</td><td>';
if (!empty($object->fk_facture) && (isModEnabled('facture') && (!empty($user->admin) || $user->hasRight('facture', 'lire')))) {
	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
	$invoice = new Facture($db);
	if ($invoice->fetch((int) $object->fk_facture) > 0 && in_array((int) $invoice->entity, array_map('intval', explode(',', getEntity('facture'))), true)) print $invoice->getNomUrl(1);
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreInvoiceDate').'</td><td>'.($object->invoice_date ? dol_print_date($object->invoice_date, 'day') : '').'</td></tr>';
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
