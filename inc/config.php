<?php
/**
 * Database configuration
 */

$config['dbtype'] = 'mysqli';
$config['hostname'] = 'localhost';
$config['username'] = 'root';
$config['password'] = '';
$config['database'] = 'mybbdev';
$config['table_prefix'] = 'mybb_branch_127_';

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
 *  If you wish to use the file system (inc/cache directory)
 *  you can change the value below to 'files' from 'db'.
 */

$config['cache_store'] = 'db';

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

$config['super_admins'] = '1';
 
?>