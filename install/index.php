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

// The version number of MyBB we are installing
$myver = '1.0';

require '../inc/class_core.php';
$mybb = new MyBB;

// Include the files necessary for installation
require '../inc/class_timers.php';
require '../inc/functions.php';
require '../admin/adminfunctions.php';
require '../inc/class_xml.php';
require '../inc/functions_user.php';
require '../inc/class_language.php';
$lang = new MyLanguage();
$lang->setPath('resources/');
$lang->load('language');

// Include the necessary contants for installation
$grouppermignore = array('gid', 'type', 'title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$groupzerogreater = array('pmquota', 'maxreputationsday', 'attachquota');
$displaygroupfields = array('title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$fpermfields = array('canview', 'candlattachments', 'canpostthreads', 'canpostreplys', 'canpostattachments', 'canratethreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canpostpolls', 'canvotepolls', 'cansearch');

// Include the installation resources
require './resources/output.php';
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
		'license' => $lang->lisence_agreement,
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
	global $output, $myver, $lang;
	$output->print_header($lang->welcome, 'welcome');
	echo sprintf($lang->welcome_step, $myver);
	$output->print_footer('license');
}

function license_agreement()
{
	global $output, $lang;
	$output->print_header($lang->lisence_agreement, 'license');
	echo $lang->lisence_step;
	$output->print_footer('requirements_check');
}

function requirements_check()
{
	global $output, $myver, $dboptions, $lang;

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

	// Check config file is writeable
	$configwritable = @fopen('../inc/config.php', 'w');
	if(!$configwritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_configfile);
		$configstatus = sprintf($lang->req_step_span_fail, $lang->not_writeable);
		$showerror = 1;
	}
	else
	{
		$configstatus = sprintf($lang->req_step_span_pass, $lang->writeable);
	}
	@fclose($configwritable);
		
	// Check settings file is writeable
	$settingswritable = @fopen('../inc/settings.php', 'w');
	if(!$settingswritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_settingsfile);
		$settingsstatus = sprintf($lang->req_step_span_fail, $lang->not_writeable);
		$showerror = 1;
	}
	else
	{
		$settingsstatus = sprintf($lang->req_step_span_pass, $lang->writeable);
	}
	@fclose($settingswritable);

	// Check upload directory is writeable
	$uploadswritable = @fopen('../uploads/test.write', 'w');
	if(!$uploadswritable)
	{
		$errors[] = sprintf($lang->req_step_error_box, $lang->req_step_error_uploaddir);
		$uploadsstatus = sprintf($lang->req_step_span_fail, $lang->not_writeable);
		$showerror = 1;
	}
	else
	{
		$uploadsstatus = sprintf($lang->req_step_span_pass, $lang->writeable);
	}
	@fclose($uploadswritable);

	// Check avatar directory is writeable
	$avatarswritable = @fopen('../uploads/avatars/test.write', 'w');
	if(!$avatarswritable)
	{
		$errors[] =  sprintf($lang->req_step_error_box, $lang->req_step_error_avatardir);
		$avatarsstatus = sprintf($lang->req_step_span_fail, $lang->not_writeable);
		$showerror = 1;
	}
	else
	{
		$avatarsstatus = sprintf($lang->req_step_span_pass, $lang->writeable);
	}
	@fclose($avatarswritable);
	
	$csswritable = @fopen('../css/test.write', 'w');
	if(!$csswritable)
	{
		$errors[] =  sprintf($lang->req_step_error_box, $lang->req_step_error_cssdir);
		$cssstatus = sprintf($lang->req_step_span_fail, $lang->not_writeable);
		$showerror = 1;
	}
	else
	{
		$cssstatus = sprintf($lang->req_step_span_pass, $lang->writeable);
	}
	@fclose($csswritable);
	
	// Output requirements page
	echo sprintf($lang->req_step_reqtable, $phpversion, $dbsupportlist, $xmlstatus, $configstatus, $settingsstatus, $uploadsstatus, $avatarsstatus, $cssstatus);

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
	global $output, $myver, $dbinfo, $errors, $mybb, $dboptions, $lang;
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
	global $output, $myver, $dbinfo, $errors, $mybb, $dboptions, $lang;

	if(!file_exists("../inc/db_{$mybb->input['dbengine']}.php"))
	{
		$errors[] = $lang->db_step_error_invalidengine;
		database_info();
	}

	// Attempt to connect to the db
	require "../inc/db_{$mybb->input['dbengine']}.php";
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
/* Database Configuration */
\$config['dbtype'] = \"{$mybb->input['dbengine']}\";
\$config['hostname'] = \"{$mybb->input['dbhost']}\";
\$config['username'] = \"{$mybb->input['dbuser']}\";
\$config['password'] = \"{$mybb->input['dbpass']}\";
\$config['database'] = \"{$mybb->input['dbname']}\";
\$config['table_prefix'] = \"{$mybb->input['tableprefix']}\";

/* Admin CP*/
\$config['admindir'] = \"admin\";
\$config['hideadminlinks'] = 0;

/* Datacache Configuration */

/* files = Stores datacache in files inside /inc/cache/ (Must be writable)*/

