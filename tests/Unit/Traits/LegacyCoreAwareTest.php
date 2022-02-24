<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\Traits;

/**
 * This trait can be used by any unit tests that is testing a legacy MyBB component.
 *
 * It sets up some of the globals that old MyBB components expect, such as `$mybb`, `$lang`, `$plugins`, etc.
 *
 * @package MyBB\Tests\Unit\Traits
 */
trait LegacyCoreAwareTest
{
    protected static function setupMybb()
    {
        // This is nasty, but the parser has a lot of hidden dependencies upon globals such as `$mybb` and `$plugins`...
        // In the future we should move the parser into the `\MyBB\` namespace and inject these dependencies.
        defined('MYBB_ROOT') or define('MYBB_ROOT', __DIR__ . '/../../../');

        require_once __DIR__ . '/../../../inc/class_core.php';
        require_once __DIR__ . '/../../../inc/class_plugins.php';
        require_once __DIR__ . '/../../../inc/class_language.php';
        require_once __DIR__ . '/../../../inc/class_parser.php';
        require_once __DIR__ . '/../../../inc/class_datacache.php';
        require_once __DIR__ . '/../../../inc/cachehandlers/interface.php';

        // The core MyBB class expects to be accessed by a browser
        $_SERVER['REQUEST_METHOD'] = 'get';

        $GLOBALS['mybb'] = new \MyBB();
        $GLOBALS['plugins'] = new \pluginSystem();
        $GLOBALS['lang'] = new \MyLanguage();
        $GLOBALS['cache'] = new \datacache();

        $GLOBALS['mybb']->settings = [
            'bburl' => 'http://example.com',
            'bbname' => 'Test Board',
        ];

        $cacheHandler = \Mockery::mock('CacheHandlerInterface');
        $cacheHandler->shouldReceive('fetch')->andReturn([]);

        $GLOBALS['lang']->settings = [
            'charset' => 'utf-8',
        ];

        $GLOBALS['lang']->set_path(__DIR__ . '/../../../inc/languages');

        $GLOBALS['lang']->load('global');

        $GLOBALS['cache']->handler = $cacheHandler;

        $GLOBALS['mybb']->cache = &$GLOBALS['cache'];

        $templatesMock = \Mockery::mock(\templates::class);
        $GLOBALS['templates'] = $templatesMock;

        $dbMock = \Mockery::mock(\DB_Base::class);
        $GLOBALS['db'] = $dbMock;

        require_once __DIR__ . '/../../../inc/functions.php';
    }
}
