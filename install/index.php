<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

@error_reporting(E_ALL & ~E_NOTICE);

@set_time_limit(0);

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__)."/");
define("TIME_NOW", time());
define("IN_MYBB", 1);

require_once MYBB_ROOT.'inc/class_core.php';
$mybb = new MyBB;

require_once MYBB_ROOT.'inc/class_error.php';
$error_handler = new errorHandler();

// Include the files necessary for installation
require_once MYBB_ROOT.'inc/class_timers.php';
require_once MYBB_ROOT.'inc/functions.php';

$admin_dir = "admin";

// Perform a check if MyBB is already installed or not
$installed = false;
if(file_exists(MYBB_ROOT."/inc/config.php") && file_exists(MYBB_ROOT."/inc/settings.php"))
{
	require MYBB_ROOT."/inc/config.php";
	require MYBB_ROOT."/inc/settings.php";
	if(is_array($config) && is_array($settings))
	{
		$installed = true;
		if(isset($config['admindir']))
		{
			$admin_dir = $config['admindir'];
		}
		else if(isset($config['admin_dir']))
		{
			$admin_dir = $config['admin_dir'];
		}
	}
}

require_once MYBB_ROOT.'inc/class_xml.php';
require_once MYBB_ROOT.'inc/functions_user.php';
require_once MYBB_ROOT.'inc/class_language.php';
$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources');
$lang->load('language');

// Prevent any shut down functions from running
$done_shutdown = 1;

