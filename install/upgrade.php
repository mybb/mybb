<?php
/**
 * MyBulletinBoard (MyBB)
 * Copyright © 2004 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 */
error_reporting(E_ALL & ~E_NOTICE);

// The version number of MyBB we are installing
$myver = "1.0 Preview Release 2";

require "../inc/class_core.php";
$mybb = new MyBB;

// Include the files necessary for installation
require "../inc/class_timers.php";
require "../inc/constants.php";
require "../inc/functions.php";
require "../admin/adminfunctions.php";
require "../inc/class_xml.php";
require "../inc/config.php";
require "../inc/db_$config[dbtype].php";

// Include the installation resources
require "./resources/output.php";
$output = new installerOutput;
$output->script = "upgrade.php";

$db=new bbDB;
// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config[hostname], $config[username], $config[password]);
$db->select_db($config[database]);



// Set if we need to revert templates and settings for this version
$reverttemplates = 1;
$revertalltemplates = 1;
$rebuilddbsettings = 1;
$rebuildsettingsfile = 1;
$revertallthemes = 1;

$valid = 0;
/*

NOTE: This code has been commented out because of the new login system.

if($do == "login")
{
	$query = $db->query("SELECT uid, password, usergroup, salt, loginkey FROM ".TABLE_PREFIX."users WHERE username='$adminuser'");
	$failcheck = 1;
	$md5pw = md5($adminpass);
}
else
{
	$query = $db->query("SELECT uid, password, usergroup FROM ".TABLE_PREFIX."users WHERE uid='$mybbadmin[uid]' AND password='$mybbadmin[password]'");
}
$user = $db->fetch_array($query);
$query = $db->query("SELECT cancp FROM ".TABLE_PREFIX."usergroups WHERE gid='".$user['usergroup']."'");
$ausergroup = $db->fetch_array($query);
if($ausergroup[cancp] == "yes")
{
	setcookie("mybbadmin[uid]", $user['uid']);
	setcookie("mybbadmin[password]", $user['password']);
	$valid = 1;
}

if($valid != 1)
{
	$output->print_header("Please Login", 0);
	$contents = "<p>To continue with the upgrade process we need to verify that you are indeed an administrator of these forums. Please enter your username and password below.</p>\n";
	$contents .= "<form method=\"post\" action=\"upgrade.php\"><input type=\"hidden\" name=\"do\" value=\"login\" /><p><b>Username: </b><br /><input type=\"text\" name=\"adminuser\" /></p><p><b>Password: </b><br /><input type=\"password\" name=\"adminpass\" /></p><p><input type=\"submit\" value=\"Login\"></form>\n";
	$output->print_contents($contents);
	$output->print_footer("intro");
	exit;
}
*/
if(file_exists("lock"))
{
	$output->print_error("The installer is currently locked, please remove 'lock' from the install directory to continue");
}
else
{
	
	if(!$mybb->input['action'] || $mybb->input['action'] == "intro")
	{
		$output->print_header("MyBB Upgrade Script");
		$dh = opendir("./resources");
		while(($file = readdir($dh)) !== false)
		{
			if(preg_match("#upgrade([0-9]+).php$#i", $file, $match))
			{
				$upgradescripts[$match[1]] = $file;
			}
		}
		closedir($dh);
		foreach($upgradescripts as $key => $file)
		{
			$upgradescript = implode("", file("./resources/$file"));
			preg_match("#Upgrade Script:(.*)#i", $upgradescript, $verinfo);
			preg_match("#upgrade([0-9]+).php$#i", $file, $keynum);
			if(trim($verinfo[1]))
			{
				if(!$upgradescripts[$key+1])
				{
					$vers .= "<option value=\"$keynum[1]\" selected=\"selected\">$verinfo[1]</option>\n";
				}
				else
				{
					$vers .= "<option value=\"$keynum[1]\">$verinfo[1]</option>\n";
				}
			}
		}
		unset($upgradescripts);
		unset($upgradescript);

		$output->print_contents("<p>Welcome to the upgrade wizard for MyBulletinBoard $myver.</p><p>Before you continue, please make sure you know which version of MyBB you were previously running as you will need to select it below.</p><p>We recommend that you also do a complete backup of your database before attempting to upgrade so if something goes wrong you can easily revert back to the previous version.</p></p><p>Once you're ready, please select your old version below and click next to continue.</p><p><select name=\"from\">$vers</select>");
		$output->print_footer("doupgrade");
	}
	elseif($mybb->input['action'] == "doupgrade")
	{
		$runfunction = next_function($mybb->input['from']);
	}
	elseif($mybb->input['action'] == "templates")
	{
		$runfunction = "upgradethemes";
	}
	elseif($mybb->input['action'] == "rebuildsettings")
	{
		$runfunction = "rebuildsettings";
	}
	elseif($mybb->input['action'] == "buildcaches")
	{
		$runfunction = "buildcaches";
	}
	elseif($mybb->input['action'] == "finished")
	{
		$runfunction = "upgradedone";
	}
	else // Busy running modules, come back later
	{
		$bits = explode("_", $mybb->input['action'], 2);
		if($bits[1]) // We're still running a module
		{
			$from = $bits[0];
			$runfunction = next_function($bits[0], $bits[1]);

		}
	}
	if(function_exists($runfunction))
	{
		$runfunction();
	}
}

