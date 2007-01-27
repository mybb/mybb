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

class datacache
{
	/**
	 * Cache contents.
	 *
	 * @var array
	 */
	var $cache = array();
	
	/**
	 * The current cache handler we're using
	 *
	 * @var object
	 */
	var $handler;

	/**
	 * Build cache data.
	 *
	 */
	function cache()
	{
		global $db, $mybb;
		
		switch($mybb->config['cache_store'])
		{
			// Disk cache
			case "files":
				require_once MYBB_ROOT."/inc/cachehandlers/disk.php";
				$this->handler = new diskCacheHandler;
				break;
			// Memcache cache
			case "memcache":
				require_once MYBB_ROOT."/inc/cachehandlers/memcache.php";
				$this->handler = new memcacheCacheHandler;
				break;
			// eAccelerator cache
			case "eaccelerator":
				require_once MYBB_ROOT."/inc/cachehandlers/eaccelerator.php";
				$this->handler = new eacceleratorCacheHandler;
				break;
		}
		if(is_object($this->handler))
		{
			if(method_exists($this->handler, "connect"))
			{
				if(!$this->handler->connect())
				{
					$this->handler = null;
				}
			}
		}
		else
		{
			// Database cache
			$query = $db->simple_select("datacache", "title,cache");
			while($data = $db->fetch_array($query))
			{
				$this->cache[$data['title']] = unserialize($data['cache']);
			}				
		}
	}
	
