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

class datacache
{
	var $cache = array();

	function cache()
	{
		global $db, $mybb;
		if($mybb->config['cachestore'] == "files")
		{
			// Check if no files exist in cache directory, if not we need to create them (possible move from db to files)
			if(!file_exists("./inc/cache/version.php"))
			{
				$query = $db->query("SELECT title,cache FROM ".TABLE_PREFIX."datacache");
				while($data = $db->fetch_array($query))
				{
					$this->update($data['title'], unserialize($data['cache']));
				}
			}
		}
		else
		{
			$query = $db->query("SELECT title,cache FROM ".TABLE_PREFIX."datacache");
			while($data = $db->fetch_array($query))
			{
				$this->cache[$data['title']] = unserialize($data['cache']);
			}
		}
	}

	function read($name, $hard="")
	{
		global $db, $test, $mybb;
		if($mybb->config['cachestore'] == "files")
		{
			if($hard)
			{
				@include("./inc/cache/".$name.".php");
			}
			else
			{
				@include_once("./inc/cache/".$name.".php");
			}
			$this->cache[$name] = $$name;
			unset($$name);
		}
		else
		{
			if($hard)
			{
				$query = $db->query("SELECT title, cache FROM ".TABLE_PREFIX."datacache WHERE title='$name'");
				$data = $db->fetch_array($query);
				$this->cache[$data['title']] = unserialize($data['cache']);
			}
		}
		return $this->cache[$name];
	}

	function update($name, $contents)
	{
		global $db, $mybb;
		$this->cache[$name] = $contents;

		// We ALWAYS keep a running copy in the db just incase we need it
		$dbcontents = addslashes(serialize($contents));
		$db->query("REPLACE INTO ".TABLE_PREFIX."datacache (title, cache) VALUES ('$name','$dbcontents')");

		// If using files, update the cache file too
		if($mybb->config['cachestore'] == "files")
		{
			if(!@is_writable("./inc/cache/"))
			{
				$mybb->trigger_generic_error("cache_no_write");
			}
			$cachefile = fopen("./inc/cache/$name.php", "w");
			$cachecontents = "<?php\n\n/** MyBB Generated Cache - Do Not Alter\n * Cache Name: $name\n * Generated: ".gmdate("r")."\n*/\n\n";
			$cachecontents .= "\$$name = ".var_export($contents, true).";\n\n ?>";
			fwrite($cachefile, $cachecontents);
			fclose($cachefile);
		}
	}

	function updateversion()
	{
		global $db, $mybboard;
		$this->update("version", $mybboard);
	}

