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

	$db->update_query('settings', array('optionscode' => 'numeric\r\nmin=0'), "name IN ('avatarsize', 'loginattemptstimeout', 'maxattachments', 'maxmultipagelinks', 'maxpolloptions')");
	$db->update_query('settings', array('optionscode' => 'select\r\n0=No CAPTCHA\r\n1=MyBB Default CAPTCHA\r\n4=NoCAPTCHA reCAPTCHA\r\n5=reCAPTCHA invisible'), "name='captchaimage'");

	if($mybb->settings['captchaimage'] == 2)
	{
		$db->update_query('settings', array('value' => 1), "name='captchaimage'"); // Reset CAPTCHA to MyBB Default
		$db->update_query('settings', "value=''", 'name IN (\'captchapublickey\', \'captchaprivatekey\''); // Clean out stored credential keys
	}
	
	if($db->field_exists('users', 'aim'))
	{
		$db->drop_column('aim', 'users');
	}
	$db->delete_query("settings", "name='allowaimfield'");
	
	$db->update_query('settings', array('disporder' => 13), "name='cookiesecureflag'");
	$db->update_query('settings', array('disporder' => 14), "name='showvernum'");
	$db->update_query('settings', array('disporder' => 15), "name='mailingaddress'");
	$db->update_query('settings', array('disporder' => 16), "name='faxno'");

	$values = array(
		'name'			=> 'cookiesamesiteflag',
		'title'			=> 'SameSite Cookie Flag',
		'description'	=> 'Authentication cookies will carry the SameSite flag to prevent CSRF attacks. Keep this disabled if you expect cross-origin POST requests.',
		'optionscode'	=> 'yesno',
		'value'			=> '1',
		'disporder'		=> 12,
		'gid'			=> 2,
		'isdefault'		=> 1
	);

	$db->insert_query('settings', $values);

	$cache->delete("mybb_credits");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("43_done");
}