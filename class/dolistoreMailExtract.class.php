<?php
/* Copyright (C) 2017      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once 'dolistorextractConfig.class.php';

class dolistoreMailExtract
{

	/**
	 *
	 * @var Db $db DB object
	 */
	public $db;

	/**
	 *
	 * @var string $textBody	Body of the message, HTML version
	 */
	public $textBody;

	/**
	 *
	 * @var json $json data extracted from text part of mail
	 */
	public $json;

	/**
	 * @param Db $db
	 * @param string|null $textBody
	 */
	function __construct(DoliDB $db, ?string $textBody = '')
	{
		$this->db = $db;
		$this->textBody = (string) $textBody;
		if (!empty($this->textBody)) {

			$jsonTxt = str_replace(array("\r\n", "\n", "\r"), " ", $this->textBody);

			$start = strpos($jsonTxt, '{');
			$end = strrpos($jsonTxt, '}');
			if ($start !== false && $end !== false) {
				$jsonTxt = substr($jsonTxt, $start, $end - $start + 1);
			}

			$jsonTxt = preg_replace('/\s+/', ' ', $jsonTxt);

			$this->json = json_decode($jsonTxt, true);
		}
	}

	/**
	 * Extract order data from message content
	 *
	 * Load DOM data from hidden div id="buyer_fulldata"
	 *
	 * Return an array with keys and values extracted
	 */
	function extractOrderDatas() : array
	{
		if (empty($this->textBody)) {
			return array();
		}
		$confDolExtract = new dolistorextractConfig();

		$extractDatas = array();

		// Invoice informations
		foreach ($confDolExtract->arrayExtractTags as $key)
		{
			if(isset($this->json[$key])) {
				$extractDatas[$key] = $this->json[$key];
			}
		}

		return $extractDatas;
	}

	/**
	 * Extract products datas from email body
	 *
	 * @see self::ARRAY_EXTRACT_TAGS_PRODUCT
	 *
	 * @return array contains keys defined in self::ARRAY_EXTRACT_TAGS_PRODUCT
	 */
	function extractProductsData() : array
	{
		if (empty($this->textBody)) {
			return array();
		}
		$confDolExtract = new dolistorextractConfig();

		$extractProducts = array();

		// Invoice informations
		$i=0;

		// print "<p>List to search is " . json_encode($confDolExtract->arrayExtractTagsProduct) . "</p>";

		//dolistore 2025 = 1 mail per ordered product
		$extractProducts[$i] = array();

		foreach($confDolExtract->arrayExtractTagsProduct as $key) {
			if(isset($this->json[$key])) {
				$extractProducts[$i][$key] = $this->json[$key];
			}
		}

		// print "Extract products = " . json_encode($extractProducts);
		return $extractProducts;
	}


	/**
	 * Extract all datas
	 *
	 * Extract all datas from $this->textBody and return an array which contains one keys `items` for products listing
	 * @return array
	 */
	public function extractAllDatas() : array
	{
		$datas = $this->extractOrderDatas();
		// Extract product data
		$lines = $this->extractProductsData();
		if (is_array($lines) && count($lines) > 0) {
			$datas['items'] = $lines;
		}

		if(empty($datas['buyer_company'])) {
			if(!empty($datas['buyer_lastname']) && !empty($datas['buyer_firstname'])) {
				$datas['buyer_company'] = $datas['buyer_firstname'].' '.$datas['buyer_lastname'];
			}
		}

		return (array) $datas;
	}


	/**
	 * Extract order identifiers from email subject
	 *
	 * @param string $subject
	 * @param string $lang
	 * @return array{id:string,ref:string}
	 */
	public static function extractOrderDatasFromSubject(string $subject, string $lang = '') : array
	{
		$subjectDecoded = $subject;
		if (function_exists('mb_decode_mimeheader')) {
			$subjectDecoded = mb_decode_mimeheader($subjectDecoded);
		}

		$orderDatas = array('id' => '', 'ref' => '');

		if (preg_match('/n\s*[°ºo]?\s*([0-9]{2,})/iu', $subjectDecoded, $matches)) {
			$orderDatas['id'] = (string) $matches[1];
		}

		if (preg_match('/-\s*([A-Z0-9][A-Z0-9_-]{4,})\s*$/u', $subjectDecoded, $matches)) {
			$orderDatas['ref'] = (string) $matches[1];
		}

		if (empty($orderDatas['ref']) && preg_match('/(?:order|commande|pedido|ordine|bestellung)\s*[:#-]\s*([A-Z0-9][A-Z0-9_-]{4,})/iu', $subjectDecoded, $matches)) {
			$orderDatas['ref'] = strtoupper((string) $matches[1]);
		}

		return $orderDatas;
	}

	/**
	 * Detect email lang from subject
	 *
	 * @param string $subject
	 * @return string Langage code
	 * @see dolistoreMailExtract::ARRAY_TITLE_TRANSLATION_MAP
	 */
	public static function detectLang( string $subject) : string
	{
		$foundLang = '';
		$confDolExtract = new dolistorextractConfig();

		foreach ($confDolExtract->arrayTitleTranslationMap as $key => $lang) {
			if (preg_match('/'.$key.'/', $subject)) {
				$foundLang = $lang;
				break;
			}
		}
		return $foundLang;
	}

}
