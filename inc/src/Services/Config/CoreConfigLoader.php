<?php

namespace MyBB\Services\Config;

use Illuminate\Contracts\Support\Arrayable;
use MyBB\Contracts\Config\LoaderInterface;

class CoreConfigLoader implements LoaderInterface
{

    /**
     * @var array|null config
     */
    private $config;

    /**
     * @var string path
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
        require $this->path;
        $this->config = $config;
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        if (is_null($this->config)) {
            $this->loadConfig();
        }

        if ($this->config[$key]) {
            return $this->config[$key];
        }

        return $default;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        if (is_null($this->config)) {
            $this->loadConfig();
        }
        return isset($this->config[$key]);
    }

    /**
     * Return all configuration values.
     *
     * @return array|mixed
     */
    public function all()
    {
        if (is_null($this->config)) {
            $this->loadConfig();
        }
        return $this->config;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param null $value
     * @return mixed
     */
    public function set($key, $value = null)
    {
        if (is_null($this->config)) {
            $this->loadConfig();
        }

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