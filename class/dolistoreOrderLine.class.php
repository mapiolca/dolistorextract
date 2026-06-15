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
 * DoliStore order line.
 */
class DolistoreOrderLine extends CommonObject
{
	public $module = 'dolistorextract';
	public $element = 'dolistoreextract_order_line';
	public $table_element = 'dolistoreextract_order_line';
	public $picto = 'dolistore@dolistorextract';
	public $ismultientitymanaged = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'position' => 5, 'notnull' => 1),
		'fk_order' => array('type' => 'integer:DolistoreOrder:dolistorextract/class/dolistoreOrder.class.php', 'label' => 'DolistoreOrder', 'enabled' => 1, 'visible' => 1, 'position' => 10, 'notnull' => 1),
		'product_dolistore_ref' => array('type' => 'varchar(128)', 'label' => 'DolistoreProductRef', 'enabled' => 1, 'visible' => 1, 'position' => 20),
		'product_label' => array('type' => 'varchar(255)', 'label' => 'DolistoreProductLabel', 'enabled' => 1, 'visible' => 1, 'position' => 30),
		'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'ProductOrService', 'enabled' => 1, 'visible' => 1, 'position' => 40),
		'qty' => array('type' => 'double(24,8)', 'label' => 'Qty', 'enabled' => 1, 'visible' => 1, 'position' => 50),
		'unit_price_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreUnitPriceHt', 'enabled' => 1, 'visible' => 1, 'position' => 60),
		'total_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalHt', 'enabled' => 1, 'visible' => 1, 'position' => 70),
		'total_tva' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalTva', 'enabled' => 1, 'visible' => 1, 'position' => 80),
		'total_ttc' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalTtc', 'enabled' => 1, 'visible' => 1, 'position' => 90),
		'billable_unit_price_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreBillableUnitPriceHt', 'enabled' => 1, 'visible' => 1, 'position' => 100),
		'billable_total_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreBillableTotalHt', 'enabled' => 1, 'visible' => 1, 'position' => 110),
		'tax_rate' => array('type' => 'double(8,4)', 'label' => 'VATRate', 'enabled' => 1, 'visible' => 1, 'position' => 120),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'visible' => 1, 'position' => 130),
		'raw_hash' => array('type' => 'varchar(128)', 'label' => 'DolistoreRawHash', 'enabled' => 1, 'visible' => 0, 'position' => 140),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 0, 'position' => 150),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'visible' => -2, 'position' => 160),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'position' => 500),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'position' => 510),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'position' => 520),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'position' => 530),
	);

	public $id;
	public $rowid;
	public $entity;
	public $fk_order;
	public $product_dolistore_ref;
	public $product_label;
	public $fk_product;
	public $qty = 1;
	public $unit_price_ht = 0;
	public $total_ht = 0;
	public $total_tva = 0;
	public $total_ttc = 0;
	public $billable_unit_price_ht = 0;
	public $billable_total_ht = 0;
	public $tax_rate = 0;
	public $description;
	public $raw_hash;
	public $status = 1;
	public $import_key;
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
	 * Fetch one line.
	 *
	 * @param int $id Line id
	 * @return int
	 */
	public function fetch($id)
	{
		$sql = 'SELECT l.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as l';
		$sql .= ' WHERE l.rowid = '.((int) $id);
		$sql .= ' AND l.entity IN ('.getEntity('dolistoreextract_order').')';

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
	 * Fetch all lines for an order.
	 *
	 * @param int $orderId Order id
	 * @return DolistoreOrderLine[]
	 */
	public function fetchAllByOrder($orderId)
	{
		$lines = array();
		$sql = 'SELECT l.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as l';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order as o ON o.rowid = l.fk_order';
		$sql .= ' WHERE l.fk_order = '.((int) $orderId);
		$sql .= ' AND o.entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' ORDER BY l.rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$line = new self($this->db);
			$line->setVarsFromObject($obj);
			$lines[] = $line;
		}
		$this->db->free($resql);

		return $lines;
	}

	/**
	 * Create line.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		$this->calculateAmounts();
		if (empty($this->raw_hash)) {
			$this->raw_hash = $this->buildLineHash();
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, fk_order, product_dolistore_ref, product_label, fk_product, qty, unit_price_ht, total_ht, total_tva, total_ttc, billable_unit_price_ht, billable_total_ht, tax_rate, description, raw_hash, status, import_key, datec, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).',';
		$sql .= ((int) $this->fk_order).',';
		$sql .= $this->quoteNullableValue($this->product_dolistore_ref).',';
		$sql .= $this->quoteNullableValue($this->product_label).',';
		$sql .= (!empty($this->fk_product) ? (int) $this->fk_product : 'NULL').',';
		$sql .= price2num($this->qty, 'MU').',';
		$sql .= price2num($this->unit_price_ht, 'MU').',';
		$sql .= price2num($this->total_ht, 'MU').',';
		$sql .= price2num($this->total_tva, 'MU').',';
		$sql .= price2num($this->total_ttc, 'MU').',';
		$sql .= price2num($this->billable_unit_price_ht, 'MU').',';
		$sql .= price2num($this->billable_total_ht, 'MU').',';
		$sql .= price2num($this->tax_rate, 'MU').',';
		$sql .= $this->quoteNullableValue($this->description).',';
		$sql .= $this->quoteNullableValue($this->raw_hash).',';
		$sql .= ((int) $this->status).',';
		$sql .= $this->quoteNullableValue($this->import_key).',';
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= ((int) $user->id);
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;

		return (int) $this->id;
	}

	/**
	 * Update line.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
		if (empty($this->id)) {
			$this->error = 'Missing line id';
			return -1;
		}
		$this->calculateAmounts();

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' product_dolistore_ref = '.$this->quoteNullableValue($this->product_dolistore_ref);
		$sql .= ', product_label = '.$this->quoteNullableValue($this->product_label);
		$sql .= ', fk_product = '.(!empty($this->fk_product) ? (int) $this->fk_product : 'NULL');
		$sql .= ', qty = '.price2num($this->qty, 'MU');
		$sql .= ', unit_price_ht = '.price2num($this->unit_price_ht, 'MU');
		$sql .= ', total_ht = '.price2num($this->total_ht, 'MU');
		$sql .= ', total_tva = '.price2num($this->total_tva, 'MU');
		$sql .= ', total_ttc = '.price2num($this->total_ttc, 'MU');
		$sql .= ', billable_unit_price_ht = '.price2num($this->billable_unit_price_ht, 'MU');
		$sql .= ', billable_total_ht = '.price2num($this->billable_total_ht, 'MU');
		$sql .= ', tax_rate = '.price2num($this->tax_rate, 'MU');
		$sql .= ', description = '.$this->quoteNullableValue($this->description);
		$sql .= ', raw_hash = '.$this->quoteNullableValue($this->raw_hash);
		$sql .= ', status = '.((int) $this->status);
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
	 * Delete line.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function delete($user, $notrigger = 0)
	{
		if (empty($this->id)) {
			return -1;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.getEntity('dolistoreextract_order').')';

		return $this->db->query($sql) ? 1 : -1;
	}

	/**
	 * Calculate source and billable amounts.
	 *
	 * @return void
	 */
	public function calculateAmounts()
	{
		$qty = (float) $this->qty;
		if (abs($qty) <= 0) {
			$qty = 1;
		}
		$this->qty = $qty;

		if ((float) $this->total_ht == 0 && (float) $this->unit_price_ht != 0) {
			$this->total_ht = (float) $this->unit_price_ht * $qty;
		}
		if ((float) $this->unit_price_ht == 0 && $qty != 0) {
			$this->unit_price_ht = (float) $this->total_ht / $qty;
		}
		if ((float) $this->total_tva == 0 && (float) $this->tax_rate != 0) {
			$this->total_tva = (float) $this->total_ht * ((float) $this->tax_rate / 100);
		}
		if ((float) $this->total_ttc == 0) {
			$this->total_ttc = (float) $this->total_ht + (float) $this->total_tva;
		}
		if ((float) $this->billable_total_ht == 0 && (float) $this->billable_unit_price_ht != 0) {
			$this->billable_total_ht = (float) $this->billable_unit_price_ht * $qty;
		}
		if ((float) $this->billable_unit_price_ht == 0 && $qty != 0) {
			$this->billable_unit_price_ht = (float) $this->billable_total_ht / $qty;
		}
	}

	/**
	 * Build deterministic duplicate hash.
	 *
	 * @return string
	 */
	public function buildLineHash()
	{
		return hash('sha256', implode('|', array(
			(int) $this->fk_order,
			(string) $this->product_dolistore_ref,
			(string) $this->product_label,
			price2num($this->total_ht, 'MU'),
			price2num($this->qty, 'MU')
		)));
	}

	/**
	 * Assign object properties from SQL object.
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
	}

	/**
	 * Quote nullable SQL value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function quoteNullableValue($value)
	{
		if ($value === null || $value === '') {
			return 'NULL';
		}

		return "'".$this->db->escape((string) $value)."'";
	}
}
