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

/**
 * Convert an integer 1/0 into text yes/no
 * @param int Integer to be converted
 * @return string Correspondig yes or no
 */
function int_to_yesno($var)
{
	$var = intval($var);
	
	if($var == 1)
	{
		return 'yes';
	}
	else
	{
		return 'no';
	}
}

/**
 * Update the import session cache
 */
function update_session()
{
	global $session, $db;
	
	$session = $db->escape_string(serialize($session));
	$query = $db->simple_select("datacache", "*", "title='importcache'");
	$sess = $db->fetch_array($query);
	
	if(!$sess['cache'])
	{
		$insertarray = array(
			'title' => 'importcache',
			'cache' => $session,
		);
		$db->insert_query("datacache", $insertarray);
	} 
	else
	{
		$db->update_query("datacache", array('cache' => $session), "title='importcache'");
	}
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
		if($key == intval($current_module))
		{
			$return .= "<li class=\"active\">{$module['name']}</li>";
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