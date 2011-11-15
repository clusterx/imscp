<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 *
 * @copyright   2001-2006 by moleSoftware GmbH
 * @copyright   2006-2010 by ispCP | http://isp-control.net
 * @copyright   2010-2011 by i-MSCP | http://i-mscp.net
 * @link        http://i-mscp.net
 * @author      ispCP Team
 * @author      i-MSCP Team
 *
 * @license
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * The Original Code is "VHCS - Virtual Hosting Control System".
 *
 * The Initial Developer of the Original Code is moleSoftware GmbH.
 * Portions created by Initial Developer are Copyright (C) 2001-2006
 * by moleSoftware GmbH. All Rights Reserved.
 *
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 *
 * Portions created by the i-MSCP Team are Copyright (C) 2010-2011 by
 * i-MSCP a internet Multi Server Control Panel. All Rights Reserved.
 */

/**
 * Returns domain default properties.
 *
 * Note: For performance reasons, the data are retrieved once per request.
 *
 * @param int $domainAdminId User unique identifier
 * @param bool $returnWKeys    Tells whether or not return value should be a
 *                             associative array
 * @return array               If $returnWkeys is TRUE, returns an associative array
 *                             where each key is a domain propertie name. Otherwise
 *                             returns an indexed array where each value correspond
 *                             to  a propertie value, following the columns order in
 *                             database table.
 * @todo add all properties
 */
function get_domain_default_props($domainAdminId, $returnWKeys = false)
{
	static $domainProperties = null;

	if (null === $domainProperties) {
		$query = "
			SELECT
				`domain_id`, `domain_name`, `domain_gid`, `domain_uid`,
				`domain_created_id`, `domain_created`, `domain_expires`,
				`domain_last_modified`, `domain_mailacc_limit`, `domain_ftpacc_limit`,
				`domain_traffic_limit`, `domain_sqld_limit`, `domain_sqlu_limit`,
				`domain_status`, `domain_alias_limit`, `domain_subd_limit`,
				`domain_ip_id`, `domain_disk_limit`, `domain_disk_usage`,
				`domain_php`, `domain_cgi`, `allowbackup`, `domain_dns`,
				`domain_software_allowed`, `phpini_perm_system`
			FROM
				`domain`
			WHERE
				`domain_admin_id` = ?
		";
		$stmt = exec_query($query, $domainAdminId);
		$domainProperties = $stmt->fields;
	}

    if ($returnWKeys) {
		return $domainProperties;
    } else {
        return array_values($domainProperties);
    }
}

/**
 * Returns total number of subdomains that belong to a specific domain.
 *
 * Note, this function doesn't make any differentiation between sub domains and the
 * aliasses subdomains. The result is simply the sum of both.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of subdomains
 */
function get_domain_running_sub_cnt($domain_id)
{
    $query = "
		SELECT
			COUNT(*) AS `cnt`
		FROM
			`subdomain`
		WHERE
			`domain_id` = ?
	";
    $stmt1 = exec_query($query, $domain_id);

    $query = "
		SELECT
			COUNT(`subdomain_alias_id`) AS `cnt`
		FROM
			`subdomain_alias`
		WHERE
			`alias_id` IN (SELECT `alias_id` FROM `domain_aliasses` WHERE `domain_id` = ?)
	";
    $stmt2 = exec_query($query, $domain_id);

    return $stmt1->fields['cnt'] + $stmt2->fields['cnt'];
}

/**
 * Returns number of domain aliasses that belong to a specific domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of domain aliasses
 */
function get_domain_running_als_cnt($domain_id)
{
    $query = "
		SELECT
			COUNT(*) AS `cnt`
		FROM
			`domain_aliasses`
		WHERE
			`domain_id` = ?
	";
    $stmt = exec_query($query, $domain_id);

    return $stmt->fields['cnt'];
}

/**
 * Returns information about number of mail account for a specific domain.
 *
 * @param  int $domain_id     Domain unique identifier
 * @return array              An array of values where the first item is the sum of
 *                            all other items, and where each other item represents
 *                            total number of a specific Mail account type
 */
