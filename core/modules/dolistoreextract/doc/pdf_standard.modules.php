<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../modules_dolistoreorder.php';
require_once dirname(__DIR__, 4).'/lib/dolistoreextract.lib.php';

/**
 * Standard PDF model for DoliStore orders.
 */
class pdf_standard extends ModelePDFDolistoreOrder
{
	public $db;
	public $name;
	public $description;
	public $type;
	public $version = 'dolibarr';
	public $page_largeur;
	public $page_hauteur;
	public $format;
	public $marge_gauche;
	public $marge_droite;
	public $marge_haute;
	public $marge_basse;
	public $update_main_doc_field = 1;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$this->name = 'standard';
		$langs->load('dolistorextract@dolistorextract');
		$this->description = $langs->trans('DolistoreOrderPdfStandardDescription');
		$this->type = 'pdf';

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
	}

	/** @var float Reserved footer height, measured before content. */
	private $heightforfooter = 0;
	/** @var Societe */
	private $issuer;
	/** @var array<string,string> */
	public $result = array();

	/** Generate with native headers/footers and measured, splittable rows.
	 * @param DolistoreOrder $object Order
	 * @param Translate $outputlangs Output language
	 * @param string $srctemplatepath Template
	 * @param int $hidedetails Hide lines
	 * @param int $hidedesc Hide labels
	 * @param int $hideref Hide product references
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs, $mysoc, $user, $conf;
		if (!isModEnabled('dolistorextract') || !empty($user->socid)
			|| (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'read')) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'write'))) return 0;
		if (!is_object($outputlangs)) $outputlangs = $langs;
		$outputlangs->loadLangs(array('main', 'products', 'dict', 'companies', 'dolistorextract@dolistorextract'));
		$dir = dolistoreextractGetOrderUploadDir($object);
		if ($dir === '' || dol_mkdir($dir) < 0) {
			$this->error = $langs->trans('DolistoreDocumentDirectoryUnavailable');
			return 0;
		}
		$file = $dir.'/'.dol_sanitizeFileName($object->ref).'.pdf';
		if (is_link($file)) { $this->error = $langs->trans('DolistoreDocumentMigrationConflict'); return 0; }
		$originalConf = $conf;
		$conf = clone $conf;
		$conf->global = clone $conf->global;
		try {
		if ((int) $object->entity !== (int) $conf->entity) {
			// Native owner configuration; never persist changes in the consultation entity.
			$ownerConf = new Conf();
			$ownerConf->db = clone $conf->db;
			$ownerConf->file = clone $conf->file;
			if (isset($conf->multicompany)) $ownerConf->multicompany = clone $conf->multicompany;
			$ownerConf->entity = (int) $conf->entity;
			if ($ownerConf->setEntityValues($this->db, (int) $object->entity) < 0) throw new RuntimeException('owner configuration');
			$conf = $ownerConf;
		}
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$this->issuer = new Societe($this->db);
		$this->issuer->setMysoc($conf);
		// The native plain-text footer intentionally measures at width 20000.
		// Use its HTML measuring mode locally, escaping plain text first. This
		// preserves content and stored preferences while allowing actual wrapping.
		if (!getDolGlobalInt('PDF_ALLOW_HTML_FOR_FREE_TEXT')) {
			$conf->global->MAIN_PDF_FREETEXT = dol_htmlentitiesbr(getDolGlobalString('MAIN_PDF_FREETEXT'), 0);
			$conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT = 1;
		}
		$conf->global->PDF_FREETEXT_DISABLE_PAGEBREAK = 1;
		$conf->global->PDF_FOOTER_DISABLE_PAGEBREAK = 1;
		$pdf = pdf_getInstance($this->format);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', pdf_getPDFFontSize($outputlangs));
		$pdf->AddPage();
		// Measure the native final footer (including HTML and pdf_pagefoot hooks)
		// on an isolated PDF clone. No final pass repaints existing page footers.
		$probe = clone $pdf;
		$probe->SetAutoPageBreak(false, 0);
		$this->heightforfooter = max($this->marge_basse, (float) $this->_pagefoot($probe, $outputlangs, $object, 0)) + 6;
		unset($probe);
		if ($this->heightforfooter > $this->page_hauteur - $this->marge_haute - 45) {
			$this->error = $langs->trans('DolistorePdfFooterTooLarge');
			return 0;
		}
		$pdf->setPageOrientation('', true, $this->heightforfooter);
		$this->pageHeader($pdf, $outputlangs, $object);
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$info = array('DolistoreOrderRef' => $object->dolistore_order_ref,
			'DolistoreOrderDate' => dol_print_date($object->dolistore_order_date, 'day', false, $outputlangs),
			'DolistoreReleaseDate' => dol_print_date($object->release_date, 'day', false, $outputlangs),
			'DolistoreCustomerFinal' => $object->customer_name,
			'AmountHT' => price($object->total_ht, 0, $outputlangs).' '.$object->currency_code,
			'DolistoreBillableAmountHT' => price($object->billable_total_ht, 0, $outputlangs).' '.$object->currency_code);
		foreach ($info as $label => $value) {
			$this->writeRow($pdf, $outputlangs, $object, array($outputlangs->trans($label), (string) $value), array($width * 0.38, $width * 0.62));
		}
		if (!$hidedetails) {
			$columns = array($width * 0.18, $width * 0.42, $width * 0.10, $width * 0.15, $width * 0.15);
			$this->writeRow($pdf, $outputlangs, $object, array($outputlangs->trans('DolistoreProductRef'), $outputlangs->trans('Label'), $outputlangs->trans('Qty'), $outputlangs->trans('AmountHT'), $outputlangs->trans('DolistoreBillableAmountHT')), $columns);
			$lines = $object->getGroupedLinesForDisplay();
			if ($object->error) { $this->error = $object->error; return 0; }
			if (!$lines) $this->writeRow($pdf, $outputlangs, $object, array($outputlangs->trans('NoRecordFound')), array($width));
			foreach ($lines as $line) {
				$this->writeRow($pdf, $outputlangs, $object, array($hideref ? '' : $line['product_dolistore_ref'], $hidedesc ? '' : $line['product_label'], price($line['qty'], 0, $outputlangs), price($line['total_ht'], 0, $outputlangs), price($line['billable_total_ht'], 0, $outputlangs)), $columns);
			}
		}
		if ($object->note_public) $this->writeRow($pdf, $outputlangs, $object, array(dol_string_nohtmltag($object->note_public)), array($width));
		$pdf->SetAutoPageBreak(false, 0);
		$this->_pagefoot($pdf, $outputlangs, $object, 0);
		$pdf->Close();
		$temporary = $dir.'/.pdf-'.bin2hex(random_bytes(12));
		$pdf->Output($temporary, 'F');
		clearstatcache(true, $temporary);
		if (!is_file($temporary) || filesize($temporary) <= 0 || !rename($temporary, $file)) {
			throw new RuntimeException('PDF write failed');
		}
		dolChmod($file);
		$this->result = array('fullpath' => $file);
		return 1;
		} catch (Throwable $e) {
			if (isset($temporary) && is_file($temporary)) dol_delete_file($temporary, 0, 0, 0);
			$this->error = $langs->trans('DolistoreArchiveWriteFailed');
			dol_syslog(__METHOD__.' PDF generation failed for order='.(int) $object->id, LOG_ERR);
			return 0;
		} finally {
			$conf = $originalConf;
		}
	}

	/** @param TCPDF $pdf @param Translate $outputlangs @param DolistoreOrder $object @return void */
	private function pageHeader($pdf, $outputlangs, $object)
	{
		global $conf;
		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		$top = $this->marge_haute;
		$logoBase = $conf->mycompany->multidir_output[(int) $object->entity] ?? '';
		$logoName = getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $this->issuer->logo : $this->issuer->logo_small;
		if (is_string($logoBase) && $logoBase !== '' && is_string($logoName) && $logoName !== '' && dol_sanitizeFileName($logoName) === $logoName) {
			$logo = $logoBase.'/logos/'.(getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? '' : 'thumbs/').$logoName;
			if (is_readable($logo) && !is_link($logo)) {
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $top, 0, $height);
				$top += $height + 3;
			}
		}
		$pdf->SetXY($this->marge_gauche, $top);
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', pdf_getPDFFontSize($outputlangs) + 2);
		$pdf->MultiCell(0, 7, $outputlangs->convToOutputCharset($outputlangs->trans('DolistoreOrder').' '.$object->ref), 0, 'L');
		$pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs));
		$pdf->MultiCell(0, 6, $outputlangs->convToOutputCharset($this->issuer->name), 0, 'L');
		$pdf->Ln(3);
	}

	/** @param TCPDF $pdf @param Translate $outputlangs @param DolistoreOrder $object @param int $hidefreetext @return float */
	protected function _pagefoot(&$pdf, $outputlangs, $object, $hidefreetext)
	{
		return pdf_pagefoot($pdf, $outputlangs, 'MAIN_PDF_FREETEXT', $this->issuer, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 1), $hidefreetext, $this->page_largeur);
	}

	/** Split oversized cells without truncation; all heights use native font metrics.
	 * @param TCPDF $pdf @param Translate $outputlangs @param DolistoreOrder $object
	 * @param list<string> $values @param list<float> $widths @return void
	 */
	private function writeRow($pdf, $outputlangs, $object, array $values, array $widths)
	{
		$remaining = array_map(array($outputlangs, 'convToOutputCharset'), $values);
		do {
			$space = $this->page_hauteur - $this->heightforfooter - $pdf->GetY() - 3;
			if ($space < 12) {
				$pdf->SetAutoPageBreak(false, 0);
				$this->_pagefoot($pdf, $outputlangs, $object, 1);
				$pdf->AddPage();
				$pdf->setPageOrientation('', true, $this->heightforfooter);
				$this->pageHeader($pdf, $outputlangs, $object);
				$space = $this->page_hauteur - $this->heightforfooter - $pdf->GetY() - 3;
			}
			$chunks = array();
			$height = 6.0;
			foreach ($remaining as $index => $text) {
				$length = dol_strlen($text);
				$low = 0; $high = $length;
				while ($low < $high) {
					$middle = (int) ceil(($low + $high) / 2);
					$part = dol_substr($text, 0, $middle);
					if ($pdf->getStringHeight($widths[$index], $part) + 2 <= $space) $low = $middle;
					else $high = $middle - 1;
				}
				if ($length > 0 && $low === 0) throw new RuntimeException('PDF font exceeds available space');
				$chunks[$index] = dol_substr($text, 0, $low);
				$remaining[$index] = dol_substr($text, $low);
				$height = max($height, $pdf->getStringHeight($widths[$index], $chunks[$index]) + 2);
			}
			$x = $this->marge_gauche; $y = $pdf->GetY();
			foreach ($chunks as $index => $text) {
				$pdf->SetXY($x, $y);
				$pdf->MultiCell($widths[$index], $height, $text, 1, 'L', false, 0);
				$x += $widths[$index];
			}
			$pdf->SetXY($this->marge_gauche, $y + $height);
		} while (implode('', $remaining) !== '');
	}
}
