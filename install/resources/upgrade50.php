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

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade50_dbchanges()
{
	global $output, $cache, $db, $mybb;

	$output->print_header("Updating Database");

	echo "<p>Updating cache...</p>";

	$cache->delete("banned");

	$db->update_query('settings', array('value' => 1), "name='nocacheheaders'");

	// Add hCaptcha support
	echo "<p>Updating settings...</p>";
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
			echo "<p>Updating Minimum Search Word Length setting to match the database system configuration (was {$old_min_length}, now {$min_length})</p>";
			$db->update_query("settings", array('value' => $min_length), "name='minsearchword'");
		}
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("50_done");
}
