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
 * Upgrade Script: 1.8.13
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

	// Remove Blekko Spider
	$db->delete_query('spiders', 'name=\'Blekko\'');

	// Insert Modern Spiders
	$db->insert_query('spiders', array("name" => "Discord", "useragent" => "Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)"));
	$db->insert_query('spiders', array("name" => "Applebot", "useragent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/600.2.5 (KHTML, like Gecko) Version/8.0.2 Safari/600.2.5 (Applebot/0.1; +http://www.apple.com/go/applebot)"));
	$db->insert_query('spiders', array("name" => "CheckHost", "useragent" => "CheckHost (http://check-host.net/)"));
	$db->insert_query('spiders', array("name" => "Pingdom", "useragent" => "Pingdom.com_bot_version_1.4_(http://www.pingdom.com)"));
	$db->insert_query('spiders', array("name" => "DuckDuckGo", "useragent" => "DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)"));
	$db->insert_query('spiders', array("name" => "UptimeRobot", "useragent" => "Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)"));

  	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("41_dbchanges2");
}

function upgrade41_dbchanges2()
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
	$output->print_footer("37_done");
}
