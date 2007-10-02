<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

class session
{
	var $sid = 0;
	var $uid = 0;
	var $ipaddress = '';
	var $useragent = '';
	var $is_spider = false;
	var $logins = 1;
	var $failedlogin = 0;

	/**
	 * Initialize a session
	 */
	function init()
	{
		global $db, $mybb, $cache;

		// Get our visitor's IP.
		$this->ipaddress = get_ip();

		// Find out the user agent.
		$this->useragent = $_SERVER['HTTP_USER_AGENT'];
		if(my_strlen($this->useragent) > 100)
		{
			$this->useragent = my_substr($this->useragent, 0, 100);
		}

		// Attempt to find a session id in the cookies.
		if(isset($_COOKIE['sid']))
		{
			$this->sid = $db->escape_string($_COOKIE['sid']);
			// Load the session
			$query = $db->simple_select("sessions", "*", "sid='{$this->sid}' AND ip='".$db->escape_string($this->ipaddress)."'", array('limit' => 1));
			$session = $db->fetch_array($query);
			if($session['sid'])
			{
				$this->sid = $session['sid'];
				$this->uid = $session['uid'];
				$this->logins = $session['loginattempts'];
				$this->failedlogin = $session['failedlogin'];
			}
			else
			{
				$this->sid = 0;
				$this->uid = 0;
				$this->logins = 1;
				$this->failedlogin = 0;
			}
		}

		// Still no session, fall back
		if(!$this->sid)
		{
			$this->sid = 0;
			$this->uid = 0;
			$this->logins = 1;
			$this->failedlogin = 0;
		}

		// If we have a valid session id and user id, load that users session.
		if($_COOKIE['mybbuser'])
		{
			$logon = explode("_", $_COOKIE['mybbuser'], 2);
			$this->load_user($logon[0], $logon[1]);
		}

		// If no user still, then we have a guest.
		if(!isset($mybb->user['uid']))
		{
			// Detect if this guest is a search engine spider. (bots don't get a cookied session ID so we first see if that's set
			if(!$this->sid)
			{
				$spiders = $cache->read("spiders");
				if(is_array($spiders))
				{
					foreach($spiders as $spider)
					{
						if(my_strpos(my_strtolower($this->useragent), my_strtolower($spider['useragent'])) !== false)
						{
							$this->load_spider($spider['sid']);
						}
					}
				}
			}

			// Still nothing? JUST A GUEST!
			if(!$this->is_spider)
			{
				$this->load_guest();
			}
		}


		// As a token of our appreciation for getting this far (and they aren't a spider), give the user a cookie
		if(!$_COOKIE['sid'] && $this->sid && $this->is_spider != true)
		{
			my_setcookie("sid", $this->sid, -1, true);
		}
	}

