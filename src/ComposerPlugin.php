<?php

namespace SPSOstrov\Runtime;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\InstalledVersions;
use Composer\EventDispatcher\EventSubscriberInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
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
        $data = [];
        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            $data[$package] = $this->canonizePath($installPath);
        }
        var_dump($data);
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
            if ($component === "." || $component === ".") {
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
