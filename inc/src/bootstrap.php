<?php

namespace MyBB;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Contracts\Container\Container $container */
$container = app();

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

