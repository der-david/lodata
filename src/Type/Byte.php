<?php

namespace Flat3\Lodata\Type;

use Flat3\Lodata\Helper\Constants;
use Flat3\Lodata\Primitive;

/**
 * Byte
 * @package Flat3\Lodata\Type
 */
class Byte extends Primitive
{
    const identifier = 'Edm.Byte';
    public const format = 'C';

    /** @var ?int $value */
    protected $value;

    public function toUrl(): string
    {
        if (null === $this->value) {
            return Constants::NULL;
        }

        return (string) $this->value;
    }

    public function toJsonIeee754()
    {
        return $this->toJson();
    }

    public function toJson()
    {
        if (null === $this->value) {
            return null;
        }

        if (is_nan($this->value)) {
            return 'NaN';
        }

        if (is_infinite($this->value)) {
            return (($this->value < 0) ? '-' : '').'INF';
        }

        return $this->value;
    }

    public function set($value): self
    {
        $this->value = $this->maybeNull(null === $value ? null : $this->repack($value));

        return $this;
    }

    protected function repack($value)
    {
        return unpack($this::format, pack('i', $value))[1];
    }

    protected function getEmpty()
    {
        return 0;
    }
}
