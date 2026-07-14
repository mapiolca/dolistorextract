<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'other'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$object = new DolistoreOrder($db);
if (GETPOST('id', 'int') <= 0 || $object->fetch(GETPOST('id', 'int')) <= 0) accessforbidden();

llxHeader('', $langs->trans('DolistoreMailSource'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'mail', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Subject').'</td><td>'.dol_escape_htmltag($object->email_subject).'</td></tr>';
print '<tr><td>'.$langs->trans('Date').'</td><td>'.($object->email_date ? dol_print_date($object->email_date, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreEmailMessageId').'</td><td>'.dol_escape_htmltag($object->email_message_id).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreImapFolder').'</td><td>'.dol_escape_htmltag($object->email_folder).'</td></tr>';
print '<tr><td>'.$langs->trans('DolistoreRawHash').'</td><td><span class="opacitymedium">'.dol_escape_htmltag($object->raw_hash).'</span></td></tr>';
print '</table><br>';
print '<div class="info">'.$langs->trans('DolistoreMailSourceStoredAsDocument').'</div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
