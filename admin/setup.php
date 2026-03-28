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
require_once DOL_DOCUMENT_ROOT."/core/class/html.formmail.class.php";
require_once DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once("/dolistorextract/include/ssilence/php-imap-client/autoload.php");

use SSilence\ImapClient\ImapClientException;
use SSilence\ImapClient\ImapConnect;
use SSilence\ImapClient\ImapClient as Imap;

// Translations
$langs->load('admin');
$langs->load("dolistorextract@dolistorextract");

// Access control
if (! $user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/**
 * Returns options list from a dictionary table.
 *
 * @param DoliDB $db Database handler
 * @param string $tableName Dictionary table name without prefix
 * @param string $labelExpression SQL label expression
 * @param string $whereClause Extra where clause
 * @return array<int,string>
 */
function dolistorextractGetDictionaryOptions($db, $tableName, $labelExpression, $whereClause = '')
{
	global $langs;

	$options = array(0 => '');
	$table = $db->prefix() . $tableName;
	$columns = array();
	$resqlAllColumns = $db->query('SHOW COLUMNS FROM ' . $table);
	if ($resqlAllColumns) {
		while ($objcol = $db->fetch_object($resqlAllColumns)) {
			$columns[] = (string) $objcol->Field;
		}
		$db->free($resqlAllColumns);
	}
	if (empty($columns)) {
		return $options;
	}

	$idColumn = in_array('rowid', $columns, true) ? 'rowid' : (in_array('id', $columns, true) ? 'id' : '');
	if ($idColumn === '') {
		return $options;
	}
	$labelColumn = in_array('label', $columns, true) ? 'label' : (in_array('libelle', $columns, true) ? 'libelle' : (in_array('code', $columns, true) ? 'code' : ''));
	if ($labelColumn === '') {
		return $options;
	}

	$hasActiveColumn = false;
	$hasCodeColumn = false;
	$hasActiveColumn = in_array('active', $columns, true);
	$hasCodeColumn = in_array('code', $columns, true);

	$sql = 'SELECT ' . $idColumn . ' as rowid, ' . $labelColumn . ' as label';
	if ($hasCodeColumn) {
		$sql .= ', code';
	}
	$sql .= ' FROM ' . $table;
	$sql .= ' WHERE 1 = 1';
	if ($hasActiveColumn) {
		$sql .= ' AND active = 1';
	}
	if (!empty($whereClause)) {
		$sql .= ' AND ' . $whereClause;
	}
	$sql .= ' ORDER BY label ASC';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$optionLabel = (string) $obj->label;
			if ($hasCodeColumn && !empty($obj->code)) {
				$translatedLabel = $langs->trans((string) $obj->code);
				if (!empty($translatedLabel) && $translatedLabel !== (string) $obj->code) {
					$optionLabel = $translatedLabel;
				}
			}
			$options[(int) $obj->rowid] = $optionLabel;
		}
		$db->free($resql);
	}

	return $options;
}

/**
 * Print one update row while keeping form tags inside table cells.
 *
 * @param string $rowClass Row class
 * @param string $label Label
 * @param string $constname Constant name
 * @param string $fieldHtml Field html
 * @param string $self Self URL
 * @param string $token CSRF token
 * @param string $extraActionHtml Optional extra html in action cell
 * @return void
 */
function dolistorextractPrintUpdateRow($rowClass, $label, $constname, $fieldHtml, $self, $token, $extraActionHtml = '')
{
	global $langs;
	static $lineid = 0;

	$lineid++;
	$formid = 'dolistorextractsetupform'.$lineid;
	$fieldHtml = preg_replace('/<select\b/i', '<select data-dolistorextract-select2="1" ', $fieldHtml);

	print '<tr '.$rowClass.'><td>'.$label.'</td><td>';
	print '<form id="'.$formid.'" action="'.$self.'" method="POST">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="update">';
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
}

