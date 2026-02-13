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
require_once __DIR__ . "/../include/ssilence/php-imap-client/autoload.php";
use SSilence\ImapClient\ImapClientException;
use SSilence\ImapClient\ImapConnect;
use SSilence\ImapClient\ImapClient as Imap;


/**
 * Class ActionsDolistorextract
 *
 * Provides hooks and main processing logic for the Dolistore Extract Dolibarr module.
 * Handles automated extraction of sales/orders from Dolistore emails and integration in Dolibarr (thirdparties, contacts, events, webmodule sales).
 */
class ActionsDolistorextract extends CommonHookActions
{
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
			$res = $socStatic->create_individual($user);
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

		// A. Customer Management
		$companyId = $this->getOrCreateCustomer($user, $orderDetails['buyer_data']);

		if ($companyId <= 0) {
			$this->db->rollback();
			$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreCustomerMgmtFailed").'</span>';
			$this->nbErrors++;
			return false;
		}

		// B. Product Management (Sales & Events)
		$processedItems = $this->processOrderItems($user, $companyId, $orderRef, $orderDetails['items']); // Ajout de $orderRef

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
		$company = new \Societe($this->db);
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
						$this->logOutput .= '<br/>-> <span class="ok">Company found by SIRET: <b>' . $companyId . '</b></span>';
					}
				}
			}
		}

		// 4. Search via Contact Email (si toujours pas trouvé)
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
	 * Vérifie si une vente existe déjà pour éviter les doublons
	 * @param int $socid ID du client
	 * @param string $dolistoreRef Référence du module (ex: "module_x")
	 * @param int $dateSale Timestamp de la vente
	 * @return bool True si existe déjà, False sinon
	 */
	private function checkIfWebmoduleSaleExists(int $socid, string $dolistoreRef, int $dateSale): bool
	{
		// 1. On récupère l'ID technique du module web (rowid dans llx_webmodule)
		$fk_webmodule = $this->getWebmoduleIdByDolistoreId($dolistoreRef);

		if (!$fk_webmodule) {
			return false; // Module inconnu, donc pas de doublon possible en base
		}

		// 2. On cherche une vente correspondante
		$sql = "SELECT rowid FROM " . $this->db->prefix() . "webmodule_sales";
		$sql .= " WHERE fk_soc = " . ((int) $socid);
		$sql .= " AND fk_webmodule = " . ((int) $fk_webmodule);

		// 3. Vérification de la date (Sécurité anti-doublon)
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

		foreach ($items as $product) {
			$itemCreatedInThisPass = false;

			// WebHost Creation and Sales
			if (isModEnabled("webhost")) {
				// CHECK DOUBLON
				if ($this->checkIfWebmoduleSaleExists($companyId, $product['item_reference'], $product['date_sale'])) {
					$this->logOutput .= '<br/>-> <span class="warning">'.$langs->trans("DolistoreDuplicateSale").' '.$product['item_name'] . '</span>';
					// This is not an error, we continue
				} else {
					$resVente = $this->addWebmoduleSales($product, $companyId);
					if ($resVente <= 0) {
						$this->logOutput .= '<br/>-> <span class="error">'.$langs->trans("DolistoreSaleCreationError").' '.$product['item_name'] . '</span>';
						return null;
					} else {
						$itemCreatedInThisPass = true;
					}
				}
			}
			$this->createEventFromExtractDatas($product, $orderRef, $companyId); // Ref passé vide ou à adapter
			if ($itemCreatedInThisPass) {
				$this->logOutput .= '<br/>-> <span class="ok">' . $langs->trans("DolistoreSaleCreated", dol_escape_htmltag($product['item_name'])) . '</span>';
			}

			if ($itemCreatedInThisPass) {
				$successList[] = $product['item_name'];
			}
		}


		return $successList;
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

		$usedTemplate = $formMail->getEMailTemplate($this->db, 'dolistore_extract', $user, '', $templateId);

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
								$dateSale = strtotime($dateRaw); // IMPORTANT: Conversion date pour BDD et check doublon

								// Add the product to the order structure
								$orderData[$orderRef]['items'][] = [
									'item_reference'   => $item['item_reference'],
									'item_name'        => $item['item_name'],
									'item_price'       => $item['item_price'],
									'item_quantity'    => $item['item_quantity'],
									'item_price_total' => $item['item_price_total'],
									'item_refunded'    => $item['item_refunded'] ?? null,
									'date_sale'        => $dateSale
								];

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
