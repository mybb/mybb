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
	
	$values = array(
		'name'			=> 'forumteam',
		'title'			=> 'Forum Team',
		'description'	=> 'This section allows you to control various aspects of the forum team listing (showteam.php), such as aspects to consider while listing team members, and which features to enable or disable.',
		'disporder'		=> 29,
		'isdefault'		=> 1
	);

	$gid = $db->insert_query('settinggroup', $values);
	unset($values);

	$valueset = array(
		array(
			'name'			=> 'enableshowteam',
			'title'			=> 'Enable Forum Team Listing Functionality',
			'description'	=> 'If you wish to disable the forum team listing on your board, set this option to No.',
			'optionscode'	=> 'yesno',
			'value'			=> '1',
			'disporder'		=> 1,
			'gid'			=> $gid,
			'isdefault'		=> 1
		),
		array(
			'name'			=> 'showaddlgroups',
			'title'			=> 'Show Additional Groups',
			'description'	=> 'Whether the team list will populate considering additional groups as well.',
			'optionscode'	=> 'yesno',
			'value'			=> '1',
			'disporder'		=> 2,
			'gid'			=> $gid,
			'isdefault'		=> 1
		),
		array(
			'name'			=> 'showgroupleaders',
			'title'			=> 'Show Group Leaders',
			'description'	=> 'Include group leaders to show up in the team list.',
			'optionscode'	=> 'yesno',
			'value'			=> '1',
			'disporder'		=> 3,
			'gid'			=> $gid,
			'isdefault'		=> 1
		)
	);

	foreach ($valueset as $values) {
		$db->insert_query('settings', $values);
	}
	
	$cache->delete("mybb_credits");

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("43_done");
}