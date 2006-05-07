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
$myver = "1.0";

require "../inc/class_core.php";
$mybb = new MyBB;

// Include the files necessary for installation
require "../inc/class_timers.php";
require "../inc/functions.php";
require "../admin/adminfunctions.php";
require "../inc/class_xml.php";
require "../inc/functions_user.php";

// Include the necessary contants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

// Include the installation resources
require "./resources/output.php";
$output = new installerOutput;

$dboptions = array();

// Get the current working directory
$cwd = getcwd();

if(function_exists("mysqli_connect"))
{
	$dboptions['mysqli'] = array(
		"title" => "MySQL Improved",
		"structure_file" => "mysql_db_tables.php",
		"population_file" => "mysql_db_inserts.php"
	);
}

if(function_exists("mysql_connect"))
{
	$dboptions['mysql'] = array(
		"title" => "MySQL",
		"structure_file" => "mysql_db_tables.php",
		"population_file" => "mysql_db_inserts.php"
	);
}

if(file_exists("lock"))
{
	$output->print_error("The installer is currently locked, please remove 'lock' from the install directory to continue");
}
else
{
	$output->steps = array(
		"intro" => "Welcome",
		"license" => "License Agreement",
		"requirements_check" => "Requirements Check",
		"database_info" => "Database Configuration",
		"create_tables" => "Table Creation",
		"populate_tables" => "Data Insertion",
		"templates" => "Theme Installation",
		"configuration" => "Board Configuration",
		"adminuser" => "Administrator User",
		"final" => "Finish Setup"
	);
	if(!isset($mybb->input['action']))
	{
		$mybb->input['action'] = "intro";
	}
	switch($mybb->input['action'])
	{
		case "license":
			license_agreement();
			break;
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
		case "adminuser";
			create_admin_user();
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

	$output->print_header("Welcome", "welcome");
	echo <<<END
			<p>Welcome to the installation wizard for MyBB $myver. This wizard will install and configure a copy of MyBB on your server.</p>
			<p>Now that you've uploaded the MyBB files the database and settings need to be created and imported. Below is an outline of what is going to be completed during installation.</p>
			<ul>
				<li>MyBB requirements checked</li>
				<li>Configuration of database engine</li>
				<li>Creation of database tables</li>
				<li>Default data inserted</li>
				<li>Default themes and templates imported</li>
				<li>Creation of an administrator account to manage your board</li>
				<li>Basic board settings configured</li>
			</ul>
			<p>After each step has successfully been completed, click Next to move on to the next step.</p>
			<p>Click "Next" to view the MyBB license agreement.</p>
END;

	$output->print_footer("license");
}

function license_agreement()
{
	global $output;

	$output->print_header("License Agreement", "license");

	echo <<<END
		<div class="license_agreement">
		<h3>IMPORTANT- READ CAREFULLY:</h3>

This MyBulletinBoard End-User License Agreement ("EULA") is a legal agreement between you (either an individual or a single entity) and MyBulletinBoard for the MyBulletinBoard product identified above, which includes computer software and may include associated media, printed materials, and "online" or electronic documentation ("MyBulletinBoard"). By installing, copying, or otherwise using the MyBulletinBoard  PRODUCT, you agree to be bound by the terms of this EULA. If you do not agree to the terms of this EULA, do not install or use the MyBulletinBoard Product; you may, however, return it to MyBulletinBoard.<br />
<br />
<h3>This EULA grants you the following rights:</h3>
<ul>
<li><strong>Installation and Use</strong><br />
You may install and use an unlimited amount of copies of MyBB on your domain(s) or website(s). However, each download must be registered at the MyBB Website.
</li>
<li><strong>Reproduction and Distribution</strong><br />
You may not reproduce or distribute the MyBB  SOFTWARE for any reason with out express written consent from the entire MyBulletinBoard seat holding Admin Board.
</li>
</ul>
<h3>DESCRIPTION OF OTHER RIGHTS AND LIMITATIONS.</h3>

<ul>
<li><strong>Limitations on Reverse Engineering, Decompilation, and Disassembly</strong><br />
You may not Reverse Engineer, Decompile, or Disassemble the MyBB  SOFTWARE. You may add modifications ("HACKS") to the software that MyBulletinBoard personally releases.</li>
<li><strong>Separation of Components</strong><br />
The MyBB  SOFTWARE is licensed as a single product. Its component parts may not be separated for use on more than one computer.
</li>
<li><strong>Termination</strong><br />
Without prejudice to any other rights, MyBulletinBoard may terminate this EULA if you fail to comply with the terms and conditions of this EULA. In such event, you must destroy all copies of the MyBB  SOFTWARE and all of its component parts. MyBulletinBoard and the MyBulletinBoard Group also reserve the right to revoke any license or copy of MyBB for any reasons they specify.
</li>
</ul>
<h3>COPYRIGHT</h3>
All title and copyrights in and to the MyBB  SOFTWARE(including but not limited to any images, photographs, animations, video, audio, music, text, and "applets" incorporated into the MyBB  SOFTWARE), the accompanying materials, and any copies of the MyBB  SOFTWARE are owned by MyBulletinBoard. The MyBB  SOFTWARE is protected by copyright laws and international treaty provisions. Therefore, you must treat the MyBB  SOFTWARE like any other copyrighted material.
<br /><br />
At all times, the MyBB "Powered by" and copyright lines must be present and clearly in the footer of your board. You may not alter or change the "Powered by" and copyright lines in any way without express permission from the MyBB Group.
<h3>U.S. GOVERNMENT RESTRICTED RIGHTS</h3>
The MyBB  SOFTWARE and documentation are provided with RESTRICTED RIGHTS. Use, duplication, or disclosure by the Government is subject to restrictions as set forth in subparagraph (c)(1)(ii) of the Rights in Technical Data and Computer Software clause at DFARS 252.227-7013 or subparagraphs (c)(1) and (2) of the Commercial Computer Software-Restricted Rights at 48 CFR 52.227-19, as applicable. Manufacturer is MyBulletinBoard @ www.mybboard.com

<h3>MISCELLANEOUS</h3>
<ul>
<li>If you acquired this product in the United States, this EULA is governed by the laws of the State of Washington.<br /></li>
<li>If you acquired this product in Canada, this EULA is governed by the laws of the Province of Ontario, Canada. Each of the parties hereto irrevocably attorns to the jurisdiction of the courts of the Province of Ontario and further agrees to commence any litigation which may arise hereunder in the courts located in the Judicial District of York, Province of Ontario. <br /></li>
<li>If this product was acquired outside the United States or Canada, then local law may apply.<br /></li>
<li>Should you have any questions concerning this EULA, or if you desire to contact MyBulletinBoard for any reason, please contact MyBulletinBoard at www.mybboard.com.<br /></li>
<li>NO WARRANTIES. MyBulletinBoard expressly disclaims any warranty for the MyBB  SOFTWARE. The MyBB  SOFTWARE and any related documentation is provided "as is" without warranty of any kind, either express or implied, including, without limitation, the implied warranties or merchantability, fitness for a particular purpose, or noninfringement. The entire risk arising out of use or performance of the MyBB  SOFTWARE remains with you. <br /></li>
<li>NO LIABILITY FOR DAMAGES. In no event shall MyBulletinBoard or its suppliers be liable for any damages whatsoever (including, without limitation, damages for loss of business profits, business interruption, loss of business information, or any other pecuniary loss) arising out of the use of or inability to use this MyBulletinBoard product, even if MyBulletinBoard has been advised of the possibility of such damages. Because some states/jurisdictions do not allow the exclusion or limitation of liability for consequential or incidental damages, the above limitation may not apply to you.</li>

<li>MyBulletinBoard reserves the right to make modifications to this License Grant at any given time and will apply to all current and existing copies of the MyBB Software.</li>
</ul>
</div>
<p><strong>By clicking Next, you agree to the terms stated in the MyBB License Agreement above.</strong></p>
END;
	$output->print_footer("requirements_check");
}

function requirements_check()
{
	global $output, $myver, $dboptions;

	$output->print_header("Requirements Check", "requirements");

	echo "<p>Before you can install MyBB, we must check that you meet the minimum requirements for installation.</p>";

	$errors = array();
	$showerror = 0;

	// Check PHP Version
	$phpversion = @phpversion();
	if($phpversion < "4.1.0")
	{
		$errors[] = "<p><b>MyBB Requires PHP 4.1.0 or later to run. You currently have $phpversion installed.</b></p>";
		$phpversion = "<span class=\"fail\"><strong>$phpversion</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		$phpversion = "<span class=\"pass\">$phpversion</span>\n";
	}
	// Check database engines
	if(count($dboptions) < 1)
	{
		$errors[] = "<p><b>MyBB requires one or more suitable database extensions to be installed. Your server reported that none were available.</b></p>";
		$dbsupportlist = "<span class=\"fail\"><strong>None</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		foreach($dboptions as $dboption)
		{
			$dbsupportlist[] = $dboption['title'];
		}
		$dbsupportlist = implode(", ", $dbsupportlist);
	}

	if(!function_exists("xml_parser_create"))
	{
		$errors[] = "<p><strong>MyBB requires PHP to be compiled with support for XML Data Handling. Please see <a href=\"http://www.php.net/xml\">PHP.net</a> for more information.</strong></p>";
		$showerror = 1;
		$xmlstatus = "<span class=\"fail\"><strong>Not Installed</strong></span>\n";
	}
	else
	{
		$xmlstatus = "<span class=\"pass\"><strong>Installed</strong></span>\n";
	}

	$configwritable = is_writeable("../inc/config.php");
	if(!$configwritable)
	{
		$errors[] = "<p><b>The configuration file (inc/config.php) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$configstatus = "<span class=\"fail\"><strong>Not Writable</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		$configstatus = "<span class=\"pass\">Writable</span>\n";
	}
	$settingswritable = is_writeable("../inc/settings.php");
	if(!$settingswritable)
	{
		$errors[] = "<p><b>The settings file (inc/settings.php) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$settingsstatus = "<span class=\"fail\"><strong>Not Writable</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		$settingsstatus = "<span class=\"pass\">Writable</span>\n";
	}
	$uploadswritable = is_writeable("../uploads");
	if(!$uploadswritable)
	{
		$errors[] = "<p><b>The uploads directory (uploads/) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$uploadsstatus = "<span class=\"fail\"><strong>Not Writable</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		$uploadsstatus = "<span class=\"pass\">Writable</span>\n";
	}
	$avatarswritable = is_writeable("../uploads/avatars");
	if(!$avatarswritable)
	{
		$errors[] =  "<p><b>The avatars directory (uploads/avatars/) is not writable. Please adjust the chmod permissions to allow it to be written to.</b></p>";
		$avatarsstatus = "<span class=\"fail\"><strong>Not Writable</strong></span>\n";
		$showerror = 1;
	}
	else
	{
		$avatarsstatus = "<span class=\"pass\">Writable</span>\n";
	}
	// Output requirements page
	echo <<<END
		<div class="border_wrapper">
			<div class="title">Requirements Check</div>
		<table class="general" cellspacing="0">
		<thead>
			<tr>
				<th colspan="2" class="first last">Requirements</td>
			</tr>
		</thead>
		<tbody>
		<tr class="first">
			<td class="first">PHP Version:</td>
			<td class="last alt_col">$phpversion</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Supported DB Extensions:</td>
			<td class="last alt_col">$dbsupportlist</td>
		<tr>
			<td class="first">PHP XML Extensions:</td>
			<td class="last alt_col">$xmlstatus</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Configuration File Writable:</td>
			<td class="last alt_col">$configstatus</td>
		</tr>
		<tr>
			<td class="first">Settings File Writable:</td>
			<td class="last alt_col">$settingsstatus</td>
		</tr>
		<tr class="alt_row">
			<td class="first">File Uploads Directory Writable:</td>
			<td class="last alt_col">$uploadsstatus</td>
		</tr>
		<tr class="last">
			<td class="first">Avatar Uploads Directory Writable:</td>
			<td class="last alt_col">$avatarsstatus</td>
		</tr>
		</tbody>
		</table>
		</div>
END;

	if($showerror == 1)
	{
		echo "<div class=\"error\">\n";
		echo "<h3>Error</h3>";
		echo "<p>The MyBB Requirements check failed due to the reasons below. MyBB installation cannot continue because you did not meet the MyBB requirements. Please correct the errors below and try again:</p>\n";
		echo "<ul>\n";
		while(list($key, $val) = each($errors))
		{
			echo "<li>$val</li>\n";
		}
		echo "</ul>\n";
		echo "</div>\n";
		$output->print_footer();
	}
	else
	{
		echo "<p><strong>Congratulations, you meet the requirements to run MyBB.</strong></p>\n";
		echo "<p>Click Next to continue with the installation process.</p>";
		$output->print_footer("database_info");
	}
}

function database_info()
{
	global $output, $myver, $dbinfo, $errors, $mybb, $dboptions;
	$mybb->input['action'] = "database_info";
	$output->print_header("Database Configuration", "dbconfig");

	if(is_array($errors))
	{
		echo "<div class=\"error\">";
		echo "<h3>Error</h3>";
		echo "<p>There seems to be one or more errors with the database configuration information that you supplied:</p>";
		echo "<ul>\n";
		while(list($key, $val) = each($errors))
		{
			echo "<li>$val</li>\n";
		}
		echo "</ul>\n";
		echo "<p>Once the above are corrected, continue with the installation.</p>\n";
		echo "</div>\n";
		$dbhost = $mybb->input['dbhost'];
		$dbuser = $mybb->input['dbuser'];
		$dbname = $mybb->input['dbname'];
		$tableprefix = $mybb->input['tableprefix'];
	}
	else
	{
		echo "<p>It is now time to configure the database that MyBB will use as well as your database authentication details. If you do not have this information, it can usually be obtained from your webhost.</p>";
		$dbhost = "localhost";
		$tableprefix = "mybb_";
		$dbuser = "";
		$dbname = "";
	}
	foreach($dboptions as $dbfile => $dbtype)
	{
		$dbengines .= "<option value=\"$dbfile\">{$dbtype['title']}</option>";
	}
	echo <<<END
		<div class="border_wrapper">
			<div class="title">Database Configuration</div>

		<table class="general" cellspacing="0">
		<tr>
			<th colspan="2" class="first last">Database Settings</td>
		</tr>
		<tr class="first">
			<td class="first"><label for="dbengine">Database Engine:</label></td>
			<td class="last alt_col"><select name="dbengine" id="dbengine">$dbengines</select></td>
		</tr>
		<tr class="alt_row">
			<td class="first"><label for="dbhost">Database Host:</label></td>
			<td class="last alt_col"><input type="text" class="text_input" name="dbhost" id="dbhost" value="$dbhost" /></td>
		</tr>
		<tr>
			<td class="first"><label for="dbuser">Database Username:</label></td>
			<td class="last alt_col"><input type="text" class="text_input" name="dbuser" id="dbuser" value="$dbuser" /></td>
		</tr>
		<tr class="alt_row">
			<td class="first"><label for="dbpass">Database Password:</label></td>
			<td class="last alt_col"><input type="password" class="text_input" name="dbpass" id="dbpass" value="" /></td>
		</tr>
		<tr class="last">
			<td class="first"><label for="dbname">Database Name:</label></td>
			<td class="last alt_col"><input type="text" class="text_input" name="dbname" id="dbname" value="$dbname" /></td>
		</tr>
		<tr>
			<th colspan="2" class="first last">Table Settings</th>
		</tr>
		<tr class="last">
			<td class="first"><label for="tableprefix">Table Prefix:</label></td>
			<td class="last alt_col"><input type="text" class="text_input" name="tableprefix" id="tableprefix" value="$tableprefix" /></td>
		</tr>
		</table>
		</div>
END;

	echo "<p>Once you've checked these details are correct, click next to continue.</p>";

	$output->print_footer("create_tables");
}

function create_tables()
{
	global $output, $myver, $dbinfo, $errors, $mybb, $dboptions;

	if(!file_exists("../inc/db_".$mybb->input['dbengine'].".php"))
	{
		$errors[] = "You have selected an invalid database engine. Please make your selection from the list below.";
		database_info();
	}

	// Attempt to connect to the db
	require "../inc/db_".$mybb->input['dbengine'].".php";
	$db = new databaseEngine;
 	$db->error_reporting = 0;

	$connection = $db->connect($mybb->input['dbhost'], $mybb->input['dbuser'], $mybb->input['dbpass']);
	if(!$connection)
	{
		$errors[] = "Could not connect to the database server at ".$mybb->input['dbhost']." with the supplied username and password. Are you sure the hostname and user details are correct?";
		database_info();
	}
	$dbselect = $db->select_db($mybb->input['dbname']);
	if(!$dbselect)
	{
		$errors[] = "Could not select the database '".$mybb->input['dbname']."'. Are you sure it exists and the specified username and password have access to it?";
		database_info();
	}
	// Write the configuration file
	$configdata = "<?php\n".
		"/* Database Configuration */\n".
		"\$config['dbtype'] = \"".$mybb->input['dbengine']."\";\n".
		"\$config['hostname'] = \"".$mybb->input['dbhost']."\";\n".
		"\$config['username'] = \"".$mybb->input['dbuser']."\";\n".
		"\$config['password'] = \"".$mybb->input['dbpass']."\";\n".
		"\$config['database'] = \"".$mybb->input['dbname']."\";\n".
		"\$config['table_prefix'] = \"".$mybb->input['tableprefix']."\";\n".
		"\n/* Admin CP*/\n".
		"\$config['admindir'] = \"admin\";\n".
		"\$config['hideadminlinks'] = 0;\n".
		"\n/* Datacache Configuration */\n".
		"\n/* files = Stores datacache in files inside /inc/cache/ (Must be writable)*/\n".
		"\n/* db = Stores datacache in the database*/\n".
		"\$config['cachestore'] = \"db\";\n".
		"?>";
	$file = fopen("../inc/config.php", "w");
	fwrite($file, $configdata);
	fclose($file);

	$output->print_header("Table Creation", "createtables");

	echo "<p>Connection to the database server and table you specified was successful.</p>";
	echo "<p>Database Engine: ".$dboptions[$mybb->input['dbengine']]['title']." ".$db->get_version()."</p>";
	echo "<p>The MyBB database tables will now be created.</p>";

	if($dboptions[$config['dbtype']]['structure_file'])
	{
		$structure_file = $dboptions[$config['dbtype']]['structure_file'];
	}
	else
	{
		$structure_file = "mysql_db_tables.php";
	}
	require "./resources/".$structure_file;
	while(list($key, $val) = each($tables))
	{
		$val = preg_replace("#mybb_(\S+?)([\s\.,]|$)#", $mybb->input['tableprefix']."\\1\\2", $val);
		preg_match("#CREATE TABLE (\S+) \(#i", $val, $match);
		if($match[1])
		{
			$db->query("DROP TABLE IF EXISTS ".$match[1]);
			echo "Creating table ".$match[1]."...";
		}
		$db->query($val);
		if($match[1])
		{
			echo "done<br />\n";
		}
	}
	echo "<p>All tables have been created, click Next to populate them.</p>";
	$output->print_footer("populate_tables");
}

function populate_tables()
{
	global $output, $myver;


	require "../inc/config.php";
	require "../inc/db_$config[dbtype].php";
	$db=new databaseEngine;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config['hostname'], $config['username'], $config['password']);
	$db->select_db($config['database']);

	$output->print_header("Table Population", "tablepopulate");
	$contents = "<p>Now that the basic tables have been created, it's time to insert the default data.</p>";

	if($dboptions[$config['dbtype']]['population_file'])
	{
		$population_file = $dboptions[$config['dbtype']]['population_file'];
	}
	else
	{
		$population_file = "mysql_db_inserts.php";
	}

	require "./resources/".$population_file;
	while(list($key, $val) = each($inserts))
	{
		$val = preg_replace("#mybb_(\S+?)([\s\.,]|$)#", $config['table_prefix']."\\1\\2", $val);
		$db->query($val);
	}
	echo "<p>The default data has successfully been inserted into the database. Click Next to insert the default MyBB template and theme sets.</p>";

	$output->print_footer("templates");
}

