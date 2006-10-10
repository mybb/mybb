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

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("users");

addacpnav($lang->nav_users, "users.php?".SID);
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_user);
		break;
	case "edit":
		addacpnav($lang->nav_edit_user);
		break;
	case "delete":
		addacpnav($lang->nav_delete_user);
		break;
	case "merge":
	case "do_merge":
		addacpnav($lang->nav_merge_accounts);
		break;
	case "email":
		addacpnav($lang->nav_email);
		break;
	case "find":
		addacpnav($lang->nav_find);
		break;
	case "banned":
		addacpnav($lang->nav_banned);
		break;
	case "manageban":
		if($uid)
		{
			addacpnav($lang->nav_edit_ban);
		}
		else
		{
			addacpnav($lang->nav_add_ban);
		}
		break;
}

function date2timestamp($date)
{
	$d = explode('-', $date);
	$nowdate = date("H-j-n-Y");
	$n = explode('-', $nowdate);
	if($n[0] >= 12)
	{
		$n[1] += 1;
	}
	$n[1] += $d[0];
	$n[2] += $d[1];
	$n[3] += $d[2];
	return mktime(0, 0, 0, $n[2], $n[1], $n[3]);
}

function getbanremaining($lifted)
{
	global $lang;
	$remain = $lifted-time();
	$years = intval($remain/31536000);
	$months = intval($remain/2592000);
	$weeks = intval($remain/604800);
	$days = intval($remain/86400);
	$hours = intval($remain/3600);
	if($years > 1)
	{
		$r = "$years $lang->years";
	}
	elseif($years == 1)
	{
		$r = "1 $lang->year";
	}
	elseif($months > 1)
	{
		$r = "$months $lang->months";
	}
	elseif($months == 1)
	{
		$r = "1 $lang->month";
	}
	elseif($weeks > 1)
	{
		$r = "<span class=\"highlight3\">$weeks $lang->weeks</span>";
	}
	elseif($weeks == 1)
	{
		$r = "<span class=\"highlight2\">1 $lang->week</span>";
	}
	elseif($days > 1)
	{
		$r = "<span class=\"highlight2\">$days $lang->days</span>";
	}
	elseif($days == 1)
	{
		$r = "<span class=\"highlight1\">1 $lang->day</span>";
	}
	elseif($days < 1)
	{
		$r = "<span class=\"highlight1\">$hours $lang->hours</span>";
	}
	return $r;
}

function checkbanned()
{
	global $db;
	$time = time();
	$query = $db->simple_select("banned", "*", "lifted<='{$time}' AND lifted!='perm'");
	while($banned = $db->fetch_array($query))
	{
		$db->query("UPDATE ".TABLE_PREFIX."users SET usergroup='{$banned['oldgroup']}', additionalgroups='{$banned['oldadditionalgroups']}', displaygroup='{$banned['olddisplaygroup']}' WHERE uid='{$banned['uid']}'");
		$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='{$banned['uid']}'");
	}
}

function make_profile_field_input($required=0, $uid=0)
{
	global $db, $mybb, $lang;
	if($uid != 0)
	{
		$query = $db->simple_select("userfields", "*", "ufid='$uid'");
		$userfields = $db->fetch_array($query);
	}

	if($required == 1)
	{
		$required = 'yes';
	}
	else
	{
		$required= 'no';
	}
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE required='{$required}' ORDER BY disporder");
	while($profilefield = $db->fetch_array($query))
	{
		$profilefield['type'] = htmlspecialchars_uni(stripslashes($profilefield['type']));
		$thing = explode("\n", $profilefield['type'], "2");
		$type = trim($thing[0]);
		$options = $thing[1];
		$field = "fid$profilefield[fid]";
		if($type == "multiselect")
		{
			$useropts = explode("\n", $userfields[$field]);
			foreach($useropts as $key => $val)
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					$val = trim($val);
					$val = str_replace("\n", "\\n", $val);
					if($val == $seloptions[$val])
					{
						$sel = " selected";
					}
					else
					{
						$sel = '';
					}
					$select .= "<option value=\"$val\"$sel>$val</option>\n";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 3;
				}
				$code = "<select name=\"profile_fields[".$field."][]\" size=\"$profilefield[length]\" multiple=\"multiple\">$select</select>";
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
					if($val == $userfields[$field])
					{
						$sel = " selected";
					}
					else
					{
						$sel = '';
					}
					$select .= "<option value=\"$val\"$sel>$val</option>";
				}
				if(!$profilefield['length'])
				{
					$profilefield['length'] = 1;
				}
				$code = "<select name=\"profile_fields[$field]\" size=\"$profilefield[length]\">$select</select>";
			}
		}
		elseif($type == "radio")
		{
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					if($val == $userfields[$field])
					{
						$checked = " checked";
					}
					else
					{
						$checked = '';
					}
					$code .= "<input type=\"radio\" name=\"profile_fields[$field]\" value=\"$val\"$checked /> $val<br />";
				}
			}
		}
		elseif($type == "checkbox")
		{
			$useropts = explode("\n", $userfields[$field]);
			foreach($useropts as $key => $val)
			{
				$seloptions[$val] = $val;
			}
			$expoptions = explode("\n", $options);
			if(is_array($expoptions))
			{
				foreach($expoptions as $key => $val)
				{
					if($val == $seloptions[$val])
					{
						$checked = " checked";
					}
					else
					{
						$checked = '';
					}
					$code .= "<input type=\"checkbox\" name=\"profile_fields[".$field."][]\" value=\"$val\"$checked /> $val<br />";
				}
			}
		}
		elseif($type == "textarea")
		{
			$value = htmlspecialchars_uni($userfields[$field]);
			$code = "<textarea name=\"profile_fields[$field]\" rows=\"6\" cols=\"50\">$value</textarea>";
		}
		else
		{
			$value = htmlspecialchars_uni($userfields[$field]);
			$code = "<input type=\"text\" name=\"profile_fields[$field]\" length=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\" value=\"$value\" />";
		}
		makelabelcode($profilefield[name], $code);

		$code = '';
		$select = '';
		$val = '';
		$options = '';
		$expoptions = '';
		$useropts = '';
		$seloptions = '';
	}
}
$bantimes["1-0-0"] = "1 $lang->day";
$bantimes["2-0-0"] = "2 $lang->days";
$bantimes["3-0-0"] = "3 $lang->days";
$bantimes["4-0-0"] = "4 $lang->days";
$bantimes["5-0-0"] = "5 $lang->days";
$bantimes["6-0-0"] = "6 $lang->days";
$bantimes["7-0-0"] = "1 $lang->week";
$bantimes["14-0-0"] = "2 $lang->weeks";
$bantimes["21-0-0"] = "3 $lang->weeks";
$bantimes["0-1-0"] = "1 $lang->month";
$bantimes["0-2-0"] = "2 $lang->months";
$bantimes["0-3-0"] = "3 $lang->months";
$bantimes["0-4-0"] = "4 $lang->months";
$bantimes["0-5-0"] = "5 $lang->months";
$bantimes["0-6-0"] = "6 $lang->months";
$bantimes["0-0-1"] = "1 $lang->year";
$bantimes["0-0-2"] = "2 $lang->years";

checkadminpermissions("caneditusers");
logadmin();

$plugins->run_hooks("admin_users_start");

// Process adding of a user.
if($mybb->input['action'] == "do_add")
{

	// Determine the usergroup stuff
	if(is_array($mybb->input['additionalgroups']))
	{
		foreach($mybb->input['additionalgroups'] as $gid)
		{
			if($gid == $mybb->input['usergroup'])
			{
				unset($mybb->input['additionalgroups'][$gid]);
			}
		}
		$additionalgroups = implode(',', $mybb->input['additionalgroups']);
	}
	else
	{
		$additionalgroups = '';
	}

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('insert');

	// Set the data for the new user.
	$user = array(
		"username" => $mybb->input['userusername'],
		"password" => $mybb->input['userpassword'],
		"password2" => $mybb->input['userpassword2'],
		"email" => $mybb->input['useremail'],
		"email2" => $mybb->input['useremail2'],
		"usergroup" => $mybb->input['usergroup'],
		"additionalgroups" => $additionalgroups,
		"displaygroup" => $mybb->input['displaygroup'],
		"usertitle" => $mybb->input['usertitle'],
		"referrer" => $mybb->input['referrername'],
		"timezone" => $mybb->input['timezoneoffset'],
		"language" => $mybb->input['language'],
		"profile_fields" => $mybb->input['profile_fields'],
		"regip" => $mybb->input['ipaddress'],
		"avatar" => $mybb->input['avatar'],
		"website" => $mybb->input['website'],
		"icq" => $mybb->input['icq'],
		"aim" => $mybb->input['aim'],
		"yahoo" => $mybb->input['yahoo'],
		"msn" => $mybb->input['msn'],
		"style" => $mybb->input['style'],
		"signature" => $mybb->input['signature']
	);

	$user['birthday'] = array(
		"day" => $mybb->input['birthday_day'],
		"month" => $mybb->input['birthday_month'],
		"year" => $mybb->input['birthday_year']
	);

	$user['options'] = array(
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"pmnotify" => $mybb->input['emailpmnotify'],
		"invisible" => $mybb->input['invisible'],
		"dst" => $mybb->input['enabledst']
	);

	$plugins->run_hooks("admin_users_do_add");

	// Set the data of the user in the datahandler.
	$userhandler->set_data($user);
	$errors = '';

	// Validate the user and get any errors that might have occurred.
	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	// If there are errors, show them now.
	if(is_array($errors))
	{
		cperror($errors);
	}
	else
	{
		$user_info = $userhandler->insert_user();
	}

	// Send out activation email when needed.
	if($mybb->input['usergroup'] == 5)
	{
		$activationcode = random_str();
		$now = time();
		$activationarray = array(
			"uid" => $user_info['uid'],
			"dateline" => time(),
			"code" => $activationcode,
			"type" => 'r'
		);
		$db->insert_query("awaitingactivation", $activationarray);
		$emailsubject = sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
		$emailmessage = sprintf($lang->email_activateaccount, $username, $mybb->settings['bbname'], $mybb->settings['bburl'], $uid, $activationcode);
		my_mail($email, $emailsubject, $emailmessage);
	}
	$cache->updatestats();
	cpredirect("users.php?".SID."&lastuid={$user_info['uid']}", $lang->user_added);
}

