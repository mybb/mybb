<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */
error_reporting(E_ALL & ~E_NOTICE);

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__)."/");
define('IN_MYBB', 1);

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

// Include the files necessary for installation
require_once MYBB_ROOT."inc/class_timers.php";
require_once MYBB_ROOT."inc/functions.php";
require_once MYBB_ROOT."inc/class_xml.php";
require_once MYBB_ROOT."inc/config.php";
require_once MYBB_ROOT."inc/db_".$config['dbtype'].".php";
require_once MYBB_ROOT.'inc/class_language.php';

$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources/');
$lang->load('language');

// If there's a custom admin dir, use it.

// Legacy for those boards trying to upgrade from an older version
if(isset($config['admindir']))
{
	require_once MYBB_ROOT.$config['admindir']."/adminfunctions.php";
}
// Current
else if(isset($config['admin_dir']))
{
	require_once MYBB_ROOT.$config['admin_dir']."/adminfunctions.php";
}
// No custom set
else
{
	require_once MYBB_ROOT."admin/adminfunctions.php";
}

// Include the necessary contants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxreputationsday", "attachquota");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array("canview", "candlattachments", "canpostthreads", "canpostreplys", "canpostattachments", "canratethreads", "caneditposts", "candeleteposts", "candeletethreads", "caneditattachments", "canpostpolls", "canvotepolls", "cansearch");

// Include the installation resources
require_once INSTALL_ROOT."resources/output.php";
$output = new installerOutput;
$output->script = "upgrade.php";
$output->title = "MyBB Upgrade Wizard";

$db = new databaseEngine;
// Connect to Database
define("TABLE_PREFIX", $config['table_prefix']);
$db->connect($config['hostname'], $config['username'], $config['password']);
$db->select_db($config['database']);
$db->set_table_prefix(TABLE_PREFIX);