// Include the necessary contants for installation
$grouppermignore = array('gid', 'type', 'title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$groupzerogreater = array('pmquota', 'maxreputationsday', 'attachquota');
$displaygroupfields = array('title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$fpermfields = array('canview', 'candlattachments', 'canpostthreads', 'canpostreplys', 'canpostattachments', 'canratethreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canpostpolls', 'canvotepolls', 'cansearch');

// Include the installation resources
require_once INSTALL_ROOT.'resources/output.php';
$output = new installerOutput;

$dboptions = array();

if(function_exists('mysqli_connect'))
{
	$dboptions['mysqli'] = array(
		'class' => 'DB_MySQLi',
		'title' => 'MySQL Improved',
		'short_title' => 'MySQLi',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('mysql_connect'))
{
	$dboptions['mysql'] = array(
		'class' => 'DB_MySQL',
		'title' => 'MySQL',
		'short_title' => 'MySQL',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('sqlite_open'))
{
	$dboptions['sqlite2'] = array(
		'class' => 'DB_SQLite',
		'title' => 'SQLite 2',
		'short_title' => 'SQLite',
		'structure_file' => 'sqlite_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('pg_connect'))
{
	$dboptions['pgsql'] = array(
		'class' => 'DB_PgSQL',
		'title' => 'PostgreSQL',
		'short_title' => 'PostgreSQL',
		'structure_file' => 'pgsql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(class_exists('PDO'))
{
	$supported_dbs = PDO::getAvailableDrivers();
	if(in_array('sqlite', $supported_dbs))
	{
		$dboptions['sqlite3'] = array(
			'class' => 'DB_SQLite3',
			'title' => 'SQLite 3',
			'short_title' => 'SQLite',
			'structure_file' => 'sqlite_db_tables.php',
			'population_file' => 'mysql_db_inserts.php'
		);
	}
}

if(file_exists('lock'))
{
	$output->print_error($lang->locked);
}
else if($installed == true && !$mybb->input['action'])
{
	$output->print_header($lang->already_installed, "errormsg", 0);
	echo $lang->sprintf($lang->mybb_already_installed, $mybb->version);
	$output->print_footer();
}
else
{
	$output->steps = array(
		'intro' => $lang->welcome,
		'license' => $lang->license_agreement,
		'requirements_check' => $lang->req_check,
		'database_info' => $lang->db_config,
		'create_tables' => $lang->table_creation,
		'populate_tables' => $lang->data_insertion,
		'templates' => $lang->theme_install,
		'configuration' => $lang->board_config,
		'adminuser' => $lang->admin_user,
		'final' => $lang->finish_setup,
	);
	
	if(!isset($mybb->input['action']))
	{
		$mybb->input['action'] = 'intro';
	}
	
	switch($mybb->input['action'])
	{
		case 'license':
			license_agreement();
			break;
		case 'requirements_check':
			requirements_check();
			break;
		case 'database_info':
			database_info();
			break;
		case 'create_tables':
			create_tables();
			break;
		case 'populate_tables':
			populate_tables();
			break;
		case 'templates':
			insert_templates();
			break;
		case 'configuration':
			configure();
			break;
		case 'adminuser';
			create_admin_user();
			break;
		case 'final':
			install_done();
			break;
		default:
			intro();
			break;
	}
}

function intro()
{
	global $output, $mybb, $lang;
	
	$output->print_header($lang->welcome, 'welcome');
	if(strpos(strtolower($_SERVER['PHP_SELF']), "upload/") !== false)
	{
		echo $lang->sprintf($lang->mybb_incorrect_folder);
	}
	echo $lang->sprintf($lang->welcome_step, $mybb->version);
	$output->print_footer('license');
}

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
	echo $lang->sprintf($lang->license_step, $license);
	$output->print_footer('requirements_check');
}

function requirements_check()
{
	global $output, $mybb, $dboptions, $lang;

	$mybb->input['action'] = "requirements_check";
	$output->print_header($lang->req_check, 'requirements');
	echo $lang->req_step_top;
	$errors = array();
	$showerror = 0;

	// Check PHP Version
	$phpversion = @phpversion();
	if($phpversion < '4.1.0')
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->sprintf($lang->req_step_error_phpversion, $phpversion));
		$phpversion = $lang->sprintf($lang->req_step_span_fail, $phpversion);
		$showerror = 1;
	}
	else
	{
		$phpversion = $lang->sprintf($lang->req_step_span_pass, $phpversion);
	}
	
	if(function_exists('mb_detect_encoding'))
	{
		$mboptions[] = $lang->multi_byte;
	}
	
	if(function_exists('iconv'))
	{
		$mboptions[] = 'iconv';
	}
	
	// Check Multibyte extensions
	if(count($mboptions) < 1)
	{
		$mbstatus = $lang->sprintf($lang->req_step_span_fail, $lang->none);
	}
	else
	{
		$mbstatus = implode(', ', $mboptions);
	}

	// Check database engines
	if(count($dboptions) < 1)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_dboptions);
		$dbsupportlist = $lang->sprintf($lang->req_step_span_fail, $lang->none);
		$showerror = 1;
	}
	else
	{
		foreach($dboptions as $dboption)
		{
			$dbsupportlist[] = $dboption['title'];
		}
		$dbsupportlist = implode(', ', $dbsupportlist);
	}

	// Check XML parser is installed
	if(!function_exists('xml_parser_create'))
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_xmlsupport);
		$xmlstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_installed);
		$showerror = 1;
	}
	else
	{
		$xmlstatus = $lang->sprintf($lang->req_step_span_pass, $lang->installed);
	}

	// Check config file is writable
	$configwritable = @fopen(MYBB_ROOT.'inc/config.php', 'w');
	if(!$configwritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_configfile);
		$configstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$configstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($configwritable);

	// Check settings file is writable
	$settingswritable = @fopen(MYBB_ROOT.'inc/settings.php', 'w');
	if(!$settingswritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_settingsfile);
		$settingsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$settingsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($settingswritable);

	// Check cache directory is writable
	$cachewritable = @fopen(MYBB_ROOT.'cache/test.write', 'w');
	if(!$cachewritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_cachedir);
		$cachestatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($cachewritable);
	}
	else
	{
		$cachestatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($cachewritable);
	  	@chmod(MYBB_ROOT.'cache', 0777);
	  	@chmod(MYBB_ROOT.'cache/test.write', 0777);
		@unlink(MYBB_ROOT.'cache/test.write');
	}

	// Check upload directory is writable
	$uploadswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
	if(!$uploadswritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_uploaddir);
		$uploadsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($uploadswritable);
	}
	else
	{
		$uploadsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($uploadswritable);
	  	@chmod(MYBB_ROOT.'uploads', 0777);
	  	@chmod(MYBB_ROOT.'uploads/test.write', 0777);
		@unlink(MYBB_ROOT.'uploads/test.write');
	}

	// Check avatar directory is writable
	$avatarswritable = @fopen(MYBB_ROOT.'uploads/avatars/test.write', 'w');
	if(!$avatarswritable)
	{
		$errors[] =  $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_avatardir);
		$avatarsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($avatarswritable);
	}
	else
	{
		$avatarsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($avatarswritable);
		@chmod(MYBB_ROOT.'uploads/avatars', 0777);
	  	@chmod(MYBB_ROOT.'uploads/avatars/test.write', 0777);
		@unlink(MYBB_ROOT.'uploads/avatars/test.write');
  	}


	// Output requirements page
	echo $lang->sprintf($lang->req_step_reqtable, $phpversion, $dbsupportlist, $mbstatus, $xmlstatus, $configstatus, $settingsstatus, $cachestatus, $uploadsstatus, $avatarsstatus);

	if($showerror == 1)
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->req_step_error_tablelist, $error_list);
		echo "\n			<input type=\"hidden\" name=\"action\" value=\"{$mybb->input['action']}\" />";
		echo "\n				<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"{$lang->recheck} &raquo;\" /></div><br style=\"clear: both;\" />\n";
		$output->print_footer();
	}
	else
	{
		echo $lang->req_step_reqcomplete;
		$output->print_footer('database_info');
	}
}

