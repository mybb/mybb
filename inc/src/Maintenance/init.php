<?php

declare(strict_types=1);

use MyBB\Maintenance\InstallationState;

defined('IN_INSTALL') or die;

define('TIME_NOW', time());

if (function_exists('date_default_timezone_set') && !ini_get('date.timezone')) {
    date_default_timezone_set('GMT');
}

require_once MYBB_ROOT . 'inc/class_error.php';
$error_handler = new errorHandler(
    errortypemedium: 'none',
    errorlogmedium: 'log',
    errorloglocation: MYBB_ROOT . '/error.log',
);

// global
require_once MYBB_ROOT . 'inc/src/bootstrap.php';

require_once MYBB_ROOT . 'inc/functions.php';

require_once MYBB_ROOT . 'inc/class_core.php';
require_once MYBB_ROOT . 'inc/class_datacache.php';
require_once MYBB_ROOT . 'inc/class_language.php';

require_once MYBB_ROOT . 'inc/db_base.php';
require_once MYBB_ROOT . 'inc/AbstractPdoDbDriver.php';
require_once MYBB_ROOT . 'inc/DbException.php';

// Maintenance-specific functions
require_once MYBB_ROOT . 'inc/src/Maintenance/functions.php';
require_once MYBB_ROOT . 'inc/src/Maintenance/functions_core.php';
require_once MYBB_ROOT . 'inc/src/Maintenance/functions_data.php';
require_once MYBB_ROOT . 'inc/src/Maintenance/functions_db.php';
require_once MYBB_ROOT . 'inc/src/Maintenance/functions_upgrades.php';

// core
$mybb = new MyBB();

// language
$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT . 'inc/languages');
$lang->set_language($lang->fallbackLanguage);

$languages = $lang->get_languages();

if (count($languages) === 2) {
    // preemptively switch language when only one non-default package exists
    $lang->set_language(
        current(
            array_diff(array_keys($languages), [$lang->fallbackLanguage])
        )
    );
}

// opportunistic initialization
if (InstallationState::get() === InstallationState::INSTALLED) {
    $mybb->config = \MyBB\Maintenance\getConfigurationFileData(true);
    $mybb->settings = \MyBB\Maintenance\getCorrectedSettings(
        \MyBB\Maintenance\getSettings(true)
    );

    $error_handler->errorlogmedium = $mybb->settings['errorlogmedium'] ?? '';
    $error_handler->errorloglocation = $mybb->settings['errorloglocation'] ?? '';

    $cache = new datacache(); // global variable used in upgrade scripts
    $cache->cache();
    $mybb->cache = &$cache;
}

// Prevent any shut down functions from running
$done_shutdown = 1;
