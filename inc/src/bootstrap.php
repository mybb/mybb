<?php

namespace MyBB;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use MyBB\Twig\Extensions\CoreExtension;
use MyBB\Twig\Extensions\LangExtension;
use MyBB\Twig\Extensions\ThemeExtension;
use MyBB\Utilities\BreadcrumbManager;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Contracts\Container\Container $container */
$container = app();

// MyBB
$container->singleton(\MyBB::class, function () {
    return $GLOBALS['mybb'];
});

$container->alias(\MyBB::class, 'mybb');

// DB
$container->singleton(\DB_Base::class, function () {
    return $GLOBALS['db'];
});

$container->alias(\DB_Base::class, 'db');

// Plugins
$container->singleton(\pluginSystem::class, function () {
    return $GLOBALS['plugins'];
});

$container->alias(\pluginSystem::class, 'plugins');

// Lang
$container->singleton(\MyLanguage::class, function () {
    return $GLOBALS['lang'];
});

$container->alias(\MyLanguage::class, 'lang');

// Breadcrumb
$container->singleton(BreadcrumbManager::class, function (ContainerInterface $container) {
    /** @var \MyBB $mybb */
    $mybb = $container[\MyBB::class];

    return new BreadcrumbManager(
        $mybb->settings['bbname_orig'],
        $mybb->settings['bburl']
    );
});

$container->alias(BreadcrumbManager::class, 'breadcrumbs');

// Twig
$container->singleton(\Twig_Environment::class, function (ContainerInterface $container) {
    if (defined('IN_ADMINCP')) {
        $paths = [
            __DIR__ . '/../views/admin',
        ];
    } else {
        // TODO: views for the current theme, it's parent, it's parent's parent, etc. should be here
        // The filesystem loader works by using files from the first array entry.
        // If a file doesn't exist, it looks in the second array entry and so on.
        // This allows us to easily implement template inheritance.

        $paths = [
            __DIR__ . '/../views/base',
        ];
    }

    /** @var \pluginSystem $plugins */
    $plugins = $container->get(\pluginSystem::class);

    $plugins->run_hooks('twig_environment_before_loader', $paths);

    $loader = new \Twig_Loader_Filesystem($paths);

    $env = new \Twig_Environment($loader, [
        'debug' => true, // TODO: In live environments this should be false
        'cache' => __DIR__ . '/../../cache/views',
    ]);

    /** @var \MyBB $mybb */
    $mybb = $container->get(\MyBB::class);
    /** @var \MyLanguage $lang */
    $lang = $container->get(\MyLanguage::class);

    $env->addExtension(
        new CoreExtension(
            $mybb,
            $lang,
            $container[\pluginSystem::class],
            $container[BreadcrumbManager::class]
        )
    );
    $env->addExtension(new ThemeExtension($mybb, $container->get(\DB_Base::class)));
    $env->addExtension(new LangExtension($lang));

    $plugins->run_hooks('twig_environment_env', $env);

    $env->addGlobal('mybb', $container->get(\MyBB::class));

    return $env;
});

$container->alias(\Twig_Environment::class, 'twig');

// Events
$container->singleton(\Illuminate\Contracts\Events\Dispatcher::class, function (ContainerInterface $container) {
    return new Dispatcher($container);
});

$container->alias(\Illuminate\Contracts\Events\Dispatcher::class, 'events');

// Router
$container->singleton(Router::class, function (ContainerInterface $container) {
    return new Router($container->get('events'), $container);
});

$container->alias(Router::class, 'router');

// Request
$container->bind(Request::class, function (ContainerInterface $container) {
    return Request::capture();
});

$container->alias(Request::class, 'request');