function get_domain_running_mail_acc_cnt($domain_id)
{
    /** @var $cfg iMSCP_Config_Handler_File */
    $cfg = iMSCP_Registry::get('config');

    $query = "
		SELECT
			COUNT(`mail_id`) AS `cnt`
		FROM
			`mail_users`
		WHERE
			`mail_type` RLIKE ?
		AND
			`mail_type` NOT LIKE ?
		AND
			`domain_id` = ?
	";

    if ($cfg->COUNT_DEFAULT_EMAIL_ADDRESSES == 0) {
        $query .=
            "
			AND
				`mail_acc` != 'abuse'
			AND
				`mail_acc` != 'postmaster'
			AND
				`mail_acc` != 'webmaster'
		";
    }

    $stmt = exec_query($query, array('normal_', 'normal_catchall', $domain_id));
    $dmn_mail_acc = $stmt->fields['cnt'];

    $stmt = exec_query($query, array('alias_', 'alias_catchall', $domain_id));
    $als_mail_acc = $stmt->fields['cnt'];

    $stmt = exec_query($query, array('subdom_', 'subdom_catchall', $domain_id));
    $sub_mail_acc = $stmt->fields['cnt'];

    $stmt = exec_query($query, array('alssub_', 'alssub_catchall', $domain_id));
    $alssub_mail_acc = $stmt->fields['cnt'];

    return array($dmn_mail_acc + $als_mail_acc + $sub_mail_acc + $alssub_mail_acc,
                 $dmn_mail_acc, $als_mail_acc, $sub_mail_acc, $alssub_mail_acc);
}

/**
 * Returns total number of Ftp accounts that belong to a domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Number of Ftp accounts
 */
function get_domain_running_dmn_ftp_acc_cnt($domain_id)
{
    /** @var $cfg iMSCP_Config_Handler_File */
    $cfg = iMSCP_Registry::get('config');

    $query = "
		SELECT
			`domain_name`
		FROM
			`domain`
		WHERE
			`domain_id` = ?
	";

    $stmt = exec_query($query, $domain_id);

    $query = "
		SELECT
			COUNT(*) AS `cnt`
		FROM
			`ftp_users`
		WHERE
			`userid` LIKE ?
	";

    $stmt = exec_query($query, '%' . $cfg->FTP_USERNAME_SEPARATOR .
                                      $stmt->fields['domain_name']);

    return $stmt->fields['cnt'];
}

/**
 * Returns total number of Ftp accounts that belong to subdomains of a specific
 * domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of Ftp accounts
 */
function get_domain_running_sub_ftp_acc_cnt($domain_id)
{
    $query = "SELECT `domain_name` FROM `domain` WHERE `domain_id` = ?";
    $stmt1 = exec_query($query, $domain_id);

    $query = "
		SELECT
			`subdomain_name`
		FROM
			`subdomain`
		WHERE
			`domain_id` = ?
		ORDER BY
			`subdomain_id`
	";
    $stmt2 = exec_query($query, $domain_id);

    $sub_ftp_acc_cnt = 0;

    if ($stmt2->rowCount() != 0) {
        /** @var $cfg iMSCP_Config_Handler_File */
        $cfg = iMSCP_Registry::get('config');
        $ftpSeparator = $cfg->FTP_USERNAME_SEPARATOR;

        while (!$stmt2->EOF) {
            $query = "
			    SELECT
				    COUNT(*) AS `cnt`
			    FROM
				    `ftp_users`
			    WHERE
				    `userid` LIKE ?
		    ";
            $stmt3 = exec_query($query,
                                '%' . $ftpSeparator .
                                $stmt2->fields['subdomain_name'] . '.' .
                                $stmt1->fields['domain_name']);

            $sub_ftp_acc_cnt += $stmt3->fields['cnt'];
            $stmt2->moveNext();
        }
    }

    return $sub_ftp_acc_cnt;
}

/**
 * Returns total number of Ftp accounts that belong to domain aliasses of a specific
 * domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of Ftp accounts
 */
