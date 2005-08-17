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
$nosession['avatar'] = 1;
$templatelist = "member_register,error_nousername,error_nopassword,error_passwordmismatch,error_invalidemail,error_usernametaken,error_emailmismatch,error_noemail,redirect_registered";
$templatelist .= ",redirect_loggedout,login,redirect_loggedin,error_invalidusername,error_invalidpassword";
require "./global.php";
require "./inc/functions_post.php";
require "./inc/functions_user.php";

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
		addnav($lang->nav_register);
		break;
	case "activate":
		addnav($lang->nav_activate);
		break;
	case "resendactivation":
		addnav($lang->nav_resendactivation);
		break;
	case "lostpw":
		addnav($lang->nav_lostpw);
		break;
	case "resetpassword":
		addnav($lang->nav_resetpassword);
		break;
	case "login":
		addnav($lang->nav_login);
		break;
	case "emailuser":
		addnav($lang->nav_emailuser);
		break;
	case "rate":
		addnav($lang->nav_rate);
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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE regip='$ipaddress' AND regdate>'$datecut'");
		$regcount = $db->num_rows($query);
		if($regcount >= $mybb->settings['maxregsbetweentime'])
		{
			$lang->error_alreadyregisteredtime = sprintf($lang->error_alreadyregisteredtime, $regcount, $mybb->settings['betweenregstime']);
			error($lang->error_alreadyregisteredtime);
		}
	}
}

