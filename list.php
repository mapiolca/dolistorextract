<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
require_once __DIR__.'/class/actions_dolistorextract.class.php';
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

$pendingContextPage = 'dolistoreextractpendingorderslist';
$ordersContextPage = 'dolistoreextractorderslist';
dolistoreextractSaveSelectedFields($pendingContextPage, 'selectedfields_pendingorders');
dolistoreextractSaveSelectedFields($ordersContextPage, 'selectedfields_orders');

$pendingArrayFields = array(
	'folder' => array('label' => 'DolistoreEmailFolder', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'email_date' => array('label' => 'DolistoreEmailDate', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'email_id' => array('label' => 'ID', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'order_ref' => array('label' => 'DolistoreOrderRef', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'lang' => array('label' => 'Language', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'customer_name' => array('label' => 'DolistoreCustomerFinal', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'customer_email' => array('label' => 'EMail', 'checked' => 1, 'enabled' => 1, 'position' => 70),
	'contact_name' => array('label' => 'Contact', 'checked' => 1, 'enabled' => 1, 'position' => 80),
	'mail_count' => array('label' => 'DolistorePendingMailCount', 'checked' => 1, 'enabled' => 1, 'position' => 90, 'align' => 'center'),
	'read_status' => array('label' => 'DolistoreMailReadStatus', 'checked' => 1, 'enabled' => 1, 'position' => 100, 'align' => 'center'),
);

$ordersArrayFields = array(
	'ref' => array('label' => 'Ref', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'dolistore_order_ref' => array('label' => 'DolistoreOrderRef', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'dolistore_order_date' => array('label' => 'DolistoreOrderDate', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'release_date' => array('label' => 'DolistoreReleaseDate', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'customer' => array('label' => 'DolistoreCustomerFinal', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'products' => array('label' => 'Products', 'checked' => 1, 'enabled' => 1, 'position' => 60),
	'billable_total_ht' => array('label' => 'AmountHT', 'checked' => 1, 'enabled' => 1, 'position' => 70, 'align' => 'right'),
	'status' => array('label' => 'Status', 'checked' => 1, 'enabled' => 1, 'position' => 80, 'align' => 'center'),
	'invoiceable' => array('label' => 'DolistoreInvoiceable', 'checked' => 1, 'enabled' => 1, 'position' => 90, 'align' => 'center'),
	'invoice_ref' => array('label' => 'DolistoreLinkedInvoice', 'checked' => 1, 'enabled' => 1, 'position' => 100),
	'entity' => array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 110),
);

$selectedFieldsPending = $form->multiSelectArrayWithCheckbox('selectedfields_pendingorders', $pendingArrayFields, $pendingContextPage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));
$selectedFieldsOrders = $form->multiSelectArrayWithCheckbox('selectedfields_orders', $ordersArrayFields, $ordersContextPage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN'));

$pendingSearchFolder = GETPOST('search_pending_folder', 'alphanohtml');
$pendingSearchRef = GETPOST('search_pending_ref', 'alphanohtml');
$pendingSearchCustomer = GETPOST('search_pending_customer', 'alphanohtml');
$pendingSearchEmail = GETPOST('search_pending_email', 'alphanohtml');
$pendingSearchLang = GETPOST('search_pending_lang', 'alphanohtml');
$pendingSearchRead = GETPOST('search_pending_read', 'alpha');

$pendingOrderReader = new ActionsDolistorextract($db);
$pendingDolistoreOrders = $pendingOrderReader->fetchPendingDolistoreOrdersFromMailbox();
if (!empty($pendingOrderReader->errors)) {
	setEventMessages('', $pendingOrderReader->errors, 'warnings');
}

$filteredPendingOrders = array();
foreach ($pendingDolistoreOrders as $pendingOrder) {
	$folders = array();
	foreach (array_keys((array) ($pendingOrder['folders'] ?? array())) as $folderName) {
		if ((string) $folderName !== '') {
			$folders[] = (string) $folderName;
		}
	}
	$folderText = implode(', ', $folders);
	$unreadCount = (int) ($pendingOrder['unread_count'] ?? 0);
	$isUnread = $unreadCount > 0;

	if ($pendingSearchFolder !== '' && stripos($folderText, $pendingSearchFolder) === false) {
		continue;
	}
	if ($pendingSearchRef !== '' && stripos((string) ($pendingOrder['order_ref'] ?? ''), $pendingSearchRef) === false) {
		continue;
	}
	if ($pendingSearchCustomer !== '' && stripos((string) ($pendingOrder['customer_name'] ?? ''), $pendingSearchCustomer) === false) {
		continue;
	}
	if ($pendingSearchEmail !== '' && stripos((string) ($pendingOrder['customer_email'] ?? ''), $pendingSearchEmail) === false) {
		continue;
	}
	if ($pendingSearchLang !== '' && stripos((string) ($pendingOrder['lang'] ?? ''), $pendingSearchLang) === false) {
		continue;
	}
	if ($pendingSearchRead === 'unread' && !$isUnread) {
		continue;
	}
	if ($pendingSearchRead === 'read' && $isUnread) {
		continue;
	}

	$pendingOrder['_folder_text'] = $folderText;
	$filteredPendingOrders[] = $pendingOrder;
}

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
if (!$resql) {
	setEventMessages($db->lasterror(), null, 'errors');
}

llxHeader('', $langs->trans('DolistoreOrders'));

print load_fiche_titre($langs->trans('DolistorePendingOrdersFromMailbox'), '', 'email');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" value="">';
print '<input type="hidden" name="column_contextpage" value="'.dol_escape_htmltag($pendingContextPage).'">';
print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'folder')) print '<td><input type="text" class="flat maxwidth100" name="search_pending_folder" value="'.dol_escape_htmltag($pendingSearchFolder).'"></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_date')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_id')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'order_ref')) print '<td><input type="text" class="flat maxwidth100" name="search_pending_ref" value="'.dol_escape_htmltag($pendingSearchRef).'"></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'lang')) print '<td><input type="text" class="flat maxwidth75" name="search_pending_lang" value="'.dol_escape_htmltag($pendingSearchLang).'"></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_name')) print '<td><input type="text" class="flat maxwidth150" name="search_pending_customer" value="'.dol_escape_htmltag($pendingSearchCustomer).'"></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_email')) print '<td><input type="text" class="flat maxwidth150" name="search_pending_email" value="'.dol_escape_htmltag($pendingSearchEmail).'"></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'contact_name')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'mail_count')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'read_status')) print '<td>'.$form->selectarray('search_pending_read', array('read' => $langs->trans('DolistoreMailRead'), 'unread' => $langs->trans('DolistoreMailUnread')), $pendingSearchRead, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
print '<td class="right">';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button> ';
print '<a class="button button_search" href="'.$_SERVER['PHP_SELF'].'">'.img_picto($langs->trans('RemoveFilter'), 'searchclear').'</a> ';
print $selectedFieldsPending;
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'folder')) print '<th>'.$langs->trans('DolistoreEmailFolder').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_date')) print '<th>'.$langs->trans('DolistoreEmailDate').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_id')) print '<th>'.$langs->trans('ID').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'order_ref')) print '<th>'.$langs->trans('DolistoreOrderRef').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'lang')) print '<th>'.$langs->trans('Language').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_name')) print '<th>'.$langs->trans('DolistoreCustomerFinal').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_email')) print '<th>'.$langs->trans('EMail').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'contact_name')) print '<th>'.$langs->trans('Contact').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'mail_count')) print '<th class="center">'.$langs->trans('DolistorePendingMailCount').'</th>';
if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'read_status')) print '<th class="center">'.$langs->trans('DolistoreMailReadStatus').'</th>';
print '<th>'.$langs->trans('Actions').'</th>';
print '</tr>';

