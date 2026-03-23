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
require_once DOL_DOCUMENT_ROOT."/core/class/html.formmail.class.php";
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
	$hasActiveColumn = false;
	$hasCodeColumn = false;
	$resqlColumns = $db->query('SHOW COLUMNS FROM ' . $table . ' LIKE "active"');
	if ($resqlColumns) {
		$hasActiveColumn = ($db->num_rows($resqlColumns) > 0);
		$db->free($resqlColumns);
	}
	$resqlCodeColumn = $db->query('SHOW COLUMNS FROM ' . $table . ' LIKE "code"');
	if ($resqlCodeColumn) {
		$hasCodeColumn = ($db->num_rows($resqlCodeColumn) > 0);
		$db->free($resqlCodeColumn);
	}

	$sql = 'SELECT rowid, ' . $labelExpression . ' as label';
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



/*
 * Actions
 */
// Action mise a jour ou ajout d'une constante
if ($action == 'update' || $action == 'add')
{
	$constname=GETPOST('constname','alpha');
	$constvalue=(GETPOST('constvalue_'.$constname) ? GETPOST('constvalue_'.$constname) : GETPOST('constvalue'));
	if ($constname === 'DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES' && is_array($constvalue)) {
		$constvalue = implode(',', array_map('intval', $constvalue));
	}

	$consttype=GETPOST('consttype','alpha');
	$constnote=GETPOST('constnote');
	$res=dolibarr_set_const($db,$constname,$constvalue,$type[$consttype],0,$constnote,$conf->entity);

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

/*
 * View
 */
$page_name = "DolistorextractSetup";
llxHeader('', $langs->trans($page_name));

if (!function_exists('imap_open')) {
	print '<div class="error">Extension IMAP manquante !</div>';
}

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
		. $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
//$head = dolistorextractAdminPrepareHead();
/*dol_fiche_head(
	$head,
	'settings',
	$langs->trans("Module500000Name"),
	0,
	"dolistorextract@dolistorextract"
);
*/
// Setup page goes here
echo $langs->trans("DolistorextractSetupPage");

$form=new Form($db);
$formmail=new FormMail($db);

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '<td align="center">'.$langs->trans("Action").'</td>';
print "</tr>\n";
$var=true;

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

// IMAP server
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_AVAILABILITY_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultAvailabilityIdLabel").'</td><td>';
print $form->selectarray('constvalue', $availabilityOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_AVAILABILITY_ID'));
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultShippingMethodIdLabel").'</td><td>';
if (method_exists($form, 'selectShippingMethod')) {
	print $form->selectShippingMethod(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID'), 'constvalue', 1, '', 0, 1);
} else {
	print $form->selectarray('constvalue', $shippingMethodOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_SHIPPING_METHOD_ID'));
}
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_INPUT_REASON_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultInputReasonIdLabel").'</td><td>';
print $form->selectarray('constvalue', $originOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_INPUT_REASON_ID'));
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_COND_REGLEMENT_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultCondReglementIdLabel").'</td><td>';
print $form->selectarray('constvalue', $condReglementOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_COND_REGLEMENT_ID'));
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultModeReglementIdLabel").'</td><td>';
if (method_exists($form, 'select_types_paiements')) {
	print $form->select_types_paiements(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID'), 'constvalue');
} else {
	print $form->selectarray('constvalue', $modeReglementOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_MODE_REGLEMENT_ID'));
}
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultBankAccountIdLabel").'</td><td>';
if (method_exists($form, 'select_comptes')) {
	print $form->select_comptes(getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID'), 'constvalue', 0, '', 1);
} else {
	print $form->selectarray('constvalue', $bankAccountOptions, getDolGlobalInt('DOLISTOREXTRACT_DEFAULT_BANK_ACCOUNT_ID'));
}
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_DEFAULT_ORDER_CATEGORIES">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreDefaultOrderCategoriesLabel").'</td><td>';
print $form->multiselectarray('constvalue', $orderCategoryOptions, $selectedOrderCategories);
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_SERVER">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapServer").'</td><td>';
print '<input type="text" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER') .'" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// IMAP server port
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_SERVER_PORT">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapServerPort").'</td><td>';
print '<input type="input" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_SERVER_PORT') .'" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// Imap User
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_USER">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapUser").'</td><td>';
print '<input type="text" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_USER') .'" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// IMAP password
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_PWD">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapPassword").'</td><td>';
print '<input type="password" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_PWD') .'" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';



