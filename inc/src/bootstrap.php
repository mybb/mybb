<?php

namespace MyBB;

$autoloadFilePath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadFilePath)) {
    $mybb->trigger_generic_error("dependencies_not_installed");
    exit;
}

require_once $autoloadFilePath;

/** @var \MyBB\Application $app */
$app = new Application(realpath(__DIR__ . '/../../'));

$app->boot();

return $app;
