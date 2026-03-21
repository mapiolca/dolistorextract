<?php
/* Copyright (C) 2017      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       mails.php
 *		\ingroup    dolistorextract
 *		\brief      Show dolistore email
 */

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
//if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
//if (! defined("NOLOGIN"))        define("NOLOGIN",'1');				// If this page is public (can be called outside logged session)

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';					// to work if your module directory is into dolibarr root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../dolibarr/htdocs/main.inc.php")) $res=@include '../../../dolibarr/htdocs/main.inc.php';     // Used on dev env only
if (! $res && file_exists("../../../../dolibarr/htdocs/main.inc.php")) $res=@include '../../../../dolibarr/htdocs/main.inc.php';   // Used on dev env only
if (! $res) die("Include of main fails");
// Change this following line to use the correct relative path from htdocs
include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php');
include_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');
include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php');
include_once(DOL_DOCUMENT_ROOT.'/product/class/product.class.php');

include_once 'class/actions_dolistorextract.class.php';
include_once 'class/dolistoreMail.class.php';
include_once 'class/dolistoreMailExtract.class.php';

dol_include_once("/dolistorextract/include/ssilence/php-imap-client/autoload.php");

use SSilence\ImapClient\ImapClientException;
use SSilence\ImapClient\ImapConnect;
use SSilence\ImapClient\ImapClient as Imap;

// Load traductions files requiredby by page
$langs->load("dolistorextract@dolistorextract");
$langs->load("other");

// Get parameters
$id			= GETPOST('id', 'int');
$action		= GETPOST('action','alpha');
$cancel     = GETPOST('cancel');
$view       = GETPOST('view');
$nativeImportLog = '';



if (empty($action) && empty($id) && empty($ref)) $action='view';

// Protection if external user
if ($user->societe_id > 0 || ! $user->hasRight('dolistorextract', 'read'))
{
	accessforbidden();
}


$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
//$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals

// Initialize technical object to manage hooks of modules. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('dolistoremail'));



/*******************************************************************
* ACTIONS
*
* Put here all code to do according to value of "action" parameter
********************************************************************/

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	if (in_array($action, array('manual_create_service', 'manual_link_service'))) {
		$dolistorextractActions = new \ActionsDolistorextract($db);
		$itemReference = GETPOST('item_reference', 'alphanohtml');
		$itemName = GETPOST('item_name', 'restricthtml');
		$emailId = GETPOST('id', 'int');

		if ($action === 'manual_create_service') {
			$proposedRef = GETPOST('proposed_ref', 'alphanohtml');
			$resCreate = $dolistorextractActions->createServiceFromDolistoreData($user, $itemReference, $itemName, $proposedRef);
			setEventMessages($langs->trans($resCreate['message_key']), null, $resCreate['success'] ? 'mesgs' : 'errors');
		}

		if ($action === 'manual_link_service') {
			$serviceId = GETPOST('target_service_id', 'int');
			$resLink = $dolistorextractActions->associateDolistoreItemToExistingService($user, $itemReference, $itemName, $serviceId);
			setEventMessages($langs->trans($resLink['message_key']), null, $resLink['success'] ? 'mesgs' : 'errors');
		}

		header('Location: ' . $_SERVER['PHP_SELF'] . '?action=read&id=' . ((int) $emailId));
		exit;
	}
}




/***************************************************
* VIEW
*
* Put here all code to build page
****************************************************/

llxHeader('', $langs->trans('DolistoreMailsList'),'');

$form=new Form($db);

$mailbox = getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER');
$username = getDolGlobalString('DOLISTOREXTRACT_IMAP_USER');
$password = getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD');
$encryption = Imap::ENCRYPT_SSL;

// Open connection
try{
	$imap = new Imap($mailbox, $username, $password, $encryption);
	// You can also check out example-connect.php for more connection options

}catch (ImapClientException $error){
	echo $error->getMessage().PHP_EOL;
	die(); // Oh no :( we failed
}

// Select the folder Inbox
$imap->selectFolder('INBOX');

/*
 * Import des données du message
 */
if ($action == 'import' || $action == 'importnative') {
	$email = $imap->getMessage((int) $id);

	$dolistorextractActions = new \ActionsDolistorextract($db);
	$res = $dolistorextractActions->launchImportProcess(array($email));
	$nativeImportLog = $dolistorextractActions->logOutput;
	setEventMessages($langs->trans("DolistoreNativeImportDone"), null, 'mesgs');
	$action = 'read';
}

/*
 * Display selected message
 */
