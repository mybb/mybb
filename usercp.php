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
define('THIS_SCRIPT', 'usercp.php');
define("ALLOWABLE_PAGE", "removesubscription,removesubscriptions");

$templatelist = "usercp,usercp_nav,usercp_changename,usercp_password,usercp_subscriptions_thread,forumbit_depth2_forum_lastpost,usercp_forumsubscriptions_forum,postbit_reputation_formatted,usercp_subscriptions_thread_icon";
$templatelist .= ",usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups,usercp_nav_attachments,usercp_options_style,usercp_warnings_warning_post";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_usergroups_leader_usergroup,usercp_usergroups_leader,usercp_currentavatar,usercp_reputation,usercp_avatar_remove,usercp_resendactivation";
$templatelist .= ",usercp_attachments_attachment,usercp_attachments,usercp_forumsubscriptions_none";
$templatelist .= ",usercp_forumsubscriptions,usercp_subscriptions_none,usercp_subscriptions,usercp_options_pms_from_buddys,usercp_options_tppselect,usercp_options_pppselect,usercp_themeselector";
$templatelist .= ",usercp_nav_editsignature,usercp_referrals,usercp_notepad,usercp_latest_threads_threads,forumdisplay_thread_gotounread,usercp_latest_threads,usercp_subscriptions_remove,usercp_nav_messenger_folder";
$templatelist .= ",usercp_editsig_suspended,usercp_editsig,usercp_avatar_current,usercp_options_timezone_option,usercp_drafts,usercp_options_language,usercp_options_date_format,usercp_latest_subscribed,usercp_warnings";
$templatelist .= ",usercp_avatar,usercp_editlists_userusercp_editlists,usercp_drafts_draft,usercp_usergroups_joingroup,usercp_attachments_none,usercp_avatar_upload,usercp_options_timezone,usercp_usergroups_joinable_usergroup_join";
$templatelist .= ",usercp_warnings_warning,usercp_nav_messenger_tracking,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";
$templatelist .= ",codebuttons,usercp_nav_messenger_compose,usercp_options_language_option,usercp_editlists,usercp_latest_subscribed_threads,usercp_nav_home";
$templatelist .= ",usercp_options_tppselect_option,usercp_options_pppselect_option,forumbit_depth2_forum_lastpost_never,forumbit_depth2_forum_lastpost_hidden,usercp_avatar_auto_resize_auto,usercp_avatar_auto_resize_user,usercp_options";
$templatelist .= ",usercp_editlists_no_buddies,usercp_editlists_no_ignored,usercp_editlists_no_requests,usercp_editlists_received_requests,usercp_editlists_sent_requests,usercp_drafts_draft_thread,usercp_drafts_draft_forum,usercp_editlists_user";
$templatelist .= ",usercp_usergroups_leader_usergroup_memberlist,usercp_usergroups_leader_usergroup_moderaterequests,usercp_usergroups_memberof_usergroup_leaveprimary,usercp_usergroups_memberof_usergroup_display,usercp_email,usercp_options_pms";
$templatelist .= ",usercp_usergroups_memberof_usergroup_leaveleader,usercp_usergroups_memberof_usergroup_leaveother,usercp_usergroups_memberof_usergroup_leave,usercp_usergroups_joinable_usergroup_description,usercp_options_time_format";
$templatelist .= ",usercp_editlists_sent_request,usercp_editlists_received_request,usercp_drafts_none,usercp_usergroups_memberof_usergroup_setdisplay,usercp_usergroups_memberof_usergroup_description,usercp_options_quick_reply";
$templatelist .= ",usercp_addsubscription_thread,forumdisplay_password,forumdisplay_password_wrongpass,";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_search.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("usercp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == 0)
{
	error_no_permission();
}

$errors = '';
$error = '';

$mybb->input['action'] = $mybb->get_input('action');

usercp_menu();

$server_http_referer = '';
if(isset($_SERVER['HTTP_REFERER']))
{
	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	if(my_strpos($server_http_referer, $mybb->settings['bburl'].'/') !== 0)
	{
		if(my_strpos($server_http_referer, '/') === 0)
		{
			$server_http_referer = my_substr($server_http_referer, 1);
		}
		$url_segments = explode('/', $server_http_referer);
		$server_http_referer = $mybb->settings['bburl'].'/'.end($url_segments);
	}
}

$plugins->run_hooks("usercp_start");
if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler();

	$data = array(
		'uid' => $mybb->user['uid'],
		'signature' => $mybb->get_input('signature'),
	);

	$userhandler->set_data($data);

	if(!$userhandler->verify_signature())
	{
		$error = inline_error($userhandler->get_friendly_errors());
	}

	if(isset($error) || !empty($mybb->input['preview']))
	{
		$mybb->input['action'] = "editsig";
	}
}

// Make navigation
add_breadcrumb($lang->nav_usercp, "usercp.php");

switch($mybb->input['action'])
{
	case "profile":
	case "do_profile":
		add_breadcrumb($lang->ucp_nav_profile);
		break;
	case "options":
	case "do_options":
		add_breadcrumb($lang->nav_options);
		break;
	case "email":
	case "do_email":
		add_breadcrumb($lang->nav_email);
		break;
	case "password":
	case "do_password":
		add_breadcrumb($lang->nav_password);
		break;
	case "changename":
	case "do_changename":
		add_breadcrumb($lang->nav_changename);
		break;
	case "subscriptions":
		add_breadcrumb($lang->ucp_nav_subscribed_threads);
		break;
	case "forumsubscriptions":
		add_breadcrumb($lang->ucp_nav_forum_subscriptions);
		break;
	case "editsig":
	case "do_editsig":
		add_breadcrumb($lang->nav_editsig);
		break;
	case "avatar":
	case "do_avatar":
		add_breadcrumb($lang->nav_avatar);
		break;
	case "notepad":
	case "do_notepad":
		add_breadcrumb($lang->ucp_nav_notepad);
		break;
	case "editlists":
	case "do_editlists":
		add_breadcrumb($lang->ucp_nav_editlists);
		break;
	case "drafts":
		add_breadcrumb($lang->ucp_nav_drafts);
		break;
	case "usergroups":
		add_breadcrumb($lang->ucp_nav_usergroups);
		break;
	case "attachments":
		add_breadcrumb($lang->ucp_nav_attachments);
		break;
}

if($mybb->input['action'] == "do_profile" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();

	$plugins->run_hooks("usercp_do_profile_start");

	if($mybb->get_input('away', MyBB::INPUT_INT) == 1 && $mybb->settings['allowaway'] != 0)
	{
		$awaydate = TIME_NOW;
		if(!empty($mybb->input['awayday']))
		{
			// If the user has indicated that they will return on a specific day, but not month or year, assume it is current month and year
			if(!$mybb->get_input('awaymonth', MyBB::INPUT_INT))
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			if(!$mybb->get_input('awayyear', MyBB::INPUT_INT))
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}

			$return_month = (int)substr($mybb->get_input('awaymonth'), 0, 2);
			$return_day = (int)substr($mybb->get_input('awayday'), 0, 2);
			$return_year = min((int)$mybb->get_input('awayyear'), 9999);

			// Check if return date is after the away date.
			$returntimestamp = gmmktime(0, 0, 0, $return_month, $return_day, $return_year);
			$awaytimestamp = gmmktime(0, 0, 0, my_date('n', $awaydate), my_date('j', $awaydate), my_date('Y', $awaydate));
			if($return_year < my_date('Y', $awaydate) || ($returntimestamp < $awaytimestamp && $return_year == my_date('Y', $awaydate)))
			{
				error($lang->error_usercp_return_date_past);
			}

			$returndate = "{$return_day}-{$return_month}-{$return_year}";
		}
		else
		{
			$returndate = "";
		}
		$away = array(
			"away" => 1,
			"date" => $awaydate,
			"returndate" => $returndate,
			"awayreason" => $mybb->get_input('awayreason')
		);
	}
	else
	{
		$away = array(
			"away" => 0,
			"date" => '',
			"returndate" => '',
			"awayreason" => ''
		);
	}

	$bday = array(
		"day" => $mybb->get_input('bday1', MyBB::INPUT_INT),
		"month" => $mybb->get_input('bday2', MyBB::INPUT_INT),
		"year" => $mybb->get_input('bday3', MyBB::INPUT_INT)
	);

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array_merge($user, array(
		"uid" => $mybb->user['uid'],
		"postnum" => $mybb->user['postnum'],
		"usergroup" => $mybb->user['usergroup'],
		"additionalgroups" => $mybb->user['additionalgroups'],
		"birthday" => $bday,
		"birthdayprivacy" => $mybb->get_input('birthdayprivacy'),
		"away" => $away,
		"profile_fields" => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY)
	));

	if($mybb->usergroup['canchangewebsite'] == 1)
	{
		$user['website'] = $mybb->get_input('website');
	}

	if($mybb->usergroup['cancustomtitle'] == 1)
	{
		if($mybb->get_input('usertitle') != '')
		{
			$user['usertitle'] = $mybb->get_input('usertitle');
		}
		elseif(!empty($mybb->input['reverttitle']))
		{
			$user['usertitle'] = '';
		}
	}
	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$raw_errors = $userhandler->get_errors();

		// Set to stored value if invalid
		if(array_key_exists("invalid_birthday_privacy", $raw_errors) || array_key_exists("conflicted_birthday_privacy", $raw_errors))
		{
			$mybb->input['birthdayprivacy'] = $mybb->user['birthdayprivacy'];
			$bday = explode("-", $mybb->user['birthday']);

			if(isset($bday[2]))
			{
				$mybb->input['bday3'] = $bday[2];
			}
		}

		$errors = inline_error($errors);
		$mybb->input['action'] = "profile";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks('usercp_do_profile_end');
		redirect("usercp.php?action=profile", $lang->redirect_profileupdated);
	}
}

if($mybb->input['action'] == "profile")
{
	if($errors)
	{
		$user = $mybb->input;
		$bday = [];
		$bday[0] = $mybb->get_input('bday1', MyBB::INPUT_INT);
		$bday[1] = $mybb->get_input('bday2', MyBB::INPUT_INT);
		$bday[2] = $mybb->get_input('bday3', MyBB::INPUT_INT);

		$returndate = [];
		$returndate[0] = $mybb->get_input('awayday', MyBB::INPUT_INT);
		$returndate[1] = $mybb->get_input('awaymonth', MyBB::INPUT_INT);
		$returndate[2] = $mybb->get_input('awayyear', MyBB::INPUT_INT);
		$user['awayreason'] = htmlspecialchars_uni($mybb->get_input('awayreason'));
	}
	else
	{
		$user = $mybb->user;
		$bday = explode("-", $user['birthday']);
		if(!isset($bday[1]))
		{
			$bday[1] = 0;
		}
	}
	if(!isset($bday[2]) || $bday[2] == 0)
	{
		$bday[2] = '';
	}

	$plugins->run_hooks('usercp_profile_start');

	// Contact fields
	if(!my_validate_url($user['website']))
	{
		$user['website'] = '';
	}
	else
	{
		$user['website'] = htmlspecialchars_uni($user['website']);
	}

	// Away informations
	if($mybb->settings['allowaway'] != 0)
	{
		$returndate = explode("-", $mybb->user['returndate']);
		if(!isset($returndate[1]))
		{
			$returndate[1] = 0;
		}

		if(!isset($returndate[2]))
		{
			$returndate[2] = '';
		}
	}

	// Custom profile fields baby!
	$requiredfields = $customfields = $contactfields = [];
	$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);

	$pfcache = $cache->read('profilefields');

	if(is_array($pfcache))
	{
		foreach($pfcache as $profilefield)
		{
			if(!is_member($profilefield['editableby']) || ($profilefield['postnum'] && $profilefield['postnum'] > $mybb->user['postnum']))
			{
				continue;
			}

			$thing = explode("\n", $profilefield['type'], "2");
			$profilefield['attributes']['type'] = $thing[0];
			$profilefield['attributes']['options'] = [];
			if(isset($thing[1]))
			{
				$profilefield['attributes']['options'] = $thing[1];
			}

			if($profilefield['required'] == 1)
			{
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
	}

	$defaultTitle = '';
	if($mybb->usergroup['cancustomtitle'] == 1 && $mybb->usergroup['usertitle'] == "")
	{
		$usertitles = $cache->read('usertitles');

		foreach($usertitles as $title)
		{
			if($title['posts'] <= $mybb->user['postnum'])
			{
				$defaultTitle = htmlspecialchars_uni($title['title']);
				break;
			}
		}
	}

	$plugins->run_hooks('usercp_profile_end');

	output_page(\MyBB\template('usercp/profile.twig', [
		'errors' => $errors,
		'requiredFields' => $requiredfields,
		'customFields' => $customfields,
		'defaultTitle' => $defaultTitle,
		'user' => $user,
		'bday' => $bday,
		'returndate' => $returndate,
		'contactFields' => $contactfields
	]));
}

if($mybb->input['action'] == "do_options" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();

	$plugins->run_hooks("usercp_do_options_start");

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array_merge($user, array(
		"uid" => $mybb->user['uid'],
		"style" => $mybb->get_input('style', MyBB::INPUT_INT),
		"dateformat" => $mybb->get_input('dateformat', MyBB::INPUT_INT),
		"timeformat" => $mybb->get_input('timeformat', MyBB::INPUT_INT),
		"timezone" => $db->escape_string($mybb->get_input('timezoneoffset')),
		"language" => $mybb->get_input('language'),
		'usergroup' => $mybb->user['usergroup'],
		'additionalgroups' => $mybb->user['additionalgroups']
	));

	$user['options'] = array(
		"allownotices" => $mybb->get_input('allownotices', MyBB::INPUT_INT),
		"hideemail" => $mybb->get_input('hideemail', MyBB::INPUT_INT),
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', MyBB::INPUT_INT),
		"invisible" => $mybb->get_input('invisible', MyBB::INPUT_INT),
		"dstcorrection" => $mybb->get_input('dstcorrection', MyBB::INPUT_INT),
		"threadmode" => $mybb->get_input('threadmode'),
		"showimages" => $mybb->get_input('showimages', MyBB::INPUT_INT),
		"showvideos" => $mybb->get_input('showvideos', MyBB::INPUT_INT),
		"showsigs" => $mybb->get_input('showsigs', MyBB::INPUT_INT),
		"showavatars" => $mybb->get_input('showavatars', MyBB::INPUT_INT),
		"showquickreply" => $mybb->get_input('showquickreply', MyBB::INPUT_INT),
		"receivepms" => $mybb->get_input('receivepms', MyBB::INPUT_INT),
		"pmnotice" => $mybb->get_input('pmnotice', MyBB::INPUT_INT),
		"receivefrombuddy" => $mybb->get_input('receivefrombuddy', MyBB::INPUT_INT),
		"daysprune" => $mybb->get_input('daysprune', MyBB::INPUT_INT),
		"showcodebuttons" => $mybb->get_input('showcodebuttons', MyBB::INPUT_INT),
		"sourceeditor" => $mybb->get_input('sourceeditor', MyBB::INPUT_INT),
		"pmnotify" => $mybb->get_input('pmnotify', MyBB::INPUT_INT),
		"buddyrequestspm" => $mybb->get_input('buddyrequestspm', MyBB::INPUT_INT),
		"buddyrequestsauto" => $mybb->get_input('buddyrequestsauto', MyBB::INPUT_INT),
		"showredirect" => $mybb->get_input('showredirect', MyBB::INPUT_INT),
		"classicpostbit" => $mybb->get_input('classicpostbit', MyBB::INPUT_INT)
	);

	if($mybb->settings['usertppoptions'])
	{
		$user['options']['tpp'] = $mybb->get_input('tpp', MyBB::INPUT_INT);
	}

	if($mybb->settings['userpppoptions'])
	{
		$user['options']['ppp'] = $mybb->get_input('ppp', MyBB::INPUT_INT);
	}

	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
		$errors = inline_error($errors);
		$mybb->input['action'] = "options";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks('usercp_do_options_end');

		redirect("usercp.php?action=options", $lang->redirect_optionsupdated);
	}
}

