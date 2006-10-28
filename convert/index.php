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


// Load core files
define("MYBB_ROOT", dirname(dirname(__FILE__)));
define("CONVERT_ROOT", dirname(__FILE__));

require_once MYBB_ROOT."/inc/config.php";
if(!isset($config['dbtype']))
{
	die('MyBB needs to be installed before you can convert.');
}

require_once MYBB_ROOT."/inc/class_core.php";
$mybb = new MyBB;

// Include the files necessary for converting
require_once MYBB_ROOT."/inc/class_timers.php";
$timer = new timer;

require_once MYBB_ROOT.'/inc/class_datacache.php';
$cache = new datacache;
	
require_once MYBB_ROOT."/inc/functions.php";
require_once MYBB_ROOT."/inc/class_xml.php";
$db = db_connection($config);

require_once MYBB_ROOT.'/inc/class_language.php';
$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'/convert/resources/');
$lang->load('language');

require_once MYBB_ROOT."/inc/class_error.php";
$error_handler = new errorHandler();

// Include the converter resources
require_once CONVERT_ROOT."/resources/functions.php";
require_once CONVERT_ROOT.'/resources/output.php';
$output = new converterOutput;
require_once CONVERT_ROOT.'/resources/class_converter.php';

// Include the necessary constants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

define('TABLE_PREFIX', $config['table_prefix']);