function get_domain_running_als_ftp_acc_cnt($domain_id)
{
    $query = "
		SELECT
			`alias_name`
		FROM
			`domain_aliasses`
		WHERE
			`domain_id` = ?
		ORDER BY
			`alias_id`
	";
    $stmt1 = exec_query($query, $domain_id);

    $als_ftp_acc_cnt = 0;

    if ($stmt1->rowCount() != 0) {
        /** @var $cfg iMSCP_Config_Handler_File */
        $cfg = iMSCP_Registry::get('config');
        $ftpSeparator = $cfg->FTP_USERNAME_SEPARATOR;
        while (!$stmt1->EOF) {
            $query = "
			    SELECT
				    COUNT(*) AS cnt
			    FROM
				    `ftp_users`
			    WHERE
				    `userid` LIKE ?
		    ";
            $stmt2 = exec_query($query,
                                '%' . $ftpSeparator . $stmt1->fields['alias_name']);
            $als_ftp_acc_cnt += $stmt2->fields['cnt'];
            $stmt1->moveNext();
        }
    }

    return $als_ftp_acc_cnt;
}

/**
 * Returns information about number of Ftp account for a specific domain.
 *
 * @param  int $domain_id     Domain unique identifier
 * @return array              An array of values where the first item is the sum of
 *                            all other items, and where each other item represents
 *                            total number of a specific Ftp account type
 */
function get_domain_running_ftp_acc_cnt($domain_id)
{
    $dmn_ftp_acc_cnt = get_domain_running_dmn_ftp_acc_cnt($domain_id);
    $sub_ftp_acc_cnt = get_domain_running_sub_ftp_acc_cnt($domain_id);
    $als_ftp_acc_cnt = get_domain_running_als_ftp_acc_cnt($domain_id);

    return array($dmn_ftp_acc_cnt + $sub_ftp_acc_cnt + $als_ftp_acc_cnt,
                 $dmn_ftp_acc_cnt, $sub_ftp_acc_cnt, $als_ftp_acc_cnt);
}

/**
 * Returns total number of databases that belong to a specific domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of databases for a specific domain
 */
function get_domain_running_sqld_acc_cnt($domain_id)
{
    $query = "
		SELECT
			COUNT(*) AS `cnt`
		FROM
			`sql_database`
		WHERE
			`domain_id` = ?
	";
    $stmt = exec_query($query, $domain_id);

    return $stmt->fields['cnt'];
}

/**
 * Returns total number of SQL user that belong to a specific domain.
 *
 * @param  int $domain_id Domain unique identifier
 * @return int Total number of SQL users for a specific domain
 */
function get_domain_running_sqlu_acc_cnt($domain_id)
{
    $query = "
		SELECT DISTINCT
			`t1`.`sqlu_name`
		FROM
			`sql_user` AS `t1`, `sql_database` AS `t2`
		WHERE
			`t2`.`domain_id` = ?
		AND
			`t2`.`sqld_id` = `t1`.`sqld_id`
	";
    $stmt = exec_query($query, $domain_id);

    return $stmt->recordCount();
}

/**
 * Returns both total number of database and SQL user that belong to a specific
 * domain.
 *
 * @param  int $domain_id     Domain unique identifier
 * @return array              An array where the first item is the Database total
 *                            number, and the second the SQL users total number.
 */
function get_domain_running_sql_acc_cnt($domain_id)
{
    return array(
        get_domain_running_sqld_acc_cnt($domain_id),
        get_domain_running_sqlu_acc_cnt($domain_id));
}

/**
 * Must be documented.
 *
 * @param  int $domain_id Domain unique identifier
 * @return array
 */
function get_domain_running_props_cnt($domain_id)
{
    $sub_cnt = get_domain_running_sub_cnt($domain_id);
    $als_cnt = get_domain_running_als_cnt($domain_id);

    list($mail_acc_cnt,,,,) = get_domain_running_mail_acc_cnt($domain_id);
    list($ftp_acc_cnt,,,) = get_domain_running_ftp_acc_cnt($domain_id);
    list($sqld_acc_cnt, $sqlu_acc_cnt) = get_domain_running_sql_acc_cnt($domain_id);

    return array($sub_cnt, $als_cnt, $mail_acc_cnt, $ftp_acc_cnt, $sqld_acc_cnt,
                 $sqlu_acc_cnt);
}

