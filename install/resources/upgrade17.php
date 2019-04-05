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
 * Upgrade Script: 1.4.13 or 1.4.14
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade17_dbchanges()
{
	global $db, $output, $mybb, $cache;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	echo "<p>Adding index to private messages table ... ";
	flush();

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `toid` )");
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("17_dbchanges2");
}

function upgrade17_dbchanges2()
{
	global $db, $output, $mybb, $cache;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	// Update our version history first
	$version_history = array();
	$dh = opendir(INSTALL_ROOT."resources");
	while(($file = readdir($dh)) !== false)
	{
		if(preg_match("#upgrade([0-9]+).php$#i", $file, $match))
		{
			$version_history[$match[1]] = $match[1];
		}
	}
	sort($version_history, SORT_NUMERIC);

	// This script isn't done yet!
	unset($version_history['17']);

	$cache->update("version_history", $version_history);

	if($db->field_exists('prefix', 'threads'))
	{
		$db->drop_column("threads", "prefix");
	}

	if($db->field_exists('loginattempts', "adminoptions"))
	{
		$db->drop_column("adminoptions", "loginattempts");
	}

	if($db->field_exists('loginlockoutexpiry', "adminoptions"))
	{
		$db->drop_column("adminoptions", "loginlockoutexpiry");
	}

	if($db->field_exists('canonlyviewownthreads', "forumpermissions"))
	{
		$db->drop_column("forumpermissions", "canonlyviewownthreads");
	}

	if($db->field_exists('isgroup', 'moderators'))
	{
		$db->drop_column("moderators", "isgroup");
	}

	if($db->field_exists('referrals', 'promotions'))
	{
		$db->drop_column("promotions", "referrals");
	}

	if($db->field_exists('referralstype', 'promotions'))
	{
		$db->drop_column("promotions", "referralstype");
	}

	if($db->field_exists('pid', 'reputation'))
	{
		$db->drop_column("reputation", "pid");
	}

	if($db->field_exists('allowvideocode', 'calendars'))
	{
		$db->drop_column("calendars", "allowvideocode");
	}

	if($db->field_exists('allowvideocode', 'forums'))
	{
		$db->drop_column("forums", "allowvideocode");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("threads", "prefix", "smallint NOT NULL default '0' AFTER subject");
			$db->add_column("adminoptions", "loginattempts", "int NOT NULL default '0'");
			$db->add_column("adminoptions", "loginlockoutexpiry", "int NOT NULL default '0'");
			$db->add_column("forumpermissions", "canonlyviewownthreads", "int NOT NULL default '0' AFTER canviewthreads");
			$db->add_column("moderators", "isgroup", "int NOT NULL default '0'");
			$db->add_column("promotions", "referrals", "int NOT NULL default '0' AFTER reputationtype");
			$db->add_column("promotions", "referralstype", "char(2) NOT NULL default '' AFTER referrals");
			$db->add_column("reputation", "pid", "int NOT NULL default '0'");
			$db->add_column("calendars", "allowvideocode", "int NOT NULL default '0' AFTER allowimgcode");
			$db->add_column("forums", "allowvideocode", "int NOT NULL default '0' AFTER allowimgcode");
			break;
		case "sqlite":
			$db->add_column("threads", "prefix", "smallint NOT NULL default '0' AFTER subject");
			$db->add_column("adminoptions", "loginattempts", "int NOT NULL default '0'");
			$db->add_column("adminoptions", "loginlockoutexpiry", "int NOT NULL default '0'");
			$db->add_column("forumpermissions", "canonlyviewownthreads", "int NOT NULL default '0' AFTER canviewthreads");
			$db->add_column("moderators", "isgroup", "int NOT NULL default '0'");
			$db->add_column("promotions", "referrals", "int NOT NULL default '0' AFTER reputationtype");
			$db->add_column("promotions", "referralstype", "varchar(2) NOT NULL default '' AFTER referrals");
			$db->add_column("reputation", "pid", "int NOT NULL default '0'");
			$db->add_column("calendars", "allowvideocode", "int(1) NOT NULL default '0' AFTER allowimgcode");
			$db->add_column("forums", "allowvideocode", "int(1) NOT NULL default '0' AFTER allowimgcode");
			break;
		default:
			$db->add_column("threads", "prefix", "smallint unsigned NOT NULL default '0' AFTER subject");
			$db->add_column("adminoptions", "loginattempts", "int unsigned NOT NULL default '0'");
			$db->add_column("adminoptions", "loginlockoutexpiry", "int unsigned NOT NULL default '0'");
			$db->add_column("forumpermissions", "canonlyviewownthreads", "int(1) NOT NULL default '0' AFTER canviewthreads");
			$db->add_column("moderators", "isgroup", "int(1) unsigned NOT NULL default '0'");
			$db->add_column("promotions", "referrals", "int NOT NULL default '0' AFTER reputationtype");
			$db->add_column("promotions", "referralstype", "varchar(2) NOT NULL default '' AFTER referrals");
			$db->add_column("reputation", "pid", "int unsigned NOT NULL default '0'");
			$db->add_column("calendars", "allowvideocode", "int(1) NOT NULL default '0' AFTER allowimgcode");
			$db->add_column("forums", "allowvideocode", "int(1) NOT NULL default '0' AFTER allowimgcode");
	}

	$db->update_query("forums", array('allowvideocode' => '1'));
	$db->update_query("calendars", array('allowvideocode' => '1'));

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("17_dbchanges3");
}

