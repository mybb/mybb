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

$nosession['avatar'] = 1;
$templatelist = "member_register,error_nousername,error_nopassword,error_passwordmismatch,error_invalidemail,error_usernametaken,error_emailmismatch,error_noemail,redirect_registered";
$templatelist .= ",redirect_loggedout,login,redirect_loggedin,error_invalidusername,error_invalidpassword";
require "./global.php";

require MYBB_ROOT."inc/functions_post.php";
require MYBB_ROOT."inc/functions_user.php";
require MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("member");

if(!$mybb->input['action'])
{
	$mybb->input['action'] = "register";
}

// Make navigation
switch($mybb->input['action'])
{
	case "register":
	case "do_register":
		add_breadcrumb($lang->nav_register);
		break;
	case "activate":
		add_breadcrumb($lang->nav_activate);
		break;
	case "resendactivation":
		add_breadcrumb($lang->nav_resendactivation);
		break;
	case "lostpw":
		add_breadcrumb($lang->nav_lostpw);
		break;
	case "resetpassword":
		add_breadcrumb($lang->nav_resetpassword);
		break;
	case "login":
		add_breadcrumb($lang->nav_login);
		break;
	case "emailuser":
		add_breadcrumb($lang->nav_emailuser);
		break;
}

if(($mybb->input['action'] == "register" || $mybb->input['action'] == "do_register") && $mybb->usergroup['cancp'] != "yes")
{
	if($mybb->settings['disableregs'] == "yes")
	{
		error($lang->registrations_disabled);
	}
	if($mybb->user['regdate'])
	{
		error($lang->error_alreadyregistered);
	}
	if($mybb->settings['betweenregstime'] && $mybb->settings['maxregsbetweentime'])
	{
		$time = time();
		$datecut = $time-(60*60*$mybb->settings['betweenregstime']);
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "regip='$ipaddress' AND regdate > '$datecut'");
		$regcount = $db->num_rows($query);
		if($regcount >= $mybb->settings['maxregsbetweentime'])
		{
			$lang->error_alreadyregisteredtime = sprintf($lang->error_alreadyregisteredtime, $regcount, $mybb->settings['betweenregstime']);
			error($lang->error_alreadyregisteredtime);
		}
	}
}

