<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

use Luracast\Restler\RestException;

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
		global $db, $langs;
		$langs->loadLangs(array('main', 'dolistorextract@dolistorextract'));
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'read'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$limit = max(1, min(500, (int) $limit));
		$offset = max(0, (int) $page) * $limit;

		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'dolistoreextract_order';
		$sql .= ' WHERE entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit($limit, $offset);
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $langs->trans('DolistoreArchiveWriteFailed'));
		}

		$result = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$order = new DolistoreOrder($this->db);
			$order->setVarsFromObject($obj);
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'read'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, $langs->trans('ErrorRecordNotFound'));
		}

		$data = $this->cleanOrder($order);
		require_once __DIR__.'/dolistoreWelcomeMail.class.php';
		$welcome = new DolistoreWelcomeMail($this->db);
		$data['welcome_delivery'] = $welcome->getStatus((int) $order->id);
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'import'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$user = DolibarrApiAccess::$user;
		if (!is_array($request_data)) throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'body'));
		$data = $request_data;
		$order = new DolistoreOrder($this->db);
		$this->fillOrderFromArray($order, $data, 'create');
		$duplicateId = $this->findDuplicateOrderId($order);
		if ($duplicateId > 0) {
			throw new RestException(409, $langs->trans('DolistoreOrderAlreadyExists', $duplicateId));
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'write'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$user = DolibarrApiAccess::$user;
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, $langs->trans('ErrorRecordNotFound'));
		}
		if (!is_array($request_data)) throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'body'));
		$this->fillOrderFromArray($order, $request_data, 'update');
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'delete'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$user = DolibarrApiAccess::$user;
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $id) <= 0) {
			throw new RestException(404, $langs->trans('ErrorRecordNotFound'));
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
		global $langs;
		$user = DolibarrApiAccess::$user;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'api', 'read'))
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'invoice', 'generate'))) {
			throw new RestException(403, $langs->trans('NotEnoughPermissions'));
		}
		$actions = new ActionsDolistorextract($this->db);
		$result = $actions->generateMonthlyDolistoreInvoice(DolibarrApiAccess::$user, true);
		if ($result < 0) {
			throw new RestException(500, $actions->error ?: implode("\n", $actions->errors));
		}

		return array('invoice_id' => $result);
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
		global $langs;
		foreach ($data as $key => $value) {
			if ($value !== null && !is_scalar($value)) {
				throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'value'));
			}
		}
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
		if (array_key_exists('note_private', $data) && ($mode === 'create' || (!empty(DolibarrApiAccess::$user->admin) || DolibarrApiAccess::$user->hasRight('dolistorextract', 'order', 'write')))) {
			$order->note_private = (string) $data['note_private'];
		}

		$amountFields = ($mode === 'create') ? array('total_ht', 'total_tva', 'total_ttc', 'commission_percent', 'billable_total_ht') : array();
		foreach ($amountFields as $field) {
			if (array_key_exists($field, $data)) {
				if (!is_numeric($data[$field])) {
					throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'amount'));
				}
				$order->{$field} = (float) $data[$field];
			}
		}
		$intFields = array('fk_soc_customer', 'fk_contact_customer', 'fk_soc_dolistore');
		if ($mode === 'create') {
			$intFields[] = 'email_uid';
		}
		foreach ($intFields as $field) {
			if (array_key_exists($field, $data)) {
				if (!ctype_digit((string) $data[$field])) {
					throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'id'));
				}
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
		global $langs;
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
		if ((!empty(DolibarrApiAccess::$user->admin) || DolibarrApiAccess::$user->hasRight('dolistorextract', 'order', 'write'))) {
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
		global $langs;
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
		global $langs;
		if ($value === null || $value === '') {
			return 0;
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		$timestamp = strtotime((string) $value);
		if ($timestamp === false) {
			throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', $field));
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
		global $langs;
		if (!is_int($status) && !(is_string($status) && ctype_digit($status))) throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'status'));
		$status = (int) $status;
		$allowed = array(
			DolistoreOrder::STATUS_DRAFT,
			DolistoreOrder::STATUS_IMPORTED,
			DolistoreOrder::STATUS_WAITING_RELEASE,
			DolistoreOrder::STATUS_INVOICEABLE,
			DolistoreOrder::STATUS_ERROR,
		);
		if (!in_array($status, $allowed, true)) {
			throw new RestException(400, $langs->trans('DolistoreApiInvalidValue', 'status'));
		}

		return $status;
	}

	/**
	 * Find duplicate order from API payload.
	 *
	 * @param DolistoreOrder $order Order
	 * @return int
	 */
	private function findDuplicateOrderId(DolistoreOrder $order)
	{
		global $langs;
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