/**
 * Return domain unique identifier that belong to a specific user account.
 *
 * @param  int $userId User unique identifier
 * @return int Unique identifier of user's domain
 */
function get_user_domain_id($userId)
{
	static $domainId = null;

	if(null === $domainId) {
    	$query = 'SELECT `domain_id` FROM `domain` WHERE `domain_admin_id` = ?';
    	$stmt = exec_query($query, $userId);
		$domainId = $stmt->fields['domain_id'];
	}

    return $domainId;
}

/**
 * Translate mail type.
 *
 * @param  string $mail_type
 * @return string Translated mail type
 */
function user_trans_mail_type($mail_type)
{
    if ($mail_type === MT_NORMAL_MAIL) {
        return tr('Domain mail');
    } else if ($mail_type === MT_NORMAL_FORWARD) {
        return tr('Email forward');
    } else if ($mail_type === MT_ALIAS_MAIL) {
        return tr('Alias mail');
    } else if ($mail_type === MT_ALIAS_FORWARD) {
        return tr('Alias forward');
    } else if ($mail_type === MT_SUBDOM_MAIL) {
        return tr('Subdomain mail');
    } else if ($mail_type === MT_SUBDOM_FORWARD) {
        return tr('Subdomain forward');
    } else if ($mail_type === MT_ALSSUB_MAIL) {
        return tr('Alias subdomain mail');
    } else if ($mail_type === MT_ALSSUB_FORWARD) {
        return tr('Alias subdomain forward');
    } else if ($mail_type === MT_NORMAL_CATCHALL) {
        return tr('Domain mail');
    } else if ($mail_type === MT_ALIAS_CATCHALL) {
        return tr('Domain mail');
    } else {
        return tr('Unknown type');
    }
}

/**
 * Count SQL user by name.
 *
 * @param string $sqlu_name SQL user name to match against
 * @return int
 */
function count_sql_user_by_name($sqlu_name)
{
    $query = "
		SELECT
			COUNT(*) AS `cnt`
		FROM
			`sql_user`
		WHERE
			`sqlu_name` = ?
	";
    $stmt = exec_query($query, $sqlu_name);

    return $stmt->fields['cnt'];
}

/**
 * Deletes a SQL user.
 *
 * @param  int $domain_id Domain unique identifier
 * @param  int $db_user_id Sql user unique identifier
 * @return
 */
function sql_delete_user($domain_id, $db_user_id)
{
    $query = "
		SELECT
			`t1`.`sqld_id`, `t1`.`sqlu_name`, `t2`.`sqld_name`, `t1`.`sqlu_name`
		FROM
			`sql_user` AS `t1`,
			`sql_database` AS `t2`
		WHERE
			`t1`.`sqld_id` = `t2`.`sqld_id`
		AND
			`t2`.`domain_id` = ?
		AND
			`t1`.`sqlu_id` = ?
	";
    $stmt = exec_query($query, array($domain_id, $db_user_id));

    if ($stmt->rowCount() == 0) {
        if ($_SESSION['user_type'] === 'admin'
            || $_SESSION['user_type'] === 'reseller'
        ) {
            return;
        }
        redirectTo('sql_manage.php');
    }

    // remove from i-MSCP sql_user table.
    $query = 'DELETE FROM `sql_user` WHERE `sqlu_id` = ?';
    exec_query($query, $db_user_id);

    update_reseller_c_props(get_reseller_id($domain_id));

    $db_name = quoteIdentifier($stmt->fields['sqld_name']);
    $db_user_name = $stmt->fields['sqlu_name'];

    if (count_sql_user_by_name($stmt->fields['sqlu_name']) == 0) {
        // revoke grants on global level, if any;
        $query = "REVOKE ALL ON *.* FROM ?@'%'";
        exec_query($query, $db_user_name);

        $query = "REVOKE ALL ON *.* FROM ?@localhost";
        exec_query($query, $db_user_name);

        // delete user record from mysql.user table;
        $query = "DROP USER ?@'%'";
        exec_query($query, $db_user_name);

        $query = "DROP USER ?@'localhost'";
        exec_query($query, $db_user_name);

        // flush privileges.
        $query = "FLUSH PRIVILEGES";
        execute_query($query);
    } else {
        $query = "REVOKE ALL ON $db_name.* FROM ?@'%'";
        exec_query($query, $db_user_name);

        $query = "REVOKE ALL ON $db_name.* FROM ?@localhost";
        exec_query($query, $db_user_name);
    }
}

