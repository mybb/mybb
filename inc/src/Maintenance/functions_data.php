<?php
/**
 * Functions used for data seeding, synchronization, and migration.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use DB_Base;
use Exception;
use MyBB;
use MyLanguage;
use pluginSystem;
use PostDataHandler;
use Throwable;

use function MyBB\app;

#region installation
/**
 * @throws Exception
 */
function writeConfigurationFile(array $config): void
{
    $type = addcslashes($config['database']['type'], '\\\'');

    if (in_array($type, ['mysql', 'pgsql'])) {
        $type .= '_pdo';
    }

    foreach ($config['database'] as &$value) {
        $value = addcslashes($value ?? '', '\\\'');
    }
    unset($value);

    // Write the configuration file
    $configdata = <<<PHP
    <?php
    /**
     * Database configuration
     *
     * Please see the MyBB Docs for advanced
     * database configuration for larger installations
     * https://docs.mybb.com/
     */

    \$config['database']['type'] = '{$type}';
    \$config['database']['database'] = '{$config['database']['database']}';
    \$config['database']['table_prefix'] = '{$config['database']['table_prefix']}';

    \$config['database']['hostname'] = '{$config['database']['hostname']}';
    \$config['database']['username'] = '{$config['database']['username']}';
    \$config['database']['password'] = '{$config['database']['password']}';

    /**
     * Admin CP directory
     *  For security reasons, it is recommended you
     *  rename your Admin CP directory. You then need
     *  to adjust the value below to point to the
     *  new directory.
     */

    \$config['admin_dir'] = 'admin';

    /**
     * Hide all Admin CP links
     *  If you wish to hide all Admin CP links
     *  on the front end of the board after
     *  renaming your Admin CP directory, set this
     *  to 1.
     */

    \$config['hide_admin_links'] = 0;

    /**
     * Data-cache configuration
     *  The data cache is a temporary cache
     *  of the most commonly accessed data in MyBB.
     *  By default, the database is used to store this data.
     *
     *  If you wish to use the file system (cache/ directory), MemCache (or MemCached), xcache, APC, APCu, eAccelerator or Redis
     *  you can change the value below to 'files', 'memcache', 'memcached', 'xcache', 'apc', 'apcu', 'eaccelerator' or 'redis' from 'db'.
     */

    \$config['cache_store'] = 'db';

    /**
     * Memcache configuration
     *  If you are using memcache or memcached as your
     *  data-cache, you need to configure the hostname
     *  and port of your memcache server below.
     *
     * If not using memcache, ignore this section.
     */

    \$config['memcache']['host'] = 'localhost';
    \$config['memcache']['port'] = 11211;

    /**
     * Redis configuration
     *  If you are using Redis as your data-cache
     *  you need to configure the hostname and port
     *  of your redis server below. If you want
     *  to connect via unix sockets, use the full
     *  path to the unix socket as host and leave
     *  the port setting unconfigured or false.
     */

    \$config['redis']['host'] = 'localhost';
    \$config['redis']['port'] = 6379;

    /**
     * Super Administrators
     *  A comma separated list of user IDs who cannot
     *  be edited, deleted or banned in the Admin CP.
     *  The administrator permissions for these users
     *  cannot be altered either.
     */

    \$config['super_admins'] = '1';

    /**
     * Database Encoding
     *  If you wish to set an encoding for MyBB uncomment
     *  the line below (if it isn't already) and change
     *  the current value to the mysql charset:
     *  http://dev.mysql.com/doc/refman/5.1/en/charset-mysql.html
     */

    /**
     * Automatic Log Pruning
     *  The MyBB task system can automatically prune
     *  various log files created by MyBB.
     *  To enable this functionality for the logs below, set the
     *  the number of days before each log should be pruned.
     *  If you set the value to 0, the logs will not be pruned.
     */

    \$config['log_pruning'] = array(
        'admin_logs' => 365, // Administrator logs
        'mod_logs' => 365, // Moderator logs
        'task_logs' => 30, // Scheduled task logs
        'mail_logs' => 180, // Mail error logs
        'user_mail_logs' => 180, // User mail logs
        'promotion_logs' => 180 // Promotion logs
    );

    /**
     * Disallowed Remote Hosts
     *  List of hosts the fetch_remote_file() function will not
     *  perform requests to.
     *  It is recommended that you enter hosts resolving to the
     *  forum server here to prevent Server Side Request
     *  Forgery attacks.
     */

    \$config['disallowed_remote_hosts'] = array(
        'localhost',
    );

    /**
     * Disallowed Remote Addresses
     *  List of IPv4 addresses the fetch_remote_file() function
     *  will not perform requests to.
     *  It is recommended that you enter addresses resolving to
     *  the forum server here to prevent Server Side Request
     *  Forgery attacks.
     *  Removing all values disables resolving hosts in that
     *  function.
     */

    \$config['disallowed_remote_addresses'] = array(
        '127.0.0.1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    );

    PHP;

    try {
        $file = fopen(MYBB_ROOT . 'inc/config.php', 'w');
        fwrite($file, $configdata);
        fclose($file);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(MYBB_ROOT . "inc/config.php");
        }
    } catch (Throwable) {
        throw new Exception();
    }
}

