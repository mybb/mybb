<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.8.1, 1.8.2 or 1.8.3
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade32_dbchanges()
{
	global $db, $output;
	
	// Unset old ACP cookies from front-end since they're not needed anymore
	my_unsetcookie('adminsid');
	my_unsetcookie('acploginattempts');
	my_unsetcookie('acp_view');
	my_unsetcookie('inlinemod_useracp');

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	if($db->field_exists('candeletereputations', 'usergroups'))
	{
		$db->drop_column("usergroups", "candeletereputations");
	}

	if($db->field_exists('authsecret', 'adminoptions'))
	{
		$db->drop_column("adminoptions", "authsecret");
	}

	if($db->field_exists('recovery_codes', 'adminoptions'))
	{
		$db->drop_column("adminoptions", "recovery_codes");
	}

	if($db->field_exists('authenticated', 'adminsessions'))
	{
		$db->drop_column("adminsessions", "authenticated");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "candeletereputations", "smallint NOT NULL default '0' AFTER cangivereputations");
			$db->add_column("adminoptions", "authsecret", "varchar(16) NOT NULL default ''");
			$db->add_column("adminoptions", "recovery_codes", "varchar(177) NOT NULL default ''");
			$db->add_column("adminsessions", "authenticated", "smallint NOT NULL default '0'");
			break;
		case "sqlite":
			$db->add_column("usergroups", "candeletereputations", "tinyint(1) NOT NULL default '0'");
			$db->add_column("adminoptions", "authsecret", "varchar(16) NOT NULL default ''");
			$db->add_column("adminoptions", "recovery_codes", "varchar(177) NOT NULL default ''");
			$db->add_column("adminsessions", "authenticated", "tinyint(1) NOT NULL default '0'");
			break;
		default:
			$db->add_column("usergroups", "candeletereputations", "tinyint(1) NOT NULL default '0' AFTER cangivereputations");
			$db->add_column("adminoptions", "authsecret", "varchar(16) NOT NULL default ''");
			$db->add_column("adminoptions", "recovery_codes", "varchar(177) NOT NULL default ''");
			$db->add_column("adminsessions", "authenticated", "tinyint(1) NOT NULL default '0'");
			break;
	}

	// Delete forumpermissions belonging to a deleted forum
	$db->delete_query("forumpermissions", "fid NOT IN(SELECT fid FROM {$db->table_prefix}forums)");

	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=Are You a Human\r\n4=NoCAPTCHA reCAPTCHA'), "name='captchaimage'");
	
	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("32_done");
}