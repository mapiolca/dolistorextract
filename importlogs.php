<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'other'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

llxHeader('', $langs->trans('DolistoreImportLogs'));
print load_fiche_titre($langs->trans('DolistoreImportLogs'), '', 'generic');
print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Source').'</th><th>'.$langs->trans('Level').'</th><th>'.$langs->trans('DolistoreOrder').'</th><th>'.$langs->trans('Message').'</th></tr>';
$sql = 'SELECT l.*, o.ref as order_ref FROM '.MAIN_DB_PREFIX.'dolistoreextract_import_log l LEFT JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order o ON o.rowid = l.fk_order AND o.entity = l.entity WHERE l.entity IN ('.getEntity('dolistoreextract_order').') ORDER BY l.datec DESC, l.rowid DESC LIMIT 500';
$resql = $db->query($sql);
while ($resql && ($obj = $db->fetch_object($resql))) {
	print '<tr class="oddeven">';
	print '<td>'.dol_print_date($db->jdate($obj->datec), 'dayhour').'</td>';
	print '<td>'.dol_escape_htmltag($obj->source).'</td>';
	print '<td>'.dol_escape_htmltag($obj->level).'</td>';
	print '<td>'.(!empty($obj->fk_order) ? '<a href="'.dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $obj->fk_order.'">'.dol_escape_htmltag($obj->order_ref).'</a>' : '').'</td>';
	print '<td>'.dol_nl2br(dol_escape_htmltag($obj->message)).'</td>';
	print '</tr>';
}
if ($resql) $db->free($resql);
print '</table>';
llxFooter();
$db->close();