function insert_templates()
{
	global $output, $myver, $cache, $db;
	require "../inc/config.php";
	require "../inc/db_$config[dbtype].php";
	$db=new databaseEngine;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config['hostname'], $config['username'], $config['password']);
	$db->select_db($config['database']);


	require "../inc/class_datacache.php";
	$cache = new datacache;

	$output->print_header("Theme Insertion", "theme");

	echo "<p>Loading and importing theme and template file..</p>";

	$db->query("DELETE FROM ".TABLE_PREFIX."themes");
	$db->query("DELETE FROM ".TABLE_PREFIX."templates");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,css,cssbits,themebits,extracss) VALUES ('MyBB Master Style','0','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,def,css,cssbits,themebits,extracss) VALUES ('MyBB Default','1','1','','','','')");
	$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (title) VALUES ('Default Templates');");
	$templateset = $db->insert_id();
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
		$templatevalue = $db->escape_string($template['value']);
		$templateversion = $template['attributes']['version'];
		$time = time();
		$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid,version,status,dateline) VALUES ('$templatename','$templatevalue','$sid','$templateversion','','$time')");
	}
	update_theme(1, 0, $themebits, $css, 0);

	echo "<p>The default theme and template sets have been successfully inserted. Click Next to configure the basic options for your board.</p>";

	$output->print_footer("configuration");
}

