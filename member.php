<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'member.php');
define("ALLOWABLE_PAGE", "register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword");

$nosession['avatar'] = 1;
$templatelist = "member_register,member_register_hiddencaptcha,member_coppa_form,member_register_coppa,member_register_agreement_coppa,member_register_agreement,usercp_options_tppselect,usercp_options_pppselect,member_register_referrer,member_register_customfield,member_register_requiredfields,member_register_password,member_activate,member_resendactivation,member_lostpw";
$templatelist .= ",member_resetpassword,member_loggedin_notice,member_profile_away,member_emailuser,member_register_regimage,member_register_regimage_recaptcha,post_captcha_hidden,post_captcha,post_captcha_recaptcha,member_profile_addremove";
$templatelist .= ",member_profile_email,member_profile_offline,member_profile_reputation,member_profile_warn,member_profile_warninglevel,member_profile_customfields_field,member_profile_customfields,member_profile_adminoptions,member_profile,member_login,member_profile_online,member_profile_modoptions,member_profile_signature,member_profile_groupimage,member_profile_referrals";
require_once "./global.php";

require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("member");

$mybb->input['action'] = $mybb->get_input('action');

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

if(($mybb->input['action'] == "register" || $mybb->input['action'] == "do_register") && $mybb->usergroup['cancp'] != 1)
{
	if($mybb->settings['disableregs'] == 1)
	{
		error($lang->registrations_disabled);
	}
	if($mybb->user['uid'] != 0)
	{
		error($lang->error_alreadyregistered);
	}
	if($mybb->settings['betweenregstime'] && $mybb->settings['maxregsbetweentime'])
	{
		$time = TIME_NOW;
		$datecut = $time-(60*60*$mybb->settings['betweenregstime']);
		$query = $db->simple_select("users", "*", "regip=".$db->escape_binary($session->packedip)." AND regdate > '$datecut'");
		$regcount = $db->num_rows($query);
		if($regcount >= $mybb->settings['maxregsbetweentime'])
		{
			$lang->error_alreadyregisteredtime = $lang->sprintf($lang->error_alreadyregisteredtime, $regcount, $mybb->settings['betweenregstime']);
			error($lang->error_alreadyregisteredtime);
		}
	}
}

