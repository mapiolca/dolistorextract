<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare admin pages header.
 *
 * @return array<int,array<int,string>>
 */
function dolistorextractAdminPrepareHead()
{
	global $langs;

	$langs->load('dolistorextract@dolistorextract');

	$head = array();
	$h = 0;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php?mode=orders', 1);
	$head[$h][1] = $langs->trans('DolistoreOrdersSetup');
	$head[$h][2] = 'orders';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php?mode=billing', 1);
	$head[$h][1] = $langs->trans('DolistoreBilling');
	$head[$h][2] = 'billing';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php?mode=emailsimap', 1);
	$head[$h][1] = $langs->trans('DolistorextractEmailsImap');
	$head[$h][2] = 'emailsimap';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/compatibility.php', 1);
	$head[$h][1] = $langs->trans('Compatibility');
	$head[$h][2] = 'compatibility';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/about.php', 1);
	$head[$h][1] = $langs->trans('DolistorextractAbout');
	$head[$h][2] = 'about';

	return $head;
}

/**
 * Prepare DoliStore order tabs.
 *
 * @param DolistoreOrder $object Order
 * @return array<int,array<int,string>>
 */
function dolistoreextractOrderPrepareHead($object)
{
	global $langs;

	$langs->load('dolistorextract@dolistorextract');

	$head = array();
	$h = 0;
	$id = (int) $object->id;

	$head[$h][0] = dol_buildpath('/dolistorextract/card.php', 1).'?id='.$id;
	$head[$h][1] = img_picto('', 'order', 'class="pictofixedwidth"').' '.$langs->trans('DolistoreOrderCard');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/contacts.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('ContactsAddresses');
	$head[$h][2] = 'contacts';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/notes.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('Notes');
	$head[$h][2] = 'notes';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/documents.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreAttachedFiles');
	$documentsCount = dolistoreextractCountOrderAttachedFiles($object);
	if ($documentsCount > 0) {
		$head[$h][1] .= ' <span class="badge marginleftonlyshort">'.$documentsCount.'</span>';
	}
	$head[$h][2] = 'documents';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/mail.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreMailSource');
	$head[$h][2] = 'mail';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/billing.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreBilling');
	$head[$h][2] = 'billing';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/logs.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreJournal');
	$head[$h][2] = 'logs';

	return $head;
}

/**
 * Check module right with admin fallback.
 *
 * @param User   $user   User
 * @param string $level1 Level 1
 * @param string $level2 Level 2
 * @return bool
 */
function dolistoreextractUserHasRight($user, $level1, $level2 = '')
{
	if (!empty($user->admin)) {
		return true;
	}
	if (method_exists($user, 'hasRight')) {
		return $level2 !== '' ? (bool) $user->hasRight('dolistorextract', $level1, $level2) : (bool) $user->hasRight('dolistorextract', $level1);
	}
	if ($level2 !== '') {
		return !empty($user->rights->dolistorextract->{$level1}->{$level2});
	}

	return !empty($user->rights->dolistorextract->{$level1});
}

/**
 * Return document directory for a DoliStore order.
 *
 * @param DolistoreOrder $object Order
 * @return string
 */
function dolistoreextractGetOrderUploadDir($object)
{
	global $conf;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	$moduleOutput = getMultidirOutput($object, 'dolistorextract', 0);
	if (empty($moduleOutput) || strpos($moduleOutput, 'error-') === 0) {
		$moduleOutput = !empty($conf->dolistorextract->multidir_output[$objectEntity])
			? $conf->dolistorextract->multidir_output[$objectEntity]
			: $conf->dolistorextract->dir_output;
	}

	return rtrim($moduleOutput, '/').'/'.$object->element.'/'.dol_sanitizeFileName($object->ref);
}

/**
 * Return native document context for a DoliStore order.
 *
 * @param DolistoreOrder $object Order
 * @return array{modulepart_card:string,modulepart_files:string,modulesubdir:string,upload_dir:string}
 */
