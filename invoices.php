<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once __DIR__.'/class/actions_dolistorextract.class.php';
require_once __DIR__.'/class/dolistoreInvoiceBatch.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills'));
if (!isModEnabled('dolistorextract') || !empty($user->socid) || !$user->hasRight('dolistorextract', 'invoice', 'generate')) accessforbidden();

$form = new Form($db);
$batchstatic = new DolistoreInvoiceBatch($db);
$contextpage = 'dolistoreextractinvoiceslist';

$arrayfields = array(
	'period' => array('label' => 'Period', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'invoice_ref' => array('label' => 'InvoiceRef', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'amount_ht' => array('label' => 'AmountHT', 'checked' => 1, 'enabled' => 1, 'position' => 30, 'align' => 'right'),
	'orders_count' => array('label' => 'DolistoreOrdersCount', 'checked' => 1, 'enabled' => 1, 'position' => 40, 'align' => 'right'),
	'lines_count' => array('label' => 'DolistoreLinesCount', 'checked' => 1, 'enabled' => 1, 'position' => 50, 'align' => 'right'),
	'status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'email_sent' => array('label' => 'DolistoreEmailSent', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	'entity' => array('label' => 'DolistoreEnvironment', 'checked' => 1, 'enabled' => isModEnabled('multicompany'), 'position' => 80),
);
$selectedfields = dolistoreextractPrepareSelectedFields($form, $contextpage, 'selectedfields_invoices', $arrayfields);

$action = GETPOST('action', 'aZ09');
if ($action === 'generate') {
	if (GETPOST('token', 'alphanohtml') === '') accessforbidden('Invalid token');
	$actions = new ActionsDolistorextract($db);
	$result = $actions->generateMonthlyDolistoreInvoice($user, true);
	if ($result < 0) setEventMessages($actions->error, $actions->errors, 'errors');
	elseif ($result > 0) setEventMessages($langs->trans('DolistoreInvoiceGenerationDone'), null, 'mesgs');
	else setEventMessages($langs->trans('DolistoreInvoiceNothingToDo'), null, 'warnings');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) $sortfield = 'b.period_year,b.period_month';
if (!$sortorder) $sortorder = 'DESC,DESC';
$page = GETPOST('page', 'int');
if ($page < 0) $page = 0;
$limit = GETPOSTINT('limit');
if ($limit <= 0) $limit = $conf->liste_limit;
$offset = $limit * $page;

$search_period = GETPOST('search_period', 'alphanohtml');
$search_invoice = GETPOST('search_invoice', 'alphanohtml');
$search_status = GETPOST('search_status', 'intcomma');
$search_email_sent = GETPOST('search_email_sent', 'alpha');
$search_entity = GETPOST('search_entity', 'array');
if (!is_array($search_entity)) $search_entity = array();

$entityOptions = dolistoreextractGetEntityOptions($db);
if (GETPOSTISSET('button_search') || GETPOSTISSET('button_search_x')) $page = 0;
if (GETPOSTISSET('button_removefilter') || GETPOSTISSET('button_removefilter_x')) {
	$search_period = $search_invoice = $search_status = $search_email_sent = '';
	$search_entity = array(); $page = 0;
}
$search_entity = array_values(array_intersect(array_map('intval', $search_entity), array_keys($entityOptions)));
$offset = $limit * $page;


$where = array('b.entity IN ('.getEntity('dolistoreextract_order').')');
if ($search_period !== '') {
	$where[] = "CONCAT(b.period_year, '-', LPAD(b.period_month, 2, '0')) LIKE '%".$db->escape($search_period)."%'";
}
if ($search_invoice !== '') {
	$where[] = natural_search('f.ref', $search_invoice);
}
if ($search_status !== '') {
	$where[] = 'b.status IN ('.$db->sanitize($search_status).')';
}
if ($search_email_sent === 'yes') {
	$where[] = 'b.email_sent = 1';
} elseif ($search_email_sent === 'no') {
	$where[] = '(b.email_sent IS NULL OR b.email_sent = 0)';
}
if (!empty($search_entity)) {
	$where[] = 'b.entity IN ('.implode(',', array_map('intval', $search_entity)).')';
}

$param = '';
foreach (array('search_period', 'search_invoice', 'search_status', 'search_email_sent') as $key) {
	if (${$key} !== '') {
		$param .= '&'.$key.'='.urlencode(${$key});
	}
}
foreach ($search_entity as $entityId) {
	$param .= '&search_entity[]='.(int) $entityId;
}
if (GETPOSTINT('limit') > 0) {
	$param .= '&limit='.(int) $limit;
}

$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_invoice_batch b';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid = b.fk_facture AND f.entity = b.entity AND f.entity IN ('.$db->sanitize(getEntity('facture')).')';
$sqlWhere = ' WHERE '.implode(' AND ', $where);

$sqlCount = 'SELECT COUNT(b.rowid) as nb'.$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$num = 0;
if ($resqlCount) {
	$objCount = $db->fetch_object($resqlCount);
	$num = is_object($objCount) ? (int) $objCount->nb : 0;
	$db->free($resqlCount);
}

$sql = 'SELECT b.*, f.ref as invoice_ref, f.type as invoice_type, f.datef as invoice_date, f.total_ht as invoice_ht, f.total_tva as invoice_tva, f.total_ttc as invoice_ttc'.$sqlFrom.$sqlWhere.$db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) setEventMessages($db->lasterror(), null, 'errors');

$statusOptions = array(
	DolistoreInvoiceBatch::STATUS_DRAFT => $langs->trans('Draft'),
	DolistoreInvoiceBatch::STATUS_SUCCESS => $langs->trans('DolistoreStatusSuccess'),
	DolistoreInvoiceBatch::STATUS_ERROR => $langs->trans('Error'),
);

llxHeader('', $langs->trans('DolistoreInvoices'));
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="formfilteraction" value="">';
print '<input type="hidden" name="column_contextpage" value="'.dol_escape_htmltag($contextpage).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';

$generateUrl = $_SERVER['PHP_SELF'].'?action=generate&token='.newToken();
$newcardbutton = dolGetButtonTitle($langs->trans('DolistoreGenerateMonthlyInvoice'), '', 'fa fa-plus-circle', $generateUrl, '', $user->hasRight('facture', 'creer'));
print_barre_liste($langs->trans('DolistoreInvoices'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'bill', 0, $newcardbutton, '', $limit);

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
if (dolistoreextractArrayFieldChecked($arrayfields, 'period')) print '<td><input type="text" class="flat maxwidth100" name="search_period" value="'.dol_escape_htmltag($search_period).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'invoice_ref')) print '<td><input type="text" class="flat maxwidth100" name="search_invoice" value="'.dol_escape_htmltag($search_invoice).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'amount_ht')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'orders_count')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'lines_count')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'status')) print '<td>'.$form->selectarray('search_status', $statusOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'email_sent')) print '<td>'.$form->selectarray('search_email_sent', array('yes' => $langs->trans('Yes'), 'no' => $langs->trans('No')), $search_email_sent, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $search_entity, 0, 0, 'minwidth100 maxwidth200').'</td>';
print '<td class="right">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (dolistoreextractArrayFieldChecked($arrayfields, 'period')) print_liste_field_titre('Period', $_SERVER['PHP_SELF'], 'b.period_year,b.period_month', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'invoice_ref')) print_liste_field_titre('InvoiceRef', $_SERVER['PHP_SELF'], 'f.ref', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'amount_ht')) print_liste_field_titre('AmountHT', $_SERVER['PHP_SELF'], 'b.amount_ht', $param, '', 'align="right"', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'orders_count')) print_liste_field_titre('DolistoreOrdersCount', $_SERVER['PHP_SELF'], 'b.orders_count', $param, '', 'align="right"', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'lines_count')) print_liste_field_titre('DolistoreLinesCount', $_SERVER['PHP_SELF'], 'b.lines_count', $param, '', 'align="right"', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'status')) print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'b.status', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'email_sent')) print_liste_field_titre('DolistoreEmailSent', $_SERVER['PHP_SELF'], 'b.email_sent', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print_liste_field_titre('DolistoreEnvironment', $_SERVER['PHP_SELF'], 'b.entity', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($selectedfields, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';

