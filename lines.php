<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = include '../../main.inc.php';
if (!$res) die('Include of main fails');

require_once __DIR__.'/class/dolistoreOrder.class.php';
require_once __DIR__.'/lib/dolistoreextract.lib.php';

$langs->loadLangs(array('dolistorextract@dolistorextract', 'products'));
if (!isModEnabled('dolistorextract') || !dolistoreextractUserHasRight($user, 'order', 'read')) accessforbidden();

$id = GETPOST('id', 'int');
$object = new DolistoreOrder($db);
if ($id <= 0 || $object->fetch($id) <= 0) accessforbidden();

header('Location: '.dol_buildpath('/dolistorextract/card.php', 1).'?id='.(int) $object->id.'#dolistore-order-lines');
exit;
