<?php
/* Focused regression tests. No SMTP transport or configured ERP is loaded. */
$root = getenv('DOLIBARR_TEST_ROOT');
if (!$root || !is_file($root.'/core/class/commonobject.class.php')) {
	fwrite(STDERR, "Set DOLIBARR_TEST_ROOT to a readable Dolibarr htdocs tree.\n");
	exit(2);
}
define('DOL_DOCUMENT_ROOT', $root);
define('MAIN_DB_PREFIX', 'test_');
$now = 1800000000;
$settings = array();
$conf = (object) array('entity' => 1);
function isModEnabled($module) { return $module === 'dolistorextract'; }
function getDolGlobalInt($name, $default = 0) { global $settings; return (int) ($settings[$name] ?? $default); }
function getEntity($table, $sharing = 1, $object = null) { global $conf; return (string) $conf->entity; }
function dol_now() { global $now; return $now; }
function dol_strtolower($value) { return mb_strtolower($value, 'UTF-8'); }
function dol_syslog($message, $level = 0) {}
function dol_escape_htmltag($value, $remove = 0) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function natural_search($fields, $value) { return '('.implode(' OR ', array_map(static function ($field) use ($value) { return $field." LIKE '%".str_replace("'", "''", $value)."%'"; }, (array) $fields)).')'; }
class TestLangs {
	public function load($catalog) {}
	public function trans($key, ...$args) { return $key; }
	public function transnoentities($key, ...$args) { return $key; }
}
$langs = new TestLangs();
class Actor {
	public $id = 10;
	public $socid = 0;
	public $admin = 0;
	public $allowed = true;
	public function hasRight(...$args) { return $this->allowed; }
}
class TestDB {
	public $pdo;
	public $transaction_opened = 0;
	public $error = '';
	public function __construct() {
		$this->pdo = class_exists('Pdo\\Sqlite') ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$register = method_exists($this->pdo, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
		$this->pdo->$register('CONCAT', static function (...$parts) { return implode('', $parts); });
		$this->pdo->$register('LOWER', static function ($value) { return dol_strtolower($value); });
	}
	public function query($sql) {
		// SQLite dialect adapter only; queue logic and SQL predicates are production code.
		$sql = str_replace(' FROM DUAL WHERE', ' WHERE', $sql);
		$sql = str_replace(' ON DUPLICATE KEY UPDATE fk_order = fk_order', ' ON CONFLICT(entity, fk_order) DO NOTHING', $sql);
		try { return $this->pdo->query($sql); } catch (Throwable $e) { $this->error = $e->getMessage(); throw $e; }
	}
	public function DDLDescTable($table, $field = '') { return $this->query("SELECT name FROM pragma_table_info('".$this->escape($table)."') WHERE name='".$this->escape($field)."'"); }
	public function DDLAddField($table, $field, $definition) { $this->query('ALTER TABLE '.$table.' ADD COLUMN '.$field.' TEXT'); return 1; }

	public function escape($value) { return substr($this->pdo->quote((string) $value), 1, -1); }
	public function sanitize($value) { return preg_replace('/[^0-9,]/', '', $value); }
	public function fetch_object($result) { return $result->fetchObject(); }
	public function free($result) {}
	public function num_rows($result) { return count($result->fetchAll()); }
	public function affected_rows($result) { return $result->rowCount(); }
	public function last_insert_id($table) { return $this->pdo->lastInsertId(); }
	public function lasterror() { return $this->error; }
	public function plimit($limit, $offset = 0) { return ' LIMIT '.(int) $limit.' OFFSET '.(int) $offset; }
	public function idate($stamp) { return gmdate('Y-m-d H:i:s', (int) $stamp); }
	public function jdate($date) { return strtotime($date.' UTC'); }
	public function begin() { if ($this->transaction_opened++ === 0) $this->pdo->beginTransaction(); return 1; }
	public function commit() { if (--$this->transaction_opened === 0) $this->pdo->commit(); return 1; }
	public function rollback() { if (--$this->transaction_opened === 0) $this->pdo->rollBack(); return 1; }
}
require_once __DIR__.'/../class/dolistoreWelcomeMail.class.php';
require_once __DIR__.'/../class/dolistoreProductIdentity.class.php';
class TestDelivery extends DolistoreWelcomeMail {
	public $mode = 'success';
	public $delivered = 0;
	public $duringTransport;
	protected function prepare($row, $user): array {
		if ($this->mode === 'preflight_failure') {
			$this->error = 'DolistoreWelcomeTemplateUnavailable';
			throw new RuntimeException('preflight');
		}
		return array('lang' => $row->lang);
	}
	protected function transmit(array $message): bool {
		$this->delivered++;
		if ($this->duringTransport) ($this->duringTransport)();
		if ($this->mode === 'exception') throw new RuntimeException('SMTP outcome unknown');
		return $this->mode !== 'uncertain';
	}
}
$checks = 0;
function check($condition, $message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); }
$db = new TestDB();
$db->query('CREATE TABLE test_dolistoreextract_welcome (rowid INTEGER PRIMARY KEY, entity INTEGER, fk_order INTEGER, lang TEXT, snapshot_firstname TEXT, snapshot_lastname TEXT, status TEXT, attempts INTEGER DEFAULT 0, first_failure TEXT, next_attempt TEXT, started_at TEXT, sent_at TEXT, last_error TEXT, lock_token TEXT, date_creation TEXT, fk_user_creat INTEGER, UNIQUE(entity,fk_order))');
$db->query('CREATE TABLE test_dolistoreextract_import_log (rowid INTEGER PRIMARY KEY, entity INTEGER, fk_order INTEGER, fk_invoice_batch INTEGER, source TEXT, level TEXT, message TEXT, context TEXT, datec TEXT, fk_user_creat INTEGER)');
$actor = new Actor();
$welcome = new TestDelivery($db);
$order = new DolistoreOrder($db);
$order->id = 1; $order->entity = 1;
$order->context = array('trigger_reason' => 'purchase_import_complete', 'purchase_lang' => 'fr_FR');
check($welcome->enqueue($order, $actor) === -1, 'Queue must be transactional');
$db->begin(); check($welcome->enqueue($order, $actor) === 1, 'Queue new purchase');
check($welcome->process($actor) === -1 && $welcome->delivered === 0, 'Never send before commit');
$db->rollback(); check($welcome->process($actor) === 0, 'Rollback leaves no delivery');
$db->begin(); $welcome->enqueue($order, $actor); $db->commit();
$otherWorker = new TestDelivery($db);
$welcome->duringTransport = static function () use ($otherWorker, $actor) { check($otherWorker->process($actor) === 0, 'Concurrent worker cannot claim sending request'); };
check($welcome->process($actor) === 1, 'Delivery after commit');
$db->begin(); $welcome->enqueue($order, $actor); $db->commit();
check($welcome->process($actor) === 0 && $welcome->delivered === 1, 'Duplicate purchase never resends');
$welcome->duringTransport = null;
foreach (array('fr_FR', 'en_US', 'es_ES', 'it_IT', 'de_DE', 'unknown') as $language) {
	$order->id++; $order->context['purchase_lang'] = $language;
	$db->begin(); $welcome->enqueue($order, $actor); $db->commit();
	$row = $db->fetch_object($db->query('SELECT lang FROM test_dolistoreextract_welcome WHERE fk_order = '.$order->id));
	check($row->lang === ($language === 'unknown' ? 'en_US' : $language), 'Language selection '.$language);
}
check($welcome->process($actor) === 6, 'All five languages and unknown fallback delivered');
$settings['DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU'] = 1;
$order->id++; $db->begin(); check($welcome->enqueue($order, $actor) === 0, 'Disabled welcome not queued'); $db->commit();
check($welcome->process($actor) === 0, 'Disabled queue does not send');
$settings = array();
$order->id++; $db->begin(); $welcome->enqueue($order, $actor); $db->commit();
$welcome->mode = 'preflight_failure';
$first = $now;
foreach (array(0, 3600, 21600, 86400) as $index => $delay) {
	$now = $first + $delay;
	check($welcome->process($actor) === 0, 'Certain failure does not deliver');
	$row = $db->fetch_object($db->query('SELECT * FROM test_dolistoreextract_welcome WHERE fk_order = '.$order->id));
	check((int) $row->attempts === $index + 1 && $row->status === 'failed', 'Exact attempt count');
	$nextDelay = array(3600, 21600, 86400, null)[$index];
	check($row->next_attempt === ($nextDelay === null ? null : $db->idate($first + $nextDelay)), 'Retry schedule anchored to first failure');
}
$now += 100000;
check($welcome->process($actor) === 0, 'No fifth automatic attempt');
$welcome->mode = 'success'; check($welcome->retry($order->id, $actor) === 1, 'Manual retry after correction');
$order->id++; $db->begin(); $welcome->enqueue($order, $actor); $db->commit();
$welcome->mode = 'uncertain'; check($welcome->process($actor) === 0, 'False SMTP result stays uncertain');
check($welcome->retry($order->id, $actor) === -1, 'Uncertain requires explicit verification');
$now += 200000; check($welcome->process($actor) === 0, 'No automatic uncertain retry');
$welcome->mode = 'success'; check($welcome->retry($order->id, $actor, true) === 1, 'Verified unsent outcome can retry');
$order->id++; $db->begin(); $welcome->enqueue($order, $actor); $db->commit();
$actor->allowed = false; $actor->admin = 1;
check($welcome->process($actor) === -1, 'Administrator without permission denied');
$actor->allowed = true; $actor->socid = 10;
check($welcome->process($actor) === -1, 'External actor denied');
$actor->socid = 0; $conf->entity = 2;
check($welcome->process($actor) === 0, 'Other entity never delivered');
$conf->entity = 1;
$db->query("UPDATE test_dolistoreextract_welcome SET status='sending', started_at='".$db->idate($now-901)."' WHERE fk_order=".$order->id);
check($welcome->process($actor) === 0, 'Interrupted delivery not resent');
$row = $db->fetch_object($db->query('SELECT status FROM test_dolistoreextract_welcome WHERE fk_order='.$order->id));
check($row->status === 'uncertain', 'Interrupted delivery marked uncertain');
foreach (array(" Ref 12\t", "REF\u{00A0}12", "ref\u{202F}12") as $ref) check(DolistoreProductIdentity::key($ref, 3, 1) === 'ref:ref12', 'Reference whitespace / case');
check(DolistoreProductIdentity::key('A', 1, 1) === DolistoreProductIdentity::key('a', 2, 2), 'Reference dominates conflicting product ids');
check(DolistoreProductIdentity::key('', 3, 1) === 'product:3', 'Native id fallback');
check(DolistoreProductIdentity::key('', 0, 1) !== DolistoreProductIdentity::key('', 0, 2), 'Unidentified lines stay separate');
echo "OK: ".$checks." business assertions (SQLite adapter + simulated transport).\n";
