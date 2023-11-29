<?php

namespace SPSOstrov\Runtime\Plugins;

use SPSOstrov\AppConsole\Plugin;
use SPSOstrov\Runtime\Config;

class ShowBasedOnVariable implements Plugin
{
    private $show;

    public function __construct(string $variable)
    {
        $value = Config::get($variable);

        $this->show = false;
        if ($value !== null && $value !== '') {
            $this->show = true;
        }
    }

    public function processMetadata(array &$metadata): void
    {
        if ($this->show) {
            $metadata['hidden'] = false;
        } else {
            $metadata['hidden'] = true;
        }
    }
}
