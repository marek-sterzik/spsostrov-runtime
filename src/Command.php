<?php

namespace SPSOstrov\Runtime;

final class Command
{
    public static function get($name, $config)
    {
        if (preg_match('-/-', $name)) {
            return null;
        }
        foreach ($config['scripts-dirs'] ?? [] as $dir) {
            $command = self::createCommand($config['rootDir'], $dir, $name);
            if ($command !== null) {
                return $command;
            }
        }
        return null;
    }

    public static function getAll($config)
    {
        $commands = [];
        foreach ($config['scripts-dirs'] ?? [] as $dir) {
            $names = self::listCommands($config['rootDir'], $dir);
            foreach ($names as $name) {
                if (!isset($name)) {
                    $command = selff::createCommand($config['rootDir'], $dir, $name);
                    if ($command !== null) {
                        $commands[$name] = $command;
                    }
                }
            }
        }
        return $commands;
    }


    private static function listCommands($rootDir, $dir)
    {
        return [];
    }

    private static function createCommand($rootDir, $dir, $name)
    {
        $bin = Path::canonize($rootDir . "/" . $dir . "/" . $name);
        if (is_file($bin) && self::isInvokable($bin)) {
            return new self($bin, $name);
        }
        return null;
    }

    private static function isInvokable($bin)
    {
        return is_executable($bin) && !preg_match('/\.json$/', $bin);
    }

    private $bin;
    private $name;
    private $metadata;

    private function __construct($bin, $name)
    {
        $this->bin = $bin;
        $this->name = $name;
        $this->metadata = null;
    }

    public function getBin()
    {
        return $this->bin;
    }

    public function getName()
    {
        return $this->name;
    }

    public function invoke($config, $args)
    {
        putenv("SPSO_APP_DIR=" . $config['rootDir']);
        putenv("SPSO_APP_BIN=" . Path::canonize($config['rootDir'] . "/vendor/bin/app"));
        if (isset($config['argv0'])) {
            putenv("SPSO_APP_ARGV0=" . $config['argv0']);
        }

        $cmd = escapeshellcmd($this->bin);
        foreach ($args as $arg) {
            $cmd .= " " . escapeshellarg($arg);
        }
        $ret = 1;
        system($cmd, $ret);
        return $ret;
    }

    public function getOpts()
    {
        return $this->metadataTyped("opts", "is_string") ?? '';
    }

    public function getLongOpts()
    {
        return array_values($this->metadataTyped("longOpts", function ($opts) {
            if (!is_array($opts)) {
                return false;
            }
            foreach ($opts as $opt) {
                if (!is_string($opt)) {
                    return false;
                }
            }
            return true;
        }) ?? []);
    }

    private function metadataTyped($key, $type)
    {
        $data = $this->metadata($key);
        if ($type($data)) {
            return $data;
        }
        return null;
    }

    private function metadata($key)
    {
        if ($this->metadata === null) {
            $this->metadata = $this->loadMetadata();
        }
        return $this->metadata[$key] ?? null;
    }

    private function loadMetadata()
    {
        $data = @file_get_contents($this->bin . ".json");
        if (!is_string($data)) {
            return [];
        }
        $data = @json_decode($data, true);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }
}
