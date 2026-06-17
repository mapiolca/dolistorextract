<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'companies'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden();

$permissionnote = dolistoreextractUserHasRight($user, 'order', 'write');
$permissiontoadd = $permissionnote;
include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';

llxHeader('', $langs->trans('Notes'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'notes', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
$cssclass = 'titlefield';
include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';
print '</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
