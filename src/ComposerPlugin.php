<?php

namespace SPSOstrov\Runtime;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ComposerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        echo "ACTIVATE!\n";
        //$installer = new TemplateInstaller($io, $composer);
        //$composer->getInstallationManager()->addInstaller($installer);
    }
}
