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
			$data['lines'][] = get_object_vars($line);
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
		$this->fillOrderFromArray($order, $data);
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
		$this->fillOrderFromArray($order, (array) $request_data);
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
		if (!empty($user->admin)) {
			return true;
		}
		if (method_exists($user, 'hasRight')) {
			return (bool) $user->hasRight('dolistorextract', $object, $action);
		}

		return !empty($user->rights->dolistorextract->{$object}->{$action});
	}

	/**
	 * Fill order from request data.
	 *
	 * @param DolistoreOrder $order Order
	 * @param array          $data  Data
	 * @return void
	 */
	private function fillOrderFromArray(DolistoreOrder $order, array $data)
	{
		foreach (array('dolistore_order_ref', 'currency_code', 'customer_name', 'customer_email', 'customer_country', 'customer_country_code', 'email_message_id', 'email_subject', 'raw_hash', 'note_private', 'note_public') as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (string) $data[$field];
			}
		}
		foreach (array('total_ht', 'total_tva', 'total_ttc', 'commission_percent', 'billable_total_ht') as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (float) $data[$field];
			}
		}
		foreach (array('status', 'fk_soc_customer', 'fk_contact_customer', 'fk_soc_dolistore', 'fk_facture') as $field) {
			if (array_key_exists($field, $data)) {
				$order->{$field} = (int) $data[$field];
			}
		}
		foreach (array('dolistore_order_date', 'release_date', 'invoice_date', 'email_date') as $field) {
			if (!empty($data[$field])) {
				$order->{$field} = is_numeric($data[$field]) ? (int) $data[$field] : strtotime((string) $data[$field]);
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
		$data = get_object_vars($order);
		unset($data['db'], $data['errors']);
		return $data;
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