if($mybb->input['action'] == "do_register" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_register_start");

	// Are checking how long it takes for users to register?
	if($mybb->settings['regtime'] > 0)
	{
		// Is the field actually set?
		if(isset($mybb->input['regtime']))
		{
			// Check how long it took for this person to register
			$timetook = TIME_NOW - $mybb->get_input('regtime', 1);

			// See if they registered faster than normal
			if($timetook < $mybb->settings['regtime'])
			{
				// This user registered pretty quickly, bot detected!
				$lang->error_spam_deny_time = $lang->sprintf($lang->error_spam_deny_time, $mybb->settings['regtime'], $timetook);
				error($lang->error_spam_deny_time);
			}
		}
		else
		{
			error($lang->error_spam_deny);
		}
	}

	// If we have hidden CATPCHA enabled and it's filled, deny registration
	if($mybb->settings['hiddencaptchaimage'])
	{
		$string = $mybb->settings['hiddencaptchaimagefield'];

		if(!empty($mybb->input[$string]))
		{
			error($lang->error_spam_deny);
		}
	}

	if($mybb->settings['regtype'] == "randompass")
	{
		$mybb->input['password'] = random_str();
		$mybb->input['password2'] = $mybb->input['password'];
	}

	if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin" || $mybb->get_input('coppa', 1) == 1)
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

	$coppauser = 0;
	if(isset($mybb->cookies['coppauser']))
	{
		$coppauser = intval($mybb->cookies['coppauser']);
	}

	// Set the data for the new user.
	$user = array(
		"username" => $mybb->get_input('username'),
		"password" => $mybb->get_input('password'),
		"password2" => $mybb->get_input('password2'),
		"email" => $mybb->get_input('email'),
		"email2" => $mybb->get_input('email2'),
		"usergroup" => $usergroup,
		"referrer" => $mybb->get_input('referrername'),
		"timezone" => $mybb->get_input('timezoneoffset'),
		"language" => $mybb->get_input('language'),
		"profile_fields" => $mybb->get_input('profile_fields', 2),
		"regip" => $session->packedip,
		"coppa_user" => $coppauser,
		"regcheck1" => $mybb->get_input('regcheck1'),
		"regcheck2" => $mybb->get_input('regcheck2')
	);

	// Do we have a saved COPPA DOB?
	if(isset($mybb->cookies['coppadob']))
	{
		list($dob_day, $dob_month, $dob_year) = explode("-", $mybb->cookies['coppadob']);
		$user['birthday'] = array(
			"day" => $dob_day,
			"month" => $dob_month,
			"year" => $dob_year
		);
	}

	$user['options'] = array(
		"allownotices" => $mybb->get_input('allownotices', 1),
		"hideemail" => $mybb->get_input('hideemail', 1),
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', 1),
		"receivepms" => $mybb->get_input('receivepms', 1),
		"pmnotice" => $mybb->get_input('pmnotice', 1),
		"emailpmnotify" => $mybb->get_input('emailpmnotify', 1),
		"invisible" => $mybb->get_input('invisible', 1),
		"dstcorrection" => $mybb->get_input('dstcorrection')
	);

	$userhandler->set_data($user);

	$errors = "";

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	if($mybb->settings['captchaimage'])
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$captcha = new captcha;

		if($captcha->validate_captcha() == false)
		{
			// CAPTCHA validation failed
			foreach($captcha->get_errors() as $error)
			{
				$errors[] = $error;
			}
		}
	}

	if(is_array($errors))
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
		$email = htmlspecialchars_uni($mybb->get_input('email'));
		$email2 = htmlspecialchars_uni($mybb->get_input('email2'));
		$referrername = htmlspecialchars_uni($mybb->get_input('referrername'));

		$allownoticescheck = $hideemailcheck = $no_email_subscribe_selected = $instant_email_subscribe_selected = $no_subscribe_selected = '';
		$receivepmscheck = $pmnoticecheck = $emailpmnotifycheck = $invisiblecheck = $dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';

		if($mybb->get_input('allownotices', 1) == 1)
		{
			$allownoticescheck = "checked=\"checked\"";
		}

		if($mybb->get_input('hideemail', 1) == 1)
		{
			$hideemailcheck = "checked=\"checked\"";
		}

		if($mybb->get_input('subscriptionmethod', 1) == 1)
		{
			$no_email_subscribe_selected = "selected=\"selected\"";
		}
		else if($mybb->get_input('subscriptionmethod', 1) == 2)
		{
			$instant_email_subscribe_selected = "selected=\"selected\"";
		}
		else
		{
			$no_subscribe_selected = "selected=\"selected\"";
		}

		if($mybb->get_input('receivepms', 1) == 1)
		{
			$receivepmscheck = "checked=\"checked\"";
		}

		if($mybb->get_input('pmnotice', 1) == 1)
		{
			$pmnoticecheck = " checked=\"checked\"";
		}

		if($mybb->get_input('emailpmnotify', 1) == 1)
		{
			$emailpmnotifycheck = "checked=\"checked\"";
		}

		if($mybb->get_input('invisible', 1) == 1)
		{
			$invisiblecheck = "checked=\"checked\"";
		}

		if($mybb->get_input('dstcorrection', 1) == 2)
		{
			$dst_auto_selected = "selected=\"selected\"";
		}
		else if($mybb->get_input('dstcorrection', 1) == 1)
		{
			$dst_enabled_selected = "selected=\"selected\"";
		}
		else
		{
			$dst_disabled_selected = "selected=\"selected\"";
		}

		$regerrors = inline_error($errors);
		$mybb->input['action'] = "register";
		$fromreg = 1;
	}
	else
	{
		$user_info = $userhandler->insert_user();

		// Invalidate solved captcha
		if($mybb->settings['captchaimage'])
		{
			$captcha->invalidate_captcha();
		}

		if($mybb->settings['regtype'] != "randompass" && !isset($mybb->cookies['coppauser']))
		{
			// Log them in
			my_setcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey'], null, true);
		}

		if(isset($mybb->cookies['coppauser']))
		{
			$lang->redirect_registered_coppa_activate = $lang->sprintf($lang->redirect_registered_coppa_activate, $mybb->settings['bbname'], $user_info['username']);
			my_unsetcookie("coppauser");
			my_unsetcookie("coppadob");
			$plugins->run_hooks("member_do_register_end");
			error($lang->redirect_registered_coppa_activate);
		}
		else if($mybb->settings['regtype'] == "verify")
		{
			$activationcode = random_str();
			$now = TIME_NOW;
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => TIME_NOW,
				"code" => $activationcode,
				"type" => "r"
			);
			$db->insert_query("awaitingactivation", $activationarray);
			$emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
			switch($mybb->settings['username_method'])
			{
				case 0:
					$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
					break;
				case 1:
					$emailmessage = $lang->sprintf($lang->email_activateaccount1, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
					break;
				case 2:
					$emailmessage = $lang->sprintf($lang->email_activateaccount2, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
					break;
				default:
					$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);

			$lang->redirect_registered_activation = $lang->sprintf($lang->redirect_registered_activation, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_activation);
		}
		else if($mybb->settings['regtype'] == "randompass")
		{
			$emailsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
			switch($mybb->settings['username_method'])
			{
				case 0:
					$emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
					break;
				case 1:
					$emailmessage = $lang->sprintf($lang->email_randompassword1, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
					break;
				case 2:
					$emailmessage = $lang->sprintf($lang->email_randompassword2, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
					break;
				default:
					$emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $user_info['password']);
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_passwordsent);
		}
		else if($mybb->settings['regtype'] == "admin")
		{
			$lang->redirect_registered_admin_activate = $lang->sprintf($lang->redirect_registered_admin_activate, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_admin_activate);
		}
		else
		{
			$lang->redirect_registered = $lang->sprintf($lang->redirect_registered, $mybb->settings['bbname'], $user_info['username']);

			$plugins->run_hooks("member_do_register_end");

			redirect("index.php", $lang->redirect_registered);
		}
	}
}

if($mybb->input['action'] == "coppa_form")
{
	if(!$mybb->settings['faxno'])
	{
		$mybb->settings['faxno'] = "&nbsp;";
	}

	eval("\$coppa_form = \"".$templates->get("member_coppa_form")."\";");
	output_page($coppa_form);
}

if($mybb->input['action'] == "register")
{
	$bdaysel = '';
	if($mybb->settings['coppa'] == "disabled")
	{
		$bdaysel = $bday2blank = "<option value=\"\">&nbsp;</option>";
	}
	$mybb->input['bday1'] = $mybb->get_input('bday1', 1);
	for($i = 1; $i <= 31; ++$i)
	{
		if($mybb->input['bday1'] == $i)
		{
			$bdaysel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$bdaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$mybb->input['bday2'] = $mybb->get_input('bday2', 1);
	$bdaymonthsel = array();
	foreach(range(1, 12) as $number)
	{
		$bdaymonthsel[$number] = '';
	}
	$bdaymonthsel[$mybb->input['bday2']] = "selected=\"selected\"";
	$mybb->input['bday3'] = $mybb->get_input('bday3', 1);

	if($mybb->input['bday3'] == 0)
	{
		$mybb->input['bday3'] = '';
	}

	// Is COPPA checking enabled?
	if($mybb->settings['coppa'] != "disabled" && !isset($mybb->input['step']))
	{
		// Just selected DOB, we check
		if($mybb->input['bday1'] && $mybb->input['bday2'] && $mybb->input['bday3'])
		{
			my_unsetcookie("coppauser");

			$months = get_bdays($mybb->input['bday3']);
			if($mybb->input['bday2'] < 1 || $mybb->input['bday2'] > 12 || $mybb->input['bday3'] < (date("Y")-100) || $mybb->input['bday3'] > date("Y") || $mybb->input['bday1'] > $months[$mybb->input['bday2']-1])
			{
				error($lang->error_invalid_birthday);
			}

			$bdaytime = @mktime(0, 0, 0, $mybb->input['bday2'], $mybb->input['bday1'], $mybb->input['bday3']);

			// Store DOB in cookie so we can save it with the registration
			my_setcookie("coppadob", "{$mybb->input['bday1']}-{$mybb->input['bday2']}-{$mybb->input['bday3']}", -1);

			// User is <= 13, we mark as a coppa user
			if($bdaytime >= mktime(0, 0, 0, my_date('n'), my_date('d'), my_date('Y')-13))
			{
				my_setcookie("coppauser", 1, -0);
				$under_thirteen = true;
			}
			$mybb->request_method = "";
		}
		// Show DOB select form
		else
		{
			$plugins->run_hooks("member_register_coppa");

			my_unsetcookie("coppauser");

			eval("\$coppa = \"".$templates->get("member_register_coppa")."\";");
			output_page($coppa);
			exit;
		}
	}

	if((!isset($mybb->input['agree']) && !isset($mybb->input['regsubmit'])) || $mybb->request_method != "post")
	{
		$coppa_agreement = '';
		// Is this user a COPPA user? We need to show the COPPA agreement too
		if($mybb->settings['coppa'] != "disabled" && ($mybb->cookies['coppauser'] == 1 || $under_thirteen))
		{
			if($mybb->settings['coppa'] == "deny")
			{
				error($lang->error_need_to_be_thirteen);
			}
			$lang->coppa_agreement_1 = $lang->sprintf($lang->coppa_agreement_1, $mybb->settings['bbname']);
			eval("\$coppa_agreement = \"".$templates->get("member_register_agreement_coppa")."\";");
		}

		$plugins->run_hooks("member_register_agreement");

		eval("\$agreement = \"".$templates->get("member_register_agreement")."\";");
		output_page($agreement);
	}
	else
	{
		$plugins->run_hooks("member_register_start");

		$validator_extra = '';

		if(isset($mybb->input['timezoneoffset']))
		{
			$timezoneoffset = $mybb->get_input('timezoneoffset');
		}
		else
		{
			$timezoneoffset = $mybb->settings['timezoneoffset'];
		}
		$tzselect = build_timezone_select("timezoneoffset", $timezoneoffset, true);

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
					$tppoptions .= "<option value=\"$val\">".$lang->sprintf($lang->tpp_option, $val)."</option>\n";
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
					$pppoptions .= "<option value=\"$val\">".$lang->sprintf($lang->ppp_option, $val)."</option>\n";
				}
			}
			eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
		}
		if($mybb->settings['usereferrals'] == 1 && !$mybb->user['uid'])
		{
			if(isset($mybb->cookies['mybb']['referrer']))
			{
				$query = $db->simple_select("users", "uid,username", "uid='".intval($mybb->cookies['mybb']['referrer'])."'");
				$ref = $db->fetch_array($query);
				$referrername = $ref['username'];
			}
			elseif(isset($referrer))
			{
				$query = $db->simple_select("users", "username", "uid='".intval($referrer['uid'])."'");
				$ref = $db->fetch_array($query);
				$referrername = $ref['username'];
			}
			elseif(!empty($referrername))
			{
				$query = $db->simple_select("users", "uid", "LOWER(username)='".$db->escape_string(my_strtolower($referrername))."'");
				$ref = $db->fetch_array($query);
				if(!$ref['uid'])
				{
					$errors[] = $lang->error_badreferrer;
				}
			}
			else
			{
				$referrername = '';
			}
			if(isset($quickreg))
			{
				$refbg = "trow1";
			}
			else
			{
				$refbg = "trow2";
			}
			// JS validator extra
			$validator_extra .= "\tregValidator.register('referrer', 'ajax', {url:'xmlhttp.php?action=username_exists', loading_message:'{$lang->js_validator_checking_referrer}'});\n";

			eval("\$referrer = \"".$templates->get("member_register_referrer")."\";");
		}
		else
		{
			$referrer = '';
		}
		$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', 2);
		// Custom profile fields baby!
		$altbg = "trow1";
		$requiredfields = '';
		$query = $db->simple_select("profilefields", "*", "required='1' AND editable !='0'", array('order_by' => 'disporder'));
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$options = $thing[1];
			$select = '';
			$field = "fid{$profilefield['fid']}";
			if($errors && isset($mybb->input['profile_fields'][$field]))
			{
				$userfield = $mybb->input['profile_fields'][$field];
			}
			else
			{
				$userfield = '';
			}
			if($type == "multiselect")
			{
				if($errors)
				{
					$useropts = $userfield;
				}
				else
				{
					$useropts = explode("\n", $userfield);
				}
				if(is_array($useropts))
				{
					foreach($useropts as $key => $val)
					{
						$seloptions[$val] = $val;
					}
				}
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $key => $val)
					{
						$val = trim($val);
						$val = str_replace("\n", "\\n", $val);

						$sel = "";
						if($val == $seloptions[$val])
						{
							$sel = "selected=\"selected\"";
						}
						$select .= "<option value=\"$val\" $sel>$val</option>\n";
					}
					if(!$profilefield['length'])
					{
						$profilefield['length'] = 3;
					}
					$code = "<select name=\"profile_fields[$field][]\" id=\"{$field}\" size=\"{$profilefield['length']}\" multiple=\"multiple\">$select</select>";
				}
			}
			elseif($type == "select")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $key => $val)
					{
						$val = trim($val);
						$val = str_replace("\n", "\\n", $val);
						$sel = "";
						if($val == $userfield)
						{
							$sel = "selected=\"selected\"";
						}
						$select .= "<option value=\"$val\" $sel>$val</option>";
					}
					if(!$profilefield['length'])
					{
						$profilefield['length'] = 1;
					}
					$code = "<select name=\"profile_fields[$field]\" id=\"{$field}\" size=\"{$profilefield['length']}\">$select</select>";
				}
			}
			elseif($type == "radio")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $key => $val)
					{
						$checked = "";
						if($val == $userfield)
						{
							$checked = "checked=\"checked\"";
						}
						$code .= "<input type=\"radio\" class=\"radio\" name=\"profile_fields[$field]\" id=\"{$field}{$key}\" value=\"$val\" $checked /> <span class=\"smalltext\">$val</span><br />";
					}
				}
			}
			elseif($type == "checkbox")
			{
				if($errors)
				{
					$useropts = $userfield;
				}
				else
				{
					$useropts = explode("\n", $userfield);
				}
				if(is_array($useropts))
				{
					foreach($useropts as $key => $val)
					{
						$seloptions[$val] = $val;
					}
				}
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $key => $val)
					{
						$checked = "";
						if($val == $seloptions[$val])
						{
							$checked = "checked=\"checked\"";
						}
						$code .= "<input type=\"checkbox\" class=\"checkbox\" name=\"profile_fields[$field][]\" id=\"{$field}{$key}\" value=\"$val\" $checked /> <span class=\"smalltext\">$val</span><br />";
					}
				}
			}
			elseif($type == "textarea")
			{
				$value = htmlspecialchars_uni($userfield);
				$code = "<textarea name=\"profile_fields[$field]\" id=\"{$field}\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
			}
			else
			{
				$value = htmlspecialchars_uni($userfield);
				$maxlength = "";
				if($profilefield['maxlength'] > 0)
				{
					$maxlength = " maxlength=\"{$profilefield['maxlength']}\"";
				}
				$code = "<input type=\"text\" name=\"profile_fields[$field]\" id=\"{$field}\" class=\"textbox\" size=\"{$profilefield['length']}\"{$maxlength} value=\"$value\" />";
			}
			if($profilefield['required'] == 1)
			{
				// JS validator extra
				if($type == "checkbox" || $type == "radio")
				{
					$id = "{$field}0";
				}
				else
				{
					$id = "fid{$profilefield['fid']}";
				}
				$validator_extra .= "\tregValidator.register('{$id}', 'notEmpty', {failure_message:'{$lang->js_validator_not_empty}'});\n";

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
		if(!empty($requiredfields))
		{
			eval("\$requiredfields = \"".$templates->get("member_register_requiredfields")."\";");
		}
		if(!isset($fromreg))
		{
			$allownoticescheck = "checked=\"checked\"";
			$hideemailcheck = '';
			$emailnotifycheck = '';
			$receivepmscheck = "checked=\"checked\"";
			$pmnoticecheck = " checked=\"checked\"";
			$emailpmnotifycheck = '';
			$invisiblecheck = '';
			if($mybb->settings['dstcorrection'] == 1)
			{
				$enabledstcheck = "checked=\"checked\"";
			}
			$no_subscribe_selected = $no_email_subscribe_selected = $instant_email_subscribe_selected = '';
			$dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
			$username = $email = $email2 = '';
			$regerrors = '';
		}
		// Spambot registration image thingy
		if($mybb->settings['captchaimage'])
		{
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$captcha = new captcha(true, "member_register_regimage");

			if($captcha->html)
			{
				$regimage = $captcha->html;

				if($mybb->settings['captchaimage'] == 1)
				{
					// JS validator extra for our default CAPTCHA
					$validator_extra .= "\tregValidator.register('imagestring', 'ajax', { url: 'xmlhttp.php?action=validate_captcha', extra_body: 'imagehash', loading_message: '{$lang->js_validator_captcha_valid}', failure_message: '{$lang->js_validator_no_image_text}'} );\n";
				}
			}
		}

		$hiddencaptcha = '';
		// Hidden CAPTCHA for Spambots
		if($mybb->settings['hiddencaptchaimage'])
		{
			$captcha_field = $mybb->settings['hiddencaptchaimagefield'];

			eval("\$hiddencaptcha = \"".$templates->get("member_register_hiddencaptcha")."\";");
		}
		if($mybb->settings['regtype'] != "randompass")
		{
			// JS validator extra
			$lang->js_validator_password_length = $lang->sprintf($lang->js_validator_password_length, $mybb->settings['minpasswordlength']);
			$validator_extra .= "\tregValidator.register('password', 'length', {match_field:'password2', min: {$mybb->settings['minpasswordlength']}, failure_message:'{$lang->js_validator_password_length}'});\n";

			// See if the board has "require complex passwords" enabled.
			if($mybb->settings['requirecomplexpasswords'] == 1)
			{
				$lang->password = $lang->complex_password = $lang->sprintf($lang->complex_password, $mybb->settings['minpasswordlength']);
				$validator_extra .= "\tregValidator.register('password', 'ajax', {url:'xmlhttp.php?action=complex_password', loading_message:'{$lang->js_validator_password_complexity}'});\n";
			}
			$validator_extra .= "\tregValidator.register('password2', 'matches', {match_field:'password', status_field:'password_status', failure_message:'{$lang->js_validator_password_matches}'});\n";

			eval("\$passboxes = \"".$templates->get("member_register_password")."\";");
		}

		// JS validator extra
		if($mybb->settings['maxnamelength'] > 0 && $mybb->settings['minnamelength'] > 0)
		{
			$lang->js_validator_username_length = $lang->sprintf($lang->js_validator_username_length, $mybb->settings['minnamelength'], $mybb->settings['maxnamelength']);
			$validator_extra .= "\tregValidator.register('username', 'length', {min: {$mybb->settings['minnamelength']}, max: {$mybb->settings['maxnamelength']}, failure_message:'{$lang->js_validator_username_length}'});\n";
		}

		$languages = $lang->get_languages();
		$langoptions = '';
		foreach($languages as $lname => $language)
		{
			$language = htmlspecialchars_uni($language);
			if($mybb->get_input('language') == $lname)
			{
				$langoptions .= "<option value=\"$lname\" selected=\"selected\">$language</option>\n";
			}
			else
			{
				$langoptions .= "<option value=\"$lname\">$language</option>\n";
			}
		}

		// Set the time so we can find automated signups
		$time = TIME_NOW;

		$plugins->run_hooks("member_register_end");

		eval("\$registration = \"".$templates->get("member_register")."\";");
		output_page($registration);
	}
}