function database_info()
{
	global $output, $dbinfo, $errors, $mybb, $dboptions, $lang;
	
	$mybb->input['action'] = 'database_info';
	$output->print_header($lang->db_config, 'dbconfig');

	echo "<script type=\"text/javascript\">
		function updateDBSettings()
		{
			dbengine = \$('dbengine').options[\$('dbengine').selectedIndex].value;
			$$('.db_settings').each(function(element)
			{
				element.className = 'db_settings';
				if(dbengine+'_settings' == element.id)
				{
					Element.show(element);
				}
				else
				{
					Element.hide(element);
				}
			});
		}
		Event.observe(window, 'load', updateDBSettings);
		</script>";

	// Check for errors from this stage
	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->db_step_error_config, $error_list);
	}
	else
	{
		echo $lang->db_step_config_db;
	}
	
	// Loop through database engines
	foreach($dboptions as $dbfile => $dbtype)
	{
		if($mybb->input['dbengine'] == $dbfile)
		{
			$dbengines .= "<option value=\"{$dbfile}\" selected=\"selected\">{$dbtype['title']}</option>";
		}
		else
		{
			$dbengines .= "<option value=\"{$dbfile}\">{$dbtype['title']}</option>";
		}
	}

	foreach($dboptions as $dbfile => $dbtype)
	{
		require_once MYBB_ROOT."inc/db_{$dbfile}.php";
		$db = new $dbtype['class'];
		$encodings = $db->fetch_db_charsets();
		$encoding_select = '';
		if(!$mybb->input['config'][$dbfile]['dbhost'])
		{
			$mybb->input['config'][$dbfile]['dbhost'] = "localhost";
		}
		if(!$mybb->input['config'][$dbfile]['tableprefix'])
		{
			$mybb->input['config'][$dbfile]['tableprefix'] = "mybb_";
		}
		if(!$mybb->input['config'][$dbfile]['encoding'])
		{
			$mybb->input['config'][$dbfile]['encoding'] = "utf8";
		}

		$class = '';
		if(!$first && !$mybb->input['dbengine'])
		{
			$mybb->input['dbengine'] = $dbfile;
			$first = true;
		}
		if($dbfile == $mybb->input['dbengine'])
		{
			$class = "_selected";
		}

		$db_info[$dbfile] = "
			<tbody id=\"{$dbfile}_settings\" class=\"db_settings db_type{$class}\">
				<tr>
					<th colspan=\"2\" class=\"first last\">{$dbtype['title']} {$lang->database_settings}</th>
				</tr>";
			
		// SQLite gets some special settings
		if($dbfile == 'sqlite2' || $dbfile == 'sqlite3')
		{
			$db_info[$dbfile] .= "
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">{$lang->database_path}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
				</tr>";
		}
		// Others get db host, username, password etc
		else
		{
			$db_info[$dbfile] .= "
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbhost\">{$lang->database_host}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbhost]\" id=\"config_{$dbfile}_dbhost\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbhost'])."\" /></td>
				</tr>
				<tr>
					<td class=\"first\"><label for=\"config_{$dbfile}_dbuser\">{$lang->database_user}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbuser]\" id=\"config_{$dbfile}_dbuser\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbuser'])."\" /></td>
				</tr>
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbpass\">{$lang->database_pass}</label></td>
					<td class=\"last alt_col\"><input type=\"password\" class=\"text_input\" name=\"config[{$dbfile}][dbpass]\" id=\"config_{$dbfile}_dbpass\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbpass'])."\" /></td>
				</tr>
				<tr class=\"last\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">{$lang->database_name}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
				</tr>";
		}

		// Now we're up to table settings
		$db_info[$dbfile] .= "
			<tr>
				<th colspan=\"2\" class=\"first last\">{$dbtype['title']} {$lang->table_settings}</th>
			</tr>
			<tr class=\"first\">
				<td class=\"first\"><label for=\"config_{$dbfile}_tableprefix\">{$lang->table_prefix}</label></td>
				<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][tableprefix]\" id=\"config_{$dbfile}_tableprefix\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['tableprefix'])."\" /></td>
			</tr>
			";
		
		// Encoding selection only if supported
		if(is_array($encodings))
		{
			$select_options = "";
			foreach($encodings as $encoding => $title)
			{
				if($mybb->input['config'][$dbfile]['encoding'] == $encoding)
				{
					$select_options .= "<option value=\"{$encoding}\" selected=\"selected\">{$title}</option>";
				}
				else
				{
					$select_options .= "<option value=\"{$encoding}\">{$title}</option>";
				}
			}
			$db_info[$dbfile] .= "
				<tr class=\"last\">
					<td class=\"first\"><label for=\"config_{$dbfile}_encoding\">{$lang->table_encoding}</label></td>
					<td class=\"last alt_col\"><select name=\"config[{$dbfile}][encoding]\" id=\"config_{$dbfile}_encoding\">{$select_options}</select></td>
				</tr>
				</tbody>";
		}
	}
	$dbconfig = implode("", $db_info);

	echo $lang->sprintf($lang->db_step_config_table, $dbengines, $dbconfig);
	$output->print_footer('create_tables');
}

