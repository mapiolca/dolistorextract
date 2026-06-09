<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$form = new Form($db);
$dateStart = GETPOST('date_start', 'alpha');
$dateEnd = GETPOST('date_end', 'alpha');
if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $dateStart)) $dateStart = '';
if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $dateEnd)) $dateEnd = '';
$searchProduct = GETPOST('search_product', 'alphanohtml');
$searchStatus = GETPOST('search_status', 'intcomma');
$searchEntity = GETPOST('search_entity', 'array');
if (!is_array($searchEntity)) $searchEntity = array();
$splitBy = GETPOST('split_by', 'alpha');
if (!in_array($splitBy, array('amount', 'qty'), true)) $splitBy = 'amount';

$entityOptions = array();
$resqlEntities = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.getEntity('dolistoreextract_order').') ORDER BY label ASC');
if ($resqlEntities) {
	while ($objEntity = $db->fetch_object($resqlEntities)) {
		$entityOptions[(int) $objEntity->rowid] = (string) $objEntity->label;
	}
	$db->free($resqlEntities);
}
if (empty($entityOptions)) $entityOptions[(int) $conf->entity] = (string) $conf->entity;

$whereOrder = array('entity IN ('.getEntity('dolistoreextract_order').')');
$whereOrderAlias = array('o.entity IN ('.getEntity('dolistoreextract_order').')');
if (!empty($searchEntity)) {
	$entityList = implode(',', array_map('intval', $searchEntity));
	$whereOrder[] = 'entity IN ('.$entityList.')';
	$whereOrderAlias[] = 'o.entity IN ('.$entityList.')';
}
if ($dateStart !== '') {
	$whereOrder[] = "dolistore_order_date >= '".$db->escape($dateStart)."'";
	$whereOrderAlias[] = "o.dolistore_order_date >= '".$db->escape($dateStart)."'";
}
if ($dateEnd !== '') {
	$whereOrder[] = "dolistore_order_date <= '".$db->escape($dateEnd)."'";
	$whereOrderAlias[] = "o.dolistore_order_date <= '".$db->escape($dateEnd)."'";
}
if ($searchStatus !== '') {
	$whereOrder[] = 'status IN ('.$db->sanitize($searchStatus).')';
	$whereOrderAlias[] = 'o.status IN ('.$db->sanitize($searchStatus).')';
}
if ($searchProduct !== '') {
	$productSql = "(lf.product_label LIKE '%".$db->escape($searchProduct)."%' OR lf.product_dolistore_ref LIKE '%".$db->escape($searchProduct)."%')";
	$whereOrder[] = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line as lf WHERE lf.fk_order = '.MAIN_DB_PREFIX.'dolistoreextract_order.rowid AND lf.entity = '.MAIN_DB_PREFIX.'dolistoreextract_order.entity AND '.$productSql.')';
	$whereOrderAlias[] = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line as lf WHERE lf.fk_order = o.rowid AND lf.entity = o.entity AND '.$productSql.')';
}

$entityWhere = implode(' AND ', $whereOrder);
$entityWhereAlias = implode(' AND ', $whereOrderAlias);
$monthStart = dol_print_date(dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), 1, (int) dol_print_date(dol_now(), '%Y')), '%Y-%m-%d');

function dolistoreextractScalar($db, $sql)
{
	$resql = $db->query($sql);
	if (!$resql) return 0;
	$obj = $db->fetch_object($resql);
	$db->free($resql);
	return $obj ? (float) $obj->v : 0;
}

function dolistoreextractMonthlyGraphData($db, $sql, $valuefield)
{
	$data = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		return $data;
	}

	while ($obj = $db->fetch_object($resql)) {
		$data[] = array($obj->period, (float) $obj->{$valuefield});
	}
	$db->free($resql);

	return array_reverse($data);
}

