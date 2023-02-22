<?php
/**
 * A file with PHP syntax compatible with old versions to verify compatibility,
 * and fallback HTML to render when raw file content is returned to the browser,
 * providing human-friendly errors.
 */

/*
>
<style>
body { font-size: 0; }
p { font-size: initial; }
</style>
<p>
    <strong>Error:</strong>
    Could not run PHP code.
    Make sure the server is configured to let PHP handle files with the <code>.php</code> extension.
</p>
<!--
*/

define('MINIMUM_PHP_VERSION', '8.1');

define('IN_MYBB', 1);
define('IN_INSTALL', 1);
define('MYBB_ROOT', dirname(dirname(__FILE__)) . '/');
define('INSTALL_ROOT', dirname(__FILE__) . '/');

if (version_compare(PHP_VERSION, MINIMUM_PHP_VERSION, '<')) {
    exit('<strong>Error:</strong> PHP &ge; ' . MINIMUM_PHP_VERSION . ' required (currently running ' . PHP_VERSION . ').');
}

require_once MYBB_ROOT . 'inc/src/Maintenance/init.php';

require_once MYBB_ROOT . 'inc/src/Maintenance/functions_http.php';
\MyBB\Maintenance\httpSetup();

$app->make('\MyBB\Http\Controllers\Maintenance\MaintenanceController');
