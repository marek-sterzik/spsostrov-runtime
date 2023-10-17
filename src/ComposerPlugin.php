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
        $data['__root__'] = InstalledVersions::getInstallPath("__root__");
        foreach (InstalledVersions::getInstalledPackagesByType('spsostrov-runtime') as $package) {
            $installPath = InstalledVersions::getInstallPath($package);
            $data[$package] = $this->canonizePath($installPath);
        }
        var_dump($data);
        $this->io->writeError(["Cannot determine relative paths of packages, spsostrov/runtime package will not load its plugins"]);
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
