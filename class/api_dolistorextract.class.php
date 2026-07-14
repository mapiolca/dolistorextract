<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
require_once __DIR__.'/dolistoreOrder.class.php';
require_once __DIR__.'/dolistoreOrderLine.class.php';
require_once __DIR__.'/actions_dolistorextract.class.php';

/**
 * API for DolistoreExtract.
 *
 * @access protected
 * @class DolistoreextractApi
 */
class DolistoreextractApi extends DolibarrApi
{
	public $db;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * List DoliStore orders.
	 *
	 * @url GET /orders
	 *
	 * @param int $limit Limit
	 * @param int $page Page
	 * @return array
	 */
	public function getOrders($limit = 100, $page = 0)
	{
		$this->checkAccess('read');
		$limit = max(1, min(500, (int) $limit));
		$offset = max(0, (int) $page) * $limit;

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'dolistoreextract_order';
		$sql .= ' WHERE entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit($limit, $offset);
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}

		$result = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$order = new DolistoreOrder($this->db);
			$order->fetch((int) $obj->rowid);
			$result[] = $this->cleanOrder($order);
		}
		$this->db->free($resql);

		return $result;
	}

	/**
	 * Get one DoliStore order.
	 *
	 * @url GET /orders/{id}
	 *
	 * @param int $id Order id
	 * @return array
	 */
	public function getOrder($id)
	{
		$this->checkAccess('read');
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, 'DoliStore order not found');
		}

		$data = $this->cleanOrder($order);
		$data['lines'] = array();
		foreach ($order->getLines() as $line) {
			$data['lines'][] = $this->cleanLine($line);
		}

		return $data;
	}

	/**
	 * Create one DoliStore order shell.
	 *
	 * @url POST /orders
	 *
	 * @param array $request_data Request data
	 * @return array
	 */
	public function postOrder($request_data = null)
	{
		$this->checkAccess('import');
		$user = DolibarrApiAccess::$user;
		$data = (array) $request_data;
		$order = new DolistoreOrder($this->db);
		$this->fillOrderFromArray($order, $data, 'create');
		$duplicateId = $this->findDuplicateOrderId($order);
		if ($duplicateId > 0) {
			throw new RestException(409, 'DoliStore order already exists: '.$duplicateId);
		}
		$result = $order->create($user);
		if ($result <= 0) {
			throw new RestException(500, $order->error);
		}

		return $this->cleanOrder($order);
	}

	/**
	 * Update a DoliStore order.
	 *
	 * @url PUT /orders/{id}
	 *
	 * @param int   $id Order id
	 * @param array $request_data Request data
	 * @return array
	 */
	public function putOrder($id, $request_data = null)
	{
		$this->checkAccess('write');
		$user = DolibarrApiAccess::$user;
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, 'DoliStore order not found');
		}
		$this->fillOrderFromArray($order, (array) $request_data, 'update');
		if ($order->update($user) <= 0) {
			throw new RestException(500, $order->error);
		}

		return $this->cleanOrder($order);
	}

	/**
	 * Delete a DoliStore order.
	 *
	 * @url DELETE /orders/{id}
	 *
	 * @param int $id Order id
	 * @return array
	 */
	public function deleteOrder($id)
	{
		$this->checkAccess('delete');
		$user = DolibarrApiAccess::$user;
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, 'DoliStore order not found');
		}
		if ($order->delete($user) <= 0) {
			throw new RestException(500, $order->error);
		}

		return array('success' => true);
	}

	/**
	 * Generate monthly invoice.
	 *
	 * @url POST /orders/invoice
	 *
	 * @return array
	 */
	public function generateInvoice()
	{
		$this->checkAccess('invoice');
		$actions = new ActionsDolistorextract($this->db);
		$result = $actions->generateMonthlyDolistoreInvoice(DolibarrApiAccess::$user, true);
		if ($result < 0) {
			throw new RestException(500, $actions->error ?: implode("\n", $actions->errors));
		}

		return array('invoice_id' => $result);
	}

	/**
	 * Check API access.
	 *
	 * @param string $right Right
	 * @return void
	 */
	private function checkAccess($right)
	{
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract')) {
			throw new RestException(403, 'Module disabled');
		}
		if (!$this->hasModuleRight($user, 'api', 'read')) {
			throw new RestException(403, 'API permission denied');
		}
		if ($right === 'read') {
			if (empty($user->admin) && !$this->hasModuleRight($user, 'order', 'read')) {
				throw new RestException(403, 'Permission denied');
			}
			return;
		}
		$map = array(
			'import' => array('order', 'import'),
			'write' => array('order', 'write'),
			'delete' => array('order', 'delete'),
			'invoice' => array('invoice', 'generate'),
		);
		if (!empty($user->admin)) {
			return;
		}
		if (!isset($map[$right]) || !$this->hasModuleRight($user, $map[$right][0], $map[$right][1])) {
			throw new RestException(403, 'Permission denied');
		}
	}

	/**
	 * Check one module right with Dolibarr v20-compatible fallback.
	 *
	 * @param User   $user User
	 * @param string $object Right object
	 * @param string $action Right action
	 * @return bool
	 */
	private function hasModuleRight($user, $object, $action)
	{
		if (!is_object($user)) {
			return false;
		}
		if (!empty($user->admin)) {
			return true;
		}

		return (bool) $user->hasRight('dolistorextract', $object, $action);
	}

	/**
	 * Fill order from request data.
	 *
	 * @param DolistoreOrder $order Order
	 * @param array          $data  Data
	 * @param string         $mode  create|update
	 * @return void
	 */
	private function fillOrderFromArray(DolistoreOrder $order, array $data, $mode)
	{
		$mode = ($mode === 'create') ? 'create' : 'update';
		$textFields = array('currency_code', 'customer_name', 'customer_email', 'customer_country', 'customer_country_code', 'note_public');
		if ($mode === 'create') {
			$textFields = array_merge($textFields, array('dolistore_order_ref', 'email_message_id', 'email_subject', 'email_folder', 'raw_hash'));
		}
		foreach ($textFields as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (string) $data[$field];
			}
		}
		if (array_key_exists('note_private', $data) && ($mode === 'create' || $this->hasModuleRight(DolibarrApiAccess::$user, 'order', 'write'))) {
			$order->note_private = (string) $data['note_private'];
		}

		$amountFields = ($mode === 'create') ? array('total_ht', 'total_tva', 'total_ttc', 'commission_percent', 'billable_total_ht') : array();
		foreach ($amountFields as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (float) $data[$field];
			}
		}
		$intFields = array('fk_soc_customer', 'fk_contact_customer', 'fk_soc_dolistore');
		if ($mode === 'create') {
			$intFields[] = 'email_uid';
		}
		foreach ($intFields as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (int) $data[$field];
			}
		}

		if ($mode === 'create' && array_key_exists('status', $data)) {
			$order->status = $this->normalizeStatus($data['status']);
		}

		$dateFields = ($mode === 'create') ? array('dolistore_order_date', 'release_date', 'invoice_date', 'email_date') : array('dolistore_order_date', 'release_date');
		foreach ($dateFields as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = $this->parseApiDate($data[$field], $field);
			}
		}
	}

	/**
	 * Clean order payload.
	 *
	 * @param DolistoreOrder $order Order
	 * @return array
	 */
	private function cleanOrder(DolistoreOrder $order)
	{
		$data = array(
			'id' => (int) $order->id,
			'rowid' => (int) $order->rowid,
			'entity' => (int) $order->entity,
			'ref' => (string) $order->ref,
			'dolistore_order_ref' => (string) $order->dolistore_order_ref,
			'dolistore_order_date' => (int) $order->dolistore_order_date,
			'release_date' => (int) $order->release_date,
			'currency_code' => (string) $order->currency_code,
			'total_ht' => (float) $order->total_ht,
			'total_tva' => (float) $order->total_tva,
			'total_ttc' => (float) $order->total_ttc,
			'commission_percent' => (float) $order->commission_percent,
			'billable_total_ht' => (float) $order->billable_total_ht,
			'customer_name' => (string) $order->customer_name,
			'customer_email' => (string) $order->customer_email,
			'customer_country' => (string) $order->customer_country,
			'customer_country_code' => (string) $order->customer_country_code,
			'fk_soc_customer' => (int) $order->fk_soc_customer,
			'fk_contact_customer' => (int) $order->fk_contact_customer,
			'fk_soc_dolistore' => (int) $order->fk_soc_dolistore,
			'fk_facture' => (int) $order->fk_facture,
			'invoice_date' => (int) $order->invoice_date,
			'email_message_id' => (string) $order->email_message_id,
			'email_subject' => (string) $order->email_subject,
			'email_date' => (int) $order->email_date,
			'email_uid' => (int) $order->email_uid,
			'email_folder' => (string) $order->email_folder,
			'raw_hash' => (string) $order->raw_hash,
			'status' => (int) $order->status,
			'note_public' => (string) $order->note_public,
			'datec' => (int) $order->datec,
			'tms' => (string) $order->tms,
			'fk_user_creat' => (int) $order->fk_user_creat,
			'fk_user_modif' => (int) $order->fk_user_modif,
		);
		if ($this->canExposePrivateNote()) {
			$data['note_private'] = (string) $order->note_private;
		}

		return $data;
	}

	/**
	 * Clean line payload.
	 *
	 * @param DolistoreOrderLine $line Line
	 * @return array
	 */
	private function cleanLine(DolistoreOrderLine $line)
	{
		return array(
			'id' => (int) $line->id,
			'rowid' => (int) $line->rowid,
			'entity' => (int) $line->entity,
			'fk_order' => (int) $line->fk_order,
			'product_dolistore_ref' => (string) $line->product_dolistore_ref,
			'product_label' => (string) $line->product_label,
			'fk_product' => (int) $line->fk_product,
			'qty' => (float) $line->qty,
			'unit_price_ht' => (float) $line->unit_price_ht,
			'total_ht' => (float) $line->total_ht,
			'total_tva' => (float) $line->total_tva,
			'total_ttc' => (float) $line->total_ttc,
			'billable_unit_price_ht' => (float) $line->billable_unit_price_ht,
			'billable_total_ht' => (float) $line->billable_total_ht,
			'tax_rate' => (float) $line->tax_rate,
			'description' => (string) $line->description,
			'status' => (int) $line->status,
		);
	}

	/**
	 * Parse one API date field.
	 *
	 * @param mixed  $value Date value
	 * @param string $field Field name
	 * @return int
	 */
	private function parseApiDate($value, $field)
	{
		if ($value === null || $value === '') {
			return 0;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = strtotime((string) $value);
		if ($timestamp === false) {
			throw new RestException(400, 'Invalid date for field '.$field);
		}

		return (int) $timestamp;
	}

	/**
	 * Validate order status accepted from controlled import API.
	 *
	 * @param mixed $status Status value
	 * @return int
	 */
	private function normalizeStatus($status)
	{
		$status = (int) $status;
		$allowed = array(
			DolistoreOrder::STATUS_DRAFT,
			DolistoreOrder::STATUS_IMPORTED,
			DolistoreOrder::STATUS_WAITING_RELEASE,
			DolistoreOrder::STATUS_INVOICEABLE,
			DolistoreOrder::STATUS_ERROR,
		);
		if (!in_array($status, $allowed, true)) {
			throw new RestException(400, 'Invalid DoliStore order status');
		}

		return $status;
	}

	/**
	 * Check if private note can be exposed through API.
	 *
	 * @return bool
	 */
	private function canExposePrivateNote()
	{
		return $this->hasModuleRight(DolibarrApiAccess::$user, 'order', 'write');
	}

	/**
	 * Find duplicate order from API payload.
	 *
	 * @param DolistoreOrder $order Order
	 * @return int
	 */
	private function findDuplicateOrderId(DolistoreOrder $order)
	{
		$lookup = new DolistoreOrder($this->db);
		if (!empty($order->dolistore_order_ref) && $lookup->fetchByDolistoreRef($order->dolistore_order_ref) > 0) {
			return (int) $lookup->id;
		}
		$lookup = new DolistoreOrder($this->db);
		if (!empty($order->email_message_id) && $lookup->fetchByEmailMessageId($order->email_message_id) > 0) {
			return (int) $lookup->id;
		}
		$lookup = new DolistoreOrder($this->db);
		if (!empty($order->raw_hash) && $lookup->fetchByRawHash($order->raw_hash) > 0) {
			return (int) $lookup->id;
		}

		return 0;
	}
}
