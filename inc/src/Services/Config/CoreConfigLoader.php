<?php

namespace MyBB\Services\Config;

use Illuminate\Contracts\Support\Arrayable;
use MyBB\Contracts\Config\LoaderInterface;

class CoreConfigLoader implements LoaderInterface
{

    /**
     * @var array|null $config
     */
    private $config;

    /**
     * @var string $path
     */
    private $path;

    /**
     * CoreConfigLoader constructor.
     * @param $path
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->config = null;
    }

    /**
     * Retrieve the configuration file
     */
    private function loadConfig()
    {
        if (is_null($this->config) && file_exists($this->path)) {
            $config = array();
            require $this->path;
            $this->config = $config;
        }
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get(string $key, $default = null)
    {
        $this->loadConfig();

        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        if (strpos($key, '.') === false) {
            return $default;
        }

        $items = $this->config;
        foreach (explode('.', $key) as $segment) {
            $items = &$items[$segment];
        }

        return $items;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key) : bool
    {
        $this->loadConfig();
        return isset($this->config[$key]);
    }

    /**
     * Return all configuration values.
     *
     * @return array|mixed
     */
    public function all() : array
    {
        $this->loadConfig();
        return $this->config;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param null $value
     * @return mixed
     */
    public function set(string $key, $value = null)
    {
        $this->loadConfig();

        $this->config[$key] = $value;

        $configArray = var_export($this->config, true);

        $fileContent = <<<PHP
            <?php
            return $configArray;
PHP;
        file_put_contents($this->path, $fileContent, LOCK_EX);

        return $this->get($key);
    }
}