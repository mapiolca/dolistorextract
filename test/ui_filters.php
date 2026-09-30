<?php
/* Native GETPOST, Form, Translate and SQL regression tests. No ERP/IMAP/SMTP. */
require __DIR__.'/native.php';
require_once __DIR__.'/../class/dolistoreProductIdentity.class.php';
require_once __DIR__.'/../class/dolistoreInvoiceBatch.class.php';
$initialChecks = $checks;
$conf->entity = 1;
$conf->liste_limit = 20;
$conf->browser->name = 'test';
$conf->modules['agenda'] = 1;
$conf->global->MAIN_DISABLE_ALL_MAILS = 1;
$conf->global->MAIN_CHECKBOX_LEFT_COLUMN = 0;
$conf->main_checkbox_left_column = 0;
$user->admin = 0;
$user->testAllowed = true;
$user->conf = new stdClass();
unset($mc);
$db = new NativeSQLiteDB();
$db->db->createFunction('CONCAT', static function (...$args) { return implode('', $args); });
$db->db->createFunction('LPAD', static function ($value, $length, $pad) { return str_pad((string) $value, $length, $pad, STR_PAD_LEFT); });
$db->query('CREATE TABLE test_dolistoreextract_order (rowid INTEGER, entity INTEGER, ref TEXT, dolistore_order_ref TEXT, customer_name TEXT, customer_email TEXT, status INTEGER, fk_facture INTEGER, release_date TEXT, dolistore_order_date TEXT)');
$db->query("INSERT INTO test_dolistoreextract_order VALUES (1,1,'DSE-0','CO-0','Zero','zero@example.invalid',0,NULL,'2020-01-01','2020-01-01'),(2,1,'DSE-1','CO-1','Alpha','alpha@example.invalid',1,NULL,'2020-01-01','2020-01-01'),(3,2,'DSE-2','CO-2','Foreign','foreign@example.invalid',1,NULL,'2020-01-01','2020-01-01')");
$db->query('CREATE TABLE test_dolistoreextract_order_line (rowid INTEGER, entity INTEGER, fk_order INTEGER, product_dolistore_ref TEXT, product_label TEXT, fk_product INTEGER)');
$db->query("INSERT INTO test_dolistoreextract_order_line VALUES (1,1,1,'ABC','Ancien libellé',1),(2,1,2,'a b c','New label',1)");
$db->query('CREATE TABLE test_product (rowid INTEGER, entity INTEGER, label TEXT, ref TEXT)');
$db->query("INSERT INTO test_product VALUES (1,1,'Current product','PROD')");
$db->query('CREATE TABLE test_facture (rowid INTEGER, entity INTEGER, ref TEXT)');
$db->query("INSERT INTO test_facture VALUES (1,1,'FC-0'),(2,1,'FC-1')");
$db->query('CREATE TABLE test_dolistoreextract_invoice_batch (rowid INTEGER, entity INTEGER, fk_facture INTEGER, status INTEGER, email_sent INTEGER, period_year INTEGER, period_month INTEGER)');
$db->query('INSERT INTO test_dolistoreextract_invoice_batch VALUES (1,1,1,0,0,2026,9),(2,1,2,1,1,2026,8),(3,2,NULL,1,0,2026,9)');
$db->query('CREATE TABLE test_dolistoreextract_import_log (rowid INTEGER, entity INTEGER, fk_order INTEGER, source TEXT, level TEXT, message TEXT, datec TEXT)');
$db->query("INSERT INTO test_dolistoreextract_import_log VALUES (1,1,1,'import','success','Order imported','2026-09-10'),(2,1,2,'invoice','warning','Invoice created','2026-09-09'),(3,2,3,'import','success','Private','2026-09-10')");
$db->query('CREATE TABLE test_actioncomm (id INTEGER, entity INTEGER, fk_element INTEGER, elementtype TEXT, label TEXT, percent INTEGER, datep TEXT)');
$db->query("INSERT INTO test_actioncomm VALUES (1,1,1,'dolistoreextract_order@dolistorextract','Order imported',0,'2026-09-10'),(2,1,1,'dolistoreextract_order@dolistorextract','Invoice created',100,'2026-09-09'),(3,2,1,'dolistoreextract_order@dolistorextract','Private',0,'2026-09-10')");
$form = new Form($db);
$productIdentity = new DolistoreProductIdentity($db);
$pendingDolistoreOrders = array(
	array('order_ref'=>'CO-0', 'customer_name'=>'Zero', 'customer_email'=>'zero@example.invalid', 'lang'=>'fr_FR', 'folders'=>array('INBOX'=>true), 'unread_count'=>0),
	array('order_ref'=>'CO-1', 'customer_name'=>'Alpha', 'customer_email'=>'alpha@example.invalid', 'lang'=>'en_US', 'folders'=>array('INBOX'=>true), 'unread_count'=>1),
);
/** Extract actual controller fragments without executing bootstrap or transport. */
function uiSource($file, $start, $end) {
	$source = file_get_contents(__DIR__.'/../'.$file);
	$a = strpos($source, $start);
	$b = $a === false ? false : strpos($source, $end, $a);
	if ($a === false || $b === false) throw new RuntimeException('Controller boundary missing: '.$file);
	return substr($source, $a, $b - $a);
}
function uiIds($query) {
	global $db;
	$result = $db->query($query); $ids = array();
	while (is_object($row = $db->fetch_object($result))) $ids[] = (int) $row->id;
	return $ids;
}
$ordersParams = uiSource('list.php', '$pendingContextPage =', '$pendingOrderReader =');
$ordersWhere = uiSource('list.php', '$filteredPendingOrders =', '$sqlSelect =');
foreach (array(
	array(array(), array(1,2), 2),
	array(array('search_status'=>'-1','search_ref'=>'  ','search_product'=>'  ','search_pending_ref'=>'  ','search_pending_read'=>'-1'), array(1,2), 2),
	array(array('search_status'=>'0'), array(1), 2),
	array(array('search_ref'=>'DSE-1','search_dolistore_ref'=>'CO-1'), array(2), 2),
	array(array('search_product'=>'Current'), array(1,2), 2),
	array(array('search_product'=>'Ancien'), array(1,2), 2),
	array(array('search_pending_read'=>'unread'), array(1,2), 1),
	array(array('search_pending_ref'=>' CO-1 ', 'search_customer'=>' Alpha '), array(2), 1),
	array(array('search_ref'=>'missing','search_pending_ref'=>'CO-1','button_removefilter_x'=>'x','column_contextpage'=>'dolistoreextractorderslist'), array(1,2), 1),
	array(array('search_ref'=>'DSE-1','search_pending_ref'=>'missing','button_removefilter_x'=>'x','column_contextpage'=>'dolistoreextractpendingorderslist'), array(2), 2),
	array(array('search_status'=>'0','limit'=>'50','page'=>'2','button_search_x'=>'x','column_contextpage'=>'dolistoreextractorderslist'), array(1), 2),
	array(array('search_entity'=>array('','-1')), array(1,2), 2),
) as $case) {
	$_GET = array(); $_POST = $case[0];
	eval($ordersParams); eval($ordersWhere);
	verifyNative(uiIds('SELECT o.rowid AS id FROM test_dolistoreextract_order o WHERE '.implode(' AND ', $where).' ORDER BY o.rowid') === $case[1], 'Order filters '.json_encode($case[0]));
	verifyNative(count($filteredPendingOrders) === $case[2], 'Pending filters '.json_encode($case[0]));
	parse_str(ltrim($param, '&'), $urlParams);
	verifyNative(!isset($urlParams['search_status']) || $urlParams['search_status'] !== '-1', 'Empty status omitted from links');
	if (!empty($case[0]['button_removefilter_x'])) {
		$removed = $case[0]['column_contextpage'] === $ordersContextPage ? 'search_ref' : 'search_pending_ref';
		verifyNative(!isset($urlParams[$removed]), 'Reset filters stay removed in pagination');
	}
	if (isset($case[0]['limit'])) verifyNative($limit === 50 && $page === 0 && $offset === 0, 'Native limit and search page reset');
}
$logOrder = null;
$sourceOptions = DolistoreImportLog::SOURCE_KEYS;
$statusOptions = DolistoreImportLog::STATUS_KEYS;
foreach (array('invoices.php','importlogs.php','agenda.php','dashboard.php') as $file) {
	$fragments = array(
		'invoices.php' => array('$search_period =', '$sqlFrom ='),
		'importlogs.php' => array('$search_date_start =', '$sqlFrom ='),
		'agenda.php' => array('$search_label =', '$from ='),
		'dashboard.php' => array('$dateStart =', '$monthStart ='),
	);
	foreach (array('empty','blank','zero','text','reset') as $scenario) {
		$_GET = array(); $_POST = array(); $page = 2; $limit = 20;
		if ($scenario === 'blank') $_POST = array('search_status'=>'-1','search_level'=>'-1','search_source'=>'-1','search_invoice'=>'  ','search_order'=>'  ','search_message'=>'  ','search_label'=>'  ','search_product'=>'  ','search_entity'=>array('','-1'));
		if ($scenario === 'zero') $_POST = array('search_status'=>'0');
		if ($scenario === 'text') $_POST = array('search_invoice'=>'FC-1','search_order'=>'DSE-0','search_message'=>'imported','search_label'=>'imported','search_product'=>'Current');
		if ($scenario === 'reset') $_POST = array('search_invoice'=>'missing','search_order'=>'missing','search_label'=>'missing','button_removefilter_x'=>'x');
		$object = new DolistoreOrder($db); $object->id = 1; $object->entity = 1;
		eval(uiSource($file, $fragments[$file][0], $fragments[$file][1]));
		if ($file === 'invoices.php') $query = 'SELECT b.rowid AS id FROM test_dolistoreextract_invoice_batch b LEFT JOIN test_facture f ON f.rowid=b.fk_facture WHERE '.implode(' AND ', $where).' ORDER BY b.rowid';
		elseif ($file === 'importlogs.php') $query = 'SELECT l.rowid AS id FROM test_dolistoreextract_import_log l LEFT JOIN test_dolistoreextract_order o ON o.rowid=l.fk_order WHERE '.implode(' AND ', $where).' ORDER BY l.rowid';
		elseif ($file === 'agenda.php') $query = 'SELECT a.id FROM test_actioncomm a'.$where.' ORDER BY a.id';
		else $query = 'SELECT o.rowid AS id FROM test_dolistoreextract_order o WHERE '.$entityWhereAlias.' ORDER BY o.rowid';
		$expected = array(1,2);
		if ($scenario === 'zero' && in_array($file, array('invoices.php','dashboard.php'), true)) $expected = array(1);
		if ($scenario === 'text' && $file !== 'dashboard.php') $expected = $file === 'invoices.php' ? array(2) : array(1);
		verifyNative(uiIds($query) === $expected, $file.' '.$scenario.' native SQL');
	}
}
// Render the actual two-table controls, using native helpers and both action positions.
$_POST = array('search_pending_ref'=>'CO-1','search_status'=>'0','limit'=>'50'); $_GET = array();
foreach (array(0,1) as $left) {
	$conf->main_checkbox_left_column = $left;
	eval($ordersParams); eval($ordersWhere);
	$num = 2;
	ob_start(); eval(uiSource('list.php', "print '<form method=\"POST\" id=\"pendingordersfilter\"", '$orderRows = array();')); print '</table></div></form>'; $html = ob_get_clean();
	$dom = new DOMDocument(); $dom->loadHTML('<!doctype html><meta charset="utf-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new DOMXPath($dom);
	verifyNative($xpath->query('//select[@id="limit"]/ancestor::form[@id="ordersfilter"]')->length === 1, 'Limit belongs to orders form');
	verifyNative($xpath->query('//form[@id="ordersfilter"]//input[@name="limit"]')->length === 0, 'No conflicting hidden limit in orders form');
	verifyNative($xpath->query('//form[@id="ordersfilter"]//input[@name="search_pending_ref" and @value="CO-1"]')->length === 1, 'Pending filter survives orders submit');
	verifyNative($xpath->query('//form[@id="pendingordersfilter"]//input[@name="search_status" and @value="0"]')->length === 1, 'Draft filter survives pending submit');
	verifyNative($xpath->query('//table[@id="pendingorders"]/parent::div[@class="div-table-responsive-no-min"]')->length === 1, 'Embedded table has no reserved height');
	verifyNative($xpath->query('//table[contains(@class,"tagtable")]')->length === 2, 'Native table classes');
	foreach (array('pendingorders','dolistoreorders') as $table) {
		$position = $left ? '1' : 'last()';
		verifyNative($xpath->query('//table[@id="'.$table.'"]//tr[@class="liste_titre_filter"]/td['.$position.']//button[@name="button_search_x"]')->length === 1, 'Native action column '.$table.' '.$left);
	}
}
// Empty tables and totals keep column alignment, including hidden/disabled columns.
foreach (array(0, 1) as $left) {
	$fields = array('ref'=>array('label'=>'Ref','checked'=>1,'enabled'=>1), 'amount'=>array('label'=>'AmountHT','checked'=>1,'enabled'=>1,'align'=>'right'), 'entity'=>array('label'=>'DolistoreEnvironment','checked'=>1,'enabled'=>0));
	ob_start(); print '<table>'; dolistoreextractPrintNoRecordLine(dolistoreextractVisibleColumnCount($fields, 1)); dolistoreextractPrintTotalRow($fields, array('amount'=>'12,34'), 1, (bool) $left); print '</table>'; $html = ob_get_clean();
	$dom = new DOMDocument(); $dom->loadHTML($html); $xpath = new DOMXPath($dom);
	verifyNative($xpath->query('//td[@colspan="3"]')->length === 1, 'Empty row visible column count');
	verifyNative($xpath->query('//tr[@class="liste_total"]/td')->length === 3, 'Totals exclude disabled columns');
	verifyNative($xpath->query('//tr[@class="liste_total"]/td['.($left ? '3' : '2').']')->item(0)->textContent === '12,34', 'Totals follow action column position');
}
// Standalone mailbox filters run against synthetic rows, without reading IMAP.
foreach (array(
	array(array(), 2),
	array(array('search_mail_ref'=>'  ', 'search_mail_read'=>'-1'), 2),
	array(array('search_mail_ref'=>' CO-1 '), 1),
	array(array('search_mail_read'=>'unread'), 1),
	array(array('search_mail_ref'=>'missing','button_removefilter_x'=>'x'), 2),
) as $case) {
	$_POST = $case[0]; $_GET = array();
	eval(uiSource('mails.php', '$searchMailFolder =', '$emails ='));
	$mailRows = array(); $unreadMessages = 0;
	$syntheticRows = array(array('folder'=>'INBOX','order_ref'=>'CO-0','order_id'=>'0','lang'=>'fr_FR','company'=>'Zero','email'=>'zero@example.invalid','is_unread'=>false),array('folder'=>'INBOX','order_ref'=>'CO-1','order_id'=>'1','lang'=>'en_US','company'=>'Alpha','email'=>'alpha@example.invalid','is_unread'=>true));
	eval('foreach ($syntheticRows as $row) {'.uiSource('mails.php', "\tif ($"."searchMailFolder !== ''", '$overallMessages ='));
	verifyNative(count($mailRows) === $case[1], 'Standalone mailbox blank/search/reset');
}
$object = new DolistoreOrder($db); $object->id = 1; $object->entity = 1;
$head = dolistoreextractOrderPrepareHead($object);
$html = dol_get_fiche_head($head, 'card', $langs->trans('DolistoreOrder'), -1, $object->picto);
verifyNative($head[0][1] === $langs->trans('DolistoreOrderCard') && substr_count($html, 'imgTabTitle') === 1 && strpos($html, 'dolistore.png') !== false, 'Only native DoliStore pictogram in card tab');
// Translate the production welcome label expression with all 25 language combinations.
$source = file_get_contents(__DIR__.'/../admin/setup.php');
$labelStart = strpos($source, '$langs->trans(\'DolistoreWelcomeTemplate\',');
$labelEnd = $labelStart === false ? false : strpos($source, ', $constant', $labelStart);
if ($labelStart === false || $labelEnd === false) throw new RuntimeException('Welcome label expression not found');
$expression = substr($source, $labelStart, $labelEnd - $labelStart);
foreach (array_keys(DolistoreWelcomeMail::LANGUAGES) as $uiLanguage) {
	$langs = new Translate('', $conf); $langs->setDefaultLang($uiLanguage); $langs->loadLangs(array('main','languages','dolistorextract@dolistorextract'));
	foreach (DolistoreWelcomeMail::LANGUAGES as $language => $suffix) {
		$label = eval('return '.$expression.';');
		verifyNative(strpos($label, 'Dolistore') === false && strpos($label, 'Language_') === false && strpos($label, '%s') === false && strpos($label, $langs->trans('Language_'.$language)) !== false, 'Welcome label '.$uiLanguage.'/'.$language.': '.$label);
	}
}
fwrite(STDOUT, 'OK: '.($checks - $initialChecks)." UI/filter assertions with native helpers and synthetic SQLite data; no live ERP or mail transport.\n");
