<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.2.9
 */

/** NEEDS TO BE CHANGED PENDING FINAL RELEASE **/
$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"requires_deactivated_plugins" => 1,
);

@set_time_limit(0);

function upgrade11_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `uid` )");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX ( `visible` )");
	
	if($db->field_exists('recipients', "privatemessages"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP recipients;");
	}	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD recipients text NOT NULL AFTER fromid");
	
	if($db->field_exists('deletetime', "privatemessages"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP deletetime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD deletetime bigint(30) NOT NULL default '0' AFTER dateline");
	
	
	if($db->field_exists('maxpmrecipients', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP maxpmrecipients;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxpmrecipients int(4) NOT NULL default '5' AFTER pmquota");


	if($db->field_exists('canwarnusers', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canwarnusers;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canwarnusers char(3) NOT NULL default '' AFTER cancustomtitle");
	
	if($db->field_exists('canrecievewarnings', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canrecievewarnings;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canreceivewarnings char(3) NOT NULL default '' AFTER canwarnusers");
	
	if($db->field_exists('maxwarningsday', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP maxwarningsday;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxwarningsday int(3) NOT NULL default '3' AFTER canreceivewarnings");
	
	$db->update_query("usergroups", array('canreceivewarnings' => 'no'), "cancp='yes' OR gid='1'");
	$db->update_query("usergroups", array('maxwarningsday' => 3, 'canwarnusers' => 'yes'), "cancp='yes' OR issupermod='yes' OR gid='6'"); // Admins, Super Mods and Mods
	if($db->field_exists('newpms', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP newpms;");
	}
	
	if($db->field_exists('keywords', "searchlog"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog DROP keywords;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog ADD keywords text NOT NULL AFTER querycache");
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE canaddpublicevents canaddevents char(3) NOT NULL default '';");
	
	if($db->field_exists('canaddprivateevents', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canaddprivateevents;");
	}
	
	if($db->field_exists('canbypasseventmod', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canbypasseventmod;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canbypasseventmod char(3) NOT NULL default '' AFTER canaddevents;");
	
	if($db->field_exists('canmoderateevents', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canmoderateevents;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canmoderateevents char(3) NOT NULL default '' AFTER canbypasseventmod;");
	$db->update_query("usergroups", array('canbypasseventmod' => 'yes', 'canmoderateevents' => 'yes'), "cancp='yes' OR issupermod='yes'");
	$db->update_query("usergroups", array('canbypasseventmod' => 'no', 'canmoderateevents' => 'no'), "cancp='no' AND issupermod='no'");
	$db->update_query("usergroups", array('canaddevents' => 'no'), "gid='1'");

	$db->drop_table("maillogs");	
	$db->drop_table("mailerrors");
	$db->drop_table("promotions");
	$db->drop_table("promotionlogs");
		
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."maillogs (
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

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."mailerrors(
		eid int unsigned NOT NULL auto_increment,
		subject varchar(200) NOT NULL default '',
		message TEXT NOT NULL,
		toaddress varchar(150) NOT NULL default '',
		fromaddress varchar(150) NOT NULL default '',
		dateline bigint(30) NOT NULL default '0',
		error text NOT NULL,
		smtperror varchar(200) NOT NULL default '',
		smtpcode int(5) NOT NULL default '0',
		PRIMARY KEY(eid)
 	) TYPE=MyISAM;");
	
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."promotions(
		pid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		description text NOT NULL,
		enabled int(1) NOT NULL default '1',
		logging int(1) NOT NULL default '0',
		posts int NOT NULL default '0',
		posttype varchar(120) NOT NULL default '',
		registered int NOT NULL default '0',
		registeredtype varchar(2) NOT NULL default '',
		reputations int NOT NULL default '0',
		reputationtype varchar(120) NOT NULL default '',
		requirements varchar(2) NOT NULL default '',
		originalusergroup smallint NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		usergrouptype varchar(120) NOT NULL default '0',
		PRIMARY KEY(pid)
 	) TYPE=MyISAM;");
	
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."promotionlogs(
		plid int unsigned NOT NULL auto_increment,
		pid int unsigned NOT NULL default '0',
		uid int unsigned NOT NULL default '0',
		oldusergroup smallint unsigned NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		PRIMARY KEY(plid)
 	) TYPE=MyISAM;");

	if($db->field_exists('maxemails', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP maxemails;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxemails int(3) NOT NULL default '5' AFTER cansendemail");
	
	if($db->field_exists('parseorder', "mycode"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."mycode DROP parseorder;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."mycode ADD parseorder smallint unsigned NOT NULL default '0' AFTER active");
	
	if($db->field_exists('mod_edit_posts', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP mod_edit_posts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD mod_edit_posts char(3) NOT NULL default '' AFTER modthreads");

	if($db->field_exists('pmnotice', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP pmnotice;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE pmpopup pmnotice char(3) NOT NULL default ''");
	
	$db->drop_table("tasks");
	$db->drop_table("tasklog");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasks (
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


	$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasklog (
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

	if($db->table_exists("favorites") && !$db->table_exists("threadsubscriptions"))
	{
		$db->query("RENAME TABLE ".TABLE_PREFIX."favorites TO ".TABLE_PREFIX."threadsubscriptions");
	}
	
	if($db->field_exists('fid', "threadsubscriptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP recipients;");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions CHANGE fid sid int unsigned NOT NULL auto_increment");
	}
	
	if($db->field_exists('type', "threadsubscriptions"))
	{
		$db->update_query("threadsubscriptions", array('type' => 0), "type='f'");
		$db->update_query("threadsubscriptions", array('type' => 1), "type='s'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions CHANGE type notification int(1) NOT NULL default '0'");
	}
	
	if($db->field_exists('dateline', "threadsubscriptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions DROP dateline;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions ADD dateline bigint(30) NOT NULL default '0'");
		
	if($db->field_exists('dateline', "threadsubscriptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadssubscriptions DROP dateline;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions ADD subscriptionkey varchar(32) NOT NULL default ''");

	if($db->field_exists('emailnotify', "users"))
	{
		$db->update_query("users", array('emailnotify' => 1), "emailnotify='no'");
		$db->update_query("users", array('emailnotify' => 2), "emailnotify='yes'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE emailnotify subscriptionmethod int(1) NOT NULL default '0'");
	}

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."warninglevels (
		lid int unsigned NOT NULL auto_increment,
		percentage int(3) NOT NULL default '0',
		action text NOT NULL,
		PRIMARY KEY(lid)
	) TYPE=MyISAM;");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."warningtypes (
		tid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		points int unsigned NOT NULL default '0',
		expirationtime bigint(30) NOT NULL default '0',
		PRIMARY KEY(tid)
	) TYPE=MyISAM;");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."warnings (
		wid int unsigned NOT NULL auto_increment,
		uid int unsigned NOT NULL default '0',
		tid int unsigned NOT NULL default '0',
		pid int unsigned NOT NULL default '0',
		title varchar(120) NOT NULL default '',
		points int unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		issuedby int unsigned NOT NULL default '0',
		expires bigint(30) NOT NULL default '0',
		expired int(1) NOT NULL default '0',
		daterevoked bigint(30) NOT NULL default '0',
		revokedby int unsigned NOT NULL default '0',
		revokereason text NOT NULL,
		notes text NOT NULL,
		PRIMARY KEY(wid)
	) TYPE=MyISAM;");

	if($db->field_exists('warningpoints', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP warningpoints;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD warningpoints int(3) NOT NULL default '0' AFTER unreadpms");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD moderateposts int(1) NOT NULL default '0' AFTER warningpoints");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD moderationtime bigint(30) NOT NULL default '0' AFTER moderateposts");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD suspendposting int(1) NOT NULL default '0' AFTER moderationtime");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD suspensiontime bigint(30) NOT NULL default '0' AFTER suspendposting");
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE oldadditionalgroups oldadditionalgroups text NOT NULL default ''");

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("9_dbchanges2");
}

function upgrade11_dbchanges2()
{
	global $db, $output, $mybb;

	$output->print_header("Converting Ban Filters");

	echo "<p>Converting existing banned IP addresses, email addresses and usernames..</p>";
	
	$db->drop_table("banfilters");
	
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."banfilters (
	  fid int unsigned NOT NULL auto_increment,
	  filter varchar(200) NOT NULL default '',
	  type int(1) NOT NULL default '0',
	  lastuse bigint(30) NOT NULL default '0',
	  dateline bigint(30) NOT NULL default '0',
	  PRIMARY KEY  (fid)
	) TYPE=MyISAM;");

	// Now we convert all of the old bans in to the new system!
	$ban_types = array('bannedips','bannedemails','bannedusernames');
	foreach($ban_types as $type)
	{
		$bans = explode(",", $mybb->settings[$type]);
		$bans = array_map("trim", $bans);
		foreach($bans as $ban)
		{
			if($type == "bannedips")
			{
				$ban_type = 1;
			}
			else if($type == "bannedusernames")
			{
				$ban_type = 2;
			}
			else if($type == "bannedemails")
			{
				$ban_type = 3;
			}
			$new_ban = array(
				"filter" => $db->escape_string($banned_ip),
				"type" => $ban_type,
				"dateline" => TIME_NOW
			);
			$db->insert_query("banfilters", $new_ban);
		}
	}

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("9_dbchanges3");
}

function upgrade11_dbchanges3()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->drop_table("spiders");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."spiders (
		sid int unsigned NOT NULL auto_increment,
		name varchar(100) NOT NULL default '',
		theme int unsigned NOT NULL default '0',
		language varchar(20) NOT NULL default '',
		usergroup int unsigned NOT NULL default '0',
		useragent varchar(200) NOT NULL default '',
		lastvisit bigint(30) NOT NULL default '0',
		PRIMARY KEY(sid)
	) TYPE=MyISAM;");

	$db->insert_query("spiders", array('name' => 'GoogleBot', 'useragent' => 'google'));
	$db->insert_query("spiders", array('name' => 'Lycos', 'useragent' => 'lycos'));
	$db->insert_query("spiders", array('name' => 'Ask Jeeves', 'useragent' => 'ask jeeves'));
	$db->insert_query("spiders", array('name' => 'Hot Bot', 'useragent' => 'slurp@inktomi'));
	$db->insert_query("spiders", array('name' => 'What You Seek', 'useragent' => 'whatuseek'));
	$db->insert_query("spiders", array('name' => 'Archive.org', 'useragent' => 'is_archiver'));
	$db->insert_query("spiders", array('name' => 'Altavista', 'useragent' => 'scooter'));
	$db->insert_query("spiders", array('name' => 'Alexa', 'useragent' => 'ia_archiver'));
	$db->insert_query("spiders", array('name' => 'MSN Search', 'useragent' => 'msnbot'));
	$db->insert_query("spiders", array('name' => 'Yahoo!', 'useragent' => 'yahoo slurp'));

	// DST correction changes
	$db->update_query("users", array('dst' => 1), "dst='yes'");
	$db->update_query("users", array('dst' => 0), "dst='no'");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE dst dst INT(1) NOT NULL default '0'");
	if($db->field_exists('dstcorrection', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP dstcorrection;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD dstcorrection INT(1) NOT NULL default '0' AFTER dst");

	$db->update_query("users", array('dstcorrection' => 2));
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions CHANGE permsset permissions text NOT NULL default ''");
	
	$adminoptions = file_get_contents(INSTALL_ROOT.'resources/adminoptions.xml');
	$parser = new XMLParser($adminoptions);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();
	
	// Fetch default permissions list
	$default_permissions = array();
	foreach($tree['adminoptions'][0]['user'] as $users)
	{
		$uid = $users['attributes']['uid'];
		if($uid == 0)
		{
			foreach($users['permissions'][0]['module'] as $module)
			{
				foreach($module['permission'] as $permission)
				{
					$default_permissions[$module['attributes']['name']][$permission['attributes']['name']] = $permission['value'];
				}
			}
			break;
		}
	}
	
	$convert_permissions = array(
		"caneditsettings" => array(
				"module" => "config",
				"permission" => "settings"
			),
		"caneditann" => array(
				"module" => "forum",
				"permission" => "announcements",
			),
		"caneditforums" => array(
				"module" => "forum",
				"permission" => "management",
			),
		"canmodposts" => array(
				"module" => "forum",
				"permission" => "moderation_queue",
			),
		"caneditsmilies" => array(
				"module" => "config",
				"permission" => "smilies",
			),
		"caneditpicons" => array(
				"module" => "config",
				"permission" => "post_icons",
			),
		"caneditthemes" => array(
				"module" => "style",
				"permission" => "themes",
			),
		"canedittemps" => array(
				"module" => "style",
				"permission" => "templates",
			),
		"caneditusers" => array(
				"module" => "user",
				"permission" => "view",
			),
		"caneditpfields" => array(
				"module" => "config",
				"permission" => "profile_fields",
			),
		"caneditmodactions" => array(
				"module" => "config",
				"permission" => "mod_tools",
			),
		"caneditugroups" => array(
				"module" => "user",
				"permission" => "groups",
			),
		"caneditaperms" => array(
				"module" => "user",
				"permission" => "admin_permissions",
			),
		"caneditutitles" => array(
				"module" => "user",
				"permission" => "titles",
			),
		"caneditattach" => array(
				"module" => "forum",
				"permission" => "attachments",
			),
		"canedithelp" => array(
				"module" => "config",
				"permission" => "help_documents",
			),
		"caneditlangs" => array(
				"module" => "config",
				"permission" => "languages",
			),
		"canrunmaint" => array(
				"module" => "tools",
				"permission" => "recount_rebuild",
			),
		"canrundbtools" => array(
				"module" => "tools",
				"permission" => "backupdb",
			),
	);
	
	$new_permissions = $default_permissions;
	
	$query = $db->simple_select("adminoptions");
	while($adminoption = $db->fetch_array($query))
	{
		foreach($adminoption as $field => $value)
		{
			if(strtolower(substr($field, 0, 3)) != "can")
			{
				continue;
			}
			
			if(array_key_exists($field, $convert_permissions))
			{
				if($value == "yes")
				{
					$value = 1;
				}
				else
				{
					$value = $default_permissions[$convert_permissions[$field]['module']][$convert_permissions[$field]['permission']];
				}
				$new_permissions[$convert_permissions[$field]['module']][$convert_permissions[$field]['permission']] = $value;
			}
		}
		
		$db->update_query("adminoptions", array('permissions' => serialize($new_permissions)), "uid = '{$adminoption['uid']}'");
		
		$new_permissions = array();	
	}
	
	foreach($convert_permissions as $field => $value)
	{
		if($db->field_exists($field, "adminoptions"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP {$field}");
		}
	}
	
	require_once MYBB_ROOT."inc/functions_rebuild.php";
	rebuild_stats();

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("9_dbchanges4");
}

function upgrade11_dbchanges4()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	if($db->field_exists('statustime', "privatemessages"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP statustime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD statustime bigint(30) NOT NULL default '0' AFTER status");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."calendars (
	  cid int unsigned NOT NULL auto_increment,
	  name varchar(100) NOT NULL default '',
	  disporder int unsigned NOT NULL default '0',
	  startofweek int(1) NOT NULL default '0',
	  showbirthdays int(1) NOT NULL default '0',
	  eventlimit int(3) NOT NULL default '0',
	  moderation int(1) NOT NULL default '0',
	  allowhtml char(3) NOT NULL default '',
	  allowmycode char(3) NOT NULL default '',
	  allowimgcode char(3) NOT NULL default '',
	  allowsmilies char(3) NOT NULL default '',
	  PRIMARY KEY(cid)
	) TYPE=MyISAM;");

	$calendar_array = array(
		'name' => 'Default Calendar',
		'disporder' => 1,
		'startofweek' => 0,
		'showbirthdays' => 1,
		'eventlimit' => 4,
		'moderation' => 0,
		'allowhtml' => 'no',
		'allowmycode' => 'yes',
		'allowimgcode' => 'yes',
		'allowsmilies' => 'yes'
	);
	$db->insert_query("calendars", $calendar_array);

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."calendarpermissions (
	  cid int unsigned NOT NULL default '0',
	  gid int unsigned NOT NULL default '0',
	  canviewcalendar char(3) NOT NULL default '',
	  canaddevents char(3) NOT NULL default '',
	  canbypasseventmod char(3) NOT NULL default '',
	  canmoderateevents char(3) NOT NULL default ''
	) TYPE=MyISAM;");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."forumsread (
	  fid int unsigned NOT NULL default '0',
	  uid int unsigned NOT NULL default '0',
	  dateline int(10) NOT NULL default '0',
	  KEY dateline (dateline),
	  UNIQUE KEY fid (fid,uid)
	) TYPE=MyISAM;");

	$db->query("CREATE TABLE ".TABLE_PREFIX."stats (
		dateline bigint(30) NOT NULL default '0',
		numusers int unsigned NOT NULL default '0',
		numthreads int unsigned NOT NULL default '0',
		numposts int unsigned NOT NULL default '0',
		PRIMARY KEY(dateline)
	) TYPE=MyISAM;");


	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("9_done");
}

function upgrade11_dbchanges5()
{
	global $db, $output;

	$output->print_header("Event Conversion");

	if(!$_POST['eventspage'])
	{
		$epp = 50;
	}
	else
	{
		$epp = $_POST['eventspage'];
	}

	if($_POST['eventstart'])
	{
		$startat = $_POST['eventstart'];
		$upper = $startat+$epp;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $epp;
		$lower = 1;
	}

	$query = $db->simple_select("SELECT COUNT(eid) AS eventcount FROM ".TABLE_PREFIX."events");
	$cnt = $db->fetch_array($query);

	$contents .= "<p>Converting events {$lower} to {$upper} ({$cnt['attachcount']} Total)</p>";
	echo "<p>Converting events {$lower} to {$upper} ({$cnt['attachcount']} Total)</p>";
	
	// Just started - add fields
	if(!$db->field_exists("donecon", "events"))
	{
		// Add temporary column
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD donecon smallint(1) NOT NULL;");

		// Got structural changes?		
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD cid int unsigned NOT NULL default '0' AFTER eid");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE author uid int unsigned NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE subject name varchar(120) NOT NULL default ''");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD visible int(1) NOT NULL default '0' AFTER description");
		$db->update_query("events", array('private' => 1), "private='yes'");
		$db->update_query("events", array('private' => 0), "private='no'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE private int(1) NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD dateline int(10) unsigned NOT NULL default '0' AFTER private");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD starttime int(10) unsigned NOT NULL default '0' AFTER dateline");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD endtime int(10) unsigned NOT NULL default '0' AFTER starttime");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD timezone int(3) NOT NULL default '0' AFTER endtime");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD ignoretimezone int(1) NOT NULL default '0' AFTER timezone");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD usingtime int(1) NOT NULL default '0' AFTER dst");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD repeats text NOT NULL AFTER usingtime");
	}

	$query = $db->simple_select("events", "*", "donecon!=1", array("order_by" => "eid", "limit" => $epp));
	while($event = $db->fetch_array($query))
	{
		$e_date = explode("-", $event['date']);
		$starttime = gmmktime(0, 0, 0, $e_date[1], $e_date[0], $e_date[1]);
		$updated_event = array(
			"cid" => 1,
			"visible" => 1,
			"starttime" => $starttime,
			"dateline" => $starttime
		);
	}
	
	echo "<p>Done.</p>";
	$query = $db->simple_select("events", "COUNT(eid) AS remaining", "donecon!=1");
	$remaining = $db->fetch_field($query, "remaining");	
	if($remaining)
	{
		$nextact = "9_dbchanges5";
		$startat = $startat+$epp;
		$contents .= "<p><input type=\"hidden\" name=\"eventspage\" value=\"$epp\" /><input type=\"hidden\" name=\"eventstart\" value=\"$startat\" />Done. Click Next to move on to the next set of events.</p>";
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP donecon");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP date");
		$nextact = "9_done";
		$contents .= "<p>Done</p><p>All events have been converted to the new calendar system. Click next to continue.</p>";
	}
	$output->print_contents($contents);
	$output->print_footer($nextact);

}
?>