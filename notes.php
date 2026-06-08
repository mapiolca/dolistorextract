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

if ($action === 'setnotes' && dolistoreextractUserHasRight($user, 'order', 'write')) {
	if (GETPOST('token', 'alphanohtml') === '') accessforbidden('Invalid token');
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');
	if ($object->update($user) > 0) setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	else setEventMessages($object->error, $object->errors, 'errors');
}

llxHeader('', $langs->trans('Notes'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'notes', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');

if (dolistoreextractUserHasRight($user, 'order', 'write')) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="setnotes">';
}
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('NotePublic').'</td><td><textarea class="flat centpercent" name="note_public" rows="6">'.dol_escape_htmltag($object->note_public).'</textarea></td></tr>';
print '<tr><td>'.$langs->trans('NotePrivate').'</td><td><textarea class="flat centpercent" name="note_private" rows="8">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
print '</table>';
if (dolistoreextractUserHasRight($user, 'order', 'write')) {
	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans('Save').'"></div>';
	print '</form>';
}
print dol_get_fiche_end();
llxFooter();
$db->close();
