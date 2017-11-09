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
 * Upgrade Script: 1.8.12
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade41_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");

	// Remove Blekko Spider, Insert Modern Spiders
	$db->delete_query('spiders', "name IN('Blekko', 'Discord', 'Applebot', 'CheckHost', 'Pingdom', 'DuckDuckGo', 'UptimeRobot')");

	$db->insert_query_multiple('spiders', array(
		array("name" => "Discord", "useragent" => "Discordbot"),
		array("name" => "Applebot", "useragent" => "Applebot"),
		array("name" => "CheckHost", "useragent" => "CheckHost"),
		array("name" => "Pingdom", "useragent" => "Pingdom.com_bot"),
		array("name" => "DuckDuckGo", "useragent" => "DuckDuckBot"),
		array("name" => "UptimeRobot", "useragent" => "UptimeRobot"),
	));

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_dbchanges2");
}

function upgrade41_dbchanges2()
{
	global $db, $output;

	$output->print_header("Updating Database");

	// Remove backslashes from last 1,000 log files.
	$query = $db->simple_select('moderatorlog', 'tid, action', "action LIKE '%\\\\\\\\%'", array(
		"order_by" => 'tid',
		"order_dir" => 'DESC',
		"limit" => 1000
	));

	while($row = $db->fetch_array($query))
	{
		$original = $row['action'];
		$stripped = stripslashes($original);

		if($stripped !== $original)
		{
			$db->update_query("moderatorlog", array(
				"action" => $db->escape_string($stripped),
			), "tid = '".$row['tid']."'");
		}
	}

	// Add Google reCAPTCHA invisible
	$db->update_query("settings", array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n2=reCAPTCHA\r\n3=Are You a Human\r\n4=NoCAPTCHA reCAPTCHA\r\n5=reCAPTCHA invisible'), "name='captchaimage'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_dbchanges3");
}

function upgrade41_dbchanges3()
{
	global $db, $output, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$guestlangs = array();
	$templang = new MyLanguage();
	$templang->set_path(MYBB_ROOT."inc/languages");
	$langs = array_keys($templang->get_languages());

	foreach($langs as $langname)
	{
		unset($templang);
		$templang = new MyLanguage();
		$templang->set_path(MYBB_ROOT."inc/languages");
		$templang->set_language($langname);
		$templang->load("global");
		if(isset($templang->guest))
		{
			$guestlangs[] = $db->escape_string($templang->guest);
		}
	}

	unset($templang);
	$guestlangs = implode("', '", $guestlangs);

	$db->update_query('posts', array('username' => ''), "uid = 0 AND username IN ('{$guestlangs}')");
	$db->update_query('threads', array('username' => ''), "uid = 0 AND username IN ('{$guestlangs}')");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_done");
}