// Set up db engine list
$dboptions = array();
if(function_exists('mysqli_connect'))
{
	$dboptions['mysqli'] = array(
		'title' => 'MySQL Improved',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('mysql_connect'))
{
	$dboptions['mysql'] = array(
		'title' => 'MySQL',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

// REMOVE BEFORE RELEASE
// Temporary code to clear importcache
if(isset($mybb->input['restart']))
{
	$db->delete_query("datacache", "title='importcache'", 1);
}

// Get the import session cache if exists
$query = $db->simple_select("datacache", "*", "title='importcache'");
if($query)
{
	$session = unserialize($db->fetch_field($query, 'cache'));
}

// Set various session variables
if(isset($mybb->input['dbboard']))
{
	$session['board'] = $mybb->input['dbboard'];
}

if(isset($mybb->input['module']))
{
	$session['module'] = $mybb->input['module'];
}

if(isset($mybb->input['board']))
{
	$session['board'] = $mybb->input['board'];
}


if(file_exists('lock')) // Check if converter is locked
{
	$output->print_error($lang->locked);
}
elseif(!$session['board']) // Introductory steps & final step
{
	$output->steps = get_converter_steps();
	
	if(!isset($mybb->input['action']))
	{
		$mybb->input['action'] = 'intro';
	}
	
	switch($mybb->input['action'])
	{
		case 'license':
			license_agreement();
			break;
		case 'database_info':
			database_info();
			break;
		case 'final':
			install_done();
			break;
		default:
			intro();
			break;
	}
}
elseif(isset($mybb->input['module']) || $session['module']) // In module
{
	if(!$session['module'] && $mybb->input['module'])
	{
		$session['module'] = $mybb->input['module'];
	}
	
	// Check converter exists
	if(!file_exists(CONVERT_ROOT."/boards/".$session['board'].".php"))
	{
		$output->print_error("Umm.. Invalid board love!");
	}
	
	// Attempt to connect to the db specified
	$olddb = db_connection($session);
	
	// Make tables happy?
	data_conversion();
	
	// Get the converter up.
	require_once CONVERT_ROOT."/boards/".$session['board'].".php";
	$classname = "Convert_".$session['board'];
	$board = new $classname;
	
	// Make menu...
	$output->steps = get_converter_steps();
	
	$mybb->input['action'] = 'data_conversion';
	


	if(isset($board->modules[$session['module']])) // Yeah rightio, we're now in a module
	{		
		$function = $board->modules[$session['module']]['function'];
		$module_name = $board->modules[$session['module']]['name'];
	}
	else // Just beginning, or an invalid module (who broke it?!)
	{
		$session['module'] = 0;
		$function = $board->modules[0]['function'];
		$module_name = $board->modules[0]['name'];
	}

	$output->print_header($module_name, 'data_conversion');
	
	$board->$function();
	
	// Continue to next module if one exists
	if($board->modules[$session['module']+1])
	{
		++$session['module'];
		$output->print_footer($session['module'], 'module', 1);
	}
	else
	{
		// Destroy temp cache
		$db->delete_query("datacache", "title='importcache'", 1);
		$output->print_footer('final');
	}
}
else // Start data conversion
{
	$output->steps = get_converter_steps();
	
	if($mybb->input['board'])
	{
		$session['board'] = $mybb->input['board'];
	}
	
	$mybb->input['action'] = 'data_conversion';
	
	// Validate database information
	if(!file_exists(MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php"))
	{
		$errors[] = $lang->db_step_error_invalidengine;
		database_info($errors);
	}
	
	// Attempt to connect to the db specified
	require_once MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php"; // FIXME: redeclaring class with same name
	$olddb = new databaseEngine;
 	$olddb->error_reporting = 0;

	// Check that connection to db being converted exists
	$connection = $olddb->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass']);
	if(!$connection)
	{
		$errors[] = sprintf($lang->db_step_error_noconnect, $mybb->input['dbhost']);
	}

	// Select the database
	$dbselect = $olddb->select_db($mybb->input['dbname']);
	if(!$dbselect)
	{
		$errors[] = sprintf($lang->db_step_error_nodbname, $mybb->input['dbname']);
	}
	
	$olddb->set_table_prefix($mybb->input['tableprefix']);
	
	// Remember the old database info
	$session['table_prefix'] = $mybb->input['tableprefix'];
	$session['database'] = $mybb->input['dbname'];
	$session['hostname'] = $mybb->input['dbhost'];
	$session['username'] = $mybb->input['dbuser'];
	$session['password'] = $mybb->input['dbpass'];
	$session['dbtype'] = $mybb->input['dbengine'];

	// Send back for error fixing
	if(is_array($errors))
	{
		database_info($errors);
	}
	
	// Make converter stuff happy
	data_conversion();
	
	// Make the converter
	require_once CONVERT_ROOT."/boards/".$session['board'].".php";
	$classname = "Convert_".$session['board'];
	$board = new $classname;
	//$output->module_list();
	
	$output->steps = get_converter_steps();
	$output->print_header($board->modules[0]['name'], 'data_conversion');
	
	// Start the first one
	$session['module'] = 0;
	$function = $board->modules[0]['function'];
	$board->$function();
}

/**
 * Step 1: Introduction
 */
function intro()
{
	global $output, $mybb, $lang;
	
	$output->print_header($lang->welcome, 'welcome');
	
	echo sprintf($lang->welcome_step, $mybb->version);
	
	$output->print_footer('license');
}

/**
 * Step 2: License Agreement
 */
function license_agreement()
{
	global $output, $lang;
	
	$output->print_header($lang->license_agreement, 'license');
	
	$license = '<h3>Important - Read Carefully</h3>
<p>This MyBB End-User License Agreement ("EULA") is a legal agreement between you (either an individual or a single entity) and the MyBB Group for the MyBB product, which includes computer software and may include associated media, printed materials, and "online" or electronic documentation. By installing, copying, or otherwise using the MyBB product, you agree to be bound by the terms of this EULA. If you do not agree to the terms of this EULA, do not install or use the MyBB product and destroy any copies of the application.</p>
<p>The MyBB Group may alter or modify this license agreement without notification and any changes made to the EULA will affect all past and current copies of MyBB</p>

<h4>MyBB is FREE software</h4>
<p>MyBB is distributed as "FREE" software granting you the right to download MyBB for FREE and installing a working physical copy at no extra charge.</p>
<p>You may charge a fee for the physical act of transferring a copy.</p>

<h4>Reproduction and Distribution</h4>
<p>You may produce re-distributable copies of MyBB as long as the following terms are met:</p>
<ul>
	<li>You may not remove, alter or otherwise attempt to hide the MyBB copyright notice in any of the files within the original MyBB package.</li>
	<li>Any additional files you add must not bare the copyright of the MyBB Group.</li>
	<li>You agree that no support will be given to those who use the distributed modified copies.</li>
	<li>The modified and re-distributed copies of MyBB must also be distributed with this exact license and licensed as FREE software. You may not charge for the software or distribution of the software.</li>
</ul>

<h4>Separation of Components</h4>
<p>The MyBB software is licensed as a single product. Components, parts or any code may not be separated from the original MyBB package for either personal use or inclusion in other applications.</p>

<h4>Termination</h4>
<p>Without prejudice to any other rights, the MyBB Group may terminate this EULA if you fail to comply with the terms and conditions of this EULA. In such event, you must destroy all copies of the MyBB software and all of its component parts. The MyBB Group also reserve the right to revoke redistribution rights of MyBB from any corporation or entity for any specified reason.</p>

<h4>Copyright</h4>
<p>All title and copyrights in and to the MyBB software (including but not limited to any images, text, javascript and code incorporated in to the MyBB software), the accompanying materials and any copies of the MyBB software are owned by the MyBB Group.</p>
<p>MyBB is protected by copyright laws and international treaty provisions. Therefore, you must treat MyBB like any other copyrighted material.</p>
<p>The MyBB Group has several copyright notices and "powered by" lines embedded within the product. You must not remove, alter or hinder the visibility of any of these statements (including but not limited to the copyright notice at the top of files and the copyright/powered by lines found in publicly visible "templates").</p>

<h4>Product Warranty and Liability for Damages</h4>
<p>The MyBB Group expressly disclaims any warranty for MyBB. The MyBB software and any related documentation is provided "as is" without warranty of any kind, either express or implied, including, without limitation, the implied warranties or merchant-ability, fitness for a particular purpose, or non-infringement. The entire risk arising out of use or performance of MyBB remains with you.</p>
<p>In no event shall the MyBB Group be liable for any damages whatsoever (including, without limitation, damages for loss of business profits, business interruption, loss of business information, or any other pecuniary loss) arising out of the use of or inability to use this product, even if the MyBB Group has been advised of the possibility of such damages. Because some states/jurisdictions do not allow the exclusion or limitation of liability for consequential or incidental damages, the above limitation may not apply to you.</p>';
	
	echo sprintf($lang->license_step, $license);
	
	$output->print_footer('database_info');
}

/**
 * Step 3: Enter the conversion database information
 */
function database_info($errors='')
{
	global $output, $dbinfo, $mybb, $dboptions, $lang;
	
	$mybb->input['action'] = 'database_info';
	$output->print_header($lang->db_config, 'dbconfig');

	// Check for errors from this stage
	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo sprintf($lang->db_step_error_config, $error_list);
		$dbhost = $mybb->input['dbhost'];
		$dbuser = $mybb->input['dbuser'];
		$dbname = $mybb->input['dbname'];
		$tableprefix = $mybb->input['tableprefix'];
	}
	else
	{
		echo $lang->db_step_config_db;
		$dbhost = 'localhost';
		$tableprefix = '';
		$dbuser = '';
		$dbname = '';
	}
	$boards = $output->print_boards();

	// Loop through database engines
	foreach($dboptions as $dbfile => $dbtype)
	{
		$dbengines .= "<option value=\"{$dbfile}\">{$dbtype['title']}</option>";
	}

	echo sprintf($lang->db_step_config_table, $boards, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix);
	$output->print_footer('data_conversion', "", 1);
}

/**
 * Step 4: Set up for data conversion
 */
function data_conversion()
{
	global $mybb, $lang, $output, $db;
	
	if(!$db->field_exists('importuid', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD importuid int NOT NULL default '0' AFTER uid");
	}
	
	if(!$db->field_exists('importfid', "forums"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forums ADD importfid int NOT NULL default '0' AFTER fid");
	}
	
	if(!$db->field_exists('importtid', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD importtid int NOT NULL default '0' AFTER tid");
	}
	
	if(!$db->field_exists('importpid', "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts ADD importpid int NOT NULL default '0' AFTER pid");
	}
	
	if(!$db->field_exists('importaid', "attachments"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachments ADD importaid int NOT NULL default '0' AFTER aid");
	}
	
	if(!$db->field_exists('importgid', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD importgid int NOT NULL default '0' AFTER gid");
	}
}

/**
 * Step 5: Finish conversion
 */
function install_done()
{
	global $output, $db, $mybb, $errors, $cache, $lang, $config;
	
	if($db->field_exists('importuid', "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP importuid");
	}
	
	if($db->field_exists('importfid', "forums"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forums DROP importfid");
	}
	
	if($db->field_exists('importtid', "threads"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."threads DROP importtid");
	}
	
	if($db->field_exists('importpid', "posts"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."posts DROP importpid");
	}
	
	if($db->field_exists('importaid', "attachments"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."attachments DROP importaid");
	}
	
	if($db->field_exists('importgid', "usergroups"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP importgid");
	}

	require_once MYBB_ROOT.'/inc/config.php';
	$db = db_connection($config);
	
	require_once MYBB_ROOT.'/inc/settings.php';
	$mybb->settings = &$settings;

	ob_start();
	$output->print_header($lang->finish_setup, 'finish');

	// Automatic Login
	my_setcookie('mybbuser', $uid.'_'.$loginkey, null, true);
	ob_end_flush();

	echo $lang->done_step_cachebuilding;
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
	//$cache->updateupdate_check();
	echo $lang->done.'</p>';

	echo $lang->done_step_success;

	$written = 0;
	if(is_writable('./'))
	{
		$lock = @fopen('./lock', 'w');
		$written = @fwrite($lock, '1');
		@fclose($lock);
		if($written)
		{
			echo $lang->done_step_locked;
		}
	}
	
	if(!$written)
	{
		echo $lang->done_step_dirdelete;
	}
	echo $lang->done_subscribe_mailing;
	$output->print_footer('');
}

/**
 * Make a database connection
 * @param array Database configuration
 * @return databaseEngine Database Engine
 */
function db_connection($config)
{
	require_once MYBB_ROOT."/inc/db_{$config['dbtype']}.php";
	$db = new databaseEngine;
	
	// Connect to Database
	define('TABLE_PREFIX', $config['table_prefix']);
	$db->connect($config['hostname'], $config['username'], $config['password']);
	$db->select_db($config['database']);
	$db->set_table_prefix($config['table_prefix']);
	return $db;
}

/**
 * Return a formatted list of errors
 * 
 * @param array Errors
 * @return string Formatted errors list
 */
function error_list($array)
{
	$string = "<ul>\n";
	foreach($array as $error)
	{
		$string .= "<li>{$error}</li>\n";
	}
	$string .= "</ul>\n";
	return $string;
}

/**
 * Create the converter modules menu
 * 
 * @param array Module info array
 * @param int Current module ID
 * @return string Module nav bar list
 */
function make_data_conversion_menu($modules, $current_module=0)
{
	
	$return = '';
	foreach($modules as $key => $module)
	{
		if($key == $current_module)
		{
			$return .= "<li><strong>{$module['name']}</strong></li>";
		}
		else
		{
			$return .= "<li>{$module['name']}</li>";
		}
	}
	
	return $return;
}

/**
 * Return an array of the steps for conversion
 * 
 * @return array Conversion steps
 */
function get_converter_steps()
{
	global $lang, $board, $session;
	
	if(isset($board->modules))
	{
		$lang->data_conversion_old = $lang->data_conversion;
		$lang->data_conversion .= '</strong><ul>';
		$lang->data_conversion .= make_data_conversion_menu($board->modules, $session['module']);
		$lang->data_conversion .= '</ul><strong>';
	}
	
	return array(
		'intro' => $lang->welcome,
		'license' => $lang->license_agreement,
		'database_info' => $lang->db_config,
		'data_conversion' => $lang->data_conversion,
		'final' => $lang->finish_setup,
	);
}
?>