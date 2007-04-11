<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/eula.html
 *
 * $Id$
 */
error_reporting(E_ALL & ~E_NOTICE);

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__));
define('IN_MYBB', 1);

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

// Include the files necessary for installation
require_once MYBB_ROOT."inc/class_timers.php";
require_once MYBB_ROOT."inc/functions.php";
require_once MYBB_ROOT."inc/class_xml.php";
require_once MYBB_ROOT."inc/config.php";
require_once MYBB_ROOT."inc/db_".$config['dbtype'].".php";
require_once MYBB_ROOT.'inc/class_language.php';
$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources/');
$lang->load('language');

// If there's a custom admin dir, use it.

// Legacy for those boards trying to upgrade from an older version
if(isset($config['admindir']))
{
	require_once MYBB_ROOT.$config['admindir']."/adminfunctions.php";
}
// Current
else if(isset($config['admin_dir']))
{
	require_once MYBB_ROOT.$config['admin_dir']."/adminfunctions.php";
}
// No custom set
else
{
	require_once MYBB_ROOT."admin/adminfunctions.php";
}

// Include the necessary contants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

// Include the installation resources
require_once INSTALL_ROOT."/resources/output.php";
$output = new installerOutput;
$output->script = "upgrade.php";
$output->title = "MyBB Upgrade Wizard";

$db = new databaseEngine;
// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);