function upgrade17_dbchanges3()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	if($db->field_exists('canundovotes', 'usergroups'))
	{
		$db->drop_column("usergroups", "canundovotes");
	}

	if($db->field_exists('maxreputationsperuser', 'usergroups'))
	{
		$db->drop_column("usergroups", "maxreputationsperuser");
	}

	if($db->field_exists('maxreputationsperthread', 'usergroups'))
	{
		$db->drop_column("usergroups", "maxreputationsperthread");
	}

	if($db->field_exists('receivefrombuddy', 'users'))
	{
		$db->drop_column("users", "receivefrombuddy");
	}

	if($db->field_exists('suspendsignature', 'users'))
	{
		$db->drop_column("users", "suspendsignature");
	}

	if($db->field_exists('suspendsigtime', 'users'))
	{
		$db->drop_column("users", "suspendsigtime");
	}

	if($db->field_exists('loginattempts', 'users'))
	{
		$db->drop_column("users", "loginattempts");
	}

	if($db->field_exists('failedlogin', 'users'))
	{
		$db->drop_column("users", "failedlogin");
	}

	if($db->field_exists('usernotes', "users"))
	{
		$db->drop_column("users", "usernotes");
	}

	if($db->field_exists('referrals', 'users'))
	{
		$db->drop_column("users", "referrals");
	}

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "canundovotes", "int NOT NULL default '0' AFTER canvotepolls");
			$db->add_column("usergroups", "maxreputationsperuser", "bigint NOT NULL default '0' AFTER maxreputationsday");
			$db->add_column("usergroups", "maxreputationsperthread", "bigint NOT NULL default '0' AFTER maxreputationsperuser");
			$db->add_column("users", "receivefrombuddy", "int NOT NULL default '0'");
			$db->add_column("users", "suspendsignature", "int NOT NULL default '0'");
			$db->add_column("users", "suspendsigtime", "bigint NOT NULL default '0'");
			$db->add_column("users", "loginattempts", "smallint NOT NULL default '1'");
			$db->add_column("users", "failedlogin", "bigint NOT NULL default '0'");
			$db->add_column("users", "usernotes", "text NOT NULL default ''");
			$db->add_column("users", "referrals", "int NOT NULL default '0' AFTER referrer");
			break;
		case "sqlite":
			$db->add_column("usergroups", "canundovotes", "int NOT NULL default '0' AFTER canvotepolls");
			$db->add_column("usergroups", "maxreputationsperuser", "bigint NOT NULL default '0' AFTER maxreputationsday");
			$db->add_column("usergroups", "maxreputationsperthread", "bigint NOT NULL default '0' AFTER maxreputationsperuser");
			$db->add_column("users", "receivefrombuddy", "int NOT NULL default '0'");
			$db->add_column("users", "suspendsignature", "int NOT NULL default '0'");
			$db->add_column("users", "suspendsigtime", "bigint NOT NULL default '0'");
			$db->add_column("users", "loginattempts", "tinyint NOT NULL default '1'");
			$db->add_column("users", "failedlogin", "bigint NOT NULL default '0'");
			$db->add_column("users", "usernotes", "text NOT NULL default ''");
			$db->add_column("users", "referrals", "int NOT NULL default '0' AFTER referrer");
			break;
		default:
			$db->add_column("usergroups", "canundovotes", "int(1) NOT NULL default '0' AFTER canvotepolls");
			$db->add_column("usergroups", "maxreputationsperuser", "bigint(30) NOT NULL default '0' AFTER maxreputationsday");
			$db->add_column("usergroups", "maxreputationsperthread", "bigint(30) NOT NULL default '0' AFTER maxreputationsperuser");
			$db->add_column("users", "receivefrombuddy", "int(1) NOT NULL default '0'");
			$db->add_column("users", "suspendsignature", "int(1) NOT NULL default '0'");
			$db->add_column("users", "suspendsigtime", "bigint(30) NOT NULL default '0'");
			$db->add_column("users", "loginattempts", "tinyint(2) NOT NULL default '1'");
			$db->add_column("users", "failedlogin", "bigint(30) NOT NULL default '0'");
			$db->add_column("users", "usernotes", "text NOT NULL");
			$db->add_column("users", "referrals", "int unsigned NOT NULL default '0' AFTER referrer");
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer("17_dbchanges4");
}

