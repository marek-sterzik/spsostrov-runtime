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
        $runtimeConfig = [];
        $rootDir = $this->canonizePath(InstalledVersions::getInstallPath("__root__"));
        $packages = array_merge(
            ['__root__'],
            InstalledVersions::getInstalledPackagesByType('spsostrov-runtime'),
            [self::SELF_PACKAGE]
        );
        foreach ($packages as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            $path = $this->canonizePath($installPath);
            $path = $this->stripPathPrefix($path, $rootDir);
            if ($path !== null) {
                $runtimeConfig[$package] = $this->loadDirsFromPackage($rootDir, $path, $package);
            } else {
                $this->io->writeError([
                        sprintf(
                            "<warning>Cannot determine relative path for spsstrov-runtime plugin %s</warning>",
                            $package
                        )
                ]);
            }
        }
        var_dump($rootDir, $runtimeConfig);
    }

    private function loadDirsFromPackage($rootDir, $packageDir, $package)
    {
        $config = $this->loadExtraFromComposerJson($rootDir, $packageDir, $package);
        if (!array_key_exists('scripts-dir', $config)) {
            $config['scripts-dir'] = 'scripts';
        }
        if (is_string($config['scripts-dir'])) {
            $config['scripts-dir'] = $this->canonizePath(
                $packageDir . "/" . $this->canonizeRelativePath($config['scripts-dir'])
            );
            if ($config['scripts-dir'] === null) {
                $this->io->writeError([
                        sprintf(
                            "<warning>Package %s contains invalid scripts-dir</warning>",
                            $package
                        )
                ]);
            }
        }
        return $config;
    }

    private function canonizeRelativePath($path)
    {
        $path = ltrim('/', $path);
        if ($path === '') {
            return null;
        }
        $path = $this->canonizePath($path);
        if ($path === '..' || substr($path, 0, 3) === '../') {
            return null;
        }
        return $path;
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

    private function canonizePath($path)
    {
        $currentPath = [];
        if (substr($path, 0, 1) === "/") {
            $absolute = true;
            $path = substr($path, 1);
        } else {
            $absolute = false;
        }
        $out = false;

        foreach (explode("/", $path) as $component) {
            if ($component === "." || $component === "") {
                continue;
            }
            if ($component === ".." && !$out) {
                if (empty($currentPath)) {
                    $out = true;
                    $currentPath[] = "..";
                } else {
                    array_pop($currentPath);
                }
            } else {
                $currentPath[] = $component;
            }
        }


        if ($absolute) {
            return "/" . implode("/", $currentPath);
        } else {
            if (empty($currentPath)) {
                return '.';
            } else {
                return implode("/", $currentPath);
            }
        }
    }
}
