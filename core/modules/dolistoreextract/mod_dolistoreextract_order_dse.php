<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/modules_dolistoreorder.php';

/**
 * DSE-YYYYMM-0001 numbering model.
 */
class mod_dolistoreextract_order_dse extends ModeleNumRefDolistoreOrder
{
	public $name = 'DSE';
	public $version = 'dolibarr';
	public $prefix = 'DSE';
	private $db;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Return info.
	 *
	 * @return string
	 */
	public function info()
	{
		global $langs;
		return $langs->trans('DolistoreOrderNumberingDseInfo');
	}

	/**
	 * Return example.
	 *
	 * @return string
	 */
	public function getExample()
	{
		return 'DSE-'.dol_print_date(dol_now(), '%Y%m').'-0001';
	}

	/**
	 * Return tooltip.
	 *
	 * @return string
	 */
	public function getToolTip()
	{
		global $langs;
		return $langs->trans('DolistoreOrderNumberingDseTooltip');
	}

	/**
	 * Return next value.
	 *
	 * @param int            $entity Entity
	 * @param DolistoreOrder $object Object
	 * @return string
	 */
	public function getNextValue($entity, $object)
	{
		$entity = (int) $entity;
		$date = !empty($object->dolistore_order_date) ? (int) $object->dolistore_order_date : dol_now();
		$prefix = $this->prefix.'-'.dol_print_date($date, '%Y%m').'-';

		$numberingEntities = (string) $entity;
		if (function_exists('getEntity')) {
			$entityIds = array();
			foreach (array(getEntity('dolistoreextract_order'), getEntity('dolistoreextract_ordernumber', 1, $object)) as $entityList) {
				foreach (explode(',', (string) $entityList) as $entityId) {
					$entityId = (int) trim($entityId);
					if ($entityId > 0) {
						$entityIds[$entityId] = $entityId;
					}
				}
			}
			if (!empty($entityIds)) {
				$numberingEntities = implode(',', $entityIds);
			}
		}

		$sql = 'SELECT ref FROM '.MAIN_DB_PREFIX.'dolistoreextract_order';
		$sql .= ' WHERE entity IN ('.$numberingEntities.')';
		$sql .= " AND ref LIKE '".$this->db->escape($prefix)."%'";
		$sql .= ' ORDER BY ref DESC LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return '';
		}

		$next = 1;
		if ($obj = $this->db->fetch_object($resql)) {
			if (preg_match('/([0-9]+)$/', (string) $obj->ref, $matches)) {
				$next = (int) $matches[1] + 1;
			}
		}
		$this->db->free($resql);

		return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
	}
}