if (empty($filteredPendingOrders)) {
	dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($pendingArrayFields, 1));
} else {
	foreach ($filteredPendingOrders as $pendingOrder) {
		$emailDate = '';
		if (!empty($pendingOrder['email_date'])) {
			$emailDate = dol_print_date((int) $pendingOrder['email_date'], 'dayhour');
		} elseif (!empty($pendingOrder['email_date_raw'])) {
			$emailDate = dol_escape_htmltag((string) $pendingOrder['email_date_raw']);
		}

		$lang = (string) ($pendingOrder['lang'] ?? '');
		$langDisplay = $lang !== '' ? picto_from_langcode($lang).' '.dol_escape_htmltag($lang) : '';
		$unreadCount = (int) ($pendingOrder['unread_count'] ?? 0);
		$readStatus = $unreadCount > 0 ? $langs->trans('DolistoreMailUnreadCount', $unreadCount) : $langs->trans('DolistoreMailRead');
		$messageId = (int) ($pendingOrder['msgno'] ?? 0);
		$messageUid = (int) ($pendingOrder['uid'] ?? 0);
		$urlView = '';
		if ($messageId > 0 || $messageUid > 0) {
			$urlView = dol_buildpath('/dolistorextract/mails.php', 1);
			$urlView .= '?action=read&id='.$messageId;
			$urlView .= '&uid='.$messageUid;
			$urlView .= '&folder='.urlencode((string) ($pendingOrder['folder'] ?? ''));
		}

		print '<tr class="oddeven">';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'folder')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['_folder_text'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_date')) print '<td>'.$emailDate.'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'email_id')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['email_id'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'order_ref')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['order_ref'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'lang')) print '<td>'.$langDisplay.'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_name')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['customer_name'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'customer_email')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['customer_email'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'contact_name')) print '<td>'.dol_escape_htmltag((string) ($pendingOrder['contact_name'] ?? '')).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'mail_count')) print '<td class="center">'.(int) ($pendingOrder['mail_count'] ?? 0).'</td>';
		if (dolistoreextractArrayFieldChecked($pendingArrayFields, 'read_status')) print '<td class="center">'.dol_escape_htmltag($readStatus).'</td>';
		print '<td>'.($urlView !== '' ? '<a class="button small" href="'.dol_escape_htmltag($urlView).'">'.$langs->trans('View').'</a>' : '').'</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';
