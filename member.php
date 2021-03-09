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
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'member.php');
define("ALLOWABLE_PAGE", "register,do_register,login,do_login,logout,lostpw,do_lostpw,activate,resendactivation,do_resendactivation,resetpassword,viewnotes");

$nosession['avatar'] = 1;

$templatelist = "member_register,member_register_hiddencaptcha,member_register_coppa,member_register_agreement_coppa,member_register_agreement,member_register_customfield,member_register_requiredfields,member_profile_findthreads";
$templatelist .= ",member_loggedin_notice,member_profile_away,member_register_regimage,member_register_regimage_recaptcha_invisible,member_register_regimage_nocaptcha,post_captcha_hcaptcha_invisible,post_captcha_hcaptcha,post_captcha_hidden,post_captcha,member_register_referrer";
$templatelist .= ",member_profile_email,member_profile_offline,member_profile_reputation,member_profile_warn,member_profile_warninglevel,member_profile_warninglevel_link,member_profile_customfields_field,member_profile_customfields,member_profile_adminoptions_manageban,member_profile_adminoptions,member_profile";
$templatelist .= ",member_profile_signature,member_profile_avatar,member_profile_groupimage,member_referrals_link,member_profile_referrals,member_profile_website,member_profile_reputation_vote,member_activate,member_lostpw,member_register_additionalfields";
$templatelist .= ",member_profile_modoptions_manageuser,member_profile_modoptions_editprofile,member_profile_modoptions_banuser,member_profile_modoptions_viewnotes,member_profile_modoptions_editnotes,member_profile_modoptions_purgespammer";
$templatelist .= ",usercp_profile_profilefields_select_option,usercp_profile_profilefields_multiselect,usercp_profile_profilefields_select,usercp_profile_profilefields_textarea,usercp_profile_profilefields_radio,member_viewnotes";
$templatelist .= ",member_register_question,member_register_question_refresh,usercp_options_timezone,usercp_options_timezone_option,usercp_options_language_option,member_profile_customfields_field_multi_item,member_profile_customfields_field_multi";
$templatelist .= ",member_profile_contact_fields_google,member_profile_contact_fields_icq,member_profile_contact_fields_skype,member_profile_pm,member_profile_contact_details,member_profile_modoptions_manageban";
$templatelist .= ",member_profile_banned_remaining,member_profile_addremove,member_emailuser_guest,member_register_day,usercp_options_tppselect_option,postbit_warninglevel_formatted,member_profile_userstar,member_profile_findposts";
$templatelist .= ",usercp_options_tppselect,usercp_options_pppselect,member_resetpassword,member_login,member_profile_online,usercp_options_pppselect_option,postbit_reputation_formatted,member_emailuser,usercp_profile_profilefields_text";
$templatelist .= ",member_profile_modoptions_ipaddress,member_profile_modoptions,member_profile_banned,member_register_language,member_resendactivation,usercp_profile_profilefields_checkbox,member_register_password,member_coppa_form";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
require_once MYBB_ROOT."inc/functions_modcp.php";
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
		$datecut = $time - (60 * 60 * $mybb->settings['betweenregstime']);
		$query = $db->simple_select("users", "*", "regip=".$db->escape_binary($session->packedip)." AND regdate > '$datecut'");
		$regcount = $db->num_rows($query);
		if($regcount >= $mybb->settings['maxregsbetweentime'])
		{
			$lang->error_alreadyregisteredtime = $lang->sprintf($lang->error_alreadyregisteredtime, $regcount, $mybb->settings['betweenregstime']);
			error($lang->error_alreadyregisteredtime);
		}
	}
}

