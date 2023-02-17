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
 * Upgrade Script: 1.0.x / 1.1.x
 */


$upgrade_detail = array(
	"revert_all_templates" => 1,
	"revert_all_themes" => 1,
	"revert_all_settings" => 1,
	"requires_deactivated_plugins" => 1,
);

function upgrade5_dbchanges()
{
	global $db, $output, $mybb;

	// Performing Queries

	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users CHANGE avatartype avatartype varchar(10) NOT NULL;");
	if($db->field_exists('totalpms', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP totalpms;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD totalpms int(10) NOT NULL default '0' AFTER showcodebuttons;");


	if($db->field_exists('newpms', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP newpms;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD newpms int(10) NOT NULL default '0' AFTER totalpms;");


	if($db->field_exists('unreadpms', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP unreadpms;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD unreadpms int(10) NOT NULL default '0' AFTER newpms;");


	if($db->field_exists('showredirect', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP showredirect;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD showredirect char(3) NOT NULL default '' AFTER showquickreply;");


	if($db->field_exists('avatardimensions', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP avatardimensions;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."users ADD avatardimensions varchar(10) NOT NULL default '' AFTER avatar;");


	if($db->field_exists('unapprovedposts', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP unapprovedposts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER visible;");


	if($db->field_exists('unapprovedthreads', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP unapprovedthreads;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedthreads INT(10) unsigned NOT NULL default '0' AFTER rules;");


	if($db->field_exists('unapprovedposts', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP unapprovedposts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER rules;");


	if($db->field_exists('defaultdatecut', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP defaultdatecut;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultdatecut smallint(4) unsigned NOT NULL default '0' AFTER unapprovedposts;");


	if($db->field_exists('defaultsortby', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP defaultsortby;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortby varchar(10) NOT NULL default '' AFTER defaultdatecut;");


	if($db->field_exists('defaultsortorder', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP defaultsortorder;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortorder varchar(4) NOT NULL default '' AFTER defaultsortby;");


	if($db->field_exists('lastposteruid', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP lastposteruid;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastposteruid int(10) unsigned NOT NULL default '0' AFTER lastposter;");


	if($db->field_exists('lastpostsubject', "forums"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums DROP lastpostsubject;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastpostsubject varchar(120) NOT NULL default '' AFTER lastposttid");


	if($db->field_exists('lastposteruid', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP lastposteruid;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD lastposteruid int unsigned NOT NULL default '0' AFTER lastposter");


	if($db->field_exists('canmanagemembers', "groupleaders"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders DROP canmanagemembers;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagemembers char(3) NOT NULL default '' AFTER uid;");


	if($db->field_exists('canmanagerequests', "groupleaders"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders DROP canmanagerequests;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagerequests char(3) NOT NULL default '' AFTER canmanagemembers;");


	if($db->field_exists('caneditlangs', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP caneditlangs;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD caneditlangs char(3) NOT NULL default '' AFTER canedithelp;");


	if($db->field_exists('canrundbtools', "adminoptions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions DROP canrundbtools;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD canrundbtools char(3) NOT NULL default ''");


	if($db->field_exists('allowedgroups', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP allowedgroups;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD allowedgroups text NOT NULL AFTER extracss;");


	if($db->field_exists('canmovetononmodforum', "moderators"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderators DROP canmovetononmodforum;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderators ADD canmovetononmodforum char(3) NOT NULL default '' AFTER canmanagethreads;");


	if($db->field_exists('csscached', "themes"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes DROP csscached;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."themes ADD csscached bigint(30) NOT NULL default '0'");


	if($db->field_exists('title', "settinggroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."settinggroups DROP title;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."settinggroups ADD title varchar(220) NOT NULL default ''");


	$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET caneditlangs='yes' WHERE canrunmaint='yes'");
	$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET caneditlangs='no' WHERE canrunmaint='no'");
	$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET canrundbtools='yes' WHERE canrunmaint='yes'");
	$db->write_query("UPDATE ".TABLE_PREFIX."adminoptions SET canrundbtools='no' WHERE canrunmaint='no'");
	$db->write_query("UPDATE ".TABLE_PREFIX."settings SET optionscode='select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password\r\nadmin=Administrator Activation' WHERE name = 'regtype'");
	$db->write_query("UPDATE ".TABLE_PREFIX."users SET totalpms='-1', newpms='-1', unreadpms='-1'");
	$db->write_query("UPDATE ".TABLE_PREFIX."settings SET name='maxmessagelength' WHERE name='messagelength'");

	$collation = $db->build_create_table_collation();

	$db->drop_table("mycode");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."mycode (
		    cid int unsigned NOT NULL auto_increment,
		    title varchar(100) NOT NULL default '',
		    description text NOT NULL,
		    regex text NOT NULL,
		    replacement text NOT NULL,
		    active char(3) NOT NULL default '',
			PRIMARY KEY(cid)
		) ENGINE=MyISAM{$collation};");

	$db->drop_table("templategroups");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."templategroups (
			gid int unsigned NOT NULL auto_increment,
			prefix varchar(50) NOT NULL default '',
			title varchar(100) NOT NULL default '',
			PRIMARY KEY (gid)
			) ENGINE=MyISAM{$collation};");

	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('1','calendar','<lang:group_calendar>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('2','editpost','<lang:group_editpost>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('3','email','<lang:group_email>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('4','emailsubject','<lang:group_emailsubject>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('5','forumbit','<lang:group_forumbit>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('6','forumjump','<lang:group_forumjump>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('7','forumdisplay','<lang:group_forumdisplay>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('8','index','<lang:group_index>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('9','error','<lang:group_error>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('10','memberlist','<lang:group_memberlist>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('11','multipage','<lang:group_multipage>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('12','private','<lang:group_private>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('13','portal','<lang:group_portal>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('14','postbit','<lang:group_postbit>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('15','redirect','<lang:group_redirect>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('16','showthread','<lang:group_showthread>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('17','usercp','<lang:group_usercp>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('18','online','<lang:group_online>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('19','moderation','<lang:group_moderation>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('20','nav','<lang:group_nav>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('21','search','<lang:group_search>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('22','showteam','<lang:group_showteam>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('23','reputation','<lang:group_reputation>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('24','newthread','<lang:group_newthread>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('25','newreply','<lang:group_newreply>');");
	$db->write_query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('26','member','<lang:group_member>');");

	$db->drop_table("searchlog");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."searchlog (
		  sid varchar(32) NOT NULL default '',
		  uid int unsigned NOT NULL default '0',
		  dateline bigint(30) NOT NULL default '0',
		  ipaddress varchar(120) NOT NULL default '',
		  threads text NOT NULL,
		  posts text NOT NULL,
		  searchtype varchar(10) NOT NULL default '',
		  resulttype varchar(10) NOT NULL default '',
		  querycache text NOT NULL,
		  keywords text NOT NULL,
		  PRIMARY KEY  (sid)
		) ENGINE=MyISAM{$collation};");

	$db->write_query("UPDATE ".TABLE_PREFIX."settings SET name='bannedemails' WHERE name='emailban' LIMIT 1");
	$db->write_query("UPDATE ".TABLE_PREFIX."settings SET name='bannedips' WHERE name='ipban' LIMIT 1");

	$query = $db->simple_select("settings", "value", "name='bannedusernames'");
	$bannedusernames = $db->fetch_field($query, 'sid');
	$bannedusernames = explode(" ", $bannedusernames);
	$bannedusernames = implode(",", $bannedusernames);
	$query = $db->write_query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($bannedusernames)."' WHERE name='bannedusernames'");

	$query = $db->simple_select("settings", "value", "name='bannedemails'");
	$bannedemails = $db->fetch_field($query, 'sid');
	$bannedemails = explode(" ", $bannedemails);
	$bannedemails = implode(",", $bannedemails);
	$query = $db->write_query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($bannedemails)."' WHERE name='bannedemails'");

	$query = $db->simple_select("settings", "value", "name='bannedips'");
	$bannedips = $db->fetch_field($query, 'sid');
	$bannedips = explode(" ", $bannedips);
	$bannedips = implode(",", $bannedips);
	$db->update_query("settings", array('value' => $db->escape_string($bannedips)), "name='bannedips'");

	$db->drop_table("reputation");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."reputation (
	  rid int unsigned NOT NULL auto_increment,
	  uid int unsigned NOT NULL default '0',
	  adduid int unsigned NOT NULL default '0',
	  reputation bigint(30) NOT NULL default '0',
	  dateline bigint(30) NOT NULL default '0',
	  comments text NOT NULL,
      PRIMARY KEY(rid)
	) ENGINE=MyISAM{$collation};");

	$db->drop_table("mailqueue");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."mailqueue (
		mid int unsigned NOT NULL auto_increment,
		mailto varchar(200) NOT NULL,
		mailfrom varchar(200) NOT NULL,
		subject varchar(200) NOT NULL,
		message text NOT NULL,
		headers text NOT NULL,
		PRIMARY KEY(mid)
	) ENGINE=MyISAM{$collation};");

	$db->update_query("users", array('reputation' => 0));

	$db->update_query("usergroups", array('reputationpower' => 1));
	$db->update_query("usergroups", array('reputationpower' => 2), "cancp='yes'");

	if($db->field_exists('rating', "users"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."users DROP rating;");
	}

	if($db->field_exists('attachmentcount', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP attachmentcount;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD attachmentcount int(10) unsigned NOT NULL default '0'");


	if($db->field_exists('posthash', "posts"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts DROP posthash;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."posts ADD posthash varchar(32) NOT NULL default '' AFTER visible");


	$db->write_query("ALTER TABLE ".TABLE_PREFIX."attachtypes CHANGE extension extension varchar(10) NOT NULL;");

	if($db->field_exists('deletetime', "threads"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads DROP deletetime;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ADD deletetime int(10) unsigned NOT NULL default '0' AFTER attachmentcount");


	if($db->field_exists('loginattempts', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP loginattempts;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions ADD loginattempts tinyint(2) NOT NULL default '1'");


	if($db->field_exists('failedlogin', "sessions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions DROP failedlogin;");
	}
  	$db->write_query("ALTER TABLE ".TABLE_PREFIX."sessions ADD failedlogin bigint(30) NOT NULL default '0'");


	if($db->field_exists('canviewthreads', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP canviewthreads;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canviewthreads char(3) NOT NULL default '' AFTER canview");


	if($db->field_exists('canviewthreads', "forumpermissions"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumpermissions DROP canviewthreads;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."forumpermissions ADD canviewthreads char(3) NOT NULL default '' AFTER canview");


	$db->drop_table("captcha");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."captcha (
	  imagehash varchar(32) NOT NULL default '',
	  imagestring varchar(8) NOT NULL default '',
	  dateline bigint(30) NOT NULL default '0'
	) ENGINE=MyISAM{$collation};");

	if($db->field_exists('data', "moderatorlog"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog DROP data;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."moderatorlog ADD data text NOT NULL AFTER action;");


	$db->drop_table("adminsessions");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."adminsessions (
		sid varchar(32) NOT NULL default '',
		uid int unsigned NOT NULL default '0',
		loginkey varchar(50) NOT NULL default '',
		ip varchar(40) NOT NULL default '',
		dateline bigint(30) NOT NULL default '0',
		lastactive bigint(30) NOT NULL default '0'
	) ENGINE=MyISAM{$collation};");

	$db->drop_table("modtools");
	$db->write_query("CREATE TABLE ".TABLE_PREFIX."modtools (
		tid smallint unsigned NOT NULL auto_increment,
		name varchar(200) NOT NULL,
		description text NOT NULL,
		forums text NOT NULL,
		type char(1) NOT NULL default '',
		postoptions text NOT NULL,
		threadoptions text NOT NULL,
		PRIMARY KEY (tid)
	) ENGINE=MyISAM{$collation};");

	if($db->field_exists('disporder', "usergroups"))
	{
		$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP disporder;");
	}
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD disporder smallint(6) NOT NULL default '0' AFTER image");


	$db->write_query("UPDATE ".TABLE_PREFIX."usergroups SET canviewthreads=canview");
	$db->write_query("UPDATE ".TABLE_PREFIX."forumpermissions SET canviewthreads=canview");
}

function upgrade5_lastposts()
{
	global $db, $output;
	// Rebuilding Last Post Columns

	// Updating {$start} to {$end} of {$num_threads}...

	$query = $db->simple_select("threads", "tid, firstpost", "closed NOT LIKE 'moved|%'", array("order_by" => "tid", "order_dir" => "asc"));

	while($thread = $db->fetch_array($query))
	{
		$recount_thread = get_thread($thread['tid']);
		$count = array();

		$query = $db->simple_select("posts", "COUNT(pid) AS replies", "tid='{$thread['tid']}' AND pid!='{$recount_thread['firstpost']}' AND visible='1'");
		$count['replies'] = $db->fetch_field($query, "replies");

		// Unapproved posts
		$query = $db->simple_select("posts", "COUNT(pid) AS unapprovedposts", "tid='{$thread['tid']}' AND pid != '{$recount_thread['firstpost']}' AND visible='0'");
		$count['unapprovedposts'] = $db->fetch_field($query, "unapprovedposts");

		// Attachment count
		$query = $db->query("
				SELECT COUNT(aid) AS attachment_count
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
				WHERE p.tid='{$thread['tid']}' AND a.visible=1
		");
		$count['attachmentcount'] = $db->fetch_field($query, "attachment_count");

		$db->update_query("threads", $count, "tid='{$thread['tid']}'");
		update_thread_data($thread['tid']);

		if($thread['firstpost'] == 0)
		{
			update_first_post($thread['tid']);
		}
	}
}

function upgrade5_forumlastposts()
{
	global $db, $output;
	// Rebuilding Forum Last Posts
	// Rebuilding last post information for forums..
	$query = $db->simple_select("forums", "fid");
	while($forum = $db->fetch_array($query))
	{
		update_forum_lastpost($forum['fid']);
	}
}

function upgrade5_indexes()
{
	global $db, $output;

	// Indexing
	// Checking and creating fulltext database indexes..


	if($db->is_fulltext("threads", "subject"))
	{
		$db->drop_index("threads", "subject");
	}
	if($db->is_fulltext("threads", "subject_2"))
	{
		$db->drop_index("threads", "subject_2");
	}

	if($db->supports_fulltext("threads"))
	{
		$db->create_fulltext_index("threads", "subject");
	}
	if($db->supports_fulltext_boolean("posts"))
	{
		if(!$db->is_fulltext("posts", "message"))
		{
			$db->create_fulltext_index("posts", "message");
		}
	}
}