if($mybb->input['action'] == "options")
{
	if($errors != '')
	{
		$user = $mybb->input;
	}
	else
	{
		$user = $mybb->user;
	}

	$plugins->run_hooks("usercp_options_start");

	$languages = $lang->get_languages();
	$timezones = build_timezone_select("timezoneoffset", $mybb->user['timezone'], true);

	if(!isset($user['style']))
	{
		$user['style'] = '';
	}

	$stylelist = build_theme_select("style", $user['style']);

	$plugins->run_hooks('usercp_options_end');

	output_page(\MyBB\template('usercp/options.twig', [
		'errors' => $errors,
		'user' => $user,
		'date_formats' => $date_formats,
		'time_formats' => $time_formats,
		'timezones' => $timezones,
		'languages' => $languages,
		'stylelist' => $stylelist
	]));
}

if($mybb->input['action'] == 'do_email' && $mybb->request_method == 'post')
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = [];

	$plugins->run_hooks('usercp_do_email_start');
	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');

		$user = [
			'uid' => $mybb->user['uid'],
			'email' => $mybb->get_input('email'),
			'email2' => $mybb->get_input('email2'),
		];

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$activation = false;
			// Checking for pending activations for non-activated accounts
			if($mybb->user['usergroup'] == 5 && ($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "both"))
			{
				$query = $db->simple_select("awaitingactivation", "*", "uid='".$mybb->user['uid']."' AND (type='r' OR type='b')");
				$activation = $db->fetch_array($query);
			}
			if($activation)
			{
				$userhandler->update_user();

				$db->delete_query("awaitingactivation", "uid='".$mybb->user['uid']."'");

				// Send new activation mail for non-activated accounts
				$activationcode = random_str();
				$activationarray = array(
					"uid" => $mybb->user['uid'],
					"dateline" => TIME_NOW,
					"code" => $activationcode,
					"type" => $activation['type']
				);
				$db->insert_query("awaitingactivation", $activationarray);
				$emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
				switch($mybb->settings['username_method'])
				{
					case 0:
						$emailmessage = $lang->sprintf($lang->email_activateaccount, $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->user['uid'], $activationcode);
						break;
					case 1:
						$emailmessage = $lang->sprintf($lang->email_activateaccount1, $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->user['uid'], $activationcode);
						break;
					case 2:
						$emailmessage = $lang->sprintf($lang->email_activateaccount2, $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->user['uid'], $activationcode);
						break;
					default:
						$emailmessage = $lang->sprintf($lang->email_activateaccount, $mybb->user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $mybb->user['uid'], $activationcode);
						break;
				}
				my_mail($mybb->user['email'], $emailsubject, $emailmessage);

				$plugins->run_hooks("usercp_do_email_changed");
				redirect("usercp.php?action=email", $lang->redirect_emailupdated);
			}
			elseif($mybb->usergroup['cancp'] != 1 && ($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "both"))
			{
				$uid = $mybb->user['uid'];
				$username = $mybb->user['username'];

				// Emails require verification
				$activationcode = random_str();
				$db->delete_query('awaitingactivation', "uid='".$mybb->user['uid']."'");

				$newactivation = [
					'uid' => $mybb->user['uid'],
					'dateline' => TIME_NOW,
					'code' => $activationcode,
					'type' => "e",
					'misc' => $db->escape_string($mybb->get_input('email')),
				];

				$db->insert_query('awaitingactivation', $newactivation);

				$mail_message = $lang->sprintf($lang->email_changeemail, $mybb->user['username'],
					$mybb->settings['bbname'], $mybb->user['email'], $mybb->get_input('email'),
					$mybb->settings['bburl'], $activationcode, $mybb->user['username'], $mybb->user['uid']);

				$lang->emailsubject_changeemail = $lang->sprintf($lang->emailsubject_changeemail,
					$mybb->settings['bbname']);
				my_mail($mybb->get_input('email'), $lang->emailsubject_changeemail, $mail_message);

				$plugins->run_hooks('usercp_do_email_verify');
				error($lang->redirect_changeemail_activation);
			}
			else
			{
				$userhandler->update_user();
				// Email requires no activation
				$mail_message = $lang->sprintf($lang->email_changeemail_noactivation, $mybb->user['username'],
					$mybb->settings['bbname'], $mybb->user['email'], $mybb->get_input('email'),
					$mybb->settings['bburl']);
				my_mail($mybb->get_input('email'),
					$lang->sprintf($lang->emailsubject_changeemail, $mybb->settings['bbname']), $mail_message);
				$plugins->run_hooks('usercp_do_email_changed');
				redirect('usercp.php?action=email', $lang->redirect_emailupdated);
			}
		}
	}
	if(count($errors) > 0)
	{
		$mybb->input['action'] = 'email';
		$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == "email")
{
	// Coming back to this page after one or more errors were experienced, show fields the user previously entered (with the exception of the password)
	if($errors)
	{
		$email = $mybb->get_input('email');
		$email2 = $mybb->get_input('email2');
	}
	else
	{
		$email = $email2 = '';
	}

	$plugins->run_hooks('usercp_email');

	output_page(\MyBB\template('usercp/email.twig', [
		'errors' => $errors,
		'email' => $email,
		'email2' => $email2,
	]));
}

if($mybb->input['action'] == 'do_password' && $mybb->request_method == 'post')
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$user = array();
	$errors = [];

	$plugins->run_hooks('usercp_do_password_start');
	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('oldpassword')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler("update");

		$user = array_merge($user, array(
			"uid" => $mybb->user['uid'],
			"password" => $mybb->get_input('password'),
			"password2" => $mybb->get_input('password2')
		));

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			my_setcookie('mybbuser', $mybb->user['uid'].'_'.$userhandler->data['loginkey'], null, true, "lax");

			// Notify the user by email that their password has been changed
			$mail_message = $lang->sprintf($lang->email_changepassword, $mybb->user['username'], $mybb->user['email'],
				$mybb->settings['bbname'], $mybb->settings['bburl']);
			$lang->emailsubject_changepassword = $lang->sprintf($lang->emailsubject_changepassword,
				$mybb->settings['bbname']);
			my_mail($mybb->user['email'], $lang->emailsubject_changepassword, $mail_message);

			$plugins->run_hooks('usercp_do_password_end');
			redirect('usercp.php?action=password', $lang->redirect_passwordupdated);
		}
	}
	if(count($errors) > 0)
	{
		$mybb->input['action'] = 'password';
		$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == 'password')
{
	$plugins->run_hooks('usercp_password');

	output_page(\MyBB\template('usercp/password.twig', [
		'errors' => $errors,
	]));
}

if($mybb->input['action'] == 'do_changename' && $mybb->request_method == 'post')
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	if($mybb->usergroup['canchangename'] != 1)
	{
		error_no_permission();
	}

	$user = array();

	$plugins->run_hooks("usercp_do_changename_start");

	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');

		$user = array_merge($user, array(
			"uid" => $mybb->user['uid'],
			"username" => $mybb->get_input('username')
		));

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			$plugins->run_hooks("usercp_do_changename_end");
			redirect("usercp.php?action=changename", $lang->redirect_namechanged);
		}
	}
	if(count($errors) > 0)
	{
		$errors = inline_error($errors);
		$mybb->input['action'] = 'changename';
	}
}

if($mybb->input['action'] == 'changename')
{
	$plugins->run_hooks('usercp_changename_start');
	if($mybb->usergroup['canchangename'] != 1)
	{
		error_no_permission();
	}

	// Coming back to this page after one or more errors were experienced, show field the user previously entered (with the exception of the password)
	if($errors)
	{
		$username = $mybb->get_input('username');
	}
	else
	{
		$username = '';
	}

	$plugins->run_hooks("usercp_changename_end");

	output_page(\MyBB\template('usercp/changename.twig', [
		'errors' => $errors,
        'username' => $username,
	]));
}

if($mybb->input['action'] == "do_subscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if(!isset($mybb->input['check']) || !is_array($mybb->input['check']))
	{
		error($lang->no_subscriptions_selected);
	}

	$plugins->run_hooks("usercp_do_subscriptions_start");

	// Clean input - only accept integers thanks!
	$mybb->input['check'] = array_map('intval', $mybb->get_input('check', MyBB::INPUT_ARRAY));
	$tids = implode(",", $mybb->input['check']);

	// Deleting these subscriptions?
	if($mybb->get_input('do') == "delete")
	{
		$db->delete_query("threadsubscriptions", "tid IN ($tids) AND uid='{$mybb->user['uid']}'");
	}
	// Changing subscription type
	else
	{
		if($mybb->get_input('do') == "no_notification")
		{
			$new_notification = 0;
		}
		elseif($mybb->get_input('do') == "email_notification")
		{
			$new_notification = 1;
		}
		elseif($mybb->get_input('do') == "pm_notification")
		{
			$new_notification = 2;
		}

		// Update
		$update_array = array("notification" => $new_notification);
		$db->update_query("threadsubscriptions", $update_array, "tid IN ($tids) AND uid='{$mybb->user['uid']}'");
	}

	// Done, redirect
	redirect("usercp.php?action=subscriptions", $lang->redirect_subscriptions_updated);
}

