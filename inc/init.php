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

if(strpos(strtolower($_SERVER['PHP_SELF']), "inc/init.php") !== false)
{
	die("Direct initialization of this file is not allowed.");
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

//
// MYBB 1.2 DEVELOPMENT CODE - TO BE REMOVED BEFORE RELEASE
//
if(isset($_COOKIE['phpdbug']) || isset($_GET['phpdebug']))
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

require MYBB_ROOT."inc/class_timers.php";
$maintimer = new timer();

require MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

// Include the required core files
require MYBB_ROOT."inc/config.php";
if(!isset($config['dbtype']))
{
	$mybb->trigger_generic_error("board_not_installed");
}
if(!isset($config['admin_dir']))
{
	$config['admin_dir'] = "admin";
}
$mybb->config = $config;

require MYBB_ROOT."inc/db_".$config['dbtype'].".php";
$db = new databaseEngine;

require MYBB_ROOT."inc/functions.php";

require MYBB_ROOT."inc/class_templates.php";
$templates = new templates;

require MYBB_ROOT."inc/class_datacache.php";
$cache = new datacache;

require MYBB_ROOT."inc/class_plugins.php";
$plugins = new pluginSystem;

// Include our base data handler class
require MYBB_ROOT."inc/datahandler.php";

// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);

// Language initialisation
require MYBB_ROOT."inc/class_language.php";
$lang = new MyLanguage;
$lang->set_path(MYBB_ROOT."inc/languages");

// Load cache
$cache->cache();

// Load Settings
require MYBB_ROOT."inc/settings.php";
$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$mybb->settings = $settings;


// Load plugins
if(!defined("NO_PLUGINS"))
{
	$plugins->load();
}

// Set up any shutdown functions we need to run globally
add_shutdown('send_mail_queue');

$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");

// These are fields in the usergroups table that are also forum permission specific.
$fpermfields = array(
	'canview',
	'canviewthreads',
	'candlattachments',
	'canpostthreads',
	'canpostreplys',
	'canpostattachments',
	'canratethreads',
	'caneditposts',
	'candeleteposts',
	'candeletethreads',
	'caneditattachments',
	'canpostpolls',
	'canvotepolls',
	'cansearch'
);

// Generate a random number for performing random actions.
$rand = rand(0, 10);

/* URL Definitions */
define('PROFILE_URL', "member.php?action=profile&amp;uid={uid}");
define('FORUM_URL', "forumdisplay.php?fid={fid}");
define('FORUM_URL_PAGED', "forumdisplay.php?fid={fid}&page={page}");
define('THREAD_URL', "showthread.php?tid={tid}");
define('THREAD_URL_PAGED', "showthread.php?tid={tid}&page={page}");
define('INDEX_URL', "index.php");
?>