/**
 * Checks if an user has permissions on a specific SQL user.
 *
 * @param  int $db_user_id SQL user unique identifier.
 * @return bool TRUE if user have permission on SQL user, FALSE otherwise.
 */
function check_user_sql_perms($db_user_id)
{
    if (who_owns_this($db_user_id, 'sqlu_id') != $_SESSION['user_id']) {
        return false;
    }

    return true;
}

/**
 * Checks if an user has permissions on  specific SQL Database.
 *
 * @param  int $db_id Database unique identifier
 * @return bool TRUE if user have permission on SQL user, FALSE otherwise.
 */
function check_db_sql_perms($db_id)
{
    if (who_owns_this($db_id, 'sqld_id') != $_SESSION['user_id']) {
        return false;
    }

    return true;
}

/**
 * Checks if an user has permissions on a specific Ftp account.
 *
 * @param  int $ftp_acc Ftp account unique identifier
 * @return bool TRUE if user have permission on Ftp account, FALSE otherwise.
 */
function check_ftp_perms($ftp_acc)
{
    if (who_owns_this($ftp_acc, 'ftp_user') != $_SESSION['user_id']) {
        return false;
    }

    return true;
}

/**
 * Deletes a SQL database.
 *
 * @param  int $domain_id Domain unique identifier
 * @param  int $database_id Databse unique identifier
 * @return
 */
function delete_sql_database($domain_id, $database_id)
{
    $query = "
		SELECT
			`sqld_name` AS `db_name`
		FROM
			`sql_database`
		WHERE
			`domain_id` = ?
		AND
			`sqld_id` = ?
	";
    $stmt = exec_query($query, array($domain_id, $database_id));

    if ($stmt->recordCount() == 0) {
        if ($_SESSION['user_type'] === 'admin'
            || $_SESSION['user_type'] === 'reseller'
        ) {
            return;
        }

        redirectTo('sql_manage.php');
    }

    $db_name = quoteIdentifier($stmt->fields['db_name']);

    // have we any users assigned to this database;
    $query = "
		SELECT
			`t2`.`sqlu_id` AS `db_user_id`,
			`t2`.`sqlu_name` AS `db_user_name`
		FROM
			`sql_database` AS `t1`,
			`sql_user` AS `t2`
		WHERE
			`t1`.`sqld_id` = `t2`.`sqld_id`
		AND
			`t1`.`domain_id` = ?
		AND
			`t1`.`sqld_id` = ?
	";
    $stmt = exec_query($query, array($domain_id, $database_id));

    if ($stmt->rowCount() != 0) {
        while (!$stmt->EOF) {
            $db_user_id = $stmt->fields['db_user_id'];
            sql_delete_user($domain_id, $db_user_id);
            $stmt->moveNext();
        }
    }

    exec_query("DROP DATABASE IF EXISTS $db_name");

    write_log($_SESSION['user_logged'] . ': delete SQL database: ' . tohtml($db_name), E_USER_NOTICE);

    $query = "DELETE FROM `sql_database` WHERE `domain_id` = ? AND `sqld_id` = ?";
    exec_query($query, array($domain_id, $database_id));

    update_reseller_c_props(get_reseller_id($database_id));
}

/**
 * Returns translated gender code.
 *
 * @param string $code Gender code to be returned
 * @param bool $nullOnBad Tells whether or not null must be returned on unknow $code
 * @return null|string Translated gender or null in some circonstances.
 */
function get_gender_by_code($code, $nullOnBad = false)
{
    switch (strtolower($code)) {
        case 'm':
        case 'M':
            return tr('Male');
        case 'f':
        case 'F':
            return tr('Female');
        default:
            return (!$nullOnBad) ? tr('Unknown') : null;
    }
}

/**
 * Checks if a mount point exists.
 *
 * @param  int $domain_id Domain unique identifier
 * @param  string $mnt_point mount point to check
 * @return bool TRUE if the mount point exists, FALSE otherwise
 */
