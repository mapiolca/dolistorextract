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
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
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
 * Handles automated extraction of sales/orders from Dolistore emails and integration in Dolibarr (thirdparties, contacts, events, webmodule sales).
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
	 * Creates a Dolibarr calendar event (actioncomm) for a sold product extracted from Dolistore mail.
	 * Detects and avoids duplicate events based on a tag in the event note.
	 *
	 * @param array  $productDatas  Product/item array (extracted from email)
	 * @param string $orderRef      Dolistore order reference
	 * @param int    $socid         Thirdparty/Customer rowid
	 * @return int            New event rowid, 0 if already exists, -1 if error
	 */
	public function createEventFromExtractDatas(array $productDatas, string $orderRef, int $socid) : int
	{
		global $conf, $langs;

		// Check value
		if (empty($orderRef) || empty($productDatas['item_reference'])) {
			dol_syslog(__METHOD__ . ' Error : params order_name and product_ref missing');
			return -1;
		}

		$res = 0;

		$userStatic = new User($this->db);
		$userStatic->fetch(getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS'));

		require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
		$actionStatic = new ActionComm($this->db);

		$actionStatic->socid = $socid;

		$actionStatic->authorid = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');
		$actionStatic->userownerid = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');

		$actionStatic->datec = time();
		$actionStatic->datem = time();
		$actionStatic->datep = time();
		$actionStatic->percentage = 100;

		$actionStatic->type_code = 'AC_STRXTRACT';
		$actionStatic->label = $langs->trans('DolistorextractLabelActionForSale', $productDatas['item_name'] . ' (' . $productDatas['item_reference'] . ')');
		// Define a tag which allow to detect twice
		$actionStatic->note = 'ORDER:' . $orderRef . ':' . $productDatas['item_reference'];
		// Check if import already done
		if (! $this->isAlreadyImported($actionStatic->note)) {
			$res = (int) $actionStatic->create($userStatic);
		}

		return $res;
	}

	/**
	 * Checks if an event has already been imported, by searching for a specific tag in note field.
	 *
	 * @param string $noteString Tag/note to search (e.g., 'ORDER:...:...')
	 * @return int|false Rowid if exists, false if not, -1 if error
	 */
	private function isAlreadyImported(string $noteString) : bool
	{
		$sql = "SELECT id FROM " . $this->db->prefix() . "actioncomm WHERE note='" . $this->db->escape($noteString) . "'";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$this->db->free($resql); // Toujours libérer le curseur après usage
			return ($num > 0);
		}
		return false;
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

		$mailbox = getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER');
		$username = getDolGlobalString('DOLISTOREXTRACT_IMAP_USER');
		$password = getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD');
		$encryption = Imap::ENCRYPT_SSL;

		// Open connection
		try {
			$imap = new Imap($mailbox, $username, $password, $encryption);
		} catch (ImapClientException $error) {
			$this->errors[] = $error->getMessage() . PHP_EOL;
			return -1;
		}

		// Select the folder Inbox
		$imap->selectFolder(getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER') ? getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER') : 'INBOX');

		// Fetch all the messages in the current folder
		$emails = $imap->getMessages();
		$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreMailsToProcess", count($emails)) . '</strong>';

		if (getDolGlobalString('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')) {
			$this->logOutput .= '<br/><strong class="error">' . $langs->trans("DolistoreMailSendDisabled") . '</strong>';
		}

		// Filter only unread Dolistore emails
		$dolistoreEmails = [];
		foreach ($emails as $email) {
			if (strpos($email->header->subject, 'DoliStore') !== false && !$email->header->seen) {
				$dolistoreEmails[] = $email;
			}
		}

		if (empty($dolistoreEmails)) {
			$this->logOutput .= '<br/>' . $langs->trans("DolistoreNoUnreadMailFound");
			return 0;
		}

		$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreEmailsToProcessCount", count($dolistoreEmails)) . '</strong>';

		// Process all emails at once
		$result = $this->launchImportProcess($dolistoreEmails);

		// Process results by order reference
		if (is_array($result)) {
			// Organize emails by order reference
			$emailsByOrderRef = [];
			foreach ($dolistoreEmails as $email) {
				$dolistoreMailExtract = new \dolistoreMailExtract($this->db, $email->message->text);
				$data = $dolistoreMailExtract->extractAllDatas();

				if (!empty($data) && !empty($data['order_ref'])) {
					$orderRef = $data['order_ref'];
					if (!isset($emailsByOrderRef[$orderRef])) {
						$emailsByOrderRef[$orderRef] = [];
					}
					$emailsByOrderRef[$orderRef][] = $email;
				} else {
					// Email not associated with any valid order, move to error folder
					if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR')) {
						$moveResult = $imap->moveMessage($email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR'));
						if (!$moveResult) {
							$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR'));
						}
					}
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
						foreach ($emailsByOrderRef[$orderRef] as $email) {
							$imap->setSeenMessage($email->header->msgno, true);
							if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE')) {
								$moveResult = $imap->moveMessage($email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
								if (!$moveResult) {
									$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
								}
							}
						}
					} else {
						// Order processing failed, move all related emails to error folder
						$errorCount++;
						foreach ($emailsByOrderRef[$orderRef] as $email) {
							if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR')) {
								$moveResult = $imap->moveMessage($email->header->uid, getDolGlobalString('DOLISTOREEXTRACT_IMAP_FOLDER_ERROR'));
								if (!$moveResult) {
									$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR'));
								}
							}
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
				foreach ($dolistoreEmails as $email) {
					if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR')) {
						$moveResult = $imap->moveMessage($email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR'));
						if (!$moveResult) {
							$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR'));
						}
					}
				}
				return $result;
			} else {
				// Mark all emails as read and archive them
				foreach ($dolistoreEmails as $email) {
					$imap->setSeenMessage($email->header->msgno, true);
					if (getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE')) {
						$moveResult = $imap->moveMessage($email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
						if (!$moveResult) {
							$this->logOutput .= '<br/>' . $langs->trans("DolistoreErrorMovingMessage", $email->header->uid, getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE'));
						}
					}
				}
				return $result;
			}
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
		$user->fetch(getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS'));

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

		$this->db->begin();

		$existingOrderId = $this->isOrderAlreadyImported($orderRef);
		if ($existingOrderId > 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/><span class="warning">' . $langs->trans("DolistoreOrderAlreadyImportedSkip", $orderRef, $existingOrderId) . '</span>';
			dol_syslog(__METHOD__ . ' skip import for already imported order_ref=' . $orderRef . ' existing_order_id=' . $existingOrderId, LOG_WARNING);
			return true;
		}

		// A. Customer Management
		$companyId = $this->getOrCreateCustomer($user, $orderDetails['buyer_data']);

		if ($companyId <= 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreCustomerMgmtFailed").'</span>';
			$this->nbErrors++;
			return false;
		}

		// B. Product Management (Sales & Events)
		$processedItems = $this->processOrderItems($user, $companyId, $orderRef, $orderDetails['items']); // Add $orderRef

		if ($processedItems === null) {
			$this->db->rollback(); // Technical error during insertion
			$this->nbErrors++;
			return false;
		}

		// C. Email dispatch (only if database successful)
		if (!empty($processedItems)) {
			$this->sendThankYouEmail($user, $orderDetails, $processedItems);
		}

		// TOTAL SUCCESS
		$this->db->commit();
		// D. Feedback Log intelligent
		if (empty($processedItems)) {
			// Case of complete duplicate: The order has been processed, but nothing has been inserted.
			$this->logOutput .= '<br/><span class="warning">'.$langs->trans("DolistoreOrderAlreadyExists", $orderRef).'</span>';
		} else {
			// Normal case: Sales have been entered.
			$this->logOutput .= '<br/><span class="ok">'.$langs->trans("DolistoreOrderImported", $orderRef).'</span>';
		}
		return true;
	}
	/**
	 * Adds a Dolibarr webmodule sale to the database, based on extracted product data and customer.
	 *
	 * @param array $TItemDatas Array containing item data (reference, name, price, quantity, etc.)
	 * @param int   $socid      Customer rowid
	 * @return int  ID of the created sale, or <=0 if failed
	 */
	public function addWebmoduleSales(array $TItemDatas, int $socid): int
	{
		global $user, $error;

		// Include the Webmodulesales class
		dol_include_once('/webhost/class/webmodulesales.class.php');

		// Instantiate a new Webmodulesales object
		$webSales = new Webmodulesales($this->db);

		// Get the web module ID based on the Dolistore ID
		$fk_webmodule = $this->getWebmoduleIdByDolistoreId($TItemDatas['item_reference'] ?? '');

		// Check if a corresponding web module was found
		if ($fk_webmodule > 0) {
			// Convert the price to float and assign the data to the $webSales object
			$webSales->amount = $this->convertToFloat($TItemDatas['item_price_total'] ?? 0);
			$webSales->qty = $TItemDatas['item_quantity'] ?? 1;  // Default value if not specified
			$webSales->fk_soc = $socid;
			$webSales->import_key = date('Ymd');  // Generate import key with current date
			$webSales->fk_webmodule = $fk_webmodule;
			$webSales->date_sale = $TItemDatas['date_sale'];  // Current date for the sale
			$webSales->status = !empty($TItemDatas['item_refunded']) ? WebModuleSales::STATUS_REFUNDED : Webmodulesales::STATUS_SOLD;

			// Create the sale and check the result
			$res = $webSales->create($user);

			if ($res <= 0) {
				// If creation fails, log the error and add it to the error array
				$this->logError('Unable to create web sale: ' . $webSales->error . ' ' . implode(' - ', $webSales->errors));
			}

			// Return the ID of the created sale if successful
			return $res;
		}
		// If no web module is found, log the error and add it to the error array
		$this->logError('No web module found for fk_dolistore=' . ($TItemDatas['item_reference'] . ' ' .  $TItemDatas['item_name']));


		// Return 0 if no web module was found
		return 0;
	}
	/**
	 * Search a category linked to a Dolistore product reference.
	 *
	 * Kept for backward compatibility with mails.php.
	 *
	 * @param string $productReference Dolistore product reference
	 * @return int                     Category id if found, 0 otherwise
	 */
	public function searchCategoryDolistore(string $productReference): int
	{
		if (empty($productReference)) {
			return 0;
		}

		$category = new Categorie($this->db);
		$res = $category->fetch('', $productReference);
		if ($res > 0) {
			return (int) $category->id;
		}

		return 0;
	}

	/**
	 * Retrieves the webmodule rowid from Dolistore ID (using extrafields linkage).
	 *
	 * @param string $fk_dolistore Dolistore product reference
	 * @return int                 Webmodule rowid, or 0 if not found
	 */
	public function getWebmoduleIdByDolistoreId(string $fk_dolistore): int
	{
		// Build SQL query to get the web module ID
		$sql = 'SELECT DISTINCT w.rowid';
		$sql .= ' FROM ' . $this->db->prefix() . 'webmodule as w';
		$sql .= ' INNER JOIN ' . $this->db->prefix() . 'webmodule_version wv ON w.rowid = wv.fk_webmodule';
		$sql .= ' INNER JOIN ' . $this->db->prefix() . 'webmodule_version_extrafields wve ON wv.rowid = wve.fk_object';
		$sql .= ' WHERE wve.iddolistore = "' . $this->db->escape($fk_dolistore) . '"';

		// Execute the query
		$resql = $this->db->query($sql);

		// Check if the query failed
		if (! $resql) {
			return 0;  // Return 0 if no result was found
		}

		// Extract the result
		$obj = $this->db->fetch_object($resql);

		// Return the web module ID or 0 if not found
		return $obj->rowid ?? 0;
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

		$serviceId = $this->findServiceIdByField('iddolistore', $fk_dolistore);
		if ($serviceId > 0) {
			dol_syslog(__METHOD__ . ' service found by extrafield iddolistore=' . $fk_dolistore . ' => ' . $serviceId, LOG_DEBUG);
			return $serviceId;
		}

		dol_syslog(__METHOD__ . ' no service found by extrafield iddolistore=' . $fk_dolistore . ', fallback on ref', LOG_DEBUG);

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

		$sql = 'SELECT p.rowid, p.ref, p.label, pe.iddolistore,';
		$sql .= ' (';
		if ($searchOnRef) {
			$sql .= ' (CASE WHEN p.ref = "' . $refEscaped . '" THEN 100 ELSE 0 END)';
			$sql .= ' + (CASE WHEN p.ref LIKE "' . $refEscaped . '%" THEN 60 ELSE 0 END)';
			$sql .= ' + (CASE WHEN p.ref LIKE "%' . $refEscaped . '%" THEN 40 ELSE 0 END)';
			$sql .= ' + (CASE WHEN pe.iddolistore = "' . $refEscaped . '" THEN 30 ELSE 0 END)';
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
		$sql .= ' LEFT JOIN ' . $this->db->prefix() . 'product_extrafields as pe ON pe.fk_object = p.rowid';
		$sql .= ' WHERE p.fk_product_type = ' . ((int) Product::TYPE_SERVICE);
		$sql .= ' AND p.entity IN (' . getEntity('product') . ')';
		$sql .= ' AND (';
		$conditions = array();
		if ($searchOnRef) {
			$conditions[] = 'p.ref = "' . $refEscaped . '"';
			$conditions[] = 'p.ref LIKE "' . $refEscaped . '%"';
			$conditions[] = 'p.ref LIKE "%' . $refEscaped . '%"';
			$conditions[] = 'pe.iddolistore = "' . $refEscaped . '"';
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

		if (empty($user->rights->produit->creer)) {
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

		if (empty($user->rights->produit->creer)) {
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
	 * Manually creates a native Dolibarr customer order from Dolistore extracted data.
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

		$result = array(
			'success' => false,
			'code' => 'error',
			'order_id' => 0,
			'order_ref' => '',
			'message_key' => 'DolistoreOrderManualCreateError'
		);

		if (empty($user->rights->commande->creer)) {
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreOrderManualCreateError", '', '') . '</span>';
			dol_syslog(__METHOD__ . ' permission denied for user=' . ((int) $user->id), LOG_WARNING);
			return $result;
		}

		$orderRefClient = !empty($orderData['order_ref']) ? (string) $orderData['order_ref'] : '';
		$dateOrder = !empty($orderData['date_order']) ? (int) $orderData['date_order'] : dol_now();

		$existingOrderId = $this->isOrderAlreadyImported($orderRefClient);
		if ($existingOrderId > 0) {
			$existingOrder = new Commande($this->db);
			$existingOrder->fetch($existingOrderId);
			$result['success'] = true;
			$result['code'] = 'already_exists';
			$result['order_id'] = (int) $existingOrderId;
			$result['order_ref'] = (string) $existingOrder->ref;
			$result['message_key'] = 'DolistoreOrderManualAlreadyExists';
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreOrderManualAlreadyExists", dol_escape_htmltag($orderRefClient), dol_escape_htmltag($existingOrder->ref)) . '</span>';
			return $result;
		}

		$order = new Commande($this->db);
		$order->socid = (int) $socid;
		$order->date = $dateOrder;
		$order->ref_client = $orderRefClient;
		$order->note_private = $this->buildDolistoreImportPrivateNote($orderData);

		$this->db->begin();
		$orderCreateResult = $order->create($user);
		if ($orderCreateResult <= 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreOrderManualCreateError", dol_escape_htmltag($orderRefClient), dol_escape_htmltag($order->error)) . '</span>';
			dol_syslog(__METHOD__ . ' failed to create order for socid=' . ((int) $socid) . ' ref_client=' . $orderRefClient . ' error=' . $order->error, LOG_ERR);
			return $result;
		}

		$lineResult = $this->createCustomerOrderLinesFromDolistoreItems($order, $items);
		if ($lineResult < 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">' . $langs->trans("DolistoreOrderManualCreateError", dol_escape_htmltag($orderRefClient), dol_escape_htmltag($order->error)) . '</span>';
			dol_syslog(__METHOD__ . ' failed to create lines on order_id=' . ((int) $order->id) . ' error=' . $order->error, LOG_ERR);
			return $result;
		}

		$this->db->commit();

		$result['success'] = true;
		$result['code'] = 'created';
		$result['order_id'] = (int) $order->id;
		$result['order_ref'] = (string) $order->ref;
		$result['message_key'] = 'DolistoreOrderManualCreated';

		$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreOrderManualCreated", dol_escape_htmltag($orderRefClient), dol_escape_htmltag($order->ref)) . '</span>';
		dol_syslog(__METHOD__ . ' order created order_id=' . ((int) $order->id) . ' ref=' . $order->ref . ' ref_client=' . $orderRefClient, LOG_INFO);

		return $result;
	}

	/**
	 * Checks if a customer order was already imported using Dolistore reference in ref_client.
	 *
	 * @param string $dolistoreRef Dolistore order reference
	 * @return int                 Existing order id, or 0 if none
	 */
	public function isOrderAlreadyImported(string $dolistoreRef): int
	{
		$dolistoreRef = trim($dolistoreRef);
		if ($dolistoreRef === '') {
			return 0;
		}

		$sql = 'SELECT c.rowid';
		$sql .= ' FROM ' . $this->db->prefix() . 'commande as c';
		$sql .= ' WHERE c.ref_client = "' . $this->db->escape($dolistoreRef) . '"';
		$sql .= ' AND c.entity IN (' . getEntity('commande') . ')';
		$sql .= ' ORDER BY c.rowid DESC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (! $resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!empty($obj->rowid)) {
			dol_syslog(__METHOD__ . ' duplicate order detected for ref_client=' . $dolistoreRef . ' order_id=' . ((int) $obj->rowid), LOG_WARNING);
			return (int) $obj->rowid;
		}

		return 0;
	}

	/**
	 * Creates native customer order lines from Dolistore extracted items.
	 *
	 * @param Commande $order Customer order object
	 * @param array    $items Extracted items
	 * @return int            Number of created lines, -1 on error
	 */
	private function createCustomerOrderLinesFromDolistoreItems(Commande $order, array $items): int
	{
		global $langs;

		$createdLines = 0;
		foreach ($items as $item) {
			$itemReference = (string) ($item['item_reference'] ?? '');
			$itemQty = !empty($item['item_quantity']) ? (float) $item['item_quantity'] : 1;
			$itemQty = abs($itemQty) > 0 ? abs($itemQty) : 1;
			$itemTotal = !empty($item['item_price_total']) ? $this->convertToFloat((string) $item['item_price_total']) : $this->convertToFloat((string) ($item['item_price'] ?? '0'));
			$itemUnitPrice = $itemTotal;
			if (!empty($item['item_price_total']) && $itemQty > 0) {
				$itemUnitPrice = $itemTotal / $itemQty;
			}
			$isRefunded = !empty($item['item_refunded']);
			if ($isRefunded) {
				$itemUnitPrice = -1 * abs($itemUnitPrice);
				dol_syslog(__METHOD__ . ' refund line normalized for item_reference=' . $itemReference . ' qty=' . $itemQty . ' unit_price=' . $itemUnitPrice, LOG_INFO);
			}
			$serviceId = $this->getServiceIdByDolistoreId($itemReference);
			$itemDesc = $this->buildDolistoreOrderLineDescription($item, $isRefunded);

			$lineId = $order->addline($itemDesc, $itemUnitPrice, $itemQty, 0, 0, 0, $serviceId, 0, 'HT', 0, '', '', self::DOLISTORE_PRODUCT_TYPE_SERVICE);
			if ($lineId <= 0) {
				dol_syslog(__METHOD__ . ' failed addline for order_id=' . ((int) $order->id) . ' item_reference=' . $itemReference . ' error=' . $order->error, LOG_ERR);
				return -1;
			}

			if (class_exists('OrderLine')) {
				$orderLine = new OrderLine($this->db);
				if ($orderLine->fetch($lineId) > 0) {
					$orderLine->array_options['options_dolistore_item_ref'] = $itemReference;
					$lineExtraResult = $orderLine->insertExtraFields();
					if ($lineExtraResult < 0) {
						dol_syslog(__METHOD__ . ' failed set line extrafield on line_id=' . ((int) $lineId), LOG_ERR);
						return -1;
					}
				}
			}

			dol_syslog(__METHOD__ . ' created order line line_id=' . ((int) $lineId) . ' service_id=' . ((int) $serviceId) . ' dolistore_item_ref=' . $itemReference, LOG_INFO);
			$createdLines++;
		}

		return $createdLines;
	}

	/**
	 * Builds customer order line description from Dolistore item data.
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

		$allowedFields = array(
			'iddolistore' => 'pe.iddolistore',
			'ref' => 'p.ref'
		);

		if (!isset($allowedFields[$fieldName])) {
			return 0;
		}

		$sql = 'SELECT p.rowid';
		$sql .= ' FROM ' . $this->db->prefix() . 'product as p';
		$sql .= ' INNER JOIN ' . $this->db->prefix() . 'product_extrafields as pe ON pe.fk_object = p.rowid';
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
				$resql = $this->db->query($sql);
				if ($resql) {
					$obj = $this->db->fetch_object($resql);
					if ($obj && $obj->rowid > 0) {
						$companyId = (int) $obj->rowid;
						$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreCompanyFoundBySiret", $companyId) . '</span>';
					}
				}
			}
		}

		// 4. Search via Contact Email (if still not found)
		if (!$companyId) {
			$contact = new \Contact($this->db);
			$fetchResult = $contact->fetch('', '', '', trim($buyerData['buyer_email']));

			if ($fetchResult > 0) {
				if ($fetchResult > 1) { // Multiple contacts with this address
					$q = 'SELECT s.rowid
                          FROM ' . $this->db->prefix() . 'societe s
                          INNER JOIN ' . $this->db->prefix() . 'socpeople sp ON (sp.fk_soc = s.rowid)
                          WHERE sp.email = "' . $this->db->escape($buyerData['buyer_email']) . '"
                          ORDER BY s.rowid ASC
                          LIMIT 1';
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
			$sql = "SELECT rowid FROM " . $this->db->prefix() . "socpeople
                    WHERE fk_soc = " . $companyId . "
                    AND email = '" . $this->db->escape($buyerData['buyer_email']) . "'";
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
	 * Check if a sale already exists to prevent duplicates.
	 *
	 * @param int    $socid        Customer rowid
	 * @param string $dolistoreRef Module reference (e.g., "module_x")
	 * @param int    $dateSale     Sale timestamp
	 * @return bool                True if it already exists, false otherwise
	 */
	private function checkIfWebmoduleSaleExists(int $socid, string $dolistoreRef, int $dateSale): bool
	{
		// 1. Retrieve the technical ID of the web module (rowid in llx_webmodule)
		$fk_webmodule = $this->getWebmoduleIdByDolistoreId($dolistoreRef);

		if (!$fk_webmodule) {
			return false; // Unknown module, therefore no duplicate possible in database
		}

		// 2. We are looking for a matching sale
		$sql = "SELECT rowid FROM " . $this->db->prefix() . "webmodule_sales";
		$sql .= " WHERE fk_soc = " . ((int) $socid);
		$sql .= " AND fk_webmodule = " . ((int) $fk_webmodule);

		// 3. Date verification (Duplicate prevention)
		$dayStart = date('Y-m-d 00:00:00', $dateSale);
		$dayEnd   = date('Y-m-d 23:59:59', $dateSale);

		$sql .= " AND date_sale >= '" . $dayStart . "'";
		$sql .= " AND date_sale <= '" . $dayEnd . "'";

		$res = $this->db->query($sql);

		if ($res && $this->db->num_rows($res) > 0) {
			return true;
		}
		return false;
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
			} else {
				$mappedItems[] = $product;
				$successList[] = $product['item_name'];
			}

			$this->createEventFromExtractDatas($product, $orderRef, $companyId); // Ref passed empty or to be adapted
		}

		if (empty($mappedItems)) {
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreNoServiceCandidate") . '</span>';
			return array();
		}

		$orderMetadata = array(
			'order_ref' => $orderRef,
			'date_order' => !empty($mappedItems[0]['date_sale']) ? (int) $mappedItems[0]['date_sale'] : dol_now()
		);
		$resOrder = $this->createCustomerOrderFromDolistoreData($user, $companyId, $orderMetadata, $mappedItems);
		if (empty($resOrder['success'])) {
			return null;
		}

		if (!empty($resOrder['code']) && $resOrder['code'] === 'already_exists') {
			$this->logOutput .= '<br/>-> <span class="warning">' . $langs->trans("DolistoreOrderAlreadyExists", $orderRef) . '</span>';
		}

		return $successList;
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
	 * Sends a thank you email to the customer using a template based on their language.
	 * Checks configuration to ensure sending is enabled.
	 *
	 * @param User  $user         Dolibarr user object.
	 * @param array  $orderDetails Array containing buyer data and language.
	 * @param array  $productList  List of valid product names to include in the email.
	 * @return void
	 */
	private function sendThankYouEmail(User $user, array $orderDetails, array $productList): void
	{
		global $langs;
		// 1. Check configuration
		if (getDolGlobalString('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')) {
			// Logically skipped
			return;
		}

		// 2. Prepare dependencies
		require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
		require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';

		$formMail = new \FormMail($this->db);
		// We use the helper class to format data (names, etc.) cleanly
		$dolistoreMail = new \dolistoreMail();
		$dolistoreMail->setDatas($orderDetails['buyer_data']);

		$from = getDolGlobalString('MAIN_INFO_SOCIETE_NOM') . ' <dolistore@atm-consulting.fr>';
		$sendTo = $dolistoreMail->buyer_email;

		// 3. Select Template (EN or FR)
		$templateId = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_EN');
		if (preg_match('/fr.*/', $orderDetails['lang'])) {
			$templateId = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_FR');
		}

		$usedTemplate = $formMail->getEMailTemplate($this->db, 'dolistore_extract', $user, $langs, $templateId);

		// 4. Substitutions
		$productListString = implode(', ', $productList);
		$arraySubstitution = [
			'__DOLISTORE_ORDER_NAME__'        => $dolistoreMail->order_name,
			'__DOLISTORE_INVOICE_FIRSTNAME__' => $dolistoreMail->buyer_firstname,
			'__DOLISTORE_INVOICE_COMPANY__'   => $dolistoreMail->buyer_company,
			'__DOLISTORE_INVOICE_LASTNAME__'  => $dolistoreMail->buyer_lastname,
			'__DOLISTORE_LIST_PRODUCTS__'     => $productListString
		];

		$subject = make_substitutions($usedTemplate->topic, $arraySubstitution);
		$message = make_substitutions($usedTemplate->content, $arraySubstitution);

		// 5. Send Email
		$emailFile = new \CMailFile($subject, $sendTo, $from, $message, [], [], [], '', '', 0, -1);

		if ($emailFile->error) {
			dol_syslog(__METHOD__ . ' Error creating email: ' . $emailFile->error, LOG_ERR);
			$this->logOutput .= '<br/><span class="error">' . $langs->trans("DolistoreEmailCreationError", dol_escape_htmltag($emailFile->error)) . '</span>';
		} else {
			if ($emailFile->sendfile()) {
				$this->logOutput .= '<br/><span class="ok">' . $langs->trans("DolistoreEmailSentTo", dol_escape_htmltag($sendTo)) . '</span>';
			} else {
				$this->logOutput .= '<br/><span class="error">' . $langs->trans("DolistoreEmailSentFailed", dol_escape_htmltag($sendTo)) . '</span>';
			}
		}
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
			// Only mails from Dolistore and not seen
			if (strpos($email->header->subject, 'DoliStore') !== false && !$email->header->seen) {
				$this->logOutput .= '<br/><strong>' . $langs->trans("DolistoreProcessingEmail", dol_escape_htmltag($email->header->subject)) . '</strong>';

				// Data extraction
				$dolistoreMailExtract = new \dolistoreMailExtract($this->db, $email->message->text);
				$emailLanguage = $dolistoreMailExtract->detectLang($email->header->subject);
				$data = $dolistoreMailExtract->extractAllDatas();

				// Validation
				if (!empty($data) && !empty($data['order_ref']) && !empty($data['buyer_email'])) {
					$orderRef = $data['order_ref'];
					$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreDataExtracted", '<b>' . dol_escape_htmltag($orderRef) . '</b>') . '</span>';

					// Initialize the order if it doesn't exist yet
					if (!isset($orderData[$orderRef])) {
						$orderData[$orderRef] = [
							'buyer_data' => $data,
							'items' => [],
							'lang' => $emailLanguage
						];
					}

					// Loop through each item in the order
					if (!empty($data['items']) && is_array($data['items'])) {
						foreach ($data['items'] as $item) {
							if (!empty($item['item_reference']) && !empty($item['item_name'])) {
								$dateRaw = $email->header->date;
								$dateSale = strtotime($dateRaw); // IMPORTANT: Date conversion for database and duplicate check

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
		return $orderData;
	}
}