if($mybb->input['action'] == "subscriptions")
{
	$plugins->run_hooks('usercp_subscriptions_start');

	// Thread visiblity
	$where = array(
		"s.uid={$mybb->user['uid']}",
		get_visible_where('t')
	);

	if($unviewable_forums = get_unviewable_forums(true))
	{
		$where[] = "t.fid NOT IN ({$unviewable_forums})";
	}

	if($inactive_forums = get_inactive_forums())
	{
		$where[] = "t.fid NOT IN ({$inactive_forums})";
	}

	$where = implode(' AND ', $where);

	// Do Multi Pages
	$query = $db->query("
        SELECT COUNT(s.tid) as threads
        FROM ".TABLE_PREFIX."threadsubscriptions s
        LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = s.tid)
        WHERE {$where}
    ");
	$threadcount = $db->fetch_field($query, "threads");

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page - 1) * $perpage;
		$pages = $threadcount / $perpage;
		$pages = ceil($pages);
		if($page > $pages || $page <= 0)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start + 1;
	$upper = $end;
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=subscriptions");
	$fpermissions = forum_permissions();
	$del_subscriptions = $subscriptions = [];

	// Fetch subscriptions
	$query = $db->query("
        SELECT s.*, t.*, t.username AS threadusername, u.username, last_poster.avatar as last_poster_avatar
        FROM ".TABLE_PREFIX."threadsubscriptions s
        LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
        LEFT JOIN ".TABLE_PREFIX."users last_poster ON (t.lastposteruid=last_poster.uid)
        WHERE {$where}
        ORDER BY t.lastpost DESC
        LIMIT $start, $perpage
    ");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $subscription['uid'] != $mybb->user['uid'])
		{
			// Hmm, you don't have permission to view this thread - unsubscribe!
			$del_subscriptions[] = $subscription['sid'];
		}
		elseif($subscription['tid'])
		{
			$subscriptions[$subscription['tid']] = $subscription;
		}
	}

	if(!empty($del_subscriptions))
	{
		$sids = implode(',', $del_subscriptions);

		if($sids)
		{
			$db->delete_query("threadsubscriptions", "sid IN ({$sids}) AND uid='{$mybb->user['uid']}'");
		}

		$threadcount = $threadcount - count($del_subscriptions);

		if($threadcount < 0)
		{
			$threadcount = 0;
		}
	}

	if(!empty($subscriptions))
	{
		$tids = implode(",", array_keys($subscriptions));
		$readforums = [];

		// Build a forum cache.
		$query = $db->query("
            SELECT f.fid, fr.dateline AS lastread
            FROM ".TABLE_PREFIX."forums f
            LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
            WHERE f.active != 0
            ORDER BY pid, disporder
        ");

		while($forum = $db->fetch_array($query))
		{
			$readforums[$forum['fid']] = $forum['lastread'];
		}

		// Check participation by the current user in any of these threads - for 'dot' folder icons
		if($mybb->settings['dotfolders'] != 0)
		{
			$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($post = $db->fetch_array($query))
			{
				$subscriptions[$post['tid']]['doticon'] = 1;
			}
		}

		// Read threads
		if($mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($readthread = $db->fetch_array($query))
			{
				$subscriptions[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}

		$icon_cache = $cache->read("posticons");
		$threadprefixes = build_prefixes();

		$threads = [];

		// Now we can build our subscription list
		foreach($subscriptions as $thread)
		{
			$thread['bg_colour'] = alt_trow();

			$folder = '';
			$prefix = '';
			$thread['threadprefix'] = '';

			// If this thread has a prefix, insert a space between prefix and subject
			if($thread['prefix'] != 0 && !empty($threadprefixes[$thread['prefix']]))
			{
				$thread['threadprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'];
			}

			// Sanitize
			$thread['subject'] = $parser->parse_badwords($thread['subject']);

			// Build our links
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

			// Fetch the thread icon if we have one
			if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
			{
				$icon = $icon_cache[$thread['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$thread['icon'] = $icon;
			}
			else
			{
				$icon = "&nbsp;";
			}

			// Determine the folder
			$thread['folder'] = '';
			$thread['folder_label'] = '';

			if(isset($thread['doticon']))
			{
				$thread['folder'] = "dot_";
				$thread['folder_label'] .= $lang->icon_dot;
			}

			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;

			if($mybb->settings['threadreadcut'] > 0)
			{
				$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				if(empty($readforums[$thread['fid']]) || $readforums[$thread['fid']] < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
				else
				{
					$forum_read = $readforums[$thread['fid']];
				}
			}

			$cutoff = 0;
			if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
			{
				$cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
			}

			if($thread['lastpost'] > $cutoff)
			{
				if(!empty($thread['lastread']))
				{
					$lastread = $thread['lastread'];
				}
				else
				{
					$lastread = 1;
				}
			}

			if(!$lastread)
			{
				$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
				if($readcookie > $forum_read)
				{
					$lastread = $readcookie;
				}
				else
				{
					$lastread = $forum_read;
				}
			}

			if($lastread && $lastread < $thread['lastpost'])
			{
				$thread['folder'] .= "new";
				$thread['folder_label'] .= $lang->icon_new;
				$thread['new_class'] = "subject_new";
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
			}
			else
			{
				$thread['folder_label'] .= $lang->icon_no_new;
				$thread['new_class'] = "subject_old";
			}

			if($thread['closed'] == 1)
			{
				$folder .= "close";
				$folder_label .= $lang->icon_close;
			}

			if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
			{
				$thread['folder'] .= "hot";
				$thread['folder_label'] .= $lang->icon_hot;
			}

			$thread['folder'] .= "folder";

			if($thread['visible'] == 0)
			{
				$thread['bg_colour'] = "trow_shaded";
			}

			// Build last post info
			$thread['last_post_date'] = my_date('relative', $thread['lastpost']);
			$lastposteruid = $thread['lastposteruid'];
			if(!$lastposteruid && !$thread['lastposter'])
			{
				$lastposter = htmlspecialchars_uni($lang->guest);
			}
			else
			{
				$lastposter = htmlspecialchars_uni($thread['lastposter']);
			}

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$thread['last_poster_link'] = $lastposter;
			}
			else
			{
				$thread['last_poster_link'] = build_profile_link($lastposter, $lastposteruid);
			}

			$thread['last_poster_name'] = $lastposter;

			$thread['replies'] = my_number_format($thread['replies']);
			$thread['views'] = my_number_format($thread['views']);

			// What kind of notification type do we have here?
			switch($thread['notification'])
			{
				case "2": // PM
					$thread['notification_type'] = $lang->pm_notification;
					break;
				case "1": // Email
					$thread['notification_type'] = $lang->email_notification;
					break;
				default: // No notification
					$thread['notification_type'] = $lang->no_notification;
			}

			$threads[] = $thread;
		}
	}
	else
	{
		$remove_options = '';
	}

	$plugins->run_hooks('usercp_subscriptions_end');

	output_page(\MyBB\template('usercp/subscribed_threads.twig', [
		'multipage' => $multipage,
		'subscriptions' => $subscriptions,
		'thread_count' => $threadcount,
		'threads' => $threads,
	]));
}

if($mybb->input['action'] == 'forumsubscriptions')
{
	$plugins->run_hooks('usercp_forumsubscriptions_start');

	// Build a forum cache.
	$query = $db->query("
        SELECT f.fid, fr.dateline AS lastread
        FROM ".TABLE_PREFIX."forums f
        LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
        WHERE f.active != 0
        ORDER BY pid, disporder
    ");
	$readforums = [];
	while($forum = $db->fetch_array($query))
	{
		$readforums[$forum['fid']] = $forum['lastread'];
	}

	$fpermissions = forum_permissions();
	require_once MYBB_ROOT.'inc/functions_forumlist.php';

	$query = $db->query("
        SELECT fs.*, f.*, t.subject AS lastpostsubject, fr.dateline AS lastread, last_poster.avatar as last_poster_avatar
        FROM ".TABLE_PREFIX."forumsubscriptions fs
        LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid)
        LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)
        LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
        LEFT JOIN ".TABLE_PREFIX."users last_poster ON (t.lastposteruid=last_poster.uid)
        WHERE f.type='f' AND fs.uid='{$mybb->user['uid']}'
        ORDER BY f.name ASC
    ");

	$forums = [];
	while($forum = $db->fetch_array($query))
	{
		$forum['url'] = get_forum_link($forum['fid']);
		$forumpermissions = $fpermissions[$forum['fid']];

		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			continue;
		}

		$lightbulb = get_forum_lightbulb(
			array(
				'open' => $forum['open'],
			 	'lastread' => $forum['lastread'],
			 	'linkto' => $forum['linkto']
			),
			array(
				'lastpost' => $forum['lastpost']
			)
		);

		$forum['light_bulb_folder'] = $lightbulb['folder'];
		$forum['light_bulb_alt_on_off'] = $lightbulb['altonoff'];

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0)
		{
			$forum['posts'] = '-';
			$forum['threads'] = '-';
		}
		else
		{
			$forum['posts'] = my_number_format($forum['posts']);
			$forum['threads'] = my_number_format($forum['threads']);
		}

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $forum['lastposteruid'] != $mybb->user['uid'])
		{
			$forum['lastpost_hidden'] = true;
		}
		else
		{
			$forum['lastpost_hidden'] = false;

			$forum['lastpostsubject'] = $parser->parse_badwords($forum['lastpostsubject']);
			if(!$forum['lastposteruid'] && !$forum['lastposter'])
			{
				$lastposter = $lang->guest;
			}
			else
			{
				$lastposter = $forum['lastposter'];
			}

			if($forum['lastposteruid'] == 0)
			{
				$lastpost_profilelink = $lastposter;
			}
			else
			{
				$lastpost_profilelink = build_profile_link($lastposter, $forum['lastposteruid']);
			}

			$lastpost_subject = $forum['lastpostsubject'];
			if(my_strlen($lastpost_subject) > 25)
			{
				$lastpost_subject = my_substr($lastpost_subject, 0, 25)."...";
			}

			$forum['last_post'] = [
				'link' => get_thread_link($forum['lastposttid'], 0, 'lastpost'),
				'full_subject' => $forum['lastpostsubject'],
				'subject' => $lastpost_subject,
				'date' => my_date('relative', $forum['lastpost']),
				'profile_link' => $lastpost_profilelink,
				'last_poster_avatar_url' => $forum['last_poster_avatar'],
				'lastposter' => $lastposter,
				'lastposteruid' => $forum['lastposteruid'],
			];
		}

		if($mybb->settings['showdescriptions'] == 0)
		{
			$forum['description'] = "";
		}

		$forums[] = $forum;
	}

	$plugins->run_hooks('usercp_forumsubscriptions_end');

	output_page(\MyBB\template('usercp/subscribed_forums.twig', [
		'forums' => $forums,
	]));
}

if($mybb->input['action'] == "do_addsubscription" && $mybb->get_input('type') != "forum")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$thread = get_thread($mybb->get_input('tid'));
	if(!$thread || $thread['visible'] == -1)
	{
		error($lang->error_invalidthread);
	}

	// Is the currently logged in user a moderator of this forum?
	$ismod = is_moderator($thread['fid']);

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		error($lang->error_invalidthread);
	}

	$forumpermissions = forum_permissions($thread['fid']);
	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

	// check if the forum requires a password to view. If so, we need to show a form to the user
	check_forum_password($thread['fid']);

	// Naming of the hook retained for backward compatibility while dropping usercp2.php
	$plugins->run_hooks("usercp2_do_addsubscription");

	add_subscribed_thread($thread['tid'], $mybb->get_input('notification', MyBB::INPUT_INT));

	if($mybb->get_input('referrer'))
	{
		$mybb->input['referrer'] = $mybb->get_input('referrer');

		if(my_strpos($mybb->input['referrer'], $mybb->settings['bburl'].'/') !== 0)
		{
			if(my_strpos($mybb->input['referrer'], '/') === 0)
			{
				$mybb->input['referrer'] = my_substr($mybb->input['url'], 1);
			}
			$url_segments = explode('/', $mybb->input['referrer']);
			$mybb->input['referrer'] = $mybb->settings['bburl'].'/'.end($url_segments);
		}

		$url = htmlspecialchars_uni($mybb->input['referrer']);
	}
	else
	{
		$url = get_thread_link($thread['tid']);
	}
	redirect($url, $lang->redirect_subscriptionadded);
}