function mount_point_exists($domain_id, $mnt_point)
{
    $query = "
		SELECT
			`t1`.`domain_id`, `t2`.`alias_mount`, `t3`.`subdomain_mount`,
			`t4`.`subdomain_alias_mount`
		FROM
			`domain` AS `t1`
		LEFT JOIN
			(`domain_aliasses` AS `t2`)
		ON
			(`t1`.`domain_id` = `t2`.`domain_id`)
		LEFT JOIN
			(`subdomain` AS `t3`)
		ON
			(`t1`.`domain_id` = `t3`.`domain_id`)
		LEFT JOIN
			(`subdomain_alias` AS `t4`)
		ON
			(`t2`.`alias_id` = `t4`.`alias_id`)
		WHERE
			`t1`.`domain_id` = ?
		AND
			(
				`alias_mount` = ?
			OR
				`subdomain_mount` = ?
			OR
				`subdomain_alias_mount` = ?
			)
	";

    $stmt = exec_query($query,  array(
                                     $domain_id, $mnt_point, $mnt_point, $mnt_point));

    if ($stmt->recordCount() != 0) {
        return true;
    }

    return false;
}

/**
 * Tells whether or not the given feature is available for the customer.
 *
 * @author Laurent Declercq <l.declercq@nuxwin.com>
 * @throws iMSCP_Exception When $featureName is not known
 * @param string $featureName Feature name
 * @param bool $reload If true force data to be reloaded
 * @return return bool TRUE if $featureName is available for customer, FALSE otherwise
 */
function customerHasFeature($featureName, $reload = false)
{
	static $availableFeatures = null;
	$featureName = strtolower($featureName);

	if (null === $availableFeatures || $reload) {
		/** @var $cfg iMSCP_Config_Handler_File */
		$cfg = iMSCP_Registry::get('config');
		$domainProperties = get_domain_default_props((int)$_SESSION['user_id'], true);

		$availableFeatures = array(
			'domain' => ($domainProperties['domain_alias_limit'] != '-1'
						 || $domainProperties['domain_subd_limit'] != '-1'
						 || $domainProperties['domain_dns'] != 'no') ? true : false,
			'php' => ($domainProperties['domain_php'] == 'yes') ? true : false,
			'php_editor' => ($domainProperties['phpini_perm_system'] == 'yes') ? true : false,
			'cgi' => ($domainProperties['domain_cgi'] == 'yes') ? true : false,
			'ftp' => ($domainProperties['domain_ftpacc_limit'] != '-1') ? true : false,
			'sql' => ($domainProperties['domain_sqld_limit'] != '-1') ? true : false,
			'mail' => ($domainProperties['domain_mailacc_limit'] != '-1') ? true : false,
			'subdomains' => ($domainProperties['domain_subd_limit'] != '-1') ? true : false,
			'domain_aliasses' => ($domainProperties['domain_alias_limit'] != '-1') ? true : false,
			'custom_dns_records' => ($domainProperties['domain_dns'] != 'no') ? true : false,
			'awstats' => ($cfg->AWSTATS_ACTIVE != 'no') ? true : false,
			'backup' => ($cfg->BACKUP_DOMAINS != 'no' && $domainProperties['allowbackup'] != 'no') ? true : false,
			'protected_areas' => true,
			'custom_error_pages' => true,
			'aps' => ($domainProperties['domain_software_allowed'] != 'no') ? true : false);

		if (($cfg->IMSCP_SUPPORT_SYSTEM)) {
			$query = "SELECT `support_system` FROM `reseller_props` WHERE `reseller_id` = ?";
			$stmt = exec_query($query, $_SESSION['user_created_by']);
			$availableFeatures['support'] = ($stmt->fields['support_system'] == 'yes') ? true : false;
		} else {
			$availableFeatures['support'] = false;
		}
	}

	if (!array_key_exists($featureName, $availableFeatures)) {
		throw new iMSCP_Exception(sprintf("Feature %s is not known by the customerHasFeature() function.", $featureName));
	}

	return $availableFeatures[$featureName];
}