function createDatabaseStructure(array $config, DB_Base $db): void
{
    $file = getAvailableDatabaseDriversData()[ $config['database']['type'] ]['structure_file'] ?? null;
    $filePath = MYBB_ROOT . 'inc/schemas/' . $file;

    if ($file !== null && file_exists($filePath)) {
        /** @var array $tables */
        require_once $filePath;

        foreach ($tables as $val) {
            $val = preg_replace('#mybb_(\S+?)([\s\.,\(]|$)#', $config['database']['table_prefix'] . '\\1\\2', $val);
            $val = preg_replace('#;$#', $db->build_create_table_collation() . ";", $val);
            preg_match('#CREATE TABLE (\S+)(\s?|\(?)\(#i', $val, $match);
            if (!empty($match[1])) {
                if ($db->type == 'sqlite') {
                    $db->close_cursors();
                }

                $db->drop_table(my_substr($match[1], my_strlen($config['database']['table_prefix'])));
            }
            $db->write_query($val);
        }

        // Make fulltext columns if supported
        if ($db->supports_fulltext('threads')) {
            $db->create_fulltext_index('threads', 'subject');
        }
        if ($db->supports_fulltext_boolean('posts')) {
            $db->create_fulltext_index('posts', 'message');
        }
    } else {
        throw new Exception('Database structure file not found');
    }
}