function create_tables()
{
	global $output, $dbinfo, $errors, $mybb, $dboptions, $lang;
	
	if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
	{
		$errors[] = $lang->db_step_error_invalidengine;
		database_info();
	}

	$config = $mybb->input['config'][$mybb->input['dbengine']];
	
	if(strstr($mybb->input['dbengine'], "sqlite") !== false)
	{
		if(strstr($config['dbname'], "./") !== false || strstr($config['dbname'], "../") !== false)
		{
			$errors[] = $lang->db_step_error_sqlite_invalid_dbname;
			database_info();
		}
	}

	// Attempt to connect to the db
	require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";
	$db = new databaseEngine;
 	$db->error_reporting = 0;

	$connect_array = array(
		"hostname" => $config['dbhost'],
		"username" => $config['dbuser'],
		"password" => $config['dbpass'],
		"database" => $config['dbname'],
		"encoding" => $config['encoding']
	);

	$connection = $db->connect($connect_array);
	if(!$connection)
	{
		$errors[] = $lang->sprintf($lang->db_step_error_noconnect, $config['dbhost']);
	}

	if(is_array($errors))
	{
		database_info();
	}
	
	// Decide if we can use a database encoding or not
	if($db->fetch_db_charsets() != false)
	{
		$db_encoding = "\$config['database']['encoding'] = '{$config['encoding']}';";
	}
	else
	{
		$db_encoding = "// \$config['database']['encoding'] = '{$config['encoding']}';";
	}
	
	// Write the configuration file
	$configdata = "<?php
/**
 * Database configuration
 *
 * Please see the MyBB Wiki for advanced
 * database configuration for larger installations
 * http://wiki.mybboard.net/
 */

\$config['database']['type'] = '{$mybb->input['dbengine']}';
\$config['database']['database'] = '{$config['dbname']}';
\$config['database']['table_prefix'] = '{$config['tableprefix']}';

\$config['database']['hostname'] = '{$config['dbhost']}';
\$config['database']['username'] = '{$config['dbuser']}';
\$config['database']['password'] = '{$config['dbpass']}';

/**
 * Admin CP directory
 *  For security reasons, it is recommended you
 *  rename your Admin CP directory. You then need
 *  to adjust the value below to point to the
 *  new directory.
 */

\$config['admin_dir'] = 'admin';

/**
 * Hide all Admin CP links
 *  If you wish to hide all Admin CP links
 *  on the front end of the board after
 *  renaming your Admin CP directory, set this
 *  to 1.
 */

\$config['hide_admin_links'] = 0;

/**
 * Data-cache configuration
 *  The data cache is a temporary cache
 *  of the most commonly accessed data in MyBB.
 *  By default, the database is used to store this data.
 *
 *  If you wish to use the file system (inc/cache directory), MemCache or eAccelerator
 *  you can change the value below to 'files', 'memcache' or 'eaccelerator' from 'db'.
 */

\$config['cache_store'] = 'db';

/**
 * Memcache configuration
 *  If you are using memcache as your data-cache,
 *  you need to configure the hostname and port
 *  of your memcache server below.
 *
 * If not using memcache, ignore this section.
 */

\$config['memcache_host'] = 'localhost';
\$config['memcache_port'] = 11211;

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

\$config['super_admins'] = '1';

/**
 * Database Encoding
 *  If you wish to set an encoding for MyBB uncomment 
 *  the line below (if it isn't already) and change
 *  the current value to the mysql charset:
 *  http://dev.mysql.com/doc/refman/5.1/en/charset-mysql.html
 */

{$db_encoding}

/**
 * Automatic Log Pruning
 *  The MyBB task system can automatically prune
 *  various log files created by MyBB.
 *  To enable this functionality for the logs below, set the
 *  the number of days before each log should be pruned.
 *  If you set the value to 0, the logs will not be pruned.
 */

\$config['log_pruning'] = array(
	'admin_logs' => 365, // Administrator logs
	'mod_logs' => 365, // Moderator logs
	'task_logs' => 30, // Scheduled task logs
	'mail_logs' => 180, // Mail error logs
	'user_mail_logs' => 180, // User mail logs
	'promotion_logs' => 180 // Promotion logs
);
 
?>";

	$file = fopen(MYBB_ROOT.'inc/config.php', 'w');
	fwrite($file, $configdata);
	fclose($file);

	// Error reporting back on
 	$db->error_reporting = 1;

	$output->print_header($lang->table_creation, 'createtables');
	echo $lang->sprintf($lang->tablecreate_step_connected, $dboptions[$mybb->input['dbengine']]['short_title'], $db->get_version());
	
	if($dboptions[$mybb->input['dbengine']]['structure_file'])
	{
		$structure_file = $dboptions[$mybb->input['dbengine']]['structure_file'];
	}
	else
	{
		$structure_file = 'mysql_db_tables.php';
	}

	require_once INSTALL_ROOT."resources/{$structure_file}";
	foreach($tables as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,\(]|$)#', $config['tableprefix'].'\\1\\2', $val);
		$val = preg_replace('#;$#', $db->build_create_table_collation().";", $val);
		preg_match('#CREATE TABLE (\S+)(\s?|\(?)\(#i', $val, $match);
		if($match[1])
		{
			$db->drop_table($match[1], false, false);
			echo $lang->sprintf($lang->tablecreate_step_created, $match[1]);
		}
		$db->query($val);
		if($match[1])
		{
			echo $lang->done . "<br />\n";
		}
	}
	echo $lang->tablecreate_step_done;
	$output->print_footer('populate_tables');
}

