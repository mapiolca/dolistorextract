<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
$res = 0;
if (file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
elseif (file_exists('../../../main.inc.php')) $res = include '../../../main.inc.php';
if (!$res) die('Include of main fails');
$langs->load('dolistorextract@dolistorextract');
if (!isModEnabled('dolistorextract') || !empty($user->socid) || (empty($user->admin) && !$user->hasRight('dolistorextract', 'order', 'import'))) accessforbidden();
// The Webhost target was removed in V2. Never accept an upload that cannot be imported.
llxHeader('', $langs->trans('ImportCSVData'));
print load_fiche_titre($langs->trans('ImportCSVData'), '', 'upload');
print '<div class="warning">'.$langs->trans('DolistoreLegacyWebhostImportRemoved').'</div>';
print '<a class="button" href="'.dol_buildpath('/dolistorextract/list.php', 1).'">'.$langs->trans('DolistoreOrders').'</a>';
llxFooter();
$db->close();
