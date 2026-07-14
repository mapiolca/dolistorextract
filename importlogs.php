<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'other'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$form = new Form($db);
$contextpage = 'dolistoreextractimportlogslist';

$arrayfields = array(
	'datec' => array('label' => 'Date', 'checked' => 1, 'enabled' => 1, 'position' => 10),
	'source' => array('label' => 'Source', 'checked' => 1, 'enabled' => 1, 'position' => 20),
	'level' => array('label' => 'Level', 'checked' => 1, 'enabled' => 1, 'position' => 30),
	'order_ref' => array('label' => 'DolistoreOrder', 'checked' => 1, 'enabled' => 1, 'position' => 40),
	'message' => array('label' => 'Message', 'checked' => 1, 'enabled' => 1, 'position' => 50),
	'entity' => array('label' => 'Environment', 'checked' => 1, 'enabled' => 1, 'position' => 60),
);
$selectedfields = dolistoreextractPrepareSelectedFields($form, $contextpage, 'selectedfields_importlogs', $arrayfields);

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortfield) $sortfield = 'l.datec,l.rowid';
if (!$sortorder) $sortorder = 'DESC,DESC';
$page = GETPOST('page', 'int');
if ($page < 0) $page = 0;
$limit = $conf->liste_limit;
$offset = $limit * $page;

$search_date_start = dolistoreextractGetDateFilter('search_date_start', 'search_date_start', 0, 0, 0);
$search_date_end = dolistoreextractGetDateFilter('search_date_end', 'search_date_end', 23, 59, 59);
$search_source = GETPOST('search_source', 'alphanohtml');
$search_level = GETPOST('search_level', 'alphanohtml');
$search_order = GETPOST('search_order', 'alphanohtml');
$search_message = GETPOST('search_message', 'restricthtml');
$search_entity = GETPOST('search_entity', 'array');
if (!is_array($search_entity)) $search_entity = array();

$entityOptions = array();
$resqlEntities = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.getEntity('dolistoreextract_order').') ORDER BY label ASC');
if ($resqlEntities) {
	while ($objEntity = $db->fetch_object($resqlEntities)) {
		$entityOptions[(int) $objEntity->rowid] = (string) $objEntity->label;
	}
	$db->free($resqlEntities);
}
if (empty($entityOptions)) $entityOptions[(int) $conf->entity] = (string) $conf->entity;

$where = array('l.entity IN ('.getEntity('dolistoreextract_order').')');
if (!empty($search_date_start)) {
	$where[] = "l.datec >= '".$db->escape(dol_print_date($search_date_start, '%Y-%m-%d'))." 00:00:00'";
}
if (!empty($search_date_end)) {
	$where[] = "l.datec <= '".$db->escape(dol_print_date($search_date_end, '%Y-%m-%d'))." 23:59:59'";
}
if ($search_source !== '') {
	$where[] = natural_search('l.source', $search_source);
}
if ($search_level !== '') {
	$where[] = natural_search('l.level', $search_level);
}
if ($search_order !== '') {
	$where[] = natural_search('o.ref', $search_order);
}
if ($search_message !== '') {
	$where[] = natural_search('l.message', $search_message);
}
if (!empty($search_entity)) {
	$where[] = 'l.entity IN ('.implode(',', array_map('intval', $search_entity)).')';
}

$param = '';
dolistoreextractAppendDateFilterParam($param, 'search_date_start', $search_date_start);
dolistoreextractAppendDateFilterParam($param, 'search_date_end', $search_date_end);
foreach (array('search_source', 'search_level', 'search_order', 'search_message') as $key) {
	if (GETPOST($key, 'restricthtml') !== '') {
		$param .= '&'.$key.'='.urlencode(GETPOST($key, 'restricthtml'));
	}
}
foreach ($search_entity as $entityId) {
	$param .= '&search_entity[]='.(int) $entityId;
}

$sqlFrom = ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_import_log l';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order o ON o.rowid = l.fk_order AND o.entity = l.entity';
$sqlFrom .= ' LEFT JOIN '.MAIN_DB_PREFIX.'entity ent ON ent.rowid = l.entity';
$sqlWhere = ' WHERE '.implode(' AND ', $where);

