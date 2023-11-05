<?php

namespace SPSOstrov\Runtime;

class ArrayString
{
    public static function toArray(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $values = [];
        $delim = "";
        $acc = null;
        $escape = false;
        foreach (explode("|", $value) as $item) {
            if (preg_match('/^(.*[^\\\\])?(\\\\+)$/', $item, $matches)) {
                if (strlen($matches[2]) % 2 === 0) {
                    $escape = false;
                } else {
                    $escape = true;
                    $item = substr($item, 0, -1);
                }
            } else {
                $escape = false;
            }
            $item = preg_replace('/\\\\(.)/', '\1', $item);
            if ($escape) {
                if ($acc === null) {
                    $acc = $item;
                } else {
                    $acc .= $delim . $item;
                }
            } else {
                if ($acc === null) {
                    $values[] = $item;
                } else {
                    $acc .= $delim . $item;
                    $values[] = $acc;
                    $acc = null;
                }
            }
            $delim = "|";
        }
        if ($acc !== null) {
            if ($escape) {
                $acc .= "\\";
            }
            $values[] = $acc;
        }
        return $values;
    }

    public static function toString(array $values): string
    {
        return implode("|", array_map(function ($item) {
            return preg_replace('/([\\|\\\\])/', '\\\\\\1', $item);
        }, $values));
    }
}
