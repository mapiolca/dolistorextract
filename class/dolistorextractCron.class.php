<?php
/* Copyright (C) 2026      Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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

/**
 * Cron class for module dolistorextract.
 *
 */
class dolistorextractCron
{

	public $db;
	public $output = '';
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB &$db)
	{
		$this->db = $db;
	}
	/**
	 * Run import
	 *
	 * @return int 0 if OK, < 0 if KO
	 */
	public function runImport() : int
	{
		global $langs;

		$langs->loadLangs(array('dolistorextract@dolistorextract'));
		if (!isModEnabled('dolistorextract')) {
			$this->output .= $langs->trans('DolistoreModuleDisabled');
			return 0;
		}
		if (!getDolGlobalInt('DOLISTOREXTRACT_AUTO_IMPORT_ENABLED')) {
			$this->output .= $langs->trans('DolistoreAutoImportDisabled');
			return 0;
		}

		require_once __DIR__.'/actions_dolistorextract.class.php';

		$dolistorextractActions = new \ActionsDolistorextract($this->db);
		$res = $dolistorextractActions->launchCronJob();

		$this->output.= '<p>'.$dolistorextractActions->logCat.'</p>';
		if ($res < 0) {
			$this->output.= $langs->trans('DolistoreCronImportMetadataError');

			if (!empty($dolistorextractActions->error)) {
				$this->output.= '<br/>'.$dolistorextractActions->error;
			}

			if (!empty($dolistorextractActions->errors) && is_array($dolistorextractActions->errors)) {
				$this->output.= implode('<br/>', $dolistorextractActions->errors);
			}

			return -1;
		}

		if ($res >= 0) {
			$this->output.= $dolistorextractActions->logOutput;
			$this->output.= '<br/>'.$langs->trans('DolistoreCronImportProcessedSales', $res);
			return 0;
		}

		return 0;
	}

	/**
	 * Run DoliStore monthly invoice generation.
	 *
	 * @return int 0 if OK, <0 if KO
	 */
	public function runInvoice() : int
	{
		global $langs;

		$langs->loadLangs(array('dolistorextract@dolistorextract'));
		if (!isModEnabled('dolistorextract')) {
			$this->output .= $langs->trans('DolistoreModuleDisabled');
			return 0;
		}
		if (!getDolGlobalInt('DOLISTOREXTRACT_AUTO_CREATE_INVOICE')) {
			$this->output .= $langs->trans('DolistoreInvoiceAutoDisabled');
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		require_once __DIR__.'/actions_dolistorextract.class.php';

		$user = new User($this->db);
		$userId = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');
		if ($userId <= 0 || $user->fetch($userId) <= 0) {
			$this->output .= $langs->trans('DolistoreActionUserUnavailable');
			return -1;
		}
		$user->getrights();
		if (!empty($user->socid) || !$user->hasRight('dolistorextract', 'invoice', 'generate')) {
			$this->output .= $langs->trans('NotEnoughPermissions');
			return -1;
		}

		$dolistorextractActions = new \ActionsDolistorextract($this->db);
		$res = $dolistorextractActions->generateMonthlyDolistoreInvoice($user);
		$this->output .= $dolistorextractActions->logOutput;

		if ($res < 0) {
			if (!empty($dolistorextractActions->error)) {
				$this->output .= '<br/>'.$dolistorextractActions->error;
			}
			if (!empty($dolistorextractActions->errors) && is_array($dolistorextractActions->errors)) {
				$this->output .= '<br/>'.implode('<br/>', $dolistorextractActions->errors);
			}
			return -1;
		}

		$this->output .= '<br/>'.($res > 0 ? $langs->trans('DolistoreInvoiceGenerated', $res) : $langs->trans('DolistoreInvoiceNothingToDo'));
		return 0;
	}

	/**
	 * Run optional daily notification.
	 *
	 * @return int
	 */
	public function runDailyNotification() : int
	{
		global $langs;

		$langs->loadLangs(array('dolistorextract@dolistorextract'));
		if (!isModEnabled('dolistorextract')) {
			$this->output .= $langs->trans('DolistoreModuleDisabled');
			return 0;
		}
		if (!getDolGlobalInt('DOLISTOREXTRACT_DAILY_NOTIFICATION_ENABLED')) {
			$this->output .= $langs->trans('DolistoreDailyNotificationDisabled');
			return 0;
		}

		$this->output .= $langs->trans('DolistoreDailyNotificationNotImplemented');
		return 0;
	}

	/** @return int 0 completed, -1 access/storage error */
	public function runWelcome(): int
	{
		global $langs;
		$langs->load('dolistorextract@dolistorextract');
		if (!isModEnabled('dolistorextract') || getDolGlobalInt('DOLISTOREXTRACT_DISABLE_SEND_THANK_YOU')) {
			return 0;
		}
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		require_once __DIR__.'/dolistoreWelcomeMail.class.php';
		$user = new User($this->db);
		$id = getDolGlobalInt('DOLISTOREXTRACT_USER_FOR_ACTIONS');
		if ($id <= 0 || $user->fetch($id) <= 0) {
			$this->output = $langs->trans('DolistoreActionUserUnavailable');
			return -1;
		}
		$user->getrights();
		if (!empty($user->socid) || !$user->hasRight('dolistorextract', 'order', 'import')) {
			$this->output = $langs->trans('NotEnoughPermissions');
			return -1;
		}
		$welcome = new DolistoreWelcomeMail($this->db);
		$result = $welcome->process($user);
		$this->output = $result < 0 ? $welcome->error : $langs->trans('DolistoreWelcomeCronResult', $result);
		return $result < 0 ? -1 : 0;
	}
}
