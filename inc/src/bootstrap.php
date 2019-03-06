<?php

namespace MyBB;

require_once __DIR__ . '/../vendor/autoload.php';

/** @var \MyBB\Application $app */
$app = new Application(realpath(__DIR__ . '/../../'));

$app->boot();

return $app;