function configure()
{
	global $output, $myver, $mybb, $errors;
	$output->print_header("Board Configuration", "config");
	if(is_array($errors))
	{
		echo "<div class=\"error\">";
		echo "<h3>Error</h3>";
		echo "<p>There seems to be one or more errors with the board configuration you supplied:</p>";
		echo "<ul>\n";
		while(list($key, $val) = each($errors))
		{
			echo "<li>$val</li>\n";
		}
		echo "</ul>\n";
		echo "<p>Once the above are corrected, continue with the installation.</p>\n";
		echo "</div>\n";
		$bbname = $mybb->input['bbname'];
		$bburl = $mybb->input['bburl'];
		$websitename = $mybb->input['websitename'];
		$websiteurl = $mybb->input['websiteurl'];
		$cookiedomain = $mybb->input['cookiedomain'];
		$cookiepath = $mybb->input['cookiepath'];
		$contactemail =  $mybb->input['contactemail'];
	}
	else
	{
		// Attempt auto-detection
		if($_SERVER['HTTP_HOST'])
		{
			$hostname = "http://".$_SERVER['HTTP_HOST'];
		}
		elseif($_SERVER['SERVER_NAME'])
		{
			$hostname = "http://".$_SERVER['SERVER_NAME'];
		}
		if($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80)
		{
			$hostname .= ":".$_SERVER['PORT'];
		}
		$currentscript = $hostname.get_current_location();
		if($currentscript)
		{
			$bburl = substr($currentscript, 0, strpos($currentscript, "/install/"));
		}
		$bbname = "Forums";
		$cookiedomain = "";
		$cookiepath = "/";
		$websiteurl = $hostname."/";
		$websitename = "Your Website";
		$contactemail = "";
	}
	echo <<<END
		<p>It is now time for you to configure the basic settings for your forums such as forum name, URL, your website details, along with your "cookie" domain and paths. These settings can easily be changed in the future through the MyBB Admin Control Panel.</p>
		<div class="border_wrapper">
			<div class="title">Board Configuration</div>
			<table class="general" cellspacing="0">
				<tbody>
				<tr>
					<th colspan="2" class="first last">Forum Details</th>
				</tr>
				<tr class="first">
					<td class="first"><label for="bbname">Forum Name:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="bbname" id="bbname" value="$bbname" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="bburl">Forum URL (No trailing slash):</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="bburl" id="bburl" value="$bburl" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Website Details</th>
				</tr>
				<tr>
					<td class="first"><label for="websitename">Website Name:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="websitename" id="websitename" value="$websitename" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="websiteurl">Website URL:</td>
					<td class="last alt_col"><input type="text" class="text_input" name="websiteurl" id="websiteurl" value="$websiteurl" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Cookie settings</th>
				</tr>
				<tr>
					<td class="first"><label for="cookiedomain">Cookie Domain:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="cookiedomain" id="cookiedomain" value="$cookiedomain" /></td>
				</tr>
				<tr class="alt_row last">
					<td class="first"><label for="cookiepath">Cookie Path:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" name="cookiepath" id="cookiepath" value="$cookiepath" /></td>
				</tr>
				<tr>
					<th colspan="2" class="first last">Contact Details (Shown in footer)</th>
				</tr>
				<tr class="last">
					<td class="first"><label for="contactemail">Contact Email:</label></td>
					<td class="last alt_col"><input type="text" class="text_input" class="text_input" name="contactemail" id="contactemail" value="$contactemail" /></td>
				</tr>
			</table>
		</div>

	<p>Once you've correctly entered the details above and are ready to proceed, click Next.</p>
END;
	$output->print_footer("adminuser");
}

