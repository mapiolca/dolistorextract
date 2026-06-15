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
		$list = getListOfModels($db, 'dolistoreextract_order', $maxfilenamelength);
		if (!is_array($list)) {
			$list = array();
		}

		if (empty($list) && getDolGlobalString('DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED') === '') {
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
	 * Return true if module can be listed.
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return true;
	}

	/**
	 * Return true if module can be activated.
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
		return !empty($this->version) ? $this->version : 'dolibarr';
	}

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
