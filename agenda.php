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

$langs->loadLangs(array('dolistorextract@dolistorextract', 'agenda'));

if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

$title = $langs->trans('DolistoreOrder').' - '.$langs->trans('EventsAgenda');
llxHeader('', $title);

print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'agenda', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');

if (isModEnabled('agenda')) {
	$formactions = new FormActions($db);
	print '<div class="fichecenter">';
	$morehtmlcenter = '';
	$formactions->showactions($object, 'dolistoreextract_order@dolistorextract', 0, 0, '', $conf->liste_limit, '', $morehtmlcenter);
	print '</div>';
} else {
	print '<div class="opacitymedium">'.$langs->trans('ModuleDisabled', $langs->transnoentitiesnoconv('Agenda')).'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
