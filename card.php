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
require_once __DIR__.'/class/dolistoreWelcomeMail.class.php';
require_once __DIR__.'/core/modules/dolistoreextract/modules_dolistoreorder.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'agenda', 'bills', 'companies', 'products'));

if (!isModEnabled('dolistorextract') || !empty($user->socid) || !$user->hasRight('dolistorextract', 'order', 'read')) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

$welcome = new DolistoreWelcomeMail($db);
if ($action === 'confirm_retry_welcome' && GETPOST('confirm', 'alpha') === 'yes') {
	if (!$user->hasRight('dolistorextract', 'order', 'import') || (int) $object->entity !== (int) $conf->entity) {
		accessforbidden();
	}
	$result = $welcome->retry((int) $object->id, $user, GETPOSTINT('verified_unsent') === 1);
	setEventMessages($result < 0 ? $welcome->error : $langs->trans('DolistoreWelcomeRetryRequested'), null, $result < 0 ? 'errors' : 'mesgs');
	header('Location: '.dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $object->id);
	exit;
}
$welcomeStatus = $welcome->getStatus((int) $object->id);

if ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && $user->hasRight('dolistorextract', 'order', 'delete')) {
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

$documentContext = dolistoreextractGetOrderDocumentContext($object);
$uploadDir = $documentContext['upload_dir'];
if ($uploadDir === '') accessforbidden($object->error);
$upload_dir = $uploadDir;
$permissiontoadd = $user->hasRight('dolistorextract', 'order', 'write');
$permissiontodelete = $user->hasRight('dolistorextract', 'order', 'delete');
$usercangeneretedoc = $user->hasRight('dolistorextract', 'order', 'write');
$usercangeneratedoc = $usercangeneretedoc;
$modelselected = !empty($object->model_pdf) ? $object->model_pdf : getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON_PDF', 'standard');
include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';

$form = new Form($db);
$formfile = new FormFile($db);
$formactions = new FormActions($db);

$customerThirdparty = null;
$customerHtml = dol_escape_htmltag($object->customer_name);
if (!empty($object->fk_soc_customer) && $user->hasRight('societe', 'lire')) {
	$customerThirdparty = new Societe($db);
	if ($customerThirdparty->fetch((int) $object->fk_soc_customer) > 0 && in_array((int) $customerThirdparty->entity, array_map('intval', explode(',', getEntity('societe'))), true)) {
		$object->socid = (int) $customerThirdparty->id;
		$object->thirdparty = $customerThirdparty;
		$customerHtml = $customerThirdparty->getNomUrl(1);
	}
}

$entityOptions = dolistoreextractGetEntityOptions($db);
$entityLabel = $entityOptions[(int) $object->entity] ?? '';

llxHeader('', $langs->trans('DolistoreOrder'));
if ($action === 'delete' && $user->hasRight('dolistorextract', 'order', 'delete')) print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('Delete'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 'no', 1);

if ($action === 'retry_welcome' && $user->hasRight('dolistorextract', 'order', 'import') && is_array($welcomeStatus)) {
	$uncertain = $welcomeStatus['status'] === 'uncertain';
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.(int) $object->id, $langs->trans('DolistoreWelcomeRetry'), $langs->trans($uncertain ? 'DolistoreWelcomeConfirmUnsent' : 'DolistoreWelcomeConfirmRetry'), 'confirm_retry_welcome', array(array('type' => 'hidden', 'name' => 'verified_unsent', 'value' => $uncertain ? 1 : 0)), 'no', 1);
}

$head = dolistoreextractOrderPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');

$linkback = '<a href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">';
if (isModEnabled('multicompany')) {
	$morehtmlref .= '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabel).'</span></div>';
}
$morehtmlref .= '</div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

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
print '<tr><td>'.$langs->trans('DolistoreWelcomeMail').'</td><td>';
if (is_array($welcomeStatus)) {
	$welcomeLabel = $langs->trans(DolistoreWelcomeMail::STATUS_KEYS[$welcomeStatus['status']] ?? 'DolistoreWelcomeUncertain');
	print dolGetStatus($welcomeLabel, '', '', $welcomeStatus['status'] === 'sent' ? 'status4' : 'status1', 5);
	if (!empty($welcomeStatus['last_error'])) {
		print '<br><span class="opacitymedium">'.dol_escape_htmltag($langs->trans($welcomeStatus['last_error'])).'</span>';
	}
} else {
	print $langs->trans('DolistoreWelcomeNotScheduled');
}
print '</td></tr>';
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
$orderLines = $object->getGroupedLinesForDisplay();
if (empty($orderLines)) {
	dolistoreextractPrintNoRecordLine(7);
} else {
	foreach ($orderLines as $line) {
		$productHtml = '<span class="warning">'.$langs->trans('DolistoreServiceUnmapped').'</span>';
		if (!empty($line['fk_product'])) $productHtml = dol_escape_htmltag($line['product_label']);
		if (!empty($line['conflict'])) $productHtml .= ' '.img_warning($langs->trans('DolistoreProductConflict'));
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($line['product_dolistore_ref']).'</td>';
		print '<td>'.dol_escape_htmltag($line['product_label']).'</td>';
		print '<td>'.$productHtml.'</td>';
		print '<td class="right">'.price($line['qty']).'</td>';
		print '<td class="right">'.price($line['unit_price_ht']).'</td>';
		print '<td class="right">'.price($line['total_ht']).'</td>';
		print '<td class="right">'.price($line['billable_total_ht']).'</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
if (is_array($welcomeStatus) && in_array($welcomeStatus['status'], array('failed', 'uncertain'), true)
	&& $user->hasRight('dolistorextract', 'order', 'import') && (int) $object->entity === (int) $conf->entity
	&& !getDolGlobalInt('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')) {
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=retry_welcome&token='.newToken().'">'.$langs->trans('DolistoreWelcomeRetry').'</a>';
}
if ($user->hasRight('dolistorextract', 'order', 'delete')) {
	print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
}
print '</div>';

print '<div class="clearboth"></div><br>';
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<a name="builddoc"></a>';
$urlsource = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;
print $formfile->showdocuments($documentContext['modulepart_card'], $documentContext['modulesubdir'], $uploadDir, $urlsource, $usercangeneratedoc, $user->hasRight('dolistorextract', 'order', 'delete'), $modelselected, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, '', $object);
print '<br>';
$form->showLinkedObjectBlock($object);
print '</div>';
print '<div class="fichehalfright">';
if (isModEnabled('agenda') && $user->hasRight('agenda', 'myactions', 'read')) {
	$MAXEVENT = 10;
	$morehtmlcenter = '';
	$formactions->showactions($object, 'dolistoreextract_order@dolistorextract', 0, 1, '', $MAXEVENT, '', $morehtmlcenter);
}
print '</div>';
print '</div>';

llxFooter();
$db->close();
