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

// The version number of MyBB we are installing
$myver = "1.0 Preview Release 1";

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


// Fix register_globals
@extract($_POST, EXTR_OVERWRITE);
@extract($_FILES, EXTR_OVERWRITE);
@extract($_GET, EXTR_OVERWRITE);
@extract($_ENV, EXTR_OVERWRITE);
@extract($_COOKIE, EXTR_OVERWRITE);
@extract($_SERVER, EXTR_OVERWRITE);

// Include the files necessary for installation
require "../inc/class_timers.php";
require "../inc/constants.php";
require "../inc/functions.php";
require "../admin/adminfunctions.php";
require "../inc/class_xml.php";

// Include the installation resources
require "./resources/output.php";
$output = new installerOutput;


if(file_exists("lock"))
{
	$output->print_error("The installer is currently locked, please remove 'lock' from the install directory to continue");
}
else
{
	switch($action)
	{
		case "requirements_check":
			requirements_check();
			break;
		case "database_info":
			database_info();
			break;
		case "create_tables":
			create_tables();
			break;
		case "populate_tables":
			populate_tables();
			break;
		case "templates":
			insert_templates();
			break;
		case "configuration":
			configure();
			break;
		case "final":
			install_done();
			break;
		default:
			intro();
			break;
	}
}

function intro()
{
	global $output, $myver;

	$output->print_header("Welcome");
	$output->print_contents("<p>Welcome to the installation wizard for MyBulletinBoard $myver.</p><p>Before you continue please be sure that your have your database settings available and that you have uploaded the entire contents of the MyBB archive to your server.</p><p>Once you're ready, please click next to continue and we'll double check everything is ready for the installation.</p><p>Thank you for choosing MyBB!</p>");
	$output->print_footer("requirements_check");
}


function requirements_check()
{
	global $output, $myver;

	$output->print_header("Requirements Check");
	$contents = "<p>Before you can install MyBB, we must check that you meet the minimum requirements for installation.</p>";
	$errors = array();
	$showerror = 0;
	// Check PHP Version
	$phpversion = @phpversion();
	if($phpversion < "4.1.0")
	{
		$errors[] = "<p><b>MyBB Requires PHP 4.1.0 or later to run. You currently have $phpversion installed.</b></p>";
		$showerror = 1;
	}
	else
	{
		$contents .= "<p>PHP Version: $phpversion</p>";
	}
	if(!function_exists("xml_parser_create"))
	{
		$errors[] = "<p><strong>MyBB requires PHP to be compiled with support for XML Data Handling. Please see <a href=\"http://www.php.net/xml\">PHP.net</a> for more information.</strong></p>";
		$showerror = 1;
	}
	else
	{
		$contents .= "<p>PHP XML Extensions: Installed</p>";
	}
	$configwritable = is_writeable("../inc/config.php");
	if(!$configwritable)
	{
		$errors[] = "<p><b>The configuration file (inc/config.php) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$showerror = 1;
	}
	else
	{
		$contents .= "<p>Configuration File Writable: Yes</p>";
	}
	$settingswritable = is_writeable("../inc/settings.php");
	if(!$settingswritable)
	{
		$errors[] = "<p><b>The settings file (inc/settings.php) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$showerror = 1;
	}
	else
	{
		$contents .= "<p>Settings File Writable: Yes</p>";
	}
	// Done requirement checks
	if($showerror == 1)
	{
		$contents .= "<p><b><font color=\"red\">REQUIREMENTS CHECK FAILED</b></font><br />We cannot proceed to install MyBB because you did not pass the minimum requirements to install MyBB outlined below:</p>";
		while(list($key, $val) = each($errors))
		{
			$contents .= $val;
		}
		$output->print_contents($contents);
		$output->print_footer();
	}
	else
	{
		$contents .= "<p><b>Everything seems to be in normal order, click Next to continue.</b></p>";
		$output->print_contents($contents);
		$output->print_footer("database_info");
	}
}

