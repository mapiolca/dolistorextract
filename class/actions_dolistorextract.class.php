<?php
/* Copyright (C) - 2017    Jean-François Ferry    <jfefe@aternatik.fr>
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

/**
 *    \file       dolistorextract/class/actions_dolistorextract.class.php
 *    \ingroup    dolistorextract
 *    \brief      File Class dolistorextract
 */
//require_once "dolistorextract.class.php";
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once __DIR__ . '/dolistoreOrder.class.php';
require_once __DIR__ . '/dolistoreOrderLine.class.php';
require_once __DIR__ . '/dolistoreInvoiceBatch.class.php';
require_once __DIR__ . '/dolistoreImportLog.class.php';
require_once __DIR__ . '/../lib/dolistoreextract.lib.php';
require_once __DIR__ . "/../include/ssilence/php-imap-client/autoload.php";
use SSilence\ImapClient\ImapClientException;
use SSilence\ImapClient\ImapConnect;
use SSilence\ImapClient\ImapClient as Imap;

if (!class_exists('CommonHookActions')) {
	// EN: Load the native Dolibarr CommonHookActions class when it exists.
	// FR: Charger la classe native Dolibarr CommonHookActions lorsqu'elle existe.
	dol_include_once('/core/class/commonhookactions.class.php');
}

if (!class_exists('CommonHookActions')) {
	/**
	 * EN: Compatibility fallback for Dolibarr versions that do not provide CommonHookActions.
	 * FR: Fallback de compatibilité pour les versions Dolibarr qui ne fournissent pas CommonHookActions.
	 */
	class CommonHookActions
	{
		public $resprints = '';
		public $results = array();
		public $errors = array();
	}
}


/**
 * Class ActionsDolistorextract
 *
 * Provides hooks and main processing logic for the Dolistore Extract Dolibarr module.
 * Handles automated extraction of orders from Dolistore emails and integration in Dolibarr (thirdparties, contacts, events, archived DoliStore orders).
 */
class ActionsDolistorextract extends CommonHookActions
{
	/**
	 * Dolistore items are always business services in Dolibarr.
	 */
	public const DOLISTORE_PRODUCT_TYPE_SERVICE = 1;

	/**
	 * Standard support duration for Dolistore services (1 year).
	 */
	public const DOLISTORE_SERVICE_SUPPORT_DURATION_MONTHS = 12;

	/**
	 * Import behavior when a Dolistore service mapping is missing.
	 */
	public const DOLISTORE_UNMAPPED_BEHAVIOR_BLOCK = 'block';
	public const DOLISTORE_UNMAPPED_BEHAVIOR_SKIP = 'skip';
	public const DOLISTORE_UNMAPPED_BEHAVIOR_MANUAL = 'manual';
	public const DOLISTORE_UNMAPPED_POLICY_ABANDON = 'abandon';
	public const DOLISTORE_UNMAPPED_POLICY_CREATE = 'create';

	public $db;
	public $dao;
	public $mesg;
	public $error;
	public $nbErrors;
	public $errors = array();
	//! Numero de l'erreur
	public $errno = 0;
	public $template_dir;
	public $template;

	public $logCat = '';
	public $logOutput = '';
	public $lastOrderImportStatus = '';
	public $hasProductIddolistoreColumn = null;

	/**
	 *    Constructor
	 *
	 *    @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Return Multicompany sharing definition for this external module.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getMulticompanySharingDefinition(): array
	{
		return array(
			'dolistoreextract' => array(
				'sharingelements' => array(
					'dolistoreextract_order' => array(
						'type' => 'element',
						'icon' => 'dolistore@dolistorextract',
						'lang' => 'dolistorextract@dolistorextract',
						'tooltip' => 'DolistoreOrderSharingInfo',
						'enable' => 'isModEnabled("dolistorextract")',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
					'dolistoreextract_ordernumber' => array(
						'type' => 'objectnumber',
						'icon' => 'hashtag',
						'lang' => 'dolistorextract@dolistorextract',
						'tooltip' => 'DolistoreOrderNumberSharingInfo',
						'enable' => 'isModEnabled("dolistorextract")',
						'input' => array(
							'global' => array(
								'showhide' => true,
								'hide' => true,
								'del' => true,
							),
						),
					),
				),
				'sharingmodulename' => array(
					'dolistoreextract_order' => 'dolistoreextract',
					'dolistoreextract_ordernumber' => 'dolistoreextract',
				),
			),
		);
	}

	/**
	 * Return business events exposed to native Agenda and Notifications.
	 *
	 * @return array<string,array<string,int|string>>
	 */
	public static function getBusinessEventsDefinition(): array
	{
		$elementtype = 'dolistoreextract_order@dolistorextract';

		return array(
			'DOLISTOREEXTRACT_ORDER_CREATE' => array('label' => 'DolistoreOrderTriggerLabelCreate', 'description' => 'DolistoreOrderTriggerDescCreate', 'elementtype' => $elementtype, 'rang' => 2500),
			'DOLISTOREEXTRACT_ORDER_UPDATE' => array('label' => 'DolistoreOrderTriggerLabelUpdate', 'description' => 'DolistoreOrderTriggerDescUpdate', 'elementtype' => $elementtype, 'rang' => 2501),
			'DOLISTOREEXTRACT_ORDER_DELETE' => array('label' => 'DolistoreOrderTriggerLabelDelete', 'description' => 'DolistoreOrderTriggerDescDelete', 'elementtype' => $elementtype, 'rang' => 2502),
		);
	}

	/**
	 * Return event codes supported by native Notifications.
	 *
	 * @return string[]
	 */
	public static function getNotificationEventCodes(): array
	{
		return array_keys(self::getBusinessEventsDefinition());
	}

	/**
	 * Hook for Multicompany external modules sharing.
	 *
	 * @param array       $parameters  Parameters
	 * @param object|null $object      Object
	 * @param string      $action      Action
	 * @param HookManager $hookmanager Hookmanager
	 * @return int
	 */
	public function multicompanyExternalModulesSharing($parameters, &$object, &$action, $hookmanager): int
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * Compatibility alias for Multicompany hook naming.
	 *
	 * @param array       $parameters  Parameters
	 * @param object|null $object      Object
	 * @param string      $action      Action
	 * @param HookManager $hookmanager Hookmanager
	 * @return int
	 */
	public function multicompanyExternalModuleSharing($parameters, &$object, &$action, $hookmanager): int
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * Hook for Multicompany sharing options.
	 *
	 * @param array       $parameters  Parameters
	 * @param object|null $object      Object
	 * @param string      $action      Action
	 * @param HookManager $hookmanager Hookmanager
	 * @return int
	 */
	public function multicompanySharingOptions($parameters, &$object, &$action, $hookmanager): int
	{
		$this->results = array_replace_recursive($this->results, self::getMulticompanySharingDefinition());
		return 0;
	}

	/**
	 * Add DoliStore order events to native Notifications supported events.
	 *
	 * @param array       $parameters  Parameters
	 * @param object|null $object      Object
	 * @param string      $action      Action
	 * @param HookManager $hookmanager Hookmanager
	 * @return int
	 */
	public function notifsupported($parameters, &$object, &$action, $hookmanager): int
	{
		$events = self::getNotificationEventCodes();
		if (!empty($hookmanager->resArray['arrayofnotifsupported']) && is_array($hookmanager->resArray['arrayofnotifsupported'])) {
			$events = array_merge($hookmanager->resArray['arrayofnotifsupported'], $events);
		}

		$this->results = array('arrayofnotifsupported' => array_values(array_unique($events)));
		return 0;
	}

