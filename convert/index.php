<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 * NOT TO BE DISTRIBUTED WITH THE MYBB PACKAGE
 * INCLUDED FOR TESTING PURPOSES ONLY
 */
 
error_reporting(E_ALL & ~E_NOTICE); 
set_time_limit(0);

// Load core files
define("MYBB_ROOT", dirname(dirname(__FILE__)).'/');
define("CONVERT_ROOT", dirname(__FILE__).'/');

require_once MYBB_ROOT."inc/config.php";
if(!isset($config['dbtype']))
{
	die('MyBB needs to be installed before you can convert.');
}

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

require_once MYBB_ROOT."inc/class_error.php";
$error_handler = new errorHandler();

// Include the files necessary for converting
require_once MYBB_ROOT."inc/class_timers.php";
$timer = new timer;

require_once MYBB_ROOT.'inc/class_datacache.php';
$cache = new datacache;
	
require_once MYBB_ROOT."inc/functions.php";

require_once MYBB_ROOT."inc/class_xml.php";

// Include the converter resources
require_once CONVERT_ROOT."resources/functions.php";
require_once CONVERT_ROOT.'resources/output.php';
$output = new converterOutput;
require_once CONVERT_ROOT.'resources/class_converter.php';

require_once MYBB_ROOT."inc/db_".$config['dbtype'].".php";
$db = new databaseEngine;

// Connect to the installed MyBB database
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);
$db->set_table_prefix($config['table_prefix']);
define('TABLE_PREFIX', $config['table_prefix']);

// REMOVE BEFORE RELEASE
// Temporary code to clear importcache
if(isset($mybb->input['restart']))
{
	//@unlink(CONVERT_ROOT.'lock');
	$db->delete_query("datacache", "title='import_cache'", 1);
	delete_import_fields();
}

// Get the import session cache if exists
$import_session = $cache->read("import_cache", 1);

if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

if(!$import_session['disabled'])
{
	$import_session['disabled'] = array();
}

if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

// TEMPORARY
if($mybb->input['debug'])
{
	echo "<pre>";
	print_r($import_session);
	echo "</pre>";
}

if($mybb->input['board'])
{
	$mybb->input['board'] = str_replace(".", "", $mybb->input['board']);
	if(!file_exists(CONVERT_ROOT."boards/".$mybb->input['board'].".php"))
	{
		$output->print_error("The board module you have selected does not exist.");
	}
	$import_session['board'] = $mybb->input['board'];
}

if($mybb->input['module'])
{
	$resume_module = $import_session['module'];
	$import_session['module'] = $mybb->input['module'];
}

// If no board is selected then we show the main page where users can select a board
if(!$import_session['board'])
{
	$output->board_list();
}
// Perhaps we have selected to stop converting
elseif(isset($mybb->input['action']) && $mybb->input['action'] == 'finish')
{
	// Delete import fields
	delete_import_fields();
	$cache->update_stats();
	$cache->update_badwords();
	$cache->update_usergroups();
	$cache->update_forumpermissions();
	$cache->update_moderators();
	$cache->update_forums();
	$cache->update_usertitles();
	
	// Delete import session cache
	$import_session = null;
	
	$output->finish_conversion();
}
// Otherwise that means we've selected a module to run or we're in one
elseif($import_session['module'] && $mybb->input['action'] != 'module_list')
{ 
	// Get the converter up.
	require_once CONVERT_ROOT."boards/".$import_session['board'].".php";
	$classname = "convert_".$import_session['board'];
	$board = new $classname;

	// We've selected a module (or we're in one) that is valid
	if($board->modules[$import_session['module']])
	{		
		$function = $import_session['module'];
	}
	// Otherwise we're trying to use an invalid module or we're still at the beginning
	else
	{
		if($resume_module)
		{
			$import_session['resume_module'][] = $resume_module;
		}
		$import_session['module'] = '';
		
		update_import_session();
		header("Location: index.php");
		exit;
	}

	// Run the module
	$result = $board->$function();


	// If the module returns "finished" then it has finished everything it needs to do. We set the import session
	// to blank so we go back to the module list
	if($result == "finished")
	{		
		$key = array_search($import_session['module'], $import_session['resume_module']);
		if(isset($key))
		{
			unset($import_session['resume_module'][$key]);
		}
		
		$import_session['completed'][] = $import_session['module'];
		$import_session['module'] = '';
		update_import_session();
		header("Location: index.php");
		exit;
	}
}
// Otherwise we've selected a board but we're not in any module so we show the module selection list
else
{
	// Get the converter up.
	require_once CONVERT_ROOT."boards/".$import_session['board'].".php";
	$classname = "convert_".$import_session['board'];
	$board = new $classname;
	
	$output->module_list();
}
?>