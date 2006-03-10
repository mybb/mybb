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

error_reporting(E_ALL & ~E_NOTICE);


//
// MYBB 1.2 DEVELOPMENT CODE - TO BE REMOVED BEFORE RELEASE
//
if($_SERVER['SERVER_NAME'] == "roadrunner")
{
	error_reporting(E_ALL);

	function my_devel_error_handler($errno, $errstr, $errfile, $errline)
	{
		if(strstr($errstr, "MyLanguage::") !== false || strstr($errstr, "public/private/protected") !== false)
		{
			return;
		}
		echo "Error: [$errno] $errstr (<b>$errfile</b> on line <b>$errline</b>)<br />\n";
	}
	set_error_handler("my_devel_error_handler");
}
//
// END MYBB 1.2 DEVELOPMENT CODE
//

define("NO_SHUTDOWN", false);

require "./inc/class_timers.php";
$maintimer = new timer();

require "./inc/class_core.php";
$mybb = new MyBB;

// Include the required core files
require "./inc/config.php";
if(!isset($config['dbtype']))
{
	$mybb->trigger_generic_error("board_not_installed");
}
if(!isset($config['admindir']))
{
	$config['admindir'] = "admin";
}
$mybb->config = $config;

require "./inc/db_".$config['dbtype'].".php";
$db = new databaseEngine;

require "./inc/functions.php";

require "./inc/class_templates.php";
$templates = new templates;

require "./inc/class_datacache.php";
$cache = new datacache;

require "./inc/class_plugins.php";
$plugins = new pluginSystem;

require "./inc/integration.php";

// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);

// Language initialisation
require "./inc/class_language.php";
$lang = new MyLanguage;
$lang->setPath("./inc/languages");

// Load cache
$cache->cache();

// Load Settings
require "./inc/settings.php";
$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$mybb->settings = $settings;


// Load plugins
if(!defined("NO_PLUGINS"))
{
	$plugins->load();
}

if(!NO_SHUTDOWN)
{
	register_shutdown_function("run_shutdown");
}

$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
// These are fields in the usergroups table that are also forum permission specific
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

// Generate a random number for performing random actions.
$rand = rand(0, 10);

/* URL Definitions */
define('PROFILE_URL', "member.php?action=profile&amp;uid={uid}");
define('FORUM_URL', "forumdisplay.php?fid={fid}");
define('THREAD_URL', "showthread.php?tid={tid}");
define('INDEX_URL', "index.php");
?>