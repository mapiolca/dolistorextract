<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2017  Jean-François Ferry <jfefe@aternatik.fr>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * \file    admin/setup.php
* \ingroup dolistorextract
* \brief   Dolistorextract module setup page.
*
* Define parameters for module
*/

// Dolibarr environment
$res = '';
if (file_exists("../../main.inc.php")) {
	$res = include "../../main.inc.php"; // From htdocs directory
} elseif (!$res && file_exists("../../../main.inc.php")) {
	$res = include "../../../main.inc.php"; // From "custom" directory
} else {
	die("Include of main fails");
}


// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once __DIR__ . '/dolistorextract.lib.php';
require_once __DIR__ . '/../class/dolistoreOrder.class.php';
require_once DOL_DOCUMENT_ROOT."/core/class/html.formmail.class.php";
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once("/dolistorextract/include/ssilence/php-imap-client/autoload.php");

use SSilence\ImapClient\ImapClientException;
use SSilence\ImapClient\ImapClient as Imap;

// Translations
$langs->load('admin');
$langs->load("dolistorextract@dolistorextract");

// Access control
if (empty($user->admin) && !dolistoreextractUserHasRight($user, 'setup', 'write')) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'aZ09');
$modulepart = GETPOST('modulepart', 'aZ09');
$label = GETPOST('label', 'alphanohtml');
$scandir = GETPOST('scan_dir', 'alphanohtml');
$mode = GETPOST('mode', 'aZ09');
if (!in_array($mode, array('settings', 'orders', 'billing', 'emailsimap'), true)) {
	$mode = 'settings';
}
$self = $_SERVER['PHP_SELF'];
$setupPageUrl = $self.'?mode='.urlencode($mode);
$type = 'dolistoreextract_order';
$error = 0;
if (in_array($action, array('update', 'add', 'setmod', 'set', 'del', 'setdoc', 'create_dolistore_order_category', 'create_dolistore_association_thirdparty'), true) && GETPOST('token', 'alphanohtml') === '') {
	accessforbidden('Invalid token');
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/**
 * Print one update row while keeping form tags inside table cells.
 *
 * @param string $rowClass Row class
 * @param string $label Label
 * @param string $constname Constant name
 * @param string $fieldHtml Field html
 * @param string $actionUrl Form action URL
 * @param string $mode Active setup tab
 * @param string $token CSRF token
 * @param string $extraActionHtml Optional extra html in action cell
 * @return void
 */
function dolistorextractPrintUpdateRow($rowClass, $label, $constname, $fieldHtml, $actionUrl, $mode, $token, $extraActionHtml = '')
{
	global $langs;
	static $lineid = 0;

	$lineid++;
	$formid = 'dolistorextractsetupform'.$lineid;
	$fieldHtml = preg_replace('/<select\b/i', '<select data-dolistorextract-select2="1" ', $fieldHtml);

	print '<tr '.$rowClass.'><td>'.$label.'</td><td>';
	print '<form id="'.$formid.'" action="'.dol_escape_htmltag($actionUrl).'" method="POST">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
	print '<input type="hidden" name="constname" value="'.$constname.'">';
	print $fieldHtml;
	print '</form>';
	print '</td><td align="center" width="80">';
	print '<a class="button" href="#" onclick="document.getElementById(\''.$formid.'\').submit(); return false;">'.$langs->trans("Update").'</a>';
	if (!empty($extraActionHtml)) {
		print '<br>'.$extraActionHtml;
	}
	print '</td></tr>';
}

/**
 * Capture HTML printed by Dolibarr form helpers and normalize returned value.
 *
 * @param callable $renderer Renderer callback
 * @return string
 */
function dolistorextractCaptureFieldHtml($renderer)
{
	ob_start();
	$returnValue = call_user_func($renderer);
	$fieldHtml = ob_get_clean();

	if ($fieldHtml !== '') {
		return $fieldHtml;
	}
	if (is_string($returnValue)) {
		return $returnValue;
	}

	return '';
}

/**
 * Return true if a document model is active for current entity.
 *
 * @param DoliDB $db   Database handler
 * @param string $type Document model type
 * @param string $name Model name
 * @return bool
 */
function dolistorextractDocumentModelIsActive($db, $type, $name)
{
	global $conf;

	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'document_model';
	$sql .= " WHERE nom = '".$db->escape($name)."'";
	$sql .= " AND type = '".$db->escape($type)."'";
	$sql .= ' AND entity = '.((int) $conf->entity);

	$resql = $db->query($sql);
	if (!$resql) {
		return false;
	}
	$isActive = (bool) $db->fetch_object($resql);
	$db->free($resql);

	return $isActive;
}

/*
 * Actions
 */
// Action mise a jour ou ajout d'une constante
if ($action == 'update' || $action == 'add')
{
	$constname=GETPOST('constname','alpha');
	$constvalue=(GETPOST('constvalue_'.$constname) ? GETPOST('constvalue_'.$constname) : GETPOST('constvalue'));
	if ($constname === 'DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES' && GETPOSTISARRAY('categories')) {
		$constvalue = GETPOST('categories', 'array');
	}
	if ($constname === 'DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES' && is_array($constvalue)) {
		$constvalue = implode(',', array_map('intval', $constvalue));
	}

	$consttype=GETPOST('consttype','alpha');
	$constnote=GETPOST('constnote');
	$res=dolibarr_set_const($db,$constname,$constvalue,'chaine',0,$constnote,$conf->entity);

	if (! $res > 0) $error++;

	if (! $error)
	{
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
	else
	{
		setEventMessages($langs->trans("Error"), null, 'errors');
	}

	header('Location: '.$setupPageUrl);
	exit;
}

if ($action == 'setmod') {
	$numberingModule = $value;
	if (substr($numberingModule, -4) === '.php') {
		$numberingModule = substr($numberingModule, 0, -4);
	}

	if (!preg_match('/^mod_dolistoreextract_order_[a-z0-9_]+$/', $numberingModule)) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		$file = dol_buildpath('/dolistorextract/core/modules/dolistoreextract/'.$numberingModule.'.php');
		if (is_readable($file)) {
			require_once $file;
		}

		if (!class_exists($numberingModule)) {
			setEventMessages($langs->trans('ErrorModuleNotFound'), null, 'errors');
		} else {
			$module = new $numberingModule($db);
			if (method_exists($module, 'canBeActivated') && !$module->canBeActivated()) {
				setEventMessages($langs->trans('Error'), null, 'errors');
			} else {
				$res = dolibarr_set_const($db, 'DOLISTOREXTRACT_ORDER_ADDON', $numberingModule, 'chaine', 0, '', (int) $conf->entity);
				if ($res > 0) {
					$conf->global->DOLISTOREXTRACT_ORDER_ADDON = $numberingModule;
					setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
				} else {
					setEventMessages($db->lasterror(), null, 'errors');
				}
			}
		}
	}
}

if ($action == 'set') {
	if ($value === '') {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		$ret = 1;
		if (!dolistorextractDocumentModelIsActive($db, $type, $value)) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
		if ($ret > 0) {
			dolibarr_set_const($db, 'DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED', '1', 'chaine', 0, '', (int) $conf->entity);
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

if ($action == 'del') {
	if ($value === '') {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			if (getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON_PDF') == $value) {
				dolibarr_del_const($db, 'DOLISTOREXTRACT_ORDER_ADDON_PDF', (int) $conf->entity);
				$conf->global->DOLISTOREXTRACT_ORDER_ADDON_PDF = '';
			}
			dolibarr_set_const($db, 'DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED', '1', 'chaine', 0, '', (int) $conf->entity);
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

if ($action == 'setdoc') {
	if ($value === '') {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		$ret = 1;
		if (!dolistorextractDocumentModelIsActive($db, $type, $value)) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
		if ($ret > 0 && dolibarr_set_const($db, 'DOLISTOREXTRACT_ORDER_ADDON_PDF', $value, 'chaine', 0, '', (int) $conf->entity) > 0) {
			$conf->global->DOLISTOREXTRACT_ORDER_ADDON_PDF = $value;
			dolibarr_set_const($db, 'DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED', '1', 'chaine', 0, '', (int) $conf->entity);
			setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
}

if ($action == 'create_dolistore_order_category') {
	setEventMessages($langs->trans("DolistoreNativeOrderImportObsolete"), null, 'warnings');
}

if ($action == 'create_dolistore_association_thirdparty') {
	$sql = 'SELECT rowid FROM ' . $db->prefix() . 'societe';
	$sql .= ' WHERE siret = "52033993800018"';
	$sql .= ' AND entity IN (' . getEntity('societe') . ')';
	$sql .= ' ORDER BY rowid ASC';
	$sql .= ' LIMIT 1';
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql))) {
		$db->free($resql);
		dolibarr_set_const($db, 'DOLISTOREXTRACT_BILLING_THIRDPARTY_ID', (int) $obj->rowid, 'entier', 0, '', $conf->entity);
		setEventMessages($langs->trans("DolistoreAssociationThirdpartyExists", (int) $obj->rowid), null, 'warnings');
	} else {
		$societe = new Societe($db);
		$societe->name = 'Association Dolibarr';
		$societe->address = '265 RUE DE LA VALLEE';
		$societe->zip = '45160';
		$societe->town = 'OLIVET';
		$societe->country_code = 'FR';
		$societe->idprof1 = '520339938';
		$societe->idprof2 = '52033993800018';
		$societe->tva_intra = 'FR87520339938';
		$societe->client = 2;
		$socid = $societe->create($user);
		if ($socid > 0) {
			dolibarr_set_const($db, 'DOLISTOREXTRACT_ASSOCIATION_DOLIBARR_THIRDPARTY_ID', $socid, 'entier', 0, '', $conf->entity);
			dolibarr_set_const($db, 'DOLISTOREXTRACT_BILLING_THIRDPARTY_ID', $socid, 'entier', 0, '', $conf->entity);
			setEventMessages($langs->trans("DolistoreAssociationThirdpartyCreated", (int) $socid), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("DolistoreAssociationThirdpartyCreateError"), null, 'errors');
		}
	}
}

/*
 * View
 */
$page_name = "DolistorextractSetup";
$pageTitle = $langs->trans($page_name);
if ($pageTitle === $page_name || strpos($pageTitle, 'mon module') !== false) {
	$pageTitle = $langs->trans("Setup").' '.$langs->trans("Module104976Name");
}
llxHeader('', $pageTitle);

if (!function_exists('imap_open')) {
	print '<div class="error">Extension IMAP manquante !</div>';
}

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?search_keyword='.urlencode('dolistorextract').'">'
		. $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($pageTitle, $linkback);

// Configuration header
$head = dolistorextractAdminPrepareHead();
// Setup page goes here
$form = new Form($db);
$formmail = new FormMail($db);
$token = $_SESSION['newtoken'];

print dol_get_fiche_head($head, $mode, $langs->trans("Module104976Name"), -1, "dolistore@dolistorextract");
if ($mode !== 'orders') {
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Description").'</td>';
	print '<td>'.$langs->trans("Value").'</td>';
	print '<td align="center">'.$langs->trans("Action").'</td>';
	print "</tr>\n";
}
$var = true;

if ($mode === 'settings') {
	$var = !$var;
	print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreLegacyV1Settings").'</td><td class="opacitymedium">'.$langs->trans("DolistoreLegacyNativeOrderSettingsObsolete").'</td><td align="center">&nbsp;</td></tr>';

	$var = !$var;
	print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreAssociationThirdpartyLabel").'</td><td>'.$langs->trans("DolistoreAssociationThirdpartyDataHint").'</td><td align="center" width="80">';
	print '<a class="button button-edit" href="'.$self.'?action=create_dolistore_association_thirdparty&token='.$token.'">'.$langs->trans("DolistoreAssociationThirdpartyCreateButton").'</a>';
	print '</td></tr>';

	$var = !$var;
	$fieldBillingThirdparty = dolistorextractCaptureFieldHtml(function () use ($form) {
		return $form->select_company(getDolGlobalInt('DOLISTOREXTRACT_BILLING_THIRDPARTY_ID'), 'constvalue', '(s.client:IN:1,2,3)');
	});
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreBillingThirdpartyLabel"), 'DOLISTOREXTRACT_BILLING_THIRDPARTY_ID', $fieldBillingThirdparty, $setupPageUrl, $mode, $token);

	$var = !$var;
	$fieldCommission = '<input type="text" class="text flat" name="constvalue" value="' . dol_escape_htmltag(getDolGlobalString('DOLISTOREXTRACT_COMMISSION_PERCENT')) .'" placeholder="0"><span class="opacitymedium"> %</span>';
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreCommissionPercentLabel"), 'DOLISTOREXTRACT_COMMISSION_PERCENT', $fieldCommission, $setupPageUrl, $mode, $token);

	$var = !$var;
	print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreUnmappedServiceSettings").'</td><td class="opacitymedium">'.$langs->trans("DolistoreUnmappedServiceSettingsObsolete").'</td><td align="center">&nbsp;</td></tr>';

	$var = !$var;
	$fieldUserForActions = $form->select_dolusers(getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS'), 'constvalue');
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractUserForActions"), 'DOLISTOREXTRACT_USER_FOR_ACTIONS', $fieldUserForActions, $setupPageUrl, $mode, $token);
}

if ($mode === 'billing') {
	$var = !$var;
	$fieldBillingThirdparty = dolistorextractCaptureFieldHtml(function () use ($form) {
		return $form->select_company(getDolGlobalInt('DOLISTOREXTRACT_BILLING_THIRDPARTY_ID'), 'constvalue', '(s.client:IN:1,2,3)');
	});
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreBillingThirdpartyLabel"), 'DOLISTOREXTRACT_BILLING_THIRDPARTY_ID', $fieldBillingThirdparty, $setupPageUrl, $mode, $token);

	$textRows = array(
		array('DOLISTOREXTRACT_INVOICE_EMAIL_TO', 'DolistoreInvoiceEmailTo', 'text'),
		array('DOLISTOREXTRACT_INVOICE_MIN_AMOUNT_HT', 'DolistoreInvoiceMinAmountHt', 'text'),
		array('DOLISTOREXTRACT_PAYMENT_RELEASE_DELAY_DAYS', 'DolistorePaymentReleaseDelayDays', 'text'),
	);
	foreach ($textRows as $textDefinition) {
		$var = !$var;
		$fieldText = '<input type="'.$textDefinition[2].'" class="text flat minwidth300" name="constvalue" value="'.dol_escape_htmltag(getDolGlobalString($textDefinition[0])).'">';
		dolistorextractPrintUpdateRow($bc[$var], $langs->trans($textDefinition[1]), $textDefinition[0], $fieldText, $setupPageUrl, $mode, $token);
	}

	$var = !$var;
	$configuredInvoiceVatRate = getDolGlobalString('DOLISTOREXTRACT_INVOICE_TVA_RATE');
	$selectedInvoiceVatRate = $configuredInvoiceVatRate;
	if ($selectedInvoiceVatRate === '' && is_object($mysoc)) {
		$defaultInvoiceVatRate = get_default_tva($mysoc, $mysoc);
		if ($defaultInvoiceVatRate !== -1 && $defaultInvoiceVatRate !== '-1') {
			$selectedInvoiceVatRate = (string) $defaultInvoiceVatRate;
		}
	}
	$fieldInvoiceVatRate = $form->load_tva('constvalue', $selectedInvoiceVatRate, $mysoc, $mysoc, 0, 0, '', false, 1, 1);
	if (strpos($fieldInvoiceVatRate, ' disabled') !== false) {
		$fieldInvoiceVatRate .= '<input type="hidden" name="constvalue" value="'.dol_escape_htmltag($selectedInvoiceVatRate).'">';
	}
	if ($configuredInvoiceVatRate !== '' && strpos($fieldInvoiceVatRate, ' selected') === false) {
		$fieldInvoiceVatRate .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreInvoiceVatRateUnavailable', dol_escape_htmltag($configuredInvoiceVatRate)).'</span>';
	}
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans('DolistoreInvoiceTvaRate'), 'DOLISTOREXTRACT_INVOICE_TVA_RATE', $fieldInvoiceVatRate, $setupPageUrl, $mode, $token);

	$var = !$var;
	$invoiceEmailTemplates = array();
	$sql = 'SELECT rowid, module, label, lang';
	$sql .= ' FROM '.MAIN_DB_PREFIX.'c_email_templates';
	$sql .= " WHERE type_template = 'facture_send'";
	$sql .= ' AND active = 1';
	$sql .= ' AND private = 0';
	$sql .= ' AND entity IN ('.getEntity('c_email_templates').')';
	$sql .= ' ORDER BY position ASC, lang ASC, label ASC';
	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		while (is_object($emailTemplate = $db->fetch_object($resql))) {
			$templateModule = (string) $emailTemplate->module;
			if ($templateModule !== '' && !isModEnabled($templateModule)) {
				continue;
			}
			$templateLabel = (string) $emailTemplate->label;
			if ((string) $emailTemplate->lang !== '') {
				$templateLabel .= ' ['.(string) $emailTemplate->lang.']';
			}
			$invoiceEmailTemplates[(int) $emailTemplate->rowid] = $templateLabel;
		}
		$db->free($resql);
	}
	$selectedInvoiceEmailTemplate = getDolGlobalInt('DOLISTOREXTRACT_INVOICE_EMAIL_TEMPLATE_ID');
	$fieldInvoiceEmailTemplate = $form->selectarray('constvalue', $invoiceEmailTemplates, $selectedInvoiceEmailTemplate, 1);
	if ($selectedInvoiceEmailTemplate > 0 && !isset($invoiceEmailTemplates[$selectedInvoiceEmailTemplate])) {
		$fieldInvoiceEmailTemplate .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreInvoiceEmailTemplateUnavailable', $selectedInvoiceEmailTemplate).'</span>';
	} elseif (empty($invoiceEmailTemplates)) {
		$fieldInvoiceEmailTemplate .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreInvoiceEmailTemplateMissing').'</span>';
	}
	$manageEmailTemplatesUrl = DOL_URL_ROOT.'/admin/mails_templates.php?search_type_template='.urlencode('facture_send');
	$manageEmailTemplatesLink = '<a href="'.dol_escape_htmltag($manageEmailTemplatesUrl).'">'.img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans('DolistoreManageEmailTemplates').'</a>';
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans('DolistoreInvoiceEmailTemplate'), 'DOLISTOREXTRACT_INVOICE_EMAIL_TEMPLATE_ID', $fieldInvoiceEmailTemplate, $setupPageUrl, $mode, $token, $manageEmailTemplatesLink);

	$var = !$var;
	$selectedInvoiceStatus = getDolGlobalString('DOLISTOREXTRACT_INVOICE_STATUS');
	if ($selectedInvoiceStatus === '') {
		$selectedInvoiceStatus = 'draft';
	}
	$fieldInvoiceStatus = $form->selectarray('constvalue', array('draft' => $langs->trans('Draft'), 'validated' => $langs->trans('Validated')), $selectedInvoiceStatus);
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans('DolistoreInvoiceGeneratedStatus'), 'DOLISTOREXTRACT_INVOICE_STATUS', $fieldInvoiceStatus, $setupPageUrl, $mode, $token);

	$binaryConstants = array(
		'DOLISTOREXTRACT_AUTO_CREATE_INVOICE' => 'DolistoreAutoCreateInvoice',
		'DOLISTOREXTRACT_AUTO_SEND_INVOICE' => 'DolistoreAutoSendInvoice',
		'DOLISTOREXTRACT_DAILY_NOTIFICATION_ENABLED' => 'DolistoreDailyNotificationEnabled',
	);
	foreach ($binaryConstants as $constName => $labelKey) {
		$var = !$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$langs->trans($labelKey).'</td>';
		print '<td align="center">&nbsp;</td>';
		print '<td align="right">';
		print '<div class="notopnoleft"><form method="POST" action="'.dol_escape_htmltag($setupPageUrl).'">';
		print '<input type="hidden" name="token" value="'.$token.'">';
		print '<input type="hidden" name="action" value="set_'.$constName.'">';
		print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
		print ajax_constantonoff($constName);
		print '</form></div>';
		print '</td></tr>';
	}
}

if ($mode === 'emailsimap') {
	$textConstants = array(
		array('DOLISTOREXTRACT_IMAP_SERVER', 'DolistorExtractImapServer', 'text', ''),
		array('DOLISTOREXTRACT_IMAP_SERVER_PORT', 'DolistorExtractImapServerPort', 'text', ''),
		array('DOLISTOREXTRACT_IMAP_USER', 'DolistorExtractImapUser', 'text', ''),
		array('DOLISTOREXTRACT_IMAP_PWD', 'DolistorExtractImapPassword', 'password', ''),
		array('DOLISTOREXTRACT_IMAP_FOLDER', 'DolistorExtractImapFolder', 'text', 'INBOX'),
		array('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE', 'DolistorExtractImapFolderArchive', 'text', 'INBOX/ARCHIVES'),
		array('DOLISTOREXTRACT_IMAP_FOLDER_ERROR', 'DolistorExtractImapFolderError', 'text', 'INBOX/ERRORS')
	);
	foreach ($textConstants as $textDefinition) {
		$var = !$var;
		$fieldText = '<input type="'.$textDefinition[2].'" class="text flat" name="constvalue" value="'.dol_escape_htmltag(getDolGlobalString($textDefinition[0])).'"';
		if (!empty($textDefinition[3])) {
			$fieldText .= ' placeholder="'.$textDefinition[3].'"';
		}
		$fieldText .= '>';
		dolistorextractPrintUpdateRow($bc[$var], $langs->trans($textDefinition[1]), $textDefinition[0], $fieldText, $setupPageUrl, $mode, $token);
	}

	$var = !$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DolistoreAutoImportEnabled').'</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<div class="notopnoleft"><form method="POST" action="'.dol_escape_htmltag($setupPageUrl).'">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="set_DOLISTOREXTRACT_AUTO_IMPORT_ENABLED">';
	print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';
	print ajax_constantonoff('DOLISTOREXTRACT_AUTO_IMPORT_ENABLED');
	print '</form></div>';
	print '</td></tr>';

	$var = !$var;
	print '<tr '.$bc[$var].'><td>'.$langs->trans("DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU").'</td><td class="opacitymedium">'.$langs->trans("DolistoreFinalCustomerEmailObsolete").'</td><td align="center">&nbsp;</td></tr>';

	$arrayTemplatesFr = array();
	$arrayTemplatesEn = array();
	$ret = $formmail->fetchAllEMailTemplate('dolistore_extract', $user, $langs);
	if ($ret < 0) {
		setEventMessages($formmail->error, $formmail->errors, 'errors');
	} elseif (is_array($formmail->lines_model)) {
		foreach ($formmail->lines_model as $modelEmail) {
			if (!empty($modelEmail->private)) {
				continue;
			}
			if ((string) $modelEmail->lang === 'fr_FR') {
				$arrayTemplatesFr[(int) $modelEmail->id] = (string) $modelEmail->label;
			} elseif ((string) $modelEmail->lang === 'en_US') {
				$arrayTemplatesEn[(int) $modelEmail->id] = (string) $modelEmail->label;
			}
		}
	}
	$manageDolistoreEmailTemplatesUrl = DOL_URL_ROOT.'/admin/mails_templates.php?search_type_template='.urlencode('dolistore_extract');
	$manageDolistoreEmailTemplatesLink = '<a href="'.dol_escape_htmltag($manageDolistoreEmailTemplatesUrl).'">'.img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans('DolistoreManageEmailTemplates').'</a>';

	$var = !$var;
	$selectedTemplateFr = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_FR');
	$fieldTemplateFr = $form->selectarray('constvalue', $arrayTemplatesFr, $selectedTemplateFr);
	if ($selectedTemplateFr > 0 && !isset($arrayTemplatesFr[$selectedTemplateFr])) {
		$fieldTemplateFr .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreOrderEmailTemplateUnavailable', $selectedTemplateFr).'</span>';
	} elseif (empty($arrayTemplatesFr)) {
		$fieldTemplateFr .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreOrderEmailTemplateMissing', $langs->trans('French')).'</span>';
	}
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractEmailTemplateFr"), 'DOLISTOREXTRACT_EMAIL_TEMPLATE_FR', $fieldTemplateFr, $setupPageUrl, $mode, $token, $manageDolistoreEmailTemplatesLink);

	$var = !$var;
	$selectedTemplateEn = getDolGlobalInt('DOLISTOREXTRACT_EMAIL_TEMPLATE_EN');
	$fieldTemplateEn = $form->selectarray('constvalue', $arrayTemplatesEn, $selectedTemplateEn);
	if ($selectedTemplateEn > 0 && !isset($arrayTemplatesEn[$selectedTemplateEn])) {
		$fieldTemplateEn .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreOrderEmailTemplateUnavailable', $selectedTemplateEn).'</span>';
	} elseif (empty($arrayTemplatesEn)) {
		$fieldTemplateEn .= '<br><span class="warning">'.img_warning().' '.$langs->trans('DolistoreOrderEmailTemplateMissing', $langs->trans('English')).'</span>';
	}
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractEmailTemplateEn"), 'DOLISTOREXTRACT_EMAIL_TEMPLATE_EN', $fieldTemplateEn, $setupPageUrl, $mode, $token);
}

if ($mode === 'orders') {
	print load_fiche_titre($langs->trans('DolistoreOrderNumberingModules'), '', '');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Name').'</td>';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td>'.$langs->trans('Example').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('ShortInfo').'</td>';
	print '</tr>';

	$currentNumberingModule = getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON', 'mod_dolistoreextract_order_dse');
	if (substr($currentNumberingModule, -4) === '.php') {
		$currentNumberingModule = substr($currentNumberingModule, 0, -4);
	}

	$numberingDir = dol_buildpath('/dolistorextract/core/modules/dolistoreextract/');
	$numberingFiles = array();
	if (is_dir($numberingDir)) {
		$handle = opendir($numberingDir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				if (preg_match('/^mod_dolistoreextract_order_.*\.php$/', $file)) {
					$numberingFiles[] = $file;
				}
			}
			closedir($handle);
		}
	}
	sort($numberingFiles);

	$foundNumberingModule = false;
	foreach ($numberingFiles as $file) {
		$classname = substr($file, 0, -4);
		require_once $numberingDir.$file;
		if (!class_exists($classname)) {
			continue;
		}
		$module = new $classname($db);
		if (!empty($module->version) && $module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
			continue;
		}
		if (!empty($module->version) && $module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
			continue;
		}
		if (method_exists($module, 'isEnabled') && !$module->isEnabled()) {
			continue;
		}

		$foundNumberingModule = true;
		$specimen = new DolistoreOrder($db);
		$specimen->initAsSpecimen();
		$nextValue = $module->getNextValue((int) $conf->entity, $specimen);
		$htmltooltip = '<b>'.$langs->trans('Version').':</b> '.dol_escape_htmltag($module->getVersion()).'<br>';
		$htmltooltip .= '<b>'.$langs->trans('NextValue').':</b> '.dol_escape_htmltag($nextValue !== '' ? $nextValue : $module->error).'<br>';
		if (method_exists($module, 'getToolTip')) {
			$htmltooltip .= dol_escape_htmltag($module->getToolTip());
		}

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag(!empty($module->name) ? $module->name : $classname).'</td>';
		print '<td>'.$module->info().'</td>';
		print '<td>'.dol_escape_htmltag($module->getExample()).'</td>';
		print '<td class="center">';
		if ($currentNumberingModule == $classname) {
			print img_picto($langs->trans('Activated'), 'switch_on');
		} else {
			$url = $self.'?mode=orders&action=setmod&value='.urlencode($classname).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td>';
		print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 0).'</td>';
		print '</tr>';
	}
	if (!$foundNumberingModule) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
	print '<br>';

	print load_fiche_titre($langs->trans('DolistoreOrderDocumentModels'), '', '');
	$activeDocumentModels = array();
	$sql = 'SELECT nom FROM '.MAIN_DB_PREFIX.'document_model';
	$sql .= " WHERE type = '".$db->escape($type)."'";
	$sql .= ' AND entity = '.((int) $conf->entity);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$activeDocumentModels[] = $obj->nom;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Name').'</td>';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('Default').'</td>';
	print '<td class="center">'.$langs->trans('ShortInfo').'</td>';
	print '</tr>';

	$documentDir = dol_buildpath('/dolistorextract/core/modules/dolistoreextract/doc/');
	$documentRealPath = 'dolistorextract/core/modules/dolistoreextract/doc';
	$documentFiles = array();
	if (is_dir($documentDir)) {
		$handle = opendir($documentDir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				if (preg_match('/^pdf_.*\.modules\.php$/', $file)) {
					$documentFiles[] = $file;
				}
			}
			closedir($handle);
		}
	}
	sort($documentFiles);

	$foundDocumentModel = false;
	foreach ($documentFiles as $file) {
		$name = substr($file, 4, dol_strlen($file) - 16);
		$classname = substr($file, 0, dol_strlen($file) - 12);
		require_once $documentDir.$file;
		if (!class_exists($classname)) {
			continue;
		}
		$module = new $classname($db);
		if (!empty($module->version) && $module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
			continue;
		}
		if (!empty($module->version) && $module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
			continue;
		}

		$foundDocumentModel = true;
		$isActive = in_array($name, $activeDocumentModels, true);
		$isDefault = getDolGlobalString('DOLISTOREXTRACT_ORDER_ADDON_PDF') == $name;
		$modelLabel = !empty($module->name) ? $module->name : $name;
		$htmltooltip = '<b>'.$langs->trans('Name').':</b> '.dol_escape_htmltag($modelLabel).'<br>';
		$htmltooltip .= '<b>'.$langs->trans('Type').':</b> '.dol_escape_htmltag(!empty($module->type) ? $module->type : $langs->trans('Unknown')).'<br>';
		if (!empty($module->type) && $module->type == 'pdf' && !empty($module->page_largeur) && !empty($module->page_hauteur)) {
			$htmltooltip .= '<b>'.$langs->trans('Width').'/'.$langs->trans('Height').':</b> '.dol_escape_htmltag($module->page_largeur.'/'.$module->page_hauteur).'<br>';
		}
		$htmltooltip .= '<b>'.$langs->trans('Path').':</b> '.dol_escape_htmltag($documentRealPath.'/'.$file);

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($modelLabel).'</td>';
		print '<td>'.(!empty($module->description) ? $module->description : '').'</td>';
		print '<td class="center">';
		if ($isActive) {
			$url = $self.'?mode=orders&action=del&value='.urlencode($name).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Enabled'), 'switch_on').'</a>';
		} else {
			$url = $self.'?mode=orders&action=set&value='.urlencode($name).'&scan_dir='.urlencode($documentRealPath).'&label='.urlencode($modelLabel).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td>';
		print '<td class="center">';
		if ($isDefault) {
			print img_picto($langs->trans('Default'), 'on');
		} else {
			$url = $self.'?mode=orders&action=setdoc&value='.urlencode($name).'&scan_dir='.urlencode($documentRealPath).'&label='.urlencode($modelLabel).'&token='.urlencode($token);
			print '<a href="'.dol_escape_htmltag($url).'">'.img_picto($langs->trans('Disabled'), 'off').'</a>';
		}
		print '</td>';
		print '<td class="center">'.$form->textwithpicto('', $htmltooltip, 1, 0).'</td>';
		print '</tr>';
	}
	if (!$foundDocumentModel) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	print '</table>';
}

if ($mode !== 'orders') {
	print '</table>';
}
print '<br>';

print '<script>
$(document).ready(function () {
	if (typeof $.fn.select2 === "undefined") return;
	$("select[data-dolistorextract-select2=\'1\']").each(function () {
		var $select = $(this);
		if ($select.hasClass("select2-hidden-accessible")) return;
		$select.select2({
			width: "resolve",
			language: (typeof select2arrayoflanguage === "undefined") ? "en" : select2arrayoflanguage
		});
	});
});
</script>';

if ($mode === 'emailsimap') {
	print '<a class="butActions" href="'.$_SERVER['PHP_SELF'].'?mode=emailsimap&action=test_connect&token='.$token.'">Test IMAP</a>';
}


if ($action == 'test_connect') {


	$mailbox = getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER');
	$username = getDolGlobalString('DOLISTOREXTRACT_IMAP_USER');
	$password = getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD');
	$encryption = Imap::ENCRYPT_SSL;

	// Open connection
	try{
		$imap = new Imap($mailbox, $username, $password, $encryption);
		// You can also check out example-connect.php for more connection options

		print '<div class="confirm">OK!</div>';

	}catch (ImapClientException $error){
		print '<div class="error">';
		print $error->getMessage().PHP_EOL;
		print '</div>';
		die(); // Oh no :( we failed
	}
}

// Page end
print dol_get_fiche_end();
llxFooter();