function database_info()
{
	global $output, $myver, $dbinfo;

	$output->print_header("Database Configuration");
	$contents = "<p>To continue with the installation we now require your MySQL database information. If you do not have this information it can usually be obtained from your webhost.</p>";
	$contents .= "<p>Database Engine:<br /><select name=\"dbinfo[engine]\"><option value=\"mysql\">MySQL</option></select></p>\n";
	$contents .= "<p>Database Host:<br /><input type=\"text\" name=\"dbinfo[host]\" value=\"localhost\" /></p>\n";
	$contents .= "<p>Database Username:<br /><input type=\"text\" name=\"dbinfo[username]\" value=\"root\" /></p>\n";
	$contents .= "<p>Database Password:<br /><input type=\"password\" name=\"dbinfo[password]\" value=\"\" /></p>\n";
	$contents .= "<p>Database Name:<br /><input type=\"text\" name=\"dbinfo[name]\" value=\"mybb\" /></p>\n";
	$contents .= "<p>Table Prefix:<br /><input type=\"text\" name=\"dbinfo[prefix]\" value=\"mybb_\" /></p>\n";
	$contents .= "<p><b>Once you have checked these details and are ready to proceed, click Next.</b></p>\n";
	$output->print_contents($contents);
	$output->print_footer("create_tables");
}

function create_tables()
{
	global $output, $myver, $dbinfo;
	
	if(!file_exists("../inc/db_".$dbinfo['engine'].".php")) {
		$output->print_error("<p>Sorry but you have selected an invalid database engine, please go back and try again.</p>");
	}

	// Attempt to connect to the db
	require "../inc/db_".$dbinfo['engine'].".php";
	$db = new bbDB;
	$db->error_reporting = 0;

	$connection = $db->connect($dbinfo['host'], $dbinfo['username'], $dbinfo['password']);
	if(!$connection)
	{
		$output->print_error("<p>Sorry, but we could not connect to the database server you specified with the username and password.</p><p>The error was:<br />".$db->error()."</p>");
	}
	$dbselect = @mysql_select_db($dbinfo['name']);
	if(!$dbselect)
	{
		$output->print_error("<p>Sorry, but we could not attach to the database name you specified. Are you sure its correct?</p><p>The error was:<br />".$db->error()."</p>");
	}
	// Write the configuration file
	$configdata = "<?php\n".
	"\$config['dbtype'] = \"".$dbinfo['engine']."\";\n".
	"\$config['hostname'] = \"".$dbinfo['host']."\";\n".
	"\$config['username'] = \"".$dbinfo['username']."\";\n".
	"\$config['password'] = \"".$dbinfo['password']."\";\n".
	"\$config['database'] = \"".$dbinfo['name']."\";\n".
	"\$config['table_prefix'] = \"".$dbinfo['prefix']."\";\n".
	"?>";
	$file = fopen("../inc/config.php", "w");
	fwrite($file, $configdata);
	fclose($file);

	$output->print_header("Table Creation");

	$contents = "<p>We managed to connect to the database successfully, now we need to create the tables.</p>";
	
	require "./resources/".$dbinfo['engine']."_db_tables.php";
	while(list($key, $val) = each($tables))
	{
		$val = preg_replace("#mybb_(\S+?)([\s\.,]|$)#", $dbinfo['prefix']."\\1\\2", $val);
		preg_match("#CREATE TABLE (\S+) \(#i", $val, $match);
		if($match[1])
		{
			mysql_query("DROP TABLE IF EXISTS ".$match[1]);
			$contents .= "Creating table ".$match[1]."...";
		}
		mysql_query($val);
		if($match[1])
		{
			$contents .= "done<br />";
		}
	}
	$contents .= "<p>All tables have been created, click Next to populate them.</p>";
	$output->print_contents($contents);
	$output->print_footer("populate_tables");
}

function populate_tables()
{
	global $output, $myver;

	require "../inc/config.php";
	require "../inc/db_$config[dbtype].php";
	$db=new bbDB;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config[hostname], $config[username], $config[password]);
	$db->select_db($config[database]);
	
	$output->print_header("Table Population");
	$contents = "<p>Now that the basic tables have been created we need to insert the default data.</p>";

	require "./resources/".$config['dbtype']."_db_inserts.php";
	while(list($key, $val) = each($inserts))
	{
		$val = preg_replace("#mybb_(\S+?)([\s\.,]|$)#", $config['table_prefix']."\\1\\2", $val);
		mysql_query($val);
	}
	$contents .= "<p>OK! Great! Click Next to insert the default theme and templates.</p>";
	$output->print_contents($contents);
	$output->print_footer("templates");
}

