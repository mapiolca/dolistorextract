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

	$langs->loadLangs(array('dolistorextract@dolistorextract', 'admin', 'agenda', 'bills', 'companies'));

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
	$head[$h][1] = $langs->trans('DolistorextractCompatibility');
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
	global $langs, $user;

	$langs->loadLangs(array('dolistorextract@dolistorextract', 'admin', 'agenda', 'bills', 'companies'));

	$head = array();
	$h = 0;
	$id = (int) $object->id;

	$head[$h][0] = dol_buildpath('/dolistorextract/card.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreOrderCard');
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

	if (isModEnabled('agenda') && (!empty($user->admin) || $user->hasRight('agenda', 'myactions', 'read'))) {
		$head[$h][0] = dol_buildpath('/dolistorextract/agenda.php', 1).'?id='.$id;
		$head[$h][1] = $langs->trans('Module2400Name');
		$head[$h][2] = 'agenda';
		$h++;
	}

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
 * Return document directory for a DoliStore order.
 *
 * @param DolistoreOrder $object Order
 * @return string
 */
function dolistoreextractGetOrderUploadDir($object)
{
	global $conf, $langs;
	$entity = (int) $object->entity;
	$module = $conf->dolistorextract ?? null;
	if ((int) $object->id <= 0 || $entity <= 0 || !is_object($module)
		|| !isset($module->multidir_output) || !is_array($module->multidir_output)
		|| !isset($module->multidir_output[$entity]) || !is_string($module->multidir_output[$entity])
		|| trim($module->multidir_output[$entity]) === '' || trim((string) $object->ref) === ''
		|| dol_sanitizeFileName($object->ref) !== $object->ref
		|| !in_array($entity, array_map('intval', explode(',', getEntity($object->table_element))), true)) {
		$object->error = $langs->trans('DolistoreDocumentDirectoryUnavailable');
		return '';
	}
	$path = getMultidirOutput($object, 'dolistorextract', 1);
	if (!is_string($path) || $path === '' || strpos($path, 'error-') === 0) {
		$object->error = $langs->trans('DolistoreDocumentDirectoryUnavailable');
		return '';
	}
	// Reject symlink components, including a redirected entity root.
	$parent = rtrim($path, '/');
	while ($parent !== '' && $parent !== '/' && $parent !== '.') {
		if (is_link($parent)) {
			$object->error = $langs->trans('DolistoreDocumentDirectoryUnavailable');
			return '';
		}
		$parent = dirname($parent);
	}
	return rtrim($path, '/');
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
	$modulesubdir = $ref;

	return array(
		'modulepart_card' => 'dolistorextract:DolistoreOrder',
		'modulepart_files' => 'dolistorextract',
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
	if ($uploadDir === '') return 0;
	$filearray = dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'name', SORT_ASC, 1);
	$linkCount = Link::count($db, $object->element, (int) $object->id);
	if ($linkCount < 0) {
		$linkCount = 0;
	}

	return count($filearray) + (int) $linkCount;
}

/**
 * Prepare and render native selected columns component for a module list table.
 *
 * @param Form                              $form        Form helper
 * @param string                            $contextpage Context key used by Dolibarr user preferences
 * @param string                            $htmlname    Hidden input name generated by multiSelectArrayWithCheckbox
 * @param array<string,array<string,mixed>> $arrayfields List arrayfields, modified by Dolibarr helper
 * @return string
 */
function dolistoreextractPrepareSelectedFields($form, $contextpage, $htmlname, &$arrayfields)
{
	global $conf;

	if (function_exists('dol_sort_array')) {
		$arrayfields = dol_sort_array($arrayfields, 'position');
	}

	dolistoreextractSaveSelectedFields($contextpage, $htmlname, $arrayfields);

	$checkboxLeftColumn = !empty($conf->main_checkbox_left_column) ? 1 : (int) getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN');
	$selectedFields = $form->multiSelectArrayWithCheckbox($htmlname, $arrayfields, $contextpage, $checkboxLeftColumn);

	$nativeClass = $checkboxLeftColumn ? 'selectedfieldsleft' : 'selectedfields';
	$generatedClass = $htmlname.($checkboxLeftColumn ? 'left' : '');
	$selectedFields = str_replace(
		'<ul class="'.$generatedClass.'">',
		'<ul class="'.$generatedClass.' '.$nativeClass.'">',
		$selectedFields
	);

	return $selectedFields;
}

/**
 * Persist selected columns for a module list table.
 *
 * @param string                            $contextpage Context key used by Dolibarr user preferences
 * @param string                            $htmlname    Hidden input name generated by multiSelectArrayWithCheckbox
 * @param array<string,array<string,mixed>> $arrayfields List arrayfields used to validate posted keys
 * @return void
 */