function upgrade17_dbchanges4()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	if($db->field_exists('remember', 'users'))
	{
		$db->drop_column("users", "remember");
	}

	if($db->type != "pgsql")
	{
		// PgSQL doesn't support longtext
		$db->modify_column("searchlog", "threads", "longtext NOT NULL");
		$db->modify_column("searchlog", "posts", "longtext NOT NULL");
	}

	if($db->field_exists("uid", "moderators") && !$db->field_exists("id", "moderators"))
	{
		switch($db->type)
		{
			case "pgsql":
				$db->rename_column("moderators", "uid", "id", "int", true, "'0'");
				break;
			default:
				$db->rename_column("moderators", "uid", "id", "int unsigned NOT NULL default '0'");
		}
	}

	if($db->table_exists("threadprefixes"))
	{
		$db->drop_table("threadprefixes");
	}

	if($db->table_exists("delayedmoderation"))
	{
		$db->drop_table("delayedmoderation");
	}

	switch($db->type)
	{
		case "sqlite":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."threadprefixes (
				rid INTEGER PRIMARY KEY,
				tid int NOT NULL default '0',
				uid int NOT NULL default '0',
				rating smallint NOT NULL default '0',
				ipaddress varchar(30) NOT NULL default ''
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."delayedmoderation (
				did integer PRIMARY KEY,
				type varchar(30) NOT NULL default '',
				delaydateline bigint(30) NOT NULL default '0',
				uid int(10) NOT NULL default '0',
				fid smallint(5) NOT NULL default '0',
				tids text NOT NULL,
				dateline bigint(30) NOT NULL default '0',
				inputs text NOT NULL
			);");
			break;
		case "pgsql":
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."threadprefixes (
				pid serial,
				prefix varchar(120) NOT NULL default '',
				displaystyle varchar(200) NOT NULL default '',
				forums text NOT NULL,
				groups text NOT NULL,
				PRIMARY KEY(pid)
			);");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."delayedmoderation (
				did serial,
				type varchar(30) NOT NULL default '',
				delaydateline bigint NOT NULL default '0',
				uid int NOT NULL default '0',
				fid smallint NOT NULL default '0',
				tids text NOT NULL,
				dateline bigint NOT NULL default '0',
				inputs text NOT NULL default '',
				PRIMARY KEY (did)
			);");
			break;
		default:
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."threadprefixes (
				pid int unsigned NOT NULL auto_increment,
				prefix varchar(120) NOT NULL default '',
				displaystyle varchar(200) NOT NULL default '',
				forums text NOT NULL,
				`groups` text NOT NULL,
				PRIMARY KEY(pid)
			) ENGINE=MyISAM;");
			$db->write_query("CREATE TABLE ".TABLE_PREFIX."delayedmoderation (
				did int unsigned NOT NULL auto_increment,
				type varchar(30) NOT NULL default '',
				delaydateline bigint(30) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				fid smallint(5) unsigned NOT NULL default '0',
				tids text NOT NULL,
				dateline bigint(30) NOT NULL default '0',
				inputs text NOT NULL,
				PRIMARY KEY (did)
			) ENGINE=MyISAM;");
	}

	$added_tasks = sync_tasks();

	echo "<p>Added {$added_tasks} new tasks.</p>";

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer("17_dbchanges5");
}

