<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'agenda', 'bills', 'companies'));

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
$formactions = new FormActions($db);

$customerThirdparty = null;
$customerHtml = dol_escape_htmltag($object->customer_name);
if (!empty($object->fk_soc_customer)) {
	$customerThirdparty = new Societe($db);
	if ($customerThirdparty->fetch((int) $object->fk_soc_customer) > 0) {
		$object->socid = (int) $customerThirdparty->id;
		$object->thirdparty = $customerThirdparty;
		$customerHtml = $customerThirdparty->getNomUrl(1);
	}
}

$entityLabel = (string) ((int) $object->entity);
$sqlEntity = 'SELECT label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid = '.((int) $object->entity);
$resEntity = $db->query($sqlEntity);
if ($resEntity) {
	$objEntity = $db->fetch_object($resEntity);
	if (!empty($objEntity->label)) {
		$entityLabel = $objEntity->label;
	}
	$db->free($resEntity);
}

llxHeader('', $langs->trans('DolistoreOrder'));

$head = dolistoreextractOrderPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');

$linkback = '<a href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">';
$morehtmlref .= img_picto($langs->trans('Environment'), 'company', 'class="pictofixedwidth"');
$morehtmlref .= '<span class="badge badge-status0">'.dol_escape_htmltag($entityLabel).'</span>';
$morehtmlref .= '</div>';
$morehtmlstatus = $object->getLibStatut(5);
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', $morehtmlstatus);

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DolistoreOrderRef').'</td><td>'.dol_escape_htmltag($object->dolistore_order_ref).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreOrderDate').'</td><td>'.($object->dolistore_order_date ? dol_print_date($object->dolistore_order_date, 'day') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreReleaseDate').'</td><td>'.($object->release_date ? dol_print_date($object->release_date, 'day') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('Currency').'</td><td>'.dol_escape_htmltag($object->currency_code).'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('DolistoreCustomerFinal').'</td><td>'.$customerHtml.'</td></tr>';
print '<tr><td>'.$langs->trans('AmountHT').'</td><td class="right">'.price($object->total_ht).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreBillableAmountHT').'</td><td class="right">'.price($object->billable_total_ht).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreCommissionPercentLabel').'</td><td class="right">'.price($object->commission_percent).'%</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="clearboth"></div><br>';
print '<div id="dolistore-order-lines">';
print load_fiche_titre($langs->trans('DolistoreOrderLines'), '', 'product');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder noshadow centpercent tablelines">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('DolistoreProductRef').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('Product').'</th>';
print '<th class="right">'.$langs->trans('Qty').'</th>';
print '<th class="right">'.$langs->trans('UnitPriceHT').'</th>';
print '<th class="right">'.$langs->trans('AmountHT').'</th>';
print '<th class="right">'.$langs->trans('DolistoreBillableAmountHT').'</th>';
print '</tr>';
$orderLines = $object->getLines();
if (empty($orderLines)) {
	dolistoreextractPrintNoRecordLine(7);
} else {
	foreach ($orderLines as $line) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($line->product_dolistore_ref).'</td>';
		print '<td>'.dol_escape_htmltag($line->product_label).'</td>';
		print '<td>'.(!empty($line->fk_product) ? '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.(int) $line->fk_product.'">'.((int) $line->fk_product).'</a>' : '<span class="warning">'.$langs->trans('DolistoreServiceUnmapped').'</span>').'</td>';
		print '<td class="right">'.price($line->qty).'</td>';
		print '<td class="right">'.price($line->unit_price_ht).'</td>';
		print '<td class="right">'.price($line->total_ht).'</td>';
		print '<td class="right">'.price($line->billable_total_ht).'</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
if (dolistoreextractUserHasRight($user, 'order', 'delete')) {
	print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=confirm_delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
}
print '</div>';

print '<div class="clearboth"></div><br>';
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<a name="builddoc"></a>';
$uploadDir = dolistoreextractGetOrderUploadDir($object);
$genallowed = 0; // No DoliStore order PDF model is provided yet; keep native attachments only.
$formfile->showdocuments('dolistoreextract', $object->ref, $uploadDir, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, $genallowed, dolistoreextractUserHasRight($user, 'order', 'delete'), '', 1, 0, 0, 0, 0, '', '', '', $langs);
print '<br>';
$form->showLinkedObjectBlock($object);
print '</div>';
print '<div class="fichehalfright">';
if (isModEnabled('agenda')) {
	$MAXEVENT = 10;
	$morehtmlcenter = '';
	$formactions->showactions($object, $object->element, 0, 1, '', $MAXEVENT, '', $morehtmlcenter);
}
print '</div>';
print '</div>';

llxFooter();
$db->close();
