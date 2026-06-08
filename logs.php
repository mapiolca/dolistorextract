<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/class/dolistoreImportLog.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'other'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$object = new DolistoreOrder($db);
if (GETPOST('id', 'int') <= 0 || $object->fetch(GETPOST('id', 'int')) <= 0) accessforbidden();
$log = new DolistoreImportLog($db);

llxHeader('', $langs->trans('DolistoreJournal'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'logs', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Source').'</th><th>'.$langs->trans('Level').'</th><th>'.$langs->trans('Message').'</th></tr>';
foreach ($log->fetchAllByOrder((int) $object->id) as $entry) {
	print '<tr class="oddeven">';
	print '<td>'.dol_print_date($db->jdate($entry->datec), 'dayhour').'</td>';
	print '<td>'.dol_escape_htmltag($entry->source).'</td>';
	print '<td>'.dol_escape_htmltag($entry->level).'</td>';
	print '<td>'.dol_nl2br(dol_escape_htmltag($entry->message)).'</td>';
	print '</tr>';
}
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
