<?php

namespace SPSOstrov\Runtime;

class RuntimeConfig
{
    const CONFIG = "vendor/composer/spsostrov-runtime.json";

    private $rootDir;

    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function set($config)
    {
        $config = json_encode($config);
        file_put_contents($this->rootDir . "/" . self::CONFIG, $config);
    }

    public function get()
    {
        $config = @file_get_contents($this->rootDir . "/" . self::CONFIG);
        if (!is_string($config)) {
            return [];
        }
        $config = @json_decode($config, true);
        if (!is_array($config)) {
            return [];
        }
        return $config;
    }
}