function dolistoreextractGetOrderDocumentContext($object)
{
	$ref = dol_sanitizeFileName($object->ref);
	$modulesubdir = $object->element.'/'.$ref;

	return array(
		'modulepart_card' => 'dolistoreextract:DolistoreOrder',
		'modulepart_files' => 'dolistoreextract',
		'modulesubdir' => $modulesubdir,
		'upload_dir' => dolistoreextractGetOrderUploadDir($object),
	);
}

/**
 * Count attached files and links for a DoliStore order.
 *
 * @param DolistoreOrder $object Order
 * @return int
 */
function dolistoreextractCountOrderAttachedFiles($object)
{
	global $db;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

	$uploadDir = dolistoreextractGetOrderUploadDir($object);
	$filearray = dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'name', SORT_ASC, 1);
	$linkCount = Link::count($db, $object->element, (int) $object->id);
	if ($linkCount < 0) {
		$linkCount = 0;
	}

	return count($filearray) + (int) $linkCount;
}

/**
 * Persist selected columns for a module list table.
 *
 * @param string $contextpage Context key used by Dolibarr user preferences
 * @param string $htmlname    Hidden input name generated by multiSelectArrayWithCheckbox
 * @return void
 */
function dolistoreextractSaveSelectedFields($contextpage, $htmlname)
{
	global $db, $conf, $user;

	if (GETPOST('formfilteraction', 'alphanohtml') !== 'listafterchangingselectedfields') {
		return;
	}
	if (GETPOST('column_contextpage', 'aZ09') !== $contextpage) {
		return;
	}

	$selectedFields = GETPOST($htmlname, 'aZ09comma');
	$paramName = 'MAIN_SELECTEDFIELDS_'.$contextpage;
	$tabparam = array($paramName => $selectedFields);

	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	dol_set_user_param($db, $conf, $user, $tabparam);
	$user->conf->{$paramName} = $selectedFields;
}

/**
 * Return true when an arrayfield is visible.
 *
 * @param array<string,array<string,mixed>> $arrayfields List arrayfields
 * @param string                           $key         Field key
 * @return bool
 */
function dolistoreextractArrayFieldChecked($arrayfields, $key)
{
	return !empty($arrayfields[$key]['checked']);
}

/**
 * Count visible columns.
 *
 * @param array<string,array<string,mixed>> $arrayfields List arrayfields
 * @param int                              $extra        Extra columns not declared in arrayfields
 * @return int
 */
function dolistoreextractVisibleColumnCount($arrayfields, $extra = 0)
{
	$count = (int) $extra;
	foreach ($arrayfields as $val) {
		if (!empty($val['checked'])) {
			$count++;
		}
	}

	return $count;
}

/**
 * Print native empty list row.
 *
 * @param int $colspan Number of visible columns
 * @return void
 */
function dolistoreextractPrintNoRecordLine($colspan)
{
	global $langs;

	print '<tr class="oddeven"><td colspan="'.((int) $colspan).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

/**
 * Print a native total row.
 *
 * @param array<string,array<string,mixed>> $arrayfields List arrayfields
 * @param array<string,string>              $totals      HTML totals indexed by field key
 * @param int                              $extra        Extra empty columns not declared in arrayfields
 * @return void
 */
function dolistoreextractPrintTotalRow($arrayfields, $totals, $extra = 0)
{
	global $langs;

	print '<tr class="liste_total">';
	$labelPrinted = false;
	foreach ($arrayfields as $key => $val) {
		if (empty($val['checked'])) {
			continue;
		}
		$class = !empty($val['align']) ? ' class="'.$val['align'].'"' : '';
		print '<td'.$class.'>';
		if (array_key_exists($key, $totals)) {
			print $totals[$key];
		} elseif (!$labelPrinted) {
			print $langs->trans('Total');
			$labelPrinted = true;
		}
		print '</td>';
	}
	for ($i = 0; $i < $extra; $i++) {
		print '<td></td>';
	}
	print '</tr>';
}
