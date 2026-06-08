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
	$head[$h][1] = $langs->trans('AttachedFiles');
	$head[$h][2] = 'documents';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/lines.php', 1).'?id='.$id;
	$head[$h][1] = $langs->trans('DolistoreOrderLines');
	$head[$h][2] = 'lines';
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

	$uploadDir = getMultidirOutput($object, 'dolistorextract', 1);
	if (!empty($uploadDir)) {
		return $uploadDir;
	}

	$objectEntity = !empty($object->entity) ? (int) $object->entity : (int) $conf->entity;
	$moduleOutput = !empty($conf->dolistorextract->multidir_output[$objectEntity])
		? $conf->dolistorextract->multidir_output[$objectEntity]
		: $conf->dolistorextract->dir_output;

	return $moduleOutput.'/'.$object->element.'/'.dol_sanitizeFileName($object->ref);
}
