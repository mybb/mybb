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
 * Upgrade Script: 1.8.8
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade37_dbchanges()
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