if ($action == 'create_dolistore_order_category') {
	$categorie = new Categorie($db);
	$categorie->type = 16;
	$categorie->label = 'Dolistore';
	$categorie->description = '';
	$db->begin();
	$categoryId = $categorie->create($user);
	if ($categoryId > 0) {
		$currentCategoryIds = array_filter(array_map('intval', preg_split('/[,; ]+/', (string) getDolGlobalString('DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES'))));
		$currentCategoryIds[] = (int) $categoryId;
		$currentCategoryIds = array_values(array_unique($currentCategoryIds));
		$resconst = dolibarr_set_const($db, 'DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES', implode(',', $currentCategoryIds), 'chaine', 0, '', $conf->entity);
		if ($resconst > 0) {
			$db->commit();
			setEventMessages($langs->trans("DolistoreOrderCategoryCreated", $categoryId), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	} else {
		$db->rollback();
		setEventMessages($langs->trans("DolistoreOrderCategoryCreateError"), null, 'errors');
	}
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
	$pageTitle = $langs->trans("Setup").' '.$langs->trans("Module500000Name");
}
llxHeader('', $pageTitle);

if (!function_exists('imap_open')) {
	print '<div class="error">Extension IMAP manquante !</div>';
}

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
		. $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($pageTitle, $linkback);

// Configuration header
$head = dolistorextractAdminPrepareHead();
// Setup page goes here
$form = new Form($db);
$formmail = new FormMail($db);
$formcompany = new FormCompany($db);
$self = $_SERVER['PHP_SELF'];
$token = $_SESSION['newtoken'];
$mode = GETPOST('mode', 'aZ09');
if (!in_array($mode, array('settings', 'customerorders', 'emailsimap'), true)) {
	$mode = 'settings';
}

print dol_get_fiche_head($head, $mode, $langs->trans("Module500000Name"), -1, "dolistore@dolistorextract");
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '<td align="center">'.$langs->trans("Action").'</td>';
print "</tr>\n";
$var = true;

if ($mode === 'customerorders') {
	$availabilityOptions = dolistorextractGetDictionaryOptions($db, 'c_availability', 'label');
	$shippingMethodOptions = dolistorextractGetDictionaryOptions($db, 'c_shipment_mode', 'label');
	$originOptions = dolistorextractGetDictionaryOptions($db, 'c_input_reason', 'label');
	$condReglementOptions = dolistorextractGetDictionaryOptions($db, 'c_payment_term', 'libelle');
	$modeReglementOptions = dolistorextractGetDictionaryOptions($db, 'c_paiement', 'libelle');

$bankAccountOptions = array(0 => '');
$sqlBankAccount = 'SELECT rowid, CONCAT(ref, " - ", label) as label';
$sqlBankAccount .= ' FROM ' . $db->prefix() . 'bank_account';
$sqlBankAccount .= ' WHERE entity IN (' . getEntity('bank_account') . ')';
$sqlBankAccount .= ' ORDER BY ref ASC';
$resqlBankAccount = $db->query($sqlBankAccount);
if ($resqlBankAccount) {
	while ($objBankAccount = $db->fetch_object($resqlBankAccount)) {
		$bankAccountOptions[(int) $objBankAccount->rowid] = (string) $objBankAccount->label;
	}
	$db->free($resqlBankAccount);
}

$orderCategoryOptions = array();
$sqlOrderCategories = 'SELECT rowid, label';
$sqlOrderCategories .= ' FROM ' . $db->prefix() . 'categorie';
$sqlOrderCategories .= ' WHERE type = 16';
$sqlOrderCategories .= ' AND entity IN (' . getEntity('category') . ')';
$sqlOrderCategories .= ' ORDER BY label ASC';
$resqlOrderCategories = $db->query($sqlOrderCategories);
if ($resqlOrderCategories) {
	while ($objOrderCategory = $db->fetch_object($resqlOrderCategories)) {
		$orderCategoryOptions[(int) $objOrderCategory->rowid] = (string) $objOrderCategory->label;
	}
	$db->free($resqlOrderCategories);
}
$selectedOrderCategories = array_filter(array_map('intval', preg_split('/[,; ]+/', (string) getDolGlobalString('DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES'))));

$selectRows = array();
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_AVAILABILITY_ID', $langs->trans('DolistoreDefaultAvailabilityIdLabel'), dolistorextractCaptureFieldHtml(function () use ($formcompany, $form, $availabilityOptions) {
	return method_exists($formcompany, 'selectAvailabilityDelay') ? $formcompany->selectAvailabilityDelay(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_AVAILABILITY_ID'), 'constvalue', '', 1) : $form->selectarray('constvalue', $availabilityOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_AVAILABILITY_ID'));
}));
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID', $langs->trans('DolistoreDefaultShippingMethodIdLabel'), dolistorextractCaptureFieldHtml(function () use ($form, $shippingMethodOptions) {
	return method_exists($form, 'selectShippingMethod') ? $form->selectShippingMethod(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID'), 'constvalue', 1, '', 0, 1) : $form->selectarray('constvalue', $shippingMethodOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID'));
}));
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_INPUT_REASON_ID', $langs->trans('DolistoreDefaultInputReasonIdLabel'), dolistorextractCaptureFieldHtml(function () use ($formcompany, $form, $originOptions) {
	return method_exists($formcompany, 'selectInputReason') ? $formcompany->selectInputReason(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_INPUT_REASON_ID'), 'constvalue', '', 1) : $form->selectarray('constvalue', $originOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_INPUT_REASON_ID'));
}));
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_COND_REGLEMENT_ID', $langs->trans('DolistoreDefaultCondReglementIdLabel'), dolistorextractCaptureFieldHtml(function () use ($form, $condReglementOptions) {
	return method_exists($form, 'select_conditions_paiements') ? $form->select_conditions_paiements(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_COND_REGLEMENT_ID'), 'constvalue') : $form->selectarray('constvalue', $condReglementOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_COND_REGLEMENT_ID'));
}));
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID', $langs->trans('DolistoreDefaultModeReglementIdLabel'), dolistorextractCaptureFieldHtml(function () use ($form, $modeReglementOptions) {
	return method_exists($form, 'select_types_paiements') ? $form->select_types_paiements(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID'), 'constvalue') : $form->selectarray('constvalue', $modeReglementOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID'));
}));
$selectRows[] = array('DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID', $langs->trans('DolistoreDefaultBankAccountIdLabel'), dolistorextractCaptureFieldHtml(function () use ($form, $bankAccountOptions) {
	return method_exists($form, 'select_comptes') ? $form->select_comptes(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID'), 'constvalue', 0, '', 1) : $form->selectarray('constvalue', $bankAccountOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID'));
}));

