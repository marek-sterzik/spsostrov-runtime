<?php


namespace SPSOstrov\Runtime\Type;

interface TypeInterface
{
    public function check(?string &$value): ?string;
}
