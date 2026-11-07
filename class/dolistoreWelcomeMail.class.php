<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/dolistoreOrder.class.php';
require_once __DIR__.'/dolistoreImportLog.class.php';

/**
 * Purchase welcome delivery. The CRUD trigger queues inside the import transaction;
 * transport runs only after commit. R-12.7: native Notifications subscriptions do
 * not represent the buyer of each archived purchase or uncertain SMTP outcomes.
 */
class DolistoreWelcomeMail
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var list<string> */
	public $errors = array();
	public const LANGUAGES = array('fr_FR' => 'FR', 'en_US' => 'EN', 'es_ES' => 'ES', 'it_IT' => 'IT', 'de_DE' => 'DE');
	public const STATUS_KEYS = array('pending' => 'DolistoreWelcomePending', 'sending' => 'DolistoreWelcomeSending', 'sent' => 'DolistoreWelcomeSent', 'failed' => 'DolistoreWelcomeFailed', 'uncertain' => 'DolistoreWelcomeUncertain');

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Queue only a completed new purchase, never historical orders or API shells.
	 * Buyer names are an immutable purchase snapshot for legacy substitutions.
	 * @param DolistoreOrder $order Order
	 * @param User $user Import actor
	 * @return int 1 queued/already queued, 0 disabled/not applicable, -1 error
	 */
	public function enqueue(DolistoreOrder $order, $user): int
	{
		global $conf, $langs;
		$langs->load('dolistorextract@dolistorextract');
		if (!isModEnabled('dolistorextract') || getDolGlobalInt('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')
			|| ($order->context['trigger_reason'] ?? '') !== 'purchase_import_complete') {
			return 0;
		}
		if (!empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'import'))
			|| $this->db->transaction_opened <= 0 || (int) $order->id <= 0 || (int) $order->entity !== (int) $conf->entity) {
			$this->error = 'DolistoreWelcomeAccessDenied';
			return -1;
		}
		$lang = (string) ($order->context['purchase_lang'] ?? '');
		if (!isset(self::LANGUAGES[$lang])) {
			$lang = 'en_US';
		}
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'dolistoreextract_welcome';
		$sql .= ' (entity, fk_order, lang, snapshot_firstname, snapshot_lastname, status, next_attempt, date_creation, fk_user_creat)';
		$sql .= ' VALUES ('.((int) $order->entity).', '.((int) $order->id).", '".$this->db->escape($lang)."', '";
		$sql .= $this->db->escape((string) ($order->context['purchase_firstname'] ?? ''))."', '";
		$sql .= $this->db->escape((string) ($order->context['purchase_lastname'] ?? ''))."', 'pending', '";
		$sql .= $this->db->idate(dol_now())."', '".$this->db->idate(dol_now())."', ".((int) $user->id).')';
		// A replay must preserve the first delivery and its state.
		$sql .= ' ON DUPLICATE KEY UPDATE fk_order = fk_order';
		$inserted = $this->db->query($sql);
		if (!$inserted) {
			$this->error = 'DolistoreWelcomeStorageError';
			return -1;
		}
		if ($this->db->affected_rows($inserted) === 1 && DolistoreImportLog::add($this->db, 'info', $langs->transnoentities('DolistoreWelcomePending'), (int) $order->id, 'welcome', array(), $user) < 0) {
			$this->error = 'DolistoreWelcomeStorageError'; return -1;
		}
		return 1;
	}

	/**
	 * Send due requests in the current owning entity, outside any transaction.
	 * @param User $user Actor with import permission
	 * @param int $orderId Optional order to process immediately
	 * @return int Number sent, -1 on storage/access error
	 */
	public function process($user, int $orderId = 0): int
	{
		global $conf, $langs;
		$langs->load('dolistorextract@dolistorextract');
		if (!isModEnabled('dolistorextract') || getDolGlobalInt('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')) {
			return 0;
		}
		if (!empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'import')) || $this->db->transaction_opened > 0) {
			$this->error = $langs->trans('DolistoreWelcomeAccessDenied');
			return -1;
		}
		$entity = (int) $conf->entity;
		// An interrupted process may have delivered its message. Never requeue it.
		$expired = $this->db->query('SELECT rowid, fk_order FROM '.MAIN_DB_PREFIX."dolistoreextract_welcome WHERE entity = ".$entity." AND status = 'sending' AND started_at < '".$this->db->idate(dol_now() - 900)."'");
		if (!$expired) { $this->error = $langs->trans('DolistoreWelcomeStorageError'); return -1; }
		$interrupted = array();
		while (is_object($row = $this->db->fetch_object($expired))) $interrupted[] = $row;
		$this->db->free($expired);
		foreach ($interrupted as $row) {
			$result = $this->db->query('UPDATE '.MAIN_DB_PREFIX."dolistoreextract_welcome SET status = 'uncertain', lock_token = NULL, next_attempt = NULL, last_error = 'DolistoreWelcomeInterrupted' WHERE rowid = ".(int) $row->rowid.' AND entity = '.$entity." AND status = 'sending' AND started_at < '".$this->db->idate(dol_now() - 900)."'");
			if (!$result) { $this->error = $langs->trans('DolistoreWelcomeStorageError'); return -1; }
			if ($this->db->affected_rows($result) === 1) DolistoreImportLog::add($this->db, 'warning', $langs->transnoentities('DolistoreWelcomeInterrupted'), (int) $row->fk_order, 'welcome', array(), $user);
		}
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'dolistoreextract_welcome WHERE entity = '.$entity;
		$sql .= " AND status IN ('pending', 'failed') AND next_attempt IS NOT NULL AND next_attempt <= '".$this->db->idate(dol_now())."'";
		if ($orderId > 0) {
			$sql .= ' AND fk_order = '.$orderId;
		}
		$sql .= ' ORDER BY next_attempt, rowid'.$this->db->plimit(50);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $langs->trans('DolistoreWelcomeStorageError');
			return -1;
		}
		$rows = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$rows[] = $row;
		}
		$this->db->free($resql);
		$sent = 0;
		foreach ($rows as $row) {
			$token = bin2hex(random_bytes(16));
			$sql = 'UPDATE '.MAIN_DB_PREFIX."dolistoreextract_welcome SET status = 'sending', attempts = attempts + 1, started_at = '".$this->db->idate(dol_now())."', lock_token = '".$token."'";
			$sql .= ' WHERE rowid = '.((int) $row->rowid).' AND entity = '.$entity." AND status IN ('pending', 'failed')";
			$sql .= " AND next_attempt IS NOT NULL AND next_attempt <= '".$this->db->idate(dol_now())."'";
			$claim = $this->db->query($sql);
			if (!$claim) {
				$this->error = $langs->trans('DolistoreWelcomeStorageError');
				return -1;
			}
			if ($this->db->affected_rows($claim) !== 1) {
				continue;
			}
			DolistoreImportLog::add($this->db, 'info', $langs->transnoentities('DolistoreWelcomeSending'), (int) $row->fk_order, 'welcome', array(), $user);
			$attempt = (int) $row->attempts + 1;
			$status = 'failed';
			$reason = '';
			$transportStarted = false;
			try {
				$message = $this->prepare($row, $user);
				$transportStarted = true;
				$status = $this->transmit($message) ? 'sent' : 'uncertain';
				$reason = $status === 'uncertain' ? 'DolistoreWelcomeTransportUncertain' : '';
			} catch (Throwable $e) {
				$status = $transportStarted ? 'uncertain' : 'failed';
				// Never persist SMTP exceptions, recipients or credentials in logs.
				$reason = $transportStarted ? 'DolistoreWelcomeTransportUncertain' : $this->error;
				if ($reason === '' || !preg_match('/^DolistoreWelcome[A-Za-z]+$/D', $reason)) {
					$reason = 'DolistoreWelcomePreparationFailed';
				}
			}
			$firstFailure = !empty($row->first_failure) ? $this->db->jdate($row->first_failure) : dol_now();
			$next = self::nextAttempt($status, $attempt, (int) $firstFailure);
			$sql = 'UPDATE '.MAIN_DB_PREFIX."dolistoreextract_welcome SET status = '".$status."', lock_token = NULL";
			$sql .= ', next_attempt = '.($next === null ? 'NULL' : "'".$this->db->idate($next)."'");
			$sql .= ', sent_at = '.($status === 'sent' ? "'".$this->db->idate(dol_now())."'" : 'NULL');
			$sql .= ', last_error = '.($reason === '' ? 'NULL' : "'".$this->db->escape($reason)."'");
			if ($status === 'failed') {
				$sql .= ", first_failure = '".$this->db->idate($firstFailure)."'";
			}
			$sql .= ' WHERE rowid = '.((int) $row->rowid).' AND entity = '.$entity." AND status = 'sending' AND lock_token = '".$token."'";
			$finish = $this->db->query($sql);
			if (!$finish || $this->db->affected_rows($finish) !== 1) {
				$this->error = $langs->trans('DolistoreWelcomeStorageError');
				return -1;
			}
			$sent += $status === 'sent' ? 1 : 0;
			DolistoreImportLog::add($this->db, $status === 'sent' ? 'success' : 'warning', $langs->transnoentities(self::STATUS_KEYS[$status]), (int) $row->fk_order, 'welcome', array('attempt' => $attempt, 'reason' => $reason), $user);
		}
		return $sent;
	}

	/** @return int|null Retry after the initial attempt, then stop after attempt four. */
	public static function nextAttempt(string $status, int $attempt, int $firstFailure): ?int
	{
		$delays = array(1 => 3600, 2 => 21600, 3 => 86400);
		return $status === 'failed' && isset($delays[$attempt]) ? $firstFailure + $delays[$attempt] : null;
	}

	/**
	 * Prepare through native templates/substitutions. Failure here is certainly unsent.
	 * @param object $request Persisted queue row
	 * @param User $user Actor
	 * @return array{subject:string,html:string,from:string,to:string,replyto:string}
	 */
	protected function prepare($request, $user): array
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
		$order = new DolistoreOrder($this->db);
		if ($order->fetch((int) $request->fk_order) <= 0 || (int) $order->entity !== (int) $conf->entity) {
			$this->error = 'DolistoreWelcomeOrderUnavailable';
			throw new RuntimeException($this->error);
		}
		if (getDolGlobalInt('MAIN_DISABLE_ALL_MAILS') || !isValidEmail((string) $order->customer_email)) {
			$this->error = 'DolistoreWelcomeRecipientOrMailDisabled';
			throw new RuntimeException($this->error);
		}
		$lang = isset(self::LANGUAGES[$request->lang]) ? $request->lang : 'en_US';
		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang($lang);
		$outputlangs->loadLangs(array('main', 'mails', 'companies', 'products', 'dolistorextract@dolistorextract'));
		$templateId = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_'.self::LANGUAGES[$lang]);
		$sql = 'SELECT rowid, module FROM '.MAIN_DB_PREFIX.'c_email_templates WHERE rowid = '.$templateId;
		$sql .= " AND type_template = 'dolistore_extract' AND lang = '".$this->db->escape($lang)."' AND active = 1 AND private = 0";
		$sql .= ' AND entity IN ('.$this->db->sanitize(getEntity('c_email_templates')).')';
		$resql = $this->db->query($sql);
		$record = $resql ? $this->db->fetch_object($resql) : null;
		if ($resql) {
			$this->db->free($resql);
		}
		if (!is_object($record) || (!empty($record->module) && !isModEnabled($record->module))) {
			$this->error = 'DolistoreWelcomeTemplateUnavailable';
			throw new RuntimeException($this->error);
		}
		$formmail = new FormMail($this->db);
		$template = $formmail->getEMailTemplate($this->db, 'dolistore_extract', $user, $outputlangs, $templateId, 1, '', -1);
		if (!is_object($template) || (int) $template->id !== $templateId || trim((string) $template->content) === '' || trim((string) $template->topic) === '') {
			$this->error = 'DolistoreWelcomeTemplateUnavailable';
			throw new RuntimeException($this->error);
		}
		$order->context['purchase_firstname'] = (string) $request->snapshot_firstname;
		$order->context['purchase_lastname'] = (string) $request->snapshot_lastname;
		$this->error = '';
		$substitutions = getCommonSubstitutionArray($outputlangs, 0, null, $order);
		complete_substitutions_array($substitutions, $outputlangs, $order, array('context' => 'formemail'));
		if (!empty($order->error)) {
			$this->error = 'DolistoreWelcomePreparationFailed';
			throw new RuntimeException($this->error);
		}
		$subject = make_substitutions((string) $template->topic, $substitutions);
		// Keep native substitutions available; escape all plain text before HTML insertion.
		$htmlSubstitutions = array();
		foreach ($substitutions as $key => $value) {
			$htmlSubstitutions[$key] = in_array($key, array('__SENDEREMAIL_SIGNATURE__', '__USER_SIGNATURE__'), true) ? dol_htmlwithnojs((string) $value, 1) : dol_escape_htmltag((string) $value);
		}
		$html = make_substitutions((string) $template->content, $htmlSubstitutions);
		$from = make_substitutions((string) $template->email_from, $substitutions);
		if ($from === '') {
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'));
		}
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		if (!isValidEmail(CMailFile::getValidAddress($from, 2, 0, 1)) || preg_match('/[\r\n]/', $from.$subject)) {
			$this->error = 'DolistoreWelcomeSenderInvalid';
			throw new RuntimeException($this->error);
		}
		// Native restrictive HTML cleaning preserves email tables and inline formatting.
		$html = dol_htmlwithnojs($html, 1);
		return array('subject' => $subject, 'html' => $html, 'from' => $from, 'to' => (string) $order->customer_email, 'replyto' => '');
	}

	/**
	 * A false result is NOT proof of non-delivery (sendMailAfter may fail after SMTP).
	 * @param array{subject:string,html:string,from:string,to:string,replyto:string} $message Message
	 * @return bool
	 */
	protected function transmit(array $message): bool
	{
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		$mail = new CMailFile($message['subject'], $message['to'], $message['from'], $message['html'], array(), array(), array(), '', '', 0, 1, '', '', '', '', 'dolistorewelcome', $message['replyto']);
		return (bool) $mail->sendfile();
	}

	/**
	 * Retry a failed delivery. Uncertain outcomes require explicit verification.
	 * @param int $orderId Order
	 * @param User $user Actor
	 * @param bool $verifiedUnsent Administrator verified non-delivery
	 * @return int
	 */
	public function retry(int $orderId, $user, bool $verifiedUnsent = false): int
	{
		global $conf, $langs;
		if (!isModEnabled('dolistorextract') || getDolGlobalInt('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU') || $this->db->transaction_opened > 0 || !empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'import'))) {
			$this->error = $langs->trans('DolistoreWelcomeAccessDenied');
			return -1;
		}
		$states = $verifiedUnsent ? "'failed','uncertain'" : "'failed'";
		$sql = 'UPDATE '.MAIN_DB_PREFIX."dolistoreextract_welcome SET status = 'pending', attempts = 0, first_failure = NULL, last_error = NULL, next_attempt = '".$this->db->idate(dol_now())."'";
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_order = '.$orderId.' AND status IN ('.$states.')';
		$resql = $this->db->query($sql);
		if (!$resql || $this->db->affected_rows($resql) !== 1) {
			$this->error = $langs->trans('DolistoreWelcomeRetryRefused');
			return -1;
		}
		DolistoreImportLog::add($this->db, 'info', $langs->transnoentities('DolistoreWelcomeRetryRequested'), $orderId, 'welcome', array('verified_unsent' => $verifiedUnsent), $user);
		return $this->process($user, $orderId);
	}

	/**
	 * Read safe delivery metadata; no snapshot, recipient or internal lock exposed.
	 * @param int $orderId Order
	 * @return array<string,int|string|null>|null
	 */
	public function getStatus(int $orderId): ?array
	{
		$sql = 'SELECT w.status, w.lang, w.attempts, w.next_attempt, w.sent_at, w.last_error FROM '.MAIN_DB_PREFIX.'dolistoreextract_welcome w';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order o ON o.rowid = w.fk_order AND o.entity = w.entity';
		$sql .= ' WHERE o.rowid = '.$orderId.' AND o.entity IN ('.$this->db->sanitize(getEntity('dolistoreextract_order')).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = 'DolistoreWelcomeStorageError';
			return null;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($row)) {
			return null;
		}
		return array('status' => (string) $row->status, 'lang' => (string) $row->lang, 'attempts' => (int) $row->attempts, 'next_attempt' => $row->next_attempt, 'sent_at' => $row->sent_at, 'last_error' => $row->last_error);
	}
}
