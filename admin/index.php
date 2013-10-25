<?php
/**
 * MyBB 1.8
 * Copyright 2013 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);

// Here you can change how much of an Admin CP IP address must match in a previous session for the user is validated (defaults to 3 which matches a.b.c)
define("ADMIN_IP_SEGMENTS", 0);

require_once dirname(dirname(__FILE__))."/inc/init.php";

send_page_headers();

if(!isset($config['admin_dir']) || !file_exists(MYBB_ROOT.$config['admin_dir']."/inc/class_page.php"))
{
	$config['admin_dir'] = "admin";
}

define('MYBB_ADMIN_DIR', MYBB_ROOT.$config['admin_dir'].'/');

define('COPY_YEAR', my_date('Y', TIME_NOW));

require_once MYBB_ADMIN_DIR."inc/class_page.php";
require_once MYBB_ADMIN_DIR."inc/class_form.php";
require_once MYBB_ADMIN_DIR."inc/class_table.php";
require_once MYBB_ADMIN_DIR."inc/functions.php";
require_once MYBB_ROOT."inc/functions_user.php";

if(!file_exists(MYBB_ROOT."inc/languages/".$mybb->settings['cplanguage']."/admin/home_dashboard.lang.php"))
{
	$mybb->settings['cplanguage'] = "english";
}
$lang->set_language($mybb->settings['cplanguage'], "admin");

// Load global language phrases
$lang->load("global");

if(function_exists('mb_internal_encoding') && !empty($lang->settings['charset']))
{
	@mb_internal_encoding($lang->settings['charset']);
}

header("Content-type: text/html; charset={$lang->settings['charset']}");

$time = TIME_NOW;
$errors = null;

if(is_dir(MYBB_ROOT."install") && !file_exists(MYBB_ROOT."install/lock"))
{
	$mybb->trigger_generic_error("install_directory");
}

$ip_address = get_ip();
unset($user);

// Load Admin CP style
if(!isset($cp_style))
{
	if(!empty($mybb->settings['cpstyle']) && file_exists(MYBB_ADMIN_DIR."/styles/".$mybb->settings['cpstyle']."/main.css"))
	{
		$cp_style = $mybb->settings['cpstyle'];
	}
	else
	{
		$cp_style = "default";
	}
}

$default_page = new DefaultPage;

$logged_out = false;
$fail_check = 0;
$post_verify = true;

foreach(array('action', 'do', 'module') as $input)
{
	if(!isset($mybb->input[$input]))
	{
		$mybb->input[$input] = '';
	}
}

if($mybb->input['action'] == "unlock")
{
	$user = array();
	$error = '';
	if($mybb->input['username'])
	{
		$username = $db->escape_string(my_strtolower($mybb->input['username']));
		switch($mybb->settings['username_method'])
		{
			case 0:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$username."'", array('limit' => 1));
				break;
			case 1:
				$query = $db->simple_select("users", "*", "LOWER(email)='".$username."'", array('limit' => 1));
				break;
			case 2:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$username."' OR LOWER(email)='".$username."'", array('limit' => 1));
				break;
			default:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$username."'", array('limit' => 1));
				break;
		}
		$user = $db->fetch_array($query);
		if(!$user['uid'])
		{
			$error = $lang->error_invalid_username;
		}
	}
	else if($mybb->input['uid'])
	{
		$user = get_user($mybb->input['uid']);
		if(!$user['uid'])
		{
			$error = $lang->error_invalid_uid;
		}
	}

	// Do we have the token? If so let's process it
	if($mybb->input['token'] && $user['uid'])
	{
		$query = $db->simple_select("awaitingactivation", "COUNT(aid) AS num", "uid='".intval($user['uid'])."' AND code='".$db->escape_string($mybb->input['token'])."' AND type='l'");

		// If we're good to go
		if($db->fetch_field($query, "num") > 0)
		{
			$db->delete_query("awaitingactivation", "uid='".intval($user['uid'])."' AND code='".$db->escape_string($mybb->input['token'])."' AND type='l'");
			$db->update_query("adminoptions", array('loginlockoutexpiry' => 0, 'loginattempts' => 0), "uid='".intval($user['uid'])."'");

			admin_redirect("index.php");
		}
		else
		{
			$error = $lang->error_invalid_token;
		}
	}

	$default_page->show_lockout_unlock($error, 'error');
}
elseif($mybb->input['do'] == "login")
{
	require_once MYBB_ROOT."inc/datahandlers/login.php";
	$loginhandler = new LoginDataHandler("get");

	$mybb->settings['username_method'] = 0; // Overrides to check for ACP login

	// Validate PIN first
	if(!empty($config['secret_pin']) && (empty($mybb->input['pin']) || $mybb->input['pin'] != $config['secret_pin']))
	{
		$default_page->show_login($lang->error_invalid_secret_pin, "error");
	}

	$loginhandler->set_data(array(
		'username' => $mybb->input['username'],
		'password' => $mybb->input['password']
	));

	if($loginhandler->verify_username() !== false && $loginhandler->verify_password() !== false)
	{
		$mybb->user = get_user($loginhandler->login_data['uid']);
	}

	if($mybb->user['uid'])
	{
		if(login_attempt_check_acp($mybb->user['uid']) == true)
		{
			$default_page->show_lockedout();
		}

		$db->delete_query("adminsessions", "uid='{$mybb->user['uid']}'");

		$sid = md5(uniqid(microtime(true), true));

		$useragent = $_SERVER['HTTP_USER_AGENT'];
		if(my_strlen($useragent) > 100)
		{
			$useragent = my_substr($useragent, 0, 100);
		}

		// Create a new admin session for this user
		$admin_session = array(
			"sid" => $sid,
			"uid" => $mybb->user['uid'],
			"loginkey" => $mybb->user['loginkey'],
			"ip" => $db->escape_binary(my_inet_pton(get_ip())),
			"dateline" => TIME_NOW,
			"lastactive" => TIME_NOW,
			"data" => serialize(array()),
			"useragent" => $db->escape_string($useragent),
		);
		$db->insert_query("adminsessions", $admin_session);
		$admin_session['data'] = array();
		$db->update_query("adminoptions", array("loginattempts" => 0, "loginlockoutexpiry" => 0), "uid='".intval($mybb->user['uid'])."'", 1);
		my_setcookie("adminsid", $sid);
		my_setcookie('acploginattempts', 0);
		$post_verify = false;

		$mybb->request_method = "get";

		if(!empty($mybb->input['module']))
		{
			// $query_string should contain the module
			$query_string = '?module='.htmlspecialchars_uni($mybb->input['module']);

			// Now we look for any paramters passed in $_SERVER['QUERY_STRING']
			if($_SERVER['QUERY_STRING'])
			{
				$qstring = '?'.preg_replace('#adminsid=(.{32})#i', '', $_SERVER['QUERY_STRING']);
				$qstring = str_replace('action=logout', '', $qstring);
				$qstring = preg_replace('#&+#', '&', $qstring);
				$qstring = str_replace('?&', '?', $qstring);

				// So what do we do? We know that parameters are devided by ampersands
				// That means we must get to work!
				$parameters = explode('&', $qstring);

				// Remove our first member if it's for the module
				if(substr($parameters[0], 0, 8) == '?module=')
				{
					unset($parameters[0]);
				}

				foreach($parameters as $key => $param)
				{
					$params = explode("=", $param);

					$query_string .= '&'.htmlspecialchars_uni($params[0])."=".htmlspecialchars_uni($params[1]);
				}
			}

			admin_redirect("index.php".$query_string);
		}
	}
	else
	{
		$username = $db->escape_string(my_strtolower($mybb->input['username']));
		switch($mybb->settings['username_method'])
		{
			case 0:
				$query = $db->simple_select("users", "uid,email", "LOWER(username)='".$username."'", array('limit' => 1));
				break;
			case 1:
				$query = $db->simple_select("users", "uid,email", "LOWER(email)='".$username."'", array('limit' => 1));
				break;
			case 2:
				$query = $db->simple_select("users", "uid,email", "LOWER(username)='".$username."' OR LOWER(email)='".$username."'", array('limit' => 1));
				break;
			default:
				$query = $db->simple_select("users", "uid,email", "LOWER(username)='".$username."'", array('limit' => 1));
				break;
		}
		$login_user = $db->fetch_array($query);

		if($login_user['uid'] > 0)
		{
			$db->update_query("adminoptions", array("loginattempts" => "loginattempts+1"), "uid='".intval($login_user['uid'])."'", 1, true);
		}

		$loginattempts = login_attempt_check_acp($login_user['uid'], true);

		// Have we attempted too many times?
		if($loginattempts['loginattempts'] > 0)
		{
			// Have we set an expiry yet?
			if($loginattempts['loginlockoutexpiry'] == 0)
			{
				$db->update_query("adminoptions", array("loginlockoutexpiry" => TIME_NOW+(intval($mybb->settings['loginattemptstimeout'])*60)), "uid='".intval($login_user['uid'])."'", 1);
			}

			// Did we hit lockout for the first time? Send the unlock email to the administrator
			if($loginattempts['loginattempts'] == $mybb->settings['maxloginattempts'])
			{
				$db->delete_query("awaitingactivation", "uid='".intval($login_user['uid'])."' AND type='l'");
				$lockout_array = array(
					"uid" => $login_user['uid'],
					"dateline" => TIME_NOW,
					"code" => random_str(),
					"type" => "l"
				);
				$db->insert_query("awaitingactivation", $lockout_array);

				$subject = $lang->sprintf($lang->locked_out_subject, $mybb->settings['bbname']);
				$message = $lang->sprintf($lang->locked_out_message, htmlspecialchars_uni($mybb->input['username']), $mybb->settings['bbname'], $mybb->settings['maxloginattempts'], $mybb->settings['bburl'], $mybb->config['admin_dir'], $lockout_array['code'], $lockout_array['uid']);
				my_mail($login_user['email'], $subject, $message);
			}

			$default_page->show_lockedout();
		}

		$fail_check = 1;
	}
}
else
{
	// No admin session - show message on the login screen
	if(!isset($mybb->cookies['adminsid']))
	{
		$login_message = "";
	}
	// Otherwise, check admin session
	else
	{
		$query = $db->simple_select("adminsessions", "*", "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
		$admin_session = $db->fetch_array($query);

		// No matching admin session found - show message on login screen
		if(!$admin_session['sid'])
		{
			$login_message = $lang->error_invalid_admin_session;
		}
		else
		{
			$admin_session['data'] = @unserialize($admin_session['data']);

			// Fetch the user from the admin session
			$mybb->user = get_user($admin_session['uid']);

			// Login key has changed - force logout
			if(!$mybb->user['uid'] || $mybb->user['loginkey'] != $admin_session['loginkey'])
			{
				unset($mybb->user);
			}
			else
			{
				// Admin CP sessions 2 hours old are expired
				if($admin_session['lastactive'] < TIME_NOW-7200)
				{
					$login_message = $lang->error_admin_session_has_expired;
					$db->delete_query("adminsessions", "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
					unset($mybb->user);
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
						$login_message = $lang->error_invalid_ip;
						unset($mybb->user);
					}
				}
			}
		}
	}
}

if($mybb->input['action'] == "logout" && $mybb->user)
{
	if(verify_post_check($mybb->input['my_post_key']))
	{
		$db->delete_query("adminsessions", "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
		my_unsetcookie('adminsid');
		$logged_out = true;
	}
}

if(!isset($mybb->user['usergroup']))
{
	$mybbgroups = 1;
}
else
{
	$mybbgroups = $mybb->user['usergroup'].",".$mybb->user['additionalgroups'];
}
$mybb->usergroup = usergroup_permissions($mybbgroups);

if($mybb->usergroup['cancp'] != 1 || !$mybb->user['uid'])
{
	$uid = 0;
	if(isset($mybb->user['uid']))
	{
		$uid = intval($mybb->user['uid']);
	}
	$db->delete_query("adminsessions", "uid = '{$uid}'");
	unset($mybb->user);
	my_unsetcookie('adminsid');
}

if(!empty($mybb->user['uid']))
{
	$query = $db->simple_select("adminoptions", "*", "uid='".$mybb->user['uid']."'");
	$admin_options = $db->fetch_array($query);

	if(!empty($admin_options['cpstyle']) && file_exists(MYBB_ADMIN_DIR."/styles/{$admin_options['cpstyle']}/main.css"))
	{
		$cp_style = $admin_options['cpstyle'];
	}

	// Update the session information in the DB
	if($admin_session['sid'])
	{
		$db->update_query("adminsessions", array('lastactive' => TIME_NOW, 'ip' => $db->escape_binary(my_inet_pton(get_ip()))), "sid='".$db->escape_string($admin_session['sid'])."'");
	}

	// Fetch administrator permissions
	$mybb->admin['permissions'] = get_admin_permissions($mybb->user['uid']);
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
if(!isset($mybb->user['uid']) || $logged_out == true)
{
	if($logged_out == true)
	{
		$page->show_login($lang->success_logged_out);
	}
	elseif($fail_check == 1)
	{
		$page->show_login($lang->error_invalid_username_or_password, "error");
	}
	else
	{
		// If we have this error while retreiving it from an AJAX request, then send back a nice error
		if(isset($mybb->input['ajax']) && $mybb->input['ajax'] == 1)
		{
			echo json_encode(array("errors" => array("login")));
			exit;
		}
		$page->show_login($login_message, "error");
	}
}

$page->add_breadcrumb_item($lang->home, "index.php");

// Begin dealing with the modules
$is_super_admin = is_super_admin($mybb->user['uid']);

$modules_dir = MYBB_ADMIN_DIR."modules";
$dir = opendir($modules_dir);
while(($module = readdir($dir)) !== false)
{
	if(is_dir($modules_dir."/".$module) && !in_array($module, array(".", "..")) && file_exists($modules_dir."/".$module."/module_meta.php"))
	{
		require_once $modules_dir."/".$module."/module_meta.php";

		// Need to always load it for admin permissions / quick access
		$lang->load($module."_module_meta", false, true);

		$has_permission = false;
		if(function_exists($module."_admin_permissions"))
		{
			if(isset($mybb->admin['permissions'][$module]) || $is_super_admin == true)
			{
				$has_permission = true;
			}
		}
		// This module doesn't support permissions
		else
		{
			$has_permission = true;
		}

		// Do we have permissions to run this module (Note: home is accessible by all)
		if($module == "home" || $has_permission == true)
		{
			$meta_function = $module."_meta";
			$initialized = $meta_function();
			if($initialized == true)
			{
				$modules[$module] = 1;
			}
		}
		else
		{
			$modules[$module] = 0;
		}
	}
}

$modules = $plugins->run_hooks("admin_tabs", $modules);

closedir($dir);

if(strpos($mybb->input['module'], "/") !== false)
{
	$current_module = explode("/", $mybb->input['module'], 2);
}
else
{
	$current_module = explode("-", $mybb->input['module'], 2);
}

if(!isset($current_module[1]))
{
	$current_module[1] = 'home';
}

if($mybb->input['module'] && isset($modules[$current_module[0]]))
{
	$run_module = $current_module[0];
}
else
{
	$run_module = "home";
}

$action_handler = $run_module."_action_handler";
$action_file = $action_handler($current_module[1]);

// Set our POST validation code here
$mybb->post_code = generate_post_check();

if($run_module != "home")
{
	check_admin_permissions(array('module' => $page->active_module, 'action' => $page->active_action));
}

// Only POST actions with a valid post code can modify information. Here we check if the incoming request is a POST and if that key is valid.
$post_check_ignores = array(
	"example/page" => array("action")
); // An array of modules/actions to ignore POST checks for.

if($mybb->request_method == "post")
{
	if(in_array($mybb->input['module'], $post_check_ignores))
	{
		$k = array_search($mybb->input['module'], $post_check_ignores);
		if(in_array($mybb->input['action'], $post_check_ignores[$k]))
		{
			$post_verify = false;
		}
	}

	if($post_verify == true)
	{
		// If the post key does not match we switch the action to GET and set a message to show the user
		if(!isset($mybb->input['my_post_key']) || $mybb->post_code != $mybb->input['my_post_key'])
		{
			$mybb->request_method = "get";
			$page->show_post_verify_error = true;
		}
	}
}

$lang->load("{$run_module}_{$page->active_action}", false, true);

$plugins->run_hooks("admin_load");

require $modules_dir."/".$run_module."/".$action_file;
?>
