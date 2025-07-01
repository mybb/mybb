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

    // Drop deprecated columns
    if ($db->field_exists("google", "users")) {
        $db->drop_column("users", "google");
    }

    if ($db->field_exists("skype", "users")) {
        $db->drop_column("users", "skype");
    }

    // Modify columns
    $db->modify_column("forums", "style", "varchar(30)", "set", "''");
    $db->modify_column("users", "password", "varchar(500)", "set", "''");
    $db->modify_column("users", "style", "varchar(30)", "set", "''");

    // Add userfields columns
    foreach (["fid4", "fid5", "fid6"] as $fid) {
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
            $moved_tid_substring = "SUBSTRING(closed FROM 7)::INTEGER";
            break;

        case 'sqlite':
            if (!$db->field_exists("contact", "profilefields")) {
                $db->add_column("profilefields", "contact", "tinyint(1) NOT NULL default '0'");
            }
            if (!$db->field_exists("moved", "threads")) {
                $db->add_column("threads", "moved", "int NOT NULL default '0'");
            }
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
            $moved_tid_substring = "SUBSTR(closed, 7)";
            break;

        default: // MySQL
            if (!$db->field_exists("contact", "profilefields")) {
                $db->add_column("profilefields", "contact", "tinyint(1) NOT NULL default '0' AFTER disporder");
            }
            if (!$db->field_exists("moved", "threads")) {
                $db->add_column("threads", "moved", "int unsigned NOT NULL default '0' AFTER closed");
            }
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
                ) ENGINE=MyISAM;");
            }
            $moved_tid_substring = "CAST(SUBSTRING(closed, 7) AS SIGNED)";
            break;
    }

    // Update moved threads
    $db->query("
        UPDATE ".TABLE_PREFIX."threads
        SET closed = '0', moved = ".$moved_tid_substring."
        WHERE closed LIKE 'moved|%' AND (moved IS NULL OR moved = 0);
    ");

    // Remove deprecated settings
    $db->delete_query("settings", "name='mail_parameters'");
}