	/**
	 * Load a user via the user credentials.
	 *
	 * @param int The user id.
	 * @param string The user's password.
	 */
	function load_user($uid, $password='')
	{
		global $mybb, $db, $time, $lang, $mybbgroups, $session;

		$uid = intval($uid);
		$query = $db->query("
			SELECT u.*, f.*, b.dateline AS bandate, b.lifted AS banlifted, b.oldgroup AS banoldgroup, b.olddisplaygroup as banolddisplaygroup, b.oldadditionalgroups as banoldadditionalgroups
			FROM ".TABLE_PREFIX."users u 
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) 
			LEFT JOIN ".TABLE_PREFIX."banned b ON (b.uid=u.uid) 
			WHERE u.uid='$uid'
			LIMIT 1
		");
		$mybb->user = $db->fetch_array($query);

		// Check the password if we're not using a session
		if($password != $mybb->user['loginkey'] || !$mybb->user['uid'])
		{
			unset($mybb->user);
			$this->uid = 0;
			return false;
		}
		$this->uid = $mybb->user['uid'];

		// Set the logout key for this user
		$mybb->user['logoutkey'] = md5($mybb->user['loginkey']);

		// Sort out the private message count for this user.
		if(($mybb->user['totalpms'] == -1 || $mybb->user['unreadpms'] == -1) && $mybb->settings['enablepms'] != 0) // Forced recount
		{
			$update = 0;
			if($mybb->user['totalpms'] == -1)
			{
				$update += 1;
			}
			if($mybb->user['unreadpms'] == -1)
			{
				$update += 2;
			}

			require_once MYBB_ROOT."inc/functions_user.php";
			$pmcount = update_pm_count('', $update);
			if(is_array($pmcount))
			{
				$mybb->user = array_merge($mybb->user, $pmcount);
			}
		}
		$mybb->user['pms_total'] = $mybb->user['totalpms'];
		$mybb->user['pms_unread'] = $mybb->user['unreadpms'];

		if($mybb->user['lastip'] != $this->ipaddress)
		{
			$lastip_add .= ", lastip='".$db->escape_string($this->ipaddress)."', longlastip='".intval(ip2long($this->ipaddress))."'";
		}

		// If the last visit was over 900 seconds (session time out) ago then update lastvisit.
		$time = TIME_NOW;
		if($time - $mybb->user['lastactive'] > 900)
		{
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='{$mybb->user['lastactive']}', lastactive='$time' {$lastip_add} WHERE uid='{$mybb->user['uid']}'");
			$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			require_once MYBB_ROOT."inc/functions_user.php";
			update_pm_count('', 2);
		}
		else
		{
			$timespent = TIME_NOW - $mybb->user['lastactive'];
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastactive='$time', timeonline=timeonline+$timespent {$lastip_add} WHERE uid='{$mybb->user['uid']}'");
		}

		// Sort out the language and forum preferences.
		if($mybb->user['language'] && $lang->language_exists($mybb->user['language']))
		{
			$mybb->settings['bblanguage'] = $mybb->user['language'];
		}
		if($mybb->user['dateformat'] != "0" || $mybb->user['dateformat'] != '')
		{
			global $date_formats;
			if($date_formats[$mybb->user['dateformat']])
			{
				$mybb->setings['dateformat'] = $date_formats[$mybb->user['dateformat']];
			}
		}

		// Choose time format.
		if($mybb->user['timeformat'] != "0" || $mybb->user['timeformat'] != '')
		{
			global $time_formats;
			if($time_formats[$mybb->user['timeformat']])
			{
				$mybb->setings['timeformat'] = $time_formats[$mybb->user['timeformat']];
			}
		}

		// Find out the threads per page preference.
		if($mybb->user['tpp'])
		{
			$mybb->settings['threadsperpage'] = $mybb->user['tpp'];
		}

		// Find out the posts per page preference.
		if($mybb->user['ppp'])
		{
			$mybb->settings['postsperpage'] = $mybb->user['ppp'];
		}

		// Check if this user is currently banned and if we have to lift it.
		if(!empty($mybb->user['bandate']) && (isset($mybb->user['banlifted']) && !empty($mybb->user['banlifted'])) && $mybb->user['banlifted'] < $time)  // hmmm...bad user... how did you get banned =/
		{
			// must have been good.. bans up :D
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET usergroup='".intval($mybb->user['banoldgroup'])."', additionalgroups='".$mybb->user['oldadditionalgroups']."', displaygroup='".intval($mybb->user['olddisplaygroup'])."' WHERE uid='".$mybb->user['uid']."' LIMIT 1");
			$db->shutdown_query("DELETE FROM ".TABLE_PREFIX."banned WHERE uid='".$mybb->user['uid']."'");
			// we better do this..otherwise they have dodgy permissions
			$mybb->user['usergroup'] = $mybb->user['banoldgroup'];
			$mybb->user['displaygroup'] = $mybb->user['banolddisplaygroup'];
			$mybb->user['additionalgroups'] = $mybb->user['banoldadditionalgroups'];

			$mybbgroups = $mybb->user['usergroup'];
			if($mybb->user['additionalgroups'])
			{
				$mybbgroups .= ','.$mybb->user['additionalgroups'];
			}
		}
		else if(!empty($mybb->user['bandate']) && (empty($mybb->user['banlifted'])  || !empty($mybb->user['banlifted']) && $mybb->user['banlifted'] > $time))
        {
            $mybbgroups = $mybb->user['usergroup'];
        }
        else
        {
			// Gather a full permission set for this user and the groups they are in.
			$mybbgroups = $mybb->user['usergroup'];
			if($mybb->user['additionalgroups'])
			{
				$mybbgroups .= ','.$mybb->user['additionalgroups'];
			}
        }

		$mybb->usergroup = usergroup_permissions($mybbgroups);
		if(!$mybb->user['displaygroup'])
		{
			$mybb->user['displaygroup'] = $mybb->user['usergroup'];
		}

		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}
		
