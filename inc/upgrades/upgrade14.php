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
 * Upgrade Script: 1.4.2 or 1.4.3
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade14_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	// TODO: Need to check for PostgreSQL / SQLite support

	if($db->field_exists('codepress', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP codepress;");
	}

	if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD codepress int NOT NULL default '1' AFTER cpstyle");
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD codepress int(1) NOT NULL default '1' AFTER cpstyle");
	}

	if($db->type != "sqlite")
	{
		$longregip_index = $db->index_exists("users", "longregip");
		$longlastip_index = $db->index_exists("users", "longlastip");

		if($longlastip_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longlastip");
		}

		if($longregip_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP KEY longregip");
		}

		$longipaddress_index = $db->index_exists("posts", "longipaddress");
		if($longipaddress_index == true)
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts DROP KEY longipaddress");
		}
	}

	if($db->field_exists('loginattempts', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP loginattempts;");
	}

	if($db->field_exists('loginattempts', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP loginattempts;");
	}

	if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD loginattempts smallint NOT NULL default '1';");
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD loginattempts tinyint(2) NOT NULL default '1';");
	}

	if($db->field_exists('failedlogin', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP failedlogin;");
	}

	if($db->field_exists('failedlogin', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP failedlogin;");
	}

	if($db->type == "pgsql")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD failedlogin bigint NOT NULL default '0';");
	}
	else
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD failedlogin bigint(30) NOT NULL default '0';");
	}

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longregip (longregip)");
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD INDEX longlastip (longlastip)");
	}

	if($db->type == "sqlite")
	{
		// Because SQLite 2 nor 3 allows changing a column with a primary key constraint we have to completely rebuild the entire table
		// *sigh* This is the 21st century, right?
		$query = $db->simple_select("datacache");
		while($datacache = $db->fetch_array($query))
		{
			$temp_datacache[$datacache['title']] = array('title' => $db->escape_string($datacache['title']), 'cache' => $db->escape_string($datacache['cache']));
		}

		$db->write_query("DROP TABLE ".TABLE_PREFIX."datacache");

		$db->write_query("CREATE TABLE ".TABLE_PREFIX."datacache (
  title varchar(50) NOT NULL default '' PRIMARY KEY,
  cache mediumTEXT NOT NULL
);");

		reset($temp_datacache);
		foreach($temp_datacache as $data)
		{
			$db->insert_query("datacache", $data);
		}
	}
	else if($db->type == "pgsql")
	{
		if(!$db->index_exists("datacache", "title"))
		{
			$db->write_query("ALTER TABLE ".TABLE_PREFIX."datacache ADD PRIMARY KEY (title)");
		}
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_dbchanges1");
}

function upgrade14_dbchanges1()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD INDEX longipaddress (longipaddress)");
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_dbchanges2");
}

function upgrade14_dbchanges2()
{
	global $db, $output;

	$output->print_header("Cleaning up old Settings &amp; Groups");

	echo "<p>Performing necessary upgrade queries..</p>";
	flush();

	$db->delete_query("settinggroups", "name='banning' AND isdefault='0'", 1);

	$db->delete_query("settings", "name='bannedusernames'", 1);
	$db->delete_query("settings", "name='bannedips'", 1);
	$db->delete_query("settings", "name='bannedemails'", 1);
	$db->delete_query("settings", "name='publiceventcolor'", 1);
	$db->delete_query("settings", "name='privateeventcolor'", 1);
	$db->delete_query("settings", "name='cssmedium'", 1);

	$db->delete_query("templates", "title='usercp_options_timezoneselect' AND sid != '-1'");
	$db->delete_query("templates", "title='moderation_reports' AND sid != '-1'");
	$db->delete_query("templates", "title='moderation_reports_report' AND sid != '-1'");
	$db->delete_query("templates", "title='moderation_reports_multipage' AND sid != '-1'");
	$db->delete_query("templates", "title='moderation_allreports' AND sid != '-1'");
	$db->delete_query("templates", "title='showthread_ratingdisplay' AND sid != '-1'");
	$db->delete_query("templates", "title='moderation_getip_adminoptions' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_eventbit_public' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_daybit_today' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_daybit' AND sid != '-1'");
	$db->delete_query("templates", "title='online_iplookup' AND sid != '-1'");
	$db->delete_query("templates", "title='online_iplookup_adminoptions' AND sid != '-1'");
	$db->delete_query("templates", "title='online_row_ip' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_eventbit_dates' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_eventbit_dates_recurring' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_eventbit_times' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_editevent_normal' AND sid != '-1'");
	$db->delete_query("templates", "title='calendar_editevent_recurring' AND sid != '-1'");

	$db->update_query("helpdocs", array('document' => $db->escape_string("MyBB makes use of cookies to store your login information if you are registered, and your last visit if you are not.
<br /><br />Cookies are small text documents stored on your computer; the cookies set by this forum can only be used on this website and pose no security risk.
<br /><br />Cookies on this forum also track the specific topics you have read and when you last read them.
<br /><br />To clear all cookies set by this forum, you can click <a href=\"misc.php?action=clearcookies&amp;key={1}\">here</a>.")), "hid='3'", 1);

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("14_done");
}