function populateDatabase(array $config, DB_Base $db): void
{
    $inserts = require MYBB_ROOT . 'inc/seeds/db_inserts.php';

    foreach ($inserts as $tableName => $tableInserts) {
        $db->insert_query_multiple($tableName, $tableInserts);
    }

    // Update the sequences for PgSQL
    if ($config['database']['type'] == "pgsql") {
        $sequences = [
            [
                'table' => 'attachtypes',
                'column' => 'atid',
            ],
            [
                'table' => 'forums',
                'column' => 'fid',
            ],
            [
                'table' => 'helpdocs',
                'column' => 'hid',
            ],
            [
                'table' => 'helpsections',
                'column' => 'sid',
            ],
            [
                'table' => 'icons',
                'column' => 'iid',
            ],
            [
                'table' => 'profilefields',
                'column' => 'fid',
            ],
            [
                'table' => 'smilies',
                'column' => 'sid',
            ],
            [
                'table' => 'spiders',
                'column' => 'sid',
            ],
            [
                'table' => 'templategroups',
                'column' => 'gid',
            ],
        ];

        foreach ($sequences as $sequence) {
            $db->query("
                SELECT setval(
                    '{$config['database']['table_prefix']}{$sequence['table']}_{$sequence['column']}_seq',
                    (
                        SELECT max({$sequence['column']})
                        FROM {$config['database']['table_prefix']}{$sequence['table']}
                    )
                );
            ");
        }
    }

    insertAdminOptions($db);
    insertUsergroups($config, $db);
    insertAdminviews($db);
    insertTasks($db);
}

function insertAdminOptions(DB_Base $db): int
{
    $entries = [];

    $db->delete_query('adminoptions');

    $tree = getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/adminoptions.xml');

    foreach ($tree['adminoptions'][0]['user'] as $users) {
        $insertmodule = array();

        $uid = $users['attributes']['uid'];

        foreach ($users['permissions'][0]['module'] as $module) {
            foreach ($module['permission'] as $permission) {
                $insertmodule[$module['attributes']['name']][$permission['attributes']['name']] = $permission['value'];
            }
        }

        $defaultviews = array();
        foreach ($users['defaultviews'][0]['view'] as $view) {
            $defaultviews[$view['attributes']['type']] = $view['value'];
        }

        $adminoptiondata = array(
            'uid' => (int)$uid,
            'cpstyle' => '',
            'notes' => '',
            'permissions' => $db->escape_string(my_serialize($insertmodule)),
            'defaultviews' => $db->escape_string(my_serialize($defaultviews))
        );

        $entries[] = $adminoptiondata;
    }

    $db->insert_query_multiple('adminoptions', $entries);

    return count($entries);
}

function insertUsergroups(array $config, DB_Base $db, ?int &$admin_gid = null): int
{
    $entries = [];

    $tree = getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/usergroups.xml');

    foreach ($tree['usergroups'][0]['usergroup'] as $usergroup) {
        // usergroup[cancp][0][value]
        $new_group = array();
        foreach ($usergroup as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $new_group[$key] = $db->escape_string($value[0]['value']);
        }

        $entries[] = $new_group;

        // If this group can access the admin CP and we haven't established the admin group - set it (just in case we ever change IDs)
        if ($new_group['cancp'] == 1 && $admin_gid === null) {
            $admin_gid = $usergroup['gid'][0]['value'];
        }
    }

    $db->insert_query_multiple('usergroups', $entries);

    // Restart usergroup sequence with correct # of groups
    if ($config['database']['type'] == 'pgsql') {
        $db->query("
            SELECT setval(
                '{$config['database']['table_prefix']}usergroups_gid_seq',
                (SELECT max(gid) FROM {$config['database']['table_prefix']}usergroups)
            );
        ");
    }

    return count($entries);
}

function insertAdminviews(DB_Base $db): int
{
    $entries = [];

    $tree = getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/adminviews.xml');

    // Insert admin views
    foreach ($tree['adminviews'][0]['view'] as $view) {
        $fields = array();
        foreach ($view['fields'][0]['field'] as $field) {
            $fields[] = $field['attributes']['name'];
        }

        $conditions = array();
        if (isset($view['conditions'][0]['condition']) && is_array($view['conditions'][0]['condition'])) {
            foreach ($view['conditions'][0]['condition'] as $condition) {
                if (!$condition['value']) {
                    continue;
                }
                if ($condition['attributes']['is_serialized'] == 1) {
                    $condition['value'] = my_unserialize($condition['value']);
                }
                $conditions[$condition['attributes']['name']] = $condition['value'];
            }
        }

        $custom_profile_fields = array();
        if (isset($view['custom_profile_fields'][0]['field']) && is_array($view['custom_profile_fields'][0]['field'])) {
            foreach ($view['custom_profile_fields'][0]['field'] as $field) {
                $custom_profile_fields[] = $field['attributes']['name'];
            }
        }

        $new_view = array(
            "uid" => 0,
            "type" => $view['attributes']['type'],
            "visibility" => (int)$view['attributes']['visibility'],
            "title" => $view['title'][0]['value'],
            "fields" => my_serialize($fields),
            "conditions" => my_serialize($conditions),
            "custom_profile_fields" => my_serialize($custom_profile_fields),
            "sortby" => $view['sortby'][0]['value'],
            "sortorder" => $view['sortorder'][0]['value'],
            "perpage" => (int)$view['perpage'][0]['value'],
            "view_type" => $view['view_type'][0]['value'],
        );

        $new_view = array_map($db->escape_string(...), $new_view);

        $entries[] = $new_view;
    }

    $db->insert_query_multiple('adminviews', $entries);

    return count($entries);
}

function insertTasks(DB_Base $db): int
{
    $entries = getTaskDefinitions();

    foreach ($entries as &$entry) {
        $entry = array_map($db->escape_string(...), $entry);
    }

    $db->insert_query_multiple('tasks', $entries);

    return count($entries);
}

function writeAcpPin(string $pin): void
{
    $pin = addslashes($pin);

    $file = @fopen(MYBB_ROOT."inc/config.php", "a");

    @fwrite($file, <<<PHP
    /**
     * Admin CP Secret PIN
     *  If you wish to request a PIN
     *  when someone tries to login
     *  on your Admin CP, enter it below.
     */

    \$config['secret_pin'] = '{$pin}';
    PHP);

    @fclose($file);

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(MYBB_ROOT . "inc/config.php");
    }
}

function insertSettings(DB_Base $db, array $settingsOverride): void
{
    $tree = getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/settings.xml');

    $settings = [];

    // Insert all the settings
    foreach ($tree['settings'][0]['settinggroup'] as $settinggroup) {
        $groupdata = array(
            'name' => $db->escape_string($settinggroup['attributes']['name']),
            'title' => $db->escape_string($settinggroup['attributes']['title']),
            'description' => $db->escape_string($settinggroup['attributes']['description']),
            'disporder' => (int)$settinggroup['attributes']['disporder'],
            'isdefault' => $settinggroup['attributes']['isdefault'],
        );
        $gid = $db->insert_query('settinggroups', $groupdata);

        foreach ($settinggroup['setting'] as $setting) {
            $settings[] = array(
                'name' => $db->escape_string($setting['attributes']['name']),
                'title' => $db->escape_string($setting['title'][0]['value']),
                'description' => $db->escape_string($setting['description'][0]['value'] ?? ''),
                'optionscode' => $db->escape_string($setting['optionscode'][0]['value']),
                'value' => $db->escape_string($setting['settingvalue'][0]['value']),
                'disporder' => (int)$setting['disporder'][0]['value'],
                'gid' => $gid,
                'isdefault' => 1
            );
        }
    }

    $db->insert_query_multiple('settings', $settings);

    if (my_substr($settingsOverride['bburl'], -1, 1) == '/') {
        $settingsOverride['bburl'] = my_substr($settingsOverride['bburl'], 0, -1);
    }

    foreach ($settingsOverride as $name => $value) {
        $db->update_query('settings', [
            'value' => $db->escape_string($value),
        ], "name = '" . $db->escape_string($name) . "'");
    }

    writeSettingsFile(
        getSettingsFromDatabase(),
    );
}

function insertUser(DB_Base $db, array $data): array
{
    require_once MYBB_ROOT . 'inc/functions_user.php';

    $loginkey = generate_loginkey();
    $passwordFields = create_password($data['password']);

    $newuser = $passwordFields + [
        'username' => $db->escape_string($data['username']),
        'loginkey' => $loginkey,
        'email' => $db->escape_string($data['email']),
        'usergroup' => $data['usergroup'],
        'regdate' => TIME_NOW,
        'lastactive' => TIME_NOW,
        'lastvisit' => TIME_NOW,
        'allownotices' => 1,
        'receivepms' => 1,
        'pmnotice' => 1,
        'pmnotify' => 1,
        'showimages' => 1,
        'showvideos' => 1,
        'showsigs' => 1,
        'showavatars' => 1,
        'showquickreply' => 1,
        'signature' => '',
        'timezone' => 0,
        'regip' => $db->escape_binary(my_inet_pton($data['regip'])),
        'buddylist' => '',
        'ignorelist' => '',
        'pmfolders' => "0**$%%$1**$%%$2**$%%$3**$%%$4**",
        'notepad' => '',
        'showredirect' => 1,
        'usernotes' => '',
        'language' => $db->escape_string($data['language']),
    ];

    $uid = $db->insert_query('users', $newuser);

    return [
        'uid' => $uid,
        'loginkey' => $loginkey,
    ];
}

function buildDatacache(DB_Base $db): void
{
    global $cache; // $cache required in cache_forums()

    // values that need to be initialized
    $cache = getCache();

    $cache->update("plugins", array());
    $cache->update("mostonline", array(
        'numusers' => 0,
        'time' => 0,
    ));
    $cache->update("internal_settings", array('encryption_key' => random_str(32)));
    $cache->update_default_theme();

    $version_history = array();
    $dh = opendir(MYBB_ROOT . "inc/upgrades");
    while (($file = readdir($dh)) !== false) {
        if (preg_match("#upgrade([0-9]+).php$#i", $file, $match)) {
            $version_history[$match[1]] = $match[1];
        }
    }
    sort($version_history, SORT_NUMERIC);
    $cache->update("version_history", $version_history);

    // Schedule an update check so it occurs an hour ago.  Gotta stay up to date!
    $update['nextrun'] = TIME_NOW - 3600;
    $db->update_query("tasks", $update, "tid='12'");

    $cache->update_update_check();

    // remaining caches
    rebuildDatacache();
}

function createInitialContent(array $config, DB_Base $db, bool $development_mode = false): void
{
    $mybb = app(MyBB::class);
    $lang = app(MyLanguage::class);

    // required for the PostDataHandler process
    $mybb->parse_cookies();

    require_once MYBB_ROOT . "inc/class_session.php";

    $session = new \session();
    $session->init();
    $mybb->session = &$session;

    $lang->load('global');
    $lang->load('messages');

    require_once MYBB_ROOT . 'inc/class_plugins.php';
    $GLOBALS['plugins'] = new pluginSystem();

    if (!isset($GLOBALS['groupscache']) || !is_array($GLOBALS['groupscache'])) {
        $cache = getCache();
        $GLOBALS['groupscache'] = $cache->read("usergroups");
    }


    require_once MYBB_ROOT . 'inc/datahandler.php';
    require_once MYBB_ROOT . 'inc/datahandlers/post.php';

    $posthandler = new PostDataHandler('insert');
    $posthandler->action = 'thread';
    $posthandler->set_data([
        'fid' => 2,
        'subject' => $lang->welcome_thread_subject,
        'uid' => 0,
        'username' => $lang->welcome_thread_username,
        'message' => $lang->welcome_thread_message,
        'ipaddress' => $db->escape_binary(my_inet_pton('127.0.0.1')),
        'options' => [
            'disablesmilies' => true,
            'subscriptionmethod' => '',
        ],
        'savedraft' => false,
        'posthash' => '',
        'replyto' => '',
    ]);

    if ($posthandler->validate_thread()) {
        $thread = $posthandler->insert_thread();

        if ($thread['tid'] && $development_mode === true) {
            $bburl = getSettingValue('bburl');

            $databaseString = $config['database']['type'] . ':';

            if ($config['database']['type'] !== 'sqlite') {
                $databaseString .= $config['database']['username'] . '@' . $config['database']['hostname'] . '/';
            }

            $databaseString .= $config['database']['database'];

            $posthandler = new PostDataHandler('insert');

            $posthandler->action = 'post';
            $posthandler->set_data([
                'fid' => 2,
                'tid' => $thread['tid'],
                'subject' => '',
                'uid' => 0,
                'username' => $lang->welcome_thread_username,
                'message' => $lang->sprintf(
                    $lang->welcome_thread_message_devmode,
                    $bburl . '/install/index.php',
                    $mybb->version,
                    date('c'),
                    $databaseString,
                    PHP_SAPI,
                ),
                'ipaddress' => $db->escape_binary(my_inet_pton('127.0.0.1')),
                'options' => [
                    'disablesmilies' => true,
                ],
                'savedraft' => false,
                'posthash' => '',
                'replyto' => '',
            ]);

            if ($posthandler->validate_post()) {
                $posthandler->insert_post();
            }
        }
    }
}

function createAcpUserSession(DB_Base $db, array $user, array $data): string
{
    $db->delete_query('adminsessions', 'uid=' . (int)$user['uid']);

    $sid = md5(random_str(50));

    $useragent = $data['useragent'];

    if (my_strlen($useragent) > 200) {
        $useragent = my_substr($useragent, 0, 200);
    }

    $db->insert_query('adminsessions', [
        'sid' => $sid,
        'uid' => $user['uid'],
        'loginkey' => $user['loginkey'],
        'ip' => $db->escape_binary(my_inet_pton($data['ip'])),
        'dateline' => TIME_NOW,
        'lastactive' => TIME_NOW,
        'data' => my_serialize([]),
        'useragent' => $db->escape_string($useragent),
    ]);

    return $sid;
}
#endregion

#region upgrading
function syncTasks(): void
{
    $db = getDatabaseHandle();

    $existingTasks = [];

    $query = $db->simple_select('tasks', 'file,tid');
    while ($task = $db->fetch_array($query)) {
        $existingTasks[$task['file']] = $task['tid'];
    }

    $entries = getTaskDefinitions();

    foreach ($entries as $entry) {
        $entry = array_map($db->escape_string(...), $entry);

        if (array_key_exists($task['file'], $existingTasks)) {
            $db->update_query(
                'tasks',
                [
                    'title' => $entry['title'],
                    'description' => $entry['description'],
                ],
                "file='".  $entry['file'] . "'",
            );
        } else {
            $db->insert_query("tasks", $entry);
        }
    }
}

function rebuildDatacache(): void
{
    $cache = getCache();

    $cache->update_version();
    $cache->update_attachtypes();
    $cache->update_smilies();
    $cache->update_badwords();
    $cache->update_usergroups();
    $cache->update_forumpermissions();
    $cache->update_stats();
    $cache->update_statistics();
    $cache->update_forums();
    $cache->update_moderators();
    $cache->update_usertitles();
    $cache->update_reportedcontent();
    $cache->update_awaitingactivation();
    $cache->update_mycode();
    $cache->update_profilefields();
    $cache->update_posticons();
    $cache->update_tasks();
    $cache->update_spiders();
    $cache->update_bannedips();
    $cache->update_bannedemails();
    $cache->update_birthdays();
    $cache->update_most_replied_threads();
    $cache->update_most_viewed_threads();
    $cache->update_groupleaders();
    $cache->update_threadprefixes();
    $cache->update_forumsdisplay();
    $cache->update_reportreasons(true);
}

function syncSettings(bool $restoreValuesFromCache = false): void
{
    global $db;

    $settinggroups = array();

    if ($db->type == "mysql" || $db->type == "mysqli") {
        $wheresettings = "isdefault='1' OR isdefault='yes'";
    } else {
        $wheresettings = "isdefault='1'";
    }

    $query = $db->simple_select("settinggroups", "name,title,gid", $wheresettings);
    while ($group = $db->fetch_array($query)) {
        $settinggroups[$group['name']] = $group['gid'];
    }

    // Collect all the user's settings - regardless of 'defaultivity' - we'll check them all
    // against default settings and insert/update them accordingly
    $settings = getSettingsFromDatabase();

    $tree = getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/settings.xml');

    foreach ($tree['settings'][0]['settinggroup'] as $settinggroup) {
        $groupdata = array(
            "name" => $db->escape_string($settinggroup['attributes']['name']),
            "title" => $db->escape_string($settinggroup['attributes']['title']),
            "description" => $db->escape_string($settinggroup['attributes']['description']),
            "disporder" => (int)$settinggroup['attributes']['disporder'],
            "isdefault" => $settinggroup['attributes']['isdefault']
        );

        if (!array_key_exists($settinggroup['attributes']['name'], $settinggroups)) {
            $gid = $db->insert_query("settinggroups", $groupdata);
        } else {
            $gid = $settinggroups[ $settinggroup['attributes']['name'] ];

            $db->update_query("settinggroups", $groupdata, "gid='{$gid}'");
        }

        if (!$gid) {
            continue;
        }

        foreach ($settinggroup['setting'] as $setting) {
            $settingdata = array(
                "name" => $db->escape_string($setting['attributes']['name']),
                "title" => $db->escape_string($setting['title'][0]['value']),
                "description" => $db->escape_string($setting['description'][0]['value'] ?? ''),
                "optionscode" => $db->escape_string($setting['optionscode'][0]['value']),
                "disporder" => (int)$setting['disporder'][0]['value'],
                "gid" => $gid,
                "isdefault" => 1
            );

            if (!array_key_exists($setting['attributes']['name'], $settings)) {
                $settingdata['value'] = $db->escape_string($setting['settingvalue'][0]['value']);

                $db->insert_query("settings", $settingdata);
            } else {
                $name = $db->escape_string($setting['attributes']['name']);

                $db->update_query("settings", $settingdata, "name='{$name}'");
            }
        }
    }

    if ($restoreValuesFromCache === true) {
        require MYBB_ROOT . 'inc/settings.php';

        foreach ($settings as $key => $val) {
            $db->update_query(
                'settings',
                [
                    'value' => $db->escape_string($val),
                    "name='" . $db->escape_string($key) . "'",
                ],
            );
        }
    }

    writeSettingsFile(
        getSettingsFromDatabase(),
    );
}

function getTaskDefinitions(): array
{
    include_once MYBB_ROOT . 'inc/functions_task.php';

    $entries = xmlTreeToArray(
        getXmlTreeFromFile(MYBB_ROOT . 'inc/seeds/tasks.xml')
    );

    foreach ($entries as &$entry) {
        $entry['nextrun'] = fetch_next_run($entry);

        if ($entry['file'] === 'versioncheck') {
            // set a random date and hour (so all MyBB installs don't query mybb.com all at the same time)
            $entry['hour'] = rand(0, 23);
            $entry['weekday'] = rand(0, 6);
        }
    }

    return array_column($entries, null, 'file');
}

function runVersionCheckTask(): bool
{
    global $plugins;

    require_once MYBB_ROOT . 'inc/functions_task.php';

    $db = getDatabaseHandle();

    $query = $db->simple_select('tasks', 'tid', "file='versioncheck'");

    $update_check = $db->fetch_array($query);

    if ($update_check) {
        // Load plugin system for update check
        require_once MYBB_ROOT . 'inc/class_plugins.php';

        $plugins = new pluginSystem();

        $result = run_task($update_check['tid']);

        if ($result === true) {
            $cache = getCache();

            $cache->update_update_check();

            return true;
        }
    }

    return false;
}
#endregion

#region defaults
function getSuggestedBoardName(): string
{
    $lang = app(MyLanguage::class);

    return $lang->board_name_default;
}

function getSuggestedBoardUrl(): ?string
{
    $url = null;

    if (PHP_SAPI === 'cli') {
        $url = getSettingValue('bburl');
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $url = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $url .= '://' . $_SERVER['HTTP_HOST'];

        if (!empty($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], [80, 443])) {
            $url .= ':' . (int)$_SERVER['SERVER_PORT'];
        }

        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $url .= implode(
                '/',
                array_slice(
                    explode('/', $_SERVER['SCRIPT_NAME']),
                    0,
                    -2
                ),
            );
        }
    }

    return $url;
}

