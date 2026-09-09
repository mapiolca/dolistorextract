<?php
/* Synthetic permission regressions using native Dolibarr libraries. No ERP or SMTP. */
require __DIR__.'/native.php';
require_once __DIR__.'/../core/modules/modDolistorextract.class.php';

// Evaluate the actual descriptor expressions with the native menu evaluator.
$user->testAllowed = false; $user->admin = 1; $user->socid = 0;
$descriptor = new modDolistorextract($templateDb);
foreach ($descriptor->menu as $menu) {
	verifyNative(verifCond($menu['perms']) === true, 'Administrator menu: '.$menu['leftmenu']);
}
verifyNative($descriptor->export_permission[1] === array(), 'Administrator export has no individual grant requirement');
$user->admin = 0;
$descriptor = new modDolistorextract($templateDb);
foreach ($descriptor->menu as $menu) {
	verifyNative(verifCond($menu['perms']) === false, 'Standard user denied menu: '.$menu['leftmenu']);
}
verifyNative($descriptor->export_permission[1] === array(array('dolistorextract', 'order', 'export')), 'Standard export retains native granular permission');
$user->testPermissions = array('dolistorextract.order.read' => true);
foreach ($descriptor->menu as $menu) {
	$expected = !in_array($menu['leftmenu'], array('dolistoreextract_invoices', 'dolistoreextract_setup'), true);
	verifyNative(verifCond($menu['perms']) === $expected, 'Read-only menu: '.$menu['leftmenu']);
}
$user->admin = 1; $user->socid = 12;
foreach ($descriptor->menu as $menu) verifyNative($menu['user'] === 0, 'Native internal-only menu excludes external administrator: '.$menu['leftmenu']);
$descriptor = new modDolistorextract($templateDb);
verifyNative(verifCond($descriptor->export_enabled[1]) === false, 'External administrator cannot export');
$user->socid = 0;
unset($conf->modules['dolistorextract']);
verifyNative(verifCond($descriptor->menu[0]['enabled']) === false, 'Administrator cannot activate a disabled module through a menu');
$conf->modules['dolistorextract'] = 1;

// Exercise server-side API authorization with native exceptions and synthetic data.
require_once DOL_DOCUMENT_ROOT.'/api/class/api_access.class.php';
require_once __DIR__.'/../class/api_dolistorextract.class.php';
DolibarrApiAccess::$user = $user;
$api = new DolistoreextractApi();
$user->testPermissions = array();
$rows = $api->getOrders();
verifyNative(count($rows) > 0 && array_reduce($rows, static function ($ok, $row) { return $ok && in_array($row['entity'], array(1, 2), true); }, true), 'Administrator API lists only shared entities');
$forbidden = false;
try { $api->getOrder($sampleOrder->id); } catch (Luracast\Restler\RestException $e) { $forbidden = $e->getCode() === 404; }
verifyNative($forbidden, 'Administrator API cannot read the order moved to entity 9');
$user->admin = 0;
foreach (array('getOrders' => array(), 'getOrder' => array(1), 'postOrder' => array(null), 'putOrder' => array(1, null), 'deleteOrder' => array(1), 'generateInvoice' => array()) as $method => $args) {
	$forbidden = false;
	try { $api->{$method}(...$args); } catch (Luracast\Restler\RestException $e) { $forbidden = $e->getCode() === 403; }
	verifyNative($forbidden, 'API refuses ungranted standard user: '.$method);
}
$user->testPermissions = array('dolistorextract.api.read' => true, 'dolistorextract.order.read' => true);
verifyNative(count($api->getOrders()) > 0, 'Read-only API user can list orders');
foreach (array('postOrder' => array(null), 'putOrder' => array(1, null), 'deleteOrder' => array(1), 'generateInvoice' => array()) as $method => $args) {
	$forbidden = false;
	try { $api->{$method}(...$args); } catch (Luracast\Restler\RestException $e) { $forbidden = $e->getCode() === 403; }
	verifyNative($forbidden, 'Read-only API user cannot mutate: '.$method);
}
$user->admin = 1; $user->testPermissions = array();
foreach (array('postOrder' => array(null), 'putOrder' => array(1, null), 'deleteOrder' => array(99999)) as $method => $args) {
	$expected = $method === 'deleteOrder' ? 404 : 400;
	$validated = false;
	try { $api->{$method}(...$args); } catch (Luracast\Restler\RestException $e) { $validated = $e->getCode() === $expected; }
	verifyNative($validated, 'Administrator reaches business validation without individual grants: '.$method);
}
$user->socid = 12;
$forbidden = false;
try { $api->getOrders(); } catch (Luracast\Restler\RestException $e) { $forbidden = $e->getCode() === 403; }
verifyNative($forbidden, 'External administrator excluded from API');
$user->socid = 0;
unset($conf->modules['dolistorextract']);
$forbidden = false;
try { $api->getOrders(); } catch (Luracast\Restler\RestException $e) { $forbidden = $e->getCode() === 403; }
verifyNative($forbidden, 'Disabled module excluded from administrator API');
verifyNative($sampleOrder->completePurchaseImport($user, 'fr_FR', array()) === -1, 'Disabled module prevents administrator purchase completion');
verifyNative($sampleOrder->markAsInvoiced(1, dol_now(), $user, 1) === -1, 'Disabled module prevents administrator invoice linkage');
$conf->modules['dolistorextract'] = 1;
$created = new DolistoreOrder($templateDb);
$created->ref = 'DSE-ADMIN-CREATE'; $created->dolistore_order_ref = 'CO-ADMIN';
$created->dolistore_order_date = dol_now(); $created->release_date = dol_now();
$created->status = DolistoreOrder::STATUS_IMPORTED;
$created->customer_name = 'Synthetic administrator test'; $created->customer_email = 'synthetic@example.invalid';
verifyNative($created->create($user, 1) > 0, 'Administrator creates an order without individual grants: '.$created->error);
$created->note_private = 'Synthetic note';
verifyNative($created->update($user, 1) > 0, 'Administrator modifies an order without individual grants: '.$created->error);
$user->admin = 0; $user->testPermissions = array('dolistorextract.order.read' => true);
verifyNative($created->update($user, 1) < 0, 'Standard read-only user cannot modify the same object');
fwrite(STDOUT, 'OK: '.$checks." native assertions including administrator access.\n");
