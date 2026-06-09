<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

/**
 * Parent class for DoliStore order document models.
 */
abstract class ModelePDFDolistoreextract extends CommonDocGenerator
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
		if (empty($list)) {
			$list['standard'] = is_object($langs) ? $langs->trans('DolistoreOrderPdfStandard') : 'Standard';
		}

		return $list;
	}

	/**
	 * Function to build a document on disk.
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
