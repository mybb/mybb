<?php

use MyBB\Services\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;

require_once __DIR__ . '/inc/src/bootstrap.php';

$cache = new FileStore(new Filesystem(), 'cache');

//var_dump($cache->put('key', 'assfasdfsdf'));

var_dump($cache->size_of('key'));