	/**
	 * Describe the custom object to generic Dolibarr element resolvers.
	 *
	 * @param array       $parameters  Parameters
	 * @param object|null $object      Object
	 * @param string      $action      Action
	 * @param HookManager $hookmanager Hookmanager
	 * @return int
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf;

		$elementtype = !empty($parameters['elementType']) ? (string) $parameters['elementType'] : '';
		if (!in_array($elementtype, array('dolistoreextract_order', 'dolistoreextract_order@dolistorextract'), true)) {
			return 0;
		}

		$diroutput = !empty($conf->dolistorextract->dir_output) ? $conf->dolistorextract->dir_output : '';
		$this->results = array(
			'module' => 'dolistorextract',
			'element' => 'dolistoreextract_order',
			'table_element' => 'dolistoreextract_order',
			'subelement' => 'dolistoreextract_order',
			'classpath' => 'dolistorextract/class',
			'classfile' => 'dolistoreOrder',
			'classname' => 'DolistoreOrder',
			'dir_output' => $diroutput,
			'dir_temp' => $diroutput !== '' ? $diroutput.'/temp' : '',
			'parent_element' => '',
		);
		$hookmanager->resArray = $this->results;

		return 1;
	}

	/**
	 * Hook: Provides additional info to the email element list.
	 *
	 * @param array        $parameters  Parameters from hook manager
	 * @param object       $object      Current Dolibarr object
	 * @param string       $action      Current action code
	 * @param HookManager  $hookmanager Hook manager instance
	 * @return int 0 if OK, -1 if error
	 */
	public function emailElementlist(array $parameters, ?object &$object, string &$action, HookManager $hookmanager) : int
	{
		global $langs;

		$error = 0;

		//if (in_array('admin', explode(':', $parameters['context']))) {
		$this->results = array('dolistore_extract' => $langs->trans('DolistorextractMessageToSendAfterDolistorePurchase'));
		//}

		if (! $error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Creates a new thirdparty/customer from extracted Dolistore email data.
	 *
	 * @param User          $user           User used to create the thirdparty
	 * @param dolistoreMail $dolistoreMail  Mail object with extracted customer fields
	 * @return int                          Thirdparty rowid, or -1 if creation failed
	 */
	public function newCustomerFromDatas(User $user, dolistoreMail $dolistoreMail) : int
	{
		global $conf, $langs;

		$socStatic = new Societe($this->db);

		if (empty($dolistoreMail->buyer_company) || empty($dolistoreMail->buyer_email)) {
			// print "buyer_company or email not found !";
			return -1;
		}
		// Load object modCodeTiers
		$module = (getDolGlobalString('SOCIETE_CODECLIENT_ADDON') ? getDolGlobalString('SOCIETE_CODECLIENT_ADDON') : 'mod_codeclient_leopard');
		if (substr($module, 0, 15) == 'mod_codeclient_' && substr($module, -3) == 'php') {
			$module = substr($module, 0, dol_strlen($module) - 4);
		}
		$dirsociete = array_merge(array('/core/modules/societe/'), $conf->modules_parts['societe']);
		foreach ($dirsociete as $dirroot) {
			$res = dol_include_once($dirroot . $module . '.php');
			if ($res) break;
		}
		$modCodeClient = new $module($this->db);

		$socStatic->code_client = $modCodeClient->getNextValue($socStatic, 0);
		$socStatic->name = $dolistoreMail->buyer_company;
		$socStatic->name_bis = $dolistoreMail->buyer_lastname;
		$socStatic->firstname = $dolistoreMail->buyer_firstname;
		$socStatic->address = $dolistoreMail->buyer_address1;
		$socStatic->zip = $dolistoreMail->buyer_postal_code;
		$socStatic->town = $dolistoreMail->buyer_city;
		$socStatic->email = $dolistoreMail->buyer_email;
		$socStatic->phone = $dolistoreMail->buyer_phone;
		$socStatic->country_code = $dolistoreMail->buyer_country_code;
		$socStatic->state = $dolistoreMail->buyer_state;
		$socStatic->multicurrency_code = $dolistoreMail->order_currency;
		$buyer_idprof2_clean = preg_replace('/\D/', '', $dolistoreMail->buyer_idprof2);
		if ($dolistoreMail->buyer_country_code == 'FR' && !empty($buyer_idprof2_clean)) {
			if (strlen($buyer_idprof2_clean) == 14) {
				// SIRET
				$socStatic->idprof1 = substr($buyer_idprof2_clean, 0, 9);
				$socStatic->idprof2 = $buyer_idprof2_clean;
			} elseif (strlen($buyer_idprof2_clean) == 9) {
				// SIREN
				$socStatic->idprof1 = $buyer_idprof2_clean;
				$socStatic->idprof2 = ''; // pas de SIRET complet
			} else {
				// Format inattendu, tu peux logguer ou juste mettre ce qui arrive
				$socStatic->idprof2 = $buyer_idprof2_clean;
			}
		} else {
			$socStatic->idprof2 = $buyer_idprof2_clean ?: $dolistoreMail->buyer_idprof2;
		}
		$socStatic->tva_intra = $dolistoreMail->buyer_intravat;

		// Le champ buyer_country_code contient BE/FR/DE...
		$resql = $this->db->query('SELECT rowid as fk_country FROM ' . $this->db->prefix() . "c_country WHERE code = '" . $this->db->escape($dolistoreMail->buyer_country_code) . "'");
		if ($resql) {
			if (($obj = $this->db->fetch_object($resql)) && $this->db->num_rows($resql) == 1) $socStatic->country_id = $obj->fk_country;
		}

		$socStatic->array_options["options_provenance"] = "INT";
		$socStatic->import_key = "STORE";

		$socStatic->client = 2; // Prospect / client
		$socid = $socStatic->create($user);
		$this->logOutput .= '<br/><span class="ok">-> ' . $langs->trans("DolistoreThirdPartyCreatedWithID", $dolistoreMail->buyer_company, $socStatic->id) . ' </span>';

		if ($socid > 0) {
			$socStatic->create_individual($user);
			$this->logOutput .= '<br/><span class="ok">-> ' . $langs->trans("DolistoreContactCreatedWithID", $socStatic->firstname, $socStatic->lastname, $socStatic->id) . ' </span>';
		} elseif (is_array($socStatic->errors)) {
			$this->errors = array_merge($this->errors, $socStatic->errors);
		}
		return $socid;
	}

	/**
	 * Legacy compatibility shim for the former manual Agenda event creation.
	 *
	 * DoliStore order events are now exposed through native object triggers and c_action_trigger.
	 *
	 * @param array  $productDatas Product/item array
	 * @param string $orderRef     DoliStore order reference
	 * @param int    $socid        Thirdparty rowid
	 * @return int Always 0, manual Agenda creation disabled
	 */
	public function createEventFromExtractDatas(array $productDatas, string $orderRef, int $socid) : int
	{
		dol_syslog(__METHOD__.' skipped for order_ref='.$orderRef.'; DoliStore order Agenda events are handled by native triggers.', LOG_INFO);
		return 0;
	}

	/**
	 * Main CRON job: connect to IMAP, fetch emails, launch processing, update mailbox status.
	 * Orchestrates the full import process.
	 *
	 * @return int Positive number of sales if success, negative number of errors, or 0 if no email
	 */
	public function launchCronJob() : int
	{
		global $langs, $conf;

		$this->nbErrors = 0;
		$langs->load('main');

		$lockName = 'dolistoreextract_import_'.$conf->entity;
		if (!$this->acquireSqlLock($lockName)) {
			$this->logOutput .= '<br/><span class="warning">'.$langs->trans("DolistoreImportLockUnavailable").'</span>';
			return 0;
		}

		try {
		$imap = $this->openImapClient();
		if (!$imap) {
			return -1;
		}

		$emailEntries = $this->fetchDolistoreEmailsFromConfiguredLocation($imap, true);
		$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreMailsToProcess", count($emailEntries)) . '</strong>';

		if (empty($emailEntries)) {
			$this->logOutput .= '<br/>' . $langs->trans("DolistoreNoUnreadMailFound");
			return 0;
		}

		$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreEmailsToProcessCount", count($emailEntries)) . '</strong>';

		$dolistoreEmails = array();
		foreach ($emailEntries as $emailEntry) {
			$emailEntry['message']->dolistoreextract_folder = (string) $emailEntry['folder'];
			$dolistoreEmails[] = $emailEntry['message'];
		}

		// Process all emails at once
		$result = $this->launchImportProcess($dolistoreEmails);

		// Process results by order reference
		if (is_array($result)) {
			// Organize emails by order reference
			$emailsByOrderRef = [];
			foreach ($emailEntries as $emailEntry) {
				$email = $emailEntry['message'];
				$email->dolistoreextract_folder = (string) $emailEntry['folder'];
				$dolistoreMailExtract = new \dolistoreMailExtract($this->db, $email->message->text);
				$data = $dolistoreMailExtract->extractAllDatas();

				if (!empty($data) && !empty($data['order_ref'])) {
					$orderRef = $data['order_ref'];
					if (!isset($emailsByOrderRef[$orderRef])) {
						$emailsByOrderRef[$orderRef] = [];
					}
					$emailsByOrderRef[$orderRef][] = $emailEntry;
				} else {
					// Email not associated with any valid order, move to error folder
					$this->moveEmailEntryToErrorFolder($imap, $emailEntry);
				}
			}

			// Process emails by order reference
			$successCount = 0;
			$errorCount = 0;

			foreach ($result as $orderRef => $orderSuccess) {
				if (isset($emailsByOrderRef[$orderRef])) {
					if ($orderSuccess) {
						// Order processed successfully
						$successCount++;
						foreach ($emailsByOrderRef[$orderRef] as $emailEntry) {
							$this->markEmailEntryAsProcessed($imap, $emailEntry);
						}
					} else {
						// Order processing failed, move all related emails to error folder
						$errorCount++;
						foreach ($emailsByOrderRef[$orderRef] as $emailEntry) {
							$this->moveEmailEntryToErrorFolder($imap, $emailEntry);
						}
						$this->logOutput .= '<br/>-> <strong class="error">' . $langs->trans("DolistoreOrderProcessingFailed", $orderRef) . '</strong>';
					}
				}
			}

			// Return overall result
			if ($errorCount > 0) {
				return -$errorCount;
			} else {
				return $successCount;
			}
		} else {
			// Fallback to the old integer result format
			if ($result < 0) {
				$this->logOutput .= '-> <strong class="error">FAIL</strong>';

				// Move all emails to error folder as we can't determine which ones succeeded
				foreach ($emailEntries as $emailEntry) {
					$this->moveEmailEntryToErrorFolder($imap, $emailEntry);
				}
				return $result;
				} else {
					// Mark all emails as read and archive them
					foreach ($emailEntries as $emailEntry) {
						$this->markEmailEntryAsProcessed($imap, $emailEntry);
					}
					return $result;
				}
			}
		} finally {
			$this->releaseSqlLock($lockName);
		}
	}

	/**
	 * Open the configured IMAP client connection.
	 *
	 * @return Imap|null
	 */
	public function openImapClient(): ?Imap
	{
		$mailbox = getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER');
		$username = getDolGlobalString('DOLISTOREXTRACT_IMAP_USER');
		$password = getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD');
		$encryption = Imap::ENCRYPT_SSL;

		try {
			return new Imap($mailbox, $username, $password, $encryption);
		} catch (ImapClientException $error) {
			$this->errors[] = $error->getMessage() . PHP_EOL;
			$this->logOutput .= '<br/><span class="error">' . dol_escape_htmltag($error->getMessage()) . '</span>';
			dol_syslog(__METHOD__ . ' failed to open IMAP connection error=' . $error->getMessage(), LOG_ERR);
		}

		return null;
	}

	/**
	 * Read Dolistore emails from configured folder and optional subfolders.
	 *
	 * @param Imap $imap Active IMAP client
	 * @param bool $onlyUnread Restrict to unread messages
	 * @return array<int,array<string,mixed>>
	 */
	public function fetchDolistoreEmailsFromConfiguredLocation(Imap $imap, bool $onlyUnread = true): array
	{
		global $langs;

		$emailEntries = array();
		$folders = $this->getConfiguredImapFoldersToScan($imap);
		if (empty($folders)) {
			return $emailEntries;
		}

		foreach ($folders as $folderName) {
			if (!$imap->selectFolder($folderName)) {
				$message = $langs->trans("DolistoreImapFolderSelectFailed", $folderName);
				$this->errors[] = $message;
				$this->logOutput .= '<br/><span class="warning">' . dol_escape_htmltag($message) . '</span>';
				dol_syslog(__METHOD__ . ' unable to select folder=' . $folderName, LOG_WARNING);
				continue;
			}

			$messages = $imap->getMessages();
			foreach ($messages as $message) {
				$isDolistore = (strpos((string) $message->header->subject, 'DoliStore') !== false);
				if (!$isDolistore) {
					continue;
				}
				if ($onlyUnread && !empty($message->header->seen)) {
					continue;
				}

				$emailEntries[] = array(
					'folder' => $folderName,
					'message' => $message
				);
			}
		}

		return $emailEntries;
	}

	/**
	 * Fetch DoliStore orders still present in the configured mailbox and not archived yet.
	 *
	 * This is a read-only helper for list pages: it must not mark messages as read,
	 * move messages, create orders or call import business logic.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function fetchPendingDolistoreOrdersFromMailbox(): array
	{
		global $langs;

		if (!function_exists('imap_open')) {
			$this->errors[] = $langs->trans('DolistoreImapExtensionMissing');
			dol_syslog(__METHOD__.' IMAP extension is missing', LOG_WARNING);
			return array();
		}

		if (empty(getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER')) || empty(getDolGlobalString('DOLISTOREXTRACT_IMAP_USER')) || empty(getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD'))) {
			$this->errors[] = $langs->trans('DolistoreImapConfigurationMissing');
			dol_syslog(__METHOD__.' IMAP configuration is incomplete', LOG_WARNING);
			return array();
		}

		if (!class_exists('dolistoreMailExtract')) {
			require_once __DIR__ . '/dolistoreMailExtract.class.php';
		}

		$imap = $this->openImapClient();
		if (!$imap) {
			return array();
		}

		$emailEntries = $this->fetchDolistoreEmailsFromConfiguredLocation($imap, false);
		if (empty($emailEntries)) {
			return array();
		}

		$orders = array();
		$messageIdToOrderRefs = array();
		foreach ($emailEntries as $emailEntry) {
			$email = $emailEntry['message'];
			$subject = (string) ($email->header->subject ?? '');
			$langEmail = dolistoreMailExtract::detectLang($subject);
			$subjectOrderData = dolistoreMailExtract::extractOrderDatasFromSubject($subject, $langEmail);
			$messageBody = (string) ($email->message->text ?? $email->message->plain ?? $email->message->html ?? '');
			$mailExtract = new dolistoreMailExtract($this->db, $messageBody);
			$extractedData = $mailExtract->extractAllDatas();

			$orderRef = trim((string) ($extractedData['order_ref'] ?? ''));
			if ($orderRef === '') {
				$orderRef = trim((string) ($subjectOrderData['ref'] ?? ''));
			}
			if ($orderRef === '') {
				continue;
			}

			$messageId = $this->getEmailMessageId($email);
			if ($messageId !== '') {
				if (empty($messageIdToOrderRefs[$messageId])) {
					$messageIdToOrderRefs[$messageId] = array();
				}
				$messageIdToOrderRefs[$messageId][$orderRef] = true;
			}

			$messageDate = !empty($email->header->date) ? (int) strtotime((string) $email->header->date) : 0;
			if ($messageDate < 0) {
				$messageDate = 0;
			}
			$isUnread = (!empty($email->header->details->Unseen) && $email->header->details->Unseen === 'U');
			if (!$isUnread && isset($email->header->seen)) {
				$isUnread = empty($email->header->seen);
			}
			$folder = (string) ($emailEntry['folder'] ?? '');

			if (empty($orders[$orderRef])) {
				$orders[$orderRef] = array(
					'folder' => $folder,
					'folders' => array($folder => true),
					'email_date' => $messageDate,
					'email_date_raw' => (string) ($email->header->date ?? ''),
					'email_id' => (string) ($subjectOrderData['id'] ?? ''),
					'order_ref' => $orderRef,
					'lang' => $langEmail,
					'customer_name' => trim((string) ($extractedData['buyer_company'] ?? '')),
					'customer_email' => trim((string) ($extractedData['buyer_email'] ?? '')),
					'contact_name' => trim((string) ($extractedData['buyer_lastname'] ?? '').' '.(string) ($extractedData['buyer_firstname'] ?? '')),
					'mail_count' => 0,
					'unread_count' => 0,
					'message_id' => $messageId,
					'msgno' => (int) ($email->header->msgno ?? 0),
					'uid' => (int) ($email->header->uid ?? 0),
				);
			}

			$orders[$orderRef]['mail_count']++;
			if ($isUnread) {
				$orders[$orderRef]['unread_count']++;
			}
			if ($folder !== '') {
				$orders[$orderRef]['folders'][$folder] = true;
			}
			if ($isUnread && empty($orders[$orderRef]['preferred_unread_message'])) {
				$orders[$orderRef]['folder'] = $folder;
				$orders[$orderRef]['message_id'] = $messageId;
				$orders[$orderRef]['msgno'] = (int) ($email->header->msgno ?? 0);
				$orders[$orderRef]['uid'] = (int) ($email->header->uid ?? 0);
				$orders[$orderRef]['preferred_unread_message'] = true;
			}
			if ($messageDate > (int) $orders[$orderRef]['email_date']) {
				$orders[$orderRef]['email_date'] = $messageDate;
				$orders[$orderRef]['email_date_raw'] = (string) ($email->header->date ?? '');
			}
			if ($orders[$orderRef]['customer_name'] === '' && !empty($extractedData['buyer_company'])) {
				$orders[$orderRef]['customer_name'] = trim((string) $extractedData['buyer_company']);
			}
			if ($orders[$orderRef]['customer_email'] === '' && !empty($extractedData['buyer_email'])) {
				$orders[$orderRef]['customer_email'] = trim((string) $extractedData['buyer_email']);
			}
			if ($orders[$orderRef]['contact_name'] === '') {
				$orders[$orderRef]['contact_name'] = trim((string) ($extractedData['buyer_lastname'] ?? '').' '.(string) ($extractedData['buyer_firstname'] ?? ''));
			}
		}

		if (empty($orders)) {
			return array();
		}

		$this->removeAlreadyArchivedMailboxOrders($orders, $messageIdToOrderRefs);

		uasort($orders, function ($a, $b) {
			return ((int) $b['email_date']) <=> ((int) $a['email_date']);
		});

		return $orders;
	}

	/**
	 * Remove mailbox entries that already have a DoliStore archived order.
	 *
	 * @param array<string,array<string,mixed>> $orders              Orders indexed by DoliStore reference
	 * @param array<string,array<string,bool>> $messageIdToOrderRefs Order references indexed by Message-ID
	 * @return void
	 */
	private function removeAlreadyArchivedMailboxOrders(array &$orders, array $messageIdToOrderRefs): void
	{
		$orderRefs = array_keys($orders);
		$messageIds = array_keys($messageIdToOrderRefs);
		if (empty($orderRefs) && empty($messageIds)) {
			return;
		}

		$whereParts = array();
		if (!empty($orderRefs)) {
			$whereParts[] = 'dolistore_order_ref IN ('.$this->buildSqlStringList($orderRefs).')';
		}
		if (!empty($messageIds)) {
			$whereParts[] = 'email_message_id IN ('.$this->buildSqlStringList($messageIds).')';
		}
		if (empty($whereParts)) {
			return;
		}

		$sql = 'SELECT dolistore_order_ref, email_message_id';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order';
		$sql .= ' WHERE entity IN ('.getEntity('dolistoreextract_order').')';
		$sql .= ' AND ('.implode(' OR ', $whereParts).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_WARNING);
			return;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$orderRef = (string) ($obj->dolistore_order_ref ?? '');
			if ($orderRef !== '' && isset($orders[$orderRef])) {
				unset($orders[$orderRef]);
			}

			$messageId = (string) ($obj->email_message_id ?? '');
			if ($messageId !== '' && !empty($messageIdToOrderRefs[$messageId])) {
				foreach (array_keys($messageIdToOrderRefs[$messageId]) as $linkedOrderRef) {
					unset($orders[$linkedOrderRef]);
				}
			}
		}
		$this->db->free($resql);
	}

	/**
	 * Build a SQL string list from scalar values.
	 *
	 * @param array<int,string> $values Values
	 * @return string
	 */
	private function buildSqlStringList(array $values): string
	{
		$quoted = array();
		foreach ($values as $value) {
			$value = trim((string) $value);
			if ($value === '') {
				continue;
			}
			$quoted[] = "'".$this->db->escape($value)."'";
		}

		return !empty($quoted) ? implode(',', array_unique($quoted)) : "''";
	}

