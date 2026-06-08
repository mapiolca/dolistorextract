<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'products'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$id = GETPOST('id', 'int');
$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden();

llxHeader('', $langs->trans('DolistoreOrderLines'));
print dol_get_fiche_head(dolistoreextractOrderPrepareHead($object), 'lines', $langs->trans('DolistoreOrder'), -1, 'dolistore@dolistorextract');
dol_banner_tab($object, 'ref', '<a href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('BackToList').'</a>', 1, 'ref', 'ref', '');

print '<div class="div-table-responsive"><table class="liste centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('DolistoreProductRef').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('Product').'</th><th class="right">'.$langs->trans('Qty').'</th><th class="right">'.$langs->trans('UnitPriceHT').'</th><th class="right">'.$langs->trans('AmountHT').'</th><th class="right">'.$langs->trans('DolistoreBillableAmountHT').'</th></tr>';
foreach ($object->getLines() as $line) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($line->product_dolistore_ref).'</td>';
	print '<td>'.dol_escape_htmltag($line->product_label).'</td>';
	print '<td>'.(!empty($line->fk_product) ? '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.(int) $line->fk_product.'">'.((int) $line->fk_product).'</a>' : '<span class="warning">'.$langs->trans('DolistoreServiceUnmapped').'</span>').'</td>';
	print '<td class="right">'.price($line->qty).'</td>';
	print '<td class="right">'.price($line->unit_price_ht).'</td>';
	print '<td class="right">'.price($line->total_ht).'</td>';
	print '<td class="right">'.price($line->billable_total_ht).'</td>';
	print '</tr>';
}
print '</table></div>';
print dol_get_fiche_end();
llxFooter();
$db->close();
