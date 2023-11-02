<?php

namespace SPSOstrov\Runtime;

use Exception;

class Run
{
    public static function run(string ...$args): int
    {
        if (empty($args)) {
            throw new Exception("Running command needs to specify the command name");
        }
        $cmd = escapeshellcmd(array_shift($args));
        foreach ($args as $arg) {
            $cmd .= " " . escapeshellarg($arg);
        }
        $ret = 0;
        if (passthru($cmd, $ret) === false) {
            $ret = 255;
        }
        return $ret;
    }

    public static function app(string ...$args): int
    {
        $app = getenv("SPSO_APP_BIN");
        if (!is_string($app)) {
            throw new Exception("Cannot determine the app-console command");
        }
        return self::run(...array_merge([$app], $args));
    }
}
