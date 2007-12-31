<?php
/**
 * Database configuration
 *
 * Please see the MyBB Wiki for advanced
 * database configuration for larger installations
 * http://wiki.mybboard.net/
 */

$config['database']['type'] = 'mysqli';
$config['database']['database'] = 'mybb';
$config['database']['table_prefix'] = 'mybb_';

$config['database']['hostname'] = 'localhost';
$config['database']['username'] = 'root';
$config['database']['password'] = 'root';

/**
 * Admin CP directory
 *  For security reasons, it is recommended you
 *  rename your Admin CP directory. You then need
 *  to adjust the value below to point to the
 *  new directory.
 */

$config['admin_dir'] = 'admin';

/**
 * Hide all Admin CP links
 *  If you wish to hide all Admin CP links
 *  on the front end of the board after
 *  renaming your Admin CP directory, set this
 *  to 1.
 */

$config['hide_admin_links'] = 0;

/**
 * Data-cache configuration
 *  The data cache is a temporary cache
 *  of the most commonly accessed data in MyBB.
 *  By default, the database is used to store this data.
 *
 *  If you wish to use the file system (inc/cache directory), MemCache or eAccelerator
 *  you can change the value below to 'files', 'memcache' or 'eaccelerator' from 'db'.
 */

$config['cache_store'] = 'db';

/**
 * Memcache configuration
 *  If you are using memcache as your data-cache,
 *  you need to configure the hostname and port
 *  of your memcache server below.
 *
 * If not using memcache, ignore this section.
 */

$config['memcache_host'] = 'localhost';
$config['memcache_port'] = 11211;

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

$config['super_admins'] = '1';

/**
 * Database Encoding
 *  If you wish to set an encoding for MyBB uncomment 
 *  the line below (if it isn't already) and change
 *  the current value to the mysql charset:
 *  http://dev.mysql.com/doc/refman/5.1/en/charset-mysql.html
 */

$config['database']['encoding'] = 'utf8';

/**
 * Automatic Log Pruning
 *  The MyBB task system can automatically prune
 *  various log files created by MyBB.
 *  To enable this functionality for the logs below, set the
 *  the number of days before each log should be pruned.
 *  If you set the value to 0, the logs will not be pruned.
 */

$config['log_pruning'] = array(
	'admin_logs' => 365, // Administrator logs
	'mod_logs' => 0, // Moderator logs
	'task_logs' => 30, // Scheduled task logs
	'mail_logs' => 180, // Mail error logs
	'user_mail_logs' => 180, // User mail logs
	'promotion_logs' => 180 // Promotion logs
);
 
?>