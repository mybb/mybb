<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.2.3
 */

/** NEEDS TO BE CHANGED PENDING FINAL RELEASE **/
$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"requires_deactivated_plugins" => 1,
);

@set_time_limit(0);

function upgrade9_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `uid` )");
	$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX ( `visible` )");
	
	if(!$db->field_exists('recipients', "privatemessages"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD recipients text NOT NULL AFTER fromid");
	}
	
	if(!$db->field_exists('deletetime', "privatemessages"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD deletetime bigint(30) NOT NULL default '0' AFTER dateline");
	}
	
	if(!$db->field_exists('maxpmrecipients', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxpmrecipients int(4) NOT NULL default '5' AFTER pmquota");
	}

	if($db->field_exists('newpms', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP newpms;");
	}
	
	if(!$db->field_exists('keywords', "searchlog"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."searchlog ADD keywords text NOT NULL AFTER querycache");
	}
	
	if(!$db->field_exists('start_day', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD start_day tinyint(2) unsigned NOT NULL");
	}

  	if(!$db->field_exists('start_month', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD start_month tinyint(2) unsigned NOT NULL");
  	}
	
	if(!$db->field_exists('start_year', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD start_year smallint(4) unsigned NOT NULL");
  	}
	
	if(!$db->field_exists('end_day', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD end_day tinyint(2) unsigned NOT NULL");
  	}
	
	if(!$db->field_exists('end_month', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD end_month tinyint(2) unsigned NOT NULL");
  	}
	
	if(!$db->field_exists('end_year', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD end_year smallint(4) unsigned NOT NULL");
  	}
	
	if(!$db->field_exists('repeat_days', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD repeat_days varchar(20) NOT NULL");
  	}
	
	if(!$db->field_exists('start_time_hours', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD start_time_hours varchar(2) NOT NULL");
  	}
	
	if(!$db->field_exists('start_time_mins', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD start_time_mins varchar(2) NOT NULL");
	}
  	
	if(!$db->field_exists('end_time_hours', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD end_time_hours varchar(2) NOT NULL");
	}
  	
	if(!$db->field_exists('end_time_mins', "events"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."events ADD end_time_mins varchar(2) NOT NULL");
	}
	
	if($db->table_exists("maillogs"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."maillogs");
	}
	
	if($db->table_exists("mailerrors"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."mailerrors");
	}
	
	if($db->table_exists("promotions"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."promotions");
	}
	
	$db->query("CREATE TABLE ".TABLE_PREFIX."maillogs (
		mid int unsigned NOT NULL auto_increment,
		subject varchar(200) not null default '',
		message text NOT NULL default '',
		dateline bigint(30) NOT NULL default '0',
		fromuid int unsigned NOT NULL default '0',
		fromemail varchar(200) not null default '',
		touid bigint(30) NOT NULL default '0',
		toemail varchar(200) NOT NULL default '',
		tid int unsigned NOT NULL default '0',
		ipaddress varchar(20) NOT NULL default '',
		PRIMARY KEY(mid)
	) TYPE=MyISAM;");

	$db->query("CREATE TABLE ".TABLE_PREFIX."mailerrors(
		eid int unsigned NOT NULL auto_increment,
		subject varchar(200) NOT NULL default '',
		toaddress varchar(150) NOT NULL default '',
		fromaddress varchar(150) NOT NULL default '',
		dateline bigint(30) NOT NULL default '0',
		error text NOT NULL,
		smtperror varchar(200) NOT NULL default '',
		smtpcode int(5) NOT NULL default '0',
		PRIMARY KEY(eid)
 	) TYPE=MyISAM;");
	
	$db->query("CREATE TABLE ".TABLE_PREFIX."promotions(
		pid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		description text NOT NULL,
		enabled int(1) NOT NULL default '1',
		logging int(1) NOT NULL default '0',
		posts int NOT NULL default '0',
		posttype varchar(120) NOT NULL default '',
		registered int NOT NULL default '0',
		reputations int NOT NULL default '0',
		reputationtype varchar(120) NOT NULL default '',
		requirements varchar(200) NOT NULL default '',
		originalusergroup varchar(200) NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		usergrouptype varchar(120) NOT NULL default '0',
		PRIMARY KEY(pid)
 	) TYPE=MyISAM;");
	
	$db->query("CREATE TABLE ".TABLE_PREFIX."promotionlogs(
		plid int unsigned NOT NULL auto_increment,
		pid int unsigned NOT NULL default '0',
		uid int unsigned NOT NULL default '0',
		oldusergroup smallint unsigned NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		PRIMARY KEY(plid)
 	) TYPE=MyISAM;");

	if(!$db->field_exists('maxemails', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxemails int(3) NOT NULL default '5' AFTER cansendemail");
	}
	
	if(!$db->field_exists('parseorder', "mycode"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."mycode ADD parseorder smallint unsigned NOT NULL default '0' AFTER active");
	}
	
	if(!$db->field_exists('mod_edit_posts', "forums"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD mod_edit_posts char(3) NOT NULL default '' AFTER modthreads");
	}

	if(!$db->field_exists('pmnotice', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE pmpopup pmnotice char(3) NOT NULL default ''");
	}
	
	if($db->table_exists("tasks"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."tasks");
	}
	
	if($db->table_exists("tasklog"))
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."tasklog");
	}

	$db->query("CREATE TABLE ".TABLE_PREFIX."tasks (
		tid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		description text NOT NULL,
		file varchar(30) NOT NULL default '',
		minute varchar(200) NOT NULL default '',
		hour varchar(200) NOT NULL default '',
		day varchar(100) NOT NULL default '',
		month varchar(30) NOT NULL default '',
		weekday varchar(15) NOT NULL default '',
		nextrun bigint(30) NOT NULL default '0',
		lastrun bigint(30) NOT NULL default '0',
		enabled int(1) NOT NULL default '1',
		logging int(1) NOT NULL default '0',
		locked bigint(30) NOT NULL default '0',
		PRIMARY KEY(tid)
	) TYPE=MyISAM;");


	$db->query("CREATE TABLE ".TABLE_PREFIX."tasklog (
		lid int unsigned NOT NULL auto_increment,
		tid int unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		data text NOT NULL,
		PRIMARY KEY(lid)
	) TYPE=MyISAM;");


	include_once MYBB_ROOT."inc/functions_task.php";
	$tasks = file_get_contents(INSTALL_ROOT.'resources/tasks.xml');
	$parser = new XMLParser($tasks);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	// Insert scheduled tasks
	foreach($tree['tasks'][0]['task'] as $task)
	{
		$new_task = array(
			'title' => $db->escape_string($task['title'][0]['value']),
			'description' => $db->escape_string($task['description'][0]['value']),
			'file' => $db->escape_string($task['file'][0]['value']),
			'minute' => $db->escape_string($task['minute'][0]['value']),
			'hour' => $db->escape_string($task['hour'][0]['value']),
			'day' => $db->escape_string($task['day'][0]['value']),
			'weekday' => $db->escape_string($task['weekday'][0]['value']),
			'month' => $db->escape_string($task['month'][0]['value']),
			'enabled' => $db->escape_string($task['enabled'][0]['value']),
			'logging' => $db->escape_string($task['logging'][0]['value'])
		);

		$new_task['nextrun'] = fetch_next_run($new_task);

		$db->insert_query("tasks", $new_task);
		$taskcount++;
	}

	$db->query("RENAME TABLE ".TABLE_PREFIX."favorites TO ".TABLE_PREFIX."threadsubscriptions");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions CHANGE fid sid int unsigned NOT NULL auto_increment");
	$db->query("UPDATE ".TABLE_PREFIX."threadsubscriptions SET type='0' WHERE type='f'");
	$db->query("UPDATE ".TABLE_PREFIX."threadsubscriptions SET type='1' WHERE type='s'");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions CHANGE type notification int(1) NOT NULL default '0'");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions ADD dateline bigint(30) NOT NULL default '0'");
	$db->query("ALTER TABLE ".TABLE_PREFIX."subscriptionkey varchar(32) NOT NULL default ''");

	$db->query("UPDATE ".TABLE_PREFIX."users SET emailnotify='1' WHERE emailnotify='no'");
	$db->query("UPDATE ".TABLE_PREFIX."users SET emailnotify='2' WHERE emailnotify='yes'");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE emailnotify subscriptionmethod int(1) NOT NULL default '0'");

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("9_done");
}
?>