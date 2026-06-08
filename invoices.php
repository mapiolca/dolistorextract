<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/actions_dolistorextract.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'bills'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'invoice', 'generate')) accessforbidden();

$action = GETPOST('action', 'aZ09');
if ($action === 'generate') {
	if (GETPOST('token', 'alphanohtml') === '') accessforbidden('Invalid token');
	$actions = new ActionsDolistorextract($db);
	$result = $actions->generateMonthlyDolistoreInvoice($user, true);
	if ($result < 0) setEventMessages($actions->error, $actions->errors, 'errors');
	else setEventMessages($langs->trans('DolistoreInvoiceGenerationDone'), null, 'mesgs');
}

llxHeader('', $langs->trans('DolistoreInvoices'));
print load_fiche_titre($langs->trans('DolistoreInvoices'), '', 'bill');
print '<div class="tabsAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=generate&token='.newToken().'">'.$langs->trans('DolistoreGenerateMonthlyInvoice').'</a></div>';

print '<table class="liste centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Period').'</th><th>'.$langs->trans('Invoice').'</th><th class="right">'.$langs->trans('AmountHT').'</th><th class="right">'.$langs->trans('DolistoreOrdersCount').'</th><th class="right">'.$langs->trans('DolistoreLinesCount').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('DolistoreEmailSent').'</th></tr>';
$sql = 'SELECT b.*, f.ref as invoice_ref FROM '.MAIN_DB_PREFIX.'dolistoreextract_invoice_batch b LEFT JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid = b.fk_facture WHERE b.entity IN ('.getEntity('dolistoreextract_order').') ORDER BY b.period_year DESC, b.period_month DESC';
$resql = $db->query($sql);
while ($resql && ($obj = $db->fetch_object($resql))) {
	print '<tr class="oddeven">';
	print '<td>'.((int) $obj->period_year).'-'.str_pad((string) $obj->period_month, 2, '0', STR_PAD_LEFT).'</td>';
	print '<td>'.(!empty($obj->fk_facture) ? '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $obj->fk_facture.'">'.dol_escape_htmltag($obj->invoice_ref).'</a>' : '').'</td>';
	print '<td class="right">'.price($obj->amount_ht).'</td><td class="right">'.((int) $obj->orders_count).'</td><td class="right">'.((int) $obj->lines_count).'</td>';
	print '<td>'.((int) $obj->status === 1 ? $langs->trans('Success') : (((int) $obj->status === 9) ? $langs->trans('Error') : $langs->trans('Draft'))).'</td>';
	print '<td>'.yn((int) $obj->email_sent).'</td>';
	print '</tr>';
}
if ($resql) $db->free($resql);
print '</table>';
llxFooter();
$db->close();
