<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = '';
if (file_exists('../../main.inc.php')) {
	$res = include '../../main.inc.php';
} elseif (!$res && file_exists('../../../main.inc.php')) {
	$res = include '../../../main.inc.php';
} else {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/dolistorextract.lib.php';
require_once __DIR__.'/../class/dolistorextractCompatibility.class.php';

$langs->loadLangs(array('admin', 'dolistorextract@dolistorextract'));

if (empty($user->admin) && !dolistoreextractUserHasRight($user, 'setup', 'write')) {
	accessforbidden();
}

$title = $langs->trans('Compatibility');
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword='.urlencode('dolistorextract').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'technic');

$head = dolistorextractAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('DolistorextractSetup'), -1, 'dolistore@dolistorextract');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('DolistoreCompatibilityEnvironment').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistoreCompatibilityPhpDetected').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreCompatibilityDolibarrDetected').'</td><td>'.(defined('DOL_VERSION') ? dol_escape_htmltag(DOL_VERSION) : '').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreCompatibilityPhpMin').'</td><td>'.DolistoreextractCompatibility::MIN_PHP.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('DolistoreCompatibilityDolibarrMin').'</td><td>'.DolistoreextractCompatibility::MIN_DOLIBARR.'</td></tr>';
print '</table><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Feature').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('Reason').'</th></tr>';
foreach (DolistoreextractCompatibility::getFeatures() as $feature) {
	$available = !empty($feature['available']);
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['label'])).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans($feature['description'])).'</td>';
	print '<td>'.($available ? '<span class="badge badge-status status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status status9">'.$langs->trans('Unavailable').'</span>').'</td>';
	print '<td>'.($available ? '' : dol_escape_htmltag($langs->trans($feature['reason']))).'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
