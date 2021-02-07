<?php

namespace Flat3\Lodata\Type;

/**
 * Int64
 * @package Flat3\Lodata\Type
 */
class Int64 extends Byte
{
    const identifier = 'Edm.Int64';

    public function toJsonIeee754(): ?string
    {
        return (string) $this->toJson();
    }

    protected function repack($value)
    {
        return (int) $value;
    }
}
