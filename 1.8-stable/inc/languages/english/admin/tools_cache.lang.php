<?php
/**
 * MyBB 1.6 English Language Pack
 * Copyright 2010 MyBB Group, All Rights Reserved
 * 
 * $Id: tools_cache.lang.php 5297 2010-12-28 22:01:14Z Tomm $
 */

$l['cache'] = "Cache:";
$l['cache_manager'] = "Cache Manager";
$l['cache_manager_description'] = "Here you can manage caches which are used as a method of optimizing MyBB. Rebuilding a cache will take all the necessary data used to create the cache and re-synchronize it. Reloading a cache will reload it into the selected cache handler (disk, eaccelerator, memcache, etc). Reloading is useful when switching from the database or file system to xcache, eaccelerator, or another cache handler.";
$l['rebuild_cache'] = "Rebuild Cache";
$l['reload_cache'] = "Reload Cache";

$l['error_cannot_rebuild'] = "This cache cannot be rebuilt.";
$l['error_empty_cache'] = "Cache is empty.";
$l['error_incorrect_cache'] = "Incorrect cache specified.";
$l['error_no_cache_specified'] = "You did not specify a cache to view.";

$l['success_cache_rebuilt'] = "The cache has been rebuilt successfully.";
$l['success_cache_reloaded'] = "The cache has been reloaded successfully.";

?>