		if(!$mybb->user['usertitle'])
		{
			$mybb->user['usertitle'] = $mybb->usergroup['usertitle'];
		}

		// Update or create the session.
		if(!defined("NO_ONLINE"))
		{
			if($this->sid > 0)
			{
				$this->update_session($this->sid, $mybb->user['uid']);
			}
			else
			{
				$this->create_session($mybb->user['uid']);
			}
		}
		return true;
	}

	/**
	 * Load a guest user.
	 *
	 */
	function load_guest()
	{
		global $mybb, $time, $db, $lang;

		// Set up some defaults
		$time = TIME_NOW;
		$mybb->user['usergroup'] = 1;
		$mybb->user['username'] = '';
		$mybb->user['uid'] = 0;
		$mybbgroups = 1;
		$mybb->user['displaygroup'] = 1;

		// Has this user visited before? Lastvisit need updating?
		if(isset($_COOKIE['mybb']['lastvisit']))
		{
			if(!isset($_COOKIE['mybb']['lastactive']))
			{
				$mybb->user['lastactive'] = $time;
				$_COOKIE['mybb']['lastactive'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastactive'] = intval($_COOKIE['mybb']['lastactive']);
			}
			if($time - $_COOKIE['mybb']['lastactive'] > 900)
			{
				my_setcookie("mybb[lastvisit]", $mybb->user['lastactive']);
				$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastvisit'] = intval($_COOKIE['mybb']['lastactive']);
			}
		}

		// No last visit cookie, create one.
		else
		{
			my_setcookie("mybb[lastvisit]", $time);
			$mybb->user['lastvisit'] = $time;
		}

		// Update last active cookie.
		my_setcookie("mybb[lastactive]", $time);

		// Gather a full permission set for this guest
		$mybb->usergroup = usergroup_permissions($mybbgroups);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		// Update the online data.
		if(!defined("NO_ONLINE"))
		{
			if($this->sid > 0)
			{
				$this->update_session($this->sid);
			}
			else
			{
				$this->create_session();
			}
		}
	}

	/**
	 * Load a search engine spider.
	 *
	 * @param int The ID of the search engine spider
	 */
	function load_spider($spider_id)
	{
		global $mybb, $time, $db, $lang;

		// Fetch the spider preferences from the database
		$query = $db->simple_select("spiders", "*", "sid='{$spider_id}'", array('limit' => 1));
		$spider = $db->fetch_array($query);

		// Set up some defaults
		$time = TIME_NOW;
		$this->is_spider = true;
		if($spider['usergroup'])
		{
			$mybb->user['usergroup'] = $spider['usergroup'];
		}
		else
		{
			$mybb->user['usergroup'] = 1;
		}
		$mybb->user['username'] = '';
		$mybb->user['uid'] = 0;
		$mybb->user['displaygroup'] = $mybb->user['usergroup'];

		// Set spider language
		if($spider['language'] && $lang->language_exists($spider['language']))
		{
			$mybb->settings['bblanguage'] = $spider['language'];
		}

		// Set spider theme
		if($spider['theme'])
		{
			$mybb->user['style'] = $spider['theme'];
		}

		// Gather a full permission set for this spider.
		$mybb->usergroup = usergroup_permissions($mybb->user['usergroup']);
		$mydisplaygroup = usergroup_displaygroup($mybb->user['displaygroup']);
		$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);

		// Update spider last minute (only do so on two minute intervals - decrease load for quick spiders)
		if($spider['lastvisit'] < TIME_NOW-120)
		{
			$updated_spider = array(
				"lastvisit" => TIME_NOW
			);
			$db->update_query("spiders", $updated_spider, "sid='{$spider_id}'", 1);
		}

		// Update the online data.
		if(!defined("NO_ONLINE"))
		{
			$this->sid = "bot=".$spider_id;
			$this->create_session();
		}

	}

	/**
	 * Update a user session.
	 *
	 * @param int The session id.
	 * @param int The user id.
	 */
	function update_session($sid, $uid='')
	{
		global $db;

		// Find out what the special locations are.
		$speciallocs = $this->get_special_locations();
		if($uid)
		{
			$onlinedata['uid'] = $uid;
		}
		else
		{
			$onlinedata['uid'] = 0;
		}
		$onlinedata['time'] = TIME_NOW;
		$onlinedata['location'] = $db->escape_string(get_current_location());
		$onlinedata['useragent'] = $db->escape_string($this->useragent);
		$onlinedata['location1'] = intval($speciallocs['1']);
		$onlinedata['location2'] = intval($speciallocs['2']);
		$onlinedata['nopermission'] = 0;
		$sid = $db->escape_string($sid);

		$db->update_query("sessions", $onlinedata, "sid='{$sid}'", 1);
	}

	/**
	 * Create a new session.
	 *
	 * @param int The user id to bind the session to.
	 */
	function create_session($uid=0)
	{
		global $db;
		$speciallocs = $this->get_special_locations();

		// If there is a proper uid, delete by uid.
		if($uid > 0)
		{
			$db->delete_query("sessions", "uid='{$uid}'", 1);
			$onlinedata['uid'] = $uid;
		}
		// Is a spider - delete all other spider references
		else if($this->is_spider == true)
		{
			$db->delete_query("sessions", "sid='{$this->sid}'", 1);
		}
		// Else delete by ip.
		else
		{
			$db->delete_query("sessions", "ip='".$db->escape_string($this->ipaddress)."'", 1);
			$onlinedata['uid'] = 0;
		}

		// If the user is a search enginge spider, ...
		if($this->is_spider == true)
		{
			$onlinedata['sid'] = $this->sid;
		}
		else
		{
			$onlinedata['sid'] = md5(uniqid(microtime()));
		}
		$onlinedata['time'] = TIME_NOW;
		$onlinedata['ip'] = $db->escape_string($this->ipaddress);
		$onlinedata['location'] = $db->escape_string(get_current_location());
		$onlinedata['useragent'] = $db->escape_string($this->useragent);
		$onlinedata['location1'] = intval($speciallocs['1']);
		$onlinedata['location2'] = intval($speciallocs['2']);
		$onlinedata['nopermission'] = 0;
		$db->insert_query("sessions", $onlinedata);
		$this->sid = $onlinedata['sid'];
		$this->uid = $onlinedata['uid'];
	}

	/**
	 * Find out the special locations.
	 *
	 * @return array Special locations array.
	 */
	function get_special_locations()
	{
		global $mybb;
		$array = array('1' => '', '2' => '');
		if(preg_match("#forumdisplay.php#", $_SERVER['PHP_SELF']) && intval($mybb->input['fid']) > 0)
		{
			$array[1] = intval($mybb->input['fid']);
			$array[2] = '';
		}
		elseif(preg_match("#showthread.php#", $_SERVER['PHP_SELF']) && intval($mybb->input['tid']) > 0)
		{
			global $db;
			$array[2] = intval($mybb->input['tid']);
			$query = $db->simple_select("threads", "fid", "tid='".$array[2]."'", array('limit' => 1));
			$array[1] = $db->fetch_field($query, "fid");
		}
		return $array;
	}
}
?>
