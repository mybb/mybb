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
     * @var \DB_Base $db
     */
    private $db;

    /**
     * @var bool $debugMode
     */
    private $debugMode;

    /**
     * Concrete cache store, used to fetch cache contents.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    private $store;

    /**
     * Cache contents.
     *
     * @var array
     */
    private $cache;

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
     * Create a new instance of the data cache.
     *
     * @param \DB_Base $db Database instance to load data if it doesn't exist in the cache.
     * @param bool $debugMode Whether debug stats relating to the cache should be generated.
     * @param \Illuminate\Contracts\Cache\Repository|null $cacheStore A concrete cache store to use.
     */
    public function __construct(
        \DB_Base $db,
        bool $debugMode,
        \Illuminate\Contracts\Cache\Repository $cacheStore = null
    ) {
        $this->db = $db;
        $this->debugMode = $debugMode;
        $this->store = $cacheStore;

        $this->initCache();
    }

    /**
     * Initialise the cache, reading all entries from the database if we are using a database cache.
     */
    private function initCache()
    {
        $this->cache = [];

        if (is_null($this->store)) {
            $query = $this->db->simple_select("datacache", "title,cache");
            while ($data = $this->db->fetch_array($query)) {
                $this->cache[$data['title']] = my_unserialize($data['cache']);
            }
        }
    }

    /**
     * Read cache from files or db.
     *
     * @param string $name The cache component to read.
     * @param boolean $hard If true, cannot be overwritten during script execution.
     *
     * @return mixed
     */
    public function read(string $name, bool $hard = false)
    {
        // Already have this cache and we're not doing a hard refresh? Return cached copy
        if (isset($this->cache[$name]) && $hard == false) {
            return $this->cache[$name];
        } else {
            if ($hard == false && is_null($this->store)) {
                // If we're not hard refreshing, and this cache doesn't exist, return false
                // It would have been loaded pre-global if it did exist anyway...
                return false;
            }
        }

        get_execution_time();

        if (is_null($this->store)) {
            $data = $this->readFromDatabase($name);
        } else {
            $data = $this->store->rememberForever($name, function () use ($name) {
                return $this->readFromDatabase($name);
            });
        }

        $callTime = get_execution_time();
        $this->call_time += $callTime;
        $this->call_count++;

        if ($this->debugMode) {
            $hit = !is_null($data);

            $this->debug_call("read:{$name}", $callTime, $hit);
        }

        // Cache locally
        $this->cache[$name] = $data;

        return $data;
    }

    /**
     * Read a cache entry from the database.
     *
     * @param string $name The name of the cache item to read.
     *
     * @return mixed|null The cached data, or null if it doesn't exist.
     */
    private function readFromDatabase(string $name)
    {
        $query = $this->db->simple_select('datacache', 'title, cache',
            "title='{$this->db->escape_string($name)}'");
        $cacheData = $this->db->fetch_array($query);

        if (isset($cacheData['title']) && !empty($cacheData['title'])) {
            return my_unserialize($cacheData['cache']);
        }

        return null;
    }

    /**
     * Update cache contents.
     *
     * @param string $name The cache content identifier.
     * @param mixed $contents The cache content.
     */
    public function update(string $name, $contents)
    {
        $this->cache[$name] = $contents;

        // We ALWAYS keep a running copy in the db just incase we need it
        $dbContents = $this->db->escape_string(my_serialize($contents));

        $replace_array = array(
            'title' => $this->db->escape_string($name),
            'cache' => $dbContents,
        );
        $this->db->replace_query('datacache', $replace_array, '', false);

        if (!is_null($this->store)) {
            get_execution_time();

            $this->store->forever($name, $contents);

            $callTime = get_execution_time();
            $this->call_time += $callTime;
            $this->call_count++;

            if ($this->debugMode) {
                $this->debug_call("set:{$name}", $callTime, true);
            }
        }
    }

    /**
     * Delete cache contents.
     *
     * @param string $name Cache name or title
     * @param boolean $greedy To delete all cache starting with `$name_`.
     */
    function delete($name, $greedy = false)
    {
        // Prepare for database query.
        $dbName = $this->db->escape_string($name);
        $where = "title = '{$dbName}'";

        if (!is_null($this->store)) {
            try {
                get_execution_time();

                $hit = $this->store->delete($name);

                $callTime = get_execution_time();
                $this->call_time += $callTime;
                $this->call_count++;

                if ($this->debugMode) {
                    $this->debug_call("delete:{$name}", $callTime, $hit);
                }
            } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                // ignored - thrown if the name isn't a legal value
            }
        }

        // Greedy?
        if ($greedy) {
            $name .= '_';
            $names = array();
            $keys = array_keys($this->cache);

            foreach ($keys as $key) {
                if (strpos($key, $name) === 0) {
                    $names[$key] = 0;
                }
            }

            // TODO: Can this query be simplified?
            $ldbname = strtr($dbName,
                array(
                    '%' => '=%',
                    '=' => '==',
                    '_' => '=_',
                )
            );

            $where .= " OR title LIKE '{$ldbname}=_%' ESCAPE '='";

            if (!is_null($this->store)) {
                $query = $this->db->simple_select('datacache', 'title', $where);

                while ($row = $this->db->fetch_array($query)) {
                    $names[$row['title']] = 0;
                }

                try {
                    get_execution_time();

                    $hit = $this->store->deleteMultiple(array_keys($names));

                    $callTime = get_execution_time();
                    $this->call_time += $callTime;
                    $this->call_count++;

                    if ($this->debugMode) {
                        $this->debug_call("delete:{$name}", $callTime, $hit);
                    }
                } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                    // ignored - thrown if the name isn't a legal value
                }
            }
        }

        // Delete database cache
        $this->db->delete_query('datacache', $where);
    }

    /**
     * Debug a cache call to a non-database cache handler
     *
     * @param string $string The cache key.
     * @param int $qtime The time it took to perform the call.
     * @param boolean $hit Hit or miss status.
     */
    private function debug_call(string $string, int $qtime, bool $hit)
    {
        global $mybb, $plugins;

        $debug_extra = '';
        if ($plugins->current_hook) {
            $debug_extra = "<div style=\"float_right\">(Plugin Hook: {$plugins->current_hook})</div>";
        }

        if ($hit) {
            $hit_status = 'HIT';
        } else {
            $hit_status = 'MISS';
        }

        $cache_data = explode(':', $string);
        $cache_method = $cache_data[0];
        $cache_key = $cache_data[1];

        $this->cache_debug = "<table style=\"background-color: #666;\" width=\"95%\" cellpadding=\"4\" cellspacing=\"1\" align=\"center\">
<tr>
	<td style=\"background-color: #ccc;\">{$debug_extra}<div><strong>#{$this->call_count} - " . ucfirst($cache_method) . " Call</strong></div></td>
</tr>
<tr style=\"background-color: #fefefe;\">
	<td><span style=\"font-family: Courier; font-size: 14px;\">({$mybb->config['cache_store']}) [{$hit_status}] " . htmlspecialchars_uni($cache_key) . "</span></td>
</tr>
<tr>
	<td bgcolor=\"#ffffff\">Call Time: " . format_time_duration($qtime) . "</td>
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
     *
     * @return int The size of the cache.
     */
    public function size_of(string $name = '')
    {
        // TODO: Laravel's caching library doesn't provide a convenient way to get the size of a given cache item.
        if ($name) {
            $query = $this->db->simple_select('datacache', 'cache', "title='{$this->db->escape_string($name)}'");
            return my_strlen($this->db->fetch_field($query, "cache"));
        } else {
            return $this->db->fetch_size('datacache');
        }
    }

    /**
     * Update the MyBB version in the cache.
     *
     */
    public function update_version()
    {
        global $mybb;

        $version = array(
            "version" => $mybb->version,
            "version_code" => $mybb->version_code,
        );

        $this->update("version", $version);
    }

    /**
     * Update the attachment type cache.
     *
     */
    public function update_attachtypes()
    {
        $types = array();

        $query = $this->db->simple_select('attachtypes', '*', 'enabled=1');
        while ($type = $this->db->fetch_array($query)) {
            $type['extension'] = my_strtolower($type['extension']);
            $types[$type['extension']] = $type;
        }

        $this->update('attachtypes', $types);
    }

    /**
     * Update the smilies cache.
     *
     */
    public function update_smilies()
    {
        $smilies = array();

        $query = $this->db->simple_select('smilies', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
        while ($smilie = $this->db->fetch_array($query)) {
            $smilies[$smilie['sid']] = $smilie;
        }

        $this->update('smilies', $smilies);
    }

    /**
     * Update the posticon cache.
     *
     */
    public function update_posticons()
    {
        $icons = array();

        $query = $this->db->simple_select('icons', 'iid, name, path');
        while ($icon = $this->db->fetch_array($query)) {
            $icons[$icon['iid']] = $icon;
        }

        $this->update('posticons', $icons);
    }

    /**
     * Update the badwords cache.
     *
     */
    public function update_badwords()
    {
        $badwords = array();

        $query = $this->db->simple_select('badwords', '*');
        while ($badword = $this->db->fetch_array($query)) {
            $badwords[$badword['bid']] = $badword;
        }

        $this->update('badwords', $badwords);
    }

    /**
     * Update the usergroups cache.
     *
     */
    public function update_usergroups()
    {
        $query = $this->db->simple_select('usergroups');

        $gs = array();
        while ($g = $this->db->fetch_array($query)) {
            $gs[$g['gid']] = $g;
        }

        $this->update('usergroups', $gs);
    }

    /**
     * Update the forum permissions cache.
     *
     * @return bool When failed, returns false.
     */
    public function update_forumpermissions()
    {
        global $forum_cache;

        $this->built_forum_permissions = array(0);

        // Get our forum list
        cache_forums(true);
        if (!is_array($forum_cache)) {
            return false;
        }

        reset($forum_cache);
        $fcache = array();

        // Resort in to the structure we require
        foreach ($forum_cache as $fid => $forum) {
            $this->forum_permissions_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }

        // Sort children
        foreach ($fcache as $pid => $value) {
            ksort($fcache[$pid]);
        }
        ksort($fcache);

        // Fetch forum permissions from the database
        $query = $this->db->simple_select('forumpermissions');
        while ($forum_permission = $this->db->fetch_array($query)) {
            $this->forum_permissions[$forum_permission['fid']][$forum_permission['gid']] = $forum_permission;
        }

        $this->build_forum_permissions();
        $this->update('forumpermissions', $this->built_forum_permissions);

        return true;
    }

    /**
     * Build the forum permissions array
     *
     * @param array $permissions An optional permissions array.
     * @param int $pid An optional permission id.
     */
    private function build_forum_permissions(array $permissions = array(), int $pid = 0)
    {
        $usergroups = array_keys($this->read("usergroups", true));
        if ($this->forum_permissions_forum_cache[$pid]) {
            foreach ($this->forum_permissions_forum_cache[$pid] as $main) {
                foreach ($main as $forum) {
                    $perms = $permissions;
                    foreach ($usergroups as $gid) {
                        if ($this->forum_permissions[$forum['fid']][$gid]) {
                            $perms[$gid] = $this->forum_permissions[$forum['fid']][$gid];
                        }
                        if ($perms[$gid]) {
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
    public function update_stats()
    {
        require_once MYBB_ROOT . 'inc/functions_rebuild.php';
        rebuild_stats();
    }

    /**
     * Update the statistics cache
     *
     */
    public function update_statistics()
    {
        $query = $this->db->simple_select('users', 'uid, username, referrals', 'referrals>0',
            array('order_by' => 'referrals', 'order_dir' => 'DESC', 'limit' => 1));
        $topreferrer = $this->db->fetch_array($query);

        $timesearch = TIME_NOW - 86400;
        switch ($this->db->type) {
            case 'pgsql':
                $group_by = $this->db->build_fields_string('users', 'u.');
                break;
            default:
                $group_by = 'p.uid';
                break;
        }

        $query = $this->db->query('
			SELECT u.uid, u.username, COUNT(pid) AS poststoday
			FROM ' . TABLE_PREFIX . 'posts p
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (p.uid=u.uid)
			WHERE p.dateline>' . $timesearch . ' AND p.visible=1
			GROUP BY ' . $group_by . ' ORDER BY poststoday DESC
			LIMIT 1
		');
        $topposter = $this->db->fetch_array($query);

        $query = $this->db->simple_select('users', 'COUNT(uid) AS posters', 'postnum>0');
        $posters = $this->db->fetch_field($query, 'posters');

        $statistics = array(
            'time' => TIME_NOW,
            'top_referrer' => (array)$topreferrer,
            'top_poster' => (array)$topposter,
            'posters' => $posters,
        );

        $this->update('statistics', $statistics);
    }

    /**
     * Update the moderators cache.
     *
     * @return bool Returns false on failure
     */
    public function update_moderators()
    {
        global $forum_cache;

        $this->built_moderators = array(0);

        // Get our forum list
        cache_forums(true);
        if (!is_array($forum_cache)) {
            return false;
        }

        reset($forum_cache);
        $fcache = array();

        // Resort in to the structure we require
        foreach ($forum_cache as $fid => $forum) {
            $this->moderators_forum_cache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }

        // Sort children
        foreach ($fcache as $pid => $value) {
            ksort($fcache[$pid]);
        }
        ksort($fcache);

        $this->moderators = array();

        // Fetch moderators from the database
        $query = $this->db->query("
			SELECT m.*, u.username, u.usergroup, u.displaygroup
			FROM " . TABLE_PREFIX . "moderators m
			LEFT JOIN " . TABLE_PREFIX . "users u ON (m.id=u.uid)
			WHERE m.isgroup = '0'
			ORDER BY u.username
		");
        while ($moderator = $this->db->fetch_array($query)) {
            $this->moderators[$moderator['fid']]['users'][$moderator['id']] = $moderator;
        }

        if (!function_exists("sort_moderators_by_usernames")) {
            function sort_moderators_by_usernames($a, $b)
            {
                return strcasecmp($a['username'], $b['username']);
            }
        }

        //Fetch moderating usergroups from the database
        $query = $this->db->query("
			SELECT m.*, u.title
			FROM " . TABLE_PREFIX . "moderators m
			LEFT JOIN " . TABLE_PREFIX . "usergroups u ON (m.id=u.gid)
			WHERE m.isgroup = '1'
			ORDER BY u.title
		");
        while ($moderator = $this->db->fetch_array($query)) {
            $this->moderators[$moderator['fid']]['usergroups'][$moderator['id']] = $moderator;
        }

        if (is_array($this->moderators)) {
            foreach (array_keys($this->moderators) as $fid) {
                uasort($this->moderators[$fid], 'sort_moderators_by_usernames');
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
    public function update_awaitingactivation()
    {
        $query = $this->db->simple_select('users', 'COUNT(uid) AS awaitingusers', 'usergroup=\'5\'');
        $awaitingusers = (int)$this->db->fetch_field($query, 'awaitingusers');

        $data = array(
            'users' => $awaitingusers,
            'time' => TIME_NOW,
        );

        $this->update('awaitingactivation', $data);
    }

    /**
     * Build the moderators array
     *
     * @param array $moderators An optional moderators array (moderators of the parent forum for example).
     * @param int $pid An optional parent ID.
     */
    private function build_moderators(array $moderators = array(), int $pid = 0)
    {
        if (isset($this->moderators_forum_cache[$pid])) {
            foreach ($this->moderators_forum_cache[$pid] as $main) {
                foreach ($main as $forum) {
                    $forum_mods = array();
                    if (count($moderators)) {
                        $forum_mods = $moderators;
                    }
                    // Append - local settings override that of a parent - array_merge works here
                    if (isset($this->moderators[$forum['fid']])) {
                        if (is_array($forum_mods) && count($forum_mods)) {
                            $forum_mods = array_merge($forum_mods, $this->moderators[$forum['fid']]);
                        } else {
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
    public function update_forums()
    {
        $forums = array();

        // Things we don't want to cache
        $exclude = array(
            "unapprovedthreads",
            "unapprovedposts",
            "threads",
            "posts",
            "lastpost",
            "lastposter",
            "lastposttid",
            "lastposteruid",
            "lastpostsubject",
            "deletedthreads",
            "deletedposts",
        );

        $query = $this->db->simple_select('forums', '*', '', array('order_by' => 'pid,disporder'));
        while ($forum = $this->db->fetch_array($query)) {
            foreach ($forum as $key => $val) {
                if (in_array($key, $exclude)) {
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
    public function update_usertitles()
    {
        $usertitles = array();
        $query = $this->db->simple_select('usertitles', 'utid, posts, title, stars, starimage', '',
            array('order_by' => 'posts', 'order_dir' => 'DESC'));
        while ($usertitle = $this->db->fetch_array($query)) {
            $usertitles[] = $usertitle;
        }

        $this->update("usertitles", $usertitles);
    }

    /**
     * Update reported content cache.
     *
     */
    public function update_reportedcontent()
    {
        $query = $this->db->simple_select("reportedcontent", "COUNT(rid) AS unreadcount", "reportstatus='0'");
        $num = $this->db->fetch_array($query);

        $query = $this->db->simple_select("reportedcontent", "COUNT(rid) AS reportcount");
        $total = $this->db->fetch_array($query);

        $query = $this->db->simple_select("reportedcontent", "dateline", "reportstatus='0'",
            array('order_by' => 'dateline', 'order_dir' => 'DESC'));
        $latest = $this->db->fetch_array($query);

        $reports = array(
            "unread" => $num['unreadcount'],
            "total" => $total['reportcount'],
            "lastdateline" => $latest['dateline'],
        );

        $this->update("reportedcontent", $reports);
    }

    /**
     * Update mycode cache.
     *
     */
    public function update_mycode()
    {
        $mycodes = array();
        $query = $this->db->simple_select("mycode", "regex, replacement", "active=1",
            array('order_by' => 'parseorder'));
        while ($mycode = $this->db->fetch_array($query)) {
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
    public function update_mailqueue($last_run = 0, $lock_time = 0)
    {
        $query = $this->db->simple_select("mailqueue", "COUNT(*) AS queue_size");
        $queue_size = $this->db->fetch_field($query, "queue_size");

        $mailqueue = $this->read("mailqueue");
        if (!is_array($mailqueue)) {
            $mailqueue = array();
        }
        $mailqueue['queue_size'] = $queue_size;
        if ($last_run > 0) {
            $mailqueue['last_run'] = $last_run;
        }
        $mailqueue['locked'] = $lock_time;

        $this->update("mailqueue", $mailqueue);
    }

    /**
     * Update update_check cache (dummy function used by upgrade/install scripts)
     */
    public function update_update_check()
    {
        $update_cache = array(
            "dateline" => TIME_NOW,
        );

        $this->update("update_check", $update_cache);
    }

    /**
     * Update default_theme cache
     */
    public function update_default_theme()
    {
        $query = $this->db->simple_select("themes", "name, tid, properties, stylesheets", "def='1'",
            array('limit' => 1));
        $theme = $this->db->fetch_array($query);
        $this->update("default_theme", $theme);
    }

    /**
     * Updates the tasks cache saving the next run time
     */
    public function update_tasks()
    {
        $query = $this->db->simple_select("tasks", "nextrun", "enabled=1",
            array("order_by" => "nextrun", "order_dir" => "asc", "limit" => 1));
        $next_task = $this->db->fetch_array($query);

        $task_cache = $this->read("tasks");
        if (!is_array($task_cache)) {
            $task_cache = array();
        }
        $task_cache['nextrun'] = $next_task['nextrun'];

        if (!$task_cache['nextrun']) {
            $task_cache['nextrun'] = TIME_NOW + 3600;
        }

        $this->update("tasks", $task_cache);
    }

    /**
     * Updates the banned IPs cache
     */
    public function update_bannedips()
    {
        $banned_ips = array();
        $query = $this->db->simple_select("banfilters", "fid,filter", "type=1");
        while ($banned_ip = $this->db->fetch_array($query)) {
            $banned_ips[$banned_ip['fid']] = $banned_ip;
        }
        $this->update("bannedips", $banned_ips);
    }

    /**
     * Updates the banned emails cache
     */
    public function update_bannedemails()
    {
        $banned_emails = array();
        $query = $this->db->simple_select("banfilters", "fid, filter", "type = '3'");

        while ($banned_email = $this->db->fetch_array($query)) {
            $banned_emails[$banned_email['fid']] = $banned_email;
        }

        $this->update("bannedemails", $banned_emails);
    }

    /**
     * Updates the search engine spiders cache
     */
    public function update_spiders()
    {
        $spiders = array();
        $query = $this->db->simple_select("spiders", "sid, name, useragent, usergroup", "",
            array("order_by" => "LENGTH(useragent)", "order_dir" => "DESC"));
        while ($spider = $this->db->fetch_array($query)) {
            $spiders[$spider['sid']] = $spider;
        }
        $this->update("spiders", $spiders);
    }

    public function update_most_replied_threads()
    {
        global $mybb;

        $threads = array();

        $query = $this->db->simple_select("threads", "tid, subject, replies, fid, uid", "visible='1'", array(
            'order_by' => 'replies',
            'order_dir' => 'DESC',
            'limit_start' => 0,
            'limit' => $mybb->settings['statslimit'],
        ));
        while ($thread = $this->db->fetch_array($query)) {
            $threads[] = $thread;
        }

        $this->update("most_replied_threads", $threads);
    }

    public function update_most_viewed_threads()
    {
        global $mybb;

        $threads = array();

        $query = $this->db->simple_select("threads", "tid, subject, views, fid, uid", "visible='1'", array(
            'order_by' => 'views',
            'order_dir' => 'DESC',
            'limit_start' => 0,
            'limit' => $mybb->settings['statslimit'],
        ));

        while ($thread = $this->db->fetch_array($query)) {
            $threads[] = $thread;
        }

        $this->update("most_viewed_threads", $threads);
    }

    public function update_banned()
    {
        $bans = array();

        $query = $this->db->simple_select("banned");
        while ($ban = $this->db->fetch_array($query)) {
            $bans[$ban['uid']] = $ban;
        }

        $this->update("banned", $bans);
    }

    public function update_birthdays()
    {
        $birthdays = array();

        // Get today, yesterday, and tomorrow's time (for different timezones)
        $bdaytime = TIME_NOW;
        $bdaydate = my_date("j-n", $bdaytime, '', 0);
        $bdaydatetomorrow = my_date("j-n", ($bdaytime + 86400), '', 0);
        $bdaydateyesterday = my_date("j-n", ($bdaytime - 86400), '', 0);

        $query = $this->db->simple_select("users", "uid, username, usergroup, displaygroup, birthday, birthdayprivacy",
            "birthday LIKE '$bdaydate-%' OR birthday LIKE '$bdaydateyesterday-%' OR birthday LIKE '$bdaydatetomorrow-%'");
        while ($bday = $this->db->fetch_array($query)) {
            // Pop off the year from the birthday because we don't need it.
            $bday['bday'] = explode('-', $bday['birthday']);
            array_pop($bday['bday']);
            $bday['bday'] = implode('-', $bday['bday']);

            if ($bday['birthdayprivacy'] != 'all') {
                ++$birthdays[$bday['bday']]['hiddencount'];
                continue;
            }

            // We don't need any excess caleries in the cache
            unset($bday['birthdayprivacy']);

            $birthdays[$bday['bday']]['users'][] = $bday;
        }

        $this->update("birthdays", $birthdays);
    }

    public function update_groupleaders()
    {
        $groupleaders = array();

        $query = $this->db->simple_select("groupleaders");
        while ($groupleader = $this->db->fetch_array($query)) {
            $groupleaders[$groupleader['uid']][] = $groupleader;
        }

        $this->update("groupleaders", $groupleaders);
    }

    public function update_threadprefixes()
    {
        $prefixes = array();
        $query = $this->db->simple_select("threadprefixes", "*", "",
            array('order_by' => 'prefix', 'order_dir' => 'ASC'));

        while ($prefix = $this->db->fetch_array($query)) {
            $prefixes[$prefix['pid']] = $prefix;
        }

        $this->update("threadprefixes", $prefixes);
    }

    public function update_forumsdisplay()
    {
        $fd_statistics = array();

        $time = TIME_NOW; // Look for announcements that don't end, or that are ending some time in the future
        $query = $this->db->simple_select("announcements", "fid", "enddate = '0' OR enddate > '{$time}'",
            array("order_by" => "aid"));

        if ($this->db->num_rows($query)) {
            while ($forum = $this->db->fetch_array($query)) {
                if (!isset($fd_statistics[$forum['fid']]['announcements'])) {
                    $fd_statistics[$forum['fid']]['announcements'] = 1;
                }
            }
        }

        // Do we have any mod tools to use in our forums?
        $query = $this->db->simple_select("modtools", "forums, tid", '', array("order_by" => "tid"));

        if ($this->db->num_rows($query)) {
            unset($forum);
            while ($tool = $this->db->fetch_array($query)) {
                $forums = explode(",", $tool['forums']);

                foreach ($forums as $forum) {
                    if (!$forum) {
                        $forum = -1;
                    }

                    if (!isset($fd_statistics[$forum]['modtools'])) {
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
    public function update_profilefields()
    {
        $fields = array();
        $query = $this->db->simple_select("profilefields", "*", "", array('order_by' => 'disporder'));
        while ($field = $this->db->fetch_array($query)) {
            $fields[] = $field;
        }

        $this->update("profilefields", $fields);
    }

    /**
     * Update the report reasons cache.
     *
     * @param bool $no_plugins Whether to disable the `report_content_types` plugin hook.
     */
    public function update_reportreasons(bool $no_plugins = false)
    {
        $content_types = array('post', 'profile', 'reputation');
        if (!$no_plugins) {
            global $plugins;
            $content_types = $plugins->run_hooks("report_content_types", $content_types);
        }

        $reasons = array();

        $query = $this->db->simple_select("reportreasons", "*", "", array('order_by' => 'disporder'));
        while ($reason = $this->db->fetch_array($query)) {
            if ($reason['appliesto'] == 'all') {
                foreach ($content_types as $content) {
                    $reasons[$content][] = array(
                        'rid' => $reason['rid'],
                        'title' => $reason['title'],
                        'extra' => $reason['extra'],
                    );
                }
            } elseif ($reason['appliesto'] != '') {
                $appliesto = explode(",", $reason['appliesto']);
                foreach ($appliesto as $content) {
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
    public function reload_mostonline()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='mostonline'");
        $this->update("mostonline", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_plugins()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='plugins'");
        $this->update("plugins", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_last_backup()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='last_backup'");
        $this->update("last_backup", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_internal_settings()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='internal_settings'");
        $this->update("internal_settings", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_version_history()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='version_history'");
        $this->update("version_history", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_modnotes()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='modnotes'");
        $this->update("modnotes", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_adminnotes()
    {
        $query = $this->db->simple_select("datacache", "title,cache", "title='adminnotes'");
        $this->update("adminnotes", my_unserialize($this->db->fetch_field($query, "cache")));
    }

    public function reload_mybb_credits()
    {
        admin_redirect('index.php?module=home-credits&amp;fetch_new=-2');
    }
}
