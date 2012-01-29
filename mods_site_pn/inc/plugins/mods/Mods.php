<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Mods 
{
	static $_instance;
	
	public $project;
	public $member;
	public $build;
	
	private $alttrow;
	private $running_module;
	
	private $templates = array(
		'mods_index',
		'mods_categories',
		'mods_categories_category',
		'mods_no_data'
	);
	
	private $actions = array(
		'' => 'everyone',
		'stats' => 'everyone',
		'browse' => 'everyone',
		'panel' => 'loggedin',
		'view' => 'everyone',
		'search' => 'everyone',
		'download' => 'everyone',
		'edit' => 'loggedin',
		'builds' => 'everyone',
		'suggestions' => 'everyone',
		'bugtracker' => 'everyone',
		'recommend' => 'loggedin',
		'unrecommend' => 'loggedin',
		'changelog' => 'everyone',
		'support' => 'everyone',
		'profile' => 'everyone'
	);
	
	private $modules = array(
		'suggestions',
		'recommend',
		'unrecommend',
		'bugtracker',
		'support',
		'profile',
		'stats',
		'search',
		'download',
		'builds',
		'changelog',
		'browse',
		'view',
		'panel'
	);
	
	private $menu = array(
		0 => array('Home', 'http://mybb.com/'),
		1 => array('About', 'http://mybb.com/about'),
		2 => array('Features', 'http://mybb.com/features/'),
		3 => array('Downloads', 'http://mybb.com/downloads/'),
		4 => array('Community Forum', 'http://community.mybb.com/'),
		5 => array('Get Involved', 'http://mybb.com/get-involved'),
		6 => array('Ideas', 'http://ideas.mybb.com/'),
		7 => array('Mods', 'http://mods.mybb.com/', true),
		8 => array('Wiki', 'http://wiki.mybb.com/'),
		9 => array('Blog', 'http://blog.mybb.com/')
	);
	
	/*
	 * Constructor of our Mods class.
	*/
	private function __construct()
	{
		global $db, $mybb;
		
		// If empty, it means our class should not be loaded because Mods has not been installed.
		if (empty($mybb->settings['mods_maxbuilds']))
		{
			return false;
		}
		
		// Load our Mods Interface
		require_once MYBB_ROOT."inc/plugins/mods/ModsInterface.php";

		// Load Projects
		require_once MYBB_ROOT."inc/plugins/mods/Projects.php";
		$this->projects = new Projects();
		
		// Load Categories
		require_once MYBB_ROOT."inc/plugins/mods/Categories.php";
		$this->categories = new Categories();
		
		$this->running_module = '';
	}
	
	private function __clone() {}
	
	public static function getInstance()
	{
		if (!(self::$_instance instanceof self))
		{
			self::$_instance = new self();
			
			if (self::$_instance === false)
				self::$_instane = null;
		}
		
		return self::$_instance;
	}
	
	public function install()
	{
		global $db, $mybb;
		
		// Create settings
		$insertarray = array(
			'name' => 'mods', 
			'title' => 'Mods Site', 
			'description' => "Settings for the Mods Site.", 
			'disporder' => 200, 
			'isdefault' => 0
		);
		$gid = $db->insert_query("settinggroups", $insertarray);
		
		$setting = array(
			"sid"			=> NULL,
			"name"			=> "mods_maxbuilds",
			"title"			=> "Maximum Number of Builds",
			"description"	=> "Maximum number of builds allowed per project (regardless of its status - stable or dev).",
			"optionscode"	=> "text",
			"value"			=> '30',
			"disporder"		=> 1,
			"gid"			=> $gid
		);

		$db->insert_query("settings", $setting);
		
		$setting = array(
			"sid"			=> NULL,
			"name"			=> "mods_buildsize",
			"title"			=> "Maximum ZIP Size",
			"description"	=> "Maximum size in KB a build ZIP can have.",
			"optionscode"	=> "text",
			"value"			=> '2000',
			"disporder"		=> 2,
			"gid"			=> $gid
		);

		$db->insert_query("settings", $setting);
		
		$setting = array(
			"sid"			=> NULL,
			"name"			=> "mods_previewsize",
			"title"			=> "Maximum Preview Size",
			"description"	=> "Maximum size in KB a preview image can have.",
			"optionscode"	=> "text",
			"value"			=> '2000',
			"disporder"		=> 3,
			"gid"			=> $gid
		);

		$db->insert_query("settings", $setting);
		
		$setting = array(
			"sid"			=> NULL,
			"name"			=> "mods_mods",
			"title"			=> "Moderator Groups",
			"description"	=> "Groups that have access to the Mods site zone in the ModCP.",
			"optionscode"	=> "text",
			"value"			=> '4,6',
			"disporder"		=> 4,
			"gid"			=> $gid
		);

		$db->insert_query("settings", $setting);
		
		rebuild_settings();
		
		// Create Tables: categories, projects, builds, bugs, suggestions, log
		$collation = $db->build_create_table_collation();
		
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_categories` (
		  `cid` int(10) UNSIGNED NOT NULL auto_increment,
		  `name` varchar(50) NOT NULL DEFAULT '',
		  `description` varchar(255) NOT NULL,
		  `disporder` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
		  `counter` INT(10) NOT NULL DEFAULT '0',
		  `parent` varchar(20) NOT NULL DEFAULT '',
		  PRIMARY KEY  (`cid`)
			) ENGINE=MyISAM{$collation}");
		
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_projects` (
		  `pid` int(10) UNSIGNED NOT NULL auto_increment,
		  `name` varchar(50) NOT NULL DEFAULT '',
		  `description` varchar(255) NOT NULL DEFAULT '',
		  `information` TEXT NOT NULL,
		  `licence` TEXT NOT NULL,
		  `licence_name` varchar(100) NOT NULL DEFAULT '',
		  `codename` varchar(30) NOT NULL DEFAULT '',
		  `versions` varchar(200) NOT NULL DEFAULT '',
		  `uid` int(10) NOT NULL,
		  `cid` SMALLINT(5) NOT NULL,
		  `testers` varchar(100) NOT NULL default '',
		  `collaborators` TEXT NOT NULL,
		  `recommended` int(10) NOT NULL DEFAULT '0',
		  `approved` tinyint(1) NOT NULL DEFAULT '0',
		  `hidden` enum('0','1') NOT NULL DEFAULT '0',
		  `submitted` int(10) NOT NULL DEFAULT '0',
		  `lastupdated` int(10) NOT NULL DEFAULT '0',
		  `bugtracking` enum('0','1') NOT NULL DEFAULT '0',
		  `bugtracking_open` enum('0','1') NOT NULL DEFAULT '0',
		  `suggestions` enum('0','1') NOT NULL DEFAULT '0',
		  `notifications` enum('1','2','3') NOT NULL DEFAULT '0',
		  `bugtracker_link` varchar(255) NOT NULL default '',
		  `support_link` varchar(255) NOT NULL default '',
		  `downloads` int(10) unsigned NOT NULL DEFAULT '0',
		  `notes` TEXT NOT NULL,
		  `paypal_email` varchar(255) NOT NULL DEFAULT '',
		  `guid` varchar(32) NOT NULL default '',
		  PRIMARY KEY  (`pid`), INDEX(`cid`), INDEX(`uid`), INDEX(`approved`), INDEX(`lastupdated`), INDEX(`submitted`), UNIQUE(`codename`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_bugs` (
		  `bid` int(10) UNSIGNED NOT NULL auto_increment,
		  `title` varchar(50) NOT NULL DEFAULT '',
		  `description` TEXT NOT NULL,
		  `builds` varchar(255) NOT NULL,
		  `uid` int(10) NOT NULL,
		  `assignee` int(10) NOT NULL,
		  `assignee_name` varchar(50) NOT NULL DEFAULT '',
		  `pid` int(10) NOT NULL,
		  `priority` enum('low','medium','high') NOT NULL DEFAULT 'low',
		  `status` tinyint(1) NOT NULL DEFAULT '1',
		  `date` int(10) NOT NULL DEFAULT '0',
		  `replyto` int(10) NOT NULL,
		  PRIMARY KEY  (`bid`), INDEX(`pid`), INDEX(`uid`), INDEX(`assignee`), INDEX(`status`), INDEX(`replyto`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_suggestions` (
		  `sid` int(10) UNSIGNED NOT NULL auto_increment,
		  `title` varchar(50) NOT NULL DEFAULT '',
		  `description` TEXT NOT NULL,
		  `uid` int(10) NOT NULL,
		  `pid` int(10) NOT NULL,
		  `replyto` int(10) NOT NULL,
		  `date` int(10) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (`sid`), INDEX(`pid`), INDEX(`uid`), INDEX(`replyto`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_builds` (
		  `bid` int(10) UNSIGNED NOT NULL auto_increment,
		  `number` int(10) NOT NULL DEFAULT '0',
		  `pid` int(10) NOT NULL DEFAULT '0',
		  `uid` int(10) unsigned NOT NULL DEFAULT '0',
		  `filename` varchar(120) NOT NULL DEFAULT '',
		  `filetype` varchar(120) NOT NULL DEFAULT '',
		  `filesize` int(10) NOT NULL DEFAULT '0',
		  `dateuploaded` int(10) NOT NULL DEFAULT '0',
		  `downloads` int(10) unsigned NOT NULL DEFAULT '0',
		  `status` enum('stable','dev') NOT NULL DEFAULT 'dev',
		  `versions` varchar(200) NOT NULL DEFAULT '',
		  `md5` varchar(32) NOT NULL default '',
		  `waitingstable` tinyint(1) NOT NULL DEFAULT '0',
		  `changes` TEXT NOT NULL,
		  PRIMARY KEY  (`bid`), INDEX(`pid`), INDEX(`uid`), INDEX(`dateuploaded`), INDEX (`status`), INDEX(`waitingstable`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_approved` (
		  `uid` int(10) NOT NULL,
		  PRIMARY KEY  (`uid`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_recommended` (
		  `uid` int(10) NOT NULL,
		  `pid` int(10) NOT NULL
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_invitations` (
		  `iid` int(10) UNSIGNED NOT NULL auto_increment,
		  `fromuid` int(10) unsigned NOT NULL DEFAULT '0',
		  `touid` int(10) unsigned NOT NULL DEFAULT '0',
		  `pid` int(10) NOT NULL DEFAULT '0',
		  `date` int(10) NOT NULL DEFAULT '0',
		  PRIMARY KEY  (`iid`), INDEX(`pid`), INDEX(`fromuid`), INDEX(`touid`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_searchlog` (
		  `sid` varchar(32) NOT NULL default '',
		  `uid` int unsigned NOT NULL default '0',
		  `ipaddress` varchar(120) NOT NULL,
		  `date` bigint(30) NOT NULL,
		  `querywhere` TEXT NOT NULL,
		  PRIMARY KEY (`sid`)
			) ENGINE=MyISAM{$collation}");
			
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."mods_previews` (
		  `pid` int(10) UNSIGNED NOT NULL auto_increment,
		  `project` int(10) unsigned NOT NULL DEFAULT '0',
		  `uid` int(10) unsigned NOT NULL DEFAULT '0',
		  `date` int(10) NOT NULL DEFAULT '0',
		  `filename` varchar(255) NOT NULL DEFAULT '',
		  `thumbnail` varchar(255) NOT NULL DEFAULT '',
		  PRIMARY KEY  (`pid`), INDEX(`uid`), INDEX(`project`)
			) ENGINE=MyISAM{$collation}");
			
		// create task
		$new_task = array(
			"title" => "Mods Site",
			"description" => "Cleans old searches.",
			"file" => "mods",
			"minute" => '0',
			"hour" => '*',
			"day" => '*',
			"month" => '*',
			"weekday" => '*',
			"enabled" => '0',
			"logging" => '1'
		);
		
		$new_task['nextrun'] = 0; // once the task is enabled, it will generate a nextrun date
		$tid = $db->insert_query("tasks", $new_task);
		
		// Creates fulltext index
		if($db->supports_fulltext('mods_projects'))
		{
			$db->create_fulltext_index('mods_projects', 'name');
		}
	}
	
	public function is_installed()
	{
		global $db;
		
		if ($db->table_exists("mods_projects"))
			return true;
		
		return false;
	}
	
	public function uninstall()
	{
		global $db, $mybb;
		
		if ($db->table_exists("mods_projects"))
			$db->drop_table("mods_projects");
			
		if ($db->table_exists("mods_bugs"))
			$db->drop_table("mods_bugs");
			
		if ($db->table_exists("mods_suggestions"))
			$db->drop_table("mods_suggestions");
			
		if ($db->table_exists("mods_builds"))
			$db->drop_table("mods_builds");
			
		if ($db->table_exists("mods_log"))
			$db->drop_table("mods_log");
			
		if ($db->table_exists("mods_categories"))
			$db->drop_table("mods_categories");
		
		if ($db->table_exists("mods_approved"))
			$db->drop_table("mods_approved");
			
		if ($db->table_exists("mods_invitations"))
			$db->drop_table("mods_invitations");
			
		if ($db->table_exists("mods_searchlog"))
			$db->drop_table("mods_searchlog");
			
		if ($db->table_exists("mods_recommended"))
			$db->drop_table("mods_recommended");
			
		// delete settings group
		$db->delete_query("settinggroups", "name = 'mods'");

		// remove settings
		$db->delete_query('settings', 'name IN (\'mods_maxbuilds\',\'mods_buildsize\',\'mods_mods\',\'mods_previewsize\')');

		rebuild_settings();
		
		// delete task
		$db->delete_query('tasks', 'file=\'mods\''); 
	}
	
	public function activate()
	{
		global $db;
		
		// Add our templates
		/*$template = array(
			"tid" => "NULL",
			"title" => "mods_index",
			"template" => $db->escape_string('
<html>
<head>
<title>{$lang->mods}</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="4"><strong>{$lang->mods_index}</strong></td>
	</tr>
	<tr>
		<td class="trow1" align="center"><a href="{$mybb->settings[\'bburl\']}/mods.php?action=browse&amp;category=plugins"><strong>{$lang->mods_plugins}</strong><br /><img src="{$mybb->settings[\'bburl\']}/images/mods/plugins.png" title="{$lang->mods_plugins}" /></a></td>
		<td class="trow2" align="center"><a href="{$mybb->settings[\'bburl\']}/mods.php?action=browse&amp;category=themes"><strong>{$lang->mods_themes}</strong><br /><img src="{$mybb->settings[\'bburl\']}/images/mods/themes.png" title="{$lang->mods_themes}" /></a></td>
		<td class="trow1" align="center"><a href="{$mybb->settings[\'bburl\']}/mods.php?action=browse&amp;category=graphics"><strong>{$lang->mods_graphics}</strong><br /><img src="{$mybb->settings[\'bburl\']}/images/mods/graphics.png" title="{$lang->mods_graphics}" /></a></td>
		<td class="trow2" align="center"><a href="{$mybb->settings[\'bburl\']}/mods.php?action=browse&amp;category=resources"><strong>{$lang->mods_resources}</strong><br /><img src="{$mybb->settings[\'bburl\']}/images/mods/resources.png" title="{$lang->mods_resources}" /></a></td>
	</tr>
</table>
{$footer}
</body>
</html>'),
			"sid" => "-1",
		);
		$db->insert_query("templates", $template);
		
		$template = array(
			"tid" => "NULL",
			"title" => "mods_categories",
			"template" => $db->escape_string('
<html>
<head>
<title>{$lang->mods} - {$place}</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<tr>
		<td class="thead" colspan="2"><strong>{$lang->mods_categories}</strong></td>
	</tr>
	<tr>
		<td class="tcat"><strong>{$lang->mods_name}</strong></td>
		<td class="tcat" align="center" width="30%"><strong>{$lang->mods_downloads}</strong></td>
	</tr>
	{$categories}
</table>
{$footer}
</body>
</html>'),
			"sid" => "-1",
		);
		$db->insert_query("templates", $template);
		
		$template = array(
			"tid" => "NULL",
			"title" => "mods_categories_category",
			"template" => $db->escape_string('
<tr>
	<td class="{$bgcolor}"><a href="{$mybb->settings[\'bburl\']}/mods.php?action=browse&amp;category={$category[\'cid\']}">{$category[\'name\']}</a><br /><span class="smalltext">{$category[\'description\']}</span></td>
	<td class="{$bgcolor}" align="center">{$category[\'downloads\']}</td>
</tr>'),
			"sid" => "-1",
		);
		$db->insert_query("templates", $template);
		
		$template = array(
			"tid" => "NULL",
			"title" => "mods_no_data",
			"template" => $db->escape_string('<tr><td colspan="{$colspan}" class="trow1">{$lang->mods_no_data}</td></tr>'),
			"sid" => "-1",
		);
		$db->insert_query("templates", $template);*/
		
		// Change admin permissions
		change_admin_permission("mods", false, 1);
		change_admin_permission("mods", "categories", 1);
		change_admin_permission("mods", "projects", 1);
		change_admin_permission("mods", "builds", 1);
		change_admin_permission("mods", "logs", 1);
		change_admin_permission("mods", "approved", 1);
	}
	
	public function deactivate()
	{
		global $db;
		
		// Remove our templates
		//$db->delete_query('templates', 'title IN (\''.implode("','", $this->templates).'\')');
		
		// Change admin permissions
		change_admin_permission("mods", false, -1);
		change_admin_permission("mods", "categories", -1);
		change_admin_permission("mods", "projects", -1);
		change_admin_permission("mods", "builds", -1);
		change_admin_permission("mods", "logs", -1);
		change_admin_permission("mods", "approved", -1);
	}
	
	/*
	 * Loads and starts a specified module.
	 * @param $module String the name of the module to be loaded.
	*/
	public function module_load($module)
	{
		if (is_object($this->running_module))
		{
			return 1; // A module is already running
		}
		
		if ($module == '')
		{
			return 2; // Module name cannot be empty
		}
		
		require_once MYBB_ROOT."inc/plugins/mods/Modules.php";
		
		if (!in_array($module, $this->modules))
		{
			return 3; // Not a valid module
		}
		
		if (!file_exists(MYBB_ROOT."inc/plugins/mods/modules/".basename($module).".php"))
		{
			return 3; // Not a valid module
		}
		
		require_once MYBB_ROOT."inc/plugins/mods/modules/".basename($module).".php";
		
		$classes = get_declared_classes();
		$class = end($classes); // Grab the one that has just been declared 
		
		$module = new $class();
		if (!is_object($module))
		{
			die("Something went wrong with the Mods Site, please contact the Administrator.");
		}
		
		$this->running_module = $module;
		
		// Setup
		$module->setup();
		
		return 0;
	}
	
	public function module_run_pre()
	{
		if (!is_object($this->running_module))
		{
			return 1; // A module is not running
		}
		
		// Pre Run
		$this->running_module->run_pre();
	}
	
	public function module_run()
	{
		if (!is_object($this->running_module))
		{
			return 1; // A module is not running
		}
		
		// Run
		$this->running_module->run();
	}
	
	public function module_terminate()
	{
		if ($this->running_module == '')
		{
			return 1; // A module is not running
		}
		
		$this->running_module->end();
	}
	
	// Verifies if we're on a valid action
	public function filterAction()
	{
		global $mybb;
		
		foreach ($this->actions as $action => $perm)
		{
			if ($action == $mybb->input['action'])
				return $action;
		}
		
		return false;
	}
	
	// Must be run after the filterAction method to make sure we've got permissions to be on that action
	public function verifyPermissions()
	{
		global $mybb;
		
		switch ($this->actions[$mybb->input['action']])
		{
			case 'everyone':
				// do nothing
			break;
			
			case 'loggedin':
				if (!$mybb->user['uid'])
				{
					error_no_permission();
				}
			break;
		}
	}
	
	public function buildMenu()
	{
		ksort($this->menu);
		
		$menu = '';
		
		foreach ($this->menu as $m)
		{
			if ($m[2] !== true)
				$m['class'] = 'default';
			else
				$m['class'] = 'active';
			
			$menu .= '<li class="'.$m['class'].'"><a href="'.$m[1].'">'.$m[0].'</a></li>';
		}
		
		return $menu;
	}
	
	public function buildNavHighlight($force='')
	{
		global $navstyle, $mybb;
		
		if ($force!='')
		{
			$navstyle[$force] = ' style="font-weight: bold"';
			return;
		}
		
		switch ($mybb->input['action'])
		{
			case 'browse':
				switch ($mybb->input['category'])
				{
					case 'plugins':
						$navstyle['plugins'] = ' style="font-weight: bold"';
					break;
					
					case 'themes':
						$navstyle['themes'] = ' style="font-weight: bold"';
					break;
					
					case 'resources':
						$navstyle['resources'] = ' style="font-weight: bold"';
					break;
					
					case 'graphics':
						$navstyle['graphics'] = ' style="font-weight: bold"';
					break;
				}
			break;
			
			case 'search':
				$navstyle['search'] = ' style="font-weight: bold"';
			break;
			
			case 'stats':
				$navstyle['stats'] = ' style="font-weight: bold"';
			break;
			
			case 'panel':
				$navstyle['panel'] = ' style="font-weight: bold"';
			break;
		}
	}
	
	public function alternative_trow()
	{
		if($this->alttrow == "trow1")
		{
			$trow = "alt";
		}
		else
		{
			$trow = "row";
		}

		$this->alttrow = $trow;

		return $trow;
	}
	
	public function getDevStatus($user)
	{
		if (!is_array($user) || empty($user) || (int)$user['uid'] <= 0)
			return false;
			
		global $lang, $db;
			
		$query = $db->simple_select('mods_approved', 'uid', 'uid=\''.intval($user['uid']).'\'');
		$status = $db->fetch_field($query, 'uid');
		
		return (($status > 0) ? $lang->mods_approved_dev : $lang->mods_regular_dev); 
	}
	
	/**
	 * Sends a PM to a user
	 * 
	 * @param array: The PM to be sent; should have 'subject', 'message', 'touid' and 'receivepms'
	 * (receivepms is for admin override in case the user has disabled pm's)
	 * @param int: from user id (0 if you want to use the uid of the person that sends it. -1 to use MyBB Engine
	 * @return bool: true if PM sent
	 */
	public function send_pm($pm, $fromid = 0)
	{
		global $lang, $mybb, $db;
		if($mybb->settings['enablepms'] == 0)
			return false;
			
		if (!is_array($pm))
			return false;
			
		if (!$pm['subject'] ||!$pm['message'] || !$pm['touid'] || !$pm['receivepms'])
			return false;
		
		$lang->load('messages');
		
		require_once MYBB_ROOT."inc/datahandlers/pm.php";
		
		$pmhandler = new PMDataHandler();
		
		$subject = $pm['subject'];
		$message = $pm['message'];
		$toid = $pm['touid'];
		
		if (is_array($toid))
			$recipients_to = $toid;
		else
			$recipients_to = array($toid);
			
		$recipients_bcc = array();
		
		if (intval($fromid) == 0)
			$fromid = intval($mybb->user['uid']);
		elseif (intval($fromid) < 0)
			$fromid = 0;
		
		$pm = array(
			"subject" => $subject,
			"message" => $message,
			"icon" => -1,
			"fromid" => $fromid,
			"toid" => $recipients_to,
			"bccid" => $recipients_bcc,
			"do" => '',
			"pmid" => ''
		);
		
		$pm['options'] = array(
			"signature" => 0,
			"disablesmilies" => 0,
			"savecopy" => 0,
			"readreceipt" => 0
		);
		
		$pm['saveasdraft'] = 0;
		$pmhandler->admin_override = 1;
		$pmhandler->set_data($pm);
		if($pmhandler->validate_pm())
		{
			$pmhandler->insert_pm();
		}
		else
		{
			return false;
		}
		
		return true;
	}
	
	// Error out!
	public function error($error='')
	{
		global $lang, $templates, $theme, $mybb, $title, $menu, $guestblock, $userblock;
		
		if (empty($error))
		{
			$error = $lang->mods_unknown_error;
		}
		
		if($_SERVER['HTTP_REFERER'])
		{
			$back = '<a href="'.htmlentities($_SERVER['HTTP_REFERER']).'">'.$lang->mods_click_back.'</a>';
		}
		
		$title = $lang->mods.' - '.$lang->mods_error;
		
		eval("\$content = \"".$templates->get("mods_error")."\";");
		eval("\$page = \"".$templates->get("mods")."\";");
		output_page($page);
		exit;
	}
	
	public function check_permissions($groups_comma)
	{
		global $mybb;
		
		if ($groups_comma == '')
			return false;
			
		$groups = explode(",", $groups_comma);
		
		$ourgroups = explode(",", $mybb->user['additionalgroups']);
		$ourgroups[] = $mybb->user['usergroup'];

		if(count(array_intersect($ourgroups, $groups)) == 0)
			return false;
		else
			return true;
	}
	
	public function build_profile_link($username, $uid)
	{
		$uid = (int)$uid;
		return "<a href=\"{$mybb->settings['bburl']}/mods.php?action=profile&amp;uid={$uid}\">".htmlspecialchars_uni($username)."</a>";
	}
	
	/**
	 * Perform a thread and post search under MySQL or MySQLi
	 *
	 * @param array Array of search data
	 * @param int If 1 it searches the subject otherwise it searches messages.
	 * @return array Array of search data with results mixed in
	 */
	public function search($keywords, $what)
	{
		global $mybb, $lang, $db;
		
		if ($what == 1)
		{
			// Searching title
			$field = 'name';
		}
		else  {
			// Searching message
			$field = 'description';
		}
		
		// Similar to MyBB
		if($keywords)
		{
			// Complex search
			$keywords = " {$keywords} ";
			if(preg_match("# and|or #", $keywords))
			{
				$looking = " AND (";
				
				// Expand the string by double quotes
				$keywords_exp = explode("\"", $keywords);
				$inquote = false;

				foreach($keywords_exp as $phrase)
				{
					// If we're not in a double quoted section
					if(!$inquote)
					{
						// Expand out based on search operators (and, or)
						$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
						$count_matches = count($matches);
						
						for($i=0; $i < $count_matches; ++$i)
						{
							$word = trim($matches[$i]);
							if(empty($word))
							{
								continue;
							}
							// If this word is a search operator set the boolean
							if($i % 2 && ($word == "and" || $word == "or"))
							{
								if($i <= 1 && $looking == " AND (")
								{
									continue;
								}

								$boolean = $word;
							}
							// Otherwise check the length of the word as it is a normal search term
							else
							{
								$word = trim($word);
								// Word is too short - show error message
								if(my_strlen($word) < $mybb->settings['minsearchword'])
								{
									$lang->mods_error_minsearchlength = $lang->sprintf($lang->mods_error_minsearchlength, $mybb->settings['minsearchword']);
									error($lang->mods_error_minsearchlength);
								}
								// Add terms to search query
								$looking .= " $boolean LOWER({$field}) LIKE '%{$word}%'";
							}
						}
					}	
					// In the middle of a quote (phrase)
					else
					{
						$phrase = str_replace(array("+", "-", "*"), '', trim($phrase));
						if(my_strlen($phrase) < $mybb->settings['minsearchword'])
						{
							$lang->mods_error_minsearchlength = $lang->sprintf($lang->mods_error_minsearchlength, $mybb->settings['minsearchword']);
							error($lang->mods_error_minsearchlength);
						}
						// Add phrase to search query				
						$looking .= " $boolean LOWER({$field}) LIKE '%{$phrase}%'";
					}

					if($looking == " AND (")
					{
						// There are no search keywords to look for
						$lang->mods_error_minsearchlength = $lang->sprintf($lang->mods_error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->mods_error_minsearchlength);
					}

					$inquote = !$inquote;
				}
				$looking .= ")";
			}
			else
			{
				$keywords = str_replace("\"", '', trim($keywords));
				if(my_strlen($keywords) < $mybb->settings['minsearchword'])
				{
					$lang->mods_error_minsearchlength = $lang->sprintf($lang->mods_error_minsearchlength, $mybb->settings['minsearchword']);
					error($lang->mods_error_minsearchlength);
				}
				$looking = " AND LOWER({$field}) LIKE '%{$keywords}%'";
			}
		}
		
		return $looking;
	}
}