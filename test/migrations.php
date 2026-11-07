<?php
/* Production SQL exercised with SQLite syntax adaptation, no configured ERP. */
require __DIR__.'/business.php';
$before = $checks;
$db->query('CREATE TABLE test_dolistoreextract_order (rowid INTEGER PRIMARY KEY, entity INTEGER)');
$db->query('CREATE TABLE test_product (rowid INTEGER PRIMARY KEY, entity INTEGER, label TEXT, ref TEXT)');
$db->query('CREATE TABLE test_dolistoreextract_order_line (rowid INTEGER PRIMARY KEY, entity INTEGER, fk_order INTEGER, fk_product INTEGER, product_dolistore_ref TEXT, product_label TEXT, qty REAL, total_ht REAL)');
$db->query("INSERT INTO test_dolistoreextract_order VALUES (1,1),(2,1),(3,2)");
$db->query("INSERT INTO test_product VALUES (10,1,'Current timesheets','P10'),(20,1,'Current unified service','P20'),(30,2,'Other entity secret','P30')");
$db->query("INSERT INTO test_dolistoreextract_order_line VALUES (1,1,1,10,' Ref A ','Ancien libellé',2,40),(2,1,2,20,'REFA','Weekly timesheets',3,60),(3,1,2,0,'B','Weekly timesheets',1,10),(4,1,2,10,'','Native product',1,20),(5,1,1,0,'','Unknown',1,7),(6,1,2,0,'','Unknown',1,8),(7,2,3,30,'Ref A','Other entity secret',99,999)");
$identity = new DolistoreProductIdentity($db);
$definitions = $identity->getDefinitions(array('ref:refa','ref:b','product:10','line:5','line:6'));
check(count($definitions) === 5, 'No destructive name-based merge');
check($definitions['ref:refa']['label'] === 'Current unified service' && $definitions['ref:refa']['conflict'], 'Current label and contradictory product linkage');
check($definitions['ref:b']['label'] === 'Weekly timesheets (B)' && !$definitions['ref:b']['conflict'], 'Snapshot reference fallback');
foreach (array('Ancien libellé', 'Weekly timesheets', 'Current unified service') as $search) {
	$row = $db->fetch_object($db->query('SELECT COUNT(*) AS count FROM test_dolistoreextract_order_line l WHERE l.rowid IN (1,2) AND '.$identity->searchSql('l', $search)));
	check((int) $row->count === 2, 'Historical and selected label search: '.$search);
}
$row = $db->fetch_object($db->query('SELECT COUNT(*) AS count FROM test_dolistoreextract_order_line l WHERE l.rowid IN (1,2) AND '.$identity->searchSql('l', 'Other entity secret')));
check((int) $row->count === 0, 'Other entity labels cannot match');
$row = $db->fetch_object($db->query('SELECT SUM(qty) AS qty, SUM(total_ht) AS total FROM test_dolistoreextract_order_line l WHERE l.entity = 1 AND '.DolistoreProductIdentity::keySql('l')." = 'ref:refa' GROUP BY ".DolistoreProductIdentity::keySql('l')));
check((float) $row->qty === 5.0 && (float) $row->total === 100.0, 'Aggregation preserves commercial totals');

require_once __DIR__.'/../core/modules/modDolistorextract.class.php';
$reflection = new ReflectionClass(modDolistorextract::class);
$descriptor = $reflection->newInstanceWithoutConstructor();
$descriptor->db = $db; $descriptor->numero = 450032; $descriptor->rights_class = 'dolistorextract';
$descriptor->rights = array();
for ($r = 1; $r <= 8; $r++) $descriptor->rights[$r] = array(45003200 + $r);