if(file_exists("lock"))
{
	$output->print_error($lang->locked);
}
else
{

	$output->steps = array($lang->upgrade);

	if(!$mybb->input['action'] || $mybb->input['action'] == "intro")
	{
		$output->print_header();

		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."upgrade_data");
		$db->query("CREATE TABLE ".TABLE_PREFIX."upgrade_data (
			title varchar(30) NOT NULL,
			contents text NOT NULL,
			PRIMARY KEY(title)
		);");

		$dh = opendir(INSTALL_ROOT."resources");
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
			$upgradescript = file_get_contents(INSTALL_ROOT."resources/$file");
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

		$output->print_contents(sprintf($lang->upgrade_welcome, $mybb->version)."<p><select name=\"from\">$vers</select>");
		$output->print_footer("doupgrade");
	}
	elseif($mybb->input['action'] == "doupgrade")
	{
		require_once INSTALL_ROOT."resources/upgrade".intval($mybb->input['from']).".php";
		if($db->table_exists("datacache") && $upgrade_detail['requires_deactivated_plugins'] == 1 && $mybb->input['donewarning'] != "true")
		{
			require_once MYBB_ROOT."inc/class_datacache.php";
			$cache = new datacache;
			$plugins = $cache->read('plugins', true);
			if(!empty($plugins['active']))
			{
				$output->print_header();
				$lang->plugin_warning = "<input type=\"hidden\" name=\"from\" value=\"".intval($mybb->input['from'])."\" />\n<input type=\"hidden\" name=\"donewarning\" value=\"true\" />\n<div class=\"error\"><strong><span style=\"color: red\">Warning:</span></strong> <p>There are still ".count($plugins['active'])." plugin(s) active. Active plugins can sometimes cause problems during an upgrade procedure or may break your forum afterward. It is <strong>strongly</strong> reccommended that you deactivate your plugins before continuing.</p></div> <br />";
				$output->print_contents(sprintf($lang->plugin_warning, $mybb->version));
				$output->print_footer("doupgrade");
			}
			else
			{
				add_upgrade_store("startscript", $mybb->input['from']);
				$runfunction = next_function($mybb->input['from']);
			}
		}
		else
		{
			add_upgrade_store("startscript", $mybb->input['from']);
			$runfunction = next_function($mybb->input['from']);
		}
	}
	$currentscript = get_upgrade_store("currentscript");
	$system_upgrade_detail = get_upgrade_store("upgradedetail");

	if($mybb->input['action'] == "templates")
	{
		$runfunction = "upgradethemes";
	}
	elseif($mybb->input['action'] == "rebuildsettings")
	{
		$runfunction = "buildsettings";
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
	global $output, $db, $system_upgrade_detail, $lang;

	$output->print_header($lang->upgrade_templates_reverted);

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
		$db->query("CREATE TABLE ".TABLE_PREFIX."themes (
		  tid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  pid smallint unsigned NOT NULL default '0',
		  def smallint(1) NOT NULL default '0',
		  css text NOT NULL,
		  cssbits text NOT NULL,
		  themebits text NOT NULL,
		  extracss text NOT NULL,
		  allowedgroups text NOT NULL,
		  csscached bigint(30) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) TYPE=MyISAM;");
		
		$insert_array = array(
			'name' => 'MyBB Master Style',
			'pid' => 0,
			'css' => '',
			'cssbits' => '',
			'themebits' => '',
			'extracss' => '',
			'allowedgroups' => ''
		);
		$db->insert_query("themes", $insert_array);
		
		$insert_array = array(
			'name' => 'MyBB Default',
			'pid' => 1,
			'def' => 1,
			'css' => '',
			'cssbits' => '',
			'themebits' => '',
			'extracss' => '',
			'allowedgroups' => ''
		);
		$db->insert_query("themes", $insert_array);
		$sid = $db->insert_id();
		
		$db->update_query("users", array('style' => $sid));
		$db->update_query("forums", array('style' => 0));
		
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."templatesets;");
		$db->query("CREATE TABLE ".TABLE_PREFIX."templatesets (
		  sid smallint unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  PRIMARY KEY  (sid)
		) TYPE=MyISAM;");
		
		$db->insert_query("templatesets", array('title' => 'Default Templates'));
	}
	$sid = -2;

	$arr = @file(INSTALL_ROOT."resources/mybb_theme.xml");
	$contents = @implode("", $arr);

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];
	$css = kill_tags($theme['cssbits']);
	$themebits = kill_tags($theme['themebits']);
	$templates = $theme['templates']['template'];
	$themebits['templateset'] = 1;
	$newcount = 0;
	foreach($templates as $template)
	{
		$templatename = $template['attributes']['name'];
		$templateversion = $template['attributes']['version'];
		$templatevalue = $db->escape_string($template['value']);
		$time = time();
		$query = $db->simple_select("templates", "tid", "sid='-2' AND title='$templatename'");
		$oldtemp = $db->fetch_array($query);
		if($oldtemp['tid'])
		{
			$update_array = array(
				'template' => $templatevalue,
				'version' => templateversion,
				'dateline' => $time
			);
			$db->update_query("templates", $update_array, "title='$templatename' AND sid='-2'");
		}
		else
		{
			$insert_array = array(
				'title' => $templatename,
				'template' => $templatevalue,
				'sid' => $sid,
				'version' => $templateversion,
				'dateline' => $time
			);			
			
			$db->insert_query("templates", $insert_array);
			++$newcount;
		}
	}
	update_theme(1, 0, $themebits, $css, 0);
	$output->print_contents($lang->upgrade_templates_reverted_success);
	$output->print_footer("rebuildsettings");
}

function buildsettings()
{
	global $db, $output, $system_upgrade_detail, $lang;

	if(!is_writable(MYBB_ROOT."inc/settings.php"))
	{
		$output->print_header("Rebuilding Settings");
		echo "<p><div class=\"error\"><span style=\"color: red; font-weight: bold;\">Error: Unable to open inc/settings.php</span><h3>Before the upgrade process can continue, you need to changes the permissions of inc/settings.php so it is writable.</h3></div></p>";
		$output->print_footer("rebuildsettings");
		exit;
	}
	$synccount = sync_settings($system_upgrade_detail['revert_all_settings']);

	$output->print_header($lang->upgrade_settings_sync);
	$output->print_contents(sprintf($lang->upgrade_settings_sync_success, $synccount[1], $synccount[0]));
	$output->print_footer("buildcaches");
}