if($mybb->input['action'] == "activate")
{
	$plugins->run_hooks("member_activate_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		switch($mybb->settings['username'])
		{
			case 0:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
				break;
			case 1:
				$query = $db->simple_select("users", "*", "LOWER(email)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
				break;
			case 2:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."' OR LOWER(email)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
				break;
			default:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
				break;
		}
		$user = $db->fetch_array($query);
		if(!$user)
		{
			switch($mybb->settings['username_method'])
			{
				case 0:
					error($lang->error_invalidpworusername);
					break;
				case 1:
					error($lang->error_invalidpworusername1);
					break;
				case 2:
					error($lang->error_invalidpworusername2);
					break;
				default:
					error($lang->error_invalidpworusername);
					break;
			}
		}
		$uid = $user['uid'];
	}
	else
	{
		$user = get_user($mybb->get_input('uid', 1));
	}
	if(isset($mybb->input['code']) && $user)
	{
		$mybb->settings['awaitingusergroup'] = "5";
		$query = $db->simple_select("awaitingactivation", "*", "uid='".$user['uid']."' AND (type='r' OR type='e')");
		$activation = $db->fetch_array($query);
		if(!$activation['uid'])
		{
			error($lang->error_alreadyactivated);
		}
		if($activation['code'] != $mybb->get_input('code'))
		{
			error($lang->error_badactivationcode);
		}
		$db->delete_query("awaitingactivation", "uid='".$user['uid']."' AND (type='r' OR type='e')");
		if($user['usergroup'] == 5 && $activation['type'] != "e")
		{
			$db->update_query("users", array("usergroup" => 2), "uid='".$user['uid']."'");
		}
		if($activation['type'] == "e")
		{
			$newemail = array(
				"email" => $db->escape_string($activation['misc']),
				);
			$db->update_query("users", $newemail, "uid='".$user['uid']."'");
			$plugins->run_hooks("member_activate_emailupdated");

			redirect("usercp.php", $lang->redirect_emailupdated);
		}
		else
		{
			$plugins->run_hooks("member_activate_accountactivated");

			redirect("index.php", $lang->redirect_accountactivated);
		}
	}
	else
	{
		$plugins->run_hooks("member_activate_form");

		$code = $mybb->get_input('code');

		if(!isset($user['username']))
		{
			$user['username'] = '';
		}

		eval("\$activate = \"".$templates->get("member_activate")."\";");
		output_page($activate);
	}
}

if($mybb->input['action'] == "resendactivation")
{
	$plugins->run_hooks("member_resendactivation");

	if($mybb->settings['regtype'] == "admin")
	{
		error($lang->error_activated_by_admin);
	}
	if($mybb->user['uid'] && $mybb->user['usergroup'] != 5)
	{
		error($lang->error_alreadyactivated);
	}

	eval("\$activate = \"".$templates->get("member_resendactivation")."\";");
	output_page($activate);
}

if($mybb->input['action'] == "do_resendactivation" && $mybb->request_method == "post")
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
		WHERE u.email='".$db->escape_string($mybb->get_input('email'))."'
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
					$now = TIME_NOW;
					$uid = $user['uid'];
					$awaitingarray = array(
						"uid" => $uid,
						"dateline" => TIME_NOW,
						"code" => $user['code'],
						"type" => "r"
					);
					$db->insert_query("awaitingactivation", $awaitingarray);
				}
				$username = $user['username'];
				$email = $user['email'];
				$activationcode = $user['code'];
				$emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
				switch($mybb->settings['username_method'])
				{
					case 0:
						$emailmessage = $lang->sprintf($lang->email_activateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
						break;
					case 1:
						$emailmessage = $lang->sprintf($lang->email_activateaccount1, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
						break;
					case 2:
						$emailmessage = $lang->sprintf($lang->email_activateaccount2, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
						break;
					default:
						$emailmessage = $lang->sprintf($lang->email_activateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
						break;
				}
				my_mail($email, $emailsubject, $emailmessage);
			}
		}
		$plugins->run_hooks("member_do_resendactivation_end");

		redirect("index.php", $lang->redirect_activationresent);
	}
}

