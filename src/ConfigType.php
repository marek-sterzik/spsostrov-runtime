<?php

namespace SPSOstrov\Runtime;

use SPSOstrov\Runtime\Type\TypeWithCustomDefaultInterface;
use SPSOstrov\Runtime\Type\TypeInterface;
use SPSOstrov\Runtime\Type\StringType;
use SPSOstrov\Runtime\Type\IntegerType;
use SPSOstrov\Runtime\Type\PortType;
use SPSOstrov\Runtime\Type\BoolType;

use Exception;

final class ConfigType implements TypeWithCustomDefaultInterface
{
    const TYPES = [
        "string" => StringType::class,
        "int" => IntegerType::class,
        "integer" => IntegerType::class,
        "port" => PortType::class,
        "bool" => BoolType::class,
    ];

    public static function create($type)
    {
        if ($type !== null && !($type instanceof TypeInterface)) {
            $type = self::instantiateType($type);
        }
        if ($type instanceof TypeWithCustomDefaultInterface) {
            return $type;
        }
        return new self($type, false);
    }

    private static function instantiateType($type): TypeInterface
    {
        $allowEmpty = false;
        if (is_string($type)) {
            if (substr($type, 0, 1) === "?") {
                $allowEmpty = true;
                $type = substr($type, 1);
            }
            $args = explode(":", $type);
            $type = array_shift($args);
            if (isset(self::TYPES[$type])) {
                $type = self::TYPES[$type];
            }
            if (is_a($type, TypeInterface::class, true)) {
                $type = new $type(...$args);
            } else {
                throw new Exception(sprintf("Invalid question type: %s", $type));
            }
        } else {
            throw new Exception("Invalid question type");
        }
        if ($allowEmpty) {
            $type = new self($type, true);
        }
        return $type;
    }

    /** @var TypeInterface|null */
    private $type;

    /** @var bool */
    private $allowEmpty;

    private function __construct(?TypeInterface $type, bool $allowEmpty)
    {
        $this->type = $type;
        $this->allowEmpty = $allowEmpty;
    }

    public function check(?string &$value): ?string
    {
        if ($this->type === null) {
            return null;
        }

        if ($this->allowEmpty && $value === null) {
            return null;
        }

        return $this->type->check($value);
    }

    public function transformDefaultToString(string $default): string
    {
        if ($this->type instanceof TypeWithCustomDefaultInterface) {
            return $this->type->transformDefaultToString($default);
        } else {
            return $default;
        }
    }
}
