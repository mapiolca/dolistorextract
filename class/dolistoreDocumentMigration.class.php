<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
require_once __DIR__.'/dolistoreOrder.class.php';
require_once __DIR__.'/../lib/dolistoreextract.lib.php';

/** Idempotent relocation to the native owner-entity/reference layout. */
class DolistoreDocumentMigration
{
	public $error = '';
	private $db;
	public function __construct($db) { $this->db = $db; }

	/** Preflight the entire entity before moving any file; never overwrite.
	 * @param User $user Actor
	 * @return int Number of moved files, -1 on failure
	 */
	public function run($user)
	{
		global $conf, $langs;
		$langs->load('dolistorextract@dolistorextract');
		if (!isModEnabled('dolistorextract') || !empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'write'))) {
			$this->error = $langs->trans('DolistoreWelcomeAccessDenied');
			return -1;
		}
		$lock = 'dse_docs_'.substr(hash('sha256', MAIN_DB_PREFIX.':'.$conf->entity), 0, 40);
		$locked = $this->db->query("SELECT GET_LOCK('".$lock."', 0) AS acquired");
		$row = $locked ? $this->db->fetch_object($locked) : null;
		if ($locked) $this->db->free($locked);
		if (!is_object($row) || (int) $row->acquired !== 1) return $this->fail();
		try {
		$result = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'dolistoreextract_order WHERE entity = '.(int) $conf->entity.' ORDER BY rowid');
		if (!$result) { $this->error = $this->db->lasterror(); return -1; }
		$ids = array();
		while (is_object($row = $this->db->fetch_object($result))) $ids[] = (int) $row->rowid;
		$this->db->free($result);
		$operations = array();
		$pointers = array();
		$archives = array();
		foreach ($ids as $id) {
			$order = new DolistoreOrder($this->db);
			if ($order->fetch($id) <= 0) return $this->fail();
			$destination = dolistoreextractGetOrderUploadDir($order);
			if ($destination === '') return $this->fail();
			$base = rtrim($conf->dolistorextract->multidir_output[$order->entity], '/');
			$source = $base.'/dolistoreextract_order/'.dol_sanitizeFileName($order->ref);
			if (is_link($base.'/dolistoreextract_order') || is_link($source)) return $this->fail();
			$archives[] = array($order, $source, $destination);
			$mainDoc = (string) $order->last_main_doc;
			$physical = substr($mainDoc, 0, 1) === '/' ? $mainDoc : DOL_DATA_ROOT.'/'.$mainDoc;
			if ($mainDoc !== '' && strpos($physical, $source.'/') === 0) {
				$newPhysical = $destination.substr($physical, strlen($source));
				$newStored = strpos($newPhysical, DOL_DATA_ROOT.'/') === 0 ? substr($newPhysical, strlen(DOL_DATA_ROOT) + 1) : $newPhysical;
				$pointers[] = array($order->id, $order->entity, $mainDoc, $newStored, $newPhysical);
			}
			if (!is_dir($source)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $entry) {
				if ($entry->isLink() || !$entry->isFile()) return $this->fail();
				$relative = substr($entry->getPathname(), strlen($source) + 1);
				$target = rtrim($destination, '/').'/'.$relative;
				for ($parent = $target; $parent !== dirname($parent); $parent = dirname($parent)) {
					if (is_link($parent)) return $this->fail();
				}
				if (file_exists($target)) return $this->fail();
				$operations[] = array($entry->getPathname(), $target, $order->id, $order->entity, $order->last_main_doc);
			}
		}
		$moved = 0;
		foreach ($operations as $operation) {
			list($source, $target, $id, $entity, $mainDoc) = $operation;
			if (dol_mkdir(dirname($target)) < 0 || dol_move($source, $target, '0', 0, 0, 0) <= 0) return $this->fail();
			$moved++;
		}
		// A file move and a database commit cannot be atomic. Reconcile the native
		// ECM index on every run, including files moved by an interrupted run.
		foreach ($archives as $archive) {
			list($order, $source, $destination) = $archive;
			if (!is_dir($destination)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $entry) {
				if ($entry->isLink() || !$entry->isFile()) return $this->fail();
				$target = $entry->getPathname();
				if (strpos($target, DOL_DATA_ROOT.'/') !== 0) return $this->fail();
				$relative = substr($target, strlen(DOL_DATA_ROOT) + 1);
				$oldPhysical = $source.substr($target, strlen($destination));
				$oldRelative = substr($oldPhysical, strlen(DOL_DATA_ROOT) + 1);
				$index = new EcmFiles($this->db);
				$found = $index->fetch(0, '', $relative);
				if ($found < 0 || ($found > 0 && (int) $index->entity !== (int) $order->entity)) return $this->fail();
				$oldIndex = new EcmFiles($this->db);
				$oldFound = $oldIndex->fetch(0, '', $oldRelative);
				if ($oldFound < 0 || ($oldFound > 0 && (int) $oldIndex->entity !== (int) $order->entity)) return $this->fail();
				if ($found > 0) {
					if ($oldFound > 0) return $this->fail();
					continue;
				}
				// Keep original metadata, ownership and share keys when an index exists.
				if ($oldFound > 0) $index = $oldIndex;
				$index->filepath = dirname($relative);
				$index->filename = basename($relative);
				if ($oldFound > 0) {
					if ($index->update($user) <= 0) return $this->fail();
				} else {
					$index->entity = (int) $order->entity;
					$index->label = md5_file($target);
					$index->gen_or_uploaded = 'unknown';
					$index->src_object_type = $order->table_element;
					$index->src_object_id = (int) $order->id;
					if ($index->create($user) <= 0) return $this->fail();
				}
			}
		}
		// Repair pointers even after an interruption between a move and the SQL update.
		foreach ($pointers as $pointer) {
			list($id, $entity, $oldStored, $newStored, $physical) = $pointer;
			if (!is_file($physical) || is_link($physical)) return $this->fail();
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'dolistoreextract_order SET last_main_doc = \''.$this->db->escape($newStored).'\' WHERE rowid = '.(int) $id.' AND entity = '.(int) $entity." AND last_main_doc = '".$this->db->escape($oldStored)."'";
			if (!$this->db->query($sql)) return $this->fail();
		}
		dol_syslog(__METHOD__.' entity='.(int) $conf->entity.' files='.$moved, LOG_INFO);
		return $moved;
		} catch (Throwable $e) {
			return $this->fail();
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('".$lock."')");
		}
	}

	/** @return int */
	private function fail()
	{
		global $langs;
		$this->error = $langs->trans('DolistoreDocumentMigrationConflict');
		return -1;
	}
}