if($mybb->input['action'] == "addsubscription")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error($lang->error_invalidforum);
		}
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($forum['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_addsubscription_forum");

		add_subscribed_forum($forum['fid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "index.php";
		}
		redirect($url, $lang->redirect_forumsubscriptionadded);
	}
	else
	{
		$thread  = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread || $thread['visible'] == -1)
		{
			error($lang->error_invalidthread);
		}

		// Is the currently logged in user a moderator of this forum?
		$ismod = is_moderator($thread['fid']);

		// Make sure we are looking at a real thread here.
		if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		add_breadcrumb($lang->nav_subthreads, "usercp.php?action=subscriptions");
		add_breadcrumb($lang->nav_addsubscription);

		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
		{
			error_no_permission();
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($thread['fid']);

		$referrer = '';
		if($server_http_referer)
		{
			$referrer = $server_http_referer;
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$lang->subscribe_to_thread = $lang->sprintf($lang->subscribe_to_thread, $thread['subject']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_addsubscription_thread");

		output_page(\MyBB\template('usercp/subscribe_thread.twig', [
			'thread' => $thread
		]));
		exit;
	}
}

if($mybb->input['action'] == "removesubscription" && ($mybb->request_method == "post" || verify_post_check($mybb->get_input('my_post_key'), true)))
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error($lang->error_invalidforum);
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($forum['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_forum");

		remove_subscribed_forum($forum['fid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionremoved);
	}
	else
	{
		$thread = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread)
		{
			error($lang->error_invalidthread);
		}

		// Is the currently logged in user a moderator of this forum?
		$ismod = is_moderator($thread['fid']);

		// Make sure we are looking at a real thread here.
		if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($thread['fid']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_thread");

		remove_subscribed_thread($thread['tid']);
		if($server_http_referer && $mybb->request_method != 'post')
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionremoved);
	}
}

// Show remove subscription form when GET method and without valid my_post_key
if($mybb->input['action'] == "removesubscription")
{
	$referrer = '';
	if($mybb->get_input('type') == "forum")
	{
		$forum = get_forum($mybb->get_input('fid', MyBB::INPUT_INT));
		if(!$forum)
		{
			error($lang->error_invalidforum);
		}

		add_breadcrumb($lang->nav_forumsubscriptions, "usercp.php?action=forumsubscriptions");
		add_breadcrumb($lang->nav_removesubscription);

		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			error_no_permission();
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($forum['fid']);

		$lang->unsubscribe_from_forum = $lang->sprintf($lang->unsubscribe_from_forum, $forum['name']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_display_forum");

		output_page(\MyBB\template('usercp/removesubscription_forum.twig', [
			'forum' => $forum,
			'errors' => $errors,
		]));
	}
	else
	{
		$thread  = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));
		if(!$thread || $thread['visible'] == -1)
		{
			error($lang->error_invalidthread);
		}

		// Is the currently logged in user a moderator of this forum?
		$ismod = is_moderator($thread['fid']);

		// Make sure we are looking at a real thread here.
		if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
		{
			error($lang->error_invalidthread);
		}

		add_breadcrumb($lang->nav_subthreads, "usercp.php?action=subscriptions");
		add_breadcrumb($lang->nav_removesubscription);

		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
		{
			error_no_permission();
		}

		// check if the forum requires a password to view. If so, we need to show a form to the user
		check_forum_password($thread['fid']);

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
		$thread['subject'] = $parser->parse_badwords($thread['subject']);
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$lang->unsubscribe_from_thread = $lang->sprintf($lang->unsubscribe_from_thread, $thread['subject']);

		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscription_display_thread");

		output_page(\MyBB\template('usercp/removesubscription_thread.twig', [
			'thread' => $thread,
			'errors' => $errors,
		]));
	}
}

if($mybb->input['action'] == "removesubscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	if($mybb->get_input('type') == "forum")
	{
		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscriptions_forum");

		$db->delete_query("forumsubscriptions", "uid='".$mybb->user['uid']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=forumsubscriptions";
		}
		redirect($url, $lang->redirect_forumsubscriptionsremoved);
	}
	else
	{
		// Naming of the hook retained for backward compatibility while dropping usercp2.php
		$plugins->run_hooks("usercp2_removesubscriptions_thread");

		$db->delete_query("threadsubscriptions", "uid='".$mybb->user['uid']."'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=subscriptions";
		}
		redirect($url, $lang->redirect_subscriptionsremoved);
	}
}

if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// User currently has a suspended signature
	if($mybb->user['suspendsignature'] == 1 && $mybb->user['suspendsigtime'] > TIME_NOW)
	{
		error_no_permission();
	}

	$plugins->run_hooks("usercp_do_editsig_start");

	if($mybb->get_input('updateposts') == "enable")
	{
		$update_signature = array(
			"includesig" => 1
		);
		$db->update_query("posts", $update_signature, "uid='".$mybb->user['uid']."'");
	}
	elseif($mybb->get_input('updateposts') == "disable")
	{
		$update_signature = array(
			"includesig" => 0
		);
		$db->update_query("posts", $update_signature, "uid='".$mybb->user['uid']."'");
	}
	$new_signature = array(
		"signature" => $db->escape_string($mybb->get_input('signature'))
	);
	$plugins->run_hooks("usercp_do_editsig_process");
	$db->update_query("users", $new_signature, "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_editsig_end");
	redirect("usercp.php?action=editsig", $lang->redirect_sigupdated);
}

if($mybb->input['action'] == 'editsig')
{
	$plugins->run_hooks('usercp_editsig_start');

	$show_sig = true;

	if(!empty($mybb->input['preview']) && empty($error))
	{
		$sig = $mybb->get_input('signature');
		$show_sig_type = 'sig_preview';
	}
	elseif(empty($error))
	{
		$sig = $mybb->user['signature'];
		$show_sig_type = 'current_sig';
	}
	else
	{
		$sig = $mybb->get_input('signature');
		$show_sig = false;
		$show_sig_type = '';
	}

	if(!isset($error))
	{
		$error = '';
	}

	if($mybb->user['suspendsignature'] && ($mybb->user['suspendsigtime'] == 0 || $mybb->user['suspendsigtime'] > 0 && $mybb->user['suspendsigtime'] > TIME_NOW))
	{
		// User currently has no signature and they're suspended
		error($lang->sig_suspended);
	}

	if($mybb->usergroup['canusesig'] != 1)
	{
		// Usergroup has no permission to use this facility
		error_no_permission();
	}
	else
	{
		if($mybb->usergroup['canusesig'] == 1 && $mybb->usergroup['canusesigxposts'] > 0 && $mybb->user['postnum'] < $mybb->usergroup['canusesigxposts'])
		{
			// Usergroup can use this facility, but only after x posts
			error($lang->sprintf($lang->sig_suspended_posts, $mybb->usergroup['canusesigxposts']));
		}
	}

	$sigpreview = '';
	if($sig && $show_sig)
	{
		$sig_parser = [
			'allow_html' => $mybb->settings['sightml'],
			'allow_mycode' => $mybb->settings['sigmycode'],
			'allow_smilies' => $mybb->settings['sigsmilies'],
			'allow_imgcode' => $mybb->settings['sigimgcode'],
			'me_username' => $mybb->user['username'],
			'filter_badwords' => 1,
		];

		if($mybb->user['showimages'] != 1)
		{
			$sig_parser['allow_imgcode'] = 0;
		}

		$sigpreview = $parser->parse_message($sig, $sig_parser);
	}

	// User has a current signature, so let's display it (but show an error message)
	if($mybb->user['suspendsignature'] && $mybb->user['suspendsigtime'] > TIME_NOW)
	{
		$plugins->run_hooks('usercp_editsig_end');

		// User either doesn't have permission, or has their signature suspended
		$editsig = \MyBB\template('usercp/editsig_suspended.twig', [
			'sig' => $sig,
			'sigPreview' => $sigpreview,
			'showSigType' => $show_sig_type,
			'showSig' => $show_sig,
		]);
	}
	else
	{
		// User is allowed to edit their signature
		if($mybb->settings['sigsmilies'] == 1)
		{
			$sigsmilies = $lang->on;
			$smilieinserter = build_clickable_smilies();
		}
		else
		{
			$sigsmilies = $lang->off;
		}

		if($mybb->settings['sigmycode'] == 1)
		{
			$sigmycode = $lang->on;
		}
		else
		{
			$sigmycode = $lang->off;
		}

		if($mybb->settings['sightml'] == 1)
		{
			$sightml = $lang->on;
		}
		else
		{
			$sightml = $lang->off;
		}

		if($mybb->settings['sigimgcode'] == 1)
		{
			$sigimgcode = $lang->on;
		}
		else
		{
			$sigimgcode = $lang->off;
		}

		if($mybb->settings['siglength'] == 0)
		{
			$siglength = $lang->unlimited;
		}
		else
		{
			$siglength = $mybb->settings['siglength'];
		}

		$sig = htmlspecialchars_uni($sig);
		$lang->edit_sig_note2 = $lang->sprintf($lang->edit_sig_note2, $sigsmilies, $sigmycode, $sigimgcode, $sightml, $siglength);

		if($mybb->settings['sigmycode'] != 0 && $mybb->settings['bbcodeinserter'] != 0 && $mybb->user['showcodebuttons'] != 0)
		{
			$codebuttons = build_mycode_inserter('signature');
		}

		$plugins->run_hooks('usercp_editsig_end');

		$editsig = \MyBB\template('usercp/editsig.twig', [
			'error' => $error,
			'sig' => $sig,
			'sigPreview' => $sigpreview,
			'showSigType' => $show_sig_type,
			'showSig' => $show_sig,
			'smilieInserter' => $smilieinserter,
			'codeButtons' => $codebuttons,
		]);
	}

	output_page($editsig);
}

if($mybb->input['action'] == "do_avatar" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('usercp_do_avatar_start');
	require_once MYBB_ROOT."inc/functions_upload.php";

	$error = "";

	if(!empty($mybb->input['remove'])) // remove avatar
	{
		$updated_avatar = array(
			"avatar" => "",
			"avatardimensions" => "",
			"avatartype" => ""
		);
		$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
		remove_avatars($mybb->user['uid']);
	}
	else if($_FILES['avatarupload']['name'])
	{ // upload avatar
		if($mybb->usergroup['canuploadavatars'] == 0)
		{
			error_no_permission();
		}
		$avatar = upload_avatar();
		if(!empty($avatar['error']))
		{
			$error = $avatar['error'];
		}
		else
		{
			if($avatar['width'] > 0 && $avatar['height'] > 0)
			{
				$avatar_dimensions = $avatar['width']."|".$avatar['height'];
			}
			$updated_avatar = array(
				"avatar" => $avatar['avatar'].'?dateline='.TIME_NOW,
				"avatardimensions" => $avatar_dimensions,
				"avatartype" => "upload"
			);
			$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
		}
	}
	elseif(!$mybb->settings['allowremoteavatars'] && !$_FILES['avatarupload']['name']) // missing avatar image
	{
		$error = $lang->error_avatarimagemissing;
	}
	elseif($mybb->settings['allowremoteavatars']) // remote avatar
	{
		$mybb->input['avatarurl'] = trim($mybb->get_input('avatarurl'));
		if(validate_email_format($mybb->input['avatarurl']) != false)
		{
			// Gravatar
			$mybb->input['avatarurl'] = my_strtolower($mybb->input['avatarurl']);

			// If user image does not exist, or is a higher rating, use the mystery man
			$email = md5($mybb->input['avatarurl']);

			$s = '';
			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
			}

			// Because Gravatars are square, hijack the width
			list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($mybb->settings['maxavatardims']));
			$maxheight = (int)$maxwidth;

			// Rating?
			$types = array('g', 'pg', 'r', 'x');
			$rating = $mybb->settings['useravatarrating'];

			if(!in_array($rating, $types))
			{
				$rating = 'g';
			}

			$s = "?s={$maxheight}&r={$rating}&d=mm";

			$updated_avatar = array(
				"avatar" => "https://www.gravatar.com/avatar/{$email}{$s}",
				"avatardimensions" => "{$maxheight}|{$maxheight}",
				"avatartype" => "gravatar"
			);

			$db->update_query("users", $updated_avatar, "uid = '{$mybb->user['uid']}'");
		}
		else
		{
			$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->get_input('avatarurl'));
			$ext = get_extension($mybb->input['avatarurl']);

			// Copy the avatar to the local server (work around remote URL access disabled for getimagesize)
			$file = fetch_remote_file($mybb->input['avatarurl']);
			if(!$file)
			{
				$error = $lang->error_invalidavatarurl;
			}
			else
			{
				$tmp_name = $mybb->settings['avataruploadpath']."/remote_".md5(random_str());
				$fp = @fopen($tmp_name, "wb");
				if(!$fp)
				{
					$error = $lang->error_invalidavatarurl;
				}
				else
				{
					fwrite($fp, $file);
					fclose($fp);
					list($width, $height, $type) = @getimagesize($tmp_name);
					@unlink($tmp_name);
					if(!$type)
					{
						$error = $lang->error_invalidavatarurl;
					}
				}
			}

			if(empty($error))
			{
				if($width && $height && $mybb->settings['maxavatardims'] != "")
				{
					list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($mybb->settings['maxavatardims']));
					if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
					{
						$lang->error_avatartoobig = $lang->sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
						$error = $lang->error_avatartoobig;
					}
				}
			}

			// Limiting URL string to stay within database limit
			if(strlen($mybb->input['avatarurl']) > 200)
			{
				$error = $lang->error_avatarurltoolong;
			}

			if(empty($error))
			{
				if($width > 0 && $height > 0)
				{
					$avatar_dimensions = (int)$width."|".(int)$height;
				}
				$updated_avatar = array(
					"avatar" => $db->escape_string($mybb->input['avatarurl'].'?dateline='.TIME_NOW),
					"avatardimensions" => $avatar_dimensions,
					"avatartype" => "remote"
				);
				$db->update_query("users", $updated_avatar, "uid='".$mybb->user['uid']."'");
				remove_avatars($mybb->user['uid']);
			}
		}
	}
	else // remote avatar, but remote avatars are not allowed
	{
		$error = $lang->error_remote_avatar_not_allowed;
	}

	if(empty($error))
	{
		$plugins->run_hooks('usercp_do_avatar_end');
		redirect("usercp.php?action=avatar", $lang->redirect_avatarupdated);
	}
	else
	{
		$mybb->input['action'] = "avatar";
		$error = inline_error($error);
	}
}

if($mybb->input['action'] == "avatar")
{
	$plugins->run_hooks('usercp_avatar_start');

	$avatarurl = '';
	$extranotes = [];

	if($mybb->user['avatartype'] == "upload" || stristr($mybb->user['avatar'], $mybb->settings['avataruploadpath']))
	{
		$extranotes[] = $lang->already_uploaded_avatar;
	}
	else if($mybb->user['avatartype'] == "remote" || my_validate_url($mybb->user['avatar']))
	{
		$extranotes[] = $lang->using_remote_avatar;
		$avatarurl = htmlspecialchars_uni($mybb->user['avatar']);
	}

	$useravatar = format_avatar($mybb->user['avatar'], $mybb->user['avatardimensions'], '100x100');

	if($mybb->settings['maxavatardims'] != "")
	{
		list($maxwidth, $maxheight) = preg_split('/[|x]/', my_strtolower($mybb->settings['maxavatardims']));
		$extranotes[] = "<br />".$lang->sprintf($lang->avatar_note_dimensions, $maxwidth, $maxheight);
	}

	if($mybb->settings['avatarsize'])
	{
		$maxsize = get_friendly_size($mybb->settings['avatarsize'] * 1024);
		$extranotes[] = $lang->sprintf($lang->avatar_note_size, $maxsize);
	}

	$plugins->run_hooks('usercp_avatar_end');

	output_page(\MyBB\template('usercp/avatar.twig', [
		'error' => $error,
		'useravatar' => $useravatar,
		'extranotes' => $extranotes
	]));
}