$schemaMigration = $reflection->getMethod('migrateNativeOrderReferenceField');
check($schemaMigration->invoke($descriptor) === 1, 'Add standard ref_ext required by Dolibarr 20 FK validation');
check($schemaMigration->invoke($descriptor) === 1, 'Native field migration replay');
check($db->query("SELECT COUNT(*) FROM pragma_table_info('test_dolistoreextract_order') WHERE name='ref_ext'")->fetchColumn() == 1, 'Native field is not duplicated');
$db->query('CREATE TABLE test_rights_def (id INTEGER, entity INTEGER, libelle TEXT, module TEXT, module_origin TEXT, type TEXT, bydefault INTEGER, perms TEXT, subperms TEXT, enabled TEXT, PRIMARY KEY(id,entity))');
$db->query('CREATE TABLE test_user_rights (fk_user INTEGER, fk_id INTEGER, entity INTEGER, UNIQUE(fk_user,fk_id,entity))');
$db->query('CREATE TABLE test_usergroup_rights (fk_usergroup INTEGER, fk_id INTEGER, entity INTEGER, UNIQUE(fk_usergroup,fk_id,entity))');
foreach (array(1,2) as $entity) {
	foreach (array(104977,10497601) as $old) {
		$db->query("INSERT INTO test_rights_def VALUES ($old,$entity,'Read','dolistorextract','','',0,'order','read','1')");
		$db->query("INSERT INTO test_user_rights VALUES (10,$old,$entity)");
		$db->query("INSERT INTO test_usergroup_rights VALUES (20,$old,$entity)");
	}
}
$method = $reflection->getMethod('migrateLegacyPermissionIds');
$db->begin(); check($method->invoke($descriptor) === 1, 'Migrate both permission ranges'); $db->commit();
foreach (array('user_rights','usergroup_rights') as $table) {
	$rows = $db->query('SELECT * FROM test_'.$table)->fetchAll(PDO::FETCH_ASSOC);
	check(count($rows) === 2 && (int) $rows[0]['fk_id'] === 45003201 && (int) $rows[1]['fk_id'] === 45003201, 'Preserved assignments across entities: '.$table);
}
$db->begin(); check($method->invoke($descriptor) === 1, 'Permission migration replay'); $db->commit();
$db->query("INSERT INTO test_rights_def VALUES (45003202,1,'Collision','anothermodule','','',0,'read','','1')");
$db->begin(); check($method->invoke($descriptor) === -1, 'Reject a permission ID already used by another module'); $db->rollback();
$db->query('DELETE FROM test_rights_def WHERE id=45003202');

$db->query('CREATE TABLE test_c_email_templates (rowid INTEGER PRIMARY KEY, entity INTEGER, module TEXT, type_template TEXT, lang TEXT, private INTEGER, fk_user INTEGER, datec TEXT, label TEXT, position INTEGER, defaultfortype INTEGER, enabled TEXT, active INTEGER, email_from TEXT, email_to TEXT, email_tocc TEXT, email_tobcc TEXT, topic TEXT, joinfiles INTEGER, content TEXT, content_lines TEXT)');
function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity) { global $settings; $settings[$name] = $value; return 1; }
$settings = array();
$templates = $reflection->getMethod('initializeDolistoreEmailTemplates');
check($templates->invoke($descriptor) === 1, 'Initialize native templates');
check((int) $db->query('SELECT COUNT(*) FROM test_c_email_templates')->fetchColumn() === 6, 'Five welcome templates and one invoice template');
$configured = $reflection->getProperty('configuredConstants');
$configured->setValue($descriptor, array_fill_keys(array_keys($settings), true));
$settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_ES'] = 0;
$settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_IT'] = '';
check($templates->invoke($descriptor) === 1, 'Replay template initialization');
check($settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_ES'] === 0 && $settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_IT'] === '', 'Preserve zero and empty template selections');
check((int) $db->query('SELECT COUNT(*) FROM test_c_email_templates')->fetchColumn() === 6, 'No duplicate template on replay');
$frId = (int) $settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_FR'];
$legacy = $reflection->getMethod('getDolistoreOrderEmailTemplateFrContent')->invoke($descriptor);
$db->query("UPDATE test_c_email_templates SET content='".$db->escape($legacy)."' WHERE rowid=$frId");
check($templates->invoke($descriptor) === 1, 'Update unchanged delivered legacy model');
check($db->query("SELECT content FROM test_c_email_templates WHERE rowid=$frId")->fetchColumn() === file_get_contents(__DIR__.'/../core/tpl/welcome/fr_FR.html'), 'Exact full French HTML supplied');
$db->query("UPDATE test_c_email_templates SET content='<p>Custom text</p>', topic='Custom subject' WHERE rowid=$frId");
check($templates->invoke($descriptor) === 1, 'Preserve custom model and offer current model');
check($db->query("SELECT content FROM test_c_email_templates WHERE rowid=$frId")->fetchColumn() === '<p>Custom text</p>' && (int) $settings['DOLISTOREXTRACT_EMAIL_TEMPLATE_FR'] === $frId, 'Preserve custom content and selection');
check((int) $db->query('SELECT COUNT(*) FROM test_c_email_templates')->fetchColumn() === 7, 'New supplied variant offered');
check($templates->invoke($descriptor) === 1 && (int) $db->query('SELECT COUNT(*) FROM test_c_email_templates')->fetchColumn() === 7, 'Custom variant replay has no duplicate');
$conf->entity = 2; $settings = array(); $configured->setValue($descriptor, array());
check($templates->invoke($descriptor) === 1 && (int) $db->query('SELECT COUNT(*) FROM test_c_email_templates WHERE entity=2')->fetchColumn() === 6, 'Entity two initialized independently');
echo 'OK: '.($checks-$before)." additional product and migration assertions.\n";
