<?php

namespace SPSOstrov\Runtime\Type;

class PortType extends IntegerType
{
    public function __construct(string ...$args)
    {
        parent::__construct("1", "65535");
    }
}
