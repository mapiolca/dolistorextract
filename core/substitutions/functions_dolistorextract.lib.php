<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Complete substitutions for DoliStore order notifications and email templates.
 *
 * @param array<string,string> $substitutionarray Substitution array
 * @param Translate           $langs             Output language
 * @param CommonObject|null   $object            Current object
 * @param mixed               $parameters        Extra parameters
 * @return void
 */
function dolistorextract_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters = null)
{
	if (is_object($langs)) {
		$langs->loadLangs(array('dolistorextract@dolistorextract'));
	}

	$keys = array(
		'__DOLISTOREEXTRACT_ORDER_ID__',
		'__DOLISTOREEXTRACT_ORDER_REF__',
		'__DOLISTOREEXTRACT_ORDER_DOLISTORE_REF__',
		'__DOLISTOREEXTRACT_ORDER_CUSTOMER_NAME__',
		'__DOLISTOREEXTRACT_ORDER_CUSTOMER_EMAIL__',
		'__DOLISTOREEXTRACT_ORDER_STATUS__',
		'__DOLISTOREEXTRACT_ORDER_URL__',
		'__DOLISTOREEXTRACT_ORDER_BILLABLE_HT__',
	);
	foreach ($keys as $key) {
		if (!isset($substitutionarray[$key])) {
			$substitutionarray[$key] = '';
		}
	}

	if (empty($object) || !is_object($object)) {
		return;
	}

	$isDolistoreOrder = (!empty($object->element) && $object->element === 'dolistoreextract_order')
		|| (!empty($object->table_element) && $object->table_element === 'dolistoreextract_order');
	if (!$isDolistoreOrder) {
		return;
	}

	$id = !empty($object->id) ? (int) $object->id : (!empty($object->rowid) ? (int) $object->rowid : 0);
	$statusLabel = '';
	if (method_exists($object, 'LibStatut')) {
		$statusLabel = dol_string_nohtmltag($object->LibStatut((int) $object->status, 0));
	}

	$substitutionarray['__DOLISTOREEXTRACT_ORDER_ID__'] = $id > 0 ? (string) $id : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_REF__'] = !empty($object->ref) ? (string) $object->ref : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_DOLISTORE_REF__'] = !empty($object->dolistore_order_ref) ? (string) $object->dolistore_order_ref : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_CUSTOMER_NAME__'] = !empty($object->customer_name) ? (string) $object->customer_name : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_CUSTOMER_EMAIL__'] = !empty($object->customer_email) ? (string) $object->customer_email : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_STATUS__'] = $statusLabel;
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_URL__'] = $id > 0 ? dol_buildpath('/dolistorextract/card.php', 2).'?id='.$id : '';
	$substitutionarray['__DOLISTOREEXTRACT_ORDER_BILLABLE_HT__'] = isset($object->billable_total_ht) ? price((float) $object->billable_total_ht) : '';
}
