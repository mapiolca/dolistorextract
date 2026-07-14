<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * DolistoreExtract triggers.
 */
class InterfaceDolistorextractTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'technic';
		$this->description = 'DolistoreExtract business triggers';
		$this->version = 'development';
		$this->picto = 'dolistore@dolistorextract';
	}

	/**
	 * Trigger dispatcher.
	 *
	 * @param string $action Event code
	 * @param object $object Object
	 * @param User   $user   User
	 * @param Translate $langs Langs
	 * @param Conf   $conf Conf
	 * @return int
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (strpos($action, 'DOLISTOREEXTRACT_') !== 0) {
			return 0;
		}

		dol_syslog(__METHOD__.' action='.$action.' object='.(is_object($object) ? get_class($object) : '').' id='.(int) ($object->id ?? 0), LOG_DEBUG);

		return 0;
	}
}
