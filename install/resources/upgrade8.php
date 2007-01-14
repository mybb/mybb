<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.2.2
 */

/** NEEDS TO BE CHANGED PENDING FINAL RELEASE **/
$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade8_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD INDEX ( `uid` )");
	
	if(!$db->field_exists('recipients', "privatemessages"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages ADD recipients text NOT NULL AFTER fromid");
	}
	
	if(!$db->field_exists('maxpmrecipients', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxpmrecipients int(4) NOT NULL default '5' AFTER pmquota");
	}
	
	if(!$db->field_exists('oldadditionalgroups', "banned"))
	{	
		$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD oldadditionalgroups text NOT NULL AFTER oldgroup");
	}
	
	if(!$db->field_exists('olddisplaygroup', "banned"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD olddisplaygroup int NOT NULL default '0' AFTER oldadditionalgroups");
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

	$db->query("CREATE TABLE mybb_mailerrors(
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

	if(!$db->field_exists('maxemails' "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD maxemails int(3) NOT NULL default '5' AFTER candsendemail");
	}
	
	if(!$db->field_exists('parseorder' "mycode"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."mycode ADD parseorder smallint unsigned NOT NULL default '0' AFTER active");
	}

	$contents = "Done</p>";
	$contents .= "<p>Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("8_done");
}
?>