// Process editing of a user.
if($mybb->input['action'] == "do_edit")
{
	if(is_super_admin($mybb->input['uid']) && $mybb->user['uid'] != $mybb->input['uid'] && !is_super_admin($mybb->user['uid']))
	{
		cperror($lang->cannot_perform_action_super_admin);
	}

	// Determine the usergroup stuff
	if(is_array($mybb->input['additionalgroups']))
	{
		foreach($mybb->input['additionalgroups'] as $gid)
		{
			if($gid == $usergroup)
			{
				unset($mybb->input['additionalgroups'][$gid]);
			}
		}
		$additionalgroups = implode(",", $mybb->input['additionalgroups']);
	}
	else
	{
		$additionalgroups = '';
	}

	// Set up user handler.
	require_once MYBB_ROOT."inc/datahandlers/user.php";
	$userhandler = new UserDataHandler('update');

	// Set the data for the new user.
	$user = array(
		"uid" => $mybb->input['uid'],
		"username" => $mybb->input['userusername'],
		"email" => $mybb->input['useremail'],
		"email2" => $mybb->input['useremail'],
		"usergroup" => $mybb->input['usergroup'],
		"additionalgroups" => $additionalgroups,
		"displaygroup" => $mybb->input['displaygroup'],
		"postnum" => $mybb->input['postnum'],
		"usertitle" => $mybb->input['usertitle'],
		"referrer" => $mybb->input['referrername'],
		"timezone" => $mybb->input['timezoneoffset'],
		"language" => $mybb->input['language'],
		"profile_fields" => $mybb->input['profile_fields'],
		"regip" => $mybb->input['ipaddress'],
		"avatar" => $mybb->input['avatar'],
		"website" => $mybb->input['website'],
		"icq" => $mybb->input['icq'],
		"aim" => $mybb->input['aim'],
		"yahoo" => $mybb->input['yahoo'],
		"msn" => $mybb->input['msn'],
		"style" => $mybb->input['style'],
		"signature" => $mybb->input['signature']
	);

	if($mybb->input['userpassword'])
	{
		$user['password'] = $mybb->input['userpassword'];
		$user['password2'] = $mybb->input['userpassword'];
	}

	$user['birthday'] = array(
		"day" => $mybb->input['birthday_day'],
		"month" => $mybb->input['birthday_month'],
		"year" => $mybb->input['birthday_year']
	);

	$user['options'] = array(
		"allownotices" => $mybb->input['allownotices'],
		"hideemail" => $mybb->input['hideemail'],
		"emailnotify" => $mybb->input['emailnotify'],
		"receivepms" => $mybb->input['receivepms'],
		"pmpopup" => $mybb->input['pmpopup'],
		"pmnotify" => $mybb->input['emailpmnotify'],
		"invisible" => $mybb->input['invisible'],
		"dst" => $mybb->input['enabledst']
	);

	$plugins->run_hooks("admin_users_do_edit");

	// Set the data of the user in the datahandler.
	$userhandler->set_data($user);
	$errors = '';

	// Validate the user and get any errors that might have occurred.
	if(!$userhandler->validate_user())
	{
		$errors = $userhandler->get_friendly_errors();
	}

	// If there are errors, show them now.
	if(is_array($errors))
	{
		cperror($errors);
	}
	else
	{
		$user_info = $userhandler->update_user();
	}
	$cache->updatestats();

	cpredirect("users.php?".SID."&lastuid={$mybb->input['uid']}", $lang->profile_updated);
}