function populate_tables()
{
	global $output, $lang;

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);

	$output->print_header($lang->table_population, 'tablepopulate');
	echo $lang->sprintf($lang->populate_step_insert);

	if($dboptions[$db->type]['population_file'])
	{
		$population_file = $dboptions[$db->type]['population_file'];
	}
	else
	{
		$population_file = 'mysql_db_inserts.php';
	}

	require_once INSTALL_ROOT."resources/{$population_file}";
	foreach($inserts as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,]|$)#', $config['database']['table_prefix'].'\\1\\2', $val);
		$db->query($val);
	}
	echo $lang->populate_step_inserted;
	$output->print_footer('templates');
}

function insert_templates()
{
	global $output, $cache, $db, $lang;

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);

	require_once MYBB_ROOT.'inc/class_datacache.php';
	$cache = new datacache;

	$output->print_header($lang->theme_installation, 'theme');

	echo $lang->theme_step_importing;

	$db->delete_query("themes");
	$db->delete_query("templates");

	$insert_array = array(
		'title' => 'Default Templates'
	);
	$db->insert_query("templatesets", $insert_array);
	$templateset = $db->insert_id();

	$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	require_once MYBB_ROOT."admin/inc/functions_themes.php";
	import_theme_xml($contents, array("templateset" => -2));
	$tid = build_new_theme("Default", null, 1);

	$db->update_query("themes", array("def" => 1), "tid='{$tid}'");

	echo $lang->theme_step_imported;
	$output->print_footer('configuration');
}

function configure()
{
	global $output, $mybb, $errors, $lang;
	
	$output->print_header($lang->board_config, 'config');

	// If board configuration errors
	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->config_step_error_config, $error_list);

		$bbname = htmlspecialchars($mybb->input['bbname']);
		$bburl = htmlspecialchars($mybb->input['bburl']);
		$websitename = htmlspecialchars($mybb->input['websitename']);
		$websiteurl = htmlspecialchars($mybb->input['websiteurl']);
		$cookiedomain = htmlspecialchars($mybb->input['cookiedomain']);
		$cookiepath = htmlspecialchars($mybb->input['cookiepath']);
		$contactemail =  htmlspecialchars($mybb->input['contactemail']);
	}
	else
	{
		$bbname = 'Forums';
		$cookiedomain = '';
		$cookiepath = '/';
		$websiteurl = $hostname.'/';
		$websitename = 'Your Website';
		$contactemail = '';
		// Attempt auto-detection
		if($_SERVER['HTTP_HOST'])
		{
			$hostname = 'http://'.$_SERVER['HTTP_HOST'];
			$cookiedomain = '.'.$_SERVER['HTTP_HOST'];
		}
		elseif($_SERVER['SERVER_NAME'])
		{
			$hostname = 'http://'.$_SERVER['SERVER_NAME'];
			$cookiedomain = '.'.$_SERVER['SERVER_NAME'];
		}
		
		if($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['SERVER_NAME'] == 'localhost')
		{
			$cookiedomain = '';
		}
		
		if($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80 && !preg_match("#:[0-9]#i", $hostname))
		{
			$hostname .= ':'.$_SERVER['SERVER_PORT'];
		}
		
		$currentlocation = get_current_location();
		if($currentlocation)
		{
			// TODO: Change this to find the last position of /install/
			$pos = my_strpos($currentlocation, '/install/');
			if($pos === 0)
			{
				$cookiepath = "/";
			}
			else
			{
				$cookiepath = my_substr($currentlocation, 0, $pos).'/';
			}
		}
		
		$currentscript = $hostname.get_current_location();
		
		if($currentscript)
		{
			$bburl = my_substr($currentscript, 0, my_strpos($currentscript, '/install/'));
		}
		
		if($_SERVER['SERVER_ADMIN'])
		{
			$contactemail = $_SERVER['SERVER_ADMIN'];
		}
	}

	echo $lang->sprintf($lang->config_step_table, $bbname, $bburl, $websitename, $websiteurl, $cookiedomain, $cookiepath, $contactemail);
	$output->print_footer('adminuser');
}

