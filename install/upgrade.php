<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__)."/");
define("TIME_NOW", time());
define('IN_MYBB', 1);
define("IN_UPGRADE", 1);

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MYBB_ROOT.'inc/class_error.php';
$error_handler = new errorHandler();

require_once MYBB_ROOT."inc/functions.php";

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

require_once MYBB_ROOT."inc/config.php";

$orig_config = $config;

if(!is_array($config['database']))
{
	$config['database'] = array(
		"type" => $config['dbtype'],
		"database" => $config['database'],
		"table_prefix" => $config['table_prefix'],
		"hostname" => $config['hostname'],
		"username" => $config['username'],
		"password" => $config['password'],
		"encoding" => $config['db_encoding'],
	);
}
$mybb->config = &$config;

// Include the files necessary for installation
require_once MYBB_ROOT."inc/class_timers.php";
require_once MYBB_ROOT."inc/class_xml.php";
require_once MYBB_ROOT.'inc/class_language.php';

$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources/');
$lang->load('language');

// If we're upgrading from an SQLite installation, make sure we still work.
if($config['database']['type'] == 'sqlite3' || $config['database']['type'] == 'sqlite2')
{
	$config['database']['type'] = 'sqlite';
}

// Load DB interface
require_once MYBB_ROOT."inc/db_base.php";

require_once MYBB_ROOT."inc/db_{$config['database']['type']}.php";
switch($config['database']['type'])
{
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	default:
		$db = new DB_MySQL;
}

// Connect to Database
define('TABLE_PREFIX', $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

// Load Settings
if(file_exists(MYBB_ROOT."inc/settings.php"))
{
	require_once MYBB_ROOT."inc/settings.php";
}

if(!file_exists(MYBB_ROOT."inc/settings.php") || !$settings)
{
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);

		$query = $db->simple_select("settings", "value, name", "", $options);

		$settings = array();
		while($setting = $db->fetch_array($query))
		{
			$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
			$settings[$setting['name']] = $setting['value'];
		}
	}
}

$settings['wolcutoff'] = $settings['wolcutoffmins']*60;
$settings['bbname_orig'] = $settings['bbname'];
$settings['bbname'] = strip_tags($settings['bbname']);

// Fix for people who for some specify a trailing slash on the board URL
if(substr($settings['bburl'], -1) == "/")
{
	$settings['bburl'] = my_substr($settings['bburl'], 0, -1);
}

$mybb->settings = &$settings;
$mybb->parse_cookies();

require_once MYBB_ROOT."inc/class_datacache.php";
$cache = new datacache;

// Load cache
$cache->cache();

$mybb->cache = &$cache;

require_once MYBB_ROOT."inc/class_session.php";
$session = new session;
$session->init();
$mybb->session = &$session;