if($mybb->input['action'] == "do_register")
{

	$plugins->run_hooks("member_do_register_start");

	$username = $mybb->input['username'];

	// Remove multiple spaces from the username
	$username = preg_replace("#\s{2,}#", " ", $username);

	if(!trim($username))
	{
		$errors[] = $lang->error_nousername;
		$bannedusername = 1;
		$missingname =1;
	}

	//Banned Username Code
	$bannedusernames = explode(" ", $mybb->settings['bannedusernames']);
	if(in_array($username, $bannedusernames))
	{
		$errors[] = $lang->error_bannedusername;
		$bannedusername = 1;
	}
	if(eregi("<", $username) || eregi(">", $username) || eregi("&", $username) && !$bannedusername)
	{
		$errors[] = $lang->error_invalidusername;
		$bannedusername = 1;
	}
	$user2 = str_replace("\\", "", $username);
	if($user2 != $username)
	{
		$errors[] = $lang->error_invalidusername;
		$bannedusername = 1;
	}
	if(($mybb->settings['maxnamelength'] != 0 && strlen($username) > $mybb->settings['maxnamelength']) || ($mybb->settings['minnamelength'] != 0 && strlen($username) < $mybb->settings['minnamelength']) && !$bannedusername && !$missingname)
	{
		$lang->error_username_length = sprintf($lang->error_username_length, $mybb->settings['minnamelength'], $mybb->settings['maxnamelength']);
		$errors[] = $lang->error_username_length;
	}
	$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE username='".addslashes($username)."'");
	if($db->fetch_array($query))
	{
		$errors[] = $lang->error_usernametaken;
	}
	$password = $mybb->input['password'];
	$password2 = $mybb->input['password2'];

	if(!trim($password) && $mybb->settings['regtype'] != "randompass")
	{
		$errors[] = $lang->error_nopassword;
		$badpass = 1;
	}
	if($password != $password2 && $mybb->settings['regtype'] != "randompass" && !$badpass)
	{
		$errors[] = $lang->error_passwordmismatch;
	}
	
	$email = $mybb->input['email'];
	$email2 = $mybb->input['email2'];

	if(!trim($email))
	{
		$errors[] = $lang->error_noemail;
		$bademail = 1;
	}
	$bannedemails = explode(" ", $mybb->settings['emailban']);
	if(is_array($bannedemails) && !$bademail)
	{
		while(list($key, $bannedemail) = each($bannedemails))
		{
			$bannedemail = trim($bannedemail);
			if($bannedemail != "")
			{
				if(strstr("$email", $bannedemail) != "")
				{
					$errors[] = $lang->error_bannedemail;
				}
			}
		}
	}
	if($email != $email2 && !$bademail)
	{
		$errors[] = $lang->error_emailmismatch;
	}
	if(!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email) && !$bademail)
	{
		$errors[] = $lang->error_invalidemail;
	}

	$website = $mybb->input['website'];
	if($website == "http://" || $website == "none")
	{
		$website = "";
	}

	$bday1 = $mybb->input['bday1'];
	$bday2 = $mybb->input['bday2'];
	$bday3 = $mybb->input['bday3'];

	if($bday1 == "" || $bday2 == "")
	{
		$bday = "";
	}
	else
	{
		if(($bday3 >= (date("Y") - 100)) && ($bday3 < date("Y")))
		{
			$bday = "$bday1-$bday2-$bday3";
		}
		else
		{
			$bday = "$bday1-$bday2-";
		}
	}

	if($mybb->input['allownotices'] != "yes")
	{
		$allownotices = "no";
	}
	else
	{
		$allownotices = "yes";
		$allownoticescheck = "checked=\"checked\"";
	}

	if($mybb->input['hideemail'] != "yes")
	{
		$hideemail = "no";
	}
	else
	{
		$hideemail = "yes";
		$hideemailcheck = "checked=\"checked\"";
	}

	if($mybb->input['emailnotify'] != "yes")
	{
		$emailnotify = "no";
	}
	else
	{
		$emailnotify = "yes";
		$emailnotifycheck = "checked=\"checked\"";
	}

	if($mybb->input['receivepms'] != "yes")
	{
		$receivepms = "no";
	}
	else
	{
		$receivepms = "yes";
		$receivepmscheck = "checked=\"checked\"";
	}

	if($mybb->input['pmpopup'] != "yes")
	{
		$pmpopup = "no";
	}
	else
	{
		$pmpopup = "yes";
		$pmpopupcheck = "checked=\"checked\"";
	}

	if($mybb->input['emailpmnotify'] != "yes")
	{
		$emailpmnotify = "no";
	}
	else
	{
		$emailpmnotify = "yes";
		$emailpmnotifycheck = "checked=\"checked\"";
	}

	if($mybb->input['invisible'] != "yes")
	{
		$invisible = "no";
	}
	else
	{
		$invisible = "yes";
		$invisiblecheck = "checked=\"checked\"";
	}
	
	if($mybb->input['enabledst'] != "yes")
	{
		$enabledst = "no";
	}
	else
	{
		$enabledst = "yes";
		$enabledstcheck = "checked=\"checked\"";
	}

	if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin")
	{
		$usergroup = 5;
	}
	else
	{
		$usergroup = 2;
	}
	$style = "";
	// Custom profile fields baby!
	$userfields = array();
	$comma = "";
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
		$thing = explode("\n", $profilefield['type'], "2");
		$type = trim($thing[0]);
		$field = "fid$profilefield[fid]";
		if(!$mybb->input[$field] && $profilefield['required'] == "yes" && !$proferror)
		{
			$errors[] = $lang->error_missingrequiredfield;
			$proferror = 1;
		}
		$options = "";
		if($type == "multiselect" || $type == "checkbox")
		{
			if(is_array($mybb->input[$field]))
			{
				while(list($key, $val) = each($mybb->input[$field]))
				{
					if($options)
					{
						$options .= "\n";
					}
					$options .= "$val";
				}
			}
		}
		else
		{
			$options = $mybb->input[$field];
		}
		$userfields[$field] = $options;
		$comma = ",";
	}

	if($mybb->settings['usereferrals'] == "yes" && !$mybb->user['uid'])
	{
		if($mybb->input['referrername'])
		{
			$referrername = addslashes($mybb->input['referrername']);
			$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='$referrername'");
			$referrer = $db->fetch_array($query);
			if(!$referrer['uid'])
			{
				$errors[] = $lang->error_badreferrer;
			}
			$refuid = intval($referrer['uid']);
		}
		$_COOKIE['mybb']['referrer'] = $referrername;
	}
	if($mybb->settings['regimage'] == "on" && function_exists("imagecreatefrompng"))
	{
		$imagehash = addslashes($mybb->input['imagehash']);
		$imagestring = addslashes($mybb->input['imagestring']);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."regimages WHERE imagehash='$imagehash' AND imagestring='$imagestring'");
		$imgcheck = $db->fetch_array($query);
		if(!$imgcheck['dateline'])
		{
			$errors[]  = $lang->error_regimageinvalid;
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."regimages WHERE imagehash='$imagehash'");
	}

	if(is_array($errors))
	{
		$username = htmlspecialchars_uni($mybb->input['username']);
		$email = htmlspecialchars_uni($mybb->input['email']);
		$email2 = htmlspecialchars_uni($mybb->input['email']);
		$referrername = htmlspecialchars_uni($mybb->input['referrername']);
		$regerrors = inlineerror($errors);
		$mybb->input['action'] = "register";
		$fromreg = 1;
	}
	else
	{
		if($mybb->settings['regtype'] == "randompass")
		{
			$password = random_str();
			$md5password = md5($password);
		}
		else
		{
			$md5password = md5($mybb->input['password']);
		}

		//
		// Generate salt, salted password, and login key
		//
		$salt = generate_salt();
		$saltedpw = salt_password($md5password, $salt);
		$loginkey = generate_loginkey();
		
		$timenow = time();
		$newuser = array(
			"uid" => "NULL",
			"username" => addslashes($username),
			"password" => $saltedpw,
			"salt" => $salt,
			"loginkey" => $loginkey,
			"email" => addslashes($email),
			"usergroup" => $usergroup,
			"regdate" => $timenow,
			"lastactive" => $timenow,
			"lastvisit" => $lastvisit,
			"website" => addslashes($website),
			"icq" => intval($mybb->input['icq']),
			"aim" => addslashes($mybb->input['aim']),
			"yahoo" => addslashes($mybb->input['yahoo']),
			"msn" => addslashes($mybb->input['msn']),
			"birthday" => $bday,
			"allownotices" => $allownotices,
			"hideemail" => $hideemail,
			"emailnotify" => $emailnotify,
			"receivepms" => $receivepms,
			"pmpopup" => $pmpopup,
			"pmnotify" => $emailpmnotify,
			"invisible" => $invisible,
			"style" => intval($mybb->input['style']),
			"timezone" => addslashes($mybb->input['timezoneoffset']),
			"dst" => $enabledst,
			"threadmode" => $threadmode,
			"daysprune" => intval($mybb->input['daysprune']),
			"regip" => $ipaddress,
			"language" => addslashes($mybb->input['language']),
			"showcodebuttons" => 1,
			);
		if($mybb->settings['usertppoptions'])
		{
			$newuser['tpp'] = intval($mybb->input['tpp']);
		}
		if($mybb->settings['userpppoptions'])
		{
			$newuser['ppp'] = intval($mybb->input['ppp']);
		}
		if($refuid)
		{
			$newuser['referrer'] = $refuid;
		}

		$plugins->run_hooks("member_do_register_process");

		$db->insert_query(TABLE_PREFIX."users", $newuser);
		$uid = $db->insert_id();
	
		$userfields['ufid'] = $uid;
		$db->insert_query(TABLE_PREFIX."userfields", $userfields);
	
		if(function_exists("accountCreated"))
		{
			accountCreated($uid);
		}

		if($mybb->settings['regtype'] != "randompass")
		{
			// Log them in
			mysetcookie("mybbuser", $uid."_".$loginkey);
		}

		// Update forum stats
		$cache->updatestats();
		if($mybb->settings['regtype'] == "verify")
		{
			$activationcode = random_str();
			$now = time();
			$activationarray = array(
				"aid" => "NULL",
				"uid" => $uid,
				"dateline" => time(),
				"code" => $activationcode,
				"type" => "r"
			);
			$db->insert_query(TABLE_PREFIX."awaitingactivation", $activationarray);
			$emailsubject = sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_activateaccount, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
			mymail($email, $emailsubject, $emailmessage);
			$lang->redirect_registered_activation = sprintf($lang->redirect_registered_activation, $mybb->settings['bbname'], $username);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_activation);
		}
		else if($mybb->settings['regtype'] == "randompass")
		{
			$emailsubject = sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
			$emailmessage = sprintf($lang->email_randompassword, $username, $mybb->settings['bbname'], $username, $password);
			mymail($email, $emailsubject, $emailmessage);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_passwordsent);
		}
		else if($mybb->settings['regtype'] == "admin")
		{
			$lang->redirect_registered_admin_activate = sprintf($lang->redirect_registered_admin_activate, $mybb->settings['bbname'], $username);

			$plugins->run_hooks("member_do_register_end");

			error($lang->redirect_registered_admin_activate);
		}
		else
		{
			$lang->redirect_registered = sprintf($lang->redirect_registered, $mybb->settings['bbname'], $username);

			$plugins->run_hooks("member_do_register_end");

			redirect("index.php", $lang->redirect_registered);
		}
	}
}
if($mybb->input['action'] == "register")
{

	if(!$mybb->input['agree'] && !$mybb->input['regsubmit'])
	{
		$plugins->run_hooks("member_register_agreement");

		eval("\$agreement = \"".$templates->get("member_register_agreement")."\";");
		outputpage($agreement);
	}
	else
	{

		$plugins->run_hooks("member_register_start");

		for($i=1;$i<=31;$i++)
		{
			$bdaydaysel .= "<option value=\"$i\">$i</option>\n";
		}

		if(isset($mybb->input['timezoneoffset']))
		{
			$timezoneoffset = $mybb->input['timezoneoffset']*10;
			$timezoneoffset = str_replace("-", "n", $timezoneoffset);
			$timezoneselect[$timezoneoffset] = "selected";
		}
		else
		{
			$selzone = str_replace("-", "n", $mybb->settings['timezoneoffset']);
			$selzone = str_replace("+", "", $mybb->settings['timezoneoffset']);
			$selzone = $selzone*10;
			$timezoneselect[$selzone] = "selected";
		}
		$timenow = mydate($mybb->settings['timeformat'], time(), "-");
		$lang->time_offset_desc = sprintf($lang->time_offset_desc, $timenow);
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
		$timein['n35'] = mydate($mybb->settings['timeformat'], time(), -3.5);
		$timein['35'] = mydate($mybb->settings['timeformat'], time(), 3.5);
		$timein['45'] = mydate($mybb->settings['timeformat'], time(), 4.5);
		$timein['55'] = mydate($mybb->settings['timeformat'], time(), 5.5);
		$timein['95'] = mydate($mybb->settings['timeformat'], time(), 9.5);
		$timein['105'] = mydate($mybb->settings['timeformat'], time(), 10.5);
		$mybb->user['timezone'] = $tempzone;

		eval("\$tzselect = \"".$templates->get("usercp_options_timezoneselect")."\";");

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."themes WHERE name!='((master))' AND name!='((master-backup))' ORDER BY name ASC");
		while($style = $db->fetch_array($query))
		{
			$style['sid'] = $style['tid'];
			$selected = "";
			eval("\$stylelist .= \"".$templates->get("usercp_options_stylebit")."\";");
		}

		if($mybb->settings['usertppoptions'])
		{
			$explodedtpp = explode(",", $mybb->settings['usertppoptions']);
			if(is_array($explodedtpp))
			{
				while(list($key, $val) = each($explodedtpp))
				{
					$val = trim($val);
					$tppoptions .= "<option value=\"$val\">".sprintf($lang->tpp_option, $val)."</option>\n";
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
					$pppoptions .= "<option value=\"$val\">".sprintf($lang->ppp_option, $val)."</option>\n";
				}
			}
			eval("\$pppselect = \"".$templates->get("usercp_options_pppselect")."\";");
		}
		if($mybb->settings['usereferrals'] == "yes" && !$mybb->user['uid'])
		{
			if($_COOKIE['mybb']['referrer'])
			{
				$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='".$_COOKIE['mybb']['referrer']."'");
				$ref = $db->fetch_array($query);
				$referrername = $_COOKIE['mybb']['referrer'];
			}
			elseif($referrer)
			{
				$query = $db->query("SELECT username FROM ".TABLE_PREFIX."users WHERE uid='$referrer[uid]'");
				$ref = $db->fetch_array($query);
				$referrername = $ref['username'];
			}
			elseif($referrername)
			{
				$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='$referrername'");
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
			$referrer = "";
		}
		// Custom profile fields baby!
		$altbg = trow1;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
			$thing = explode("\n", $profilefield['type'], "2");
			$type = trim($thing[0]);
			$options = $thing[1];
			$field = "fid$profilefield[fid]";
			if($type == "multiselect")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					while(list($key, $val) = each($expoptions))
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
					while(list($key, $val) = each($expoptions))
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
					while(list($key, $val) = each($expoptions))
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
					while(list($key, $val) = each($expoptions))
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
			$code = "";
			$select = "";
			$val = "";
			$options = "";
			$expoptions = "";
			$useropts = "";
			$seloptions = "";
		}
		if($requiredfields)
		{
			eval("\$requiredfields = \"".$templates->get("member_register_requiredfields")."\";");
		}

		if(!$fromreg)
		{
			$allownoticescheck = "checked=\"checked\"";
			$hideemailcheck = "";
			$invisiblecheck = "";
			$emailnotifycheck = "";
			$receivepmscheck = "checked=\"checked\"";
		}
		// Spambot registration image thingy
		if($mybb->settings['regimage'] == "on" && function_exists("imagecreatefrompng"))
		{
			$randomstr = random_str();
			$imagehash = md5($randomstr);
			$regimagearray = array(
				"imagehash" => $imagehash,
				"imagestring" => $randomstr,
				"dateline" => time()
				);
			$db->insert_query(TABLE_PREFIX."regimages", $regimagearray);
			eval("\$regimage = \"".$templates->get("member_register_regimage")."\";");
		}
		if($mybb->settings['regtype'] != "randompass")
		{
			eval("\$passboxes = \"".$templates->get("member_register_password")."\";");
		}

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

		$plugins->run_hooks("member_register_end");

		eval("\$registration = \"".$templates->get("member_register")."\";");
		outputpage($registration);
	}
}
elseif($mybb->input['action'] == "activate")
{

	$plugins->run_hooks("member_activate_start");

	if($mybb->input['username'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."'");
		$user = $db->fetch_array($query);
		if(!$user['username'])
		{
			error($lang->error_invalidusername);
		}
		$uid = $user['uid'];
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->input['uid']."'");
		$user = $db->fetch_array($query);
	}
	if($mybb->input['code'] && $user['uid'])
	{
		$mybb->settings['awaitingusergroup'] = "5";
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$user['uid']."' AND (type='r' OR type='e')");
		$activation = $db->fetch_array($query);
		if(!$activation['uid'])
		{
			error($lang->error_alreadyactivated);
		}
		if($activation['code'] != $mybb->input['code'])
		{
			error($lang->error_badactivationcode);
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$user['uid']."' AND (type='r' OR type='e')");
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
				"email" => $activation['misc'],
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
		outputpage($activate);
	}
}
elseif($mybb->input['action'] == "resendactivation")
{
	$plugins->run_hooks("member_resendactivation");

	eval("\$activate = \"".$templates->get("member_resendactivation")."\";");
	outputpage($activate);
}
elseif($mybb->input['action'] == "do_resendactivation")
{
	$plugins->run_hooks("member_do_resendactivation_start");

	$query = $db->query("SELECT u.uid, u.username, u.usergroup, u.email, a.code FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."awaitingactivation a ON (a.uid=u.uid AND a.type='r') WHERE u.email='".addslashes($mybb->input['email'])."'");
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
						"aid" => "NULL",
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
				$emailmessage = sprintf($lang->email_activeateaccount, $user['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user['uid'], $activationcode);
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
	outputpage($lostpw);
}
elseif($mybb->input['action'] == "do_lostpw")
{
	$plugins->run_hooks("member_do_lostpw_start");

	$email = addslashes($email);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE email='".addslashes($mybb->input['email'])."'");
	$numusers = $db->num_rows($query);
	if($numusers < 1)
	{
		error($lang->error_invalidemail);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			$db->query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='$user[uid]' AND type='p'");
			$user['activationcode'] = random_str();
			$now = time();
			$uid = $user['uid'];
			$awaitingarray = array(
				"aid" => "NULL",
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
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['username'])."'");
		$user = $db->fetch_array($query);
		if(!$user['uid'])
		{
			error($lang->error_invalidusername);
		}
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->input['uid']."'");
		$user = $db->fetch_array($query);
	}
	if($code && $user['uid'])
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$user[uid]."' AND type='p'");
		$activation = $db->fetch_array($query);
		$now = time();
		if($activation['code'] != $code)
		{
			error($lang->error_badlostpwcode);
		}
		$db->query("DELETE FROM ".TABLE_PREFIX."awaitingactivation WHERE uid='".$user[uid]."' AND type='p'");
		$username = $user['username'];

		//
		// Generate a new password, then update it
		//
		$password = random_str();
		$logindetails = update_password($user['uid'], $password, $user['salt']);

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
		outputpage($activate);
	}
}
else if($mybb->input['action'] == "login")
{
	$plugins->run_hooks("member_login");

	eval("\$login = \"".$templates->get("member_login")."\";");
	outputpage($login);
}
else if($mybb->input['action'] == "do_login")
{
	$plugins->run_hooks("member_do_login_start");

	if(!username_exists($mybb->input['username']))
	{
		error($lang->error_invalidusername);
	}
	$user = validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if(!$user['uid'])
	{
		error($lang->error_invalidpassword);
	}

	$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE ip='".$session->ipaddress."' AND sid<>'".$session->sid."'");
	$newsession = array(
		"uid" => $user['uid'],
		);
	$db->update_query(TABLE_PREFIX."sessions", $newsession, "sid='".$session->sid."'");
	
	mysetcookie("mybbuser", $user['uid']."_".$user['loginkey']);
	mysetcookie("sid", $session->sid, -1);

	if(function_exists("loggedIn"))
	{
		loggedIn($user['uid']);
	}

	$plugins->run_hooks("member_do_login_end");

	if($mybb->input['url'])
	{
		redirect($mybb->input['url'], $lang->redirect_loggedin);
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
		mysetcookie("mybbuser", "");
		mysetcookie("sid", 0, -1);
		if($mybb->user['uid'])
		{
			$time = time();
			$lastvisit = array(
				"lastactive" => $time-900,
				"lastvisit" => $time,
				);
			$db->update_query(TABLE_PREFIX."users", $lastvisit, "uid='".$mybb->user['uid']."'");
			$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE uid='".$mybb->user['uid']."' OR ip='$ipaddress'");
	
			if(function_exists("loggedOut"))
			{
				loggedOut($mybb->user['uid']);
			}
		}

		$plugins->run_hooks("member_logout_end");

		redirect("index.php", $lang->redirect_loggedout);
	}
	else {
		error($lang->error_notloggedout);
	}
}
elseif($mybb->input['action'] == "profile")
{
	$plugins->run_hooks("member_profile_start");

	if($mybb->usergroup['canviewprofiles'] == "no")
	{
		nopermission();
	}
	if($mybb->input['uid'] == "lastposter")
	{
		if($mybb->input['tid'])
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='".$mybb->input['tid']."' ORDER BY dateline DESC LIMIT 0, 1");
			$post = $db->fetch_array($query);
			$uid = $post['uid'];
		}
		elseif($mybb->input['fid'])
		{
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE INSTR(CONCAT(',',parentlist,','),',".$mybb->input['fid'].",') > 0");
			while($forum = $db->fetch_array($query))
			{
				if($forum['fid'] == $mybb->input['fid'])
				{
					$theforum = $forum;
				}
				$flist .= ",".$forum['fid'];
			}
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE fid IN (0$flist) ORDER BY lastpost DESC LIMIT 0, 1");
			$thread = $db->fetch_array($query);
			$tid = $thread['tid'];
			$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE tid='$tid' ORDER BY dateline DESC LIMIT 0, 1");
			$post = $db->fetch_array($query);
			$uid = $post['uid'];
		}
	}
	else
	{
		if($mybb->input['uid'])
		{
			$uid = $mybb->input['uid'];
		}
		else
		{
			$uid = $mybb->user['uid'];
		}
	}

	$query = $db->query("SELECT u.* FROM ".TABLE_PREFIX."users u WHERE u.uid='$uid'");
	$memprofile = $db->fetch_array($query);

	if(!$memprofile['uid'])
	{
		error($lang->error_nomember);
	}
	$lang->nav_profile = sprintf($lang->nav_profile, $memprofile['username']);
	addnav($lang->nav_profile);

	$lang->users_forum_info = sprintf($lang->users_forum_info, $memprofile['username']);
	$lang->users_contact_details = sprintf($lang->users_contact_details, $memprofile['username']);
	$lang->send_pm = sprintf($lang->send_pm, $memprofile['username']);
	$lang->away_note = sprintf($lang->away_note, $memprofile['username']);
	$lang->users_additional_info = sprintf($lang->users_additional_info, $memprofile['username']);
	$lang->send_user_email = sprintf($lang->send_user_email, $memprofile['username']);
	$lang->users_signature = sprintf($lang->users_signature, $memprofile['username']);
	$lang->send_user_email = sprintf($lang->send_user_email, $memprofile['username']);

	if($memprofile['avatar'])
	{
		$memprofile['avatar'] = htmlspecialchars_uni($memprofile['avatar']);
		$avatar = "<img src=\"$memprofile[avatar]\">";
	}
	else
	{
		$avatar = "";
	}
	if($memprofile['hideemail'] != "no")
	{
		eval("\$sendemail = \"".$templates->get("member_profile_email")."\";");
	}
	else
	{
		$sendemail = "";
	}
	if($memprofile['website'])
	{
		$memprofile['website'] = htmlspecialchars_uni($memprofile['website']);
		$website = "<a href=\"$memprofile[website]\" target=\"_blank\">$memprofile[website]</a>";
	}
	else
	{
		$website = "";
	}
	if($memprofile['signature'])
	{
		$memprofile['signature'] = postify(stripslashes($memprofile['signature']), $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		eval("\$signature = \"".$templates->get("member_profile_signature")."\";");
	}

	$daysreg = (time() - $memprofile['regdate']) / (24*3600);
	$ppd = $memprofile['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if($ppd > $memprofile['postnum'])
	{
		$ppd = $memprofile['postnum'];
	}
	$query = $db->query("SELECT COUNT(pid) FROM ".TABLE_PREFIX."posts");
	$posts = $db->result($query, 0);
	if($posts == 0)
	{
		$percent = "0";
	}
	else
	{
		$percent = $memprofile['postnum']*100/$posts;
		$percent = round($percent, 2);
	}

	$query = $db->query("SELECT COUNT(*) FROM ".TABLE_PREFIX."users WHERE referrer='$memprofile[uid]'");
	$referrals = $db->result($query, 0);

	if($memprofile['icq'] != "0")
	{
		$memprofile['icq'] = stripslashes($memprofile['icq']);
	}
	else
	{
		$memprofile['icq'] = "";
	}
	$memprofile['aim'] = stripslashes($memprofile['aim']);
	$memprofile['yahoo'] = stripslashes($memprofile['yahoo']);
	$memprofile['signature'] = stripslashes($memprofile['signature']);

	if($memprofile['away'] == "yes" && $mybb->settings['allowaway'] != "no")
	{
		$lang->away_note = sprintf($lang->away_note, $memprofile['username']);
		$awaydate = mydate($mybb->settings['dateformat'], $memprofile['awaydate']);
		$memprofile['awayreason'] = htmlspecialchars_uni(stripslashes($memprofile['awayreason']));
		if($memprofile['returndate'] == "")
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

	if($memprofile['lastvisit'])
	{
		$memlastvisitdate = mydate($mybb->settings['dateformat'], $memprofile['lastvisit']);
		$memlastvisitsep = ', ';
		$memlastvisittime = mydate($mybb->settings['timeformat'], $memprofile['lastvisit']);
	}
	else
	{
		$memlastvisitdate = $lang->lastvisit_never;
		$memlastvisitsep = '';
		$memlastvisittime = '';
	}
	
	// Birthday code fix's provided meme
	if($memprofile['birthday'])
	{
		$membday = explode("-", $memprofile['birthday']);
		if($membday[2])
		{
			if($membday[2] < 1970 && strtolower(substr(PHP_OS, 0, 3)) == 'win')
			{
				$w_day = get_weekday($membday[1], $membday[0], $membday[2]);
				$lang->membdayage = sprintf($lang->membdayage, win_years($membday[1], $membday[0], $membday[2]));
				$membdayage = $lang->membdayage;
				$membday = format_bdays($settings['dateformat'], $membday[1], $membday[0], $membday[2], $w_day);
			}
			else
			{
				$bdayformat = fixmktime($mybb->settings['dateformat'], $membday[2]);
				$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
				$lang->membdayage = sprintf($lang->membdayage, floor((time() - $membday) / 31557600));
				$membdayage = $lang->membdayage;
				$membday = gmdate($bdayformat, $membday);
			}
		}
		else
		{
			$membday = mktime(0, 0, 0, $membday[1], $membday[0], 0);
			$membday = gmdate("F j", $membday);
			$membdayage = "";
		}
	}
	else
	{
		$membday = $lang->not_specified;
		$membdayage = "";
	}

	if(!$memprofile['displaygroup'])
	{
		$memprofile['displaygroup'] = $memprofile['usergroup'];
	}
	$displaygroup = usergroup_displaygroup($memprofile['displaygroup']);

	//
	// Get the user's title...
	//
	if($displaygroup['usertitle'])
	{
		$usertitle = $displaygroup['usertitle'];
		$stars = $displaygroup['stars'];
	}
	else
	{
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles ORDER BY posts DESC");
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
	
	if(trim($memprofile['usertitle']) != "")
	{
		$usertitle = $memprofile['usertitle'];
	}
	if(!$starimage)
	{
		$starimage = $displaygroup['starimage'];
	}
	for($i = 0; $i < $stars; $i++)
	{
		$userstars .= "<img src=\"$starimage\" border=\"0\" alt=\"*\" />";
	}


	if(!$memprofile['rating'])
	{
		$ratestars = $lang->not_rated;
	}
	else
	{
		$rateinfo = explode("|", $memprofile['rating']);
		$rating = round($rateinfo[0] / $rateinfo[1]);
		for($i = 1; $i <= $rating; $i++)
		{
			$ratestars .= "<img src=\"$theme[imgdir]/star.gif\" border=\"0\" title=\"$rating out of 5\" />";
		}
	}
	if(!strstr($rateinfo[2], " ".$mybb->user['uid']." ") && $mybb->user['uid'] != 0 && $mybb->usergroup['canratemembers'] != "no" && $mybb->user['uid'] != $memprofile['uid'])
	{
		$ratelink = "[<a href=\"member.php?action=rate&uid=$uid\">$lang->rate_member</a>]";
	}

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields WHERE ufid='$uid'");
	$userfields = $db->fetch_array($query);
	$customfields = "";
	$bgcolor = trow1;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE hidden='no' ORDER BY disporder");
	while($customfield = $db->fetch_array($query))
	{
		$field = "fid$customfield[fid]";
		$useropts = explode("\n", $userfields[$field]);
		$customfieldval = "";
		if(is_array($useropts) && ($customfield['type'] == "multiselect" || $customfield['type'] == "checkbox"))
		{
			while(list($key, $val) = each($useropts))
			{
				$customfieldval .= "$val<br>";
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
		if($bgcolor == "trow2")
		{
			$bgcolor = "trow1";
		}
		else
		{
			$bgcolor = "trow2";
		}
	}
	if($customfields)
	{
		eval("\$profilefields = \"".$templates->get("member_profile_customfields")."\";");
	}
	$memprofile['postnum'] = mynumberformat($memprofile['postnum']);
	$lang->ppd_percent_total = sprintf($lang->ppd_percent_total, mynumberformat($ppd), $percent);
	$formattedname = formatname($memprofile['username'], $memprofile['usergroup'], $memprofile['displaygroup']);
	if($memprofile['timeonline'])
	{
		$timeonline = nice_time($memprofile['timeonline']);
	}
	else
	{
		$timeonline = $lang->none_registered;
	}

	if($mybb->usergroup['cancp'] == "yes")
	{
		eval("\$adminoptions = \"".$templates->get("member_profile_adminoptions")."\";");
	}
	else
	{
		$adminoptions = '';
	}

	$plugins->run_hooks("member_profile_end");

	eval("\$profile = \"".$templates->get("member_profile")."\";");
	outputpage($profile);
}
elseif($mybb->input['action'] == "emailuser")
{
	$plugins->run_hooks("member_emailuser_start");

	if($mybb->usergroup['cansendemail'] == "no")
	{
		nopermission();
	}
	if($mybb->input['uid'])
	{
		$query = $db->query("SELECT username, hideemail FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->input['uid']."'");
		$emailto = $db->fetch_array($query);
		if(!$emailto['username'])
		{
			error($lang->error_invalidpmrecipient);
		}
		if($emailto['hideemail'] != "yes")
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
	outputpage($emailuser);
}
elseif($mybb->input['action'] == "do_emailuser")
{
	$plugins->run_hooks("member_do_emailuser_start");

	if($mybb->usergroup['cansendemail'] == "no")
	{
		nopermission();
	}
	$query = $db->query("SELECT uid, username, email, hideemail FROM ".TABLE_PREFIX."users WHERE username='".addslashes($mybb->input['touser'])."'");
	$emailto = $db->fetch_array($query);
	if(!$emailto['username'])
	{
		error($lang->error_invalidpmrecipient);
	}
	if($emailto['hideemail'] != "yes")
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
		$from = $fromname . " <" . $fromemail . ">";
	}
	else
	{
		$from = $mybb->user['username'] . " <" . $mybb->user['email'] . ">";
	}
	mymail($emailto['email'], $mybb->input['subject'], $mybb->input['message'], $from);

	$plugins->run_hooks("member_do_emailuser_end");

	redirect("member.php?action=profile&uid=$emailto[uid]", $lang->redirect_emailsent);
}
elseif($mybb->input['action'] == "rate" || $mybb->input['action'] == "do_rate")
{
	$plugins->run_hooks("member_rate_start");

	$query = $db->query("SELECT uid, username, rating FROM ".TABLE_PREFIX."users WHERE uid='".$mybb->input['uid']."'");
	$member = $db->fetch_array($query);
	if(!$member['username'])
	{
		error($lang->error_nomember);
	}

	if($mybb->usergroup['canratemembers'] == "no" || $mybb->user['uid'] == 0 || $mybb->user['uid'] == $member['uid'])
	{
		nopermission();
	}
	$rateinfo = explode("|", $member['rating']);
	if(strstr($rateinfo[2], " " . $mybb->user['uid'] . " "))
	{
		error($lang->error_alreadyratedmember);
	}
	if($mybb->input['action'] == "rate")
	{
		$plugins->run_hooks("member_rate_end");
		
		$uid = $mybb->input['uid'];
		eval("\$rate = \"".$templates->get("member_rate")."\";");
		outputpage($rate);
	}
	else
	{
		$uid = $mybb->input['uid'];
		$rating = $mybb->input['rating'];
		if($rating < 1)
		{
			$rating = 1;
		}
		if($rating > 5)
		{
			$rating = 5;
		}

		if(!$member['rating'])
		{
			$newrating = $rating . "|1|" . " " . $mybb->user['uid'] . " ";
		}
		else
		{
			$newrating1 = $rateinfo[0] + $rating;
			$newrating2 = $rateinfo[1] + 1;
			$newrating3 = $rateinfo[2] . $mybb->user['uid'] . " ";
			$newrating = $newrating1 . "|" . $newrating2 . "|" . $newrating3;
		}
		$rating = array(
			"rating" => $newrating,
			);
		$db->update_query(TABLE_PREFIX."users", $rating, "uid='".$uid."'");

		$plugins->run_hooks("member_do_rate_end");

		redirect("member.php?action=profile&uid=$uid", $lang->redirect_memberrated);
	}
}
?>