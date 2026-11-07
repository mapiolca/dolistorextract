<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Read-only product identity across historical purchase snapshots. */
class DolistoreProductIdentity
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';

	/** @param DoliDB $db Database */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Whitespace in imported references has no business significance.
	 * @param string $ref External reference
	 * @return string
	 */
	public static function normalizeReference(string $ref): string
	{
		return dol_strtolower(str_replace(array(' ', "\t", "\r", "\n", "\v", "\f", "\u{00A0}", "\u{202F}"), '', $ref));
	}

	/** @param string $ref Reference @param int $product Native id @param int $line Line id @return string */
	public static function key(string $ref, int $product, int $line): string
	{
		$ref = self::normalizeReference($ref);
		return $ref !== '' ? 'ref:'.$ref : ($product > 0 ? 'product:'.$product : 'line:'.$line);
	}

	/** Identical SQL normalization, compatible with MySQL/MariaDB without REGEXP_REPLACE.
	 * @param string $column Trusted SQL column, never a request value
	 * @return string
	 */
	public static function referenceSql(string $column): string
	{
		if (!preg_match('/^[a-z]+\\.[a-z_]+$/D', $column)) throw new InvalidArgumentException('Invalid SQL column');
		$sql = "COALESCE(".$column.", '')";
		foreach (array("' '", 'CHAR(9)', 'CHAR(10)', 'CHAR(11)', 'CHAR(12)', 'CHAR(13)', "'\u{00A0}'", "'\u{202F}'") as $space) {
			$sql = 'REPLACE('.$sql.', '.$space.", '')";
		}
		return 'LOWER('.$sql.')';
	}

	/** @param string $alias Trusted line alias @return string */
	public static function keySql(string $alias): string
	{
		$ref = self::referenceSql($alias.'.product_dolistore_ref');
		return "CASE WHEN ".$ref." <> '' THEN CONCAT('ref:', ".$ref.") WHEN ".$alias.".fk_product > 0 THEN CONCAT('product:', ".$alias.".fk_product) ELSE CONCAT('line:', ".$alias.'.rowid) END';
	}

	/** Match any historical or current label of the same identity, inside the shared scope.
	 * @param string $alias Line alias
	 * @param string $search User search text
	 * @return string
	 */
	public function searchSql(string $alias, string $search): string
	{
		$match = natural_search(array('history.product_label', 'history.product_dolistore_ref', 'product.label', 'product.ref'), $search, 0, 1);
		return 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line history'
			.' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order owner ON owner.rowid = history.fk_order AND owner.entity = history.entity'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'product product ON product.rowid = history.fk_product AND product.entity IN ('.$this->db->sanitize(getEntity('product')).')'
			.' WHERE history.entity IN ('.$this->db->sanitize(getEntity('dolistoreextract_order')).') AND '.self::keySql('history').' = '.self::keySql($alias).' AND '.$match.')';
	}

	/**
	 * Latest known snapshot and latest accessible native product win deterministically.
	 * Conflicting native attachments are reported, never rewritten or merged.
	 * @param list<string> $keys Identity keys
	 * @return array<string, array{label:string,ref:string,fk_product:int,conflict:bool}>
	 */
	public function getDefinitions(array $keys): array
	{
		if (!$keys) return array();
		$quoted = array();
		foreach (array_unique($keys) as $key) $quoted[] = "'".$this->db->escape($key)."'";
		$identity = self::keySql('l');
		$group = 'SELECT '.$identity.' AS identity_key, MAX(l.rowid) AS latest_id,'
			.' MAX(CASE WHEN p.rowid IS NOT NULL THEN l.rowid ELSE NULL END) AS linked_id,'
			.' COUNT(DISTINCT CASE WHEN l.fk_product > 0 THEN l.fk_product ELSE NULL END) AS product_count'
			.' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order_line l'
			.' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order o ON o.rowid = l.fk_order AND o.entity = l.entity'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = l.fk_product AND p.entity IN ('.$this->db->sanitize(getEntity('product')).')'
			.' WHERE l.entity IN ('.$this->db->sanitize(getEntity('dolistoreextract_order')).') AND '.$identity.' IN ('.implode(',', $quoted).') GROUP BY '.$identity;
		$sql = 'SELECT grouped.identity_key, grouped.product_count, latest.product_label, latest.product_dolistore_ref, p.rowid AS product_id, p.label AS native_label'
			.' FROM ('.$group.') grouped'
			.' INNER JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order_line latest ON latest.rowid = grouped.latest_id'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'dolistoreextract_order_line linked ON linked.rowid = grouped.linked_id'
			.' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = linked.fk_product AND p.entity IN ('.$this->db->sanitize(getEntity('product')).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}
		$result = array();
		while (is_object($row = $this->db->fetch_object($resql))) {
			$ref = trim((string) $row->product_dolistore_ref);
			$result[(string) $row->identity_key] = array(
				'label' => !empty($row->native_label) ? (string) $row->native_label : (string) $row->product_label.($ref !== '' ? ' ('.$ref.')' : ''),
				'ref' => $ref,
				'fk_product' => (int) $row->product_id,
				'conflict' => (int) $row->product_count > 1,
			);
		}
		$this->db->free($resql);
		return $result;
	}
}
