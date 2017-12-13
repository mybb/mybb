<?php

namespace MyBB\Services;

use MyBB\Contracts\Config\LoaderInterface;

class Config
{
    /**
     * Configuration loader
     *
     * @var
     */
    protected $loader;

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }
}