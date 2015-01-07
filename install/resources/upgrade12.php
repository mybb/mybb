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
 * Upgrade Script: 1.2.12, 1.2.13 or 1.2.14
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0,
	"requires_deactivated_plugins" => 1,
);

@set_time_limit(0);

// We need to globalize $db here because when this script is called
// during load_module $db is not globalized in the function
global $db;

// FIRST STEP IS FOR INTEGER CONVERSION PROJECT

function upgrade12_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Integer Conversion Project");

	$perpage = 10000;

	$to_int = array(
		"announcements" => array("aid", "allowhtml", "allowmycode", "allowsmilies"),
		"forumpermissions" => array("pid", "canview","canviewthreads","candlattachments","canpostthreads","canpostreplys","canpostattachments","canratethreads","caneditposts","candeleteposts","candeletethreads","caneditattachments","canpostpolls","canvotepolls","cansearch"),
		"forums" => array("fid", "active","open","allowhtml","allowmycode","allowsmilies","allowimgcode","allowpicons","allowtratings","usepostcounts","showinjump","modposts","modthreads","modattachments","overridestyle"),
		"groupleaders" => array("lid", "canmanagemembers","canmanagerequests"),
		"helpdocs" => array("hid", "usetranslation","enabled"),
		"helpsections" => array("sid", "usetranslation","enabled"),
		"moderators" => array("mid", "caneditposts","candeleteposts","canviewips","canopenclosethreads","canmanagethreads","canmovetononmodforum"),
		"mycode" => array("cid", "active"),
		"polls" => array("pid", "closed","multiple","public"),
		"posts" => array("pid", "includesig","smilieoff"),
		"privatemessages" => array("pmid", "includesig","smilieoff"),
		"profilefields" => array("fid", "required","editable","hidden"),
		"smilies" => array("sid", "showclickable"),
		"usergroups" => array("gid","isbannedgroup","canview","canviewthreads","canviewprofiles","candlattachments","canpostthreads","canpostreplys","canpostattachments","canratethreads","caneditposts","candeleteposts","candeletethreads","caneditattachments","canpostpolls","canvotepolls","canusepms","cansendpms","cantrackpms","candenypmreceipts","cansendemail","canviewmemberlist","canviewcalendar","canviewonline","canviewwolinvis","canviewonlineips","cancp","issupermod","cansearch","canusercp","canuploadavatars","canratemembers","canchangename","showforumteam","usereputationsystem","cangivereputations","candisplaygroup","cancustomtitle"),
		"users" => array("uid","allownotices","hideemail","invisible","receivepms","pmpopup","pmnotify","remember","showsigs","showavatars","showquickreply","showredirect","away"),
		"threads" => array("tid", "closed")
	);

	if(!$db->field_exists('pmpopup', "users"))
	{
		$pmpopup_key = array_search('pmpopup', $to_int['users']);
		if($pmpopup_key)
		{
			unset($to_int['users'][$pmpopup_key]);
		}
	}

	// Continuing?
	if($mybb->input['last_table'])
	{
		$current_table = $mybb->input['last_table'];
	}
	else
	{
		$current_table = array_keys($to_int);
		$current_table = $current_table[0];
		echo "<p>MyBB 1.4 represents a huge leap forward for the MyBB project.</p><p>Due to this, lots of information in the database needs to be converted to a new format.</p>";
	}

	echo "<p>MyBB is now currently converting a section of data to the new format, this may take a while.</p>";

	$remaining = $perpage;

	$final_table = array_keys($to_int);
	$final_table = $final_table[count($final_table)-1];

	$next_act = "12_dbchanges";

	$start = $mybb->get_input('start', MyBB::INPUT_INT);
	$count = $mybb->input['count'];

	foreach($to_int as $table => $columns)
	{
		if($table == $current_table)
		{
			$form_fields['last_table'] = $current_table;
			if($remaining <= 0)
			{
				break;
			}
			$columns_sql = implode(",", $columns);
			$primary_key = $columns[0];
			if(!$mybb->input['count'])
			{
				$query = $db->simple_select($table, "COUNT({$primary_key}) AS count");
				$count = $form_fields['count'] = $db->fetch_field($query, "count");
			}
			if($start <= $count)
			{
				$end = $start+$perpage;
				if($end > $count) $end = $count;
				echo "<p>{$table}: Converting {$start} to {$end} of {$count}</p>";
				flush();
				$form_fields['start'] = $perpage+$start;

				$query = $db->simple_select($table, $columns_sql, "", array('order_by' => $primary_key, 'limit_start' => $start, 'limit' => $remaining));
				while($row = $db->fetch_array($query))
				{
					$updated_row = array();
					foreach($columns as $column)
					{
						if($column == $primary_key || is_int($row[$column])) continue;
						if($row[$column] == "yes" || $row[$column] == "on")
						{
							$updated_row[$column] = 1;
						}
						else if($row[$column] == "off" || $row[$column] == "no" || $row[$column] == 'new' || $row[$column] == "")
						{
							$updated_row[$column] = 0;
						}
					}
					if(count($updated_row) > 0)
					{
						$db->update_query($table, $updated_row, "{$primary_key}={$row[$primary_key]}");
					}
					--$remaining;
				}
			}
			else
			{
				echo "<p>{$table}: Converting column type</p>";
				flush();
				$change_column = array();
				foreach($columns as $column)
				{
					// Closed does not get converted to an int
					if($column == $primary_key || ($table == "threads" && $column == "closed"))
					{
						continue;
					}
					$change_column[] = "MODIFY {$column} int(1) NOT NULL default '0'";
				}
				$db->write_query("ALTER TABLE ".TABLE_PREFIX."{$table} ".implode(", ", $change_column));

				if($table == $final_table)
				{
					// Finished, after all this!
					$next_act = "12_dbchanges1";
				}
				else
				{
					$tbl_keys = array_keys($to_int);
					$current = array_search($current_table, $tbl_keys);
					$form_fields['last_table'] = $current_table = $tbl_keys[$current+1];
					$form_fields['start'] = $start = 0;
					$form_fields['count'] = $count = $mybb->input['count'] = 0;
				}
			}
		}
	}

	// Still converting
	if($next_act == "12_dbchanges")
	{
		echo "<p>Done</p>";
		echo "<p>Click next to continue with the integer conversion process.</p>";
		foreach($form_fields as $key => $val)
		{
			echo "<input type=\"hidden\" name=\"{$key}\" value=\"{$val}\" />";
		}
		global $footer_extra;
		$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";
		$output->print_footer($next_act);
	}
	else
	{
		// Convert settings table
		$query = $db->write_query("UPDATE ".TABLE_PREFIX."settings SET value=1 WHERE value='yes' OR value='on'");
		$query = $db->write_query("UPDATE ".TABLE_PREFIX."settings SET value=0 WHERE value='no' OR value='off'");
		echo "<p>Done</p>";
		echo "<p><strong>The integrer conversion process is now complete.</strong></p>";
		echo "<p>Click next to continue with the upgrade process.</p>";
		global $footer_extra;
		$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

		$output->print_footer($next_act);
	}
}