function getSuggestedAdminEmail(): ?string
{
    $url = getSettingValue('adminemail');

    if ($url === null) {
        if (isset($_SERVER['SERVER_ADMIN']) && filter_var($_SERVER['SERVER_ADMIN'], FILTER_VALIDATE_EMAIL)) {
            $url = $_SERVER['SERVER_ADMIN'];
        }
    }

    return $url;
}

/**
 * @psalm-pure
 */
function getCookieDomainByUrl(string $url): ?string
{
    $value = parse_url($url, PHP_URL_HOST);

    if ($value) {
        if (my_substr($value, 0, 4) == 'www.') {
            $value = substr($value, 4);
        }

        $value = preg_replace('/:\d+$/', '', $value);

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            $value = '';
        }

        return $value;
    } else {
        return null;
    }
}

/**
 * @psalm-pure
 */
function getCookiePathByUrl(string $url): ?string
{
    $value = parse_url($url, PHP_URL_PATH);

    if ($value !== false) {
        $value = rtrim($value ?? '', '/');

        return $value;
    } else {
        return null;
    }
}

/**
 * @psalm-pure
 */
function getCookieSecureFlagByUrl(string $url): ?string
{
    $value = parse_url($url, PHP_URL_SCHEME);

    if ($value) {
        return $value === 'https' ? '1' : '0';
    } else {
        return null;
    }
}

/**
 * Outputs matching common database credentials, building on existing parameters and previous matches (local maximum).
 *
 * @param array{
 *   engine?: string,
 *   host?: string,
 *   port?: string,
 *   user?: string,
 *   password?: string,
 *   name?: string,
 * } $existingParameters
 * @return array<string,string>
 */
function getSuggestedDatabaseParameters(array $existingParameters = []): array
{
    $suggestedParameters = [];

    $bestScore = 0;

    $credentialSets = getDatabaseSuggestionCredentialSets();

    foreach ($credentialSets as $credentialSet) {
        if (
            array_diff_key($credentialSet, $suggestedParameters) !== [] &&
            array_intersect_key($credentialSet, $existingParameters) === []
        ) {
            $attemptParameters = array_merge($credentialSet, $suggestedParameters, $existingParameters);

            $attemptResults = testDatabaseParameters($attemptParameters, 1);

            $attemptScore = count(array_filter($attemptResults['checks']));

            if (!in_array(false, $attemptResults['checks'], true) && $attemptScore > $bestScore) {
                $suggestedParameters = $attemptParameters;
                $bestScore = $attemptScore;
            }
        }
    }

    return $suggestedParameters;
}
#endregion
