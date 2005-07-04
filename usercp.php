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
 
$templatelist = "usercp,usercp_home,usercp_nav,usercp_profile,error_nopermission,buddy_online,buddy_offline,usercp_changename,usercp_nav_changename";
$templatelist .= "usercp_usergroups_memberof_usergroup,usercp_usergroups_memberof,usercp_usergroups_joinable_usergroup,usercp_usergroups_joinable,usercp_usergroups";
require "./global.php";
require "./inc/functions_post.php";

// Load global language phrases
$lang->load("usercp");

if($mybb->user['uid'] == 0 || $mybb->usergroup['canusercp'] == "no")
{
	nopermission();
}

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can";
	$db->query("UPDATE ".TABLE_PREFIX."users SET pmfolders='".$mybb->user[pmfolders]."' WHERE uid='".$mybb->user[uid]."'");
}

makeucpnav();

if($action == "do_editsig")
{
	if($mybb->settings['maxsigimages'] != 0)
	{
		$imagecheck = postify(stripslashes($signature), $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		if(substr_count($imagecheck, "<img") > $mybb->settings['maxsigimages'])
		{
			$lang->too_many_sig_images2 = sprintf($lang->too_many_sig_images2, $mybb->settings['maxsigimages']);
			eval("\$maximageserror = \"".$templates->get("error_maxsigimages")."\";");
			$action = "editsig";
		}
	}
	if($preview) {
		$action = "editsig";
	}
}

// Make navigation
addnav($lang->nav_usercp, "usercp.php");

switch($action)
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
	
if($action == "profile")
{
	$user = $mybb->user;
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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->user[uid]."'");
		$user = $db->fetch_array($query);
		$mybb->user['usertitle'] = $user['usertitle'];
		eval("\$customtitle = \"".$templates->get("usercp_profile_customtitle")."\";");
	}
	else
	{
		$customtitle = "";
	}

	eval("\$editprofile = \"".$templates->get("usercp_profile")."\";");
	outputpage($editprofile);
}
elseif($action == "do_profile")
{
	if($website && !stristr($website, "http://"))
	{
		$website = "";
	}
	if($website == "http://" || $website == "none")
	{
		$website = "";
	}
	if(strlen($website) > 75)
	{
		error($lang->error_website_length);
	}
	if($bday1 == "" || $bday2 == "")
	{
		$bday = "";
	}
	else
	{
		if(($bday3>=(date("Y")-100)) && ($bday3<date("Y")))
		{
			$bday = "$bday1-$bday2-$bday3";
		}
		else
		{
			$bday = "$bday1-$bday2-";
		}
	}
	$titleup == "";
	if($mybb->usergroup['cancustomtitle'] == "yes")
	{
		if($usertitle <= $mybb->settings['customtitlemaxlength'])
		{
			$usertitle = addslashes(htmlspecialchars_uni($usertitle));
			$titleup = ", usertitle='$usertitle'";
		}
		else
		{
			error($lang->error_customtitle_length);
		}
	}
	$website = addslashes(htmlspecialchars_uni($website));
	$icq = addslashes(htmlspecialchars_uni($icq));
	$aim = addslashes(htmlspecialchars_uni($aim));
	$yahoo = addslashes(htmlspecialchars_uni($yahoo));
	$msn = addslashes(htmlspecialchars_uni($msn));
	$signature = addslashes($signature);
	$bio = addslashes($bio);
	if($away == "yes" && $mybb->settings['allowaway'] != "no")
	{
		$awaydate = time();
		if($awayday && $awaymonth && $awayyear)
		{
			$returndate = "$awayday-$awaymonth-$awayyear";
		}
		else
		{
			$returndate = "";
		}
		$awayreason = addslashes($awayreason);
		$awayadd = ", away='yes', awaydate='$awaydate', returndate='$returndate', awayreason='$awayreason'";
	}
	else
	{
		$awayadd = ", away='no', awaydate='', returndate='', awayreason=''";
	}
	// Custom profile fields baby!
	$upquery = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
		$thing = explode("\n", $profilefield['type'], "2");
		$type = $thing[0];
		$field = "fid$profilefield[fid]";
		if($profilefield['editable'] == "yes")
		{
			if(!$$field && $profilefield['required'] == "yes")
			{
				error($lang->error_missingrequiredfield);
			}
			$options = "";
			if($type == "multiselect" || $type == "checkbox")
			{
				while(list($key, $val) = each($$field))
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
				$options = $$field;
			}
			$options = addslashes($options);
			$upquery .= ", $field='$options'";
		}
		else
		{
			$upquery .= ", $field=".$field;
		}
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields WHERE ufid='".$mybb->user[uid]."'");
	$fields = $db->fetch_array($query);
	if(!$fields['ufid'])
	{
		$db->query("INSERT INTO ".TABLE_PREFIX."userfields (ufid) VALUES ('".$mybb->user[uid]."')");
	}
	$db->query("UPDATE ".TABLE_PREFIX."userfields SET ufid='".$mybb->user[uid]."' $upquery WHERE ufid='".$mybb->user[uid]."'");
	
	$db->query("UPDATE ".TABLE_PREFIX."users SET website='$website', icq='$icq', aim='$aim', yahoo='$yahoo', msn='$msn', birthday='$bday' $awayadd $titleup WHERE uid='".$mybb->user[uid]."'");

	if(function_exists("profileUpdated"))
	{
		profileUpdated($mybb->user['uid']);
	}

	setcookie("mybb[uid]", $mybb->user['uid']);
	redirect("usercp.php", $lang->redirect_profileupdated);
} elseif($action == "options") {
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
	if($mybb->user['allownotices'] != "no")
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

	if($mybb->user['hideemail'] == "no")
	{
		$hideemailcheck = "checked=\"checked\"";
	}
	else
	{
		$hideemailcheck = "";
	}

	if($mybb->user['emailnotify'] != "no")
	{
		$emailnotifycheck = "checked=\"checked\"";
	}
	else
	{
		$emailnotifycheck = "";
	}

	if($mybb->user['showsigs'] != "no")
	{
		$showsigscheck = "checked=\"checked\"";;
	}
	else
	{
		$showsigscheck = "";
	}

	if($mybb->user['showavatars'] != "no")
	{
		$showavatarscheck = "checked=\"checked\"";
	}
	else
	{
		$showavatarscheck = "";
	}

	if($mybb->user['showquickreply'] != "no")
	{
		$showquickreplycheck = "checked=\"checked\"";
	}
	else
	{
		$showquickreplycheck = "";
	}

	if($mybb->user['remember'] != "no")
	{
		$remembercheck = "checked=\"checked\"";
	}
	else
	{
		$remembercheck = "";
	}

	if($mybb->user['receivepms'] != "no")
	{
		$receivepmscheck = "checked=\"checked\"";
	}
	else
	{
		$receivepmscheck = "";
	}

	if($mybb->user['pmpopup'] != "no")
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
	outputpage($editprofile);
} elseif($action == "do_options")
{
	if($showcodebuttons != 1)
	{
		$showcodebuttons = 0;
	}
	if($allownotices != "yes")
	{
		$allownotices = "no";
	}

	if($invisible != "yes")
	{
		$invisible = "no";
	}

	if($hideemail != "no")
	{
		$hideemail = "yes";
	}

	if($emailnotify != "yes")
	{
		$emailnotify = "no";
	}

	if($showsigs != "yes")
	{
		$showsigs = "no";
	}

	if($showavatars != "yes")
	{
		$showavatars = "no";
	}

	if($showquickreply != "yes")
	{
		$showquickreply = "no";
	}

	if($remember != "yes")
	{
		$remember = "no";
	}

	if($receivepms != "yes")
	{
		$receivepms = "no";
	}
	if($pmpopup != "yes")
	{
		$pmpopup = "no";
	}

	if($pmnotify != "yes")
	{
		$pmnotify = "no";
	}

	if($dst != "yes")
	{
		$dst = "no";
	}

	if($mybb->settings['usertppoptions']) {
		$queryadd = ", tpp='$tpp'";
	}
	if($mybb->settings['userpppoptions']) {
		$queryadd2 = ", ppp='$ppp'";
	}

	$languages = $lang->getLanguages();
	if(!$languages[$language])
	{
		$language = "";
	}
	$style = $_POST['style'];
	$db->query("UPDATE ".TABLE_PREFIX."users SET allownotices='$allownotices', hideemail='$hideemail', emailnotify='$emailnotify', invisible='$invisible', style='$style', dateformat='$dateformat', timeformat='$timeformat', timezone='$timezoneoffset', dst='$dst', threadmode='$threadmode', showsigs='$showsigs', showavatars='$showavatars', showquickreply='$showquickreply', remember='$remember', receivepms='$receivepms', pmpopup='$pmpopup', daysprune='$daysprune', language='$language', showcodebuttons='$showcodebuttons', pmnotify='$pmnotify' $queryadd $queryadd2 WHERE uid='".$mybb->user[uid]."'");
	redirect("usercp.php", $lang->redirect_optionsupdated);
}
elseif($action == "email")
{
	eval("\$changemail = \"".$templates->get("usercp_email")."\";");
	outputpage($changemail);
}
elseif($action == "do_email")
{
	$email = addslashes($_POST['email']);
	$password = md5($password);
	if($password != $mybb->user['password'] || $password == "")
	{
		error($lang->error_invalidpassword);
	}
	if($email != $email2)
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
					if(strstr("$email", $bannedemail) != "")
					{
						error($lang->error_bannedemail);
					}
				}
			}
		}
	}
	if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email))
	{
		error($lang->error_invalidemail);
	}
	if(function_exists("emailChanged"))
	{
		emailChanged($mybb->user['uid'], $email);
	}

	if($mybb->settings['regtype'] == "verify")
	{
		$activationcode = random_str();
		$now = time();
		$db->query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$mybb->user[uid]."'");
		$db->query("INSERT INTO ".TABLE_PREFIX."awaitingactivation (aid,uid,dateline,code,type,oldgroup,misc) VALUES (NULL,'".$mybb->user[uid]."','$now','$activationcode','e','".$mybb->user[usergroup]."','$email')");
		$username = $mybb->user['username'];
		$uid = $mybb->user['uid'];
		$lang->emailsubject_changeemail = sprintf($lang->emailsubject_changeemail, $mybb->settings['bbname']);
		$lang->email_changeemail = sprintf($lang->email_changeemail, $mybb->user['username'], $mybb->settings['bbname'], $mybb->user['email'], $email, $mybb->settings['bburl'], $activationcode, $mybb->user['username'], $mybb->user['uid']);
		mymail($email, $lang->emailsubject_changeemail, $lang->email_changeemail);
		error($lang->redirect_changeemail_activation);
	}
	else
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET email='$email' WHERE uid='".$mybb->user[uid]."'");
		if(function_exists("emailChanged"))
		{
			emailChanged($mybb->user['uid'], $email);
		}
		redirect("usercp.php", $lang->redirect_emailupdated);
	}
}
elseif($action == "password")
{
	eval("\$editpassword = \"".$templates->get("usercp_password")."\";");
	outputpage($editpassword);
}
elseif($action == "do_password")
{
	$oldpassword = md5($oldpassword);
	if($oldpassword != $mybb->user['password'] || $password == "")
	{
		error($lang->error_invalidpassword);
	}
	if($password != $password2)
	{
		error($lang->error_passwordmismatch);
	}
	$password = md5($password);
	$db->query("UPDATE ".TABLE_PREFIX."users SET password='$password' WHERE uid='".$mybb->user[uid]."'");
	mysetcookie("mybbuser", $mybb->user['uid']."_".md5($password.md5($mybb->user['salt'])));
	if(function_exists("passwordChanged"))
	{
		passwordChanged($mybb->user['uid'], $password);
	}
	redirect("usercp.php", $lang->redirect_passwordupdated);
}
elseif($action == "changename")
{
	if($mybb->usergroup['canchangename'] != "yes")
	{
		nopermission();
	}
	eval("\$changename = \"".$templates->get("usercp_changename")."\";");
	outputpage($changename);
}
elseif($action == "do_changename")
{
	if($mybb->usergroup['canchangename'] != "yes")
	{
		nopermission();
	}
	if(!trim($username) || eregi("<|>|&", $username))
	{
		error($lang->error_bannedusername);
	}
	$username = addslashes($username);
	$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE username LIKE '$username'");
	if($db->fetch_array($query))
	{
		error($lang->error_usernametaken);
	}
	$oldusername = addslashes($mybb->user['username']);
	$db->query("UPDATE ".TABLE_PREFIX."users SET username='$username' WHERE uid='".$mybb->user[uid]."'");
	$db->query("UPDATE ".TABLE_PREFIX."forums SET lastposter='$username' WHERE lastposter='$oldusername'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET lastposter='$username' WHERE lastposter='$oldusername'");
	redirect("usercp.php", $lang->redirect_namechanged);
}
elseif($action == "favorites")
{
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE gid='".$mybb->user[usergroup]."'");
	while($permissions = $db->fetch_array($query)) {
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	// Do Multi Pages
	$query = $db->query("SELECT COUNT(f.tid) AS threads FROM ".TABLE_PREFIX."favorites f WHERE f.type='f' AND f.uid='".$mybb->user[uid]."'");
	$threadcount = $db->result($query, 0);
	
	$perpage = $mybb->settings['threadsperpage'];
	if($page) {
		$start = ($page-1) *$perpage;
	} else {
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount) {
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=favorites");
	$fpermissions = forum_permissions();
	$query = $db->query("SELECT f.*, t.*, i.name AS iconname, i.path AS iconpath, t.username AS threadusername, u.username FROM ".TABLE_PREFIX."favorites f LEFT JOIN ".TABLE_PREFIX."threads t ON (f.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE f.type='f' AND f.uid='".$mybb->user[uid]."' ORDER BY t.lastpost DESC");
	while($favorite = $db->fetch_array($query)) {
		$forumpermissions = $fpermissions[$favorite['fid']];
		if($forumpermissions['canview'] != "no" || $forumpermissions['canviewthreads'] != "no") {
			$lastpostdate = mydate($mybb->settings['dateformat'], $favorite['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $favorite['lastpost']);
			$lastposter = $favorite['lastposter'];
			$favorite['author'] = $favorite['uid'];
			if(!$favorite['username']) {
				$favorite['username'] = $favorite['threadusername'];
			}
			$favorite['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($favorite['subject'])));
			if($favorite['iconpath']) {
				$icon = "<img src=\"$favorite[iconpath]\" alt=\"$favorite[iconname]\">";
			} else {
				$icon = "&nbsp;";
			}
			if($mybb->user['lastvisit'] == "0") {
				$folder = "new";
			}
			if($favorite['lastpost'] > $mybb->user['lastvisit']) {
				$threadread = mygetarraycookie("threadread", $favorite['tid']);
				if($threadread < $favorite['lastpost']) {
					$folder = "new";
				}
			}
			if($favorite['replies'] >= $mybb->settings['hottopic']) {
				$folder .= "hot";
			}
			if($favorite['closed'] == "yes") {
				$folder .= "lock";
			}
			$folder .= "folder";
			eval("\$threads .= \"".$templates->get("usercp_favorites_thread")."\";");
			$folder = "";
		}
	}
	eval("\$favorites = \"".$templates->get("usercp_favorites")."\";");
	outputpage($favorites);
} elseif($action == "subscriptions") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE gid='".$mybb->user[usergroup]."'");
	while($permissions = $db->fetch_array($query)) {
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	// Do Multi Pages
	$query = $db->query("SELECT COUNT(s.tid) AS threads FROM ".TABLE_PREFIX."favorites s WHERE s.type='s' AND s.uid='".$mybb->user[uid]."'");
	$threadcount = $db->result($query, 0);
	
	$perpage = $mybb->settings['threadsperpage'];
	if($page) {
		$start = ($page-1) *$perpage;
	} else {
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;
	if($upper > $threadcount) {
		$upper = $threadcount;
	}
	$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=subscriptions");
	$fpermissions = forum_permissions();
	$query = $db->query("SELECT s.*, t.*, i.name AS iconname, i.path AS iconpath, t.username AS threadusername, u.username FROM ".TABLE_PREFIX."favorites s LEFT JOIN ".TABLE_PREFIX."threads t ON (s.tid=t.tid) LEFT JOIN ".TABLE_PREFIX."icons i ON (i.iid = t.icon) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid) WHERE s.type='s' AND s.uid='".$mybb->user[uid]."' ORDER BY t.lastpost DESC LIMIT $start, $perpage");
	while($subscription = $db->fetch_array($query)) {
		$forumpermissions = $fpermissions[$subscription['fid']];
		if($forumpermissions['canview'] != "no" || $forumpermissions['canviewthreads'] != "no") {
			$lastpostdate = mydate($mybb->settings['dateformat'], $subscription['lastpost']);
			$lastposttime = mydate($mybb->settings['timeformat'], $subscription['lastpost']);
			$lastposter = $subscription['lastposter'];
			$subscription['author'] = $subscription['uid'];
			if(!$subscription['username']) {
				$subscription['username'] = $subscription['threadusername'];
			}
			$subscription['subject'] = htmlspecialchars_uni(stripslashes(dobadwords($subscription['subject'])));
			if($subscription['iconpath']) {
				$icon = "<img src=\"$subscription[iconpath]\" alt=\"$subscription[iconname]\">";
			} else {
				$icon = "&nbsp;";
			}
			if($mybb->user['lastvisit'] == "0") {
				$folder = "new";
			}
			if($subscription['lastpost'] > $mybb->user['lastvisit']) {
				$threadread = mygetarraycookie("threadread", $subscription['tid']);
				if($threadread < $subcription['lastpost']) {
					$folder = "new";
				}
			}
			if($subscription['replies'] >= $mybb->settings['hottopic']) {
				$folder .= "hot";
			}
			if($subscription['closed'] == "yes") {
				$folder .= "lock";
			}
			$folder .= "folder";
			eval("\$threads .= \"".$templates->get("usercp_subscriptions_thread")."\";");
			$folder = "";
		}
	}
	eval("\$subscriptions = \"".$templates->get("usercp_subscriptions")."\";");
	outputpage($subscriptions);
} elseif($action == "forumsubscriptions") {
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions WHERE gid='".$mybb->user[usergroup]."'");
	while($permissions = $db->fetch_array($query)) {
		$permissioncache[$permissions['gid']][$permissions['fid']] = $permissions;
	}
	$fpermissions = forum_permissions();
	$query = $db->query("SELECT fs.*, f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forumsubscriptions fs LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid = fs.fid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE f.type='f' AND fs.uid='".$mybb->user[uid]."' ORDER BY f.name ASC");
	while($forum = $db->fetch_array($query)) {
		$forumpermissions = $fpermissions[$forum['fid']];
		if($forumpermissions['canview'] != "no") {
			if(($forum['lastpost'] > $mybb->user['lastvisit'] || $mybbforumread[$forum['fid']] > $mybb->user['lastvisit']) && $forum['lastpost'] != 0) {
				$folder = "on";
			} else {
				$folder = "off";
			}
			if($forum['lastpost'] == 0 || $forum['lastposter'] == "") {
				$lastpost = "<font><center>$lang->never</center></font>";
			} else {
				$lastpostdate = mydate($mybb->settings['dateformat'], $forum['lastpost']);
				$lastposttime = mydate($mybb->settings['timeformat'], $forum['lastpost']);
				$lastposttid = $forum['lastposttid'];
				$lastposter = $forum['lastposter'];
				$lastpostsubject = stripslashes($forum['lastpostsubject']);
				if(strlen($lastpostsubject) > 25) {
					$lastpostsubject = substr($lastpostsubject, 0, 25) . "...";
				}
				eval("\$lastpost = \"".$templates->get("forumbit_depth1_forum_lastpost")."\";");
			}
		}
		$posts = $forum['posts'];
		$threads = $forum['threads'];
		if($mybb->settings['showdescriptions'] == "no") {
			$forum['description'] = "";
		}
		eval("\$forums .= \"".$templates->get("usercp_forumsubscriptions_forum")."\";");
	}
	if(!$forums)
	{
		eval("\$forums = \"".$templates->get("usercp_forumsubscriptions_none")."\";");
	}
	eval("\$forumsubscriptions = \"".$templates->get("usercp_forumsubscriptions")."\";");
	outputpage($forumsubscriptions);
} elseif($action == "editsig") {
	if($preview) {
		$sig = $signature;
		$template = "usercp_editsig_preview";
	} else {
		$sig = $mybb->user['signature'];
		$template = "usercp_editsig_current";
	}
	if($sig) {
		$sigpreview = postify(stripslashes($sig), $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		eval("\$signature = \"".$templates->get($template)."\";");
	}
	if($mybb->settings['sigsmilies'] == "yes") {
		$sigsmilies = $lang->on;
	} else {
		$sigsmilies = $lang->off;
	}
	if($mybb->settings['sigmycode'] == "yes") {
		$sigmycode = $lang->on;
	} else {
		$sigmycode = $lang->off;
	}
	if($mybb->settings['sightml'] == "yes") {
		$sightml = $lang->on;
	} else {
		$sightml = $lang->off;
	}
	if($mybb->settings['sigimgcode'] == "yes") {
		$sigimgcode = $lang->on;
	} else {
		$sigmycode = $lang->off;
	}
	$lang->edit_sig_note2 = sprintf($lang->edit_sig_note2, $sigsmilies, $sigmycode, $sigimgcode, $sightml, $mybb->settings['siglength']);
	eval("\$editsig = \"".$templates->get("usercp_editsig")."\";");
	outputpage($editsig);
} elseif($action == "do_editsig") {
	if($mybb->settings['siglength'] != 0 && strlen($signature) > $mybb->settings['siglength'])
	{
		error($lang->sig_too_long);
	}
	if($updateposts == "enable")
	{
		$db->query("UPDATE ".TABLE_PREFIX."posts SET includesig='yes' WHERE uid='".$mybb->user[uid]."'");
	}
	elseif($updateposts == "disable")
	{
		$db->query("UPDATE ".TABLE_PREFIX."posts SET includesig='no' WHERE uid='".$mybb->user[uid]."'");
	}
	$signature = addslashes($signature);
	$db->query("UPDATE ".TABLE_PREFIX."users SET signature='$signature' WHERE uid='".$mybb->user[uid]."'");
	redirect("usercp.php", $lang->redirect_sigupdated);

}
elseif($action == "avatar")
{
	// Get a listing of available galleries
	$gallerylist['default'] = $lang->default_gallery;
	$avatardir = @opendir($mybb->settings['avatardir']);
	while($dir = @readdir($avatardir))
	{
		if(is_dir($mybb->settings['avatardir']."/$dir") && $dir != "." && $dir != "..")
		{
			$gallerylist[$dir] = str_replace("_", " ", $dir);
		}
	}
	@closedir($avatardir);
	natcasesort($gallerylist);
	reset($gallerylist);

	foreach($gallerylist as $dir => $friendlyname)
	{
		if($dir == $gallery)
		{
			$activegallery = $friendlyname;
			$selected = "selected=\"selected\"";
		}
		$galleries .= "<option value=\"$dir\" $selected>$friendlyname</option>\n";
		$selected = "";
	}

	// Check to see if we're in a gallery or not
	if($gallery)
	{
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
		outputpage($avatar);
	}

}
elseif($action == "do_avatar") {
	require "./inc/functions_upload.php";
	if($removeavatar)
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='', avatartype='' WHERE uid='".$mybb->user[uid]."'");
		remove_avatars($mybb->user['uid']);
	}
	elseif($gallery) // Gallery avatar
	{
		if($_POST['gallery'] == "default")
		{
			$avatarpath = addslashes($mybb->settings['avatardir']."/".$_POST['avatar']);
		}
		else
		{
			$avatarpath = addslashes($mybb->settings['avatardir']."/".$_POST['gallery']."/".$_POST['avatar']);
		}
		if(file_exists($avatarpath))
		{
			$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='$avatarpath', avatartype='gallery' WHERE uid='".$mybb->user[uid]."'");
		}
		remove_avatars($mybb->user['uid']);
	}
	elseif($_FILES['avatarupload']['name'])
	{
		if($mybb->usergroup['canuploadavatars'] == "no") {
			nopermission();
		}
		$avatar = upload_avatar();
		if($avatar['error'])
		{
			error($avatar['error']);
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='".$avatar['avatar']."', avatartype='upload' WHERE uid='".$mybb->user[uid]."'");
	}
	else
	{
		$avatarurl = addslashes($_POST['avatarurl']);
		$avatarurl = preg_replace("#script:#i", "", $avatarurl);
		$ext = getextention($avatarurl);
		if(preg_match("#gif|jpg|jpeg|jpe|bmp|png#i", $ext) && $mybb->settings['maxavatardims'] != "")
		{
			list($width, $height) = @getimagesize($avatarurl);
			list($maxwidth, $maxheight) = explode("x", $mybb->settings['maxavatardims']);
			if(($maxwidth && $width > $maxwidth) || ($maxheight && $height > $maxheight))
			{
				$lang->error_avatartoobig = sprintf($lang->error_avatartoobig, $maxwidth, $maxheight);
				error($lang->error_avatartoobig);
			}
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET avatar='$avatarurl', avatartype='remote' WHERE uid='".$mybb->user[uid]."'");
		remove_avatars($mybb->user['uid']);
	}
	redirect("usercp.php", $lang->redirect_avatarupdated);
}
elseif($action == "notepad")
{
	eval("\$notepad = \"".$templates->get("usercp_notepad")."\";");
	outputpage($notepad);
}
elseif($action == "do_notepad")
{
	$notepad = addslashes($notepad);
	$db->query("UPDATE ".TABLE_PREFIX."users SET notepad='$notepad' WHERE uid='".$mybb->user[uid]."'");
	redirect("usercp.php", $lang->redirect_notepadupdated);
}
elseif($action == "editlists")
{
	$buddyarray = explode(",", $mybb->user['buddylist']);
	if(is_array($buddyarray)) {
		while(list($key, $buddyid) = each($buddyarray)) {
			$buddysql .= "$comma'$buddyid'";
			$comma = ",";
		}
		$query = $db->query("SELECT username, uid FROM ".TABLE_PREFIX."users WHERE uid IN ($buddysql)");
		while($buddy = $db->fetch_array($query)) {
			$uid = $buddy['uid'];
			$username = $buddy['username'];
			eval("\$buddylist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	$ignorearray = explode(",", $mybb->user['ignorelist']);
	if(is_array($ignorearray)) {
		while(list($key, $ignoreid) = each($ignorearray)) {
			$ignoresql .= "$comma2'$ignoreid'";
			$comma2 = ",";
		}
		$query = $db->query("SELECT username, uid FROM ".TABLE_PREFIX."users WHERE uid IN ($ignoresql)");
		while($ignoreuser = $db->fetch_array($query)) {
			$uid = $ignoreuser['uid'];
			$username = $ignoreuser['username'];
			eval("\$ignorelist .= \"".$templates->get("usercp_editlists_user")."\";");
		}
	}
	for($i=1;$i<=2;$i++) {
		$uid = "new$i";
		$username = "";
		eval("\$newlist .= \"".$templates->get("usercp_editlists_user")."\";");
	}
	eval("\$listpage = \"".$templates->get("usercp_editlists")."\";");
	outputpage($listpage);
} elseif($action == "do_editlists") {
	while(list($key, $val) = each($listuser)) {
		if(strtoupper($mybb->user['username']) != strtoupper($val)) {
			$val = addslashes($val);
			$users .= "$comma'$val'";
			$comma = ",";
		}
	}
	$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username IN ($users)");
	while($user = $db->fetch_array($query)) {
		$newlist .= "$comma2$user[uid]";
		$comma2 = ",";
	}
	$type = $list."list";
	$db->query("UPDATE ".TABLE_PREFIX."users SET $type='$newlist' WHERE uid='".$mybb->user[uid]."'");
	$redirecttemplate = "redirect_".$list."updated";
	redirect("usercp.php?action=editlists", $lang->$redirecttemplate);
}
elseif($action == "drafts")
{
	// Show a listing of all of the current 'draft' posts or threads the user has.
	$query = $db->query("SELECT p.subject, p.pid, t.tid, t.subject AS threadsubject, t.fid, f.name AS forumname, p.dateline, t.visible AS threadvisible, p.visible AS postvisible FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=t.fid) WHERE p.uid='".$mybb->user[uid]."' AND p.visible='-2' ORDER BY p.dateline DESC");
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
	outputpage($draftlist);

}
elseif($action == "do_drafts")
{
	if(!$deletedraft)
	{
		error($lang->no_drafts_selected);
	}
	$pidin = "";
	$tidin = "";
	foreach($deletedraft as $id => $val)
	{
		if($val == "post")
		{
			$pidin[] .= "'$id'";
		}
		elseif($val == "thread")
		{
			$tidin[] .= "'$id'";
		}
	}
	if($tidin)
	{
		$tidin = implode(",", $tidin);
		$db->query("DELETE FROM ".TABLE_PREFIX."threads WHERE tid IN ($tidin) AND visible='-2' AND uid='".$mybb->user[uid]."'");
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
		$db->query("DELETE FROM ".TABLE_PREFIX."posts WHERE ($pidinq $tidinp) AND visible='-2' AND uid='".$mybb->user[uid]."'");
	}
	redirect("usercp.php?action=drafts", $lang->selected_drafts_deleted);
}
elseif($action == "usergroups")
{
	$ingroups = ",".$mybb->user['usergroup'].",".$mybb->user['additionalgroups'].",".$mybb->user['displaygroup'].",";

	// Changing our display group
	if($displaygroup)
	{
		if(!strstr($ingroups, ",$displaygroup,"))
		{
			error($lang->not_member_of_group);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$displaygroup'");
		$dispgroup = $db->fetch_array($query);
		if($dispgroup['candisplaygroup'] != "yes")
		{
			error($lang->cannot_set_displaygroup);
		}
		$db->query("UPDATE ".TABLE_PREFIX."users SET displaygroup='$displaygroup' WHERE uid='$uid'");
		redirect("usercp.php?action=usergroups", $lang->display_group_changed);
		exit;
	}

	// Leaving a group
	if($leavegroup)
	{
		if(!strstr($ingroups, ",$leavegroup,"))
		{
			error($lang->not_member_of_group);
		}
		if($mybb->user['usergroup'] == $leavegroup)
		{
			error($lang->cannot_leave_primary_group);
		}
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$leavegroup'");
		$usergroup = $db->fetch_array($query);
		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_leave_group);
		}
		leave_usergroup($mybb->user['uid'], $leavegroup);
		redirect("usercp.php?action=usergroups", $lang->left_group);
	}

	// Joining a group
	if($joingroup)
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid='$joingroup'");
		$usergroup = $db->fetch_array($query);

		if($usergroup['type'] != 4 && $usergroup['type'] != 3)
		{
			error($lang->cannot_join_group);
		}

		if(strstr($ingroups, ",$joingroup,") || $mybb->user['usergroup'] == $joingroup || $mybb->user['displaygroup'] == $joingroup)
		{
			error($lang->already_member_of_group);
		}

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."joinrequests WHERE uid='".$mybb->user[uid]."' AND gid='$joingroup'");
		$joinrequest = $db->fetch_array($query);
		if($joinrequest['rid'])
		{
			error($lang->already_sent_join_request);
		}
		if($do == "joingroup" && $usergroup['type'] == 4)
		{
			$reason = addslashes($reason);
			$now = time();
			$db->query("INSERT INTO ".TABLE_PREFIX."joinrequests (rid,uid,gid,reason,dateline) VALUES (NULL,'".$mybb->user[uid]."','$joingroup','$reason','$now')");
			redirect("usercp.php?action=usergroups", $lang->group_join_requestsent);
			exit;
		}
		elseif($usergroup['type'] == 4)
		{
			eval("\$joinpage = \"".$templates->get("usercp_usergroups_joingroup")."\";");
			outputpage($joinpage);
		}
		else
		{
			join_usergroup($mybb->user['uid'], $joingroup);
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
	$query = $db->query("SELECT g.title, g.gid, COUNT(u.uid) AS users, COUNT(j.rid) AS joinrequests FROM ".TABLE_PREFIX."groupleaders l LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=l.gid) LEFT JOIN ".TABLE_PREFIX."users u ON (((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')))) LEFT JOIN ".TABLE_PREFIX."joinrequests j ON (j.gid=g.gid) WHERE l.uid='".$mybb->user[uid]."' GROUP BY l.gid");
	while($usergroup = $db->fetch_array($query))
	{
		$memberlistlink = $moderaterequestslink = "";
		if($usergroup['users'] > 0)
		{
			$memberlistlink = " [<a href=\"managegroup.php?gid=".$usergroup['gid']."\">".$lang->view_members."</a>]";
		}
		if($usergroup['joinrequests'] > 0)
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
	eval("\$memberoflist = \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
	$showmemberof = false;

	if($mybb->user['additionalgroups'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups WHERE gid IN (".$mybb->user['additionalgroups'].") AND gid !='".$mybb->user[usergroup]."' ORDER BY title ASC");
		while($usergroup = $db->fetch_array($query))
		{
			$showmemberof = true;
			if($groupleader[$usergroup['gid']])
			{
				$leavelink = "<span class=\"smalltext\"><center>$lang->usergroup_leave_leader</center></span>";
			}
			else
			{
				$leavelink = "<a href=\"usercp.php?action=usergroups&leavegroup=".$usergroup['gid']."\">".$lang->usergroup_leave."</a>";
			}
			if($usergroup['description'])
			{
				$description = "<br /><span class=\"smallfont\">".$usergroup['description']."</span>";
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
			eval("\$memberoflist .= \"".$templates->get("usercp_usergroups_memberof_usergroup")."\";");
		}
		eval("\$membergroups = \"".$templates->get("usercp_usergroups_memberof")."\";");
	}

	// List of groups this user has applied for but has not been accepted in to
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."joinrequests WHERE uid='".$mybb->user[uid]."'");
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
	outputpage($groupmemberships);
}
elseif($action == "attachments")
{
	require "./inc/functions_upload.php";
	$attachments = "";
	$query = $db->query("SELECT a.*, p.subject, p.dateline, t.tid, t.subject AS threadsubject FROM ".TABLE_PREFIX."attachments a LEFT JOIN ".TABLE_PREFIX."posts p ON (a.pid=p.pid) LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) WHERE a.uid='".$mybb->user['uid']."' AND a.pid!='0' ORDER BY p.dateline DESC");
	while($attachment = $db->fetch_array($query))
	{
		if($attachment['dateline'] && $attachment['tid'])
		{
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
	outputpage($manageattachments);
}
elseif($action == "do_attachments")
{
	require "./inc/functions_upload.php";
	if(!is_array($attachments))
	{
		error($lang->no_attachments_selected);
	}

	$aids = addslashes(implode(",", $_POST['attachments']));
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments WHERE aid IN ($aids) AND uid='".$mybb->user['uid']."'");
	while($attachment = $db->fetch_array($query))
	{
		remove_attachment($attachment['pid'], "", $attachment['aid']);
	}
	redirect("usercp.php?action=attachments", $lang->attachments_deleted);
}

else {
	// Get posts per day
	$daysreg = (time() - $mybb->user['regdate']) / (24*3600);
	$perday = $mybb->user['postnum'] / $daysreg;
	$perday = round($perday, 2);
	if($perday > $mybb->user['postnum']) {
		$perday = $mybb->user['postnum'];
	}
	$lang->posts_day = sprintf($lang->posts_day, $perday);
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

	$query = $db->query("SELECT r.*, p.subject, p.tid FROM ".TABLE_PREFIX."reputation r LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=r.pid) WHERE r.uid='".$mybb->user[uid]."' ORDER BY r.dateline DESC LIMIT 0, 10");
	$numreps = $db->num_rows($query);
	$bgclass = "trow1";
	if($numreps) {
		while($reputation = $db->fetch_array($query)) {
			if($reputation['tid']) {
				if(!$reputation['subject']) {
					$reputation['subject'] = "[no subject]";
				}
				$postlink = "<a href=\"showthread.php?tid=$reputation[tid]&pid=$reputation[pid]#pid$reputation[pid]\">$reputation[subject]</a>";
			} else {
				$postlink = $lang->na_deleted;
			}
			$repdate = mydate($mybb->settings['dateformat'], $reputation['dateline']);
			$reptime = mydate($mybb->settings['timeformat'], $reputation['dateline']);
			$reputation['comments'] = stripslashes($reputation['comments']);
			if(strpos(" ".$reputation['reputation'], "-")) { // negative
				$posnegimg = "repbit_neg.gif";
			} else {
				$posnegimg = "repbit_pos.gif";
			}
			eval("\$reputationbits .= \"".$templates->get("usercp_latestreputations_bit")."\";");
			if($bgclass == "trow1") {
				$bgclass = "trow2";
			} else {
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
	eval("\$usercp = \"".$templates->get("usercp")."\";");
	outputpage($usercp);
}
?>