function upgrade17_dbchanges5()
{
	global $db, $output, $mybb, $cache;

	if(file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
	{
		require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";
	}
	else if(file_exists(MYBB_ROOT."admin/inc/functions.php"))
	{
		require_once MYBB_ROOT."admin/inc/functions.php";
	}
	else
	{
		$output->print_error("Please make sure your admin directory is uploaded correctly.");
	}

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	$db->update_query("spiders", array('name' => 'Bing'), "name='MSN Search'");
	$db->update_query("spiders", array('useragent' => 'Googlebot', 'name' => 'Google'), "useragent='google'");
	$db->update_query("spiders", array('useragent' => 'Teoma', 'name' => 'Ask.com'), "useragent='ask jeeves'");
	$db->delete_query("spiders", "name='Hot Bot'");
	$db->update_query("spiders", array('useragent' => 'archive_crawler', 'name' => 'Internet Archive'), "name='Archive.org'");
	$db->update_query("spiders", array('name' => 'Alexa Internet'), "useragent='ia_archiver'");
	$db->delete_query("spiders", "useragent='scooter'");
	$db->update_query("spiders", array('useragent' => 'Slurp'), "name='Yahoo!'");

	$query = $db->simple_select("spiders", "COUNT(*) as numexists", "useragent='twiceler'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("spiders", array('name' => "Cuil", 'useragent' => 'twiceler'));
	}

	$query = $db->simple_select("spiders", "COUNT(*) as numexists", "useragent='Baiduspider'");
	if($db->fetch_field($query, "numexists") == 0)
	{
		$db->insert_query("spiders", array('name' => "Baidu", 'useragent' => 'Baiduspider'));
	}

	$db->update_query("attachtypes", array('mimetype' => 'application/x-httpd-php'), "extension='php'");
	$db->update_query("attachtypes", array('mimetype' => 'text/html'), "extension='htm'");
	$db->update_query("attachtypes", array('mimetype' => 'text/html'), "extension='html'");
	$db->update_query("attachtypes", array('mimetype' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'), "extension='docx'");
	$db->update_query("attachtypes", array('mimetype' => 'application/vnd.ms-excel'), "extension='xls'");
	$db->update_query("attachtypes", array('mimetype' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), "extension='xlsx'");
	$db->update_query("attachtypes", array('mimetype' => 'application/vnd.ms-powerpoint'), "extension='ppt'");
	$db->update_query("attachtypes", array('mimetype' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'), "extension='pptx'");

	$cache->update_moderators();

	$db->update_query("themes", array('allowedgroups' => 'all'), "allowedgroups='' OR allowedgroups IS NULL");

	// Add permissions for all of our new ACP pages
	change_admin_permission('config', 'thread_prefixes');
	change_admin_permission('tools', 'file_verification');
	change_admin_permission('tools', 'statistics');

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer("17_dbchanges6");
}

function upgrade17_dbchanges6()
{
	global $db, $output;

	$output->print_header("Post IP Repair Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = (int)$_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = (int)$_POST['ipstart'];
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

	echo "<p>Repairing ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";
	flush();

	$ipaddress = false;

	$query = $db->simple_select("posts", "ipaddress, pid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($post = $db->fetch_array($query))
	{
		$db->update_query("posts", array('longipaddress' => (int)my_ip2long($post['ipaddress'])), "pid = '{$post['pid']}'");
		$ipaddress = true;
	}

	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "17_dbchanges6";
		$startat = $startat+$ipp;
		$contents = "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of post ips.</p>";
	}
	else
	{
		$nextact = "17_dbchanges7";
		$contents = "<p>Done</p><p>All post ips have been successfully repaired. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer($nextact);
}

function upgrade17_dbchanges7()
{
	global $db, $output;

	$output->print_header("User IP Repair Conversion");

	if(!$_POST['ipspage'])
	{
		$ipp = 5000;
	}
	else
	{
		$ipp = (int)$_POST['ipspage'];
	}

	if($_POST['ipstart'])
	{
		$startat = (int)$_POST['ipstart'];
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

	$contents .= "<p>Repairing ip {$lower} to {$upper} ({$cnt['ipcount']} Total)</p>";

	$ipaddress = false;
	$update_array = array();

	$query = $db->simple_select("users", "regip, lastip, uid", "", array('limit_start' => $lower, 'limit' => $ipp));
	while($user = $db->fetch_array($query))
	{
		$update_array = array(
			'longregip' => (int)my_ip2long($user['regip']),
			'longlastip' => (int)my_ip2long($user['lastip'])
		);

		$db->update_query("users", $update_array, "uid = '{$user['uid']}'");

		$update_array = array();
		$ipaddress = true;
	}

	$remaining = $upper-$cnt['ipcount'];
	if($remaining && $ipaddress)
	{
		$nextact = "17_dbchanges7";
		$startat = $startat+$ipp;
		$contents .= "<p><input type=\"hidden\" name=\"ipspage\" value=\"$ipp\" /><input type=\"hidden\" name=\"ipstart\" value=\"$startat\" />Done. Click Next to move on to the next set of user ips.</p>";
	}
	else
	{
		$nextact = "17_redoconfig";
		$contents .= "<p>Done</p><p>All user ips have been successfully repaired. Click next to continue.</p>";
	}
	$output->print_contents($contents);

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer($nextact);
}

function upgrade17_redoconfig()
{
	global $db, $output, $orig_config, $mybb;

	$config = $orig_config;

	$output->print_header("Rewriting config.php");

	if(!is_array($config['memcache']))
	{
		// Backup our old Config file
		@copy(MYBB_ROOT."inc/config.php", MYBB_ROOT."inc/config.backup.php");

		$fh = @fopen(MYBB_ROOT."inc/config.php", "w");
		if(!$fh)
		{
			echo "<p><span style=\"color: red; font-weight: bold;\">Unable to open inc/config.php</span><br />Before the upgrade process can continue, you need to changes the permissions of inc/config.php so it is writable.</p>";
			$output->print_footer("17_redoconfig");
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

		if(!$db->db_encoding || !$config['database']['encoding'])
		{
			$comment = " // ";
		}

		if(!$config['database']['encoding'])
		{
			$config['database']['encoding'] = "utf8";
		}

		// Update SQLite selection. SQLite 2 is depreciated.
		if($config['database']['type'] == 'sqlite2' || $config['database']['type'] == 'sqlite3')
		{
			$config['database']['type'] = 'sqlite';
		}

		// Do we have a read or a write database?
		if($config['database']['read'])
		{
			$database_config = "\$config['database']['type'] = '{$config['database']['type']}';";
			foreach(array('read', 'write') as $type)
			{
				// Multiple read/write databases?
				if($config['database'][$type][0]['database'])
				{
					$i = 0;
					foreach($config['database'][$type] as $database_connection)
					{
						$database_config .= "
\$config['database']['{$type}'][{$i}]['database'] = '{$database_connection['database']}';
\$config['database']['{$type}'][{$i}]['table_prefix'] = '{$database_connection['table_prefix']}';
\$config['database']['{$type}'][{$i}]['hostname'] = '{$database_connection['hostname']}';
\$config['database']['{$type}'][{$i}]['username'] = '{$database_connection['username']}';
\$config['database']['{$type}'][{$i}]['password'] = '{$database_connection['password']}';";
						++$i;
					}
				}
				// Just a single database read/write connection
				else
				{
					$database_config .= "
\$config['database']['{$type}']['database'] = '{$config['database'][$type]['database']}';
\$config['database']['{$type}']['table_prefix'] = '{$config['database'][$type]['table_prefix']}';

\$config['database']['{$type}']['hostname'] = '{$config['database'][$type]['hostname']}';
\$config['database']['{$type}']['username'] = '{$config['database'][$type]['username']}';
\$config['database']['{$type}']['password'] = '{$config['database'][$type]['password']}';";
				}
			}
		}
		// Standard database connection stuff
		else
		{
			$database_config = "\$config['database']['type'] = '{$config['database']['type']}';
\$config['database']['database'] = '{$config['database']['database']}';
\$config['database']['table_prefix'] = '{$config['database']['table_prefix']}';

\$config['database']['hostname'] = '{$config['database']['hostname']}';
\$config['database']['username'] = '{$config['database']['username']}';
\$config['database']['password'] = '{$config['database']['password']}';
";
		}

		$configdata = "<?php
/**
 * Database configuration
 *
 * Please see the MyBB Docs for advanced
 * database configuration for larger installations
 * https://docs.mybb.com/
 */

{$database_config}

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
 *  If you wish to use the file system (cache/ directory), MemCache, xcache, or eAccelerator
 *  you can change the value below to 'files', 'memcache', 'xcache' or 'eaccelerator' from 'db'.
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

\$config['memcache']['host'] = '{$config['memcache_host']}';
\$config['memcache']['port'] = {$config['memcache_port']};

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

{$comment}\$config['database']['encoding'] = '{$config['database']['encoding']}';

/**
 * Automatic Log Pruning
 *  The MyBB task system can automatically prune
 *  various log files created by MyBB.
 *  To enable this functionality for the logs below, set the
 *  the number of days before each log should be pruned.
 *  If you set the value to 0, the logs will not be pruned.
 */

\$config['log_pruning'] = array(
	'admin_logs' => {$config['log_pruning']['admin_logs']}, // Administrator logs
	'mod_logs' => {$config['log_pruning']['mod_logs']}, // Moderator logs
	'task_logs' => {$config['log_pruning']['task_logs']}, // Scheduled task logs
	'mail_logs' => {$config['log_pruning']['mail_logs']}, // Mail error logs
	'user_mail_logs' => {$config['log_pruning']['user_mail_logs']}, // User mail logs
	'promotion_logs' => {$config['log_pruning']['promotion_logs']} // Promotion logs
);

?".">";
		fwrite($fh, $configdata);
		fclose($fh);
	}
	echo "<p>The configuration file has been successfully rewritten.</p>";
	echo "<p>Click next to continue with the upgrade process.</p>";

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer("17_updatecss");
}
function upgrade17_updatecss()
{
	global $db, $output, $orig_config, $mybb;

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

	$output->print_header("Updating CSS");

	$query = $db->simple_select("themestylesheets", "*", "name='global.css' OR name='usercp.css'");
	while($theme = $db->fetch_array($query))
	{
		resync_stylesheet($theme);
	}

	$query = $db->simple_select("themestylesheets", "*", "name='global.css' OR name='usercp.css'");
	while($theme = $db->fetch_array($query))
	{
		$theme['stylesheet'] = upgrade_css_140_to_160($theme['name'], $theme['stylesheet']);

		// Create stylesheets
		cache_stylesheet($theme['tid'], $theme['cachefile'], $theme['stylesheet']);

		$update_stylesheet = array(
			"stylesheet" => $db->escape_string($theme['stylesheet']),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $update_stylesheet, "sid='{$theme['sid']}'");
	}

	echo "<p>The CSS has been successfully updated.</p>";
	echo "<p>Click next to continue with the upgrade process.</p>";

	global $footer_extra;
	//$footer_extra = "<script type=\"text/javascript\">$(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].trigger('submit'); } });</script>";

	$output->print_footer("17_done");
}

function upgrade_css_140_to_160($name, $css)
{
	// Update our CSS to the new stuff in 1.6
	$parsed_css = css_to_array($css);

	if($name == "global.css")
	{
		if(is_array($parsed_css))
		{
			foreach($parsed_css as $class_id => $array)
			{
				switch($array['class_name'])
				{
					case '.navigation .active':
						$parsed_css[$class_id]['values'] = str_replace('font-size: small;', 'font-size: 13px;', $array['values']);
						break;
					case '.highlight':
						$parsed_css[$class_id]['values'] = str_replace('padding: 3px;', "padding-top: 3px;\n\tpadding-bottom: 3px;", $array['values']);
						break;
					case '.pm_alert':
					case '.red_alert':
						$parsed_css[$class_id]['values'] .= "\n\tmargin-bottom: 15px;";
						break;
					case '.pagination .pagination_current':
						$parsed_css[$class_id]['values'] .= "\n\tcolor: #000;";
						break;
					default:
				}
			}
		}

		$to_add = array(
			md5('#panel .remember_me input') => array("class_name" => '#panel .remember_me input', "values" => "vertical-align: middle;\n\tmargin-top: -1px;"),
			md5('.hiddenrow') => array("class_name" => '.hiddenrow', "values" => 'display: none;'),
			md5('.selectall') => array("class_name" => '.selectall', "values" => "background-color: #FFFBD9;\n\tfont-weight: bold;\n\ttext-align: center;"),
			md5('.repbox') => array("class_name" => '.repbox', "values" => "font-size:16px;\n\tfont-weight: bold;\n\tpadding:5px 7px 5px 7px;"),
			md5('._neutral') => array("class_name" => '._neutral', "values" => "background-color:#FAFAFA;\n\tcolor: #999999;\n\tborder:1px solid #CCCCCC;"),
			md5('._minus') => array("class_name" => '._minus', "values" => "background-color: #FDD2D1;\n\tcolor: #CB0200;\n\tborder:1px solid #980201;"),
			md5('._plus') => array("class_name" => '._plus', "values" => "background-color:#E8FCDC;\n\tcolor: #008800;\n\tborder:1px solid #008800;"),
			md5('.pagination_breadcrumb') => array("class_name" => '.pagination_breadcrumb', "values" => "background-color: #f5f5f5;\n\tborder: 1px solid #fff;\n\toutline: 1px solid #ccc;\n\tpadding: 5px;\n\tmargin-top: 5px;\n\tfont-weight: normal;"),
			md5('.pagination_breadcrumb_link') => array("class_name" => '.pagination_breadcrumb_link', "values" => "vertical-align: middle;\n\tcursor: pointer;"),
		);
	}
	else if($name == "usercp.css")
	{
		$to_add = array(
			md5('.usercp_notepad') => array("class_name" => '.usercp_notepad', "values" => "width: 99%;"),
			md5('.usercp_container') => array("class_name" => '.usercp_container', "values" => "margin: 5px;\n\tpadding: 8px;\n\tborder:1px solid #CCCCCC;"),
		);
	}

	foreach($to_add as $class_id => $array)
	{
		if($already_parsed[$class_id])
		{
			$already_parsed[$class_id]++;
			$class_id .= "_".$already_parsed[$class_id];
		}
		else
		{
			$already_parsed[$class_id] = 1;
		}

		$array['name'] = "";
		$array['description'] = "";

		$parsed_css[$class_id] = $array;
	}

	$css = "";
	foreach($parsed_css as $class_id => $array)
	{
		if($array['name'] || $array['description'])
		{
			$theme['css'] .= "/* ";
			if($array['name'])
			{
				$array['css'] .= "Name: {$array['name']}";

				if($array['description'])
				{
					$array['css'] .= "\n";
				}
			}

			if($array['description'])
			{
				$array['css'] .= "Description: {$array['description']}";
			}

			$array['css'] .= " */\n";
		}

		$css .= "{$array['class_name']} {\n\t{$array['values']}\n}\n";
	}

	return $css;
}

