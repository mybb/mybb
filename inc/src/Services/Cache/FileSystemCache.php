<?php

namespace MyBB\Services\Cache;

use MyBB\Contracts\Cache\CacheInterface;

class FileSystemCache implements CacheInterface
{

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * FileSystemCache constructor.
     * @param string $directory
     */
    public function __construct($directory = 'cache')
    {
        $this->directory = $directory;
    }

    /**
     * @return mixed
     */
    public function connect()
    {
        // TODO: Implement connect() method.
    }

    /**
     * @return mixed
     */
    public function disconnect()
    {
        // TODO: Implement disconnect() method.
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function has($key)
    {
        // TODO: Implement has() method.
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function fetch($key, $default = null)
    {
        // TODO: Implement fetch() method.
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        // TODO: Implement pull() method.
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function put($key, $value, $expiration = 0)
    {
//        $storeData = [
//            'time' => time(),
//            'expire' => $expiration,
//            'data' => serialize($value)
//        ];
//
//        $dataArray = $this->_loadCache();
//
//        if (true === is_array($dataArray)) {
//            $dataArray[$key] = $storeData;
//        } else {
//            $dataArray = array($key => $storeData);
//        }
//
//        $cacheData = json_encode($dataArray);
//
//        file_put_contents($this->getCacheDir(), $cacheData);
//
//        return $this;

        // TODO: Implement put() method.
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function add($key, $value)
    {
        // TODO: Implement add() method.
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function delete($key)
    {
        // TODO: Implement delete() method.
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function size_of($name = '')
    {
        // TODO: Implement size_of() method.
    }
}