/* db = Stores datacache in the database*/
\$config['cachestore'] = \"db\";
?>";

	$file = fopen('../inc/config.php', 'w');
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

	require "./resources/{$structure_file}";
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
	global $output, $myver, $lang;

	require '../inc/config.php';
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

	require "./resources/{$population_file}";
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
	global $output, $myver, $cache, $db, $lang;

	require '../inc/config.php';
	$db = db_connection($config);

	require '../inc/class_datacache.php';
	$cache = new datacache;

	$output->print_header($lang->theme_installation, 'theme');

	echo $lang->theme_step_importing;

	$db->query("DELETE FROM ".TABLE_PREFIX."themes");
	$db->query("DELETE FROM ".TABLE_PREFIX."templates");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,css,cssbits,themebits,extracss) VALUES ('MyBB Master Style','0','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,def,css,cssbits,themebits,extracss) VALUES ('MyBB Default','1','1','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (title) VALUES ('Default Templates');");
	$templateset = $db->insert_id();

	$contents = @file_get_contents('./resources/mybb_theme.xml');
	//$arr = @file('./resources/mybb_theme.xml');
	//$contents = @implode('', $arr);

	$parser = new XMLParser($contents);
	$tree = $parser->getTree();

	$theme = $tree['theme'];
	$css = killtags($theme['cssbits']);
	$themebits = killtags($theme['themebits']);
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
	global $output, $myver, $mybb, $errors, $lang;
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
		// Attempt auto-detection
		if($_SERVER['HTTP_HOST'])
		{
			$hostname = 'http://'.$_SERVER['HTTP_HOST'];
		}
		elseif($_SERVER['SERVER_NAME'])
		{
			$hostname = 'http://'.$_SERVER['SERVER_NAME'];
		}
		if($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80)
		{
			$hostname .= ':'.$_SERVER['PORT'];
		}
		$currentscript = $hostname.get_current_location();
		if($currentscript)
		{
			$bburl = substr($currentscript, 0, strpos($currentscript, '/install/'));
		}
		$bbname = 'Forums';
		$cookiedomain = '';
		$cookiepath = '/';
		$websiteurl = $hostname.'/';
		$websitename = 'Your Website';
		$contactemail = '';
	}

	echo sprintf($lang->config_step_table, $bbname, $bburl, $websitename, $websiteurl, $cookiedomain, $cookiepath, $contactemail);
	$output->print_footer('adminuser');
}

function create_admin_user()
{
	global $output, $myver, $mybb, $errors, $db, $lang;
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
		require '../inc/config.php';
		$db = db_connection($config);

		echo $lang->admin_step_setupsettings;

		$settings = file_get_contents('./resources/settings.xml');
		$parser = new XMLParser($settings);
		$parser->collapse_dups = 0;
		$tree = $parser->getTree();

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
			$db->insert_query(TABLE_PREFIX.'settinggroups', $groupdata);
			$gid = $db->insert_id();
			$groupcount++;
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

				$db->insert_query(TABLE_PREFIX.'settings', $settingdata);
				$settingcount++;
			}
		}
		echo sprintf($lang->admin_step_insertesettings, $settingcount, $groupcount);

		if (substr($mybb->input['bburl'], -1, 1) == '/')
		{
			$mybb->input['bburl'] = substr($mybb->input['bburl'], 0, -1);
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
	global $output, $db, $myver, $mybb, $errors, $cache, $lang;

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

	require '../inc/config.php';
	$db = db_connection($config);

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
		'lastvisit' => intval($now),
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
		'regip' => $ipaddress,
		'language' => '',
		'showcodebuttons' => 1,
		'tpp' => 0,
		'ppp' => 0,
		'referrer' => 0,
	);
	$db->insert_query(TABLE_PREFIX.'users', $newuser);
	$uid = $db->insert_id();

	$db->query("INSERT INTO ".TABLE_PREFIX."adminoptions VALUES ('{$uid}','','','1','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes')");

	// Automatic Login
	mysetcookie('mybbadmin', $uid.'_'.$loginkey);
	mysetcookie('mybbuser', $uid.'_'.$loginkey);
	ob_end_flush();

	echo $lang->done . '</p>';


	// Make fulltext columns if supported
	if($db->supports_fulltext(TABLE_PREFIX.'threads'))
	{
		$db->create_fulltext_index(TABLE_PREFIX.'threads', 'subject');
	}
	if($db->supports_fulltext_boolean(TABLE_PREFIX.'posts'))
	{
		$db->create_fulltext_index(TABLE_PREFIX.'posts', 'message');
		$update_data = array(
			'value' => 'yes'
		);
		$db->update_query(TABLE_PREFIX.'settings', $update_data, 'name=\'searchtype\'');
		write_settings();
	}

	// Register a shutdown function which actually tests if this functionality is working
	add_shutdown('test_shutdown_function');

	echo $lang->done_step_cachebuilding;
	require '../inc/class_datacache.php';
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
	$output->print_footer('');
}

function db_connection($config)
{
	require "../inc/db_{$config['dbtype']}.php";
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
		$setting['value'] = $db->escape_string($setting['value']);
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