// IMAP FOLDER
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_FOLDER">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapFolder").'</td><td>';
print '<input type="input" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER') .'" placeholder="INBOX" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';


// IMAP FOLDER ARCHIVE
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapFolderArchive").'</td><td>';
print '<input type="input" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ARCHIVE') .'"  placeholder="INBOX/ARCHIVES" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// IMAP FOLDER ERROR
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_IMAP_FOLDER_ERROR">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractImapFolderError").'</td><td>';
print '<input type="input" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_IMAP_FOLDER_ERROR') .'" placeholder="INBOX/ERRORS" />';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU').'</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU">';
print ajax_constantonoff('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU');
print '</form></div>';
print '</td></tr>';

$var=!$var;

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER').'</td>';
print '<td align="center">&nbsp;</td>';
print '<td align="right">';
print '<div class="notopnoleft"><form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER">';
print ajax_constantonoff('DOLISTOREXTRACT_AUTO_VALIDATE_NATIVE_ORDER');
print '</form></div>';
print '</td></tr>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_COMMISSION_PERCENT">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreCommissionPercentLabel").'</td><td>';
print '<input type="text" class="text flat" name="constvalue" value="' . getDolGlobalString('DOLISTOREXTRACT_COMMISSION_PERCENT') .'" placeholder="0">';
print '<span class="opacitymedium"> %</span>';
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
$arrayUnmappedServicePolicy = array(
	'abandon' => $langs->trans("DolistoreUnmappedPolicyAbandon"),
	'create' => $langs->trans("DolistoreUnmappedPolicyCreate")
);
$selectedUnmappedPolicy = getDolGlobalString('DOLISTOREXTRACT_UNMAPPED_SERVICE_POLICY');
if (!in_array($selectedUnmappedPolicy, array('abandon', 'create'), true)) {
	$selectedUnmappedPolicy = 'abandon';
}
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_UNMAPPED_SERVICE_POLICY">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreUnmappedPolicyLabel").'</td><td>';
print $form->selectarray('constvalue', $arrayUnmappedServicePolicy, $selectedUnmappedPolicy);
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

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
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_UNMAPPED_SERVICE_BEHAVIOR">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistoreUnmappedBehaviorLabel").'</td><td>';
print $form->selectarray('constvalue', $arrayUnmappedServiceBehavior, $selectedUnmappedBehavior);
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';


// User for actions
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_USER_FOR_ACTIONS">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractUserForActions").'</td><td>';
print $form->select_dolusers(getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS'), 'constvalue');
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// Search email template
$arrayTemplates = array();
$ret = $formmail->fetchAllEMailTemplate('dolistore_extract', $user, $langs);
if ($ret > 0) {
	foreach ($formmail->lines_model as $modelEmail) {
		$arrayTemplates[$modelEmail->id] = $modelEmail->label;
	}
}

// FR email template
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_EMAIL_TEMPLATE_FR">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractEmailTemplateFr").'</td><td>';
print $form->selectarray('constvalue', $arrayTemplates, getDolGlobalString('DOLISTOREXTRACT_EMAIL_TEMPLATE_FR'));
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';

// EN email template
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="constname" value="DOLISTOREXTRACT_EMAIL_TEMPLATE_EN">';
print '<tr '.$bc[$var].'><td>'.$langs->trans("DolistorExtractEmailTemplateEn").'</td><td>';
print $form->selectarray('constvalue', $arrayTemplates, getDolGlobalString('DOLISTOREXTRACT_EMAIL_TEMPLATE_EN'));
print '</td><td align="center" width="80">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print "</td></tr>\n";
print '</form>';


print '</table>';
print '<br>';

print '<a class="butActions" href="'.$_SERVER['PHP_SELF'].'?action=test_connect">Test IMAP</a>';


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
dol_fiche_end();
llxFooter();
