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
	public $TRIGGER_PREFIX = 'DOLISTOREEXTRACT_ORDER';
	public $element = 'dolistoreextract_order';
	public $table_element = 'dolistoreextract_order';
	public $picto = 'dolistore@dolistorextract';
	public $ismultientitymanaged = 1;
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'Ref', 'enabled' => 1, 'visible' => -2, 'position' => 1, 'notnull' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => -2, 'position' => 5, 'notnull' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'visible' => 1, 'position' => 10, 'notnull' => 1),
		'ref_ext' => array('type' => 'varchar(255)', 'label' => 'RefExt', 'enabled' => 1, 'visible' => -2, 'position' => 11),
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
		'status' => array('arrayofkeyval' => array(0 => 'DolistoreOrderStatusDraft', 1 => 'DolistoreOrderStatusImported', 2 => 'DolistoreOrderStatusWaitingRelease', 3 => 'DolistoreOrderStatusInvoiceable', 4 => 'DolistoreOrderStatusInvoiced', 9 => 'DolistoreOrderStatusError'), 'type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'position' => 260),
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
	public $currency_code = '';
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
		global $conf;
		$this->db = $db;
		$this->currency_code = (string) ($conf->currency ?? '');
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

		$this->entity = (int) $conf->entity;
		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
		}
		if (empty($this->raw_hash)) {
			$this->raw_hash = $this->buildRawHash();
		}

		if ($this->validateBusinessData($user) < 0) return -1;
		$this->db->begin();
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
		$sql .= 'entity, ref, dolistore_order_ref, dolistore_order_date, release_date, currency_code, total_ht, total_tva, total_ttc, commission_percent, billable_total_ht, customer_name, customer_email, customer_country, customer_country_code, fk_soc_customer, fk_contact_customer, fk_soc_dolistore, fk_facture, invoice_date, email_message_id, email_subject, email_date, email_uid, email_folder, raw_hash, status, note_public, note_private, model_pdf, last_main_doc, import_key, datec, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $this->entity).',';
		$sql .= $this->quoteNullableSqlValue($this->ref).',';
		$sql .= $this->quoteNullableSqlValue($this->dolistore_order_ref).',';
		$sql .= $this->dateToSql($this->dolistore_order_date, true).',';
		$sql .= $this->dateToSql($this->release_date, true).',';
		$sql .= $this->quoteNullableSqlValue($this->currency_code).',';
		$sql .= price2num($this->total_ht, 'MT').',';
		$sql .= price2num($this->total_tva, 'MT').',';
		$sql .= price2num($this->total_ttc, 'MT').',';
		$sql .= price2num($this->commission_percent, 'MU').',';
		$sql .= price2num($this->billable_total_ht, 'MT').',';
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
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;
		$this->socid = (int) $this->fk_soc_customer;

		if (!$notrigger) {
			$result = $this->call_trigger('DOLISTOREEXTRACT_ORDER_CREATE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		if (!$this->db->commit()) return -1;
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
		global $langs;
		if (empty($this->id)) {
			$this->error = $langs->trans('ErrorRecordNotFound');
			return -1;
		}

		if ($this->validateBusinessData($user) < 0) return -1;
		$this->oldcopy = new self($this->db);
		if ($this->oldcopy->fetch($this->id) <= 0 || (int) $this->oldcopy->entity !== (int) $this->entity) return -1;
		$this->db->begin();
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element.' SET';
		$sql .= ' ref = '.$this->quoteNullableSqlValue($this->ref);
		$sql .= ', dolistore_order_ref = '.$this->quoteNullableSqlValue($this->dolistore_order_ref);
		$sql .= ', dolistore_order_date = '.$this->dateToSql($this->dolistore_order_date, true);
		$sql .= ', release_date = '.$this->dateToSql($this->release_date, true);
		$sql .= ', currency_code = '.$this->quoteNullableSqlValue($this->currency_code);
		$sql .= ', total_ht = '.price2num($this->total_ht, 'MT');
		$sql .= ', total_tva = '.price2num($this->total_tva, 'MT');
		$sql .= ', total_ttc = '.price2num($this->total_ttc, 'MT');
		$sql .= ', commission_percent = '.price2num($this->commission_percent, 'MU');
		$sql .= ', billable_total_ht = '.price2num($this->billable_total_ht, 'MT');
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
			$this->db->rollback();
			return -1;
		}
		$this->socid = (int) $this->fk_soc_customer;

		if (!$notrigger) {
			$result = $this->call_trigger('DOLISTOREEXTRACT_ORDER_UPDATE', $user);
			if ($result < 0) {
				$this->db->rollback();
				return -1;
			}
		}

		return $this->db->commit() ? 1 : -1;
	}

	/** Validate metadata and cross-object invariants at every write boundary.
	 * @param User $user Actor
	 * @return int
	 */
	private function validateBusinessData($user)
	{
		global $langs;
		$langs->loadLangs(array('main', 'dolistorextract@dolistorextract'));
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (!$user->hasRight('dolistorextract', 'order', 'write') && !$user->hasRight('dolistorextract', 'order', 'import') && !(($this->context['trigger_reason'] ?? '') === 'invoice_link' && $user->hasRight('dolistorextract', 'invoice', 'generate')))
			|| !in_array((int) $this->entity, array_map('intval', explode(',', getEntity($this->element))), true)) {
			$this->error = $langs->trans('DolistoreWelcomeAccessDenied');
			return -1;
		}
		foreach ($this->fields as $key => $definition) {
			if (in_array($key, array('rowid', 'datec', 'tms', 'fk_user_creat', 'fk_user_modif'), true)) continue;
			$value = $this->{$key};
			if ($value === null && empty($definition['notnull'])) continue;
			if (!$this->validateField($this->fields, $key, (string) $value)) return -1;
		}
		$relations = array('fk_soc_customer' => array('societe', 'societe'), 'fk_soc_dolistore' => array('societe', 'societe'),
			'fk_contact_customer' => array('socpeople', 'contact'), 'fk_facture' => array('facture', 'facture'));
		foreach ($relations as $field => $relation) {
			$id = (int) $this->{$field};
			if (!$id) continue;
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$relation[0].' WHERE rowid = '.$id.' AND entity IN ('.$this->db->sanitize(getEntity($relation[1])).')';
			if ($field === 'fk_contact_customer') $sql .= ' AND fk_soc = '.(int) $this->fk_soc_customer;
			$result = $this->db->query($sql);
			if (!$result || !$this->db->fetch_object($result)) {
				$this->setFieldError($field, $langs->trans('DolistoreInvalidRelation'));
				return -1;
			}
			$this->db->free($result);
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
		global $langs;
		if (!isModEnabled('dolistorextract') || !empty($user->socid) || !$user->hasRight('dolistorextract', 'order', 'delete') || $this->fetch((int) $this->id) <= 0) {
			$this->error = $langs->trans('NotEnoughPermissions'); return -1;
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once __DIR__.'/../lib/dolistoreextract.lib.php';
		$directory = dolistoreextractGetOrderUploadDir($this);
		if ($directory === '') return -1;
		// Keep invoiced archives and deliveries whose SMTP outcome needs review.
		$this->db->begin();
		$queue = $this->db->query('SELECT status FROM '.MAIN_DB_PREFIX.'dolistoreextract_welcome WHERE entity = '.(int) $this->entity.' AND fk_order = '.(int) $this->id.' FOR UPDATE');
		if (!$queue) { $this->error = $this->db->lasterror(); $this->db->rollback(); return -1; }
		$row = $this->db->fetch_object($queue);
		$this->db->free($queue);
		if ($this->fk_facture || (is_object($row) && in_array($row->status, array('sending', 'uncertain'), true))) {
			$this->error = $langs->trans('DolistoreOrderDeleteBlocked'); $this->db->rollback(); return -1;
		}
		$quarantine = '';
		try {
			if (is_dir($directory)) {
				$quarantine = dirname($directory).'/.deleted-'.(int) $this->id.'-'.bin2hex(random_bytes(8));
				if (!rename($directory, $quarantine)) throw new RuntimeException('directory');
			}
			foreach (array('dolistoreextract_order_line', 'dolistoreextract_welcome') as $table) {
				if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE fk_order = '.(int) $this->id.' AND entity = '.(int) $this->entity)) throw new RuntimeException('delete');
			}
			// Retain the audit trail, without a dangling object link.
			if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'dolistoreextract_import_log SET fk_order = NULL WHERE fk_order = '.(int) $this->id.' AND entity = '.(int) $this->entity)) throw new RuntimeException('log');
			if ($this->deleteObjectLinked(null, '', null, '', 0, $user, 1) < 0) throw new RuntimeException('links');
			if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE rowid = '.(int) $this->id.' AND entity = '.(int) $this->entity)) throw new RuntimeException('order');
			if (!$notrigger && $this->call_trigger('DOLISTOREEXTRACT_ORDER_DELETE', $user) < 0) throw new RuntimeException('trigger');
		} catch (Throwable $e) {
			$this->db->rollback();
			if ($quarantine !== '' && is_dir($quarantine)) rename($quarantine, $directory);
			$this->error = $langs->trans('Error');
			return -1;
		}
		if (!$this->db->commit()) {
			// Unknown commit outcome: keep the recoverable files in quarantine.
			$this->error = $langs->trans('DolistoreImportCommitFailed'); return -1;
		}
		if ($quarantine !== '' && (file_exists($directory) || !rename($quarantine, $directory) || dol_delete_dir_recursive($directory) < 0)) {
			dol_syslog(__METHOD__.' document cleanup required for order='.(int) $this->id, LOG_ERR);
		}
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
		$lines = $line->fetchAllByOrder((int) $this->id);
		if (!empty($line->error)) $this->error = $line->error;
		return $lines;
	}

	/**
	 * Publish the single CREATE event once all imported lines and sources exist.
	 * The caller owns the enclosing transaction; no transport runs from this method.
	 * @param User $user Import actor
	 * @param string $lang Purchase language
	 * @param array<string,mixed> $buyerData Purchase snapshot
	 * @return int
	 */
	public function completePurchaseImport($user, string $lang, array $buyerData): int
	{
		global $langs;
		if (!$user->hasRight('dolistorextract', 'order', 'import') || !empty($user->socid) || $this->db->transaction_opened <= 0 || (int) $this->id <= 0) {
			$this->error = $langs->trans('DolistoreWelcomeAccessDenied');
			return -1;
		}
		$langs->load('dolistorextract@dolistorextract');
		$this->context['trigger_reason'] = 'purchase_import_complete';
		$this->context['purchase_lang'] = $lang;
		$this->context['purchase_firstname'] = (string) ($buyerData['buyer_firstname'] ?? '');
		$this->context['purchase_lastname'] = (string) ($buyerData['buyer_lastname'] ?? '');
		$this->context['actionmsg2'] = $langs->transnoentities('DolistoreOrderImported', $this->dolistore_order_ref);
		$this->context['actionmsg'] = $this->context['actionmsg2'];
		try {
			return $this->call_trigger('DOLISTOREEXTRACT_ORDER_CREATE', $user);
		} finally {
			unset($this->context['actionmsg'], $this->context['actionmsg2'], $this->context['purchase_firstname'], $this->context['purchase_lastname']);
		}
	}

	/**
	 * Return lines grouped for native card/document rendering.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getGroupedLinesForDisplay()
	{
		require_once __DIR__.'/dolistoreProductIdentity.class.php';
		$lines = $this->getLines();
		$groups = array();
		foreach ($lines as $line) {
			$key = DolistoreProductIdentity::key((string) $line->product_dolistore_ref, (int) $line->fk_product, (int) $line->id);
			if (!isset($groups[$key])) {
				$groups[$key] = array('product_dolistore_ref' => (string) $line->product_dolistore_ref,
					'product_label' => (string) $line->product_label, 'fk_product' => 0, 'product' => null,
					'qty' => 0.0, 'total_ht' => 0.0, 'billable_total_ht' => 0.0,
					'unit_price_ht' => 0.0, 'billable_unit_price_ht' => 0.0, 'conflict' => false);
			}
			$groups[$key]['qty'] += (float) $line->qty;
			$groups[$key]['total_ht'] += (float) $line->total_ht;
			$groups[$key]['billable_total_ht'] += (float) $line->billable_total_ht;
		}
		$identity = new DolistoreProductIdentity($this->db);
		$definitions = $identity->getDefinitions(array_keys($groups));
		if ($identity->error !== '') $this->error = $identity->error;
		foreach ($groups as $key => &$group) {
			if (isset($definitions[$key])) {
				$group['product_label'] = $definitions[$key]['label'];
				$group['fk_product'] = $definitions[$key]['fk_product'];
				$group['conflict'] = $definitions[$key]['conflict'];
			}
			if ($group['qty'] != 0) {
				$group['unit_price_ht'] = price2num($group['total_ht'] / $group['qty'], 'MU');
				$group['billable_unit_price_ht'] = price2num($group['billable_total_ht'] / $group['qty'], 'MU');
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
		global $user, $langs, $conf;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| !$user->hasRight('dolistorextract', 'order', 'read') || !$user->hasRight('dolistorextract', 'order', 'write')
			|| !in_array((int) $this->entity, array_map('intval', explode(',', getEntity($this->element))), true)) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		if (empty($modele)) {
			$modele = !empty($this->model_pdf) ? $this->model_pdf : getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON_PDF', 'standard');
		}

		$originalConf = $conf;
		try {
			if ((int) $this->entity !== (int) $conf->entity) {
				$ownerConf = new Conf();
				$ownerConf->db = clone $conf->db;
				$ownerConf->file = clone $conf->file;
				if (isset($conf->multicompany)) $ownerConf->multicompany = clone $conf->multicompany;
				$ownerConf->entity = (int) $conf->entity;
				if ($ownerConf->setEntityValues($this->db, (int) $this->entity) < 0) {
					$this->error = $langs->trans('DolistoreDocumentDirectoryUnavailable'); return -1;
				}
				$conf = $ownerConf;
			}
			// Native ECM indexing also runs in the document owner's entity.
			return $this->commonGenerateDocument('core/modules/dolistoreextract/doc/', $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
		} finally {
			$conf = $originalConf;
		}
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

		if (!empty($this->error)) return -1;

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
		global $langs;
		if (!$user->hasRight('dolistorextract', 'invoice', 'generate') || !empty($user->socid) || $this->fetch((int) $this->id) <= 0 || !$this->isInvoiceable()) {
			$this->error = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		$oldcopy = clone $this;
		$this->context['trigger_reason'] = 'invoice_link';

		$this->fk_facture = (int) $fkFacture;
		$this->invoice_date = $invoiceDate;
		$this->status = self::STATUS_INVOICED;

		if (!$notrigger) {
			$this->oldcopy = $oldcopy;

			$oldInvoiceDate = $this->normalizeTimestamp($oldcopy->invoice_date);
			$newInvoiceDate = $this->normalizeTimestamp($this->invoice_date);
			$context = isset($this->context) && is_array($this->context) ? $this->context : array();
			$context['trigger_reason'] = 'invoice_link';
			$context['changed_fields'] = array('fk_facture', 'invoice_date', 'status');
			$context['old_fk_facture'] = !empty($oldcopy->fk_facture) ? (int) $oldcopy->fk_facture : null;
			$context['new_fk_facture'] = (int) $this->fk_facture;
			$context['old_invoice_date'] = $oldInvoiceDate > 0 ? $oldInvoiceDate : null;
			$context['new_invoice_date'] = $newInvoiceDate > 0 ? $newInvoiceDate : null;
			$context['old_status'] = isset($oldcopy->status) ? (int) $oldcopy->status : null;
			$context['new_status'] = (int) $this->status;
			$this->context = $context;
		}

		try {
			return $this->update($user, $notrigger);
		} finally {
			unset($this->context['trigger_reason']);
		}
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
	 * @param int|null $entity Strict entity, shared entities when omitted
	 * @return DolistoreOrder[]
	 */
	public function fetchInvoiceableOrders($today = null, $entity = null)
	{
		$today = $today ?: dol_now();
		$orders = array();

		$sql = 'SELECT o.* FROM '.MAIN_DB_PREFIX.$this->table_element.' as o';
		if ($entity !== null) {
			$sql .= ' WHERE o.entity = '.((int) $entity);
		} else {
			$sql .= ' WHERE o.entity IN ('.getEntity($this->element).')';
		}
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
		if (!preg_match('/^mod_dolistoreextract_order_[a-z0-9_]+$/D', $module)) return '';

		$modules = array($module);
		foreach ($modules as $moduleToLoad) {
			$file = dol_buildpath('/dolistorextract/core/modules/dolistoreextract/'.$moduleToLoad.'.php');
			if (!is_readable($file)) {
				continue;
			}
			require_once $file;
			if (class_exists($moduleToLoad)) {
				$obj = new $moduleToLoad($this->db);
				$next = $obj->getNextValue(!empty($this->entity) ? (int) $this->entity : (int) $conf->entity, $this);
				if (!empty($next)) {
					return $next;
				}
			}
		}

		return ''; // A missing/failed configured model must fail native ref validation.
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
		global $user;
		if (!isModEnabled('invoice') || !$user->hasRight('facture', 'lire')) {
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
		if ($invoice->fetch($invoiceId) > 0 && in_array((int) $invoice->entity, array_map('intval', explode(',', getEntity('facture'))), true)) {
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
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $user;

		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| !$user->hasRight('dolistorextract', 'order', 'read')
			|| !in_array((int) $this->entity, array_map('intval', explode(',', getEntity($this->table_element))), true)) {
			return '';
		}
		$notooltip = $notooltip || !empty($conf->dol_no_mouse_hover);
		$params = array('id' => (int) $this->id, 'objecttype' => $this->element.'@'.$this->module);
		$attributes = '';
		if (!$notooltip) {
			if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
				$morecss .= ' classforajaxtooltip';
				$attributes .= ' data-params="'.dol_escape_htmltag(json_encode($params)).'" title="tocomplete"';
			} else {
				$morecss .= ' classfortooltip';
				$attributes .= ' title="'.dol_escape_htmltag(implode('', $this->getTooltipContentArray($params)), 1).'"';
			}
		}
		$url = dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $this->id;
		if ($save_lastsearch_value == 1 || ($save_lastsearch_value == -1 && preg_match('/list\.php$/', $_SERVER['PHP_SELF'] ?? ''))) {
			$url .= '&save_lastsearch_values=1';
		}
		$tag = $option === 'nolink' ? 'span' : 'a';
		$result = '<'.$tag.($tag === 'a' ? ' href="'.$url.'"' : '').$attributes.' class="'.dol_escape_htmltag(trim($morecss)).'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="pictofixedwidth valignmiddle"', 0, 0, 1);
		}
		if ($withpicto != 2) $result .= dol_escape_htmltag($this->ref);
		return $result.'</'.$tag.'>';
	}

	/**
	 * Content used by the native synchronous and Ajax tooltips (Dolibarr 20+).
	 * @param array<string, int|string> $params Tooltip parameters
	 * @return array<string, string>
	 */
	public function getTooltipContentArray($params)
	{
		global $langs, $user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| !$user->hasRight('dolistorextract', 'order', 'read')
			|| !in_array((int) $this->entity, array_map('intval', explode(',', getEntity($this->table_element))), true)) {
			return array();
		}
		$langs->load('dolistorextract@dolistorextract');
		return array(
			'title' => '<div class="centpercent nowrap">'.$langs->trans('DolistoreOrder').'</div>',
			'ref' => '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref),
			'dolistore_ref' => '<br><b>'.$langs->trans('DolistoreOrderRef').':</b> '.dol_escape_htmltag($this->dolistore_order_ref),
			'date' => '<br><b>'.$langs->trans('Date').':</b> '.dol_print_date($this->dolistore_order_date, 'day'),
			'status' => '<br>'.$this->getLibStatut(5),
		);
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
		return dolGetStatus($label, '', '', $classes[(int) $status] ?? 'status0', $mode);
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
		global $conf;
		$this->currency_code = (string) $conf->currency;
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
		global $conf;
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
		$sql .= ' AND o.entity = '.(int) $conf->entity;
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
	public function setVarsFromObject($obj)
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
