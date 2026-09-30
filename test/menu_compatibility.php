<?php
/* Evaluate the descriptor's literal menu conditions with a specific native core version. */
$root = getenv('DOLIBARR_TEST_ROOT');
if (!$root || !is_file($root.'/core/lib/functions.lib.php')) exit(2);
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_VERSION', getenv('DOLIBARR_TEST_VERSION') ?: 'unknown');
require_once $root.'/core/lib/functions.lib.php';
$conf = (object) array('entity' => 1, 'modules' => array('dolistorextract' => 1), 'global' => new stdClass());
class MenuTestActor {
	public $socid = 0;
	public $admin = 0;
	public $allowed = false;
	public function hasRight($module, $object, $action) { return $this->allowed; }
}
$user = new MenuTestActor();
$source = file_get_contents(__DIR__.'/../core/modules/modDolistorextract.class.php');
preg_match_all("/'perms' => '([^']+)'/", $source, $matches);
if (count($matches[1]) !== 6) throw new RuntimeException('Expected six native menu conditions');
$checks = 0;
// External-user exclusion is provided by the native menu user=0 field and tested in admin_access.php.
foreach (array(array(1, 0, false), array(0, 0, false), array(0, 0, true), array(1, 12, true)) as $profile) {
	list($user->admin, $user->socid, $user->allowed) = $profile;
	foreach ($matches[1] as $expression) {
		$isSetup = strpos($expression, 'hasRight') === false;
		$expected = (bool) ($user->admin || (!$isSetup && $user->allowed));
		$actual = verifCond($expression);
		if ($actual !== $expected) throw new RuntimeException('Native condition failed: '.$expression.'; result='.var_export($actual, true));
		$checks++;
	}
}
fwrite(STDOUT, 'OK: '.$checks.' native menu evaluations on '.DOL_VERSION.".\n");