	/**
	 * Read cache from files or db.
	 *
	 * @param string The cache component to read.
	 * @param boolean If true, cannot be overwritten during script execution.
	 * @return unknown
	 */
	function read($name, $hard=false)
	{
		global $db, $mybb;
		
		// Already ready this cache and we're not doing a hard refresh? Return cached copy
		if(isset($this->cache[$name]) && $hard == false)
		{
			return $this->cache[$name];
		}
		
		if(is_object($this->handler))
		{
			$data = $this->handler->fetch($name);
			
			// No data returned - cache gone bad?
			if($data === false)
			{
				// Fetch from database
				$query = $db->simple_select("datacache", "title,cache", "title='$name'");
				$cache_data = $db->fetch_array($query);
				$data = @unserialize($cache_data['cache']);
				
				if($data == null)
				{
					$data = '';
				}
				
				// Update cache for handler
				$this->handler->put($name, $data);
			}
		}
		
		// Else, using internal database cache
		else
		{
			$query = $db->simple_select("datacache", "title,cache", "title='$name'");
			$cache_data = $db->fetch_array($query);
			if(!$cache_data['title'])
			{
				$data = false;
			}
			else
			{
				$data = @unserialize($cache_data['cache']);
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
	 * @param string The cache content identifier.
	 * @param string The cache content.
	 */
	function update($name, $contents)
	{
		global $db, $mybb;
		$this->cache[$name] = $contents;

		// We ALWAYS keep a running copy in the db just incase we need it
		$dbcontents = $db->escape_string(serialize($contents));
		$db->query("
			REPLACE INTO ".TABLE_PREFIX."datacache (title, cache)
			VALUES ('$name','$dbcontents')
		");

		// Do we have a cache handler we're using?
		if(is_object($this->handler))
		{
			$this->handler->put($name, $contents);
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
		
		$query = $db->simple_select("attachtypes", "atid, name, mimetype, extension, maxsize, icon");
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
		
		$query = $db->simple_select("smilies", "sid, name, find, image, disporder, showclickable", "", array('order_by' => 'LENGTH(find)', 'order_dir' => 'DESC'));
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
		
		$query = $db->simple_select("badwords", "bid, badword, replacement");
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
		while($g = $db->fetch_array($query))
		{
			$gs[$g['gid']] = $g;
		}
		
		$this->update("usergroups", $gs);
	}

	/**
	 * Update the forum permissions cache.
	 *
	 * @return false When failed, returns false.
	 */
	function update_forumpermissions()
	{
		global $forum_cache, $db;

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
	}

	/**
	 * Build the forum permissions array
	 *
	 * @access private
	 * @param array An optional permissions array.
	 * @param int An optional permission id.
	 */
	function build_forum_permissions($permissions=array(), $pid=0)
	{
		$usergroups = array_keys($this->read("usergroups", true));
		if($this->forum_permissions_forum_cache[$pid])
		{
			foreach($this->forum_permissions_forum_cache[$pid] as $main)
			{
				foreach($main as $forum)
				{
					$perms = $permissions;
					foreach($usergroups as $gid)
					{
						if($this->forum_permissions[$forum['fid']][$gid])
						{
							$perms[$gid] = $this->forum_permissions[$forum['fid']][$gid];
						}
						if($perms[$gid])
						{
							$this->built_forum_permissions[$forum['fid']][$gid] = $perms[$gid];
						}
					}
					$this->build_forum_permissions($perms, $forum['fid']);
				}
			}
		}
	}

	/**
	 * Update the stats cache.
	 *
	 */
	function update_stats()
	{
		global $db;
		
		$query = $db->simple_select("threads", "COUNT(tid) AS threads", "visible='1' AND closed NOT LIKE 'moved|%'");
		$stats['numthreads'] = $db->fetch_field($query, 'threads');
		
		$query = $db->simple_select("posts", "COUNT(pid) AS posts", "visible='1'");
		$stats['numposts'] = $db->fetch_field($query, 'posts');
		
		$query = $db->simple_select("users", "uid, username", "", array('order_by' => 'uid', 'order_dir' => 'DESC', 'limit' => 1));
		$lastmember = $db->fetch_array($query);
		$stats['lastuid'] = $lastmember['uid'];
		$stats['lastusername'] = $lastmember['username'];
		
		$query = $db->simple_select("users", "COUNT(uid) AS users");
		$stats['numusers'] = $db->fetch_field($query, 'users');
			
		$this->update("stats", $stats);
	}

	/**
	 * Update the moderators cache.
	 *
	 */
	function update_moderators()
	{
		global $db;
		
		$query = $db->simple_select("moderators", "mid, fid, uid, caneditposts, candeleteposts, canviewips, canopenclosethreads, canmanagethreads");
		while($mod = $db->fetch_array($query))
		{
			$mods[$mod['fid']][$mod['uid']] = $mod;
		}
		
		$this->update("moderators", $mods);
	}

	/**
	 * Update the forums cache.
	 *
	 */
	function update_forums()
	{
		global $db;
		
		// Things we don't want to cache
		$exclude = array("threads", "posts", "lastpost", "lastposter", "lastposttid");
		
		$query = $db->simple_select("forums", "*", "", array('order_by' => 'pid,disporder'));
		while($forum = $db->fetch_array($query))
		{
			foreach($forum as $key => $val)
			{
				if(in_array($key, $exclude))
				{
					unset($forum[$key]);
				}
				$forums[$forum['fid']] = $forum;
			}
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
		
		$query = $db->simple_select("usertitles", "utid, posts, title, stars, starimage", "", array('order_by' => 'posts', 'order_dir' => 'DESC'));
		while($usertitle = $db->fetch_array($query))
		{
			$usertitles[] = $usertitle;
		}
		
		$this->update("usertitles", $usertitles);
	}

	/**
	 * Update reported posts cache.
	 *
	 */
	function update_reportedposts()
	{
		global $db;
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS unreadcount", "reportstatus='0'");
		$num = $db->fetch_array($query);
		
		$query = $db->simple_select("reportedposts", "COUNT(rid) AS reportcount");
		$total = $db->fetch_array($query);
		
		$query = $db->simple_select("reportedposts", "dateline", "reportstatus='0'", array('order_by' => 'dateline', 'order_dir' => 'DESC'));
		$latest = $db->fetch_array($query);
		
		$reports = array(
			"unread" => $num['unreadcount'],
			"total" => $total['reportcount'],
			"lastdateline" => $latest['dateline']
		);
		
		$this->update("reportedposts", $reports);
	}

	/**
	 * Update mycode cache.
	 *
	 */
	function update_mycode()
	{
		global $db;
		
		$query = $db->simple_select("mycode", "regex, replacement", "active='yes'", array('order_by' => 'parseorder'));
		while($mycode = $db->fetch_array($query))
		{
			$mycodes[] = $mycode;
		}
		
		$this->update("mycode", $mycodes);
	}
	/**
	 * Update the mailqueue cache
	 *
	 */
	function update_mailqueue($last_run=0, $lock_time=0)
	{
		global $db;
		
		$query = $db->simple_select("mailqueue", "COUNT(*) AS queue_size");
		$queue_size = $db->fetch_field($query, "queue_size");
		
		$mailqueue = $this->read("mailqueue");
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
			"dateline" => time()
		);
		
		$this->update("update_check", $update_cache);
	}
}
?>