<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills', 'companies'));

if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new DolistoreOrder($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden();
}

if ($action === 'confirm_delete' && dolistoreextractUserHasRight($user, 'order', 'delete')) {
	if (GETPOST('token', 'alphanohtml') === '') {
		accessforbidden('Invalid token');
	}
	$result = $object->delete($user);
	if ($result > 0) {
		header('Location: '.dol_buildpath('/dolistorextract/list.php', 1));
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('DolistoreOrder'));

$head = dolistoreextractOrderPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');

$linkback = '<a href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DolistoreOrderRef').'</td><td>'.dol_escape_htmltag($object->dolistore_order_ref).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreOrderDate').'</td><td>'.($object->dolistore_order_date ? dol_print_date($object->dolistore_order_date, 'day') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreReleaseDate').'</td><td>'.($object->release_date ? dol_print_date($object->release_date, 'day') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('Currency').'</td><td>'.dol_escape_htmltag($object->currency_code).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
print '<tr><td>'.$langs->trans('Environment').'</td><td>'.(int) $object->entity.'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DolistoreCustomerFinal').'</td><td>'.dol_escape_htmltag($object->customer_name).'</td></tr>';
print '<tr><td>'.$langs->trans('Email').'</td><td>'.dol_escape_htmltag($object->customer_email).'</td></tr>';
print '<tr><td>'.$langs->trans('Country').'</td><td>'.dol_escape_htmltag($object->customer_country).'</td></tr>';
print '<tr><td>'.$langs->trans('AmountHT').'</td><td class="right">'.price($object->total_ht).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreBillableAmountHT').'</td><td class="right">'.price($object->billable_total_ht).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreCommissionPercentLabel').'</td><td class="right">'.price($object->commission_percent).'%</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="clearboth"></div><br>';
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
$uploadDir = dolistoreextractGetOrderUploadDir($object);
$genallowed = 0; // No DoliStore order PDF model is provided yet; keep native attachments only.
$formfile->showdocuments('dolistoreextract', $object->ref, $uploadDir, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, $genallowed, dolistoreextractUserHasRight($user, 'order', 'delete'), '', 1, 0, 0, 0, 0, '', '', '', $langs);
print '</div>';
print '<div class="fichehalfright">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('DolistoreFacturation').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreLinkedInvoice').'</td><td>';
if (!empty($object->fk_facture)) {
	print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $object->fk_facture.'">'.img_object('', 'bill').' '.((int) $object->fk_facture).'</a>';
}
print '</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreInvoiceDate').'</td><td>'.($object->invoice_date ? dol_print_date($object->invoice_date, 'day') : '').'</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
if (dolistoreextractUserHasRight($user, 'order', 'delete')) {
	print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=confirm_delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
}
print '</div>';

llxFooter();
$db->close();