function buildcaches()
{
	global $db, $output, $cache, $lang;

	$output->print_header($lang->upgrade_datacache_building);

	$contents .= $lang->upgrade_building_datacache;
	require_once MYBB_ROOT."inc/class_datacache.php";
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
	$contents .= $lang->done."</p>";

	$output->print_contents("$contents<p>".$lang->upgrade_continue."</p>");
	$output->print_footer("finished");
}

function upgradedone()
{
	global $db, $output, $mybb, $lang, $config;

	$output->print_header("Upgrade Complete");
	if(is_writable("./"))
	{
		$lock = @fopen("./lock", "w");
		$written = @fwrite($lock, "1");
		@fclose($lock);
		if($written)
		{
			$lock_note = sprintf($lang->upgrade_locked, $config['admin_dir']);
		}
	}
	if(!$written)
	{
		$lock_note = "<p><b><span style=\"color: red;\">".$lang->upgrade_removedir."</span></b></p>";
	}
	$output->print_contents(sprintf($lang->upgrade_congrats, $mybb->version, $lock_note));
	$output->print_footer();
}

function whatsnext()
{
	global $output, $db, $system_upgrade_detail, $lang;

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$output->print_header($lang->upgrade_template_reversion);
		$output->print_contents($lang->upgrade_template_reversion_success);
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
		if(file_exists(INSTALL_ROOT."resources/upgrade".$from.".php"))
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
	global $system_upgrade_detail, $currentscript, $upgrade_detail;
	require_once INSTALL_ROOT."resources/".$module;
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
	
	$query = $db->simple_select("upgrade_data", "*", "title='".$db->escape_string($title)."'");
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
		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."settinggroups");
		$db->query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
		  gid smallint unsigned NOT NULL auto_increment,
		  name varchar(100) NOT NULL default '',
		  title varchar(220) NOT NULL default '',
		  description text NOT NULL,
		  disporder smallint unsigned NOT NULL default '0',
		  isdefault char(3) NOT NULL default '',
		  PRIMARY KEY  (gid)
		) TYPE=MyISAM;");

		$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."settings");

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
		$query = $db->simple_select("settings", "name");
		while($setting = $db->fetch_array($query))
		{
			$settings[$setting['name']] = 1;
		}
		
		$query = $db->simple_select("settinggroups", "name,title,gid");
		while($group = $db->fetch_array($query))
		{
			$settinggroups[$group['name']] = $group['gid'];
		}
	}
	$settings_xml = file_get_contents(INSTALL_ROOT."resources/settings.xml");
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
		if(!$settinggroups[$settinggroup['attributes']['name']] || $redo == 2)
		{
			$db->insert_query("settinggroups", $groupdata);
			$gid = $db->insert_id();
			++$groupcount;
		}
		else
		{
			$gid = $settinggroups[$settinggroup['attributes']['name']];
			$db->update_query("settinggroups", $groupdata, "gid='{$gid}'");
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
				$db->insert_query("settings", $settingdata);
				$settingcount++;
			}
			else
			{
				$name = $db->escape_string($setting['attributes']['name']);
				$db->update_query("settings", $settingdata, "name='{$name}'");
			}
		}
	}
	if($redo >= 1)
	{
		require MYBB_ROOT."inc/settings.php";
		foreach($settings as $key => $val)
		{
			$db->update_query("settings", array('value' => $db->escape_string($val)), "name='$key'");
		}
	}
	unset($settings);
	$query = $db->simple_select("settings", "*", "", array('order_by' => 'title'));
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['{$setting['name']}'] = \"".$setting['value']."\";\n";
	}
	$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n?>";
	$file = fopen(MYBB_ROOT."inc/settings.php", "w");
	fwrite($file, $settings);
	fclose($file);
	return array($groupcount, $settingcount);
}

function write_settings()
{
	global $db;
	$query = $db->simple_select("settings", "*", "", array('order_by' => 'title'));
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = $db->escape_string($setting['value']);
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
?>