	/**
	 * Get list of folders to scan from configured source folder.
	 *
	 * @param Imap $imap Active IMAP client
	 * @return array<int,string>
	 */
	private function getConfiguredImapFoldersToScan(Imap $imap): array
	{
		$rootFolder = trim((string) getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER'));
		if ($rootFolder === '') {
			$rootFolder = 'INBOX';
		}

		$allFolders = $imap->getFolders(null, 1);
		if (!is_array($allFolders) || empty($allFolders)) {
			return array($rootFolder);
		}

		$folders = array();
		foreach ($allFolders as $folderName) {
			if ($folderName === $rootFolder) {
				$folders[] = $folderName;
				continue;
			}
			if (preg_match('/^' . preg_quote($rootFolder, '/') . '[\/\.]/', $folderName)) {
				$folders[] = $folderName;
			}
		}

		if (empty($folders)) {
			$folders[] = $rootFolder;
		}

		sort($folders);

		return array_values(array_unique($folders));
	}

	/**
	 * Mark message as processed and move it to archive folder when configured.
	 *
	 * @param Imap  $imap       Active IMAP client
	 * @param array $emailEntry Mail payload with folder + message keys
	 * @return void
	 */
	private function markEmailEntryAsProcessed(Imap $imap, array $emailEntry): void
	{
		global $langs;

		$message = $emailEntry['message'];
		$sourceFolder = (string) $emailEntry['folder'];
		if (!$imap->selectFolder($sourceFolder)) {
			$this->logOutput .= '<br/>' . $langs->trans("DolistoreImapFolderSelectFailed", dol_escape_htmltag($sourceFolder));
			return;
		}

		$imap->setSeenMessage((int) $message->header->msgno);
		if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE')) {
			$moveResult = $imap->moveMessage((int) $message->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
			if (!$moveResult) {
				$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $message->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
			}
		}
	}

	/**
	 * Move message to configured error folder.
	 *
	 * @param Imap  $imap       Active IMAP client
	 * @param array $emailEntry Mail payload with folder + message keys
	 * @return void
	 */
	private function moveEmailEntryToErrorFolder(Imap $imap, array $emailEntry): void
	{
		global $langs;

		$errorFolder = getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR');
		if (empty($errorFolder)) {
			return;
		}

		$message = $emailEntry['message'];
		$sourceFolder = (string) $emailEntry['folder'];
		if (!$imap->selectFolder($sourceFolder)) {
			$this->logOutput .= '<br/>' . $langs->trans("DolistoreImapFolderSelectFailed", dol_escape_htmltag($sourceFolder));
			return;
		}

		$moveResult = $imap->moveMessage((int) $message->header->uid, $errorFolder);
		if (!$moveResult) {
			$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $message->header->uid, $errorFolder);
		}
	}

	/**
	 * Core integration logic: reads emails, extracts orders, creates customers, contacts, events, and sales.
	 *
	 * @param array $emails Array of Dolistore IMAP emails (SSilence\ImapClient\Message[])
	 * @return array|int    Array of order_ref => success(bool) or int <0 if failure
	 */
	public function launchImportProcess(array $emails): array|int
	{
		global $conf, $langs;
		dol_syslog(__METHOD__ . ' launch import process for ' . count($emails) . ' messages', LOG_DEBUG);

		$this->nbErrors = 0;
		$orderResults = [];

		// 1. Loading the necessary classes
		$this->loadRequiredClasses();

		$user = new \User($this->db);
		$actionUserId = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');
		$actionUserFetchResult = $user->fetch($actionUserId);
		if ($actionUserFetchResult <= 0) {
			dol_syslog(__METHOD__ . ' unable to load actions user id=' . ((int) $actionUserId), LOG_ERR);
			$this->logOutput .= '<br/><span class="error">' . $langs->trans("ErrorBadValueForParameter", 'DOLISTOREXTRACT_USER_FOR_ACTIONS') . '</span>';
			return -1;
		}
		$user->getrights();

		// 2. Data extraction (read-only)
		$ordersData = $this->extractOrdersData($emails);

		if (empty($ordersData)) {
			$this->logOutput .= '<br/><span class="warning">'.$langs->trans("DolistoreNoValidOrderFound").'</span>';
			return 0;
		}
		// 3. Order-by-order processing
		foreach ($ordersData as $orderRef => $orderDetails) {
			// The entire processing of an order is delegated to a dedicated method.
			$success = $this->processSingleOrder($user, $orderRef, $orderDetails);
			$orderResults[$orderRef] = $success;
		}
		return $orderResults;
	}
	/**
	 * Process a single order
	 *
	 * @param User   $user         User object
	 * @param string $orderRef     Order reference
	 * @param array  $orderDetails Order details
	 * @return bool                True if success, False if error
	 */
	private function processSingleOrder(User $user, string $orderRef, array $orderDetails): bool
	{
		global $langs;
		$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreProcessingOrder", $orderRef) . '</strong>';
		$this->lastOrderImportStatus = '';

		$this->db->begin();

		$existingDolistoreOrder = $this->findExistingDolistoreOrder($orderRef, $orderDetails);
		if ($existingDolistoreOrder > 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/><span class="warning">' . $langs->trans("DolistoreOrderAlreadyImportedSkip", $orderRef, $existingDolistoreOrder) . '</span>';
			DolistoreImportLog::add($this->db, 'warning', $langs->transnoentitiesnoconv("DolistoreOrderAlreadyImportedSkip", $orderRef, $existingDolistoreOrder), $existingDolistoreOrder, 'import', array('order_ref' => $orderRef), $user);
			dol_syslog(__METHOD__ . ' skip import for already imported DoliStore order_ref=' . $orderRef . ' existing_order_id=' . $existingDolistoreOrder, LOG_WARNING);
			return true;
		}

		$companyId = $this->getOrCreateCustomer($user, $orderDetails['buyer_data']);
		if ($companyId <= 0) {
			$this->logOutput .= '<br/>-> <span class="warning">'.$langs->trans("DolistoreCustomerMgmtFailed").'</span>';
			DolistoreImportLog::add($this->db, 'warning', $langs->transnoentitiesnoconv("DolistoreCustomerMgmtFailed"), 0, 'import', array('order_ref' => $orderRef), $user);
		}
		dol_syslog(__METHOD__ . ' customer/contact management kept for DoliStore archive order_ref=' . $orderRef . ' socid=' . $companyId, LOG_INFO);

		$dolistoreOrder = $this->createDolistoreOrderArchive($user, $orderRef, $orderDetails, $companyId);
		if ($dolistoreOrder <= 0) {
			$this->db->rollback();
			$this->nbErrors++;
			return false;
		}

		$this->db->commit();
		$this->logOutput .= '<br/><span class="ok">'.$langs->trans("DolistoreOrderImported", $orderRef).'</span>';
		DolistoreImportLog::add($this->db, 'success', $langs->transnoentitiesnoconv("DolistoreOrderImported", $orderRef), $dolistoreOrder, 'import', array('order_ref' => $orderRef), $user);

		return true;
	}

	/**
	 * Find an existing archived DoliStore order using the V2 duplicate rules.
	 *
	 * @param string $orderRef     DoliStore order reference
	 * @param array  $orderDetails Extracted order details
	 * @return int Existing order id, 0 if none
	 */
	private function findExistingDolistoreOrder(string $orderRef, array $orderDetails): int
	{
		$order = new DolistoreOrder($this->db);
		if ($orderRef !== '' && $order->fetchByDolistoreRef($orderRef) > 0) {
			return (int) $order->id;
		}

		$messageId = (string) ($orderDetails['email_metadata']['message_id'] ?? '');
		if ($messageId !== '') {
			$order = new DolistoreOrder($this->db);
			if ($order->fetchByEmailMessageId($messageId) > 0) {
				return (int) $order->id;
			}
		}

		$rawHash = (string) ($orderDetails['raw_hash'] ?? '');
		if ($rawHash !== '') {
			$order = new DolistoreOrder($this->db);
			if ($order->fetchByRawHash($rawHash) > 0) {
				return (int) $order->id;
			}
		}

		return 0;
	}

	/**
	 * Create the archived DoliStore order and lines.
	 *
	 * @param User   $user         User
	 * @param string $orderRef     DoliStore order reference
	 * @param array  $orderDetails Extracted details
	 * @param int    $companyId    Customer thirdparty id
	 * @return int Order id, -1 on error
	 */
	private function createDolistoreOrderArchive(User $user, string $orderRef, array $orderDetails, int $companyId): int
	{
		global $langs;

		$buyerData = (array) ($orderDetails['buyer_data'] ?? array());
		$items = (array) ($orderDetails['items'] ?? array());
		$emailMetadata = (array) ($orderDetails['email_metadata'] ?? array());
		$orderDate = !empty($orderDetails['order_date']) ? (int) $orderDetails['order_date'] : dol_now();
		$releaseDelayDays = (int) getDolGlobalInt('DOLISTOREXTRACT_PAYMENT_RELEASE_DELAY_DAYS');
		if ($releaseDelayDays <= 0) {
			$releaseDelayDays = 30;
		}
		$releaseDate = dol_time_plus_duree($orderDate, $releaseDelayDays, 'd');
		$commissionRate = $this->getDolistoreCommissionRate();

		$order = new DolistoreOrder($this->db);
		$order->dolistore_order_ref = $orderRef;
		$order->dolistore_order_date = $orderDate;
		$order->release_date = $releaseDate;
		$order->currency_code = strtoupper((string) ($buyerData['order_currency'] ?? $buyerData['currency'] ?? 'EUR'));
		if ($order->currency_code === '') {
			$order->currency_code = 'EUR';
		}
		$order->commission_percent = $commissionRate;
		$order->customer_name = trim((string) ($buyerData['buyer_company'] ?? ''));
		$order->customer_email = trim((string) ($buyerData['buyer_email'] ?? ''));
		$order->customer_country = trim((string) ($buyerData['buyer_country'] ?? ''));
		$order->customer_country_code = trim((string) ($buyerData['buyer_country_code'] ?? ''));
		$order->fk_soc_customer = $companyId > 0 ? $companyId : 0;
		$order->fk_contact_customer = $this->findContactIdByEmail($companyId, $order->customer_email);
		$order->fk_soc_dolistore = getDolGlobalInt('DOLISTOREXTRACT_BILLING_THIRDPARTY_ID');
		$order->email_message_id = (string) ($emailMetadata['message_id'] ?? '');
		$order->email_subject = (string) ($emailMetadata['subject'] ?? '');
		$order->email_date = !empty($emailMetadata['date']) ? (int) $emailMetadata['date'] : $orderDate;
		$order->email_uid = !empty($emailMetadata['uid']) ? (int) $emailMetadata['uid'] : 0;
		$order->email_folder = (string) ($emailMetadata['folder'] ?? '');
		$order->raw_hash = (string) ($orderDetails['raw_hash'] ?? '');
		$order->status = empty($items) ? DolistoreOrder::STATUS_DRAFT : ($releaseDate <= dol_now() ? DolistoreOrder::STATUS_INVOICEABLE : DolistoreOrder::STATUS_WAITING_RELEASE);
		$order->note_private = $this->buildDolistoreImportPrivateNote(array(
			'lang' => (string) ($orderDetails['lang'] ?? ''),
			'import_hash' => (string) ($orderDetails['raw_hash'] ?? ''),
			'order_ref' => $orderRef,
			'buyer_email' => $order->customer_email,
		));

		$orderId = $order->create($user);
		if ($orderId <= 0) {
			$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreArchiveOrderCreateError", dol_escape_htmltag($orderRef), dol_escape_htmltag($order->error)).'</span>';
			DolistoreImportLog::add($this->db, 'error', $langs->transnoentitiesnoconv("DolistoreArchiveOrderCreateError", $orderRef, $order->error), 0, 'import', array('order_ref' => $orderRef), $user);
			return -1;
		}

		$createdLines = 0;
		foreach ($items as $item) {
			$resultLine = $this->createDolistoreOrderArchiveLine($user, $order, (array) $item);
			if ($resultLine < 0) {
				return -1;
			}
			$createdLines++;
		}

		$order->fetch($orderId);
		$order->updateTotalsFromLines($user);
		$this->storeOrderSourceEmails($order, $orderDetails);

		if ($createdLines === 0) {
			DolistoreImportLog::add($this->db, 'warning', $langs->transnoentitiesnoconv("DolistoreNoItemsFound"), $orderId, 'import', array('order_ref' => $orderRef), $user);
		}

		return $orderId;
	}

	/**
	 * Create one archived DoliStore order line.
	 *
	 * @param User           $user  User
	 * @param DolistoreOrder $order Parent order
	 * @param array          $item  Extracted item
	 * @return int Line id, -1 on error
	 */
	private function createDolistoreOrderArchiveLine(User $user, DolistoreOrder $order, array $item): int
	{
		global $langs;

		$item = $this->enforceDolistoreServiceBusinessRule($item);
		$itemReference = (string) ($item['item_reference'] ?? '');
		$itemName = (string) ($item['item_name'] ?? '');
		$itemQty = !empty($item['item_quantity']) ? (float) $item['item_quantity'] : 1;
		$itemQty = abs($itemQty) > 0 ? abs($itemQty) : 1;
		$itemTotal = !empty($item['item_price_total']) ? $this->convertToFloat((string) $item['item_price_total']) : $this->convertToFloat((string) ($item['item_price'] ?? '0'));
		$itemUnitPrice = ($itemQty != 0) ? ($itemTotal / $itemQty) : $itemTotal;
		$isRefunded = !empty($item['item_refunded']);
		if ($isRefunded) {
			$itemUnitPrice = -1 * abs($itemUnitPrice);
			$itemTotal = -1 * abs($itemTotal);
		}

		$serviceId = $this->getServiceIdByDolistoreId($itemReference);
		if ($serviceId <= 0) {
			DolistoreImportLog::add($this->db, 'warning', $langs->transnoentitiesnoconv("DolistoreServiceMappingNotFound", $itemReference, $itemName), (int) $order->id, 'import', array('item_reference' => $itemReference, 'item_name' => $itemName), $user);
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreServiceMappingNotFound", dol_escape_htmltag($itemReference), dol_escape_htmltag($itemName)) . '</span>';
		}

		$commissionFactor = 1 - ((float) $order->commission_percent / 100);
		$line = new DolistoreOrderLine($this->db);
		$line->entity = (int) $order->entity;
		$line->fk_order = (int) $order->id;
		$line->product_dolistore_ref = $itemReference;
		$line->product_label = $itemName;
		$line->fk_product = $serviceId > 0 ? $serviceId : 0;
		$line->qty = $itemQty;
		$line->unit_price_ht = $itemUnitPrice;
		$line->total_ht = $itemTotal;
		$line->tax_rate = (float) $this->getDolistoreInvoiceVatRate();
		$line->total_tva = 0;
		$line->total_ttc = $itemTotal;
		$line->billable_unit_price_ht = $itemUnitPrice * $commissionFactor;
		$line->billable_total_ht = $itemTotal * $commissionFactor;
		$line->description = $this->buildDolistoreOrderLineDescription($item, $isRefunded);
		$line->raw_hash = hash('sha256', implode('|', array((int) $order->id, $itemReference, $itemName, price2num($itemTotal, 'MU'), price2num($itemQty, 'MU'))));

		$lineId = $line->create($user);
		if ($lineId <= 0) {
			$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreArchiveLineCreateError", dol_escape_htmltag($itemReference), dol_escape_htmltag($line->error)).'</span>';
			DolistoreImportLog::add($this->db, 'error', $langs->transnoentitiesnoconv("DolistoreArchiveLineCreateError", $itemReference, $line->error), (int) $order->id, 'import', array('item_reference' => $itemReference), $user);
			return -1;
		}

		return $lineId;
	}

