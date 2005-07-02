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

class MyBB {
	var $settings = array();
	var $db;
	var $cache;


	function connect()
	{
		global $db;
		// Begin loading core files
		require_once "config.php";
		require_once "db_".$config['dbtype'].".php";
		require_once "functions.php";
		require_once "datacache.php";
		require_once "timers.php";

		// Setup our database and cache classes
		$this->db = new bbDB;

		// Connect to database
		define("TABLE_PREFIX", $config['table_prefix']);
		$this->db->connect($config['hostname'], $config['username'], $config['password']);
		$this->db->select_db($config['database']);
		$GLOBALS['db'] = $this->db;

		// Setup the settings
		require_once "settings.php";
		$this->settings = $settings;

		$this->cache = new datacache;
		$this->cache->cache();

		// And we're in!
		return true;
	}

	/**
	 * USER RELATED FUNCTIONS
	 */

	function validUsername($username)
	{
		$query = $this->db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username='$username'");
		$user = $this->db->fetch_array($query);
		if($user['uid'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function validPassword($uid="", $username="", $password)
	{
		if($uid)
		{
			$uquery = "uid='$uid'";
		}
		else
		{
			$uquery = "username='$username'";
		}
		$query = $this->db->query("SELECT password FROM ".TABLE_PREFIX."users WHERE $uquery AND password='".md5($password)."'");
		$user = $this->db->fetch_array($query);
		if($user['password'])
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function getProfile($uid="", $username="")
	{
		if($uid)
		{
			$uquery = "u.uid='$uid'";
		}
		else
		{
			$uquery = "u.username='$username'";
		}
		$query = $this->db->query("SELECT u.*, f.* FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid) WHERE u.uid='$uid'");
		$user = $this->db->fetch_array($query);
		if($user)
		{
			return $user;
		}
		else
		{
			return false;
		}
	}


	/**
	 * THREAD AND POST RELATED FUNCTIONS
	 */

	/**
	 * WHOS ONLINE RELATED FUNCTIONS
	 */

	function onlineUsers()
	{
		$timesearch = time() - $this->settings['wolcutoff'];
		$query = $this->db->query("SELECT DISTINCT o.ip, o.uid, o.time, o.location, u.username, u.invisible, u.usergroup, g.namestyle FROM ".TABLE_PREFIX."online o LEFT JOIN ".TABLE_PREFIX."users u ON (o.uid=u.uid) LEFT JOIN ".TABLE_PREFIX."usergroups g ON (g.gid=u.usergroup) WHERE o.time>'$timesearch' ORDER BY u.username ASC, o.time DESC");
		while($user = $this->db->fetch_array($query))
		{
			if($user['uid'] > 0)
			{
				if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
				{
					$wol['users'][$user['uid']] = $user;
				}
			}
			else
			{
				$wol['guestcount']++;
			}
		}
		return $wol;
	}

	/**
	 * CALENDAR RELATED FUNCTIONS
	 */

	function getBirthdays($day="", $month="")
	{
		if($day && $month)
		{
			$bdaytime = gmmktime(0, 0, 0, $month, $day);
		}
		else
		{
			$bdaytime = time();
		}
		$bdaydate = mydate("d-n", $bdaytime, "", 0);
		$year = mydate("Y", $bdaytime, "", 0);
		$query = $this->db->query("SELECT uid, username, birthday FROM ".TABLE_PREFIX."users WHERE birthday LIKE '$bdaydate-%'");
		while($bdayuser = $this->db->fetch_array($query))
		{
			$bdays[$bdayuser['uid']] = $bdayuser;
			if($year > $bday['2'] && $bday['2'] != "")
			{
				$bdays[$bdayuser['uid']]['age'] = $age;
			}
		}
		return $bdays;
	}

	function getEvents($day="", $month="", $year="")
	{
		if(!$year)
		{
			$year = date("Y");
		}
		if(!$month || $month > 12 || $month < 1)
		{
			$month = date("n");
		}
		$days = date("t", $time);
		if(!$day || $day > 31 || $day < 1 || $day > $days)
		{
			$day = date("j");
		}
		$query = $this->db->query("SELECT e.*, u.username FROM ".TABLE_PREFIX."events e LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid) WHERE date LIKE '$day-$month-$year' AND private!='yes'");
		while($event = $db->fetch_array($query))
		{
			$events[$event['eid']] = $event;
		}
		return $events;
	}

	function getEvent($eid)
	{
		$query = $this->db->query("SELECT e.*, u.username FROM ".TABLE_PREFIX."events e LEFT JOIN ".TABLE_PREFIX."users u ON (e.author=u.uid) WHERE eid='$eid'");
		$event = $this->db->fetch_array($query);
		if($event)
		{
			return $event;
		}
		else
		{
			return false;
		}
	}

	/**
	 * STATS RELATED FUNCTIONS
	 */

	function boardStats()
	{
		return $this->cache->read("stats");
	}

	/**
	 * FORUM RELATED FUNCTIONS
	 */

	function getForum($fid)
	{
		$query = $this->db->query("SELECT f.*, t.subject AS lastpostsubject FROM ".TABLE_PREFIX."forums f LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.lastposttid) WHERE f.fid='$fid'");
		$forum = $this->db->fetch_array($query);
		if($forum)
		{
			return $forum;
		}
		else
		{
			return false;
		}
	}
}
?>