if($mybb->input['action'] == "lostpw")
{
	$plugins->run_hooks("member_lostpw");

	eval("\$lostpw = \"".$templates->get("member_lostpw")."\";");
	output_page($lostpw);
}

if($mybb->input['action'] == "do_lostpw" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_lostpw_start");

	$email = $db->escape_string($email);
	$query = $db->simple_select("users", "*", "email='".$db->escape_string($mybb->get_input('email'))."'");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			$db->delete_query("awaitingactivation", "uid='{$user['uid']}' AND type='p'");
			$user['activationcode'] = random_str();
			$now = TIME_NOW;
			$uid = $user['uid'];
			$awaitingarray = array(
				"uid" => $user['uid'],
				"dateline" => TIME_NOW,
				"code" => $user['activationcode'],
				"type" => "p"
			);
			$db->insert_query("awaitingactivation", $awaitingarray);
			$username = $user['username'];
			$email = $user['email'];
			$activationcode = $user['activationcode'];
			$emailsubject = $lang->sprintf($lang->emailsubject_lostpw, $mybb->settings['bbname']);
			switch($mybb->settings['username_method'])
			{
				case 0:
					$emailmessage = $lang->sprintf($lang->email_lostpw, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
					break;
				case 1:
					$emailmessage = $lang->sprintf($lang->email_lostpw1, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
					break;
				case 2:
					$emailmessage = $lang->sprintf($lang->email_lostpw2, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
					break;
				default:
					$emailmessage = $lang->sprintf($lang->email_lostpw, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
					break;
			}
			my_mail($email, $emailsubject, $emailmessage);
		}
	}
	$plugins->run_hooks("member_do_lostpw_end");

	redirect("index.php", $lang->redirect_lostpwsent);
}

if($mybb->input['action'] == "resetpassword")
{
	$plugins->run_hooks("member_resetpassword_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		switch($mybb->settings['username_method'])
		{
			case 0:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
				break;
			case 1:
				$query = $db->simple_select("users", "*", "LOWER(email)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
				break;
			case 2:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."' OR LOWER(email)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
				break;
			default:
				$query = $db->simple_select("users", "*", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'");
				break;
		}
		$user = $db->fetch_array($query);
		if(!$user)
		{
			switch($mybb->settings['username_method'])
			{
				case 0:
					error($lang->error_invalidpworusername);
					break;
				case 1:
					error($lang->error_invalidpworusername1);
					break;
				case 2:
					error($lang->error_invalidpworusername2);
					break;
				default:
					error($lang->error_invalidpworusername);
					break;
			}
		}
	}
	else
	{
		$user = get_user($mybb->get_input('uid', 1));
	}
	if(isset($mybb->input['code']) && $user)
	{
		$query = $db->simple_select("awaitingactivation", "*", "uid='".$user['uid']."' AND type='p'");
		$activation = $db->fetch_array($query);
		$now = TIME_NOW;
		if($activation['code'] != $mybb->get_input('code'))
		{
			error($lang->error_badlostpwcode);
		}
		$db->delete_query("awaitingactivation", "uid='".$user['uid']."' AND type='p'");
		$username = $user['username'];

		// Generate a new password, then update it
		$password_length = intval($mybb->settings['minpasswordlength']);

		if($password_length < 8)
		{
			$password_length = 8;
		}

		$password = random_str($password_length);
		$logindetails = update_password($user['uid'], md5($password), $user['salt']);

		$email = $user['email'];

		$plugins->run_hooks("member_resetpassword_process");

		$emailsubject = $lang->sprintf($lang->emailsubject_passwordreset, $mybb->settings['bbname']);
		$emailmessage = $lang->sprintf($lang->email_passwordreset, $username, $mybb->settings['bbname'], $password);
		my_mail($email, $emailsubject, $emailmessage);

		$plugins->run_hooks("member_resetpassword_reset");

		error($lang->redirect_passwordreset);
	}
	else
	{
		$plugins->run_hooks("member_resetpassword_form");

		switch($mybb->settings['username_method'])
		{
			case 0:
				$lang_username = $lang->username;
				break;
			case 1:
				$lang_username = $lang->username1;
				break;
			case 2:
				$lang_username = $lang->username2;
				break;
			default:
				$lang_username = $lang->username;
				break;
		}

		$code = $mybb->get_input('code');

		if(!isset($user['username']))
		{
			$user['username'] = '';
		}

		eval("\$activate = \"".$templates->get("member_resetpassword")."\";");
		output_page($activate);
	}
}

$do_captcha = $correct = false;
$inline_errors = "";
if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_login_start");

	// Is a fatal call if user has had too many tries
	$errors = array();
	$logins = login_attempt_check();

	require_once MYBB_ROOT."inc/datahandlers/login.php";
	$loginhandler = new LoginDataHandler("get");

	if($mybb->get_input('quick_password') && $mybb->get_input('quick_username'))
	{
		$mybb->input['password'] = $mybb->get_input('quick_password');
		$mybb->input['username'] = $mybb->get_input('quick_username');
		$mybb->input['remember'] = $mybb->get_input('quick_remember');
	}

	$user = array(
		'username' => $mybb->get_input('username'),
		'password' => $mybb->get_input('password'),
		'remember' => $mybb->get_input('remember'),
		'imagestring' => $mybb->get_input('imagestring')
	);

	$loginhandler->set_data($user);
	$validated = $loginhandler->validate_login();

	if(!$validated)
	{
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		my_setcookie('loginattempts', $logins + 1);
		$db->update_query("users", array('loginattempts' => 'loginattempts+1'), "LOWER(username) = '".$db->escape_string(my_strtolower($user['username']))."'", 1, true);

		$errors = $loginhandler->get_friendly_errors();
	}
	else if($validated && $loginhandler->captcha_verified == true)
	{
		// Successful login
		if($loginhandler->login_data['coppauser'])
		{
			error($lang->error_awaitingcoppa);
		}

		$loginhandler->complete_login();

		$plugins->run_hooks("member_do_login_end");

		$mybb->input['url'] = $mybb->get_input('url');

		if(!empty($mybb->input['url']) && my_strpos(basename($mybb->input['url']), 'member.php') === false)
		{
			if((my_strpos(basename($mybb->input['url']), 'newthread.php') !== false || my_strpos(basename($mybb->input['url']), 'newreply.php') !== false) && my_strpos($mybb->input['url'], '&processed=1') !== false)
			{
				$mybb->input['url'] = str_replace('&processed=1', '', $mybb->input['url']);
			}

			$mybb->input['url'] = str_replace('&amp;', '&', $mybb->input['url']);

			// Redirect to the URL if it is not member.php
			redirect(htmlentities($mybb->input['url']), $lang->redirect_loggedin);
		}
		else
		{

			redirect("index.php", $lang->redirect_loggedin);
		}
	}

	if($loginhandler->captcha_verified == false)
	{
		// CAPTCHA required
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		$do_captcha = true;
	}

	$plugins->run_hooks("member_do_login_end");
}

if($mybb->input['action'] == "login")
{
	$plugins->run_hooks("member_login");

	$member_loggedin_notice = "";
	if($mybb->user['uid'] != 0)
	{
		$lang->already_logged_in = $lang->sprintf($lang->already_logged_in, build_profile_link($mybb->user['username'], $mybb->user['uid']));
		eval("\$member_loggedin_notice = \"".$templates->get("member_loggedin_notice")."\";");
	}

	// Checks to make sure the user can login; they haven't had too many tries at logging in.
	// Is a fatal call if user has had too many tries
	login_attempt_check();

	// Redirect to the page where the user came from, but not if that was the login page.
	if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], "action=login") === false)
	{
		$redirect_url = htmlentities($_SERVER['HTTP_REFERER']);
	}
	else
	{
		$redirect_url = '';
	}

	$captcha = '';
	// Show captcha image for guests if enabled and only if we have to do
	if($mybb->settings['captchaimage'] && $do_captcha == true)
	{
		$correct = false;
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$login_captcha = new captcha(false, "post_captcha");

		if($do_captcha == false && $login_captcha->type == 1)
		{
			if($login_captcha->validate_captcha() == true)
			{
				$correct = true;
				$captcha = $login_captcha->build_hidden_captcha();
			}
		}

		if(!$correct)
		{
			if($login_captcha->type == 1)
			{
				$login_captcha->build_captcha();
			}
			elseif($login_captcha->type == 2)
			{
				$login_captcha->build_recaptcha();
			}

			if($login_captcha->html)
			{
				$captcha = $login_captcha->html;
			}
		}
		elseif($correct && $login_captcha->type == 2)
		{
			$login_captcha->build_recaptcha();

			if($login_captcha->html)
			{
				$captcha = $login_captcha->html;
			}
		}
	}

	$username = "";
	$password = "";
	if(isset($mybb->input['username']) && $mybb->request_method == "post")
	{
		$username = htmlspecialchars_uni($mybb->get_input('username'));
	}

	if(isset($mybb->input['password']) && $mybb->request_method == "post")
	{
		$password = htmlspecialchars_uni($mybb->get_input('password'));
	}

	if(!empty($errors))
	{
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		$inline_errors = inline_error($errors);
	}

	switch($mybb->settings['username_method'])
	{
		case 1:
			$lang->username = $lang->username1;
			break;
		case 2:
			$lang->username = $lang->username2;
			break;
		default:
			break;
	}
	eval("\$login = \"".$templates->get("member_login")."\";");
	output_page($login);
}