// Include the necessary contants for installation
$grouppermignore = array("gid", "type", "title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$groupzerogreater = array("pmquota", "maxpmrecipients", "maxreputationsday", "attachquota", "maxemails", "maxwarningsday", "maxposts", "edittimelimit", "canusesigxposts", "maxreputationsperthread");
$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
$fpermfields = array('canview', 'canviewthreads', 'candlattachments', 'canpostthreads', 'canpostreplys', 'canpostattachments', 'canratethreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canpostpolls', 'canvotepolls', 'cansearch', 'modposts', 'modthreads', 'modattachments', 'mod_edit_posts');

// Include the installation resources
require_once INSTALL_ROOT."resources/output.php";
$output = new installerOutput;
$output->script = "upgrade.php";
$output->title = "MyBB Upgrade Wizard";

if(file_exists("lock"))
{
	$output->print_error($lang->locked);
}
else
{
	$mybb->input['action'] = $mybb->get_input('action');
	if($mybb->input['action'] == "logout" && $mybb->user['uid'])
	{
		// Check session ID if we have one
		if($mybb->get_input('logoutkey') != $mybb->user['logoutkey'])
		{
			$output->print_error("Your user ID could not be verified to log you out.  This may have been because a malicious Javascript was attempting to log you out automatically.  If you intended to log out, please click the Log Out button at the top menu.");
		}

		my_unsetcookie("mybbuser");

		if($mybb->user['uid'])
		{
			$time = TIME_NOW;
			$lastvisit = array(
				"lastactive" => $time-900,
				"lastvisit" => $time,
			);
			$db->update_query("users", $lastvisit, "uid='".$mybb->user['uid']."'");
		}
		header("Location: upgrade.php");
	}
	else if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/functions_user.php";

		if(!username_exists($mybb->get_input('username')))
		{
			$output->print_error("The username you have entered appears to be invalid.");
		}
		$options = array(
			'fields' => array('username', 'password', 'salt', 'loginkey')
		);
		$user = get_user_by_username($mybb->get_input('username'), $options);

		if(!$user['uid'])
		{
			$output->print_error("The username you have entered appears to be invalid.");
		}
		else
		{
			$user = validate_password_from_uid($user['uid'], $mybb->get_input('password'), $user);
			if(!$user['uid'])
			{
				$output->print_error("The password you entered is incorrect. If you have forgotten your password, click <a href=\"../member.php?action=lostpw\">here</a>. Otherwise, go back and try again.");
			}
		}

		my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], null, true);

		header("Location: ./upgrade.php");
	}

	$output->steps = array($lang->upgrade);

	if($mybb->user['uid'] == 0)
	{
		$output->print_header($lang->please_login, "errormsg", 0, 1);

		$output->print_contents('<p>'.$lang->login_desc.'</p>
<form action="upgrade.php" method="post">
	<div class="border_wrapper">
		<table class="general" cellspacing="0">
		<thead>
			<tr>
				<th colspan="2" class="first last">'.$lang->login.'</th>
			</tr>
		</thead>
		<tbody>
			<tr class="first">
				<td class="first">'.$lang->login_username.':</td>
				<td class="last alt_col"><input type="text" class="textbox" name="username" size="25" maxlength="'.$mybb->settings['maxnamelength'].'" style="width: 200px;" /></td>
			</tr>
			<tr class="alt_row last">
				<td class="first">'.$lang->login_password.':<br /><small>'.$lang->login_password_desc.'</small></td>
				<td class="last alt_col"><input type="password" class="textbox" name="password" size="25" style="width: 200px;" /></td>
			</tr>
		</tbody>
		</table>
	</div>
	<div id="next_button">
		<input type="submit" class="submit_button" name="submit" value="'.$lang->login.'" />
		<input type="hidden" name="action" value="do_login" />
	</div>
</form>');
		$output->print_footer("");

		exit;
	}
	else if($mybb->usergroup['cancp'] != 1 && $mybb->usergroup['cancp'] != 'yes')
	{
		$output->print_error($lang->sprintf($lang->no_permision, $mybb->user['logoutkey']));
	}

	if(!$mybb->input['action'] || $mybb->input['action'] == "intro")
	{
		$output->print_header();

		if($db->table_exists("upgrade_data"))
		{
			$db->drop_table("upgrade_data");
		}
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."upgrade_data (
			title varchar(30) NOT NULL,
			contents text NOT NULL,
			UNIQUE (title)
		);");

		$dh = opendir(INSTALL_ROOT."resources");

		$upgradescripts = array();
		while(($file = readdir($dh)) !== false)
		{
			if(preg_match("#upgrade([0-9]+).php$#i", $file, $match))
			{
				$upgradescripts[$match[1]] = $file;
				$key_order[] = $match[1];
			}
		}
		closedir($dh);
		natsort($key_order);
		$key_order = array_reverse($key_order);

		// Figure out which version we last updated from (as of 1.6)
		$version_history = $cache->read("version_history");

		// If array is empty then we must be upgrading to 1.6 since that's when this feature was added
		if(empty($version_history))
		{
			$next_update_version = 17; // 16+1
		}
		else
		{
			$next_update_version = (int)(end($version_history)+1);
		}

		$vers = '';
		foreach($key_order as $k => $key)
		{
			$file = $upgradescripts[$key];
			$upgradescript = file_get_contents(INSTALL_ROOT."resources/$file");
			preg_match("#Upgrade Script:(.*)#i", $upgradescript, $verinfo);
			preg_match("#upgrade([0-9]+).php$#i", $file, $keynum);
			if(trim($verinfo[1]))
			{
				if($keynum[1] == $next_update_version)
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

		$output->print_contents($lang->sprintf($lang->upgrade_welcome, $mybb->version)."<p><select name=\"from\">$vers</select>".$lang->upgrade_send_stats);
		$output->print_footer("doupgrade");
	}
	elseif($mybb->input['action'] == "doupgrade")
	{
		add_upgrade_store("allow_anonymous_info", $mybb->get_input('allow_anonymous_info', MyBB::INPUT_INT));
		require_once INSTALL_ROOT."resources/upgrade".$mybb->get_input('from', MyBB::INPUT_INT).".php";
		if($db->table_exists("datacache") && $upgrade_detail['requires_deactivated_plugins'] == 1 && $mybb->get_input('donewarning') != "true")
		{
			$plugins = $cache->read('plugins', true);
			if(!empty($plugins['active']))
			{
				$output->print_header();
				$lang->plugin_warning = "<input type=\"hidden\" name=\"from\" value=\"".$mybb->get_input('from', MyBB::INPUT_INT)."\" />\n<input type=\"hidden\" name=\"donewarning\" value=\"true\" />\n<div class=\"error\"><strong><span style=\"color: red\">Warning:</span></strong> <p>There are still ".count($plugins['active'])." plugin(s) active. Active plugins can sometimes cause problems during an upgrade procedure or may break your forum afterward. It is <strong>strongly</strong> reccommended that you deactivate your plugins before continuing.</p></div> <br />";
				$output->print_contents($lang->sprintf($lang->plugin_warning, $mybb->version));
				$output->print_footer("doupgrade");
			}
			else
			{
				add_upgrade_store("startscript", $mybb->get_input('from', MyBB::INPUT_INT));
				$runfunction = next_function($mybb->get_input('from', MyBB::INPUT_INT));
			}
		}
		else
		{
			add_upgrade_store("startscript", $mybb->get_input('from', MyBB::INPUT_INT));
			$runfunction = next_function($mybb->get_input('from', MyBB::INPUT_INT));
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

/**
 * Do the upgrade changes
 */
function upgradethemes()
{
	global $output, $db, $system_upgrade_detail, $lang, $mybb;

	$output->print_header($lang->upgrade_templates_reverted);

	$charset = $db->build_create_table_collation();

	if($system_upgrade_detail['revert_all_templates'] > 0)
	{
		$db->drop_table("templates");
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."templates (
		  tid int unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  template text NOT NULL,
		  sid int(10) NOT NULL default '0',
		  version varchar(20) NOT NULL default '0',
		  status varchar(10) NOT NULL default '',
		  dateline int(10) NOT NULL default '0',
		  PRIMARY KEY  (tid)
		) ENGINE=MyISAM{$charset};");
	}

	if($system_upgrade_detail['revert_all_themes'] > 0)
	{
		$db->drop_table("themes");
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."themes (
		 tid smallint unsigned NOT NULL auto_increment,
		 name varchar(100) NOT NULL default '',
		 pid smallint unsigned NOT NULL default '0',
		 def smallint(1) NOT NULL default '0',
		 properties text NOT NULL,
		 stylesheets text NOT NULL,
		 allowedgroups text NOT NULL,
		 PRIMARY KEY (tid)
		) ENGINE=MyISAM{$charset};");

		$db->drop_table("themestylesheets");
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."themestylesheets(
			sid int unsigned NOT NULL auto_increment,
			name varchar(30) NOT NULL default '',
			tid int unsigned NOT NULL default '0',
			attachedto text NOT NULL,
			stylesheet text NOT NULL,
			cachefile varchar(100) NOT NULL default '',
			lastmodified bigint(30) NOT NULL default '0',
			PRIMARY KEY(sid)
		) ENGINE=MyISAM{$charset};");

		$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
		if(file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php"))
		{
			require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php";
		}
		else if(file_exists(MYBB_ROOT."admin/inc/functions_themes.php"))
		{
			require_once MYBB_ROOT."admin/inc/functions_themes.php";
		}
		else
		{
			$output->print_error("Please make sure your admin directory is uploaded correctly.");
		}
		import_theme_xml($contents, array("templateset" => -2, "no_templates" => 1, "version_compat" => 1));
		$tid = build_new_theme("Default", null, 1);

		$db->update_query("themes", array("def" => 1), "tid='{$tid}'");
		$db->update_query("users", array('style' => $tid));
		$db->update_query("forums", array('style' => 0));

		$db->drop_table("templatesets");
		$db->write_query("CREATE TABLE ".TABLE_PREFIX."templatesets (
		  sid smallint unsigned NOT NULL auto_increment,
		  title varchar(120) NOT NULL default '',
		  PRIMARY KEY  (sid)
		) ENGINE=MyISAM{$charset};");

		$db->insert_query("templatesets", array('title' => 'Default Templates'));
	}
	else
	{
		// Re-import master
		$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
		if(file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php"))
		{
			require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";
			require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php";
		}
		elseif(file_exists(MYBB_ROOT."admin/inc/functions_themes.php"))
		{
			require_once MYBB_ROOT."admin/inc/functions.php";
			require_once MYBB_ROOT."admin/inc/functions_themes.php";
		}
		else
		{
			$output->print_error();
		}

		// Import master theme
		import_theme_xml($contents, array("tid" => 1, "no_templates" => 1, "version_compat" => 1));
	}

	$sid = -2;

	// Now deal with the master templates
	$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$theme = $tree['theme'];

	if(is_array($theme['templates']))
	{
		$templates = $theme['templates']['template'];
		foreach($templates as $template)
		{
			$templatename = $db->escape_string($template['attributes']['name']);
			$templateversion = (int)$template['attributes']['version'];
			$templatevalue = $db->escape_string($template['value']);
			$time = TIME_NOW;
			$query = $db->simple_select("templates", "tid", "sid='-2' AND title='".$db->escape_string($templatename)."'");
			$oldtemp = $db->fetch_array($query);
			if($oldtemp['tid'])
			{
				$update_array = array(
					'template' => $templatevalue,
					'version' => $templateversion,
					'dateline' => $time
				);
				$db->update_query("templates", $update_array, "title='".$db->escape_string($templatename)."' AND sid='-2'");
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
	}

	$output->print_contents($lang->upgrade_templates_reverted_success);
	$output->print_footer("rebuildsettings");
}

/**
 * Update the settings
 */
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
	$output->print_contents($lang->sprintf($lang->upgrade_settings_sync_success, $synccount[1], $synccount[0]));
	$output->print_footer("buildcaches");
}

/**
 * Rebuild caches
 */
function buildcaches()
{
	global $db, $output, $cache, $lang, $mybb;

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
	$cache->update_statistics();
	$cache->update_moderators();
	$cache->update_forums();
	$cache->update_usertitles();
	$cache->update_reportedcontent();
	$cache->update_awaitingactivation();
	$cache->update_mycode();
	$cache->update_profilefields();
	$cache->update_posticons();
	$cache->update_update_check();
	$cache->update_tasks();
	$cache->update_spiders();
	$cache->update_bannedips();
	$cache->update_banned();
	$cache->update_birthdays();
	$cache->update_most_replied_threads();
	$cache->update_most_viewed_threads();
	$cache->update_groupleaders();
	$cache->update_threadprefixes();
	$cache->update_forumsdisplay();

	$contents .= $lang->done."</p>";

	$output->print_contents("$contents<p>".$lang->upgrade_continue."</p>");
	$output->print_footer("finished");
}

/**
 * Called as latest function. Send statistics, create lock file etc
 */
function upgradedone()
{
	global $db, $output, $mybb, $lang, $config, $plugins;

	ob_start();
	$output->print_header("Upgrade Complete");

	$allow_anonymous_info = get_upgrade_store("allow_anonymous_info");
	if($allow_anonymous_info == 1)
	{
		require_once MYBB_ROOT."inc/functions_serverstats.php";
		$build_server_stats = build_server_stats(0, '', $mybb->version_code, $mybb->config['database']['encoding']);

		if($build_server_stats['info_sent_success'] == false)
		{
			echo $build_server_stats['info_image'];
		}
	}
	ob_end_flush();

	// Attempt to run an update check
	require_once MYBB_ROOT.'inc/functions_task.php';
	$query = $db->simple_select('tasks', 'tid', "file='versioncheck'");
	$update_check = $db->fetch_array($query);
	if($update_check)
	{
		// Load plugin system for update check
		require_once MYBB_ROOT."inc/class_plugins.php";
		$plugins = new pluginSystem;

		run_task($update_check['tid']);
	}

	if(is_writable("./"))
	{
		$lock = @fopen("./lock", "w");
		$written = @fwrite($lock, "1");
		@fclose($lock);
		if($written)
		{
			$lock_note = $lang->sprintf($lang->upgrade_locked, $config['admin_dir']);
		}
	}
	if(!$written)
	{
		$lock_note = "<p><b><span style=\"color: red;\">".$lang->upgrade_removedir."</span></b></p>";
	}

	// Rebuild inc/settings.php at the end of the upgrade
	if(function_exists('rebuild_settings'))
	{
		rebuild_settings();
	}
	else
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);

		$query = $db->simple_select("settings", "value, name", "", $options);
		while($setting = $db->fetch_array($query))
		{
			$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
			$settings[$setting['name']] = $setting['value'];
		}
	}

	$output->print_contents($lang->sprintf($lang->upgrade_congrats, $mybb->version, $lock_note));
	$output->print_footer();
}

/**
 * Show the finish page
 */
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

/**
 * Determine the next function we need to call
 *
 * @param int $from
 * @param string $func
 *
 * @return string
 */
function next_function($from, $func="dbchanges")
{
	global $oldvers, $system_upgrade_detail, $currentscript, $cache;

	load_module("upgrade".$from.".php");
	if(function_exists("upgrade".$from."_".$func))
	{
		$function = "upgrade".$from."_".$func;
	}
	else
	{
 		// We're done with our last upgrade script, so add it to the upgrade scripts we've already completed.
		$version_history = $cache->read("version_history");
		$version_history[$from] = $from;
		$cache->update("version_history", $version_history);

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

/**
 * @param string $module
 */
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

/**
 * Get a value from our upgrade data cache
 *
 * @param string $title
 *
 * @return mixed
 */
function get_upgrade_store($title)
{
	global $db;

	$query = $db->simple_select("upgrade_data", "*", "title='".$db->escape_string($title)."'");
	$data = $db->fetch_array($query);
	return my_unserialize($data['contents']);
}

/**
 * @param string $title
 * @param mixed $contents
 */
function add_upgrade_store($title, $contents)
{
	global $db;

	$replace_array = array(
		"title" => $db->escape_string($title),
		"contents" => $db->escape_string(my_serialize($contents))
	);
	$db->replace_query("upgrade_data", $replace_array, "title");
}

/**
 * @param int $redo 2 means that all setting tables will be dropped and recreated
 *
 * @return array
 */
function sync_settings($redo=0)
{
	global $db;

	$settingcount = $groupcount = 0;
	$settings = $settinggroups = array();
	if($redo == 2)
	{
		$db->drop_table("settinggroups");
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
				  gid serial,
				  name varchar(100) NOT NULL default '',
				  title varchar(220) NOT NULL default '',
				  description text NOT NULL default '',
				  disporder smallint NOT NULL default '0',
				  isdefault int NOT NULL default '0',
				  PRIMARY KEY (gid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
				  gid INTEGER PRIMARY KEY,
				  name varchar(100) NOT NULL default '',
				  title varchar(220) NOT NULL default '',
				  description TEXT NOT NULL,
				  disporder smallint NOT NULL default '0',
				  isdefault int(1) NOT NULL default '0'
				);");
				break;
			case "mysql":
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settinggroups (
				  gid smallint unsigned NOT NULL auto_increment,
				  name varchar(100) NOT NULL default '',
				  title varchar(220) NOT NULL default '',
				  description text NOT NULL,
				  disporder smallint unsigned NOT NULL default '0',
				  isdefault int(1) NOT NULL default '0',
				  PRIMARY KEY  (gid)
				) ENGINE=MyISAM;");
		}

		$db->drop_table("settings");

		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settings (
				  sid serial,
				  name varchar(120) NOT NULL default '',
				  title varchar(120) NOT NULL default '',
				  description text NOT NULL default '',
				  optionscode text NOT NULL default '',
				  value text NOT NULL default '',
				  disporder smallint NOT NULL default '0',
				  gid smallint NOT NULL default '0',
				  isdefault int NOT NULL default '0',
				  PRIMARY KEY (sid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settings (
				  sid INTEGER PRIMARY KEY,
				  name varchar(120) NOT NULL default '',
				  title varchar(120) NOT NULL default '',
				  description TEXT NOT NULL,
				  optionscode TEXT NOT NULL,
				  value TEXT NOT NULL,
				  disporder smallint NOT NULL default '0',
				  gid smallint NOT NULL default '0',
				  isdefault int(1) NOT NULL default '0'
				);");
				break;
			case "mysql":
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."settings (
				  sid smallint unsigned NOT NULL auto_increment,
				  name varchar(120) NOT NULL default '',
				  title varchar(120) NOT NULL default '',
				  description text NOT NULL,
				  optionscode text NOT NULL,
				  value text NOT NULL,
				  disporder smallint unsigned NOT NULL default '0',
				  gid smallint unsigned NOT NULL default '0',
				  isdefault int(1) NOT NULL default '0',
				  PRIMARY KEY (sid)
				) ENGINE=MyISAM;");
		}
	}
	else
	{
		if($db->type == "mysql" || $db->type == "mysqli")
        {
            $wheresettings = "isdefault='1' OR isdefault='yes'";
        }
        else
        {
            $wheresettings = "isdefault='1'";
        }

		$query = $db->simple_select("settinggroups", "name,title,gid", $wheresettings);
		while($group = $db->fetch_array($query))
		{
			$settinggroups[$group['name']] = $group['gid'];
		}

		// Collect all the user's settings - regardless of 'defaultivity' - we'll check them all
		// against default settings and insert/update them accordingly
        $query = $db->simple_select("settings", "name,sid");
		while($setting = $db->fetch_array($query))
		{
			$settings[$setting['name']] = $setting['sid'];
		}
	}
	$settings_xml = file_get_contents(INSTALL_ROOT."resources/settings.xml");
	$parser = new XMLParser($settings_xml);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();
	$settinggroupnames = array();
	$settingnames = array();

	foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
	{
		$settinggroupnames[] = $settinggroup['attributes']['name'];

		$groupdata = array(
			"name" => $db->escape_string($settinggroup['attributes']['name']),
			"title" => $db->escape_string($settinggroup['attributes']['title']),
			"description" => $db->escape_string($settinggroup['attributes']['description']),
			"disporder" => (int)$settinggroup['attributes']['disporder'],
			"isdefault" => $settinggroup['attributes']['isdefault']
		);
		if(!$settinggroups[$settinggroup['attributes']['name']] || $redo == 2)
		{
			$gid = $db->insert_query("settinggroups", $groupdata);
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
			$settingnames[] = $setting['attributes']['name'];

			$settingdata = array(
				"name" => $db->escape_string($setting['attributes']['name']),
				"title" => $db->escape_string($setting['title'][0]['value']),
				"description" => $db->escape_string($setting['description'][0]['value']),
				"optionscode" => $db->escape_string($setting['optionscode'][0]['value']),
				"disporder" => (int)$setting['disporder'][0]['value'],
				"gid" => $gid,
				"isdefault" => 1
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
			$db->update_query("settings", array('value' => $db->escape_string($val)), "name='".$db->escape_string($key)."'");
		}
	}
	unset($settings);
	$query = $db->simple_select("settings", "*", "", array('order_by' => 'title'));
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['{$setting['name']}'] = \"".$setting['value']."\";\n";
	}
	$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n$settings\n";
	$file = fopen(MYBB_ROOT."inc/settings.php", "w");
	fwrite($file, $settings);
	fclose($file);
	return array($groupcount, $settingcount);
}

/**
 * @param int $redo 2 means that the tasks table will be dropped and recreated
 *
 * @return int
 */
function sync_tasks($redo=0)
{
	global $db;

	$taskcount = 0;
	$tasks = array();
	if($redo == 2)
	{
		$db->drop_table("tasks");
		switch($db->type)
		{
			case "pgsql":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasks (
					tid serial,
					title varchar(120) NOT NULL default '',
					description text NOT NULL default '',
					file varchar(30) NOT NULL default '',
					minute varchar(200) NOT NULL default '',
					hour varchar(200) NOT NULL default '',
					day varchar(100) NOT NULL default '',
					month varchar(30) NOT NULL default '',
					weekday varchar(15) NOT NULL default '',
					nextrun bigint NOT NULL default '0',
					lastrun bigint NOT NULL default '0',
					enabled int NOT NULL default '1',
					logging int NOT NULL default '0',
					locked bigint NOT NULL default '0',
					PRIMARY KEY(tid)
				);");
				break;
			case "sqlite":
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasks (
					tid INTEGER PRIMARY KEY,
					title varchar(120) NOT NULL default '',
					description TEXT NOT NULL,
					file varchar(30) NOT NULL default '',
					minute varchar(200) NOT NULL default '',
					hour varchar(200) NOT NULL default '',
					day varchar(100) NOT NULL default '',
					month varchar(30) NOT NULL default '',
					weekday varchar(15) NOT NULL default '',
					nextrun bigint(30) NOT NULL default '0',
					lastrun bigint(30) NOT NULL default '0',
					enabled int(1) NOT NULL default '1',
					logging int(1) NOT NULL default '0',
					locked bigint(30) NOT NULL default '0'
				);");
				break;
			case "mysql":
			default:
				$db->write_query("CREATE TABLE ".TABLE_PREFIX."tasks (
					tid int unsigned NOT NULL auto_increment,
					title varchar(120) NOT NULL default '',
					description text NOT NULL,
					file varchar(30) NOT NULL default '',
					minute varchar(200) NOT NULL default '',
					hour varchar(200) NOT NULL default '',
					day varchar(100) NOT NULL default '',
					month varchar(30) NOT NULL default '',
					weekday varchar(15) NOT NULL default '',
					nextrun bigint(30) NOT NULL default '0',
					lastrun bigint(30) NOT NULL default '0',
					enabled int(1) NOT NULL default '1',
					logging int(1) NOT NULL default '0',
					locked bigint(30) NOT NULL default '0',
					PRIMARY KEY (tid)
				) ENGINE=MyISAM;");
		}
	}
	else
	{
        $query = $db->simple_select("tasks", "file,tid");
		while($task = $db->fetch_array($query))
		{
			$tasks[$task['file']] = $task['tid'];
		}
	}

	require_once MYBB_ROOT."inc/functions_task.php";
	$task_file = file_get_contents(INSTALL_ROOT.'resources/tasks.xml');
	$parser = new XMLParser($task_file);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	// Resync tasks
	foreach($tree['tasks'][0]['task'] as $task)
	{
		if(!$tasks[$task['file'][0]['value']] || $redo == 2)
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
		else
		{
			$update_task = array(
				'title' => $db->escape_string($task['title'][0]['value']),
				'description' => $db->escape_string($task['description'][0]['value']),
				'file' => $db->escape_string($task['file'][0]['value']),
			);

			$db->update_query("tasks", $update_task, "file='".$db->escape_string($task['file'][0]['value'])."'");
		}
	}

	return $taskcount;
}

/**
 * Write our settings to the settings file
 */
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
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n{$settings}\n";
		$file = fopen(MYBB_ROOT."inc/settings.php", "w");
		fwrite($file, $settings);
		fclose($file);
	}
}