foreach ($selectRows as $row) {
	$var = !$var;
	dolistorextractPrintUpdateRow($bc[$var], $row[1], $row[0], $row[2], $self, $token);
}

$var = !$var;
$fieldCategories = '<span class="fas fa-tag pictofixedwidth"></span><span class="multiselectarraycategories">';
$fieldCategories .= '<input type="hidden" name="categories_multiselect" value="1">';
$fieldCategories .= dolistorextractCaptureFieldHtml(function () use ($form, $orderCategoryOptions, $selectedOrderCategories) {
	return $form->multiselectarray('categories', $orderCategoryOptions, $selectedOrderCategories, '', 0, 'minwidth100 widthcentpercentminusxx');
});
	$fieldCategories .= '</span>';
	$extraCategoryAction = '<a class="button button-edit" href="'.$self.'?action=create_dolistore_order_category&token='.$token.'">'.$langs->trans("DolistoreCreateOrderCategoryButton").'</a>';
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreDefaultOrderCategoriesLabel"), 'DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES', $fieldCategories, $self, $token, $extraCategoryAction);

	$var = !$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER').'</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<div class="notopnoleft"><form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="set_DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER">';
	print ajax_constantonoff('DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER');
	print '</form></div>';
	print '</td></tr>';
}

