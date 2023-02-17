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
 * Upgrade Script: 1.8.22
 */

use \MyBB\Maintenance\Process\Runtime;

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"parameters" => [],
);

$mybb = \MyBB\app(\MyBB::class);

if(empty($mybb->settings['adminemail']))
{
	$upgrade_detail['parameters']['adminemail'] = [
		'type' => 'email',
		'required' => true,
	];
}

function upgrade50_dbchanges()
{
	global $output, $cache, $db, $mybb;

	// Updating Database

	// Updating cache...

	$cache->delete("banned");

	// Moved PM wrong folder correction
	$db->update_query("privatemessages", array('folder' => 1), "folder='0'");

	// PM folder structure conversion
	$db->update_query('users', array('pmfolders' => "0**$%%$1**$%%$2**$%%$3**$%%$4**"), "pmfolders = ''");
	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$update = "'0**$%%$' || pmfolders";
			break;
		default:
			$update = "CONCAT('0**$%%$', pmfolders)";
	}
	$db->write_query("UPDATE ".TABLE_PREFIX."users SET pmfolders=".$update." WHERE pmfolders NOT LIKE '0%'");

	$db->update_query('settings', array('value' => 1), "name='nocacheheaders'");

	// Add hCaptcha support
	// Updating settings...
	$db->update_query("settings", array('name' => 'recaptchapublickey'), "name='captchapublickey'");
	$db->update_query("settings", array('name' => 'recaptchaprivatekey'), "name='captchaprivatekey'");
	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=NoCAPTCHA reCAPTCHA\r\n4=reCAPTCHA invisible\r\n5=hCAPTCHA\r\n6=hCAPTCHA invisible\r\n7=reCAPTCHA v3'), "name='captchaimage'");

	// If using fulltext then enforce minimum word length given by database
	if($mybb->settings['minsearchword'] > 0 && $mybb->settings['searchtype'] == "fulltext" && $db->supports_fulltext_boolean("posts") && $db->supports_fulltext("threads"))
	{
		// Attempt to determine minimum word length from MySQL for fulltext searches
		$query = $db->query("SHOW VARIABLES LIKE 'ft_min_word_len';");
		$min_length = $db->fetch_field($query, 'Value');
		if(is_numeric($min_length) && $mybb->settings['minsearchword'] < $min_length)
		{
			$min_length = (int) $min_length;
			$old_min_length = (int) $mybb->settings['minsearchword'];
			// Updating Minimum Search Word Length setting to match the database system configuration (was {$old_min_length}, now {$min_length})
			$db->update_query("settings", array('value' => $min_length), "name='minsearchword'");
		}
	}
}

function upgrade50_adminemail(Runtime $process)
{
	global $db, $mybb;

	if(
		empty($mybb->settings['adminemail']) &&
		filter_var($process->getParameterValue('adminemail'), FILTER_VALIDATE_EMAIL) === false
	) {
		return [
			'error' => [
				'message' => 'The email address given was invalid. Please enter a valid email address.',
			],
			'retry' => true,
		];
 	}

	// Updating Database
	$db->update_query(
		'settings',
		[
			'value' => $db->escape_string($process->getParameterValue('adminemail')),
		],
		"name='adminemail'"
	);

	return [];
}