$sqlCount = 'SELECT COUNT(l.rowid) as nb'.$sqlFrom.$sqlWhere;
$resqlCount = $db->query($sqlCount);
$num = 0;
if ($resqlCount) {
	$objCount = $db->fetch_object($resqlCount);
	$num = (int) $objCount->nb;
	$db->free($resqlCount);
}

$sql = 'SELECT l.*, o.ref as order_ref, ent.label as entity_label'.$sqlFrom.$sqlWhere.$db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if (!$resql) setEventMessages($db->lasterror(), null, 'errors');

llxHeader('', $langs->trans('DolistoreImportLogs'));
print load_fiche_titre($langs->trans('DolistoreImportLogs'), '', 'generic');
print_barre_liste($langs->trans('DolistoreImportLogs'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'generic', 0, '', '', $limit);

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="formfilteraction" value="">';
print '<input type="hidden" name="column_contextpage" value="'.dol_escape_htmltag($contextpage).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre_filter">';
if (dolistoreextractArrayFieldChecked($arrayfields, 'datec')) {
	print '<td>';
	print '<div class="nowrap">'.$form->selectDate($search_date_start ?: '', 'search_date_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From')).'</div>';
	print '<div class="nowrap">'.$form->selectDate($search_date_end ?: '', 'search_date_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to')).'</div>';
	print '</td>';
}
if (dolistoreextractArrayFieldChecked($arrayfields, 'source')) print '<td><input type="text" class="flat maxwidth100" name="search_source" value="'.dol_escape_htmltag($search_source).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'level')) print '<td><input type="text" class="flat maxwidth100" name="search_level" value="'.dol_escape_htmltag($search_level).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'order_ref')) print '<td><input type="text" class="flat maxwidth100" name="search_order" value="'.dol_escape_htmltag($search_order).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'message')) print '<td><input type="text" class="flat maxwidth300" name="search_message" value="'.dol_escape_htmltag($search_message).'"></td>';
if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print '<td>'.$form->multiselectarray('search_entity', $entityOptions, $search_entity, 0, 0, 'minwidth100 maxwidth200').'</td>';
print '<td class="right">';
print $form->showFilterButtons();
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
if (dolistoreextractArrayFieldChecked($arrayfields, 'datec')) print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'l.datec', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'source')) print_liste_field_titre('Source', $_SERVER['PHP_SELF'], 'l.source', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'level')) print_liste_field_titre('Level', $_SERVER['PHP_SELF'], 'l.level', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'order_ref')) print_liste_field_titre('DolistoreOrder', $_SERVER['PHP_SELF'], 'o.ref', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'message')) print_liste_field_titre('Message', $_SERVER['PHP_SELF'], 'l.message', $param, '', '', $sortfield, $sortorder);
if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print_liste_field_titre('Environment', $_SERVER['PHP_SELF'], 'l.entity', $param, '', '', $sortfield, $sortorder);
print_liste_field_titre($selectedfields, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>';

$rowCount = 0;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		if ($limit > 0 && $rowCount >= $limit) {
			break;
		}
		$rowCount++;
		print '<tr class="oddeven">';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'datec')) print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'source')) print '<td>'.dol_escape_htmltag($obj->source).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'level')) print '<td>'.dol_escape_htmltag($obj->level).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'order_ref')) print '<td>'.(!empty($obj->fk_order) ? '<a href="'.dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $obj->fk_order.'">'.dol_escape_htmltag($obj->order_ref).'</a>' : '').'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'message')) print '<td>'.dol_nl2br(dol_escape_htmltag($obj->message)).'</td>';
		if (dolistoreextractArrayFieldChecked($arrayfields, 'entity')) print '<td>'.dol_escape_htmltag($obj->entity_label ?: $obj->entity).'</td>';
		print '<td></td>';
		print '</tr>';
	}
	$db->free($resql);
}

if ($rowCount === 0) {
	dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($arrayfields, 1));
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