function upgrade12_dbchanges1()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	echo "<p>Adding index to private messages table ... ";
	flush();

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `uid` )");

	echo "done.</p>";

	$contents = "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges_post1");
}

function upgrade12_dbchanges_post1()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	echo "<p>Adding index to posts table ... ";
	flush();

	// This will take a LONG time on huge post databases, so we only run it isolted from most of the other queries
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX ( `visible` )");

	echo "done.</p>";
	flush();

	$contents = "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges_post2");
}

function upgrade12_dbchanges_post2()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	if($db->field_exists('longipaddress', "posts"))
	{
		echo "<p>Dropping longipaddress column in posts table ... ";
		flush();

		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts DROP longipaddress;");

		echo "done.</p>";
		flush();
	}

	echo "<p>Adding longipaddress column to posts table ... ";
	flush();

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD longipaddress int(11) NOT NULL default '0' AFTER ipaddress");

	echo "done.</p>";
	flush();

	$contents = "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges_user");
}

function upgrade12_dbchanges_user()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	echo "<p>Adding index to users table ... ";
	flush();

	// This will take a LONG time on huge user databases, so we only run it isolted from most of the other queries
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX ( `lastvisit` )");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX ( `regdate` )");

	echo "done.</p>";
	flush();

	$contents = "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges2");
}