if($mybb->input['action'] == "acceptrequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND touid='.(int)$mybb->user['uid']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error($lang->invalid_request);
	}

	$plugins->run_hooks('usercp_acceptrequest_start');

	$user = get_user($request['uid']);
	if(!empty($user))
	{
		// We want to add us to this user's buddy list
		if($user['buddylist'] != '')
		{
			$user['buddylist'] = explode(',', $user['buddylist']);
		}
		else
		{
			$user['buddylist'] = [];
		}

		$user['buddylist'][] = (int)$mybb->user['uid'];

		// Now we have the new list, so throw it all back together
		$new_list = implode(",", $user['buddylist']);

		// And clean it up a little to ensure there is no possibility of bad values
		$new_list = preg_replace("#,{2,}#", ",", $new_list);
		$new_list = preg_replace("#[^0-9,]#", "", $new_list);

		if(my_substr($new_list, 0, 1) == ",")
		{
			$new_list = my_substr($new_list, 1);
		}
		if(my_substr($new_list, -1) == ",")
		{
			$new_list = my_substr($new_list, 0, my_strlen($new_list) - 2);
		}

		$user['buddylist'] = $db->escape_string($new_list);

		$db->update_query("users", array('buddylist' => $user['buddylist']), "uid='".(int)$user['uid']."'");

		// We want to add the user to our buddy list
		if($mybb->user['buddylist'] != '')
		{
			$mybb->user['buddylist'] = explode(',', $mybb->user['buddylist']);
		}
		else
		{
			$mybb->user['buddylist'] = [];
		}

		$mybb->user['buddylist'][] = (int)$request['uid'];

		// Now we have the new list, so throw it all back together
		$new_list = implode(",", $mybb->user['buddylist']);

		// And clean it up a little to ensure there is no possibility of bad values
		$new_list = preg_replace("#,{2,}#", ",", $new_list);
		$new_list = preg_replace("#[^0-9,]#", "", $new_list);

		if(my_substr($new_list, 0, 1) == ",")
		{
			$new_list = my_substr($new_list, 1);
		}
		if(my_substr($new_list, -1) == ",")
		{
			$new_list = my_substr($new_list, 0, my_strlen($new_list) - 2);
		}

		$mybb->user['buddylist'] = $db->escape_string($new_list);

		$db->update_query("users", array('buddylist' => $mybb->user['buddylist']), "uid='".(int)$mybb->user['uid']."'");

		$pm = array(
			'subject' => 'buddyrequest_accepted_request',
			'message' => 'buddyrequest_accepted_request_message',
			'touid' => $user['uid'],
			'language' => $user['language'],
			'language_file' => 'usercp'
		);

		send_pm($pm, $mybb->user['uid'], true);

		$db->delete_query('buddyrequests', 'id='.(int)$request['id']);
	}
	else
	{
		error($lang->user_doesnt_exist);
	}

	$plugins->run_hooks('usercp_acceptrequest_end');

	redirect("usercp.php?action=editlists", $lang->buddyrequest_accepted);
}
elseif($mybb->input['action'] == "declinerequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND touid='.(int)$mybb->user['uid']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error($lang->invalid_request);
	}

	$plugins->run_hooks('usercp_declinerequest_start');

	$user = get_user($request['uid']);
	if(!empty($user))
	{
		$db->delete_query('buddyrequests', 'id='.(int)$request['id']);
	}
	else
	{
		error($lang->user_doesnt_exist);
	}

	$plugins->run_hooks('usercp_declinerequest_end');

	redirect("usercp.php?action=editlists", $lang->buddyrequest_declined);
}
elseif($mybb->input['action'] == "cancelrequest")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Validate request
	$query = $db->simple_select('buddyrequests', '*', 'id='.$mybb->get_input('id', MyBB::INPUT_INT).' AND uid='.(int)$mybb->user['uid']);
	$request = $db->fetch_array($query);
	if(empty($request))
	{
		error($lang->invalid_request);
	}

	$plugins->run_hooks('usercp_cancelrequest_start');

	$db->delete_query('buddyrequests', 'id='.(int)$request['id']);

	$plugins->run_hooks('usercp_cancelrequest_end');

	redirect("usercp.php?action=editlists", $lang->buddyrequest_cancelled);
}

if($mybb->input['action'] == "do_editlists")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks('usercp_do_editlists_start');

	$existing_users = [];
	$selected_list = [];
	if($mybb->get_input('manage') == "ignored")
	{
		if($mybb->user['ignorelist'])
		{
			$existing_users = explode(",", $mybb->user['ignorelist']);
		}

		if($mybb->user['buddylist'])
		{
			// Create a list of buddies...
			$selected_list = explode(",", $mybb->user['buddylist']);
		}
	}
	else
	{
		if($mybb->user['buddylist'])
		{
			$existing_users = explode(",", $mybb->user['buddylist']);
		}

		if($mybb->user['ignorelist'])
		{
			// Create a list of ignored users
			$selected_list = explode(",", $mybb->user['ignorelist']);
		}
	}

	$error_message = "";
	$message = "";

	// Adding one or more users to this list
	if($mybb->get_input('add_username'))
	{
		// Split up any usernames we have
		$found_users = 0;
		$adding_self = false;
		$users = explode(",", $mybb->get_input('add_username'));
		$users = array_map("trim", $users);
		$users = array_unique($users);
		foreach($users as $key => $username)
		{
			if(empty($username))
			{
				unset($users[$key]);
				continue;
			}

			if(my_strtoupper($mybb->user['username']) == my_strtoupper($username))
			{
				$adding_self = true;
				unset($users[$key]);
				continue;
			}
			$users[$key] = $db->escape_string($username);
		}

		// Get the requests we have sent that are still pending
		$query = $db->simple_select('buddyrequests', 'touid', 'uid='.(int)$mybb->user['uid']);
		$requests = [];
		while($req = $db->fetch_array($query))
		{
			$requests[$req['touid']] = true;
		}

		// Get the requests we have received that are still pending
		$query = $db->simple_select('buddyrequests', 'uid', 'touid='.(int)$mybb->user['uid']);
		$requests_rec = [];
		while($req = $db->fetch_array($query))
		{
			$requests_rec[$req['uid']] = true;
		}

		$sent = false;

		// Fetch out new users
		if(count($users) > 0)
		{
			switch($db->type)
			{
				case 'mysql':
				case 'mysqli':
					$field = 'username';
					break;
				default:
					$field = 'LOWER(username)';
					break;
			}
			$query = $db->simple_select("users", "uid,buddyrequestsauto,buddyrequestspm,language",
				"{$field} IN ('".my_strtolower(implode("','", $users))."')");
			while($user = $db->fetch_array($query))
			{
				++$found_users;

				// Make sure we're not adding a duplicate
				if(in_array($user['uid'], $existing_users) || in_array($user['uid'], $selected_list))
				{
					if($mybb->get_input('manage') == "ignored")
					{
						$error_message = "ignore";
					}
					else
					{
						$error_message = "buddy";
					}

					// On another list?
					$string = "users_already_on_".$error_message."_list";
					if(in_array($user['uid'], $selected_list))
					{
						$string .= "_alt";
					}

					$error_message = $lang->$string;
					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				if(isset($requests[$user['uid']]))
				{
					if($mybb->get_input('manage') != "ignored")
					{
						$error_message = $lang->users_already_sent_request;
					}
					elseif($mybb->get_input('manage') == "ignored")
					{
						$error_message = $lang->users_already_sent_request_alt;
					}

					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				if(isset($requests_rec[$user['uid']]))
				{
					if($mybb->get_input('manage') != "ignored")
					{
						$error_message = $lang->users_already_rec_request;
					}
					elseif($mybb->get_input('manage') == "ignored")
					{
						$error_message = $lang->users_already_rec_request_alt;
					}

					array_pop($users); // To maintain a proper count when we call count($users)
					continue;
				}

				// Do we have auto approval set to On?
				if($user['buddyrequestsauto'] == 1 && $mybb->get_input('manage') != "ignored")
				{
					$existing_users[] = $user['uid'];

					$pm = [
						'subject' => 'buddyrequest_new_buddy',
						'message' => 'buddyrequest_new_buddy_message',
						'touid' => $user['uid'],
						'receivepms' => (int)$user['buddyrequestspm'],
						'language' => $user['language'],
						'language_file' => 'usercp',
					];

					send_pm($pm);
				}
				elseif($user['buddyrequestsauto'] != 1 && $mybb->get_input('manage') != "ignored")
				{
					// Send request
					$id = $db->insert_query('buddyrequests',
						['uid' => (int)$mybb->user['uid'], 'touid' => (int)$user['uid'], 'date' => TIME_NOW]);

					$pm = [
						'subject' => 'buddyrequest_received',
						'message' => 'buddyrequest_received_message',
						'touid' => $user['uid'],
						'receivepms' => (int)$user['buddyrequestspm'],
						'language' => $user['language'],
						'language_file' => 'usercp',
					];

					send_pm($pm);

					$sent = true;
				}
				elseif($mybb->get_input('manage') == "ignored")
				{
					$existing_users[] = $user['uid'];
				}
			}
		}

		if($found_users < count($users))
		{
			if($error_message)
			{
				$error_message .= "<br />";
			}

			$error_message .= $lang->invalid_user_selected;
		}

		if(($adding_self != true || ($adding_self == true && count($users) > 0)) && ($error_message == "" || count($users) > 1))
		{
			if($mybb->get_input('manage') == "ignored")
			{
				$message = $lang->users_added_to_ignore_list;
			}
			else
			{
				$message = $lang->users_added_to_buddy_list;
			}
		}

		if($adding_self == true)
		{
			if($mybb->get_input('manage') == "ignored")
			{
				$error_message = $lang->cant_add_self_to_ignore_list;
			}
			else
			{
				$error_message = $lang->cant_add_self_to_buddy_list;
			}
		}

		if(count($existing_users) == 0)
		{
			$message = "";

			if($sent === true)
			{
				$message = $lang->buddyrequests_sent_success;
			}
		}
	} // Removing a user from this list
	else
	{
		if($mybb->get_input('delete', MyBB::INPUT_INT))
		{
			// Check if user exists on the list
			$key = array_search($mybb->get_input('delete', MyBB::INPUT_INT), $existing_users);
			if($key !== false)
			{
				unset($existing_users[$key]);
				$user = get_user($mybb->get_input('delete', MyBB::INPUT_INT));
				if(!empty($user))
				{
					// We want to remove us from this user's buddy list
					if($user['buddylist'] != '')
					{
						$user['buddylist'] = explode(',', $user['buddylist']);
					}
					else
					{
						$user['buddylist'] = [];
					}

					$key = array_search($mybb->get_input('delete', MyBB::INPUT_INT), $user['buddylist']);
					unset($user['buddylist'][$key]);

					// Now we have the new list, so throw it all back together
					$new_list = implode(",", $user['buddylist']);

					// And clean it up a little to ensure there is no possibility of bad values
					$new_list = preg_replace("#,{2,}#", ",", $new_list);
					$new_list = preg_replace("#[^0-9,]#", "", $new_list);

					if(my_substr($new_list, 0, 1) == ",")
					{
						$new_list = my_substr($new_list, 1);
					}
					if(my_substr($new_list, -1) == ",")
					{
						$new_list = my_substr($new_list, 0, my_strlen($new_list) - 2);
					}

					$user['buddylist'] = $db->escape_string($new_list);

					$db->update_query("users", ['buddylist' => $user['buddylist']], "uid='".(int)$user['uid']."'");
				}

				if($mybb->get_input('manage') == "ignored")
				{
					$message = $lang->removed_from_ignore_list;
				}
				else
				{
					$message = $lang->removed_from_buddy_list;
				}
				$user['username'] = htmlspecialchars_uni($user['username']);
				$message = $lang->sprintf($message, $user['username']);
			}
		}
	}

	// Now we have the new list, so throw it all back together
	$new_list = implode(",", $existing_users);

	// And clean it up a little to ensure there is no possibility of bad values
	$new_list = preg_replace("#,{2,}#", ",", $new_list);
	$new_list = preg_replace("#[^0-9,]#", "", $new_list);

	if(my_substr($new_list, 0, 1) == ",")
	{
		$new_list = my_substr($new_list, 1);
	}
	if(my_substr($new_list, -1) == ",")
	{
		$new_list = my_substr($new_list, 0, my_strlen($new_list) - 2);
	}

	// And update
	$user = [];
	if($mybb->get_input('manage') == "ignored")
	{
		$user['ignorelist'] = $db->escape_string($new_list);
		$mybb->user['ignorelist'] = $user['ignorelist'];
	}
	else
	{
		$user['buddylist'] = $db->escape_string($new_list);
		$mybb->user['buddylist'] = $user['buddylist'];
	}

	$db->update_query("users", $user, "uid='".$mybb->user['uid']."'");

	$plugins->run_hooks('usercp_do_editlists_end');

	// Ajax based request, throw new list to browser
	if(!empty($mybb->input['ajax']))
	{
		if($mybb->get_input('manage') == "ignored")
		{
			$list = "ignore";
		}
		else
		{
			$list = "buddy";
		}

		$message_js = '';
		if($message)
		{
			$message_js = "$.jGrowl('{$message}', {theme:'jgrowl_success'});";
		}

		if($error_message)
		{
			$message_js .= " $.jGrowl('{$error_message}', {theme:'jgrowl_error'});";
		}

		if($mybb->get_input('delete', MyBB::INPUT_INT))
		{
			header("Content-type: text/javascript");
			echo "$(\"#".$mybb->get_input('manage')."_".$mybb->get_input('delete',
					MyBB::INPUT_INT)."\").remove();\n";
			if($new_list == "")
			{
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"0\");\n";
				echo "\$(\"#buddylink\").remove();\n";

				if($mybb->get_input('manage') == "ignored")
				{
					echo "\$(\"#ignore_list\").html(\"<li>{$lang->ignore_list_empty}</li>\");\n";
				}
				else
				{
					echo "\$(\"#buddy_list\").html(\"<li>{$lang->buddy_list_empty}</li>\");\n";
				}
			}
			else
			{
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"".count(explode(",",
						$new_list))."\");\n";
			}
			echo $message_js;
			exit;
		}
		$mybb->input['action'] = "editlists";
	}
	else
	{
		if($error_message)
		{
			$message .= "<br />".$error_message;
		}
		redirect("usercp.php?action=editlists#".$mybb->get_input('manage'), $message);
	}
}

if($mybb->input['action'] == 'editlists')
{
	$plugins->run_hooks('usercp_editlists_start');

	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

	// Fetch out buddies
	$buddy_list = [];
	if($mybb->user['buddylist'])
	{
		$query = $db->simple_select('users', '*', "uid IN ({$mybb->user['buddylist']})", ['order_by' => 'username']);
		while($user = $db->fetch_array($query))
		{
			$user['type'] = 'buddy';

			$user['profile_link'] = build_profile_link(
				format_name(htmlspecialchars_uni($user['username']),
					$user['usergroup'], $user['displaygroup']), $user['uid']
			);

			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
			{
				$user['status'] = 'online';
			}
			else
			{
				$user['status'] = 'offline';
			}

			$buddy_list[] = $user;
		}
	}

	// Fetch out ignore list users
	$ignore_list = [];
	if($mybb->user['ignorelist'])
	{
		$query = $db->simple_select('users', '*', "uid IN ({$mybb->user['ignorelist']})", ['order_by' => 'username']);
		while($user = $db->fetch_array($query))
		{
			$user['type'] = 'ignored';

			$user['profile_link'] = build_profile_link(
				format_name(htmlspecialchars_uni($user['username']),
					$user['usergroup'], $user['displaygroup']), $user['uid']
			);

			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
			{
				$user['status'] = 'online';
			}
			else
			{
				$user['status'] = 'offline';
			}

			$ignore_list[] = $user;
		}
	}

	// If an AJAX request from buddy management, echo out whatever the new list is.
	if($mybb->request_method == 'post' && $mybb->input['ajax'] == 1)
	{
		if($mybb->input['manage'] == 'ignored')
		{
			echo \MyBB\template('usercp/editlists/ignore_list.twig', [
				'ignoreList' => $ignore_list
			]);

			$ignore_count = count($ignore_list);
			echo "<script type=\"text/javascript\"> $(\"#ignored_count\").html(\"{$ignore_count}\"); {$message_js}</script>";
		}
		else
		{
			if(isset($sent) && $sent === true)
			{
				$query = $db->query('
                    SELECT r.*, u.username
                    FROM '.TABLE_PREFIX.'buddyrequests r
                    LEFT JOIN '.TABLE_PREFIX.'users u ON (u.uid=r.touid)
                    WHERE r.uid='.(int)$mybb->user['uid']);

				$sent_rows = [];

				while($request = $db->fetch_array($query))
				{
					$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']),
						(int)$request['touid']);
					$request['date'] = my_date('relative', $request['date']);

					$sent_rows[] = $request;
				}

				echo \MyBB\template('usercp/editlists/sent_requests.twig', [
					'sentRequests' => $sent_rows,
				]);

				echo $sent_requests."<script type=\"text/javascript\">{$message_js}</script>";
			}
			else
			{
				echo \MyBB\template('usercp/editlists/buddy_list.twig', [
					'buddyList' => $buddy_list,
				]);

				$buddy_count = count($buddy_list);
				echo "<script type=\"text/javascript\"> $(\"#buddy_count\").html(\"{$buddy_count}\"); {$message_js}</script>";
			}
		}
		exit;
	}

	$received_rows = [];
	$query = $db->query("
		SELECT r.*, u.username
		FROM ".TABLE_PREFIX."buddyrequests r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.uid)
		WHERE r.touid=".(int)$mybb->user['uid']);

	while($request = $db->fetch_array($query))
	{
		$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int)$request['uid']);
		$request['date'] = my_date('relative', $request['date']);

		$received_rows[] = $request;
	}

	$sent_rows = [];
	$query = $db->query("
		SELECT r.*, u.username
		FROM ".TABLE_PREFIX."buddyrequests r
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=r.touid)
		WHERE r.uid=".(int)$mybb->user['uid']);

	while($request = $db->fetch_array($query))
	{
		$request['username'] = build_profile_link(htmlspecialchars_uni($request['username']), (int)$request['touid']);
		$request['date'] = my_date('relative', $request['date']);

		$sent_rows[] = $request;
	}

	$plugins->run_hooks('usercp_editlists_end');

	output_page(\MyBB\template('usercp/editlists.twig', [
		'buddyList' => $buddy_list,
		'ignoreList' => $ignore_list,
		'receivedRequests' => $received_rows,
		'sentRequests' => $sent_rows,
	]));
}