if($mybb->input['action'] == "logout")
{
	$plugins->run_hooks("member_logout_start");

	if(!$mybb->user['uid'])
	{
		redirect("index.php", $lang->redirect_alreadyloggedout);
	}

	// Check session ID if we have one
	if(isset($mybb->input['sid']) && $mybb->get_input('sid') != $session->sid)
	{
		error($lang->error_notloggedout);
	}
	// Otherwise, check logoutkey
	else if(!isset($mybb->input['sid']) && $mybb->get_input('logoutkey') != $mybb->user['logoutkey'])
	{
		error($lang->error_notloggedout);
	}

	my_unsetcookie("mybbuser");
	my_unsetcookie("sid");

	if($mybb->user['uid'])
	{
		$time = TIME_NOW;
		// Run this after the shutdown query from session system
		$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='{$time}', lastactive='{$time}' WHERE uid='{$mybb->user['uid']}'");
		$db->delete_query("sessions", "sid = '{$session->sid}'");
	}

	$plugins->run_hooks("member_logout_end");
	redirect("index.php", $lang->redirect_loggedout);
}

if($mybb->input['action'] == "profile")
{
	$plugins->run_hooks("member_profile_start");

	if($mybb->usergroup['canviewprofiles'] == 0)
	{
		error_no_permission();
	}

	$uid = $mybb->get_input('uid', 1);
	if($uid)
	{
		$memprofile = get_user($uid);
	}
	elseif($mybb->user['uid'])
	{
		$memprofile = $mybb->user;
	}
	else
	{
		$memprofile = false;
	}

	if(!$memprofile)
	{
		error($lang->error_nomember);
	}

	$uid = $memprofile['uid'];

	$lang->profile = $lang->sprintf($lang->profile, $memprofile['username']);

	// Get member's permissions
	$memperms = user_permissions($memprofile['uid']);

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $memprofile['username']);
	add_breadcrumb($lang->nav_profile);

	$lang->users_forum_info = $lang->sprintf($lang->users_forum_info, $memprofile['username']);
	$lang->users_contact_details = $lang->sprintf($lang->users_contact_details, $memprofile['username']);

	if($mybb->settings['enablepms'] != 0 && (($memprofile['receivepms'] != 0 && $memperms['canusepms'] != 0 && my_strpos(",".$memprofile['ignorelist'].",", ",".$mybb->user['uid'].",") === false) || $mybb->usergroup['canoverridepm'] == 1))
	{
		$lang->send_pm = $lang->sprintf($lang->send_pm, $memprofile['username']);
	}
	else
	{
		$lang->send_pm = '';
	}
	$lang->away_note = $lang->sprintf($lang->away_note, $memprofile['username']);
	$lang->users_additional_info = $lang->sprintf($lang->users_additional_info, $memprofile['username']);
	$lang->users_signature = $lang->sprintf($lang->users_signature, $memprofile['username']);
	$lang->send_user_email = $lang->sprintf($lang->send_user_email, $memprofile['username']);

	$useravatar = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
	$avatar = "<img src=\"{$useravatar['image']}\" alt=\"\" {$useravatar['width_height']} />";

	$sendemail = '';
	if($memprofile['hideemail'] != 1 && (my_strpos(",".$memprofile['ignorelist'].",", ",".$mybb->user['uid'].",") === false || $mybb->usergroup['cansendemailoverride'] != 0))
	{
		eval("\$sendemail = \"".$templates->get("member_profile_email")."\";");
	}
	else
	{
		$alttrow = "trow1"; // To properly sort the contact details below
	}

	// Clean alt_trow for the contact details
	$cat_array = array(
		"pm",
		"icq",
		"aim",
		"yahoo",
		"msn",
	);

	$bgcolors = array();
	foreach($cat_array as $cat)
	{
		$bgcolors[$cat] = alt_trow();
	}

	$website = '';
	if($memprofile['website'])
	{
		$memprofile['website'] = htmlspecialchars_uni($memprofile['website']);
		$website = "<a href=\"{$memprofile['website']}\" target=\"_blank\">{$memprofile['website']}</a>";
	}

	$signature = '';
	if($memprofile['signature'] && ($memprofile['suspendsignature'] == 0 || $memprofile['suspendsigtime'] < TIME_NOW))
	{
		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode'],
			"me_username" => $memprofile['username'],
			"filter_badwords" => 1
		);

		if($memperms['signofollow'])
		{
			$sig_parser['nofollow_on'] = 1;
		}

		$memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
		eval("\$signature = \"".$templates->get("member_profile_signature")."\";");
	}

	$daysreg = (TIME_NOW - $memprofile['regdate']) / (24*3600);

	if($daysreg < 1)
	{
		$daysreg = 1;
	}

	$ppd = $memprofile['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if($ppd > $memprofile['postnum'])
	{
		$ppd = $memprofile['postnum'];
	}
	$stats = $cache->read("stats");
	$numposts = $stats['numposts'];
	if($numposts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $memprofile['postnum']*100/$numposts;
		$percent = round($percent, 2);
	}

	if($percent > 100)
	{
		$percent = 100;
	}

	if(!empty($memprofile['icq']))
	{
		$memprofile['icq'] = intval($memprofile['icq']);
	}
	else
	{
		$memprofile['icq'] = '';
	}

	$awaybit = '';
	if($memprofile['away'] == 1 && $mybb->settings['allowaway'] != 0)
	{
		$lang->away_note = $lang->sprintf($lang->away_note, $memprofile['username']);
		$awaydate = my_date($mybb->settings['dateformat'], $memprofile['awaydate']);
		if(!empty($memprofile['awayreason']))
		{
			$reason = $parser->parse_badwords($memprofile['awayreason']);
			$awayreason = htmlspecialchars_uni($reason);
		}
		else
		{
			$awayreason = $lang->away_no_reason;
		}
		if($memprofile['returndate'] == '')
		{
			$returndate = "$lang->unknown";
		}
		else
		{
			$returnhome = explode("-", $memprofile['returndate']);

			// PHP native date functions use integers so timestamps for years after 2038 will not work
			// Thus we use adodb_mktime
			if($returnhome[2] >= 2038)
			{
				require_once MYBB_ROOT."inc/functions_time.php";
				$returnmkdate = adodb_mktime(0, 0, 0, $returnhome[1], $returnhome[0], $returnhome[2]);
				$returndate = my_date($mybb->settings['dateformat'], $returnmkdate, "", 1, true);
			}
			else
			{
				$returnmkdate = mktime(0, 0, 0, $returnhome[1], $returnhome[0], $returnhome[2]);
				$returndate = my_date($mybb->settings['dateformat'], $returnmkdate);
			}

			// If our away time has expired already, we should be back, right?
			if($returnmkdate < TIME_NOW)
			{
				$db->update_query('users', array('away' => '0', 'awaydate' => '0', 'returndate' => '', 'awayreason' => ''), 'uid=\''.intval($memprofile['uid']).'\'');

				// Update our status to "not away"
				$memprofile['away'] = 0;
			}
		}

		// Check if our away status is set to 1, it may have been updated already (see a few lines above)
		if($memprofile['away'] == 1)
		{
			eval("\$awaybit = \"".$templates->get("member_profile_away")."\";");
		}
	}
	if($memprofile['dst'] == 1)
	{
		$memprofile['timezone']++;
		if(my_substr($memprofile['timezone'], 0, 1) != "-")
		{
			$memprofile['timezone'] = "+{$memprofile['timezone']}";
		}
	}
	$memregdate = my_date($mybb->settings['dateformat'], $memprofile['regdate']);
	$memlocaldate = gmdate($mybb->settings['dateformat'], TIME_NOW + ($memprofile['timezone'] * 3600));
	$memlocaltime = gmdate($mybb->settings['timeformat'], TIME_NOW + ($memprofile['timezone'] * 3600));

	$localtime = $lang->sprintf($lang->local_time_format, $memlocaldate, $memlocaltime);

	if($memprofile['lastactive'])
	{
		$memlastvisitdate = my_date($mybb->settings['dateformat'], $memprofile['lastactive']);
		$memlastvisitsep = $lang->comma;
		$memlastvisittime = my_date($mybb->settings['timeformat'], $memprofile['lastactive']);
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

		if($memprofile['birthdayprivacy'] != 'none')
		{
			if($membday[0] && $membday[1] && $membday[2])
			{
				$lang->membdayage = $lang->sprintf($lang->membdayage, get_age($memprofile['birthday']));

				if($membday[2] >= 1970)
				{
					$w_day = date("l", mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]));
					$membday = format_bdays($mybb->settings['dateformat'], $membday[1], $membday[0], $membday[2], $w_day);
				}
				else
				{
					$bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
					$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
					$membday = date($bdayformat, $membday);
				}
				$membdayage = $lang->membdayage;
			}
			elseif($membday[2])
			{
				$membday = mktime(0, 0, 0, 1, 1, $membday[2]);
				$membday = date("Y", $membday);
				$membdayage = '';
			}
			else
			{
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
				$membday = date("F j", $membday);
				$membdayage = '';
			}
		}

		if($memprofile['birthdayprivacy'] == 'age')
		{
			$membday = $lang->birthdayhidden;
		}
		else if($memprofile['birthdayprivacy'] == 'none')
		{
			$membday = $lang->birthdayhidden;
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

	// Grab the following fields from the user's displaygroup
	$displaygroupfields = array(
		"title",
		"usertitle",
		"stars",
		"starimage",
		"image",
		"usereputationsystem"
	);
	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);

	// Get the user title for this user
	unset($usertitle);
	unset($stars);
	$starimage = '';
	if(trim($memprofile['usertitle']) != '')
	{
		// User has custom user title
		$usertitle = $memprofile['usertitle'];
	}
	elseif(trim($displaygroup['usertitle']) != '')
	{
		// User has group title
		$usertitle = $displaygroup['usertitle'];
	}
	else
	{
		// No usergroup title so get a default one
		$usertitles = $cache->read('usertitles');

		if(is_array($usertitles))
		{
			foreach($usertitles as $title)
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
	}

	if($displaygroup['stars'] || $displaygroup['usertitle'])
	{
		// Set the number of stars if display group has constant number of stars
		$stars = $displaygroup['stars'];
	}
	elseif(!$stars)
	{
		if(!is_array($usertitles))
		{
			$usertitles = $cache->read('usertitles');
		}

		// This is for cases where the user has a title, but the group has no defined number of stars (use number of stars as per default usergroups)
		if(is_array($usertitles))
		{
			foreach($usertitles as $title)
			{
				if($memprofile['postnum'] >= $title['posts'])
				{
					$stars = $title['stars'];
					$starimage = $title['starimage'];
					break;
				}
			}
		}
	}

	$groupimage = '';
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
		$displaygroup['image'] = str_replace("{theme}", $theme['imgdir'], $displaygroup['image']);
		eval("\$groupimage = \"".$templates->get("member_profile_groupimage")."\";");
	}

	if(empty($starimage))
	{
		$starimage = $displaygroup['starimage'];
	}

	if(!empty($starimage))
	{
		// Only display stars if we have an image to use...
		$starimage = str_replace("{theme}", $theme['imgdir'], $starimage);
		$userstars = '';
		for($i = 0; $i < $stars; ++$i)
		{
			$userstars .= "<img src=\"$starimage\" border=\"0\" alt=\"*\" />";
		}
	}

	// User is currently online and this user has permissions to view the user on the WOL
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
	$query = $db->simple_select("sessions", "location,nopermission", "uid='$uid' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
	$session = $db->fetch_array($query);

	$online_status = '';
	if(!empty($session) && ($memprofile['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $memprofile['uid'] == $mybb->user['uid']))
	{
		// Lastvisit
		if($memprofile['lastactive'])
		{
			$memlastvisitsep = $lang->comma;
			$memlastvisitdate = my_date('relative', $memprofile['lastactive']);
		}

		// Time Online
		$timeonline = $lang->none_registered;
		if($memprofile['timeonline'] > 0)
		{
			$timeonline = nice_time($memprofile['timeonline']);
		}

		// Fetch their current location
		$lang->load("online");
		require_once MYBB_ROOT."inc/functions_online.php";
		$activity = fetch_wol_activity($session['location'], $session['nopermission']);
		$location = build_friendly_wol_location($activity);
		$location_time = my_date($mybb->settings['timeformat'], $memprofile['lastactive']);

		eval("\$online_status = \"".$templates->get("member_profile_online")."\";");
	}
	else
	{
		$memlastvisitsep = '';
		$memlastvisittime = '';
		$memlastvisitdate = $lang->lastvisit_never;

		if($memprofile['lastactive'])
		{
			// We have had at least some active time, hide it instead
			$memlastvisitdate = $lang->lastvisit_hidden;
		}

		$timeonline = $lang->timeonline_hidden;

		eval("\$online_status = \"".$templates->get("member_profile_offline")."\";");
	}

	// Build Referral
	$referrals = '';
	if($mybb->settings['usereferrals'] == 1)
	{
		// Reset the background colours to keep it inline
		$bg_color = alt_trow(true);

		eval("\$referrals = \"".$templates->get("member_profile_referrals")."\";");
	}
	else
	{
		// Manually set to override colours...
		$alttrow = 'trow2';
	}

	// Fetch the reputation for this user
	$reputation = '';
	if($memperms['usereputationsystem'] == 1 && $displaygroup['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
	{
		$bg_color = alt_trow();
		$reputation = get_reputation($memprofile['reputation']);

		// If this user has permission to give reputations show the vote link
		$vote_link = '';
		if($mybb->usergroup['cangivereputations'] == 1 && $memprofile['uid'] != $mybb->user['uid'] && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']))
		{
			$vote_link = "[<a href=\"javascript:MyBB.reputation({$memprofile['uid']});\">{$lang->reputation_vote}</a>]";
		}

		eval("\$reputation = \"".$templates->get("member_profile_reputation")."\";");
	}

	$warning_level = '';
	if($mybb->settings['enablewarningsystem'] != 0 && $memperms['canreceivewarnings'] != 0 && ($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $memprofile['uid'] && $mybb->settings['canviewownwarning'] != 0)))
	{
		$bg_color = alt_trow();
		$warning_level = round($memprofile['warningpoints']/$mybb->settings['maxwarningpoints']*100);

		if($warning_level > 100)
		{
			$warning_level = 100;
		}

		$warn_user = '';
		$warning_link = 'usercp.php';
		$warning_level = get_colored_warning_level($warning_level);
		if($mybb->usergroup['canwarnusers'] != 0 && $memprofile['uid'] != $mybb->user['uid'])
		{
			eval("\$warn_user = \"".$templates->get("member_profile_warn")."\";");
			$warning_link = "warnings.php?uid={$memprofile['uid']}";
		}

		eval("\$warning_level = \"".$templates->get("member_profile_warninglevel")."\";");
	}

	$bgcolor = $alttrow = 'trow1';
	$customfields = $profilefields = '';

	$query = $db->simple_select("userfields", "*", "ufid = '{$uid}'");
	$userfields = $db->fetch_array($query);

	// If this user is an Administrator or a Moderator then we wish to show all profile fields
	$field_hidden = 'hidden=0';
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['canmodcp'] == 1)
	{
		$field_hidden = '1=1';
	}

	$query = $db->simple_select("profilefields", "*", "{$field_hidden}", array('order_by' => 'disporder'));
	while($customfield = $db->fetch_array($query))
	{
		$thing = explode("\n", $customfield['type'], "2");
		$type = trim($thing[0]);

		$customfieldval = '';
		$field = "fid{$customfield['fid']}";

		if(isset($userfields[$field]))
		{
			$useropts = explode("\n", $userfields[$field]);
			$customfieldval = $comma = '';
			if(is_array($useropts) && ($type == "multiselect" || $type == "checkbox"))
			{
				foreach($useropts as $val)
				{
					if($val != '')
					{
						$customfieldval .= "<li style=\"margin-left: 0;\">{$val}</li>";
					}
				}
				if($customfieldval != '')
				{
					$customfieldval = "<ul style=\"margin: 0; padding-left: 15px;\">{$customfieldval}</ul>";
				}
			}
			else
			{
				$userfields[$field] = $parser->parse_badwords($userfields[$field]);

				if($customfield['type'] == "textarea")
				{
					$customfieldval = nl2br(htmlspecialchars_uni($userfields[$field]));
				}
				else
				{
					$customfieldval = htmlspecialchars_uni($userfields[$field]);
				}
			}
		}

		$customfield['name'] = htmlspecialchars_uni($customfield['name']);
		eval("\$customfields .= \"".$templates->get("member_profile_customfields_field")."\";");
		$bgcolor = alt_trow();
	}

	if($customfields)
	{
		eval("\$profilefields = \"".$templates->get("member_profile_customfields")."\";");
	}

	$memprofile['postnum'] = my_number_format($memprofile['postnum']);
	$lang->ppd_percent_total = $lang->sprintf($lang->ppd_percent_total, my_number_format($ppd), $percent);
	$formattedname = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);
	if($memprofile['timeonline'] > 0)
	{
		$timeonline = nice_time($memprofile['timeonline']);
	}
	else
	{
		$timeonline = $lang->none_registered;
	}

	$adminoptions = '';
	if($mybb->usergroup['cancp'] == 1 && $mybb->config['hide_admin_links'] != 1)
	{
		eval("\$adminoptions = \"".$templates->get("member_profile_adminoptions")."\";");
	}

	$modoptions = '';
	if($mybb->usergroup['canmodcp'] == 1)
	{
		$memprofile['usernotes'] = nl2br(htmlspecialchars_uni($memprofile['usernotes']));

		if(!empty($memprofile['usernotes']))
		{
			if(strlen($memprofile['usernotes']) > 100)
			{
				$memprofile['usernotes'] = my_substr($memprofile['usernotes'], 0, 100).'...';
			}
		}
		else
		{
			$memprofile['usernotes'] = $lang->no_usernotes;
		}

		eval("\$modoptions = \"".$templates->get("member_profile_modoptions")."\";");
	}

	$add_remove_options = array();
	$buddy_options = $ignore_options = $report_options = '';
	if($mybb->user['uid'] != $memprofile['uid'] && $mybb->user['uid'] != 0)
	{
		$buddy_list = explode(',', $mybb->user['buddylist']);
		if(in_array($uid, $buddy_list))
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_buddy_button', 'lang' => $lang->remove_from_buddy_list);
		}
		else
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;add_username=".urlencode($memprofile['username'])."&amp;my_post_key={$mybb->post_code}", 'class' => 'add_buddy_button', 'lang' => $lang->add_to_buddy_list);
		}

		eval("\$buddy_options = \"".$templates->get("member_profile_addremove")."\";"); // Add/Remove Buddy

		$ignore_list = explode(',', $mybb->user['ignorelist']);
		if(in_array($uid, $ignore_list))
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;delete={$uid}&amp;my_post_key={$mybb->post_code}", 'class' => 'remove_ignore_button', 'lang' => $lang->remove_from_ignore_list);
		}
		else
		{
			$add_remove_options = array('url' => "usercp.php?action=do_editlists&amp;manage=ignored&amp;add_username=".urlencode($memprofile['username'])."&amp;my_post_key={$mybb->post_code}", 'class' => 'add_ignore_button', 'lang' => $lang->add_to_ignore_list);
		}

		eval("\$ignore_options = \"".$templates->get("member_profile_addremove")."\";"); // Add/Remove Ignore

		if(isset($memperms['canbereported']) && $memperms['canbereported'] == 1)
		{
			// This is cheating; override the class to add onclick
			$onclick = "\" onclick=\"MyBB.popupWindow(this.href, 'reportProfile', 400, 300); return false;";

			$add_remove_options = array('url' => "report.php?type=profile&amp;pid={$memprofile['uid']}", 'class' => 'report_user_button'.$onclick, 'lang' => $lang->report_user);
			eval("\$report_options = \"".$templates->get("member_profile_addremove")."\";"); // Report User
		}
	}

	$plugins->run_hooks("member_profile_end");

	eval("\$profile = \"".$templates->get("member_profile")."\";");
	output_page($profile);
}