print '</form>';
print '<br>';

print_barre_liste($langs->trans('DolistoreOrders'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'dolistore@dolistorextract', 0, '', '', $limit);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" value="">';
print '<input type="hidden" name="column_contextpage" value="'.dol_escape_htmltag($ordersContextPage).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'ref')) print '<td><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_ref')) print '<td><input type="text" class="flat maxwidth100" name="search_dolistore_ref" value="'.dol_escape_htmltag($search_dolistore_ref).'"></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_date')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'release_date')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'customer')) print '<td><input type="text" class="flat maxwidth150" name="search_customer" value="'.dol_escape_htmltag($search_customer).'"></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'products')) print '<td><input type="text" class="flat maxwidth150" name="search_product" value="'.dol_escape_htmltag($search_product).'"></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'billable_total_ht')) print '<td></td>';
$statusOptions = array(
	DolistoreOrder::STATUS_DRAFT => $langs->trans('DolistoreOrderStatusDraft'),
	DolistoreOrder::STATUS_IMPORTED => $langs->trans('DolistoreOrderStatusImported'),
	DolistoreOrder::STATUS_WAITING_RELEASE => $langs->trans('DolistoreOrderStatusWaitingRelease'),
	DolistoreOrder::STATUS_INVOICEABLE => $langs->trans('DolistoreOrderStatusInvoiceable'),
	DolistoreOrder::STATUS_INVOICED => $langs->trans('DolistoreOrderStatusInvoiced'),
	DolistoreOrder::STATUS_ERROR => $langs->trans('DolistoreOrderStatusError'),
);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'status')) print '<td>'.$form->selectarray('search_status', $statusOptions, $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth125').'</td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoiceable')) print '<td>'.$form->selectarray('search_invoiceable', array('yes' => $langs->trans('Yes'), 'no' => $langs->trans('No')), $search_invoiceable, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100').'</td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoice_ref')) print '<td></td>';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'entity')) print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $search_entity, 0, 0, 'minwidth100 maxwidth200').'</td>';
print '<td class="right">';
print '<button class="liste_titre button_search" type="submit" name="button_search" value="x">'.img_picto($langs->trans('Search'), 'search').'</button> ';
print '<a class="button button_search" href="'.$_SERVER['PHP_SELF'].'">'.img_picto($langs->trans('RemoveFilter'), 'searchclear').'</a> ';
print $selectedFieldsOrders;
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'ref')) print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'o.ref', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_ref')) print_liste_field_titre('DolistoreOrderRef', $_SERVER['PHP_SELF'], 'o.dolistore_order_ref', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_date')) print_liste_field_titre('DolistoreOrderDate', $_SERVER['PHP_SELF'], 'o.dolistore_order_date', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'release_date')) print_liste_field_titre('DolistoreReleaseDate', $_SERVER['PHP_SELF'], 'o.release_date', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'customer')) print_liste_field_titre('DolistoreCustomerFinal', $_SERVER['PHP_SELF'], 'o.customer_name', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'products')) print_liste_field_titre('Products', $_SERVER['PHP_SELF'], '', $param);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'billable_total_ht')) print_liste_field_titre('AmountHT', $_SERVER['PHP_SELF'], 'o.billable_total_ht', $param, '', 'align="right"', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'status')) print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'o.status', $param, '', 'align="center"', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoiceable')) print_liste_field_titre('DolistoreInvoiceable', $_SERVER['PHP_SELF'], '', $param, '', 'align="center"');
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoice_ref')) print_liste_field_titre('DolistoreLinkedInvoice', $_SERVER['PHP_SELF'], 'f.ref', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'entity')) print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 'o.entity', $param, '', '', $sortfield, $sortorder);
print '<td></td>';
print '</tr>';

