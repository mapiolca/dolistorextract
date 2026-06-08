<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'other'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$object = new DolistoreOrder($db);
if (GETPOST('id', 'int') <= 0 || $object->fetch(GETPOST('id', 'int')) <= 0) accessforbidden();

$formfile = new FormFile($db);
llxHeader('', $langs->trans('Documents'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'documents', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
$uploadDir = dolistoreextractGetOrderUploadDir($object);
$formfile->showdocuments('dolistoreextract', $object->ref, $uploadDir, $_SERVER['PHP_SELF'].'?id='.(int) $object->id, dolistoreextractUserHasRight($user, 'order', 'write'), dolistoreextractUserHasRight($user, 'order', 'delete'), '', 1, 0, 0, 0, 0, '', '', '', $langs);
print dol_get_fiche_end();
llxFooter();
$db->close();
