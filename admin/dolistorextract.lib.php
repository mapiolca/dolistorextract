<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file		admin/dolistorextract.lib.php
 * \ingroup	dolistorextract
 * \brief	Library file for module admin pages.
 */

/**
 * Prepare admin pages header.
 *
 * @return array<int, array<int, string>>
 */
function dolistorextractAdminPrepareHead()
{
	global $langs;

	$langs->load("dolistorextract@dolistorextract");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php', 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php?mode=customerorders', 1);
	$head[$h][1] = $langs->trans("DolistorextractCustomerOrders");
	$head[$h][2] = 'customerorders';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/setup.php?mode=emailsimap', 1);
	$head[$h][1] = $langs->trans("DolistorextractEmailsImap");
	$head[$h][2] = 'emailsimap';
	$h++;

	$head[$h][0] = dol_buildpath('/dolistorextract/admin/about.php', 1);
	$head[$h][1] = $langs->trans("DolistorextractAbout");
	$head[$h][2] = 'about';

	return $head;
}
