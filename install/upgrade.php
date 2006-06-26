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
require "../inc/class_xml.php";
require "../inc/config.php";
require "../inc/db_".$config['dbtype'].".php";

// If there's a custom admin dir, use it.
if(isset($config['admindir']))
{
	require "../".$config['admindir']."/adminfunctions.php";
}
else
{
	require "../admin/adminfunctions.php";
}

// Include the necessary contants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

// Include the installation resources
require "./resources/output.php";
$output = new installerOutput;
$output->script = "upgrade.php";

$db=new databaseEngine;
// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);


if(file_exists("lock"))
{
	$output->print_error("The installer is currently locked, please remove 'lock' from the install directory to continue");
}
else
{

	$output->steps = array("Upgrade Process");
	
	if(!$mybb->input['action'] || $mybb->input['action'] == "intro")
	{
		$output->print_header("MyBB Upgrade Script");

		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."upgrade_data");  
		$db->query("CREATE TABLE ".TABLE_PREFIX."upgrade_data ( 
			title varchar(30) NOT NULL,  
			contents text NOT NULL,  
			PRIMARY KEY(title)  
		);");
		
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
			$upgradescript = file_get_contents("./resources/$file");
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

		$output->print_contents("<p>Welcome to the upgrade wizard for MyBB $myver.</p><p>Before you continue, please make sure you know which version of MyBB you were previously running as you will need to select it below.</p><p><strong>We recommend that you also do a complete backup of your database before attempting to upgrade</strong> so if something goes wrong you can easily revert back to the previous version.</p></p><p>Once you're ready, please select your old version below and click next to continue.</p><p><select name=\"from\">$vers</select>");
		$output->print_footer("doupgrade");
	}
	elseif($mybb->input['action'] == "doupgrade")
	{
		add_upgrade_store("startscript", $mybb->input['from']);
		$runfunction = next_function($mybb->input['from']);
	}
	$currentscript = get_upgrade_store("currentscript");
	$system_upgrade_detail = get_upgrade_store("upgradedetail");

	if($mybb->input['action'] == "templates")
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
	// Fetch current script we're in

	if(function_exists($runfunction))
	{
		$runfunction();
	}
}

function upgradethemes()
{
	global $output, $db, $system_upgrade_detail;

	$output->print_header("Templates Reverted");

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templates;");
		$db->query("CREATE TABLE ".TABLE_PREFIX."templates (
		  tid int unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  template text NOT NULL,
		  sid int(10) NOT NULL default '0',
		  version varchar(20) NOT NULL default '0',
		  status varchar(10) NOT NULL default '',
		  dateline int(10) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
	}

	if($system_upgrade_detail['revert_all_themes'] > 0)
	{
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."themes");
		$db->query("CREATE TABLE ".TABLE_PREFIX."themes" (
		  tid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  pid smallint unsigned NOT NULL default '0',
		  def smallint(1) NOT NULL default '0',
		  css text NOT NULL default '',
		  cssbits text NOT NULL default '',
		  themebits text NOT NULL default '',
		  extracss text NOT NULL default '',
		  allowedgroups text NOT NULL default '',
		  csscached bigint(30) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
		$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid) VALUES ('MyBB Master Style','0')");
		$db->query("INSERT INTO ".TABLE_PREFIX."themes (name,pid,def) VALUES ('MyBB Default','1','1')");
		$sid = $db->insert_id();
		$db->query("UPDATE ".TABLE_PREFIX."users SET style='$sid'");
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templatesets;");
		$db->query("CREATE TABLE ".TABLE_PREFIX."templatesets (
		  sid smallint unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");
		$db->query("INSERT INTO ".TABLE_PREFIX."templatesets (title) VALUES ('Default Templates')");
	}
	$sid = -2;

	$arr = @file("./resources/mybb_theme.xml");
	$contents = @implode("", $arr);

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];
	$css = kill_tags($theme['cssbits']);
	$themebits = kill_tags($theme['themebits']);
	$templates = $theme['templates']['template'];
	$themebits['templateset'] = $templateset;
	$newcount = 0;
	foreach($templates as $template)
	{
		$templatename = $template['attributes']['name'];
		$templateversion = $template['attributes']['version'];
		$templatevalue = $db->escape_string($template['value']);
		$time = time();
		$query = $db->query("SELECT tid FROM ".TABLE_PREFIX."templates WHERE sid='-2' AND title='$templatename'");
		$oldtemp = $db->fetch_array($query);
		if($oldtemp['tid'])
		{
			$db->query("UPDATE ".TABLE_PREFIX."templates SET template='$templatevalue', version='$templateversion', dateline='$time' WHERE title='$templatename' AND sid='-2'");
		}
		else
		{
			$db->query("INSERT INTO ".TABLE_PREFIX."templates (title,template,sid,version,status,dateline) VALUES ('$templatename','$templatevalue','$sid','$templateversion','','$time')");
			$newcount++;
		}
	}
	update_theme(1, 0, $themebits, $css, 0);
	$output->print_contents("<p>All of the templates have successfully been reverted to the new ones contained in this release. Please press next to continue with the upgrade process.</p>");
	$output->print_footer("rebuildsettings");
}

