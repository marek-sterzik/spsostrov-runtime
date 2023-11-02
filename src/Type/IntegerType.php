<?php

namespace SPSOstrov\Runtime\Type;

class IntegerType implements TypeInterface
{
    const PRESETS = [
        "positive" => [1, null],
        "negative" => [null, -1],
        "nonnegative" => [0, null],
        "nonpositive" => [null, 0],
    ];
    /** @var int|null */
    private $min = null;
    
    /** @var int|null */
    private $max = null;

    public function __construct(string ...$args)
    {
        $limits = [];
        foreach ($args as $arg) {
            if (preg_match('/^-?[0-9]*$/', $arg)) {
                if ($arg !== '') {
                    $arg = (int)$arg;
                } else {
                    $arg = null;
                }
                $limits[] = $arg;
            } elseif (isset(self::PRESETS[$arg])) {
                $this->merge(...self::PRESETS[$arg]);
            }
        }

        switch (count($limits)) {
        case 0:
            break;
        case 1:
            if ($limits[0] !== null) {
                if ($limits[0] < 0) {
                    $this->merge($limits[0], 0);
                } else {
                    $this->merge(0, $limits[0]);
                }
            }
            break;
        case 2:
            $this->merge($limits[0], $limits[1]);
            break;
        default:
            throw new Exception("Invalid type");
        }
    }

    private function merge(?int $min, ?int $max)
    {
        if ($min !== null) {
            if ($this->min === null || $min > $this->min) {
                $this->min = $min;
            }
        }
        if ($max !== null) {
            if ($this->max === null || $max < $this->max) {
                $this->max = $max;
            }
        }
    }

    public function check(?string &$value): ?string
    {
        if ($value === null) {
            return "Value cannot be empty";
        }
        if (!preg_match('/^-?[0-9]+$/', $value)) {
            return "Value must be an integer";
        }
        $value = (int)$value;
        $outOfRange = false;
        if ($this->min !== null && $value < $this->min) {
            $outOfRange = true;
        }
        if ($this->max !== null && $value > $this->max) {
            $outOfRange = true;
        }
        if ($outOfRange) {
            return "Value is out of range";
        }
        $value = (string)$value;
        return null;
    }
}