if($mybb->input['action'] == "drafts")
{
	$plugins->run_hooks('usercp_drafts_start');

	$query = $db->simple_select('posts', 'COUNT(pid) AS draftcount', "visible='-2' AND uid='{$mybb->user['uid']}'");
	$draftCount = $db->fetch_field($query, 'draftcount');

	$deleteDraftsEnabled = $draftCount > 0;
	$drafts = [];

	// Show a listing of all of the current 'draft' posts or threads the user has.
	if($draftCount)
	{
		$query = $db->query("
			SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
			WHERE p.uid = '{$mybb->user['uid']}' AND p.visible = '-2'
			ORDER BY p.dateline DESC, p.pid DESC
		");

		while($draft = $db->fetch_array($query))
		{
			if($draft['threadvisible'] == 1)
			{ // We're looking at a draft post
				$draft['threadlink'] = get_thread_link($draft['tid']);
				$draft['editurl'] = "newreply.php?action=editdraft&amp;pid={$draft['pid']}";
				$draft['type'] = 'post';
			}
			else
			{
				if($draft['threadvisible'] == -2)
				{ // We're looking at a draft thread
					$draft['forumlink'] = get_forum_link($draft['fid']);
					$draft['editurl'] = "newthread.php?action=editdraft&amp;tid={$draft['tid']}";
					$draft['type'] = 'thread';
				}
			}

			$draft['savedate'] = my_date('relative', $draft['dateline']);

			$drafts[] = $draft;
		}
	}

	$plugins->run_hooks('usercp_drafts_end');

	output_page(\MyBB\template('usercp/drafts.twig', [
		'draftCount' => $draftCount,
		'drafts' => $drafts,
		'deleteDraftsEnabled' => $deleteDraftsEnabled,
	]));
}

if($mybb->input['action'] == "do_drafts" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$mybb->input['deletedraft'] = $mybb->get_input('deletedraft', MyBB::INPUT_ARRAY);
	if(empty($mybb->input['deletedraft']))
	{
		error($lang->no_drafts_selected);
	}

	$plugins->run_hooks("usercp_do_drafts_start");

	$pidin = array();
	$tidin = array();

	foreach($mybb->input['deletedraft'] as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] = "'".(int)$id."'";
		}
		elseif($val == "thread")
		{
			$tidin[] = "'".(int)$id."'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->delete_query("threads", "tid IN ($tidin) AND visible='-2' AND uid='".$mybb->user['uid']."'");
		$tidinp = "OR tid IN ($tidin)";
	}
	else
	{
		$tidinp = '';
	}
	if($pidin || $tidinp)
	{
		$pidinq = $tidin = '';
		if($pidin)
		{
			$pidin = implode(",", $pidin);
			$pidinq = "pid IN ($pidin)";
		}
		else
		{
			$pidinq = "1=0";
		}
		$db->delete_query("posts", "($pidinq $tidinp) AND visible='-2' AND uid='".$mybb->user['uid']."'");
	}
	$plugins->run_hooks('usercp_do_drafts_end');
	redirect("usercp.php?action=drafts", $lang->selected_drafts_deleted);
}

