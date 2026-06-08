<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'companies'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$object = new DolistoreOrder($db);
if (GETPOST('id', 'int') <= 0 || $object->fetch(GETPOST('id', 'int')) <= 0) accessforbidden();

llxHeader('', $langs->trans('ContactsAddresses'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'contacts', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '', 1, 'ref', 'ref', '');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Type').'</th><th>'.$langs->trans('Name').'</th><th>'.$langs->trans('Email').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('ThirdParty').'</td><td>';
if (!empty($object->fk_soc_customer)) {
	$soc = new Societe($db);
	if ($soc->fetch($object->fk_soc_customer) > 0) print $soc->getNomUrl(1);
} else {
	print dol_escape_htmltag($object->customer_name);
}
print '</td><td>'.dol_escape_htmltag($object->customer_email).'</td></tr>';
if (!empty($object->fk_contact_customer)) {
	$contact = new Contact($db);
	if ($contact->fetch($object->fk_contact_customer) > 0) {
		print '<tr class="oddeven"><td>'.$langs->trans('Contact').'</td><td>'.$contact->getNomUrl(1).'</td><td>'.dol_escape_htmltag($contact->email).'</td></tr>';
	}
}
print '</table>';
print dol_get_fiche_end();
llxFooter();
$db->close();