// Process the deleting of a user.
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		if(is_super_admin($mybb->input['uid']) && $mybb->user['uid'] != $mybb->input['uid'] && !is_super_admin($mybb->user['uid']))
		{
			cperror($lang->cannot_perform_action_super_admin);
		}

		$plugins->run_hooks("admin_users_do_delete");
		$db->query("UPDATE ".TABLE_PREFIX."posts SET uid='0' WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."users WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."userfields WHERE ufid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."privatemessages WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."events WHERE author='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."moderators WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."forumsubscriptions WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."favorites WHERE uid='".intval($mybb->input['uid'])."'");
		$db->query("DELETE FROM ".TABLE_PREFIX."sessions WHERE uid='".intval($mybb->input['uid'])."'");

		// Update forum stats
		$cache->updatestats();

		cpredirect("users.php?".SID, $lang->user_deleted);
	}
	else
	{
		header("Location: users.php?".SID."&123");
	}
}
if($mybb->input['action'] == "do_email")
{
	$conditions = "1=1";

	$search = $mybb->input['search'];

	if($search['username'])
	{
		$conditions .= " AND username LIKE '%".$db->escape_string($search['username'])."%'";
	}
	if(is_array($search['usergroups']))
	{
		$conditions .= " AND (1=0";
		foreach($search['usergroups'] as $group)
		{
			$conditions .= " OR (usergroup='".intval($group)."' OR CONCAT(',',additionalgroups,',') LIKE '%,".intval($group).",%')";
		}
		$conditions .= ")";
	}

	if($search['email'])
	{
		$conditions .= " AND email LIKE '%".$db->escape_string($search['email'])."%'";
	}
	if($search['website'])
	{
		$conditions .= " AND website LIKE '%".$db->escape_string($search['website'])."%'";
	}
	if($search['icq'])
	{
		$conditions .= " AND icq LIKE '%".$db->escape_string($search['icq'])."%'";
	}
	if($search['aim'])
	{
		$conditions .= " AND aim LIKE '%".$db->escape_string($search['aim'])."%'";
	}
	if($search['yahoo'])
	{
		$conditions .= " AND yahoo LIKE '%".$db->escape_string($search['yahoo'])."%'";
	}
	if($search['msn'])
	{
		$conditions .= " AND msn LIKE '%".$db->escape_string($search['msn'])."%'";
	}
	if($search['signature'])
	{
		$conditions .= " AND signature LIKE '%".$db->escape_string($search['signature'])."%'";
	}
	if($search['usertitle'])
	{
		$conditions .= " AND usertitle LIKE '%".$db->escape_string($search['usertitle'])."%'";
	}
	if($search['postsgreater'])
	{
		$conditions .= " AND postnum>".intval($search['postsgreater']);
	}
	if($search['postsless'])
	{
		$conditions .= " AND postnum<".intval($search['postsless']);
	}
	if($search['overridenotice'] != 'yes')
	{
		$conditions .= " AND allownotices!='no'";
	}

	$searchop = $mybb->input['searchop'];
	if(!$searchop['perpage'])
	{
		$searchop['perpage'] = "500";
	}
	if(!$searchop['page'])
	{
		$searchop['page'] = "1";
		$searchop['start'] = "0";
	}
	else
	{
		$searchop['start'] = ($searchop['page']-1) * $searchop['perpage'];
	}
	$searchop['page']++;

	$plugins->run_hooks("admin_users_do_email");

	$query = $db->query("SELECT COUNT(*) AS results FROM ".TABLE_PREFIX."users WHERE $conditions ORDER BY uid");
	$num = $db->fetch_array($query);
	$num['results'] -= $searchop['start'];
	if(!$num['results'])
	{
		cpmessage($lang->error_no_users);
	}
	else
	{
		cpheader();
		starttable();
		tableheader($lang->mass_mail);
		$lang->results_matching = sprintf($lang->results_matching, $num['results']);
		tablesubheader($lang->results_matching);
		$bgcolor = getaltbg();
		echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\">\n";
		@set_time_limit(0);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE $conditions ORDER BY uid LIMIT ".intval($searchop['start']).", ".intval($searchop['perpage']));
    	while($user = $db->fetch_array($query))
		{
			$sendmessage = $searchop['message'];
			$sendmessage = str_replace("{uid}", $user['uid'], $sendmessage);
			$sendmessage = str_replace("{username}", $user['username'], $sendmessage);
			$sendmessage = str_replace("{email}", $user['email'], $sendmessage);
			$sendmessage = str_replace("{bbname}", $mybb->settings['bbname'], $sendmessage);
			$sendmessage = str_replace("{bburl}", $mybb->settings['bburl'], $sendmessage);

			if($searchop['type'] == "html" && $user['email'] != '')
			{
				echo sprintf($lang->email_sent, $user['username']);
			}
			elseif($searchop['type'] == "pm")
			{
				$insert_pm = array(
					'uid' => $user['uid'],
					'toid' => $user['uid'],
					'fromid' => $mybbadmin['uid'],
					'folder' => 1,
					'subject' => $db->escape_string($searchop['subject']),
					'message' => $db->escape_string($sendmessage),
					'dateline' => time(),
					'status' => 0,
					'receipt' => 'no'
				);
				$db->insert_query("privatemessages", $insert_pm);
				echo sprintf($lang->pm_sent, $user['username']);
			}
			elseif($user['email'] != '')
			{
				my_mail($user['email'], $searchop['subject'], $sendmessage, $searchop['from']);
				echo sprintf($lang->email_sent, $user['username']);
			}
			else
			{
				echo sprintf($lang->not_sent, $user['username']);
			}
			echo "<br />";
		}
		echo $lang->done;
		echo "</td>\n</tr>\n";
		endtable();
		startform("users.php", '', "do_email");
		if(is_array($search))
		{
			foreach($search as $key => $val)
			{
				if(is_array($val))
				{
					foreach($val as $subkey => $subval)
					{
						$hiddens .= "\n<input type=\"hidden\" name=\"search[$key][$subkey]\" value=\"$subval\" />";
					}
				}
				else
				{
					$hiddens .= "\n<input type=\"hidden\" name=\"search[$key]\" value=\"$val\" />";
				}
			}
		}
		foreach($searchop as $key => $val)
		{
			$hiddens .= "\n<input type=\"hidden\" name=\"searchop[$key]\" value=\"$val\" />";
		}
		echo $hiddens;
		if($num['results'] > $searchop['perpage'])
		{
			endform($lang->next_page, '');
		}
		cpfooter();
	}
}
if($mybb->input['action'] == "do_do_merge")
{
	if(!$mybb->input['deletesubmit'])
	{
		cpredirect("users.php?".SID."&lmaction=merge", $lang->users_not_merged);
		exit;
	}
	$query = $db->simple_select("users", "*", "username='".$db->escape_string($mybb->input['source'])."'");
	$sourceuser = $db->fetch_array($query);
	if(!$sourceuser['uid'])
	{
		cperror($lang->error_invalid_source);
	}

	$query = $db->simple_select("users", "*", "username='".$db->escape_string($mybb->input['destination'])."'");
	$destuser = $db->fetch_array($query);
	if(!$destuser['uid'])
	{
		cperror($lang->error_invalid_destination);
	}
	$plugins->run_hooks("admin_users_do_do_merge");
	$db->query("UPDATE ".TABLE_PREFIX."adminlog SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."announcements SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."events SET author='".$destuser['uid']."' WHERE author='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."favorites SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."forums SET lastposter='".$destuser['username']."' WHERE lastposter='".$sourceuser['username']."'");
	$db->query("UPDATE ".TABLE_PREFIX."forumsubscriptions SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."moderatorlog SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."moderators SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."pollvotes SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."posts SET uid='".$destuser['uid']."', username='".$destuser['username']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."posts SET edituid='".$destuser['uid']."' WHERE edituid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET toid='".$destuser['uid']."' WHERE toid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."privatemessages SET fromid='".$destuser['uid']."' WHERE fromid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."reputation SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threadratings SET uid='".$destuser['uid']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET uid='".$destuser['uid']."', username='".$destuser['username']."' WHERE uid='".$sourceuser['uid']."'");
	$db->query("UPDATE ".TABLE_PREFIX."threads SET lastposter='".$destuser['username']."', username='".$destuser['username']."' WHERE lastposter='".$sourceuser['username']."'");
	$db->query("DELETE FROM ".TABLE_PREFIX."users WHERE uid='".$sourceuser['uid']."'");
	$db->query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".$sourceuser['uid']."'");
	$query = $db->query("SELECT COUNT(*) AS postnum FROM ".TABLE_PREFIX."posts WHERE uid='".$destuser['uid']."'");
	$num = $db->fetch_array($query);
	$db->query("UPDATE ".TABLE_PREFIX."users SET postnum='".$num['postnum']."' WHERE uid='".$destuser['uid']."'");
	$lang->users_merged = sprintf($lang->users_merged, $sourceuser['username'], $sourceuser['username'], $destuser['username']);
	cpmessage($lang->users_merged);
}
if($mybb->input['action'] == "do_merge")
{
	$query = $db->simple_select("users", "uid, username", "username='".$db->escape_string($mybb->input['source'])."'");
	$sourceuser = $db->fetch_array($query);
	if(!$sourceuser['uid'])
	{
		cperror($lang->error_invalid_source);
	}

	$query = $db->simple_select("users", "uid, username", "username='".$db->escape_string($mybb->input['destination'])."'");
	$destuser = $db->fetch_array($query);
	if(!$destuser['uid'])
	{
		cperror($lang->error_invalid_destination);
	}
	$plugins->run_hooks("admin_users_do_merge");
	$lang->confirm_merge = sprintf($lang->confirm_merge, $sourceuser['username'], $destuser['username'], $sourceuser['username']);
	cpheader();
	startform("users.php", '', "do_do_merge");
	makehiddencode("source", $mybb->input['source']);
	makehiddencode("destination", $mybb->input['destination']);
	starttable();
	tableheader($lang->merge_accounts, '', 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode('no', $lang->no);
	makelabelcode("<div align=\"center\">$lang->confirm_merge<br /><br />$yes$no</div>", '');
	endtable();
	endform();
	cpfooter();
}

// Show add user page
if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_users_add");
	cpheader();
	startform("users.php", '', "do_add", 0);
	starttable();
	tableheader($lang->add_user);
	tablesubheader($lang->required_info);
	makeinputcode($lang->username, "userusername", '', 25, '', $mybb->settings['maxnamelength'], 0);
	makepasswordcode($lang->password, "userpassword", '', 25, 0);
	makepasswordcode($lang->password_confirm, "userpassword2", '', 25, 0);
	makeinputcode($lang->email, "useremail");
	makeinputcode($lang->email_confirm, "useremail2");
	makeselectcode($lang->primary_usergroup, "usergroup", "usergroups", "gid", "title", 2);
	makelabelcode($lang->secondary_usergroups, "<small>".make_usergroup_checkbox_code("additionalgroups")."</small>");
	makeselectcode($lang->display_group, "displaygroup", "usergroups", "gid", "title", 0, "--".$lang->primary_usergroup."--");
	make_profile_field_input(1);
	tablesubheader($lang->optional_info);
	makeinputcode($lang->custom_title, "usertitle");
	makeinputcode($lang->avatar_url, "avatar");
	makeinputcode($lang->website, "website");
	makeinputcode($lang->icq_number, "icq");
	makeinputcode($lang->aim_handle, "aim");
	makeinputcode($lang->yahoo_handle, "yahoo");
	makeinputcode($lang->msn_address, "msn");

	// Add the birthday dropdown.
	$options = array(
		'blank_fields' => true,
		'no_selected_year' => true,
		'years_back' => 100,
		'years_ahead' => '0',
	);
	$birthday_dropdown = build_date_dropdown('birthday', $options);
	makelabelcode($lang->birthday, $birthday_dropdown);
	make_profile_field_input();
	tablesubheader($lang->account_prefs);
	makeyesnocode($lang->invisible_mode, "invisible", 'no');
	makeyesnocode($lang->admin_emails, "allownotices", 'yes');
	makeyesnocode($lang->hide_email, "hideemail", 'no');
	makeyesnocode($lang->email_notify, "emailnotify", 'yes');
	makeyesnocode($lang->enable_pms, "receivepms", 'yes');
	makeyesnocode($lang->pm_popup, "pmpopup", 'yes');
	makeyesnocode($lang->pm_notify, "emailpmnotify", 'yes');
	makeinputcode($lang->time_offset, "timezoneoffset");
	makeselectcode($lang->style, "style", "themes", "tid", "name", 0, $lang->use_default, '', "tid>1");
	maketextareacode($lang->signature, "signature", '', 6, 50);
	endtable();
	endform($lang->add_user, $lang->reset_button);
	cpfooter();
}

// Show edit user page
if($mybb->input['action'] == "edit")
{
	if(is_super_admin($mybb->input['uid']) && $mybb->user['uid'] != $mybb->input['uid'] && !is_super_admin($mybb->user['uid']))
	{
		cperror($lang->cannot_perform_action_super_admin);
	}

	$plugins->run_hooks("admin_users_edit");
	$uid = intval($mybb->input['uid']);
	$query = $db->simple_select("users", "*", "uid='$uid'");
	$user = $db->fetch_array($query);

	$additionalgroups = explode(",", $user['additionalgroups']);

	$lang->modify_user = sprintf($lang->modify_user, $user['username']);

	cpheader();
	starttable();
	makelabelcode("<ul>\n<li><a href=\"users.php?".SID."&amp;action=delete&amp;uid=$uid\">$lang->delete_account</a></li>\n<li><a href=\"users.php?".SID."&amp;action=misc&amp;uid=$uid\">$lang->view_user_stats</a></li>\n</ul>");
	endtable();

	starttable();
	startform("users.php", '', "do_edit", 0);
	makehiddencode("uid", $uid);
	tableheader($lang->modify_user);
	tablesubheader($lang->required_info);
	makeinputcode($lang->username, "userusername", $user['username'], 25, '', $mybb->settings['maxnamelength'], 0);
	makepasswordcode($lang->new_password, "userpassword", '', 25, 0);
	makeinputcode($lang->email, "useremail", $user['email']);
	makeselectcode($lang->primary_usergroup, "usergroup", "usergroups", "gid", "title", $user['usergroup']);
	makelabelcode($lang->secondary_usergroups, "<small>".make_usergroup_checkbox_code("additionalgroups", $additionalgroups)."</small>");
	if(!$user['displaygroup'])
	{
		$user['displaygroup'] = 0;
	}
	makeselectcode($lang->display_group, "displaygroup", "usergroups", "gid", "title", $user['displaygroup'], "--".$lang->primary_usergroup."--");
	makeinputcode($lang->post_count, "postnum", $user['postnum'], 4);
	make_profile_field_input(1, $user['uid']);
	tablesubheader($lang->optional_info);
	makeinputcode($lang->custom_title, "usertitle", $user['usertitle']);
	makeinputcode($lang->avatar_url, "avatar", $user['avatar']);
	makeinputcode($lang->website, "website", $user['website']);
	makeinputcode($lang->icq_number, "icq", $user['icq']);
	makeinputcode($lang->aim_handle, "aim", $user['aim']);
	makeinputcode($lang->yahoo_handle, "yahoo", $user['yahoo']);
	makeinputcode($lang->msn_address, "msn", $user['msn']);

	// Add the birthday dropdown.
	$bday = explode('-', $user['birthday']);
	$options = array(
		'blank_fields' => true,
		'no_selected_year' => true,
		'years_back' => 100,
		'years_ahead' => '0',
		'selected_day' => $bday[0],
		'selected_month' => $bday[1],
		'selected_year' => $bday[2]
	);
	$birthday_dropdown = build_date_dropdown('birthday', $options);
	makelabelcode($lang->birthday, $birthday_dropdown);
	make_profile_field_input(0, $user['uid']);
	tablesubheader($lang->account_prefs);
	makeyesnocode($lang->invisible_mode, "invisible", $user['invisible']);
	makeyesnocode($lang->admin_emails, "allownotices", $user['allownotices']);
	makeyesnocode($lang->hide_email, "hideemail", $user['hideemail']);
	makeyesnocode($lang->email_notify, "emailnotify", $user['emailnotify']);
	makeyesnocode($lang->enable_pms, "receivepms", $user['receivepms']);
	makeyesnocode($lang->pm_popup, "pmpopup", $user['pmpopup']);
	makeyesnocode($lang->pm_notify, "pmnotify", $user['pmnotify']);
	makeinputcode($lang->time_offset, "timezoneoffset", $user['timezone']);
	if(!$user['style'])
	{
		$user['style'] = 0;
	}
	makeselectcode($lang->style, "style", "themes", "tid", "name", $user['style'], $lang->use_default, '', "tid>1"); 
	maketextareacode($lang->signature, "signature", $user['signature'], 6, 50);
	if(!$user['regip']) { $user['regip'] = "&nbsp;"; }
	makelabelcode($lang->reg_ip, $user['regip']);

	endtable();
	endform($lang->update_user, $lang->reset_button);
}
if($mybb->input['action'] == "delete")
{
	if(is_super_admin($mybb->input['uid']) && $mybb->user['uid'] != $mybb->input['uid'] && !is_super_admin($mybb->user['uid']))
	{
		cperror($lang->cannot_perform_action_super_admin);
	}

	$uid = intval($mybb->input['uid']);
	$query = $db->simple_select("users", "username", "uid='$uid'");
	$user = $db->fetch_array($query);
	$plugins->run_hooks("admin_users_delete");
	$lang->delete_user = sprintf($lang->delete_user, $user['username']);
	$lang->confirm_delete_user = sprintf($lang->confirm_delete_user, $user['username']);
	cpheader();
	startform("users.php", '', "do_delete");
	makehiddencode("uid", $uid);
	starttable();
	tableheader($lang->delete_user, '', 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode('no', $lang->no);
	makelabelcode("<div align=\"center\">$lang->confirm_delete_user<br /><br />$yes$no</div>", '');
	endtable();
	endform();
	cpfooter();
}

if($mybb->input['action'] == "showreferrers")
{
	cpheader();
	$uid = intval($mybb->input['uid']);
	if($uid)
	{
		$query = $db->simple_select("users", "username", "uid='$uid'");
		$user = $db->fetch_array($query);
		$plugins->run_hooks("admin_users_showreferrers");
		$lang->members_referred_by = sprintf($lang->members_referred_by, $user['username']);

		starttable();
		tableheader($lang->members_referred_by, '', 6);
		echo "<tr>\n";
		echo "<td class=\"subheader\">$lang->username</td>\n";
		echo "<td class=\"subheader\">$lang->posts</td>\n";
		echo "<td class=\"subheader\">$lang->email</td>\n";
		echo "<td class=\"subheader\">$lang->reg_date</td>\n";
		echo "<td class=\"subheader\">$lang->last_visit</td>\n";
		echo "</tr>\n";

		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE referrer='$uid' ORDER BY regdate DESC");
		while($refuser = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			$regdate = gmdate("d-m-Y", $refuser['regdate']);
			$lvdate = gmdate("d-m-Y", $refuser['lastvisit']);
			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">$refuser[username]</td>\n";
			echo "<td class=\"$bgcolor\">$refuser[postnum]</td>\n";
			echo "<td class=\"$bgcolor\">$refuser[email]</td>\n";
			echo "<td class=\"$bgcolor\">$regdate</td>\n";
			echo "<td class=\"$bgcolor\">$lvdate</td>\n";
			echo "</tr>\n";
		}
		endtable();
	}
}
if($mybb->input['action'] == "findips")
{
	$plugins->run_hooks("admin_users_findips");
	cpheader();
	$uid = intval($mybb->input['uid']);
	$query = $db->simple_select("users", "uid,username,regip", "uid='$uid'");
	$user = $db->fetch_array($query);
	if (!$user['uid'])
	{
		cperror($lang->error_no_users);
	}
	starttable();
	$lang->ip_addresses_user = sprintf($lang->ip_addresses_user, $user['username']);
	tableheader($lang->ip_addresses_user, '');
	tablesubheader($lang->reg_ip, '');
	if(!empty($user['regip']))
	{
		echo "<tr>\n<td class=\"$bgcolor\" width=\"40%\">$user[regip]</td>\n";
		echo "<td class=\"$bgcolor\" align=\"right\" width=\"60%\"><input type=\"button\" value=\"$lang->find_users_reg_with_ip\" onclick=\"hopto('users.php?".SID."&action=find&search[regip]=$user[regip]');\" class=\"submitbutton\" />  <input type=\"button\" value=\"$lang->find_users_posted_with_ip\" onclick=\"hopto('users.php?".SID."&action=find&search[postip]=$user[regip]');\" class=\"submitbutton\" />";
		echo "</td>\n</tr>\n";
	}
	else
	{
		makelabelcode($lang->error_no_ips, '', 2);
	}
	tablesubheader($lang->post_ip);
	$query = $db->query("SELECT DISTINCT ipaddress FROM ".TABLE_PREFIX."posts WHERE uid='$uid'");
	if($db->num_rows($query) > 0)
	{
		while($row = $db->fetch_array($query))
		{
			if(!empty($row['ipaddress']))
			{
				$bgcolor = getaltbg();
				echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$row[ipaddress]</td>\n";
				echo "<td class=\"$bgcolor\" align=\"right\" width=\"60%\"><input type=\"button\" value=\"$lang->find_users_reg_with_ip\" onclick=\"hopto('users.php?".SID."&action=find&search[regip]=$row[ipaddress]');\" class=\"submitbutton\" />  <input type=\"button\" value=\"$lang->find_users_posted_with_ip\" onclick=\"hopto('users.php?".SID."&action=find&search[postip]=$row[ipaddress]');\" class=\"submitbutton\" />";
				echo "</td>\n</tr>\n";
			}
		}
	}
	else
	{
		makelabelcode($lang->error_no_ips, '', 2);
	}
	endtable();
}
if($mybb->input['action'] == "misc")
{
	$plugins->run_hooks("admin_users_misc");
	cpheader();
	$uid = intval($mybb->input['uid']);
	starttable();
	makelabelcode("<ul>\n
		<li><a href=\"users.php?".SID."&action=showreferrers&uid=$uid\">$lang->show_referred_members</a></li>\n
		<li><a href=\"users.php?".SID."&action=pmstats&uid=$uid\">$lang->pm_stats</a></li>\n
		<li><a href=\"users.php?".SID."&action=stats&uid=$uid\">$lang->general_stats</a></li>\n
		<li><a href=\"users.php?".SID."&action=findips&uid=$uid\">$lang->ip_addresses</a></li>\n
		<li><a href=\"attachments.php?".SID."&action=do_search&uid=$uid\">$lang->show_attachments</a></li>\n
		</ul>");
	endtable();
	cpfooter();
}
if($mybb->input['action'] == "merge")
{
	$plugins->run_hooks("admin_users_merge");
	cpheader();
	startform("users.php", '', "do_merge");
	starttable();
	tableheader($lang->merge_user_accounts);
	tablesubheader($lang->instructions);
	makelabelcode($lang->merge_instructions, '', 2);
	tablesubheader($lang->user_accounts);
	makeinputcode($lang->source_account, "source");
	makeinputcode($lang->dest_account, "destination");
	endtable();
	endform($lang->merge_user_accounts);
	cpfooter();
}
if($mybb->input['action'] == "stats")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->simple_select("users", "*", "uid='$uid'");
	$user = $db->fetch_array($query);
	$lang->general_user_stats = sprintf($lang->general_user_stats, $user['username']);

	$daysreg = (time() - $user['regdate']) / (24*3600);
	$ppd = $user['postnum'] / $daysreg;
	$ppd = round($ppd, 2);
	if(!$ppd || $ppd > $user['postnum'])
	{
		$ppd = $user['postnum'];
	}
	$query = $db->simple_select("posts", "COUNT(pid) AS count");
	$posts = $db->fetch_field($query, 'count');
	if($posts == 0)
	{
		$percent = "0%";
	}
	else
	{
		$percent = $user['postnum']*100/$posts;
		$percent = round($percent, 2).'%';
	}

	$query = $db->simple_select("users", "COUNT(*) AS count", "referrer='$user[uid]'");
	$referrals = $db->fetch_field($query, 'count');

	$memregdate = my_date($mybb->settings['dateformat'], $user['regdate']);
	$memlocaldate = gmdate($mybb->settings['dateformat'], time() + ($user['timezone'] * 3600));
	$memlocaltime = gmdate($mybb->settings['timeformat'], time() + ($user['timezone'] * 3600));
	$memlastvisitdate = my_date($mybb->settings['dateformat'], $user['lastvisit']);
	$memlastvisittime = my_date($mybb->settings['timeformat'], $user['lastvisit']);

	if($user['birthday'])
	{
		$membday = explode('-', $user['birthday']);
		if($membday[2])
		{
			$bdayformat = fix_mktime($mybb->settings['dateformat'], $membday[2]);
			$membday = mktime(0, 0, 0, $membday[1], $membday[0], $membday[2]);
			$membdayage = "(" . floor((time() - $membday) / 31557600) . " ".$lang->years_old .")";
			$membday = gmdate($bdayformat, $membday);
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
		$membdayage = $lang->not_specified;
	}
	$plugins->run_hooks("admin_users_stats");
	cpheader();
	starttable();
	tableheader($lang->general_user_stats);
	makelabelcode($lang->reg_date, $memregdate);
	makelabelcode($lang->total_posts, $user['postnum']);
	makelabelcode($lang->posts_per_day, $ppd);
	makelabelcode($lang->percent_tot_posts, $percent);
	makelabelcode($lang->last_visit, "$memlastvisitdate $memlastvisittime");
	makelabelcode($lang->local_time, "$memlocaldate $memlocaltime");
	makelabelcode($lang->age, $membdayage);
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "pmstats")
{
	$uid = intval($mybb->input['uid']);
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid='$uid'");
	$user = $db->fetch_array($query);
	$lang->pm_stats = sprintf($lang->pm_stats, $user['username']);
	$lang->custom_pm_folders = sprintf($lang->custom_pm_folders, $user['username']);

	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='$uid'");
	$pmscount = $db->fetch_array($query);

	$query = $db->simple_select("privatemessages", "COUNT(*) AS newpms", "uid='$uid' AND dateline>$user[lastvisit] AND folder='1'");
	$newpmscount = $db->fetch_array($query);

	$query = $db->simple_select("privatemessages", "COUNT(*) AS unreadpms", "uid='$uid' AND status='0' AND folder='1'");
	$unreadpmscount = $db->fetch_array($query);

	$plugins->run_hooks("admin_users_pmstats");

	cpheader();
	starttable();
	tableheader($lang->pm_stats);
	makelabelcode($lang->total_pms, $pmscount['total']);
	makelabelcode($lang->new_pms, $newpmscount['newpms']);
	makelabelcode($lang->unread_pms, $unreadpmscount['unreadpms']);
	tablesubheader($lang->custom_pm_folders);
	$pmfolders = explode('$%%$', $user['pmfolders']);
	foreach($pmfolders as $key => $folder);
	{
		$folderinfo = explode('**', $folder, 2);
		$query = $db->query("SELECT COUNT(*) AS inthisfolder FROM ".TABLE_PREFIX."privatemessages WHERE uid='$uid' AND folder='$folderinfo[0]'");
		$thecount = $db->fetch_array($query);
		makelabelcode("$folderinfo[1]", "<b>$thecount[inthisfolder]</b> ".$lang->messages);
		$thecount = '';
	}
	endtable();
	cpfooter();

}

if($mybb->input['action'] == "email")
{
	$plugins->run_hooks("admin_users_email");
	if(!$noheader)
	{
		cpheader();
	}
	startform("users.php", '', "do_email");
	starttable();
	tableheader($lang->mass_email_users);
	tablesubheader($lang->email_options);
	makeinputcode($lang->per_page, "searchop[perpage]", "500", "10");
	makeinputcode($lang->from, "searchop[from]", $mybb->settings['adminemail']);
	makeinputcode($lang->subject, "searchop[subject]");
	maketextareacode($lang->message, "searchop[message]", '', 10, 50);
	$typeoptions = "<input type=\"radio\" name=\"searchop[type]\" value=\"email\" checked=\"checked\" /> $lang->normal_email<br />\n";
//	$typeoptions .= "<input type=\"radio\" name=\"searchop[type]\" value=\"html\" /> $lang->html_email<br />\n";
	$typeoptions .= "<input type=\"radio\" name=\"searchop[type]\" value=\"pm\" /> $lang->send_pm<br />\n";
	makelabelcode($lang->send_method, $typeoptions);
	tablesubheader($lang->email_users);
	makeinputcode($lang->name_contains, "search[username]");
	$options = array(
		'order_by' => 'title',
		'order_dir' => 'ASC',
		);
	$query = $db->simple_select("usergroups", "*", '', $options);
	while($usergroup = $db->fetch_array($query))
	{
		$groups[] = "<input type=\"checkbox\" name=\"search[usergroups][]\" value=\"$usergroup[gid]\" /> $usergroup[title]";
	}
	$groups = implode('<br />', $groups);

	makelabelcode($lang->primary_group, "<small>$groups</small>");
	makeinputcode($lang->and_email, "search[email]");
	makeinputcode($lang->and_website, "search[homepage]");
	makeinputcode($lang->and_icq, "search[icq]");
	makeinputcode($lang->and_aim, "search[aim]");
	makeinputcode($lang->and_yahoo, "search[yahoo]");
	makeinputcode($lang->and_msn, "search[msn]");
	makeinputcode($lang->and_sig, "search[signature]");
	makeinputcode($lang->and_title, "search[usertitle]");
	makeinputcode($lang->posts_more, "search[postsgreater]");
	makeinputcode($lang->posts_less, "search[postsless]");
	makeyesnocode($lang->override_notice, "search[overridenotice]", 'no');
	endtable();
	endform($lang->send_mail, $lang->reset_button);
	cpfooter();
}

if($mybb->input['action'] == "find")
{
	$searchdisp = $mybb->input['searchdisp'];
	$search = $mybb->input['search'];
	$searchop = $mybb->input['searchop'];

	$dispcount = count($searchdisp);
	$yescount = 0;
	if($mybb->input['searchdisp'])
	{
		foreach($mybb->input['searchdisp'] as $disp)
		{
			if($disp == 'yes')
			{
				++$yescount;
			}
		}
	}
	if($yescount == 0)
	{
		$searchdisp['username'] = 'yes';
		$searchdisp['ops'] = 'yes';
		$searchdisp['email'] = 'yes';
		$searchdisp['regdate'] = 'yes';
		$searchdisp['lastvisit'] = 'yes';
		$searchdisp['postnum'] = 'yes';
		$dispcount = count($searchdisp);
	}
	$conditions = '1=1';

	if($search['username'])
	{
		$search['username'] = $db->escape_string($search['username']);
		$conditions .= " AND username LIKE '%$search[username]%'";
	}
    if($search['usergroup'])
    {
		// Searching for primary usergroup users
        $search['usergroup'] = intval($search['usergroup']);
        $conditions .= " AND usergroup = '$search[usergroup]'";
    }
    if(is_array($search['additionalgroups']) && count($search['additionalgroups']) > 0)
    {
		// Searching for users in secondary usergroups
		foreach($search['additionalgroups'] as $group)
		{
			$conditions .= " AND CONCAT(',',additionalgroups,',') LIKE '%,".intval($group).",%'";
		}
	}
    if(is_array($search['usergroups']) && count($search['usergroups']) > 0)
    {
		// Searching for users in both primary usergroups and secondary usergroups
		foreach($search['usergroups'] as $group)
		{
			$conditions .= " AND (usergroup='".intval($group)."' OR CONCAT(',',additionalgroups,',') LIKE '%,".intval($group).",%')";
		}
	}
	if($search['email'])
	{
		$search['email'] = $db->escape_string($search['email']);
		$conditions .= " AND email LIKE '%$search[email]%'";
	}
	if($search['website'])
	{
		$search['website'] = $db->escape_string($search['website']);
		$conditions .= " AND website LIKE '%$search[website]%'";
	}
	if($search['icq'])
	{
		$search['icq'] = intval($search['icq']);
		$conditions .= " AND icq LIKE '%$search[icq]%'";
	}
	if($search['aim'])
	{
		$search['aim'] = $db->escape_string($search['aim']);
		$conditions .= " AND aim LIKE '%$search[aim]%'";
	}
	if($search['yahoo'])
	{
		$search['yahoo'] = $db->escape_string($search['yahoo']);
		$conditions .= " AND yahoo LIKE '%$search[yahoo]%'";
	}
	if($search['msn'])
	{
		$search['msn'] = $db->escape_string($search['msn']);
		$conditions .= " AND msn LIKE '%$search[msn]%'";
	}
	if($search['signature'])
	{
		$search['signature'] = $db->escape_string($search['signature']);
		$conditions .= " AND signature LIKE '%$search[signature]%'";
	}
	if($search['usertitle'])
	{
		$search['usertitle'] = $db->escape_string($search['usertitle']);
		$conditions .= " AND usertitle LIKE '%$search[usertitle]%'";
	}
	if($search['postsgreater'])
	{
		$search['postsgreater'] = intval($search['postsgreater']);
		$conditions .= " AND postnum>$search[postsgreater]";
	}
	if($search['postsless'])
	{
		$search['postsless'] = intval($search['postsless']);
		$conditions .= " AND postnum<$search[postsless]";
	}
	if($search['regip'])
	{
		$search['regip'] = $db->escape_string($search['regip']);
		$conditions .= " AND regip LIKE '$search[regip]%'";
	}
	if($search['postip'])
	{
		$search['postip'] = $db->escape_string($search['postip']);
		$query = $db->query("SELECT DISTINCT uid FROM ".TABLE_PREFIX."posts WHERE ipaddress LIKE '$search[postip]%'");
		$uids = ',';
		while($u = $db->fetch_array($query))
		{
			$uids .= $u['uid'] . ',';
		}
		$conditions .= " AND '$uids' LIKE CONCAT('%,',uid,',%')";
	}
	if(is_array($search['profilefields']))
	{
		foreach($search['profilefields'] as $fid => $value)
		{
			if(empty($value))
			{
				continue;
			}
			$fid = "fid".$fid;
			if(is_array($value))
			{
				foreach($value as $condition => $text)
				{
					$conditions .= " AND $fid='".$db->escape_string($condition)."'";
				}
			}
			else
			{
				$conditions .= " AND $fid='".$db->escape_string($value)."'";
			}
		}
	}
	if($listall)
	{
		$conditions = '1=1';
	}
	if(!$searchop['sortby'])
	{
		$searchop['sortby'] = "username";
	}
	$searchop['page'] = intval($searchop['page']);
	$searchop['perpage'] = intval($searchop['perpage']);
	if(!$searchop['perpage'])
	{
		$searchop['perpage'] = '30';
	}
	if(!$searchop['page'])
	{
		$searchop['page'] = '1';
		$searchop['start'] = '0';
	}
	else
	{
		$searchop['start'] = ($searchop['page']-1) * $searchop['perpage'];
	}
	$searchop['page'];

	$plugins->run_hooks("admin_users_find");

	$countquery = "SELECT * FROM ".TABLE_PREFIX."users LEFT JOIN ".TABLE_PREFIX."userfields ON (ufid=uid) WHERE $conditions";
	$query = $db->query($countquery);
	$numusers = $db->num_rows($query);

	$query = $db->query("$countquery ORDER BY $searchop[sortby] $searchop[order] LIMIT $searchop[start], $searchop[perpage]");

	if($numusers == 0)
	{
		cpheader();
		starttable();
		makelabelcode($lang->error_no_users);
		endtable();
		$noheader = 1;
		$mybb->input['action'] = "search";
	}
	else
	{
		$query2 = $db->simple_select("usergroups");
		while($usergroup = $db->fetch_array($query2))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}
		$lang->results_found = sprintf($lang->results_found, $numusers);
		cpheader();
		starttable();
		tableheader($lang->search_results);
		makelabelcode($lang->results_found);
		endtable();
		starttable();
		echo "<tr>\n";

		if($searchdisp['uid'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->uid_header</td>\n";
		}
		if($searchdisp['username'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->name_header</td>\n";
		}
		if($searchdisp['usergroup'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->usergroup</td>\n";
		}
		if($searchdisp['email'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->email</td>\n";
		}
		if($searchdisp['website'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->website</td>\n";
		}
		if($searchdisp['icq'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->icq_number</td>\n";
		}
		if($searchdisp['aim'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->aim_handle</td>\n";
		}
		if($searchdisp['yahoo'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->yahoo_handle</td>\n";
		}
		if($searchdisp['msn'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->msn_address</td>\n";
		}
		if($searchdisp['signature'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->signature</td>\n";
		}
		if($searchdisp['usertitle'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->usertitle</td>\n";
		}
		if($searchdisp['regdate'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->reg_date</td>\n";
		}
		if($searchdisp['lastvisit'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->last_visit</td>\n";
		}
		if($searchdisp['postnum'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->posts</td>\n";
		}
		if($searchdisp['birthday'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->birthday</td>\n";
		}
		if($searchdisp['regip'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->reg_ip</td>\n";
		}
		if($searchdisp['ops'] == 'yes')
		{
			echo "<td class=\"subheader\" align=\"center\">$lang->options</td>\n";
		}
		echo "</tr>\n";

		$options['edit'] = $lang->edit;
		$options['delete'] = $lang->delete;
		$options['manageban'] = $lang->ban;
		$options['showreferrers'] = $lang->show_referred;
		$options['findips'] = $lang->ip_addresses;
		$options['misc'] = $lang->misc_options;
		while($user = $db->fetch_array($query))
		{
			foreach($user as $name => $value)
			{
				$user[$name] = htmlspecialchars_uni($value);
			}
			if($user['usergroup'] == 5)
			{
				$options['activate'] = $lang->activate;
			}
			$bgcolor = getaltbg();
			echo "<tr>\n";
			if($searchdisp['uid'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[uid]</td>\n";
			}
			if($searchdisp['username'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[username]</td>\n";
			}
			if($searchdisp['usergroup'] == 'yes')
			{
				echo "<td class=\"$bgcolor\" align=\"center\">";
				if(isset($usergroups[$user['usergroup']]))
				{
					$group = $usergroups[$user['usergroup']];
					echo "<b>".$group['title']."</b>";
				}
				$additional = explode(",", $user['additionalgroups']);
				if($additional)
				{
					foreach($additional as $othergroup)
					{
						if($othergroup != $user['usergroup'])
						{
							$ugroup = $usergroups[$othergroup];
							echo "<br />".$ugroup['title'];
						}
					}
				}
				echo "</td>\n";
			}
			if($searchdisp['email'] == 'yes')
			{
				echo "<td class=\"$bgcolor\"><a href=\"mailto:$user[email]\">$user[email]</a></td>\n";
			}
			if($searchdisp['website'] == 'yes')
			{
				echo "<td class=\"$bgcolor\"><a href=\"$user[website]\" target=\"_blank\">$user[website]</a></td>\n";
			}
			if($searchdisp['icq'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[icq]</td>\n";
			}
			if($searchdisp['aim'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[aim]</td>\n";
			}
			if($searchdisp['yahoo'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[yahoo]</td>\n";
			}
			if($searchdisp['msn'] == 'yes') 
			{
				echo "<td class=\"$bgcolor\">$user[msn]</td>\n";
			}
			if($searchdisp['signature'] == 'yes')
			{
				$user['signature'] = nl2br($user['signature']);
				echo "<td class=\"$bgcolor\">$user[signature]</td>\n";
			}
			if($searchdisp['usertitle'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[usertitle]</td>\n";
			}
			if($searchdisp['regdate'] == 'yes')
			{
				$date = gmdate("d-m-Y", $user['regdate']);
				echo "<td class=\"$bgcolor\">$date</td>\n";
			}
			if($searchdisp['lastvisit'] == 'yes')
			{
				if(!$user['lastvisit'])
				{
					$date = $lang->never;
				}
				else
				{
					$date = gmdate("d-m-Y", $user['lastvisit']);
				}
				echo "<td class=\"$bgcolor\">$date</td>\n";
			}
			if($searchdisp['postnum'] == 'yes')
			{
				echo "<td class=\"$bgcolor\"><a href=\"../search.php?action=finduser&amp;uid=$user[uid]\">$user[postnum]</a></td>\n";
			}
			if($searchdisp['birthday'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[birthday]</td>\n";
			}
			if($searchdisp['regip'] == 'yes')
			{
				echo "<td class=\"$bgcolor\">$user[regip]</td>\n";
			}
			if($searchdisp['ops'] == 'yes')
			{
				echo "<td class=\"$bgcolor\" align=\"right\">";
				startform("users.php");
				makehiddencode("uid", $user['uid']);
				makehiddencode("auid", $user['uid']);
				echo makehopper("action", $options);
				endform();
				echo"</td>\n";
			}
			echo "</tr>\n";
		}
		endtable();

		// Generate hiddens for form for next/prev pages
		if(is_array($search))
		{
			foreach($search as $key => $val)
			{
				if($key != 'additionalgroups' && $key != "profilefields")
				{
					$hiddens .= "<input type=\"hidden\" name=\"search[$key]\" value=\"$val\" />";
				}
			}
		}
		if(is_array($search['additionalgroups']))
		{
			foreach($search['additionalgroups'] as $key => $val)
			{
				$hiddens .= "<input type=\"hidden\" name=\"search[additionalgroups][]\" value=\"$val\" />";
			}
		}
		if(is_array($search['profilefields']))
		{
			foreach($search['profilefields'] as $fid => $value)
			{
				if(is_array($value))
				{
					foreach($value as $key => $field)
					{
						$hiddens .= "<input type=\"hidden\" name=\"search[profilefields][$fid][$key]\" value=\"".htmlspecialchars($field)."\" />";
					}
				}
				else
				{
					$hiddens .= "<input type=\"hidden\" name=\"search[profilefields][$fid]\" value=\"".htmlspecialchars($value)."\" />";
				}
			}
		}
		foreach($searchop as $key => $val)
		{
			if($key != 'page')
			{
				$hiddens .= "<input type=\"hidden\" name=\"searchop[$key]\" value=\"$val\" />";
			}
		}
		foreach($searchdisp as $key => $val)
		{
			$hiddens .= "<input type=\"hidden\" name=\"searchdisp[$key]\" value=\"$val\" />";
		}

		$first_page_button = $prev_page_button = $next_page_button = $last_page_button = '';
		if($searchop['page'] != 1)
		{
			echo '<div style="float: left">';
			echo "<form action=\"users.php?action=find\" method=\"post\" style=\"display:inline;\">";
			echo $hiddens;
			makehiddencode('adminsid', $admin_session['sid']);
			makehiddencode('searchop[page]', 1);
			echo makebuttoncode('pageact', $lang->firstpage);
			echo '</form>';

			echo "<form action=\"users.php?action=find\" method=\"post\" style=\"display:inline;\">";
			echo $hiddens;
			makehiddencode('adminsid', $admin_session['sid']);
			makehiddencode('searchop[page]', ($searchop['page']-1));
			echo makebuttoncode('pageact', $lang->prevpage);
			echo '</form>';
			echo '</div>';
		}
		if($searchop['page'] != ceil($numusers / $searchop['perpage']))
		{
			echo '<div style="float: right">';
			echo "<form action=\"users.php?action=find\" method=\"post\" style=\"display:inline;\">";
			echo $hiddens;
			makehiddencode('adminsid', $admin_session['sid']);
			makehiddencode('searchop[page]', ($searchop['page']+1));
			echo makebuttoncode('pageact', $lang->nextpage);
			echo '</form>';

			echo "<form action=\"users.php?action=find\" method=\"post\" style=\"display:inline;\">";
			echo $hiddens;
			makehiddencode('adminsid', $admin_session['sid']);
			makehiddencode('searchop[page]', (ceil($numusers / $searchop['perpage'])));
			echo makebuttoncode('pageact', $lang->lastpage);
			echo '</form>';
			echo '</div>';
		}
		echo $first_page_button.$prev_page_button.$next_page_button.$last_page_button;
		cpfooter();
	}
}
if($mybb->input['action'] == "activate")
{
	$plugins->run_hooks("admin_users_activate");
	$query = $db->query("UPDATE ".TABLE_PREFIX."users SET usergroup = '2' WHERE uid='".intval($mybb->input['uid'])."' AND usergroup = '5'");
	cpredirect("users.php?".SID, $lang->activated);
}
if($mybb->input['action'] == "do_manageban")
{
	$plugins->run_hooks("admin_users_do_manageban");
	if($mybb->input['uid'])
	{
		$query = $db->simple_select("banned", "*", "uid='".intval($mybb->input['uid'])."'");
		$ban = $db->fetch_array($query);

		$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);

		if(!$ban['uid'])
		{
			cperror($lang->error_not_banned);
		}
		$bancheck = $ban;

	}
	else
	{
		$query = $db->simple_select("users", "*", "username='".$db->escape_string($mybb->input['username'])."'");
		$user = $db->fetch_array($query);

		if(!$user['uid'])
		{
			cperror($lang->error_not_found);
		}
		$query = $db->simple_select("banned", "*", "uid='$user[uid]'");
		$bancheck = $db->fetch_array($query);
		$uid = $user['uid'];
	}

	if(is_super_admin($user['uid']) && $mybb->user['uid'] != $user['uid'] && !is_super_admin($mybb->user['uid']))
	{
		cperror($lang->cannot_perform_action_super_admin);
	}

	if($mybb->input['liftafter'] == '---')
	{ // permanent ban
		$liftdate = "perm";
		$mybb->input['liftafter'] = 'perm';
	}
	else
	{
		$liftdate = date2timestamp($mybb->input['liftafter']);
	}
	$lang->ban_updated = sprintf($lang->ban_updated, $user['username']);
	$lang->ban_added = sprintf($lang->ban_added, $user['username']);
	$now = time();
	$groupupdate = array(
		"usergroup" => intval($mybb->input['usergroup'])
	);
	$db->update_query("users", $groupupdate, "uid='".$user['uid']."'");
	if($bancheck['uid'])
	{
		$banneduser = array(
			"admin" => $mybbadmin['uid'],
			"dateline" => time(),
			"gid" => intval($mybb->input['gid']),
			"bantime" => $db->escape_string($mybb->input['liftafter']),
			"lifted" => $liftdate,
			"reason" => $db->escape_string($mybb->input['banreason'])
		);

		$db->update_query("banned", $banneduser, "uid='".$user['uid']."'");
		cpredirect("users.php?".SID."&action=banned", $lang->ban_updated);
	}
	else
	{
		$banneduser = array(
			"uid" => $user['uid'],
			"admin" => $mybbadmin['uid'],
			"gid" => $mybb->input['gid'],
			"oldgroup" => $user['usergroup'],
			"dateline" => time(),
			"bantime" => $db->escape_string($mybb->input['liftafter']),
			"lifted" => $liftdate,
			"reason" => $db->escape_string($mybb->input['banreason'])
		);
		$db->insert_query("banned", $banneduser);
		cpredirect("users.php?".SID."&action=banned", $lang->ban_added);
	}
}
if($mybb->input['action'] == "liftban")
{
	$query = $db->simple_select("banned", "*", "uid='".intval($mybb->input['uid'])."'");
	$ban = $db->fetch_array($query);
	$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
	$user = $db->fetch_array($query);
	$plugins->run_hooks("admin_users_liftban");
	$lang->ban_lifted = sprintf($lang->ban_lifted, $user['username']);
	if(!$ban['uid'])
	{
		cperror($lang->error_not_banned);
	}
	$groupupdate = array(
		'usergroup' => $ban['oldgroup'],
		'additionalgroups' => $ban['oldadditionalgroups'],
		'displaygroup' => $ban['olddisplaygroup']
	);
	$db->update_query("users", $groupupdate, "uid='".intval($mybb->input['uid'])."'");
	$db->delete_query("banned", "uid='".intval($mybb->input['uid'])."'");
	cpredirect("users.php?".SID."&action=banned", $lang->ban_lifted);
}
if($mybb->input['action'] == "manageban")
{
	$plugins->run_hooks("admin_users_manageban");
	if($mybb->input['uid'] && !$mybb->input['auid'])
	{ // editing a ban
		$query = $db->simple_select("banned", "*", "uid='".intval($mybb->input['uid'])."'");
		$ban = $db->fetch_array($query);

		$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['uid'])."'");
		$user = $db->fetch_array($query);
		$lang->edit_banning_options = sprintf($lang->edit_banning_options, $user['username']);

		if(!$ban['uid'])
		{
			cperror($lang->error_not_banned);
		}

		cpheader();
		startform("users.php", '', "do_manageban");
		makehiddencode("uid", $mybb->input['uid']);
		starttable();
		tableheader($lang->edit_banning_options);
	}
	else
	{
		if(is_super_admin($mybb->input['auid']) && $mybb->user['uid'] != $mybb->input['auid'] && !is_super_admin($mybb->user['uid']))
		{
			cperror($lang->cannot_perform_action_super_admin);
		}

		$query = $db->simple_select("users", "*", "uid='".intval($mybb->input['auid'])."'");
		$user = $db->fetch_array($query);

		cpheader();
		startform("users.php", '', "do_manageban");
		starttable();
		tableheader($lang->ban_user);
		$ban['bantime'] = '1-0-0';
		makeinputcode($lang->username, "username", $user['username']);
	}
	makeinputcode($lang->ban_reason, "banreason", $ban['reason']);
	makeselectcode($lang->move_banned_group, "usergroup", "usergroups", "gid", "title", $user['usergroup'], '', '', "isbannedgroup='yes'");
	reset($bantimes);
	foreach($bantimes as $time => $title)
	{
		$liftlist .= "<option value=\"$time\"";
		if($time == $ban[bantime])
		{
			$liftlist .= ' selected="selected"';
		}
		$thatime = date("D, jS M Y @ g:ia", date2timestamp($time));
		$liftlist .= ">$title ($thatime)</option>\n";
	}
	if($ban[bantime] == "perm" || $ban[bantime] == "---")
	{
		$permsel = ' selected="selected"';
	}
	makelabelcode($lang->lift_ban_after, "<select name=\"liftafter\">\n$liftlist\n<option value=\"---\"$permsel>$lang->perm_ban</option>\n</select>\n");
	endtable();
	if($uid)
	{
		endform($lang->update_ban_settings, $lang->reset_button);
	}
	else
	{
		endform($lang->ban_user, $lang->reset_button);
	}
	cpfooter();
}
if($mybb->input['action'] == "banned")
{
	$plugins->run_hooks("admin_users_banned");
	checkbanned();
	$query = $db->query("SELECT b.*, a.username AS adminuser, u.username FROM ".TABLE_PREFIX."banned b LEFT JOIN ".TABLE_PREFIX."users u ON (b.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."users a ON (b.admin=a.uid) ORDER BY lifted ASC");
	$numbans = $db->num_rows($query);
	cpheader();
	$hopto[] = "<input type=\"button\" value=\"$lang->ban_user\" onclick=\"hopto('users.php?".SID."&amp;action=manageban');\" class=\"hoptobutton\" />";
	makehoptolinks($hopto);

	starttable();
	tableheader($lang->banned_users, '', 7);
	echo "<tr>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->username</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->banned_by</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->banned_on</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->ban_length</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->lifted_on</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->time_remaining</td>\n";
	echo "<td class=\"subheader\" align=\"center\">$lang->options</td>\n";
	echo "</tr>\n";
	if(!$numbans)
	{
		makelabelcode("<div align=\"center\">$lang->error_no_banned</div>", '', 7);
	}
	else
	{
		while($user = $db->fetch_array($query))
		{
			$bgcolor = getaltbg();
			if($user['lifted'] == 'perm' || $user['lifted'] == '' || $user['bantime'] == 'perm' || $user['bantime'] == '---')
			{
				$banlength = $lang->permanent;
				$timeremaining = '-';
				$liftedon = $lang->never;
			}
			else
			{
				$banlength = $bantimes[$user['bantime']];
				$timeremaining = getbanremaining($user['lifted']);
				$liftedon = my_date($mybb->settings['dateformat'], $user['lifted']);
			}
			$user['banreason'] = htmlspecialchars_uni($user['banreason']);
			$bannedon = my_date($mybb->settings['dateformat'], $user['dateline']);
			echo "<tr title='$user[reason]'>\n";
			echo "<td class=\"$bgcolor\" align=\"center\"><a href=\"users.php?".SID."&amp;action=edit&amp;uid=$user[uid]\">$user[username]</a></td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$user[adminuser]</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$bannedon</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$banlength</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$liftedon</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">$timeremaining</td>\n";
			echo "<td class=\"$bgcolor\" align=\"center\">".makelinkcode("edit", "users.php?".SID."&amp;action=manageban&amp;uid=$user[uid]")." ".makelinkcode("lift", "users.php?".SID."&amp;action=liftban&amp;uid=$user[uid]")."</td>\n";
		}
	}
	endtable();
	cpfooter();
}

if($mybb->input['action'] == "search" || !$mybb->input['action'])
{
	$plugins->run_hooks("admin_users_search");
	if(!$noheader)
	{
		cpheader();
	}
	else
	{
		echo '<br />';
	}

	//If there was a user previously edited, get their username:
	if(isset($mybb->input['lastuid']))
	{
		$last_uid = intval($mybb->input['lastuid']);
		$query = $db->simple_select("users", "username", "uid='$last_uid'");
		$last_user = $db->fetch_array($query);
		$lang->last_edited = sprintf($lang->last_edited, $last_user['username']);
		$last_user['username'] = urlencode($last_user['username']);
		$last_edited = "<li><a href=\"users.php?".SID."&amp;action=find&amp;search[username]=$last_user[username]&amp;searchop[sortby]=regdate&amp;searchop[order]=desc\">".$lang->last_edited."</li>\n";
	}

  startform("users.php", '', "find");
	starttable();
	tableheader($lang->user_management);
	tablesubheader($lang->quick_search_listing);
	makelabelcode("<ul>\n
		$last_edited
		<li><a href=\"users.php?".SID."&amp;action=find\">$lang->list_all</a></li>\n
		<li><a href=\"users.php?".SID."&amp;action=find&amp;searchop[sortby]=postnum&amp;searchop[order]=desc\">$lang->list_top_posters</a></li>\n
		<li><a href=\"users.php?".SID."&amp;action=find&amp;searchop[sortby]=regdate&amp;searchop[order]=desc\">$lang->list_new_regs</a></li>\n
		<li><a href=\"users.php?".SID."&amp;action=find&amp;search[usergroups][]=5&amp;searchop[sortby]=regdate&amp;searchop[order]=desc\">$lang->list_awaiting_activation</a></li>\n
		</ul>", '', 2);
	tablesubheader($lang->search_users_where);
	makeinputcode($lang->name_contains, "search[username]");
	makeinputcode($lang->and_email, "search[email]");
	makelabelcode($lang->is_member_of, make_usergroup_checkbox_code("search[usergroups]"));
	makeinputcode($lang->and_website, "search[homepage]");
	makeinputcode($lang->and_icq, "search[icq]");
	makeinputcode($lang->and_aim, "search[aim]");
	makeinputcode($lang->and_yahoo, "search[yahoo]");
	makeinputcode($lang->and_msn, "search[msn]");
	makeinputcode($lang->and_sig, "search[signature]");
	makeinputcode($lang->and_title, "search[usertitle]");
	makeinputcode($lang->posts_more, "search[postsgreater]");
	makeinputcode($lang->posts_less, "search[postsless]");
	makeinputcode($lang->and_reg_ip, "search[regip]");
	makeinputcode($lang->and_post_ip, "search[postip]");

	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields WHERE editable='yes' ORDER BY disporder");
	$profilefields = $db->num_rows($query);

	if($profilefields > 0)
	{
		tablesubheader($lang->custom_profile_fields);
		while($profilefield = $db->fetch_array($query))
		{
			$profilefield['type'] = htmlspecialchars_uni($profilefield['type']);
			$thing = explode("\n", $profilefield['type'], "2");
			$type = $thing[0];
			$options = $thing[1];
			$field = "search[profilefields][{$profilefield['fid']}]";
			$select = $code = '';
			if($type == "multiselect")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions)) {
					$select .= "<option value=\"\">&nbsp;</option>";
					foreach($expoptions as $key => $val)
					{
						$val = trim($val);
						$val = str_replace("\n", "\\n", $val);
						$select .= "<option value=\"$val\">$val</option>\n";
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
					$select .= "<option value=\"\">&nbsp;</option>";
					foreach($expoptions as $key => $val)
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
					foreach($expoptions as $key => $val)
					{
						$code .= "<input type=\"radio\" name=\"$field\" value=\"$val\" /> $val<br />";
					}
				}
			}
			elseif($type == "checkbox")
			{
				$expoptions = explode("\n", $options);
				if(is_array($expoptions))
				{
					foreach($expoptions as $key => $val)
					{
						$code .= "<input type=\"checkbox\" name=\"".$field."[]\" value=\"$val\" /> $val<br />";
					}
				}
			}
			elseif($type == "textarea")
			{
				$code = "<textarea name=\"$field\" rows=\"6\" cols=\"30\" style=\"width: 95%\"></textarea>";
			}
			else
			{
				$code = "<input type=\"text\" name=\"$field\" size=\"$profilefield[length]\" maxlength=\"$profilefield[maxlength]\" />";
			}
			makelabelcode($profilefield['name'], $code);
		}
	}

	tablesubheader($lang->sorting_misc_options);
	$bgcolor = getaltbg();
	echo "<tr>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">$lang->sort_results</td>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\">\n";
	echo "<select name=\"searchop[sortby]\">\n";
	echo "<option value=\"username\">$lang->select_username</option>\n";
	echo "<option value=\"email\">$lang->select_email</option>\n";
	echo "<option value=\"regdate\">$lang->select_reg_date</option>\n";
	echo "<option value=\"lastvisit\">$lang->select_last_visit</option>\n";
	echo "<option value=\"postnum\">$lang->select_posts</option>\n";
	echo "</select>\n";
	echo "<select name=\"searchop[order]\">\n";
	echo "<option value=\"asc\">$lang->sort_asc</option>\n";
	echo "<option value=\"desc\">$lang->sort_desc</option>\n";
	echo "</select>\n</td>\n</tr>\n";
	makeinputcode($lang->results_per_page, "searchop[perpage]", "30");

	tablesubheader($lang->display_options);
	makeyesnocode($lang->display_uid, "searchdisp[uid]", 'no');
	makeyesnocode($lang->display_username, "searchdisp[username]", 'yes');
	makeyesnocode($lang->display_options_2, "searchdisp[ops]", 'yes');
	makeyesnocode($lang->display_group, "searchdisp[usergroup]", 'no');
	makeyesnocode($lang->display_email, "searchdisp[email]", 'yes');
	makeyesnocode($lang->display_website, "searchdisp[website]", 'no');
	makeyesnocode($lang->display_icq, "searchdisp[icq]", 'no');
	makeyesnocode($lang->display_aim, "searchdisp[aim]", 'no');
	makeyesnocode($lang->display_yahoo, "searchdisp[yahoo]", 'no');
	makeyesnocode($lang->display_msn, "searchdisp[msn]", 'no');
	makeyesnocode($lang->display_sig, "searchdisp[signature]", 'no');
	makeyesnocode($lang->display_title, "searchdisp[usertitle]", 'no');
	makeyesnocode($lang->display_reg_date, "searchdisp[regdate]", 'yes');
	makeyesnocode($lang->display_last_visit, "searchdisp[lastvisit]", 'yes');
	makeyesnocode($lang->display_num_posts, "searchdisp[postnum]", 'yes');
	makeyesnocode($lang->display_birthday, "searchdisp[birthday]", 'no');
	makeyesnocode($lang->display_regip, "searchdisp[regip]", 'no');

	endtable();
	endform($lang->search, $lang->reset_button);

	cpfooter();
}
?>