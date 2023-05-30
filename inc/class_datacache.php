<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

class datacache
{
	/**
	 * Cache contents.
	 *
	 * @var array
	 */
	public $cache = array();

	/**
	 * The current cache handler we're using
	 *
	 * @var CacheHandlerInterface
	 */
	public $handler = null;

	/**
	 * A count of the number of calls.
	 *
	 * @var int
	 */
	public $call_count = 0;

	/**
	 * A list of the performed calls.
	 *
	 * @var array
	 */
	public $calllist = array();

	/**
	 * The time spent on cache operations
	 *
	 * @var float
	 */
	public $call_time = 0;

	/**
	 * Explanation of a cache call.
	 *
	 * @var string
	 */
	public $cache_debug;

	/**
	 * @var array
	 */
	public $moderators;

	/**
	 * @var array
	 */
	public $built_moderators;

	/**
	 * @var array
	 */
	public $moderators_forum_cache;

	/**
	 * Build cache data.
	 *
	 */
	function cache()
	{
		global $db, $mybb;

		require_once MYBB_ROOT."/inc/cachehandlers/interface.php";

		switch($mybb->config['cache_store'])
		{
			// Disk cache
			case "files":
				require_once MYBB_ROOT."/inc/cachehandlers/disk.php";
				$this->handler = new diskCacheHandler();
				break;
			// Memcache cache
			case "memcache":
				require_once MYBB_ROOT."/inc/cachehandlers/memcache.php";
				$this->handler = new memcacheCacheHandler();
				break;
			// Memcached cache
			case "memcached":
				require_once MYBB_ROOT."/inc/cachehandlers/memcached.php";
				$this->handler = new memcachedCacheHandler();
				break;
			// eAccelerator cache
			case "eaccelerator":
				require_once MYBB_ROOT."/inc/cachehandlers/eaccelerator.php";
				$this->handler = new eacceleratorCacheHandler();
				break;
			// Xcache cache
			case "xcache":
				require_once MYBB_ROOT."/inc/cachehandlers/xcache.php";
				$this->handler = new xcacheCacheHandler();
				break;
			// APC cache
			case "apc":
				require_once MYBB_ROOT."/inc/cachehandlers/apc.php";
				$this->handler = new apcCacheHandler();
				break;
			// APCu cache
			case "apcu":
				require_once MYBB_ROOT."/inc/cachehandlers/apcu.php";
				$this->handler = new apcuCacheHandler();
				break;
			// Redis cache
			case "redis":
				require_once MYBB_ROOT."/inc/cachehandlers/redis.php";
				$this->handler = new redisCacheHandler();
				break;
		}

		if($this->handler instanceof CacheHandlerInterface)
		{
			if(!$this->handler->connect())
			{
				$this->handler = null;
			}
		}
		else
		{
			// Database cache
			$query = $db->simple_select("datacache", "title,cache");
			while($data = $db->fetch_array($query))
			{
				// use PHP's own unserialize() for performance reasons
				$this->cache[$data['title']] = unserialize($data['cache'], array('allowed_classes' => false));
			}
		}
	}