	/**
	 * Find customer contact by email.
	 *
	 * @param int    $companyId Thirdparty id
	 * @param string $email     Email
	 * @return int
	 */
	private function findContactIdByEmail(int $companyId, string $email): int
	{
		$email = trim($email);
		if ($companyId <= 0 || $email === '') {
			return 0;
		}

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'socpeople';
		$sql .= ' WHERE fk_soc = '.((int) $companyId);
		$sql .= " AND email = '".$this->db->escape($email)."'";
		$sql .= ' AND entity IN ('.getEntity('contact').')';
		$sql .= ' ORDER BY rowid ASC LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return !empty($obj->rowid) ? (int) $obj->rowid : 0;
	}

	/**
	 * Store source emails as native attached documents.
	 *
	 * @param DolistoreOrder $order        Order
	 * @param array          $orderDetails Extracted order details
	 * @return void
	 */
	private function storeOrderSourceEmails(DolistoreOrder $order, array $orderDetails): void
	{
		$emails = (array) ($orderDetails['source_emails'] ?? array());
		if (empty($emails) || empty($order->id) || empty($order->ref)) {
			return;
		}

		$uploadDir = dolistoreextractGetOrderUploadDir($order);
		dol_mkdir($uploadDir);

		$index = 0;
		foreach ($emails as $sourceEmail) {
			$index++;
			$filename = dol_sanitizeFileName($order->ref.'-source-'.$index.'.eml');
			$filepath = $uploadDir.'/'.$filename;
			if (is_file($filepath)) {
				continue;
			}
			$content = (string) $sourceEmail;
			if ($content === '') {
				continue;
			}
			file_put_contents($filepath, $content);
		}
	}

	/**
	 * Build an auditable EML-like source from the IMAP object.
	 *
	 * @param object $email IMAP message
	 * @return string
	 */
	private function buildEmailSourceContent($email): string
	{
		$subject = (string) ($email->header->subject ?? '');
		$date = (string) ($email->header->date ?? '');
		$messageId = $this->getEmailMessageId($email);
		$from = (string) ($email->header->from ?? '');
		$to = (string) ($email->header->to ?? '');
		$text = (string) ($email->message->text ?? $email->message->plain ?? '');
		$html = (string) ($email->message->html ?? '');

		$body = $text !== '' ? $text : $html;

		return "Subject: ".$subject."\r\n"
			."Date: ".$date."\r\n"
			."Message-ID: ".$messageId."\r\n"
			."From: ".$from."\r\n"
			."To: ".$to."\r\n"
			."Content-Type: text/plain; charset=UTF-8\r\n\r\n"
			.$body;
	}

	/**
	 * Return stable message id from IMAP object.
	 *
	 * @param object $email IMAP message
	 * @return string
	 */
	private function getEmailMessageId($email): string
	{
		$candidates = array(
			$email->header->message_id ?? '',
			$email->header->messageid ?? '',
			$email->header->details->message_id ?? '',
			$email->header->details->messageid ?? '',
			$email->header->details->MessageID ?? '',
		);
		foreach ($candidates as $candidate) {
			$candidate = trim((string) $candidate);
			if ($candidate !== '') {
				return $candidate;
			}
		}

		return '';
	}
	/**
	 * Retrieves a Dolibarr service rowid from a Dolistore identifier.
	 * First search is done on extrafield iddolistore, then fallback on native product ref.
	 *
	 * @param string $fk_dolistore Dolistore identifier
	 * @return int                 Service rowid (>0) if found, 0 otherwise
	 */
	public function getServiceIdByDolistoreId(string $fk_dolistore): int
	{
		if (empty($fk_dolistore)) {
			return 0;
		}

		if ($this->hasProductIddolistoreColumn()) {
			$serviceId = $this->findServiceIdByField('iddolistore', $fk_dolistore);
			if ($serviceId > 0) {
				dol_syslog(__METHOD__ . ' service found by extrafield iddolistore=' . $fk_dolistore . ' => ' . $serviceId, LOG_DEBUG);
				return $serviceId;
			}

			dol_syslog(__METHOD__ . ' no service found by extrafield iddolistore=' . $fk_dolistore . ', fallback on ref', LOG_DEBUG);
		} else {
			dol_syslog(__METHOD__ . ' product extrafield iddolistore is missing, fallback on ref for ' . $fk_dolistore, LOG_WARNING);
		}

		$serviceId = $this->findServiceIdByField('ref', $fk_dolistore);
		if ($serviceId > 0) {
			dol_syslog(__METHOD__ . ' service found by ref=' . $fk_dolistore . ' => ' . $serviceId, LOG_DEBUG);
			return $serviceId;
		}

		dol_syslog(__METHOD__ . ' no service mapping found for identifier=' . $fk_dolistore, LOG_WARNING);

		return 0;
	}

	/**
	 * Finds service candidates to assist manual mapping in UI.
	 * This method never creates or links anything automatically.
	 *
	 * @param string $itemReference Dolistore item reference
	 * @param string $itemName      Dolistore item label
	 * @param int    $limit         Maximum number of candidates
	 * @return array<int,array<string,mixed>> Candidate list for manual interface
	 */
	public function findServiceCandidatesFromDolistoreData(string $itemReference, string $itemName, int $limit = 10): array
	{
		$itemReference = trim($itemReference);
		$itemName = trim($itemName);
		$limit = max(1, min(50, $limit));

		if ($itemReference === '' && $itemName === '') {
			return array();
		}

		$refEscaped = $this->db->escape($itemReference);
		$nameEscaped = $this->db->escape($itemName);
		$searchOnRef = ($itemReference !== '');
		$searchOnName = ($itemName !== '');
		$hasIddolistoreColumn = $this->hasProductIddolistoreColumn();

		$sql = 'SELECT p.rowid, p.ref, p.label';
		if ($hasIddolistoreColumn) {
			$sql .= ', pe.iddolistore';
		} else {
			$sql .= ', "" as iddolistore';
		}
		$sql .= ',';
		$sql .= ' (';
		if ($searchOnRef) {
			$sql .= ' (CASE WHEN p.ref = "' . $refEscaped . '" THEN 100 ELSE 0 END)';
			$sql .= ' + (CASE WHEN p.ref LIKE "' . $refEscaped . '%" THEN 60 ELSE 0 END)';
			$sql .= ' + (CASE WHEN p.ref LIKE "%' . $refEscaped . '%" THEN 40 ELSE 0 END)';
			if ($hasIddolistoreColumn) {
				$sql .= ' + (CASE WHEN pe.iddolistore = "' . $refEscaped . '" THEN 30 ELSE 0 END)';
			}
		}
		if ($searchOnRef && $searchOnName) {
			$sql .= ' + ';
		}
		if ($searchOnName) {
			$sql .= ' (CASE WHEN p.label LIKE "%' . $nameEscaped . '%" THEN 35 ELSE 0 END)';
			$sql .= ' + (CASE WHEN p.description LIKE "%' . $nameEscaped . '%" THEN 15 ELSE 0 END)';
		}
		$sql .= ' ) as match_score';
		$sql .= ' FROM ' . $this->db->prefix() . 'product as p';
		if ($hasIddolistoreColumn) {
			$sql .= ' LEFT JOIN ' . $this->db->prefix() . 'product_extrafields as pe ON pe.fk_object = p.rowid';
		}
		$sql .= ' WHERE p.fk_product_type = ' . ((int) Product::TYPE_SERVICE);
		$sql .= ' AND p.entity IN (' . getEntity('product') . ')';
		$sql .= ' AND (';
		$conditions = array();
		if ($searchOnRef) {
			$conditions[] = 'p.ref = "' . $refEscaped . '"';
			$conditions[] = 'p.ref LIKE "' . $refEscaped . '%"';
			$conditions[] = 'p.ref LIKE "%' . $refEscaped . '%"';
			if ($hasIddolistoreColumn) {
				$conditions[] = 'pe.iddolistore = "' . $refEscaped . '"';
			}
		}
		if ($searchOnName) {
			$conditions[] = 'p.label LIKE "%' . $nameEscaped . '%"';
			$conditions[] = 'p.description LIKE "%' . $nameEscaped . '%"';
		}
		$sql .= implode(' OR ', $conditions);
		$sql .= ')';
		$sql .= ' ORDER BY match_score DESC, p.ref ASC';
		$sql .= ' LIMIT ' . ((int) $limit);

		$resql = $this->db->query($sql);
		if (! $resql) {
			dol_syslog(__METHOD__ . ' SQL error while searching candidates for ref=' . $itemReference . ' name=' . $itemName, LOG_WARNING);
			return array();
		}

		$candidates = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$candidates[] = array(
				'id' => (int) $obj->rowid,
				'ref' => (string) $obj->ref,
				'label' => (string) $obj->label,
				'iddolistore' => (string) ($obj->iddolistore ?? ''),
				'score' => (int) $obj->match_score
			);
		}
		$this->db->free($resql);

		dol_syslog(__METHOD__ . ' candidates proposed=' . count($candidates) . ' for ref=' . $itemReference . ' name=' . $itemName, LOG_DEBUG);

