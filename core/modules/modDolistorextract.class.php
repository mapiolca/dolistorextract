<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2016 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2017      Jean-François Ferry	<jfefe@aternatik.fr>
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
 * 	\defgroup   dolistorextract     Module Dolistorextract
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/dolistorextract/core/modules directory.
 *  \file       htdocs/dolistorextract/core/modules/modDolistorextract.class.php
 *  \ingroup    dolistorextract
 *  \brief      Description and activation file for module Dolistorextract
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Dolistorextract
 */
class modDolistorextract extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104976;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'dolistorextract';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','interface','other'
		// It is used to group modules by family in module setup page
		$this->family = "ATM Consulting";
		// Module position in the family
		$this->module_position = 500;
		// Gives the possibility to the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '001', 'label' => $langs->trans("MyOwnFamily")));

		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = 'Dolistorextract';
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module d'archivage et de facturation des commandes DoliStore";
		$this->descriptionlong = "";
		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '2.0.0';
		// Key used in the Dolibarr constants table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='dolistore@dolistorextract';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /dolistorextract/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /dolistorextract/core/modules/barcode)
		// for specific css file (eg: /dolistorextract/css/dolistorextract.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/dolistorextract/css/dolistorextract.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/dolistorextract/js/dolistorextract.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2',...) // Set here all hooks context managed by module. You can also set hook context 'all'
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty(isModEnabled('module1')) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@dolistorextract')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
			'triggers' => 1,
			'models' => 1,
			'api' => 1,
			'substitutions' => 1,
			'hooks' => array(
				'data' => array(
					'admin',
					'agenda',
					'emailtemplates',
					'notification',
					'multicompanyexternalmodulesharing',
					'multicompanyexternalmodules',
					'multicompanysharingoptions',
				),
				'entity' => '0',
			),
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/dolistorextract/temp");
		$this->dirs = array('/dolistorextract');

		// Config pages. Put here list of php page, stored into dolistorextract/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@dolistorextract");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of module class names as string that must be enabled if this module is enabled
		$this->requiredby = array();	// List of module ids to disable if this one is disabled
		$this->conflictwith = array();	// List of module class names as string this module is in conflict with
		$this->phpmin = array(8,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(20,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("dolistorextract@dolistorextract");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = array();

		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@dolistorextract:$user->rights->dolistorextract->read:/dolistorextract/mynewtab1.php?id=__ID__',  					// To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@dolistorextract:$user->rights->othermodule->read:/dolistorextract/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
        //                              'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
		// where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'buyer_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view
        $this->tabs = array();

		if (! isset($conf->dolistorextract) || ! isModEnabled('dolistorextract'))
        {
        	$conf->dolistorextract=new stdClass();
        	$conf->dolistorextract->enabled=0;
        }

        // Dictionaries
		$this->dictionaries=array();
        /* Example:
        $this->dictionaries=array(
            'langs'=>'mylangfile@dolistorextract',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->dolistorextract->enabled,$conf->dolistorextract->enabled,$conf->dolistorextract->enabled)												// Condition to show each dictionary
        );
        */

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		// Example:
		//$this->boxes=array(
		//    0=>array('file'=>'myboxa.php@dolistorextract','note'=>'','enabledbydefaulton'=>'Home'),
		//    1=>array('file'=>'myboxb.php@dolistorextract','note'=>''),
		//    2=>array('file'=>'myboxc.php@dolistorextract','note'=>'')
		//);

		// Cronjobs
		$this->cronjobs = array(
			0 => array('label' => 'DolistoreCronImportLabel', 'jobtype' => 'method', 'class' => '/dolistorextract/class/dolistorextractCron.class.php', 'objectname' => 'dolistorextractCron', 'method' => 'runImport', 'parameters' => '', 'comment' => 'DolistoreCronImportComment', 'frequency' => 1, 'unitfrequency' => 3600, 'status' => 1, 'test' => 'isModEnabled("dolistorextract")', 'priority' => 50),
			1 => array('label' => 'DolistoreCronInvoiceLabel', 'jobtype' => 'method', 'class' => '/dolistorextract/class/dolistorextractCron.class.php', 'objectname' => 'dolistorextractCron', 'method' => 'runInvoice', 'parameters' => '', 'comment' => 'DolistoreCronInvoiceComment', 'frequency' => 1, 'unitfrequency' => 86400, 'status' => 1, 'test' => 'isModEnabled("dolistorextract")', 'priority' => 55),
			2 => array('label' => 'DolistoreCronDailyNotificationLabel', 'jobtype' => 'method', 'class' => '/dolistorextract/class/dolistorextractCron.class.php', 'objectname' => 'dolistorextractCron', 'method' => 'runDailyNotification', 'parameters' => '', 'comment' => 'DolistoreCronDailyNotificationComment', 'frequency' => 1, 'unitfrequency' => 86400, 'status' => 1, 'test' => 'isModEnabled("dolistorextract")', 'priority' => 90),
		);

		// Permissions
		$this->rights = array();
		$r = 0;

		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Read DoliStore orders';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'order';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Import DoliStore orders';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'order';
		$this->rights[$r][5] = 'import';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Modify DoliStore orders';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'order';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Delete DoliStore orders';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'order';
		$this->rights[$r][5] = 'delete';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Generate DoliStore invoices';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'invoice';
		$this->rights[$r][5] = 'generate';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Configure DolistoreExtract';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'setup';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Use DolistoreExtract API';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'api';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'Export DoliStore orders';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'order';
		$this->rights[$r][5] = 'export';
		$r++;

		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus
		//
		// Example to declare a new Top Menu entry and its Left menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>'',			                // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
		//							'type'=>'top',			                // This is a Top menu entry
		//							'titre'=>'Dolistorextract top menu',
		//							'mainmenu'=>'dolistorextract',
		//							'leftmenu'=>'dolistorextract',
		//							'url'=>'/dolistorextract/pagetop.php',
		//							'langs'=>'mylangfile@dolistorextract',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'$conf->dolistorextract->enabled',	// Define condition to show or hide menu entry. Use '$conf->dolistorextract->enabled' if entry must be visible if module is enabled.
		//							'perms'=>'1',			                // Use 'perms'=>'$user->rights->dolistorextract->level1->level2' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		// $r++;
		//
		// Example to declare a Left Menu entry into an existing Top menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=xxx',		    // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
		//							'type'=>'left',			                // This is a Left menu entry
		//							'titre'=>'Dolistorextract left menu',
		//							'mainmenu'=>'xxx',
		//							'leftmenu'=>'dolistorextract',
		//							'url'=>'/dolistorextract/pagelevel2.php',
		//							'langs'=>'mylangfile@dolistorextract',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'$conf->dolistorextract->enabled',  // Define condition to show or hide menu entry. Use '$conf->dolistorextract->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
		//							'perms'=>'1',			                // Use 'perms'=>'$user->rights->dolistorextract->level1->level2' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		// $r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial',
			'type' => 'left',
			'titre' => 'DolistoreMenuRoot',
			'prefix' => 'fa-store',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract',
			'url' => '/dolistorextract/dashboard.php',
			'langs' => 'dolistorextract@dolistorextract',
			'position' => 100,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "order", "read")',
			'target' => '',
			'user' => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=dolistoreextract',
			'type' => 'left',
			'titre' => 'DolistoreDashboard',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract_dashboard',
			'url' => '/dolistorextract/dashboard.php',
			'langs' => 'dolistorextract@dolistorextract',
			'position' => 101,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "order", "read")',
			'target' => '',
			'user' => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=dolistoreextract',
			'type' => 'left',
			'titre' => 'DolistoreOrders',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract_orders',
			'url' => '/dolistorextract/list.php',
			'langs' => 'dolistorextract@dolistorextract',
			'position' => 102,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "order", "read")',
			'target' => '',
			'user' => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=dolistoreextract',
			'type' => 'left',
			'titre' => 'DolistoreInvoices',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract_invoices',
			'url' => '/dolistorextract/invoices.php',
			'langs' => 'dolistorextract@dolistorextract',
			'position' => 103,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "invoice", "generate")',
			'target' => '',
			'user' => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=dolistoreextract',
			'type' => 'left',
			'titre' => 'DolistoreImportLogs',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract_logs',
			'url' => '/dolistorextract/importlogs.php',
			'langs' => 'dolistorextract@dolistorextract',
			'position' => 104,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "order", "read")',
			'target' => '',
			'user' => 0
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=commercial,fk_leftmenu=dolistoreextract',
			'type' => 'left',
			'titre' => 'Setup',
			'mainmenu' => 'commercial',
			'leftmenu' => 'dolistoreextract_setup',
			'url' => '/dolistorextract/admin/setup.php',
			'langs' => 'admin',
			'position' => 105,
			'enabled' => 'isModEnabled("dolistorextract")',
			'perms' => '$user->admin || $user->hasRight("dolistorextract", "setup", "write")',
			'target' => '',
			'user' => 0
		);


		// Exports
		$r=1;

		// Example:
		// $this->export_code[$r]=$this->rights_class.'_'.$r;
		// $this->export_label[$r]='Dolistorextract';	// Translation key (used only if key ExportDataset_xxx_z not found)
        // $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
        // $this->export_icon[$r]='generic:Dolistorextract';					// Put here code of icon then string for translation key of module name
		// $this->export_permission[$r]=array(array("dolistorextract","level1","level2"));
		// $this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.zip'=>'Zip','s.town'=>'Town','s.fk_pays'=>'Country','s.phone'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','s.code_compta'=>'CustomerAccountancyCode','s.code_compta_fournisseur'=>'SupplierAccountancyCode','f.rowid'=>"InvoiceId",'f.facnumber'=>"InvoiceRef",'f.datec'=>"InvoiceDateCreation",'f.datef'=>"DateInvoice",'f.total'=>"TotalHT",'f.total_ttc'=>"TotalTTC",'f.tva'=>"TotalVAT",'f.paye'=>"InvoicePaid",'f.fk_statut'=>'InvoiceStatus','f.note'=>"InvoiceNote",'fd.rowid'=>'LineId','fd.description'=>"LineDescription",'fd.price'=>"LineUnitPrice",'fd.tva_tx'=>"LineVATRate",'fd.qty'=>"LineQty",'fd.total_ht'=>"LineTotalHT",'fd.total_tva'=>"LineTotalTVA",'fd.total_ttc'=>"LineTotalTTC",'fd.date_start'=>"DateStart",'fd.date_end'=>"DateEnd",'fd.fk_product'=>'ProductId','p.ref'=>'ProductRef');
		// $this->export_TypeFields_array[$r]=array('t.date'=>'Date', 't.qte'=>'Numeric', 't.poids'=>'Numeric', 't.fad'=>'Numeric', 't.paq'=>'Numeric', 't.stockage'=>'Numeric', 't.fadparliv'=>'Numeric', 't.livau100'=>'Numeric', 't.forfait'=>'Numeric', 's.nom'=>'Text','s.address'=>'Text','s.zip'=>'Text','s.town'=>'Text','c.code'=>'Text','s.phone'=>'Text','s.siren'=>'Text','s.siret'=>'Text','s.ape'=>'Text','s.idprof4'=>'Text','s.code_compta'=>'Text','s.code_compta_fournisseur'=>'Text','s.tva_intra'=>'Text','f.facnumber'=>"Text",'f.datec'=>"Date",'f.datef'=>"Date",'f.date_lim_reglement'=>"Date",'f.total'=>"Numeric",'f.total_ttc'=>"Numeric",'f.tva'=>"Numeric",'f.paye'=>"Boolean",'f.fk_statut'=>'Status','f.note_private'=>"Text",'f.note_public'=>"Text",'fd.description'=>"Text",'fd.subprice'=>"Numeric",'fd.tva_tx'=>"Numeric",'fd.qty'=>"Numeric",'fd.total_ht'=>"Numeric",'fd.total_tva'=>"Numeric",'fd.total_ttc'=>"Numeric",'fd.date_start'=>"Date",'fd.date_end'=>"Date",'fd.special_code'=>'Numeric','fd.product_type'=>"Numeric",'fd.fk_product'=>'List:product:label','p.ref'=>'Text','p.label'=>'Text','p.accountancy_code_sell'=>'Text');
		// $this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.zip'=>'company','s.town'=>'company','s.fk_pays'=>'company','s.phone'=>'company','s.siren'=>'company','s.siret'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.code_compta'=>'company','s.code_compta_fournisseur'=>'company','f.rowid'=>"invoice",'f.facnumber'=>"invoice",'f.datec'=>"invoice",'f.datef'=>"invoice",'f.total'=>"invoice",'f.total_ttc'=>"invoice",'f.tva'=>"invoice",'f.paye'=>"invoice",'f.fk_statut'=>'invoice','f.note'=>"invoice",'fd.rowid'=>'buyer_line','fd.description'=>"buyer_line",'fd.price'=>"buyer_line",'fd.total_ht'=>"buyer_line",'fd.total_tva'=>"buyer_line",'fd.total_ttc'=>"buyer_line",'fd.tva_tx'=>"buyer_line",'fd.qty'=>"buyer_line",'fd.date_start'=>"buyer_line",'fd.date_end'=>"buyer_line",'fd.fk_product'=>'product','p.ref'=>'product');
		// $this->export_dependencies_array[$r]=array('buyer_line'=>'fd.rowid','product'=>'fd.rowid'); // To add unique key if we ask a field of a child to avoid the DISTINCT to discard them
		// $this->export_sql_start[$r]='SELECT DISTINCT ';
		// $this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'facturedet as fd, '.MAIN_DB_PREFIX.'societe as s)';
		// $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (fd.fk_product = p.rowid)';
			// $this->export_sql_end[$r] .=' WHERE f.fk_soc = s.rowid AND f.rowid = fd.fk_facture';
			// $this->export_sql_order[$r] .=' ORDER BY s.nom';
			// $r++;
			$this->export_code[$r] = $this->rights_class.'_orders';
			$this->export_label[$r] = 'DolistoreOrders';
			$this->export_enabled[$r] = 'isModEnabled("dolistorextract")';
			$this->export_permission[$r] = array(array('dolistorextract', 'order', 'export'));
			$this->export_fields_array[$r] = array(
				'o.rowid' => 'Id',
				'o.entity' => 'Entity',
				'o.ref' => 'Ref',
				'o.dolistore_order_ref' => 'DolistoreOrderRef',
				'o.dolistore_order_date' => 'DolistoreOrderDate',
				'o.release_date' => 'DolistoreReleaseDate',
				'o.customer_name' => 'DolistoreCustomerName',
				'o.customer_email' => 'DolistoreCustomerEmail',
				'o.customer_country' => 'DolistoreCustomerCountry',
				'o.total_ht' => 'DolistoreTotalHt',
				'o.total_ttc' => 'DolistoreTotalTtc',
				'o.commission_percent' => 'DolistoreCommissionPercent',
				'o.billable_total_ht' => 'DolistoreBillableTotalHt',
				'o.status' => 'Status',
				'f.ref' => 'DolistoreLinkedInvoice',
				'o.datec' => 'DateCreation',
			);
			$this->export_TypeFields_array[$r] = array(
				'o.rowid' => 'Numeric',
				'o.entity' => 'Numeric',
				'o.ref' => 'Text',
				'o.dolistore_order_ref' => 'Text',
				'o.dolistore_order_date' => 'Date',
				'o.release_date' => 'Date',
				'o.customer_name' => 'Text',
				'o.customer_email' => 'Text',
				'o.customer_country' => 'Text',
				'o.total_ht' => 'Numeric',
				'o.total_ttc' => 'Numeric',
				'o.commission_percent' => 'Numeric',
				'o.billable_total_ht' => 'Numeric',
				'o.status' => 'Status',
				'f.ref' => 'Text',
				'o.datec' => 'Date',
			);
			$this->export_entities_array[$r] = array(
				'o.rowid' => 'dolistoreextract_order',
				'o.entity' => 'dolistoreextract_order',
				'o.ref' => 'dolistoreextract_order',
				'o.dolistore_order_ref' => 'dolistoreextract_order',
				'o.dolistore_order_date' => 'dolistoreextract_order',
				'o.release_date' => 'dolistoreextract_order',
				'o.customer_name' => 'dolistoreextract_order',
				'o.customer_email' => 'dolistoreextract_order',
				'o.customer_country' => 'dolistoreextract_order',
				'o.total_ht' => 'dolistoreextract_order',
				'o.total_ttc' => 'dolistoreextract_order',
				'o.commission_percent' => 'dolistoreextract_order',
				'o.billable_total_ht' => 'dolistoreextract_order',
				'o.status' => 'dolistoreextract_order',
				'f.ref' => 'invoice',
				'o.datec' => 'dolistoreextract_order',
			);
			$this->export_sql_start[$r] = 'SELECT DISTINCT ';
			$this->export_sql_end[$r] = ' FROM '.MAIN_DB_PREFIX.'dolistoreextract_order as o';
			$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'facture as f ON f.rowid = o.fk_facture';
			$this->export_sql_end[$r] .= ' WHERE o.entity IN ('.getEntity('dolistoreextract_order').')';
			$this->export_sql_order[$r] = ' ORDER BY o.dolistore_order_date DESC, o.rowid DESC';
			$r++;
		}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	public function init($options='')
	{
		$sql = array();

		$this->_load_tables('/dolistorextract/sql/');

		$result = $this->_init($sql, $options);
		if ($result <= 0) {
			return $result;
		}

		$result = $this->activateDeclaredScheduledJobs();
		if ($result < 0) {
			return 0;
		}

		$result = $this->migrateLegacyPermissionIds();
		if ($result < 0) {
			return 0;
		}

		$result = $this->setDefaultConfigurationConstants();
		if ($result < 0) {
			return 0;
		}

		$result = $this->initializeOrderDocumentModel();
		if ($result < 0) {
			return 0;
		}

		$result = $this->persistMulticompanySharingDefinition();
		if ($result < 0) {
			return 0;
		}

		$result = $this->createDolistoreServiceExtraField();
		if ($result < 0) {
			return 0;
		}

		$result = $this->registerDolistoreActionTriggers();
		if ($result < 0) {
			return 0;
		}

		$result = $this->cleanupLegacyActionTriggers();
		if ($result < 0) {
			return 0;
		}

		return 1;
	}

	/**
	 * Activate this module's existing scheduled jobs in the current entity.
	 *
	 * Only the native status is changed so administrator scheduling choices are preserved.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	private function activateDeclaredScheduledJobs()
	{
		global $conf;

		$methods = array('runImport', 'runInvoice', 'runDailyNotification');
		$quotedMethods = array();
		foreach ($methods as $method) {
			$quotedMethods[] = "'".$this->db->escape($method)."'";
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'cronjob SET status = 1';
		$sql .= " WHERE module_name = '".$this->db->escape($this->rights_class)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND methodename IN ('.implode(',', $quotedMethods).')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Migrate old permission identifiers to the module-id based range.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	private function migrateLegacyPermissionIds()
	{
		$permissionIdMap = array(
			104977 => $this->numero * 100 + 1,
			104978 => $this->numero * 100 + 2,
			104979 => $this->numero * 100 + 3,
			104980 => $this->numero * 100 + 4,
			104981 => $this->numero * 100 + 5,
			104982 => $this->numero * 100 + 6,
			104983 => $this->numero * 100 + 7,
			104984 => $this->numero * 100 + 8,
		);

		foreach ($permissionIdMap as $oldId => $newId) {
			$result = $this->copyLegacyPermissionRows('user_rights', 'fk_user', (int) $oldId, (int) $newId);
			if ($result < 0) {
				return -1;
			}

			$result = $this->copyLegacyPermissionRows('usergroup_rights', 'fk_usergroup', (int) $oldId, (int) $newId);
			if ($result < 0) {
				return -1;
			}
		}

		$oldIds = implode(',', array_map('intval', array_keys($permissionIdMap)));

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'user_rights WHERE fk_id IN ('.$oldIds.')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'usergroup_rights WHERE fk_id IN ('.$oldIds.')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'rights_def';
		$sql .= " WHERE module = '".$this->db->escape($this->rights_class)."'";
		$sql .= ' AND id IN ('.$oldIds.')';
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Copy legacy permission assignments when the new assignment does not exist yet.
	 *
	 * @param string $tableName Table suffix without database prefix
	 * @param string $ownerField Owner field name
	 * @param int    $oldId Legacy permission id
	 * @param int    $newId New permission id
	 * @return int 1 if OK, -1 if KO
	 */
	private function copyLegacyPermissionRows($tableName, $ownerField, $oldId, $newId)
	{
		$prefixedTable = MAIN_DB_PREFIX.$tableName;

		$sql = 'INSERT INTO '.$prefixedTable.' ('.$ownerField.', fk_id, entity)';
		$sql .= ' SELECT source.'.$ownerField.', '.((int) $newId).', source.entity';
		$sql .= ' FROM '.$prefixedTable.' AS source';
		$sql .= ' WHERE source.fk_id = '.((int) $oldId);
		$sql .= ' AND NOT EXISTS (';
		$sql .= 'SELECT 1 FROM '.$prefixedTable.' AS existing';
		$sql .= ' WHERE existing.'.$ownerField.' = source.'.$ownerField;
		$sql .= ' AND existing.fk_id = '.((int) $newId);
		$sql .= ' AND existing.entity = source.entity';
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Create product/service extrafield for Dolistore identifier mapping.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	private function createDolistoreServiceExtraField()
	{
		global $langs;

		dol_include_once('/core/class/extrafields.class.php');

		$extrafields = new ExtraFields($this->db);
		$langs->load('dolistorextract@dolistorextract');

		$res = $extrafields->fetch_name_optionals_label('product', true);
		if ($res < 0) {
			$this->error = $extrafields->error;
			return -1;
		}

		if (!empty($extrafields->attributes['product']['label']['iddolistore'])) {
			return 1;
		}

		$label = $langs->transnoentitiesnoconv('DolistoreServiceIddolistoreLabel');
		$help = $langs->transnoentitiesnoconv('DolistoreServiceIddolistoreHelp');
		$res = $extrafields->addExtraField('iddolistore', $label, 'varchar', 100, 255, 'product', 0, 0, '', '', 0, '', -1, $help);
		if ($res < 0) {
			$this->error = $extrafields->error;
			return -1;
		}

		return 1;
	}

	/**
	 * Register DoliStore order action triggers for Agenda and Notifications native modules.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	private function registerDolistoreActionTriggers()
	{
		global $langs;

		$langs->load('dolistorextract@dolistorextract');
		dol_include_once('/dolistorextract/class/actions_dolistorextract.class.php');
		if (!class_exists('ActionsDolistorextract')) {
			$this->error = 'ActionsDolistorextract class not found';
			return -1;
		}

		$triggers = ActionsDolistorextract::getBusinessEventsDefinition();
		foreach ($triggers as $code => $triggerconf) {
			$label = $this->db->escape($langs->transnoentities($triggerconf['label']));
			$description = $this->db->escape($langs->transnoentities($triggerconf['description']));
			$elementtype = $this->db->escape((string) $triggerconf['elementtype']);
			$rang = (int) $triggerconf['rang'];

			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_action_trigger (code, label, description, elementtype, rang)';
			$sql .= " SELECT '".$this->db->escape($code)."', '".$label."', '".$description."', '".$elementtype."', ".$rang;
			$sql .= ' FROM DUAL';
			$sql .= " WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."c_action_trigger WHERE code = '".$this->db->escape($code)."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}

			$sql = 'UPDATE '.MAIN_DB_PREFIX.'c_action_trigger';
			$sql .= " SET label = '".$label."', description = '".$description."', elementtype = '".$elementtype."', rang = ".$rang;
			$sql .= " WHERE code = '".$this->db->escape($code)."'";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Remove legacy non-CRUD action trigger declarations.
	 *
	 * @return int 1 if OK, -1 if KO
	 */
	private function cleanupLegacyActionTriggers()
	{
		$legacyActionCode = 'DOLISTOREEXTRACT_ORDER_'.'INVOICE';

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'c_action_trigger';
		$sql .= " WHERE code = '".$this->db->escape($legacyActionCode)."'";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param      string	$options    Options when enabling module ('', 'noboxes')
	 * @return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		$result = $this->persistMulticompanySharingDefinition();
		if ($result < 0) {
			return 0;
		}

		return $this->_remove($sql, $options);
	}

	/**
	 * Set default constants without overwriting administrator choices.
	 *
	 * @return int
	 */
	private function setDefaultConfigurationConstants()
	{
		global $conf, $mysoc;

		$defaultInvoiceVatRate = '0';
		if (getDolGlobalString('DOLISTOREXTRACT_INVOICE_TVA_RATE') === '' && is_object($mysoc)) {
			$detectedInvoiceVatRate = get_default_tva($mysoc, $mysoc);
			if ($detectedInvoiceVatRate !== -1 && $detectedInvoiceVatRate !== '-1' && trim((string) $detectedInvoiceVatRate) !== '') {
				$defaultInvoiceVatRate = (string) $detectedInvoiceVatRate;
			}
		}

		$defaults = array(
			'DOLISTOREXTRACT_ORDER_ADDON' => 'mod_dolistoreextract_order_dse',
			'DOLISTOREXTRACT_ORDER_ADDON_PDF' => 'standard',
			'DOLISTOREXTRACT_PAYMENT_RELEASE_DELAY_DAYS' => '30',
			'DOLISTOREXTRACT_INVOICE_MIN_AMOUNT_HT' => '100.00',
			'DOLISTOREXTRACT_INVOICE_TVA_RATE' => $defaultInvoiceVatRate,
			'DOLISTOREXTRACT_AUTO_IMPORT_ENABLED' => '0',
			'DOLISTOREXTRACT_AUTO_CREATE_INVOICE' => '0',
			'DOLISTOREXTRACT_AUTO_SEND_INVOICE' => '0',
			'DOLISTOREXTRACT_INVOICE_STATUS' => 'draft',
			'DOLISTOREXTRACT_DAILY_NOTIFICATION_ENABLED' => '0',
			'DOLISTOREXTRACT_V2_ARCHIVE_MODE' => '1',
		);

		foreach ($defaults as $name => $value) {
			if (getDolGlobalString($name) !== '') {
				continue;
			}
			$result = dolibarr_set_const($this->db, $name, $value, 'chaine', 0, '', (int) $conf->entity);
			if ($result <= 0) {
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Register the standard DoliStore order document model once per entity.
	 *
	 * @return int
	 */
	private function initializeOrderDocumentModel()
	{
		global $conf;

		if (getDolGlobalString('DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED') !== '') {
			return 1;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		$type = 'dolistoreextract_order';
		$model = 'standard';
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'document_model';
		$sql .= " WHERE nom = '".$this->db->escape($model)."'";
		$sql .= " AND type = '".$this->db->escape($type)."'";
		$sql .= ' AND entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$exists = (bool) $this->db->fetch_object($resql);
		$this->db->free($resql);

		if (!$exists) {
			$result = addDocumentModel($model, $type, 'Standard', 'dolistoreextract/core/modules/dolistoreextract/doc');
			if ($result <= 0) {
				return -1;
			}
		}

		$result = dolibarr_set_const($this->db, 'DOLISTOREXTRACT_ORDER_DOCUMENT_MODEL_INITIALIZED', '1', 'chaine', 0, '', (int) $conf->entity);
		if ($result <= 0) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Persist Multicompany sharing definition for external module settings.
	 *
	 * @return int
	 */
	private function persistMulticompanySharingDefinition()
	{
		global $conf;

		dol_include_once('/dolistorextract/class/actions_dolistorextract.class.php');
		if (!class_exists('ActionsDolistorextract') || !method_exists('ActionsDolistorextract', 'getMulticompanySharingDefinition')) {
			return 1;
		}

		$current = array();
		$currentRaw = getDolGlobalString('MULTICOMPANY_EXTERNAL_MODULES_SHARING');
		if (!empty($currentRaw)) {
			$decoded = json_decode($currentRaw, true);
			if (is_array($decoded)) {
				$current = $decoded;
			}
		}

		$definition = ActionsDolistorextract::getMulticompanySharingDefinition();
		$merged = array_replace_recursive($current, $definition);
		$json = json_encode($merged);
		if ($json === false) {
			return -1;
		}

		$result = dolibarr_set_const($this->db, 'MULTICOMPANY_EXTERNAL_MODULES_SHARING', $json, 'chaine', 0, '', (int) $conf->entity);
		return ($result > 0) ? 1 : -1;
	}

}