	/**
	 * Read cache from files or db.
	 *
	 * @param string $name The cache component to read.
	 * @param boolean $hard If true, cannot be overwritten during script execution.
	 * @return mixed
	 */
	function read($name, $hard=false)
	{
		global $db, $mybb;

		// Already have this cache and we're not doing a hard refresh? Return cached copy
		if(isset($this->cache[$name]) && $hard == false)
		{
			return $this->cache[$name];
		}
		// If we're not hard refreshing, and this cache doesn't exist, return false
		// It would have been loaded pre-global if it did exist anyway...
		else if($hard == false && !($this->handler instanceof CacheHandlerInterface))
		{
			return false;
		}

		if($this->handler instanceof CacheHandlerInterface)
		{
			get_execution_time();

			$data = $this->handler->fetch($name);

			$call_time = get_execution_time();
			$this->call_time += $call_time;
			$this->call_count++;

			if($mybb->debug_mode)
			{
				$hit = true;
				if($data === false)
				{
					$hit = false;
				}
				$this->debug_call('read:'.$name, $call_time, $hit);
			}

			// No data returned - cache gone bad?
			if($data === false)
			{
				// Fetch from database
				$query = $db->simple_select("datacache", "title,cache", "title='".$db->escape_string($name)."'");
				$cache_data = $db->fetch_array($query);

				// use PHP's own unserialize() for performance reasons
				$data = unserialize($cache_data['cache'], array('allowed_classes' => false));

				// Update cache for handler
				get_execution_time();

				$hit = $this->handler->put($name, $data);

				$call_time = get_execution_time();
				$this->call_time += $call_time;
				$this->call_count++;

				if($mybb->debug_mode)
				{
					$this->debug_call('set:'.$name, $call_time, $hit);
				}
			}
		}
		// Else, using internal database cache
		else
		{
			$query = $db->simple_select("datacache", "title,cache", "title='$name'");
			$cache_data = $db->fetch_array($query);

			if(empty($cache_data['title']))
			{
				$data = false;
			}
			else
			{
				// use PHP's own unserialize() for performance reasons
				$data = unserialize($cache_data['cache'], array('allowed_classes' => false));
			}
		}

		// Cache locally
		$this->cache[$name] = $data;

		if($data !== false)
		{
			return $data;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Update cache contents.
	 *
	 * @param string $name The cache content identifier.
	 * @param mixed $contents The cache content.
	 */
	function update($name, $contents)
	{
		global $db, $mybb;

		$this->cache[$name] = $contents;

		// We ALWAYS keep a running copy in the db just incase we need it
		$dbcontents = $db->escape_string(my_serialize($contents));

		$replace_array = array(
			"title" => $db->escape_string($name),
			"cache" => $dbcontents
		);
		$db->replace_query("datacache", $replace_array, "", false);

		// Do we have a cache handler we're using?
		if($this->handler instanceof CacheHandlerInterface)
		{
			get_execution_time();

			$hit = $this->handler->put($name, $contents);

			$call_time = get_execution_time();
			$this->call_time += $call_time;
			$this->call_count++;

			if($mybb->debug_mode)
			{
				$this->debug_call('update:'.$name, $call_time, $hit);
			}
		}
	}

	/**
	 * Delete cache contents.
	 * Originally from frostschutz's PluginLibrary
	 * github.com/frostschutz
	 *
	 * @param string $name Cache name or title
	 * @param boolean $greedy To delete a cache starting with name_
	 */
	 function delete($name, $greedy = false)
	 {
		global $db, $mybb, $cache;

		// Prepare for database query.
		$dbname = $db->escape_string($name);
		$where = "title = '{$dbname}'";

		// Delete on-demand or handler cache
		if($this->handler instanceof CacheHandlerInterface)
		{
			get_execution_time();

			$hit = $this->handler->delete($name);

			$call_time = get_execution_time();
			$this->call_time += $call_time;
			$this->call_count++;

			if($mybb->debug_mode)
			{
				$this->debug_call('delete:'.$name, $call_time, $hit);
			}
		}

		// Greedy?
		if($greedy)
		{
			$name .= '_';
			$names = array();
			$keys = array_keys($cache->cache);

			foreach($keys as $key)
			{
				if(strpos($key, $name) === 0)
				{
					$names[$key] = 0;
				}
			}

			$ldbname = strtr($dbname,
				array(
					'%' => '=%',
					'=' => '==',
					'_' => '=_'
				)
			);

			$where .= " OR title LIKE '{$ldbname}=_%' ESCAPE '='";

			if($this->handler instanceof CacheHandlerInterface)
			{
				$query = $db->simple_select("datacache", "title", $where);

				while($row = $db->fetch_array($query))
				{
					$names[$row['title']] = 0;
				}

				// ...from the filesystem...
				$start = strlen(MYBB_ROOT."cache/");
				foreach((array)@glob(MYBB_ROOT."cache/{$name}*.php") as $filename)
				{
					if($filename)
					{
						$filename = substr($filename, $start, strlen($filename)-4-$start);
						$names[$filename] = 0;
					}
				}

				foreach($names as $key => $val)
				{
					get_execution_time();

					$hit = $this->handler->delete($key);

					$call_time = get_execution_time();
					$this->call_time += $call_time;
					$this->call_count++;

					if($mybb->debug_mode)
					{
						$this->debug_call('delete:'.$name, $call_time, $hit);
					}
				}
			}
		}

		// Delete database cache
		$db->delete_query("datacache", $where);
	}

	/**
	 * Debug a cache call to a non-database cache handler
	 *
	 * @param string $string The cache key
	 * @param string $qtime The time it took to perform the call.
	 * @param boolean $hit Hit or miss status
	 */
	function debug_call($string, $qtime, $hit)
	{
		global $mybb, $plugins;

		$debug_extra = '';
		if($plugins->current_hook)
		{
			$debug_extra = "<div style=\"float_right\">(Plugin Hook: {$plugins->current_hook})</div>";
		}

		if($hit)
		{
			$hit_status = 'HIT';
		}
		else
		{
			$hit_status = 'MISS';
		}

		$cache_data = explode(':', $string);
		$cache_method = $cache_data[0];
		$cache_key = $cache_data[1];

		$this->cache_debug = "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">
<tr>
	<td style=\"background-color: #ccc;\">{$debug_extra}<div><strong>#{$this->call_count} - ".ucfirst($cache_method)." Call</strong></div></td>
</tr>
<tr style=\"background-color: #fefefe;\">
	<td><span style=\"font-family: Courier; font-size: 14px;\">({$mybb->config['cache_store']}) [{$hit_status}] ".htmlspecialchars_uni($cache_key)."</span></td>
</tr>
<tr>
	<td bgcolor=\"#ffffff\">Call Time: ".format_time_duration($qtime)."</td>
</tr>
</table>
<br />\n";

		$this->calllist[$this->call_count]['key'] = $string;
		$this->calllist[$this->call_count]['time'] = $qtime;
	}

	/**
	 * Select the size of the cache
	 *
	 * @param string $name The name of the cache
	 * @return integer the size of the cache
	 */
	function size_of($name='')
	{
		global $db;

		if($this->handler instanceof CacheHandlerInterface)
		{
			$size = $this->handler->size_of($name);
			if(!$size)
			{
				if($name)
				{
					$query = $db->simple_select("datacache", "cache", "title='{$name}'");
					return strlen($db->fetch_field($query, "cache"));
				}
				else
				{
					return $db->fetch_size("datacache");
				}
			}
			else
			{
				return $size;
			}
		}
		// Using MySQL as cache
		else
		{
			if($name)
			{
				$query = $db->simple_select("datacache", "cache", "title='{$name}'");
				return strlen($db->fetch_field($query, "cache"));
			}
			else
			{
				return $db->fetch_size("datacache");
			}
		}
	}

	/**
	 * Update the MyBB version in the cache.
	 *
	 */
	function update_version()
	{
		global $mybb;

		$version = array(
			"version" => $mybb->version,
			"version_code" => $mybb->version_code
		);

		$this->update("version", $version);
	}

	/**
	 * Update the attachment type cache.
	 *
	 */
	function update_attachtypes()
	{
		global $db;

		$types = array();

		$query = $db->simple_select('attachtypes', '*', 'enabled=1');
		while($type = $db->fetch_array($query))
		{
			$type['extension'] = my_strtolower($type['extension']);
			$types[$type['extension']] = $type;
		}

		$this->update("attachtypes", $types);
	}

	/**
	 * Update the smilies cache.
	 *
	 */
	function update_smilies()
	{
		global $db;

		$smilies = array();

		$query = $db->simple_select("smilies", "*", "", array('order_by' => 'disporder', 'order_dir' => 'ASC'));
		while($smilie = $db->fetch_array($query))
		{
			$smilies[$smilie['sid']] = $smilie;
		}

		$this->update("smilies", $smilies);
	}

	/**
	 * Update the posticon cache.
	 *
	 */
	function update_posticons()
	{
		global $db;

		$icons = array();

		$query = $db->simple_select("icons", "iid, name, path");
		while($icon = $db->fetch_array($query))
		{
			$icons[$icon['iid']] = $icon;
		}

		$this->update("posticons", $icons);
	}

	/**
	 * Update the badwords cache.
	 *
	 */
	function update_badwords()
	{
		global $db;

		$badwords = array();

		$query = $db->simple_select("badwords", "*");
		while($badword = $db->fetch_array($query))
		{
			$badwords[$badword['bid']] = $badword;
		}

		$this->update("badwords", $badwords);
	}

	/**
	 * Update the usergroups cache.
	 *
	 */
	function update_usergroups()
	{
		global $db;

		$query = $db->simple_select("usergroups");

		$gs = array();
		while($g = $db->fetch_array($query))
		{
			$gs[$g['gid']] = $g;
		}

		$this->update("usergroups", $gs);
	}

	/**
	 * Update the forum permissions cache.
	 *
	 * @return bool When failed, returns false.
	 */
	function update_forumpermissions()
	{
		global $forum_cache, $db;

		$this->forum_permissions = $this->built_forum_permissions = array(0);

		// Get our forum list
		cache_forums(true);
		if(!is_array($forum_cache))
		{
			return false;
		}

		reset($forum_cache);
		$fcache = array();

		// Resort in to the structure we require
		foreach($forum_cache as $fid => $forum)
		{
			$this->forum_permissions_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}

		// Sort children
		foreach($fcache as $pid => $value)
		{
			ksort($fcache[$pid]);
		}
		ksort($fcache);

		// Fetch forum permissions from the database
		$query = $db->simple_select("forumpermissions");
		while($forum_permission = $db->fetch_array($query))
		{
			$this->forum_permissions[$forum_permission['fid']][$forum_permission['gid']] = $forum_permission;
		}

		$this->build_forum_permissions();
		$this->update("forumpermissions", $this->built_forum_permissions);

		return true;
	}

	/**
	 * Build the forum permissions array
	 *
	 * @access private
	 * @param array $permissions An optional permissions array.
	 * @param int $pid An optional permission id.
	 */
	private function build_forum_permissions($permissions=array(), $pid=0)
	{
		$usergroups = array_keys($this->read("usergroups", true));
		if(!empty($this->forum_permissions_forum_cache[$pid]))
		{
			foreach($this->forum_permissions_forum_cache[$pid] as $main)
			{
				foreach($main as $forum)
				{
					$perms = $permissions;
					foreach($usergroups as $gid)
					{
						if(isset($this->forum_permissions[$forum['fid']][$gid]) && $this->forum_permissions[$forum['fid']][$gid])
						{
							$perms[$gid] = $this->forum_permissions[$forum['fid']][$gid];
						}
						if(!empty($perms[$gid]))
						{
							$perms[$gid]['fid'] = $forum['fid'];
							$this->built_forum_permissions[$forum['fid']][$gid] = $perms[$gid];
						}
					}
					$this->build_forum_permissions($perms, $forum['fid']);
				}
			}
		}
	}

	/**
	 * Update the stats cache (kept for the sake of being able to rebuild this cache via the cache interface)
	 *
	 */
	function update_stats()
	{
		require_once MYBB_ROOT."inc/functions_rebuild.php";
		rebuild_stats();
	}

	/**
	 * Update the statistics cache
	 *
	 */
	function update_statistics()
	{
		global $db;

		$query = $db->simple_select('users', 'uid, username, referrals', 'referrals>0', array('order_by' => 'referrals', 'order_dir' => 'DESC', 'limit' => 1));
		$topreferrer = $db->fetch_array($query);

		$timesearch = TIME_NOW - 86400;

		$query = $db->query("
			SELECT u.uid, u.username, COUNT(*) AS poststoday
			FROM {$db->table_prefix}posts p
			LEFT JOIN {$db->table_prefix}users u ON (p.uid=u.uid)
			WHERE p.dateline > {$timesearch} AND p.visible=1
			GROUP BY u.uid, u.username
			ORDER BY poststoday DESC
		");

		$most_posts = 0;
		$topposter = array();
		while($user = $db->fetch_array($query))
		{
			if($user['poststoday'] > $most_posts)
			{
				$most_posts = $user['poststoday'];
				$topposter = $user;
			}
		}

		$query = $db->simple_select('users', 'COUNT(uid) AS posters', 'postnum>0');
		$posters = $db->fetch_field($query, 'posters');

		$statistics = array(
			'time' => TIME_NOW,
			'top_referrer' => (array)$topreferrer,
			'top_poster' => (array)$topposter,
			'posters' => $posters
		);

		$this->update('statistics', $statistics);
	}

	/**
	 * Update the moderators cache.
	 *
	 * @return bool Returns false on failure
	 */
	function update_moderators()
	{
		global $forum_cache, $db;

		$this->built_moderators = array(0);

		// Get our forum list
		cache_forums(true);
		if(!is_array($forum_cache))
		{
			return false;
		}

		reset($forum_cache);
		$fcache = array();

		// Resort in to the structure we require
		foreach($forum_cache as $fid => $forum)
		{
			$this->moderators_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}

		// Sort children
		foreach($fcache as $pid => $value)
		{
			ksort($fcache[$pid]);
		}
		ksort($fcache);

		$this->moderators = array();

		// Fetch moderators from the database
		$query = $db->query("
			SELECT m.*, u.username, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."users u ON (m.id=u.uid)
			WHERE m.isgroup = '0'
			ORDER BY u.username
		");
		while($moderator = $db->fetch_array($query))
		{
			$this->moderators[$moderator['fid']]['users'][$moderator['id']] = $moderator;
		}

		if(!function_exists("sort_moderators_by_usernames"))
		{
			function sort_moderators_by_usernames($a, $b)
			{
				return strcasecmp($a['username'], $b['username']);
			}
		}

		//Fetch moderating usergroups from the database
		$query = $db->query("
			SELECT m.*, u.title
			FROM ".TABLE_PREFIX."moderators m
			LEFT JOIN ".TABLE_PREFIX."usergroups u ON (m.id=u.gid)
			WHERE m.isgroup = '1'
			ORDER BY u.title
		");
		while($moderator = $db->fetch_array($query))
		{
			$this->moderators[$moderator['fid']]['usergroups'][$moderator['id']] = $moderator;
		}

		foreach(array_keys($this->moderators) as $fid)
		{
			if(isset($this->moderators[$fid]['users']))
			{
				uasort($this->moderators[$fid]['users'], 'sort_moderators_by_usernames');
			}
		}

		$this->build_moderators();

		$this->update("moderators", $this->built_moderators);

		return true;
	}

	/**
	 * Update the users awaiting activation cache.
	 *
	 */
	function update_awaitingactivation()
	{
		global $db;

		$query = $db->simple_select('users', 'COUNT(uid) AS awaitingusers', 'usergroup=\'5\'');
		$awaitingusers = (int)$db->fetch_field($query, 'awaitingusers');

		$data = array(
			'users'	=> $awaitingusers,
			'time'	=> TIME_NOW
		);

		$this->update('awaitingactivation', $data);
	}

	/**
	 * Build the moderators array
	 *
	 * @access private
	 * @param array $moderators An optional moderators array (moderators of the parent forum for example).
	 * @param int $pid An optional parent ID.
	 */
	private function build_moderators($moderators=array(), $pid=0)
	{
		if(isset($this->moderators_forum_cache[$pid]))
		{
			foreach($this->moderators_forum_cache[$pid] as $main)
			{
				foreach($main as $forum)
				{
					$forum_mods = array();
					if(count($moderators))
					{
						$forum_mods = $moderators;
					}
					// Append - local settings override that of a parent - array_merge works here
					if(isset($this->moderators[$forum['fid']]))
					{
						if(is_array($forum_mods) && count($forum_mods))
						{
							$forum_mods = array_merge($forum_mods, $this->moderators[$forum['fid']]);
						}
						else
						{
							$forum_mods = $this->moderators[$forum['fid']];
						}
					}
					$this->built_moderators[$forum['fid']] = $forum_mods;
					$this->build_moderators($forum_mods, $forum['fid']);
				}
			}
		}
	}

	/**
	 * Update the forums cache.
	 *
	 */
	function update_forums()
	{
		global $db;

		$forums = array();

		// Things we don't want to cache
		$exclude = array("unapprovedthreads", "unapprovedposts", "threads", "posts", "lastpost", "lastposter", "lastposttid", "lastposteruid", "lastpostsubject", "deletedthreads", "deletedposts");

		$query = $db->simple_select("forums", "*", "", array('order_by' => 'pid,disporder'));
		while($forum = $db->fetch_array($query))
		{
			foreach($forum as $key => $val)
			{
				if(in_array($key, $exclude))
				{
					unset($forum[$key]);
				}
			}
			$forums[$forum['fid']] = $forum;
		}

		$this->update("forums", $forums);
	}

	/**
	 * Update usertitles cache.
	 *
	 */
	function update_usertitles()
	{
		global $db;

		$usertitles = array();
		$query = $db->simple_select("usertitles", "utid, posts, title, stars, starimage", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		while($usertitle = $db->fetch_array($query))
		{
			$usertitles[] = $usertitle;
		}

		$this->update("usertitles", $usertitles);
	}

	/**
	 * Update reported content cache.
	 *
	 */
	function update_reportedcontent()
	{
		global $db;

		$query = $db->simple_select("reportedcontent", "COUNT(rid) AS unreadcount", "reportstatus='0'");
		$unreadcount = $db->fetch_field($query, 'unreadcount');

		$query = $db->simple_select("reportedcontent", "COUNT(rid) AS reportcount");
		$reportcount = $db->fetch_field($query, 'reportcount');
		
		$query = $db->simple_select("reportedcontent", "dateline", "reportstatus='0'", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
		$dateline = $db->fetch_field($query, 'dateline');

		$reports = array(
			'unread' => $unreadcount,
			'total' => $reportcount,
			'lastdateline' => $dateline,
		);

		$this->update("reportedcontent", $reports);
	}

	/**
	 * Update mycode cache.
	 *
	 */
	function update_mycode()
	{
		global $db;

		$mycodes = array();
		$query = $db->simple_select("mycode", "regex, replacement", "active=1", array('order_by' => 'parseorder'));
		while($mycode = $db->fetch_array($query))
		{
			$mycodes[] = $mycode;
		}

		$this->update("mycode", $mycodes);
	}

	/**
	 * Update the mailqueue cache
	 *
	 * @param int $last_run
	 * @param int $lock_time
	 */
	function update_mailqueue($last_run=0, $lock_time=0)
	{
		global $db;

		$query = $db->simple_select("mailqueue", "COUNT(*) AS queue_size");
		$queue_size = $db->fetch_field($query, "queue_size");

		$mailqueue = $this->read("mailqueue");
		if(!is_array($mailqueue))
		{
			$mailqueue = array();
		}
		$mailqueue['queue_size'] = $queue_size;
		if($last_run > 0)
		{
			$mailqueue['last_run'] = $last_run;
		}
		$mailqueue['locked'] = $lock_time;

		$this->update("mailqueue", $mailqueue);
	}

	/**
	 * Update update_check cache (dummy function used by upgrade/install scripts)
	 */
	function update_update_check()
	{
		$update_cache = array(
			"dateline" => TIME_NOW
		);

		$this->update("update_check", $update_cache);
	}

	/**
	 * Update default_theme cache
	 */
	function update_default_theme()
	{
		global $db;

		$query = $db->simple_select("themes", "name, tid, properties, stylesheets", "def='1'", array('limit' => 1));
		$theme = $db->fetch_array($query);
		$this->update("default_theme", $theme);
	}

	/**
	 * Updates the tasks cache saving the next run time
	 */
	function update_tasks()
	{
		global $db;

		$query = $db->simple_select("tasks", "nextrun", "enabled=1", array("order_by" => "nextrun", "order_dir" => "asc", "limit" => 1));
		$next_task = $db->fetch_array($query);

		$task_cache = $this->read("tasks");
		if(!is_array($task_cache))
		{
			$task_cache = array();
		}
		$task_cache['nextrun'] = $next_task['nextrun'];

		if(!$task_cache['nextrun'])
		{
			$task_cache['nextrun'] = TIME_NOW+3600;
		}

		$this->update("tasks", $task_cache);
	}

	/**
	 * Updates the banned IPs cache
	 */
	function update_bannedips()
	{
		global $db;

		$banned_ips = array();
		$query = $db->simple_select("banfilters", "fid,filter", "type=1");
		while($banned_ip = $db->fetch_array($query))
		{
			$banned_ips[$banned_ip['fid']] = $banned_ip;
		}
		$this->update("bannedips", $banned_ips);
	}

	/**
	 * Updates the banned emails cache
	 */
	function update_bannedemails()
	{
		global $db;

		$banned_emails = array();
		$query = $db->simple_select("banfilters", "fid, filter", "type = '3'");

		while($banned_email = $db->fetch_array($query))
		{
			$banned_emails[$banned_email['fid']] = $banned_email;
		}

		$this->update("bannedemails", $banned_emails);
	}

	/**
	 * Updates the search engine spiders cache
	 */
	function update_spiders()
	{
		global $db;

		$spiders = array();
		$query = $db->simple_select("spiders", "sid, name, useragent, usergroup", "", array("order_by" => "LENGTH(useragent)", "order_dir" => "DESC"));
		while($spider = $db->fetch_array($query))
		{
			$spiders[$spider['sid']] = $spider;
		}
		$this->update("spiders", $spiders);
	}

	function update_most_replied_threads()
	{
		global $db, $mybb;

		$threads = array();

		$query = $db->simple_select("threads", "tid, subject, replies, fid, uid", "visible='1'", array('order_by' => 'replies', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
		while($thread = $db->fetch_array($query))
		{
			$threads[] = $thread;
		}

		$this->update("most_replied_threads", $threads);
	}

	function update_most_viewed_threads()
	{
		global $db, $mybb;

		$threads = array();

		$query = $db->simple_select("threads", "tid, subject, views, fid, uid", "visible='1'", array('order_by' => 'views', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => $mybb->settings['statslimit']));
		while($thread = $db->fetch_array($query))
		{
			$threads[] = $thread;
		}

		$this->update("most_viewed_threads", $threads);
	}

	/**
	 * @deprecated
	 */
	function update_banned()
	{
		// "banned" cache removed
	}

	function update_birthdays()
	{
		global $db;

		$birthdays = array();

		// Get today, yesterday, and tomorrow's time (for different timezones)
		$bdaytime = TIME_NOW;
		$bdaydate = my_date("j-n", $bdaytime, '', 0);
		$bdaydatetomorrow = my_date("j-n", ($bdaytime+86400), '', 0);
		$bdaydateyesterday = my_date("j-n", ($bdaytime-86400), '', 0);

		$query = $db->simple_select("users", "uid, username, usergroup, displaygroup, birthday, birthdayprivacy", "birthday LIKE '$bdaydate-%' OR birthday LIKE '$bdaydateyesterday-%' OR birthday LIKE '$bdaydatetomorrow-%'");
		while($bday = $db->fetch_array($query))
		{
			// Pop off the year from the birthday because we don't need it.
			$bday['bday'] = explode('-', $bday['birthday']);
			array_pop($bday['bday']);
			$bday['bday'] = implode('-', $bday['bday']);

			if($bday['birthdayprivacy'] != 'all')
			{
				if(isset($birthdays[$bday['bday']]['hiddencount']))
				{
					++$birthdays[$bday['bday']]['hiddencount'];
				}
				else
				{
					$birthdays[$bday['bday']]['hiddencount'] = 1;
				}
				continue;
			}

			// We don't need any excess caleries in the cache
			unset($bday['birthdayprivacy']);

			if(!isset($birthdays[$bday['bday']]['users']))
			{
				$birthdays[$bday['bday']]['users'] = array();
			}

			$birthdays[$bday['bday']]['users'][] = $bday;
		}

		$this->update("birthdays", $birthdays);
	}

	function update_groupleaders()
	{
		global $db;

		$groupleaders = array();

		$query = $db->simple_select("groupleaders");
		while($groupleader = $db->fetch_array($query))
		{
			$groupleaders[$groupleader['uid']][] = $groupleader;
		}

		$this->update("groupleaders", $groupleaders);
	}

	function update_threadprefixes()
	{
		global $db;

		$prefixes = array();
		$query = $db->simple_select("threadprefixes", "*", "", array('order_by' => 'prefix', 'order_dir' => 'ASC'));

		while($prefix = $db->fetch_array($query))
		{
			$prefixes[$prefix['pid']] = $prefix;
		}

		$this->update("threadprefixes", $prefixes);
	}

	function update_forumsdisplay()
	{
		global $db;

		$fd_statistics = array();

		$time = TIME_NOW; // Look for announcements that don't end, or that are ending some time in the future
		$query = $db->simple_select("announcements", "fid", "enddate = '0' OR enddate > '{$time}'", array("order_by" => "aid"));

		if($db->num_rows($query))
		{
			while($forum = $db->fetch_array($query))
			{
				if(!isset($fd_statistics[$forum['fid']]['announcements']))
				{
					$fd_statistics[$forum['fid']]['announcements'] = 1;
				}
			}
		}

		// Do we have any mod tools to use in our forums?
		$query = $db->simple_select("modtools", "forums, tid", '', array("order_by" => "tid"));

		if($db->num_rows($query))
		{
			unset($forum);
			while($tool = $db->fetch_array($query))
			{
				$forums = explode(",", $tool['forums']);

				foreach($forums as $forum)
				{
					if(!$forum)
					{
						$forum = -1;
					}

					if(!isset($fd_statistics[$forum]['modtools']))
					{
						$fd_statistics[$forum]['modtools'] = 1;
					}
				}
			}
		}

		$this->update("forumsdisplay", $fd_statistics);
	}

	/**
	 * Update profile fields cache.
	 *
	 */
	function update_profilefields()
	{
		global $db;

		$fields = array();
		$query = $db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
		while($field = $db->fetch_array($query))
		{
			$fields[] = $field;
		}

		$this->update("profilefields", $fields);
	}

	/**
	 * Update the report reasons cache.
	 *
	 */
	function update_reportreasons($no_plugins = false)
	{
		global $db;

		$content_types = array('post', 'profile', 'reputation');
		if(!$no_plugins)
		{
			global $plugins;
			$content_types = $plugins->run_hooks("report_content_types", $content_types);
		}

		$reasons = array();

		$query = $db->simple_select("reportreasons", "*", "", array('order_by' => 'disporder'));
		while($reason = $db->fetch_array($query))
		{
			if($reason['appliesto'] == 'all')
			{
				foreach($content_types as $content)
				{
					$reasons[$content][] = array(
						'rid' => $reason['rid'],
						'title' => $reason['title'],
						'extra' => $reason['extra'],
					);
				}
			}
			elseif($reason['appliesto'] != '')
			{
				$appliesto = explode(",", $reason['appliesto']);
				foreach($appliesto as $content)
				{
					$reasons[$content][] = array(
						'rid' => $reason['rid'],
						'title' => $reason['title'],
						'extra' => $reason['extra'],
					);
				}
			}
		}

		$this->update("reportreasons", $reasons);
	}

	/* Other, extra functions for reloading caches if we just changed to another cache extension (i.e. from db -> xcache) */
	function reload_mostonline()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='mostonline'");
		$this->update("mostonline", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_plugins()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='plugins'");
		$this->update("plugins", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_last_backup()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='last_backup'");
		$this->update("last_backup", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_internal_settings()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='internal_settings'");
		$this->update("internal_settings", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_version_history()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='version_history'");
		$this->update("version_history", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_modnotes()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='modnotes'");
		$this->update("modnotes", my_unserialize($db->fetch_field($query, "cache")));
	}

	function reload_adminnotes()
	{
		global $db;

		$query = $db->simple_select("datacache", "title,cache", "title='adminnotes'");
		$this->update("adminnotes", my_unserialize($db->fetch_field($query, "cache")));
	}
}