function create_admin_user()
{
	global $output, $mybb, $errors, $db, $lang;
	
	$mybb->input['action'] = "adminuser";
	// If no errors then check for errors from last step
	if(!is_array($errors))
	{
		if(empty($mybb->input['bburl']))
		{
			$errors[] = $lang->config_step_error_url;
		}
		if(empty($mybb->input['bbname']))
		{
			$errors[] = $lang->config_step_error_name;
		}
		if(is_array($errors))
		{
			configure();
		}
	}
	$output->print_header($lang->create_admin, 'admin');

	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->admin_step_error_config, $error_list);
		$adminuser = $mybb->input['adminuser'];
		$adminemail = $mybb->input['adminemail'];
	}
	else
	{
		require MYBB_ROOT.'inc/config.php';
		$db = db_connection($config);

		echo $lang->admin_step_setupsettings;

		$settings = file_get_contents(INSTALL_ROOT.'resources/settings.xml');
		$parser = new XMLParser($settings);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();

		// Insert all the settings
		foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
		{
			$groupdata = array(
				'name' => $db->escape_string($settinggroup['attributes']['name']),
				'title' => $db->escape_string($settinggroup['attributes']['title']),
				'description' => $db->escape_string($settinggroup['attributes']['description']),
				'disporder' => intval($settinggroup['attributes']['disporder']),
				'isdefault' => $settinggroup['attributes']['isdefault'],
			);
			$db->insert_query('settinggroups', $groupdata);
			$gid = $db->insert_id();
			++$groupcount;
			foreach($settinggroup['setting'] as $setting)
			{
				$settingdata = array(
					'name' => $db->escape_string($setting['attributes']['name']),
					'title' => $db->escape_string($setting['title'][0]['value']),
					'description' => $db->escape_string($setting['description'][0]['value']),
					'optionscode' => $db->escape_string($setting['optionscode'][0]['value']),
					'value' => $db->escape_string($setting['settingvalue'][0]['value']),
					'disporder' => intval($setting['disporder'][0]['value']),
					'gid' => $gid
				);

				$db->insert_query('settings', $settingdata);
				$settingcount++;
			}
		}

		if(my_substr($mybb->input['bburl'], -1, 1) == '/')
		{
			$mybb->input['bburl'] = my_substr($mybb->input['bburl'], 0, -1);
		}

		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['bbname'])), "name='bbname'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['bburl'])), "name='bburl'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['websitename'])), "name='homename'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['websiteurl'])), "name='homeurl'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['cookiedomain'])), "name='cookiedomain'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['cookiepath'])), "name='cookiepath'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->input['contactemail'])), "name='adminemail'");
		$db->update_query("settings", array('value' => 'mailto:'.$db->escape_string($mybb->input['contactemail'])), "name='contactlink'");

		write_settings();

		echo $lang->sprintf($lang->admin_step_insertesettings, $settingcount, $groupcount);

		include_once MYBB_ROOT."inc/functions_task.php";
		$tasks = file_get_contents(INSTALL_ROOT.'resources/tasks.xml');
		$parser = new XMLParser($tasks);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();

		// Insert scheduled tasks
		foreach($tree['tasks'][0]['task'] as $task)
		{
			$new_task = array(
				'title' => $db->escape_string($task['title'][0]['value']),
				'description' => $db->escape_string($task['description'][0]['value']),
				'file' => $db->escape_string($task['file'][0]['value']),
				'minute' => $db->escape_string($task['minute'][0]['value']),
				'hour' => $db->escape_string($task['hour'][0]['value']),
				'day' => $db->escape_string($task['day'][0]['value']),
				'weekday' => $db->escape_string($task['weekday'][0]['value']),
				'month' => $db->escape_string($task['month'][0]['value']),
				'enabled' => $db->escape_string($task['enabled'][0]['value']),
				'logging' => $db->escape_string($task['logging'][0]['value'])
			);

			$new_task['nextrun'] = fetch_next_run($new_task);

			$db->insert_query("tasks", $new_task);
			$taskcount++;
		}

		echo $lang->sprintf($lang->admin_step_insertedtasks, $taskcount);

		$views = file_get_contents(INSTALL_ROOT.'resources/adminviews.xml');
		$parser = new XMLParser($views);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();

		// Insert admin views
		foreach($tree['adminviews'][0]['view'] as $view)
		{
			$fields = array();
			foreach($view['fields'][0]['field'] as $field)
			{
				$fields[] = $field['attributes']['name'];
			}
			$conditions = array();
			
			if(is_array($view['conditions'][0]['condition']))
			{
				foreach($view['conditions'][0]['condition'] as $condition)
				{
					if(!$condition['value']) continue;
					if($condition['attributes']['is_serialized'] == 1)
					{
						$condition['value'] = unserialize($condition['value']);
					}
					$conditions[$condition['attributes']['name']] = $condition['value'];
				}
			}

			$new_view = array(
				"uid" => 0,
				"type" => $db->escape_string($view['attributes']['type']),
				"visibility" => intval($view['attributes']['visibility']),
				"title" => $db->escape_string($view['title'][0]['value']),
				"fields" => $db->escape_string(serialize($fields)),
				"conditions" => $db->escape_string(serialize($conditions)),
				"sortby" => $db->escape_string($view['sortby'][0]['value']),
				"sortorder" => $db->escape_string($view['sortorder'][0]['value']),
				"perpage" => intval($view['perpage'][0]['value']),
				"view_type" => $db->escape_string($view['view_type'][0]['value'])
			);
			$db->insert_query("adminviews", $new_view);
			$view_count++;
		}

		echo $lang->sprintf($lang->admin_step_insertedviews, $view_count);

		echo $lang->admin_step_createadmin;
	}

	echo $lang->sprintf($lang->admin_step_admintable, $adminuser, $adminemail);
	$output->print_footer('final');
}

