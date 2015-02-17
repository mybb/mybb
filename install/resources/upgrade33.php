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
 * Upgrade Script: 1.8.4
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade33_dbchanges()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary optimization queries...</p>";
	flush();

	switch($db->type)
	{
		// PostgreSQL and SQLite do not support unsigned ints
		case "pgsql":
			$db->modify_column("buddyrequests", "uid", "int", "set", "'0'");
			$db->modify_column("buddyrequests", "touid", "int", "set", "'0'");
			$db->modify_column("buddyrequests", "date", "int", "set", "'0'");
			break;
		case "sqlite":
			$db->modify_column("threadratings", "rating", "tinyint(1) NOT NULL default '0'");
			$db->modify_column("buddyrequests", "uid", "int NOT NULL default '0'");
			$db->modify_column("buddyrequests", "touid", "int NOT NULL default '0'");
			$db->modify_column("buddyrequests", "date", "int NOT NULL default '0'");
			break;
		default:
			$db->modify_column("adminviews", "perpage", "smallint(4) unsigned NOT NULL default '0'");
			$db->modify_column("attachments", "filesize", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("attachtypes", "maxsize", "int(15) unsigned NOT NULL default '0'");
			$db->modify_column("banned", "olddisplaygroup", "int unsigned NOT NULL default '0'");
			$db->modify_column("buddyrequests", "uid", "int unsigned NOT NULL default '0'");
			$db->modify_column("buddyrequests", "touid", "int unsigned NOT NULL default '0'");
			$db->modify_column("buddyrequests", "date", "int unsigned NOT NULL default '0'");
			$db->modify_column("calendars", "eventlimit", "smallint(3) unsigned NOT NULL default '0'");
			$db->modify_column("massemails", "perpage", "smallint(4) unsigned NOT NULL default '50'");
			$db->modify_column("promotions", "posts", "int unsigned NOT NULL default '0'");
			$db->modify_column("promotions", "threads", "int unsigned NOT NULL default '0'");
			$db->modify_column("promotions", "registered", "int unsigned NOT NULL default '0'");
			$db->modify_column("promotions", "online", "int unsigned NOT NULL default '0'");
			$db->modify_column("promotions", "referrals", "int unsigned NOT NULL default '0'");
			$db->modify_column("promotions", "warnings", "int unsigned NOT NULL default '0'");
			$db->modify_column("sessions", "location1", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("sessions", "location2", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("threadratings", "rating", "tinyint(1) unsigned NOT NULL default '0'");
			$db->modify_column("threads", "views", "int(100) unsigned NOT NULL default '0'");
			$db->modify_column("threads", "replies", "int(100) unsigned NOT NULL default '0'");
			break;
	}

	global $footer_extra;
	$footer_extra = "<script type=\"text/javascript\">$(document).ready(function() { var button = $('.submit_button'); if(button) { button.val('Automatically Redirecting...'); button.prop('disabled', true); button.css('color', '#aaa'); button.css('border-color', '#aaa'); document.forms[0].submit(); } });</script>";

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("33_dbchanges2");
}

function upgrade33_dbchanges2()
{
	global $db, $output;

	$output->print_header("Updating Database");
	echo "<p>Performing necessary optimization queries...</p>";
	flush();

	switch($db->type)
	{
		// PostgreSQL and SQLite do not support unsigned ints
		case "sqlite":
			$db->modify_column("usergroups", "type", "tinyint(1) NOT NULL default '2'");
			break;
		case "mysql":
		case "mysqli":
			$db->modify_column("usergroups", "type", "tinyint(1) unsigned NOT NULL default '2'");
			$db->modify_column("usergroups", "stars", "smallint(4) unsigned NOT NULL default '0'");
			$db->modify_column("usergroups", "pmquota", "int(3) unsigned NOT NULL default '0'");
			$db->modify_column("usergroups", "maxpmrecipients", "int(4) unsigned NOT NULL default '5'");
			$db->modify_column("usergroups", "maxemails", "int(3) unsigned NOT NULL default '5'");
			$db->modify_column("usergroups", "emailfloodtime", "int(3) unsigned NOT NULL default '5'");
			$db->modify_column("usergroups", "maxwarningsday", "int(3) unsigned NOT NULL default '3'");
			$db->modify_column("usergroups", "edittimelimit", "int(4) unsigned NOT NULL default '0'");
			$db->modify_column("usergroups", "maxposts", "int(4) unsigned NOT NULL default '0'");
			$db->modify_column("users", "postnum", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("users", "threadnum", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("users", "ppp", "smallint(6) unsigned NOT NULL default '0'");
			$db->modify_column("users", "tpp", "smallint(6) unsigned NOT NULL default '0'");
			$db->modify_column("users", "daysprune", "smallint(6) unsigned NOT NULL default '0'");
			$db->modify_column("users", "totalpms", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("users", "unreadpms", "int(10) unsigned NOT NULL default '0'");
			$db->modify_column("users", "warningpoints", "int(3) unsigned NOT NULL default '0'");
			$db->modify_column("usertitles", "stars", "smallint(4) unsigned NOT NULL default '0'");
			$db->modify_column("warninglevels", "percentage", "smallint(3) unsigned NOT NULL default '0'");
			break;
	}

	$output->print_contents("<p>Click next to continue with the upgrade process.</p>");
	$output->print_footer("33_done");
}