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

define("IN_MYBB", 1);

// Lets pretend we're a level higher
define("IN_ADMINCP", 1);

// Here you can change how much of an Admin CP IP address must match in a previous session for the user is validated (defaults to 3 which matches a.b.c)
define("ADMIN_IP_SEGMENTS", 3);

require_once dirname(dirname(__FILE__))."/inc/init.php";

send_page_headers();

if(!isset($config['admin_dir']))
{
	$config['admin_dir'] = "admin";
}

//
// TEMPORARY
//
define('MYBB_ADMIN_DIR', MYBB_ROOT."admincp/");
//define('MYBB_ADMIN_DIR', MYBB_ROOT.$config['admin_dir'].'/');

// Check installation (TEMPORARY)
if(!$db->table_exists('adminlog2'))
{
	switch($config['dbtype'])
	{
		case "sqlite3":
		case "sqlite2":
			$db->query("CREATE TABLE ".TABLE_PREFIX."adminlog2 (
			  uid int unsigned NOT NULL default '0',
			  ipaddress varchar(50) NOT NULL default '',
			  dateline bigint(30) NOT NULL default '0',
			  module varchar(50) NOT NULL default '',
			  action varchar(50) NOT NULL default '',
			  data text NOT NULL default ''
			);");
			 break;
		case "pgsql":
			$db->query("CREATE TABLE ".TABLE_PREFIX."adminlog2 (
			  uid int NOT NULL default '0',
			  ipaddress varchar(50) NOT NULL default '',
			  dateline bigint NOT NULL default '0',
			  module varchar(50) NOT NULL default '',
			  action varchar(50) NOT NULL default '',
			  data text NOT NULL default ''
			);");
			 break;
		default:
			$db->query("CREATE TABLE ".TABLE_PREFIX."adminlog2 (
			  uid int unsigned NOT NULL default '0',
			  ipaddress varchar(50) NOT NULL default '',
			  dateline bigint(30) NOT NULL default '0',
			  module varchar(50) NOT NULL default '',
			  action varchar(50) NOT NULL default '',
			  data text NOT NULL default '',
			  KEY module (module, action)
			) TYPE=MyISAM;");
	}
}

if(!$db->field_exists('data', 'adminsessions'))
{
	switch($config['dbtype'])
	{
		case "pgsql":
			$db->query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD data TEXT");
			$db->query("ALTER TABLE ".TABLE_PREFIX."adminsessions ALTER COLUMN data SET NOT NULL");
			break;
		default:
			$db->query("ALTER TABLE ".TABLE_PREFIX."adminsessions ADD data TEXT NOT NULL AFTER lastactive;");
	}
}

require_once MYBB_ADMIN_DIR."/inc/class_page.php";
require_once MYBB_ADMIN_DIR."/inc/class_form.php";
require_once MYBB_ADMIN_DIR."/inc/class_table.php";
require_once MYBB_ADMIN_DIR."/inc/functions.php";
require_once MYBB_ROOT."inc/functions_user.php";

$lang->set_language($mybb->settings['cplanguage'], "admincp");

// Load global language phrases
$lang->load("global");

$time = time();

if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
	$mybb->trigger_generic_error("install_directory");
}

$ip_address = get_ip();
unset($user);

$logged_out = false;

