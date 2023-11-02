<?php

namespace SPSOstrov\Runtime\Type;

interface TypeWithCustomDefaultInterface extends TypeInterface
{
    public function transformDefaultToString(string $default): string;
}