function insert_templates()
{
	global $output, $myver, $cache, $db;
	require "../inc/config.php";
	require "../inc/db_$config[dbtype].php";
	$db=new bbDB;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config[hostname], $config[username], $config[password]);
	$db->select_db($config[database]);


	require "../inc/class_datacache.php";
	$cache = new datacache;

	$output->print_header("Default Template Installation");

	$page = "<p>Loading amd importing theme and template file..</p>";

	$db->query("DELETE FROM ".TABLE_PREFIX."themes");
	$db->query("DELETE FROM ".TABLE_PREFIX."templates");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (tid,name,pid) VALUES (NULL,'MyBB Master Style','0')");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (tid,name,pid,def) VALUES (NULL,'MyBB Default','1','1')");
	
	$arr = @file("./resources/mybb_theme.xml");
	$contents = @implode("", $arr);

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
		$templatevalue = addslashes($template['value']);
		$db->query("INSERT INTO ".TABLE_PREFIX."templates VALUES ('','$templatename','$templatevalue','$sid')");
	}
	update_theme(1, 0, $themebits, $css, 0);
	$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (sid,title) VALUES (NULL,'Default Templates');");
	$page .= "Completed Successfully! Click next to setup basic options for your board.";

	$output->print_contents($page);
	$output->print_footer("configuration");
}

function configure()
{
	global $output, $myver;
	$output->print_header("Board Configuration and Administrator Account Setup");
	$contents = "<p>Now that we have managed to install the default templates, as well as populate the database we need you to specify the basic board configuration below as well as create an Administrator account.</p>";
	$contents .= "<p>Board Name:<br /><input type=\"text\" name=\"boardname\" value=\"\" /></p>\n";
	$contents .= "<p>Board URL: (Enter the full URL to your board without the trailing slash.)<br /><input type=\"text\" name=\"boardurl\" value=\"http://\" /></p>\n";
	$contents .= "<p>&nbsp;</p>";
	$contents .= "<p>Administrator Username:<br /><input type=\"text\" name=\"adminuser\" value=\"\" /></p>\n";
	$contents .= "<p>Administrator Password:<br /><input type=\"password\" name=\"adminpass\" value=\"\" /></p>\n";
	$contents .= "<p>Administrator Email:<br /><input type=\"text\" name=\"adminemail\" value=\"\" /></p>\n";
	$contents .= "<p><b>Once you have checked these details and are ready to proceed, click Next.</b></p>\n";
	$output->print_contents($contents);
	$output->print_footer("final");

}

function install_done()
{
	global $output, $db, $myver, $adminuser, $adminpass, $boardname, $boardurl, $adminemail, $cache;

	require "../inc/config.php";
	require "../inc/db_".$config['dbtype'].".php";
	$db=new bbDB;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config[hostname], $config[username], $config[password]);
	$db->select_db($config[database]);

	$output->print_header("Final Steps");
	$contents = "<p>Inserting MyBB settings...";
//	require "./resources/settings.php";
	$contents .=  "done</p>";

	$contents .= "<p>Creating Administrator account...";
	$now = time();

	$adminuser = addslashes($_POST['adminuser']);
	$db->query("INSERT INTO ".TABLE_PREFIX."users (uid,username,password,email,usergroup,regdate) VALUES (NULL,'".$adminuser."','".md5($adminpass)."','$adminemail','4','$now')");
	$uid = $db->insert_id();
	$db->query("INSERT INTO ".TABLE_PREFIX."adminoptions VALUES ('$uid','','','1','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes')");
	$contents .= "done</p>";

	$contents .= "<p>Setting up basic board settings...";
	$boardname = addslashes($_POST['boardname']);
	$db->query("UPDATE ".TABLE_PREFIX."settings SET value='$boardname' WHERE name='bbname'");
	$db->query("UPDATE ".TABLE_PREFIX."settings SET value='$boardurl' WHERE name='bburl'");
	$db->query("UPDATE ".TABLE_PREFIX."settings SET value='$adminemail' WHERE name='adminemail'");
	write_settings();
	$contents .= "done</p>";

	// Make fulltext column
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD FULLTEXT KEY subject_2 (subject)", 1);

	$contents .= "<p>Building cache's...";
	require "../inc/class_datacache.php";
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
	$contents .= "done</p>";

	$contents .= "<p><b>Installation has successfully been completed.</b></p><p><b><font color=\"red\">Please remove this directory before exploring your copy of MyBB.</font></b></p>";
	$output->print_contents($contents);
	$output->print_footer("");
}

function write_settings()
{
	global $db;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings ORDER BY title ASC");
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = addslashes($setting['value']);
		$settings .= "\$settings['".$setting['name']."'] = \"".$setting['value']."\";\n";
	}
	$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?>";
	$file = fopen("../inc/settings.php", "w");
	fwrite($file, $settings);
	fclose($file);
}
?>