if(!$mybb->input['action'] || $mybb->input['action'] == "intro")
{
	if($db->table_exists(TABLE_PREFIX."datacache"))
	{
		require_once MYBB_ROOT."inc/class_datacache.php";
		$cache = new datacache;
		$plugins = $cache->read('plugins', true);
		if(!empty($plugins['active']))
			{
				$lang->upgrade_welcome = "<div class=\"error\"><strong><span style=\"color: red\">Warning:</span></strong> <p>There are still ".count($plugins['active'])." plugin(s) active. Active plugins can sometimes cause problems during an upgrade procedure.</p></div> <br />".$lang->upgrade_welcome;
			}
		}

		$output->print_header();

		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."upgrade_data_createmybb");
		$db->query("CREATE TABLE ".TABLE_PREFIX."upgrade_data_createmybb (
			title varchar(30) NOT NULL,
			contents text NOT NULL,
			PRIMARY KEY(title)
		);");
	}
	elseif($mybb->input['action'] == "doupgrade")
	{
		add_upgrade_store("startscript", 5);
		
		$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE avatartype avatartype varchar(10) NOT NULL;");
		if(!$db->field_exists('totalpms', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD totalpms int(10) NOT NULL default '0' AFTER showcodebuttons;");
		}
		
		if(!$db->field_exists('newpms', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD newpms int(10) NOT NULL default '0' AFTER totalpms;");
		}
		
		if(!$db->field_exists('unreadpms', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD unreadpms int(10) NOT NULL default '0' AFTER newpms;");
		}
		
		if(!$db->field_exists('showredirect', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD showredirect char(3) NOT NULL default '' AFTER showquickreply;");
		}
		
		if(!$db->field_exists('avatardimensions', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD avatardimensions varchar(10) NOT NULL default '' AFTER avatar;");
		}
		
		if(!$db->field_exists('unapprovedposts', TABLE_PREFIX."threads"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER visible;");
		}
		
		if(!$db->field_exists('unapprovedthreads', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedthreads INT(10) unsigned NOT NULL default '0' AFTER rules;");
		}
		
		if(!$db->field_exists('unapprovedposts', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD unapprovedposts INT(10) unsigned NOT NULL default '0' AFTER rules;");
		}
		
		if(!$db->field_exists('defaultdatecut', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultdatecut smallint(4) unsigned NOT NULL default '0' AFTER unapprovedposts;");
		}
		
		if(!$db->field_exists('defaultsortby', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortby varchar(10) NOT NULL default '' AFTER defaultdatecut;");
		}
		
		if(!$db->field_exists('defaultsortorder', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD defaultsortorder varchar(4) NOT NULL default '' AFTER defaultsortby;");
		}
		
		if(!$db->field_exists('lastposteruid', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastposteruid int(10) unsigned NOT NULL default '0' AFTER lastposter;");
		}
		
		if(!$db->field_exists('lastpostsubject', TABLE_PREFIX."forums"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD lastpostsubject varchar(120) NOT NULL default '' AFTER lastposttid");
		}
		
		if(!$db->field_exists('lastposteruid', TABLE_PREFIX."threads"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD lastposteruid int unsigned NOT NULL default '0' AFTER lastposter");
		}
		
		if(!$db->field_exists('canmanagemembers', TABLE_PREFIX."groupleaders"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagemembers char(3) NOT NULL default '' AFTER uid;");
		}
		
		if(!$db->field_exists('canmanagerequests', TABLE_PREFIX."groupleaders"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders ADD canmanagerequests char(3) NOT NULL default '' AFTER canmanagemembers;");
		}
		
		if(!$db->field_exists('caneditlangs', TABLE_PREFIX."adminoptions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD caneditlangs char(3) NOT NULL default '' AFTER canedithelp;");
		}
		
		if(!$db->field_exists('canrundbtools', TABLE_PREFIX."adminoptions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."adminoptions ADD canrundbtools char(3) NOT NULL default ''");
		}
		
		if(!$db->field_exists('allowedgroups', TABLE_PREFIX."themes"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."themes ADD allowedgroups text NOT NULL AFTER extracss;");
		}
		
		if(!$db->field_exists('canmovetononmodforum', TABLE_PREFIX."moderators"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."moderators ADD canmovetononmodforum char(3) NOT NULL default '' AFTER canmanagethreads;");
		}
		
		if(!$db->field_exists('csscached', TABLE_PREFIX."themes"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."themes ADD csscached bigint(30) NOT NULL default '0'");
		}
		
		$db->query("UPDATE ".TABLE_PREFIX."adminoptions SET caneditlangs='yes' WHERE canrunmaint='yes'");
		$db->query("UPDATE ".TABLE_PREFIX."adminoptions SET caneditlangs='no' WHERE canrunmaint='no'");
		$db->query("UPDATE ".TABLE_PREFIX."adminoptions SET canrundbtools='yes' WHERE canrunmaint='yes'");
		$db->query("UPDATE ".TABLE_PREFIX."adminoptions SET canrundbtools='no' WHERE canrunmaint='no'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET optionscode='select\r\ninstant=Instant Activation\r\nverify=Send Email Verification\r\nrandompass=Send Random Password\r\nadmin=Administrator Activation' WHERE name = 'regtype'");
		$db->query("UPDATE ".TABLE_PREFIX."users SET totalpms='-1', newpms='-1', unreadpms='-1'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET name='maxmessagelength' WHERE name='messagelength'");
	
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."mycode");
		$db->query("CREATE TABLE ".TABLE_PREFIX."mycode (
				cid int unsigned NOT NULL auto_increment,
				title varchar(100) NOT NULL default '',
				description text NOT NULL,
				regex text NOT NULL,
				replacement text NOT NULL,
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
	
		$db->query("INSERT INTO ".TABLE_PREFIX."templategroups (gid,prefix,title) VALUES ('1','calendar','<lang:group_calendar>');");
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
		$db->query("CREATE TABLE ".TABLE_PREFIX."searchlog (
			  sid varchar(32) NOT NULL default '',
			  uid int unsigned NOT NULL default '0',
			  dateline bigint(30) NOT NULL default '0',
			  ipaddress varchar(120) NOT NULL default '',
			  threads text NOT NULL,
			  posts text NOT NULL,
			  searchtype varchar(10) NOT NULL default '',
			  resulttype varchar(10) NOT NULL default '',
			  querycache text NOT NULL,
			  PRIMARY KEY  (sid)
			) TYPE=MyISAM;");
	
		$db->query("UPDATE ".TABLE_PREFIX."settings SET name='bannedemails' WHERE name='emailban' LIMIT 1");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET name='bannedips' WHERE name='ipban' LIMIT 1");
	
		$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedusernames'");
		$bannedusernames = $db->fetch_field($query, 'sid');
		$bannedusernames = explode(" ", $bannedusernames);
		$bannedusernames = implode(",", $bannedusernames);
		$query = $db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($bannedusernames)."' WHERE name='bannedusernames'");
	
		$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedemails'");
		$bannedemails = $db->fetch_field($query, 'sid');
		$bannedemails = explode(" ", $bannedemails);
		$bannedemails = implode(",", $bannedemails);
		$query = $db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($bannedemails)."' WHERE name='bannedemails'");
	
		$query = $db->query("SELECT value FROM ".TABLE_PREFIX."settings WHERE name='bannedips'");
		$bannedips = $db->fetch_field($query, 'sid');
		$bannedips = explode(" ", $bannedips);
		$bannedips = implode(",", $bannedips);
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($bannedips)."' WHERE name='bannedips'");
	
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."reputation");
	
		$db->query("CREATE TABLE ".TABLE_PREFIX."reputation (
		  rid int unsigned NOT NULL auto_increment,
		  uid int unsigned NOT NULL default '0',
		  adduid int unsigned NOT NULL default '0',
		  reputation bigint(30) NOT NULL default '0',
		  dateline bigint(30) NOT NULL default '0',
		  comments text NOT NULL,
		  PRIMARY KEY(rid)
		) TYPE=MyISAM;");
	
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."mailqueue");
		$db->query("CREATE TABLE ".TABLE_PREFIX."mailqueue (
			mid int unsigned NOT NULL auto_increment,
			mailto varchar(200) NOT NULL,
			mailfrom varchar(200) NOT NULL,
			subject varchar(200) NOT NULL,
			message text NOT NULL,
			headers text NOT NULL,
			PRIMARY KEY(mid)
		) TYPE=MyISAM;");
	
		$db->query("UPDATE ".TABLE_PREFIX."users SET reputation='0'");
	
		$db->query("UPDATE ".TABLE_PREFIX."usergroups SET reputationpower='1'");
		$db->query("UPDATE ".TABLE_PREFIX."usergroups SET reputationpower='2' WHERE cancp='yes'");
	
		if($db->field_exists('rating', TABLE_PREFIX."users"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP rating;");
		}
		
		if(!$db->field_exists('attachmentcount', TABLE_PREFIX."threads"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD attachmentcount int(10) unsigned NOT NULL default '0'");
		}
		
		if(!$db->field_exists('posthash', TABLE_PREFIX."posts"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD posthash varchar(32) NOT NULL default '' AFTER visible");
		}
		
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachtypes CHANGE extension extension varchar(10) NOT NULL;");
		
		if(!$db->field_exists('deletetime', TABLE_PREFIX."threads"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD deletetime int(10) unsigned NOT NULL default '0' AFTER attachmentcount");
		}
		
		if(!$db->field_exists('loginattempts', TABLE_PREFIX."sessions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."sessions ADD loginattempts tinyint(2) NOT NULL default '1'");
		}
		
		if(!$db->field_exists('failedlogin', TABLE_PREFIX."sessions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."sessions ADD failedlogin bigint(30) NOT NULL default '0'");
		}
		
		if(!$db->field_exists('canviewthreads', TABLE_PREFIX."usergroups"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD canviewthreads char(3) NOT NULL default '' AFTER canview");
		}
		
		if(!$db->field_exists('canviewthreads', TABLE_PREFIX."forumpermissions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions ADD canviewthreads char(3) NOT NULL default '' AFTER canview");
		}
		
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."captcha");
		$db->query("CREATE TABLE ".TABLE_PREFIX."captcha (
		  imagehash varchar(32) NOT NULL default '',
		  imagestring varchar(8) NOT NULL default '',
		  dateline bigint(30) NOT NULL default '0'
		) TYPE=MyISAM;");
	
		if(!$db->field_exists('data', TABLE_PREFIX."moderatorlog"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog ADD data text NOT NULL AFTER action;");
		}
		
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."adminsessions");
		$db->query("CREATE TABLE ".TABLE_PREFIX."adminsessions (
			sid varchar(32) NOT NULL default '',
			uid int unsigned NOT NULL default '0',
			loginkey varchar(50) NOT NULL default '',
			ip varchar(40) NOT NULL default '',
			dateline bigint(30) NOT NULL default '0',
			lastactive bigint(30) NOT NULL default '0'
		) TYPE=MyISAM;");
	
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."modtools");
		$db->query("CREATE TABLE ".TABLE_PREFIX."modtools (
			tid smallint unsigned NOT NULL auto_increment,
			name varchar(200) NOT NULL,
			description text NOT NULL,
			forums text NOT NULL,
			type char(1) NOT NULL default '',
			postoptions text NOT NULL,
			threadoptions text NOT NULL,
			PRIMARY KEY (tid)
		) TYPE=MyISAM;");
	
		if(!$db->field_exists('disporder', TABLE_PREFIX."usergroups"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD disporder smallint(6) NOT NULL default '0' AFTER image");
		}
		
		$db->query("UPDATE ".TABLE_PREFIX."usergroups SET canviewthreads=canview");
		$db->query("UPDATE ".TABLE_PREFIX."forumpermissions SET canviewthreads=canview");
		
		$query = $db->simple_select(TABLE_PREFIX."threads", "tid, firstpost", "closed NOT LIKE 'moved|%'", array("order_by" => "tid", "order_dir" => "asc"));
		while($thread = $db->fetch_array($query))
		{
			update_thread_count($thread['tid']);
			if($thread['firstpost'] == 0)
			{
				update_first_post($thread['tid']);
			}
		}
		
		$query = $db->simple_select(TABLE_PREFIX."forums", "fid");
		while($forum = $db->fetch_array($query))
		{
			update_forum_count($forum['fid']);
		}
		
		if($db->is_fulltext(TABLE_PREFIX."threads", "subject"))
		{
			$db->drop_index(TABLE_PREFIX."threads", "subject");
		}
		
		if($db->is_fulltext(TABLE_PREFIX."threads", "subject_2"))
		{
			$db->drop_index(TABLE_PREFIX."threads", "subject_2");
		}
	
		if($db->supports_fulltext(TABLE_PREFIX."threads"))
		{
			$db->create_fulltext_index(TABLE_PREFIX."threads", "subject");
		}
		
		if($db->supports_fulltext_boolean(TABLE_PREFIX."posts"))
		{
			if(!$db->is_fulltext(TABLE_PREFIX."posts", "message"))
			{
				$db->create_fulltext_index(TABLE_PREFIX."posts", "message");
			}
		}
		
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='yes' WHERE name='useshutdownfunc'");
		
		$db->query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE regex regex text NOT NULL");
		$db->query("ALTER TABLE ".TABLE_PREFIX."mycode CHANGE replacement replacement text NOT NULL");
		
		if(!$db->field_exists('oldadditionalgroups', TABLE_PREFIX."banned"))
		{
				$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD oldadditionalgroups text NOT NULL AFTER oldgroup");
		}
	
		if(!$db->field_exists('olddisplaygroup', TABLE_PREFIX."banned"))
		{
				$db->query("ALTER TABLE ".TABLE_PREFIX."banned ADD olddisplaygroup int NOT NULL default '0' AFTER oldadditionalgroups");
		}
	}
	
	$currentscript = get_upgrade_store("currentscript");
	$system_upgrade_detail = get_upgrade_store("upgradedetail");

	if($mybb->input['action'] == "templates")
	{
		$runfunction = "upgradethemes";
	}
	elseif($mybb->input['action'] == "rebuildsettings")
	{
		$runfunction = "buildsettings";
	}
	elseif($mybb->input['action'] == "buildcaches")
	{
		$runfunction = "buildcaches";
	}
	elseif($mybb->input['action'] == "finished")
	{
		$runfunction = "upgradedone";
	}

	if(function_exists($runfunction))
	{
		$runfunction();
	}
}

function upgradethemes()
{
	global $output, $db, $system_upgrade_detail, $lang;

	$output->print_header($lang->upgrade_templates_reverted);

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templates;");
		$db->query("CREATE TABLE ".TABLE_PREFIX."templates (
		  tid int unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  template text NOT NULL,
		  sid int(10) NOT NULL default '0',
		  version varchar(20) NOT NULL default '0',
		  status varchar(10) NOT NULL default '',
		  dateline int(10) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
	}

	if($system_upgrade_detail['revert_all_themes'] > 0)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."themes");
		$db->query("CREATE TABLE ".TABLE_PREFIX."themes (
		  tid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  pid smallint unsigned NOT NULL default '0',
		  def smallint(1) NOT NULL default '0',
		  css text NOT NULL,
		  cssbits text NOT NULL,
		  themebits text NOT NULL,
		  extracss text NOT NULL,
		  allowedgroups text NOT NULL,
		  csscached bigint(30) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");

		$insert_array = array(
			'name' => 'MyBB Master Style',
			'pid' => 0,
			'css' => '',
			'cssbits' => '',
			'themebits' => '',
			'extracss' => '',
			'allowedgroups' => ''
		);
		$db->insert_query(TABLE_PREFIX."themes", $insert_array);
		
		$insert_array = array(
			'name' => 'MyBB Default',
			'pid' => 1,
			'def' => 1,
			'css' => '',
			'cssbits' => '',
			'themebits' => '',
			'extracss' => '',
			'allowedgroups' => ''
		);
		$db->insert_query(TABLE_PREFIX."themes", $insert_array);

		$sid = $db->insert_id();
		$db->query("UPDATE ".TABLE_PREFIX."users SET style='$sid'");
		$db->query("UPDATE ".TABLE_PREFIX."forums SET style='0'");
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templatesets;");
		$db->query("CREATE TABLE ".TABLE_PREFIX."templatesets (
		  sid smallint unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");
		$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (title) VALUES ('Default Templates')");
	}
	$sid = -2;

	$arr = @file(INSTALL_ROOT."/resources/mybb_theme.xml");
	$contents = @implode("", $arr);

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];
	$css = kill_tags($theme['cssbits']);
	$themebits = kill_tags($theme['themebits']);
	$templates = $theme['templates']['template'];
	$themebits['templateset'] = 1;
	$newcount = 0;
	foreach($templates as $template)
	{
		$templatename = $template['attributes']['name'];
		$templateversion = $template['attributes']['version'];
		$templatevalue = $db->escape_string($template['value']);
		$time = time();
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."templates WHERE sid='-2' AND title='$templatename'");
		$oldtemp = $db->fetch_array($query);
		if($oldtemp['tid'])
		{
			$db->query("UPDATE ".TABLE_PREFIX."templates SET template='$templatevalue', version='$templateversion', dateline='$time' WHERE title='$templatename' AND sid='-2'");
		}
		else
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid,version,status,dateline) VALUES ('$templatename','$templatevalue','$sid','$templateversion','','$time')");
			$newcount++;
		}
	}
	update_theme(1, 0, $themebits, $css, 0);
	$output->print_contents($lang->upgrade_templates_reverted_success);
	$output->print_footer("rebuildsettings");
}

function buildsettings()
{
	global $db, $output, $system_upgrade_detail, $lang;

	if(!is_writable(MYBB_ROOT."inc/settings.php"))
	{
		$output->print_header("Rebuilding Settings");
		echo "<p><div class=\"error\"><span style=\"color: red; font-weight: bold;\">Error: Unable to open inc/settings.php</span><h3>Before the upgrade process can continue, you need to changes the permissions of inc/settings.php so it is writable.</h3></div></p>";
		$output->print_footer("rebuildsettings");
		exit;
	}
	$synccount = sync_settings($system_upgrade_detail['revert_all_settings']);

	$output->print_header($lang->upgrade_settings_sync);
	$output->print_contents(sprintf($lang->upgrade_settings_sync_success, $synccount[1], $synccount[0]));
	$output->print_footer("buildcaches");
}

function buildcaches()
{
	global $db, $output, $cache, $lang;

	$output->print_header($lang->upgrade_datacache_building);

	$contents .= $lang->upgrade_building_datacache;
	require_once MYBB_ROOT."inc/class_datacache.php";
	$cache = new datacache;
	$cache->updateversion();
	$cache->updateattachtypes();
	$cache->updatesmilies();
	$cache->updatebadwords();
	$cache->updateusergroups();
	$cache->updateforumpermissions();
	$cache->updatestats();
	$cache->updatemoderators();
	$cache->updateforums();
	$cache->updateusertitles();
	$cache->updatereportedposts();
	$cache->updatemycode();
	$cache->updateposticons();
	$cache->updateupdate_check();
	$contents .= $lang->done."</p>";

	$output->print_contents("$contents<p>".$lang->upgrade_continue."</p>");
	$output->print_footer("finished");
}

function upgradedone()
{
	global $db, $output, $mybb, $lang, $config;

	$output->print_header("Upgrade Complete");
	if(is_writable("./"))
	{
		$lock = @fopen("./lock", "w");
		$written = @fwrite($lock, "1");
		@fclose($lock);
		if($written)
		{
			$lock_note = sprintf($lang->upgrade_locked, $config['admin_dir']);
		}
	}
	if(!$written)
	{
		$lock_note = "<p><b><span style=\"color: red;\">".$lang->upgrade_removedir."</span></b></p>";
	}
	$output->print_contents(sprintf($lang->upgrade_congrats, $mybb->version, $lock_note));
	$output->print_footer();
}

function whatsnext()
{
	global $output, $db, $system_upgrade_detail, $lang;

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$output->print_header($lang->upgrade_template_reversion);
		$output->print_contents($lang->upgrade_template_reversion_success);
		$output->print_footer("templates");
	}
	else
	{
		upgradethemes();
	}
}

function next_function($from, $func="dbchanges")
{
	global $oldvers, $system_upgrade_detail, $currentscript;

	load_module("upgrade".$from.".php");
	if(function_exists("upgrade".$from."_".$func))
	{
		$function = "upgrade".$from."_".$func;
	}
	else
	{
		$from = $from+1;
		if(file_exists(INSTALL_ROOT."/resources/upgrade".$from.".php"))
		{
			$function = next_function($from);
		}
	}

	if(!$function)
	{
		$function = "whatsnext";
	}
	return $function;
}

function load_module($module)
{
	global $system_upgrade_detail, $currentscript;
	require_once INSTALL_ROOT."/resources/".$module;
	if($currentscript != $module)
	{
		foreach($upgrade_detail as $key => $val)
		{
			if(!$system_upgrade_detail[$key] || $val > $system_upgrade_detail[$key])
			{
				$system_upgrade_detail[$key] = $val;
			}
		}
		add_upgrade_store("upgradedetail", $system_upgrade_detail);
		add_upgrade_store("currentscript", $module);
	}
}

function get_upgrade_store($title)
{
	global $db;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."upgrade_data WHERE title='".$db->escape_string($title)."'");
	$data = $db->fetch_array($query);
	return unserialize($data['contents']);
}

function add_upgrade_store($title, $contents)
{
	global $db;
	$db->query("REPLACE INTO ".TABLE_PREFIX."upgrade_data (title,contents) VALUES ('".$db->escape_string($title)."', '".$db->escape_string(serialize($contents))."')");
}

function sync_settings($redo=0)
{
	global $db;
	
	$settingcount = $groupcount = 0;
	if($redo == 2)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."settinggroups");
		$db->query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
		  gid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  title varchar(220) NOT NULL default '',
		  description text NOT NULL,
		  disporder smallint unsigned NOT NULL default '0',
		  isdefault char(3) NOT NULL default '',
		  PRIMARY KEY  (gid)
		) TYPE=MyISAM;");

		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."settings");

		$db->query("CREATE TABLE ".TABLE_PREFIX."settings (
		  sid smallint(6) NOT NULL auto_increment,
		  name varchar(120) NOT NULL default '',
		  title varchar(120) NOT NULL default '',
		  description text NOT NULL,
		  optionscode text NOT NULL,
		  value text NOT NULL,
		  disporder smallint(6) NOT NULL default '0',
		  gid smallint(6) NOT NULL default '0',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");
	}
	else
	{
		$query = $db->query("SELECT name FROM ".TABLE_PREFIX."settings");
		while($setting = $db->fetch_array($query))
		{
			$settings[$setting['name']] = 1;
		}
		$query = $db->query("SELECT name,title,gid FROM ".TABLE_PREFIX."settinggroups");
		while($group = $db->fetch_array($query))
		{
			$settinggroups[$group['name']] = $group['gid'];
		}
	}
	$settings_xml = file_get_contents(INSTALL_ROOT."/resources/settings.xml");
	$parser = new XMLParser($settings_xml);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
	{
		$groupdata = array(
			"name" => $db->escape_string($settinggroup['attributes']['name']),
			"title" => $db->escape_string($settinggroup['attributes']['title']),
			"description" => $db->escape_string($settinggroup['attributes']['description']),
			"disporder" => intval($settinggroup['attributes']['disporder']),
			"isdefault" => $settinggroup['attributes']['isdefault']
		);
		if(!$settinggroups[$settinggroup['attributes']['name']] || $redo == 2)
		{
			$db->insert_query(TABLE_PREFIX."settinggroups", $groupdata);
			$gid = $db->insert_id();
			$groupcount++;
		}
		else
		{
			$gid = $settinggroups[$settinggroup['attributes']['name']];
			$db->update_query(TABLE_PREFIX."settinggroups", $groupdata, "gid='{$gid}'");
		}
		if(!$gid)
		{
			continue;
		}
		foreach($settinggroup['setting'] as $setting)
		{
			$settingdata = array(
				"name" => $db->escape_string($setting['attributes']['name']),
				"title" => $db->escape_string($setting['title'][0]['value']),
				"description" => $db->escape_string($setting['description'][0]['value']),
				"optionscode" => $db->escape_string($setting['optionscode'][0]['value']),
				"disporder" => intval($setting['disporder'][0]['value']),
				"gid" => $gid
			);
			if(!$settings[$setting['attributes']['name']] || $redo == 2)
			{
				$settingdata['value'] = $db->escape_string($setting['settingvalue'][0]['value']);
				$db->insert_query(TABLE_PREFIX."settings", $settingdata);
				$settingcount++;
			}
			else
			{
				$name = $db->escape_string($setting['attributes']['name']);
				$db->update_query(TABLE_PREFIX."settings", $settingdata, "name='{$name}'");
			}
		}
	}
	if($redo >= 1)
	{
		require MYBB_ROOT."inc/settings.php";
		foreach($settings as $key => $val)
		{
			$db->update_query(TABLE_PREFIX."settings", array('value' => $db->escape_string($val)), "name='$key'");
		}
	}
	unset($settings);
	
	return array($groupcount, $settingcount);
}

function write_settings()
{
	return "depreciated";
}
?>