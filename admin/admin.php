<?php

define("IN_MYBB", 1);
define("IN_ADMINCP", 1);

require __DIR__ . '/../inc/src/bootstrap.php';

/** @var \Illuminate\Routing\Router $router */
$router = \MyBB\app('router');

$router->group(['namespace' => '\\MyBB\\Http\\Controllers\\Admin'], function(\Illuminate\Routing\Router $router) {
    $router->get('tools/php_info', ['name' => 'acp.tools.php_info', 'uses' => 'ToolsController@getPhpInfo']);
});

$response = $router->dispatch(\MyBB\app('request'));

$response->send();