$rowCount = 0;
$totalAmountHt = 0;
$totalOrders = 0;
$totalLines = 0;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if ($limit > 0 && $rowCount >= $limit) {
			break;
		}
		$rowCount++;
		$totalAmountHt += (float) $obj->amount_ht;
		$totalOrders += (int) $obj->orders_count;
		$totalLines += (int) $obj->lines_count;
		$period = ((int) $obj->period_year).'-'.str_pad((string) $obj->period_month, 2, '0', STR_PAD_LEFT);
		$hasLinkedInvoice = !empty($obj->fk_facture) && !empty($obj->invoice_ref);
		$batchstatic->status = $hasLinkedInvoice ? (int) $obj->status : DolistoreInvoiceBatch::STATUS_ERROR;

		$invoiceLink = '';
		if (!empty($obj->invoice_ref) && $user->hasRight('facture', 'lire')) {
			$invoice = new Facture($db);
			$invoice->id = (int) $obj->fk_facture;
			$invoice->ref = (string) $obj->invoice_ref;
			$invoice->type = (int) $obj->invoice_type;
			$invoice->date = $db->jdate($obj->invoice_date);
			$invoice->total_ht = $obj->invoice_ht;
			$invoice->total_tva = $obj->invoice_tva;
			$invoice->total_ttc = $obj->invoice_ttc;
			$invoiceLink = $invoice->getNomUrl(1);
		}
		print '<tr class="oddeven">';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'period')) print '<td>'.dol_escape_htmltag($period).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'invoice_ref')) {
			print '<td>';
			if ($hasLinkedInvoice) {
				print $invoiceLink;
			} else {
				print '<span class="warning">'.$langs->trans('DolistoreInvoiceMissing').'</span>';
			}
			print '</td>';
		}
		if (dolistoreextractArrayFieldChecked($arrayfields, 'amount_ht')) print '<td class="right">'.price($obj->amount_ht).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'orders_count')) print '<td class="right">'.((int) $obj->orders_count).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'lines_count')) print '<td class="right">'.((int) $obj->lines_count).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'status')) print '<td>'.$batchstatic->getLibStatut(5).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'email_sent')) print '<td>'.yn((int) $obj->email_sent).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print '<td class="center"><div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityOptions[(int) $obj->entity] ?? '').'</span></div></td>';
		print '<td></td>';
		print '</tr>';
	}
	$db->free($resql);
}

if ($rowCount === 0) {
	dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($arrayfields, 1));
} else {
	$totals = array();
	if (dolistoreextractArrayFieldChecked($arrayfields, 'amount_ht')) $totals['amount_ht'] = price($totalAmountHt);
	if (dolistoreextractArrayFieldChecked($arrayfields, 'orders_count')) $totals['orders_count'] = (string) $totalOrders;
	if (dolistoreextractArrayFieldChecked($arrayfields, 'lines_count')) $totals['lines_count'] = (string) $totalLines;
	if (!empty($totals)) dolistoreextractPrintTotalRow($arrayfields, $totals, 1);
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
