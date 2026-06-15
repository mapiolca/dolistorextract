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
 * DoliStore monthly invoice batch.
 */
class DolistoreInvoiceBatch extends CommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_SUCCESS = 1;
	public const STATUS_ERROR = 9;

	public $module = 'dolistorextract';
	public $element = 'dolistoreextract_invoice_batch';
	public $table_element = 'dolistoreextract_invoice_batch';
	public $picto = 'bill';
	public $ismultientitymanaged = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'position' => 5, 'notnull' => 1),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'DolistoreLinkedInvoice', 'enabled' => 1, 'visible' => 1, 'position' => 10),
		'period_year' => array('type' => 'integer', 'label' => 'Year', 'enabled' => 1, 'visible' => 1, 'position' => 20, 'notnull' => 1),
		'period_month' => array('type' => 'integer', 'label' => 'Month', 'enabled' => 1, 'visible' => 1, 'position' => 30, 'notnull' => 1),
		'amount_ht' => array('type' => 'double(24,8)', 'label' => 'AmountHT', 'enabled' => 1, 'visible' => 1, 'position' => 40),
		'orders_count' => array('type' => 'integer', 'label' => 'DolistoreOrdersCount', 'enabled' => 1, 'visible' => 1, 'position' => 50),
		'lines_count' => array('type' => 'integer', 'label' => 'DolistoreLinesCount', 'enabled' => 1, 'visible' => 1, 'position' => 60),
		'email_sent' => array('type' => 'integer', 'label' => 'DolistoreEmailSent', 'enabled' => 1, 'visible' => 1, 'position' => 70),
		'email_sent_date' => array('type' => 'datetime', 'label' => 'DateSend', 'enabled' => 1, 'visible' => 1, 'position' => 80),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'position' => 90),
		'log' => array('type' => 'text', 'label' => 'Log', 'enabled' => 1, 'visible' => 1, 'position' => 100),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'position' => 500),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'position' => 510),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'position' => 520),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'position' => 530),
	);

	public $id;
	public $rowid;
	public $entity;
	public $fk_facture;
	public $period_year;
	public $period_month;
	public $amount_ht = 0;
	public $orders_count = 0;
	public $lines_count = 0;
	public $email_sent = 0;
	public $email_sent_date;
	public $status = self::STATUS_DRAFT;
	public $log;
	public $datec;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;

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
	 * Fetch batch.
	 *
	 * @param int $id Batch id
	 * @return int
	 */
	public function fetch($id)
	{
		$sql = 'SELECT b.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as b';
		$sql .= ' WHERE b.rowid = '.((int) $id);
		$sql .= ' AND b.entity IN ('.getEntity('dolistoreextract_order').')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($resql) === 0) {
			$this->db->free($resql);
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->setVarsFromObject($obj);
		$this->db->free($resql);

		return 1;
	}

	/**
	 * Fetch batch for a period.
	 *
	 * @param int $year Year
	 * @param int $month Month
	 * @return int
	 */
	public function fetchByPeriod($year, $month)
	{
		$sql = 'SELECT b.rowid FROM '.MAIN_DB_PREFIX.$this->table_element.' as b';
		$sql .= ' WHERE b.period_year = '.((int) $year);
		$sql .= ' AND b.period_month = '.((int) $month);
		$sql .= ' AND b.entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' ORDER BY b.rowid DESC LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (empty($obj->rowid)) {
			return 0;
		}

		return $this->fetch((int) $obj->rowid);
	}

	/**
	 * Create batch.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, fk_facture, period_year, period_month, amount_ht, orders_count, lines_count, email_sent, email_sent_date, status, log, datec, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).',';
		$sql .= $this->nullableInt($this->fk_facture).',';
		$sql .= ((int) $this->period_year).',';
		$sql .= ((int) $this->period_month).',';
		$sql .= price2num($this->amount_ht, 'MU').',';
		$sql .= ((int) $this->orders_count).',';
		$sql .= ((int) $this->lines_count).',';
		$sql .= ((int) $this->email_sent).',';
		$sql .= $this->dateToSql($this->email_sent_date).',';
		$sql .= ((int) $this->status).',';
		$sql .= $this->quoteNullableSqlValue($this->log).',';
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= ((int) $user->id);
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;

		return (int) $this->id;
	}

	/**
	 * Update batch.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
		if (empty($this->id)) {
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' fk_facture = '.$this->nullableInt($this->fk_facture);
		$sql .= ', amount_ht = '.price2num($this->amount_ht, 'MU');
		$sql .= ', orders_count = '.((int) $this->orders_count);
		$sql .= ', lines_count = '.((int) $this->lines_count);
		$sql .= ', email_sent = '.((int) $this->email_sent);
		$sql .= ', email_sent_date = '.$this->dateToSql($this->email_sent_date);
		$sql .= ', status = '.((int) $this->status);
		$sql .= ', log = '.$this->quoteNullableSqlValue($this->log);
		$sql .= ', fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.getEntity('dolistoreextract_order').')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Return status label.
	 *
	 * @param int $mode Output mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return status label.
	 *
	 * @param int $status Status
	 * @param int $mode   Output mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(
			self::STATUS_DRAFT => 'Draft',
			self::STATUS_SUCCESS => 'Success',
			self::STATUS_ERROR => 'Error',
		);
		$classes = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_SUCCESS => 'status4',
			self::STATUS_ERROR => 'status9',
		);
		$label = $langs->trans($labels[(int) $status] ?? 'Unknown');

		if (function_exists('dolGetStatus')) {
			return dolGetStatus($label, '', '', $classes[(int) $status] ?? 'status0', $mode);
		}

		return $label;
	}

	/**
	 * Assign SQL row.
	 *
	 * @param stdClass $obj SQL result
	 * @return void
	 */
	private function setVarsFromObject($obj)
	{
		foreach (get_object_vars($obj) as $key => $value) {
			$this->{$key} = $value;
		}
		$this->id = (int) $obj->rowid;
		$this->rowid = (int) $obj->rowid;
		$this->datec = !empty($obj->datec) ? $this->db->jdate($obj->datec) : 0;
		$this->email_sent_date = !empty($obj->email_sent_date) ? $this->db->jdate($obj->email_sent_date) : 0;
	}

	/**
	 * Quote nullable SQL value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function quoteNullableSqlValue($value)
	{
		if ($value === null || $value === '') {
			return 'NULL';
		}

		return "'".$this->db->escape((string) $value)."'";
	}

	/**
	 * Nullable integer SQL value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function nullableInt($value)
	{
		return ((int) $value > 0) ? (string) ((int) $value) : 'NULL';
	}

	/**
	 * SQL datetime.
	 *
	 * @param mixed $value Date value
	 * @return string
	 */
	private function dateToSql($value)
	{
		if (empty($value)) {
			return 'NULL';
		}
		if (!is_numeric($value)) {
			$value = $this->db->jdate($value);
		}

		return "'".$this->db->idate((int) $value)."'";
	}
}