function dolistoreextractSaveSelectedFields($contextpage, $htmlname, $arrayfields = array())
{
	global $db, $conf, $user;

	if (GETPOST('formfilteraction', 'alphanohtml') !== 'listafterchangingselectedfields') {
		return;
	}
	if (GETPOST('column_contextpage', 'aZ09') !== $contextpage) {
		return;
	}

	$rawSelectedFields = GETPOST($htmlname, 'restricthtml');
	$selectedFieldsArray = array();
	foreach (explode(',', $rawSelectedFields) as $selectedField) {
		$selectedField = trim((string) $selectedField);
		if ($selectedField === '') {
			continue;
		}
		if (!empty($arrayfields) && !array_key_exists($selectedField, $arrayfields)) {
			continue;
		}
		if (!empty($arrayfields[$selectedField]) && array_key_exists('enabled', $arrayfields[$selectedField]) && empty($arrayfields[$selectedField]['enabled'])) {
			continue;
		}
		$selectedFieldsArray[$selectedField] = $selectedField;
	}
	$selectedFields = !empty($selectedFieldsArray) ? implode(',', array_values($selectedFieldsArray)) : '__none__';
	$paramName = 'MAIN_SELECTEDFIELDS_'.$contextpage;
	$tabparam = array($paramName => $selectedFields);

	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	dol_set_user_param($db, $conf, $user, $tabparam);
	$user->conf->{$paramName} = $selectedFields;
}

/**
 * Read a native Dolibarr date filter.
 *
 * @param string $prefix     Date input prefix used by Form::selectDate()
 * @param string $legacyName Optional legacy YYYY-MM-DD input name
 * @param int    $hour       Hour
 * @param int    $minute     Minute
 * @param int    $second     Second
 * @return int
 */
function dolistoreextractGetDateFilter($prefix, $legacyName = '', $hour = 0, $minute = 0, $second = 0)
{
	$year = GETPOSTINT($prefix.'year');
	$month = GETPOSTINT($prefix.'month');
	$day = GETPOSTINT($prefix.'day');
	if ($year > 0 && $month > 0 && $day > 0) {
		return dol_mktime($hour, $minute, $second, $month, $day, $year);
	}

	if ($legacyName !== '') {
		$legacyValue = GETPOST($legacyName, 'alpha');
		if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $legacyValue, $matches)) {
			return dol_mktime($hour, $minute, $second, (int) $matches[2], (int) $matches[3], (int) $matches[1]);
		}
	}

	return 0;
}

/**
 * Append a native Dolibarr date filter to a list URL parameter string.
 *
 * @param string $param  URL parameter string
 * @param string $prefix Date input prefix used by Form::selectDate()
 * @param int    $date   Date timestamp
 * @return void
 */
function dolistoreextractAppendDateFilterParam(&$param, $prefix, $date)
{
	if (empty($date)) {
		return;
	}

	$param .= '&'.$prefix.'day='.urlencode(dol_print_date($date, '%d'));
	$param .= '&'.$prefix.'month='.urlencode(dol_print_date($date, '%m'));
	$param .= '&'.$prefix.'year='.urlencode(dol_print_date($date, '%Y'));
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
	return !empty($arrayfields[$key]['checked']) && !empty($arrayfields[$key]['enabled']);
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
		if (!empty($val['checked']) && !empty($val['enabled'])) {
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
 * @param bool                             $extraLeft    Put action columns before the data columns
 * @return void
 */
function dolistoreextractPrintTotalRow($arrayfields, $totals, $extra = 0, $extraLeft = false)
{
	global $langs;

	print '<tr class="liste_total">';
	if ($extraLeft) print str_repeat('<td></td>', $extra);
	$labelPrinted = false;
	foreach ($arrayfields as $key => $val) {
		if (empty($val['checked']) || empty($val['enabled'])) {
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
	for ($i = 0; !$extraLeft && $i < $extra; $i++) {
		print '<td></td>';
	}
	print '</tr>';
}

/**
 * Resolve the shared order environments once for filters and row badges.
 * getEntity delegates the accessible sharing scope to Multicompany.
 * @param DoliDB $db Database
 * @return array<int, string>
 */
function dolistoreextractGetEntityOptions($db)
{
	global $conf, $mysoc;
	if (!isModEnabled('multicompany')) {
		return array((int) $conf->entity => (string) ($mysoc->name ?? ''));
	}
	$options = array();
	$resql = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE active = 1 AND rowid IN ('.$db->sanitize(getEntity('dolistoreextract_order')).') ORDER BY label');
	if (!$resql) {
		dol_syslog(__FUNCTION__.' '.$db->lasterror(), LOG_ERR);
		return $options;
	}
	while (is_object($row = $db->fetch_object($resql))) $options[(int) $row->rowid] = (string) $row->label;
	$db->free($resql);
	return $options;
}
