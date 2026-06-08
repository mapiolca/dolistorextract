<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && file_exists('../main.inc.php')) {
	$res = include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills', 'companies'));

if (!isModEnabled('dolistorextract')) {
	accessforbidden();
}
if (!dolistoreextractUserHasRight($user, 'order', 'read')) {
	accessforbidden();
}

$form = new Form($db);
$objectstatic = new DolistoreOrder($db);

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) {
	$sortfield = 'o.dolistore_order_date';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}
$page = GETPOST('page', 'int');
if ($page < 0) {
	$page = 0;
}
$limit = $conf->liste_limit;
$offset = $limit * $page;

$search_ref = GETPOST('search_ref', 'alphanohtml');
$search_dolistore_ref = GETPOST('search_dolistore_ref', 'alphanohtml');
$search_customer = GETPOST('search_customer', 'alphanohtml');
$search_product = GETPOST('search_product', 'alphanohtml');
$search_status = GETPOST('search_status', 'intcomma');
$search_invoiceable = GETPOST('search_invoiceable', 'alpha');
$search_entity = GETPOST('search_entity', 'array');
if (!is_array($search_entity)) {
	$search_entity = array();
}

$entityOptions = array();
$resqlEntities = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.getEntity('dolistoreextract_order').') ORDER BY label ASC');
if ($resqlEntities) {
	while ($objEntity = $db->fetch_object($resqlEntities)) {
		$entityOptions[(int) $objEntity->rowid] = (string) $objEntity->label;
	}
	$db->free($resqlEntities);
}
if (empty($entityOptions)) {
	$entityOptions[(int) $conf->entity] = (string) $conf->entity;
}

$param = '';
foreach (array('search_ref', 'search_dolistore_ref', 'search_customer', 'search_product', 'search_status', 'search_invoiceable') as $key) {
	if (GETPOST($key, 'alphanohtml') !== '') {
		$param .= '&'.$key.'='.urlencode(GETPOST($key, 'alphanohtml'));
	}
}
foreach ($search_entity as $entityId) {
	$param .= '&search_entity[]='.(int) $entityId;
}