function create_admin_user()
{
	global $output, $myver, $mybb, $errors, $db;
	if(!$errors)
	{
		if(!$mybb->input['bburl'])
		{
			$inerrors[] = "You did not enter the URL to your forums.";
		}
		if(!$mybb->input['bbname'])
		{
			$inerrors[] = "You did not enter a name for your copy of MyBB.";
		}
		if(is_array($inerrors))
		{
			configure();
		}
	}
	$output->print_header("Create Administrator Account", "admin");

	if(is_array($errors))
	{
		echo "<div class=\"error\">";
		echo "<h3>Error</h3>";
		echo "<p>There seems to be one or more errors with the board configuration you supplied:</p>";
		echo "<ul>\n";
		while(list($key, $val) = each($errors))
		{
			echo "<li>$val</li>\n";
		}
		echo "</ul>\n";
		echo "<p>Once the above are corrected, continue with the installation.</p>\n";
		echo "</div>\n";
		$adminuser = $mybb->input['adminuser'];
		$adminemail = $mybb->input['adminemail'];
	}
	else
	{
		require "../inc/config.php";
		require "../inc/db_".$config['dbtype'].".php";
		$db = new databaseEngine;

		// Connect to Database
		define("TABLE_PREFIX", $config['table_prefix']);
		$db->connect($config['hostname'], $config['username'], $config['password']);
		$db->select_db($config['database']);

		echo "<p>Setting up basic board settings...</p>";

		$settings = file_get_contents("./resources/settings.xml");
		$parser = new XMLParser($settings);
		$parser->collapse_dups = 0;
		$tree = $parser->getTree();

		foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
		{
			$groupdata = array(
				"name" => $db->escape_string($settinggroup['attributes']['name']),
				"title" => $db->escape_string($settinggroup['attributes']['title']),
				"description" => $db->escape_string($settinggroup['attributes']['description']),
				"disporder" => intval($settinggroup['attributes']['disporder']),
				"isdefault" => $settinggroup['attributes']['isdefault']
			);
			$db->insert_query(TABLE_PREFIX."settinggroups", $groupdata);
			$gid = $db->insert_id();
			$groupcount++;
			foreach($settinggroup['setting'] as $setting)
			{
				$settingdata = array(
					"name" => $db->escape_string($setting['attributes']['name']),
					"title" => $db->escape_string($setting['title'][0]['value']),
					"description" => $db->escape_string($setting['description'][0]['value']),
					"optionscode" => $db->escape_string($setting['optionscode'][0]['value']),
					"value" => $db->escape_string($setting['settingvalue'][0]['value']),
					"disporder" => intval($setting['disporder'][0]['value']),
					"gid" => $gid
					);

				$db->insert_query(TABLE_PREFIX."settings", $settingdata);
				$settingcount++;
			}
		}

		echo "<p>Inserted $settingcount settings into $groupcount groups.</p>";
		echo "<p>Updating settings with user defined values.</p>";


		if (substr($mybb->input['bburl'], -1, 1) == "/")
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

		echo "<p>You need to create an initial administrator account for you to login and manage your copy of MyBB. Please fill in the required fields below to create this account.</p>";
	}

	echo <<<END
		<div class="border_wrapper">
			<div class="title">Administrator Account Details</div>

		<table class="general" cellspacing="0">
		<thead>
		<tr>
			<th colspan="2" class="first last">Account Details</th>
		</tr>
		</thead>
		<tr class="first">
			<td class="first"><label for="adminuser">Username:</label></td>
			<td class="alt_col last"><input type="text" class="text_input" name="adminuser" id="adminuser" value="$adminuser" autocomplete="off" /></td>
		</tr>
		<tr class="alt_row">
			<td class="first"><label for="adminpass">Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass" id="adminpass" value="" autocomplete="off"  /></td>
		</tr>
		<tr class="last">
			<td class="first"><label for="adminpass2">Retype Password:</label></td>
			<td class="alt_col last"><input type="password" class="text_input" name="adminpass2" id="adminpass2" value="" autocomplete="off"  /></td>
		</tr>
		<tr>
			<th colspan="2" class="first last">Contact Details</th>
		</tr>
		<tr class="first last">
			<td class="first"><label for="adminemail">Email Address:</label></td>
			<td class="alt_col last"><input type="text" class="text_input" name="adminemail" id="adminemail" value="$adminemail" /></td>
		</tr>
	</table>
	</div>

	<p>Once you've correctly entered the details above and are ready to proceed, click Next.</p>
END;
	$output->print_footer("final");
}