function upgradethemes()
{
	global $output, $db, $reverttemplates, $revertalltemplates, $rebuilddbsettings, $rebuildsettingsfile, $revertallthemes;

	if($revertalltemplates)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templates;");
		$db->query("CREATE TABLE mybb_templates (
		  tid int unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  template text NOT NULL,
		  sid int(10) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
	}
	else
	{
		$db->query("DELETE FROM ".TABLE_PREFIX."templates WHERE sid='-2'");
	}

	if($revertallthemes)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."themes");
		$db->query("CREATE TABLE mybb_themes (
		  tid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  pid smallint unsigned NOT NULL default '0',
		  def smallint(1) NOT NULL default '0',
		  css text NOT NULL,
		  cssbits text NOT NULL,
		  themebits text NOT NULL,
		  extracss text NOT NULL,
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
		$db->query("INSERT INTO ".TABLE_PREFIX."themes (tid,name,pid) VALUES (NULL,'MyBB Master Style','0')");
		$db->query("INSERT INTO ".TABLE_PREFIX."themes (tid,name,pid,def) VALUES (NULL,'MyBB Default','1','1')");
		$sid = $db->insert_id();
		$db->query("UPDATE ".TABLE_PREFIX."users SET style='$tid'");
	}
	$sid = -2;

	$arr = @file("./resources/mybb_theme.xml");
	$contents = @implode("", $arr);

	$parser = new XMLParser($contents);
	$tree = $parser->getTree();

	$theme = $tree['theme'];
	$css = killtags($theme['cssbits']);
	$themebits = killtags($theme['themebits']);
	$templates = $theme['templates']['template'];
	$themebits['templateset'] = $templateset;
	foreach($templates as $template)
	{
		$templatename = $template['attributes']['name'];
		$templatevalue = addslashes($template['value']);
		$db->query("INSERT INTO ".TABLE_PREFIX."templates VALUES ('','$templatename','$templatevalue','$sid')");
	}
	update_theme(1, 0, $themebits, $css, 0);

	$output->print_header("Templates Reverted");
	$output->print_contents("<p>All of the templates have successfully been reverted to the new ones contained in this release. Please press next to continue with the upgrade process.</p>");
	if($rebuilddbsettings || $rebuildsettingsfile)
	{
		$output->print_footer("rebuildsettings");
	}
	else
	{
		$output->print_footer("buildcaches");
	}
}

function rebuildsettings()
{
	global $db, $output, $rebuilddbsettings, $rebuildsettingsfile;

	if($rebuilddbsettings)
	{
		require "../inc/settings.php";
		while(list($key, $val) = each($settings)) {
			$db->query("UPDATE ".TABLE_PREFIX."settings SET value='$val' WHERE name='$key'");
		}
	}
	unset($settings);
	if($rebuildsettingsfile)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings ORDER BY title ASC");
		while($setting = $db->fetch_array($query)) {
			$setting[value] = addslashes($setting[value]);
			$settings .= "\$settings[".$setting['name']."] = \"".$setting['value']."\";\n";
		}
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?>";
		$file = fopen("../inc/settings.php", "w");
		fwrite($file, $settings);
		fclose($file);
	}

	$output->print_header("Settings Rebuilt");
	$output->print_contents("<p>The board settings have been rebuilt.</p><p>To finalise the upgrade, please click next below to continue.</p>");
	$output->print_footer("buildcaches");
}
function buildcaches()
{
	global $db, $output, $myver, $cache;

	$output->print_header("Data Cache Building");

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

	$output->print_contents("$contents<p>Please press next to continue</p>");
	$output->print_footer("finished");
}

function upgradedone()
{
	global $db, $output, $myver;
	
	$output->print_header("Upgrade Complete");
	if(is_writable("./"))
	{
		$lock = @fopen("./lock", "w");
		$written = @fwrite($lock, "1");
		@fclose($lock);
		if($written)
		{
			$lock_note = "<p>Your installer has been locked. To unlock the installer please delete the 'lock' file in this directory.</p><p>You may now proceed to your upgraded copy of <a href=\"../index.php\">MyBB</a> or its <a href=\"../admin/index.php\">Admin Control Panel</a>.</p>";
		}
	}
	if(!$written)
	{
		$lock_note = "<p><b><font color=\"red\">Please remove this directory before exploring your upgraded MyBB.</font></b></p>";
	}
	$output->print_contents("<p>Congratulations, your copy of MyBB has successfully been updated to $myver.</p>$lock_note");
	$output->print_footer();
}

function whatsnext()
{
	global $output, $db, $reverttemplates, $rebuilddbsettings, $rebuildsettingsfile;

	if($reverttemplates)
	{
		$output->print_header("Template Reversion Warning");
		$output->print_contents("<p>All necessary database modifications have successfully been made to upgrade your board.</p><p>This upgrade requires all templates to be reverted to the new ones contained in the package so please back up any custom templates you have made before clicking next.");
		$output->print_footer("templates");
	}
	if($rebuilddbsettings || $rebuildsettingsfile)
	{
		rebuildsettings();
	}
}

function next_function($from, $func="dbchanges")
{
	global $oldvers;
	require_once "./resources/upgrade".$from.".php";
	if(function_exists("upgrade".$from."_".$func))
	{
		$function = "upgrade".$from."_".$func;
	}
	else
	{
		$from = $from+1;
		if($oldvers[$from])
		{
			$function = next_function($from);
		}
	}

	if(!$function)
	{
		$function = "whatsnext";
	}
	return $function;
}
?>