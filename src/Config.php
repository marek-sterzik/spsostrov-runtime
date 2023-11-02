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
        return self::$file->getFileName();
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

        $vars = [];
        foreach (($putVar !== null) ? [$putVar] : array_keys(self::$loadedVars) as $var) {
            $vars[$var] = self::$loadedVars[$var] ?? null;
        }
        $append = ($putVar !== null) ? true : false;
        self::$file->writeTemp($vars, $append);
    }

    private static function initialize(bool $wantConfigSection = false, bool $refresh = false): void
    {
        if (!$refresh && self::$file !== null) {
            return;
        }
        $rootDir = getenv("SPSO_APP_DIR");
        if (!is_string($rootDir)) {
            throw new Exception("Cannot determine the application root dir");
        }
        $configFile = getenv("SPSO_CONFIG_FILE");

        self::$file = new ConfigFile($rootDir, $configFile);

        if (is_string($configFile)) {
            $interactive = getenv("SPSO_CONFIG_INTERACTIVE");
            self::$interactiveMode = is_string($interactive) ? ($interactive ? true : false) : true;
            self::$configurationMode = true;
            self::$defaultVars = self::$file->readFinal();
            self::$loadedVars = self::$file->readTemp();
        } else {
            self::$interactiveMode = false;
            self::$configurationMode = false;
            self::$defaultVars = [];
            self::$loadedVars = self::$file->readFinal();
        }

        if ($wantConfigSection && !self::$configurationMode) {
            throw new Exception("This function may be run only inside of the configure script");
        }
    }
}