function install_done()
{
	global $output, $db, $myver, $mybb, $errors, $cache;

	if(!$mybb->input['adminuser'])
	{
		$errors[] = "You did not enter a username for your Administrator account.";
	}
	if(!$mybb->input['adminpass'])
	{
		$errors[] = "You did not enter a password for your Administrator account";
	}
	if($mybb->input['adminpass'] != $mybb->input['adminpass2'] && $mybb->input['adminpass'] != "")
	{
		$errors[] = "The passwords you entered do not match.";
	}
	if(!$mybb->input['adminemail'])
	{
		$errors[] = "You did not enter your email address for the Administrator's account.";
	}
	if(is_array($errors))
	{
		create_admin_user();
	}

	require "../inc/config.php";
	require "../inc/db_".$config['dbtype'].".php";
	$db=new databaseEngine;
	// Connect to Database
	define("TABLE_PREFIX", $config['table_prefix']);
	$db->connect($config['hostname'], $config['username'], $config['password']);
	$db->select_db($config['database']);

	ob_start();
	$output->print_header("Finish Setup", "finish");

	echo "<p>Creating Administrator account...";
	$now = time();
	$salt = random_str();
	$loginkey = generate_loginkey();
	$saltedpw = md5(md5($salt).md5($mybb->input['adminpass']));

	$newuser = array(
		"username" => $db->escape_string($mybb->input['adminuser']),
		"password" => $saltedpw,
		"salt" => $salt,
		"loginkey" => $loginkey,
		"email" => $db->escape_string($mybb->input['adminemail']),
		"usergroup" => 4,
		"regdate" => $now,
		"lastactive" => $now,
		"lastvisit" => intval($now),
		"website" => "",
		"icq" => "",
		"aim" => "",
		"yahoo" => "",
		"msn" =>"",
		"birthday" => "",
		"allownotices" => "yes",
		"hideemail" => "no",
		"emailnotify" => "no",
		"receivepms" => "yes",
		"pmpopup" => "yes",
		"pmnotify" => "yes",
		"remember" => "yes",
		"showsigs" => "yes",
		"showavatars" => "yes",
		"showquickreply" => "yes",
		"invisible" => "no",
		"style" => '0',
		"timezone" => 0,
		"dst" => "no",
		"threadmode" => "",
		"daysprune" => 0,
		"regip" => $ipaddress,
		"language" => "",
		"showcodebuttons" => 1,
		"tpp" => 0,
		"ppp" => 0,
		"referrer" => 0
		);
	$db->insert_query(TABLE_PREFIX."users", $newuser);
	$uid = $db->insert_id();

	$db->query("INSERT INTO ".TABLE_PREFIX."adminoptions VALUES ('$uid','','','1','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes','yes')");
	// Automatic Login
	mysetcookie("mybbadmin", $uid."_".$loginkey);
	mysetcookie("mybbuser", $uid."_".$loginkey);
	ob_end_flush();
	echo "done</p>";


	// Make fulltext columns if supported
	if($db->supports_fulltext(TABLE_PREFIX."threads"))
	{
		$db->create_fulltext_index(TABLE_PREFIX."threads", "subject");
	}
	if($db->supports_fulltext_boolean(TABLE_PREFIX."posts"))
	{
		$db->create_fulltext_index(TABLE_PREFIX."posts", "message");
		$update_data = array(
			"value" => "yes"
		);
		$db->update_query(TABLE_PREFIX."settings", $update_data, "name='searchtype'");
		write_settings();
	}
	
	// Register a shutdown function which actually tests if this functionality is working
	add_shutdown('test_shutdown_function');

	echo "<p>Building data cache's...";
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
	$cache->updatemycode();
	echo "done</p>";

	echo "<p class=\"success\">Your copy of MyBB has successfully been installed and configured correctly.</p>";
	echo "<p>The MyBB Group thanks you for your support in installing our software and we hope to see you around the community forums if you need help or wish to become a part of the MyBB community.</p>";

	$written = 0;
	if(is_writable("./"))
	{
		$lock = @fopen("./lock", "w");
		$written = @fwrite($lock, "1");
		@fclose($lock);
		if($written)
		{
			echo "<p>Your installer has been locked. To unlock the installer please delete the 'lock' file in this directory.</p><p>You may now proceed to your new copy of <a href=\"../index.php\">MyBB</a> or its <a href=\"../admin/index.php\">Admin Control Panel</a>.</p>";
		}
	}
	if(!$written)
	{
		echo "<p><b><font color=\"red\">Please remove this directory before exploring your copy of MyBB.</font></b></p>";
	}
	$output->print_footer("");
}

function write_settings()
{
	global $db, $cwd;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings ORDER BY title ASC");
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = $db->escape_string($setting['value']);
		$settings .= "\$settings['".$setting['name']."'] = \"".$setting['value']."\";\n";
	}
	if($settings != '')
	{
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?>";
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
