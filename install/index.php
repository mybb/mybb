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
error_reporting(E_ALL & ~E_NOTICE);

define("MYBB_ROOT", dirname(dirname(__FILE__)));
define("INSTALL_ROOT", dirname(__FILE__));

require_once MYBB_ROOT.'/inc/class_core.php';
$mybb = new MyBB;

// Include the files necessary for installation
require_once MYBB_ROOT.'/inc/class_timers.php';
require_once MYBB_ROOT.'/inc/functions.php';
require_once MYBB_ROOT.'/admin/adminfunctions.php';
require_once MYBB_ROOT.'/inc/class_xml.php';
require_once MYBB_ROOT.'/inc/functions_user.php';
require_once MYBB_ROOT.'/inc/class_language.php';
$lang = new MyLanguage();
$lang->set_path('resources/');
$lang->load('language');

// Include the necessary contants for installation
$grouppermignore = array('gid', 'type', 'title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$groupzerogreater = array('pmquota', 'maxreputationsday', 'attachquota');
$displaygroupfields = array('title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$fpermfields = array('canview', 'candlattachments', 'canpostthreads', 'canpostreplys', 'canpostattachments', 'canratethreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canpostpolls', 'canvotepolls', 'cansearch');

// Include the installation resources
require_once INSTALL_ROOT.'/resources/output.php';
$output = new installerOutput;

$dboptions = array();

// Get the current working directory
$cwd = getcwd();

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

if(file_exists('lock'))
{
	$output->print_error($lang->locked);
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
	echo sprintf($lang->welcome_step, $mybb->version);
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
	echo sprintf($lang->license_step, $license);
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
		$errors[] = sprintf($lang->req_step_error_box, sprintf($lang->req_step_error_phpversion, $phpversion));
		$phpversion = sprintf($lang->req_step_span_fail, $phpversion);
		$showerror = 1;
	}
	else
	{
		$phpversion = sprintf($lang->req_step_span_pass, $phpversion);
	}

	// Check database engines
	if(count($dboptions) < 1)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_dboptions);
		$dbsupportlist = sprintf($lang->req_step_span_fail, $lang->none);
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
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_xmlsupport);
		$xmlstatus = sprintf($lang->req_step_span_fail, $lang->not_installed);
		$showerror = 1;
	}
	else
	{
		$xmlstatus = sprintf($lang->req_step_span_pass, $lang->installed);
	}

	// Check config file is writable
	$configwritable = @fopen(MYBB_ROOT.'/inc/config.php', 'w');
	if(!$configwritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_configfile);
		$configstatus = sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$configstatus = sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($configwritable);

	// Check settings file is writable
	$settingswritable = @fopen(MYBB_ROOT.'/inc/settings.php', 'w');
	if(!$settingswritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_settingsfile);
		$settingsstatus = sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$settingsstatus = sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($settingswritable);

	// Check upload directory is writable
	$uploadswritable = @fopen(MYBB_ROOT.'/uploads/test.write', 'w');
	if(!$uploadswritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_uploaddir);
		$uploadsstatus = sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($uploadswritable);
	}
	else
	{
		$uploadsstatus = sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($uploadswritable);
	  @chmod(MYBB_ROOT.'/uploads', 0777);
	  @chmod(MYBB_ROOT.'/uploads/test.write', 0777);
		@unlink(MYBB_ROOT.'/uploads/test.write');
	}

	// Check avatar directory is writable
	$avatarswritable = @fopen(MYBB_ROOT.'/uploads/avatars/test.write', 'w');
	if(!$avatarswritable)
	{
		$errors[] =  sprintf($lang->req_step_error_box, $lang->req_step_error_avatardir);
		$avatarsstatus = sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($avatarswritable);
	}
	else
	{
		$avatarsstatus = sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($avatarswritable);
		@chmod(MYBB_ROOT.'/uploads/avatars', 0777);
	  @chmod(MYBB_ROOT.'/uploads/avatars/test.write', 0777);
		@unlink(MYBB_ROOT.'/uploads/avatars/test.write');
  }


	// Output requirements page
	echo sprintf($lang->req_step_reqtable, $phpversion, $dbsupportlist, $xmlstatus, $configstatus, $settingsstatus, $uploadsstatus, $avatarsstatus);

	if($showerror == 1)
	{
		$error_list = error_list($errors);
		echo sprintf($lang->req_step_error_tablelist, $error_list);
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
		$tableprefix = 'mybb_';
		$dbuser = '';
		$dbname = '';
	}

	// Loop through database engines
	foreach($dboptions as $dbfile => $dbtype)
	{
		$dbengines .= "<option value=\"{$dbfile}\">{$dbtype['title']}</option>";
	}

	echo sprintf($lang->db_step_config_table, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix);
	$output->print_footer('create_tables');
}

