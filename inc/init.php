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
define("NO_SHUTDOWN", false);

require "./inc/class_timers.php";
$maintimer = new timer();

require "./inc/class_core.php";
$mybb = new MyBB;

		if(!defined("KILL_GLOBALS"))
		{
			@extract($_POST, EXTR_OVERWRITE);
			@extract($_GET, EXTR_OVERWRITE);
		}

/*
// start our main timer! :)
require "./inc/class_timers.php";

$maintimer = new timer();

unset($templatecache);
$templatecache = array();

// This cheap little trick was found over at php.net
if(PHP_VERSION < "4.1.0")
{
	$_COOKIE = $HTTP_COOKIE_VARS;
	$_GET = $HTTP_GET_VARS;
	$_POST = $HTTP_POST_VARS;
	$_SERVER = $HTTP_SERVER_VARS;
	$_FILES = $HTTP_POST_FILES;
}

// Magic quotes, the sum of all evil
if(get_magic_quotes_gpc())
{
	stripslashesarray($_POST);
	stripslashesarray($_GET);
	stripslashesarray($_COOKIE);
}

function stripslashesarray(&$array)
{
	while(list($key, $val) = each($array))
	{
		if(is_array($array[$key]))
		{
			stripslashesarray($array[$key]);
		}
		else
		{
			$array[$key] = stripslashes($array[$key]);
		}
	}
}

// Disable magic quotes
@set_magic_quotes_runtime(0);
@ini_set("magic_quotes_gpc", 0);
@ini_set("magic_quotes_runtime", 0); 


// Fix register_globals
@extract($_POST, EXTR_OVERWRITE);
@extract($_FILES, EXTR_OVERWRITE);
@extract($_GET, EXTR_OVERWRITE);
@extract($_ENV, EXTR_OVERWRITE);
@extract($_COOKIE, EXTR_OVERWRITE);
@extract($_SERVER, EXTR_OVERWRITE);

*/

// Include the required core files
require "./inc/config.php";

require "./inc/db_".$config['dbtype'].".php";
$db = new bbDB;

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

// Load Settings
require "./inc/settings.php";
$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$mybb->settings = $settings;

// Language initialisation
require "./inc/class_language.php";
$lang = new MyLanguage;
$lang->setPath("./inc/languages");

// Load cache
$cache->cache();

// Load plugins
$plugins->load();

if(!NO_SHUTDOWN)
{
	register_shutdown_function("run_shutdown");
}

$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
// These are fields in the usergroups table that are also forum permission specific
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");
?>