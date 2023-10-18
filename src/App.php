<?php

namespace SPSOstrov\Runtime;

class App
{
    private $config;

    public function __construct($composerAutoloadPath)
    {
        $rootDir = dirname(Path::canonize(dirname($composerAutoloadPath)));
        $this->config = (new RuntimeConfig($rootDir))->get();
        $this->config['rootDir'] = $rootDir;
        $this->config['argv0'] = null;
    }

    public function run($argv)
    {
        $this->config['argv0'] = $argv[0];
        $opts = $this->getopt($argv, "hv", ["help", "version"], $index);

        if ($opts === false) {
            fprintf(STDERR, "Error: Cannot parse options.\n");
            fprintf(STDERR, "For help use: %s --help\n", $argv[0]);
            return 1;
        }

        $args = array_slice($argv, $index);
        
        if (empty($args)) {
            $command = null;
        } else {
            $command = array_shift($args);
        }

        if (array_key_exists("h", $opts) || array_key_exists("help", $opts)) {
            if ($command === null) {
                $this->printGlobalHelp();
            } else {
                $commandObj = Command::get($command, $this->config);
                $this->printCommandHelp($commandObj);
            }
            return 1;
        }

        if (array_key_exists("v", $opts) || array_key_exists("version", $opts)) {
            $this->printVersionInfo();
            return 1;
        }

        if ($command === null) {
            $this->printGlobalHelp();
            return 1;
        }

        $commandObj = Command::get($command, $this->config);

        if ($commandObj === null) {
            fprintf(STDERR, "Unknown command: %s\n", $command);
            return 1;
        }

        if ($this->trySpecial($commandObj, $args)) {
            return 1;
        }

        return $commandObj->invoke($this->config, $args);
    }

    private function trySpecial($command, $args)
    {
        $opts = $command->getOpts() . "hv";
        $longOpts = array_merge($command->getLongOpts(), ["help", "version"]);
        $opts = $this->getopt(array_merge([$command->getBin()], $args), $opts, $longOpts, $index);
        if ($opts === false) {
            return false;
        }

        if (array_key_exists("h", $opts) || array_key_exists("help", $opts)) {
            $this->printCommandHelp($command);
            return true;
        }

        if (array_key_exists("v", $opts) || array_key_exists("version", $opts)) {
            $this->printCommandVersion($command);
            return true;
        }

        return false;
    }

    private function printGlobalHelp()
    {
        fprintf(STDERR, "usage: %s [opts] command [command-opts]\n", $this->config['argv0']);
    }

    private function printCommandHelp($command)
    {
        fprintf(STDERR, "Error: command help not yet implemented!\n");
    }

    private function printVersionInfo()
    {
        fprintf(STDERR, "Error: version info not yet available!\n");
    }

    private function getopt($args, $opts, $longOpts, &$index)
    {
        global $argv;

        $argvBackup = $argv;
        $argvServerBackup = $_SERVER['argv'];
        $argcBackup = $_SERVER['argc'];

        $ret = getopt($opts, $longOpts, $index);

        $_SERVER['argv'] = $argvServerBackup;
        $_SERVER['argc'] = $argcBackup;
        $argv = $argvBackup;

        return $ret;
    }
}