if($mybb->input['action'] == "do_emailuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("member_do_emailuser_start");

	// Guests or those without permission can't email other users
	if($mybb->usergroup['cansendemail'] == 0 || !$mybb->user['uid'])
	{
		error_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "fromuid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count >= $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	$query = $db->simple_select("users", "uid, username, email, hideemail", "uid='".$mybb->get_input('uid', 1)."'");
	$to_user = $db->fetch_array($query);

	if(!$to_user['username'])
	{
		error($lang->error_invalidusername);
	}

	if($to_user['hideemail'] != 0)
	{
		error($lang->error_hideemail);
	}

	$errors = array();

	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->error_no_email_subject;
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->error_no_email_message;
	}

	if(count($errors) == 0)
	{
		if($mybb->settings['mail_handler'] == 'smtp')
		{
			$from = $mybb->user['email'];
		}
		else
		{
			$from = "{$mybb->user['username']} <{$mybb->user['email']}>";
		}

		$message = $lang->sprintf($lang->email_emailuser, $to_user['username'], $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->get_input('message'));
		my_mail($to_user['email'], $mybb->get_input('subject'), $message, $from, "", "", false, "text", "", $mybb->user['email']);

		if($mybb->settings['mail_logging'] > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($mybb->get_input('subject')),
				"message" => $db->escape_string($mybb->get_input('message')),
				"dateline" => TIME_NOW,
				"fromuid" => $mybb->user['uid'],
				"fromemail" => $db->escape_string($mybb->user['email']),
				"touid" => $to_user['uid'],
				"toemail" => $db->escape_string($to_user['email']),
				"tid" => 0,
				"ipaddress" => $db->escape_binary($session->packedip)
			);
			$db->insert_query("maillogs", $log_entry);
		}

		$plugins->run_hooks("member_do_emailuser_end");

		redirect(get_profile_link($to_user['uid']), $lang->redirect_emailsent);
	}
	else
	{
		$mybb->input['action'] = "emailuser";
	}
}