function rebuildsettings()
{
	global $db, $output, $system_upgrade_detail;

	$synccount = sync_settings($system_upgrade_detail['revert_all_settings']);

	$output->print_header("Settings Synchronisation");
	$output->print_contents("<p>The board settings have been synchronised with the latest in MyBB.</p><p>".$synccount[1]." new settings inserted along with ".$synccount[0]." new setting groups.</p><p>To finalise the upgrade, please click next below to continue.</p>");
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
	$cache->updatemycode();
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
			$lock_note = "<p>Your installer has been locked. To unlock the installer please delete the 'lock' file in this directory.</p><p>You may now proceed to your upgraded copy of <a href=\"../index.php\">MyBB</a> or its <a href=\"../".$config['admindir']."/index.php\">Admin Control Panel</a>.</p>";
		}
	}
	if(!$written)
	{
		$lock_note = "<p><b><font color=\"red\">Please remove this directory before exploring your upgraded MyBB.</font></b></p>";
	}
	$output->print_contents("<p>Congratulations, your copy of MyBB has successfully been updated to $myver.</p>$lock_note<p><strong>What's Next?</strong></p><ul><li>Please use the 'Find Updated Templates' tool in the Admin CP to find customised templates updated during this upgrade process. Edit them to contain the changes or revert them to originals.</li><li>Ensure that your board is still fully functional.</li></ul>");
	$output->print_footer();
}

function whatsnext()
{
	global $output, $db, $system_upgrade_detail;

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$output->print_header("Template Reversion Warning");
		$output->print_contents("<p>All necessary database modifications have successfully been made to upgrade your board.</p><p>This upgrade requires all templates to be reverted to the new ones contained in the package so please back up any custom templates you have made before clicking next.");
		$output->print_footer("templates");
	}
	else
	{
		upgradethemes();
	}
}