$fromreg = 0;
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
			$timetook = TIME_NOW - $mybb->get_input('regtime', MyBB::INPUT_INT);

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

		$password_length = (int)$mybb->settings['minpasswordlength'];
		if($password_length < 8)
		{
			$password_length = min(8, (int)$mybb->settings['maxpasswordlength']);
		}

		$mybb->input['password'] = random_str($password_length, $mybb->settings['requirecomplexpasswords']);
		$mybb->input['password2'] = $mybb->input['password'];
	}

	if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin" || $mybb->settings['regtype'] == "both" || $mybb->get_input('coppa', MyBB::INPUT_INT) == 1)
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
		$coppauser = (int)$mybb->cookies['coppauser'];
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
		"profile_fields" => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY),
		"regip" => $session->packedip,
		"coppa_user" => $coppauser,
		"regcheck1" => $mybb->get_input('regcheck1'),
		"regcheck2" => $mybb->get_input('regcheck2'),
		"registration" => true
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
		"allownotices" => $mybb->get_input('allownotices', MyBB::INPUT_INT),
		"hideemail" => $mybb->get_input('hideemail', MyBB::INPUT_INT),
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
		"receivepms" => $mybb->get_input('receivepms', MyBB::INPUT_INT),
		"pmnotice" => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
		"pmnotify" => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
		"invisible" => $mybb->get_input('invisible', MyBB::INPUT_INT),
		"dstcorrection" => $mybb->get_input('dstcorrection')
	);

	$userhandler->set_data($user);

	$errors = array();

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	if($mybb->settings['enablestopforumspam_on_register'])
	{
		require_once MYBB_ROOT.'/inc/class_stopforumspamchecker.php';

		$stop_forum_spam_checker = new StopForumSpamChecker(
			$plugins,
			$mybb->settings['stopforumspam_min_weighting_before_spam'],
			$mybb->settings['stopforumspam_check_usernames'],
			$mybb->settings['stopforumspam_check_emails'],
			$mybb->settings['stopforumspam_check_ips'],
			$mybb->settings['stopforumspam_log_blocks']
		);

		try
		{
			if($stop_forum_spam_checker->is_user_a_spammer($user['username'], $user['email'], get_ip()))
			{
				error($lang->sprintf($lang->error_stop_forum_spam_spammer,
					$stop_forum_spam_checker->getErrorText(array(
						'stopforumspam_check_usernames',
						'stopforumspam_check_emails',
						'stopforumspam_check_ips'
					))));
			}
		}
		catch(Exception $e)
		{
			if($mybb->settings['stopforumspam_block_on_error'])
			{
				error($lang->error_stop_forum_spam_fetching);
			}
		}
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

	// If we have a security question, check to see if answer is correct
	if($mybb->settings['securityquestion'])
	{
		$question_id = $db->escape_string($mybb->get_input('question_id'));
		$answer = $db->escape_string($mybb->get_input('answer'));

		$query = $db->query("
			SELECT q.*, s.sid
			FROM ".TABLE_PREFIX."questionsessions s
			LEFT JOIN ".TABLE_PREFIX."questions q ON (q.qid=s.qid)
			WHERE q.active='1' AND s.sid='{$question_id}'
		");
		if($db->num_rows($query) > 0)
		{
			$question = $db->fetch_array($query);
			$valid_answers = explode("\n", $question['answer']);
			$validated = 0;

			foreach($valid_answers as $answers)
			{
				if(my_strtolower($answers) == my_strtolower($answer))
				{
					$validated = 1;
				}
			}

			if($validated != 1)
			{
				$update_question = array(
					"incorrect" => $question['incorrect'] + 1
				);
				$db->update_query("questions", $update_question, "qid='{$question['qid']}'");

				$errors[] = $lang->error_question_wrong;
			}
			else
			{
				$update_question = array(
					"correct" => $question['correct'] + 1
				);
				$db->update_query("questions", $update_question, "qid='{$question['qid']}'");
			}

			$db->delete_query("questionsessions", "sid='{$sid}'");
		}
	}

	$regerrors = '';
	if(!empty($errors))
	{
		$select['username'] = $mybb->get_input('username');
		$select['email'] = $mybb->get_input('email');
		$select['email2'] = $mybb->get_input('email2');
		$select['referrername'] = $mybb->get_input('referrername');

		$select['allownotices'] = $select['hideemail'] = $select['receivepms'] = $select['pmnotice'] = $select['pmnotify'] = $select['invisible'] = false;
		$select['subscriptionmethod']['none'] = $select['subscriptionmethod']['email'] = $select['subscriptionmethod']['pm'] = $select['subscriptionmethod']['no'] = false;
		$select['dst']['auto'] = $select['dst']['enabled'] = $select['dst']['disabled'] = false;

		if($mybb->get_input('allownotices', MyBB::INPUT_INT) == 1)
		{
			$select['allownotices'] = true;
		}

		if($mybb->get_input('hideemail', MyBB::INPUT_INT) == 1)
		{
			$select['hideemail'] = true;
		}

		if($mybb->get_input('receivepms', MyBB::INPUT_INT) == 1)
		{
			$select['receivepms'] = true;
		}

		if($mybb->get_input('pmnotice', MyBB::INPUT_INT) == 1)
		{
			$select['pmnotice'] = true;
		}

		if($mybb->get_input('pmnotify', MyBB::INPUT_INT) == 1)
		{
			$select['pmnotify'] = true;
		}

		if($mybb->get_input('invisible', MyBB::INPUT_INT) == 1)
		{
			$select['invisible'] = true;
		}

		if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 1)
		{
			$select['subscriptionmethod']['no'] = true;
		}
		else if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 2)
		{
			$select['subscriptionmethod']['email'] = true;
		}
		else if($mybb->get_input('subscriptionmethod', MyBB::INPUT_INT) == 3)
		{
			$select['subscriptionmethod']['pm'] = true;
		}
		else
		{
			$select['subscriptionmethod']['none'] = true;
		}

		if($mybb->get_input('dstcorrection', MyBB::INPUT_INT) == 2)
		{
			$select['dst']['auto'] = true;
		}
		else if($mybb->get_input('dstcorrection', MyBB::INPUT_INT) == 1)
		{
			$select['dst']['enabled'] = true;
		}
		else
		{
			$select['dst']['disabled'] = true;
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
			my_setcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey'], null, true, "lax");
		}

		if(isset($mybb->cookies['coppauser']))
		{
			$lang->redirect_registered_coppa_activate = $lang->sprintf($lang->redirect_registered_coppa_activate, $mybb->settings['bbname'], htmlspecialchars_uni($user_info['username']));
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

			$lang->redirect_registered_activation = $lang->sprintf($lang->redirect_registered_activation, $mybb->settings['bbname'], htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_activation);
		}
		else if($mybb->settings['regtype'] == "randompass")
		{
			$emailsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
			switch($mybb->settings['username_method'])
			{
				case 0:
					$emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				case 1:
					$emailmessage = $lang->sprintf($lang->email_randompassword1, $user['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				case 2:
					$emailmessage = $lang->sprintf($lang->email_randompassword2, $user['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				default:
					$emailmessage = $lang->sprintf($lang->email_randompassword, $user['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
			}
			my_mail($user_info['email'], $emailsubject, $emailmessage);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_passwordsent);
		}
		else if($mybb->settings['regtype'] == "admin")
		{
			$groups = $cache->read("usergroups");
			$admingroups = array();
			if(!empty($groups)) // Shouldn't be...
			{
				foreach($groups as $group)
				{
					if($group['cancp'] == 1)
					{
						$admingroups[] = (int)$group['gid'];
					}
				}
			}

			if(!empty($admingroups))
			{
				$sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
				foreach($admingroups as $admingroup)
				{
					switch($db->type)
					{
						case 'pgsql':
						case 'sqlite':
							$sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
							break;
						default:
							$sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
							break;
					}
				}
				$q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
				while($recipient = $db->fetch_array($q))
				{
					// First we check if the user's a super admin: if yes, we don't care about permissions
					$is_super_admin = is_super_admin($recipient['uid']);
					if(!$is_super_admin)
					{
						// Include admin functions
						if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
						{
							continue;
						}

						require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

						// Verify if we have permissions to access user-users
						require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
						if(function_exists("user_admin_permissions"))
						{
							// Get admin permissions
							$adminperms = get_admin_permissions($recipient['uid']);

							$permissions = user_admin_permissions();
							if(array_key_exists('users', $permissions['permissions']) && $adminperms['user']['users'] != 1)
							{
								continue; // No permissions
							}
						}
					}

					// Load language
					if($recipient['language'] != $lang->language && $lang->language_exists($recipient['language']))
					{
						$reset_lang = true;
						$lang->set_language($recipient['language']);
						$lang->load("member");
					}

					$subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
					$message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user['username']);
					my_mail($recipient['email'], $subject, $message);
				}

				// Reset language
				if(isset($reset_lang))
				{
					$lang->set_language($mybb->settings['bblanguage']);
					$lang->load("member");
				}
			}

			$lang->redirect_registered_admin_activate = $lang->sprintf($lang->redirect_registered_admin_activate, $mybb->settings['bbname'], htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_admin_activate);
		}
		else if($mybb->settings['regtype'] == "both")
		{
			$groups = $cache->read("usergroups");
			$admingroups = array();
			if(!empty($groups)) // Shouldn't be...
			{
				foreach($groups as $group)
				{
					if($group['cancp'] == 1)
					{
						$admingroups[] = (int)$group['gid'];
					}
				}
			}

			if(!empty($admingroups))
			{
				$sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
				foreach($admingroups as $admingroup)
				{
					switch($db->type)
					{
						case 'pgsql':
						case 'sqlite':
							$sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
							break;
						default:
							$sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
							break;
					}
				}
				$q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
				while($recipient = $db->fetch_array($q))
				{
					// First we check if the user's a super admin: if yes, we don't care about permissions
					$is_super_admin = is_super_admin($recipient['uid']);
					if(!$is_super_admin)
					{
						// Include admin functions
						if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
						{
							continue;
						}

						require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";

						// Verify if we have permissions to access user-users
						require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
							// Get admin permissions
							$adminperms = get_admin_permissions($recipient['uid']);
							if(empty($adminperms['user']['users']) || $adminperms['user']['users'] != 1)
							{
								continue; // No permissions
							}
					}

					// Load language
					if($recipient['language'] != $lang->language && $lang->language_exists($recipient['language']))
					{
						$reset_lang = true;
						$lang->set_language($recipient['language']);
						$lang->load("member");
					}

					$subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
					$message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user['username']);
					my_mail($recipient['email'], $subject, $message);
				}

				// Reset language
				if(isset($reset_lang))
				{
					$lang->set_language($mybb->settings['bblanguage']);
					$lang->load("member");
				}
			}

			$activationcode = random_str();
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => TIME_NOW,
				"code" => $activationcode,
				"type" => "b"
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

			$lang->redirect_registered_activation = $lang->sprintf($lang->redirect_registered_activation, $mybb->settings['bbname'], htmlspecialchars_uni($user_info['username']));

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_activation);
		}
		else
		{
			$lang->redirect_registered = $lang->sprintf($lang->redirect_registered, $mybb->settings['bbname'], htmlspecialchars_uni($user_info['username']));

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

	$plugins->run_hooks("member_coppa_form");

	output_page(\MyBB\template('member/coppa_form.twig'));
}

if($mybb->input['action'] == "register")
{
	$days = [];
	$mybb->input['bday1'] = $mybb->get_input('bday1', MyBB::INPUT_INT);
	for($day_count = 1; $day_count <= 31; ++$day_count)
	{
		$day['day'] = $day_count;
		if($mybb->input['bday1'] == $day_count)
		{
			$day['selected'] = true;
		}
		else
		{
			$day['selected'] = '';
		}

		$days[] = $day;
	}

	$mybb->input['bday2'] = $mybb->get_input('bday2', MyBB::INPUT_INT);
	$months = [];
	for($month_count = 1; $month_count <= 12; ++$month_count)
	{
		$month['month'] = $month_count;

		$lang_string = 'month_'.$month_count;
		$month['name'] = $lang->{$lang_string};

		if($mybb->input['bday2'] == $month_count)
		{
			$month['selected'] = true;
		}
		else
		{
			$month['selected'] = '';
		}

		$months[] = $month;
	}

	$birthday_year = $mybb->get_input('bday3', MyBB::INPUT_INT);

	if($birthday_year == 0)
	{
		$birthday_year = '';
	}

	// Is COPPA checking enabled?
	if($mybb->settings['coppa'] != "disabled" && !isset($mybb->input['step']))
	{
		// Just selected DOB, we check
		if($mybb->input['bday1'] && $mybb->input['bday2'] && $birthday_year)
		{
			my_unsetcookie("coppauser");

			$month_check = get_bdays($birthday_year);
			if($mybb->input['bday2'] < 1 || $mybb->input['bday2'] > 12 || $birthday_year < (date("Y") - 100) || $birthday_year > date("Y") || $mybb->input['bday1'] > $month_check[$mybb->input['bday2'] - 1])
			{
				error($lang->error_invalid_birthday);
			}

			$bdaytime = @mktime(0, 0, 0, $mybb->input['bday2'], $mybb->input['bday1'], $birthday_year);

			// Store DOB in cookie so we can save it with the registration
			my_setcookie("coppadob", "{$mybb->input['bday1']}-{$mybb->input['bday2']}-{$birthday_year}", -1);

			// User is <= 13, we mark as a coppa user
			if($bdaytime >= mktime(0, 0, 0, my_date('n'), my_date('d'), my_date('Y') - 13))
			{
				my_setcookie("coppauser", 1, -0);
				$under_thirteen = true;
			}

			$mybb->request_method = "";
		}
		else
		{
			// Show DOB select form
			$plugins->run_hooks("member_register_coppa");

			my_unsetcookie("coppauser");

			$coppa_desc = $mybb->settings['coppa'] == 'deny' ? $lang->coppa_desc_for_deny : $lang->coppa_desc;

			output_page(\MyBB\template('member/register_coppa.twig', [
				'days' => $days,
				'months' => $months,
				'year' => $birthday_year,
				'coppa_desc' => $coppa_desc,
			]));
			exit;
		}
	}

	if((!isset($mybb->input['agree']) && !isset($mybb->input['regsubmit'])) && $fromreg == 0 || $mybb->request_method != "post")
	{
		$coppa_agreement = false;
		// Is this user a COPPA user? We need to show the COPPA agreement too
		if($mybb->settings['coppa'] != "disabled" && ($mybb->cookies['coppauser'] == 1 || $under_thirteen))
		{
			$coppa_agreement = true;
			if($mybb->settings['coppa'] == "deny")
			{
				error($lang->error_need_to_be_thirteen);
			}
		}

		$plugins->run_hooks("member_register_agreement");

		output_page(\MyBB\template('member/register_agreement.twig', [
			'coppa_agreement' => $coppa_agreement,
		]));
	}
	else
	{
		$plugins->run_hooks("member_register_start");

		// JS validator extra
		if($mybb->settings['maxnamelength'] > 0 && $mybb->settings['minnamelength'] > 0)
		{
			$lang->js_validator_username_length = $lang->sprintf($lang->js_validator_username_length, $mybb->settings['minnamelength'], $mybb->settings['maxnamelength']);
		}

		if(isset($mybb->input['timezoneoffset']))
		{
			$timezoneoffset = $mybb->get_input('timezoneoffset');
		}
		else
		{
			$timezoneoffset = $mybb->settings['timezoneoffset'];
		}

		$timezones = build_timezone_select("timezoneoffset", $timezoneoffset, true);

		$registration['showreferrer'] = false;
		if($mybb->settings['usereferrals'] == 1 && !$mybb->user['uid'])
		{
			$registration['showreferrer'] = true;
			if(isset($mybb->cookies['mybb']['referrer']))
			{
				$query = $db->simple_select("users", "uid,username", "uid='".(int)$mybb->cookies['mybb']['referrer']."'");
				$ref = $db->fetch_array($query);
				$select['referrername'] = $ref['username'];
			}
			else if(isset($referrer))
			{
				$query = $db->simple_select("users", "username", "uid='".(int)$referrer['uid']."'");
				$ref = $db->fetch_array($query);
				$select['referrername'] = $ref['username'];
			}
			else if(!empty($select['referrername']))
			{
				$ref = get_user_by_username($select['referrername']);
				if(!$ref['uid'])
				{
					$errors[] = $lang->error_badreferrer;
				}
			}
			else
			{
				$select['referrername'] = '';
			}
		}

		$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);

		// Custom profile fields baby!
		$requiredfields = $customfields = $contactfields = [];

		if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin" || $mybb->settings['regtype'] == "both" || $mybb->get_input('coppa', MyBB::INPUT_INT) == 1)
		{
			$usergroup = 5;
		}
		else
		{
			$usergroup = 2;
		}

		$pfcache = $cache->read('profilefields');

		if(is_array($pfcache))
		{
			$jsvar_reqfields = array();
			foreach($pfcache as $profilefield)
			{
				if($profilefield['required'] != 1 && $profilefield['registration'] != 1 || !is_member($profilefield['editableby'], array('usergroup' => $mybb->user['usergroup'], 'additionalgroups' => $usergroup)))
				{
					continue;
				}

				$profilefield['type_multiselect'] = $profilefield['type_select'] = $profilefield['type_radio'] = $profilefield['type_checkbox'] = $profilefield['type_textarea'] = $profilefield['type_text'] = false;
				$seloptions = array();
				$thing = explode("\n", $profilefield['type'], "2");
				$type = trim($thing[0]);
				$options = $thing[1];
				$profilefield['field'] = "fid{$profilefield['fid']}";

				if(!empty($errors) && isset($mybb->input['profile_fields'][$field]))
				{
					$userfield = $mybb->input['profile_fields'][$profilefield['field']];
				}
				else
				{
					$userfield = '';
				}

				if($type == "multiselect")
				{
					if(!empty($errors))
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
					$profilefield['select'] = [];
					if(is_array($expoptions))
					{
						$profilefield['type_multiselect'] = true;
						foreach($expoptions as $key => $val)
						{
							$val = trim($val);
							$val = str_replace("\n", "\\n", $val);

							$select_item['selected'] = false;
							if(isset($seloptions[$val]) && $val == $seloptions[$val])
							{
								$select_item['selected'] = true;
							}

							$select_item['value'] = $val;
							$profilefield['select'][] = $select_item;
						}

						if(!$profilefield['length'])
						{
							$profilefield['length'] = 3;
						}
					}
				}
				else if($type == "select")
				{
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						$profilefield['type_select'] = true;
						$profilefield['select'] = [];
						foreach($expoptions as $key => $val)
						{
							$val = trim($val);
							$val = str_replace("\n", "\\n", $val);

							$select_item['selected'] = false;
							if($val == $userfield)
							{
								$select_item['selected'] = true;
							}

							$select_item['value'] = $val;
							$profilefield['select'][] = $select_item;
						}

						if(!$profilefield['length'])
						{
							$profilefield['length'] = 1;
						}
					}
				}
				else if($type == "radio")
				{
					$expoptions = explode("\n", $options);
					if(is_array($expoptions))
					{
						$profilefield['type_radio'] = true;
						$profilefield['radio'] = [];
						foreach($expoptions as $key => $val)
						{
							$radio_item['checked'] = false;
							if($val == $userfield)
							{
								$radio_item['checked'] = true;
							}

							$radio_item['value'] = $val;
							$profilefield['radio'][] = $radio_item;
						}
					}
				}
				else if($type == "checkbox")
				{
					if(!empty($errors))
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
						$profilefield['type_checkbox'] = true;
						$profilefield['checkbox'] = [];
						foreach($expoptions as $key => $val)
						{
							$checkbox_item['checked'] = false;
							if(isset($seloptions[$val]) && $val == $seloptions[$val])
							{
								$checkbox_item['checked'] = true;
							}

							$checkbox_item['value'] = $val;
							$profilefield['checkbox'][] = $checkbox_item;
						}
					}
				}
				else if($type == "textarea")
				{
					$profilefield['type_textarea'] = true;
					$profilefield['value'] = $userfield;
				}
				else
				{
					$profilefield['type_text'] = true;
					$profilefield['value'] = $userfield;
				}

				$profilefield['fieldtype'] = $type;

				if($profilefield['required'] == 1)
				{
					// JS validator extra, choose correct selectors for everything except single select which always has value
					if($type != 'select')
					{
						$jsvar_reqfields[] = array(
							'type' => $type,
							'fid' => $field,
						);
					}

					$requiredfields[] = $profilefield;
				}
				elseif($profilefield['contact'] == 1)
				{
					$contactfields[] = $profilefield;
				}
				else
				{
					$customfields[] = $profilefield;
				}
			}

			$registration['showrequiredfields'] = false;
			if(!empty($requiredfields))
			{
				$registration['showrequiredfields'] = true;
			}

			$registration['showcustomfields'] = false;
			if(!empty($customfields))
			{
				$registration['showcustomfields'] = true;
			}

			$registration['showcontactfields'] = false;
			if(!empty($contactfields))
			{
				$registration['showcontactfields'] = true;
			}
		}

		if(!isset($fromreg) || $fromreg == 0)
		{
			$select['allownotices'] = true;
			$select['hideemail'] = false;
			$select['receivepms'] = true;
			$select['pmnotice'] = true;
			$select['pmnotify'] = false;
			$select['invisible'] = false;

			$select['subscriptionmethod']['none'] = $select['subscriptionmethod']['email'] = $select['subscriptionmethod']['pm'] = $select['subscriptionmethod']['no'] = false;
			$select['dst']['auto'] = $select['dst']['enabled'] = $select['dst']['disabled'] = false;
			$select['username'] = $select['email'] = $select['email2'] = '';
			$regerrors = '';
		}

		// Spambot registration image thingy
		$captcha_html = 0;
		$regimage = '';
		if($mybb->settings['captchaimage'])
		{
			require_once MYBB_ROOT.'inc/class_captcha.php';
			$captcha = new captcha(true, "register");

			if($captcha->html)
			{
				$captcha_html = 1;
				$registration['regimage'] = $captcha->html;
			}
		}

		// Security Question
		$registration['showquestion'] = false;
		$question_exists = 0;
		if($mybb->settings['securityquestion'])
		{
			$registration['questionsid'] = generate_question();
			$query = $db->query("
				SELECT q.question, s.sid
				FROM ".TABLE_PREFIX."questionsessions s
				LEFT JOIN ".TABLE_PREFIX."questions q ON (q.qid=s.qid)
				WHERE q.active='1' AND s.sid='{$registration['questionsid']}'
			");
			if($db->num_rows($query) > 0)
			{
				$registration['showquestion'] = true;
				$question_exists = 1;
				$question = $db->fetch_array($query);

				//Set parser options for security question
				$parser_options = array(
					"allow_html" => 0,
					"allow_mycode" => 1,
					"allow_smilies" => 1,
					"allow_imgcode" => 1,
					"allow_videocode" => 1,
					"filter_badwords" => 1,
					"me_username" => 0,
					"shorten_urls" => 0,
					"highlight" => 0,
				);

				$registration['questionrefresh'] = false;
				//Parse question
				$registration['question'] = $parser->parse_message($question['question'], $parser_options);

				// Total questions
				$q = $db->simple_select('questions', 'COUNT(qid) as num', 'active=1');
				$num = $db->fetch_field($q, 'num');
				if($num > 1)
				{
					$registration['questionrefresh'] = true;
				}

			}
		}

		$registration['showpassword'] = false;
		if($mybb->settings['regtype'] != "randompass")
		{
			$registration['showpassword'] = true;
			// JS validator extra
			$lang->js_validator_password_length = $lang->sprintf($lang->js_validator_password_length, $mybb->settings['minpasswordlength']);

			// See if the board has "require complex passwords" enabled.
			if($mybb->settings['requirecomplexpasswords'] == 1)
			{
				$lang->password = $lang->complex_password = $lang->sprintf($lang->complex_password, $mybb->settings['minpasswordlength']);
			}
		}

		$registration['showlanguages'] = false;
		$get_languages = $lang->get_languages();
		$languages = [];
		if(count($get_languages) > 1)
		{
			$registration['showlanguages'] = true;
			foreach($get_languages as $name => $language)
			{
				$lang_array['language'] = $language;
				$lang_array['name'] = $name;

				$lang_array['selected'] = false;
				if($mybb->get_input('language') == $name)
				{
					$lang_array['selected'] = true;
				}

				$languages[] = $lang_array;
			}
		}

		// Set the time so we can find automated signups
		$registration['time'] = TIME_NOW;

		$plugins->run_hooks("member_register_end");

		$jsvar_reqfields = json_encode($jsvar_reqfields);

		$validator_javascript = "<script type=\"text/javascript\">
			var regsettings = {
				requiredfields: '{$jsvar_reqfields}',
				minnamelength: '{$mybb->settings['minnamelength']}',
				maxnamelength: '{$mybb->settings['maxnamelength']}',
				minpasswordlength: '{$mybb->settings['minpasswordlength']}',
				captchaimage: '{$mybb->settings['captchaimage']}',
				captchahtml: '{$captcha_html}',
				securityquestion: '{$mybb->settings['securityquestion']}',
				questionexists: '{$question_exists}',
				requirecomplexpasswords: '{$mybb->settings['requirecomplexpasswords']}',
				regtype: '{$mybb->settings['regtype']}',
				hiddencaptchaimage: '{$mybb->settings['hiddencaptchaimage']}'
			};

			lang.js_validator_no_username = '{$lang->js_validator_no_username}';
			lang.js_validator_username_length = '{$lang->js_validator_username_length}';
			lang.js_validator_invalid_email = '{$lang->js_validator_invalid_email}';
			lang.js_validator_email_match = '{$lang->js_validator_email_match}';
			lang.js_validator_not_empty = '{$lang->js_validator_not_empty}';
			lang.js_validator_password_length = '{$lang->js_validator_password_length}';
			lang.js_validator_password_matches = '{$lang->js_validator_password_matches}';
			lang.js_validator_no_image_text = '{$lang->js_validator_no_image_text}';
			lang.js_validator_no_security_question = '{$lang->js_validator_no_security_question}';
			lang.js_validator_bad_password_security = '{$lang->js_validator_bad_password_security}';
		</script>\n";

		output_page(\MyBB\template('member/register.twig', [
			'regerrors' => $regerrors,
			'select' => $select,
			'registration' => $registration,
			'timezones' => $timezones,
			'requiredfields' => $requiredfields,
			'customfields' => $customfields,
			'contactfields' => $contactfields,
			'languages' => $languages,
			'validator_javascript' => $validator_javascript,
		]));
	}
}

if($mybb->input['action'] == "activate")
{
	$plugins->run_hooks("member_activate_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		$options = array(
			'username_method' => $mybb->settings['username_method'],
			'fields' => '*',
		);
		$user = get_user_by_username($mybb->input['username'], $options);
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
		$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));
	}
	if(isset($mybb->input['code']) && $user)
	{
		$query = $db->simple_select("awaitingactivation", "*", "uid='".$mybb->user['uid']."' AND (type='r' OR type='e' OR type='b')");
		$activation = $db->fetch_array($query);
		if(!$activation['uid'])
		{
			error($lang->error_alreadyactivated);
		}
		if($activation['code'] !== $mybb->get_input('code'))
		{
			error($lang->error_badactivationcode);
		}

		if($activation['type'] == "b" && $activation['validated'] == 1)
		{
			error($lang->error_alreadyvalidated);
		}

		$db->delete_query("awaitingactivation", "uid='".$user['uid']."' AND (type='r' OR type='e')");

		if($user['usergroup'] == 5 && $activation['type'] != "e" && $activation['type'] != "b")
		{
			$db->update_query("users", array("usergroup" => 2), "uid='".$user['uid']."'");

			$cache->update_awaitingactivation();
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
		elseif($activation['type'] == "b")
		{
			$update = array(
				"validated" => 1,
			);
			$db->update_query("awaitingactivation", $update, "uid='".$user['uid']."' AND type='b'");
			$plugins->run_hooks("member_activate_emailactivated");

			redirect("index.php", $lang->redirect_accountactivated_admin, "", true);
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

		$activate['code'] = $mybb->get_input('code');

		if(!isset($user['username']))
		{
			$user['username'] = '';
		}

		$activate['username'] = $user['username'];

		output_page(\MyBB\template('member/activate.twig', [
			'activate' => $activate,
		]));
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

	$query = $db->simple_select("awaitingactivation", "*", "uid='".$mybb->user['uid']."' AND type='b'");
	$activation = $db->fetch_array($query);

	if($activation['validated'] == 1)
	{
		error($lang->error_activated_by_admin);
	}

	$plugins->run_hooks("member_resendactivation_end");

	output_page(\MyBB\template('member/resendactivation.twig', [
		'email' => $email,
    ]));
}

if($mybb->input['action'] == "do_resendactivation" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_resendactivation_start");

	if($mybb->settings['regtype'] == "admin")
	{
		error($lang->error_activated_by_admin);
	}

	$query = $db->query("
		SELECT u.uid, u.username, u.usergroup, u.email, a.code, a.type, a.validated
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."awaitingactivation a ON (a.uid=u.uid AND (a.type='r' OR a.type='b'))
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
			if($user['type'] == "b" && $user['validated'] == 1)
			{
				error($lang->error_activated_by_admin);
			}

			if($user['usergroup'] == 5)
			{
				if(!$user['code'])
				{
					$user['code'] = random_str();
					$uid = $user['uid'];
					$awaitingarray = array(
						"uid" => $uid,
						"dateline" => TIME_NOW,
						"code" => $user['code'],
						"type" => $user['type']
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

if($mybb->input['action'] == "do_lostpw" && $mybb->request_method == "post")
{
	$plugins->run_hooks("member_do_lostpw_start");

	$errors = array();

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

	$query = $db->simple_select("users", "*", "email='".$db->escape_string($mybb->get_input('email'))."'");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		if(count($errors) == 0)
		{
			while($user = $db->fetch_array($query))
			{
				$db->delete_query("awaitingactivation", "uid='{$user['uid']}' AND type='p'");
				$user['activationcode'] = random_str(30);
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

			$plugins->run_hooks("member_do_lostpw_end");

			redirect("index.php", $lang->redirect_lostpwsent, "", true);
		}
		else
		{
			$mybb->input['action'] = "lostpw";
		}
	}
}

if($mybb->input['action'] == "lostpw")
{
	$plugins->run_hooks("member_lostpw");

	$captcha = '';
	// Generate CAPTCHA?
	if($mybb->settings['captchaimage'])
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(true, "post");

		if($post_captcha->html)
		{
			$captcha = $post_captcha->html;
		}
	}

	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$email = $mybb->get_input('email');
	}
	else
	{
		$errors = '';
		$email = '';
	}

	output_page(\MyBB\template('member/lostpw.twig', [
			'captcha' => $captcha,
			'errors' => $errors,
			'email' => $email,
    ]));
}

if($mybb->input['action'] == "resetpassword")
{
	$plugins->run_hooks("member_resetpassword_start");

	if(isset($mybb->input['username']))
	{
		$mybb->input['username'] = $mybb->get_input('username');
		$options = array(
			'username_method' => $mybb->settings['username_method'],
			'fields' => '*',
		);
		$user = get_user_by_username($mybb->input['username'], $options);
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
		$user = get_user($mybb->get_input('uid', MyBB::INPUT_INT));
	}

	if(isset($mybb->input['code']) && $user)
	{
		$query = $db->simple_select("awaitingactivation", "code", "uid='".$user['uid']."' AND type='p'");
		$activationcode = $db->fetch_field($query, 'code');
		$now = TIME_NOW;
		if(!$activationcode || $activationcode !== $mybb->get_input('code'))
		{
			error($lang->error_badlostpwcode);
		}
		$db->delete_query("awaitingactivation", "uid='".$user['uid']."' AND type='p'");
		$username = $user['username'];

		// Generate a new password, then update it
		$password_length = (int)$mybb->settings['minpasswordlength'];

		if($password_length < 8)
		{
			$password_length = min(8, (int)$mybb->settings['maxpasswordlength']);
		}

		// Set up user handler.
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');

		while(!$userhandler->verify_password())
		{
			$password = random_str($password_length, $mybb->settings['requirecomplexpasswords']);

			$userhandler->set_data(array(
				'uid' => $user['uid'],
				'username' => $user['username'],
				'email' => $user['email'],
				'password' => $password
			));

			$userhandler->set_validated(true);
			$userhandler->errors = array();
		}

		$userhandler->update_user();

		$logindetails = array(
			'salt' => $userhandler->data['salt'],
			'password' => $userhandler->data['saltedpw'],
			'loginkey' => $userhandler->data['loginkey'],
		);

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
				$activate['lang_username'] = $lang->username;
				break;
			case 1:
				$activate['lang_username'] = $lang->username1;
				break;
			case 2:
				$activate['lang_username'] = $lang->username2;
				break;
			default:
				$activate['lang_username'] = $lang->username;
				break;
		}

		$activate['code'] = $mybb->get_input('code');

		if(!isset($mybb->input['username']))
		{
			$input_username = '';
		}

		$activate['username'] = $user['username'];

		output_page(\MyBB\template('member/resetpassword.twig', [
			'activate' => $activate,
		]));
	}
}

$do_captcha = $correct = false;
$inline_errors = "";
if($mybb->input['action'] == "do_login" && $mybb->request_method == "post")
{
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	$plugins->run_hooks("member_do_login_start");

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

	$options = array(
		'fields' => 'loginattempts',
		'username_method' => (int)$mybb->settings['username_method'],
	);

	$user_loginattempts = get_user_by_username($user['username'], $options);
	if(!empty($user_loginattempts))
	{
		$user['loginattempts'] = (int)$user_loginattempts['loginattempts'];
	}

	$loginhandler->set_data($user);
	$validated = $loginhandler->validate_login();

	if(!$validated)
	{
		$mybb->input['action'] = "login";
		$mybb->request_method = "get";

		$login_user_uid = 0;
		if(!empty($loginhandler->login_data))
		{
			$login_user_uid = (int)$loginhandler->login_data['uid'];
			$user['loginattempts'] = (int)$loginhandler->login_data['loginattempts'];
		}

		// Is a fatal call if user has had too many tries
		$logins = login_attempt_check($login_user_uid);

		$db->update_query("users", array('loginattempts' => 'loginattempts+1'), "uid='".$login_user_uid."'", 1, true);

		$errors = $loginhandler->get_friendly_errors();

		// If we need a captcha set it here
		if($mybb->settings['failedcaptchalogincount'] > 0 && ($user['loginattempts'] > $mybb->settings['failedcaptchalogincount'] || (int)$mybb->cookies['loginattempts'] > $mybb->settings['failedcaptchalogincount']))
		{
			$do_captcha = true;
			$correct = $loginhandler->captcha_verified;
		}
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

		if(!empty($mybb->input['url']) && my_strpos(basename($mybb->input['url']), 'member.php') === false && !preg_match('#^javascript:#i', $mybb->input['url']))
		{
			if((my_strpos(basename($mybb->input['url']), 'newthread.php') !== false || my_strpos(basename($mybb->input['url']), 'newreply.php') !== false) && my_strpos($mybb->input['url'], '&processed=1') !== false)
			{
				$mybb->input['url'] = str_replace('&processed=1', '', $mybb->input['url']);
			}

			$mybb->input['url'] = str_replace('&amp;', '&', $mybb->input['url']);

			if(my_strpos($mybb->input['url'], $mybb->settings['bburl'].'/') !== 0)
			{
				if(my_strpos($mybb->input['url'], '/') === 0)
				{
					$mybb->input['url'] = my_substr($mybb->input['url'], 1);
				}
				$url_segments = explode('/', $mybb->input['url']);
				$mybb->input['url'] = $mybb->settings['bburl'].'/'.end($url_segments);
			}

			// Redirect to the URL if it is not member.php
			redirect($mybb->input['url'], $lang->redirect_loggedin);
		}
		else
		{

			redirect("index.php", $lang->redirect_loggedin);
		}
	}

	$plugins->run_hooks("member_do_login_end");
}

if($mybb->input['action'] == "login")
{
	$plugins->run_hooks("member_login");

	if($mybb->user['uid'] != 0)
	{
		$lang->already_logged_in = $lang->sprintf($lang->already_logged_in, build_profile_link($mybb->user['username'], $mybb->user['uid']));
	}

	// Checks to make sure the user can login; they haven't had too many tries at logging in.
	// Is a fatal call if user has had too many tries. This particular check uses cookies, as a uid is not set yet
	// and we can't check loginattempts in the db
	login_attempt_check();

	// Redirect to the page where the user came from, but not if that was the login page.
	if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], "action=login") === false)
	{
		$login['redirect_url'] = htmlentities($_SERVER['HTTP_REFERER']);
	}

	// Show captcha image for guests if enabled and only if we have to do
	if($mybb->settings['captchaimage'] && $do_captcha == true)
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$login_captcha = new captcha(false, "post");

		if($login_captcha->type == captcha::DEFAULT_CAPTCHA)
		{
			if(!$correct)
			{
				$login_captcha->build_captcha();
			}
			else
			{
				$captcha = $login_captcha->build_hidden_captcha();
			}
		}
		elseif(in_array($login_captcha->type, array(captcha::NOCAPTCHA_RECAPTCHA, captcha::RECAPTCHA_INVISIBLE, captcha::RECAPTCHA_V3)))
		{
			$login_captcha->build_recaptcha();
		}
		elseif(in_array($login_captcha->type, array(captcha::HCAPTCHA, captcha::HCAPTCHA_INVISIBLE)))
		{
			$login_captcha->build_hcaptcha();
		}

		if($login_captcha->html)
		{
			$captcha = $login_captcha->html;
		}
	}

	if(isset($mybb->input['username']) && $mybb->request_method == "post")
	{
		$login['username'] = $mybb->get_input('username');
	}

	if(isset($mybb->input['password']) && $mybb->request_method == "post")
	{
		$login['password'] = $mybb->get_input('password');
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

	$plugins->run_hooks("member_login_end");

	output_page(\MyBB\template('member/login.twig', [
		'inline_errors' => $inline_errors,
		'login' => $login,
		'captcha' => $captcha,
	]));
}

if($mybb->input['action'] == "logout")
{
	$plugins->run_hooks("member_logout_start");

	if(!$mybb->user['uid'])
	{
		redirect("index.php", $lang->redirect_alreadyloggedout);
	}

	// Check session ID if we have one
	if(isset($mybb->input['sid']) && $mybb->get_input('sid') !== $session->sid)
	{
		error($lang->error_notloggedout);
	}
	// Otherwise, check logoutkey
	else if(!isset($mybb->input['sid']) && $mybb->get_input('logoutkey') !== $mybb->user['logoutkey'])
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

if($mybb->input['action'] == "viewnotes")
{
	$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
	$user = get_user($uid);

	// Make sure we are looking at a real user here.
	if(!$user)
	{
		error($lang->error_nomember);
	}

	if($mybb->user['uid'] == 0 || $mybb->usergroup['canmodcp'] != 1)
	{
		error_no_permission();
	}

	$user['username'] = htmlspecialchars_uni($user['username']);
	$lang->view_notes_for = $lang->sprintf($lang->view_notes_for, $user['username']);

	$user['usernotes'] = nl2br($user['usernotes']);

	$plugins->run_hooks('member_viewnotes');

	output_page(\MyBB\template('member/viewnotes.twig', [
		'user' => $user,
	]));
	exit;
}

if($mybb->input['action'] == "profile")
{
	if($mybb->usergroup['canviewprofiles'] == 0)
	{
		error_no_permission();
	}

	$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
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

	$plugins->run_hooks("member_profile_start");

	$me_username = $memprofile['username'];
	$memprofile['username'] = htmlspecialchars_uni($memprofile['username']);

	// Get member's permissions
	$memperms = user_permissions($memprofile['uid']);

	// Set display group
	$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);
	if(is_array($displaygroup))
	{
		$memperms = array_merge($memperms, $displaygroup);
	}

	$lang->nav_profile = $lang->sprintf($lang->nav_profile, $memprofile['username']);
	add_breadcrumb($lang->nav_profile);

	$memprofile['useravatar'] = format_avatar($memprofile['avatar'], $memprofile['avatardimensions']);
	$memprofile['avatar_image'] = $memprofile['useravatar']['image'];
	$memprofile['avatar_width_height'] = $memprofile['useravatar']['width_height'];

	$memprofile['hascontacts'] = false;
	$memprofile['showwebsite'] = false;
	if(my_validate_url($memprofile['website']) && !is_member($mybb->settings['hidewebsite']) && $memperms['canchangewebsite'] == 1)
	{
		$memprofile['hascontacts'] = true;
		$memprofile['showwebsite'] = true;
	}

	$memprofile['showemail'] = false;
	if($mybb->usergroup['cansendemail'] == 1 && $uid != $mybb->user['uid'] && $memprofile['hideemail'] != 1 && (my_strpos(",".$memprofile['ignorelist'].",", ",".$mybb->user['uid'].",") === false || $mybb->usergroup['cansendemailoverride'] != 0))
	{
		$memprofile['hascontacts'] = true;
		$memprofile['showemail'] = true;
	}

	$memprofile['showpm'] = false;
	if($mybb->settings['enablepms'] != 0 && $uid != $mybb->user['uid'] && $mybb->usergroup['canusepms'] == 1 && (($memprofile['receivepms'] != 0 && $memperms['canusepms'] != 0 && my_strpos(",".$memprofile['ignorelist'].",", ",".$mybb->user['uid'].",") === false) || $mybb->usergroup['canoverridepm'] == 1))
	{
		$memprofile['hascontacts'] = true;
		$memprofile['showpm'] = true;
	}

	$memprofile['showsignature'] = false;
	if($memprofile['signature'] && ($memprofile['suspendsignature'] == 0 || $memprofile['suspendsigtime'] < TIME_NOW) && !is_member($mybb->settings['hidesignatures']) && $memperms['canusesig'] && $memperms['canusesigxposts'] <= $memprofile['postnum'])
	{
		$memprofile['showsignature'] = true;

		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode'],
			"me_username" => $me_username,
			"filter_badwords" => 1
		);

		if($memperms['signofollow'])
		{
			$sig_parser['nofollow_on'] = 1;
		}

		if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
		{
			$sig_parser['allow_imgcode'] = 0;
		}

		$memprofile['signature'] = $parser->parse_message($memprofile['signature'], $sig_parser);
	}

	$daysreg = (TIME_NOW - $memprofile['regdate']) / (24 * 3600);

	if($daysreg < 1)
	{
		$daysreg = 1;
	}

	$stats = $cache->read("stats");

	// Format post count, per day count and percent of total
	$ppd = $memprofile['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if($ppd > $memprofile['postnum'])
	{
		$ppd = $memprofile['postnum'];
	}

	$numposts = $stats['numposts'];
	if($numposts == 0)
	{
		$post_percent = "0";
	}
	else
	{
		$post_percent = $memprofile['postnum'] * 100 / $numposts;
		$post_percent = round($post_percent, 2);
	}

	if($post_percent > 100)
	{
		$post_percent = 100;
	}

	// Format thread count, per day count and percent of total
	$tpd = $memprofile['threadnum'] / $daysreg;
	$tpd = round($tpd, 2);
	if($tpd > $memprofile['threadnum'])
	{
		$tpd = $memprofile['threadnum'];
	}

	$numthreads = $stats['numthreads'];
	if($numthreads == 0)
	{
		$thread_percent = "0";
	}
	else
	{
		$thread_percent = $memprofile['threadnum'] * 100 / $numthreads;
		$thread_percent = round($thread_percent, 2);
	}

	if($thread_percent > 100)
	{
		$thread_percent = 100;
	}

	if($memprofile['away'] == 1 && $mybb->settings['allowaway'] != 0)
	{
		$memprofile['awaydate'] = my_date($mybb->settings['dateformat'], $memprofile['awaydate']);

		if(!empty($memprofile['awayreason']))
		{
			$memprofile['awayreason'] = $parser->parse_badwords($memprofile['awayreason']);
		}
		else
		{
			$memprofile['awayreason'] = $lang->away_no_reason;
		}

		if($memprofile['returndate'] == '')
		{
			$memprofile['returndate'] = $lang->unknown;
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
				$memprofile['returndate'] = my_date($mybb->settings['dateformat'], $returnmkdate, "", 1, true);
			}
			else
			{
				$returnmkdate = mktime(0, 0, 0, $returnhome[1], $returnhome[0], $returnhome[2]);
				$memprofile['returndate'] = my_date($mybb->settings['dateformat'], $returnmkdate);
			}

			// If our away time has expired already, we should be back, right?
			if($returnmkdate < TIME_NOW)
			{
				$db->update_query('users', array('away' => '0', 'awaydate' => '0', 'returndate' => '', 'awayreason' => ''), 'uid=\''.(int)$memprofile['uid'].'\'');

				// Update our status to "not away"
				$memprofile['away'] = 0;
			}
		}
	}

	$memprofile['timezone'] = (float)$memprofile['timezone'];

	if($memprofile['dst'] == 1)
	{
		$memprofile['timezone']++;
		if(my_substr($memprofile['timezone'], 0, 1) != "-")
		{
			$memprofile['timezone'] = "+{$memprofile['timezone']}";
		}
	}

	$memprofile['memregdate'] = my_date($mybb->settings['dateformat'], $memprofile['regdate']);
	$memprofile['memlocaldate'] = gmdate($mybb->settings['dateformat'], TIME_NOW + ($memprofile['timezone'] * 3600));
	$memprofile['memlocaltime'] = gmdate($mybb->settings['timeformat'], TIME_NOW + ($memprofile['timezone'] * 3600));

	$localtime = $lang->sprintf($lang->local_time_format, $memprofile['memlocaldate'], $memprofile['memlocaltime']);

	if($memprofile['birthday'])
	{
		$membday = explode("-", $memprofile['birthday']);

		if($memprofile['birthdayprivacy'] != 'none')
		{
			if($membday[0] && $membday[1] && $membday[2])
			{
				$lang->membdayage = $lang->sprintf($lang->membdayage, get_age($memprofile['birthday']));

				$bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
				$memprofile['membday'] = date($bdayformat, $membday);

				$memprofile['membdayage'] = $lang->membdayage;
			}
			else if($membday[2])
			{
				$membday = mktime(0, 0, 0, 1, 1, $membday[2]);
				$memprofile['membday'] = date("Y", $membday);
				$memprofile['membdayage'] = '';
			}
			else
			{
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
				$memprofile['membday'] = date("F j", $membday);
				$memprofile['membdayage'] = '';
			}
		}

		if($memprofile['birthdayprivacy'] == 'age')
		{
			$memprofile['membday'] = $lang->birthdayhidden;
		}
		else if($memprofile['birthdayprivacy'] == 'none')
		{
			$memprofile['membday'] = $lang->birthdayhidden;
			$memprofile['membdayage'] = '';
		}
	}
	else
	{
		$memprofile['membday'] = $lang->not_specified;
		$memprofile['membdayage'] = '';
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
	unset($memprofile['user_title']);
	unset($stars);
	$starimage = '';
	if(trim($memprofile['usertitle']) != '')
	{
		// User has custom user title
		$memprofile['user_title'] = $memprofile['usertitle'];
	}
	else if(trim($displaygroup['usertitle']) != '')
	{
		// User has group title
		$memprofile['user_title'] = $displaygroup['usertitle'];
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
					$memprofile['user_title'] = $title['title'];
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
	else if(!$stars)
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

	$memprofile['groupimage'] = false;
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

		$memprofile['groupimage'] = $displaygroup['image'];
	}

	if(empty($starimage))
	{
		$starimage = $displaygroup['starimage'];
	}

	if(!empty($starimage))
	{
		// Only display stars if we have an image to use...
		$memprofile['stars'] = $stars;
		$memprofile['starimage'] = str_replace("{theme}", $theme['imgdir'], $starimage);
	}

	// User is currently online and this user has permissions to view the user on the WOL
	$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins'] * 60;
	$query = $db->simple_select("sessions", "location,nopermission", "uid='$uid' AND time>'{$timesearch}'", array('order_by' => 'time', 'order_dir' => 'DESC', 'limit' => 1));
	$session = $db->fetch_array($query);

	$memprofile['isonline'] = false;
	if($memprofile['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1 || $memprofile['uid'] == $mybb->user['uid'])
	{
		// Last Visit
        $memprofile['last_active'] = max(array($memprofile['lastactive'], $memprofile['lastvisit']));
		if(!empty($memprofile['last_active']))
		{
			$memprofile['last_active'] = my_date('relative', $memprofile['last_active']);
		}

		// Time Online
		$memprofile['time_online'] = $lang->none_registered;
		if($memprofile['timeonline'] > 0)
		{
			$memprofile['time_online'] = nice_time($memprofile['timeonline']);
		}

		// Online?
		if(!empty($session))
		{
			$memprofile['isonline'] = true;

			// Fetch their current location
			$lang->load("online");
			require_once MYBB_ROOT."inc/functions_online.php";
			$activity = fetch_wol_activity($session['location'], $session['nopermission']);
			$memprofile['online_location'] = build_friendly_wol_location($activity);
			$memprofile['online_location_time'] = my_date($mybb->settings['timeformat'], $memprofile['lastactive']);
		}
	}

	if($memprofile['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1 && $memprofile['uid'] != $mybb->user['uid'])
	{
		$memprofile['last_active'] = $lang->lastvisit_never;

		if($memprofile['lastactive'])
		{
			// We have had at least some active time, hide it instead
			$memprofile['last_active'] = $lang->lastvisit_hidden;
		}

		$memprofile['time_online'] = $lang->timeonline_hidden;

	}

	// Build Referral
	if($mybb->settings['usereferrals'] == 1)
	{
		$uid = (int) $memprofile['uid'];
		$referral_count = $memprofile['referrals'];
		$memprofile['referrals_link'] = \MyBB\template('referrals/referrals_link.twig', [
			'uid' => $uid,
			'referral_count' => $referral_count,
		]);
	}

	// Fetch the reputation for this user
	$memprofile['showreputation'] = false;
	if($memperms['usereputationsystem'] == 1 && $displaygroup['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
	{
		$memprofile['showreputation'] = true;
		$memprofile['reputation'] = get_reputation($memprofile['reputation']);

		// If this user has permission to give reputations show the vote link
		$memprofile['showvote'] = false;
		if($mybb->usergroup['cangivereputations'] == 1 && $memprofile['uid'] != $mybb->user['uid'] && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']))
		{
			$memprofile['showvote'] = true;
		}
	}

	$memprofile['showwarning'] = false;
	if($mybb->settings['enablewarningsystem'] != 0 && $memperms['canreceivewarnings'] != 0 && ($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $memprofile['uid'] && $mybb->settings['canviewownwarning'] != 0)))
	{
		$memprofile['showwarning'] = true;

		if($mybb->settings['maxwarningpoints'] < 1)
		{
			$mybb->settings['maxwarningpoints'] = 10;
		}

		$warning_level = round($memprofile['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);

		if($warning_level > 100)
		{
			$warning_level = 100;
		}

		$memprofile['warn_user'] = false;
		$memprofile['warning_link'] = 'usercp.php';
		$memprofile['warning_level'] = get_colored_warning_level($warning_level);

		if($mybb->usergroup['canwarnusers'] != 0 && $memprofile['uid'] != $mybb->user['uid'])
		{
			$memprofile['warn_user'] = true;
			$memprofile['warning_link'] = "warnings.php?uid={$memprofile['uid']}";
		}
	}

	$memprofile['profilefields'] = false;
	$customfields = $contactfields = [];

	$query = $db->simple_select("userfields", "*", "ufid = '{$uid}'");
	$userfields = $db->fetch_array($query);

	// If this user is an Administrator or a Moderator then we wish to show all profile fields
	$pfcache = $cache->read('profilefields');

	if(is_array($pfcache))
	{
		foreach($pfcache as $customfield)
		{
			if($mybb->usergroup['cancp'] != 1 && $mybb->usergroup['issupermod'] != 1 && $mybb->usergroup['canmodcp'] != 1 && !is_member($customfield['viewableby']) || !$customfield['profile'])
			{
				continue;
			}

			$thing = explode("\n", $customfield['type'], "2");
			$type = trim($thing[0]);

			$field = "fid{$customfield['fid']}";

			if(!empty($userfields[$field]))
			{

				$useropts = explode("\n", $userfields[$field]);
				$customfield['ismulti'] = false;
				if(is_array($useropts) && ($type == "multiselect" || $type == "checkbox"))
				{
					$customfield['ismulti'] = true;
					$customfield['value'] = [];
					foreach($useropts as $val)
					{
						if($val != '')
						{
							$customfield['value'][] = $val;
						}
					}
				}
				else
				{
					$parser_options = array(
						"allow_html" => $customfield['allowhtml'],
						"allow_mycode" => $customfield['allowmycode'],
						"allow_smilies" => $customfield['allowsmilies'],
						"allow_imgcode" => $customfield['allowimgcode'],
						"allow_videocode" => $customfield['allowvideocode'],
						#"nofollow_on" => 1,
						"filter_badwords" => 1
					);

					if($customfield['type'] == "textarea")
					{
						$parser_options['me_username'] = $memprofile['username'];
					}
					else
					{
						$parser_options['nl2br'] = 0;
					}

					if($mybb->user['showimages'] != 1 && $mybb->user['uid'] != 0 || $mybb->settings['guestimages'] != 1 && $mybb->user['uid'] == 0)
					{
						$parser_options['allow_imgcode'] = 0;
					}

					$customfield['value'] = $parser->parse_message($userfields[$field], $parser_options);
				}

				if($customfield['contact'])
				{
					$memprofile['hascontacts'] = true;
					$contactfields[] = $customfield;
				}
				else
				{
					$memprofile['profilefields'] = true;
					$customfields[] = $customfield;
				}
			}
		}
	}

	$memprofile['post_percent'] = $post_percent;
	$memprofile['thread_percent'] = $thread_percent;

	$memprofile['formattedname'] = format_name($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);

	$memprofile['banned'] = false;
	if($memperms['isbannedgroup'] == 1 && $mybb->usergroup['canbanusers'] == 1)
	{
		$memprofile['banned'] = true;

		// Fetch details on their ban
		$query = $db->simple_select('banned b LEFT JOIN '.TABLE_PREFIX.'users a ON (b.admin=a.uid)', 'b.*, a.username AS adminuser', "b.uid='{$uid}'", array('limit' => 1));
		$memban = $db->fetch_array($query);

		if($memban['reason'])
		{
			$memprofile['banned_reason'] = $parser->parse_badwords($memban['reason']);
		}
		else
		{
			$memprofile['banned_reason'] = $lang->na;
		}

		$memprofile['perm_ban'] = false;
		if($memban['lifted'] == 'perm' || $memban['lifted'] == '' || $memban['bantime'] == 'perm' || $memban['bantime'] == '---')
		{
			$memprofile['perm_ban'] = true;
			$memprofile['banlength'] = $lang->permanent;
		}
		else
		{
			// Set up the array of ban times.
			$bantimes = fetch_ban_times();

			$memprofile['banlength'] = $bantimes[$memban['bantime']];
			$remaining = $memban['lifted'] - TIME_NOW;

			$memprofile['timeremaining'] = nice_time($remaining, array('short' => 1, 'seconds' => false))."";

			if($remaining < 3600)
			{
				$memprofile['banned_class'] = "high_banned";
			}
			else if($remaining < 86400)
			{
				$memprofile['banned_class'] = "moderate_banned";
			}
			else if($remaining < 604800)
			{
				$memprofile['banned_class'] = "low_banned";
			}
			else
			{
				$memprofile['banned_class'] = "normal_banned";
			}
		}

		$memprofile['banned_adminuser'] = build_profile_link(htmlspecialchars_uni($memban['adminuser']), $memban['admin']);
	}

	$memprofile['showadminoptions'] = false;
	if($mybb->usergroup['cancp'] == 1 && $mybb->config['hide_admin_links'] != 1)
	{
		$memprofile['showadminoptions'] = true;
		$memprofile['admin_dir'] = $config['admin_dir'];
	}

	$memprofile['showmodoptions'] = $memprofile['showmanageuser'] = false;
	$can_purge_spammer = purgespammer_show($memprofile['postnum'], $memprofile['usergroup'], $memprofile['uid']);
	if($mybb->usergroup['canmodcp'] == 1 || $can_purge_spammer)
	{
		$memprofile['showmodoptions'] = true;
		if($mybb->usergroup['canuseipsearch'] == 1)
		{
			$memprofile['regip'] = my_inet_ntop($db->unescape_binary($memprofile['regip']));
			$memprofile['lastip'] = my_inet_ntop($db->unescape_binary($memprofile['lastip']));
		}

		$memprofile['showeditprofile'] = false;
		if($mybb->usergroup['caneditprofiles'] == 1 && modcp_can_manage_user($memprofile['uid']))
		{
			$memprofile['showmanageuser'] = true;
			$memprofile['showeditprofile'] = true;
		}

		$memprofile['showbanuser'] = false;
		if($mybb->usergroup['canbanusers'] == 1 && modcp_can_manage_user($memprofile['uid']))
		{
			$memprofile['showmanageuser'] = true;
			$memprofile['showbanuser'] = true;
		}

		$memprofile['showpurgespammer'] = false;
		if($can_purge_spammer)
		{
			$memprofile['showmanageuser'] = true;
			$memprofile['showpurgespammer'] = true;
		}
	}

	$memprofile['showoptions'] = false;
	if($mybb->user['uid'] != $memprofile['uid'] && $mybb->user['uid'] != 0)
	{
		$memprofile['showoptions'] = true;

		$buddy_list = explode(',', $mybb->user['buddylist']);
		$ignore_list = explode(',', $mybb->user['ignorelist']);

		$memprofile['onbuddylist'] = false;
		if(in_array($uid, $buddy_list) && !in_array($uid, $ignore_list))
		{
			$memprofile['onbuddylist'] = true;
		}

		$memprofile['showbuddy'] = false;
		if(!in_array($uid, $ignore_list))
		{
			$memprofile['showbuddy'] = true;
		}

		$memprofile['onignorelist'] = false;
		if(in_array($uid, $ignore_list))
		{
			$memprofile['onignorelist'] = true;
		}

		$memprofile['showignore'] = false;
		if(!in_array($uid, $buddy_list))
		{
			$memprofile['showignore'] = true;
		}

		$memprofile['showreport'] = false;
		if(isset($memperms['canbereported']) && $memperms['canbereported'] == 1)
		{
			$memprofile['showreport'] = true;
			$reportable = true;
			$query = $db->simple_select("reportedcontent", "reporters", "reportstatus != '1' AND id = '{$memprofile['uid']}' AND type = 'profile'");
			if($db->num_rows($query))
			{
				$report = $db->fetch_array($query);
				$report['reporters'] = my_unserialize($report['reporters']);
				if(is_array($report['reporters']) && in_array($mybb->user['uid'], $report['reporters']))
				{
					$memprofile['showreport'] = false;
				}
			}
		}
	}

	$plugins->run_hooks("member_profile_end");

	output_page(\MyBB\template('member/profile.twig', [
		'memprofile' => $memprofile,
		'customfields' => $customfields,
		'contactfields' => $contactfields,
		'memperms' => $memperms,
	]));
}

if($mybb->input['action'] == "do_emailuser" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("member_do_emailuser_start");

	// Guests or those without permission can't email other users
	if($mybb->usergroup['cansendemail'] == 0)
	{
		error_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "{$user_check} AND dateline >= '".(TIME_NOW - (60 * 60 * 24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count >= $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	// Check email flood control
	if($mybb->usergroup['emailfloodtime'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$timecut = TIME_NOW - $mybb->usergroup['emailfloodtime'] * 60;

		$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_email = $db->fetch_array($query);

		// Users last email was within the flood time, show the error
		if($last_email['mid'])
		{
			$remaining_time = ($mybb->usergroup['emailfloodtime'] * 60) - (TIME_NOW - $last_email['dateline']);

			if($remaining_time == 1)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
			}
			elseif($remaining_time < 60)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
			}
			elseif($remaining_time > 60 && $remaining_time < 120)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
			}
			else
			{
				$remaining_time_minutes = ceil($remaining_time / 60);
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
			}

			error($lang->error_emailflooding);
		}
	}

	$query = $db->simple_select("users", "uid, username, email, hideemail", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
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

	if($mybb->user['uid'])
	{
		$mybb->input['fromemail'] = $mybb->user['email'];
		$mybb->input['fromname'] = $mybb->user['username'];
	}

	if(!validate_email_format($mybb->input['fromemail']))
	{
		$errors[] = $lang->error_invalidfromemail;
	}

	if(empty($mybb->input['fromname']))
	{
		$errors[] = $lang->error_noname;
	}

	if(empty($mybb->input['subject']))
	{
		$errors[] = $lang->error_no_email_subject;
	}

	if(empty($mybb->input['message']))
	{
		$errors[] = $lang->error_no_email_message;
	}

	if($mybb->settings['captchaimage'] && $mybb->user['uid'] == 0)
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

	if(count($errors) == 0)
	{
		if($mybb->settings['mail_handler'] == 'smtp')
		{
			$from = $mybb->input['fromemail'];
		}
		else
		{
			$from = "{$mybb->input['fromname']} <{$mybb->input['fromemail']}>";
		}

		$message = $lang->sprintf($lang->email_emailuser, $to_user['username'], $mybb->input['fromname'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->get_input('message'));
		my_mail($to_user['email'], $mybb->get_input('subject'), $message, '', '', '', false, 'text', '', $from);

		if($mybb->settings['mail_logging'] > 0)
		{
			// Log the message
			$log_entry = array(
				"subject" => $db->escape_string($mybb->get_input('subject')),
				"message" => $db->escape_string($mybb->get_input('message')),
				"dateline" => TIME_NOW,
				"fromuid" => $mybb->user['uid'],
				"fromemail" => $db->escape_string($mybb->input['fromemail']),
				"touid" => $to_user['uid'],
				"toemail" => $db->escape_string($to_user['email']),
				"tid" => 0,
				"ipaddress" => $db->escape_binary($session->packedip),
				"type" => 1
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
	if($mybb->usergroup['cansendemail'] == 0)
	{
		error_no_permission();
	}

	// Check group limits
	if($mybb->usergroup['maxemails'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$query = $db->simple_select("maillogs", "COUNT(*) AS sent_count", "{$user_check} AND dateline >= '".(TIME_NOW - (60 * 60 * 24))."'");
		$sent_count = $db->fetch_field($query, "sent_count");
		if($sent_count >= $mybb->usergroup['maxemails'])
		{
			$lang->error_max_emails_day = $lang->sprintf($lang->error_max_emails_day, $mybb->usergroup['maxemails']);
			error($lang->error_max_emails_day);
		}
	}

	// Check email flood control
	if($mybb->usergroup['emailfloodtime'] > 0)
	{
		if($mybb->user['uid'] > 0)
		{
			$user_check = "fromuid='{$mybb->user['uid']}'";
		}
		else
		{
			$user_check = "ipaddress=".$db->escape_binary($session->packedip);
		}

		$timecut = TIME_NOW - $mybb->usergroup['emailfloodtime'] * 60;

		$query = $db->simple_select("maillogs", "mid, dateline", "{$user_check} AND dateline > '{$timecut}'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_email = $db->fetch_array($query);

		// Users last email was within the flood time, show the error
		if($last_email['mid'])
		{
			$remaining_time = ($mybb->usergroup['emailfloodtime'] * 60) - (TIME_NOW - $last_email['dateline']);

			if($remaining_time == 1)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_second, $mybb->usergroup['emailfloodtime']);
			}
			elseif($remaining_time < 60)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_seconds, $mybb->usergroup['emailfloodtime'], $remaining_time);
			}
			elseif($remaining_time > 60 && $remaining_time < 120)
			{
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_1_minute, $mybb->usergroup['emailfloodtime']);
			}
			else
			{
				$remaining_time_minutes = ceil($remaining_time / 60);
				$lang->error_emailflooding = $lang->sprintf($lang->error_emailflooding_minutes, $mybb->usergroup['emailfloodtime'], $remaining_time_minutes);
			}

			error($lang->error_emailflooding);
		}
	}

	$query = $db->simple_select("users", "uid, username, email, hideemail, ignorelist", "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
	$to_user = $db->fetch_array($query);

	$to_user['username'] = htmlspecialchars_uni($to_user['username']);
	$lang->email_user = $lang->sprintf($lang->email_user, $to_user['username']);
	$email['uid'] = $to_user['uid'];

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

	$email['fromname'] = $email['fromemail'] = $email['subject'] = $email['message'] = '';
	if(isset($errors) && count($errors) > 0)
	{
		$errors = inline_error($errors);
		$email['fromname'] = $mybb->get_input('fromname');
		$email['fromemail'] = $mybb->get_input('fromemail');
		$email['subject'] = $mybb->get_input('subject');
		$email['message'] = $mybb->get_input('message');
	}

	// Generate CAPTCHA?
	$captcha = '';
	if($mybb->settings['captchaimage'] && $mybb->user['uid'] == 0)
	{
		require_once MYBB_ROOT.'inc/class_captcha.php';
		$post_captcha = new captcha(true, "post");

		if($post_captcha->html)
		{
			$captcha = $post_captcha->html;
		}
	}

	$email['guest'] = false;
	if($mybb->user['uid'] == 0)
	{
		$email['guest'] = true;
	}

	$plugins->run_hooks("member_emailuser_end");

	output_page(\MyBB\template('member/emailuser.twig', [
		'errors' => $errors,
		'captcha' => $captcha,
		'email' => $email,
	]));
}

if($mybb->input['action'] == 'referrals')
{
	$plugins->run_hooks('member_referrals_start');

	$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
	if(!$uid)
	{
		error($lang->referrals_no_user_specified);
	}

	$user = get_user($uid);
	if(!$user['$uid'])
	{
		error($lang->referrals_invalid_user);
	}

	$lang->nav_referrals = $lang->sprintf($lang->nav_referrals, $user['username']);
	add_breadcrumb($lang->nav_referrals);

	$query = $db->simple_select('users', 'COUNT(uid) AS total', "referrer='{$uid}'");
	$referral_count = $db->fetch_field($query, 'total');

	$bg_color = 'trow1';

	if($referral_count > 0)
	{
		// Figure out if we need to display multiple pages.
		$perpage = 20;
		if ((int) $mybb->settings['referralsperpage']) {
			$perpage = (int) $mybb->settings['referralsperpage'];
		}

		$page = 1;
		if($mybb->get_input('page', MyBB::INPUT_INT))
		{
			$page = $mybb->get_input('page', MyBB::INPUT_INT);
		}

		$pages = ceil($referral_count / $perpage);

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		$multipage = multipage($referral_count, $perpage, $page, "member.php?action=referrals&amp;uid={$uid}");

        $referrals = array();
		foreach(get_user_referrals($uid, $start, $perpage) as $referral)
		{
			$username = htmlspecialchars_uni($referral['username']);
			$username = format_name($username, $referral['usergroup'], $referral['displaygroup']);
			$username = build_profile_link($username, $referral['uid']);

			$regdate = my_date('normal', $referral['regdate']);

			$referral['username'] = $username;
			$referral['regdate'] = $regdate;

			$referrals[] = $referral;
        }
	}

	$plugins->run_hooks('member_referrals_end');

	output_page(\MyBB\template('referrals/referrals.twig', [
		'referral_count' => $referral_count,
		'referrals' => $referrals,
		'multipage' => $multipage,
	]));
}

if(!$mybb->input['action'])
{
	header("Location: index.php");
}
