<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'companies', 'other'));

if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (empty($sortfield)) {
	$sortfield = 'name';
}
if (empty($sortorder)) {
	$sortorder = 'ASC';
}

$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

$documentContext = dolistoreextractGetOrderDocumentContext($object);
$upload_dir = $documentContext['upload_dir'];
$modulepart = $documentContext['modulepart_files'];
$permissiontoadd = dolistoreextractUserHasRight($user, 'order', 'write') || dolistoreextractUserHasRight($user, 'order', 'delete');
$permtoedit = $permissiontoadd;
$relativepathwithnofile = $documentContext['modulesubdir'];
$backtopage = $_SERVER['PHP_SELF'].'?id='.(int) $object->id;

$hookmanager->initHooks(array('dolistoreextractdocument', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

$form = new Form($db);
$formfile = new FormFile($db);

$title = $langs->trans('DolistoreOrder').' - '.$langs->trans('DolistoreAttachedFiles');
llxHeader('', $title);

print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'documents', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');

$linkback = '<a href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '');

$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (strtolower($sortorder) === 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += $file['size'];
}
$linkCount = Link::count($db, $object->element, (int) $object->id);
if ($linkCount < 0) {
	$linkCount = 0;
}

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td colspan="3">'.(count($filearray) + (int) $linkCount).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td colspan="3">'.dol_print_size($totalsize, 1, 1).'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

$permission = $permissiontoadd;
$param = '&id='.(int) $object->id;
include_once DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
