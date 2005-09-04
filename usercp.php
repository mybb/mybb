<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */
 define("KILL_GLOBALS", 1);
 
$templatelist = "usercp,usercp_home,usercp_nav,usercp_profile,error_nopermission,buddy_online,buddy_offline,usercp_changename,usercp_nav_changename";
$templatelist .= "usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups";
$templatelist .= "usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc";
require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_user.php";

// Load global language phrases
$lang->load("usercp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == "no")
{
	nopermission();
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can";
	$db->query("UPDATE ".TABLE_PREFIX."users SET pmfolders='".$mybb->user['pmfolders']."' WHERE uid='".$mybb->user['uid']."'");
}

usercp_menu();

if($mybb->input['action'] == "do_editsig")
{
	$imagecheck = postify($mybb->input['signature'], $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
	if(($mybb->settings['sigimgcode'] == "no" && substr_count($imagecheck, "<img") > 0) || ($mybb->settings['sigimgcode'] == "yes" && substr_count($imagecheck, "<img") > $mybb->settings['maxsigimages']))
	{
		if($mybb->settings['sigimgcode'] == "yes")
		{
			$imgsallowed = $mybb->settings['maxsigimages'];
		}
		else
		{
			$imgsallowed = 0;
		}
		$lang->too_many_sig_images2 = sprintf($lang->too_many_sig_images2, $imgsallowed);
		eval("\$maximageserror = \"".$templates->get("error_maxsigimages")."\";");
		$mybb->input['action'] = "editsig";
	}
	if($mybb->input['preview'])
	{
		$mybb->input['action'] = "editsig";
	}
}

// Make navigation
addnav($lang->nav_usercp, "usercp.php");

switch($mybb->input['action'])
{
	case "profile":
		addnav($lang->nav_profile);
		break;
	case "options":
		addnav($lang->nav_options);
		break;
	case "email":
		addnav($lang->nav_email);
		break;
	case "password":
		addnav($lang->nav_password);
		break;
	case "changename":
		addnav($lang->nav_changename);
		break;
	case "favorites":
		addnav($lang->nav_favorites);
		break;
	case "subscriptions":
		addnav($lang->nav_subthreads);
		break;
	case "forumsubscriptions":
		addnav($lang->nav_forumsubscriptions);
		break;
	case "editsig":
		addnav($lang->nav_editsig);
		break;
	case "avatar":
		addnav($lang->nav_avatar);
		break;
	case "notepad":
		addnav($lang->nav_notepad);
		break;
	case "editlists":
		addnav($lang->nav_editlists);
		break;
	case "drafts":
		addnav($lang->nav_drafts);
		break;
	case "usergroups":
		addnav($lang->nav_usergroups);
		break;
}
	
if($mybb->input['action'] == "profile")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->user['uid']."'");
	$user = $db->fetch_array($query);

	$plugins->run_hooks("usercp_profile_start");
	$bday = explode("-", $mybb->user['birthday']);
	for($i=1;$i<=31;$i++)
	{
		if($bday[0] == $i)
		{
			$bdaydaysel .= "<option value=\"$i\" selected>$i</option>\n";
		}
		else
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$bdaymonthsel[$bday[1]] = "selected";

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
		$icq = stripslashes($user['icq']);
	}
	else
	{
		$icq = "";
	}
	if($mybb->settings['allowaway'] != "no")
	{
		if($mybb->user['away'] == "yes")
		{
			$awaydate = mydate($mybb->settings['dateformat'], $mybb->user['awaydate']);
			$awaycheck['yes'] = "checked";
			$awaynotice = sprintf($lang->away_notice_away, $awaydate);
		}
		else
		{
			$awaynotice = $lang->away_notice;
			$awaycheck['no'] = "checked";
		}
		$returndate = explode("-", $mybb->user['returndate']);
		for($i=1;$i<=31;$i++)
		{
			if($returndate[0] == $i)
			{
				$returndatesel .= "<option value=\"$i\" selected>$i</option>\n";
			}
			else
			{
				$returndatesel .= "<option value=\"$i\">$i</option>\n";
			}
		}
		$returndatemonthsel[$returndate[1]] = "selected";

		eval("\$awaysection = \"".$templates->get("usercp_profile_away")."\";");
	}
	// Custom profile fields baby!
	$altbg = "trow1";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		$options = $thing[1];
		$field = "fid$profilefield[fid]";
		if($type == "multiselect")
		{
			$useropts = explode("\n", $mybb->user[$field]);
			while(list($key, $val) = each($useropts))
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions)) {
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					if($val == $seloptions[$val])
					{
						$sel = "selected";
					}
					else
					{
						$sel = "";
					}
					$select .= "<option value=\"$val\" $sel>$val</option>\n";
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
				while(list($key, $val) = each($expoptions))
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					if($val == $mybb->user[$field])
					{
						$sel = "selected";
					}
					else
					{
						$sel = "";
					}
					$select .= "<option value=\"$val\" $sel>$val</option>";
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
				while(list($key, $val) = each($expoptions))
				{
					if($val == $mybb->user[$field])
					{
						$checked = "checked";
					}
					else
					{
						$checked = "";
					}
					$code .= "<input type=\"radio\" name=\"$field\" value=\"$val\" $checked /> $val<br>";
				}
			}
		}
		elseif($type == "checkbox")
		{
			$useropts = explode("\n", $mybb->user[$field]);
			while(list($key, $val) = each($useropts))
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions)) {
				while(list($key, $val) = each($expoptions))
				{
					if($val == $seloptions[$val])
					{
						$checked = "checked";
					}
					else
					{
						$checked = "";
					}
					$code .= "<input type=\"checkbox\" name=\"".$field."[]\" value=\"$val\" $checked /> $val<br>";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($mybb->user[$field]);
			$code = "<textarea name=\"$field\" rows=\"6\" cols=\"30\" style=\"width: 95%\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($mybb->user[$field]);
			$code = "<input type=\"text\" name=\"$field\" size=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\" value=\"$value\" />";
		}
		if($profilefield['required'] == "yes")
		{
			eval("\$requiredfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		else
		{
			eval("\$customfields .= \"".$templates->get("usercp_profile_customfield")."\";");
		}
		if($altbg == "trow1")
		{
			$altbg = "trow2";
		}
		else
		{
			$altbg = "trow1";
		}
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

	if($mybb->usergroup['cancustomtitle'] == "yes")
	{
		if($mybb->usergroup['usertitle'] == "")
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles WHERE posts <='".$mybb->user['postnum']."' ORDER BY posts DESC LIMIT 1");
			$utitle = $db->fetch_array($query);
			$defaulttitle = $utitle['title'];
		}
		else
		{
			$defaulttitle = $mybb->usergroup['usertitle'];
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->user['uid']."'");
		$user = $db->fetch_array($query);
		$mybb->user['usertitle'] = $user['usertitle'];
		eval("\$customtitle = \"".$templates->get("usercp_profile_customtitle")."\";");
	}
	else
	{
		$customtitle = "";
	}
	eval("\$editprofile = \"".$templates->get("usercp_profile")."\";");
	$plugins->run_hooks("usercp_profile_end");
	outputpage($editprofile);
}
elseif($mybb->input['action'] == "do_profile")
{
	$plugins->run_hooks("usercp_do_profile_start");
	if($mybb->input['website'] && !stristr($mybb->input['website'], "http://"))
	{
		$mybb->input['website'] = "";
	}
	if($mybb->input['website'] == "http://" || $mybb->input['website'] == "none")
	{
		$mybb->input['website'] = "";
	}
	if(strlen($mybb->input['website']) > 75)
	{
		error($lang->error_website_length);
	}
	if($mybb->input['bday1'] == "" || $mybb->input['bday2'] == "")
	{
		$bday = "";
	}
	else
	{
		if(($mybb->input['bday3']>=(date("Y")-100)) && ($mybb->input['bday3']<date("Y")))
		{
			$bday = intval($mybb->input['bday1'])."-".intval($mybb->input['bday2'])."-".intval($mybb->input['bday3']);
		}
		else
		{
			$bday = intval($mybb->input['bday1'])."-".intval($mybb->input['bday2'])."-";
		}
	}
	$titleup == "";
	$usertitle = "";
	if($mybb->usergroup['cancustomtitle'] == "yes")
	{
		if(strlen($mybb->input['usertitle']) <= $mybb->settings['customtitlemaxlength'])
		{
			$usertitle = $mybb->input['usertitle'];
		}
		elseif($mybb->input['usertitle'] == $mybb->user['usertitle'])
		{
			$usertitle = $mybb->input['usertitle'];
		}
		else
		{
			error($lang->error_customtitle_length);
		}
	}
	if($mybb->input['away'] == "yes" && $mybb->settings['allowaway'] != "no")
	{
		$awaydate = time();
		if($mybb->input['awayday'] && $mybb->input['awaymonth'] && $mybb->input['awayyear'])
		{
			$returntimestamp = gmmktime(0, 0, 0, $mybb->input['awaymonth'], $mybb->input['awayday'], $mybb->input['awayyear']);
			$awaytimestamp = gmmktime(0, 0, 0, mydate('n', $awaydate), mydate('j', $awaydate), mydate('Y', $awaydate));
			if ($returntimestamp < $awaytimestamp) {
				error($lang->error_usercp_return_date_past);
			}
			$returndate = $mybb->input['awayday']."-".$mybb->input['awaymonth']."-".$mybb->input['awayyear'];
		}
		else
		{
			$returndate = "";
		}
		$away = "yes";
	}
	else
	{
		$away = "no";
		$awaydate = "";
		$returndate = "";
		$mybb->input['awayreason'] = "";
	}
	// Custom profile fields baby!
	$upquery = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		$field = "fid$profilefield[fid]";
		if($profilefield['editable'] == "yes")
		{
			if(!$mybb->input[$field] && $profilefield['required'] == "yes")
			{
				error($lang->error_missingrequiredfield);
			}
			$options = "";
			if($type == "multiselect" || $type == "checkbox")
			{
				while(list($key, $val) = each($mybb->input[$field]))
				{
					if($options)
					{
						$options .= "\n";
					}
					$options .= $val;
				}
			}
			else
			{
				$options = $mybb->input[$field];
			}
			$profilefields[$field] = addslashes($options);
		}
		else
		{
			$profilefields[$field] = addslashes($mybb->user[$field]);
		}
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields WHERE ufid='".$mybb->user['uid']."'");
	$fields = $db->fetch_array($query);
	if(!$fields['ufid'])
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."userfields (ufid) VALUES ('".$mybb->user['uid']."')");
	}
	$db->update_query(TABLE_PREFIX."userfields", $profilefields, "ufid='".$mybb->user['uid']."'");
	
	$newprofile = array(
		"website" => addslashes(htmlspecialchars($mybb->input['website'])),
		"icq" => intval($mybb->input['icq']),
		"aim" => addslashes(htmlspecialchars($mybb->input['aim'])),
		"yahoo" => addslashes(htmlspecialchars($mybb->input['yahoo'])),
		"msn" => addslashes(htmlspecialchars($mybb->input['msn'])),
		"birthday" => $bday,
		"away" => $away,
		"awaydate" => $awaydate,
		"returndate" => $returndate,
		"awayreason" => addslashes(htmlspecialchars($mybb->input['awayreason']))
		);

	if($usertitle)
	{
		$newprofile['usertitle'] = addslashes(htmlspecialchars_uni($usertitle));
	}
	$plugins->run_hooks("usercp_do_profile_process");

	$db->update_query(TABLE_PREFIX."users", $newprofile, "uid='".$mybb->user['uid']."'");
	setcookie("mybb[uid]", $mybb->user['uid']);
	$plugins->run_hooks("usercp_do_profile_end");
	redirect("usercp.php", $lang->redirect_profileupdated);
}
elseif($mybb->input['action'] == "options")
{
	$plugins->run_hooks("usercp_options_start");

	$user = $mybb->user;
	$languages = $lang->getLanguages();
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

	// Lets work out which options the user has selected and check the boxes
	if($mybb->user['allownotices'] == "yes")
	{
		$allownoticescheck = "checked=\"checked\"";
	}
	else
	{
		$allownoticescheck = "";
	}

	if($mybb->user['invisible'] == "yes")
	{
		$invisiblecheck = "checked=\"checked\"";
	}
	else
	{
		$invisiblecheck = "";
	}

	if($mybb->user['hideemail'] == "yes")
	{
		$hideemailcheck = "checked=\"checked\"";
	}
	else
	{
		$hideemailcheck = "";
	}

	if($mybb->user['emailnotify'] == "yes")
	{
		$emailnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$emailnotifycheck = "";
	}

	if($mybb->user['showsigs'] == "yes")
	{
		$showsigscheck = "checked=\"checked\"";;
	}
	else
	{
		$showsigscheck = "";
	}

	if($mybb->user['showavatars'] == "yes")
	{
		$showavatarscheck = "checked=\"checked\"";
	}
	else
	{
		$showavatarscheck = "";
	}

	if($mybb->user['showquickreply'] == "yes")
	{
		$showquickreplycheck = "checked=\"checked\"";
	}
	else
	{
		$showquickreplycheck = "";
	}

	if($mybb->user['remember'] == "yes")
	{
		$remembercheck = "checked=\"checked\"";
	}
	else
	{
		$remembercheck = "";
	}

	if($mybb->user['receivepms'] == "yes")
	{
		$receivepmscheck = "checked=\"checked\"";
	}
	else
	{
		$receivepmscheck = "";
	}

	if($mybb->user['pmpopup'] == "yes")
	{
		$pmpopupcheck = "checked=\"checked\"";
	}
	else
	{
		$pmpopupcheck = "";
	}

	if($mybb->user['dst'] == "yes")
	{
		$dstcheck = "checked=\"checked\"";
		$mybb->user['timezone']--;
	}
	else
	{
		$dstcheck = "";
	}
	if($mybb->user['showcodebuttons'] == 1)
	{
		$showcodebuttonscheck = "checked=\"checked\"";
	}
	else
	{
		$showcodebuttonscheck = "";
	}
	
	if($mybb->user['pmnotify'] != "no")
	{
		$pmnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$pmnotifycheck = "";
	}

	$dateselect[$mybb->user['dateformat']] = "selected";
	$timeselect[$mybb->user['timeformat']] = "selected";
	$mybb->user['timezone'] = $mybb->user['timezone']*10;
	$mybb->user['timezone'] = str_replace("-", "n", $mybb->user['timezone']);
	$timezoneselect[$mybb->user['timezone']] = "selected";
	// We need to revisit this to see if it can be optomitized and made smaller
	// maybe in version 5
	$tempzone = $mybb->user['timezone'];
	$mybb->user['timezone'] = "";
	$timenow = mydate($mybb->settings['timeformat'], time(), "-");
	for($i=-12;$i<=12;$i++) {
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
	$timein[n35] = mydate($mybb->settings['timeformat'], time(), -3.5);
	$timein[35] = mydate($mybb->settings['timeformat'], time(), 3.5);
	$timein[45] = mydate($mybb->settings['timeformat'], time(), 4.5);
	$timein[55] = mydate($mybb->settings['timeformat'], time(), 5.5);
	$timein[95] = mydate($mybb->settings['timeformat'], time(), 9.5);
	$timein[105] = mydate($mybb->settings['timeformat'], time(), 10.5);
	$mybb->user['timezone'] = $tempzone;
	eval("\$tzselect = \"".$templates->get("usercp_options_timezoneselect")."\";");

	$threadview[$mybb->user['threadmode']] = "selected";
	$daysprunesel[$mybb->user['daysprune']] = "selected";
	$stylelist = themeselect("style", $mybb->user['style']);
	if($mybb->settings['usertppoptions'])
	{
		$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
		if(is_array($explodedtpp))
		{
			while(list($key, $val) = each($explodedtpp))
			{
				$val = trim($val);
				if($mybb->user['tpp'] == $val)
				{
					$selected = "selected";
				}
				else
				{
					$selected = "";
				}
				$tppoptions .= "<option value=\"$val\" $selected>".sprintf($lang->tpp_option, $val)."</option>\n";
			}
		}
		eval("\$tppselect = \"".$templates->get("usercp_options_tppselect")."\";");
	}
	if($mybb->settings['userpppoptions'])
	{
		$explodedppp = explode(",", $mybb->settings['userpppoptions']);
		if(is_array($explodedppp))
		{
			while(list($key, $val) = each($explodedppp))
			{
				$val = trim($val);
				if($mybb->user['ppp'] == $val)
				{
					$selected = "selected";
				}
				else
				{
					$selected = "";
				}
				$pppoptions .= "<option value=\"$val\" $selected>".sprintf($lang->ppp_option, $val)."</option>\n";
			}
		}
		eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
	}
	eval("\$editprofile = \"".$templates->get("usercp_options")."\";");
	$plugins->run_hooks("usercp_options_end");
	outputpage($editprofile);
}
elseif($mybb->input['action'] == "do_options")
{
	$plugins->run_hooks("usercp_do_options_start");

	if($mybb->input['showcodebuttons'] != 1)
	{
		$mybb->input['showcodebuttons'] = 0;
	}
	
	if($mybb->input['allownotices'] != "yes")
	{
		$mybb->input['allownotices'] = "no";
	}

	if($mybb->input['hideemail'] != "yes")
	{
		$mybb->input['hideemail'] = "no";
	}

	if($mybb->input['emailnotify'] != "yes")
	{
		$mybb->input['emailnotify'] = "no";
	}

	if($mybb->input['receivepms'] != "yes")
	{
		$mybb->input['receivepms'] = "no";
	}
	
	if($mybb->input['pmpopup'] != "yes")
	{
		$mybb->input['pmpopup'] = "no";
	}

	if($mybb->input['pmnotify'] != "yes")
	{
		$mybb->input['pmnotify'] = "no";
	}

	if($mybb->input['invisible'] != "yes")
	{
		$mybb->input['invisible'] = "no";
	}

	if($mybb->input['showsigs'] != "yes")
	{
		$mybb->input['showsigs'] = "no";
	}

	if($mybb->input['showavatars'] != "yes")
	{
		$mybb->input['showavatars'] = "no";
	}

	if($mybb->input['showquickreply'] != "yes")
	{
		$mybb->input['showquickreply'] = "no";
	}

	if($mybb->input['remember'] != "yes")
	{
		$mybb->input['remember'] = "no";
	}

	if($mybb->input['dst'] != "yes")
	{
		$mybb->input['dst'] = "no";
	}

	if($mybb->settings['usertppoptions'])
	{
		$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
		if(is_array($explodedtpp))
		{
			@asort($explodedtpp);
			$biggest = $explodedtpp[count($explodedtpp)-1];
			if($mybb->input['tpp'] > $biggest)
			{
				$mybb->input['tpp'] = $biggest;
			}
		}
	}
	if($mybb->settings['userpppoptions'])
	{
		$explodedppp = explode(",", $mybb->settings['userpppoptions']);
		if(is_array($explodedppp))
		{
			@asort($explodedppp);
			$biggest = $explodedppp[count($explodedppp)-1];
			if($mybb->input['ppp'] > $biggest)
			{
				$mybb->input['ppp'] = $biggest;
			}
		}
	}

	$languages = $lang->getLanguages();
	if(!$languages[$mybb->input['language']])
	{
		$mybb->input['language'] = "";
	}	
	$updatedoptions = array(
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"invisible" => $mybb->input['invisible'],
		"style" => intval($mybb->input['style']),
		"dateformat" => intval($mybb->input['dateformat']),
		"timeformat" => intval($mybb->input['timeformat']),
		"timezone" => addslashes($mybb->input['timezoneoffset']),
		"dst" => $mybb->input['dst'],
		"threadmode" => $mybb->input['threadmode'],
		"showsigs" => $mybb->input['showsigs'],
		"showavatars" => $mybb->input['showavatars'],
		"showquickreply" => $mybb->input['showquickreply'],
		"remember" => $mybb->input['remember'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"daysprune" => intval($mybb->input['daysprune']),
		"language" => $mybb->input['language'],
		"showcodebuttons" => $mybb->input['showcodebuttons'],
		"pmnotify" => $mybb->input['pmnotify'],
		);

	if($mybb->settings['usertppoptions'])
	{
		$updatedoptions['tpp'] = intval($mybb->input['tpp']);
	}
	if($mybb->settings['userpppoptions'])
	{
		$updatedoptions['ppp'] = intval($mybb->input['ppp']);
	}
	$plugins->run_hooks("usercp_do_options_process");
	$db->update_query(TABLE_PREFIX."users", $updatedoptions, "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_options_end");
	redirect("usercp.php", $lang->redirect_optionsupdated);
}
elseif($mybb->input['action'] == "email")
{
	$plugins->run_hooks("usercp_email_start");
	eval("\$changemail = \"".$templates->get("usercp_email")."\";");
	$plugins->run_hooks("usercp_email_end");
	outputpage($changemail);
}
elseif($mybb->input['action'] == "do_email")
{
	$plugins->run_hooks("usercp_do_email_start");
	$user = validate_password_from_uid($mybb->user['uid'], $mybb->input['password']);
	if(!$user['uid'])
	{
		error($lang->error_invalidpassword);
	}
	if($mybb->input['email'] != $mybb->input['email2'])
	{
		error($lang->error_emailmismatch);
	}

	//Email Banning Code
	if($mybb->settings['emailkeep'] != "yes")
	{
		$bannedemails = explode(" ", $mybb->settings['emailban']);
		if(is_array($bannedemails)) {
			while(list($key, $bannedemail) = each($bannedemails))
			{
				$bannedemail = trim($bannedemail);
				if($bannedemail != "")
				{
					if(strstr($mybb->input['email'], $bannedemail) != "")
					{
						error($lang->error_bannedemail);
					}
				}
			}
		}
	}
	if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $mybb->input['email']))
	{
		error($lang->error_invalidemail);
	}
	if(function_exists("emailChanged"))
	{
		emailChanged($mybb->user['uid'], $mybb->input['email']);
	}

	if(!$mybb->user['usergroup'] == "5")
	{
		$activationcode = random_str();
		$now = time();
		$db->query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$mybb->user['uid']."'");
		$newactivation = array(
			"aid" => "NULL",
			"uid" => $mybb->user['uid'],
			"dateline" => time(),
			"code" => $activationcode,
			"type" => "e",
			"oldgroup" => $mybb->user['usergroup'],
			"misc" => addslashes($mybb->input['email'])
			);
		$db->insert_query(TABLE_PREFIX."awaitingactivation", $newactivation);

		$username = $mybb->user['username'];
		$uid = $mybb->user['uid'];
		$lang->emailsubject_changeemail = sprintf($lang->emailsubject_changeemail, $mybb->settings['bbname']);
		$lang->email_changeemail = sprintf($lang->email_changeemail, $mybb->user['username'], $mybb->settings['bbname'], $mybb->user['email'], $mybb->input['email'], $mybb->settings['bburl'], $activationcode, $mybb->user['username'], $mybb->user['uid']);
		mymail($email, $lang->emailsubject_changeemail, $lang->email_changeemail);
		$plugins->run_hooks("usercp_do_email_verify");
		error($lang->redirect_changeemail_activation);
	}
	else
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET email='".addslashes($mybb->input['email'])."' WHERE uid='".$mybb->user['uid']."'");
		$plugins->run_hooks("usercp_do_email_changed");
		redirect("usercp.php", $lang->redirect_emailupdated);
	}
}
elseif($mybb->input['action'] == "password")
{
	$plugins->run_hooks("usercp_password_start");
	eval("\$editpassword = \"".$templates->get("usercp_password")."\";");
	$plugins->run_hooks("usercp_password_end");
	outputpage($editpassword);
}
elseif($mybb->input['action'] == "do_password")
{
	$plugins->run_hooks("usercp_do_password_start");
	if(validate_password_from_uid($mybb->user['uid'], $mybb->input['oldpassword']) == false)
	{
		error($lang->error_invalidpassword);
	}
	if($mybb->input['password'] == "")
	{
		error($lang->error_invalidnewpassword);
	}
	if($mybb->input['password'] != $mybb->input['password2'])
	{
		error($lang->error_passwordmismatch);
	}
	$plugins->run_hooks("usercp_do_password_process");
	$logindetails = update_password($mybb->user['uid'], md5($mybb->input['password']), $mybb->user['salt']);

	mysetcookie("mybbuser", $mybb->user['uid']."_".$logindetails['loginkey']);
	$plugins->run_hooks("usercp_do_password_end");
	redirect("usercp.php", $lang->redirect_passwordupdated);
}
elseif($mybb->input['action'] == "changename")
{
	$plugins->run_hooks("usercp_changename_start");
	if($mybb->usergroup['canchangename'] != "yes")
	{
		nopermission();
	}
	eval("\$changename = \"".$templates->get("usercp_changename")."\";");
	$plugins->run_hooks("usercp_changename_end");
	outputpage($changename);
}
elseif($mybb->input['action'] == "do_changename")
{
	$plugins->run_hooks("usercp_do_changename_start");
	if($mybb->usergroup['canchangename'] != "yes")
	{
		nopermission();
	}
	if(!trim($mybb->input['username']) || eregi("<|>|&", $mybb->input['username']))
	{
		error($lang->error_bannedusername);
	}
	$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE username LIKE '".addslashes($mybb->input['username'])."'");
	
	if($db->fetch_array($query))
	{
		error($lang->error_usernametaken);
	}
	$plugins->run_hooks("usercp_do_changename_process");
	$db->query("UPDATE ".TABLE_PREFIX."users SET username='".addslashes($mybb->input['username'])."' WHERE uid='".$mybb->user['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."forums SET lastposter='".addslashes($mybb->input['username'])."' WHERE lastposter='".addslashes($mybb->user['username'])."'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET lastposter='".addslashes($mybb->input['username'])."' WHERE lastposter='".addslashes($mybb->user['username'])."'");
	$plugins->run_hooks("usercp_do_changename_end");
	redirect("usercp.php", $lang->redirect_namechanged);
}
elseif($mybb->input['action'] == "favorites")
{
	$plugins->run_hooks("usercp_favorites_start");
	// Do Multi Pages
	$query = $db->query("SELECT COUNT(f.tid) AS threads FROM ".TABLE_PREFIX."favorites f WHERE f.type='f' AND f.uid='".$mybb->user['uid']."'");
	$threadcount = $db->result($query, 0);
	
	$perpage = $mybb->settings['threadsperpage'];
	$page = intval($mybb->input['page']);
	if($page)
	{
		$start = ($page-1) *$perpage;
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
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=favorites");
	$fpermissions = forum_permissions();
	$query = $db->query("SELECT f.*, t.*, i.name AS iconname, i.path AS iconpath, t.username AS threadusername, u.username FROM ".TABLE_PREFIX."favorites f LEFT JOIN ".TABLE_PREFIX."threads t ON (f.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE f.type='f' AND f.uid='".$mybb->user['uid']."' ORDER BY t.lastpost DESC");
	while($favorite = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$favorite['fid']];
		if($forumpermissions['canview'] != "no" || $forumpermissions['canviewthreads'] != "no")
		{
			$lastpostdate = mydate($mybb->settings['dateformat'], $favorite['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $favorite['lastpost']);
			$lastposter = $favorite['lastposter'];
			$favorite['author'] = $favorite['uid'];
			if(!$favorite['username'])
			{
				$favorite['username'] = $favorite['threadusername'];
			}
			$favorite['subject'] = htmlspecialchars_uni(dobadwords($favorite['subject']));
			if($favorite['iconpath'])
			{
				$icon = "<img src=\"$favorite[iconpath]\" alt=\"$favorite[iconname]\">";
			}
			else
			{
				$icon = "&nbsp;";
			}
			if($mybb->user['lastvisit'] == "0")
			{
				$folder = "new";
			}
			if($favorite['lastpost'] > $mybb->user['lastvisit'])
			{
				$threadread = mygetarraycookie("threadread", $favorite['tid']);
				if($threadread < $favorite['lastpost'])
				{
					$folder = "new";
				}
			}
			if($favorite['replies'] >= $mybb->settings['hottopic'])
			{
				$folder .= "hot";
			}
			if($favorite['closed'] == "yes")
			{
				$folder .= "lock";
			}
			$folder .= "folder";
			$favorite['replies'] = mynumberformat($favorite['replies']);
			$favorite['views'] = mynumberformat($favorite['views']);
			eval("\$threads .= \"".$templates->get("usercp_favorites_thread")."\";");
			$folder = "";
		}
	}
	if(!$threads)
	{
		eval("\$threads = \"".$templates->get("usercp_favorites_none")."\";");
	}
	eval("\$favorites = \"".$templates->get("usercp_favorites")."\";");
	$plugins->run_hooks("usercp_favorites_end");
	outputpage($favorites);
}
elseif($mybb->input['action'] == "subscriptions")
{
	$plugins->run_hooks("usercp_subscriptions_start");
	// Do Multi Pages
	$query = $db->query("SELECT COUNT(s.tid) AS threads FROM ".TABLE_PREFIX."favorites s WHERE s.type='s' AND s.uid='".$mybb->user['uid']."'");
	$threadcount = $db->result($query, 0);
	
	$perpage = $mybb->settings['threadsperpage'];
	$page = intval($mybb->input['page']);
	if($page)
	{
		$start = ($page-1) *$perpage;
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
	$query = $db->query("SELECT s.*, t.*, i.name AS iconname, i.path AS iconpath, t.username AS threadusername, u.username FROM ".TABLE_PREFIX."favorites s LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE s.type='s' AND s.uid='".$mybb->user['uid']."' ORDER BY t.lastpost DESC LIMIT $start, $perpage");
	while($subscription = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$subscription['fid']];
		if($forumpermissions['canview'] != "no" || $forumpermissions['canviewthreads'] != "no")
		{
			$lastpostdate = mydate($mybb->settings['dateformat'], $subscription['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $subscription['lastpost']);
			$lastposter = $subscription['lastposter'];
			$subscription['author'] = $subscription['uid'];
			if(!$subscription['username'])
			{
				$subscription['username'] = $subscription['threadusername'];
			}
			$subscription['subject'] = htmlspecialchars_uni(dobadwords($subscription['subject']));
			if($subscription['iconpath'])
			{
				$icon = "<img src=\"$subscription[iconpath]\" alt=\"$subscription[iconname]\">";
			}
			else
			{
				$icon = "&nbsp;";
			}
			if($mybb->user['lastvisit'] == "0")
			{
				$folder = "new";
			}
			if($subscription['lastpost'] > $mybb->user['lastvisit'])
			{
				$threadread = mygetarraycookie("threadread", $subscription['tid']);
				if($threadread < $subcription['lastpost'])
				{
					$folder = "new";
				}
			}
			if($subscription['replies'] >= $mybb->settings['hottopic'])
			{
				$folder .= "hot";
			}
			if($subscription['closed'] == "yes")
			{
				$folder .= "lock";
			}
			$folder .= "folder";
			$subscription['replies'] = mynumberformat($subscription['replies']);
			$subscription['views'] = mynumberformat($subscription['views']);
			eval("\$threads .= \"".$templates->get("usercp_subscriptions_thread")."\";");
			$folder = "";
		}
	}
	if(!$threads)
	{
		eval("\$threads = \"".$templates->get("usercp_subscriptions_none")."\";");
	}
	eval("\$subscriptions = \"".$templates->get("usercp_subscriptions")."\";");
	$plugins->run_hooks("usercp_subscriptions_end");
	outputpage($subscriptions);
}
elseif($mybb->input['action'] == "forumsubscriptions")
{
	$plugins->run_hooks("usercp_forumsubscriptions_start");
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE gid='".$mybb->user['usergroup']."'");
	while($permissions = $db->fetch_array($query))
	{
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	$fpermissions = forum_permissions();
	$query = $db->query("SELECT fs.*, f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forumsubscriptions fs LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE f.type='f' AND fs.uid='".$mybb->user['uid']."' ORDER BY f.name ASC");
	while($forum = $db->fetch_array($query))
	{
		$forumpermissions = $fpermissions[$forum['fid']];
		if($forumpermissions['canview'] != "no")
		{
			if(($forum['lastpost'] > $mybb->user['lastvisit'] || $mybbforumread[$forum['fid']] > $mybb->user['lastvisit']) && $forum['lastpost'] != 0)
			{
				$folder = "on";
			}
			else
			{
				$folder = "off";
			}
			if($forum['lastpost'] == 0 || $forum['lastposter'] == "")
			{
				$lastpost = "<font><center>$lang->never</center></font>";
			}
			else
			{
				$lastpostdate = mydate($mybb->settings['dateformat'], $forum['lastpost']);
				$lastposttime = mydate($mybb->settings['timeformat'], $forum['lastpost']);
				$lastposttid = $forum['lastposttid'];
				$lastposter = $forum['lastposter'];
				$lastpostsubject = stripslashes($forum['lastpostsubject']);
				if(strlen($lastpostsubject) > 25)
				{
					$lastpostsubject = substr($lastpostsubject, 0, 25) . "...";
				}
				eval("\$lastpost = \"".$templates->get("forumbit_depth1_forum_lastpost")."\";");
			}
		}
		$posts = mynumberformat($forum['posts']);
		$threads = mynumberformat($forum['threads']);
		if($mybb->settings['showdescriptions'] == "no")
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
	outputpage($forumsubscriptions);
}
elseif($mybb->input['action'] == "editsig")
{
	$plugins->run_hooks("usercp_editsig_start");
	if($mybb->input['preview'])
	{
		$sig = $mybb->input['signature'];
		$template = "usercp_editsig_preview";
	}
	else
	{
		$sig = $mybb->user['signature'];
		$template = "usercp_editsig_current";
	}
	if($sig)
	{
		$sigpreview = postify($sig, $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		eval("\$signature = \"".$templates->get($template)."\";");
	}
	if($mybb->settings['sigsmilies'] == "yes")
	{
		$sigsmilies = $lang->on;
	}
	else
	{
		$sigsmilies = $lang->off;
	}
	if($mybb->settings['sigmycode'] == "yes")
	{
		$sigmycode = $lang->on;
	}
	else
	{
		$sigmycode = $lang->off;
	}
	if($mybb->settings['sightml'] == "yes")
	{
		$sightml = $lang->on;
	}
	else
	{
		$sightml = $lang->off;
	}
	if($mybb->settings['sigimgcode'] == "yes")
	{
		$sigimgcode = $lang->on;
	}
	else
	{
		$sigimgcode = $lang->off;
	}
	$lang->edit_sig_note2 = sprintf($lang->edit_sig_note2, $sigsmilies, $sigmycode, $sigimgcode, $sightml, $mybb->settings['siglength']);
	eval("\$editsig = \"".$templates->get("usercp_editsig")."\";");
	$plugins->run_hooks("usercp_endsig_end");
	outputpage($editsig);
}
elseif($mybb->input['action'] == "do_editsig")
{
	$plugins->run_hooks("usercp_do_editsig_start");
	if($mybb->settings['siglength'] != 0 && strlen($mybb->input['signature']) > $mybb->settings['siglength'])
	{
		error($lang->sig_too_long);
	}
	if($mybb->input['updateposts'] == "enable")
	{
		$db->query("UPDATE ".TABLE_PREFIX."posts SET includesig='yes' WHERE uid='".$mybb->user['uid']."'");
	}
	elseif($mybb->input['updateposts'] == "disable")
	{
		$db->query("UPDATE ".TABLE_PREFIX."posts SET includesig='no' WHERE uid='".$mybb->user['uid']."'");
	}
	$newsignature = array(
		"signature" => addslashes($mybb->input['signature'])
		);
	$plugins->run_hooks("usercp_do_editsig_process");
	$db->update_query(TABLE_PREFIX."users", $newsignature, "uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_editsig_end");
	redirect("usercp.php?action=editsig", $lang->redirect_sigupdated);

}
elseif($mybb->input['action'] == "avatar")
{
	$plugins->run_hooks("usercp_avatar_start");
	// Get a listing of available galleries
	$gallerylist['default'] = $lang->default_gallery;
	$avatardir = @opendir($mybb->settings['avatardir']);
	while($dir = @readdir($avatardir))
	{
		if(is_dir($mybb->settings['avatardir']."/$dir") && substr($dir, 0, 1) != ".")
		{
			$gallerylist[$dir] = str_replace("_", " ", $dir);
		}
	}
	@closedir($avatardir);
	natcasesort($gallerylist);
	reset($gallerylist);

	foreach($gallerylist as $dir => $friendlyname)
	{
		if($dir == $mybb->input['gallery'])
		{
			$activegallery = $friendlyname;
			$selected = "selected=\"selected\"";
		}
		$galleries .= "<option value=\"$dir\" $selected>$friendlyname</option>\n";
		$selected = "";
	}

	// Check to see if we're in a gallery or not
	if($mybb->input['gallery'])
	{
		$gallery = $mybb->input['gallery'];
		$lang->avatars_in_gallery = sprintf($lang->avatars_in_gallery, $friendlyname);
		// Get a listing of avatars in this gallery
		$avatardir = $mybb->settings['avatardir'];
		if($gallery != "default")
		{
			$avatardir .= "/$gallery";
		}
		$opendir = opendir($avatardir);
		while($avatar = @readdir($opendir))
		{
			$avatarpath = $avatardir."/".$avatar;
			if(is_file($avatarpath) && preg_match("#\.(jpg|jpeg|gif|bmp|png)$#i", $avatar))
			{
				$avatars[] = $avatar;
			}
		}
		@closedir($opendir);

		if(is_array($avatars))
		{
			natcasesort($avatars);
			reset($avatars);
			$count = 0;
			$avatarlist = "<tr>\n";
			foreach($avatars as $avatar)
			{
				$avatarpath = $avatardir."/".$avatar;
				$avatarname = preg_replace("#\.(jpg|jpeg|gif|bmp|png)$#i", "", $avatar);
				$avatarname = ucwords(str_replace("_", " ", $avatarname));
				if($mybb->user['avatar'] == $avatarpath)
				{
					$checked = "checked=\"checked\"";
				}
				if($count == 5)
				{
					$avatarlist .= "</tr>\n<tr>\n";
					$count = 0;
				}
				$count++;
				eval("\$avatarlist .= \"".$templates->get("usercp_avatar_gallery_avatar")."\";");
			}
			if($count != 0)
			{
				for($i=$count;$i<=5;$i++)
				{
					eval("\$avatarlist .= \"".$templates->get("usercp_avatar_gallery_blankblock")."\";");
				}
			}
		}
		else
		{
			eval("\$avatarlist = \"".$templates->get("usercp_avatar_gallery_noavatars")."\";");
		}
		eval("\$gallery = \"".$templates->get("usercp_avatar_gallery")."\";");
		$plugins->run_hooks("usercp_avatar_end");
		outputpage($gallery);
	}
	// Show main avatar page
	else
	{
		if($mybb->user['avatartype'] == "upload" || stristr($mybb->user['avatar'], $mybb->settings['avataruploadpath']))
		{
			$avatarmsg = "<br /><strong>".$lang->already_uploaded_avatar."</strong>";
		}
		elseif($mybb->user['avatartype'] == "gallery" || stristr($mybb->user['avatar'], $mybb->settings['avatardir']))
		{
			$avatarmsg = "<br /><strong>".$lang->using_gallery_avatar."</strong>";
		}
		elseif($mybb->user['avatartype'] == "remote" || strstr(strtolower($mybb->user['avatar']), "http://") !== false)
		{
			$avatarmsg = "<br /><strong>".$lang->using_remote_avatar."</strong>";
			$avatarurl = htmlspecialchars_uni($mybb->user['avatar']);
		}
		$urltoavatar = htmlspecialchars_uni($mybb->user['avatar']);
		if($mybb->user['avatar'])
		{
			eval("\$currentavatar = \"".$templates->get("usercp_avatar_current")."\";");
			$colspan = 1;
		}
		else
		{
			$colspan = 2;
		}
		if($mybb->settings['maxavatardims'] != "")
		{
			list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
			$lang->avatar_note .= "<br />".sprintf($lang->avatar_note_dimensions, $maxwidth, $maxheight);
		}
		if($mybb->settings['avatarsize'])
		{
			$maxsize = getfriendlysize($mybb->settings['avatarsize']*1024);
			$lang->avatar_note .= "<br />".sprintf($lang->avatar_note_size, $maxsize);
		}
		eval("\$avatar = \"".$templates->get("usercp_avatar")."\";");
		$plugins->run_hooks("usercp_avatar_end");
		outputpage($avatar);
	}

}
elseif($mybb->input['action'] == "do_avatar")
{
	$plugins->run_hooks("usercp_do_avatar_start");
	require "./inc/functions_upload.php";
	if($mybb->input['removeavatar'])
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='', avatartype='' WHERE uid='".$mybb->user['uid']."'");
		remove_avatars($mybb->user['uid']);
	}
	elseif($mybb->input['gallery']) // Gallery avatar
	{
		if($mybb->input['gallery'] == "default")
		{
			$avatarpath = addslashes($mybb->settings['avatardir']."/".$mybb->input['avatar']);
		}
		else
		{
			$avatarpath = addslashes($mybb->settings['avatardir']."/".$mybb->input['gallery']."/".$mybb->input['avatar']);
		}
		if(file_exists($avatarpath))
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='$avatarpath', avatartype='gallery' WHERE uid='".$mybb->user['uid']."'");
		}
		remove_avatars($mybb->user['uid']);
	}
	elseif($_FILES['avatarupload']['name'])
	{
		if($mybb->usergroup['canuploadavatars'] == "no")
		{
			nopermission();
		}
		$avatar = upload_avatar();
		if($avatar['error'])
		{
			error($avatar['error']);
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='".$avatar['avatar']."', avatartype='upload' WHERE uid='".$mybb->user['uid']."'");
	}
	else
	{
		$mybb->input['avatarurl'] = preg_replace("#script:#i", "", $mybb->input['avatarurl']);
		$ext = getextention($mybb->input['avatarurl']);
		if(preg_match("#gif|jpg|jpeg|jpe|bmp|png#i", $ext) && $mybb->settings['maxavatardims'] != "")
		{
			list($width, $height) = @getimagesize($mybb->input['avatarurl']);
			list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
			if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
			{
				$lang->error_avatartoobig = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
				error($lang->error_avatartoobig);
			}
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='".addslashes($mybb->input['avatarurl'])."', avatartype='remote' WHERE uid='".$mybb->user['uid']."'");
		remove_avatars($mybb->user['uid']);
	}
	$plugins->run_hooks("usercp_do_avatar_end");
	redirect("usercp.php", $lang->redirect_avatarupdated);
}
elseif($mybb->input['action'] == "notepad")
{
	$plugins->run_hooks("usercp_notepad_start");
	eval("\$notepad = \"".$templates->get("usercp_notepad")."\";");
	$plugins->run_hooks("usercp_notepad_end");
	outputpage($notepad);
}
elseif($mybb->input['action'] == "do_notepad")
{
	$plugins->run_hooks("usercp_do_notepad_start");
	$db->query("UPDATE ".TABLE_PREFIX."users SET notepad='".addslashes($mybb->input['notepad'])."' WHERE uid='".$mybb->user['uid']."'");
	$plugins->run_hooks("usercp_do_notepad_end");
	redirect("usercp.php", $lang->redirect_notepadupdated);
}
elseif($mybb->input['action'] == "editlists")
{
	$plugins->run_hooks("usercp_editlists_start");
	$buddyarray = explode(",", $mybb->user['buddylist']);
	if(is_array($buddyarray))
	{
		while(list($key, $buddyid) = each($buddyarray))
		{
			$buddysql .= "$comma'$buddyid'";
			$comma = ",";
		}
		$query = $db->query("SELECT username, uid FROM ".TABLE_PREFIX."users WHERE uid IN ($buddysql)");
		while($buddy = $db->fetch_array($query))
		{
			$uid = $buddy['uid'];
			$username = $buddy['username'];
			eval("\$buddylist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	$ignorearray = explode(",", $mybb->user['ignorelist']);
	if(is_array($ignorearray)) {
		while(list($key, $ignoreid) = each($ignorearray))
		{
			$ignoresql .= "$comma2'$ignoreid'";
			$comma2 = ",";
		}
		$query = $db->query("SELECT username, uid FROM ".TABLE_PREFIX."users WHERE uid IN ($ignoresql)");
		while($ignoreuser = $db->fetch_array($query))
		{
			$uid = $ignoreuser['uid'];
			$username = $ignoreuser['username'];
			eval("\$ignorelist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	for($i=1;$i<=2;$i++)
	{
		$uid = "new$i";
		$username = "";
		eval("\$newlist .= \"".$templates->get("usercp_editlists_user")."\";");
	}
	eval("\$listpage = \"".$templates->get("usercp_editlists")."\";");
	$plugins->run_hooks("usercp_editlists_end");
	outputpage($listpage);
}
elseif($mybb->input['action'] == "do_editlists")
{
	$plugins->run_hooks("usercp_do_editlists_start");
	while(list($key, $val) = each($mybb->input['listuser']))
	{
		if(strtoupper($mybb->user['username']) != strtoupper($val))
		{
			$val = addslashes($val);
			$users .= "$comma'$val'";
			$comma = ",";
		}
	}
	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username IN ($users)");
	while($user = $db->fetch_array($query))
	{
		$newlist .= "$comma2$user[uid]";
		$comma2 = ",";
	}
	$type = $mybb->input['list']."list";
	$db->query("UPDATE ".TABLE_PREFIX."users SET $type='$newlist' WHERE uid='".$mybb->user['uid']."'");
	$redirecttemplate = "redirect_".$mybb->input['list']."updated";
	$plugins->run_hooks("usercp_do_editlists_end");
	redirect("usercp.php?action=editlists", $lang->$redirecttemplate);
}
elseif($mybb->input['action'] == "drafts")
{
	$plugins->run_hooks("usercp_drafts_start");
	// Show a listing of all of the current 'draft' posts or threads the user has.
	$query = $db->query("SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) WHERE p.uid='".$mybb->user['uid']."' AND p.visible='-2' ORDER BY p.dateline DESC");
	while($draft = $db->fetch_array($query))
	{
		if($trow == "trow1")
		{
			$trow = "trow2";
		}
		else
		{
			$trow = "trow1";
		}
		if($draft['threadvisible'] == 1) // We're looking at a draft post
		{
			$detail = $lang->thread." <a href=\"showthread.php?tid=".$draft['tid']."\">".htmlspecialchars_uni($draft['threadsubject'])."</a>";
			$editurl = "newreply.php?action=editdraft&pid=$draft[pid]";
			$id = $draft['pid'];
			$type = "post";
		}
		elseif($draft['threadvisible'] == -2) // We're looking at a draft thread
		{
			$detail = $lang->forum." <a href=\"forumdisplay.php?fid=".$draft['fid']."\">".htmlspecialchars_uni($draft['forumname'])."</a>";
			$editurl = "newthread.php?action=editdraft&tid=$draft[tid]";
			$id = $draft['tid'];
			$type = "thread";
		}
		$draft['subject'] = htmlspecialchars_uni($draft['subject']);
		$savedate = mydate($mybb->settings['dateformat'], $draft['dateline']);
		$savetime = mydate($mybb->settings['timeformat'], $draft['dateline']);
		eval("\$drafts .= \"".$templates->get("usercp_drafts_draft")."\";");
	}
	if(!$drafts)
	{
		eval("\$drafts = \"".$templates->get("usercp_drafts_none")."\";");
	}
	else
	{
		eval("\$draftsubmit = \"".$templates->get("usercp_drafts_submit")."\";");
	}
	eval("\$draftlist = \"".$templates->get("usercp_drafts")."\";");
	$plugins->run_hooks("usercp_drafts_end");
	outputpage($draftlist);

}
elseif($mybb->input['action'] == "do_drafts")
{
	$plugins->run_hooks("usercp_do_drafts_start");
	if(!$mybb->input['deletedraft'])
	{
		error($lang->no_drafts_selected);
	}
	$pidin = "";
	$tidin = "";
	foreach($mybb->input['deletedraft'] as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] .= "'".intval($id)."'";
		}
		elseif($val == "thread")
		{
			$tidin[] .= "'".intval($id)."'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE tid IN ($tidin) AND visible='-2' AND uid='".$mybb->user['uid']."'");
		$tidinp = "OR tid IN ($tidin)";
	}
	if($pidin || $tidinp)
	{
		if($pidin)
		{
			$pidin = implode(",", $pidin);
			$pidinq = "pid IN ($pidin)";
		}
		else
		{
			$pidinq = "1=0";
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE ($pidinq $tidinp) AND visible='-2' AND uid='".$mybb->user['uid']."'");
	}
	$plugins->run_hooks("usercp_do_drafts_end");
	redirect("usercp.php?action=drafts", $lang->selected_drafts_deleted);
}
elseif($mybb->input['action'] == "usergroups")
{
	$plugins->run_hooks("usercp_usergroups_start");
	$ingroups = ",".$mybb->user['usergroup'].",".$mybb->user['additionalgroups'].",".$mybb->user['displaygroup'].",";

	// Changing our display group
	if($mybb->input['displaygroup'])
	{
		if(!strstr($ingroups, ",".$mybb->input['displaygroup'].","))
		{
			error($lang->not_member_of_group);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['displaygroup'])."'");
		$dispgroup = $db->fetch_array($query);
		if($dispgroup['candisplaygroup'] != "yes")
		{
			error($lang->cannot_set_displaygroup);
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET displaygroup='".intval($mybb->input['displaygroup'])."' WHERE uid='".$mybb->user['uid']."'");
		$plugins->run_hooks("usercp_usergroups_change_displaygroup");
		redirect("usercp.php?action=usergroups", $lang->display_group_changed);
		exit;
	}

	// Leaving a group
	if($mybb->input['leavegroup'])
	{
		if(!strstr($ingroups, ",".$mybb->input['leavegroup'].","))
		{
			error($lang->not_member_of_group);
		}
		if($mybb->user['usergroup'] == $mybb->input['leavegroup'])
		{
			error($lang->cannot_leave_primary_group);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['leavegroup'])."'");
		$usergroup = $db->fetch_array($query);
		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_leave_group);
		}
		leave_usergroup($mybb->user['uid'], $mybb->input['leavegroup']);
		$plugins->run_hooks("usercp_usergroups_leave_group");
		redirect("usercp.php?action=usergroups", $lang->left_group);
	}

	// Joining a group
	if($mybb->input['joingroup'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".intval($mybb->input['joingroup'])."'");
		$usergroup = $db->fetch_array($query);

		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_join_group);
		}

		if(strstr($ingroups, ",".intval($mybb->input['joingroup']).",") || $mybb->user['usergroup'] == $mybb->input['joingroup'] || $mybb->user['displaygroup'] == $mybb->input['joingroup'])
		{
			error($lang->already_member_of_group);
		}

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."joinrequests WHERE uid='".$mybb->user['uid']."' AND gid='".intval($mybb->input['joingroup'])."'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			error($lang->already_sent_join_request);
		}
		if($mybb->input['do'] == "joingroup" && $usergroup['type'] == 4)
		{
			$reason = addslashes($reason);
			$now = time();
			$joinrequest = array(
				"rid" => "NULL",
				"uid" => $mybb->user['uid'],
				"gid" => intval($mybb->input['gid']),
				"reason" => addslashes($mybb->input['reason']),
				"dateline" => time()
				);

			$db->insert_query(TABLE_PREFIX."joinrequests", $joinrequest);
			$plugins->run_hooks("usercp_usergroups_join_group_request");
			redirect("usercp.php?action=usergroups", $lang->group_join_requestsent);
			exit;
		}
		elseif($usergroup['type'] == 4)
		{
			$joingroup = $mybb->input['joingroup'];
			eval("\$joinpage = \"".$templates->get("usercp_usergroups_joingroup")."\";");
			outputpage($joinpage);
		}
		else
		{
			join_usergroup($mybb->user['uid'], $mybb->input['joingroup']);
			$plugins->run_hooks("usercp_usergroups_join_group");
			redirect("usercp.php?action=usergroups", $lang->joined_group);
		}
	}
	// Show listing of various group related things

	// List of usergroup leaders
	$query = $db->query("SELECT g.*, u.username FROM ".TABLE_PREFIX."groupleaders g LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=g.uid) ORDER BY u.username ASC");
	while($leader = $db->fetch_array($query))
	{
		$groupleaders[$leader['gid']][$leader['uid']] = $leader;
	}

	// List of groups this user is a leader of
	$query = $db->query("SELECT g.title, g.gid, g.type, COUNT(u.uid) AS users, COUNT(j.rid) AS joinrequests FROM ".TABLE_PREFIX."groupleaders l LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=l.gid) LEFT JOIN ".TABLE_PREFIX."users u ON (((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')))) LEFT JOIN ".TABLE_PREFIX."joinrequests j ON (j.gid=g.gid) WHERE l.uid='".$mybb->user['uid']."' GROUP BY l.gid");
	while($usergroup = $db->fetch_array($query))
	{
		$memberlistlink = $moderaterequestslink = "";
		$memberlistlink = " [<a href=\"managegroup.php?gid=".$usergroup['gid']."\">".$lang->view_members."</a>]";
		if($usergroup['type'] != 4)
		{
			$usergroup['joinrequests'] = '--';
		}
		elseif($usergroup['joinrequests'] > 0)
		{
			$moderaterequestslink = " [<a href=\"managegroup.php?action=joinrequests&gid=".$usergroup['gid']."\">".$lang->view_requests."</a>]";
		}
		$groupleader[$usergroup['gid']] = 1;
		$trow = alt_trow();
		eval("\$groupsledlist .= \"".$templates->get("usercp_usergroups_leader_usergroup")."\";");
	}
	if($groupsledlist)
	{
		eval("\$leadinggroups = \"".$templates->get("usercp_usergroups_leader")."\";");
	}
			
	// Fetch the list of groups the member is in
	// Do the primary group first
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='".$mybb->user['usergroup']."'");
	$usergroup = $db->fetch_array($query);
	$leavelink = "<span class=\"smalltext\"><center>$lang->usergroup_leave_primary</center></span>";
	$trow = alt_trow();
	if($usergroup['candisplaygroup'] == "yes" && $usergroup['gid'] == $mybb->user['displaygroup'])
	{
		$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"$usergroup[gid]\" checked=\"checked\" />";
	}
	elseif($usergroup['candisplaygroup'] == "yes")
	{
		$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"$usergroup[gid]\" />";
	}
	else
	{
		$displaycode = '';
	}

	eval("\$memberoflist = \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
	$showmemberof = false;

	if($mybb->user['additionalgroups'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid IN (".$mybb->user['additionalgroups'].") AND gid !='".$mybb->user['usergroup']."' ORDER BY title ASC");
		while($usergroup = $db->fetch_array($query))
		{
			$showmemberof = true;
			if($groupleader[$usergroup['gid']])
			{
				$leavelink = "<span class=\"smalltext\"><center>$lang->usergroup_leave_leader</center></span>";
			}
			else
			{
				$leavelink = "<center><a href=\"usercp.php?action=usergroups&leavegroup=".$usergroup['gid']."\">".$lang->usergroup_leave."</a></center>";
			}
			if($usergroup['description'])
			{
				$description = "<br /><span class=\"smalltext\">".$usergroup['description']."</span>";
			}
			else
			{
				$description = "";
			}
			if(!$usergroup['usertitle'])
			{
				// fetch title here
			}
			$trow = alt_trow();
			if($usergroup['candisplaygroup'] == "yes" && $usergroup['gid'] == $mybb->user['displaygroup'])
			{
				$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"$usergroup[gid]\" checked=\"checked\" />";
			}
			elseif($usergroup['candisplaygroup'] == "yes")
			{
				$displaycode = "<input type=\"radio\" name=\"displaygroup\" value=\"$usergroup[gid]\" />";
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
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."joinrequests WHERE uid='".$mybb->user['uid']."'");
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
	$joinablegroups = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE (type='3' OR type='4') AND gid NOT IN ($existinggroups) ORDER BY title ASC");
	while($usergroup = $db->fetch_array($query))
	{
		$trow = alt_trow();
		if($usergroup['description'])
		{
			$description = "<br /><span class=\"smallfont\">".$usergroup['description']."</span>";
		}
		else
		{
			$description = "";
		}
		if($usergroup['type'] == 4) // Moderating join requests
		{
			$conditions = $lang->usergroup_joins_moderated;
		}
		else
		{
			$conditions = $lang->usergroup_joins_anyone;
		}
		if($appliedjoin[$usergroup['gid']])
		{
			$applydate = mydate($mybb->settings['dateformat'], $appliedjoin[$usergroup['gid']]);
			$applytime = mydate($mybb->settings['timeformat'], $appliedjoin[$usergroup['gid']]);
			$joinlink = sprintf($lang->join_group_applied, $applydate, $applytime);
		}
		else
		{
			$joinlink = "<a href=\"usercp.php?action=usergroups&joingroup=".$usergroup['gid']."\">".$lang->join_group."</a>";
		}
		$usergroupleaders = "";
		if($groupleaders[$usergroup['gid']])
		{
			foreach($groupleaders[$usergroup['gid']] as $leader)
			{
				$usergroupleaders .= "$comma<a href=\"member.php?action=profile&uid=".$leader['uid']."\">".$leader['username']."</a>";
				$comma = ", ";
			}
			$usergroupleaders = $lang->usergroup_leaders." ".$usergroupleaders;
		}
		eval("\$joinablegrouplist .= \"".$templates->get("usercp_usergroups_joinable_usergroup")."\";");
		$usergroupleaders = $comma = "";
	}
	if($joinablegrouplist)
	{
		eval("\$joinablegroups = \"".$templates->get("usercp_usergroups_joinable")."\";");
	}

	eval("\$groupmemberships = \"".$templates->get("usercp_usergroups")."\";");
	$plugins->run_hooks("usercp_usergroups_end");
	outputpage($groupmemberships);
}
elseif($mybb->input['action'] == "attachments")
{
	$plugins->run_hooks("usercp_attachments_start");
	require "./inc/functions_upload.php";
	$attachments = "";
	$query = $db->query("SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) WHERE a.uid='".$mybb->user['uid']."' AND a.pid!='0' ORDER BY p.dateline DESC");
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
			$attachment['subject'] = htmlspecialchars_uni(dobadwords($attachment['subject']));
			$attachment['threadsubject'] = htmlspecialchars_uni(dobadwords($attachment['threadsubject']));
			$size = getfriendlysize($attachment['filesize']);
			$icon = getattachicon(getextention($attachment['filename']));
			$sizedownloads = sprintf($lang->attachment_size_downloads, $size, $attachment['downloads']);
			$attachdate = mydate($mybb->settings['dateformat'], $attachment['dateline']);
			$attachtime = mydate($mybb->settings['timeformat'], $attachment['dateline']);
			$altbg = alt_trow();
			eval("\$attachments .= \"".$templates->get("usercp_attachments_attachment")."\";");
		}
		else
		{
			// This little thing delets attachments without a thread/post
			remove_attachment($attachment['pid'], $attachment['posthash'], $attachment['aid']);
		}
	}
	$query = $db->query("SELECT SUM(filesize) AS ausage, COUNT(aid) AS acount FROM ".TABLE_PREFIX."attachments WHERE uid='".$mybb->user['uid']."'");
	$usage = $db->fetch_array($query);
	$totalusage = $usage['ausage'];
	$totalattachments = $usage['acount'];
	$friendlyusage = getfriendlysize($totalusage);
	if($mybb->usergroup['attachquota'])
	{
		$percent = round(($totalusage/($mybb->usergroup['attachquota']*1000))*100)."%";
		$attachquota = getfriendlysize($mybb->usergroup['attachquota']*1000);
		$usagenote = sprintf($lang->attachments_usage_quota, $friendlyusage, $attachquota, $percent, $totalattachments);
	}
	else
	{
		$usagenote = sprintf($lang->attachments_usage, $friendlyusage, $totalattachments);
	}
	if(!$attachments)
	{
		eval("\$attachments = \"".$templates->get("usercp_attachments_none")."\";");
		$usagenote = "";
	}
	eval("\$manageattachments = \"".$templates->get("usercp_attachments")."\";");
	$plugins->run_hooks("usercp_attachments_end");
	outputpage($manageattachments);
}
elseif($mybb->input['action'] == "do_attachments")
{
	$plugins->run_hooks("usercp_do_attachments_start");
	require "./inc/functions_upload.php";
	if(!is_array($mybb->input['attachments']))
	{
		error($lang->no_attachments_selected);
	}
	$aids = addslashes(implode(",", $mybb->input['attachments']));
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE aid IN ($aids) AND uid='".$mybb->user['uid']."'");
	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], "", $attachment['aid']);
	}
	$plugins->run_hooks("usercp_do_attachments_end");
	redirect("usercp.php?action=attachments", $lang->attachments_deleted);
}
else
{
	$plugins->run_hooks("usercp_start");
	// Get posts per day
	$daysreg = (time() - $mybb->user['regdate']) / (24*3600);
	$perday = $mybb->user['postnum'] / $daysreg;
	$perday = round($perday, 2);
	if($perday > $mybb->user['postnum'])
	{
		$perday = $mybb->user['postnum'];
	}
	$lang->posts_day = sprintf($lang->posts_day, mynumberformat($perday));
	$usergroup = $groupscache[$mybb->user['usergroup']]['title'];

	$colspan = 2;
	if($mybb->user['avatar'])
	{
		eval("\$avatar = \"".$templates->get("usercp_currentavatar")."\";");
		$colspan = 3;
	}
	else
	{
		$avatar = "";
	}
	$regdate = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $mybb->user['regdate']);

	$query = $db->query("SELECT r.*, p.subject, p.tid FROM ".TABLE_PREFIX."reputation r LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=r.pid) WHERE r.uid='".$mybb->user['uid']."' ORDER BY r.dateline DESC LIMIT 0, 10");
	$numreps = $db->num_rows($query);
	$bgclass = "trow1";
	if($numreps)
	{
		while($reputation = $db->fetch_array($query))
		{
			if($reputation['tid'])
			{
				if(!$reputation['subject'])
				{
					$reputation['subject'] = "[no subject]";
				}
				$postlink = "<a href=\"showthread.php?tid=$reputation[tid]&pid=$reputation[pid]#pid$reputation[pid]\">$reputation[subject]</a>";
			}
			else
			{
				$postlink = $lang->na_deleted;
			}
			$repdate = mydate($mybb->settings['dateformat'], $reputation['dateline']);
			$reptime = mydate($mybb->settings['timeformat'], $reputation['dateline']);
			$reputation['comments'] = stripslashes($reputation['comments']);
			if(strpos(" ".$reputation['reputation'], "-"))
			{ // negative
				$posnegimg = "repbit_neg.gif";
			}
			else
			{
				$posnegimg = "repbit_pos.gif";
			}
			eval("\$reputationbits .= \"".$templates->get("usercp_latestreputations_bit")."\";");
			if($bgclass == "trow1")
			{
				$bgclass = "trow2";
			}
			else
			{
				$bgclass = "trow1";
			}
		}
		$lang->latest_reputations_received = sprintf($lang->latest_reputations_received, $mybb->user['reputation']);
		eval("\$reputations = \"".$templates->get("usercp_latestreputations")."\";");
	}
	if($mybb->user['usergroup'] == 5)
	{
		$usergroup .= "<br />(<a href=\"member.php?action=resendactivation\">$lang->resend_activation</a>)";
	}
	$mybbuser['postnum'] = mynumberformat($mybb->user['postnum']);
	eval("\$usercp = \"".$templates->get("usercp")."\";");
	$plugins->run_hooks("usercp_end");
	outputpage($usercp);
}
?>