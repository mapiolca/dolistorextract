<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../modules_dolistoreextract.php';
require_once dirname(__DIR__, 4).'/lib/dolistoreextract.lib.php';

/**
 * Standard PDF model for DoliStore orders.
 */
class pdf_standard extends ModelePDFDolistoreextract
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
		$this->description = is_object($langs) ? $langs->trans('DolistoreOrderPdfStandardDescription') : 'Standard DoliStore order PDF';
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

	/**
	 * Build PDF onto disk.
	 *
	 * @param DolistoreOrder $object Object
	 * @param Translate     $outputlangs Output language
	 * @param string        $srctemplatepath Source template path
	 * @param int           $hidedetails Hide details
	 * @param int           $hidedesc Hide description
	 * @param int           $hideref Hide reference
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}
		$outputlangs->loadLangs(array('main', 'products', 'dict', 'companies', 'dolistorextract@dolistorextract'));

		$dir = dolistoreextractGetOrderUploadDir($object);
		$objectref = dol_sanitizeFileName($object->ref);
		$file = $dir.'/'.$objectref.'.pdf';
		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetCreator('Dolibarr '.(defined('DOL_VERSION') ? DOL_VERSION : ''));
		$pdf->SetTitle($this->pdfText($outputlangs, $object->ref));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(1, $this->marge_basse);
		$pdf->AddPage();

		$defaultFont = pdf_getPDFFont($outputlangs);
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);
		$right = $this->page_largeur - $this->marge_droite;
		$y = $this->marge_haute;

		$pdf->SetFont($defaultFont, 'B', $defaultFontSize + 4);
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($right - $this->marge_gauche, 8, $this->pdfText($outputlangs, $outputlangs->trans('DolistoreOrder').' '.$object->ref), 0, 'L');
		$y += 12;

		$pdf->SetFont($defaultFont, '', $defaultFontSize);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DolistoreOrderRef'), $object->dolistore_order_ref);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DolistoreOrderDate'), $object->dolistore_order_date ? dol_print_date($object->dolistore_order_date, 'day') : '');
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DolistoreReleaseDate'), $object->release_date ? dol_print_date($object->release_date, 'day') : '');
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DolistoreCustomerFinal'), $object->customer_name);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('AmountHT'), price($object->total_ht).' '.$object->currency_code);
		$this->writeInfoLine($pdf, $outputlangs, $y, $outputlangs->trans('DolistoreBillableAmountHT'), price($object->billable_total_ht).' '.$object->currency_code);

		$y += 6;
		$this->writeLinesTable($pdf, $outputlangs, $object, $y, $defaultFont, $defaultFontSize);

		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);
		$this->result = array('fullpath' => $file);

		return 1;
	}

	/**
	 * Write one information line.
	 *
	 * @param TCPDF     $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param float     $y Current Y
	 * @param string    $label Label
	 * @param string    $value Value
	 * @return void
	 */
	private function writeInfoLine(&$pdf, $outputlangs, &$y, $label, $value)
	{
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->SetFont('', 'B');
		$pdf->MultiCell(55, 6, $this->pdfText($outputlangs, $label), 0, 'L', 0, 0);
		$pdf->SetFont('', '');
		$pdf->MultiCell(120, 6, $this->pdfText($outputlangs, (string) $value), 0, 'L', 0, 1);
		$y += 6;
	}

	/**
	 * Write grouped order lines table.
	 *
	 * @param TCPDF          $pdf PDF instance
	 * @param Translate      $outputlangs Output language
	 * @param DolistoreOrder $object Object
	 * @param float          $y Current Y
	 * @param string         $defaultFont Default font
	 * @param int            $defaultFontSize Default font size
	 * @return void
	 */
	private function writeLinesTable(&$pdf, $outputlangs, $object, &$y, $defaultFont, $defaultFontSize)
	{
		$columns = array(
			array('label' => 'DolistoreProductRef', 'width' => 30, 'align' => 'L'),
			array('label' => 'Label', 'width' => 45, 'align' => 'L'),
			array('label' => 'Product', 'width' => 32, 'align' => 'L'),
			array('label' => 'Qty', 'width' => 14, 'align' => 'R'),
			array('label' => 'UnitPriceHT', 'width' => 20, 'align' => 'R'),
			array('label' => 'AmountHT', 'width' => 20, 'align' => 'R'),
			array('label' => 'DolistoreBillableAmountHT', 'width' => 29, 'align' => 'R'),
		);

		$this->writeLinesHeader($pdf, $outputlangs, $y, $columns, $defaultFont, $defaultFontSize);
		$pdf->SetFont($defaultFont, '', $defaultFontSize - 2);

		$lines = $object->getGroupedLinesForDisplay();
		if (empty($lines)) {
			$pdf->SetXY($this->marge_gauche, $y);
			$pdf->MultiCell(190, 6, $this->pdfText($outputlangs, $outputlangs->trans('NoRecordFound')), 1, 'L', 0, 1);
			$y += 6;
			return;
		}

		foreach ($lines as $line) {
			if ($y > ($this->page_hauteur - $this->marge_basse - 15)) {
				$pdf->AddPage();
				$y = $this->marge_haute;
				$this->writeLinesHeader($pdf, $outputlangs, $y, $columns, $defaultFont, $defaultFontSize);
				$pdf->SetFont($defaultFont, '', $defaultFontSize - 2);
			}

			$productRef = '';
			if (!empty($line['product']) && is_object($line['product'])) {
				$productRef = (string) $line['product']->ref;
			}

			$values = array(
				dol_trunc($line['product_dolistore_ref'], 24),
				dol_trunc($line['product_label'], 38),
				dol_trunc($productRef, 26),
				price($line['qty']),
				price($line['unit_price_ht']),
				price($line['total_ht']),
				price($line['billable_total_ht']),
			);

			$x = $this->marge_gauche;
			foreach ($columns as $index => $column) {
				$pdf->SetXY($x, $y);
				$pdf->MultiCell($column['width'], 6, $this->pdfText($outputlangs, $values[$index]), 1, $column['align'], 0, 0);
				$x += $column['width'];
			}
			$y += 6;
		}
	}

	/**
	 * Write grouped lines table header.
	 *
	 * @param TCPDF     $pdf PDF instance
	 * @param Translate $outputlangs Output language
	 * @param float     $y Current Y
	 * @param array<int,array<string,mixed>> $columns Columns
	 * @param string    $defaultFont Default font
	 * @param int       $defaultFontSize Default font size
	 * @return void
	 */
	private function writeLinesHeader(&$pdf, $outputlangs, &$y, $columns, $defaultFont, $defaultFontSize)
	{
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetFont($defaultFont, 'B', $defaultFontSize - 2);
		$x = $this->marge_gauche;
		foreach ($columns as $column) {
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($column['width'], 7, $this->pdfText($outputlangs, $outputlangs->trans($column['label'])), 1, $column['align'], 1, 0);
			$x += $column['width'];
		}
		$y += 7;
	}

	/**
	 * Convert text to the PDF output charset.
	 *
	 * @param Translate $outputlangs Output language
	 * @param string    $text Text
	 * @return string
	 */
	private function pdfText($outputlangs, $text)
	{
		return $outputlangs->convToOutputCharset((string) $text);
	}
}
