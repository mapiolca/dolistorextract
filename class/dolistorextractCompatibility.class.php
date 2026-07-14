<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Centralized compatibility checks for DolistoreExtract.
 */
class DolistoreextractCompatibility
{
	public const MIN_DOLIBARR = '20.0.0';
	public const MIN_PHP = '8.0.0';

	/**
	 * Check Dolibarr version.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	public static function isDolibarrVersionAtLeast($version)
	{
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, $version, '>=');
	}

	/**
	 * Check PHP version.
	 *
	 * @param string $version Version
	 * @return bool
	 */
	public static function isPhpVersionAtLeast($version)
	{
		return version_compare(PHP_VERSION, $version, '>=');
	}

	/**
	 * Return feature matrix.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getFeatures()
	{
		$features = array(
			'v2_orders' => array(
				'label' => 'DolistoreCompatibilityV2Orders',
				'description' => 'DolistoreCompatibilityV2OrdersDesc',
				'min_dolibarr' => self::MIN_DOLIBARR,
				'min_php' => self::MIN_PHP,
				'available' => self::isDolibarrVersionAtLeast(self::MIN_DOLIBARR) && self::isPhpVersionAtLeast(self::MIN_PHP),
				'reason' => 'DolistoreCompatibilityRequiresDolibarr20Php80',
			),
			'native_invoice' => array(
				'label' => 'DolistoreCompatibilityNativeInvoice',
				'description' => 'DolistoreCompatibilityNativeInvoiceDesc',
				'min_dolibarr' => self::MIN_DOLIBARR,
				'min_php' => self::MIN_PHP,
				'available' => class_exists('Facture') || is_readable(DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php'),
				'reason' => 'DolistoreCompatibilityInvoiceClassMissing',
			),
			'multicompany_documents' => array(
				'label' => 'DolistoreCompatibilityMulticompanyDocuments',
				'description' => 'DolistoreCompatibilityMulticompanyDocumentsDesc',
				'min_dolibarr' => self::MIN_DOLIBARR,
				'min_php' => self::MIN_PHP,
				'available' => function_exists('getMultidirOutput') || is_readable(DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php'),
				'reason' => 'DolistoreCompatibilityFilesHelperMissing',
			),
		);

		return $features;
	}

	/**
	 * Check feature availability.
	 *
	 * @param string $code Feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($code)
	{
		$features = self::getFeatures();
		return !empty($features[$code]['available']);
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getUnavailableFeatures()
	{
		$unavailable = array();
		foreach (self::getFeatures() as $code => $feature) {
			if (empty($feature['available'])) {
				$unavailable[$code] = $feature;
			}
		}

		return $unavailable;
	}
}
