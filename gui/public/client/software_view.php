<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2018 by i-MSCP Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

use iMSCP_Registry as Registry;
use iMSCP\TemplateEngine;

/***********************************************************************************************************************
 *  Functions
 */

/**
 * Generate page and return software unique identifier
 *
 * @param TemplateEngine $tpl Template engine instance
 * @return int software unique identifier
 */
function client_generatePage($tpl)
{
    $softwareId = intval($_GET['id']);
    $domainProperties = get_domain_default_props($_SESSION['user_id']);
    $stmt = exec_query('SELECT created_by FROM admin WHERE admin_id = ?', [$_SESSION['user_id']]);
    get_software_props($tpl, $domainProperties['domain_id'], $softwareId, $stmt->fetchColumn());
    return $softwareId;
}

/***********************************************************************************************************************
 * Main
 */

require_once 'imscp-lib.php';

check_login('user');
Registry::get('iMSCP_Application')->getEventsManager()->dispatch(iMSCP_Events::onClientScriptStart);
customerHasFeature('aps') && isset($_GET['id']) or showBadRequestErrorPage();

$tpl = new TemplateEngine();
$tpl->define([
    'layout'                  => 'shared/layouts/ui.tpl',
    'page'                    => 'client/software_view.tpl',
    'page_message'            => 'layout',
    'software_message'        => 'page',
    'software_install'        => 'page',
    'installed_software_info' => 'page',
    'software_item'           => 'page',
    'no_software'             => 'page'
]);
$tpl->assign([
    'TR_PAGE_TITLE'    => tr('Client / Webtools / Software / View'),
    'SOFTWARE_ID'      => client_generatePage($tpl),
    'TR_VIEW_SOFTWARE' => tr('Software details'),
    'TR_NAME'          => tr('Software'),
    'TR_VERSION'       => tr('Version'),
    'TR_LANGUAGE'      => tr('Language'),
    'TR_TYPE'          => tr('Type'),
    'TR_DB'            => tr('Database required'),
    'TR_LINK'          => tr('Homepage'),
    'TR_DESC'          => tr('Description'),
    'TR_BACK'          => tr('Back'),
    'TR_INSTALL'       => tr('Install'),
    'TR_SOFTWARE_MENU' => tr('Software installation')
]);

generateNavigation($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
Registry::get('iMSCP_Application')->getEventsManager()->dispatch(iMSCP_Events::onClientScriptEnd, ['templateEngine' => $tpl]);
$tpl->prnt();

unsetMessages();
