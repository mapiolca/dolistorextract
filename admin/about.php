<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		admin/about.php
 * \ingroup	dolistorextract
 * \brief	About page of Dolistorextract module.
 */

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
require_once __DIR__.'/../core/modules/modDolistorextract.class.php';

$langs->loadLangs(array('admin', 'dolistorextract@dolistorextract'));

if (empty($user->admin)) {
	accessforbidden();
}

$moduleDescriptor = new modDolistorextract($db);
$title = $langs->trans('DolistorextractAbout');

llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'info');

$head = dolistorextractAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, -1, 'dolistore@dolistorextract');

print '<div class="underbanner opacitymedium">'.$langs->trans('DolistorextractAboutPage').'</div>';
print '<br>';
print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('DolistorextractAboutGeneral').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutVersion').'</td><td>'.dol_escape_htmltag($moduleDescriptor->version).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutFamily').'</td><td>'.dol_escape_htmltag($moduleDescriptor->family).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutDescription').'</td><td>'.dol_escape_htmltag($moduleDescriptor->description).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutMaintainer').'</td><td>'.dol_escape_htmltag($moduleDescriptor->editor_name).'</td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '<div class="fichehalfright">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('DolistorextractAboutResources').'</th></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutDocumentation').'</td><td><a href="'.dol_buildpath('/dolistorextract/COPYING', 1).'" target="_blank" rel="noopener">'.$langs->trans('DolistorextractAboutDocumentationLink').'</a></td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutSupport').'</td><td>'.dol_escape_htmltag($langs->trans('DolistorextractAboutSupportValue')).'</td></tr>';
print '<tr class="oddeven"><td class="titlefield">'.$langs->trans('DolistorextractAboutContact').'</td><td><a href="'.$moduleDescriptor->editor_url.'" target="_blank" rel="noopener">'.dol_escape_htmltag($moduleDescriptor->editor_url).'</a></td></tr>';
print '</table>';
print '</div>';
print '</div>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
