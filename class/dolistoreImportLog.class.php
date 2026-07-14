<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Persistent DoliStore import log.
 */
class DolistoreImportLog extends CommonObject
{
	public $module = 'dolistorextract';
	public $element = 'dolistoreextract_import_log';
	public $table_element = 'dolistoreextract_import_log';
	public $ismultientitymanaged = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'position' => 5, 'notnull' => 1),
		'fk_order' => array('type' => 'integer:DolistoreOrder:dolistorextract/class/dolistoreOrder.class.php', 'label' => 'DolistoreOrder', 'enabled' => 1, 'visible' => 1, 'position' => 10),
		'fk_invoice_batch' => array('type' => 'integer:DolistoreInvoiceBatch:dolistorextract/class/dolistoreInvoiceBatch.class.php', 'label' => 'DolistoreInvoiceBatch', 'enabled' => 1, 'visible' => 1, 'position' => 20),
		'source' => array('type' => 'varchar(64)', 'label' => 'Source', 'enabled' => 1, 'visible' => 1, 'position' => 30),
		'level' => array('type' => 'varchar(32)', 'label' => 'Level', 'enabled' => 1, 'visible' => 1, 'position' => 40),
		'message' => array('type' => 'text', 'label' => 'Message', 'enabled' => 1, 'visible' => 1, 'position' => 50),
		'context' => array('type' => 'text', 'label' => 'Context', 'enabled' => 1, 'visible' => 0, 'position' => 60),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => 1, 'position' => 70),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => 0, 'position' => 80),
	);

	public $id;
	public $entity;
	public $fk_order;
	public $fk_invoice_batch;
	public $source;
	public $level = 'info';
	public $message;
	public $context;
	public $datec;
	public $fk_user_creat;

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
	 * Add one log entry.
	 *
	 * @param DoliDB      $db       Database handler
	 * @param string      $level    Level
	 * @param string      $message  Message
	 * @param int         $fkOrder  Order id
	 * @param string      $source   Source
	 * @param array       $context  Structured context
	 * @param User|null   $user     User
	 * @param int         $fkBatch  Invoice batch id
	 * @return int
	 */
	public static function add($db, $level, $message, $fkOrder = 0, $source = 'import', $context = array(), $user = null, $fkBatch = 0)
	{
		global $conf;

		$allowedLevels = array('info', 'warning', 'error', 'success');
		if (!in_array($level, $allowedLevels, true)) {
			$level = 'info';
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'dolistoreextract_import_log (';
		$sql .= 'entity, fk_order, fk_invoice_batch, source, level, message, context, datec, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $conf->entity).',';
		$sql .= ((int) $fkOrder > 0 ? (int) $fkOrder : 'NULL').',';
		$sql .= ((int) $fkBatch > 0 ? (int) $fkBatch : 'NULL').',';
		$sql .= self::quoteNullableSqlValue($db, $source).',';
		$sql .= self::quoteNullableSqlValue($db, $level).',';
		$sql .= self::quoteNullableSqlValue($db, $message).',';
		$sql .= self::quoteNullableSqlValue($db, !empty($context) ? json_encode($context) : '').',';
		$sql .= "'".$db->idate(dol_now())."',";
		$sql .= (!empty($user->id) ? (int) $user->id : 'NULL');
		$sql .= ')';

		if (!$db->query($sql)) {
			dol_syslog(__METHOD__.' SQL error: '.$db->lasterror(), LOG_WARNING);
			return -1;
		}

		return (int) $db->last_insert_id(MAIN_DB_PREFIX.'dolistoreextract_import_log');
	}

	/**
	 * Fetch entries for an order.
	 *
	 * @param int $fkOrder Order id
	 * @return array<int,object>
	 */
	public function fetchAllByOrder($fkOrder)
	{
		$logs = array();
		$sql = 'SELECT l.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as l';
		$sql .= ' WHERE l.fk_order = '.((int) $fkOrder);
		$sql .= ' AND l.entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' ORDER BY l.datec DESC, l.rowid DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$logs[] = $obj;
		}
		$this->db->free($resql);

		return $logs;
	}

	/**
	 * Quote nullable SQL value.
	 *
	 * @param DoliDB $db Database
	 * @param mixed  $value Value
	 * @return string
	 */
	private static function quoteNullableSqlValue($db, $value)
	{
		if ($value === null || $value === '') {
			return 'NULL';
		}

		return "'".$db->escape((string) $value)."'";
	}
}