if($mybb->input['action'] == "emailuser")
{
	$plugins->run_hooks("member_emailuser_start");

	// Guests or those without permission can't email other users
	if($mybb->usergroup['cansendemail'] == 0 || !$mybb->user['uid'])
	{
		error_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "fromuid='{$mybb->user['uid']}' AND dateline >= '".(TIME_NOW - (60*60*24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count > $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	$query = $db->simple_select("users", "uid, username, email, hideemail, ignorelist", "uid='".$mybb->get_input('uid', 1)."'");
	$to_user = $db->fetch_array($query);

	$lang->email_user = $lang->sprintf($lang->email_user, $to_user['username']);

	if(!$to_user['uid'])
	{
		error($lang->error_invaliduser);
	}

	if($to_user['hideemail'] != 0)
	{
		error($lang->error_hideemail);
	}

	if($to_user['ignorelist'] && (my_strpos(",".$to_user['ignorelist'].",", ",".$mybb->user['uid'].",") !== false && $mybb->usergroup['cansendemailoverride'] != 1))
	{
		error_no_permission();
	}

	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$subject = htmlspecialchars_uni($mybb->get_input('subject'));
		$message = htmlspecialchars_uni($mybb->get_input('message'));
	}
	else
	{
		$errors = '';
		$subject = '';
		$message = '';
	}

	$plugins->run_hooks("member_emailuser_end");

	eval("\$emailuser = \"".$templates->get("member_emailuser")."\";");
	output_page($emailuser);
}

if(!$mybb->input['action'])
{
	header("Location: index.php");
}
?>
