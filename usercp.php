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
define('THIS_SCRIPT', 'usercp.php');

$templatelist = "usercp,usercp_nav,usercp_profile,usercp_changename,usercp_email,usercp_password,usercp_subscriptions_thread,forumbit_depth2_forum_lastpost,usercp_forumsubscriptions_forum";
$templatelist .= ",usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,usercp_usergroups_leader_usergroup,usercp_usergroups_leader,usercp_currentavatar,usercp_reputation";
$templatelist .= ",usercp_attachments_attachment,usercp_attachments,usercp_profile_away,usercp_profile_customfield,usercp_profile_profilefields,usercp_profile_customtitle,usercp_forumsubscriptions_none";
$templatelist .= ",usercp_forumsubscriptions,usercp_subscriptions_none,usercp_subscriptions,usercp_options_pms_from_buddys,usercp_options_tppselect,usercp_options_pppselect,usercp_options";
$templatelist .= ",usercp_nav_editsignature,usercp_referrals,usercp_notepad,usercp_latest_threads_threads,forumdisplay_thread_gotounread,usercp_latest_threads,usercp_subscriptions_remove";
$templatelist .= ",usercp_editsig_suspended,usercp_editsig,usercp_avatar_gallery_avatar,usercp_avatar_gallery_blankblock,usercp_avatar_gallery_noavatars,usercp_avatar_gallery,usercp_avatar_current";
$templatelist .= ",usercp_avatar,usercp_editlists_userusercp_editlists,usercp_drafts_draft,usercp_drafts_none,usercp_drafts,usercp_usergroups_joingroup,usercp_attachments_none";
$templatelist .= ",usercp_warnings_warning,usercp_warnings,usercp_latest_subscribed_threads,usercp_latest_subscribed,usercp_nav_messenger_tracking,multipage_prevpage,multipage_start,multipage_end";
$templatelist .= ",multipage_nextpage,multipage,multipage_page_current,codebuttons,smilieinsert_getmore,smilieinsert_smilie,smilieinsert_smilie_empty,smilieinsert";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("usercp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == 0)
{
	error_no_permission();
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**".$lang->folder_inbox."$%%$2**".$lang->folder_sent_items."$%%$3**".$lang->folder_drafts."$%%$4**".$lang->folder_trash;
	$db->update_query("users", array('pmfolders' => $mybb->user['pmfolders']), "uid='".$mybb->user['uid']."'");
}

$errors = '';

$mybb->input['action'] = $mybb->get_input('action');

usercp_menu();

$plugins->run_hooks("usercp_start");
if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	$parser_options = array(
		'allow_html' => $mybb->settings['sightml'],
		'filter_badwords' => 1,
		'allow_mycode' => $mybb->settings['sigmycode'],
		'allow_smilies' => $mybb->settings['sigsmilies'],
		'allow_imgcode' => $mybb->settings['sigimgcode'],
		"filter_badwords" => 1
	);
	$parsed_sig = $parser->parse_message($mybb->get_input('signature'), $parser_options);
	if((($mybb->settings['sigimgcode'] == 0 && $mybb->settings['sigsmilies'] != 1) &&
		substr_count($parsed_sig, "<img") > 0) ||
		(($mybb->settings['sigimgcode'] == 1 || $mybb->settings['sigsmilies'] == 1) &&
		substr_count($parsed_sig, "<img") > $mybb->settings['maxsigimages'])
	)
	{
		if($mybb->settings['sigimgcode'] == 1)
		{
			$imgsallowed = $mybb->settings['maxsigimages'];
		}
		else
		{
			$imgsallowed = 0;
		}
		$lang->too_many_sig_images2 = $lang->sprintf($lang->too_many_sig_images2, $imgsallowed);
		$error = inline_error($lang->too_many_sig_images." ".$lang->too_many_sig_images2);
		$mybb->input['preview'] = 1;
	}
	else if($mybb->settings['siglength'] > 0)
	{
		if($mybb->settings['sigcountmycode'] == 0)
		{
			$parsed_sig = $parser->text_parse_message($mybb->get_input('signature'));
		}
		else
		{
			$parsed_sig = $mybb->get_input('signature');
		}
		$parsed_sig = preg_replace("#\s#", "", $parsed_sig);
		$sig_length = my_strlen($parsed_sig);
		if($sig_length > $mybb->settings['siglength'])
		{
			$lang->sig_too_long = $lang->sprintf($lang->sig_too_long, $mybb->settings['siglength']);
			if($sig_length - $mybb->settings['siglength'] > 1)
			{
				$lang->sig_too_long .= $lang->sprintf($lang->sig_remove_chars_plural, $sig_length-$mybb->settings['siglength']);
			}
			else
			{
				$lang->sig_too_long .= $lang->sig_remove_chars_singular;
			}
			$error = inline_error($lang->sig_too_long);
		}
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

	$plugins->run_hooks("usercp_do_profile_start");

	if($mybb->get_input('away', 1) == 1 && $mybb->settings['allowaway'] != 0)
	{
		$awaydate = TIME_NOW;
		if(!empty($mybb->input['awayday']))
		{
			// If the user has indicated that they will return on a specific day, but not month or year, assume it is current month and year
			if(!$mybb->get_input('awaymonth', 1))
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			if(!$mybb->get_input('awayyear', 1))
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}

			$return_month = intval(substr($mybb->get_input('awaymonth'), 0, 2));
			$return_day = intval(substr($mybb->get_input('awayday'), 0, 2));
			$return_year = min(intval($mybb->get_input('awayyear')), 9999);

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
		"day" => $mybb->get_input('bday1', 1),
		"month" => $mybb->get_input('bday2', 1),
		"year" => $mybb->get_input('bday3', 1)
	);

	// Set up user handler.
	require_once "inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array(
		"uid" => $mybb->user['uid'],
		"website" => $mybb->get_input('website'),
		"icq" => $mybb->get_input('icq', 1),
		"aim" => $mybb->get_input('aim'),
		"yahoo" => $mybb->get_input('yahoo'),
		"msn" => $mybb->get_input('msn'),
		"birthday" => $bday,
		"birthdayprivacy" => $mybb->get_input('birthdayprivacy', 1),
		"away" => $away,
		"profile_fields" => $mybb->get_input('profile_fields', 2)
	);

	if($mybb->usergroup['cancustomtitle'] == 1)
	{
		if($mybb->get_input('usertitle') != '')
		{
			$user['usertitle'] = $mybb->get_input('usertitle');
		}
		else if(!empty($mybb->input['reverttitle']))
		{
			$user['usertitle'] = '';
		}
	}
	$userhandler->set_data($user);

	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();

		// Set allowed value otherwise select options disappear
		if(in_array($lang->userdata_invalid_birthday_privacy, $errors))
		{
			$mybb->input['birthdayprivacy'] = 'none';
		}

		$errors = inline_error($errors);
		$mybb->input['action'] = "profile";
	}
	else
	{
		$userhandler->update_user();

		$plugins->run_hooks("usercp_do_profile_end");
		redirect("usercp.php", $lang->redirect_profileupdated);
	}
}