$rowCount = 0;
$totalBillableHt = 0;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if ($limit > 0 && $rowCount >= $limit) {
			break;
		}
		$objectstatic->id = (int) $obj->rowid;
		$objectstatic->ref = (string) $obj->ref;
		$objectstatic->status = (int) $obj->status;
		$objectstatic->release_date = !empty($obj->release_date) ? $db->jdate($obj->release_date) : 0;
		$objectstatic->fk_facture = (int) $obj->fk_facture;
		$totalBillableHt += (float) $obj->billable_total_ht;
		$rowCount++;

		print '<tr class="oddeven">';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'ref')) print '<td>'.$objectstatic->getNomUrl(1).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_ref')) print '<td>'.dol_escape_htmltag($obj->dolistore_order_ref).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'dolistore_order_date')) print '<td>'.(!empty($obj->dolistore_order_date) ? dol_print_date($db->jdate($obj->dolistore_order_date), 'day') : '').'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'release_date')) print '<td>'.(!empty($obj->release_date) ? dol_print_date($db->jdate($obj->release_date), 'day') : '').'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'customer')) print '<td>'.dol_escape_htmltag($obj->customer_name).'<br><span class="opacitymedium">'.dol_escape_htmltag($obj->customer_email).'</span></td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'products')) print '<td>'.dol_escape_htmltag($obj->products).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'billable_total_ht')) print '<td class="right">'.price($obj->billable_total_ht).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'status')) print '<td class="center">'.$objectstatic->getLibStatut(5).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoiceable')) print '<td class="center">'.($objectstatic->isInvoiceable() ? yn(1) : yn(0)).'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'invoice_ref')) print '<td>'.(!empty($obj->fk_facture) ? '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $obj->fk_facture.'">'.dol_escape_htmltag($obj->invoice_ref).'</a>' : '').'</td>';
		if (dolistoreextractArrayFieldChecked($ordersArrayFields, 'entity')) print '<td>'.dol_escape_htmltag($obj->entity_label ?: $obj->entity).'</td>';
		print '<td></td>';
		print '</tr>';
	}
	$db->free($resql);
}

if ($rowCount === 0) {
	dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($ordersArrayFields, 1));
} elseif (dolistoreextractArrayFieldChecked($ordersArrayFields, 'billable_total_ht')) {
	dolistoreextractPrintTotalRow($ordersArrayFields, array('billable_total_ht' => price($totalBillableHt)), 1);
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
