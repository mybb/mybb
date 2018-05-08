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
 * Upgrade Script: 1.8.15
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade43_dbchanges()
{
	global $output, $mybb, $db, $cache;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary upgrade queries...</p>";
	flush();

	$db->update_query('settings', array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n4=NoCAPTCHA reCAPTCHA\r\n5=reCAPTCHA invisible'), "name='captchaimage'");

	if($mybb->settings['captchaimage'] == 2)
	{
		$db->update_query('settings', array('value' => 1), "name='captchaimage'"); // Reset CAPTCHA to MyBB Default
		$db->update_query('settings', "value=''", 'name IN (\'captchapublickey\', \'captchaprivatekey\''); // Clean out stored credential keys
	}
	
	$cache->delete("mybb_credits");

	if($db->field_exists('users', 'aim'))
	{
		$db->drop_column('aim', 'users');
	}
	$db->delete_query("settings", "name='allowaimfield'");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("43_done");
}