function dolistoreextractPrintLineGraph($title, $legend, $data, $graphid)
{
	global $langs;

	$dolgraph = new DolGraph();
	$dolgraph->setWidth('100%');
	$dolgraph->setHeight('220');
	$dolgraph->setShowLegend(0);

	print '<table class="noborder nohover centpercent">';
	print '<tr class="liste_titre"><th>'.$title.'</th></tr>';
	print '<tr class="oddeven"><td class="center">';
	if (!empty($data)) {
		$dolgraph->SetData($data);
		$dolgraph->SetLegend(array($legend));
		$dolgraph->SetType(array('lines'));
		$dolgraph->SetMinValue(0);
		$dolgraph->SetMaxValue(max(1, $dolgraph->GetCeilMaxValue()));
		$dolgraph->draw($graphid);
		print $dolgraph->show();
	} else {
		print $dolgraph->show($langs->trans('NoRecordFound'));
	}
	print '</td></tr>';
	print '</table>';
}

$ordersMonth = dolistoreextractScalar($db, 'SELECT COUNT(rowid) as v FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere." AND dolistore_order_date >= '".$db->escape($monthStart)."'");
$amountMonth = dolistoreextractScalar($db, 'SELECT SUM(total_ht) as v FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere." AND dolistore_order_date >= '".$db->escape($monthStart)."'");
$amountInvoiceable = dolistoreextractScalar($db, 'SELECT SUM(billable_total_ht) as v FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere.' AND fk_facture IS NULL AND release_date <= \''.dol_print_date(dol_now(), '%Y-%m-%d').'\'');
$waitingRelease = dolistoreextractScalar($db, 'SELECT COUNT(rowid) as v FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere.' AND status = '.DolistoreOrder::STATUS_WAITING_RELEASE);
$errors = dolistoreextractScalar($db, 'SELECT COUNT(rowid) as v FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere.' AND status = '.DolistoreOrder::STATUS_ERROR);
$lastInvoice = '';
$resqlLastInvoice = $db->query('SELECT f.ref FROM '.MAIN_DB_PREFIX.'dolistoreextract_invoice_batch b INNER JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid = b.fk_facture WHERE b.entity IN ('.getEntity('dolistoreextract_order').') ORDER BY b.rowid DESC LIMIT 1');
if ($resqlLastInvoice && ($obj = $db->fetch_object($resqlLastInvoice))) $lastInvoice = $obj->ref;
if ($resqlLastInvoice) $db->free($resqlLastInvoice);

llxHeader('', $langs->trans('DolistoreDashboard'));
print load_fiche_titre($langs->trans('DolistoreDashboard'), '', 'dolistore@dolistorextract');

$statusOptions = array(
	DolistoreOrder::STATUS_DRAFT => $langs->trans('DolistoreOrderStatusDraft'),
	DolistoreOrder::STATUS_IMPORTED => $langs->trans('DolistoreOrderStatusImported'),
	DolistoreOrder::STATUS_WAITING_RELEASE => $langs->trans('DolistoreOrderStatusWaitingRelease'),
	DolistoreOrder::STATUS_INVOICEABLE => $langs->trans('DolistoreOrderStatusInvoiceable'),
	DolistoreOrder::STATUS_INVOICED => $langs->trans('DolistoreOrderStatusInvoiced'),
	DolistoreOrder::STATUS_ERROR => $langs->trans('DolistoreOrderStatusError'),
);
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="10">'.$langs->trans('Filters').'</th></tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DateStart').'</td><td><input type="date" class="flat" name="date_start" value="'.dol_escape_htmltag($dateStart).'"></td>';
print '<td>'.$langs->trans('DateEnd').'</td><td><input type="date" class="flat" name="date_end" value="'.dol_escape_htmltag($dateEnd).'"></td>';
print '<td>'.$langs->trans('Product').'</td><td><input type="text" class="flat maxwidth150" name="search_product" value="'.dol_escape_htmltag($searchProduct).'"></td>';
print '<td>'.$langs->trans('Status').'</td><td>'.$form->selectarray('search_status', $statusOptions, $searchStatus, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td>'.$langs->trans('Environment').'</td><td>'.$form->multiselectarray('search_entity', $entityOptions, $searchEntity, 0, 0, 'minwidth100 maxwidth200').'</td>';
print '</tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreProductSplitMode').'</td><td>'.$form->selectarray('split_by', array('amount' => $langs->trans('Amount'), 'qty' => $langs->trans('Qty')), $splitBy).'</td><td colspan="8" class="right"><button class="button" type="submit">'.$langs->trans('Search').'</button> <a class="button" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('RemoveFilter').'</a></td></tr>';
print '</table><br>';
print '</form>';

print '<div class="fichecenter">';
$cards = array(
	array('DolistoreOrdersThisMonth', $ordersMonth),
	array('DolistoreAmountThisMonth', price($amountMonth)),
	array('DolistoreAmountInvoiceable', price($amountInvoiceable)),
	array('DolistoreWaitingRelease', $waitingRelease),
	array('DolistoreErrors', $errors),
	array('DolistoreLastInvoice', $lastInvoice),
);
foreach ($cards as $card) {
	print '<div class="inline-block minwidth200 centpercentminusx maxwidth300 marginrightonly marginbottomonly">';
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.dol_escape_htmltag($langs->trans($card[0])).'</th></tr><tr class="oddeven"><td class="center amount">'.dol_escape_htmltag((string) $card[1]).'</td></tr></table>';
	print '</div>';
}
print '</div><br>';

print '<div class="fichecenter"><div class="fichehalfleft">';
$sql = 'SELECT DATE_FORMAT(dolistore_order_date, "%Y-%m") as period, COUNT(rowid) as nb FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere.' GROUP BY period ORDER BY period DESC LIMIT 12';
$ordersByMonthData = dolistoreextractMonthlyGraphData($db, $sql, 'nb');
dolistoreextractPrintLineGraph($langs->trans('DolistoreOrdersByMonth'), $langs->trans('Orders'), $ordersByMonthData, 'dolistoreextract_orders_by_month');
print '</div><div class="fichehalfright">';
$sql = 'SELECT DATE_FORMAT(dolistore_order_date, "%Y-%m") as period, SUM(total_ht) as amount FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE '.$entityWhere.' GROUP BY period ORDER BY period DESC LIMIT 12';
$amountsByMonthData = dolistoreextractMonthlyGraphData($db, $sql, 'amount');
dolistoreextractPrintLineGraph($langs->trans('DolistoreAmountsByMonth'), $langs->trans('Amount'), $amountsByMonthData, 'dolistoreextract_amounts_by_month');
print '</div></div><br>';

print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="3">'.$langs->trans('DolistoreSalesByProduct').'</th></tr>';
$productOrder = ($splitBy === 'qty') ? 'qty' : 'amount';
$sql = 'SELECT l.product_label, SUM(l.billable_total_ht) as amount, SUM(l.qty) as qty';
$sql .= ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line as l';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order as o ON o.rowid = l.fk_order AND o.entity = l.entity';
$sql .= ' WHERE '.$entityWhereAlias;
if ($searchProduct !== '') {
	$sql .= " AND (l.product_label LIKE '%".$db->escape($searchProduct)."%' OR l.product_dolistore_ref LIKE '%".$db->escape($searchProduct)."%')";
}
$sql .= ' GROUP BY l.product_label ORDER BY '.$productOrder.' DESC LIMIT 20';
$resql = $db->query($sql);
while ($resql && ($obj = $db->fetch_object($resql))) {
	print '<tr class="oddeven"><td>'.dol_escape_htmltag($obj->product_label).'</td><td class="right">'.price($obj->amount).'</td><td class="right">'.price($obj->qty).'</td></tr>';
}
if ($resql) $db->free($resql);
print '</table>';

llxFooter();
$db->close();