if ($mode === 'settings') {
	$var = !$var;
	print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreAssociationThirdpartyLabel").'</td><td>'.$langs->trans("DolistoreAssociationThirdpartyDataHint").'</td><td align="center" width="80">';
	print '<a class="button button-edit" href="'.$self.'?action=create_dolistore_association_thirdparty&token='.$token.'">'.$langs->trans("DolistoreAssociationThirdpartyCreateButton").'</a>';
	print '</td></tr>';

	$var = !$var;
	$fieldBillingThirdparty = dolistorextractCaptureFieldHtml(function () use ($form) {
		return $form->select_company(getDolGlobalInt('DOLISTOREXTRACT_BILLING_THIRDPARTY_ID'), 'constvalue', '(s.client:IN:1,2,3)');
	});
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreBillingThirdpartyLabel"), 'DOLISTOREXTRACT_BILLING_THIRDPARTY_ID', $fieldBillingThirdparty, $self, $token);

	$var = !$var;
	$fieldCommission = '<input type="text" class="text flat" name="constvalue" value="' . dol_escape_htmltag(getDolGlobalString('DOLISTOREXTRACT_COMMISSION_PERCENT')) .'" placeholder="0"><span class="opacitymedium"> %</span>';
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreCommissionPercentLabel"), 'DOLISTOREXTRACT_COMMISSION_PERCENT', $fieldCommission, $self, $token);

	$var=!$var;
	$arrayUnmappedServicePolicy = array(
		'abandon' => $langs->trans("DolistoreUnmappedPolicyAbandon"),
		'create' => $langs->trans("DolistoreUnmappedPolicyCreate")
	);
	$selectedUnmappedPolicy = getDolGlobalString('DOLISTOREXTRACT_UNMAPPED_SERVICE_POLICY');
	if (!in_array($selectedUnmappedPolicy, array('abandon', 'create'), true)) {
		$selectedUnmappedPolicy = 'abandon';
	}
	$fieldPolicy = $form->selectarray('constvalue', $arrayUnmappedServicePolicy, $selectedUnmappedPolicy);
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreUnmappedPolicyLabel"), 'DOLISTOREXTRACT_UNMAPPED_SERVICE_POLICY', $fieldPolicy, $self, $token);

	$var=!$var;
	$arrayUnmappedServiceBehavior = array(
		'block' => $langs->trans("DolistoreUnmappedBehaviorBlock"),
		'skip' => $langs->trans("DolistoreUnmappedBehaviorSkip"),
		'manual' => $langs->trans("DolistoreUnmappedBehaviorManual")
	);
	$selectedUnmappedBehavior = getDolGlobalString('DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR');
	if (!in_array($selectedUnmappedBehavior, array('block', 'skip', 'manual'), true)) {
		$selectedUnmappedBehavior = 'manual';
	}
	$fieldBehavior = $form->selectarray('constvalue', $arrayUnmappedServiceBehavior, $selectedUnmappedBehavior);
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistoreUnmappedBehaviorLabel"), 'DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR', $fieldBehavior, $self, $token);

	$var = !$var;
	$fieldUserForActions = $form->select_dolusers(getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS'), 'constvalue');
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractUserForActions"), 'DOLISTOREXTRACT_USER_FOR_ACTIONS', $fieldUserForActions, $self, $token);
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
		dolistorextractPrintUpdateRow($bc[$var], $langs->trans($textDefinition[1]), $textDefinition[0], $fieldText, $self, $token);
	}

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU').'</td>';
	print '<td align="center">&nbsp;</td>';
	print '<td align="right">';
	print '<div class="notopnoleft"><form method="POST" action="'.$self.'">';
	print '<input type="hidden" name="token" value="'.$token.'">';
	print '<input type="hidden" name="action" value="set_DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU">';
	print ajax_constantonoff('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU');
	print '</form></div>';
	print '</td></tr>';

	$arrayTemplates = array();
	$ret = $formmail->fetchAllEMailTemplate('dolistore_extract', $user, $langs);
	if ($ret > 0) {
		foreach ($formmail->lines_model as $modelEmail) {
			$arrayTemplates[$modelEmail->id] = $modelEmail->label;
		}
	}

	$var = !$var;
	$fieldTemplateFr = $form->selectarray('constvalue', $arrayTemplates, getDolGlobalString('DOLISTOREXTRACT_EMAIL_TEMPLATE_FR'));
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractEmailTemplateFr"), 'DOLISTOREXTRACT_EMAIL_TEMPLATE_FR', $fieldTemplateFr, $self, $token);

	$var = !$var;
	$fieldTemplateEn = $form->selectarray('constvalue', $arrayTemplates, getDolGlobalString('DOLISTOREXTRACT_EMAIL_TEMPLATE_EN'));
	dolistorextractPrintUpdateRow($bc[$var], $langs->trans("DolistorExtractEmailTemplateEn"), 'DOLISTOREXTRACT_EMAIL_TEMPLATE_EN', $fieldTemplateEn, $self, $token);
}

print '<tr class="liste_total"><td colspan="3"></td></tr>';
print '</table>';
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
	print '<a class="butActions" href="'.$_SERVER['PHP_SELF'].'?mode=emailsimap&action=test_connect">Test IMAP</a>';
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
