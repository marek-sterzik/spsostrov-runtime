<?php

namespace SPSOstrov\Runtime;

use Symfony\Component\Dotenv\Dotenv;
use Exception;

class Config
{
    private static $file = null;
    private static $configurationMode = false;
    private static $interactiveMode = false;
    private static $loadedVars = [];
    private static $defaultVars = [];
    private static $section = null;
    private static $sectionDisplayed = false;
    private static $someSectionDisplayed = false;

    public static function error(string $message): void
    {
        self::message(sprintf("Error: %s", $message));
    }

    public static function message(string $message): void
    {
        self::initialize(true);
        self::flushSections();
        fprintf(STDERR, "%s\n", $message);
    }

    public static function section(string $caption): void
    {
        self::initialize(true);
        self::$section = $caption;
        self::$sectionDisplayed = false;
    }

    private static function flushSections(): void
    {
        if (self::$section !== null && !self::$sectionDisplayed) {
            $underline = str_repeat("-", mb_strlen(self::$section, "utf-8"));
            fprintf(STDERR, "\n%s%s\n%s\n\n", self::$someSectionDisplayed ? "\n":"", self::$section, $underline);
            self::$sectionDisplayed = true;
            self::$someSectionDisplayed = true;
        }
    }

    public static function question(?string $variable, string $question, ?string $default = null, $type = null): ?string
    {
        self::initialize(true);
        if (!self::$configurationMode) {
            throw new Exception("This function may be run only inside of the configure script");
        }
        $type = ConfigType::create($type);
        $previousValue = null;
        if ($variable !== null) {
            $previousValue = self::get($variable);
            if ($previousValue !== null) {
                $default = $previousValue;
            }
        }

        $defaultOk = ($type->check($default) === null) ? true : false;
        if (!$defaultOk && $default !== null) {
            $default = null;
            $defaultOk = ($type->check($default) === null) ? true : false;
        }
        
        if ($default !== null) {
            $displayDefault = $type->transformDefaultToString($default);
            $displayDefault = preg_replace('/[\[\]\\\\]/', '\\\\\1', $displayDefault);
            $question = sprintf("%s [%s]", $question, $displayDefault);
        }


        do {
            if (self::$interactiveMode) {
                self::flushSections();
                fprintf(STDERR, "%s ", $question);
                $value = rtrim(fgets(STDIN), "\r\n");
                if ($value === '') {
                    $value = null;
                }
            } else {
                $value = null;
            }
            if ($value !== null) {
                $message = $type->check($value);
                $valueOk = ($message === null) ? true : false;
                if (!$valueOk) {
                    self::error(sprintf("Invalid value: %s", $message));
                }
            } else {
                $valueOk = $defaultOk;
                if ($defaultOk) {
                    $value = $default;
                } else {
                    self::error("Invalid value.");
                }

            }
            if (!$valueOk && !self::$interactiveMode) {
                throw new Exception("Invalid default value in non-interactive mode");
            }
        } while (!$valueOk);
        if ($variable !== null) {
            self::set($variable, $value);
        }
        return $value;
    }

    public static function getFile(): string
    {
        self::initialize();
        return self::$file;
    }

    public static function get(string $var, bool $useDefault = true): ?string
    {
        self::initialize();
        return self::$loadedVars[$var] ?? self::$defaultVars[$var] ?? null;
    }

    public static function set(string $var, ?string $value): void
    {
        self::initialize(true);
        $putVar = $var;
        if (isset(self::$loadedVars[$var])) {
            unset(self::$loadedVars[$var]);
            $putVar = null;
        }
        if ($value !== null) {
            self::$loadedVars[$var] = $value;
        }
        self::write(($putVar !== null) ? [$putVar] : array_keys(self::$loadedVars), ($putVar !== null) ? false : true);
    }

    private static function initialize(bool $wantConfigSection = false): void
    {
        if (self::$file !== null) {
            return;
        }
        $rootDir = getenv("SPSO_APP_DIR");
        if (!is_string($rootDir)) {
            throw new Exception("Cannot determine the application root dir");
        }
        $configFile = getenv("SPSO_CONFIG_FILE");

        $regularConfig = $rootDir . "/" . Configure::CONFIG;

        if (is_string($configFile)) {
            self::$file = $configFile;
            $interactive = getenv("SPSO_CONFIG_INTERACTIVE");
            self::$interactiveMode = is_string($interactive) ? ($interactive ? true : false) : true;
            self::$configurationMode = true;
            self::$defaultVars = self::loadVars($regularConfig);
        } else {
            self::$file = $regularConfig;
            self::$interactiveMode = false;
            self::$configurationMode = false;
            self::$defaultVars = [];
        }

        self::$loadedVars = self::loadVars(self::$file);
        if ($wantConfigSection && !self::$configurationMode) {
            throw new Exception("This function may be run only inside of the configure script");
        }
    }

    private static function write(array $vars, bool $overwrite): void
    {
        $fd = fopen(self::$file, "c");
        if (!$fd) {
            throw new Exception("Cannot write variable to the config file");
        }
        if ($overwrite) {
            ftruncate($fd, 0);
        } else {
            fseek($fd, 0, SEEK_END);
        }
        foreach ($vars as $var) {
            $value = self::$loadedVars[$var] ?? null;
            if ($value !== null) {
                fprintf($fd, "%s\n", self::getVarDef($var, $value));
            }
        }
        fclose($fd);
    }

    private static function getVarDef(string $variable, string $value): string
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

    private static function loadVars(string $file): array
    {
        $contents = @file_get_contents($file);
        if (!is_string($contents)) {
            return [];
        }
        $dotenv = new Dotenv();
        return $dotenv->parse($contents);
    }
}

