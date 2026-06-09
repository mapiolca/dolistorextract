<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

/**
 * Base class for DoliStore order document models.
 */
abstract class ModelePDFDolistoreOrder extends CommonDocGenerator
{
	/**
	 * Return list of active generation modules.
	 *
	 * @param DoliDB $db Database handler
	 * @param int    $maxfilenamelength Max filename length
	 * @return array<string,string>
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		global $langs;

		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$list = getListOfModels($db, 'dolistoreextract', $maxfilenamelength);
		if (!is_array($list)) {
			$list = array();
		}
		if (empty($list)) {
			$list['standard'] = is_object($langs) ? $langs->trans('DolistoreOrderPdfStandard') : 'Standard';
		}

		return $list;
	}

	/**
	 * Build document on disk.
	 *
	 * @param DolistoreOrder $object Object
	 * @param Translate     $outputlangs Output language
	 * @param string        $srctemplatepath Source template path
	 * @param int           $hidedetails Hide details
	 * @param int           $hidedesc Hide description
	 * @param int           $hideref Hide reference
	 * @return int
	 */
	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
 * Base class for DoliStore order numbering modules.
 */
abstract class ModeleNumRefDolistoreOrder
{
	public $error = '';

	/**
	 * Return information.
	 *
	 * @return string
	 */
	abstract public function info();

	/**
	 * Return example.
	 *
	 * @return string
	 */
	abstract public function getExample();

	/**
	 * Return next value.
	 *
	 * @param int             $entity Entity
	 * @param DolistoreOrder  $object Object
	 * @return string
	 */
	abstract public function getNextValue($entity, $object);
}

/**
 * DSE-YYYYMM-0001 numbering model.
 */
class mod_dolistoreextract_order_dse extends ModeleNumRefDolistoreOrder
{
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
	 * Check activation.
	 *
	 * @return bool
	 */
	public function canBeActivated()
	{
		return true;
	}

	/**
	 * Return version.
	 *
	 * @return string
	 */
	public function getVersion()
	{
		return $this->version;
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
	 * @param int             $entity Entity
	 * @param DolistoreOrder  $object Object
	 * @return string
	 */
	public function getNextValue($entity, $object)
	{
		$entity = (int) $entity;
		$date = !empty($object->dolistore_order_date) ? (int) $object->dolistore_order_date : dol_now();
		$prefix = $this->prefix.'-'.dol_print_date($date, '%Y%m').'-';

		$numberingEntities = function_exists('getEntity') ? getEntity('dolistoreextract_ordernumber') : (string) $entity;
		if ($numberingEntities === '') {
			$numberingEntities = (string) $entity;
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