if($mybb->input['action'] == "usergroups")
{
	$ingroups = ",".$mybb->user['usergroup'].",".$mybb->user['additionalgroups'].",".$mybb->user['displaygroup'].",";

	$usergroups = $mybb->cache->read('usergroups');

	$plugins->run_hooks("usercp_usergroups_start");

	// Changing our display group
	if($mybb->get_input('displaygroup', MyBB::INPUT_INT))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(my_strpos($ingroups, ",".$mybb->input['displaygroup'].",") === false)
		{
			error($lang->not_member_of_group);
		}

		$dispgroup = $usergroups[$mybb->get_input('displaygroup', MyBB::INPUT_INT)];
		if($dispgroup['candisplaygroup'] != 1)
		{
			error($lang->cannot_set_displaygroup);
		}
		$db->update_query("users", array('displaygroup' => $mybb->get_input('displaygroup', MyBB::INPUT_INT)), "uid='".$mybb->user['uid']."'");
		$cache->update_moderators();
		$plugins->run_hooks('usercp_usergroups_change_displaygroup');
		redirect("usercp.php?action=usergroups", $lang->display_group_changed);
		exit;
	}

	// Leaving a group
	if($mybb->get_input('leavegroup', MyBB::INPUT_INT))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(my_strpos($ingroups, ",".$mybb->get_input('leavegroup', MyBB::INPUT_INT).",") === false)
		{
			error($lang->not_member_of_group);
		}
		if($mybb->user['usergroup'] == $mybb->get_input('leavegroup', MyBB::INPUT_INT))
		{
			error($lang->cannot_leave_primary_group);
		}

		$usergroup = $usergroups[$mybb->get_input('leavegroup', MyBB::INPUT_INT)];
		if($usergroup['type'] != 4 && $usergroup['type'] != 3 && $usergroup['type'] != 5)
		{
			error($lang->cannot_leave_group);
		}
		leave_usergroup($mybb->user['uid'], $mybb->get_input('leavegroup', MyBB::INPUT_INT));
		$plugins->run_hooks('usercp_usergroups_leave_group');
		redirect("usercp.php?action=usergroups", $lang->left_group);
		exit;
	}

	$groupleaders = [];

	// List of usergroup leaders
	$query = $db->query("
        SELECT g.*, u.username, u.displaygroup, u.usergroup, u.email, u.language
        FROM ".TABLE_PREFIX."groupleaders g
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
        ORDER BY u.username ASC
    ");
	while($leader = $db->fetch_array($query))
	{
		$groupleaders[$leader['gid']][$leader['uid']] = $leader;
	}

	// Joining a group
	if($mybb->get_input('joingroup', MyBB::INPUT_INT))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$usergroup = $usergroups[$mybb->get_input('joingroup', MyBB::INPUT_INT)];

		if($usergroup['type'] == 5)
		{
			error($lang->cannot_join_invite_group);
		}

		if(($usergroup['type'] != 4 && $usergroup['type'] != 3) || !$usergroup['gid'])
		{
			error($lang->cannot_join_group);
		}

		if(my_strpos($ingroups, ",".$mybb->get_input('joingroup', MyBB::INPUT_INT).",") !== false)
		{
			error($lang->already_member_of_group);
		}

		$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."' AND gid='".$mybb->get_input('joingroup', MyBB::INPUT_INT)."'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			error($lang->already_sent_join_request);
		}
		if($mybb->get_input('do') == "joingroup" && $usergroup['type'] == 4)
		{
			$reasonlength = my_strlen($mybb->get_input('reason'));

			if($reasonlength > 250) // Reason field is varchar(250) in database
			{
				error($lang->sprintf($lang->joinreason_too_long, ($reasonlength - 250)));
			}

			$now = TIME_NOW;
			$joinrequest = array(
				"uid" => $mybb->user['uid'],
				"gid" => $mybb->get_input('joingroup', MyBB::INPUT_INT),
				"reason" => $db->escape_string($mybb->get_input('reason')),
				"dateline" => TIME_NOW
			);

			$db->insert_query("joinrequests", $joinrequest);

			if(array_key_exists($usergroup['gid'], $groupleaders))
			{
				foreach($groupleaders[$usergroup['gid']] as $leader)
				{
					// Load language
					$lang->set_language($leader['language']);
					$lang->load("messages");

					$subject = $lang->sprintf($lang->emailsubject_newjoinrequest, $mybb->settings['bbname']);
					$message = $lang->sprintf($lang->email_groupleader_joinrequest, $leader['username'], $mybb->user['username'], $usergroup['title'], $mybb->settings['bbname'], $mybb->get_input('reason'), $mybb->settings['bburl'], $leader['gid']);
					my_mail($leader['email'], $subject, $message);
				}
			}

			// Load language
			$lang->set_language($mybb->user['language']);
			$lang->load("messages");

			$plugins->run_hooks('usercp_usergroups_join_group_request');
			redirect("usercp.php?action=usergroups", $lang->group_join_requestsent);
			exit;
		}
		elseif($usergroup['type'] == 4)
		{
			$joingroup = $mybb->get_input('joingroup', MyBB::INPUT_INT);

			output_page(\MyBB\template('usercp/joingroup.twig', [
				'usergroup' => $usergroup,
				'joingroup' => $joingroup,
			]));

			exit;
		}
		else
		{
			join_usergroup($mybb->user['uid'], $mybb->get_input('joingroup', MyBB::INPUT_INT));
			$plugins->run_hooks('usercp_usergroups_join_group');
			redirect("usercp.php?action=usergroups", $lang->joined_group);
		}
	}

	// Accepting invitation
	if($mybb->get_input('acceptinvite', MyBB::INPUT_INT))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$usergroup = $usergroups[$mybb->get_input('acceptinvite', MyBB::INPUT_INT)];

		if(my_strpos($ingroups, ",".$mybb->get_input('acceptinvite', MyBB::INPUT_INT).",") !== false)
		{
			error($lang->already_accepted_invite);
		}

		$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."' AND gid='".$mybb->get_input('acceptinvite', MyBB::INPUT_INT)."' AND invite='1'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			join_usergroup($mybb->user['uid'], $mybb->get_input('acceptinvite', MyBB::INPUT_INT));
			$db->delete_query("joinrequests", "uid='{$mybb->user['uid']}' AND gid='".$mybb->get_input('acceptinvite', MyBB::INPUT_INT)."'");
			$plugins->run_hooks('usercp_usergroups_accept_invite');
			redirect("usercp.php?action=usergroups", $lang->joined_group);
		}
		else
		{
			error($lang->no_pending_invitation);
		}
	}
	// Show listing of various group related things

	// List of groups this user is a leader of
	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->query("
                SELECT g.title, g.gid, g.type, COUNT(DISTINCT u.uid) AS users, COUNT(DISTINCT j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers, l.caninvitemembers
                FROM ".TABLE_PREFIX."groupleaders l
                LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
                LEFT JOIN ".TABLE_PREFIX."users u ON(((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
                LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid AND j.uid != 0)
                WHERE l.uid='".$mybb->user['uid']."'
                GROUP BY g.gid, g.title, g.type, l.canmanagerequests, l.canmanagemembers, l.caninvitemembers
            ");
			break;
		default:
			$query = $db->query("
                SELECT g.title, g.gid, g.type, COUNT(DISTINCT u.uid) AS users, COUNT(DISTINCT j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers, l.caninvitemembers
                FROM ".TABLE_PREFIX."groupleaders l
                LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
                LEFT JOIN ".TABLE_PREFIX."users u ON(((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
                LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid AND j.uid != 0)
                WHERE l.uid='".$mybb->user['uid']."'
                GROUP BY g.gid, g.title, g.type, l.canmanagerequests, l.canmanagemembers, l.caninvitemembers
            ");
	}

	$leadinggroups = [];
	$groupleader = [];
	while($usergroup = $db->fetch_array($query))
	{
		$groupleader[$usergroup['gid']] = 1;
		$leadinggroups[] = $usergroup;
	}

	// Fetch the list of groups the member is in
	// Do the primary group first
	$groupsmemberof = [];
	$usergroups[$mybb->user['usergroup']]['primary'] = 1;
	$groupsmemberof[] = $usergroups[$mybb->user['usergroup']];

	if($mybb->user['additionalgroups'])
	{
		$additionalgroups = implode(
			',',
			array_map(
				'intval',
				explode(',', $mybb->user['additionalgroups'])
			)
		);
		$query = $db->simple_select("usergroups", "*", "gid IN (".$additionalgroups.") AND gid !='".$mybb->user['usergroup']."'", array('order_by' => 'title'));
		while($usergroup = $db->fetch_array($query))
		{
			$groupsmemberof[] = $usergroup;
		}

	}

	// List of groups this user has applied for but has not been accepted in to
	$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."'");
	while($request = $db->fetch_array($query))
	{
		$appliedjoin[$request['gid']] = $request['dateline'];
	}

	// Fetch list of groups the member can join
	$existinggroups = $mybb->user['usergroup'];
	if($mybb->user['additionalgroups'])
	{
		$additionalgroups = implode(
			',',
			array_map(
				'intval',
				explode(',', $mybb->user['additionalgroups'])
			)
		);
		$existinggroups .= ",".$additionalgroups;
	}

	$joinablegroups = [];
	$query = $db->simple_select("usergroups", "*", "(type='3' OR type='4' OR type='5') AND gid NOT IN ($existinggroups)", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		if(isset($appliedjoin[$usergroup['gid']]) && $usergroup['type'] != 5)
		{
			$applydate = my_date('relative', $appliedjoin[$usergroup['gid']]);
			$usergroup['joinlink'] = $lang->sprintf($lang->join_group_applied, $applydate);
		}
		elseif(isset($appliedjoin[$usergroup['gid']]) && $usergroup['type'] == 5)
		{
			$usergroup['invited'] = true;
		}

		$usergroup['leaders'] = [];
		if(!empty($groupleaders[$usergroup['gid']]))
		{
			foreach($groupleaders[$usergroup['gid']] as $leader)
			{
				$leader['username'] = format_name(htmlspecialchars_uni($leader['username']), $leader['usergroup'], $leader['displaygroup']);
				$usergroup['leaders'][$leader['uid']] = build_profile_link($leader['username'], $leader['uid']);
			}
		}

		if(!in_array($mybb->user['uid'], array_keys($usergroup['leaders'])))
		{
			// User is already a leader of the group, so don't show as a "Join Group"
			$joinablegroups[] = $usergroup;
		}
	}

	$plugins->run_hooks('usercp_usergroups_end');

	output_page(\MyBB\template('usercp/usergroups.twig', [
		'joinablegroups' => $joinablegroups,
		'leadinggroups' => $leadinggroups,
		'groupleader' => $groupleader,
		'groupsmemberof' => $groupsmemberof
	]));
}

if($mybb->input['action'] == "attachments")
{
	require_once MYBB_ROOT."inc/functions_upload.php";

	if($mybb->settings['enableattachments'] == 0)
	{
		error($lang->attachments_disabled);
	}

	$plugins->run_hooks("usercp_attachments_start");

	// Get unviewable forums
	$f_perm_sql = '';
	$unviewable_forums = get_unviewable_forums(true);
	$inactiveforums = get_inactive_forums();
	if($unviewable_forums)
	{
		$f_perm_sql = " AND t.fid NOT IN ($unviewable_forums)";
	}
	if($inactiveforums)
	{
		$f_perm_sql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$attachments = '';

	// Pagination
	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	if($page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$end = $start + $perpage;
	$lower = $start + 1;

	$query = $db->query("
		SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE a.uid='".$mybb->user['uid']."' {$f_perm_sql}
		ORDER BY p.dateline DESC, p.pid DESC LIMIT {$perpage} OFFSET {$start}
	");
	$attachments = [];

	$bandwidth = $totaldownloads = $totalusage = $totalattachments = $processedattachments = 0;
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
			$attachment['subject'] = $parser->parse_badwords($attachment['subject']);
			$attachment['postlink'] = get_post_link($attachment['pid'], $attachment['tid']);
			$attachment['threadlink'] = get_thread_link($attachment['tid']);
			$attachment['threadsubject'] = $parser->parse_badwords($attachment['threadsubject']);

			$attachment['size'] = get_friendly_size($attachment['filesize']);
			$attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));

			$attachment['date'] = my_date('relative', $attachment['dateline']);

			$attachments[] = $attachment;

			// Add to bandwidth total
			$bandwidth += ($attachment['filesize'] * $attachment['downloads']);
			$totaldownloads += $attachment['downloads'];
			$totalusage += $attachment['filesize'];
			++$totalattachments;
		}
		else
		{
			// This little thing delets attachments without a thread/post
			remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
		}
		++$processedattachments;
	}

	$multipage = '';
	if($processedattachments >= $perpage || $page > 1)
	{
		$query = $db->query("
			SELECT SUM(a.filesize) AS ausage, COUNT(a.aid) AS acount
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE a.uid='".$mybb->user['uid']."' {$f_perm_sql}
		");
		$usage = $db->fetch_array($query);
		$totalusage = $usage['ausage'];
		$totalattachments = $usage['acount'];

		$multipage = multipage($totalattachments, $perpage, $page, "usercp.php?action=attachments");
	}

	$friendlyusage = get_friendly_size((int)$totalusage);
	$percent = 0;
	if($mybb->usergroup['attachquota'])
	{
		$percent = round(($totalusage / ($mybb->usergroup['attachquota'] * 1024)) * 100);
		$friendlyusage .= $lang->sprintf($lang->attachments_usage_percent, $percent);
		$attachquota = get_friendly_size($mybb->usergroup['attachquota'] * 1024);
		$usagenote = $lang->sprintf($lang->attachments_usage_quota, $friendlyusage, $attachquota, $totalattachments);
	}
	else
	{
		$attachquota = $lang->unlimited;
		$usagenote = $lang->sprintf($lang->attachments_usage, $friendlyusage, $totalattachments);
	}

	$bandwidth = get_friendly_size($bandwidth);

	if(!$attachments)
	{
		$usagenote = '';
		$delete_button = '';
	}

	$plugins->run_hooks('usercp_attachments_end');

	output_page(\MyBB\template('usercp/attachments.twig', [
		'attachments' => $attachments,
		'usage_note' => $usagenote,
		'multipage' => $multipage,
		'total_attachments' => $totalattachments,
		'friendly_usage' => $friendlyusage,
		'percent' => $percent,
		'attach_quota' => $attachquota,
		'total_downloads' => $totaldownloads,
		'bandwidth' => $bandwidth,
	]));
}

if($mybb->input['action'] == "do_attachments" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	require_once MYBB_ROOT."inc/functions_upload.php";
	if(!isset($mybb->input['attachments']) || !is_array($mybb->input['attachments']))
	{
		error($lang->no_attachments_selected);
	}

	$plugins->run_hooks("usercp_do_attachments_start");

	// Get unviewable forums
	$f_perm_sql = '';
	$unviewable_forums = get_unviewable_forums(true);
	$inactiveforums = get_inactive_forums();
	if($unviewable_forums)
	{
		$f_perm_sql = " AND p.fid NOT IN ($unviewable_forums)";
	}
	if($inactiveforums)
	{
		$f_perm_sql .= " AND p.fid NOT IN ($inactiveforums)";
	}

	$aids = implode(',', array_map('intval', $mybb->input['attachments']));

	$query = $db->query("
		SELECT a.*, p.fid
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
		WHERE aid IN ({$aids}) AND a.uid={$mybb->user['uid']} {$f_perm_sql}
	");

	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], '', $attachment['aid']);
	}
	$plugins->run_hooks('usercp_do_attachments_end');
	redirect("usercp.php?action=attachments", $lang->attachments_deleted);
}

if($mybb->input['action'] == "do_notepad" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	// Cap at 60,000 chars; text will allow up to 65535?
	if(my_strlen($mybb->get_input('notepad')) > 60000)
	{
		$mybb->input['notepad'] = my_substr($mybb->get_input('notepad'), 0, 60000);
	}

	$plugins->run_hooks('usercp_do_notepad_start');
	$db->update_query("users", array('notepad' => $db->escape_string($mybb->get_input('notepad'))), "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks('usercp_do_notepad_end');
	redirect("usercp.php", $lang->redirect_notepadupdated);
}

if(!$mybb->input['action'])
{
	// Get posts per day
	$daysreg = (TIME_NOW - $mybb->user['regdate']) / (24 * 3600);

	if($daysreg < 1)
	{
		$daysreg = 1;
	}

	$perday = $mybb->user['postnum'] / $daysreg;
	$perday = round($perday, 2);
	if($perday > $mybb->user['postnum'])
	{
		$perday = $mybb->user['postnum'];
	}

	$stats = $cache->read("stats");
	$posts = $stats['numposts'];
	if($posts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $mybb->user['postnum'] * 100 / $posts;
		$percent = round($percent, 2);
	}

	$lang->posts_day = $lang->sprintf($lang->posts_day, my_number_format($perday), $percent);
	$mybb->user['regdate'] = my_date('relative', $mybb->user['regdate']);

	$useravatar = format_avatar($mybb->user['avatar'], $mybb->user['avatardimensions'], '100x100');

	// Make reputations row
	$reputation_link = '';
	if($mybb->usergroup['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
	{
		$reputation_link = get_reputation($mybb->user['reputation']);
	}

	$latest_warnings = '';
	if($mybb->settings['enablewarningsystem'] != 0 && $mybb->settings['canviewownwarning'] != 0)
	{
		if($mybb->settings['maxwarningpoints'] < 1)
		{
			$mybb->settings['maxwarningpoints'] = 10;
		}
		$warning_level = round($mybb->user['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);
		if($warning_level > 100)
		{
			$warning_level = 100;
		}

		if($mybb->user['warningpoints'] > $mybb->settings['maxwarningpoints'])
		{
			$mybb->user['warningpoints'] = $mybb->settings['maxwarningpoints'];
		}

		$warnings = [];
		if($warning_level > 0)
		{
			require_once MYBB_ROOT.'inc/datahandlers/warnings.php';
			$warningshandler = new WarningsHandler('update');

			$warningshandler->expire_warnings();

			$lang->current_warning_level = $lang->sprintf($lang->current_warning_level, $warning_level, $mybb->user['warningpoints'], $mybb->settings['maxwarningpoints']);
			// Fetch latest warnings
			$query = $db->query("
                SELECT w.*, t.title AS type_title, u.username, p.subject AS post_subject
                FROM ".TABLE_PREFIX."warnings w
                LEFT JOIN ".TABLE_PREFIX."warningtypes t ON (t.tid=w.tid)
                LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=w.issuedby)
                LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=w.pid)
                WHERE w.uid='{$mybb->user['uid']}'
                ORDER BY w.expired ASC, w.dateline DESC
                LIMIT 5
            ");
			while($warning = $db->fetch_array($query))
			{
				if($warning['post_subject'])
				{
					$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
					$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
					$warning['postlink'] = get_post_link($warning['pid']);
				}
				$warning['username'] = htmlspecialchars_uni($warning['username']);
				$warning['issuedby'] = build_profile_link($warning['username'], $warning['issuedby']);
				$warning['dateissued'] = my_date('relative', $warning['dateline']);
				if($warning['type_title'])
				{
					$warning['type'] = $warning['type_title'];
				}
				else
				{
					$warning['type'] = $warning['title'];
				}
				if($warning['points'] > 0)
				{
					$warning['points'] = "+{$warning['points']}";
				}
				$warning['points'] = $lang->sprintf($lang->warning_points, $warning['points']);

				// Figure out expiration time
				if($warning['daterevoked'])
				{
					$warning['expiration'] = $lang->warning_revoked;
				}
				elseif($warning['expired'])
				{
					$warning['expiration'] = $lang->already_expired;
				}
				elseif($warning['expires'] == 0)
				{
					$warning['expiration'] = $lang->never;
				}
				else
				{
					$warning['expiration'] = nice_time($warning['expires'] - TIME_NOW);
				}

				$warnings[] = $warning;
			}
		}
	}

	// Format username
	$username = format_name(htmlspecialchars_uni($mybb->user['username']), $mybb->user['usergroup'], $mybb->user['displaygroup']);
	$username = build_profile_link($username, $mybb->user['uid']);

	// Format post numbers
	$mybb->user['posts'] = my_number_format($mybb->user['postnum']);

	// Thread Subscriptions with New Posts
	$subscriptions = $latestsubscriptions = [];
	$query = $db->simple_select("threadsubscriptions", "sid", "uid = '".$mybb->user['uid']."'", array("limit" => 1));
	if($db->num_rows($query))
	{
		$where = array(
			"s.uid={$mybb->user['uid']}",
			"t.lastposteruid!={$mybb->user['uid']}",
			get_visible_where('t')
		);

		if($unviewable_forums = get_unviewable_forums(true))
		{
			$where[] = "t.fid NOT IN ({$unviewable_forums})";
		}

		if($inactive_forums = get_inactive_forums())
		{
			$where[] = "t.fid NOT IN ({$inactive_forums})";
		}

		$where = implode(' AND ', $where);

		$query = $db->query("
            SELECT s.*, t.*, t.username AS threadusername, u.username, last_poster.avatar as last_poster_avatar
            FROM ".TABLE_PREFIX."threadsubscriptions s
            LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
            LEFT JOIN ".TABLE_PREFIX."users last_poster ON (t.lastposteruid=last_poster.uid)
            WHERE {$where}
            ORDER BY t.lastpost DESC
            LIMIT 0, 10
        ");

		$subscriptions = array();
		$fpermissions = forum_permissions();

		while($subscription = $db->fetch_array($query))
		{
			$forumpermissions = $fpermissions[$subscription['fid']];

			if(!isset($forumpermissions['canonlyviewownthreads']) || $forumpermissions['canonlyviewownthreads'] == 0 || $subscription['uid'] == $mybb->user['uid'])
			{
				$subscriptions[$subscription['tid']] = $subscription;
			}
		}

		if($subscriptions)
		{
			$tids = implode(",", array_keys($subscriptions));

			// Checking read
			if($mybb->settings['threadreadcut'] > 0)
			{
				$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
				while($readthread = $db->fetch_array($query))
				{
					if($readthread['dateline'] >= $subscriptions[$readthread['tid']]['lastpost'])
					{
						unset($subscriptions[$readthread['tid']]); // If it's already been read, then don't display the thread
					}
					else
					{
						$subscriptions[$readthread['tid']]['lastread'] = $readthread['dateline'];
					}
				}
			}

			if($subscriptions)
			{
				if($mybb->settings['dotfolders'] != 0)
				{
					$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
					while($post = $db->fetch_array($query))
					{
						$subscriptions[$post['tid']]['doticon'] = 1;
					}
				}

				$icon_cache = $cache->read("posticons");
				$threadprefixes = build_prefixes();
				$latest_subscribed_threads = '';

				foreach($subscriptions as $thread)
				{
					$plugins->run_hooks("usercp_thread_subscriptions_thread");

					if(!empty($thread['tid']))
					{
						$thread['subject'] = $parser->parse_badwords($thread['subject']);
						$thread['subject'] = htmlspecialchars_uni($thread['subject']);
						$thread['threadlink'] = get_thread_link($thread['tid']);
						$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

						// If this thread has a prefix...
						if($thread['prefix'] != 0 && !empty($threadprefixes[$thread['prefix']]))
						{
							$thread['displayprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'];
						}
						else
						{
							$thread['displayprefix'] = '';
						}

						// Fetch the thread icon if we have one
						if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
						{
							$icon = $icon_cache[$thread['icon']];
							$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
							$thread['icon'] = $icon;
						}
						else
						{
							$thread['icon'] = "&nbsp;";
						}
						// Determine the folder
						$thread['folder'] = $thread['folder_label'] = $thread['class'] = '';

						// Check to see which icon we display
						if(!empty($thread['lastread']) && $thread['lastread'] < $thread['lastpost'])
						{
							$thread['folder'] .= "new";
							$thread['folder_label'] .= $lang->icon_new;
							$thread['new_class'] = "subject_new";
							$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
						}
						else
						{
							$thread['folder_label'] .= $lang->icon_no_new;
							$thread['new_class'] = "subject_old";
						}

						$thread['folder'] .= "folder";

						if($thread['visible'] == 0)
						{
							$thread['class'] = "trow_shaded";
						}

						$thread['lastpostdate'] = my_date('relative', $thread['lastpost']);

						$thread['last_poster_name'] = $thread['lastposter'];
						$thread['lastposter'] = build_profile_link($thread['lastposter'], $thread['lastposteruid']);

						$thread['replies'] = my_number_format($thread['replies']);
						$thread['views'] = my_number_format($thread['views']);
						$thread['author'] = build_profile_link($thread['username'], $thread['uid']);
					}
					$latestsubscriptions[] = $thread;
				}
			}
		}
	}

	// User's Latest Threads
	$where = array(
		"t.uid={$mybb->user['uid']}",
		get_visible_where('t')
	);

	if($unviewable_forums = get_unviewable_forums(true))
	{
		$where[] = "t.fid NOT IN ({$unviewable_forums})";
	}

	if($inactive_forums = get_inactive_forums())
	{
		$where[] = "t.fid NOT IN ({$inactive_forums})";
	}

	$where = implode(' AND ', $where);

	$query = $db->query("
        SELECT t.*, t.username AS threadusername, u.username, last_poster.avatar as last_poster_avatar
        FROM ".TABLE_PREFIX."threads t
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
        LEFT JOIN ".TABLE_PREFIX."users last_poster ON (t.lastposteruid=last_poster.uid)
        WHERE {$where}
        ORDER BY t.lastpost DESC
        LIMIT 0, 5
    ");

	// Figure out whether we can view these threads...
	$threadcache = $latestthreads = [];
	$fpermissions = forum_permissions();
	while($thread = $db->fetch_array($query))
	{
		$threadcache[$thread['tid']] = $thread;
	}

	if(!empty($threadcache))
	{
		$tids = implode(",", array_keys($threadcache));
		$readforums = [];

		// Read Forums
		$query = $db->query("
            SELECT f.fid, fr.dateline AS lastread
            FROM ".TABLE_PREFIX."forums f
            LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
            WHERE f.active != 0
            ORDER BY pid, disporder
        ");

		while($forum = $db->fetch_array($query))
		{
			$readforums[$forum['fid']] = $forum['lastread'];
		}

		// Threads being read?
		if($mybb->settings['threadreadcut'] > 0)
		{
			$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($readthread = $db->fetch_array($query))
			{
				$threadcache[$readthread['tid']]['lastread'] = $readthread['dateline'];
			}
		}

		// Icon Stuff
		if($mybb->settings['dotfolders'] != 0)
		{
			$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
			while($post = $db->fetch_array($query))
			{
				$threadcache[$post['tid']]['doticon'] = 1;
			}
		}

		$icon_cache = $cache->read("posticons");
		$threadprefixes = build_prefixes();

		// Run the threads...
		foreach($threadcache as $thread)
		{
			$plugins->run_hooks("usercp_latest_threads_thread");
			if($thread['tid'])
			{
				$lastread = 0;

				// If this thread has a prefix...
				if($thread['prefix'] != 0)
				{
					if(!empty($threadprefixes[$thread['prefix']]))
					{
						$thread['displayprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'];
					}
				}
				else
				{
					$thread['displayprefix'] = '';
				}

				$thread['subject'] = $parser->parse_badwords($thread['subject']);
				$thread['threadlink'] = get_thread_link($thread['tid']);
				$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

				if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
				{
					$icon = $icon_cache[$thread['icon']];
					$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
					$thread['icon'] = $icon;
				}
				else
				{
					$thread['icon'] = "&nbsp;";
				}

				if($mybb->settings['threadreadcut'] > 0)
				{
					$forum_read = $readforums[$thread['fid']];

					$read_cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
					if($forum_read == 0 || $forum_read < $read_cutoff)
					{
						$forum_read = $read_cutoff;
					}
				}

				$cutoff = 0;
				if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
				{
					$cutoff = TIME_NOW - $mybb->settings['threadreadcut'] * 60 * 60 * 24;
				}

				if($thread['lastpost'] > $cutoff)
				{
					if(!empty($thread['lastread']))
					{
						$lastread = $thread['lastread'];
					}
				}

				if(!$lastread)
				{
					$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
					if($readcookie > $forum_read)
					{
						$lastread = $readcookie;
					}
					else
					{
						$lastread = $forum_read;
					}
				}

				$thread['lastread'] = $lastread;
				$thread['folder_label'] = '';

				// Folder Icons
				if($thread['doticon'])
				{
					$thread['folder'] = "dot_";
					$thread['folder_label'] .= $lang->icon_dot;
				}

				if($thread['lastpost'] > $lastread && $lastread)
				{
					$thread['folder'] .= "new";
					$thread['folder_label'] .= $lang->icon_new;
					$thread['class'] = "subject_new";
					$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
				}
				else
				{
					$thread['folder_label'] .= $lang->icon_no_new;
					$thread['class'] = "subject_old";
				}

				if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
				{
					$thread['folder'] .= "hot";
					$thread['folder_label'] .= $lang->icon_hot;
				}

				// Is our thread visible?
				if($thread['visible'] == 0)
				{
					$thread['class'] = 'trow_shaded';
				}

				if($thread['closed'] == 1)
				{
					$thread['folder'] .= "close";
					$thread['folder_label'] .= $lang->icon_close;
				}

				$thread['folder'] .= "folder";

				$thread['lastpostdate'] = my_date('relative', $thread['lastpost']);

				$thread['last_poster_name'] = $thread['lastposter'];
				$thread['lastposter'] = build_profile_link($thread['lastposter'], $thread['lastposteruid']);

				$thread['replies'] = my_number_format($thread['replies']);
				$thread['views'] = my_number_format($thread['views']);
				$thread['author'] = build_profile_link($thread['username'], $thread['uid']);

				$latestthreads[] = $thread;
			}
		}
	}

	$plugins->run_hooks('usercp_end');

	output_page(\MyBB\template('usercp/home.twig', [
		'useravatar' => $useravatar,
		'username' => $username,
		'groupscache' => $groupscache,
		'reputation_link' => $reputation_link,
		'latestsubscriptions' => $latestsubscriptions,
		'latestthreads' => $latestthreads,
		'warnings' => $warnings
	]));
}
