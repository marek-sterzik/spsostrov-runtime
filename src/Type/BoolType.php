<?php

namespace SPSOstrov\Runtime\Type;

class BoolType implements TypeWithCustomDefaultInterface
{
    const TRUE_VALUES = ["1", "y", "yes", "true"];
    const FALSE_VALUES = ["0", "n", "no", "false"];

    public function check(?string &$value): ?string
    {
        if ($value === null) {
            return "Value cannot be empty";
        }

        $value = strtolower($value);

        if (in_array($value, self::TRUE_VALUES)) {
            $value = "1";
        } elseif (in_array($value, self::FALSE_VALUES)) {
            $value = "0";
        } else {
            return "Value must be a boolean (y/n)";
        }
        return null;
    }

    public function transformDefaultToString(string $default): string
    {
        if ($default === "1") {
            return 'yes';
        }
        if ($default === "0") {
            return 'no';
        }
        return $default ? 'yes' : 'no';
    }
}

