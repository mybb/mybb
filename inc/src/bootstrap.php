<?php

namespace MyBB;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Psr\Container\ContainerInterface;
use MyBB\Services\Config;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Contracts\Container\Container $container */
$container = app();

$container->bind(Services\Config::class, function(ContainerInterface $container) {
    //Will need to pass the loader in
   return new Config(new Config\CoreConfigLoader(__DIR__ . '/../config.php'));
});

$container->alias(Services\Config::class, 'config');

$container->bind(Capsule::class, function(Container $container) {
    $capsule = new Capsule;
    $config = $container->get('config');

    $capsule->addConnection([
        'driver'    => $config->get('database.type'),
        'host'      => $config->get('database.hostname'),
        'database'  => $config->get('database.database'),
        'username'  => $config->get('database.username'),
        'password'  => $config->get('database.password'),
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => $config->get('database.table_prefix'),
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    return $capsule;
});

$container->alias(Capsule::class, 'database');

// Twig
$container->singleton(\Twig_Environment::class, function(ContainerInterface $container) {
    // TODO: The loader should be aware of both front-end and ACP themes
    $loader = new \Twig_Loader_Filesystem([
        __DIR__ . '/../views'
    ]);

    return new \Twig_Environment($loader, [
        'debug' => true,
        'cache' => __DIR__ . '/../../cache/views',

    ]);
});

$container->alias(\Twig_Environment::class, 'twig');

// Events
$container->singleton(\Illuminate\Contracts\Events\Dispatcher::class, function(ContainerInterface $container) {
    return new Dispatcher($container);
});

$container->alias(\Illuminate\Contracts\Events\Dispatcher::class, 'events');

// Router
$container->singleton(Router::class, function(ContainerInterface $container) {
    return new Router($container->get('events'), $container);
});

$container->alias(Router::class, 'router');

// Request
$container->bind(Request::class, function(ContainerInterface $container) {
    return Request::capture();
});

$container->alias(Request::class, 'request');