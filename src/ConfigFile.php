<?php

namespace SPSOstrov\Runtime;

use Symfony\Component\Dotenv\Dotenv;
use Exception;

class ConfigFile
{
    private const CONFIG_MAIN_NAME = ".env";
    private const CONFIG_NAME = ".env.local";
    private const CONFIG_NAME_TMP = ".env.tmp";

    /** @var string */
    private $mainFileName;
    
    /** @var string */
    private $fileName;

    /** @var string */
    private $tmpFileName;

    /** @var mixed */
    private $lockFd;

    public function __construct(string $dir, ?string $tempFileName = null)
    {
        $this->mainFileName = $dir . "/" . self::CONFIG_MAIN_NAME;
        $this->fileName = $dir . "/" . self::CONFIG_NAME;
        if ($tempFileName === null) {
            $this->tmpFileName = $dir . "/" . self::CONFIG_NAME_TMP;
        } else {
            $this->tmpFileName = $tempFileName;
        }
        $this->lockFd = null;
    }

    public function getTmpFileName(): string
    {
        return $this->tmpFileName;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function configured(): bool
    {
        $size = @filesize($this->fileName);
        return (is_int($size) && $size > 0) ? true : false;
    }

    public function startTransaction(bool $wait): self
    {
        if ($this->lockFd !== null) {
            throw new Exception("Transactions cannot be nested");
        }
        $this->lockFd = fopen($this->fileName, "c+");
        if (!flock($this->lockFd, LOCK_EX | ($wait ? 0 : LOCK_NB))) {
            throw new Exception(
                'Cannot acquire lock for the config file.' . ($wait ? '' : ' (configure process already running)')
            );
        }
        file_put_contents($this->tmpFileName, '');

        return $this;
    }

    public function readFinal(): array
    {
        return $this->read($this->fileName);
    }

    public function readTemp(): array
    {
        return $this->read($this->tmpFileName);
    }
    
    public function read(string $file): array
    {
        $contents = @file_get_contents($file);
        if (!is_string($contents)) {
            return [];
        }
        $dotenv = new Dotenv();
        return $dotenv->parse($contents);
    }

    public function writeTemp(array $vars = [], bool $append = true): self
    {
        $fd = fopen($this->tmpFileName, "c");
        if (!$fd) {
            throw new Exception("Cannot write variable to the config file");
        }
        $this->write($fd, $vars, $append);
        fclose ($fd);
        return $this;
    }

    private function write($fd, array $vars, bool $append): self
    {
        if ($append) {
            fseek($fd, 0, SEEK_END);
        } else {
            fseek($fd, 0, SEEK_SET);
            ftruncate($fd, 0);
        }
        foreach ($vars as $var => $value) {
            if ($value !== null) {
                fprintf($fd, "%s\n", $this->getVarDef($var, $value));
            }
        }
        return $this;
    }

    private function getVarDef(string $variable, string $value): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable)) {
            throw new Exception(sprintf("Invalid variable name to set: %s", $variable));
        }
        $valueEscaped = escapeshellarg($value);
        if ($valueEscaped === ("'" . $value . "'") || $valueEscaped === ('"' . $value . '"')) {
            $valueEscaped = $value;
        }
        return sprintf("%s=%s", $variable, $valueEscaped);
    }

    public function rollback(): self
    {
        if ($this->lockFd === null) {
            throw new Exception("No transaction started, cannot rollback");
        }
        @unlink($this->tmpFileName);
        fclose($this->lockFd);
        $this->lockFd = null;
        return $this;
    }

    public function commit(): self
    {
        if ($this->lockFd === null) {
            throw new Exception("No transaction started, cannot commit");
        }
        $data = $this->readTemp();
        $this->write($this->lockFd, $data, false);
        @unlink($this->tmpFileName);
        fclose($this->lockFd);
        $this->lockFd = null;
        @touch($this->mainFileName);
        return $this;
    }
}
