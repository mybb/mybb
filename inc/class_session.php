<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class session
{
	/**
	 * @var int
	 */
	public $sid = 0;
	/**
	 * @var int
	 */
	public $uid = 0;
	/**
	 * @var string
	 */
	public $ipaddress = '';
	/**
	 * @var string
	 */
	public $packedip = '';
	/**
	 * @var string
	 */
	public $useragent = '';
	/**
	 * @var bool
	 */
	public $is_spider = false;

	/**
	 * Request parameters that are to be ignored for location storage
	 *
	 * @var array
	 */
	public $ignore_parameters = array(
		'my_post_key',
		'logoutkey',
	);

	/**
	 * Initialize a session
	 */
	function init()
	{
		global $db, $mybb, $cache, $plugins;

		// Get our visitor's IP.
		$this->ipaddress = get_ip();
		$this->packedip = my_inet_pton($this->ipaddress);

		// Find out the user agent.
		if(isset($_SERVER['HTTP_USER_AGENT']))
		{
			$this->useragent = $_SERVER['HTTP_USER_AGENT'];
		}

		// Attempt to find a session id in the cookies.
		if(isset($mybb->cookies['sid']) && !defined('IN_UPGRADE'))
		{
			$sid = $db->escape_string($mybb->cookies['sid']);

			// Load the session if not using a bot sid
			if(substr($sid, 3, 1) !== '=')
			{
				$query = $db->simple_select("sessions", "*", "sid='{$sid}'");
				$session = $db->fetch_array($query);
				if(!empty($session) && $session['sid'])
				{
					$this->sid = $session['sid'];
				}
			}
		}

		if(isset($plugins))
		{
			$plugins->run_hooks('pre_session_load', $this);
		}

		// If we have a valid session id and user id, load that users session.
		if(!empty($mybb->cookies['mybbuser']))
		{
			$logon = explode("_", $mybb->cookies['mybbuser'], 2);
			$this->load_user($logon[0], $logon[1]);
		}

		// If no user still, then we have a guest.
		if(!isset($mybb->user['uid']))
		{
			// Detect if this guest is a search engine spider. (bots don't get a cookied session ID so we first see if that's set)
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
		if($this->sid && (!isset($mybb->cookies['sid']) || $mybb->cookies['sid'] != $this->sid) && $this->is_spider != true)
		{
			my_setcookie("sid", $this->sid, -1, true);
		}
	}

	/**
	 * Load a user via the user credentials.
	 *
	 * @param int $uid The user id.
	 * @param string $loginkey The user's loginkey.
	 * @return bool
	 */
	function load_user($uid, $loginkey='')
	{
		global $mybb, $db, $time, $lang, $mybbgroups, $cache;

		$uid = (int)$uid;
		$query = $db->query("
			SELECT u.*, f.*
			FROM ".TABLE_PREFIX."users u
			LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
			WHERE u.uid='$uid'
			LIMIT 1
		");
		$mybb->user = $db->fetch_array($query);

		// Check the password if we're not using a session
		if(empty($loginkey) || $loginkey !== $mybb->user['loginkey'] || !$mybb->user['uid'])
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

		if($mybb->user['lastip'] != $this->packedip && array_key_exists('lastip', $mybb->user) && !defined('IN_UPGRADE'))
		{
			$lastip_add = ", lastip=".$db->escape_binary($this->packedip);
		}
		else
		{
			$lastip_add = '';
		}

		// If the last visit was over 900 seconds (session time out) ago then update lastvisit.
		$time = TIME_NOW;
		if($time - $mybb->user['lastactive'] > 900)
		{
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastvisit='{$mybb->user['lastactive']}', lastactive='$time'{$lastip_add} WHERE uid='{$mybb->user['uid']}'");
			$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			require_once MYBB_ROOT."inc/functions_user.php";
			update_pm_count('', 2);
		}
		else
		{
			$timespent = TIME_NOW - $mybb->user['lastactive'];
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET lastactive='$time', timeonline=timeonline+$timespent{$lastip_add} WHERE uid='{$mybb->user['uid']}'");
		}

		// Sort out the language and forum preferences.
		if($mybb->user['language'] && $lang->language_exists($mybb->user['language']))
		{
			$mybb->settings['bblanguage'] = $mybb->user['language'];
		}
		if($mybb->user['dateformat'] != 0 && $mybb->user['dateformat'] != '')
		{
			global $date_formats;
			if(!empty($date_formats[$mybb->user['dateformat']]))
			{
				$mybb->settings['dateformat'] = $date_formats[$mybb->user['dateformat']];
			}
		}

		// Choose time format.
		if($mybb->user['timeformat'] != 0 && $mybb->user['timeformat'] != '')
		{
			global $time_formats;
			if(!empty($time_formats[$mybb->user['timeformat']]))
			{
				$mybb->settings['timeformat'] = $time_formats[$mybb->user['timeformat']];
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

		// Does this user prefer posts in classic mode?
		if($mybb->user['classicpostbit'])
		{
			$mybb->settings['postlayout'] = 'classic';
		}
		else
		{
			$mybb->settings['postlayout'] = 'horizontal';
		}

		$usergroups = $cache->read('usergroups');

		if(!empty($usergroups[$mybb->user['usergroup']]) && $usergroups[$mybb->user['usergroup']]['isbannedgroup'] == 1)
		{
			$ban = $db->fetch_array(
				$db->simple_select('banned', '*', 'uid='.(int)$mybb->user['uid'], array('limit' => 1))
			);

			if($ban)
			{
				$mybb->user['banned'] = 1;
				$mybb->user['bandate'] = $ban['dateline'];
				$mybb->user['banlifted'] = $ban['lifted'];
				$mybb->user['banoldgroup'] = $ban['oldgroup'];
				$mybb->user['banolddisplaygroup'] = $ban['olddisplaygroup'];
				$mybb->user['banoldadditionalgroups'] = $ban['oldadditionalgroups'];
				$mybb->user['banreason'] = $ban['reason'];
			}
			else
			{
				$mybb->user['banned'] = 0;
			}
		}

		// Check if this user is currently banned and if we have to lift it.
		if(!empty($mybb->user['bandate']) && (isset($mybb->user['banlifted']) && !empty($mybb->user['banlifted'])) && $mybb->user['banlifted'] < $time)  // hmmm...bad user... how did you get banned =/
		{
			// must have been good.. bans up :D
			$db->shutdown_query("UPDATE ".TABLE_PREFIX."users SET usergroup='".(int)$mybb->user['banoldgroup']."', additionalgroups='".$db->escape_string($mybb->user['banoldadditionalgroups'])."', displaygroup='".(int)$mybb->user['banolddisplaygroup']."' WHERE uid='".$mybb->user['uid']."'");
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
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			if(!empty($this->sid))
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
		$mybb->user['additionalgroups'] = '';
		$mybb->user['username'] = '';
		$mybb->user['uid'] = 0;
		$mybbgroups = 1;
		$mybb->user['displaygroup'] = 1;
		$mybb->user['invisible'] = 0;

		// Has this user visited before? Lastvisit need updating?
		if(isset($mybb->cookies['mybb']['lastvisit']))
		{
			if(!isset($mybb->cookies['mybb']['lastactive']))
			{
				$mybb->user['lastactive'] = $time;
				$mybb->cookies['mybb']['lastactive'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastactive'] = (int)$mybb->cookies['mybb']['lastactive'];
			}
			if($time - $mybb->cookies['mybb']['lastactive'] > 900)
			{
				my_setcookie("mybb[lastvisit]", $mybb->user['lastactive']);
				$mybb->user['lastvisit'] = $mybb->user['lastactive'];
			}
			else
			{
				$mybb->user['lastvisit'] = (int)$mybb->cookies['mybb']['lastactive'];
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
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		// Update the online data.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			if(!empty($this->sid))
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
	 * @param int $spider_id The ID of the search engine spider
	 */
	function load_spider($spider_id)
	{
		global $mybb, $time, $db, $lang;

		// Fetch the spider preferences from the database
		$query = $db->simple_select("spiders", "*", "sid='{$spider_id}'");
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
		$mybb->user['additionalgroups'] = '';
		$mybb->user['invisible'] = 0;

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
		if(is_array($mydisplaygroup))
		{
			$mybb->usergroup = array_merge($mybb->usergroup, $mydisplaygroup);
		}

		// Update spider last minute (only do so on two minute intervals - decrease load for quick spiders)
		if($spider['lastvisit'] < TIME_NOW-120)
		{
			$updated_spider = array(
				"lastvisit" => TIME_NOW
			);
			$db->update_query("spiders", $updated_spider, "sid='{$spider_id}'");
		}

		// Update the online data.
		if(!defined("NO_ONLINE") && !defined('IN_UPGRADE'))
		{
			$this->sid = "bot=".$spider_id;
			$this->create_session();
		}

	}

	/**
	 * Update a user session.
	 *
	 * @param int $sid The session id.
	 * @param int $uid The user id.
	 */
	function update_session($sid, $uid=0)
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

		$onlinedata['location'] = $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150));
		$onlinedata['useragent'] = $db->escape_string(my_substr($this->useragent, 0, 200));

		$onlinedata['location1'] = (int)$speciallocs['1'];
		$onlinedata['location2'] = (int)$speciallocs['2'];
		$onlinedata['nopermission'] = 0;
		$sid = $db->escape_string($sid);

		$db->update_query("sessions", $onlinedata, "sid='{$sid}'");
	}

	/**
	 * Create a new session.
	 *
	 * @param int $uid The user id to bind the session to.
	 */
	function create_session($uid=0)
	{
		global $db;
		$speciallocs = $this->get_special_locations();

		// If there is a proper uid, delete by uid.
		if($uid > 0)
		{
			$db->delete_query("sessions", "uid='{$uid}'");
			$onlinedata['uid'] = $uid;
		}
		else
		{
			// Is a spider - delete all other spider references
			if($this->is_spider == true)
			{
				$db->delete_query("sessions", "sid='{$this->sid}'");
			}

			$onlinedata['uid'] = 0;
		}

		// If the user is a search enginge spider, ...
		if($this->is_spider == true)
		{
			$onlinedata['sid'] = $this->sid;
		}
		else
		{
			$onlinedata['sid'] = md5(random_str(50));
		}
		$onlinedata['time'] = TIME_NOW;
		$onlinedata['ip'] = $db->escape_binary($this->packedip);

		$onlinedata['location'] = $db->escape_string(substr(get_current_location(false, $this->ignore_parameters), 0, 150));
		$onlinedata['useragent'] = $db->escape_string(my_substr($this->useragent, 0, 200));

		$onlinedata['location1'] = (int)$speciallocs['1'];
		$onlinedata['location2'] = (int)$speciallocs['2'];
		$onlinedata['nopermission'] = 0;
		$db->replace_query("sessions", $onlinedata, "sid", false);
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
		if(preg_match("#forumdisplay.php#", $_SERVER['PHP_SELF']) && $mybb->get_input('fid', MyBB::INPUT_INT) > 0 && $mybb->get_input('fid', MyBB::INPUT_INT) < 4294967296)
		{
			$array[1] = $mybb->get_input('fid', MyBB::INPUT_INT);
			$array[2] = '';
		}
		elseif(preg_match("#showthread.php#", $_SERVER['PHP_SELF']))
		{
			global $db;

			if($mybb->get_input('tid', MyBB::INPUT_INT) > 0 && $mybb->get_input('tid', MyBB::INPUT_INT) < 4294967296)
			{
				$array[2] = $mybb->get_input('tid', MyBB::INPUT_INT);
			}

			// If there is no tid but a pid, trick the system into thinking there was a tid anyway.
			elseif(isset($mybb->input['pid']) && !empty($mybb->input['pid']))
			{
				$options = array(
					"limit" => 1
				);
				$query = $db->simple_select("posts", "tid", "pid=".$mybb->get_input('pid', MyBB::INPUT_INT), $options);
				$post = $db->fetch_array($query);
				$array[2] = $post['tid'];
			}

			$thread = get_thread($array[2]);
			$array[1] = $thread['fid'];
		}
		return $array;
	}
}