		return $candidates;
	}

	/**
	 * Builds a lightweight manual mapping proposal payload for UI usage.
	 *
	 * @param string $itemReference Dolistore item reference
	 * @param string $itemName      Dolistore item label
	 * @param array  $candidates    Candidate services
	 * @return array<string,mixed>  Structured proposal
	 */
	public function buildServiceMappingProposal(string $itemReference, string $itemName, array $candidates = array()): array
	{
		return array(
			'dolistore_ref' => trim($itemReference),
			'dolistore_label' => trim($itemName),
			'candidates' => $candidates,
			'actions' => array(
				'can_create_service' => true,
				'can_link_existing_service' => !empty($candidates)
			)
		);
	}

	/**
	 * Builds private note block for Dolistore automatic import metadata.
	 *
	 * @param array $importData Import metadata (lang, import_hash, order_ref, buyer_email)
	 * @return string           Formatted private note block
	 */
	public function buildDolistoreImportPrivateNote(array $importData = array()): string
	{
		global $langs;

		$langDetected = !empty($importData['lang']) ? (string) $importData['lang'] : $langs->defaultlang;
		$importHash = !empty($importData['import_hash']) ? (string) $importData['import_hash'] : substr(hash('sha256', json_encode($importData)), 0, 16);

		$lines = array();
		$lines[] = $langs->transnoentitiesnoconv("DolistorePrivateNoteHeader");
		$lines[] = $langs->transnoentitiesnoconv("DolistorePrivateNoteLangLabel") . ': ' . ($langDetected !== '' ? $langDetected : $langs->transnoentitiesnoconv("DolistorePrivateNoteNotAvailable"));
		$lines[] = $langs->transnoentitiesnoconv("DolistorePrivateNoteHashLabel") . ': ' . ($importHash !== '' ? $importHash : $langs->transnoentitiesnoconv("DolistorePrivateNoteNotAvailable"));

		if (!empty($importData['order_ref'])) {
			$lines[] = $langs->transnoentitiesnoconv("DolistorePrivateNoteOrderRefLabel") . ': ' . (string) $importData['order_ref'];
		}
		if (!empty($importData['buyer_email'])) {
			$lines[] = $langs->transnoentitiesnoconv("DolistorePrivateNoteBuyerEmailLabel") . ': ' . (string) $importData['buyer_email'];
		}

		return implode("\n", $lines);
	}

	/**
	 * Manually creates a native Dolibarr service from Dolistore data.
	 * This method must be called explicitly from a manual action.
	 *
	 * @param User   $user        User performing manual action
	 * @param string $itemReference Dolistore reference
	 * @param string $itemName    Dolistore label
	 * @param string $proposedRef Optional proposed service ref
	 * @return array<string,mixed> Result payload for UI
	 */
	public function createServiceFromDolistoreData(User $user, string $itemReference, string $itemName, string $proposedRef = ''): array
	{
		global $langs;

		$result = array(
			'success' => false,
			'code' => 'error',
			'service_id' => 0,
			'service_ref' => '',
			'message_key' => 'DolistoreServiceManualCreateError'
		);

		if (!$this->hasServiceManagementPermission($user)) {
			$result['code'] = 'permission_denied';
			$result['message_key'] = 'DolistoreServiceManualCreateDenied';
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualCreateDenied") . '</span>';
			dol_syslog(__METHOD__ . ' permission denied for user=' . ((int) $user->id), LOG_WARNING);
			return $result;
		}

		$itemReference = trim($itemReference);
		$itemName = trim($itemName);
		$proposedRef = trim($proposedRef);

		$existingId = $this->getServiceIdByDolistoreId($itemReference);
		if ($existingId > 0) {
			$existingProduct = new Product($this->db);
			$existingProduct->fetch($existingId);
			$result['success'] = true;
			$result['code'] = 'already_exists';
			$result['service_id'] = $existingId;
			$result['service_ref'] = (string) $existingProduct->ref;
			$result['message_key'] = 'DolistoreServiceManualAlreadyExists';
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreServiceManualAlreadyExists", dol_escape_htmltag($itemReference), dol_escape_htmltag($existingProduct->ref)) . '</span>';
			dol_syslog(__METHOD__ . ' service already exists for item_reference=' . $itemReference . ' service_id=' . $existingId, LOG_INFO);
			return $result;
		}

		$serviceRef = $proposedRef;
		if ($serviceRef === '') {
			$serviceRef = $itemReference !== '' ? $itemReference : preg_replace('/[^A-Za-z0-9_-]+/', '-', $itemName);
		}
		$serviceRef = trim($serviceRef, '-_');
		if ($serviceRef === '') {
			$serviceRef = 'DOLISTORE-SERVICE';
		}

		$baseRef = $serviceRef;
		$refIndex = 1;
		while ($this->findServiceIdByField('ref', $serviceRef) > 0) {
			$refIndex++;
			$serviceRef = $baseRef . '-' . $refIndex;
		}

		$product = new Product($this->db);
		$product->ref = $serviceRef;
		$product->label = $itemName !== '' ? $itemName : $itemReference;
		$product->description = $itemName;
		$product->type = self::DOLISTORE_PRODUCT_TYPE_SERVICE;
		$product->status = 1;
		$product->status_buy = 0;
		$product->duration = self::DOLISTORE_SERVICE_SUPPORT_DURATION_MONTHS . 'm';

		$this->db->begin();
		$createResult = $product->create($user);
		if ($createResult <= 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualCreateError", dol_escape_htmltag($itemReference), dol_escape_htmltag($itemName), dol_escape_htmltag($product->error)) . '</span>';
			dol_syslog(__METHOD__ . ' failed to create service ref=' . $serviceRef . ' error=' . $product->error, LOG_ERR);
			return $result;
		}

		$product->array_options = array('options_iddolistore' => $itemReference);
		$extraResult = $product->insertExtraFields();
		if ($extraResult < 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualCreateError", dol_escape_htmltag($itemReference), dol_escape_htmltag($itemName), dol_escape_htmltag($product->error)) . '</span>';
			dol_syslog(__METHOD__ . ' failed to set iddolistore extrafield for service_id=' . ((int) $product->id), LOG_ERR);
			return $result;
		}

		$this->db->commit();

		$result['success'] = true;
		$result['code'] = 'created';
		$result['service_id'] = (int) $product->id;
		$result['service_ref'] = (string) $product->ref;
		$result['message_key'] = 'DolistoreServiceManualCreated';

		$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreServiceManualCreated", dol_escape_htmltag($itemReference), dol_escape_htmltag($product->ref)) . '</span>';
		dol_syslog(__METHOD__ . ' service created manually service_id=' . ((int) $product->id) . ' ref=' . $product->ref . ' for item_reference=' . $itemReference, LOG_INFO);

		return $result;
	}

	/**
	 * Manually links a Dolistore identifier to an existing Dolibarr service.
	 * This method must be called explicitly from a manual action.
	 *
	 * @param User   $user          User performing manual action
	 * @param string $itemReference Dolistore reference
	 * @param string $itemName      Dolistore label
	 * @param int    $serviceId     Target Dolibarr service id
	 * @return array<string,mixed>  Result payload for UI
	 */
	public function associateDolistoreItemToExistingService(User $user, string $itemReference, string $itemName, int $serviceId): array
	{
		global $langs;

		$result = array(
			'success' => false,
			'code' => 'error',
			'service_id' => 0,
			'service_ref' => '',
			'message_key' => 'DolistoreServiceManualLinkError'
		);

		if (!$this->hasServiceManagementPermission($user)) {
			$result['code'] = 'permission_denied';
			$result['message_key'] = 'DolistoreServiceManualLinkDenied';
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualLinkDenied") . '</span>';
			dol_syslog(__METHOD__ . ' permission denied for user=' . ((int) $user->id), LOG_WARNING);
			return $result;
		}

		$itemReference = trim($itemReference);
		$itemName = trim($itemName);
		$serviceId = (int) $serviceId;

		$targetService = new Product($this->db);
		if ($serviceId <= 0 || $targetService->fetch($serviceId) <= 0 || (int) $targetService->type !== (int) Product::TYPE_SERVICE) {
			$result['code'] = 'invalid_target';
			$result['message_key'] = 'DolistoreServiceManualLinkInvalidTarget';
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualLinkInvalidTarget", $serviceId) . '</span>';
			dol_syslog(__METHOD__ . ' invalid target service_id=' . $serviceId . ' for item_reference=' . $itemReference, LOG_WARNING);
			return $result;
		}

		$targetService->fetch_optionals();
		$currentMappedRef = (string) ($targetService->array_options['options_iddolistore'] ?? '');
		if ($currentMappedRef !== '' && $currentMappedRef !== $itemReference) {
			$result['code'] = 'target_conflict';
			$result['service_id'] = (int) $targetService->id;
			$result['service_ref'] = (string) $targetService->ref;
			$result['message_key'] = 'DolistoreServiceManualLinkConflictTarget';
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualLinkConflictTarget", dol_escape_htmltag($targetService->ref), dol_escape_htmltag($currentMappedRef)) . '</span>';
			dol_syslog(__METHOD__ . ' target conflict service_id=' . ((int) $targetService->id) . ' existing_iddolistore=' . $currentMappedRef . ' requested=' . $itemReference, LOG_WARNING);
			return $result;
		}

		$alreadyMappedServiceId = $this->getServiceIdByDolistoreId($itemReference);
		if ($alreadyMappedServiceId > 0 && $alreadyMappedServiceId !== (int) $targetService->id) {
			$alreadyMappedService = new Product($this->db);
			$alreadyMappedService->fetch($alreadyMappedServiceId);
			$result['code'] = 'reference_conflict';
			$result['service_id'] = (int) $alreadyMappedService->id;
			$result['service_ref'] = (string) $alreadyMappedService->ref;
			$result['message_key'] = 'DolistoreServiceManualLinkConflictReference';
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualLinkConflictReference", dol_escape_htmltag($itemReference), dol_escape_htmltag($alreadyMappedService->ref)) . '</span>';
			dol_syslog(__METHOD__ . ' reference conflict item_reference=' . $itemReference . ' already linked to service_id=' . $alreadyMappedServiceId, LOG_WARNING);
			return $result;
		}

		$this->db->begin();
		$targetService->array_options['options_iddolistore'] = $itemReference;
		$extraResult = $targetService->insertExtraFields();
		if ($extraResult < 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreServiceManualLinkError", dol_escape_htmltag($itemReference), dol_escape_htmltag($itemName), dol_escape_htmltag($targetService->error)) . '</span>';
			dol_syslog(__METHOD__ . ' failed to link service_id=' . ((int) $targetService->id) . ' item_reference=' . $itemReference, LOG_ERR);
			return $result;
		}
		$this->db->commit();

		$result['success'] = true;
		$result['code'] = 'linked';
		$result['service_id'] = (int) $targetService->id;
		$result['service_ref'] = (string) $targetService->ref;
		$result['message_key'] = 'DolistoreServiceManualLinked';

		$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreServiceManualLinked", dol_escape_htmltag($itemReference), dol_escape_htmltag($targetService->ref)) . '</span>';
		dol_syslog(__METHOD__ . ' linked item_reference=' . $itemReference . ' to service_id=' . ((int) $targetService->id), LOG_INFO);

		return $result;
	}

	/**
	 * Legacy V1 entry point kept for compatibility.
	 *
	 * V2 no longer creates native Dolibarr customer orders from DoliStore mails.
	 *
	 * @param User  $user      User performing manual action
	 * @param int   $socid     Thirdparty id
	 * @param array $orderData Order metadata (order_ref, date_order, lang, import_hash, buyer_email)
	 * @param array $items     Extracted Dolistore items
	 * @return array<string,mixed> Result payload for UI
	 */
	public function createCustomerOrderFromDolistoreData(User $user, int $socid, array $orderData = array(), array $items = array()): array
	{
		global $langs;

		$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreNativeOrderImportObsolete") . '</span>';
		dol_syslog(__METHOD__ . ' blocked obsolete native customer order creation in DoliStore Extract V2', LOG_WARNING);

		return array(
			'success' => false,
			'code' => 'obsolete',
			'order_id' => 0,
			'order_ref' => '',
			'message_key' => 'DolistoreNativeOrderImportObsolete'
		);
	}

	/**
	 * Returns configured Dolistore commission rate in percent.
	 *
	 * @return float
	 */
	private function getDolistoreCommissionRate(): float
	{
		$commissionRateRaw = trim((string) getDolGlobalString('DOLISTOREXTRACT_COMMISSION_PERCENT'));
		if ($commissionRateRaw === '') {
			return 0.0;
		}

		$commissionRateRaw = str_replace(',', '.', $commissionRateRaw);
		$commissionRate = (float) $commissionRateRaw;
		if ($commissionRate < 0) {
			$commissionRate = 0;
		}
		if ($commissionRate > 100) {
			$commissionRate = 100;
		}

		return $commissionRate;
	}

	/**
	 * Builds archived DoliStore order line description from Dolistore item data.
	 *
	 * @param array $item       Dolistore extracted item
	 * @param bool  $isRefunded Refund flag
	 * @return string     Formatted line description
	 */
	private function buildDolistoreOrderLineDescription(array $item, bool $isRefunded = false): string
	{
		global $langs;

		$itemName = (string) ($item['item_name'] ?? '');
		$itemReference = (string) ($item['item_reference'] ?? '');

		$lines = array();
		if ($isRefunded) {
			$lines[] = $langs->transnoentitiesnoconv("DolistoreOrderLineDescRefund");
		}
		$lines[] = $itemName !== '' ? $itemName : $langs->transnoentitiesnoconv("DolistoreOrderLineDescHeader");
		$lines[] = $langs->transnoentitiesnoconv("DolistoreOrderLineDescItemRef") . ': ' . ($itemReference !== '' ? $itemReference : $langs->transnoentitiesnoconv("DolistorePrivateNoteNotAvailable"));
		$lines[] = $langs->transnoentitiesnoconv("DolistoreOrderLineDescSupport");

		return implode("\n", $lines);
	}

	/**
	 * Finds one Dolibarr service by a supported mapping field.
	 *
	 * @param string $fieldName  Supported field name (iddolistore|ref)
	 * @param string $fieldValue Value to search
	 * @return int               Service rowid (>0) if found, 0 otherwise
	 */
	private function findServiceIdByField(string $fieldName, string $fieldValue): int
	{
		if (empty($fieldValue)) {
			return 0;
		}

		$hasIddolistoreColumn = $this->hasProductIddolistoreColumn();
		$allowedFields = array(
			'iddolistore' => 'pe.iddolistore',
			'ref' => 'p.ref'
		);

		if (!isset($allowedFields[$fieldName])) {
			return 0;
		}
		if ($fieldName === 'iddolistore' && !$hasIddolistoreColumn) {
			return 0;
		}

		$sql = 'SELECT p.rowid';
		$sql .= ' FROM ' . $this->db->prefix() . 'product as p';
		if ($hasIddolistoreColumn) {
			$sql .= ' INNER JOIN ' . $this->db->prefix() . 'product_extrafields as pe ON pe.fk_object = p.rowid';
		}
		$sql .= ' WHERE ' . $allowedFields[$fieldName] . ' = "' . $this->db->escape($fieldValue) . '"';
		$sql .= ' AND p.fk_product_type = ' . ((int) Product::TYPE_SERVICE);
		$sql .= ' AND p.entity IN (' . getEntity('product') . ')';
		$sql .= ' ORDER BY p.rowid ASC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (! $resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return !empty($obj->rowid) ? (int) $obj->rowid : 0;
	}

	/**
	 * Checks if iddolistore column exists on product extrafields table.
	 *
	 * @return bool True when column exists
	 */
	private function hasProductIddolistoreColumn(): bool
	{
		if ($this->hasProductIddolistoreColumn !== null) {
			return (bool) $this->hasProductIddolistoreColumn;
		}

		$sql = 'SHOW COLUMNS FROM ' . $this->db->prefix() . 'product_extrafields LIKE "iddolistore"';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->hasProductIddolistoreColumn = false;
			return false;
		}

		$this->hasProductIddolistoreColumn = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return (bool) $this->hasProductIddolistoreColumn;
	}

	/**
	 * Checks if user can create/manage services.
	 *
	 * @param User $user User context
	 * @return bool      True when permission is granted
	 */
	private function hasServiceManagementPermission(User $user): bool
	{
		if (!empty($user->admin)) {
			return true;
		}

		if (method_exists($user, 'hasRight')) {
			return (bool) $user->hasRight('produit', 'creer');
		}

		return !empty($user->rights->produit->creer);
	}
	/**
	 * Converts a formatted string representing a monetary amount to a float.
	 *
	 * @param string $formattedString The formatted amount (e.g., '2 356 156,00 €').
	 * @return float The converted float value (e.g., 2356156.00).
	 */
	public function convertToFloat(string $formattedString): float
	{
		//Espace insécable
		$formattedString = str_replace("\xC2\xA0", " ", $formattedString);  // Remplace les espaces insécables par des espaces réguliers

		// Step 1: Validate the input (checks if the string contains digits, commas, and potentially a currency symbol)
		if (! preg_match('/^[\d\s,.€]+$/', $formattedString)) {
			$this->logError('Invalid number format: ' . $formattedString);
		}

		// Step 2: Remove the euro symbol and thousand separators
		$cleanString = str_replace(['€', ' '], '', $formattedString);

		// Step 3: Replace the decimal comma with a dot for PHP's float notation
		$cleanString = str_replace(',', '.', $cleanString);

		// Step 4: Convert the cleaned string to float
		$floatValue = (float) $cleanString;

		// Return the converted float value
		return $floatValue;
	}
	/**
	 * Retrieves an existing customer ID (by name, email, SIRET or Contact) or creates a new one.
	 * Also handles the creation of the contact if the company exists.
	 *
	 * @param User $user      Dolibarr user object.
	 * @param array $buyerData Array of customer details extracted from the email.
	 * @return int             ID of the company (socid), or 0 if failed.
	 */
	private function getOrCreateCustomer(User $user, array $buyerData): int
	{
		global $langs;
		$company = new Societe($this->db);
		$companyId = 0;

		// 1. Search exactly by name
		if ($company->fetch(0, $buyerData['buyer_company']) > 0) {
			$companyId = $company->id;
		}

		// 2. Search exactly by email (Société)
		if (!$companyId && $company->fetch(0, '', '', '', '', '', '', '', '', '', $buyerData['buyer_email']) > 0) {
			$companyId = $company->id;
		}

		// 3. Search exactly by idprof2 / SIRET (FR Only)
		if (!$companyId && $buyerData['buyer_country_code'] == 'FR' && !empty($buyerData['buyer_idprof2']) && is_numeric($buyerData['buyer_idprof2'])) {
			$sql = '';
			if (strlen($buyerData['buyer_idprof2']) == 14) {
				$sql = 'SELECT rowid FROM ' . $this->db->prefix() . 'societe WHERE siret = "' . $this->db->escape($buyerData['buyer_idprof2']) . '"';
			} elseif (strlen($buyerData['buyer_idprof2']) == 9) {
				$sql = 'SELECT rowid FROM ' . $this->db->prefix() . 'societe WHERE siren = "' . $this->db->escape($buyerData['buyer_idprof2']) . '"';
			}
			if ($sql) {
				$sql .= ' AND entity IN (' . getEntity('societe') . ')';
			}

				if ($sql) {
					$resql = $this->db->query($sql);
					if ($resql) {
						$obj = $this->db->fetch_object($resql);
						if ($obj && $obj->rowid > 0) {
							$companyId = (int) $obj->rowid;
							$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreCompanyFoundBySiret", $companyId) . '</span>';
						}
						$this->db->free($resql);
					}
				}
			}

			// 4. Search via Contact Email (if still not found)
		if (!$companyId) {
			$contact = new \Contact($this->db);
			$fetchResult = $contact->fetch('', '', '', trim($buyerData['buyer_email']));

			if ($fetchResult > 0) {
					if ($fetchResult > 1) { // Multiple contacts with this address
						$q = 'SELECT s.rowid';
						$q .= ' FROM ' . $this->db->prefix() . 'societe s';
						$q .= ' INNER JOIN ' . $this->db->prefix() . 'socpeople sp ON (sp.fk_soc = s.rowid)';
						$q .= ' WHERE sp.email = "' . $this->db->escape($buyerData['buyer_email']) . '"';
						$q .= ' AND s.entity IN (' . getEntity('societe') . ')';
						$q .= ' AND sp.entity IN (' . getEntity('contact') . ')';
						$q .= ' ORDER BY s.rowid ASC';
						$q .= ' LIMIT 1';
					$queryResult = $this->db->query($q);
					if ($res = $this->db->fetch_object($queryResult)) {
						$companyId = $res->rowid;
						$this->logOutput .= '<br/>-> <span class="warning">Multiple contacts found, selected company ID: <b>' . $companyId . '</b></span>';
					}
				} else {
					// Single contact found
					if ($contact->socid > 0) {
						$companyId = $contact->socid;
						$this->logOutput .= '<br/>-> <span class="ok">Company found by contact email.</span>';
					}
				}
			}
		}

		// 5. Critical validation: Company name missing
		if (empty($buyerData['buyer_company'])) {
			$this->logOutput .= '<br/>-> <span class="error">No company name in order data, cannot proceed</span>';
			// Note: We return 0 to indicate failure; the rollback will be performed higher up.
			return 0;
		}

		// 6. Contact Management for Existing Companies or Third-Party Creation
		if ($companyId > 0) {
			// Company found: We make sure that the contact exists.
			$contact = new \Contact($this->db);
			$sql = "SELECT rowid FROM " . $this->db->prefix() . "socpeople";
			$sql .= " WHERE fk_soc = " . $companyId;
			$sql .= " AND email = '" . $this->db->escape($buyerData['buyer_email']) . "'";
			$sql .= " AND entity IN (" . getEntity('contact') . ")";
			$resql = $this->db->query($sql);

			// If no contact exists, create one
			if ($resql && $this->db->num_rows($resql) == 0) {
				// Use dolistoreMail to clean up first and last names as before
				$dolistoreMailTemp = new \dolistoreMail();
				$dolistoreMailTemp->setDatas($buyerData);

				$contact->socid = $companyId;
				$contact->lastname  = $dolistoreMailTemp->buyer_lastname;
				$contact->firstname = $buyerData['buyer_firstname'];
				$contact->email     = $buyerData['buyer_email'];

				$result = $contact->create($user);
				if ($result < 0) {
					$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreContactCreationError").'</span>';
				} else {
					$this->logOutput .= '<br/>-> <span class="ok">'.$langs->trans("DolistoreContactCreated").'</span>';
				}
			} else {
				$this->logOutput .= '<br/>-> <span class="ok">'.$langs->trans("DolistoreContactFound").'</span>';
			}
		} else {
			// 7. Customer not found => Create new company
			$dolistoreMailTemp = new \dolistoreMail();
			$dolistoreMailTemp->setDatas($buyerData);
			$companyId = $this->newCustomerFromDatas($user, $dolistoreMailTemp);

			if ($companyId <= 0) {
				$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreFailedToCreateNewCompany") . '</span>';
			}
		}

		return $companyId;
	}
	/**
	 * Logs an error message, adds it to the error array, and increments the error count.
	 *
	 * @param string $message The error message to log
	 * @return void
	 */
	private function logError(string $message): void
	{
		global $error;
		dol_syslog(__METHOD__ . ' - ' . $message, LOG_ERR);
		$this->errors[] = $message;
		$this->logCat .= $message;
		$error++;
	}
	/**
	 * Process order items (Sales & Events)
	 *
	 * @param User  $user      User object
	 * @param int    $companyId Company ID
	 * @param string $orderRef  Order reference
	 * @param array  $items     List of items
	 * @return array|null       List of success items, or null on critical error
	 */
	private function processOrderItems(User $user, int $companyId, string $orderRef, array $items): ?array
	{
		global $langs;
		$successList = [];
		$mappedItems = array();
		$hasUnmappedItems = false;
		$unmappedPolicy = $this->getUnmappedServicePolicy();

		foreach ($items as $product) {
			$product = $this->enforceDolistoreServiceBusinessRule($product);
			$product['fk_service'] = $this->getServiceIdByDolistoreId((string) ($product['item_reference'] ?? ''));
			$product['service_candidates'] = array();
			$product['service_mapping_proposal'] = array();
			if (empty($product['fk_service'])) {
				$product['service_candidates'] = $this->findServiceCandidatesFromDolistoreData((string) ($product['item_reference'] ?? ''), (string) ($product['item_name'] ?? ''));
				$product['service_mapping_proposal'] = $this->buildServiceMappingProposal((string) ($product['item_reference'] ?? ''), (string) ($product['item_name'] ?? ''), $product['service_candidates']);
				if (!empty($product['service_candidates'])) {
					$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreServiceMappingCandidatesFound", dol_escape_htmltag((string) ($product['item_reference'] ?? '')), dol_escape_htmltag((string) ($product['item_name'] ?? '')), count($product['service_candidates'])) . '</span>';
					dol_syslog(__METHOD__ . ' no exact service mapping for ref=' . ((string) ($product['item_reference'] ?? '')) . ' label=' . ((string) ($product['item_name'] ?? '')) . ', candidates=' . count($product['service_candidates']), LOG_INFO);
				} else {
					$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreServiceMappingNotFound", dol_escape_htmltag((string) ($product['item_reference'] ?? '')), dol_escape_htmltag((string) ($product['item_name'] ?? ''))) . '</span>';
					dol_syslog(__METHOD__ . ' no service mapping and no candidates for ref=' . ((string) ($product['item_reference'] ?? '')) . ' label=' . ((string) ($product['item_name'] ?? '')), LOG_WARNING);
				}

				$hasUnmappedItems = true;
				if ($unmappedPolicy === self::DOLISTORE_UNMAPPED_POLICY_CREATE) {
					$createResult = $this->createServiceFromDolistoreData($user, (string) ($product['item_reference'] ?? ''), (string) ($product['item_name'] ?? ''), (string) ($product['item_reference'] ?? ''));
					if (!empty($createResult['success']) && !empty($createResult['service_id'])) {
						$product['fk_service'] = (int) $createResult['service_id'];
						$mappedItems[] = $product;
						$successList[] = $product['item_name'];
						$this->notifyConfiguredUserForUnmappedService($orderRef, (string) ($product['item_reference'] ?? ''), (string) ($product['item_name'] ?? ''), self::DOLISTORE_UNMAPPED_POLICY_CREATE, (string) ($createResult['service_ref'] ?? ''));
						continue;
					}
				}

				$this->notifyConfiguredUserForUnmappedService($orderRef, (string) ($product['item_reference'] ?? ''), (string) ($product['item_name'] ?? ''), self::DOLISTORE_UNMAPPED_POLICY_ABANDON);
				$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreUnmappedPolicyAbandonLine", dol_escape_htmltag((string) ($product['item_reference'] ?? '')), dol_escape_htmltag($orderRef)) . '</span>';
				dol_syslog(__METHOD__ . ' line abandoned for order_ref=' . $orderRef . ' item_reference=' . ((string) ($product['item_reference'] ?? '')) . ' unmapped policy=' . $unmappedPolicy, LOG_WARNING);
			} else {
				$mappedItems[] = $product;
				$successList[] = $product['item_name'];
			}

		}

		if (empty($mappedItems)) {
			$this->lastOrderImportStatus = ($hasUnmappedItems ? 'skipped_unmapped' : '');
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreNoServiceCandidate") . '</span>';
			return array();
		}

		return $successList;
	}

	/**
	 * Returns configured policy when a Dolistore service mapping is missing.
	 *
	 * @return string One of self::DOLISTORE_UNMAPPED_POLICY_*
	 */
	private function getUnmappedServicePolicy(): string
	{
		$policy = trim((string) getDolGlobalString('DOLISTOREXTRACT_UNMAPPED_SERVICE_POLICY'));
		if ($policy === '') {
			$legacyBehavior = trim((string) getDolGlobalString('DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR'));
			if ($legacyBehavior === self::DOLISTORE_UNMAPPED_BEHAVIOR_SKIP) {
				$policy = self::DOLISTORE_UNMAPPED_POLICY_CREATE;
			} else {
				$policy = self::DOLISTORE_UNMAPPED_POLICY_ABANDON;
			}
		}

		$allowed = array(self::DOLISTORE_UNMAPPED_POLICY_ABANDON, self::DOLISTORE_UNMAPPED_POLICY_CREATE);
		if (!in_array($policy, $allowed, true)) {
			return self::DOLISTORE_UNMAPPED_POLICY_ABANDON;
		}

		return $policy;
	}

	/**
	 * Return the configured invoice VAT rate or the native entity default.
	 *
	 * The string format is preserved so Dolibarr can keep an optional VAT code.
	 *
	 * @return string VAT rate such as "20", "5.5" or "20 (CODE)"
	 */
	private function getDolistoreInvoiceVatRate(): string
	{
		global $mysoc;

		$invoiceVatRate = trim(getDolGlobalString('DOLISTOREXTRACT_INVOICE_TVA_RATE'));
		if ($invoiceVatRate !== '') {
			return $invoiceVatRate;
		}

		if (is_object($mysoc)) {
			$defaultInvoiceVatRate = get_default_tva($mysoc, $mysoc);
			if ($defaultInvoiceVatRate !== -1 && $defaultInvoiceVatRate !== '-1' && trim((string) $defaultInvoiceVatRate) !== '') {
				return (string) $defaultInvoiceVatRate;
			}
		}

		return '0';
	}

	/**
	 * Generate the monthly DoliStore invoice.
	 *
	 * @param User $user  User
	 * @param bool $force Run even when automatic generation is disabled
	 * @return int 0 if nothing to do, invoice id if generated, <0 on error
	 */
	public function generateMonthlyDolistoreInvoice(User $user, bool $force = false): int
	{
		global $conf, $langs;

		$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills'));

		if (!$force && !getDolGlobalInt('DOLISTOREXTRACT_AUTO_CREATE_INVOICE')) {
			$this->logOutput .= '<br/><span class="warning">'.$langs->trans("DolistoreInvoiceAutoDisabled").'</span>';
			return 0;
		}

		$socid = getDolGlobalInt('DOLISTOREXTRACT_BILLING_THIRDPARTY_ID');
		if ($socid <= 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceThirdpartyMissing'), $user);
		}

		$year = (int) dol_print_date(dol_now(), '%Y');
		$month = (int) dol_print_date(dol_now(), '%m');
		$lockName = 'dolistoreextract_invoice_'.$conf->entity;
		if (!$this->acquireSqlLock($lockName)) {
			$this->logOutput .= '<br/><span class="warning">'.$langs->trans("DolistoreInvoiceLockUnavailable").'</span>';
			return 0;
		}

		try {
			$repairedInvoiceId = $this->reconcileHistoricalOrphanInvoiceBatches($user, $socid, $year, $month);
			$batch = new DolistoreInvoiceBatch($this->db);
			$batchResult = $batch->fetchByPeriod($year, $month, (int) $conf->entity);
			if ($batchResult < 0) {
				$result = $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchFetchError', $batch->error), $user);
			} elseif ($batchResult > 0) {
				$result = $this->reconcileExistingInvoiceBatch($batch, $user, $socid, $year, $month);
			} else {
				$result = $this->doGenerateMonthlyDolistoreInvoice($user, $socid, $year, $month);
			}
			if ($result === 0 && $repairedInvoiceId > 0) {
				$result = $repairedInvoiceId;
			}
		} finally {
			$this->releaseSqlLock($lockName);
		}

		return $result;
	}

	/**
	 * Internal invoice generation once lock is acquired.
	 *
	 * @param User $user User
	 * @param int  $socid Billing thirdparty id
	 * @param int  $year Period year
	 * @param int  $month Period month
	 * @return int
	 */
	private function doGenerateMonthlyDolistoreInvoice(User $user, int $socid, int $year, int $month, ?DolistoreInvoiceBatch $existingBatch = null): int
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$orderStatic = new DolistoreOrder($this->db);
		$orders = $orderStatic->fetchInvoiceableOrders(dol_now(), (int) $conf->entity);
		if (empty($orders)) {
			if ($existingBatch !== null) {
				return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchCannotBeReconciled'), $user, $existingBatch);
			}
			$this->logOutput .= '<br/>'.$langs->trans("DolistoreInvoiceNoInvoiceableOrder");
			return 0;
		}

		$amountHt = 0;
		$linesCount = 0;
		foreach ($orders as $order) {
			$amountHt += (float) $order->billable_total_ht;
			$linesCount += count($order->getLines());
		}
		$amountHt = (float) price2num($amountHt, 'MT');

		if ($existingBatch !== null && !$this->invoiceableOrdersMatchBatch($orders, $amountHt, $linesCount, $existingBatch)) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchCannotBeReconciled'), $user, $existingBatch);
		}

		if ($existingBatch === null) {
			$thresholdRaw = getDolGlobalString('DOLISTOREXTRACT_INVOICE_MIN_AMOUNT_HT');
			if ($thresholdRaw === '') {
				$thresholdRaw = '100.00';
			}
			$threshold = (float) str_replace(',', '.', $thresholdRaw);
			if ($amountHt < $threshold) {
				$this->logOutput .= '<br/>'.$langs->trans("DolistoreInvoiceThresholdNotReached", price($amountHt), price($threshold));
				return 0;
			}
		}

		$societe = new Societe($this->db);
		if ($societe->fetch($socid) <= 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceThirdpartyMissing'), $user, $existingBatch);
		}

		$this->db->begin();
		$invoice = new Facture($this->db);
		$invoice->entity = (int) $conf->entity;
		$invoice->socid = $socid;
		$invoice->date = dol_now();
		$invoice->datef = dol_now();
		$invoice->type = Facture::TYPE_STANDARD;
		$invoice->ref_client = $this->getMonthlyInvoiceCustomerReference($year, $month);
		$invoice->note_private = $this->buildDolistoreInvoicePrivateNote($year, $month, count($orders), $amountHt);
		$invoiceVatRate = $this->getDolistoreInvoiceVatRate();

		$invoiceId = $invoice->create($user);
		if ($invoiceId <= 0) {
			$this->db->rollback();
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceCreateError', $invoice->error), $user, $existingBatch);
		}

		foreach ($orders as $order) {
			foreach ($order->getLines() as $line) {
				$desc = $this->buildDolistoreInvoiceLineDescription($order, $line);
				$unitPrice = (float) price2num($line->billable_unit_price_ht, 'MU');
				$lineId = $invoice->addline($desc, $unitPrice, (float) $line->qty, $invoiceVatRate, 0, 0, (int) $line->fk_product);
				if ($lineId <= 0) {
					$this->db->rollback();
					return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceLineCreateError', $invoice->error), $user, $existingBatch);
				}
			}
		}

		foreach ($orders as $order) {
			if ($order->markAsInvoiced($invoiceId, dol_now(), $user) <= 0) {
				$this->db->rollback();
				return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreOrderMarkInvoicedError', $order->ref), $user, $existingBatch);
			}
		}

		$invoiceStatus = getDolGlobalString('DOLISTOREXTRACT_INVOICE_STATUS');
		if ($invoiceStatus === '') {
			$invoiceStatus = 'draft';
		}
		if ($invoiceStatus === 'validated') {
			if ($invoice->fetch($invoiceId) <= 0 || $invoice->fetch_lines() < 0) {
				$this->db->rollback();
				return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceReloadError'), $user, $existingBatch);
			}
			$validateResult = $invoice->validate($user);
			if ($validateResult < 0) {
				$this->db->rollback();
				return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceValidateError', $invoice->error), $user, $existingBatch);
			}
		}

		$batch = $existingBatch !== null ? $existingBatch : new DolistoreInvoiceBatch($this->db);
		$batch->entity = (int) $conf->entity;
		$batch->fk_facture = $invoiceId;
		$batch->period_year = $year;
		$batch->period_month = $month;
		$batch->amount_ht = $amountHt;
		$batch->orders_count = count($orders);
		$batch->lines_count = $linesCount;
		$batch->status = DolistoreInvoiceBatch::STATUS_DRAFT;
		$batch->log = $langs->transnoentitiesnoconv('DolistoreInvoiceBatchPendingLog', $invoiceId, count($orders), price($amountHt));
		$batchResult = $existingBatch !== null ? $batch->update($user) : $batch->create($user);
		if ($batchResult <= 0) {
			$this->db->rollback();
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchCreateError', $batch->error), $user, $existingBatch);
		}
		$batchId = (int) $batch->id;

		$this->db->commit();

		if ($invoice->fetch($invoiceId) <= 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceReloadError'), $user, $batch, $batchId);
		}

		return $this->completeInvoiceBatch($invoice, $batch, $societe, $user);
	}

	/**
	 * Relink historical orphan batches only when one existing invoice matches exactly.
	 *
	 * Historical batches are never regenerated because their original order selection
	 * cannot be reconstructed safely after the billing period has passed.
	 *
	 * @param User $user User
	 * @param int  $socid Billing thirdparty id
	 * @param int  $currentYear Current year
	 * @param int  $currentMonth Current month
	 * @return int Last repaired invoice id, 0 when no batch was repaired
	 */
	private function reconcileHistoricalOrphanInvoiceBatches(User $user, int $socid, int $currentYear, int $currentMonth): int
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$societe = new Societe($this->db);
		if ($societe->fetch($socid) <= 0) {
			return 0;
		}

		$sql = 'SELECT b.rowid FROM '.MAIN_DB_PREFIX.'dolistoreextract_invoice_batch as b';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'facture as linked_invoice ON linked_invoice.rowid = b.fk_facture AND linked_invoice.entity = b.entity';
		$sql .= ' WHERE b.entity = '.((int) $conf->entity);
		$sql .= ' AND (b.fk_facture IS NULL OR linked_invoice.rowid IS NULL)';
		$sql .= ' AND NOT (b.period_year = '.$currentYear.' AND b.period_month = '.$currentMonth.')';
		$sql .= ' ORDER BY b.period_year ASC, b.period_month ASC, b.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$batchIds = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$batchIds[] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		$lastRepairedInvoiceId = 0;
		foreach ($batchIds as $batchId) {
			$batch = new DolistoreInvoiceBatch($this->db);
			if ($batch->fetch($batchId) <= 0 || (int) $batch->entity !== (int) $conf->entity) {
				continue;
			}

			$refClient = $this->getMonthlyInvoiceCustomerReference((int) $batch->period_year, (int) $batch->period_month);
			$sql = 'SELECT f.rowid FROM '.MAIN_DB_PREFIX.'facture as f';
			$sql .= ' WHERE f.entity = '.((int) $batch->entity);
			$sql .= ' AND f.fk_soc = '.$socid;
			$sql .= " AND f.ref_client = '".$this->db->escape($refClient)."'";
			$sql .= ' ORDER BY f.rowid ASC';
			$resql = $this->db->query($sql);
			if (!$resql) {
				continue;
			}

			$candidateIds = array();
			while (is_object($obj = $this->db->fetch_object($resql))) {
				$candidateIds[] = (int) $obj->rowid;
			}
			$this->db->free($resql);

			$matchingInvoices = array();
			foreach ($candidateIds as $candidateId) {
				$invoice = new Facture($this->db);
				if ($invoice->fetch($candidateId) > 0 && $this->invoiceMatchesBatch($invoice, $batch, $socid, (int) $batch->period_year, (int) $batch->period_month)) {
					$matchingInvoices[] = $invoice;
				}
			}

			if (count($candidateIds) !== 1 || count($matchingInvoices) !== 1) {
				continue;
			}

			$invoice = $matchingInvoices[0];
			$batch->fk_facture = (int) $invoice->id;
			$batch->status = DolistoreInvoiceBatch::STATUS_DRAFT;
			if ($batch->update($user) <= 0) {
				continue;
			}
			$repairResult = $this->completeInvoiceBatch($invoice, $batch, $societe, $user, false);
			if ($repairResult > 0) {
				$lastRepairedInvoiceId = $repairResult;
			}
		}

		return $lastRepairedInvoiceId;
	}

	/**
	 * Reconcile a batch already registered for the current period.
	 *
	 * @param DolistoreInvoiceBatch $batch Existing batch
	 * @param User                  $user User
	 * @param int                   $socid Billing thirdparty id
	 * @param int                   $year Period year
	 * @param int                   $month Period month
	 * @return int
	 */
	private function reconcileExistingInvoiceBatch(DolistoreInvoiceBatch $batch, User $user, int $socid, int $year, int $month): int
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$societe = new Societe($this->db);
		if ($societe->fetch($socid) <= 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceThirdpartyMissing'), $user, $batch);
		}

		if ((int) $batch->fk_facture > 0) {
			$invoice = new Facture($this->db);
			if ($invoice->fetch((int) $batch->fk_facture) > 0 && $this->invoiceMatchesBatch($invoice, $batch, $socid, $year, $month)) {
				return $this->completeInvoiceBatch($invoice, $batch, $societe, $user);
			}
		}

		$refClient = $this->getMonthlyInvoiceCustomerReference($year, $month);
		$sql = 'SELECT f.rowid FROM '.MAIN_DB_PREFIX.'facture as f';
		$sql .= ' WHERE f.entity = '.((int) $batch->entity);
		$sql .= ' AND f.fk_soc = '.$socid;
		$sql .= " AND f.ref_client = '".$this->db->escape($refClient)."'";
		$sql .= ' ORDER BY f.rowid ASC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchFetchError', $this->db->lasterror()), $user, $batch);
		}

		$candidateIds = array();
		while (is_object($obj = $this->db->fetch_object($resql))) {
			$candidateIds[] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		$matchingInvoices = array();
		foreach ($candidateIds as $candidateId) {
			$invoice = new Facture($this->db);
			if ($invoice->fetch($candidateId) > 0 && $this->invoiceMatchesBatch($invoice, $batch, $socid, $year, $month)) {
				$matchingInvoices[] = $invoice;
			}
		}

		if (count($candidateIds) === 1 && count($matchingInvoices) === 1) {
			$invoice = $matchingInvoices[0];
			$batch->fk_facture = (int) $invoice->id;
			$batch->status = DolistoreInvoiceBatch::STATUS_DRAFT;
			if ($batch->update($user) <= 0) {
				return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchCreateError', $batch->error), $user, $batch);
			}

			return $this->completeInvoiceBatch($invoice, $batch, $societe, $user);
		}

		if (!empty($candidateIds)) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchAmbiguous'), $user, $batch);
		}

		return $this->doGenerateMonthlyDolistoreInvoice($user, $socid, $year, $month, $batch);
	}

	/**
	 * Complete a batch only after the native PDF exists and is readable.
	 *
	 * @param Facture               $invoice Native invoice
	 * @param DolistoreInvoiceBatch $batch Invoice batch
	 * @param Societe               $societe Billing thirdparty
	 * @param User                  $user User
	 * @param bool                  $allowEmail Allow optional email sending
	 * @return int
	 */
	private function completeInvoiceBatch(Facture $invoice, DolistoreInvoiceBatch $batch, Societe $societe, User $user, bool $allowEmail = true): int
	{
		global $langs;

		$pdfResult = $this->generateInvoicePdf($invoice, $langs);
		if ($pdfResult < 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoicePdfError'), $user, $batch, (int) $batch->id, (int) $invoice->id);
		}

		$batch->status = DolistoreInvoiceBatch::STATUS_SUCCESS;
		$batch->log = $langs->transnoentitiesnoconv('DolistoreInvoiceBatchCreatedLog', $invoice->ref, $batch->orders_count, price($batch->amount_ht));
		if ($batch->update($user) <= 0) {
			return $this->recordInvoiceFailure($langs->transnoentitiesnoconv('DolistoreInvoiceBatchCreateError', $batch->error), $user, $batch, (int) $batch->id, (int) $invoice->id);
		}

		if ($allowEmail && getDolGlobalInt('DOLISTOREXTRACT_AUTO_SEND_INVOICE') && empty($batch->email_sent)) {
			$mailResult = $this->sendDolistoreInvoiceEmail($invoice, $societe, $user);
			if ($mailResult > 0) {
				$batch->email_sent = 1;
				$batch->email_sent_date = dol_now();
				$batch->update($user);
			} else {
				$batch->status = DolistoreInvoiceBatch::STATUS_ERROR;
				$batch->log .= "\n".$langs->transnoentitiesnoconv('DolistoreInvoiceEmailError');
				$batch->update($user);
				DolistoreImportLog::add($this->db, 'error', $langs->transnoentitiesnoconv('DolistoreInvoiceEmailError'), 0, 'invoice', array('invoice_id' => (int) $invoice->id), $user, (int) $batch->id);
			}
		}

		DolistoreImportLog::add($this->db, 'success', $langs->transnoentitiesnoconv('DolistoreInvoiceGenerated', $invoice->ref), 0, 'invoice', array('invoice_id' => (int) $invoice->id), $user, (int) $batch->id);
		$this->logOutput .= '<br/><span class="ok">'.$langs->trans('DolistoreInvoiceGenerated', $invoice->ref).'</span>';

		return (int) $invoice->id;
	}

	/**
	 * Check that a native invoice unambiguously represents a stored batch.
	 *
	 * @param Facture               $invoice Native invoice
	 * @param DolistoreInvoiceBatch $batch Batch
	 * @param int                   $socid Billing thirdparty id
	 * @param int                   $year Period year
	 * @param int                   $month Period month
	 * @return bool
	 */
	private function invoiceMatchesBatch(Facture $invoice, DolistoreInvoiceBatch $batch, int $socid, int $year, int $month): bool
	{
		if ((int) $invoice->entity !== (int) $batch->entity || (int) $invoice->socid !== $socid) {
			return false;
		}
		if ((string) $invoice->ref_client !== $this->getMonthlyInvoiceCustomerReference($year, $month)) {
			return false;
		}
		if ((float) price2num($invoice->total_ht, 'MT') !== (float) price2num($batch->amount_ht, 'MT')) {
			return false;
		}

		$sql = 'SELECT COUNT(o.rowid) as orders_count, COALESCE(SUM(o.billable_total_ht), 0) as amount_ht';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order as o';
		$sql .= ' WHERE o.entity = '.((int) $batch->entity);
		$sql .= ' AND o.fk_facture = '.((int) $invoice->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!is_object($obj) || (int) $obj->orders_count !== (int) $batch->orders_count) {
			return false;
		}
		if ((float) price2num($obj->amount_ht, 'MT') !== (float) price2num($batch->amount_ht, 'MT')) {
			return false;
		}

		$sql = 'SELECT COUNT(l.rowid) as lines_count';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line as l';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order as o ON o.rowid = l.fk_order AND o.entity = l.entity';
		$sql .= ' WHERE o.entity = '.((int) $batch->entity);
		$sql .= ' AND o.fk_facture = '.((int) $invoice->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return is_object($obj) && (int) $obj->lines_count === (int) $batch->lines_count;
	}

	/**
	 * Check whether current invoiceable orders exactly match an orphan batch.
	 *
	 * @param DolistoreOrder[]      $orders Orders
	 * @param float                 $amountHt Total excl. tax
	 * @param int                   $linesCount Lines count
	 * @param DolistoreInvoiceBatch $batch Batch
	 * @return bool
	 */
	private function invoiceableOrdersMatchBatch(array $orders, float $amountHt, int $linesCount, DolistoreInvoiceBatch $batch): bool
	{
		return count($orders) === (int) $batch->orders_count
			&& $linesCount === (int) $batch->lines_count
			&& (float) price2num($amountHt, 'MT') === (float) price2num($batch->amount_ht, 'MT');
	}

	/**
	 * Return the stable customer reference used to identify a monthly invoice.
	 *
	 * @param int $year Year
	 * @param int $month Month
	 * @return string
	 */
	private function getMonthlyInvoiceCustomerReference(int $year, int $month): string
	{
		return 'DOLISTORE-'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Record a generation failure and keep a batch retryable.
	 *
	 * @param string                     $message Error message
	 * @param User                       $user User
	 * @param DolistoreInvoiceBatch|null $batch Batch
	 * @param int                        $batchId Batch id
	 * @param int                        $invoiceId Invoice id
	 * @return int Always -1
	 */
	private function recordInvoiceFailure(string $message, User $user, ?DolistoreInvoiceBatch $batch = null, int $batchId = 0, int $invoiceId = 0): int
	{
		$this->error = $message;
		$this->errors = array($message);
		$this->logOutput .= '<br/><span class="error">'.dol_escape_htmltag($message).'</span>';

		if ($batch !== null && !empty($batch->id)) {
			$batch->status = DolistoreInvoiceBatch::STATUS_ERROR;
			$batch->log = trim((string) $batch->log."\n".$message);
			$batch->update($user);
			$batchId = (int) $batch->id;
		}

		DolistoreImportLog::add($this->db, 'error', $message, 0, 'invoice', array('invoice_id' => $invoiceId), $user, $batchId);

		return -1;
	}

	/**
	 * Build invoice private note.
	 *
	 * @param int   $year Year
	 * @param int   $month Month
	 * @param int   $ordersCount Orders count
	 * @param float $amountHt Amount
	 * @return string
	 */
	private function buildDolistoreInvoicePrivateNote(int $year, int $month, int $ordersCount, float $amountHt): string
	{
		global $langs;

		return $langs->transnoentitiesnoconv("DolistoreInvoicePrivateNoteHeader")."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoicePrivateNotePeriod").': '.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT)."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoicePrivateNoteOrdersCount").': '.$ordersCount."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoicePrivateNoteAmountHt").': '.price($amountHt);
	}

	/**
	 * Build invoice line description.
	 *
	 * @param DolistoreOrder     $order Order
	 * @param DolistoreOrderLine $line  Line
	 * @return string
	 */
	private function buildDolistoreInvoiceLineDescription(DolistoreOrder $order, DolistoreOrderLine $line): string
	{
		global $langs;

		return trim((string) $line->product_label)."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineOrderRef").': '.(string) $order->dolistore_order_ref."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineOrderDate").': '.dol_print_date((int) $order->dolistore_order_date, 'day')."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineCustomer").': '.(string) $order->customer_name."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineCustomerEmail").': '.(string) $order->customer_email."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineProduct").': '.(string) $line->product_label."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineProductRef").': '.(string) $line->product_dolistore_ref."\n"
			.$langs->transnoentitiesnoconv("DolistoreInvoiceLineReleaseDate").': '.dol_print_date((int) $order->release_date, 'day');
	}

	/**
	 * Generate invoice PDF with the native model.
	 *
	 * @param Facture   $invoice Invoice
	 * @param Translate $langs   Langs
	 * @return int
	 */
	private function generateInvoicePdf(Facture $invoice, Translate $langs): int
	{
		if (!method_exists($invoice, 'generateDocument')) {
			return -1;
		}

		$model = getDolGlobalString('FACTURE_ADDON_PDF');
		if ($model === '') {
			$model = getDolGlobalString('INVOICE_ADDON_PDF');
		}

		$result = $invoice->generateDocument($model, $langs);
		if ($result <= 0 || $invoice->fetch((int) $invoice->id) <= 0) {
			return -1;
		}

		return $this->findInvoiceMainDocumentPath($invoice) !== '' ? 1 : -1;
	}

	/**
	 * Send invoice email to configured DoliStore address.
	 *
	 * @param Facture $invoice Invoice
	 * @param Societe $societe Thirdparty
	 * @param User    $user    User
	 * @return int
	 */
	private function sendDolistoreInvoiceEmail(Facture $invoice, Societe $societe, User $user): int
	{
		global $conf, $langs;

		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$to = getDolGlobalString('DOLISTOREXTRACT_INVOICE_EMAIL_TO');
		if ($to === '') {
			$to = (string) $societe->email;
		}
		if ($to === '') {
			return -1;
		}

		$outputlangs = $langs;
		if (getDolGlobalInt('MAIN_MULTILANGS') && !empty($societe->default_lang)) {
			$outputlangs = new Translate('', $conf);
			$outputlangs->setDefaultLang($societe->default_lang);
		}
		$outputlangs->loadLangs(array('bills', 'companies', 'mails', 'main', 'products', 'dolistorextract@dolistorextract'));

		$formmail = new FormMail($this->db);
		$templateId = getDolGlobalInt('DOLISTOREXTRACT_INVOICE_EMAIL_TEMPLATE_ID');
		$template = $formmail->getEMailTemplate($this->db, 'facture_send', $user, $outputlangs, $templateId, 1, '', ($templateId > 0 ? -1 : 1));
		if (!is_object($template) || ($templateId > 0 && (int) $template->id !== $templateId)) {
			return -1;
		}

		$invoice->thirdparty = $societe;
		$substitutionArray = getCommonSubstitutionArray($outputlangs, 0, null, $invoice);
		$substitutionArray['__INVOICE_REF__'] = (string) $invoice->ref;
		$substitutionArray['__SENDEREMAIL_SIGNATURE__'] = (string) $user->signature;
		complete_substitutions_array($substitutionArray, $outputlangs, $invoice, array('context' => 'formemail'));

		$subject = (string) $template->topic;
		if ($subject === '') {
			$subject = $outputlangs->transnoentitiesnoconv('SendBillRef', '__REF__');
		}
		$message = (string) $template->content;
		$subject = make_substitutions($subject, $substitutionArray);
		$message = make_substitutions(str_replace('\\n', "\n", $message), $substitutionArray);

		$from = (string) $template->email_from;
		if ($from !== '') {
			$from = make_substitutions($from, $substitutionArray);
		} else {
			$fromEmail = getDolGlobalString('INVOICE_EMAIL_SENDER', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'));
			if ($fromEmail === '') {
				$fromEmail = (string) $user->email;
			}
			$fromName = getDolGlobalString('INVOICE_EMAIL_SENDER_NAME');
			$from = $fromName !== '' ? $fromName.' <'.$fromEmail.'>' : $fromEmail;
		}

		$filenameList = array();
		$mimetypeList = array();
		$mimefilenameList = array();
		$pdfPath = $this->findInvoiceMainDocumentPath($invoice);
		if ($pdfPath === '' || !is_readable($pdfPath)) {
			return -1;
		}
		$filenameList[] = $pdfPath;
		$mimetypeList[] = dol_mimetype($pdfPath);
		$mimefilenameList[] = basename($pdfPath);

		$mail = new CMailFile($subject, $to, $from, $message, $filenameList, $mimetypeList, $mimefilenameList, '', '', 0, -1);
		return $mail->sendfile() ? 1 : -1;
	}

	/**
	 * Find generated invoice main document.
	 *
	 * @param Facture $invoice Invoice
	 * @return string
	 */
	private function findInvoiceMainDocumentPath(Facture $invoice): string
	{
		global $conf;

		if (empty($invoice->last_main_doc)) {
			return '';
		}

		$entity = !empty($invoice->entity) ? (int) $invoice->entity : (int) $conf->entity;
		$baseDir = !empty($conf->facture->multidir_output[$entity]) ? $conf->facture->multidir_output[$entity] : $conf->facture->dir_output;
		$invoiceRef = dol_sanitizeFileName($invoice->ref);
		$candidates = array(
			$baseDir.'/'.$invoice->last_main_doc,
			$baseDir.'/'.$invoiceRef.'/'.$invoice->last_main_doc,
			$baseDir.'/'.$invoiceRef.'/'.basename($invoice->last_main_doc),
		);

		foreach ($candidates as $candidate) {
			if (is_readable($candidate)) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Acquire SQL lock.
	 *
	 * @param string $lockName Lock name
	 * @return bool
	 */
	private function acquireSqlLock(string $lockName): bool
	{
			$resql = $this->db->query("SELECT GET_LOCK('".$this->db->escape($lockName)."', 0) as locked");
			if (!$resql) {
				dol_syslog(__METHOD__.' unable to acquire SQL lock '.$lockName.': '.$this->db->lasterror(), LOG_WARNING);
				return false;
			}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return !empty($obj->locked);
	}

	/**
	 * Release SQL lock.
	 *
	 * @param string $lockName Lock name
	 * @return void
	 */
	private function releaseSqlLock(string $lockName): void
	{
		$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($lockName)."')");
	}

	/**
	 * Enforces the Dolistore business rule for service-only items.
	 * This central guard must be reused by any future service creation flow.
	 *
	 * @param array $itemData Raw extracted item data
	 * @return array          Normalized item data for service workflows
	 */
	private function enforceDolistoreServiceBusinessRule(array $itemData): array
	{
		global $langs;

		if (isset($itemData['product_type']) && (int) $itemData['product_type'] !== self::DOLISTORE_PRODUCT_TYPE_SERVICE) {
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreServiceRuleEnforced", dol_escape_htmltag($itemData['item_reference'] ?? '')) . '</span>';
		}

		$itemData['product_type'] = self::DOLISTORE_PRODUCT_TYPE_SERVICE;
		$itemData['support_duration'] = self::DOLISTORE_SERVICE_SUPPORT_DURATION_MONTHS;
		$itemData['support_duration_unit'] = 'm';

		return $itemData;
	}
	/**
	 * Legacy V1 thank-you email entry point kept disabled in V2.
	 *
	 * @param User  $user         Dolibarr user object.
	 * @param array  $orderDetails Array containing buyer data and language.
	 * @param array  $productList  List of valid product names to include in the email.
	 * @return void
	 */
	private function sendThankYouEmail(User $user, array $orderDetails, array $productList): void
	{
		dol_syslog(__METHOD__ . ' skipped obsolete final customer thank-you email in DoliStore Extract V2', LOG_INFO);
	}

	/**
	 * Notify configured internal user about an unmapped Dolistore service workflow.
	 *
	 * @param string $orderRef Order reference
	 * @param string $itemReference Dolistore item reference
	 * @param string $itemName Dolistore item name
	 * @param string $actionTaken Action taken (abandon|create)
	 * @param string $serviceRef Created service reference
	 * @return void
	 */
	private function notifyConfiguredUserForUnmappedService(string $orderRef, string $itemReference, string $itemName, string $actionTaken, string $serviceRef = ''): void
	{
		global $langs;

		$userId = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');
		if ($userId <= 0) {
			return;
		}

		$user = new User($this->db);
		if ($user->fetch($userId) <= 0 || empty($user->email)) {
			return;
		}

		require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
		$from = getDolGlobalString('MAIN_INFO_SOCIETE_NOM') . ' <' . getDolGlobalString('MAIN_INFO_SOCIETE_MAIL') . '>';
		$to = $user->email;
		$subject = $langs->transnoentitiesnoconv("DolistoreUnmappedNotifySubject", $orderRef, $itemReference);
		$message = $langs->transnoentitiesnoconv("DolistoreUnmappedNotifyBody", $orderRef, $itemReference, $itemName, $actionTaken, $serviceRef);

		$mail = new CMailFile($subject, $to, $from, $message);
		if ($mail->sendfile()) {
			$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreUnmappedNotifySent", dol_escape_htmltag($to)) . '</span>';
			return;
		}

		$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreUnmappedNotifyFailed", dol_escape_htmltag($to)) . '</span>';
	}
	/**
	 * Load required classes if not already loaded
	 *
	 * @return void
	 */
	private function loadRequiredClasses(): void
	{
		if (!class_exists('Societe')) require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
		if (!class_exists('Contact')) require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
		if (!class_exists('dolistoreMailExtract')) require_once __DIR__ . "/dolistoreMailExtract.class.php";
		if (!class_exists('dolistoreMail')) require_once __DIR__ . "/dolistoreMail.class.php";
	}
	/**
	 * Parses raw IMAP emails to extract structured order data (read-only operation).
	 *
	 * @param array $emails Raw email objects.
	 * @return array Structured array of orders indexed by order reference.
	 */
	private function extractOrdersData(array $emails): array
	{
		global $langs;
		$orderData = [];

		if (empty($emails)) {
			$this->logOutput .= '<br/><span class="warning">No emails to process (IMAP inbox is empty)</span>';
			return [];
		}

		foreach ($emails as $email) {
			// Only Dolistore emails
			if (strpos($email->header->subject, 'DoliStore') !== false) {
				$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreProcessingEmail", dol_escape_htmltag($email->header->subject)) . '</strong>';

				// Data extraction
				$messageBody = (string) ($email->message->text ?? $email->message->plain ?? $email->message->html ?? '');
				$dolistoreMailExtract = new \dolistoreMailExtract($this->db, $messageBody);
				$emailLanguage = $dolistoreMailExtract->detectLang($email->header->subject);
				$data = $dolistoreMailExtract->extractAllDatas();
				$messageDate = !empty($email->header->date) ? (int) strtotime((string) $email->header->date) : dol_now();
				if ($messageDate <= 0) {
					$messageDate = dol_now();
				}
				$emailMetadata = array(
					'message_id' => $this->getEmailMessageId($email),
					'subject' => (string) ($email->header->subject ?? ''),
					'date' => $messageDate,
					'uid' => (int) ($email->header->uid ?? 0),
					'folder' => (string) ($email->dolistoreextract_folder ?? ''),
				);

				// Validation
				if (!empty($data) && !empty($data['order_ref']) && !empty($data['buyer_email'])) {
					$orderRef = $data['order_ref'];
					$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreDataExtracted", '<b>' . dol_escape_htmltag($orderRef) . '</b>') . '</span>';

					// Initialize the order if it doesn't exist yet
					if (!isset($orderData[$orderRef])) {
						$orderData[$orderRef] = [
							'buyer_data' => $data,
							'items' => [],
							'lang' => $emailLanguage,
							'email_metadata' => $emailMetadata,
							'order_date' => $messageDate,
							'source_emails' => array(),
							'raw_hash_parts' => array(),
						];
					}
					$orderData[$orderRef]['source_emails'][] = $this->buildEmailSourceContent($email);
					$orderData[$orderRef]['raw_hash_parts'][] = $emailMetadata['message_id'].'|'.$emailMetadata['subject'].'|'.$messageBody;

					// Loop through each item in the order
					if (!empty($data['items']) && is_array($data['items'])) {
						foreach ($data['items'] as $item) {
							if (!empty($item['item_reference']) && !empty($item['item_name'])) {
								$dateSale = $messageDate; // IMPORTANT: Date conversion for database and duplicate check

								$itemData = [
									'item_reference'   => $item['item_reference'],
									'item_name'        => $item['item_name'],
									'item_price'       => $item['item_price'],
									'item_quantity'    => $item['item_quantity'],
									'item_price_total' => $item['item_price_total'],
									'item_refunded'    => $item['item_refunded'] ?? null,
									'date_sale'        => $dateSale
								];

								// Keep service typing logic centralized for all current and future service creations.
								$orderData[$orderRef]['items'][] = $this->enforceDolistoreServiceBusinessRule($itemData);
								$orderData[$orderRef]['raw_hash_parts'][] = json_encode($itemData);

								$this->logOutput .= '<br/>-- <span class="ok">' . $langs->trans("DolistoreProductExtracted", dol_escape_htmltag($item['item_name'])) . '</span>';
							} else {
								$this->logOutput .= '<br/>-- <strong class="error">' . $langs->trans("DolistoreIncompleteProductData") . '</strong>';
							}
						}
					} else {
						$this->logOutput .= '<br/>-- <strong class="warning">' . $langs->trans("DolistoreNoItemsFound") . '</strong>';
					}
				} else {
					$this->logOutput .= '<br/>-> <strong class="error">' . $langs->trans("DolistoreExtractionFailed") . '</strong>';
				}
			}
		}
		foreach ($orderData as $orderRef => $details) {
			$orderData[$orderRef]['raw_hash'] = hash('sha256', implode("\n", (array) ($details['raw_hash_parts'] ?? array($orderRef))));
			unset($orderData[$orderRef]['raw_hash_parts']);
		}
		return $orderData;
	}
}
