<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once __DIR__.'/dolistoreOrderLine.class.php';

/**
 * DoliStore archived order.
 */
class DolistoreOrder extends CommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_IMPORTED = 1;
	public const STATUS_WAITING_RELEASE = 2;
	public const STATUS_INVOICEABLE = 3;
	public const STATUS_INVOICED = 4;
	public const STATUS_ERROR = 9;

	public $module = 'dolistorextract';
	public $element = 'dolistoreextract_order';
	public $table_element = 'dolistoreextract_order';
	public $picto = 'dolistore@dolistorextract';
	public $ismultientitymanaged = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'ID', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'position' => 5, 'notnull' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'visible' => 1, 'position' => 10, 'notnull' => 1),
		'dolistore_order_ref' => array('type' => 'varchar(128)', 'label' => 'DolistoreOrderRef', 'enabled' => 1, 'visible' => 1, 'position' => 20),
		'dolistore_order_date' => array('type' => 'date', 'label' => 'DolistoreOrderDate', 'enabled' => 1, 'visible' => 1, 'position' => 30),
		'release_date' => array('type' => 'date', 'label' => 'DolistoreReleaseDate', 'enabled' => 1, 'visible' => 1, 'position' => 40),
		'currency_code' => array('type' => 'varchar(3)', 'label' => 'Currency', 'enabled' => 1, 'visible' => 1, 'position' => 50),
		'total_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalHt', 'enabled' => 1, 'visible' => 1, 'position' => 60),
		'total_tva' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalTva', 'enabled' => 1, 'visible' => 1, 'position' => 70),
		'total_ttc' => array('type' => 'double(24,8)', 'label' => 'DolistoreTotalTtc', 'enabled' => 1, 'visible' => 1, 'position' => 80),
		'commission_percent' => array('type' => 'double(8,4)', 'label' => 'DolistoreCommissionPercent', 'enabled' => 1, 'visible' => 1, 'position' => 90),
		'billable_total_ht' => array('type' => 'double(24,8)', 'label' => 'DolistoreBillableTotalHt', 'enabled' => 1, 'visible' => 1, 'position' => 100),
		'customer_name' => array('type' => 'varchar(255)', 'label' => 'DolistoreCustomerName', 'enabled' => 1, 'visible' => 1, 'position' => 110),
		'customer_email' => array('type' => 'varchar(255)', 'label' => 'DolistoreCustomerEmail', 'enabled' => 1, 'visible' => 1, 'position' => 120),
		'customer_country' => array('type' => 'varchar(128)', 'label' => 'DolistoreCustomerCountry', 'enabled' => 1, 'visible' => 1, 'position' => 130),
		'customer_country_code' => array('type' => 'varchar(8)', 'label' => 'DolistoreCustomerCountryCode', 'enabled' => 1, 'visible' => 0, 'position' => 140),
		'fk_soc_customer' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'DolistoreCustomerThirdparty', 'enabled' => 1, 'visible' => 1, 'position' => 150),
		'fk_contact_customer' => array('type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'DolistoreCustomerContact', 'enabled' => 1, 'visible' => 1, 'position' => 160),
		'fk_soc_dolistore' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'DolistoreBillingThirdpartyLabel', 'enabled' => 1, 'visible' => 1, 'position' => 170),
		'fk_facture' => array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'DolistoreLinkedInvoice', 'enabled' => 1, 'visible' => 1, 'position' => 180),
		'invoice_date' => array('type' => 'date', 'label' => 'DolistoreInvoiceDate', 'enabled' => 1, 'visible' => 1, 'position' => 190),
		'email_message_id' => array('type' => 'varchar(255)', 'label' => 'DolistoreEmailMessageId', 'enabled' => 1, 'visible' => 0, 'position' => 200),
		'email_subject' => array('type' => 'varchar(255)', 'label' => 'DolistoreEmailSubject', 'enabled' => 1, 'visible' => 0, 'position' => 210),
		'email_date' => array('type' => 'datetime', 'label' => 'DolistoreEmailDate', 'enabled' => 1, 'visible' => 0, 'position' => 220),
		'email_uid' => array('type' => 'integer', 'label' => 'DolistoreEmailUid', 'enabled' => 1, 'visible' => 0, 'position' => 230),
		'email_folder' => array('type' => 'varchar(255)', 'label' => 'DolistoreEmailFolder', 'enabled' => 1, 'visible' => 0, 'position' => 240),
		'raw_hash' => array('type' => 'varchar(128)', 'label' => 'DolistoreRawHash', 'enabled' => 1, 'visible' => 0, 'position' => 250),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'position' => 260),
		'note_public' => array('type' => 'text', 'label' => 'NotePublic', 'enabled' => 1, 'visible' => 0, 'position' => 270),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'visible' => 0, 'position' => 280),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'ModelPdf', 'enabled' => 1, 'visible' => 0, 'position' => 290),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => 1, 'visible' => 0, 'position' => 300),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'visible' => -2, 'position' => 310),
		'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'position' => 500),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'position' => 510),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'position' => 520),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'position' => 530),
	);

	public $id;
	public $rowid;
	public $entity;
	public $ref;
	public $dolistore_order_ref;
	public $dolistore_order_date;
	public $release_date;
	public $currency_code = 'EUR';
	public $total_ht = 0;
	public $total_tva = 0;
	public $total_ttc = 0;
	public $commission_percent = 0;
	public $billable_total_ht = 0;
	public $customer_name;
	public $customer_email;
	public $customer_country;
	public $customer_country_code;
	public $fk_soc_customer;
	public $socid;
	public $fk_contact_customer;
	public $fk_soc_dolistore;
	public $fk_facture;
	public $invoice_date;
	public $email_message_id;
	public $email_subject;
	public $email_date;
	public $email_uid;
	public $email_folder;
	public $raw_hash;
	public $status = self::STATUS_DRAFT;
	public $note_public;
	public $note_private;
	public $model_pdf;
	public $last_main_doc;
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
	 * Fetch one order.
	 *
	 * @param int         $id  Object id
	 * @param string|null $ref Object ref
	 * @return int
	 */
	public function fetch($id, $ref = null)
	{
		$sql = 'SELECT o.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as o';
		$sql .= ' WHERE 1 = 1';
		if ($id > 0) {
			$sql .= ' AND o.rowid = '.((int) $id);
		} else {
			$sql .= ' AND o.ref = '.$this->quoteNullableSqlValue($ref);
		}
		$sql .= ' AND o.entity IN ('.getEntity($this->element).')';

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
	 * Fetch by DoliStore reference.
	 *
	 * @param string $ref DoliStore reference
	 * @return int
	 */
	public function fetchByDolistoreRef($ref)
	{
		return $this->fetchByField('dolistore_order_ref', $ref);
	}

	/**
	 * Fetch by email Message-ID.
	 *
	 * @param string $messageId Email Message-ID
	 * @return int
	 */
	public function fetchByEmailMessageId($messageId)
	{
		return $this->fetchByField('email_message_id', $messageId);
	}

	/**
	 * Fetch by raw hash.
	 *
	 * @param string $rawHash Raw hash
	 * @return int
	 */
	public function fetchByRawHash($rawHash)
	{
		return $this->fetchByField('raw_hash', $rawHash);
	}

	/**
	 * Create order.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
		}
		if (empty($this->raw_hash)) {
			$this->raw_hash = $this->buildRawHash();
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, ref, dolistore_order_ref, dolistore_order_date, release_date, currency_code, total_ht, total_tva, total_ttc, commission_percent, billable_total_ht, customer_name, customer_email, customer_country, customer_country_code, fk_soc_customer, fk_contact_customer, fk_soc_dolistore, fk_facture, invoice_date, email_message_id, email_subject, email_date, email_uid, email_folder, raw_hash, status, note_public, note_private, model_pdf, last_main_doc, import_key, datec, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).',';
		$sql .= $this->quoteNullableSqlValue($this->ref).',';
		$sql .= $this->quoteNullableSqlValue($this->dolistore_order_ref).',';
		$sql .= $this->dateToSql($this->dolistore_order_date, true).',';
		$sql .= $this->dateToSql($this->release_date, true).',';
		$sql .= $this->quoteNullableSqlValue($this->currency_code ?: 'EUR').',';
		$sql .= price2num($this->total_ht, 'MU').',';
		$sql .= price2num($this->total_tva, 'MU').',';
		$sql .= price2num($this->total_ttc, 'MU').',';
		$sql .= price2num($this->commission_percent, 'MU').',';
		$sql .= price2num($this->billable_total_ht, 'MU').',';
		$sql .= $this->quoteNullableSqlValue($this->customer_name).',';
		$sql .= $this->quoteNullableSqlValue($this->customer_email).',';
		$sql .= $this->quoteNullableSqlValue($this->customer_country).',';
		$sql .= $this->quoteNullableSqlValue($this->customer_country_code).',';
		$sql .= $this->nullableInt($this->fk_soc_customer).',';
		$sql .= $this->nullableInt($this->fk_contact_customer).',';
		$sql .= $this->nullableInt($this->fk_soc_dolistore).',';
		$sql .= $this->nullableInt($this->fk_facture).',';
		$sql .= $this->dateToSql($this->invoice_date, true).',';
		$sql .= $this->quoteNullableSqlValue($this->email_message_id).',';
		$sql .= $this->quoteNullableSqlValue($this->email_subject).',';
		$sql .= $this->dateToSql($this->email_date, false).',';
		$sql .= $this->nullableInt($this->email_uid).',';
		$sql .= $this->quoteNullableSqlValue($this->email_folder).',';
		$sql .= $this->quoteNullableSqlValue($this->raw_hash).',';
		$sql .= ((int) $this->status).',';
		$sql .= $this->quoteNullableSqlValue($this->note_public).',';
		$sql .= $this->quoteNullableSqlValue($this->note_private).',';
		$sql .= $this->quoteNullableSqlValue($this->model_pdf).',';
		$sql .= $this->quoteNullableSqlValue($this->last_main_doc).',';
		$sql .= $this->quoteNullableSqlValue($this->import_key).',';
		$sql .= "'".$this->db->idate(dol_now())."',";
		$sql .= ((int) $user->id);
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;
		$this->socid = (int) $this->fk_soc_customer;

		if (!$notrigger) {
			$result = $this->call_trigger('DOLISTOREEXTRACT_ORDER_CREATE', $user);
			if ($result < 0) {
				return -1;
			}
		}

		return (int) $this->id;
	}

	/**
	 * Update order.
	 *
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function update($user, $notrigger = 0)
	{
		if (empty($this->id)) {
			$this->error = 'Missing order id';
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' ref = '.$this->quoteNullableSqlValue($this->ref);
		$sql .= ', dolistore_order_ref = '.$this->quoteNullableSqlValue($this->dolistore_order_ref);
		$sql .= ', dolistore_order_date = '.$this->dateToSql($this->dolistore_order_date, true);
		$sql .= ', release_date = '.$this->dateToSql($this->release_date, true);
		$sql .= ', currency_code = '.$this->quoteNullableSqlValue($this->currency_code ?: 'EUR');
		$sql .= ', total_ht = '.price2num($this->total_ht, 'MU');
		$sql .= ', total_tva = '.price2num($this->total_tva, 'MU');
		$sql .= ', total_ttc = '.price2num($this->total_ttc, 'MU');
		$sql .= ', commission_percent = '.price2num($this->commission_percent, 'MU');
		$sql .= ', billable_total_ht = '.price2num($this->billable_total_ht, 'MU');
		$sql .= ', customer_name = '.$this->quoteNullableSqlValue($this->customer_name);
		$sql .= ', customer_email = '.$this->quoteNullableSqlValue($this->customer_email);
		$sql .= ', customer_country = '.$this->quoteNullableSqlValue($this->customer_country);
		$sql .= ', customer_country_code = '.$this->quoteNullableSqlValue($this->customer_country_code);
		$sql .= ', fk_soc_customer = '.$this->nullableInt($this->fk_soc_customer);
		$sql .= ', fk_contact_customer = '.$this->nullableInt($this->fk_contact_customer);
		$sql .= ', fk_soc_dolistore = '.$this->nullableInt($this->fk_soc_dolistore);
		$sql .= ', fk_facture = '.$this->nullableInt($this->fk_facture);
		$sql .= ', invoice_date = '.$this->dateToSql($this->invoice_date, true);
		$sql .= ', email_message_id = '.$this->quoteNullableSqlValue($this->email_message_id);
		$sql .= ', email_subject = '.$this->quoteNullableSqlValue($this->email_subject);
		$sql .= ', email_date = '.$this->dateToSql($this->email_date, false);
		$sql .= ', email_uid = '.$this->nullableInt($this->email_uid);
		$sql .= ', email_folder = '.$this->quoteNullableSqlValue($this->email_folder);
		$sql .= ', raw_hash = '.$this->quoteNullableSqlValue($this->raw_hash);
		$sql .= ', status = '.((int) $this->status);
		$sql .= ', note_public = '.$this->quoteNullableSqlValue($this->note_public);
		$sql .= ', note_private = '.$this->quoteNullableSqlValue($this->note_private);
		$sql .= ', model_pdf = '.$this->quoteNullableSqlValue($this->model_pdf);
		$sql .= ', last_main_doc = '.$this->quoteNullableSqlValue($this->last_main_doc);
		$sql .= ', import_key = '.$this->quoteNullableSqlValue($this->import_key);
		$sql .= ', fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.getEntity($this->element).')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->socid = (int) $this->fk_soc_customer;

		if (!$notrigger) {
			$result = $this->call_trigger('DOLISTOREEXTRACT_ORDER_UPDATE', $user);
			if ($result < 0) {
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Delete order and lines.
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

		$this->db->begin();
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line';
		$sql .= ' WHERE fk_order = '.((int) $this->id);
		$sql .= ' AND entity IN ('.getEntity($this->element).')';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity IN ('.getEntity($this->element).')';
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			$this->error = $this->db->lasterror();
			return -1;
		}

		if (!$notrigger) {
			$result = $this->call_trigger('DOLISTOREEXTRACT_ORDER_DELETE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Fetch lines.
	 *
	 * @return DolistoreOrderLine[]
	 */
	public function getLines()
	{
		$line = new DolistoreOrderLine($this->db);
		return $line->fetchAllByOrder((int) $this->id);
	}

	/**
	 * Return lines grouped for native card/document rendering.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getGroupedLinesForDisplay()
	{
		$lines = $this->getLines();
		if (empty($lines)) {
			return array();
		}

		$productIdsByDolistoreRef = $this->resolveProductIdsByDolistoreRefs($lines);
		$productIds = array();
		foreach ($lines as $line) {
			$productId = (int) $line->fk_product;
			$dolistoreRef = trim((string) $line->product_dolistore_ref);
			if ($productId <= 0 && $dolistoreRef !== '' && !empty($productIdsByDolistoreRef[$dolistoreRef])) {
				$productId = (int) $productIdsByDolistoreRef[$dolistoreRef];
			}
			if ($productId > 0) {
				$productIds[$productId] = $productId;
			}
		}
		$products = $this->fetchProductsByIds($productIds);

		$groups = array();
		foreach ($lines as $line) {
			$dolistoreRef = trim((string) $line->product_dolistore_ref);
			$productId = (int) $line->fk_product;
			if ($productId <= 0 && $dolistoreRef !== '' && !empty($productIdsByDolistoreRef[$dolistoreRef])) {
				$productId = (int) $productIdsByDolistoreRef[$dolistoreRef];
			}

			$key = strtolower($dolistoreRef).'|'.$productId;
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'product_dolistore_ref' => $dolistoreRef,
					'product_label' => (string) $line->product_label,
					'fk_product' => $productId,
					'product' => !empty($products[$productId]) ? $products[$productId] : null,
					'qty' => 0.0,
					'unit_price_ht' => 0.0,
					'total_ht' => 0.0,
					'billable_unit_price_ht' => 0.0,
					'billable_total_ht' => 0.0,
				);
			}

			if ($groups[$key]['product_label'] === '' && !empty($line->product_label)) {
				$groups[$key]['product_label'] = (string) $line->product_label;
			}
			if (empty($groups[$key]['product']) && !empty($products[$productId])) {
				$groups[$key]['product'] = $products[$productId];
			}

			$groups[$key]['qty'] += (float) $line->qty;
			$groups[$key]['total_ht'] += (float) $line->total_ht;
			$groups[$key]['billable_total_ht'] += (float) $line->billable_total_ht;
		}

		foreach ($groups as &$group) {
			$qty = (float) $group['qty'];
			if (abs($qty) > 0.0000001) {
				$group['unit_price_ht'] = (float) $group['total_ht'] / $qty;
				$group['billable_unit_price_ht'] = (float) $group['billable_total_ht'] / $qty;
			}
		}
		unset($group);

		return array_values($groups);
	}

	/**
	 * Generate a document for the DoliStore order.
	 *
	 * @param string         $modele           Model name
	 * @param Translate     $outputlangs      Output language
	 * @param int           $hidedetails      Hide details
	 * @param int           $hidedesc         Hide description
	 * @param int           $hideref          Hide reference
	 * @param array<string,mixed>|null $moreparams More parameters
	 * @return int
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		if (empty($modele)) {
			$modele = !empty($this->model_pdf) ? $this->model_pdf : getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON_PDF', 'standard');
		}

		return $this->commonGenerateDocument('core/modules/dolistoreextract/doc/', $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	/**
	 * Recalculate totals from lines and update object.
	 *
	 * @param User $user User
	 * @return int
	 */
	public function updateTotalsFromLines($user)
	{
		$totalHt = 0;
		$totalTva = 0;
		$totalTtc = 0;
		$billableTotalHt = 0;
		foreach ($this->getLines() as $line) {
			$totalHt += (float) $line->total_ht;
			$totalTva += (float) $line->total_tva;
			$totalTtc += (float) $line->total_ttc;
			$billableTotalHt += (float) $line->billable_total_ht;
		}

		$this->total_ht = $totalHt;
		$this->total_tva = $totalTva;
		$this->total_ttc = $totalTtc;
		$this->billable_total_ht = $billableTotalHt;

		return $this->update($user, 1);
	}

	/**
	 * Check invoiceable state.
	 *
	 * @param int|null $today Reference date timestamp
	 * @return bool
	 */
	public function isInvoiceable($today = null)
	{
		$today = $today ?: dol_now();
		$statusAllowed = in_array((int) $this->status, array(self::STATUS_IMPORTED, self::STATUS_WAITING_RELEASE, self::STATUS_INVOICEABLE), true);
		$releaseDate = $this->normalizeTimestamp($this->release_date);

		return $statusAllowed && empty($this->fk_facture) && $releaseDate > 0 && $releaseDate <= $today;
	}

	/**
	 * Mark order as invoiced.
	 *
	 * @param int  $fkFacture Invoice id
	 * @param int  $invoiceDate Invoice date timestamp
	 * @param User $user User
	 * @param int  $notrigger 1 to disable triggers
	 * @return int
	 */
	public function markAsInvoiced($fkFacture, $invoiceDate, $user, $notrigger = 0)
	{
		$this->fk_facture = (int) $fkFacture;
		$this->invoice_date = $invoiceDate;
		$this->status = self::STATUS_INVOICED;
		$result = $this->update($user, 1);
		if ($result > 0 && !$notrigger) {
			$resultTrigger = $this->call_trigger('DOLISTOREEXTRACT_ORDER_INVOICE', $user);
			if ($resultTrigger < 0) {
				return -1;
			}
		}

		return $result;
	}

	/**
	 * Get total amount.
	 *
	 * @param string $field Field to sum
	 * @return float
	 */
	public function getTotalAmount($field = 'billable_total_ht')
	{
		$total = 0;
		foreach ($this->getLines() as $line) {
			$total += (float) ($line->{$field} ?? 0);
		}

		return $total;
	}

	/**
	 * Fetch invoiceable orders.
	 *
	 * @param int|null $today Today timestamp
	 * @return DolistoreOrder[]
	 */
	public function fetchInvoiceableOrders($today = null)
	{
		$today = $today ?: dol_now();
		$orders = array();

		$sql = 'SELECT o.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as o';
		$sql .= ' WHERE o.entity IN ('.getEntity($this->element).')';
		$sql .= ' AND o.status IN ('.self::STATUS_IMPORTED.','.self::STATUS_WAITING_RELEASE.','.self::STATUS_INVOICEABLE.')';
		$sql .= ' AND o.fk_facture IS NULL';
		$sql .= " AND o.release_date <= '".dol_print_date($today, '%Y-%m-%d')."'";
		$sql .= ' ORDER BY o.release_date ASC, o.rowid ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$order = new self($this->db);
			$order->setVarsFromObject($obj);
			$orders[] = $order;
		}
		$this->db->free($resql);

		return $orders;
	}

	/**
	 * Return next internal reference.
	 *
	 * @return string
	 */
	public function getNextNumRef()
	{
		global $conf;

		$module = getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON');
		if ($module === '') {
			$module = 'mod_dolistoreextract_order_dse';
		}
		if (substr($module, -4) === '.php') {
			$module = substr($module, 0, -4);
		}

		$file = dol_buildpath('/dolistoreextract/core/modules/dolistoreextract/modules_dolistoreorder.php');
		if (is_readable($file)) {
			require_once $file;
			if (class_exists($module)) {
				$obj = new $module($this->db);
				$next = $obj->getNextValue(!empty($this->entity) ? (int) $this->entity : (int) $conf->entity, $this);
				if (!empty($next)) {
					return $next;
				}
			}
		}

		return 'DSE-'.dol_print_date(dol_now(), '%Y%m').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Fetch native linked objects and expose the invoice stored on the archive.
	 *
	 * @param int|null     $sourceid        Source object id
	 * @param string       $sourcetype      Source object type
	 * @param int|null     $targetid        Target object id
	 * @param string       $targettype      Target object type
	 * @param string       $clause          SQL clause between source and target filters
	 * @param int          $alsosametype    Include links to objects with same type
	 * @param string       $orderby         SQL order by
	 * @param int|string   $loadalsoobjects Load linked objects
	 * @return int
	 */
	public function fetchObjectLinked($sourceid = null, $sourcetype = '', $targetid = null, $targettype = '', $clause = 'OR', $alsosametype = 1, $orderby = 'sourcetype', $loadalsoobjects = 1)
	{
		$result = parent::fetchObjectLinked($sourceid, $sourcetype, $targetid, $targettype, $clause, $alsosametype, $orderby, $loadalsoobjects);
		if ($result < 0 || empty($this->fk_facture)) {
			return $result;
		}
		if (function_exists('isModEnabled') && !isModEnabled('invoice')) {
			return $result;
		}
		if (empty($loadalsoobjects) || (!is_numeric($loadalsoobjects) && $loadalsoobjects !== 'facture')) {
			return $result;
		}

		$invoiceId = (int) $this->fk_facture;
		foreach (($this->linkedObjects['facture'] ?? array()) as $linkedObject) {
			if (!empty($linkedObject->id) && (int) $linkedObject->id === $invoiceId) {
				return $result;
			}
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$invoice = new Facture($this->db);
		if ($invoice->fetch($invoiceId) > 0) {
			$linkKey = 'dolistoreextract_fk_facture_'.$invoiceId;
			$this->linkedObjectsIds['facture'][$linkKey] = $invoiceId;
			$this->linkedObjects['facture'][$linkKey] = $invoice;
		}

		return $result;
	}

	/**
	 * Return object URL.
	 *
	 * @param int $withpicto Add picto
	 * @return string
	 */
	public function getNomUrl($withpicto = 0)
	{
		$result = '';
		$label = '<u>'.dol_escape_htmltag($this->ref).'</u>';
		if ($withpicto) {
			$result .= img_object($this->ref, $this->picto).' ';
		}
		$result .= '<a href="'.dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $this->id.'">'.$label.'</a>';

		return $result;
	}

	/**
	 * Return status label.
	 *
	 * @param int $mode Display mode
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
	 * @param int $mode Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		$labels = array(
			self::STATUS_DRAFT => 'DolistoreOrderStatusDraft',
			self::STATUS_IMPORTED => 'DolistoreOrderStatusImported',
			self::STATUS_WAITING_RELEASE => 'DolistoreOrderStatusWaitingRelease',
			self::STATUS_INVOICEABLE => 'DolistoreOrderStatusInvoiceable',
			self::STATUS_INVOICED => 'DolistoreOrderStatusInvoiced',
			self::STATUS_ERROR => 'DolistoreOrderStatusError',
		);
		$classes = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_IMPORTED => 'status4',
			self::STATUS_WAITING_RELEASE => 'status1',
			self::STATUS_INVOICEABLE => 'status8',
			self::STATUS_INVOICED => 'status6',
			self::STATUS_ERROR => 'status9',
		);

		$key = $labels[(int) $status] ?? 'Unknown';
		$label = $langs->trans($key);
		if (function_exists('dolGetStatus')) {
			return dolGetStatus($label, '', '', $classes[(int) $status] ?? 'status0', $mode);
		}

		return '<span class="badge badge-status '.($classes[(int) $status] ?? 'status0').'">'.dol_escape_htmltag($label).'</span>';
	}

	/**
	 * Initialize specimen.
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		$this->id = 0;
		$this->ref = 'DSE-'.dol_print_date(dol_now(), '%Y%m').'-0001';
		$this->dolistore_order_ref = 'DS-123456';
		$this->dolistore_order_date = dol_now();
		$this->release_date = dol_time_plus_duree(dol_now(), 30, 'd');
		$this->currency_code = 'EUR';
		$this->customer_name = 'Jean Dupont';
		$this->customer_email = 'jean.dupont@example.com';
		$this->status = self::STATUS_IMPORTED;
	}

	/**
	 * Fetch object by one unique field.
	 *
	 * @param string $field Field name
	 * @param string $value Field value
	 * @return int
	 */
	private function fetchByField($field, $value)
	{
		$value = trim((string) $value);
		if ($value === '') {
			return 0;
		}
		$allowed = array('dolistore_order_ref', 'email_message_id', 'raw_hash');
		if (!in_array($field, $allowed, true)) {
			return -1;
		}

		$sql = 'SELECT o.rowid FROM '.MAIN_DB_PREFIX.$this->table_element.' as o';
		$sql .= ' WHERE o.'.$field.' = '.$this->quoteNullableSqlValue($value);
		$sql .= ' AND o.entity IN ('.getEntity($this->element).')';
		$sql .= ' ORDER BY o.rowid DESC LIMIT 1';
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
	 * Build raw duplicate hash.
	 *
	 * @return string
	 */
	public function buildRawHash()
	{
		return hash('sha256', implode('|', array(
			(string) $this->dolistore_order_ref,
			(string) $this->email_message_id,
			(string) $this->email_subject,
			(string) $this->customer_email,
			(string) $this->total_ht
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
		$this->dolistore_order_date = $this->normalizeTimestamp($obj->dolistore_order_date);
		$this->release_date = $this->normalizeTimestamp($obj->release_date);
		$this->invoice_date = $this->normalizeTimestamp($obj->invoice_date);
		$this->email_date = $this->normalizeTimestamp($obj->email_date);
		$this->datec = $this->normalizeTimestamp($obj->datec);
		$this->socid = (int) $this->fk_soc_customer;
	}

	/**
	 * Normalize date value to timestamp.
	 *
	 * @param mixed $value SQL date or timestamp
	 * @return int
	 */
	private function normalizeTimestamp($value)
	{
		if (empty($value)) {
			return 0;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}

		return (int) $this->db->jdate($value);
	}

	/**
	 * Resolve Dolibarr products from DoliStore product references.
	 *
	 * @param DolistoreOrderLine[] $lines Lines
	 * @return array<string,int>
	 */
	private function resolveProductIdsByDolistoreRefs($lines)
	{
		$refs = array();
		foreach ($lines as $line) {
			if (!empty($line->fk_product)) {
				continue;
			}
			$ref = trim((string) $line->product_dolistore_ref);
			if ($ref !== '') {
				$refs[$ref] = $ref;
			}
		}
		if (empty($refs)) {
			return array();
		}

		$mapping = array();
		if ($this->productIddolistoreColumnExists()) {
			$sql = 'SELECT pe.iddolistore, p.rowid';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'product as p';
			$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields as pe ON pe.fk_object = p.rowid';
			$sql .= ' WHERE p.entity IN ('.getEntity('product').')';
			$sql .= ' AND p.fk_product_type = '.((int) Product::TYPE_SERVICE);
			$sql .= ' AND pe.iddolistore IN ('.$this->buildSqlStringList($refs).')';
			$sql .= ' ORDER BY p.rowid ASC';

			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$ref = (string) $obj->iddolistore;
					if (!isset($mapping[$ref])) {
						$mapping[$ref] = (int) $obj->rowid;
					}
				}
				$this->db->free($resql);
			}
		}

		$remainingRefs = array();
		foreach ($refs as $ref) {
			if (empty($mapping[$ref])) {
				$remainingRefs[$ref] = $ref;
			}
		}
		if (empty($remainingRefs)) {
			return $mapping;
		}

		$sql = 'SELECT p.ref, p.rowid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'product as p';
		$sql .= ' WHERE p.entity IN ('.getEntity('product').')';
		$sql .= ' AND p.fk_product_type = '.((int) Product::TYPE_SERVICE);
		$sql .= ' AND p.ref IN ('.$this->buildSqlStringList($remainingRefs).')';
		$sql .= ' ORDER BY p.rowid ASC';

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$ref = (string) $obj->ref;
				if (!isset($mapping[$ref])) {
					$mapping[$ref] = (int) $obj->rowid;
				}
			}
			$this->db->free($resql);
		}

		return $mapping;
	}

	/**
	 * Fetch products once per unique id.
	 *
	 * @param int[] $productIds Product ids
	 * @return array<int,Product>
	 */
	private function fetchProductsByIds($productIds)
	{
		$products = array();
		foreach (array_unique(array_map('intval', $productIds)) as $productId) {
			if ($productId <= 0) {
				continue;
			}
			$product = new Product($this->db);
			if ($product->fetch($productId) > 0) {
				$products[$productId] = $product;
			}
		}

		return $products;
	}

	/**
	 * Return true when the DoliStore product extrafield SQL column exists.
	 *
	 * @return bool
	 */
	private function productIddolistoreColumnExists()
	{
		$sql = 'SHOW COLUMNS FROM '.MAIN_DB_PREFIX.'product_extrafields LIKE \'iddolistore\'';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$exists = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $exists;
	}

	/**
	 * Build a quoted SQL string list.
	 *
	 * @param string[] $values Values
	 * @return string
	 */
	private function buildSqlStringList($values)
	{
		$quoted = array();
		foreach ($values as $value) {
			$quoted[] = "'".$this->db->escape((string) $value)."'";
		}

		return implode(',', $quoted);
	}

	/**
	 * Convert timestamp to SQL date.
	 *
	 * @param mixed $value Date value
	 * @param bool  $dateOnly Use date only
	 * @return string
	 */
	private function dateToSql($value, $dateOnly = false)
	{
		$timestamp = $this->normalizeTimestamp($value);
		if ($timestamp <= 0) {
			return 'NULL';
		}
		if ($dateOnly) {
			return "'".dol_print_date($timestamp, '%Y-%m-%d')."'";
		}

		return "'".$this->db->idate($timestamp)."'";
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
	 * Return nullable integer SQL value.
	 *
	 * @param mixed $value Value
	 * @return string
	 */
	private function nullableInt($value)
	{
		return ((int) $value > 0) ? (string) ((int) $value) : 'NULL';
	}
}