if($mybb->input['action'] == "do_register" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_register_start");

	if($mybb->settings['regtype'] == "randompass")
	{
		$mybb->input['password'] = randomstr();
		$mybb->input['password2'] = $mybb->input['password'];
	}

	if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin")
	{
		$usergroup = 5;
	}
	else
	{
		$usergroup = 2;
	}

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("insert");

	// Set the data for the new user.
	$user = array(
		"username" => $mybb->input['username'],
		"password" => $mybb->input['password'],
		"password2" => $mybb->input['password2'],
		"email" => $mybb->input['email'],
		"email2" => $mybb->input['email2'],
		"usergroup" => $usergroup,
		"referrer" => $mybb->input['referrername'],
		"timezone" => $mybb->input['timezoneoffset'],
		"language" => $mybb->input['language'],
		"profile_fields" => $mybb->input['profile_fields'],
		"regip" => $session->ipaddress
	);

	$user['birthday'] = array(
		"day" => $mybb->input['bday1'],
		"month" => $mybb->input['bday2'],
		"year" => $mybb->input['bday3']
	);

	$user['options'] = array(
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"emailpmnotify" => $mybb->input['emailpmnotify'],
		"invisible" => $mybb->input['invisible'],
		"enabledst" => $mybb->input['enabledst']
	);

	$userhandler->set_data($user);

	$errors = "";

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	if($mybb->settings['captchaimage'] == "on" && function_exists("imagecreatefrompng"))
	{
		$imagehash = $db->escape_string($mybb->input['imagehash']);
		$imagestring = $db->escape_string($mybb->input['imagestring']);
		$query = $db->simple_select(TABLE_PREFIX."captcha", "*", "imagehash='$imagehash' AND imagestring='$imagestring'");
		$imgcheck = $db->fetch_array($query);
		if(!$imgcheck['dateline'])
		{
			$errors[]  = $lang->error_regimageinvalid;
		}
		$db->delete_query(TABLE_PREFIX."captcha", "imagehash='$imagehash'");
	}

	if(is_array($errors))
	{
		$username = htmlspecialchars_uni($mybb->input['username']);
		$email = htmlspecialchars_uni($mybb->input['email']);
		$email2 = htmlspecialchars_uni($mybb->input['email']);
		$referrername = htmlspecialchars_uni($mybb->input['referrername']);

		if($mybb->input['allownotices'] == "yes")
		{
			$allownoticescheck = "checked=\"checked\"";
		}

		if($mybb->input['hideemail'] == "yes")
		{
			$hideemailcheck = "checked=\"checked\"";
		}

		if($mybb->input['emailnotify'] == "yes")
		{
			$emailnotifycheck = "checked=\"checked\"";
		}

		if($mybb->input['receivepms'] == "yes")
		{
			$receivepmscheck = "checked=\"checked\"";
		}

		if($mybb->input['pmpopup'] == "yes")
		{
			$pmpopupcheck = "checked=\"checked\"";
		}

		if($mybb->input['emailpmnotify'] == "yes")
		{
			$emailpmnotifycheck = "checked=\"checked\"";
		}

		if($mybb->input['invisible'] == "yes")
		{
			$invisiblecheck = "checked=\"checked\"";
		}

		if($mybb->input['enabledst'] == "yes")
		{
			$enabledstcheck = "checked=\"checked\"";
		}

		$regerrors = inline_error($errors);
		$mybb->input['action'] = "register";
		$fromreg = 1;
	}
	else
	{
		$user_info = $userhandler->insert_user();

		if($mybb->settings['regtype'] != "randompass")
		{
			// Log them in
			mysetcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey']);
		}

		if($mybb->settings['regtype'] == "verify")
		{
			$activationcode = random_str();
			$now = time();
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => time(),
				"code" => $activationcode,
				"type" => "r"
			);
			$db->insert_query(TABLE_PREFIX."awaitingactivation", $activationarray);
			$emailsubject = sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
			mymail($email, $emailsubject, $emailmessage);
			$lang->redirect_registered_activation = sprintf($lang->redirect_registered_activation, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_activation);
		}
		else if($mybb->settings['regtype'] == "randompass")
		{
			$emailsubject = sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
			mymail($email, $emailsubject, $emailmessage);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_passwordsent);
		}
		else if($mybb->settings['regtype'] == "admin")
		{
			$lang->redirect_registered_admin_activate = sprintf($lang->redirect_registered_admin_activate, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_admin_activate);
		}
		else
		{
			$lang->redirect_registered = sprintf($lang->redirect_registered, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			redirect("index.php", $lang->redirect_registered);
		}
	}
}
if($mybb->input['action'] == "register")
{

	if((!isset($mybb->input['agree']) && !isset($mybb->input['regsubmit'])) || $mybb->request_method != "post")
	{
		$plugins->run_hooks("member_register_agreement");

		eval("\$agreement = \"".$templates->get("member_register_agreement")."\";");
		output_page($agreement);
	}
	else
	{
		$plugins->run_hooks("member_register_start");

		$bdaysel = '';
		for($i = 1; $i <= 31; $i++)
		{
			$bdaysel .= "<option value=\"$i\">$i</option>\n";
		}

		if(isset($mybb->input['timezoneoffset']))
		{
			$timezoneoffset = $mybb->input['timezoneoffset']*10;
			$timezoneoffset = str_replace("-", "n", $timezoneoffset);
			$timezoneselect[$timezoneoffset] = "selected=\"selected\"";
		}
		else
		{
			// Replace any 'n' with - ... don't know why anyone would use a n, except someone who knows the system anyway
			$mybb->settings['timezoneoffset'] = str_replace("n", "-", $mybb->settings['timezoneoffset']);
			// Multiply it by 10 as required by the system and make any negative disappear
			$selzonetime = abs(intval($mybb->settings['timezoneoffset'])*10);
			// If the timezone is negative, use a prefix n, else no prefix.
			if(substr($mybb->settings['timezoneoffset'], 0, 1) == "-")
			{
				$selzoneway = "n";
			}
			else
			{
				$selzoneway = '';
			}
			// Couple the prefix and offset together and make it selected!
			$selzone = $selzoneway.$selzonetime;
			$timezoneselect[$selzone] = "selected=\"selected\"";
		}
		$timenow = mydate($mybb->settings['timeformat'], time(), "-");
		$lang->time_offset_desc = sprintf($lang->time_offset_desc, $timenow);
		for($i = -12; $i <= 12; $i++)
		{
			if($i == 0)
			{
				$i2 = "-";
			}
			else
			{
				$i2 = $i;
			}
			$temptime = mydate($mybb->settings['timeformat'], time(), $i2);
			$zone = $i*10;
			$zone = str_replace("-", "n", $zone);
			$timein[$zone] = $temptime;
		}
		// Sad code for all the weird timezones
		$timein['n35'] = mydate($mybb->settings['timeformat'], time(), -3.5);
		$timein['35'] = mydate($mybb->settings['timeformat'], time(), 3.5);
		$timein['45'] = mydate($mybb->settings['timeformat'], time(), 4.5);
		$timein['55'] = mydate($mybb->settings['timeformat'], time(), 5.5);
		$timein['575'] = mydate($mybb->settings['timeformat'], time(), 5.75);
		$timein['95'] = mydate($mybb->settings['timeformat'], time(), 9.5);
		$timein['105'] = mydate($mybb->settings['timeformat'], time(), 10.5);
		$mybb->user['timezone'] = $tempzone;

		eval("\$tzselect = \"".$templates->get("usercp_options_timezoneselect")."\";");

		$stylelist = build_theme_select("style");

		if($mybb->settings['usertppoptions'])
		{
			$tppoptions = '';
			$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
			if(is_array($explodedtpp))
			{
				foreach($explodedtpp as $val)
				{
					$val = trim($val);
					$tppoptions .= "<option value=\"$val\">".sprintf($lang->tpp_option, $val)."</option>\n";
				}
			}
			eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
		}
		if($mybb->settings['userpppoptions'])
		{
			$pppoptions = '';
			$explodedppp = explode(",", $mybb->settings['userpppoptions']);
			if(is_array($explodedppp))
			{
				foreach($explodedppp as $val)
				{
					$val = trim($val);
					$pppoptions .= "<option value=\"$val\">".sprintf($lang->ppp_option, $val)."</option>\n";
				}
			}
			eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
		}
		if($mybb->settings['usereferrals'] == "yes" && !$mybb->user['uid'])
		{
			if($_COOKIE['mybb']['referrer'])
			{
				$query = $db->simple_select(TABLE_PREFIX."users", "uid", "username='".$db->escape_string($_COOKIE['mybb']['referrer'])."'");
				$ref = $db->fetch_array($query);
				$referrername = $_COOKIE['mybb']['referrer'];
			}
			elseif($referrer)
			{
				$query = $db->simple_select(TABLE_PREFIX."users", "username", "uid='".intval($referrer['uid'])."'");
				$ref = $db->fetch_array($query);
				$referrername = $ref['username'];
			}
			elseif($referrername)
			{
				$query = $db->simple_select(TABLE_PREFIX."users", "uid", "username='".$db->escape_string($referrername)."'");
				$ref = $db->fetch_array($query);
				if(!$ref['uid'])
				{
					$errors[] = $lang->error_badreferrer;
				}
			}
			if($quickreg)
			{
				$refbg = "trow1";
			}
			else
			{
				$refbg = "trow2";
			}
			eval("\$referrer = \"".$templates->get("member_register_referrer")."\";");
		}
		else
		{
			$referrer = '';
		}
		// Custom profile fields baby!
		$altbg = "trow1";
		$query = $db->simple_select(TABLE_PREFIX."profilefields", "*", "editable='yes'", array('order_by' => 'disporder'));
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$options = $thing[1];
			$select = '';
			$field = "fid$profilefield[fid]";
			if($type == "multiselect")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $val)
					{
						$val = trim($val);
						$val = str_replace("\n", "\\n", $val);
						$select .= "<option value\"$val\">$val</option>\n";
					}
					if(!$profilefield['length'])
					{
						$profilefield['length'] = 3;
					}
					$code = "<select name=\"".$field."[]\" size=\"$profilefield[length]\" multiple=\"multiple\">$select</select>";
				}
			}
			elseif($type == "select")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $val)
					{
						$val = trim($val);
						$val = str_replace("\n", "\\n", $val);
						$select .= "<option value=\"$val\">$val</option>";
					}
					if(!$profilefield['length'])
					{
						$profilefield['length'] = 1;
					}
					$code = "<select name=\"$field\" size=\"$profilefield[length]\">$select</select>";
				}
			}
			elseif($type == "radio")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $val)
					{
						$code .= "<input type=\"radio\" name=\"$field\" value=\"$val\"> $val<br>";
					}
				}
			}
			elseif($type == "checkbox")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $val)
					{
						$code .= "<input type=\"checkbox\" name=\"".$field."[]\" value=\"$val\"> $val<br>";
					}
				}
			}
			elseif($type == "textarea")
			{
				$code = "<textarea name=\"$field\" rows=\"6\" cols=\"50\">$value</textarea>";
			}
			else
			{
				$code = "<input type=\"text\" name=\"$field\" length=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\">";
			}
			if($profilefield['required'] == "yes")
			{
				eval("\$requiredfields .= \"".$templates->get("member_register_customfield")."\";");
			}
			$code = '';
			$select = '';
			$val = '';
			$options = '';
			$expoptions = '';
			$useropts = '';
			$seloptions = '';
		}
		if($requiredfields)
		{
			eval("\$requiredfields = \"".$templates->get("member_register_requiredfields")."\";");
		}
		if(!$fromreg)
		{
			$allownoticescheck = "checked=\"checked\"";
			$hideemailcheck = '';
			$emailnotifycheck = '';
			$receivepmscheck = "checked=\"checked\"";
			$pmpopupcheck = "checked=\"checked\"";
			$pmnotifycheck = '';
			$invisiblecheck = '';
			if($mybb->settings['dstcorrection'] == "yes")
			{
				$enabledstcheck = "checked=\"checked\"";
			}
			
		}
		// Spambot registration image thingy
		if($mybb->settings['captchaimage'] == "on" && function_exists("imagecreatefrompng"))
		{
			$randomstr = random_str(5);
			$imagehash = md5($randomstr);
			$regimagearray = array(
				"imagehash" => $imagehash,
				"imagestring" => $randomstr,
				"dateline" => time()
				);
			$db->insert_query(TABLE_PREFIX."captcha", $regimagearray);
			eval("\$regimage = \"".$templates->get("member_register_regimage")."\";");
		}
		if($mybb->settings['regtype'] != "randompass")
		{
			eval("\$passboxes = \"".$templates->get("member_register_password")."\";");
		}

		$languages = $lang->get_languages();
		$langoptions = '';
		foreach($languages as $lname => $language)
		{
			if($user['language'] == $lname)
			{
				$langoptions .= "<option value=\"$lname\" selected=\"selected\">$language</option>\n";
			}
			else
			{
				$langoptions .= "<option value=\"$lname\">$language</option>\n";
			}
		}

		$plugins->run_hooks("member_register_end");

		eval("\$registration = \"".$templates->get("member_register")."\";");
		output_page($registration);
	}
}
elseif($mybb->input['action'] == "activate")
{
	$plugins->run_hooks("member_activate_start");

	if($mybb->input['username'])
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "username='".$db->escape_string($mybb->input['username'])."'");
		$user = $db->fetch_array($query);
		if(!$user['username'])
		{
			error($lang->error_invalidusername);
		}
		$uid = $user['uid'];
	}
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);
	}
	if($mybb->input['code'] && $user['uid'])
	{
		$mybb->settings['awaitingusergroup'] = "5";
		$query = $db->simple_select(TABLE_PREFIX."awaitingactivation", "*", "uid='".$user['uid']."' AND (type='r' OR type='e')");
		$activation = $db->fetch_array($query);
		if(!$activation['uid'])
		{
			error($lang->error_alreadyactivated);
		}
		if($activation['code'] != $mybb->input['code'])
		{
			error($lang->error_badactivationcode);
		}
		$db->delete_query(TABLE_PREFIX."awaitingactivation", "uid='".$user['uid']."' AND (type='r' OR type='e')");
		if($user['usergroup'] == 5 && $activation['type'] != "e")
		{
			$newgroup = array(
				"usergroup" => 2,
				);
			$db->update_query(TABLE_PREFIX."users", $newgroup, "uid='".$user['uid']."'");
		}
		if($activation['type'] == "e")
		{
			$newemail = array(
				"email" => $db->escape_string($activation['misc']),
				);
			$db->update_query(TABLE_PREFIX."users", $newemail, "uid='".$user['uid']."'");
			if(function_exists("emailChanged"))
			{
				emailChanged($mybb->user['uid'], $email);
			}

			$plugins->run_hooks("member_activate_emailupdated");

			redirect("usercp.php", $lang->redirect_emailupdated);
		}
		else
		{
			if(function_exists("accountActivated") && $activation['type'] == "r")
			{
				accountActivated($user['uid']);
			}

			$plugins->run_hooks("member_activate_accountactivated");

			redirect("index.php", $lang->redirect_accountactivated);
		}
	}
	else
	{
		$plugins->run_hooks("member_activate_form");

		eval("\$activate = \"".$templates->get("member_activate")."\";");
		output_page($activate);
	}
}
elseif($mybb->input['action'] == "resendactivation")
{
	$plugins->run_hooks("member_resendactivation");

	if($mybb->settings['regtype'] == "admin")
	{
		error($lang->error_activated_by_admin);
	}

	eval("\$activate = \"".$templates->get("member_resendactivation")."\";");
	output_page($activate);
}
elseif($mybb->input['action'] == "do_resendactivation" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_resendactivation_start");

	if($mybb->settings['regtype'] == "admin")
	{
		error($lang->error_activated_by_admin);
	}

	$query = $db->query("
		SELECT u.uid, u.username, u.usergroup, u.email, a.code
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."awaitingactivation a ON (a.uid=u.uid AND a.type='r')
		WHERE u.email='".$db->escape_string($mybb->input['email'])."'
	");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			if($user['usergroup'] == 5)
			{
				if(!$user['code'])
				{
					$user['code'] = random_str();
					$now = time();
					$uid = $user['uid'];
					$awaitingarray = array(
						"uid" => $uid,
						"dateline" => time(),
						"code" => $user['code'],
						"type" => "r"
					);
					$db->insert_query(TABLE_PREFIX."awaitingactivation", $awaitingarray);
				}
				$username = $user['username'];
				$email = $user['email'];
				$activationcode = $user['code'];
				$emailsubject = sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
				$emailmessage = sprintf($lang->email_activateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
				mymail($email, $emailsubject, $emailmessage);
			}
		}
		$plugins->run_hooks("member_do_resendactivation_end");

		redirect("index.php", $lang->redirect_activationresent);
	}
}
elseif($mybb->input['action'] == "lostpw")
{
	$plugins->run_hooks("member_lostpw");

	eval("\$lostpw = \"".$templates->get("member_lostpw")."\";");
	output_page($lostpw);
}
elseif($mybb->input['action'] == "do_lostpw" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_lostpw_start");

	$email = $db->escape_string($email);
	$query = $db->simple_select(TABLE_PREFIX."users", "*", "email='".$db->escape_string($mybb->input['email'])."'");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			$db->delete_query(TABLE_PREFIX."awaitingactivation", "uid='{$user['uid']}' AND type='p'");
			$user['activationcode'] = random_str();
			$now = time();
			$uid = $user['uid'];
			$awaitingarray = array(
				"uid" => $user['uid'],
				"dateline" => time(),
				"code" => $user['activationcode'],
				"type" => "p"
			);
			$db->insert_query(TABLE_PREFIX."awaitingactivation", $awaitingarray);
			$username = $user['username'];
			$email = $user['email'];
			$activationcode = $user['activationcode'];
			$emailsubject = sprintf($lang->emailsubject_lostpw, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_lostpw, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
			mymail($email, $emailsubject, $emailmessage);
		}
	}
	$plugins->run_hooks("member_do_lostpw_end");

	redirect("index.php", $lang->redirect_lostpwsent);
}
elseif($mybb->input['action'] == "resetpassword")
{
	$plugins->run_hooks("member_resetpassword_start");

	if($mybb->input['username'])
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "username='".$db->escape_string($mybb->input['username'])."'");
		$user = $db->fetch_array($query);
		if(!$user['uid'])
		{
			error($lang->error_invalidusername);
		}
	}
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);
	}
	if($mybb->input['code'] && $user['uid'])
	{
		$query = $db->simple_select(TABLE_PREFIX."awaitingactivation", "*", "uid='".$user['uid']."' AND type='p'");
		$activation = $db->fetch_array($query);
		$now = time();
		if($activation['code'] != $mybb->input['code'])
		{
			error($lang->error_badlostpwcode);
		}
		$db->delete_query(TABLE_PREFIX."awaitingactivation", "uid='".$user['uid']."' AND type='p'");
		$username = $user['username'];

		//
		// Generate a new password, then update it
		//
		$password = random_str();
		$logindetails = update_password($user['uid'], md5($password), $user['salt']);

		$email = $user['email'];

		$plugins->run_hooks("member_resetpassword_process");

		$emailsubject = sprintf($lang->emailsubject_passwordreset, $mybb->settings['bbname']);
		$emailmessage = sprintf($lang->email_passwordreset, $username, $mybb->settings['bbname'], $password);
		mymail($email, $emailsubject, $emailmessage);

		$plugins->run_hooks("member_resetpassword_reset");

		error($lang->redirect_passwordreset);
	}
	else
	{
		$plugins->run_hooks("member_resetpassword_form");

		eval("\$activate = \"".$templates->get("member_resetpassword")."\";");
		output_page($activate);
	}
}
else if($mybb->input['action'] == "login")
{
	$plugins->run_hooks("member_login");

	//Checks to make sure the user can login; they haven't had too many tries at logging in.
	//Is a fatal call if user has had too many tries
	login_attempt_check();

	// Redirect to the page where the user came from, but not if that was the login page.
	if($mybb->input['url'] && !preg_match("/^(member\.php)?([^\?action=login]+)/i", $mybb->input['url']))
	{
		$redirect_url = htmlentities($mybb->input['url']);
	}
	elseif($_SERVER['HTTP_REFERER'])
	{
		$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
	}

	eval("\$login = \"".$templates->get("member_login")."\";");
	output_page($login);
}
else if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_login_start");

	//Checks to make sure the user can login; they haven't had too many tries at logging in.
	//Is a fatal call if user has had too many tries
	$logins = login_attempt_check();
	$login_text = '';

	if(!username_exists($mybb->input['username']))
	{
		mysetcookie('loginattempts', $logins + 1);
		$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts=loginattempts+1 WHERE sid = '{$session->sid}'");
		if($mybb->settings['failedlogintext'] == "yes")
		{
			$login_text = sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
		}
		error($lang->error_invalidusername.$login_text);
	}
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if(!$user['uid'])
	{
		mysetcookie('loginattempts', $logins + 1);
		$db->query("UPDATE ".TABLE_PREFIX."sessions SET loginattempts=loginattempts+1 WHERE sid = '{$session->sid}'");
		if($mybb->settings['failedlogintext'] == "yes")
		{
			$login_text = sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
		}
		error($lang->error_invalidpassword.$login_text);
	}

	mysetcookie('loginattempts', 1);
	$db->delete_query(TABLE_PREFIX."sessions", "ip='".$session->ipaddress."' AND sid != '".$session->sid."'");
	$newsession = array(
		"uid" => $user['uid'],
		"loginattempts" => 1,
		);
	$db->update_query(TABLE_PREFIX."sessions", $newsession, "sid='".$session->sid."'");

	// Temporarily set the cookie remember option for the login cookies
	$mybb->user['remember'] = $user['remember'];

	mysetcookie("mybbuser", $user['uid']."_".$user['loginkey']);
	mysetcookie("sid", $session->sid, -1);

	if(function_exists("loggedIn"))
	{
		loggedIn($user['uid']);
	}

	$plugins->run_hooks("member_do_login_end");

	if($mybb->input['url'])
	{
		redirect(htmlentities($mybb->input['url']), $lang->redirect_loggedin);
	}
	else
	{
		redirect("index.php", $lang->redirect_loggedin);
	}
}
else if($mybb->input['action'] == "logout")
{
	$plugins->run_hooks("member_logout_start");

	if(!$mybb->user['uid'])
	{
		redirect("index.php", $lang->redirect_alreadyloggedout);
	}
	if($mybb->input['uid'] == $mybb->user['uid'])
	{
		myunsetcookie("mybbuser");
		mysetcookie("sid", 0, -1);
		if($mybb->user['uid'])
		{
			$time = time();
			$lastvisit = array(
				"lastactive" => $time-900,
				"lastvisit" => $time,
				);
			$db->update_query(TABLE_PREFIX."users", $lastvisit, "uid='".$mybb->user['uid']."'");
			$db->delete_query(TABLE_PREFIX."sessions", "uid='".$mybb->user['uid']."' OR ip='".$ipaddress."'");

			if(function_exists("loggedOut"))
			{
				loggedOut($mybb->user['uid']);
			}
		}

		$plugins->run_hooks("member_logout_end");

		redirect("index.php", $lang->redirect_loggedout);
	}
	else 
	{
		error($lang->error_notloggedout);
	}
}
elseif($mybb->input['action'] == "profile")
{
	$plugins->run_hooks("member_profile_start");

	if($mybb->usergroup['canviewprofiles'] == "no")
	{
		error_no_permission();
	}
	if($mybb->input['uid'] == "lastposter")
	{
		if($mybb->input['tid'])
		{
			$query = $db->simple_select(TABLE_PREFIX."posts", "uid", "tid='".intval($mybb->input['tid'])."'	AND visible = 1", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => '1'));
			$post = $db->fetch_array($query);
			$uid = $post['uid'];
		}
		elseif($mybb->input['fid'])
		{
			$flist = '';
			$query = $db->simple_select(TABLE_PREFIX."forums", "fid", "INSTR(CONCAT(',',parentlist,','),',".intval($mybb->input['fid']).",') > 0");
			while($forum = $db->fetch_array($query))
			{
				if($forum['fid'] == $mybb->input['fid'])
				{
					$theforum = $forum;
				}
				$flist .= ",".$forum['fid'];
			}
			$query = $db->simple_select(TABLE_PREFIX."threads", "tid", "fid IN (0$flist) AND visible = 1", array('order_by' => 'lastpost', 'order_dir' => 'DESC', 'limit' => '1'));
			$thread = $db->fetch_array($query);
			$tid = $thread['tid'];
			$query = $db->simple_select(TABLE_PREFIX."posts", "uid", "tid='$tid' AND visible = 1", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => '1'));
			$post = $db->fetch_array($query);
			$uid = $post['uid'];
		}
	}
	else
	{
		if($mybb->input['uid'])
		{
			$uid = intval($mybb->input['uid']);
		}
		else
		{
			$uid = $mybb->user['uid'];
		}
	}

	$query = $db->simple_select(TABLE_PREFIX."users", "*", "uid='$uid'");
	$memprofile = $db->fetch_array($query);

	if(!$memprofile['uid'])
	{
		error($lang->error_nomember);
	}

	// Get member's permissions
	$memperms = user_permissions($memprofile['uid']);

	$lang->nav_profile = sprintf($lang->nav_profile, $memprofile['username']);
	add_breadcrumb($lang->nav_profile);

	$lang->users_forum_info = sprintf($lang->users_forum_info, $memprofile['username']);
	$lang->users_contact_details = sprintf($lang->users_contact_details, $memprofile['username']);

	if($mybb->settings['enablepms'] != "no" && $memprofile['receivepms'] != "no" && $memperms['canusepms'] != "no" && strpos(",".$memprofile['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
	{
		$lang->send_pm = sprintf($lang->send_pm, $memprofile['username']);
	}
	$lang->away_note = sprintf($lang->away_note, $memprofile['username']);
	$lang->users_additional_info = sprintf($lang->users_additional_info, $memprofile['username']);
	$lang->users_signature = sprintf($lang->users_signature, $memprofile['username']);
	$lang->send_user_email = sprintf($lang->send_user_email, $memprofile['username']);

	if(!empty($memprofile['awayreason']))
	{
		$awayreason = $memprofile['awayreason'];
	}
	else
	{
		$awayreason = $lang->away_no_reason;
	}

	if($memprofile['avatar'])
	{
		$memprofile['avatar'] = htmlspecialchars_uni($memprofile['avatar']);
		$avatar = "<img src=\"$memprofile[avatar]\">";
	}
	else
	{
		$avatar = '';
	}

	if($memprofile['hideemail'] != "yes")
	{
		eval("\$sendemail = \"".$templates->get("member_profile_email")."\";");
	}
	else
	{
		$sendemail = '';
	}

	if($memprofile['website'])
	{
		$memprofile['website'] = htmlspecialchars_uni($memprofile['website']);
		$website = "<a href=\"$memprofile[website]\" target=\"_blank\">$memprofile[website]</a>";
	}
	else
	{
		$website = '';
	}

	if($memprofile['signature'])
	{
		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode']
		);

		$memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
		eval("\$signature = \"".$templates->get("member_profile_signature")."\";");
	}

	$daysreg = (time() - $memprofile['regdate']) / (24*3600);
	$ppd = $memprofile['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if($ppd > $memprofile['postnum'])
	{
		$ppd = $memprofile['postnum'];
	}
	$query = $db->simple_select(TABLE_PREFIX."posts", "COUNT(pid) AS posts");
	$posts = $db->fetch_field($query, "posts");
	if($posts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $memprofile['postnum']*100/$posts;
		$percent = round($percent, 2);
	}

	$query = $db->simple_select(TABLE_PREFIX."users", "COUNT(*) AS referrals", "referrer='{$memprofile['uid']}'");
	$referrals = $db->fetch_field($query, "referrals");

	if(!empty($memprofile['icq']))
	{
		$memprofile['icq'] = intval($memprofile['icq']);
	}
	else
	{
		$memprofile['icq'] = '';
	}

	if($memprofile['away'] == "yes" && $mybb->settings['allowaway'] != "no")
	{
		$lang->away_note = sprintf($lang->away_note, $memprofile['username']);
		$awaydate = mydate($mybb->settings['dateformat'], $memprofile['awaydate']);
		$memprofile['awayreason'] = $memprofile['awayreason'];
		if($memprofile['returndate'] == '')
		{
			$returndate = "$lang->unknown";
		}
		else
		{
			$returnhome = explode("-", $memprofile['returndate']);
			$returnmkdate = mktime(0, 0, 0, $returnhome[1], $returnhome[0], $returnhome[2]);
			$returndate = mydate($mybb->settings['dateformat'], $returnmkdate);
		}
		eval("\$awaybit = \"".$templates->get("member_profile_away")."\";");
	}
	if($memprofile['dst'] == "yes")
	{
		$memprofile['timezone']++;
		if(substr($memprofile['timezone'], 0, 1) != "-")
		{
			$memprofile['timezone'] = "+$memprofile[timezone]";
		}
	}
	$memregdate = mydate($mybb->settings['dateformat'], $memprofile['regdate']);
	$memlocaldate = gmdate($mybb->settings['dateformat'], time() + ($memprofile['timezone'] * 3600));
	$memlocaltime = gmdate($mybb->settings['timeformat'], time() + ($memprofile['timezone'] * 3600));

	$localtime = sprintf($lang->local_time_format, $memlocaldate, $memlocaltime);

	if($memprofile['lastactive'])
	{
		$memlastvisitdate = mydate($mybb->settings['dateformat'], $memprofile['lastactive']);
		$memlastvisitsep = ', ';
		$memlastvisittime = mydate($mybb->settings['timeformat'], $memprofile['lastactive']);
	}
	else
	{
		$memlastvisitdate = $lang->lastvisit_never;
		$memlastvisitsep = '';
		$memlastvisittime = '';
	}

	if($memprofile['birthday'])
	{
		$membday = explode("-", $memprofile['birthday']);
		if($membday[2])
		{
			if($membday[2] < 1970)
			{
				$w_day = get_weekday($membday[1], $membday[0], $membday[2]);
				$membday = format_bdays($settings['dateformat'], $membday[1], $membday[0], $membday[2], $w_day);
			}
			else
			{
				$bdayformat = fixmktime($mybb->settings['dateformat'], $membday[2]);
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
				$membday = date($bdayformat, $membday);
			}
			$lang->membdayage = sprintf($lang->membdayage, get_age($memprofile['birthday']));
			$membdayage = $lang->membdayage;
		}
		else
		{
			$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
			$membday = gmdate("F j", $membday);
			$membdayage = '';
		}
	}
	else
	{
		$membday = $lang->not_specified;
		$membdayage = '';
	}

	if(!$memprofile['displaygroup'])
	{
		$memprofile['displaygroup'] = $memprofile['usergroup'];
	}
	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);

	// Get the user title for this user
	if($displaygroup['usertitle'])
	{
		$usertitle = $displaygroup['usertitle'];
		$stars = $displaygroup['stars'];
	}
	else
	{
		$query = $db->simple_select(TABLE_PREFIX."usertitles", "*", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		while($title = $db->fetch_array($query))
		{
			if($memprofile['postnum'] >= $title['posts'])
			{
				$usertitle = $title['title'];
				$stars = $title['stars'];
				$starimage = $title['starimage'];
				break;
			}
		}
	}

	if(!empty($displaygroup['image']))
	{
		if(!empty($mybb->user['language']))
		{
			$language = $mybb->user['language'];
		}
		else
		{
			$language = $mybb->settings['bblanguage'];
		}
		$displaygroup['image'] = str_replace("{lang}", $language, $displaygroup['image']);
		eval("\$groupimage = \"".$templates->get("member_profile_groupimage")."\";");
	}

	if(trim($memprofile['usertitle']) != '')
	{
		$usertitle = $memprofile['usertitle'];
	}
	if(!$starimage)
	{
		$starimage = $displaygroup['starimage'];
	}
	$userstars = '';
	for($i = 0; $i < $stars; $i++)
	{
		$userstars .= "<img src=\"$starimage\" border=\"0\" alt=\"*\" />";
	}


	// Fetch the reputation for this user
	if($memperms['usereputationsystem'] == "yes")
	{
		$reputation = get_reputation($memprofile['reputation']);

		// If this user has permission to give reputations show the vote link
		if($mybb->usergroup['cangivereputations'] == "yes" && $memprofile['uid'] != $mybb->user['uid'])
		{
			$vote_link = "[<a href=\"javascript:MyBB.reputation({$memprofile['uid']});\">{$lang->reputation_vote}</a>]";
		}

		eval("\$reputation = \"".$templates->get("member_profile_reputation")."\";");
	}

	$query = $db->query("
		SELECT *
		FROM ".TABLE_PREFIX."userfields
		WHERE ufid='$uid'
	");
	$userfields = $db->fetch_array($query);
	$customfields = '';
	$bgcolor = trow1;
	// If this user is an Administrator or a Moderator then we wish to show all profile fields
	if($mybb->usergroup['cancp'] == "yes" || $mybb->usergroup['issupermod'] == "yes" || $mybb->usergroup['gid'] == 6)
	{
		$field_hidden = '1=1';
	}
	else
	{
		$field_hidden = "hidden='no'";
	}
	$query = $db->simple_select(TABLE_PREFIX."profilefields", "*", "{$field_hidden}", array('order_by' => 'disporder'));
	while($customfield = $db->fetch_array($query))
	{
		$field = "fid$customfield[fid]";
		$useropts = explode("\n", $userfields[$field]);
		$customfieldval = '';
		if(is_array($useropts) && ($customfield['type'] == "multiselect" || $customfield['type'] == "checkbox"))
		{
			foreach($useropts as $val)
			{
				$customfieldval .= "$val<br />";
			}
		}
		else
		{
			if($customfield['type'] == "textarea")
			{
				$customfieldval = nl2br(htmlspecialchars_uni($userfields[$field]));
			}
			else
			{
				$customfieldval = htmlspecialchars_uni($userfields[$field]);
			}
		}
		eval("\$customfields .= \"".$templates->get("member_profile_customfields_field")."\";");
		
		$bgcolor = alt_trow();
	}
	if($customfields)
	{
		eval("\$profilefields = \"".$templates->get("member_profile_customfields")."\";");
	}
	$memprofile['postnum'] = mynumberformat($memprofile['postnum']);
	$lang->ppd_percent_total = sprintf($lang->ppd_percent_total, mynumberformat($ppd), $percent);
	$formattedname = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);
	if($memprofile['timeonline'] > 0)
	{
		$timeonline = nice_time($memprofile['timeonline']);
	}
	else
	{
		$timeonline = $lang->none_registered;
	}

	if($mybb->usergroup['cancp'] == "yes" && $mybb->config['hideadminlinks'] != 1)
	{
		eval("\$adminoptions = \"".$templates->get("member_profile_adminoptions")."\";");
	}
	else
	{
		$adminoptions = '';
	}

	$plugins->run_hooks("member_profile_end");

	eval("\$profile = \"".$templates->get("member_profile")."\";");
	output_page($profile);
}
elseif($mybb->input['action'] == "emailuser")
{
	$plugins->run_hooks("member_emailuser_start");

	if($mybb->usergroup['cansendemail'] == "no")
	{
		error_no_permission();
	}
	if($mybb->input['uid'])
	{
		$query = $db->simple_select(TABLE_PREFIX."users", "username, hideemail", "uid='".intval($mybb->input['uid'])."'");
		$emailto = $db->fetch_array($query);
		if(!$emailto['username'])
		{
			error($lang->error_invalidpmrecipient);
		}
		if($emailto['hideemail'] != "no")
		{
			error($lang->error_hideemail);
		}
	}
	if($mybb->user['uid'] == 0)
	{
		eval("\$guestfields = \"".$templates->get("member_emailuser_guest")."\";");
	}

	$plugins->run_hooks("member_emailuser_end");

	eval("\$emailuser = \"".$templates->get("member_emailuser")."\";");
	output_page($emailuser);
}
elseif($mybb->input['action'] == "do_emailuser" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_emailuser_start");

	if($mybb->usergroup['cansendemail'] == "no")
	{
		error_no_permission();
	}
	$query = $db->simple_select(TABLE_PREFIX."users", "uid, username, email, hideemail", "username='".$db->escape_string($mybb->input['touser'])."'");
	$emailto = $db->fetch_array($query);
	if(!$emailto['username'])
	{
		error($lang->error_invalidpmrecipient);
	}
	if($emailto['hideemail'] != "no")
	{
		error($lang->error_hideemail);
	}
	if(!$mybb->input['subject'] || !$mybb->input['message'])
	{
		error($lang->error_incompletefields);
	}
	if($mybb->user['uid'] == 0)
	{
		if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $mybb->input['fromemail']))
		{
			error($lang->error_invalidemail);
		}
		if(!$mybb->input['fromname'])
		{
			error($lang->error_incompletefields);
		}
		$from = $mybb->input['fromname'] . " <" . $mybb->input['fromemail'] . ">";
	}
	else
	{
		$from = $mybb->user['username'] . " <" . $mybb->user['email'] . ">";
	}
	mymail($emailto['email'], $parser->parse_badwords($mybb->input['subject']), $parser->parse_badwords($mybb->input['message']), $from);

	$plugins->run_hooks("member_do_emailuser_end");

	redirect("member.php?action=profile&uid=$emailto[uid]", $lang->redirect_emailsent);
}
?>