if($mybb->input['action'] == "logout")
{
	// Delete session from the database
	$db->delete_query("adminsessions", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
	
	$logged_out = true;
}
elseif($mybb->input['do'] == "login")
{
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if($user['uid'])
	{
		$query = $db->simple_select("users", "*", "uid='".$user['uid']."'");
		$mybb->user = $db->fetch_array($query);
	}
	$fail_check = 1;

	if($mybb->user['uid'])
	{
		$sid = md5(uniqid(microtime()));
		
		// Create a new admin session for this user
		$admin_session = array(
			"sid" => $sid,
			"uid" => $mybb->user['uid'],
			"loginkey" => $mybb->user['loginkey'],
			"ip" => $db->escape_string(get_ip()),
			"dateline" => time(),
			"lastactive" => time()
		);
		$db->insert_query("adminsessions", $admin_session);
	}
}
else
{
	// No admin session - show message on the login screen
	if(!$mybb->input['adminsid'])
	{
		$login_message = "No administration session was found";
	}
	// Otherwise, check admin session
	else
	{
		$query = $db->simple_select("adminsessions", "*", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
		$admin_session = $db->fetch_array($query);

		// No matching admin session found - show message on login screen
		if(!$admin_session['sid'])
		{
			$login_message = "Invalid administration session";
		}
		else
		{
			$admin_session['data'] = @unserialize($admin_session['data']);

			// Fetch the user from the admin session
			$query = $db->simple_select("users", "*", "uid='{$admin_session['uid']}'");
			$mybb->user = $db->fetch_array($query);

			// Login key has changed - force logout
			if(!$mybb->user['uid'] && $mybb->user['loginkey'] != $admin_session['loginkey'])
			{
				unset($user);
			}
			else
			{
				// Admin CP sessions 2 hours old are expired
				if($admin_session['lastactive'] < time()-7200)
				{
					$login_message = "Your administration session has expired";
					$db->delete_query("adminsessions", "sid='".$db->escape_string($mybb->input['adminsid'])."'");
					unset($user);
				}
				// If IP matching is set - check IP address against the session IP
				else if(ADMIN_IP_SEGMENTS > 0)
				{
					$exploded_ip = explode(".", $ip_address);
					$exploded_admin_ip = explode(".", $admin_session['ip']);
					$matches = 0;
					$valid_ip = false;
					for($i = 0; $i < ADMIN_IP_SEGMENTS; ++$i)
					{
						if($exploded_ip[$i] == $exploded_admin_ip[$i])
						{
							++$matches;
						}
						if($matches == ADMIN_IP_SEGMENTS)
						{
							$valid_ip = true;
							break;
						}
					}
					
					// IP doesn't match properly - show message on logon screen
					if(!$valid_ip)
					{
						$login_message = "Your IP address is not valid for this session";
					}
				}
			}
		}
	}
}

if(!$mybb->user['usergroup'])
{
	$mybbgroups = 1;
}
else
{
	$mybbgroups = $mybb->user['usergroup'].",".$mybb->user['additionalgroups'];
}
$mybb->usergroup = usergroup_permissions($mybbgroups);

if($mybb->usergroup['cancp'] != "yes" || !$mybb->user['uid'])
{
	unset($mybb->user);
}


if($mybb->user['uid'])
{
	$query = $db->simple_select("adminoptions", "*", "uid='".$mybb->user['uid']."'");
	$admin_options = $db->fetch_array($query);
	
	if($admin_options['cpstyle'] && is_dir(MYBB_ADMIN_DIR."/styles/{$admin_options['cpstyle']}"))
	{
		$cp_style = $admin_options['cpstyle'];
	}

	// Update the session information in the DB
	if($admin_session['sid'])
	{
		$updated_session = array(
			"lastactive" => time(),
			"ip" => $ip_address
		);
		$db->update_query("adminsessions", $updated_session, "sid='".$db->escape_string($admin_session['sid'])."'");
	}
	define("SID", "adminsid={$admin_session['sid']}");

	// Fetch administrator permissions
	$mybb->admin['permissions'] = get_admin_permissions($mybb->user['uid']);
}

// Load Admin CP style
if(!$cp_style)
{
	if($mybb->settings['cpstyle'] && is_dir(MYBB_ADMIN_DIR."/styles/".$mybb->settings['cpstyle']))
	{
		$cp_style = $mybb->settings['cpstyle'];
	}
	else
	{
		$cp_style = "default";
	}
}

// Include the layout generation class overrides for this style
if(file_exists(MYBB_ADMIN_DIR."/styles/{$cp_style}/style.php"))
{
	require_once MYBB_ADMIN_DIR."/styles/{$cp_style}/style.php";
}

// Check if any of the layout generation classes we can override exist in the style file
$classes = array(
	"Page" => "DefaultPage",
	"SidebarItem" => "DefaultSidebarItem",
	"PopupMenu" => "DefaultPopupMenu",
	"Table" => "DefaultTable",
	"Form" => "DefaultForm",
	"FormContainer" => "DefaultFormContainer"
);
foreach($classes as $style_name => $default_name)
{
	// Style does not have this layout generation class, create it
	if(!class_exists($style_name))
	{
		eval("class {$style_name} extends {$default_name} { }");
	}
}

$page = new Page;
$page->style = $cp_style;


// Do not have a valid Admin user, throw back to login page.
if(!$mybb->user['uid'] || $logged_out == true)
{
	if($logged_out == true)
	{
		$page->show_login("You have successfully been logged out.");
	}
	elseif($fail_check == 1)
	{
		$page->show_login("The username and password you entered are invalid or the account is not a valid administrator", "error");
	}
	else
	{
		$page->show_login($login_message, "error");
	}
}

if($rand == 2 || $rand == 5)
{
	$stamp = time()-604800;
	$db->delete_query("adminsessions", "lastactive < '{$stamp}'");
}

$page->add_breadcrumb_item("Home", "index.php?".SID);

// Begin dealing with the modules
$modules_dir = MYBB_ADMIN_DIR."modules";
$dir = opendir($modules_dir);
while(($module = readdir($dir)) !== false)
{
	if(is_dir($modules_dir."/".$module) && !in_array($module, array(".", "..")) && file_exists($modules_dir."/".$module."/module_meta.php"))
	{
		// Do we have permissions to run this module (Note: home is accessible by all)
		if($module == "home" || $mybb->admin['permissions'][$module])
		{
			require_once $modules_dir."/".$module."/module_meta.php";
			$meta_function = $module."_meta";
			$initialized = $meta_function();
			if($initialized == true)
			{
				$modules[$module] = 1;
			}
		}
	}
}
closedir($dir);

$current_module = explode("/", $mybb->input['module'], 2);
if($mybb->input['module'] && $modules[$current_module[0]])
{
	$run_module = $current_module[0];
}
else
{
	$run_module = "home";
}

$action_handler = $run_module."_action_handler";
$action_file = $action_handler($current_module[1]);

if($run_module != "home")
{
	check_admin_permissions(array('module' => $page->active_module, 'action' => $page->active_action));
}

// Log the action this user is trying to perform
log_admin_action();

$lang->load("{$run_module}_{$page->active_action}", false, true);

require $modules_dir."/".$run_module."/".$action_file;

if($mybb->input['debug'])
{
	echo $db->explain;
}
?>