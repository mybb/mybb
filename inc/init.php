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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

error_reporting(E_ALL & ~E_NOTICE);

/* Defines the root directory for MyBB.

	Uncomment the below line and set the path manually
	if you experience problems. Acceptable values are:

	Always add a trailing slash to the end of the path.

	* Path to your copy of MyBB
	* "./"
 */
//define('MYBB_ROOT', "./");

// Attempt autodetection
if(!defined('MYBB_ROOT'))
{
	define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
}

require_once MYBB_ROOT."inc/class_error.php";
$error_handler = new errorHandler();

require_once MYBB_ROOT."inc/functions.php";

require_once MYBB_ROOT."inc/class_timers.php";
$maintimer = new timer();

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

// Include the required core files
require_once MYBB_ROOT."inc/config.php";

if(!isset($config['dbtype']))
{
	$mybb->trigger_generic_error("board_not_installed", true);
}

if(empty($config['admin_dir']))
{
	$config['admin_dir'] = "admin";
}

$mybb->config = $config;

// This stuff is killing me for the moment. We need a better way of checking :S
/*
if($config['dbtype'] == 'pgsql')
{
	if(!extension_loaded('pgsql')) 
	{
   		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
		{
       		@dl('php_pgsql.dll');
   		} 
		else 
		{
       		@dl('pgsql.so');
   		}
	}
	
	if(!function_exists('pg_connect'))
	{
		$config['dbtype'] = "mysql";
	}
}
elseif(!function_exists($config['dbtype']."_connect") && !function_exists($config['dbtype']."_open"))
{
	$config['dbtype'] = "mysql";
}
*/

require_once MYBB_ROOT."inc/db_".$config['dbtype'].".php";
$db = new databaseEngine;

require_once MYBB_ROOT."inc/class_templates.php";
$templates = new templates;

require_once MYBB_ROOT."inc/class_datacache.php";
$cache = new datacache;

require_once MYBB_ROOT."inc/class_plugins.php";
$plugins = new pluginSystem;

// Include our base data handler class
require_once MYBB_ROOT."inc/datahandler.php";

// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['dbtype'];

// Language initialisation
require_once MYBB_ROOT."inc/class_language.php";
$lang = new MyLanguage;
$lang->set_path(MYBB_ROOT."inc/languages");

// Load cache
$cache->cache();

// Load Settings
if(file_exists(MYBB_ROOT."inc/settings.php"))
{
	require_once MYBB_ROOT."inc/settings.php";
}

if(!file_exists(MYBB_ROOT."inc/settings.php") || !$settings)
{
	if(function_exists('rebuildsettings'))
	{
		rebuildsettings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);
		
		$query = $db->simple_select("settings", "value, name", "", $options);
		while($setting = $db->fetch_array($query))
		{
			$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
			$settings[$setting['name']] = $setting['value'];
		}
	}	
}

$settings['wolcutoff'] = $settings['wolcutoffmins']*60;

$mybb->settings = &$settings;
$mybb->config = &$config;
$mybb->cache = &$cache;

// Load plugins
if(!defined("NO_PLUGINS"))
{
	$plugins->load();
}

// Set up any shutdown functions we need to run globally
add_shutdown('send_mail_queue');

// Generate a random number for performing random actions.
$rand = rand(0, 10);

/* URL Definitions */
if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
{
	define('FORUM_URL', "forum{fid}.html");
	define('FORUM_URL_PAGED', "forum{fid}-{page}.html");
	define('THREAD_URL', "thread{tid}.html");
	define('THREAD_URL_PAGED', "thread{tid}-{page}.html");
	define('THREAD_URL_ACTION', 'thread{tid}-{action}.html');
	define('THREAD_URL_POST', 'thread{tid}-p{pid}.html');
	define('POST_URL', "post{pid}.html");
	define('PROFILE_URL', "user{uid}.html");
	define('ANNOUNCEMENT_URL', "announcement{aid}.html");
	define('CALENDAR_URL', "calendar{year}-{month}.html");
	define('CALENDAR_URL_DAY', 'calendar{year}-{month}-{day}.html');
	define('EVENT_URL', "event{eid}.html");
	define('INDEX_URL', "index.php");
}
else
{
	define('FORUM_URL', "forumdisplay.php?fid={fid}");
	define('FORUM_URL_PAGED', "forumdisplay.php?fid={fid}&page={page}");
	define('THREAD_URL', "showthread.php?tid={tid}");
	define('THREAD_URL_PAGED', "showthread.php?tid={tid}&page={page}");
	define('THREAD_URL_ACTION', 'showthread.php?tid={tid}&action={action}');
	define('THREAD_URL_POST', 'showthread.php?tid={tid}&pid={pid}');
	define('POST_URL', "showthread.php?pid={pid}");
	define('PROFILE_URL', "member.php?action=profile&uid={uid}");
	define('ANNOUNCEMENT_URL', "announcements.php?aid={aid}");
	define('CALENDAR_URL', "calendar.php?year={year}&month={month}");
	define('CALENDAR_URL_DAY', 'calendar.php?action=dayview&year={year}&month={month}&day={day}');
	define('EVENT_URL', "calendar.php?action=event&eid={eid}");
	define('INDEX_URL', "index.php");
}
?>