function upgrade12_dbchanges2()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

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
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canwarnusers int(1) NOT NULL default '0' AFTER cancustomtitle");

	if($db->field_exists('lastip', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP lastip;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD lastip varchar(50) NOT NULL default '' AFTER regip");



	if($db->field_exists('coppauser', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP coppauser;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD coppauser int(1) NOT NULL default '0'");

	if($db->field_exists('classicpostbit', 'users'))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP classicpostbit;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD classicpostbit int(1) NOT NULL  default '0'");

	if($db->field_exists('canreceivewarnings', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canreceivewarnings;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canreceivewarnings int(1) NOT NULL default '0' AFTER canwarnusers");

	if($db->field_exists('maxwarningsday', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP maxwarningsday;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxwarningsday int(3) NOT NULL default '3' AFTER canreceivewarnings");

	$db->update_query("usergroups", array('canreceivewarnings' => 1), "cancp != 1");
	$db->update_query("usergroups", array('maxwarningsday' => 3, 'canwarnusers' => 1), "cancp=1 OR issupermod=1 OR gid=6"); // Admins, Super Mods and Mods

	if($db->field_exists('canmodcp', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canmodcp;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canmodcp int(1) NOT NULL default '0' AFTER maxwarningsday");
	$db->update_query("usergroups", array('canmodcp' => 1), "cancp=1 OR issupermod=1 OR gid='6'"); // Admins, Super Mods and Mods

	if($db->field_exists('newpms', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP newpms;");
	}

	if($db->field_exists('keywords', "searchlog"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog DROP keywords;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog ADD keywords text NOT NULL AFTER querycache");

	if($db->field_exists('canaddpublicevents', "usergroups") && !$db->field_exists('canaddevents', "usergroups"))
	{
		$db->update_query("usergroups", array('canaddpublicevents' => 0), "canaddpublicevents='no'");
		$db->update_query("usergroups", array('canaddpublicevents' => 1), "canaddpublicevents='yes'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE canaddpublicevents canaddevents int(1) NOT NULL default '0';");
	}

	if($db->field_exists('canaddprivateevents', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canaddprivateevents;");
	}

	if($db->field_exists('canbypasseventmod', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canbypasseventmod;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canbypasseventmod int(1) NOT NULL default '0' AFTER canaddevents;");

	if($db->field_exists('canmoderateevents', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canmoderateevents;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canmoderateevents int(1) NOT NULL default '0' AFTER canbypasseventmod;");
	$db->update_query("usergroups", array('canbypasseventmod' => 1, 'canmoderateevents' => 1), "cancp=1 OR issupermod=1");
	$db->update_query("usergroups", array('canbypasseventmod' => 0, 'canmoderateevents' => 0), "cancp=0 AND issupermod=0");
	$db->update_query("usergroups", array('canaddevents' => 0), "gid='1'");

	$db->drop_table("maillogs");
	$db->drop_table("mailerrors");
	$db->drop_table("promotions");
	$db->drop_table("promotionlogs");
	$db->drop_table("massemails");

	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."massemails (
		mid int unsigned NOT NULL auto_increment,
		uid int unsigned NOT NULL default '0',
		subject varchar(200) NOT NULL default '',
		message text NOT NULL,
		htmlmessage text NOT NULL,
		type tinyint(1) NOT NULL default '0',
		format tinyint(1) NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		senddate bigint(30) NOT NULL default '0',
		status tinyint(1) NOT NULL default '0',
		sentcount int unsigned NOT NULL default '0',
		totalcount int unsigned NOT NULL default '0',
		conditions text NOT NULL,
		perpage smallint(4) NOT NULL default '50',
		PRIMARY KEY(mid)
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."maillogs (
		mid int unsigned NOT NULL auto_increment,
		subject varchar(200) not null default '',
		message TEXT NOT NULL,
		dateline bigint(30) NOT NULL default '0',
		fromuid int unsigned NOT NULL default '0',
		fromemail varchar(200) not null default '',
		touid bigint(30) NOT NULL default '0',
		toemail varchar(200) NOT NULL default '',
		tid int unsigned NOT NULL default '0',
		ipaddress varchar(20) NOT NULL default '',
		PRIMARY KEY(mid)
	) ENGINE=MyISAM{$collation};");

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
 	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."promotions (
		pid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		description text NOT NULL,
		enabled tinyint(1) NOT NULL default '1',
		logging tinyint(1) NOT NULL default '0',
		posts int NOT NULL default '0',
		posttype char(2) NOT NULL default '',
		registered int NOT NULL default '0',
		registeredtype varchar(20) NOT NULL default '',
		reputations int NOT NULL default '0',
		reputationtype char(2) NOT NULL default '',
		requirements varchar(200) NOT NULL default '',
		originalusergroup varchar(120) NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		usergrouptype varchar(120) NOT NULL default '0',
		PRIMARY KEY (pid)
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."promotionlogs(
		plid int unsigned NOT NULL auto_increment,
		pid int unsigned NOT NULL default '0',
		uid int unsigned NOT NULL default '0',
		oldusergroup varchar(200) NOT NULL default '0',
		newusergroup smallint unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		type varchar(9) NOT NULL default 'primary',
		PRIMARY KEY(plid)
 	) ENGINE=MyISAM{$collation};");

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
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD mod_edit_posts int(1) NOT NULL default '0' AFTER modthreads");

	if($db->field_exists('pmpopup', "users") && !$db->field_exists('pmnotice', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE pmpopup pmnotice int(1) NOT NULL default '0'");
	}

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
	) ENGINE=MyISAM{$collation};");


	$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasklog (
		lid int unsigned NOT NULL auto_increment,
		tid int unsigned NOT NULL default '0',
		dateline bigint(30) NOT NULL default '0',
		data text NOT NULL,
		PRIMARY KEY(lid)
	) ENGINE=MyISAM{$collation};");


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
		$db->write_query("RENAME TABLE ".TABLE_PREFIX."favorites TO ".TABLE_PREFIX."threadsubscriptions");
	}

	if($db->field_exists('fid', "threadsubscriptions"))
	{
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

	if($db->field_exists('subscriptionkey', "threadsubscriptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions DROP subscriptionkey;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threadsubscriptions ADD subscriptionkey varchar(32) NOT NULL default ''");

	if($db->field_exists('emailnotify', "users"))
	{
		$db->update_query("users", array('emailnotify' => 0), "emailnotify='no' OR emailnotify='0'");
		$db->update_query("users", array('emailnotify' => 2), "emailnotify='yes' OR emailnotify='1'");
		$db->update_query("users", array('emailnotify' => 0), "emailnotify != 1 AND emailnotify != 2");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE emailnotify subscriptionmethod int(1) NOT NULL default '0'");
	}

	$db->drop_table("warninglevels");
	$db->drop_table("warningtypes");
	$db->drop_table("warnings");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."warninglevels (
		lid int unsigned NOT NULL auto_increment,
		percentage int(3) NOT NULL default '0',
		action text NOT NULL,
		PRIMARY KEY(lid)
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."warningtypes (
		tid int unsigned NOT NULL auto_increment,
		title varchar(120) NOT NULL default '',
		points int unsigned NOT NULL default '0',
		expirationtime bigint(30) NOT NULL default '0',
		PRIMARY KEY(tid)
	) ENGINE=MyISAM{$collation};");

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
	) ENGINE=MyISAM{$collation};");

	if($db->field_exists('warningpoints', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP warningpoints;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD warningpoints int(3) NOT NULL default '0' AFTER unreadpms");

	if($db->field_exists('moderateposts', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP moderateposts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD moderateposts int(1) NOT NULL default '0' AFTER warningpoints");

	if($db->field_exists('moderationtime', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP moderationtime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD moderationtime bigint(30) NOT NULL default '0' AFTER moderateposts");

	if($db->field_exists('suspendposting', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP suspendposting;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD suspendposting int(1) NOT NULL default '0' AFTER moderationtime");

	if($db->field_exists('suspensiontime', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP suspensiontime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD suspensiontime bigint(30) NOT NULL default '0' AFTER suspendposting");

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE oldadditionalgroups oldadditionalgroups TEXT NOT NULL");

	if($db->field_exists('birthdayprivacy', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP birthdayprivacy;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD birthdayprivacy varchar(4) NOT NULL default 'all' AFTER birthday");

	if($db->field_exists('birthdayprivacy', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP birthdayprivacy;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD birthdayprivacy varchar(4) NOT NULL default 'all' AFTER birthday");

	if($db->field_exists('longregip', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP longregip;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD longregip int(11) NOT NULL default '0' AFTER lastip");

	if($db->field_exists('longlastip', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP longlastip;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD longlastip int(11) NOT NULL default '0' AFTER lastip");

	// Unused column
	if($db->field_exists('titles', "searchlog"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."searchlog DROP titles;");
	}

	$db->drop_table("adminlog");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."adminlog (
	  uid int unsigned NOT NULL default '0',
	  ipaddress varchar(50) NOT NULL default '',
	  dateline bigint(30) NOT NULL default '0',
	  module varchar(50) NOT NULL default '',
	  action varchar(50) NOT NULL default '',
	  data text NOT NULL,
	  KEY module (module, action)
	) ENGINE=MyISAM{$collation};");

	if($db->field_exists('data', "adminsessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions DROP data;");
	}

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD data TEXT NOT NULL AFTER lastactive;");

	if($db->field_exists('isdefault', "settings"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."settings DROP isdefault;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settings ADD isdefault int(1) NOT NULL default '0' AFTER gid;");

	$setting_group_cache = array();
	$query = $db->simple_select("settinggroups", "gid, isdefault");
	while($settinggroup = $db->fetch_array($query))
	{
		$setting_group_cache[$settinggroup['gid']] = $settinggroup['isdefault'];
	}

	$query = $db->simple_select("settings", "gid, sid");
	while($setting = $db->fetch_array($query))
	{
		if($setting_group_cache[$setting['gid']] == 1)
		{
			$db->update_query("settings", array('isdefault' => 1), "sid = '{$setting['sid']}'", 1);
		}
	}

	$db->update_query("settings", array('value' => 'classic'), "name='postlayout' AND value != 'horizontal'");

	$db->update_query("settings", array('optionscode' => $db->escape_string('php
<select name=\"upsetting[{$setting[\'name\']}]\"><option value=\"standard\">".($lang->setting_searchtype_standard?$lang->setting_searchtype_standard:"Standard")."</option>".($db->supports_fulltext("threads") && $db->supports_fulltext_boolean("posts")?"<option value=\"fulltext\"".($setting[\'value\']=="fulltext"?" selected=\"selected\"":"").">".($lang->setting_searchtype_fulltext?$lang->setting_searchtype_fulltext:"Full Text")."</option>":"")."</select>')), "name='searchtype'", 1);

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges3");
}

function upgrade12_dbchanges3()
{
	global $db, $output, $mybb;

	$output->print_header("Converting Ban Filters");

	echo "<p>Converting existing banned IP addresses, email addresses and usernames..</p>";
	flush();

	$db->drop_table("banfilters");

	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."banfilters (
	  fid int unsigned NOT NULL auto_increment,
	  filter varchar(200) NOT NULL default '',
	  type int(1) NOT NULL default '0',
	  lastuse bigint(30) NOT NULL default '0',
	  dateline bigint(30) NOT NULL default '0',
	  PRIMARY KEY (fid)
	) ENGINE=MyISAM{$collation};");

	// Now we convert all of the old bans in to the new system!
	$ban_types = array('bannedips', 'bannedemails', 'bannedusernames');
	foreach($ban_types as $type)
	{
		// Some people put spaces or breaks (\r\n) instead, so we should take that into account.
		$mybb->settings[$type] = str_replace(array("\n", "\r\n", "\r"), ",", $mybb->settings[$type]);

		// Usernames can have spaces so don't replace those with commas.
		if($type != 'bannedusernames')
		{
			$mybb->settings[$type] = str_replace(" ", ",", $mybb->settings[$type]);
		}

		$bans = explode(",", $mybb->settings[$type]);
		$bans = array_unique($bans);
		$bans = array_map("trim", $bans);
		foreach($bans as $ban)
		{
			if(!$ban)
			{
				continue;
			}

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
				"filter" => $db->escape_string($ban),
				"type" => $ban_type,
				"dateline" => TIME_NOW
			);
			$db->insert_query("banfilters", $new_ban);
		}
	}

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges4");
}

function upgrade12_dbchanges4()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	$db->drop_table("spiders");
	$db->drop_table("stats");

	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."spiders (
		sid int unsigned NOT NULL auto_increment,
		name varchar(100) NOT NULL default '',
		theme int unsigned NOT NULL default '0',
		language varchar(20) NOT NULL default '',
		usergroup int unsigned NOT NULL default '0',
		useragent varchar(200) NOT NULL default '',
		lastvisit bigint(30) NOT NULL default '0',
		PRIMARY KEY(sid)
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."stats (
		dateline bigint(30) NOT NULL default '0',
		numusers int unsigned NOT NULL default '0',
		numthreads int unsigned NOT NULL default '0',
		numposts int unsigned NOT NULL default '0',
		PRIMARY KEY(dateline)
	) ENGINE=MyISAM{$collation};");

	$db->insert_query("spiders", array('name' => 'GoogleBot', 'useragent' => 'google'));
	$db->insert_query("spiders", array('name' => 'Lycos', 'useragent' => 'lycos'));
	$db->insert_query("spiders", array('name' => 'Ask Jeeves', 'useragent' => 'ask jeeves'));
	$db->insert_query("spiders", array('name' => 'Hot Bot', 'useragent' => 'slurp@inktomi'));
	$db->insert_query("spiders", array('name' => 'What You Seek', 'useragent' => 'whatuseek'));
	$db->insert_query("spiders", array('name' => 'Archive.org', 'useragent' => 'is_archiver'));
	$db->insert_query("spiders", array('name' => 'Altavista', 'useragent' => 'scooter'));
	$db->insert_query("spiders", array('name' => 'Alexa', 'useragent' => 'ia_archiver'));
	$db->insert_query("spiders", array('name' => 'MSN Search', 'useragent' => 'msnbot'));
	$db->insert_query("spiders", array('name' => 'Yahoo!', 'useragent' => 'yahoo! slurp'));

	// DST correction changes
	$db->update_query("users", array('dst' => 1), "dst=1");
	$db->update_query("users", array('dst' => 0), "dst=0");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE dst dst INT(1) NOT NULL default '0'");
	if($db->field_exists('dstcorrection', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP dstcorrection;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD dstcorrection INT(1) NOT NULL default '0' AFTER dst");

	$db->update_query("users", array('dstcorrection' => 2));

	$db->update_query("adminoptions", array('cpstyle' => ''));

	if($db->field_exists('permsset', "adminoptions") && !$db->field_exists('permissions', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions CHANGE permsset permissions TEXT NOT NULL ");
	}

	$adminoptions = file_get_contents(INSTALL_ROOT.'resources/adminoptions.xml');
	$parser = new XMLParser($adminoptions);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	// Fetch default permissions list
	$default_permissions = array();
	foreach($tree['adminoptions'][0]['user'] as $users)
	{
		$uid = $users['attributes']['uid'];
		if($uid == -4)
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
				// Note: old adminoptions table is still yes/no - do not change me
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

		$db->update_query("adminoptions", array('permissions' => my_serialize($new_permissions)), "uid = '{$adminoption['uid']}'");

		$new_permissions = $default_permissions;
	}

	foreach($convert_permissions as $field => $value)
	{
		if($db->field_exists($field, "adminoptions"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP {$field}");
		}
	}

	// Set default views
	if($db->field_exists('defaultviews', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP defaultviews");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD defaultviews TEXT NOT NULL");
	$db->update_query("adminoptions", array('defaultviews' => my_serialize(array('user' => 1))));

	require_once MYBB_ROOT."inc/functions_rebuild.php";
	rebuild_stats();

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges5");
}

function upgrade12_dbchanges5()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	$db->drop_table("templategroups");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."templategroups (
		gid int unsigned NOT NULL auto_increment,
		prefix varchar(50) NOT NULL default '',
		title varchar(100) NOT NULL default '',
		PRIMARY KEY (gid)
		) ENGINE=MyISAM{$collation};");

	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('1','calendar','<lang:group_calendar>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('2','editpost','<lang:group_editpost>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('3','forumbit','<lang:group_forumbit>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('4','forumjump','<lang:group_forumjump>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('5','forumdisplay','<lang:group_forumdisplay>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('6','index','<lang:group_index>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('7','error','<lang:group_error>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('8','memberlist','<lang:group_memberlist>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('9','multipage','<lang:group_multipage>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('10','private','<lang:group_private>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('11','portal','<lang:group_portal>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('12','postbit','<lang:group_postbit>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('13','redirect','<lang:group_redirect>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('14','showthread','<lang:group_showthread>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('15','usercp','<lang:group_usercp>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('16','online','<lang:group_online>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('17','moderation','<lang:group_moderation>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('18','nav','<lang:group_nav>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('19','search','<lang:group_search>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('20','showteam','<lang:group_showteam>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('21','reputation','<lang:group_reputation>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('22','newthread','<lang:group_newthread>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('23','newreply','<lang:group_newreply>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('24','member','<lang:group_member>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('25','warnings','<lang:group_warning>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('26','global','<lang:group_global>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('27','header','<lang:group_header>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('28','managegroup','<lang:group_managegroup>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('29','misc','<lang:group_misc>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('30','modcp','<lang:group_modcp>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('31','php','<lang:group_php>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('32','polls','<lang:group_polls>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('33','post','<lang:group_post>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('34','printthread','<lang:group_printthread>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('35','report','<lang:group_report>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('36','smilieinsert','<lang:group_smilieinsert>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('37','stats','<lang:group_stats>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('38','xmlhttp','<lang:group_xmlhttp>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('39','footer','<lang:group_footer>');");

	$query = $db->query("SHOW INDEX FROM ".TABLE_PREFIX."users");
	while($ukey = $db->fetch_array($query))
	{
		if($ukey['Key_name'] == "username")
		{
			$index = $ukey;
			break;
		}
	}
	if($index)
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY username");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD UNIQUE KEY username (username)");

	if($db->field_exists('statustime', "privatemessages"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages DROP statustime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD statustime bigint(30) NOT NULL default '0' AFTER status");

	$collation = $db->build_create_table_collation();

	$db->drop_table("calendars");
	$db->drop_table("calendarpermissions");
	$db->drop_table("forumsread");
	$db->drop_table("adminviews");
	$db->drop_table("threadviews");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."threadviews (
		tid int unsigned NOT NULL default '0'
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."calendars (
	  cid int unsigned NOT NULL auto_increment,
	  name varchar(100) NOT NULL default '',
	  disporder int unsigned NOT NULL default '0',
	  startofweek int(1) NOT NULL default '0',
	  showbirthdays int(1) NOT NULL default '0',
	  eventlimit int(3) NOT NULL default '0',
	  moderation int(1) NOT NULL default '0',
	  allowhtml int(1) NOT NULL default '0',
	  allowmycode int(1) NOT NULL default '0',
	  allowimgcode int(1) NOT NULL default '0',
	  allowsmilies int(1) NOT NULL default '0',
	  PRIMARY KEY(cid)
	) ENGINE=MyISAM{$collation};");

	$calendar_array = array(
		'name' => 'Default Calendar',
		'disporder' => 1,
		'startofweek' => 0,
		'showbirthdays' => 1,
		'eventlimit' => 4,
		'moderation' => 0,
		'allowhtml' => 0,
		'allowmycode' => 1,
		'allowimgcode' => 1,
		'allowsmilies' => 1
	);
	$db->insert_query("calendars", $calendar_array);

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."calendarpermissions (
	  cid int unsigned NOT NULL default '0',
	  gid int unsigned NOT NULL default '0',
	  canviewcalendar int(1) NOT NULL default '0',
	  canaddevents int(1) NOT NULL default '0',
	  canbypasseventmod int(1) NOT NULL default '0',
	  canmoderateevents int(1) NOT NULL default '0'
	) ENGINE=MyISAM{$collation};");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."forumsread (
	  fid int unsigned NOT NULL default '0',
	  uid int unsigned NOT NULL default '0',
	  dateline int(10) NOT NULL default '0',
	  KEY dateline (dateline),
	  UNIQUE KEY fid (fid,uid)
	) ENGINE=MyISAM{$collation};");

	if($db->field_exists('dateuploaded', "attachments"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments DROP dateuploaded;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachments ADD dateuploaded bigint(30) NOT NULL default '0' AFTER downloads");

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."adminviews (
		vid int unsigned NOT NULL auto_increment,
		uid int unsigned NOT NULL default '0',
		title varchar(100) NOT NULL default '',
		type varchar(6) NOT NULL default '',
		visibility int(1) NOT NULL default '0',
		`fields` text NOT NULL,
		conditions text NOT NULL,
		sortby varchar(20) NOT NULL default '',
		sortorder varchar(4) NOT NULL default '',
		perpage int(4) NOT NULL default '0',
		view_type varchar(6) NOT NULL default '',
		PRIMARY KEY(vid)
	) ENGINE=MyISAM{$collation};");

	$views = file_get_contents(INSTALL_ROOT.'resources/adminviews.xml');
	$parser = new XMLParser($views);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	// Insert admin views
	foreach($tree['adminviews'][0]['view'] as $view)
	{
		$fields = array();
		foreach($view['fields'][0]['field'] as $field)
		{
			$fields[] = $field['attributes']['name'];
		}
		$conditions = array();
		if($view['conditions'][0]['condition'])
		{
			foreach($view['conditions'][0]['condition'] as $condition)
			{
				if(!$condition['value']) continue;
				if($condition['attributes']['is_serialized'] == 1)
				{
					$condition['value'] = my_unserialize($condition['value']);
				}
				$conditions[$condition['attributes']['name']] = $condition['value'];
			}
		}
		$new_view = array(
			"uid" => 0,
			"type" => $db->escape_string($view['attributes']['type']),
			"visibility" => (int)$view['attributes']['visibility'],
			"title" => $db->escape_string($view['title'][0]['value']),
			"fields" => $db->escape_string(my_serialize($fields)),
			"conditions" => $db->escape_string(my_serialize($conditions)),
			"sortby" => $db->escape_string($view['sortby'][0]['value']),
			"sortorder" => $db->escape_string($view['sortorder'][0]['value']),
			"perpage" => (int)$view['perpage'][0]['value'],
			"view_type" => $db->escape_string($view['view_type'][0]['value'])
		);
		$db->insert_query("adminviews", $new_view);
		$view_count++;
	}

	$avatardimensions = str_replace('x', '|', my_strtolower($mybb->settings['postmaxavatarsize']));

	$db->simple_select("users", "uid", "avatar != '' AND avatardimensions = ''");
	while($user = $db->fetch_array($query))
	{
		$db->update_query("users", array('avatardimensions' => $avatardimensions), "uid='{$user['uid']}'", 1);
	}

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_redoconfig");
}

function upgrade12_redoconfig()
{
	global $db, $output, $orig_config, $mybb;

	$config = $orig_config;

	$output->print_header("Rewriting config.php");

	if(!is_array($config['database']))
	{
		// Backup our old Config file
		@copy(MYBB_ROOT."inc/config.php", MYBB_ROOT."inc/config.backup.php");

		$fh = @fopen(MYBB_ROOT."inc/config.php", "w");
		if(!$fh)
		{
			echo "<p><span style=\"color: red; font-weight: bold;\">Unable to open inc/config.php</span><br />Before the upgrade process can continue, you need to changes the permissions of inc/config.php so it is writable.</p>";
			$output->print_footer("12_redoconfig");
			exit;
		}

		if(!$config['memcache_host'])
		{
			$config['memcache_host'] = "localhost";
		}

		if(!$config['memcache_port'])
		{
			$config['memcache_port'] = 11211;
		}

		$comment = "";

		if(!$db->db_encoding || !$config['db_encoding'])
		{
			$comment = " // ";
		}

		if(!$config['db_encoding'])
		{
			$config['db_encoding'] = "utf8";
		}

		$configdata = "<?php
/**
 * Database configuration
 *
 * Please see the MyBB Docs for advanced
 * database configuration for larger installations
 * http://docs.mybb.com/
 */

\$config['database']['type'] = '{$config['dbtype']}';
\$config['database']['database'] = '{$config['database']}';
\$config['database']['table_prefix'] = '{$config['table_prefix']}';

\$config['database']['hostname'] = '{$config['hostname']}';
\$config['database']['username'] = '{$config['username']}';
\$config['database']['password'] = '{$config['password']}';

/**
 * Admin CP directory
 *  For security reasons, it is recommended you
 *  rename your Admin CP directory. You then need
 *  to adjust the value below to point to the
 *  new directory.
 */

\$config['admin_dir'] = '{$config['admin_dir']}';

/**
 * Hide all Admin CP links
 *  If you wish to hide all Admin CP links
 *  on the front end of the board after
 *  renaming your Admin CP directory, set this
 *  to 1.
 */

\$config['hide_admin_links'] = {$config['hide_admin_links']};

/**
 * Data-cache configuration
 *  The data cache is a temporary cache
 *  of the most commonly accessed data in MyBB.
 *  By default, the database is used to store this data.
 *
 *  If you wish to use the file system (cache/ directory)
 *  you can change the value below to 'files', 'memcache' or 'eaccelerator' from 'db'.
 */

\$config['cache_store'] = '{$config['cache_store']}';

/**
 * Memcache configuration
 *  If you are using memcache as your data-cache,
 *  you need to configure the hostname and port
 *  of your memcache server below.
 *
 * If not using memcache, ignore this section.
 */

\$config['memcache_host'] = '{$config['memcache_host']}';
\$config['memcache_port'] = {$config['memcache_port']};

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

\$config['super_admins'] = '{$config['super_admins']}';

/**
 * Database Encoding
 *  If you wish to set an encoding for MyBB uncomment
 *  the line below (if it isn't already) and change
 *  the current value to the mysql charset:
 *  http://dev.mysql.com/doc/refman/5.1/en/charset-mysql.html
 */

{$comment}\$config['database']['encoding'] = '{$config['db_encoding']}';

/**
 * Automatic Log Pruning
 *  The MyBB task system can automatically prune
 *  various log files created by MyBB.
 *  To enable this functionality for the logs below, set the
 *  the number of days before each log should be pruned.
 *  If you set the value to 0, the logs will not be pruned.
 */

\$config['log_pruning'] = array(
	'admin_logs' => 365, // Administrator logs
	'mod_logs' => 0, // Moderator logs
	'task_logs' => 30, // Scheduled task logs
	'mail_logs' => 180, // Mail error logs
	'user_mail_logs' => 180, // User mail logs
	'promotion_logs' => 180 // Promotion logs
);

?".">";
		fwrite($fh, $configdata);
		fclose($fh);
	}
	echo "<p>The configuration file has successfully been rewritten.</p>";
	echo "<p>Click next to continue with the upgrade process.</p>";

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_dbchanges6");
}

function upgrade12_dbchanges6()
{
	global $db, $output;

	$output->print_header("Post IP Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = $_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = $_POST['ipstart'];
		$upper = $startat+$ipp;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $ipp;
		$lower = 1;
	}

	$query = $db->simple_select("posts", "COUNT(pid) AS ipcount");
	$cnt = $db->fetch_array($query);

	if($upper > $cnt['ipcount'])
	{
		$upper = $cnt['ipcount'];
	}

	echo "<p>Converting ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";
	flush();

	$ipaddress = false;

	$query = $db->simple_select("posts", "ipaddress, longipaddress, pid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($post = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(!$post['longipaddress'])
		{
			$db->update_query("posts", array('longipaddress' => my_ip2long($post['ipaddress'])), "pid = '{$post['pid']}'");
		}
		$ipaddress = true;
	}

	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "12_dbchanges6";
		$startat = $startat+$ipp;
		$contents = "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of post ips.</p>";
	}
	else
	{
		$nextact = "12_dbchanges7";
		$contents = "<p>Done</p><p>All post ips have been converted to the new ip format. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer($nextact);
}

function upgrade12_dbchanges7()
{
	global $db, $output;

	$output->print_header("User IP Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = $_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = $_POST['ipstart'];
		$upper = $startat+$ipp;
		$lower = $startat;
	}
	else
	{
		$startat = 0;
		$upper = $ipp;
		$lower = 1;
	}

	$query = $db->simple_select("users", "COUNT(uid) AS ipcount");
	$cnt = $db->fetch_array($query);

	if($upper > $cnt['ipcount'])
	{
		$upper = $cnt['ipcount'];
	}

	$contents .= "<p>Converting ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";

	$ipaddress = false;
	$update_array = array();

	$query = $db->simple_select("users", "regip, lastip, longlastip, longregip, uid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($user = $db->fetch_array($query))
	{
		// Have we already converted this ip?
		if(!$user['longregip'])
		{
			$update_array['longregip'] = (int)my_ip2long($user['regip']);
		}

		if(!$user['longlastip'])
		{
			$update_array['longlastip'] = (int)my_ip2long($user['lastip']);
		}

		if(!empty($update_array))
		{
			$db->update_query("users", $update_array, "uid = '{$user['uid']}'");
		}

		$update_array = array();
		$ipaddress = true;
	}

	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "12_dbchanges7";
		$startat = $startat+$ipp;
		$contents .= "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of user ips.</p>";
	}
	else
	{
		$nextact = "12_dbchanges8";
		$contents .= "<p>Done</p><p>All user ips have been converted to the new ip format. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer($nextact);
}

function upgrade12_dbchanges8()
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

	$query = $db->simple_select("events", "COUNT(eid) AS eventcount");
	$cnt = $db->fetch_array($query);

	if($upper > $cnt['eventcount'])
	{
		$upper = $cnt['eventcount'];
	}

	$contents .= "<p>Converting events {$lower} to {$upper} ({$cnt['eventcount']} Total)</p>";

	// Just started - add fields
	if(!$db->field_exists("donecon", "events"))
	{
		// Add temporary column
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD donecon smallint(1) NOT NULL;");

		if($db->field_exists('cid', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP cid;");
		}

		if($db->field_exists('visible', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP visible;");
		}

		if($db->field_exists('dateline', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP dateline;");
		}

		if($db->field_exists('starttime', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP starttime;");
		}

		if($db->field_exists('endtime', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP endtime;");
		}

		if($db->field_exists('timezone', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP timezone;");
		}

		if($db->field_exists('ignoretimezone', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP ignoretimezone;");
		}

		if($db->field_exists('usingtime', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP usingtime;");
		}

		if($db->field_exists('repeats', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP repeats;");
		}

		// Got structural changes?
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD cid int unsigned NOT NULL default '0' AFTER eid");

		if($db->field_exists('author', "events") && !$db->field_exists('uid', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE author uid int unsigned NOT NULL default '0'");
		}

		if($db->field_exists('subject', "events") && !$db->field_exists('name', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE subject name varchar(120) NOT NULL default ''");
		}
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD visible int(1) NOT NULL default '0' AFTER description");
		$db->update_query("events", array('private' => 1), "private='yes' OR private='1'");
		$db->update_query("events", array('private' => 0), "private='no' OR private='0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events CHANGE private private int(1) NOT NULL default '0'");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD dateline int(10) unsigned NOT NULL default '0' AFTER private");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD starttime int(10) unsigned NOT NULL default '0' AFTER dateline");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD endtime int(10) unsigned NOT NULL default '0' AFTER starttime");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD timezone int(3) NOT NULL default '0' AFTER endtime");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD ignoretimezone int(1) NOT NULL default '0' AFTER timezone");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD usingtime int(1) NOT NULL default '0' AFTER ignoretimezone");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events ADD repeats text NOT NULL AFTER usingtime");
	}

	if($db->field_exists('date', "events"))
	{
		$query = $db->simple_select("events", "*", "donecon!=1", array("order_by" => "eid", "limit" => $epp));
		while($event = $db->fetch_array($query))
		{
			$e_date = explode("-", $event['date']);
			if(!$e_date[2]) $e_date[2] = 2005;
			$starttime = gmmktime(0, 0, 0, $e_date[1], $e_date[0], $e_date[2]);
			$updated_event = array(
				"cid" => 1,
				"visible" => 1,
				"donecon" => 1,
				"starttime" => $starttime,
				"dateline" => $starttime
			);
			$db->update_query("events", $updated_event, "eid='{$event['eid']}'", 1);
		}

		$date = true;
	}
	else
	{
		$date = false;
	}

	$query = $db->simple_select("events", "COUNT(eid) AS remaining", "donecon!=1");
	$remaining = $db->fetch_field($query, "remaining");
	if($remaining && $date)
	{
		$nextact = "12_dbchanges8";
		$startat = $startat+$epp;
		$contents .= "<p><input type=\"hidden\" name=\"eventspage\" value=\"$epp\" /><input type=\"hidden\" name=\"eventstart\" value=\"$startat\" />Done. Click Next to move on to the next set of events.</p>";
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP donecon");
		if($db->field_exists('date', "events"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."events DROP date");
		}
		$nextact = "12_redothemes";
		$contents .= "<p>Done</p><p>All events have been converted to the new calendar system. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer($nextact);
}

function upgrade12_redothemes()
{
	global $db, $output, $config, $mybb;

	$output->print_header("Converting themes");

	if(!@is_dir(MYBB_ROOT.'cache/'))
	{
		@mkdir(MYBB_ROOT.'cache/', 077);

		// Add in empty index.html!
		$fp = @fopen(MYBB_ROOT."cache/index.html", "w");
		@fwrite($fp, "");
		@fclose($fp);
	}
	$cachewritable = @fopen(MYBB_ROOT.'cache/test.write', 'w');
	if(!$cachewritable)
	{
		$not_writable = true;
		@fclose($cachewritable);
	}
	else
	{
		@fclose($cachewritable);
	  	@my_chmod(MYBB_ROOT.'cache', '0777');
	  	@my_chmod(MYBB_ROOT.'cache/test.write', '0777');
		@unlink(MYBB_ROOT.'cache/test.write');
	}

	if($not_writable)
	{
		echo "<p><span style=\"color: red; font-weight: bold;\">Unable to write to the cache/ directory.</span><br />Before the upgrade process can continue you need to make sure this directory exists and is writable (chmod 777)</p>";
		$output->print_footer("12_redothemes");
		exit;
	}

	$not_writable = false;
	if(!@is_dir(MYBB_ROOT.'cache/themes/'))
	{
		@mkdir(MYBB_ROOT.'cache/themes/', 077);

		// Add in empty index.html!
		$fp = @fopen(MYBB_ROOT."cache/themes/index.html", "w");
		@fwrite($fp, "");
		@fclose($fp);
	}
	$themewritable = @fopen(MYBB_ROOT.'cache/themes/test.write', 'w');
	if(!$themewritable)
	{
		$not_writable = true;
		@fclose($themewritable);
	}
	else
	{
		@fclose($themewritable);
	  	@my_chmod(MYBB_ROOT.'cache/themes', '0777');
	  	@my_chmod(MYBB_ROOT.'cache/themes/test.write', '0777');
		@unlink(MYBB_ROOT.'cache/themes/test.write');
	}

	if($not_writable)
	{
		echo "<p><span style=\"color: red; font-weight: bold;\">Unable to write to the cache/themes/ directory.</span><br />Before the upgrade process can continue you need to make sure this directory exists and is writable (chmod 777)</p>";
		$output->print_footer("12_redothemes");
		exit;
	}

	if($db->field_exists('themebits', "themes") && !$db->field_exists('properties', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes CHANGE themebits properties text NOT NULL");
	}

	if($db->field_exists('cssbits', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP cssbits");
	}

	if($db->field_exists('csscached', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP csscached");
	}

	if($db->field_exists('stylesheets', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP stylesheets");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD stylesheets text NOT NULL AFTER properties");

	if($db->table_exists("themestylesheets"))
	{
		$db->drop_table("themestylesheets");
	}

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."themestylesheets(
		sid int unsigned NOT NULL auto_increment,
		name varchar(30) NOT NULL default '',
		tid int unsigned NOT NULL default '0',
		attachedto text NOT NULL,
		stylesheet text NOT NULL,
		cachefile varchar(100) NOT NULL default '',
		lastmodified bigint(30) NOT NULL default '0',
		PRIMARY KEY(sid)
	) ENGINE=MyISAM{$collation};");

	// Define our default stylesheets - MyBB 1.4 contains additional stylesheets that our converted themes will also need
	$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	if(file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php";
	}
	else if(file_exists(MYBB_ROOT."admin/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT."admin/inc/functions_themes.php";
	}
	else
	{
		$output->print_error("Please make sure your admin directory is uploaded correctly.");
	}

	// Import master theme
	if(import_theme_xml($contents, array("tid" => 1, "no_templates" => 1, "version_compat" => 1)) === -1)
	{
		$output->print_error("Please make sure your install/resources/mybb_theme.xml file is uploaded correctly.");
	}

	// Fetch out default stylesheets from master
	$query = $db->simple_select("themes", "*", "tid=1");
	$master_theme = $db->fetch_array($query);

	$master_stylesheets = my_unserialize($master_theme['stylesheets']);

	if(is_array($master_stylesheets))
	{
		// Note: 1.4 only ships with one global|global stylesheet
		foreach($master_stylesheets as $location => $sheets)
		{
			foreach($sheets as $action => $sheets)
			{
				foreach($sheets as $stylesheet)
				{
					if($location == "global" && $action == "global")
					{
						continue; // Skip global
					}

					$default_stylesheets[$location][$action][] = $stylesheet;
					$default_stylesheets['inherited']["{$location}_{$action}"][$stylesheet] = 1; // This stylesheet is inherited from the master
				}
			}
		}
	}

	$query = $db->simple_select("themes");
	while($theme = $db->fetch_array($query))
	{
		if(!$theme['css'])
		{
			continue;
		}

		$theme['css'] .= "\n\n".$theme['extracss'];

		$theme['css'] = upgrade_css_120_to_140($theme['css']);


		// Create stylesheets
		$cache_file = cache_stylesheet($theme['tid'], "global.css", $theme['css']);

		$new_stylesheet = array(
			"tid" => $theme['tid'],
			"name" => "global.css",
			"attachedto" => "",
			"stylesheet" => $db->escape_string($theme['css']),
			"cachefile" => "global.css",
			"lastmodified" => TIME_NOW
		);
		$sid = $db->insert_query("themestylesheets", $new_stylesheet);
		$css_url = "css.php?stylesheet={$sid}";
		if($cache_file)
		{
			$css_url = $cache_file;
		}

		// Now we go and update the stylesheets column for this theme
		$stylesheets = $default_stylesheets;

		// Add in our local for this theme
		$stylesheets['global']['global'][] = $css_url;

		// Update the theme
		$db->update_query("themes", array("stylesheets" => $db->escape_string(my_serialize($stylesheets))), "tid='{$theme['tid']}'");
	}

	if($db->field_exists('css', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP css");
	}

	if($db->field_exists('extracss', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP extracss");
	}

	// We need to replace this for our themes css to show up
	// <link rel="stylesheet" type="text/css" href="{$theme['css_url']}" /> must be present in the old template (it usually is)
	$query = $db->simple_select("templates", "tid,template", "title='headerinclude'");
	while($template = $db->fetch_array($query))
	{
		$template['template'] = str_replace('<link rel="stylesheet" type="text/css" href="{$theme[\'css_url\']}" />', '{$stylesheets}', $template['template']);

		$db->update_query("templates", array('template' => $db->escape_string($template['template'])), "tid='{$template['tid']}'");
	}

	echo "<p>Your themes have successfully been converted to the new theme system.</p>";
	echo "<p>Click next to continue with the upgrade process.</p>";

	global $footer_extra;
	//$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_footer("12_done");
}
