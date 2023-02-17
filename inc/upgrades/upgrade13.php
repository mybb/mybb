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
 * Upgrade Script: 1.4 or 1.4.1
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

function upgrade13_dbchanges()
{
	global $db, $output, $mybb;

	// Performing Queries

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `uid` )");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD INDEX ( `dateline` )");
	}

	if($db->type != "sqlite")
	{
		if($db->index_exists("users", "username"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY username");
		}

		$query = $db->simple_select("users", "username, uid", "1=1 GROUP BY uid, username HAVING count(*) > 1");
		while($user = $db->fetch_array($query))
		{
			$db->update_query("users", array('username' => $user['username']."_dup".$user['uid']), "uid='{$user['uid']}'", 1);
		}

		if($db->type == "pgsql")
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD UNIQUE(username)");
		}
		else
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD UNIQUE KEY username (username)");
		}
	}

	if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longregip longregip int NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longlastip longlastip int NOT NULL default '0'");

		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE longipaddress longipaddress int NOT NULL default '0'");
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longregip longregip int(11) NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE longlastip longlastip int(11) NOT NULL default '0'");

		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE longipaddress longipaddress int(11) NOT NULL default '0'");
	}
}

function upgrade13_dbchanges1()
{
	global $db, $output;

	// Post IP Repair Conversion

	$query = $db->simple_select("posts", "ipaddress, longipaddress, pid");
	while($post = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(my_ip2long($post['ipaddress']) < 0)
		{
			$db->update_query("posts", array('longipaddress' => my_ip2long($post['ipaddress'])), "pid = '{$post['pid']}'");
		}
	}
}

function upgrade13_dbchanges2()
{
	global $db, $output;

	// User IP Repair Conversion

	$update_array = array();

	$query = $db->simple_select("users", "regip, lastip, longlastip, longregip, uid");
	while($user = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(my_ip2long($user['regip']) < 0)
		{
			$update_array['longregip'] = (int)my_ip2long($user['regip']);
		}

		if(my_ip2long($user['lastip']) < 0)
		{
			$update_array['longlastip'] = (int)my_ip2long($user['lastip']);
		}

		if(!empty($update_array))
		{
			$db->update_query("users", $update_array, "uid = '{$user['uid']}'");
		}

		$update_array = array();
	}
}

