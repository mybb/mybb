<?php
/**
 * MyBB 1.9
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.8.x
 */

$upgrade_detail = array(
    "revert_all_templates" => 0,
    "revert_all_themes" => 0,
    "revert_all_settings" => 0
);

function upgrade100_dbchanges()
{
    global $db;

    if ($db->type == 'sqlite') {
        $db->close_cursors();
    }

    // Drop deprecated columns
    if ($db->field_exists("google", "users")) {
        $db->drop_column("users", "google");
    }

    if ($db->field_exists("skype", "users")) {
        $db->drop_column("users", "skype");
    }

    // Modify columns
    $db->modify_column("users", "password", "varchar(500)", "set", "''");
    $db->modify_column("mailerrors", "smtperror", "text", "set", false);

    if ($db->field_exists("pid", "themes")) {
        $db->drop_column("themes", "pid");
    }
    if (!$db->field_exists("package", "themes")) {
        // Delete incompatible data
        $db->delete_query("themes");
        $db->delete_query("themestylesheets");

        $db->add_column("themes", "package", "varchar(100) NOT NULL");

        // Insert new default theme entry
        $db->insert_query("themes", [
            'package' => 'core.base',
            'name' => 'Base',
            'def' => '1',
            'properties' => 'a:0:{}',
            'stylesheets' => 'a:0:{}',
            'allowedgroups' => 'all',
        ]);

        // Reset incompatible preferences
        $db->update_query("users", ["style" => 0], "style != 0");
        $db->update_query("forums", ["style" => 0], "style != 0");
    }

    // Add userfields columns
    foreach (["fid4", "fid5"] as $fid) {
        if (!$db->field_exists($fid, "userfields")) {
            $db->add_column("userfields", $fid, "text NOT NULL");
        }
    }

    // Database specific changes
    switch($db->type)
    {
        case 'pgsql':
            if (!$db->field_exists("contact", "profilefields")) {
                $db->add_column("profilefields", "contact", "smallint NOT NULL default '0'");
            }
            if (!$db->field_exists("moved", "threads")) {
                $db->add_column("threads", "moved", "int NOT NULL default '0'");
            }

            // Update moved threads
            $db->query("
                UPDATE ".TABLE_PREFIX."threads
                SET closed = '0', moved = SUBSTRING(closed::text FROM 7)::integer
                WHERE closed::text LIKE 'moved|%' AND (moved IS NULL OR moved = 0);
            ");

            if (!$db->field_exists("showinlegend", "usergroups")) {
                $db->add_column("usergroups", "showinlegend", "smallint NOT NULL default '0'");
            }
            if (!$db->field_exists("password_algorithm", "users")) {
                $db->add_column("users", "password_algorithm", "varchar(30) NOT NULL DEFAULT ''");
            }
            if ($db->field_exists("closed", "threads")) {
                $db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ALTER COLUMN closed DROP DEFAULT;");
                $db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ALTER COLUMN closed SET DATA TYPE smallint USING closed::smallint;");
                $db->write_query("ALTER TABLE ".TABLE_PREFIX."threads ALTER COLUMN closed SET DEFAULT 0;");
            }
            if (!$db->table_exists("securitylog")) {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."securitylog (
                    uid int NOT NULL default '0',
                    ipaddress bytea NOT NULL default '',
                    dateline int NOT NULL default '0',
                    type varchar(50) NOT NULL default ''
                );");
            }
            break;

        case 'sqlite':
            if (!$db->field_exists("contact", "profilefields")) {
                $db->add_column("profilefields", "contact", "tinyint(1) NOT NULL default '0'");
            }
            if (!$db->field_exists("moved", "threads")) {
                $db->add_column("threads", "moved", "int NOT NULL default '0'");
            }

            // Update moved threads
            $db->query("
                UPDATE ".TABLE_PREFIX."threads
                SET closed = '0', moved = SUBSTR(closed, 7)
                WHERE closed LIKE 'moved|%' AND (moved IS NULL OR moved = 0);
            ");

            if (!$db->field_exists("showinlegend", "usergroups")) {
                $db->add_column("usergroups", "showinlegend", "tinyint(1) NOT NULL default '0'");
            }
            if (!$db->field_exists("password_algorithm", "users")) {
                $db->add_column("users", "password_algorithm", "varchar(30) NOT NULL DEFAULT ''");
            }
            if ($db->field_exists("closed", "threads")) {
                $db->modify_column("threads", "closed", "smallint", "set", "'0'");
            }
            if (!$db->table_exists("securitylog")) {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."securitylog (
                    uid int NOT NULL default '0',
                    ipaddress blob(16) NOT NULL default '',
                    dateline int NOT NULL default '0',
                    type varchar(50) NOT NULL default ''
                );");
            }
            break;

        default: // MySQL
            if (!$db->field_exists("contact", "profilefields")) {
                $db->add_column("profilefields", "contact", "tinyint(1) NOT NULL default '0' AFTER disporder");
            }
            if (!$db->field_exists("moved", "threads")) {
                $db->add_column("threads", "moved", "int unsigned NOT NULL default '0' AFTER closed");
            }

            // Update moved threads
            $db->query("
                UPDATE ".TABLE_PREFIX."threads
                SET closed = '0', moved = CAST(SUBSTRING(closed, 7) AS SIGNED)
                WHERE closed LIKE 'moved|%' AND (moved IS NULL OR moved = 0);
            ");

            if (!$db->field_exists("showinlegend", "usergroups")) {
                $db->add_column("usergroups", "showinlegend", "tinyint(1) NOT NULL default '0' AFTER canchangewebsite");
            }
            if (!$db->field_exists("password_algorithm", "users")) {
                $db->add_column("users", "password_algorithm", "varchar(30) NOT NULL DEFAULT '' AFTER password");
            }
            if ($db->field_exists("closed", "threads")) {
                $db->modify_column("threads", "closed", "tinyint(1)", "set", "'0'");
            }
            if (!$db->table_exists("securitylog")) {
                $db->write_query("CREATE TABLE ".TABLE_PREFIX."securitylog (
                    uid int unsigned NOT NULL default '0',
                    ipaddress varbinary(16) NOT NULL default '',
                    dateline int unsigned NOT NULL default '0',
                    type varchar(50) NOT NULL default '',
                    KEY uid (uid)
                ) ENGINE=InnoDB;");
            }
            break;
    }

    // Remove deprecated settings
    $db->delete_query("settings", "name='mail_parameters'");

    // Remove deprecated profile fields
    $db->delete_query("profilefields", "name='Skype'");

    // Set legacy password algorithm for existing users
    $db->update_query("users", ["password_algorithm" => "mybb"], "password_algorithm = ''");
}

function upgrade100_indexes()
{
    global $db;
    $indexes = [];

    if (in_array($db->type, array('sqlite', 'pgsql'))) {
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."adminlog_module_action ON ".TABLE_PREFIX."adminlog (module, action);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."adminlog_uid ON ".TABLE_PREFIX."adminlog (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."announcements_fid ON ".TABLE_PREFIX."announcements (fid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."attachments_pid_visible ON ".TABLE_PREFIX."attachments (pid, visible);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."attachments_uid ON ".TABLE_PREFIX."attachments (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."banfilters_type ON ".TABLE_PREFIX."banfilters (type);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."banned_uid ON ".TABLE_PREFIX."banned (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."banned_dateline ON ".TABLE_PREFIX."banned (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."buddyrequests_uid ON ".TABLE_PREFIX."buddyrequests (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."buddyrequests_touid ON ".TABLE_PREFIX."buddyrequests (touid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."captcha_imagehash ON ".TABLE_PREFIX."captcha (imagehash);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."captcha_dateline ON ".TABLE_PREFIX."captcha (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."events_cid ON ".TABLE_PREFIX."events (cid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."events_daterange ON ".TABLE_PREFIX."events (starttime, endtime);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."events_private ON ".TABLE_PREFIX."events (private);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."forumpermissions_fid_gid ON ".TABLE_PREFIX."forumpermissions (fid, gid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."forumsread_dateline ON ".TABLE_PREFIX."forumsread (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."forumsubscriptions_uid ON ".TABLE_PREFIX."forumsubscriptions (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."moderatorlog_uid ON ".TABLE_PREFIX."moderatorlog (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."moderatorlog_fid ON ".TABLE_PREFIX."moderatorlog (fid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."moderatorlog_tid ON ".TABLE_PREFIX."moderatorlog (tid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."moderators_id_fid ON ".TABLE_PREFIX."moderators (id, fid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."polls_tid ON ".TABLE_PREFIX."polls (tid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."pollvotes_pid_uid ON ".TABLE_PREFIX."pollvotes (pid, uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_tid_uid ON ".TABLE_PREFIX."posts (tid, uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_uid ON ".TABLE_PREFIX."posts (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_visible ON ".TABLE_PREFIX."posts (visible);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_dateline ON ".TABLE_PREFIX."posts (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_ipaddress ON ".TABLE_PREFIX."posts (ipaddress);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."posts_tid_dateline ON ".TABLE_PREFIX."posts (tid, dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."privatemessages_uid_folder ON ".TABLE_PREFIX."privatemessages (uid, folder);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."privatemessages_toid ON ".TABLE_PREFIX."privatemessages (toid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."reportedcontent_reportstatus ON ".TABLE_PREFIX."reportedcontent (reportstatus);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."reportedcontent_lastreport ON ".TABLE_PREFIX."reportedcontent (lastreport);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."reputation_uid ON ".TABLE_PREFIX."reputation (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."securitylog_uid ON ".TABLE_PREFIX."securitylog (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."sessions_location ON ".TABLE_PREFIX."sessions (location1, location2);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."sessions_time ON ".TABLE_PREFIX."sessions (time);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."sessions_uid ON ".TABLE_PREFIX."sessions (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."sessions_ip ON ".TABLE_PREFIX."sessions (ip);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."settings_gid ON ".TABLE_PREFIX."settings (gid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."templates_sid_title ON ".TABLE_PREFIX."templates (sid, title);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."themestylesheets_tid ON ".TABLE_PREFIX."themestylesheets (tid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadratings_tid ON ".TABLE_PREFIX."threadratings (tid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadviews_tid ON ".TABLE_PREFIX."threadviews (tid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threads_fid ON ".TABLE_PREFIX."threads (fid, visible, sticky);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threads_dateline ON ".TABLE_PREFIX."threads (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threads_lastpost ON ".TABLE_PREFIX."threads (lastpost, fid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threads_firstpost ON ".TABLE_PREFIX."threads (firstpost);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threads_uid ON ".TABLE_PREFIX."threads (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadsread_dateline ON ".TABLE_PREFIX."threadsread (dateline);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadsubscriptions_uid ON ".TABLE_PREFIX."threadsubscriptions (uid);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadsubscriptions_tid_notification ON ".TABLE_PREFIX."threadsubscriptions (tid, notification);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."users_usergroup ON ".TABLE_PREFIX."users (usergroup);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."users_regip ON ".TABLE_PREFIX."users (regip);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."users_lastip ON ".TABLE_PREFIX."users (lastip);";
        $indexes[] = "CREATE INDEX IF NOT EXISTS ".TABLE_PREFIX."warnings_uid ON ".TABLE_PREFIX."warnings (uid);";
    }

    if ($db->type == 'sqlite') {
        $indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS ".TABLE_PREFIX."forumsread_fid_uid_uq ON ".TABLE_PREFIX."forumsread (fid, uid);";
        $indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS ".TABLE_PREFIX."threadsread_tid_uid_uq ON ".TABLE_PREFIX."threadsread (tid, uid);";
        $indexes[] = "CREATE UNIQUE INDEX IF NOT EXISTS ".TABLE_PREFIX."users_username_uq ON ".TABLE_PREFIX."users (username);";
    }

    foreach ($indexes as $index) {
        $db->write_query($index);
    }
}

function upgrade100_convert_innodb()
{
    global $db;

    if ($db->type == "mysql" || $db->type == "mysqli") {
        $tables = $db->query("SHOW TABLE STATUS LIKE '" . TABLE_PREFIX . "%'");

        while ($table = $db->fetch_array($tables)) {
            if (strtoupper($table['Engine']) != 'INNODB') {
                $db->write_query(
                    "ALTER TABLE `{$table['Name']}` ENGINE=InnoDB;"
                );
            }
        }
    }
}


function upgrade100_smilies()
{
    global $db;

    $smilies_dir = MYBB_ROOT . 'images/smilies/';
    if (!is_dir($smilies_dir)) {
        return;
    }

    // Update existing smilies from PNG to SVG if SVG exists
    $svg_files = glob($smilies_dir . '*.svg');
    if ($svg_files !== false) {
        foreach ($svg_files as $svg_file_path) {
            $smiley_name = basename($svg_file_path, '.svg');
            $png_image_path = 'images/smilies/' . $smiley_name . '.png';
            $svg_image_path = 'images/smilies/' . $smiley_name . '.svg';

            // Only update if PNG entry exists
            $query = $db->simple_select('smilies', 'sid', "image='" . $db->escape_string($png_image_path) . "'");
            if ($db->num_rows($query)) {
                $db->update_query('smilies', ['image' => $db->escape_string($svg_image_path)], "image='" . $db->escape_string($png_image_path) . "'");
            }
        }
    }

    // List of new smilies to insert
    $smilies = [
        ['name' => 'Censored', 'find' => ':censored:', 'image' => 'images/smilies/censored.svg', 'showclickable' => 1],
        ['name' => 'Dead', 'find' => ':dead:', 'image' => 'images/smilies/dead.svg', 'showclickable' => 1],
        ['name' => 'Evil', 'find' => ':evil:', 'image' => 'images/smilies/evil.svg', 'showclickable' => 1],
        ['name' => 'Frozen', 'find' => ':frozen:', 'image' => 'images/smilies/frozen.svg', 'showclickable' => 1],
        ['name' => 'Grimace', 'find' => ':grimace:', 'image' => 'images/smilies/grimace.svg', 'showclickable' => 1],
        ['name' => 'Hot', 'find' => ':hot:', 'image' => 'images/smilies/hot.svg', 'showclickable' => 1],
        ['name' => 'Like', 'find' => ':like:', 'image' => 'images/smilies/like.svg', 'showclickable' => 1],
        ['name' => 'Lol', 'find' => ':lol:', 'image' => 'images/smilies/lol.svg', 'showclickable' => 1],
        ['name' => 'Neutral', 'find' => ':neutral:', 'image' => 'images/smilies/neutral.svg', 'showclickable' => 1],
        ['name' => 'Shocked', 'find' => ':shocked:', 'image' => 'images/smilies/shocked.svg', 'showclickable' => 1],
        ['name' => 'Surprise', 'find' => ':surprise:', 'image' => 'images/smilies/surprise.svg', 'showclickable' => 1],
        ['name' => 'Unlike', 'find' => ':unlike:', 'image' => 'images/smilies/unlike.svg', 'showclickable' => 1],
        ['name' => 'Wtf', 'find' => ':wtf:', 'image' => 'images/smilies/wtf.svg', 'showclickable' => 1],
        ['name' => 'Yawn', 'find' => ':yawn:', 'image' => 'images/smilies/yawn.svg', 'showclickable' => 1],
        ['name' => 'Zzz', 'find' => ':zzz:', 'image' => 'images/smilies/zzz.svg', 'showclickable' => 1],
        ['name' => 'Huh Dark', 'find' => ':huh_d:', 'image' => 'images/smilies/huh_d.svg', 'showclickable' => 1],
    ];

    $disporder = 0;
    $query = $db->simple_select('smilies', 'MAX(disporder) AS max_disporder');
    $max_disporder = $db->fetch_field($query, 'max_disporder');
    if ($max_disporder !== null) {
        $disporder = (int)$max_disporder;
    }

    // Resynchronize the PostgreSQL sequence for smilies
    if ($db->type == "pgsql") {
        $db->query("
            SELECT setval(
                pg_get_serial_sequence('" . TABLE_PREFIX . "smilies', 'sid'),
                (SELECT MAX(sid) FROM " . TABLE_PREFIX . "smilies)
            )
        ");
    }

    // Avoid inserting duplicates
    $existing_smilies = [];
    $query = $db->simple_select('smilies', 'find');
    while ($row = $db->fetch_array($query)) {
        $existing_smilies[] = $row['find'];
    }

    $insert = [];
    foreach ($smilies as $smiley) {
        if (in_array($smiley['find'], $existing_smilies, true)) {
            continue;
        }
        $insert[] = [
            'name' => $smiley['name'],
            'find' => $smiley['find'],
            'image' => $smiley['image'],
            'disporder' => ++$disporder,
            'showclickable' => $smiley['showclickable'],
        ];
    }

    if (!empty($insert)) {
        $db->insert_query_multiple('smilies', $insert);
    }
}

function upgrade100_check_constraints()
{
    global $db;

    $constraints = [
        ['table' => 'calendars', 'column' => 'disporder'],
        ['table' => 'forums', 'column' => 'disporder'],
        ['table' => 'helpdocs', 'column' => 'disporder'],
        ['table' => 'helpsections', 'column' => 'disporder'],
        ['table' => 'profilefields', 'column' => 'disporder'],
        ['table' => 'reportreasons', 'column' => 'disporder'],
        ['table' => 'settinggroups', 'column' => 'disporder'],
        ['table' => 'settings', 'column' => 'disporder'],
        ['table' => 'smilies', 'column' => 'disporder'],
        ['table' => 'usergroups', 'column' => 'disporder'],
    ];

    if ($db->type === "pgsql") {
        foreach ($constraints as $constraint) {
            $table_name = TABLE_PREFIX . $constraint['table'];
            $column_name = $constraint['column'];
            $constraint_name = $table_name . '_' . $column_name ."_check";
            $query = $db->query("
                SELECT 1 FROM pg_constraint
                WHERE conrelid = '" . $table_name . "'::regclass
                    AND contype = 'c' AND conname = '" . $constraint_name . "'
            ");

            if ($db->num_rows($query) == 0) {
                // Reset values that are negative
                $db->update_query($constraint['table'], array($column_name => 0), $column_name . ' < 0');
                // Create constraint
                $db->write_query("
                    ALTER TABLE " . $table_name . "
                    ADD CONSTRAINT " . $constraint_name . " CHECK (" . $column_name  . " >= 0)
                ");
            }
        }
    }
}

