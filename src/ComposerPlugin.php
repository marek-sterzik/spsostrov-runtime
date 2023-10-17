<?php

namespace SPSOstrov\Runtime;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\InstalledVersions;
use Composer\EventDispatcher\EventSubscriberInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    const SELF_PACKAGE = "spsostrov/runtime";

    private $composer;
    private $io;
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => 'postAutoloadDump',
        ];
    }

    public function postAutoloadDump($object = null)
    {
        $runtimeConfig = ['scripts-dirs' => []];
        $rootDir = Path::canonize(InstalledVersions::getInstallPath("__root__"));
        $packages = array_merge(
            ['__root__'],
            InstalledVersions::getInstalledPackagesByType('spsostrov-runtime'),
            [self::SELF_PACKAGE]
        );
        foreach ($packages as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            $path = Path::canonize($installPath);
            $path = $this->stripPathPrefix($path, $rootDir);
            if ($path !== null) {
                $packageConfig = $this->loadDirsFromPackage($rootDir, $path, $package);
                if ($packageConfig['scripts-dir'] !== null) {
                    $runtimeConfig['scripts-dirs'][] = $packageConfig['scripts-dir'];
                }
            } else {
                $this->io->writeError([
                        sprintf(
                            "<warning>Cannot determine relative path for spsstrov-runtime plugin %s</warning>",
                            $package
                        )
                ]);
            }
        }
        (new RuntimeConfig($rootDir))->set($runtimeConfig);
    }

    private function loadDirsFromPackage($rootDir, $packageDir, $package)
    {
        $config = $this->loadExtraFromComposerJson($rootDir, $packageDir, $package);
        if (!array_key_exists('scripts-dir', $config)) {
            $config['scripts-dir'] = 'scripts';
        }
        if (is_string($config['scripts-dir'])) {
            $config['scripts-dir'] = Path::canonize(
                $packageDir . "/" . Path::canonizeRelative($config['scripts-dir'])
            );
            if ($config['scripts-dir'] === null) {
                $this->io->writeError([
                        sprintf(
                            "<warning>Package %s contains invalid scripts-dir</warning>",
                            $package
                        )
                ]);
            } else {
                if (!is_dir($rootDir . "/" . $config['scripts-dir'])) {
                    $config['scripts-dir'] = null;
                }
            }
        }
        return $config;
    }

    private function loadExtraFromComposerJson($rootDir, $packageDir, $package)
    {
        $composerFile = sprintf("%s/%s/composer.json", $rootDir, $packageDir);
        $content = @file_get_contents($composerFile);
        if (!is_string($content)) {
            return [];
        }
        $content = @json_decode($content, true);
        if (!is_array($content)) {
                $this->io->writeError([
                        sprintf(
                            "<warning>Package %s has broken composer.json</warning>",
                            $package
                        )
                ]);
                return [];
        }
        if (!isset($content['extra'])) {
            return [];
        }
        if (!is_array($content['extra'])) {
            $this->io->writeError([
                    sprintf(
                        "<warning>Package %s has broken extra field in composer.json</warning>",
                        $package
                    )
            ]);
            return [];
        }
        if (!isset($content['extra']['spsostrov-runtime'])) {
            return [];
        }
        $config = $content['extra']['spsostrov-runtime'];
        if (!$this->validateExtraConfig($config)) {
            $this->io->writeError([
                    sprintf(
                        "<warning>Package %s has broken runtime configuration in composer.json</warning>",
                        $package
                    )
            ]);
            return [];
        }

        return $config;
    }

    private function validateExtraConfig(&$config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (isset($config['scripts-dir']) && !is_string($config['scripts-dir'])) {
            return false;
        }
        return true;
    }

    private function stripPathPrefix($path, $prefix)
    {
        if ($path === $prefix) {
            return '.';
        }
        if (substr($prefix, -1, 1) !== "/") {
            $prefix = $prefix . "/";
        }
        $len = strlen($prefix);
        if (substr($path, 0, $len) === $prefix) {
            return substr($path, $len);
        } else {
            return null;
        }
    }
}
