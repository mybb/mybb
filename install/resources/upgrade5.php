<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

/**
 * Upgrade Script: 1.0 Final
 */

$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
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
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER visible;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedthreads INT(10) unsigned NOT NULL default '0' AFTER rules;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER rules;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD daysprune smallint(4) unsigned NOT NULL default '0' AFTER unapprovedposts;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortby varchar(10) NOT NULL default '' AFTER daysprune;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortorder varchar(4) NOT NULL default '' AFTER defaultsortby;");
	
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
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('21','search','<lang:group_nav>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('22','showteam','<lang:group_showteam>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('23','reputation','<lang:group_reputation>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('24','newthread','<lang:group_newthread>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('25','newreply','<lang:group_newreply>');");
	$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('26','member','<lang:group_member>');");

	echo "Done</p>";
	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("5_done");
}

?>