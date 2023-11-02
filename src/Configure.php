<?php

namespace SPSOstrov\Runtime;

class Configure
{
    const CONFIG = ".env";
    const CONFIG_TMP = ".env.tmp";

    /** @var string */
    private $appRoot;

    /** @var string */
    private $configFile;

    /** @var string */
    private $configFileTmp;
    
    public function __construct(string $appRoot)
    {
        $this->appRoot = $appRoot;
        $this->configFile = $appRoot . "/" . self::CONFIG;
        $this->configFileTmp = $appRoot . "/" . self::CONFIG_TMP;
    }

    public function configured(): bool
    {
        return file_exists($this->configFile) ? true : false;
    }

    public function run(): int
    {
        $fd = fopen($this->configFileTmp, "c");
        if (!flock($fd, LOCK_EX | LOCK_NB)) {
            fprintf(STDERR, "Error: Cannot acquire lock for the configuration file, configure already running?\n");
            exit (1);
        }
        ftruncate($fd, 0);


        putenv("SPSO_CONFIG_FILE=" . $this->configFileTmp);
        $success = false;
        if ($this->runPlugins()) {
            @unlink($this->configFile);
            copy($this->configFileTmp, $this->configFile);
            $success = true;
        }
        fclose($fd);
        unlink($this->configFileTmp);

        return $success ? 0 : 1;
    }

    private function runPlugins(): bool
    {
        $ret = Run::app("--all", "--reverse", ".configure");
        return ($ret === 0) ? true : false;
    }
}
