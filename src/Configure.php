<?php

namespace SPSOstrov\Runtime;

use Exception;
use Throwable;

class Configure
{
    public const CONFIG = ".env.local";
    public const CONFIG_TMP = ".env.tmp";

    /** @var string */
    private $appRoot;

    /** @var ConfigFile */
    private $configFile;

    /** @var string */
    private $configFileTmp;
    
    public function __construct(string $appRoot)
    {
        $this->appRoot = $appRoot;
        $this->configFile = new ConfigFile($appRoot);
    }

    public function configured(): bool
    {
        return $this->configFile->configured();
    }

    public function run(bool $interactive = true): int
    {
        try {
            $this->configFile->startTransaction(false);

            putenv("SPSO_CONFIG_INTERACTIVE=" . ($interactive ? '1' : '0'));
            putenv("SPSO_CONFIG_FILE=" . $this->configFile->getTmpFileName());
            
            $ret = Run::app("--all", "--reverse", "--abort-on-failure", ".configure");
            if ($ret != 0) {
                throw new Exception("");
            }
            $ret = Run::app("--all", "--reverse", "--abort-on-failure", ".configure-postprocess");
            if ($ret != 0) {
                throw new Exception("Postprocessing of the configuration failed");
            }
            $this->configFile->commit();
            
        } catch (Throwable $e) {
            $this->configFile->rollback();
            $message = $e->getMessage();
            if ($message !== "") {
                fprintf(STDERR, "Error: %s\n", $message);
            }
            return 1;
        }

        return 0;
    }
}
