<?php

namespace SPSOstrov\Runtime\Type;

class StringType implements TypeInterface
{
    public function check(?string &$value): ?string
    {
        if ($value === null) {
            return "Value cannot be empty";
        }
        return null;
    }
}