$where = array();
$where[] = 'o.entity IN ('.getEntity('dolistoreextract_order').')';
if ($search_ref !== '') {
	$where[] = natural_search('o.ref', $search_ref);
}
if ($search_dolistore_ref !== '') {
	$where[] = natural_search('o.dolistore_order_ref', $search_dolistore_ref);
}
if ($search_customer !== '') {
	$where[] = '(o.customer_name LIKE \'%'.$db->escape($search_customer).'%\' OR o.customer_email LIKE \'%'.$db->escape($search_customer).'%\')';
}
if ($search_status !== '') {
	$where[] = 'o.status IN ('.$db->sanitize($search_status).')';
}
if (!empty($search_entity)) {
	$where[] = 'o.entity IN ('.implode(',', array_map('intval', $search_entity)).')';
}
if ($search_invoiceable === 'yes') {
	$where[] = 'o.status IN ('.DolistoreOrder::STATUS_IMPORTED.','.DolistoreOrder::STATUS_WAITING_RELEASE.','.DolistoreOrder::STATUS_INVOICEABLE.')';
	$where[] = 'o.fk_facture IS NULL';
	$where[] = "o.release_date <= '".dol_print_date(dol_now(), '%Y-%m-%d')."'";
}
if ($search_invoiceable === 'no') {
	$where[] = '(o.fk_facture IS NOT NULL OR o.release_date > \''.dol_print_date(dol_now(), '%Y-%m-%d').'\')';
}
if ($search_product !== '') {
	$where[] = 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line as lf WHERE lf.fk_order = o.rowid AND lf.entity = o.entity AND (lf.product_label LIKE \'%'.$db->escape($search_product).'%\' OR lf.product_dolistore_ref LIKE \'%'.$db->escape($search_product).'%\'))';
}

$sqlSelect = 'SELECT o.rowid, o.entity, o.ref, o.dolistore_order_ref, o.dolistore_order_date, o.release_date, o.customer_name, o.customer_email, o.billable_total_ht, o.status, o.fk_facture, f.ref as invoice_ref, ent.label as entity_label, GROUP_CONCAT(DISTINCT l.product_label ORDER BY l.product_label SEPARATOR ", ") as products';
$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order as o';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order_line as l ON l.fk_order = o.rowid AND l.entity = o.entity';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'facture as f ON f.rowid = o.fk_facture';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity as ent ON ent.rowid = o.entity';
$sqlWhere = ' WHERE '.implode(' AND ', $where);
$sqlGroup = ' GROUP BY o.rowid, o.entity, o.ref, o.dolistore_order_ref, o.dolistore_order_date, o.release_date, o.customer_name, o.customer_email, o.billable_total_ht, o.status, o.fk_facture, f.ref, ent.label';

$sqlCount = 'SELECT COUNT(DISTINCT o.rowid) as nb'.$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$num = 0;
if ($resqlCount) {
	$objCount = $db->fetch_object($resqlCount);
	$num = (int) $objCount->nb;
	$db->free($resqlCount);
}

$sql = $sqlSelect.$sqlFrom.$sqlWhere.$sqlGroup.$db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

llxHeader('', $langs->trans('DolistoreOrders'));

print_barre_liste($langs->trans('DolistoreOrders'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'dolistore@dolistorextract', 0, '', '', $limit);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_dolistore_ref" value="'.dol_escape_htmltag($search_dolistore_ref).'"></td>';
print '<td></td><td></td>';
print '<td><input type="text" class="flat maxwidth150" name="search_customer" value="'.dol_escape_htmltag($search_customer).'"></td>';
print '<td><input type="text" class="flat maxwidth150" name="search_product" value="'.dol_escape_htmltag($search_product).'"></td>';
print '<td></td>';
$statusOptions = array(
	DolistoreOrder::STATUS_DRAFT => $langs->trans('DolistoreOrderStatusDraft'),
	DolistoreOrder::STATUS_IMPORTED => $langs->trans('DolistoreOrderStatusImported'),
	DolistoreOrder::STATUS_WAITING_RELEASE => $langs->trans('DolistoreOrderStatusWaitingRelease'),
	DolistoreOrder::STATUS_INVOICEABLE => $langs->trans('DolistoreOrderStatusInvoiceable'),
	DolistoreOrder::STATUS_INVOICED => $langs->trans('DolistoreOrderStatusInvoiced'),
	DolistoreOrder::STATUS_ERROR => $langs->trans('DolistoreOrderStatusError'),
);
print '<td>'.$form->selectarray('search_status', $statusOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
print '<td>'.$form->selectarray('search_invoiceable', array('yes' => $langs->trans('Yes'), 'no' => $langs->trans('No')), $search_invoiceable, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td></td>';
print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $search_entity, 0, 0, 'minwidth100 maxwidth200').'</td>';
print '<td>';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button>';
print '<a class="button button_search" href="'.$_SERVER['PHP_SELF'].'">'.img_picto($langs->trans('RemoveFilter'), 'searchclear').'</a>';
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'o.ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DolistoreOrderRef', $_SERVER['PHP_SELF'], 'o.dolistore_order_ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DolistoreOrderDate', $_SERVER['PHP_SELF'], 'o.dolistore_order_date', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DolistoreReleaseDate', $_SERVER['PHP_SELF'], 'o.release_date', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('DolistoreCustomerFinal', $_SERVER['PHP_SELF'], 'o.customer_name', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Products', $_SERVER['PHP_SELF'], '', $param);
print_liste_field_titre('AmountHT', $_SERVER['PHP_SELF'], 'o.billable_total_ht', $param, '', 'align="right"', $sortfield, $sortorder);
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'o.status', $param, '', 'align="center"', $sortfield, $sortorder);
print_liste_field_titre('DolistoreInvoiceable', $_SERVER['PHP_SELF'], '', $param, '', 'align="center"');
print_liste_field_titre('DolistoreLinkedInvoice', $_SERVER['PHP_SELF'], 'f.ref', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 'o.entity', $param, '', '', $sortfield, $sortorder);
print '<td></td>';
print '</tr>';

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$objectstatic->id = (int) $obj->rowid;
		$objectstatic->ref = (string) $obj->ref;
		$objectstatic->status = (int) $obj->status;
		$objectstatic->release_date = !empty($obj->release_date) ? $db->jdate($obj->release_date) : 0;
		$objectstatic->fk_facture = (int) $obj->fk_facture;
		print '<tr class="oddeven">';
		print '<td>'.$objectstatic->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($obj->dolistore_order_ref).'</td>';
		print '<td>'.(!empty($obj->dolistore_order_date) ? dol_print_date($db->jdate($obj->dolistore_order_date), 'day') : '').'</td>';
		print '<td>'.(!empty($obj->release_date) ? dol_print_date($db->jdate($obj->release_date), 'day') : '').'</td>';
		print '<td>'.dol_escape_htmltag($obj->customer_name).'<br><span class="opacitymedium">'.dol_escape_htmltag($obj->customer_email).'</span></td>';
		print '<td>'.dol_escape_htmltag($obj->products).'</td>';
		print '<td class="right">'.price($obj->billable_total_ht).'</td>';
		print '<td class="center">'.$objectstatic->getLibStatut(5).'</td>';
		print '<td class="center">'.($objectstatic->isInvoiceable() ? yn(1) : yn(0)).'</td>';
		print '<td>'.(!empty($obj->fk_facture) ? '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $obj->fk_facture.'">'.dol_escape_htmltag($obj->invoice_ref).'</a>' : '').'</td>';
		print '<td>'.dol_escape_htmltag($obj->entity_label ?: $obj->entity).'</td>';
		print '<td></td>';
		print '</tr>';
	}
	$db->free($resql);
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
