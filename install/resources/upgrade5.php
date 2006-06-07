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
 * Upgrade Script: 1.0 / 1.1
 */

// Todo - rebuild parent lists of forums to ensure they're correct, update_forum_count for each forum

$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 2
	);

@set_time_limit(0);

function upgrade5_dbchanges()
{
	global $db, $output;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE avatartype avatartype varchar(10) NOT NULL;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD totalpms int(10) NOT NULL default '0' AFTER showcodebuttons;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD newpms int(10) NOT NULL default '0' AFTER totalpms;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD unreadpms int(10) NOT NULL default '0' AFTER newpms;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD showredirect char(3) NOT NULL default '' AFTER showquickreply;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER visible;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedthreads INT(10) unsigned NOT NULL default '0' AFTER rules;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER rules;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD daysprune smallint(4) unsigned NOT NULL default '0' AFTER unapprovedposts;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortby varchar(10) NOT NULL default '' AFTER daysprune;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortorder varchar(4) NOT NULL default '' AFTER defaultsortby;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastposteruid int(10) unsigned NOT NULL default '0' AFTER lastposter;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastpostsubject varchar(120) NOT NULL default '' AFTER lastposttid");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD lastposteruid int unsigned NOT NULL default '0' AFTER lastposter");
	$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagemembers char(3) NOT NULL default '' AFTER uid;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagerequests char(3) NOT NULL default '' AFTER canmanagemembers;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD caneditlangs char(3) NOT NULL default '' AFTER canedithelp;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."themes ADD allowedgroups text NOT NULL default '' AFTER extracss;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."moderators ADD canmovetononmodforum char(3) NOT NULL default '' AFTER canmanagethreads;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."themes ADD csscached bigint(30) NOT NULL default '0'");

	$db->query("UPDATE ".TABLE_PREFIX."settings SET optionscode='select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password\r\nadmin=Administrator Activation' WHERE name = 'regtype'");
	$db->query("UPDATE ".TABLE_PREFIX."users SET totalpms='-1', newpms='-1', unreadpms='-1'");

	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."mycode");
	$db->query("CREATE TABLE ".TABLE_PREFIX."mycode (
		    cid int unsigned NOT NULL auto_increment,
		    title varchar(100) NOT NULL default '',
		    description text NOT NULL default '',
		    regex varchar(255) NOT NULL default '',
		    replacement varchar(255) NOT NULL default '',
		    active char(3) NOT NULL default '',
			PRIMARY KEY(cid)
		) TYPE=MyISAM;");

	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templategroups");
	$db->query("CREATE TABLE ".TABLE_PREFIX."templategroups (
			gid int unsigned NOT NULL auto_increment,
			prefix varchar(50) NOT NULL default '',
			title varchar(100) NOT NULL default '',
			PRIMARY KEY (gid)
			) TYPE=MyISAM;");

	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('1','calendar','<lang:group_calendar>');";
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('2','editpost','<lang:group_editpost>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('3','email','<lang:group_email>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('4','emailsubject','<lang:group_emailsubject>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('5','forumbit','<lang:group_forumbit>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('6','forumjump','<lang:group_forumjump>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('7','forumdisplay','<lang:group_forumdisplay>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('8','index','<lang:group_index>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('9','error','<lang:group_error>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('10','memberlist','<lang:group_memberlist>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('11','multipage','<lang:group_multipage>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('12','private','<lang:group_private>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('13','portal','<lang:group_portal>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('14','postbit','<lang:group_postbit>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('15','redirect','<lang:group_redirect>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('16','showthread','<lang:group_showthread>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('17','usercp','<lang:group_usercp>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('18','online','<lang:group_online>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('19','moderation','<lang:group_moderation>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('20','nav','<lang:group_nav>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('21','search','<lang:group_search>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('22','showteam','<lang:group_showteam>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('23','reputation','<lang:group_reputation>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('24','newthread','<lang:group_newthread>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('25','newreply','<lang:group_newreply>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('26','member','<lang:group_member>');");

	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."searchlog");
	$db->query("CREATE TABLE mybb_searchlog (
		  sid varchar(32) NOT NULL default '',
		  uid int unsigned NOT NULL default '0',
		  dateline bigint(30) NOT NULL default '0',
		  ipaddress varchar(120) NOT NULL default '',
		  threads text NOT NULL default '',
		  posts text NOT NULL default '',
		  searchtype varchar(10) NOT NULL default '',
		  resulttype varchar(10) NOT NULL default '',
		  querycache text NOT NULL default '',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");

	$db->query("UPDATE ".TABLE_PREFIX."settings SET name='bannedemails' WHERE name='emailban' LIMIT 1");
	$db->query("UPDATE ".TABLE_PREFIX."settings SET name='bannedips' WHERE name='ipban' LIMIT 1");

	$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedusernames'");
	$bannedusernames = $db->fetch_result($query, 0);
	$bannedusernames = explode(" ", $bannedusernames);
	$bannedusernames = implode(",", $bannedusernames);
	$query = $db->query("UPDATE ".TABLE_PREFIX."settings SET value=".$db->escape_string($bannedusernames)." WHERE name='bannedusernames'");

	$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedemails'");
	$bannedemails = $db->fetch_result($query, 0);
	$bannedemails = explode(" ", $bannedemails);
	$bannedemails = implode(",", $bannedemails);
	$query = $db->query("UPDATE ".TABLE_PREFIX."settings SET value=".$db->escape_string($bannedemails)." WHERE name='bannedemails'");

	$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedips'");
	$bannedips = $db->fetch_result($query, 0);
	$bannedips = explode(" ", $bannedips);
	$bannedips = implode(",", $bannedips);
	$query = $db->query("UPDATE ".TABLE_PREFIX."settings SET value=".$db->escape_string($bannedips)." WHERE name='bannedips'");

	$query = $db->query("DROP TABLE ".TABLE_PREFIX."reputation");

	$query = $db->query("CREATE TABLE ".TABLE_PREFIX."reputation (
	  rid int unsigned NOT NULL auto_increment,
	  uid int unsigned NOT NULL default '0',
	  adduid int unsigned NOT NULL default '0',
	  reputation bigint(30) NOT NULL default '0',
	  dateline bigint(30) NOT NULL default '0',
	  comments text NOT NULL,
      PRIMARY KEY(rid)
	) TYPE=MyISAM;");

	$query = $db->query("CREATE TABLE ".TABLE_PREFIX."mailqueue (
		mid int unsigned NOT NULL auto_increment,
		mailto varchar(200) NOT NULL,
		mailfrom varchar(200) NOT NULL,
		subject varchar(200) NOT NULL,
		message text NOT NULL,
		headers text NOT NULL,
		PRIMARY KEY(mid)
	) TYPE=MyISAM;")

	$db->query("UPDATE ".TABLE_PREFIX."users SET reputation='0'");

	$db->query("UPDATE ".TABLE_PREFIX."usergroups SET reputationpower='1'");
	$db->query("UPDATE ".TABLE_PREFIX."usergroups SET reputationpower='2' WHERE cancp='yes'");

	$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP rating;");

	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD attachmentcount int(10) unsigned NOT NULL default '0'");

	$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD posthash varchar(32) NOT NULL default '' AFTER visible");

	$db->query("ALTER TABLE ".TABLE_PREFIX."attachtypes CHANGE extension extension varchar(10) NOT NULL;");

	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD deletetime int(10) unsigned NOT NULL default '0' AFTER attachmentcount");

	$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canviewthreads char(3) NOT NULL default '' AFTER canview");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions ADD canviewthreads char(3) NOT NULL default '' AFTER canview");

	$db->query("DROP ".TALE_PREFIX."regimages");
	$db->query("CREATE TABLE ".TABLE_PREFIX."captcha (
	  imagehash varchar(32) NOT NULL default '',
	  imagestring varchar(8) NOT NULL default '',
	  dateline bigint(30) NOT NULL default '0'
	) TYPE=MyISAM;");
	
	$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog ADD data text NOT NULL default '' AFTER action;");

	//
	// NEED TO INSERT SETTINGS FOR FULLTEXT SEARCHING AND SHUTDOWN FUNCTION STUFF ____HERE____
	//

	echo "Done</p>";

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("5_dbchanges2");
}

function upgrade5_dbchanges2()
{
	$output->print_header("Indexing");
	echo "<p>Checking and creating database indexes..</p>";

	$db->drop_index(TABLE_PREFIX."threads", "subject");
	if($db->is_fulltext(TABLE_PREFIX."threads", "subject_2"))
	{
		$db->drop_index(TABLE_PREFIX."threads", "subject_2");
	}

	if($db->supports_fulltext(TABLE_PREFIX."threads"))
	{
		$db->create_fulltext_index(TABLE_PREFIX."threads", "subject");
	}
	$fulltext = "no";
	if($db->supports_fulltext_boolean(TABLE_PREFIX."posts"))
	{
		$db->create_fulltext_index(TABLE_PREFIX."posts", "message");
		$update_data = array(
			"value" => "yes"
		);
		$fulltext = "yes";
	}

	// Register a shutdown function which actually tests if this functionality is working
	register_shutdown_function('test_shutdown_function');

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("5_done");
}



function test_shutdown_function()
{
	global $db;
	$db->query("UPDATE ".TABLE_PREFIX."settings SET value='yes' WHERE name='useshutdownfunc'");
	write_settings();
}
?>