	function updateattachtypes()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachtypes");
		while($type = $db->fetch_array($query))
		{
			$type['extension'] = strtolower($type['extension']);
			$types[$type['extension']] = $type;
		}
		$this->update("attachtypes", $types);
	}

	function updatesmilies()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."smilies");
		while($smilie = $db->fetch_array($query))
		{
			$smilies[$smilie['sid']] = $smilie;
		}
		$this->update("smilies", $smilies);
	}

	function updateposticons()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."icons");
		while($icon = $db->fetch_array($query))
		{
			$icons[$icon['iid']] = $icon;
		}
		$this->update("posticons", $icons);
	}

	function updatebadwords()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."badwords");
		while($badword = $db->fetch_array($query)) {
			$badwords[$badword['bid']] = $badword;
		}
		$this->update("badwords", $badwords);
	}

	function updateusergroups()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups");
		while($g = $db->fetch_array($query))
		{
			$gs[$g['gid']] = $g;
		}
		$this->update("usergroups", $gs);
	}

	function updateforumpermissions()
	{
		global $forumcache, $fcache, $db, $usergroupcache, $fperms, $fpermfields, $forumpermissions;

		// Get usergroups
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups");
		while($usergroup = $db->fetch_array($query))
		{
			$gid = $usergroup['gid'];
			foreach($usergroup as $key => $val)
			{
				if(!in_array($key, $fpermfields))
				{
					unset($usergroup[$key]);
				}
			}
			$usergroupcache[$gid] = $usergroup;
		}
	
		// Get our forum list
		cacheforums();
		if(!is_array($forumcache))
		{
			return false;
		}
		reset($forumcache);
		foreach($forumcache as $fid => $forum)
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
		foreach($fcache as $pid => $value)
		{
			ksort($fcache[$pid]);
		}
		ksort($fcache);
	
		// Fetch forum permissions
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forumpermissions");
		while($fperm = $db->fetch_array($query))
		{
			$fperms[$fperm['fid']][$fperm['gid']] = $fperm;
		}
		$this->buildforumpermissions();
		$this->update("forumpermissions", $forumpermissions);
	}

	function buildforumpermissions($permissions="", $pid=0)
	{
		global $fcache, $usergroupcache, $fperms, $forumpermissions;
		if($fcache[$pid])
		{
			foreach($fcache[$pid] as $key => $main)
			{
				foreach($main as $forum)
				{
					$perms = $permissions;
					foreach($usergroupcache as $gid => $usergroup)
					{

						if($fperms[$forum['fid']][$gid])
						{
							$perms[$gid] = $fperms[$forum['fid']][$gid];
						}
						if($perms[$gid])
						{
							$forumpermissions[$forum['fid']][$gid] = $perms[$gid];
						}
					}
					$this->buildforumpermissions($perms, $forum['fid']);
				}
			}
		}
	}

	function updatestats()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."threads WHERE visible='1'");
		$stats['numthreads'] = $db->num_rows($query);
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."posts WHERE visible='1'");
		$stats['numposts'] = $db->num_rows($query);
		$query = $db->query("SELECT uid, username FROM ".TABLE_PREFIX."users ORDER BY uid DESC");
		$stats['numusers'] = $db->num_rows($query);
		$lastmember = $db->fetch_array($query);
		$stats['lastuid'] = $lastmember['uid'];
		$stats['lastusername'] = $lastmember['username'];
		$this->update("stats", $stats);
	}

	function updatemoderators()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."moderators");
		while($mod = $db->fetch_array($query))
		{
			$mods[$mod['fid']][$mod['uid']] = $mod;
		}
		$this->update("moderators", $mods);
	}


	function updateforums()
	{
		global $db;
		$exclude = array("threads", "posts", "lastpost", "lastposter", "lastposttid");
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums  ORDER BY pid, disporder");
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

	function updateusertitles()
	{
		global $db;
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles ORDER BY posts DESC");
		while($usertitle = $db->fetch_array($query))
		{
			$usertitles[] = $usertitle;
		}
		$this->update("usertitles", $usertitles);
	}

	function updatereportedposts()
	{
		global $db;
		$query = $db->query("SELECT COUNT(rid) AS unreadcount FROM ".TABLE_PREFIX."reportedposts WHERE reportstatus='0'");
		$num = $db->fetch_array($query);
		$query = $db->query("SELECT COUNT(rid) AS reportcount FROM ".TABLE_PREFIX."reportedposts");
		$total = $db->fetch_array($query);
		$query = $db->query("SELECT dateline FROM ".TABLE_PREFIX."reportedposts WHERE reportstatus='0' ORDER BY dateline DESC");
		$latest = $db->fetch_array($query);
		$reports['unread'] = $num['unreadcount'];
		$reports['total'] = $total['reportcount'];
		$reports['lastdateline'] = $latest['dateline'];
		$this->update("reportedposts", $reports);
	}

	function updatemycodes()
	{
		global $db;
		$query = $db->query("SELECT regex, replacement FROM mybb_mycodes WHERE active='yes'");
		while($mycode = $db->fetch_array($query))
		{
			$mycodes[] = $mycode;
		}
		$this->update("mycode", $mycode);
	}
}
?>
