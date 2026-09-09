<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'agenda', 'admin'));

if (!isModEnabled('dolistorextract') || !empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'read'))) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}


if (!isModEnabled('agenda') || (empty($user->admin) && !$user->hasRight('agenda', 'myactions', 'read'))) accessforbidden();
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
$form = new Form($db);
$event = new ActionComm($db);
$owner = new User($db);
$contact = new Contact($db);
$action = GETPOST('action', 'aZ09');
$hookmanager->initHooks(array('dolistoreextractagendalist'));
$arrayfields = array();
foreach (array('a.id' => 'Ref', 'a.datep' => 'DateStart', 'u.lastname' => 'Owner', 'c.code' => 'Type', 'a.label' => 'Label', 'sp.lastname' => 'Contact', 'a.fk_element' => 'LinkedObject', 'a.percent' => 'Status') as $field => $label) {
	$arrayfields[$field] = array('label' => $label, 'checked' => 1, 'enabled' => 1, 'position' => count($arrayfields) * 10);
}
$contextpage = 'dolistoreextractagendalist';
$selectedfields = dolistoreextractPrepareSelectedFields($form, $contextpage, 'selectedfields', $arrayfields);
$actionColumnLeft = !empty($conf->main_checkbox_left_column) || getDolGlobalInt('MAIN_CHECKBOX_LEFT_COLUMN');
$limit = GETPOSTINT('limit') > 0 ? min(GETPOSTINT('limit'), 1000) : $conf->liste_limit;
$page = max(0, GETPOSTINT('page'));
$sortfield = GETPOST('sortfield', 'aZ09comma');
if (!isset($arrayfields[$sortfield])) $sortfield = 'a.datep';
$sortorder = GETPOST('sortorder', 'aZ09') === 'ASC' ? 'ASC' : 'DESC';
$search_label = trim(GETPOST('search_label', 'alphanohtml'));
$search_status = trim(GETPOST('search_status', 'alpha'));
if (!in_array($search_status, array('todo', 'progress', 'done', 'na'), true)) $search_status = '';
$search_start = dolistoreextractGetDateFilter('search_start');
$search_end = dolistoreextractGetDateFilter('search_end', '', 23, 59, 59);
if (GETPOSTISSET('button_search') || GETPOSTISSET('button_search_x')) $page = 0;
if (GETPOSTISSET('button_removefilter') || GETPOSTISSET('button_removefilter_x')) {
	$search_label = $search_status = ''; $search_start = $search_end = 0; $page = 0;
}
$where = ' WHERE a.fk_element = '.(int) $object->id." AND a.elementtype = 'dolistoreextract_order@dolistorextract'";
$where .= ' AND a.entity IN ('.$db->sanitize(getEntity('agenda')).')';
if ((!isModEnabled('agenda') || (empty($user->admin) && !$user->hasRight('agenda', 'allactions', 'read')))) {
	$where .= ' AND (a.fk_user_action = '.(int) $user->id.' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX."actioncomm_resources ar WHERE ar.fk_actioncomm = a.id AND ar.element_type = 'user' AND ar.fk_element = ".(int) $user->id.'))';
}
if ($search_label !== '') $where .= ' AND '.natural_search('a.label', $search_label, 0, 1);
if ($search_start) $where .= " AND a.datep >= '".$db->idate($search_start)."'";
if ($search_end) $where .= " AND a.datep <= '".$db->idate($search_end)."'";
if ($search_status === 'todo') $where .= ' AND a.percent = 0';
if ($search_status === 'progress') $where .= ' AND a.percent > 0 AND a.percent < 100';
if ($search_status === 'done') $where .= ' AND a.percent = 100';
if ($search_status === 'na') $where .= ' AND a.percent = -1';
$from = ' FROM '.MAIN_DB_PREFIX.'actioncomm a LEFT JOIN '.MAIN_DB_PREFIX.'c_actioncomm c ON c.id = a.fk_action';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid = a.fk_user_action AND u.entity IN (0,'.$db->sanitize(getEntity('user')).')';
$from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'socpeople sp ON sp.rowid = a.fk_contact AND sp.entity IN ('.$db->sanitize(getEntity('contact')).')';
$num = 0;
$count = $db->query('SELECT COUNT(*) AS total'.$from.$where);
if ($count) { $row = $db->fetch_object($count); if (is_object($row)) $num = (int) $row->total; $db->free($count); }
if ($page * $limit >= $num) $page = 0;
$sql = 'SELECT a.*, c.code AS type_code, c.libelle AS type_label, c.type AS type_type, c.color AS type_color, c.picto AS type_picto, u.rowid AS owner_id, u.login, u.firstname AS owner_firstname, u.lastname AS owner_lastname, sp.rowid AS contact_id, sp.firstname AS contact_firstname, sp.lastname AS contact_lastname'.$from.$where.$db->order($sortfield, $sortorder).$db->plimit($limit, $page * $limit);
$resql = $db->query($sql);
if (!$resql) setEventMessages($db->lasterror(), null, 'errors');
$param = '&limit='.(int) $limit.'&id='.(int) $object->id.'&search_label='.urlencode($search_label).'&search_status='.urlencode($search_status);
dolistoreextractAppendDateFilterParam($param, 'search_start', $search_start);
dolistoreextractAppendDateFilterParam($param, 'search_end', $search_end);
llxHeader('', $langs->trans('Module2400Name'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'agenda', $langs->trans('DolistoreOrder'), -1, $object->picto);
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
print dol_get_fiche_end();
print '<form method="POST" id="dolistoreagendafilter" action="'.$_SERVER['PHP_SELF'].'">';
foreach (array('token' => newToken(), 'id' => $object->id, 'sortfield' => $sortfield, 'sortorder' => $sortorder, 'column_contextpage' => $contextpage, 'formfilteraction' => '') as $name => $value) print '<input type="hidden" name="'.$name.'" value="'.dol_escape_htmltag($value).'">';
print_barre_liste($langs->trans('Module2400Name'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $num, 'action', 0, '', '', $limit);
print '<div class="div-table-responsive-no-min"><table id="dolistoreagenda" class="tagtable liste centpercent"><tr class="liste_titre_filter">';
if ($actionColumnLeft) print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
$statusOptions = array('todo' => $event->LibStatut(0, 0), 'progress' => $event->LibStatut(50, 0), 'done' => $event->LibStatut(100, 0), 'na' => $event->LibStatut(-1, 0));
foreach ($arrayfields as $field => $definition) {
	if (!dolistoreextractArrayFieldChecked($arrayfields, $field)) continue;
	print '<td>';
	if ($field === 'a.label') print '<input class="flat maxwidth150" name="search_label" value="'.dol_escape_htmltag($search_label).'">';
	if ($field === 'a.percent') print $form->selectarray('search_status', $statusOptions, $search_status, 1);
	if ($field === 'a.datep') {
		print '<div class="nowrap">'.$form->selectDate($search_start ?: '', 'search_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From')).'</div>';
		print '<div class="nowrap">'.$form->selectDate($search_end ?: '', 'search_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to')).'</div>';
	}
	print '</td>';
}
if (!$actionColumnLeft) print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
print '</tr><tr class="liste_titre">';
if ($actionColumnLeft) print_liste_field_titre($selectedfields, $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'center maxwidthsearch');
foreach ($arrayfields as $field => $definition) if (dolistoreextractArrayFieldChecked($arrayfields, $field)) print_liste_field_titre($definition['label'], $_SERVER['PHP_SELF'], $field, $param, '', '', $sortfield, $sortorder);
if (!$actionColumnLeft) print_liste_field_titre($selectedfields, $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center');
print '</tr>';
$shown = 0;
while ($resql && is_object($row = $db->fetch_object($resql))) {
	$shown++;
	$event->id = $row->id; $event->ref = $row->ref ?: (string) $row->id; $event->label = $row->label;
	$event->type_code = $row->type_code; $event->type_label = $row->type_label; $event->type = $row->type_type;
	$event->type_color = $row->type_color; $event->type_picto = $row->type_picto; $event->code = $row->code;
	$event->datep = $db->jdate($row->datep); $event->percentage = $row->percent; $event->userownerid = $row->fk_user_action;
	$owner->id = (int) $row->owner_id; $owner->login = $row->login; $owner->firstname = $row->owner_firstname; $owner->lastname = $row->owner_lastname;
	$contact->id = (int) $row->contact_id; $contact->firstname = $row->contact_firstname; $contact->lastname = $row->contact_lastname;
	$cells = array('a.id' => $event->getNomUrl(1, -1), 'a.datep' => dol_print_date($event->datep, 'dayhour'), 'u.lastname' => $owner->id > 0 ? $owner->getNomUrl(1) : '', 'c.code' => $event->getTypePicto().dol_escape_htmltag($event->getTypeLabel()), 'a.label' => dol_escape_htmltag($row->label), 'sp.lastname' => $contact->id > 0 && (isModEnabled('societe') && (!empty($user->admin) || $user->hasRight('societe', 'contact', 'lire'))) ? $contact->getNomUrl(1) : '', 'a.fk_element' => $object->getNomUrl(1), 'a.percent' => $event->getLibStatut(5));
	print '<tr class="oddeven">';
	if ($actionColumnLeft) print '<td></td>';
	foreach ($arrayfields as $field => $definition) if (dolistoreextractArrayFieldChecked($arrayfields, $field)) print '<td>'.$cells[$field].'</td>';
	if (!$actionColumnLeft) print '<td></td>';
	print '</tr>';
}
if ($resql) $db->free($resql);
if (!$shown) dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($arrayfields, 1));
print '</table></div></form>';
llxFooter();
$db->close();