function next_function($from, $func="dbchanges")
{
	global $oldvers, $system_upgrade_detail, $currentscript;

	load_module("upgrade".$from.".php");
	if(function_exists("upgrade".$from."_".$func))
	{
		$function = "upgrade".$from."_".$func;
	}
	else
	{
		$from = $from+1;
		if(file_exists("./resources/upgrade".$from.".php"))
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

function load_module($module)
{
	global $system_upgrade_detail, $currentscript;
	require_once "./resources/".$module;
	if($currentscript != $module)
	{
		foreach($upgrade_detail as $key => $val)
		{
			if(!$system_upgrade_detail[$key] || $val > $system_upgrade_detail[$key])
			{
				$system_upgrade_detail[$key] = $val;
			}
		}
		add_upgrade_store("upgradedetail", $system_upgrade_detail);
		add_upgrade_store("currentscript", $module);
	}
}

function get_upgrade_store($title)  
{  
	global $db;  
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."upgrade_data WHERE title='".$db->escape_string($title)."'");  
	$data = $db->fetch_array($query);  
	return unserialize($data['contents']);  
}

function add_upgrade_store($title, $contents)  
{  
	global $db;  
	$db->query("REPLACE INTO ".TABLE_PREFIX."upgrade_data (title,contents) VALUES ('".$db->escape_string($title)."', '".$db->escape_string(serialize($contents))."')");  
}

function sync_settings($redo=0)
{
	global $db;
	$settingcount = $groupcount = 0;
	if($redo == 2)
	{
		$db->query("DROP TABLE ".TABLE_PREFIX."settinggroups");
		$db->query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
		  gid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  title varchar(220) NOT NULL default '',
		  description text NOT NULL default '',
		  disporder smallint unsigned NOT NULL default '0',
		  isdefault char(3) NOT NULL default '',
		  PRIMARY KEY  (gid)
		) TYPE=MyISAM;");
		
		$db->query("DROP TABLE ".TABLE_PREFIX."settings");

		$db->query("CREATE TABLE ".TABLE_PREFIX."settings (
		  sid smallint(6) NOT NULL auto_increment,
		  name varchar(120) NOT NULL default '',
		  title varchar(120) NOT NULL default '',
		  description text NOT NULL,
		  optionscode text NOT NULL,
		  value text NOT NULL,
		  disporder smallint(6) NOT NULL default '0',
		  gid smallint(6) NOT NULL default '0',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");
	}
	else
	{
		$query = $db->query("SELECT name FROM ".TABLE_PREFIX."settings");
		while($setting = $db->fetch_array($query))
		{
			$settings[$setting['name']] = 1;
		}
		$query = $db->query("SELECT name,title,gid FROM ".TABLE_PREFIX."settinggroups");
		while($group = $db->fetch_array($query))
		{
			$settinggroups[$group['name']] = $group['gid'];
		}
	}
	$settings_xml = file_get_contents("./resources/settings.xml");
	$parser = new XMLParser($settings_xml);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
	{
		$groupdata = array(
			"name" => $db->escape_string($settinggroup['attributes']['name']),
			"title" => $db->escape_string($settinggroup['attributes']['title']),
			"description" => $db->escape_string($settinggroup['attributes']['description']),
			"disporder" => intval($settinggroup['attributes']['disporder']),
			"isdefault" => $settinggroup['attributes']['isdefault']
		);
		if(!$settinggroups[$settinggroup['attributes']['key']] || $redo == 2)
		{
			$db->insert_query(TABLE_PREFIX."settinggroups", $groupdata);
			$gid = $db->insert_id();
			$groupcount++;
		}
		else
		{
			$gid = $settinggroups[$settinggroup['attributes']['name']];
			$db->insert_query(TABLE_PREFIX."settinggroups", $groupdata, "gid='{$gid}");
		}
		if(!$gid)
		{
			continue;
		}
		foreach($settinggroup['setting'] as $setting)
		{
			$settingdata = array(
				"name" => $db->escape_string($setting['attributes']['name']),
				"title" => $db->escape_string($setting['title'][0]['value']),
				"description" => $db->escape_string($setting['description'][0]['value']),
				"optionscode" => $db->escape_string($setting['optionscode'][0]['value']),
				"disporder" => intval($setting['disporder'][0]['value']),
				"gid" => $gid
			);
			if(!$settings[$setting['attributes']['name']] || $redo == 2)
			{
				$settingdata['value'] = $db->escape_string($setting['settingvalue'][0]['value']);
				$db->insert_query(TABLE_PREFIX."settings", $settingdata);
				$settingcount++;
			}
			else
			{
				$name = $db->escape_string($setting['attributes']['name']);
				$db->update_query(TABLE_PREFIX."settings", $settingdata, "name='{$name}'");
			}
		}
	}
	if($redo == 1)
	{
		require "../inc/settings.php";
		foreach($settings as $key => $val)
		{
			$db->update_query(TABLE_PREFIX."settings", array('value' => $val), "name='$key'");
		}
	}
	unset($settings);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."settings ORDER BY title ASC");
	while($setting = $db->fetch_array($query)) 
	{
		$setting['value'] = $db->escape_string($setting['value']);
		$settings .= "\$settings[".$setting['name']."] = \"".$setting['value']."\";\n";
	}
	$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?>";
	$file = fopen("../inc/settings.php", "w");
	fwrite($file, $settings);
	fclose($file);
	return array($groupcount, $settingcount);
}
?>