if($mybb->input['action'] == "profile")
{
	if($errors)
	{
		$user = $mybb->input;
		$bday = array();
		$bday[0] = $mybb->get_input('bday1', 1);
		$bday[1] = $mybb->get_input('bday2', 1);
		$bday[2] = $mybb->get_input('bday3', 1);
	}
	else
	{
		$user = $mybb->user;
		$bday = explode("-", $user['birthday']);
		if(!isset($bday[1]))
		{
			$bday[1] = 0;
		}
		if(!isset($bday[2]))
		{
			$bday[2] = '';
		}
	}

	$plugins->run_hooks("usercp_profile_start");

	$bdaydaysel = '';
	for($i = 1; $i <= 31; ++$i)
	{
		if($bday[0] == $i)
		{
			$bdaydaysel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$bdaymonthsel = array();
	foreach(range(1, 12) as $month)
	{
		$bdaymonthsel[$month] = '';
	}
	$bdaymonthsel[$bday[1]] = 'selected="selected"';

	$bdayprivacysel = '';
	if($user['birthdayprivacy'] == 'all' || !$user['birthdayprivacy'])
	{
		$bdayprivacysel .= "<option value=\"all\" selected=\"selected\">{$lang->birthdayprivacyall}</option>\n";
		$bdayprivacysel .= "<option value=\"none\">{$lang->birthdayprivacynone}</option>\n";
		$bdayprivacysel .= "<option value=\"age\">{$lang->birthdayprivacyage}</option>";
	}
	else if($user['birthdayprivacy'] == 'none')
	{
		$bdayprivacysel .= "<option value=\"all\">{$lang->birthdayprivacyall}</option>\n";
		$bdayprivacysel .= "<option value=\"none\" selected=\"selected\">{$lang->birthdayprivacynone}</option>\n";
		$bdayprivacysel .= "<option value=\"age\">{$lang->birthdayprivacyage}</option>";
	}
	else if($user['birthdayprivacy'] == 'age')
	{
		$bdayprivacysel .= "<option value=\"all\">{$lang->birthdayprivacyall}</option>\n";
		$bdayprivacysel .= "<option value=\"none\">{$lang->birthdayprivacynone}</option>\n";
		$bdayprivacysel .= "<option value=\"age\" selected=\"selected\">{$lang->birthdayprivacyage}</option>";
	}

	if($user['website'] == "" || $user['website'] == "http://")
	{
		$user['website'] = "http://";
	}
	else
	{
		$user['website'] = htmlspecialchars_uni($user['website']);
	}

	if($user['icq'] != "0")
	{
		$user['icq'] = intval($user['icq']);
	}

	if($user['icq'] == 0)
	{
		$user['icq'] = '';
	}

	if($errors)
	{
		$user['msn'] = htmlspecialchars_uni($user['msn']);
		$user['aim'] = htmlspecialchars_uni($user['aim']);
		$user['yahoo'] = htmlspecialchars_uni($user['yahoo']);
	}
	if($mybb->settings['allowaway'] != 0)
	{
		$awaycheck = array('', '');
		if($errors)
		{
			if($user['away'] == 1)
			{
				$awaycheck[1] = "checked=\"checked\"";
			}
			else
			{
				$awaycheck[0] = "checked=\"checked\"";
			}
			$returndate = array();
			$returndate[0] = $mybb->get_input('awayday', 1);
			$returndate[1] = $mybb->get_input('awaymonth', 1);
			$returndate[2] = $mybb->get_input('awayyear', 1);
			$user['awayreason'] = htmlspecialchars_uni($mybb->get_input('awayreason'));
		}
		else
		{
			$user['awayreason'] = htmlspecialchars_uni($user['awayreason']);
			if($mybb->user['away'] == 1)
			{
				$awaydate = my_date($mybb->settings['dateformat'], $mybb->user['awaydate']);
				$awaycheck[1] = "checked=\"checked\"";
				$awaynotice = $lang->sprintf($lang->away_notice_away, $awaydate);
			}
			else
			{
				$awaynotice = $lang->away_notice;
				$awaycheck[0] = "checked=\"checked\"";
			}
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
		$returndatesel = '';
		for($i = 1; $i <= 31; ++$i)
		{
			if($returndate[0] == $i)
			{
				$returndatesel .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
			}
			else
			{
				$returndatesel .= "<option value=\"$i\">$i</option>\n";
			}
		}
		$returndatemonthsel = array();
		foreach(range(1, 12) as $month)
		{
			$returndatemonthsel[$month] = '';
		}
		$returndatemonthsel[$returndate[1]] = "selected";

		eval("\$awaysection = \"".$templates->get("usercp_profile_away")."\";");
	}
	// Custom profile fields baby!
	$altbg = "trow1";
	$requiredfields = '';
	$customfields = '';
	$mybb->input['profile_fields'] = $mybb->get_input('profile_fields', 2);
	$query = $db->simple_select("profilefields", "*", "editable=1", array('order_by' => 'disporder'));
	while($profilefield = $db->fetch_array($query))
	{
		// Does this field have a minimum post count?
		if($profilefield['postnum'] && $profilefield['postnum'] > $user['postnum'])
		{
			continue;
		}

		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$profilefield['name'] = htmlspecialchars_uni($profilefield['name']);
		$profilefield['description'] = htmlspecialchars_uni($profilefield['description']);
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		if(isset($thing[1]))
		{
			$options = $thing[1];
		}
		else
		{
			$options = array();
		}
		$field = "fid{$profilefield['fid']}";
		$select = '';
		if($errors)
		{
			if(!isset($mybb->input['profile_fields'][$field]))
			{
				$mybb->input['profile_fields'][$field] = '';
			}
			$userfield = $mybb->input['profile_fields'][$field];
		}
		else
		{
			$userfield = $user[$field];
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
					$val = htmlspecialchars_uni($val);
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
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>\n";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 3;
				}
				$code = "<select name=\"profile_fields[$field][]\" size=\"{$profilefield['length']}\" multiple=\"multiple\">$select</select>";
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
					if($val == htmlspecialchars_uni($userfield))
					{
						$sel = " selected=\"selected\"";
					}
					$select .= "<option value=\"$val\"$sel>$val</option>";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 1;
				}
				$code = "<select name=\"profile_fields[$field]\" size=\"{$profilefield['length']}\">$select</select>";
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
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"radio\" class=\"radio\" name=\"profile_fields[$field]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
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
						$checked = " checked=\"checked\"";
					}
					$code .= "<input type=\"checkbox\" class=\"checkbox\" name=\"profile_fields[$field][]\" value=\"$val\"$checked /> <span class=\"smalltext\">$val</span><br />";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfield);
			$code = "<textarea name=\"profile_fields[$field]\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfield);
			$maxlength = "";
			if($profilefield['maxlength'] > 0)
			{
				$maxlength = " maxlength=\"{$profilefield['maxlength']}\"";
			}
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" class=\"textbox\" size=\"{$profilefield['length']}\"{$maxlength} value=\"$value\" />";
		}
		if($profilefield['required'] == 1)
		{
			eval("\$requiredfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		else
		{
			eval("\$customfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		$altbg = alt_trow();
		$code = "";
		$select = "";
		$val = "";
		$options = "";
		$expoptions = "";
		$useropts = "";
		$seloptions = "";
	}
	if($customfields)
	{
		eval("\$customfields = \"".$templates->get("usercp_profile_profilefields")."\";");
	}

	if($mybb->usergroup['cancustomtitle'] == 1)
	{
		if($mybb->usergroup['usertitle'] == "")
		{
			$defaulttitle = '';
			$usertitles = $cache->read('usertitles');

			foreach($usertitles as $title)
			{
				if($title['posts'] <= $mybb->user['postnum'])
				{
					$defaulttitle = $title['title'];
					break;
				}
			}
		}
		else
		{
			$defaulttitle = $mybb->usergroup['usertitle'];
		}

		$newtitle = '';
		if(trim($user['usertitle']) == '')
		{
			$lang->current_custom_usertitle = '';
		}
		else
		{
			if($errors)
			{
				$newtitle = htmlspecialchars_uni($user['usertitle']);
				$user['usertitle'] = $mybb->user['usertitle'];
			}
		}
		eval("\$customtitle = \"".$templates->get("usercp_profile_customtitle")."\";");
	}
	else
	{
		$customtitle = "";
	}

	$plugins->run_hooks("usercp_profile_end");

	eval("\$editprofile = \"".$templates->get("usercp_profile")."\";");
	output_page($editprofile);
}

if($mybb->input['action'] == "do_options" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_options_start");

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler("update");

	$user = array(
		"uid" => $mybb->user['uid'],
		"style" => $mybb->get_input('style', 1),
		"dateformat" => $mybb->get_input('dateformat', 1),
		"timeformat" => $mybb->get_input('timeformat', 1),
		"timezone" => $db->escape_string($mybb->get_input('timezoneoffset')),
		"language" => $mybb->get_input('language')
	);

	$user['options'] = array(
		"allownotices" => $mybb->get_input('allownotices', 1),
		"hideemail" => $mybb->get_input('hideemail', 1),
		"subscriptionmethod" => $mybb->get_input('subscriptionmethod', 1),
		"invisible" => $mybb->get_input('invisible', 1),
		"dstcorrection" => $mybb->get_input('dstcorrection', 1),
		"threadmode" => $mybb->get_input('threadmode'),
		"showsigs" => $mybb->get_input('showsigs', 1),
		"showavatars" => $mybb->get_input('showavatars', 1),
		"showquickreply" => $mybb->get_input('showquickreply', 1),
		"receivepms" => $mybb->get_input('receivepms', 1),
		"pmnotice" => $mybb->get_input('pmnotice', 1),
		"receivefrombuddy" => $mybb->get_input('receivefrombuddy', 1),
		"daysprune" => $mybb->get_input('daysprune', 1),
		"showcodebuttons" => $mybb->get_input('showcodebuttons', 1),
		"pmnotify" => $mybb->get_input('pmnotify', 1),
		"showredirect" => $mybb->get_input('showredirect', 1),
		"classicpostbit" => $mybb->get_input('classicpostbit', 1)
	);

	if($mybb->settings['usertppoptions'])
	{
		$user['options']['tpp'] = $mybb->get_input('tpp', 1);
	}

	if($mybb->settings['userpppoptions'])
	{
		$user['options']['ppp'] = $mybb->get_input('ppp', 1);
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

		$plugins->run_hooks("usercp_do_options_end");

		redirect("usercp.php", $lang->redirect_optionsupdated);
	}
}

if($mybb->input['action'] == "options")
{
	$plugins->run_hooks("usercp_options_start");

	if($errors != '')
	{
		$user = $mybb->input;
	}
	else
	{
		$user = $mybb->user;
	}
	$languages = $lang->get_languages();
	$langoptions = '';
	foreach($languages as $lname => $language)
	{
		$sel = "";
		if(isset($user['language']) && $user['language'] == $lname)
		{
			$sel = " selected=\"selected\"";
		}
		$langoptions .= "<option value=\"$lname\"$sel>".htmlspecialchars_uni($language)."</option>\n";
	}

	// Lets work out which options the user has selected and check the boxes
	if(isset($user['allownotices']) && $user['allownotices'] == 1)
	{
		$allownoticescheck = "checked=\"checked\"";
	}
	else
	{
		$allownoticescheck = "";
	}

	if(isset($user['invisible']) && $user['invisible'] == 1)
	{
		$invisiblecheck = "checked=\"checked\"";
	}
	else
	{
		$invisiblecheck = "";
	}

	if(isset($user['hideemail']) && $user['hideemail'] == 1)
	{
		$hideemailcheck = "checked=\"checked\"";
	}
	else
	{
		$hideemailcheck = "";
	}

	$no_email_subscribe_selected = $instant_email_subscribe_selected = $no_subscribe_selected = '';
	if(isset($user['subscriptionmethod']) && $user['subscriptionmethod'] == 1)
	{
		$no_email_subscribe_selected = "selected=\"selected\"";
	}
	else if(isset($user['subscriptionmethod']) && $user['subscriptionmethod'] == 2)
	{
		$instant_email_subscribe_selected = "selected=\"selected\"";
	}
	else
	{
		$no_subscribe_selected = "selected=\"selected\"";
	}

	if(isset($user['showsigs']) && $user['showsigs'] == 1)
	{
		$showsigscheck = "checked=\"checked\"";
	}
	else
	{
		$showsigscheck = "";
	}

	if(isset($user['showavatars']) && $user['showavatars'] == 1)
	{
		$showavatarscheck = "checked=\"checked\"";
	}
	else
	{
		$showavatarscheck = "";
	}

	if(isset($user['showquickreply']) && $user['showquickreply'] == 1)
	{
		$showquickreplycheck = "checked=\"checked\"";
	}
	else
	{
		$showquickreplycheck = "";
	}

	if(isset($user['receivepms']) && $user['receivepms'] == 1)
	{
		$receivepmscheck = "checked=\"checked\"";
	}
	else
	{
		$receivepmscheck = "";
	}

	if(isset($user['receivefrombuddy']) && $user['receivefrombuddy'] == 1)
	{
		$receivefrombuddycheck = "checked=\"checked\"";
	}
	else
	{
		$receivefrombuddycheck = "";
	}

	if(isset($user['pmnotice']) && $user['pmnotice'] >= 1)
	{
		$pmnoticecheck = " checked=\"checked\"";
	}
	else
	{
		$pmnoticecheck = "";
	}

	$dst_auto_selected = $dst_enabled_selected = $dst_disabled_selected = '';
	if(isset($user['dstcorrection']) && $user['dstcorrection'] == 2)
	{
		$dst_auto_selected = "selected=\"selected\"";
	}
	else if(isset($user['dstcorrection']) && $user['dstcorrection'] == 1)
	{
		$dst_enabled_selected = "selected=\"selected\"";
	}
	else
	{
		$dst_disabled_selected = "selected=\"selected\"";
	}

	if(isset($user['showcodebuttons']) && $user['showcodebuttons'] == 1)
	{
		$showcodebuttonscheck = "checked=\"checked\"";
	}
	else
	{
		$showcodebuttonscheck = "";
	}

	if(isset($user['showredirect']) && $user['showredirect'] != 0)
	{
		$showredirectcheck = "checked=\"checked\"";
	}
	else
	{
		$showredirectcheck = "";
	}

	if(isset($user['pmnotify']) && $user['pmnotify'] != 0)
	{
		$pmnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$pmnotifycheck = '';
	}


	if(!isset($user['threadmode']) || ($user['threadmode'] != "threaded" && $user['threadmode'] != "linear"))
	{
		$user['threadmode'] = ''; // Leave blank to show default
	}

	if(isset($user['classicpostbit']) && $user['classicpostbit'] != 0)
	{
		$classicpostbitcheck = "checked=\"checked\"";
	}
	else
	{
		$classicpostbitcheck = '';
	}


	$date_format_options = "<option value=\"0\">{$lang->use_default}</option>";
	foreach($date_formats as $key => $format)
	{
		if(isset($user['dateformat']) && $user['dateformat'] == $key)
		{
			$date_format_options .= "<option value=\"$key\" selected=\"selected\">".my_date($format, TIME_NOW, "", 0)."</option>";
		}
		else
		{
			$date_format_options .= "<option value=\"$key\">".my_date($format, TIME_NOW, "", 0)."</option>";
		}
	}

	$time_format_options = "<option value=\"0\">{$lang->use_default}</option>";
	foreach($time_formats as $key => $format)
	{
		if(isset($user['timeformat']) && $user['timeformat'] == $key)
		{
			$time_format_options .= "<option value=\"$key\" selected=\"selected\">".my_date($format, TIME_NOW, "", 0)."</option>";
		}
		else
		{
			$time_format_options .= "<option value=\"$key\">".my_date($format, TIME_NOW, "", 0)."</option>";
		}
	}

	$tzselect = build_timezone_select("timezoneoffset", $mybb->user['timezone'], true);

	$pms_from_buddys = '';
	if($mybb->settings['allowbuddyonly'] == 1)
	{
		eval("\$pms_from_buddys = \"".$templates->get("usercp_options_pms_from_buddys")."\";");
	}

	$threadview = array('linear' => '', 'threaded' => '');
	if(isset($user['threadmode']) && is_scalar($user['threadmode']))
	{
		$threadview[$user['threadmode']] = 'selected="selected"';
	}
	$daysprunesel = array(1 => '', 5 => '', 10 => '', 20 => '', 50 => '', 75 => '', 100 => '', 365 => '', 9999 => '');
	if(isset($user['daysprune']) && is_numeric($user['daysprune']))
	{
		$daysprunesel[$user['daysprune']] = 'selected="selected"';
	}
	if(!isset($user['style']))
	{
		$user['style'] = '';
	}
	$stylelist = build_theme_select("style", $user['style']);
	$tppselect = $pppselect = '';
	if($mybb->settings['usertppoptions'])
	{
		$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
		$tppoptions = '';
		if(is_array($explodedtpp))
		{
			foreach($explodedtpp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if(isset($user['tpp']) && $user['tpp'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$tppoptions .= "<option value=\"$val\" $selected>".$lang->sprintf($lang->tpp_option, $val)."</option>\n";
			}
		}
		eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
	}
	if($mybb->settings['userpppoptions'])
	{
		$explodedppp = explode(",", $mybb->settings['userpppoptions']);
		$pppoptions = '';
		if(is_array($explodedppp))
		{
			foreach($explodedppp as $key => $val)
			{
				$val = trim($val);
				$selected = "";
				if(isset($user['ppp']) && $user['ppp'] == $val)
				{
					$selected = "selected=\"selected\"";
				}
				$pppoptions .= "<option value=\"$val\" $selected>".$lang->sprintf($lang->ppp_option, $val)."</option>\n";
			}
		}
		eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
	}

	$plugins->run_hooks("usercp_options_end");

	eval("\$editprofile = \"".$templates->get("usercp_options")."\";");
	output_page($editprofile);
}

if($mybb->input['action'] == "do_email" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	$plugins->run_hooks("usercp_do_email_start");
	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"email" => $mybb->get_input('email'),
			"email2" => $mybb->get_input('email2')
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			if($mybb->user['usergroup'] != "5" && $mybb->usergroup['cancp'] != 1)
			{
				$uid = $mybb->user['uid'];
				$username = $mybb->user['username'];

				if($mybb->settings['regtype'] == "verify")
				{
					// Emails require verification
					$activationcode = random_str();
					$db->delete_query("awaitingactivation", "uid='".$mybb->user['uid']."'");

					$newactivation = array(
						"uid" => $mybb->user['uid'],
						"dateline" => TIME_NOW,
						"code" => $activationcode,
						"type" => "e",
						"oldgroup" => $mybb->user['usergroup'],
						"misc" => $db->escape_string($mybb->get_input('email'))
					);

					$db->insert_query("awaitingactivation", $newactivation);

					$mail_message = $lang->sprintf($lang->email_changeemail, $mybb->user['username'], $mybb->settings['bbname'], $mybb->user['email'], $mybb->get_input('email'), $mybb->settings['bburl'], $activationcode, $mybb->user['username'], $mybb->user['uid']);
				}
				else
				{
					// Email requires no activation
					$mail_message = $lang->sprintf($lang->email_changeemail_noactivation, $mybb->user['username'], $mybb->settings['bbname'], $mybb->user['email'], $mybb->get_input('email'), $mybb->settings['bburl']);
				}

				$lang->emailsubject_changeemail = $lang->sprintf($lang->emailsubject_changeemail, $mybb->settings['bbname']);
				my_mail($mybb->get_input('email'), $lang->emailsubject_changeemail, $mail_message);

				$plugins->run_hooks("usercp_do_email_verify");
				error($lang->redirect_changeemail_activation);
			}
			else
			{
				$userhandler->update_user();
				$plugins->run_hooks("usercp_do_email_changed");
				redirect("usercp.php", $lang->redirect_emailupdated);
			}
		}
	}
	if(count($errors) > 0)
	{
			$mybb->input['action'] = "email";
			$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == "email")
{
	// Coming back to this page after one or more errors were experienced, show fields the user previously entered (with the exception of the password)
	if($errors)
	{
		$email = htmlspecialchars_uni($mybb->get_input('email'));
		$email2 = htmlspecialchars_uni($mybb->get_input('email2'));
	}
	else
	{
		$email = $email2 = '';
	}

	$plugins->run_hooks("usercp_email");

	eval("\$changemail = \"".$templates->get("usercp_email")."\";");
	output_page($changemail);
}

if($mybb->input['action'] == "do_password" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$errors = array();

	$plugins->run_hooks("usercp_do_password_start");
	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('oldpassword')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"password" => $mybb->get_input('password'),
			"password2" => $mybb->get_input('password2')
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			my_setcookie("mybbuser", $mybb->user['uid']."_".$userhandler->data['loginkey']);
			$plugins->run_hooks("usercp_do_password_end");
			redirect("usercp.php", $lang->redirect_passwordupdated);
		}
	}
	if(count($errors) > 0)
	{
			$mybb->input['action'] = "password";
			$errors = inline_error($errors);
	}
}

if($mybb->input['action'] == "password")
{
	$plugins->run_hooks("usercp_password");

	eval("\$editpassword = \"".$templates->get("usercp_password")."\";");
	output_page($editpassword);
}

if($mybb->input['action'] == "do_changename" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_changename_start");
	if($mybb->usergroup['canchangename'] != 1)
	{
		error_no_permission();
	}

	if(validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password')) == false)
	{
		$errors[] = $lang->error_invalidpassword;
	}
	else
	{
		// Set up user handler.
		require_once "inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("update");

		$user = array(
			"uid" => $mybb->user['uid'],
			"username" => $mybb->get_input('username')
		);

		$userhandler->set_data($user);

		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
		}
		else
		{
			$userhandler->update_user();
			$plugins->run_hooks("usercp_do_changename_end");
			redirect("usercp.php", $lang->redirect_namechanged);

		}
	}
	if(count($errors) > 0)
	{
		$errors = inline_error($errors);
		$mybb->input['action'] = "changename";
	}
}

if($mybb->input['action'] == "changename")
{
	$plugins->run_hooks("usercp_changename_start");
	if($mybb->usergroup['canchangename'] != 1)
	{
		error_no_permission();
	}

	$plugins->run_hooks("usercp_changename_end");

	eval("\$changename = \"".$templates->get("usercp_changename")."\";");
	output_page($changename);
}

if($mybb->input['action'] == "do_subscriptions")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_subscriptions_start");

	if(!isset($mybb->input['check']) || !is_array($mybb->input['check']))
	{
		error($lang->no_subscriptions_selected);
	}

	// Clean input - only accept integers thanks!
	$mybb->input['check'] = array_map('intval', $mybb->get_input('check', 2));
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
		else if($mybb->get_input('do') == "instant_notification")
		{
			$new_notification = 1;
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
	$plugins->run_hooks("usercp_subscriptions_start");

	// Thread visiblity
	$visible = "AND t.visible != 0";
	if(is_moderator() == true)
	{
		$visible = '';
	}

	// Do Multi Pages
	$query = $db->query("
		SELECT COUNT(ts.tid) as threads
		FROM ".TABLE_PREFIX."threadsubscriptions ts
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = ts.tid)
		WHERE ts.uid = '".$mybb->user['uid']."' {$visible}
	");
	$threadcount = $db->fetch_field($query, "threads");

	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', 1);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
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
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount)
	{
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=subscriptions");
	$fpermissions = forum_permissions();
	$del_subscriptions = $subscriptions = array();

	// Fetch subscriptions
	$query = $db->query("
		SELECT s.*, t.*, t.username AS threadusername, u.username
		FROM ".TABLE_PREFIX."threadsubscriptions s
		LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
		WHERE s.uid='".$mybb->user['uid']."' {$visible}
		ORDER BY t.lastpost DESC
		LIMIT $start, $perpage
	");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];

		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $subscription['uid'] != $mybb->user['uid']))
		{
			// Hmm, you don't have permission to view this thread - unsubscribe!
			$del_subscriptions[] = $subscription['sid'];
		}
		else if($subscription['tid'])
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

		if($mybb->user['uid'] == 0)
		{
			// Build a forum cache.
			$query = $db->query("
				SELECT fid
				FROM ".TABLE_PREFIX."forums
				WHERE active != 0
				ORDER BY pid, disporder
			");

			$forumsread = unserialize($mybb->cookies['mybb']['forumread']);
		}
		else
		{
			// Build a forum cache.
			$query = $db->query("
				SELECT f.fid, fr.dateline AS lastread
				FROM ".TABLE_PREFIX."forums f
				LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
				WHERE f.active != 0
				ORDER BY pid, disporder
			");
		}
		while($forum = $db->fetch_array($query))
		{
			if($mybb->user['uid'] == 0)
			{
				if($forumsread[$forum['fid']])
				{
					$forum['lastread'] = $forumsread[$forum['fid']];
				}
			}
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

		$threads = '';

		// Now we can build our subscription list
		foreach($subscriptions as $thread)
		{
			$bgcolor = alt_trow();

			$folder = '';
			$prefix = '';
			$thread['threadprefix'] = '';

			// If this thread has a prefix, insert a space between prefix and subject
			if($thread['prefix'] != 0 && isset($threadprefixes[$thread['prefix']]))
			{
				$thread['threadprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
			}

			// Sanitize
			$thread['subject'] = $parser->parse_badwords($thread['subject']);
			$thread['subject'] = htmlspecialchars_uni($thread['subject']);

			// Build our links
			$thread['threadlink'] = get_thread_link($thread['tid']);
			$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

			// Fetch the thread icon if we have one
			if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
			{
				$icon = $icon_cache[$thread['icon']];
				$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
			}
			else
			{
				$icon = "&nbsp;";
			}

			// Determine the folder
			$folder = '';
			$folder_label = '';

			if(isset($thread['doticon']))
			{
				$folder = "dot_";
				$folder_label .= $lang->icon_dot;
			}

			$gotounread = '';
			$isnew = 0;
			$donenew = 0;
			$lastread = 0;

			if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
			{
				$forum_read = $readforums[$thread['fid']];

				$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				if($forum_read == 0 || $forum_read < $read_cutoff)
				{
					$forum_read = $read_cutoff;
				}
			}
			else
			{
				$forum_read = $forumsread[$thread['fid']];
			}

			$cutoff = 0;
			if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
			{
				$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
			}

			if($thread['lastpost'] > $cutoff)
			{
				if($thread['lastread'])
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
				$folder .= "new";
				$folder_label .= $lang->icon_new;
				$new_class = "subject_new";
				$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
				eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
				$unreadpost = 1;
			}
			else
			{
				$folder_label .= $lang->icon_no_new;
				$new_class = "subject_old";
			}

			if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
			{
				$folder .= "hot";
				$folder_label .= $lang->icon_hot;
			}

			if($thread['closed'] == 1)
			{
				$folder .= "lock";
				$folder_label .= $lang->icon_lock;
			}

			$folder .= "folder";

			if($thread['visible'] == 0)
			{
				$bgcolor = "trow_shaded";
			}

			// Build last post info
			$lastpostdate = my_date('relative', $thread['lastpost']);
			$lastposter = $thread['lastposter'];
			$lastposteruid = $thread['lastposteruid'];

			// Don't link to guest's profiles (they have no profile).
			if($lastposteruid == 0)
			{
				$lastposterlink = $lastposter;
			}
			else
			{
				$lastposterlink = build_profile_link($lastposter, $lastposteruid);
			}

			$thread['replies'] = my_number_format($thread['replies']);
			$thread['views'] = my_number_format($thread['views']);

			// What kind of notification type do we have here?
			switch($thread['notification'])
			{
				case "1": // Instant
					$notification_type = $lang->instant_notification;
					break;
				default: // No notification
					$notification_type = $lang->no_notification;
			}

			eval("\$threads .= \"".$templates->get("usercp_subscriptions_thread")."\";");
		}

		// Provide remove options
		eval("\$remove_options = \"".$templates->get("usercp_subscriptions_remove")."\";");
	}
	else
	{
		$remove_options = '';
		eval("\$threads = \"".$templates->get("usercp_subscriptions_none")."\";");
	}

	$plugins->run_hooks("usercp_subscriptions_end");

	eval("\$subscriptions = \"".$templates->get("usercp_subscriptions")."\";");
	output_page($subscriptions);
}

if($mybb->input['action'] == "forumsubscriptions")
{
	$plugins->run_hooks("usercp_forumsubscriptions_start");

	if($mybb->user['uid'] == 0)
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT fid
			FROM ".TABLE_PREFIX."forums
			WHERE active != 0
			ORDER BY pid, disporder
		");

		if(isset($mybb->cookies['mybb']['forumread']))
		{
			$forumsread = my_unserialize($mybb->cookies['mybb']['forumread']);
		}
	}
	else
	{
		// Build a forum cache.
		$query = $db->query("
			SELECT f.fid, fr.dateline AS lastread
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
			WHERE f.active != 0
			ORDER BY pid, disporder
		");
	}
	$readforums = array();
	while($forum = $db->fetch_array($query))
	{
		if($mybb->user['uid'] == 0)
		{
			if($forumsread[$forum['fid']])
			{
				$forum['lastread'] = $forumsread[$forum['fid']];
			}
		}
		$readforums[$forum['fid']] = $forum['lastread'];
	}

	$fpermissions = forum_permissions();
	require_once MYBB_ROOT."inc/functions_forumlist.php";

	$query = $db->query("
		SELECT fs.*, f.*, t.subject AS lastpostsubject, fr.dateline AS lastread
		FROM ".TABLE_PREFIX."forumsubscriptions fs
		LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid)
		LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
		WHERE f.type='f' AND fs.uid='".$mybb->user['uid']."'
		ORDER BY f.name ASC
	");

	$forums = '';
	while($forum = $db->fetch_array($query))
	{
		$forum_url = get_forum_link($forum['fid']);
		$forumpermissions = $fpermissions[$forum['fid']];

		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0)
		{
			continue;
		}

		$lightbulb = get_forum_lightbulb(array('open' => $forum['open'], 'lastread' => $forum['lastread']), array('lastpost' => $forum['lastpost']));
		$folder = $lightbulb['folder'];

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0)
		{
			$posts = '-';
			$threads = '-';
		}
		else
		{
			$posts = my_number_format($forum['posts']);
			$threads = my_number_format($forum['threads']);
		}

		if($forum['lastpost'] == 0 || $forum['lastposter'] == "")
		{
			$lastpost = "<div align=\"center\">{$lang->never}</div>";
		}
		// Hide last post
		elseif(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $forum['lastposteruid'] != $mybb->user['uid'])
		{
			$lastpost = "<div align=\"center\">{$lang->na}</div>";
		}
		else
		{
			$forum['lastpostsubject'] = $parser->parse_badwords($forum['lastpostsubject']);
			$lastpost_date = my_date('relative', $forum['lastpost']);
			$lastposttid = $forum['lastposttid'];
			$lastposter = $forum['lastposter'];
			$lastpost_profilelink = build_profile_link($lastposter, $forum['lastposteruid']);
			$full_lastpost_subject = $lastpost_subject = htmlspecialchars_uni($forum['lastpostsubject']);
			if(my_strlen($lastpost_subject) > 25)
			{
				$lastpost_subject = my_substr($lastpost_subject, 0, 25) . "...";
			}
			$lastpost_link = get_thread_link($forum['lastposttid'], 0, "lastpost");
			eval("\$lastpost = \"".$templates->get("forumbit_depth2_forum_lastpost")."\";");
		}

		if($mybb->settings['showdescriptions'] == 0)
		{
			$forum['description'] = "";
		}

		eval("\$forums .= \"".$templates->get("usercp_forumsubscriptions_forum")."\";");
	}

	if(!$forums)
	{
		eval("\$forums = \"".$templates->get("usercp_forumsubscriptions_none")."\";");
	}

	$plugins->run_hooks("usercp_forumsubscriptions_end");

	eval("\$forumsubscriptions = \"".$templates->get("usercp_forumsubscriptions")."\";");
	output_page($forumsubscriptions);
}

if($mybb->input['action'] == "do_editsig" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_editsig_start");

	// User currently has a suspended signature
	if($mybb->user['suspendsignature'] == 1 && $mybb->user['suspendsigtime'] > TIME_NOW)
	{
		error_no_permission();
	}

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

if($mybb->input['action'] == "editsig")
{
	$plugins->run_hooks("usercp_editsig_start");
	if(!empty($mybb->input['preview']) && empty($error))
	{
		$sig = $mybb->get_input('signature');
		$template = "usercp_editsig_preview";
	}
	elseif(empty($error))
	{
		$sig = $mybb->user['signature'];
		$template = "usercp_editsig_current";
	}
	else
	{
		$sig = $mybb->get_input('signature');
		$template = false;
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
	else if($mybb->usergroup['canusesig'] == 1 && $mybb->usergroup['canusesigxposts'] > 0 && $mybb->user['postnum'] < $mybb->usergroup['canusesigxposts'])
	{
		// Usergroup can use this facility, but only after x posts
		error($lang->sprintf($lang->sig_suspended_posts, $mybb->usergroup['canusesigxposts']));
	}

	$signature = '';
	if($sig && $template)
	{
		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode'],
			"me_username" => $mybb->user['username'],
			"filter_badwords" => 1
		);

		$sigpreview = $parser->parse_message($sig, $sig_parser);
		eval("\$signature = \"".$templates->get($template)."\";");
	}

	// User has a current signature, so let's display it (but show an error message)
	if($mybb->user['suspendsignature'] && $mybb->user['suspendsigtime'] > TIME_NOW)
	{
		$plugins->run_hooks("usercp_editsig_end");

		// User either doesn't have permission, or has their signature suspended
		eval("\$editsig = \"".$templates->get("usercp_editsig_suspended")."\";");
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
		$sig = htmlspecialchars_uni($sig);
		$lang->edit_sig_note2 = $lang->sprintf($lang->edit_sig_note2, $sigsmilies, $sigmycode, $sigimgcode, $sightml, $mybb->settings['siglength']);

		if($mybb->settings['bbcodeinserter'] != 0 || $mybb->user['showcodebuttons'] != 0)
		{
			$codebuttons = build_mycode_inserter("signature");
		}

		$plugins->run_hooks("usercp_editsig_end");

		eval("\$editsig = \"".$templates->get("usercp_editsig")."\";");
	}

	output_page($editsig);
}

if($mybb->input['action'] == "do_avatar" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_avatar_start");
	require_once MYBB_ROOT."inc/functions_upload.php";

	$avatar_error = "";

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
	elseif($_FILES['avatarupload']['name']) // upload avatar
	{
		if($mybb->usergroup['canuploadavatars'] == 0)
		{
			error_no_permission();
		}
		$avatar = upload_avatar();
		if($avatar['error'])
		{
			$avatar_error = $avatar['error'];
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
	else // remote avatar
	{
		$mybb->input['avatarurl'] = my_strtolower(trim($mybb->get_input('avatarurl')));
		if(validate_email_format($mybb->input['avatarurl']) != false)
		{
			// Gravatar
			// If user image does not exist, or is a higher rating, use the mystery man
			$email = md5($mybb->input['avatarurl']);

			$s = '';
			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
			}

			// Because Gravatars are square, hijack the width
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$maxheight = intval($maxwidth);

			// Rating?
			$types = array('g', 'pg', 'r', 'x');
			$rating = $mybb->settings['useravatarrating'];

			if(!in_array($rating, $types))
			{
				$rating = 'g';
			}

			$s = "?s={$maxheight}&r={$rating}&d=mm";

			$updated_avatar = array(
				"avatar" => "http://www.gravatar.com/avatar/{$email}{$s}.jpg",
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
				$avatar_error = $lang->error_invalidavatarurl;
			}
			else
			{
				$tmp_name = $mybb->settings['avataruploadpath']."/remote_".md5(random_str());
				$fp = @fopen($tmp_name, "wb");
				if(!$fp)
				{
					$avatar_error = $lang->error_invalidavatarurl;
				}
				else
				{
					fwrite($fp, $file);
					fclose($fp);
					list($width, $height, $type) = @getimagesize($tmp_name);
					@unlink($tmp_name);
					if(!$type)
					{
						$avatar_error = $lang->error_invalidavatarurl;
					}
				}
			}

			if(empty($avatar_error))
			{
				if($width && $height && $mybb->settings['maxavatardims'] != "")
				{
					list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
					if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
					{
						$lang->error_avatartoobig = $lang->sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
						$avatar_error = $lang->error_avatartoobig;
					}
				}
			}

			if(empty($avatar_error))
			{
				if($width > 0 && $height > 0)
				{
					$avatar_dimensions = intval($width)."|".intval($height);
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

	if(empty($avatar_error))
	{
		$plugins->run_hooks("usercp_do_avatar_end");
		redirect("usercp.php", $lang->redirect_avatarupdated);
	}
	else
	{
		$mybb->input['action'] = "avatar";
		$avatar_error = inline_error($avatar_error);
	}
}

if($mybb->input['action'] == "avatar")
{
	$plugins->run_hooks("usercp_avatar_start");

	$avatarmsg = $avatarurl = '';

	if($mybb->user['avatartype'] == "upload" || stristr($mybb->user['avatar'], $mybb->settings['avataruploadpath']))
	{
		$avatarmsg = "<br /><strong>".$lang->already_uploaded_avatar."</strong>";
	}
	elseif($mybb->user['avatartype'] == "remote" || my_strpos(my_strtolower($mybb->user['avatar']), "http://") !== false)
	{
		$avatarmsg = "<br /><strong>".$lang->using_remote_avatar."</strong>";
		$avatarurl = htmlspecialchars_uni($mybb->user['avatar']);
	}

	$useravatar = format_avatar(htmlspecialchars_uni($mybb->user['avatar']), $mybb->user['avatardimensions'], '100x100');
	eval("\$currentavatar = \"".$templates->get("usercp_avatar_current")."\";");

	if($mybb->settings['maxavatardims'] != "")
	{
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
		$lang->avatar_note .= "<br />".$lang->sprintf($lang->avatar_note_dimensions, $maxwidth, $maxheight);
	}

	if($mybb->settings['avatarsize'])
	{
		$maxsize = get_friendly_size($mybb->settings['avatarsize']*1024);
		$lang->avatar_note .= "<br />".$lang->sprintf($lang->avatar_note_size, $maxsize);
	}

	if($mybb->settings['avatarresizing'] == "auto")
	{
		$auto_resize = "<br /><span class=\"smalltext\">{$lang->avatar_auto_resize_note}</span>\n";
	}
	else if($mybb->settings['avatarresizing'] == "user")
	{
		$auto_resize = "<br /><span class=\"smalltext\"><input type=\"checkbox\" name=\"auto_resize\" value=\"1\" checked=\"checked\" id=\"auto_resize\" /> <label for=\"auto_resize\">{$lang->avatar_auto_resize_option}</label></span>";
	}

	$plugins->run_hooks("usercp_avatar_end");
	
	if(!isset($avatar_error))
	{
		$avatar_error = '';
	}

	eval("\$avatar = \"".$templates->get("usercp_avatar")."\";");
	output_page($avatar);
}

if($mybb->input['action'] == "do_editlists")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_editlists_start");

	$existing_users = array();
	$selected_list = array();
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

		// Fetch out new users
		if(count($users) > 0)
		{
			$query = $db->simple_select("users", "uid", "LOWER(username) IN ('".my_strtolower(implode("','", $users))."')");
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

				$existing_users[] = $user['uid'];
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
		}
	}

	// Removing a user from this list
	else if($mybb->get_input('delete', 1))
	{
		// Check if user exists on the list
		$key = array_search($mybb->get_input('delete', 1), $existing_users);
		if($key !== false)
		{
			unset($existing_users[$key]);
			$user = get_user($mybb->get_input('delete', 1));
			if($mybb->get_input('manage') == "ignored")
			{
				$message = $lang->removed_from_ignore_list;
			}
			else
			{
				$message = $lang->removed_from_buddy_list;
			}
			$message = $lang->sprintf($message, $user['username']);
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
		$new_list = my_substr($new_list, 0, my_strlen($new_list)-2);
	}

	// And update
	$user = array();
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

	$plugins->run_hooks("usercp_do_editlists_end");

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
			$message_js = "$.jGrowl('{$message}');";
		}

		if($error_message)
		{
			$message_js .= " $.jGrowl('{$error_message}');";
		}

		if($mybb->get_input('delete', 1))
		{
			header("Content-type: text/javascript");
			echo "$(\"#".$mybb->get_input('manage')."_".$mybb->get_input('delete', 1)."\").remove();\n";
			if($new_list == "")
			{
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"0\");\n";
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
				echo "\$(\"#".$mybb->get_input('manage')."_count\").html(\"".count(explode(",", $new_list))."\");\n";
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

if($mybb->input['action'] == "editlists")
{
	$plugins->run_hooks("usercp_editlists_start");

	$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

	// Fetch out buddies
	$buddy_count = 0;
	$buddy_list = '';
	if($mybb->user['buddylist'])
	{
		$type = "buddy";
		$query = $db->simple_select("users", "*", "uid IN ({$mybb->user['buddylist']})", array("order_by" => "username"));
		while($user = $db->fetch_array($query))
		{
			$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);
			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
			{
				$status = "online";
			}
			else
			{
				$status = "offline";
			}
			eval("\$buddy_list .= \"".$templates->get("usercp_editlists_user")."\";");
			++$buddy_count;
		}
	}

	$lang->current_buddies = $lang->sprintf($lang->current_buddies, $buddy_count);
	if(!$buddy_list)
	{
		$buddy_list = "<li>{$lang->buddy_list_empty}</li>";
	}

	// Fetch out ignore list users
	$ignore_count = 0;
	$ignore_list = '';
	if($mybb->user['ignorelist'])
	{
		$type = "ignored";
		$query = $db->simple_select("users", "*", "uid IN ({$mybb->user['ignorelist']})", array("order_by" => "username"));
		while($user = $db->fetch_array($query))
		{
			$profile_link = build_profile_link(format_name($user['username'], $user['usergroup'], $user['displaygroup']), $user['uid']);
			if($user['lastactive'] > $timecut && ($user['invisible'] == 0 || $mybb->usergroup['canviewwolinvis'] == 1) && $user['lastvisit'] != $user['lastactive'])
			{
				$status = "online";
			}
			else
			{
				$status = "offline";
			}
			eval("\$ignore_list .= \"".$templates->get("usercp_editlists_user")."\";");
			++$ignore_count;
		}
	}

	$lang->current_ignored_users = $lang->sprintf($lang->current_ignored_users, $ignore_count);
	if(!$ignore_list)
	{
		$ignore_list = "<li>{$lang->ignore_list_empty}</li>";
	}

	// If an AJAX request from buddy management, echo out whatever the new list is.
	if($mybb->request_method == "post" && $mybb->input['ajax'] == 1)
	{
		if($mybb->input['manage'] == "ignored")
		{
			echo $ignore_list;
			echo "<script type=\"text/javascript\"> $(\"#ignored_count\").html(\"{$ignore_count}\"); {$message_js}</script>";
		}
		else
		{
			echo $buddy_list;
			echo "<script type=\"text/javascript\"> $(\"#buddy_count\").html(\"{$buddy_count}\"); {$message_js}</script>";
		}
		exit;
	}

	$plugins->run_hooks("usercp_editlists_end");

	eval("\$listpage = \"".$templates->get("usercp_editlists")."\";");
	output_page($listpage);
}

if($mybb->input['action'] == "drafts")
{
	$plugins->run_hooks("usercp_drafts_start");

	$query = $db->simple_select("posts", "COUNT(pid) AS draftcount", "visible='-2' AND uid='{$mybb->user['uid']}'");
	$draftcount = $db->fetch_field($query, 'draftcount');

	$drafts = $disable_delete_drafts = '';
	$lang->drafts_count = $lang->sprintf($lang->drafts_count, my_number_format($draftcount));

	// Show a listing of all of the current 'draft' posts or threads the user has.
	if($draftcount)
	{
		$query = $db->query("
			SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid)
			WHERE p.uid = '{$mybb->user['uid']}' AND p.visible = '-2'
			ORDER BY p.dateline DESC
		");

		while($draft = $db->fetch_array($query))
		{
			$trow = alt_trow();
			if($draft['threadvisible'] == 1) // We're looking at a draft post
			{
				$detail = $lang->thread." <a href=\"".get_thread_link($draft['tid'])."\">".htmlspecialchars_uni($draft['threadsubject'])."</a>";
				$editurl = "newreply.php?action=editdraft&amp;pid={$draft['pid']}";
				$id = $draft['pid'];
				$type = "post";
			}
			elseif($draft['threadvisible'] == -2) // We're looking at a draft thread
			{
				$detail = $lang->forum." <a href=\"".get_forum_link($draft['fid'])."\">{$draft['forumname']}</a>";
				$editurl = "newthread.php?action=editdraft&amp;tid={$draft['tid']}";
				$id = $draft['tid'];
				$type = "thread";
			}

			$draft['subject'] = htmlspecialchars_uni($draft['subject']);
			$savedate = my_date('relative', $draft['dateline']);
			eval("\$drafts .= \"".$templates->get("usercp_drafts_draft")."\";");
		}
	}
	else
	{
		$disable_delete_drafts = 'disabled="disabled"';
		eval("\$drafts = \"".$templates->get("usercp_drafts_none")."\";");
	}

	$plugins->run_hooks("usercp_drafts_end");

	eval("\$draftlist = \"".$templates->get("usercp_drafts")."\";");
	output_page($draftlist);
}

if($mybb->input['action'] == "do_drafts" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_drafts_start");
	$mybb->input['deletedraft'] = $mybb->get_input('deletedraft', 2);
	if(empty($mybb->input['deletedraft']))
	{
		error($lang->no_drafts_selected);
	}
	$pidin = array();
	$tidin = array();
	foreach($mybb->input['deletedraft'] as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] = "'".intval($id)."'";
		}
		elseif($val == "thread")
		{
			$tidin[] = "'".intval($id)."'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->delete_query("threads", "tid IN ($tidin) AND visible='-2' AND uid='".$mybb->user['uid']."'");
		$tidinp = "OR tid IN ($tidin)";
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
	$plugins->run_hooks("usercp_do_drafts_end");
	redirect("usercp.php?action=drafts", $lang->selected_drafts_deleted);
}
if($mybb->input['action'] == "usergroups")
{
	$plugins->run_hooks("usercp_usergroups_start");
	$ingroups = ",".$mybb->user['usergroup'].",".$mybb->user['additionalgroups'].",".$mybb->user['displaygroup'].",";

	// Changing our display group
	if($mybb->get_input('displaygroup', 1))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		if(my_strpos($ingroups, ",".$mybb->input['displaygroup'].",") === false)
		{
			error($lang->not_member_of_group);
		}
		$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('displaygroup', 1)."'");
		$dispgroup = $db->fetch_array($query);
		if($dispgroup['candisplaygroup'] != 1)
		{
			error($lang->cannot_set_displaygroup);
		}
		$db->update_query("users", array('displaygroup' => $mybb->get_input('displaygroup', 1)), "uid='".$mybb->user['uid']."'");
		$cache->update_moderators();
		$plugins->run_hooks("usercp_usergroups_change_displaygroup");
		redirect("usercp.php?action=usergroups", $lang->display_group_changed);
		exit;
	}

	// Leaving a group
	if($mybb->get_input('leavegroup', 1))
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(my_strpos($ingroups, ",".$mybb->get_input('leavegroup', 1).",") === false)
		{
			error($lang->not_member_of_group);
		}
		if($mybb->user['usergroup'] == $mybb->get_input('leavegroup', 1))
		{
			error($lang->cannot_leave_primary_group);
		}
		$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('leavegroup', 1)."'");
		$usergroup = $db->fetch_array($query);
		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_leave_group);
		}
		leave_usergroup($mybb->user['uid'], $mybb->get_input('leavegroup', 1));
		$plugins->run_hooks("usercp_usergroups_leave_group");
		redirect("usercp.php?action=usergroups", $lang->left_group);
		exit;
	}

	$groupleaders = array();

	// List of usergroup leaders
	$query = $db->query("
		SELECT g.*, u.username, u.displaygroup, u.usergroup
		FROM ".TABLE_PREFIX."groupleaders g
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid)
		ORDER BY u.username ASC
	");
	while($leader = $db->fetch_array($query))
	{
		$groupleaders[$leader['gid']][$leader['uid']] = $leader;
	}

	$usergroups = $mybb->cache->read('usergroups');

	// Joining a group
	if($mybb->get_input('joingroup', 1))
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$query = $db->simple_select("usergroups", "*", "gid='".$mybb->get_input('joingroup', 1)."'");
		$usergroup = $db->fetch_array($query);

		if(($usergroup['type'] != 4 && $usergroup['type'] != 3) || !$usergroup['gid'])
		{
			error($lang->cannot_join_group);
		}

		if(my_strpos($ingroups, ",".$mybb->get_input('joingroup', 1).",") !== false)
		{
			error($lang->already_member_of_group);
		}

		$query = $db->simple_select("joinrequests", "*", "uid='".$mybb->user['uid']."' AND gid='".$mybb->get_input('joingroup', 1)."'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			error($lang->already_sent_join_request);
		}
		if($mybb->get_input('do') == "joingroup" && $usergroup['type'] == 4)
		{
			$now = TIME_NOW;
			$joinrequest = array(
				"uid" => $mybb->user['uid'],
				"gid" => $mybb->get_input('joingroup', 1),
				"reason" => $db->escape_string($mybb->get_input('reason')),
				"dateline" => TIME_NOW
			);

			$db->insert_query("joinrequests", $joinrequest);

			foreach($groupleaders as $key => $groupleader)
			{
				foreach($groupleader as $leader)
				{
					$leader_user = get_user($leader['uid']);
					$subject = $lang->sprintf($lang->emailsubject_newjoinrequest, $mybb->settings['bbname']);
					$message = $lang->sprintf($lang->email_groupleader_joinrequest, $leader_user['username'], $mybb->user['username'], $usergroups[$leader['gid']]['title'], $mybb->settings['bbname'], $mybb->get_input('reason'), $mybb->settings['bburl'], $leader['gid']);
					my_mail($leader_user['email'], $subject, $message);
				}
			}

			$plugins->run_hooks("usercp_usergroups_join_group_request");
			redirect("usercp.php?action=usergroups", $lang->group_join_requestsent);
			exit;
		}
		elseif($usergroup['type'] == 4)
		{
			$joingroup = $mybb->get_input('joingroup', 1);
			eval("\$joinpage = \"".$templates->get("usercp_usergroups_joingroup")."\";");
			output_page($joinpage);
			exit;
		}
		else
		{
			join_usergroup($mybb->user['uid'], $mybb->get_input('joingroup', 1));
			$plugins->run_hooks("usercp_usergroups_join_group");
			redirect("usercp.php?action=usergroups", $lang->joined_group);
		}
	}
	// Show listing of various group related things

	// List of groups this user is a leader of
	$groupsledlist = '';

	switch($db->type)
	{
		case "pgsql":
		case "sqlite":
			$query = $db->query("
				SELECT g.title, g.gid, g.type, COUNT(DISTINCT u.uid) AS users, COUNT(DISTINCT j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers
				FROM ".TABLE_PREFIX."groupleaders l
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON(((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
				LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid AND j.uid != 0)
				WHERE l.uid='".$mybb->user['uid']."'
				GROUP BY g.gid, g.title, g.type, l.canmanagerequests, l.canmanagemembers
			");
			break;
		default:
			$query = $db->query("
				SELECT g.title, g.gid, g.type, COUNT(DISTINCT u.uid) AS users, COUNT(DISTINCT j.rid) AS joinrequests, l.canmanagerequests, l.canmanagemembers
				FROM ".TABLE_PREFIX."groupleaders l
				LEFT JOIN ".TABLE_PREFIX."usergroups g ON(g.gid=l.gid)
				LEFT JOIN ".TABLE_PREFIX."users u ON(((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
				LEFT JOIN ".TABLE_PREFIX."joinrequests j ON(j.gid=g.gid AND j.uid != 0)
				WHERE l.uid='".$mybb->user['uid']."'
				GROUP BY l.gid
			");
	}

	while($usergroup = $db->fetch_array($query))
	{
		$memberlistlink = $moderaterequestslink = '';
		$memberlistlink = " [<a href=\"managegroup.php?gid=".$usergroup['gid']."\">".$lang->view_members."</a>]";
		if($usergroup['type'] != 4)
		{
			$usergroup['joinrequests'] = '--';
		}
		if($usergroup['joinrequests'] > 0 && $usergroup['canmanagerequests'] == 1)
		{
			$moderaterequestslink = " [<a href=\"managegroup.php?action=joinrequests&amp;gid={$usergroup['gid']}\">{$lang->view_requests}</a>]";
		}
		$groupleader[$usergroup['gid']] = 1;
		$trow = alt_trow();
		eval("\$groupsledlist .= \"".$templates->get("usercp_usergroups_leader_usergroup")."\";");
	}
	$leadinggroups = '';
	if($groupsledlist)
	{
		eval("\$leadinggroups = \"".$templates->get("usercp_usergroups_leader")."\";");
	}

	// Fetch the list of groups the member is in
	// Do the primary group first
	$usergroup = $usergroups[$mybb->user['usergroup']];
	$leavelink = "<div style=\"text-align:center;\"><span class=\"smalltext\">{$lang->usergroup_leave_primary}</span></div>";
	$trow = alt_trow();
	if($usergroup['candisplaygroup'] == 1 && $usergroup['gid'] == $mybb->user['displaygroup'])
	{
		$displaycode = " ({$lang->display_group})";
	}
	elseif($usergroup['candisplaygroup'] == 1)
	{
		$displaycode = " (<a href=\"usercp.php?action=usergroups&amp;displaygroup={$usergroup['gid']}&amp;my_post_key={$mybb->post_code}\">{$lang->set_as_display_group}</a>)";
	}
	else
	{
		$displaycode = '';
	}

	eval("\$memberoflist = \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
	$showmemberof = false;
	if($mybb->user['additionalgroups'])
	{
		$query = $db->simple_select("usergroups", "*", "gid IN (".$mybb->user['additionalgroups'].") AND gid !='".$mybb->user['usergroup']."'", array('order_by' => 'title'));
		while($usergroup = $db->fetch_array($query))
		{
			$showmemberof = true;

			if(isset($groupleader[$usergroup['gid']]))
			{
				$leavelink = "<div style=\"text-align: center;\"><span class=\"smalltext\">$lang->usergroup_leave_leader</span></div>";
			}
			elseif($usergroup['type'] != 4 && $usergroup['type'] != 3)
			{
				$leavelink = "<div style=\"text-align: center;\"><span class=\"smalltext\">{$lang->usergroup_cannot_leave}</span></div>";
			}
			else
			{
				$leavelink = "<div style=\"text-align: center;\"><a href=\"usercp.php?action=usergroups&amp;leavegroup=".$usergroup['gid']."&amp;my_post_key={$mybb->post_code}\">".$lang->usergroup_leave."</a></div>";
			}
			if($usergroup['description'])
			{
				$description = "<br /><span class=\"smalltext\">".$usergroup['description']."</span>";
			}
			else
			{
				$description = '';
			}
			if(!$usergroup['usertitle'])
			{
				// fetch title here
			}
			$trow = alt_trow();
			if($usergroup['candisplaygroup'] == 1 && $usergroup['gid'] == $mybb->user['displaygroup'])
			{
				$displaycode = " ({$lang->display_group})";
			}
			elseif($usergroup['candisplaygroup'] == 1)
			{
				$displaycode = "(<a href=\"usercp.php?action=usergroups&amp;displaygroup={$usergroup['gid']}&amp;my_post_key={$mybb->post_code}\">{$lang->set_as_display_group}</a>)";
			}
			else
			{
				$displaycode = '';
			}
			eval("\$memberoflist .= \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
		}
	}
	eval("\$membergroups = \"".$templates->get("usercp_usergroups_memberof")."\";");

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
		$existinggroups .= ",".$mybb->user['additionalgroups'];
	}

	$joinablegroups = $joinablegrouplist = '';
	$query = $db->simple_select("usergroups", "*", "(type='3' OR type='4') AND gid NOT IN ($existinggroups)", array('order_by' => 'title'));
	while($usergroup = $db->fetch_array($query))
	{
		$trow = alt_trow();
		if($usergroup['description'])
		{
			$description = "<br /><span class=\"smallfont\">".$usergroup['description']."</span>";
		}
		else
		{
			$description = '';
		}

		 // Moderating join requests?
		if($usergroup['type'] == 4)
		{
			$conditions = $lang->usergroup_joins_moderated;
		}
		else
		{
			$conditions = $lang->usergroup_joins_anyone;
		}

		if(isset($appliedjoin[$usergroup['gid']]))
		{
			$applydate = my_date('relative', $appliedjoin[$usergroup['gid']]);
			$joinlink = $lang->sprintf($lang->join_group_applied, $applydate);
		}
		else
		{
			$joinlink = "<a href=\"usercp.php?action=usergroups&amp;joingroup={$usergroup['gid']}&amp;my_post_key={$mybb->post_code}\">{$lang->join_group}</a>";
		}

		$usergroupleaders = '';
		if(!empty($groupleaders[$usergroup['gid']]))
		{
			$comma = '';
			$usergroupleaders = '';
			foreach($groupleaders[$usergroup['gid']] as $leader)
			{
				$leader['username'] = format_name($leader['username'], $leader['usergroup'], $leader['displaygroup']);
				$usergroupleaders .= $comma.build_profile_link($leader['username'], $leader['uid']);
				$comma = $lang->comma;
			}
			$usergroupleaders = $lang->usergroup_leaders." ".$usergroupleaders;
		}

		if(my_strpos($usergroupleaders, $mybb->user['username']) === false)
		{
			// User is already a leader of the group, so don't show as a "Join Group"
			eval("\$joinablegrouplist .= \"".$templates->get("usercp_usergroups_joinable_usergroup")."\";");
		}
	}
	if($joinablegrouplist)
	{
		eval("\$joinablegroups = \"".$templates->get("usercp_usergroups_joinable")."\";");
	}

	$plugins->run_hooks("usercp_usergroups_end");

	eval("\$groupmemberships = \"".$templates->get("usercp_usergroups")."\";");
	output_page($groupmemberships);
}
if($mybb->input['action'] == "attachments")
{
	$plugins->run_hooks("usercp_attachments_start");
	require_once MYBB_ROOT."inc/functions_upload.php";

	$attachments = '';

	// Pagination
	if(!$mybb->settings['threadsperpage'])
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', 1);

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
	$lower = $start+1;

	$query = $db->query("
		SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE a.uid='".$mybb->user['uid']."'
		ORDER BY p.dateline DESC LIMIT {$start}, {$perpage}
	");

	$bandwidth = $totaldownloads = 0;
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
			$attachment['subject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['subject']));
			$attachment['postlink'] = get_post_link($attachment['pid'], $attachment['tid']);
			$attachment['threadlink'] = get_thread_link($attachment['tid']);
			$attachment['threadsubject'] = htmlspecialchars_uni($parser->parse_badwords($attachment['threadsubject']));

			$size = get_friendly_size($attachment['filesize']);
			$icon = get_attachment_icon(get_extension($attachment['filename']));
			$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

			$sizedownloads = $lang->sprintf($lang->attachment_size_downloads, $size, $attachment['downloads']);
			$attachdate = my_date('relative', $attachment['dateline']);
			$altbg = alt_trow();

			eval("\$attachments .= \"".$templates->get("usercp_attachments_attachment")."\";");

			// Add to bandwidth total
			$bandwidth += ($attachment['filesize'] * $attachment['downloads']);
			$totaldownloads += $attachment['downloads'];
		}
		else
		{
			// This little thing delets attachments without a thread/post
			remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
		}
	}

	$query = $db->simple_select("attachments", "SUM(filesize) AS ausage, COUNT(aid) AS acount", "uid='".$mybb->user['uid']."'");
	$usage = $db->fetch_array($query);
	$totalusage = $usage['ausage'];
	$totalattachments = $usage['acount'];
	$friendlyusage = get_friendly_size($totalusage);
	if($mybb->usergroup['attachquota'])
	{
		$percent = round(($totalusage/($mybb->usergroup['attachquota']*1024))*100)."%";
		$attachquota = get_friendly_size($mybb->usergroup['attachquota']*1024);
		$usagenote = $lang->sprintf($lang->attachments_usage_quota, $friendlyusage, $attachquota, $percent, $totalattachments);
	}
	else
	{
		$percent = $lang->unlimited;
		$attachquota = $lang->unlimited;
		$usagenote = $lang->sprintf($lang->attachments_usage, $friendlyusage, $totalattachments);
	}

	$multipage = multipage($totalattachments, $perpage, $page, "usercp.php?action=attachments");
	$bandwidth = get_friendly_size($bandwidth);

	if(!$attachments)
	{
		eval("\$attachments = \"".$templates->get("usercp_attachments_none")."\";");
		$usagenote = '';
	}

	$plugins->run_hooks("usercp_attachments_end");

	eval("\$manageattachments = \"".$templates->get("usercp_attachments")."\";");
	output_page($manageattachments);
}

if($mybb->input['action'] == "do_attachments" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("usercp_do_attachments_start");
	require_once MYBB_ROOT."inc/functions_upload.php";
	if(!isset($mybb->input['attachments']) || !is_array($mybb->input['attachments']))
	{
		error($lang->no_attachments_selected);
	}
	$aids = implode(',', array_map('intval', $mybb->input['attachments']));
	$query = $db->simple_select("attachments", "*", "aid IN ($aids) AND uid='".$mybb->user['uid']."'");
	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], '', $attachment['aid']);
	}
	$plugins->run_hooks("usercp_do_attachments_end");
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

	$plugins->run_hooks("usercp_do_notepad_start");
	$db->update_query("users", array('notepad' => $db->escape_string($mybb->get_input('notepad'))), "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_notepad_end");
	redirect("usercp.php", $lang->redirect_notepadupdated);
}

if(!$mybb->input['action'])
{
	// Get posts per day
	$daysreg = (TIME_NOW - $mybb->user['regdate']) / (24*3600);

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
		$percent = $mybb->user['postnum']*100/$posts;
		$percent = round($percent, 2);
	}

	$colspan = 2;
	$lang->posts_day = $lang->sprintf($lang->posts_day, my_number_format($perday), $percent);
	$regdate = my_date('relative', $mybb->user['regdate']);

	$useravatar = format_avatar(htmlspecialchars_uni($mybb->user['avatar']), $mybb->user['avatardimensions'], '100x100');
	eval("\$avatar = \"".$templates->get("usercp_currentavatar")."\";");

	$usergroup = $groupscache[$mybb->user['usergroup']]['title'];
	if($mybb->user['usergroup'] == 5 && $mybb->settings['regtype'] != "admin")
	{
		$usergroup .= "<br />(<a href=\"member.php?action=resendactivation\">$lang->resend_activation</a>)";
	}
	// Make reputations row
	$reputations = '';
	if($mybb->usergroup['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
	{
		$reputation_link = get_reputation($mybb->user['reputation']);
		eval("\$reputation = \"".$templates->get("usercp_reputation")."\";");
	}

	$latest_warnings = '';
	if($mybb->settings['enablewarningsystem'] != 0 && $mybb->settings['canviewownwarning'] != 0)
	{
		$warning_level = round($mybb->user['warningpoints']/$mybb->settings['maxwarningpoints']*100);
		if($warning_level > 100)
		{
			$warning_level = 100;
		}

		if($mybb->user['warningpoints'] > $mybb->settings['maxwarningpoints'])
		{
			$mybb->user['warningpoints'] = $mybb->settings['maxwarningpoints'];
		}

		if($warning_level > 0)
		{
			expire_warnings();

			$lang->current_warning_level = $lang->sprintf($lang->current_warning_level, $warning_level, $mybb->user['warningpoints'], $mybb->settings['maxwarningpoints']);
			$warnings = '';
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
				$post_link = "";
				if($warning['post_subject'])
				{
					$warning['post_subject'] = $parser->parse_badwords($warning['post_subject']);
					$warning['post_subject'] = htmlspecialchars_uni($warning['post_subject']);
					$post_link = "<br /><small>{$lang->warning_for_post} <a href=\"".get_post_link($warning['pid'])."\">{$warning['post_subject']}</a></small>";
				}
				$issuedby = build_profile_link($warning['username'], $warning['issuedby']);
				$date_issued = my_date('relative', $warning['dateline']);
				if($warning['type_title'])
				{
					$warning_type = $warning['type_title'];
				}
				else
				{
					$warning_type = $warning['title'];
				}
				$warning_type = htmlspecialchars_uni($warning_type);
				if($warning['points'] > 0)
				{
					$warning['points'] = "+{$warning['points']}";
				}
				$points = $lang->sprintf($lang->warning_points, $warning['points']);

				// Figure out expiration time
				if($warning['daterevoked'])
				{
					$expires = $lang->warning_revoked;
				}
				elseif($warning['expired'])
				{
					$expires = $lang->already_expired;
				}
				elseif($warning['expires'] == 0)
				{
					$expires = $lang->never;
				}
				else
				{
					$expires = my_date('relative', $warning['expires']);
				}

				$alt_bg = alt_trow();
				eval("\$warnings .= \"".$templates->get("usercp_warnings_warning")."\";");
			}
			if($warnings)
			{
				eval("\$latest_warnings = \"".$templates->get("usercp_warnings")."\";");
			}
		}
	}

	// Format username
	$username = format_name($mybb->user['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
	$username = build_profile_link($username, $mybb->user['uid']);

	// Format post numbers
	$mybb->user['posts'] = my_number_format($mybb->user['postnum']);

	// Build referral link
	if($mybb->settings['usereferrals'] == 1)
	{
		$referral_link = $lang->sprintf($lang->referral_link, $settings['bburl'], $mybb->user['uid']);
		eval("\$referral_info = \"".$templates->get("usercp_referrals")."\";");
	}

	// User Notepad
	$plugins->run_hooks("usercp_notepad_start");
	$mybb->user['notepad'] = htmlspecialchars_uni($mybb->user['notepad']);
	eval("\$user_notepad = \"".$templates->get("usercp_notepad")."\";");
	$plugins->run_hooks("usercp_notepad_end");

	// Thread Subscriptions with New Posts
	$latest_subscribed = '';
	$query = $db->simple_select("threadsubscriptions", "sid", "uid = '".$mybb->user['uid']."'", array("limit" => 1));
	if($db->num_rows($query))
	{
		$visible = "AND t.visible != 0";
		if(is_moderator() == true)
		{
			$visible = '';
		}

		$query = $db->query("
			SELECT s.*, t.*, t.username AS threadusername, u.username
			FROM ".TABLE_PREFIX."threadsubscriptions s
			LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
			WHERE s.uid='".$mybb->user['uid']."' {$visible}
			ORDER BY t.lastpost DESC
			LIMIT 0, 10
		");

		$fpermissions = forum_permissions();
		while($subscription = $db->fetch_array($query))
		{
			$forumpermissions = $fpermissions[$subscription['fid']];
			if($forumpermissions['canview'] != 0 && $forumpermissions['canviewthreads'] != 0 && ($forumpermissions['canonlyviewownthreads'] == 0 || $subscription['uid'] == $mybb->user['uid']))
			{
				$subscriptions[$subscription['tid']] = $subscription;
			}
		}

		if(is_array($subscriptions))
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

				foreach($subscriptions as $thread)
				{
					$folder = '';
					$folder_label = '';
					$gotounread = '';

					if($thread['tid'])
					{
						$bgcolor = alt_trow();
						$thread['subject'] = $parser->parse_badwords($thread['subject']);
						$thread['subject'] = htmlspecialchars_uni($thread['subject']);
						$thread['threadlink'] = get_thread_link($thread['tid']);
						$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

						// If this thread has a prefix...
						if($thread['prefix'] != 0 && isset($threadprefixes[$thread['prefix']]))
						{
							$thread['displayprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
						}
						else
						{
							$thread['displayprefix'] = '';
						}

						// Icons
						if($thread['icon'] > 0 && isset($icon_cache[$thread['icon']]))
						{
							$icon = $icon_cache[$thread['icon']];
							$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
						}
						else
						{
							$icon = "&nbsp;";
						}

						if($thread['doticon'])
						{
							$folder = "dot_";
							$folder_label .= $lang->icon_dot;
						}

						// Check to see which icon we display
						if($thread['lastread'] && $thread['lastread'] < $thread['lastpost'])
						{
							$folder .= "new";
							$folder_label .= $lang->icon_new;
							$new_class = "subject_new";
							$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
							eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
						}
						else
						{
							$folder_label .= $lang->icon_no_new;
							$new_class = "subject_old";
						}

						$folder .= "folder";

						if($thread['visible'] == 0)
						{
							$bgcolor = "trow_shaded";
						}

						$lastpostdate = my_date('relative', $thread['lastpost']);
						$lastposter = $thread['lastposter'];
						$lastposteruid = $thread['lastposteruid'];

						if($lastposteruid == 0)
						{
							$lastposterlink = $lastposter;
						}
						else
						{
							$lastposterlink = build_profile_link($lastposter, $lastposteruid);
						}

						$thread['replies'] = my_number_format($thread['replies']);
						$thread['views'] = my_number_format($thread['views']);
						$thread['author'] = build_profile_link($thread['username'], $thread['uid']);

						eval("\$latest_subscribed_threads .= \"".$templates->get("usercp_latest_subscribed_threads")."\";");
					}
				}
				eval("\$latest_subscribed = \"".$templates->get("usercp_latest_subscribed")."\";");
			}
		}
	}

	// User's Latest Threads

	// Get unviewable forums
	$f_perm_sql = '';
	$unviewable_forums = get_unviewable_forums();
	if($unviewable_forums)
	{
		$f_perm_sql = "AND t.fid NOT IN (".$unviewable_forums.")";
	}

	$visible = " AND t.visible != 0";
	if(is_moderator() == true)
	{
		$visible = '';
	}

	$query = $db->query("
		SELECT t.*, t.username AS threadusername, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
		WHERE t.uid='".$mybb->user['uid']."' AND t.firstpost != 0 AND t.visible != '-2' {$visible} {$f_perm_sql}
		ORDER BY t.lastpost DESC
		LIMIT 0, 5
	");

	// Figure out whether we can view these threads...
	$threadcache = array();
	$fpermissions = forum_permissions();
	while($thread = $db->fetch_array($query))
	{
		// Moderated, and not moderator?
		if($thread['visible'] == 0 && is_moderator($thread['fid']) === false)
		{
			continue;
		}

		$forumpermissions = $fpermissions[$thread['fid']];
		if($forumpermissions['canview'] != 0 || $forumpermissions['canviewthreads'] != 0)
		{
			$threadcache[$thread['tid']] = $thread;
		}
	}

	if(!empty($threadcache))
	{
		$tids = implode(",", array_keys($threadcache));

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
		$latest_threads_threads = '';
		foreach($threadcache as $thread)
		{
			if($thread['tid'])
			{
				$bgcolor = alt_trow();
				$folder = '';
				$folder_label = '';
				$prefix = '';
				$gotounread = '';
				$isnew = 0;
				$donenew = 0;
				$lastread = 0;

				// If this thread has a prefix...
				if($thread['prefix'] != 0)
				{
					if(isset($threadprefixes[$thread['prefix']]))
					{
						$thread['displayprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
					}
				}
				else
				{
					$thread['displayprefix'] = '';
				}

				$thread['subject'] = $parser->parse_badwords($thread['subject']);
				$thread['subject'] = htmlspecialchars_uni($thread['subject']);
				$thread['threadlink'] = get_thread_link($thread['tid']);
				$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

				if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
				{
					$icon = $icon_cache[$thread['icon']];
					$icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" />";
				}
				else
				{
					$icon = "&nbsp;";
				}

				if($mybb->settings['threadreadcut'] > 0)
				{
					$forum_read = $readforums[$thread['fid']];

					$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
					if($forum_read == 0 || $forum_read < $read_cutoff)
					{
						$forum_read = $read_cutoff;
					}
				}

				if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
				{
					$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				}

				$cutoff = 0;
				if($thread['lastpost'] > $cutoff)
				{
					if($thread['lastread'])
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

				// Folder Icons
				if($thread['doticon'])
				{
					$folder = "dot_";
					$folder_label .= $lang->icon_dot;
				}

				if($thread['lastpost'] > $lastread && $lastread)
				{
					$folder .= "new";
					$folder_label .= $lang->icon_new;
					$new_class = "subject_new";
					$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
					eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
					$unreadpost = 1;
				}
				else
				{
					$folder_label .= $lang->icon_no_new;
					$new_class = "subject_old";
				}

				if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
				{
					$folder .= "hot";
					$folder_label .= $lang->icon_hot;
				}

				// Is our thread visible?
				if($thread['visible'] == 0)
				{
					$bgcolor = 'trow_shaded';
				}

				if($thread['closed'] == 1)
				{
					$folder .= "lock";
					$folder_label .= $lang->icon_lock;
				}

				$folder .= "folder";

				$lastpostdate = my_date('relative', $thread['lastpost']);
				$lastposter = $thread['lastposter'];
				$lastposteruid = $thread['lastposteruid'];

				if($lastposteruid == 0)
				{
					$lastposterlink = $lastposter;
				}
				else
				{
					$lastposterlink = build_profile_link($lastposter, $lastposteruid);
				}

				$thread['replies'] = my_number_format($thread['replies']);
				$thread['views'] = my_number_format($thread['views']);
				$thread['author'] = build_profile_link($thread['username'], $thread['uid']);

				eval("\$latest_threads_threads .= \"".$templates->get("usercp_latest_threads_threads")."\";");
			}
		}

		eval("\$latest_threads = \"".$templates->get("usercp_latest_threads")."\";");
	}

	$plugins->run_hooks("usercp_end");

	eval("\$usercp = \"".$templates->get("usercp")."\";");
	output_page($usercp);
}
?>