function create_tables()
{
	global $output, $dbinfo, $errors, $mybb, $dboptions, $lang;

	if(!file_exists(MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php"))
	{
		$errors[] = $lang->db_step_error_invalidengine;
		database_info();
	}

	// Attempt to connect to the db
	require_once MYBB_ROOT."/inc/db_{$mybb->input['dbengine']}.php";
	$db = new databaseEngine;
 	$db->error_reporting = 0;

	$connection = $db->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass']);
	if(!$connection)
	{
		$errors[] = sprintf($lang->db_step_error_noconnect, $mybb->input['dbhost']);
	}

	// Select the database
	$dbselect = $db->select_db($mybb->input['dbname']);
	if(!$dbselect)
	{
		$errors[] = sprintf($lang->db_step_error_nodbname, $mybb->input['dbname']);
	}

	if(is_array($errors))
	{
		database_info();
	}

	// Write the configuration file
	$configdata = "<?php
/**
 * Daatabase configuration
 */

\$config['dbtype'] = '{$mybb->input['dbengine']}';
\$config['hostname'] = '{$mybb->input['dbhost']}';
\$config['username'] = '{$mybb->input['dbuser']}';
\$config['password'] = '{$mybb->input['dbpass']}';
\$config['database'] = '{$mybb->input['dbname']}';
\$config['table_prefix'] = '{$mybb->input['tableprefix']}';

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
 *  If you wish to use the file system (inc/cache directory)
 *  you can change the value below to 'files' from 'db'.
 */

\$config['cache_store'] = 'db';

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

\$config['super_admins'] = '1';
 
?>";

	$file = fopen(MYBB_ROOT.'/inc/config.php', 'w');
	fwrite($file, $configdata);
	fclose($file);

	$output->print_header($lang->table_creation, 'createtables');
	echo sprintf($lang->tablecreate_step_connected, $dboptions[$mybb->input['dbengine']]['title'], $db->get_version());

	if($dboptions[$config['dbtype']]['structure_file'])
	{
		$structure_file = $dboptions[$config['dbtype']]['structure_file'];
	}
	else
	{
		$structure_file = 'mysql_db_tables.php';
	}

	require_once INSTALL_ROOT."/resources/{$structure_file}";
	foreach($tables as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,]|$)#', $mybb->input['tableprefix'].'\\1\\2', $val);
		preg_match('#CREATE TABLE (\S+) \(#i', $val, $match);
		if($match[1])
		{
			$db->query('DROP TABLE IF EXISTS '.$match[1]);
			echo sprintf($lang->tablecreate_step_created, $match[1]);
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

	require_once MYBB_ROOT.'/inc/config.php';
	$db = db_connection($config);

	$output->print_header($lang->table_population, 'tablepopulate');
	echo sprintf($lang->populate_step_insert);

	if($dboptions[$config['dbtype']]['population_file'])
	{
		$population_file = $dboptions[$config['dbtype']]['population_file'];
	}
	else
	{
		$population_file = 'mysql_db_inserts.php';
	}

	require_once INSTALL_ROOT."/resources/{$population_file}";
	foreach($inserts as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,]|$)#', $config['table_prefix'].'\\1\\2', $val);
		$db->query($val);
	}
	echo $lang->populate_step_inserted;
	$output->print_footer('templates');
}