function install_done()
{
	global $output, $db, $mybb, $errors, $cache, $lang;

	if(empty($mybb->input['adminuser']))
	{
		$errors[] = $lang->admin_step_error_nouser;
	}
	if(empty($mybb->input['adminpass']))
	{
		$errors[] = $lang->admin_step_error_nopassword;
	}
	if($mybb->input['adminpass'] != $mybb->input['adminpass2'])
	{
		$errors[] = $lang->admin_step_error_nomatch;
	}
	if(empty($mybb->input['adminemail']))
	{
		$errors[] = $lang->admin_step_error_noemail;
	}
	if(is_array($errors))
	{
		create_admin_user();
	}

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);
	
	require MYBB_ROOT.'inc/settings.php';
	$mybb->settings = &$settings;

	ob_start();
	$output->print_header($lang->finish_setup, 'finish');
	
	echo $lang->done_step_usergroupsinserted;
	
	// Insert all of our user groups from the XML file	
	$settings = file_get_contents(INSTALL_ROOT.'resources/usergroups.xml');
	$parser = new XMLParser($settings);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	$admin_gid = '';
	$group_count = 0;
	foreach($tree['usergroups'][0]['usergroup'] as $usergroup)
	{
		// usergroup[cancp][0][value]
		$new_group = array();
		foreach($usergroup as $key => $value)
		{
			if($key == "gid" || !is_array($value)) continue;
			$new_group[$key] = $db->escape_string($value[0]['value']);
		}
		$db->insert_query("usergroups", $new_group);
		// If this group can access the admin CP and we haven't established the admin group - set it (just in case we ever change IDs)
		if($new_group['cancp'] == 1 && !$admin_gid)
		{
			$admin_gid = $db->insert_id();
		}
		$group_count++;
	}
	echo $lang->done . '</p>';
	
	echo $lang->done_step_admincreated;
	$now = TIME_NOW;
	$salt = random_str();
	$loginkey = generate_loginkey();
	$saltedpw = md5(md5($salt).md5($mybb->input['adminpass']));

	$newuser = array(
		'username' => $db->escape_string($mybb->input['adminuser']),
		'password' => $saltedpw,
		'salt' => $salt,
		'loginkey' => $loginkey,
		'email' => $db->escape_string($mybb->input['adminemail']),
		'usergroup' => $admin_gid, // assigned above
		'regdate' => $now,
		'lastactive' => $now,
		'lastvisit' => $now,
		'website' => '',
		'icq' => '',
		'aim' => '',
		'yahoo' => '',
		'msn' =>'',
		'birthday' => '',
		'signature' => '',
		'allownotices' => 1,
		'hideemail' => 0,
		'subscriptionmethod' => '0',
		'receivepms' => 1,
		'pmnotice' => 1,
		'pmnotify' => 1,
		'remember' => 1,
		'showsigs' => 1,
		'showavatars' => 1,
		'showquickreply' => 1,
		'invisible' => 0,
		'style' => '0',
		'timezone' => 0,
		'dst' => 0,
		'threadmode' => '',
		'daysprune' => 0,
		'regip' => $db->escape_string(get_ip()),
		'longregip' => intval(ip2long(get_ip())),
		'language' => '',
		'showcodebuttons' => 1,
		'tpp' => 0,
		'ppp' => 0,
		'referrer' => 0,
		'buddylist' => '',
		'ignorelist' => '',
		'pmfolders' => '',
		'notepad' => '',
		'showredirect' => 1
	);
	$db->insert_query('users', $newuser);
	echo $lang->done . '</p>';

	echo $lang->done_step_adminoptions;
	$adminoptions = file_get_contents(INSTALL_ROOT.'resources/adminoptions.xml');
	$parser = new XMLParser($adminoptions);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();
	$insertmodule = array();
	
	$db->delete_query("adminoptions");
	
	// Insert all the admin permissions
	foreach($tree['adminoptions'][0]['user'] as $users)
	{			
		$uid = $users['attributes']['uid'];
		
		foreach($users['permissions'][0]['module'] as $module)
		{
			foreach($module['permission'] as $permission)
			{
				$insertmodule[$module['attributes']['name']][$permission['attributes']['name']] = $permission['value'];
			}
		}

		$defaultviews = array();
		foreach($users['defaultviews'][0]['view'] as $view)
		{
			$defaultviews[$view['attributes']['type']] = $view['value'];
		}
		
		$adminoptiondata = array(
			'uid' => intval($uid),
			'cpstyle' => '',
			'notes' => '',
			'permissions' => $db->escape_string(serialize($insertmodule)),
			'defaultviews' => $db->escape_string(serialize($defaultviews))
		);

		$insertmodule = array();

		$db->insert_query('adminoptions', $adminoptiondata);
	}
	echo $lang->done . '</p>';

	// Automatic Login
	my_unsetcookie("sid");
	my_unsetcookie("mybbuser");
	my_setcookie('mybbuser', $uid.'_'.$loginkey, null, true);
	ob_end_flush();

	// Make fulltext columns if supported
	if($db->supports_fulltext('threads'))
	{
		$db->create_fulltext_index('threads', 'subject');
	}
	if($db->supports_fulltext_boolean('posts'))
	{
		$db->create_fulltext_index('posts', 'message');
	}

	// Register a shutdown function which actually tests if this functionality is working
	add_shutdown('test_shutdown_function');

	echo $lang->done_step_cachebuilding;
	require_once MYBB_ROOT.'inc/class_datacache.php';
	$cache = new datacache;
	$cache->update_version();
	$cache->update_attachtypes();
	$cache->update_smilies();
	$cache->update_badwords();
	$cache->update_usergroups();
	$cache->update_forumpermissions();
	$cache->update_stats();
	$cache->update_moderators();
	$cache->update_forums();
	$cache->update_usertitles();
	$cache->update_reportedposts();
	$cache->update_mycode();
	$cache->update_posticons();
	$cache->update_update_check();
	$cache->update_tasks();
	$cache->update_spiders();
	$cache->update_bannedips();
	$cache->update_banned();
	$cache->update_birthdays();
	echo $lang->done . '</p>';

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

function db_connection($config)
{
	require_once MYBB_ROOT."inc/db_{$config['database']['type']}.php";
	$db = new databaseEngine;
	
	// Connect to Database
	define('TABLE_PREFIX', $config['database']['table_prefix']);

	$db->connect($config['database']);
	$db->set_table_prefix(TABLE_PREFIX);
	return $db;
}

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

function write_settings()
{
	global $db;
	
	$query = $db->simple_select('settings', '*', '', array('order_by' => 'title'));
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['{$setting['name']}'] = \"{$setting['value']}\";\n";
	}
	if(!empty($settings))
	{
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n{$settings}\n?>";
		$file = fopen(MYBB_ROOT."inc/settings.php", "w");
		fwrite($file, $settings);
		fclose($file);
	}
}

function test_shutdown_function()
{
	global $db;
	
	$db->update_query("settings", array('value' => 1), "name='useshutdownfunc'");
	write_settings();
}
?>