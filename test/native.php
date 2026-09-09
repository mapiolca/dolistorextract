<?php
/* Native library regression test. Synthetic objects, no ERP configuration or SMTP. */
$root = getenv('DOLIBARR_TEST_ROOT');
if (!$root || !is_file($root.'/core/lib/functions.lib.php')) exit(2);
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_DOCUMENT_ROOT_ALT', dirname(__DIR__, 2));
define('DOL_URL_ROOT', '');
define('TCPDF_PATH', $root.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', $root.'/includes/tcpdi/');
define('DOL_DATA_ROOT', __DIR__.'/.tmp');
define('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: '25.0.0-alpha');
define('DOL_MAIN_URL_ROOT', 'http://localhost');
define('MAIN_DB_PREFIX', 'test_');
$dolibarr_main_url_root = 'http://localhost';
$_SERVER['PHP_SELF'] = '/dolistorextract/test/native.php';
$_SESSION = array('dol_tz_string' => 'Europe/Paris');
require_once $root.'/core/class/conf.class.php';
require_once $root.'/core/lib/functions.lib.php';
require_once $root.'/core/lib/functions2.lib.php';
if (!function_exists('dol_escape_htmltag')) require_once $root.'/core/lib/html.lib.php';
require_once $root.'/core/class/translate.class.php';
require_once $root.'/core/class/hookmanager.class.php';
require_once $root.'/core/class/extrafields.class.php';
require_once $root.'/societe/class/societe.class.php';
require_once $root.'/user/class/user.class.php';
$conf = new Conf();
$conf->entity = 1;
$conf->currency = 'EUR';
$conf->file->dol_document_root = array($root, dirname(__DIR__, 2));
$conf->file->dol_url_root = array('', '');
$conf->file->instance_unique_id = 'synthetic-test';
$conf->global = (object) array('MAIN_DISABLE_ALL_MAILS' => 1, 'MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2, 'MAIN_PDF_FREETEXT' => '', 'MAIN_INFO_SOCIETE_NOM' => 'Société de test', 'MAIN_INFO_SOCIETE_COUNTRY' => '1:FR:France', 'MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS' => 0, 'MAIN_PDF_COMPRESSION' => 0);
$conf->modules = array('dolistorextract' => 1);
$conf->dolistorextract = (object) array('enabled' => 1, 'dir_output' => DOL_DATA_ROOT.'/entity1', 'multidir_output' => array(1 => DOL_DATA_ROOT.'/entity1', 2 => DOL_DATA_ROOT.'/entity2'));
$conf->modules_parts['substitutions'] = array('/dolistorextract/core/substitutions/');
class NativeTestDB {
	public $type = 'mysqli';
	public function prefix() { return MAIN_DB_PREFIX; }
	public function free($result) {}
	public function num_rows($result) { return $result->count(); }
	public function order($field, $direction) { return ' ORDER BY '.$field.' '.$direction; }
	public function escape($value) { return addslashes($value); }
	public function query($sql) { if (strpos($sql, 'c_paper_format') !== false) return new ArrayIterator(array((object) array('width' => 210, 'height' => 297, 'unit' => 'mm'))); if (stripos(ltrim($sql), 'SELECT') === 0) return new ArrayIterator(array()); throw new RuntimeException('Write forbidden in native rendering test'); }
	public function fetch_object($result) { $value = $result->current(); $result->next(); return $value; }
}
$db = new NativeTestDB();
$hookmanager = new HookManager($db);
class NativeTestUser extends User {
	public $testAllowed = true;
	public function hasRight($module, $level1, $level2 = null) { return $this->testAllowed; }
}
$user = new NativeTestUser($db);
$user->login = 'test'; $user->email = 'actor@example.invalid'; $user->signature = ''; $user->civility_code = '';
$user->id = 10; $user->socid = 0; $user->firstname = 'Test'; $user->lastname = 'User';
$langs = new Translate('', $conf); $langs->setDefaultLang('fr_FR'); $langs->loadLangs(array('main', 'errors', 'dict', 'companies', 'dolistorextract@dolistorextract'));
$mysoc = new Societe($db); $mysoc->setMysoc($conf);
require_once __DIR__.'/../class/dolistoreOrder.class.php';
require_once __DIR__.'/../core/substitutions/functions_dolistorextract.lib.php';
require_once __DIR__.'/../core/modules/dolistoreextract/doc/pdf_standard.modules.php';
class NativeTestOrder extends DolistoreOrder {
	public $testLines = array();
	public function getGroupedLinesForDisplay() { return $this->testLines; }
}
$order = new NativeTestOrder($db);
$order->id = 1; $order->entity = 1; $order->ref = 'DSE-TEST'; $order->dolistore_order_ref = 'CO-TEST';
$order->customer_name = 'Élodie <script>alert(1)</script> & Fils'; $order->customer_email = 'synthetic@example.invalid';
$order->currency_code = 'EUR'; $order->status = 1; $order->dolistore_order_date = dol_now(); $order->release_date = dol_now();
$order->total_ht = 1234.56; $order->billable_total_ht = 987.654;
$order->context['purchase_firstname'] = 'Élodie'; $order->context['purchase_lastname'] = 'Müller';
$checks = 0;
function verifyNative($condition, $message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
foreach (array('fr_FR', 'en_US', 'es_ES', 'it_IT', 'de_DE') as $language) {
	$output = new Translate('', $conf); $output->setDefaultLang($language); $output->loadLangs(array('main', 'companies', 'dolistorextract@dolistorextract'));
	$output->cache_currencies['EUR'] = array('label' => 'Euro', 'unicode' => array('8364'));
	$substitutions = array(); dolistorextract_completesubstitutionarray($substitutions, $output, $order);
	verifyNative($substitutions['__DOLISTOREEXTRACT_ORDER_STATUS__'] !== 'DolistoreOrderStatusImported', 'Native language loading '.$language);
	verifyNative($substitutions['__DOLISTORE_INVOICE_FIRSTNAME__'] === 'Élodie', 'Legacy substitution '.$language);
	$html = file_get_contents(__DIR__.'/../core/tpl/welcome/'.$language.'.html');
	$clean = dol_htmlwithnojs($html, 1);
	verifyNative(substr_count($clean, 'PRODUCTS_START') === 1, 'Product block must not repeat '.$language);
	verifyNative(substr_count($clean, '<h2') === 4 && strpos($clean, 'mailto:contact@lesmetiersdubatiment.fr') !== false, 'Native HTML cleaning preserves content '.$language);
}
verifyNative(dolistoreextractGetOrderUploadDir($order) === DOL_DATA_ROOT.'/entity1/DSE-TEST', 'Owner directory');
$mc = new class { public function getEntity($element, $shared, $object) { return '1,2'; } };
$order->entity = 2;
verifyNative(dolistoreextractGetOrderUploadDir($order) === DOL_DATA_ROOT.'/entity2/DSE-TEST', 'Shared owner directory');
unset($conf->dolistorextract->multidir_output[2]);
verifyNative(dolistoreextractGetOrderUploadDir($order) === '', 'Missing owner directory must fail closed');
$order->entity = 1; $order->error = '';
foreach (array('short' => '', 'long' => str_repeat('Texte libre avec accents : évolution, Prüfung, actualización, società. ', 12)) as $case => $footer) {
	$conf->global->MAIN_PDF_FREETEXT = $footer;
	$order->ref = 'DSE-TEST-'.$case;
	$order->testLines = array_fill(0, 45, array('product_dolistore_ref' => 'ABC123', 'product_label' => str_repeat('Libellé très long de service — ÄÖÜ ß / español / italiano. ', 3), 'qty' => 1.25, 'total_ht' => 12.345, 'billable_total_ht' => 9.876));
	$order->note_public = str_repeat('Une note publique longue qui doit rester intégralement lisible. ', 80).' FIN-DE-NOTE';
	$model = new pdf_standard($db);
	verifyNative($model->write_file($order, $langs) === 1, 'PDF '.$case.': '.$model->error);
}
fwrite(STDOUT, 'OK: '.$checks." native assertions; PDF outputs in test/.tmp/entity1.\n");

// Exercise real FormMail / Translate / substitutions without invoking CMailFile::sendfile.
require_once __DIR__.'/../class/dolistoreWelcomeMail.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/sqlite3.class.php';
class NativeSQLiteDB extends DoliDBSqlite3 {
	// Core SQLite constructor registers unsupported functions in this checkout.
	public function __construct() { $this->db = new SQLite3(':memory:'); $this->connected = true; $this->ok = true; }
	public function query($query, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		if (strpos($query, 'GET_LOCK(') !== false || strpos($query, 'RELEASE_LOCK(') !== false) return new ArrayIterator(array((object) array('acquired' => 1)));
		if (stripos(ltrim($query), 'SELECT') !== 0) { if (!$this->db->exec($query)) throw new RuntimeException($this->db->lastErrorMsg()); return new ArrayIterator(array()); }
		$result = $this->db->query($query);
		if (!$result) throw new RuntimeException($this->db->lastErrorMsg());
		$rows = array();
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = (object) $row;
		return new ArrayIterator($rows);
	}
	public function num_rows($resultset) { return $resultset->count(); }
	public function free($resultset = null) {}
	public function fetch_object($resultset) { $value = $resultset->current(); $resultset->next(); return $value; }
}
$templateDb = new NativeSQLiteDB();
$templateDb->query('CREATE TABLE test_dolistoreextract_order (rowid INTEGER PRIMARY KEY, entity INTEGER, ref TEXT, dolistore_order_ref TEXT, customer_name TEXT, customer_email TEXT, status INTEGER, billable_total_ht REAL, currency_code TEXT, fk_soc_customer INTEGER, dolistore_order_date TEXT, release_date TEXT, invoice_date TEXT, email_date TEXT, datec TEXT)');
$templateDb->query("INSERT INTO test_dolistoreextract_order VALUES (1,1,'DSE-TEST','CO-TEST','Élodie <img src=x onerror=alert(1)> & Fils','buyer@example.invalid',1,123.45,'EUR',0,NULL,NULL,NULL,NULL,NULL)");
$templateDb->query('CREATE TABLE test_dolistoreextract_order_line (rowid INTEGER PRIMARY KEY, fk_order INTEGER, entity INTEGER)');
$templateDb->query('CREATE TABLE test_c_email_templates (rowid INTEGER PRIMARY KEY, entity INTEGER, module TEXT, label TEXT, type_template TEXT, topic TEXT, email_from TEXT, joinfiles INTEGER, content TEXT, content_lines TEXT, lang TEXT, email_to TEXT, email_tocc TEXT, email_tobcc TEXT, private INTEGER, active INTEGER, position INTEGER, fk_user INTEGER, defaultfortype INTEGER)');
$delivery = new DolistoreWelcomeMail($templateDb);
$prepare = new ReflectionMethod(DolistoreWelcomeMail::class, 'prepare');
$conf->global->MAIN_DISABLE_ALL_MAILS = 0; // Transport is never called; preparation only.
$conf->global->MAIN_MAIL_EMAIL_FROM = 'sender@example.invalid';
$i = 0;
foreach (DolistoreWelcomeMail::LANGUAGES as $language => $suffix) {
	$i++;
	$content = file_get_contents(__DIR__.'/../core/tpl/welcome/'.$language.'.html');
	$templateDb->query("INSERT INTO test_c_email_templates VALUES ($i,1,'dolistorextract','Synthetic','dolistore_extract','Subject','',0,'".$templateDb->escape($content)."','','$language','','','',0,1,10,0,1)");
	$conf->global->{'DOLISTOREXTRACT_EMAIL_TEMPLATE_'.$suffix} = $i;
	$request = (object) array('fk_order' => 1, 'lang' => $language, 'snapshot_firstname' => 'Élodie', 'snapshot_lastname' => 'Müller');
	$message = $prepare->invoke($delivery, $request, $user);
	verifyNative(substr_count($message['html'], '<h2') === 4 && $message['to'] === 'buyer@example.invalid' && $message['from'] === 'sender@example.invalid', 'Native welcome preparation '.$language);
}
$request->lang = 'fr_FR';
$templateDb->query("UPDATE test_c_email_templates SET content='<p>__DOLISTOREEXTRACT_ORDER_CUSTOMER_NAME__</p>' WHERE rowid=1");
$message = $prepare->invoke($delivery, $request, $user);
verifyNative(strpos($message['html'], '<img src=x') === false && strpos($message['html'], '&amp; Fils') !== false, 'Customer substitution is HTML-escaped');
foreach (array("type_template='facture_send'", "type_template='dolistore_extract', active=0", "active=1, lang='en_US'", "lang='fr_FR', entity=9", "entity=1, private=1") as $change) {
	$templateDb->query('UPDATE test_c_email_templates SET '.$change.' WHERE rowid=1');
	$failed = false;
	try { $prepare->invoke($delivery, $request, $user); } catch (RuntimeException $e) { $failed = $delivery->error === 'DolistoreWelcomeTemplateUnavailable'; }
	verifyNative($failed, 'Incompatible configured template rejected: '.$change);
}
$conf->global->MAIN_DISABLE_ALL_MAILS = 1;
fwrite(STDOUT, 'OK: '.$checks." total native assertions; no real recipient or SMTP call.\n");

// Native field validation and amount calculation on complete synthetic objects.
$db = $templateDb;
$templateDb->query('CREATE TABLE test_c_tva (taux REAL, localtax1 REAL, localtax2 REAL, localtax1_type INTEGER, localtax2_type INTEGER, fk_pays INTEGER, entity INTEGER)');
$templateDb->query('INSERT INTO test_c_tva VALUES (20,0,0,0,0,1,1)');
$sampleOrder = new DolistoreOrder($templateDb);
$existingColumns = array('rowid','entity','ref','dolistore_order_ref','customer_name','customer_email','status','billable_total_ht','currency_code','fk_soc_customer','dolistore_order_date','release_date','invoice_date','email_date','datec');
foreach ($sampleOrder->fields as $field => $definition) if (!in_array($field, $existingColumns, true)) $templateDb->query('ALTER TABLE test_dolistoreextract_order ADD COLUMN '.$field.' TEXT');
$sampleOrder->ref = 'DSE-NATIVE-CREATE'; $sampleOrder->dolistore_order_ref = 'CO-NATIVE';
$sampleOrder->dolistore_order_date = dol_now(); $sampleOrder->release_date = dol_now();
$sampleOrder->status = DolistoreOrder::STATUS_IMPORTED;
$sampleOrder->customer_name = 'Synthetic'; $sampleOrder->customer_email = 'synthetic@example.invalid';
verifyNative($sampleOrder->create($user, 1) > 0, 'Native object creation: '.$sampleOrder->error);
$sampleLine = new DolistoreOrderLine($templateDb);
foreach ($sampleLine->fields as $field => $definition) if (!in_array($field, array('rowid','fk_order','entity'), true)) $templateDb->query('ALTER TABLE test_dolistoreextract_order_line ADD COLUMN '.$field.' TEXT');
$sampleLine->fk_order = $sampleOrder->id; $sampleLine->product_label = 'Synthetic product'; $sampleLine->product_dolistore_ref = 'TEST';
$sampleLine->qty = 2; $sampleLine->unit_price_ht = 12.34567; $sampleLine->tax_rate = 20;
$sampleLine->billable_unit_price_ht = 10.12345;
verifyNative($sampleLine->create($user) > 0, 'Native line creation and price calculation: '.$sampleLine->error);
verifyNative($sampleOrder->updateTotalsFromLines($user) > 0, 'Uninitialized native error is not a totals failure: '.$sampleOrder->error);
verifyNative(abs((float) $sampleOrder->total_ht - (float) price2num(2 * 12.34567, 'MT')) < 0.000001, 'Native total precision retained');
$invalid = clone $sampleLine; $invalid->id = 0; $invalid->fk_order = 99999;
verifyNative($invalid->create($user) < 0, 'Missing parent rejected by line business boundary');
$invalid = clone $sampleOrder; $invalid->id = 0; $invalid->ref = str_repeat('R', 200);
verifyNative($invalid->create($user, 1) < 0, 'Native metadata rejects oversized reference');
fwrite(STDOUT, 'OK: '.$checks." total native assertions including object and line writes on synthetic SQLite data.\n");

// Real filesystem relocation and native ECM metadata reconciliation.
require_once __DIR__.'/../class/dolistoreDocumentMigration.class.php';
$ecmSql = file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_ecm_files.sql');
$ecmSql = preg_replace('/--[^\n]*/', '', $ecmSql);
$ecmSql = str_replace(array('llx_ecm_files', 'AUTO_INCREMENT', ' ON UPDATE CURRENT_TIMESTAMP', ' ENGINE=innodb'), array('test_ecm_files', '', '', ''), $ecmSql);
$templateDb->query($ecmSql);
$oldPath = DOL_DATA_ROOT.'/entity1/dolistoreextract_order/'.$sampleOrder->ref;
$newPath = DOL_DATA_ROOT.'/entity1/'.$sampleOrder->ref;
if (is_dir($oldPath)) dol_delete_dir_recursive($oldPath);
if (is_dir($newPath)) dol_delete_dir_recursive($newPath);
dol_mkdir($oldPath); dol_mkdir($newPath);
file_put_contents($oldPath.'/source.eml', 'Synthetic mail, no personal data.');
file_put_contents($newPath.'/source.eml', 'Existing destination to preserve.');
$migration = new DolistoreDocumentMigration($templateDb);
verifyNative($migration->run($user) < 0 && file_get_contents($newPath.'/source.eml') === 'Existing destination to preserve.' && is_file($oldPath.'/source.eml'), 'Document collision fails before any move');
dol_delete_file($newPath.'/source.eml', 0, 0, 0);
$templateDb->query("UPDATE test_dolistoreextract_order SET last_main_doc='entity1/dolistoreextract_order/".$sampleOrder->ref."/source.eml' WHERE rowid=".(int) $sampleOrder->id);
verifyNative($migration->run($user) === 1 && is_file($newPath.'/source.eml') && !file_exists($oldPath.'/source.eml'), 'Native document relocation: '.$migration->error);
$indexed = $templateDb->fetch_object($templateDb->query("SELECT * FROM test_ecm_files WHERE filename='source.eml'"));
verifyNative(is_object($indexed) && $indexed->filepath === 'entity1/'.$sampleOrder->ref && (int) $indexed->entity === 1, 'Native ECM index follows owner directory');
verifyNative($migration->run($user) === 0, 'Document migration replay');
// Emulate interruption after the file move, before ECM indexing or pointer update.
$templateDb->query("DELETE FROM test_ecm_files WHERE filename='source.eml'");
$templateDb->query("UPDATE test_dolistoreextract_order SET last_main_doc='entity1/dolistoreextract_order/".$sampleOrder->ref."/source.eml' WHERE rowid=".(int) $sampleOrder->id);
verifyNative($migration->run($user) === 0, 'Interrupted migration reconciles without another move');
$pointer = $templateDb->fetch_object($templateDb->query('SELECT last_main_doc FROM test_dolistoreextract_order WHERE rowid='.(int) $sampleOrder->id));
$indexed = $templateDb->fetch_object($templateDb->query("SELECT * FROM test_ecm_files WHERE filename='source.eml'"));
verifyNative(is_object($indexed) && $pointer->last_main_doc === 'entity1/'.$sampleOrder->ref.'/source.eml', 'Interrupted ECM index and last_main_doc repaired');
fwrite(STDOUT, 'OK: '.$checks." total native assertions including document migration.\n");

// Native link markup and restrictedArea extension with partial rights/entities.
require_once __DIR__.'/../class/actions_dolistorextract.class.php';
$conf->global->MAIN_ENABLE_AJAX_TOOLTIP = 1;
$link = $sampleOrder->getNomUrl(1);
verifyNative(strpos($link, 'classforajaxtooltip') !== false && strpos($link, 'pictofixedwidth') !== false && strpos($link, 'dolistoreextract_order@dolistorextract') !== false, 'Native Ajax link has resolver and small pictogram');
$access = new ActionsDolistorextract($templateDb);
$parameters = array('features'=>'dolistorextract','tableandshare'=>'dolistoreextract_order','objectid'=>$sampleOrder->id);
$action = ''; $unused = null;
$access->restrictedArea($parameters, $unused, $action, $hookmanager);
verifyNative($access->results['result'] === 1, 'Native access hook accepts authorized order');
$user->testAllowed = false; $user->admin = 1;
$access->restrictedArea($parameters, $unused, $action, $hookmanager);
verifyNative($access->results['result'] === 0 && $sampleOrder->getNomUrl(1) === '' && $sampleOrder->getTooltipContentArray(array()) === array(), 'Administrator without functional rights denied');
$user->testAllowed = true; $user->socid = 12;
$access->restrictedArea($parameters, $unused, $action, $hookmanager);
verifyNative($access->results['result'] === 0, 'External user denied by native access hook');
$user->socid = 0;
$templateDb->query('UPDATE test_dolistoreextract_order SET entity=9 WHERE rowid='.(int) $sampleOrder->id);
$access->restrictedArea($parameters, $unused, $action, $hookmanager);
verifyNative($access->results['result'] === 0, 'Unauthorized entity denied by native access hook');
$parameters['features'] = 'facture';
verifyNative($access->restrictedArea($parameters, $unused, $action, $hookmanager) === 0, 'Access hook does not override another module');
fwrite(STDOUT, 'OK: '.$checks." total native assertions including tooltip and access hook.\n");
