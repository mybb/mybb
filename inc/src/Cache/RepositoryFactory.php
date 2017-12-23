<?php

namespace MyBB\Cache;

use Illuminate\Cache\ApcStore;
use Illuminate\Cache\ApcWrapper;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;

class RepositoryFactory
{
    /**
     * Build a cache repository instance based upon configuration.
     *
     * @param \MyBB $mybb The MyBB core instance.
     * @param \errorHandler|null $errorHandler Error handler instance to handle fatal errors.
     *
     * @return \Illuminate\Contracts\Cache\Repository|null The created repository instance, or null if no cache is
     *     configured.
     */
    public static function getRepository(\MyBB $mybb, \errorHandler $errorHandler = null)
    {
        switch ($mybb->config['cache_store']) {
            case 'apc':
                $store = new ApcStore(new ApcWrapper());
                break;
            case 'files':
                $store = new FileStore(new Filesystem(), MYBB_ROOT . 'cache');
                break;
            case 'memcached':
                $store = static::getMemcachedStore($mybb, $errorHandler);
                break;
            default:
                return null;
        }

        return new Repository($store);
    }

    /**
     * Create a new store using memcached as the backend.
     *
     * @param \MyBB $mybb The MyBB core instance.
     * @param \errorHandler|null $errorHandler Error handler instance to handle fatal errors.
     *
     * @return MemcachedStore The created memcached store.
     */
    private static function getMemcachedStore(\MyBB $mybb, \errorHandler $errorHandler = null)
    {
        $memcached = new \Memcached();

        if ($mybb->config['memcache']['host']) {
            $mybb->config['memcache'][0] = $mybb->config['memcache'];
            unset($mybb->config['memcache']['host']);
            unset($mybb->config['memcache']['port']);
        }

        foreach ($mybb->config['memcache'] as $serverSettings) {
            if (!$serverSettings['host']) {
                $message = "Please configure the memcache settings in inc/config.php before attempting to use this cache handler";

                if (!is_null($errorHandler)) {
                    $errorHandler->trigger($message, MYBB_CACHEHANDLER_LOAD_ERROR);
                    die;
                } else {
                    die($message);
                }
            }

            if (!isset($serverSettings['port'])) {
                $serverSettings['port'] = "11211";
            }

            $memcached->addServer($serverSettings['host'], $serverSettings['port']);
        }

        return new MemcachedStore($memcached);
    }
}