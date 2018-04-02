<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Fallback
if(!defined('THIS_SCRIPT'))
{
	define('THIS_SCRIPT', 'unknown');
}

/* Defines the root directory for MyBB.

	Uncomment the below line and set the path manually
	if you experience problems.

	Always add a trailing slash to the end of the path.

	* Path to your copy of MyBB
 */
//define('MYBB_ROOT', "./");

// Attempt autodetection
if(!defined('MYBB_ROOT'))
{
	define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
}

define("TIME_NOW", time());

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MYBB_ROOT."inc/class_error.php";
$error_handler = new errorHandler();

if(!function_exists('json_encode') || !function_exists('json_decode'))
{
	require_once MYBB_ROOT.'inc/3rdparty/json/json.php';
}

require_once MYBB_ROOT."inc/functions.php";

require_once MYBB_ROOT."inc/class_timers.php";
$maintimer = new timer();

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

$not_installed = false;
if(!file_exists(MYBB_ROOT."inc/config.php"))
{
	$not_installed = true;
}
else
{
	// Include the required core files
	require_once MYBB_ROOT."inc/config.php";
	$mybb->config = &$config;

	if(!isset($config['database']))
	{
		$not_installed = true;
	}
}

if($not_installed !== false)
{
	if(file_exists(MYBB_ROOT."install/index.php"))
	{
		if(defined("IN_ARCHIVE") || defined("IN_ADMINCP"))
		{
			header("Location: ../install/index.php");
			exit;
		}
		header("Location: ./install/index.php");
		exit;
	}

	$mybb->trigger_generic_error("board_not_installed");
}

if(!is_array($config['database']))
{
	$mybb->trigger_generic_error("board_not_upgraded");
}

if(empty($config['admin_dir']))
{
	$config['admin_dir'] = "admin";
}

// Trigger an error if the installation directory exists
if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
	$mybb->trigger_generic_error("install_directory");
}

// Load DB interface
require_once MYBB_ROOT."inc/db_base.php";

require_once MYBB_ROOT."inc/db_".$config['database']['type'].".php";

switch($config['database']['type'])
{
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	default:
		$db = new DB_MySQL;
}

// Check if our DB engine is loaded
if(!extension_loaded($db->engine))
{
	// Throw our super awesome db loading error
	$mybb->trigger_generic_error("sql_load_error");
}

require_once MYBB_ROOT."inc/class_templates.php";
$templates = new templates;

require_once MYBB_ROOT."inc/class_datacache.php";
$cache = new datacache;

require_once MYBB_ROOT."inc/class_plugins.php";
$plugins = new pluginSystem;

// Include our base data handler class
require_once MYBB_ROOT."inc/datahandler.php";

// Connect to Database
define("TABLE_PREFIX", $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

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

if(!file_exists(MYBB_ROOT."inc/settings.php") || empty($settings))
{
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);

		$query = $db->simple_select("settings", "value, name", "", $options);

		$settings = array();
		while($setting = $db->fetch_array($query))
		{
			$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
			$settings[$setting['name']] = $setting['value'];
		}
		$db->free_result($query);
	}
}

$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$settings['bbname_orig'] = $settings['bbname'];
$settings['bbname'] = strip_tags($settings['bbname']);
$settings['orig_bblanguage'] = $settings['bblanguage'];

// Fix for people who for some specify a trailing slash on the board URL
if(substr($settings['bburl'], -1) == "/")
{
	$settings['bburl'] = my_substr($settings['bburl'], 0, -1);
}

// Setup our internal settings and load our encryption key
$settings['internal'] = $cache->read("internal_settings");
if(!$settings['internal']['encryption_key'])
{
	$cache->update("internal_settings", array('encryption_key' => random_str(32)));
	$settings['internal'] = $cache->read("internal_settings");
}

$mybb->settings = &$settings;
$mybb->parse_cookies();
$mybb->cache = &$cache;
$mybb->asset_url = $mybb->get_asset_url();

if($mybb->use_shutdown == true)
{
	register_shutdown_function('run_shutdown');
}

// Did we just upgrade to a new version and haven't run the upgrade scripts yet?
$version = $cache->read("version");
if(!defined("IN_INSTALL") && !defined("IN_UPGRADE") && $version['version_code'] < $mybb->version_code)
{
	$version_history = $cache->read("version_history");
	if(empty($version_history) || file_exists(MYBB_ROOT."install/resources/upgrade".(int)(end($version_history)+1).".php"))
	{
		$mybb->trigger_generic_error("board_not_upgraded");
	}
}

// Load plugins
if(!defined("NO_PLUGINS") && !($mybb->settings['no_plugins'] == 1))
{
	$plugins->load();
}

// Set up any shutdown functions we need to run globally
add_shutdown('send_mail_queue');

/* URL Definitions */
if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && isset($_SERVER['SEO_SUPPORT']) && $_SERVER['SEO_SUPPORT'] == 1))
{
	$mybb->seo_support = true;

	define('FORUM_URL', "forum-{fid}.html");
	define('FORUM_URL_PAGED', "forum-{fid}-page-{page}.html");
	define('THREAD_URL', "thread-{tid}.html");
	define('THREAD_URL_PAGED', "thread-{tid}-page-{page}.html");
	define('THREAD_URL_ACTION', 'thread-{tid}-{action}.html');
	define('THREAD_URL_POST', 'thread-{tid}-post-{pid}.html');
	define('POST_URL', "post-{pid}.html");
	define('PROFILE_URL', "user-{uid}.html");
	define('ANNOUNCEMENT_URL', "announcement-{aid}.html");
	define('CALENDAR_URL', "calendar-{calendar}.html");
	define('CALENDAR_URL_MONTH', 'calendar-{calendar}-year-{year}-month-{month}.html');
	define('CALENDAR_URL_DAY', 'calendar-{calendar}-year-{year}-month-{month}-day-{day}.html');
	define('CALENDAR_URL_WEEK', 'calendar-{calendar}-week-{week}.html');
	define('EVENT_URL', "event-{eid}.html");
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
	define('CALENDAR_URL', "calendar.php?calendar={calendar}");
	define('CALENDAR_URL_MONTH', "calendar.php?calendar={calendar}&year={year}&month={month}");
	define('CALENDAR_URL_DAY', 'calendar.php?action=dayview&calendar={calendar}&year={year}&month={month}&day={day}');
	define('CALENDAR_URL_WEEK', 'calendar.php?action=weekview&calendar={calendar}&week={week}');
	define('EVENT_URL', "calendar.php?action=event&eid={eid}");
}
define('INDEX_URL', "index.php");

// An array of valid date formats (Used for user selections etc)
$date_formats = array(
	1 => "m-d-Y",
	2 => "m-d-y",
	3 => "m.d.Y",
	4 => "m.d.y",
	5 => "d-m-Y",
	6 => "d-m-y",
	7 => "d.m.Y",
	8 => "d.m.y",
	9 => "F jS, Y",
	10 => "l, F jS, Y",
	11 => "jS F, Y",
	12 => "l, jS F, Y",
	// ISO 8601
	13 => "Y-m-d"
);

// An array of valid time formats (Used for user selections etc)
$time_formats = array(
	1 => "h:i a",
	2 => "h:i A",
	3 => "H:i"
);