if ($action == 'read') {
	print load_fiche_titre($langs->trans('DolistoreMailShow'));

	$email = $imap->getMessage((int) $id);

	if ($view == 'plain') {
		print '<pre>';
		print $email->message->plain;
		print '</pre>';
	}
	if ($view == 'html') {
		print $email->message->html;
	}
	$socStatic = new Societe($db);
	$formMail = new FormMail($db);


	$dolistoreMailExtract = new \dolistoreMailExtract($db, $email->message->html);
	$datas = $dolistoreMailExtract->extractAllDatas();
	$langEmail = $dolistoreMailExtract->detectLang($email->header->subject);

	$dolistoreMail = new \dolistoreMail();
	$dolistorextractActions = new \ActionsDolistorextract($db);

	$dolistoreMail->setDatas($datas);

	// Search exactly by name
	$filterSearch = array();

	$invoiceCompany = !empty($datas['invoice_company']) ? $datas['invoice_company'] : (isset($datas['buyer_company']) ? $datas['buyer_company'] : '');
	$searchSoc = $socStatic->fetch('', $invoiceCompany);  // Retourne -2 si on trouve plusieurs Tiers

	if($searchSoc < 0) {
		print "Erreur recherche client";

	} else {
		print 'Client trouvé : '.$socStatic->getNomUrl(1).'<br />';
	}
	$listProduct = array();
	$canManageServices = !empty($user->rights->produit->creer);
	// Service mapping management
	foreach ($dolistoreMail->items as $product) {
	    // Save list of products for email message
	    $listProduct[] = $product['item_name'];

		$mappedServiceId = $dolistorextractActions->getServiceIdByDolistoreId((string) $product['item_reference']);
		echo '<div class="div-table-responsive-no-min" style="margin-top:10px;">';
		echo '<table class="noborder" width="100%">';
		echo '<tr class="liste_titre"><th colspan="2">' . $langs->trans("DolistoreManualMappingTitle", dol_escape_htmltag($product['item_reference'])) . '</th></tr>';

		if ($mappedServiceId > 0) {
			$mappedService = new Product($db);
			$mappedService->fetch($mappedServiceId);
			echo '<tr><td width="30%"><strong>' . $langs->trans("DolistoreServiceMappedLabel") . '</strong></td><td>' . $mappedService->getNomUrl(1) . '</td></tr>';
		} else {
			$candidates = $dolistorextractActions->findServiceCandidatesFromDolistoreData((string) $product['item_reference'], (string) $product['item_name']);
			$proposal = $dolistorextractActions->buildServiceMappingProposal((string) $product['item_reference'], (string) $product['item_name'], $candidates);

			echo '<tr><td colspan="2"><span class="warning">' . $langs->trans("DolistoreNoExactServiceMapping", dol_escape_htmltag($proposal['dolistore_ref']), dol_escape_htmltag($proposal['dolistore_label'])) . '</span></td></tr>';

			if (!empty($proposal['candidates'])) {
				echo '<tr><td><strong>' . $langs->trans("DolistoreCandidateServices") . '</strong></td><td><ul>';
				foreach ($proposal['candidates'] as $candidate) {
					echo '<li>' . dol_escape_htmltag($candidate['ref']) . ' - ' . dol_escape_htmltag($candidate['label']) . '</li>';
				}
				echo '</ul></td></tr>';
			} else {
				echo '<tr><td><strong>' . $langs->trans("DolistoreCandidateServices") . '</strong></td><td>' . $langs->trans("DolistoreNoServiceCandidate") . '</td></tr>';
			}

			if ($canManageServices) {
				echo '<tr><td><strong>' . $langs->trans("DolistoreActionCreateService") . '</strong></td><td>';
				echo '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
				echo '<input type="hidden" name="token" value="' . newToken() . '">';
				echo '<input type="hidden" name="action" value="manual_create_service">';
				echo '<input type="hidden" name="id" value="' . ((int) $id) . '">';
				echo '<input type="hidden" name="item_reference" value="' . dol_escape_htmltag((string) $proposal['dolistore_ref']) . '">';
				echo '<input type="hidden" name="item_name" value="' . dol_escape_htmltag((string) $proposal['dolistore_label']) . '">';
				echo '<input type="text" name="proposed_ref" value="' . dol_escape_htmltag((string) $proposal['dolistore_ref']) . '" placeholder="' . dol_escape_htmltag($langs->trans("DolistoreServiceRefProposal")) . '"> ';
				echo '<button class="button" type="submit">' . $langs->trans("DolistoreActionCreateService") . '</button>';
				echo '</form>';
				echo '</td></tr>';

				echo '<tr><td><strong>' . $langs->trans("DolistoreActionLinkService") . '</strong></td><td>';
				echo '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
				echo '<input type="hidden" name="token" value="' . newToken() . '">';
				echo '<input type="hidden" name="action" value="manual_link_service">';
				echo '<input type="hidden" name="id" value="' . ((int) $id) . '">';
				echo '<input type="hidden" name="item_reference" value="' . dol_escape_htmltag((string) $proposal['dolistore_ref']) . '">';
				echo '<input type="hidden" name="item_name" value="' . dol_escape_htmltag((string) $proposal['dolistore_label']) . '">';
				echo '<select name="target_service_id">';
				echo '<option value="">' . $langs->trans("DolistoreSelectExistingService") . '</option>';
				foreach ($proposal['candidates'] as $candidate) {
					echo '<option value="' . ((int) $candidate['id']) . '">' . dol_escape_htmltag($candidate['ref']) . ' - ' . dol_escape_htmltag($candidate['label']) . '</option>';
				}
				echo '</select> ';
				echo '<button class="button" type="submit">' . $langs->trans("DolistoreActionLinkService") . '</button>';
				echo '</form>';
				echo '</td></tr>';
			}
		}
		echo '</table>';
		echo '</div>';
	}

	print '<br />';
	print 'Langue du mail : '.$langEmail;

	// EN template by default
	$idTemplate = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_EN');
	if(preg_match('/fr.*/', $langEmail)) {
		$idTemplate = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_FR');
	}
	$usedTemplate = $formMail->getEMailTemplate($db, 'dolistore_extract', $user, $langs, $idTemplate);
	$listProductString = implode(', ', $listProduct);
	$arraySubstitutionDolistore = [
			'__DOLISTORE_ORDER_NAME__' => $dolistoreMail->order_name,
			'__DOLISTORE_INVOICE_FIRSTNAME__' => $dolistoreMail->buyer_firstname,
			'__DOLISTORE_BUYER_COMPANY__' => $dolistoreMail->buyer_company,
			'__DOLISTORE_INVOICE_LASTNAME__' => $dolistoreMail->buyer_lastname,
	        '__DOLISTORE_LIST_PRODUCTS__' => $listProductString
	];

	$subject=make_substitutions($usedTemplate->topic, $arraySubstitutionDolistore);
	$message=make_substitutions($usedTemplate->content, $arraySubstitutionDolistore);
	print '<br />Sujet du mail : '.$subject;
	print '<br />Texte du mail : '.$message;

	print '<strong>Données extraites</strong><br/>';
	print '<pre>';

	var_dump($dolistoreMail);

	if (!empty($nativeImportLog)) {
		print '<div class="info">';
		print $nativeImportLog;
		print '</div>';
	}

	print '<div class="center">';
	// TODO: check if already imported
	print '<a class="button" href="'.$_SERVER['PHP_SELF'].'?action=importnative&id='.$id.'">'.$langs->trans("DolistoreManualNativeImport").'</a>';


	print '<a class="button" href="'.$_SERVER['PHP_SELF'].'">Fermer</a>';

	print '</div>';

}
if (!$id) {


print load_fiche_titre($langs->trans('DolistoreMailsList'));

// Count the messages in current folder
$overallMessages = $imap->countMessages();
$unreadMessages = $imap->countUnreadMessages();

print '<div class="info">'.$overallMessages.' messages / '. $unreadMessages.' non lus</div>';
// Fetch all the messages in the current folder
$emails = $imap->getMessages();

print '<table class="liste">';

print '<tr class="liste_titre">';
print '<th>Date</th>';
print '<th>ID</th>';
print '<th>Ref</th>';
print '<th>Lang</th>';
print '<th>Company</th>';
print '<th>Mail</th>';
print '<th>Contact</th>';
print '<th>Lu/Non Lu</th>';
print '<th>Actions</th>';
print '</tr>';

foreach($emails as $email) {

	$mailExtract = new \dolistoreMailExtract($db, $email->message->html);

	// Seulement les mails en provenance de dolistore
	if (strpos($email->header->subject, 'DoliStore') > 0) {

		$langEmail = dolistoreMailExtract::detectLang($email->header->subject);
		$datasCustomer = $mailExtract->extractOrderDatas();
		$datasOrder = dolistoreMailExtract::extractOrderDatasFromSubject($email->header->subject, $langEmail);

		print '<tr>';

		// Date
		print '<td>';
		print $email->header->date;
		print '</td>';

		// ID
		print '<td>';
		print $datasOrder['id'];
		print '</td>';

		// ref
		print '<td>';
		print $datasOrder['ref'];
		print '</td>';

		// Lang
		print '<td>';
		print picto_from_langcode($langEmail);
		print '</td>';

		// Company
		print '<td>';
		print $datasCustomer['buyer_company'];
		print '</td>';

		// Email
		print '<td>';
		print $datasCustomer['buyer_email'];
		print '</td>';

		// Contact name
		print '<td>';
		print $datasCustomer['buyer_lastname'].' '.$datasCustomer['buyer_firstname'];
		print '</td>';

		// Read / unread
		print '<td>';
		print $email->header->details->Unseen == "U" ? 'Non lu' : 'Lu';
		print '</td>';

		// Actions
		print '<td>';
		print '<a href="'.$_SERVER['PHP_SELF'].'?action=read&view=plain&id='.$email->header->msgno.'">Voir</a>';
		//print '<a href="'.$_SERVER['PHP_SELF'].'?action=read&view=html&id='.$email->header->uid.'">HTML</a>';
		print '</td>';

		print '</tr>';
	}

}
print '<table>';


}

// End of page
llxFooter();
$db->close();