function insert_templates()
{
	global $output, $cache, $db, $lang;

	require_once MYBB_ROOT.'/inc/config.php';
	$db = db_connection($config);

	require_once MYBB_ROOT.'/inc/class_datacache.php';
	$cache = new datacache;

	$output->print_header($lang->theme_installation, 'theme');

	echo $lang->theme_step_importing;

	$db->query("DELETE FROM ".TABLE_PREFIX."themes");
	$db->query("DELETE FROM ".TABLE_PREFIX."templates");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,css,cssbits,themebits,extracss) VALUES ('MyBB Master Style','0','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,def,css,cssbits,themebits,extracss) VALUES ('MyBB Default','1','1','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (title) VALUES ('Default Templates');");
	$templateset = $db->insert_id();

	$contents = @file_get_contents(INSTALL_ROOT.'/resources/mybb_theme.xml');
	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];
	$css = kill_tags($theme['cssbits']);
	$themebits = kill_tags($theme['themebits']);
	$templates = $theme['templates']['template'];
	$themebits['templateset'] = $templateset;
	$sid = -2;
	foreach($templates as $template)
	{
		$templatename = $template['attributes']['name'];
		$templatevalue = $db->escape_string($template['value']);
		$templateversion = $template['attributes']['version'];
		$time = time();
		$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid,version,status,dateline) VALUES ('{$templatename}','{$templatevalue}','{$sid}','{$templateversion}','','{$time}')");
	}
	update_theme(1, 0, $themebits, $css, 0);

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
		echo sprintf($lang->config_step_error_config, $error_list);

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
		if($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80 && !preg_match("#:[0-9]#i", $hostname))
		{
			$hostname .= ':'.$_SERVER['SERVER_PORT'];
		}
		$currentlocation = get_current_location();
		if($currentlocation)
		{
			$cookiepath = my_substr($currentlocation, 0, strpos($currentlocation, '/install/')).'/';
		}
		$currentscript = $hostname.get_current_location();
		if($currentscript)
		{
			$bburl = my_substr($currentscript, 0, strpos($currentscript, '/install/'));
		}
	}

	echo sprintf($lang->config_step_table, $bbname, $bburl, $websitename, $websiteurl, $cookiedomain, $cookiepath, $contactemail);
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
		echo sprintf($lang->admin_step_error_config, $error_list);
		$adminuser = $mybb->input['adminuser'];
		$adminemail = $mybb->input['adminemail'];
	}
	else
	{
		require_once MYBB_ROOT.'/inc/config.php';
		$db = db_connection($config);

		echo $lang->admin_step_setupsettings;

		$settings = file_get_contents(INSTALL_ROOT.'/resources/settings.xml');
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
		echo sprintf($lang->admin_step_insertesettings, $settingcount, $groupcount);

		if (my_substr($mybb->input['bburl'], -1, 1) == '/')
		{
			$mybb->input['bburl'] = my_substr($mybb->input['bburl'], 0, -1);
		}

		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['bbname'])."' WHERE name='bbname'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['bburl'])."' WHERE name='bburl'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['websitename'])."' WHERE name='homename'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['websiteurl'])."' WHERE name='homeurl'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['cookiedomain'])."' WHERE name='cookiedomain'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['cookiepath'])."' WHERE name='cookiepath'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='".$db->escape_string($mybb->input['contactemail'])."' WHERE name='adminemail'");
		$db->query("UPDATE ".TABLE_PREFIX."settings SET value='mailto:".$db->escape_string($mybb->input['contactemail'])."' WHERE name='contactlink'");

		write_settings();
		echo $lang->admin_step_createadmin;
	}

	echo sprintf($lang->admin_step_admintable, $adminuser, $adminemail);
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

	require_once MYBB_ROOT.'/inc/config.php';
	$db = db_connection($config);
	
	require_once MYBB_ROOT.'/inc/settings.php';
	$mybb->settings = &$settings;

	ob_start();
	$output->print_header($lang->finish_setup, 'finish');

	echo $lang->done_step_admincreated;
	$now = time();
	$salt = random_str();
	$loginkey = generate_loginkey();
	$saltedpw = md5(md5($salt).md5($mybb->input['adminpass']));

	$newuser = array(
		'username' => $db->escape_string($mybb->input['adminuser']),
		'password' => $saltedpw,
		'salt' => $salt,
		'loginkey' => $loginkey,
		'email' => $db->escape_string($mybb->input['adminemail']),
		'usergroup' => 4,
		'regdate' => $now,
		'lastactive' => $now,
		'lastvisit' => $now,
		'website' => '',
		'icq' => '',
		'aim' => '',
		'yahoo' => '',
		'msn' =>'',
		'birthday' => '',
		'allownotices' => 'yes',
		'hideemail' => 'no',
		'emailnotify' => 'no',
		'receivepms' => 'yes',
		'pmpopup' => 'yes',
		'pmnotify' => 'yes',
		'remember' => 'yes',
		'showsigs' => 'yes',
		'showavatars' => 'yes',
		'showquickreply' => 'yes',
		'invisible' => 'no',
		'style' => '0',
		'timezone' => 0,
		'dst' => 'no',
		'threadmode' => '',
		'daysprune' => 0,
		'regip' => $db->escape_string(get_ip()),
		'language' => '',
		'showcodebuttons' => 1,
		'tpp' => 0,
		'ppp' => 0,
		'referrer' => 0,
	);
	$db->insert_query('users', $newuser);
	$uid = $db->insert_id();

	$db->query("INSERT INTO ".TABLE_PREFIX."adminoptions VALUES ('{$uid}','','','1','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes')");

	// Automatic Login
	my_setcookie('mybbuser', $uid.'_'.$loginkey, null, true);
	ob_end_flush();

	echo $lang->done . '</p>';


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
	require_once MYBB_ROOT.'/inc/class_datacache.php';
	$cache = new datacache;
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
	$cache->updateupdate_check();
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
	require_once MYBB_ROOT."/inc/db_{$config['dbtype']}.php";
	$db = new databaseEngine;
	// Connect to Database
	define('TABLE_PREFIX', $config['table_prefix']);
	$db->connect($config['hostname'], $config['username'], $config['password']);
	$db->select_db($config['database']);
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
	global $db, $cwd;
	$query = $db->query('SELECT * FROM '.TABLE_PREFIX.'settings ORDER BY title ASC');
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['{$setting['name']}'] = \"{$setting['value']}\";\n";
	}
	if(!empty($settings))
	{
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n{$settings}\n?>";
		$file = fopen(dirname($cwd)."/inc/settings.php", "w");
		fwrite($file, $settings);
		fclose($file);
	}
}

function test_shutdown_function()
{
	global $db;
	$db->query("UPDATE ".TABLE_PREFIX."settings SET value='yes' WHERE name='useshutdownfunc'");
	write_settings();
}
?>