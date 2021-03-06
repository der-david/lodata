<?php

namespace Flat3\Lodata\Type;

use ErrorException;
use Flat3\Lodata\Exception\Protocol\InternalServerErrorException;
use Flat3\Lodata\Helper\Constants;
use Flat3\Lodata\Primitive;

/**
 * String
 * @package Flat3\Lodata\Type
 */
class String_ extends Primitive
{
    const identifier = 'Edm.String';

    /** @var ?string $value */
    protected $value;

    public function toUrl(): string
    {
        if (null === $this->value) {
            return Constants::NULL;
        }

        return "'".str_replace("'", "''", $this->value)."'";
    }

    public function set($value): self
    {
        try {
            $this->value = $this->maybeNull(null === $value ? null : (string) $value);
        } catch (ErrorException $e) {
            throw new InternalServerErrorException('invalid_conversion', 'Could not convert value to string');
        }

        return $this;
    }

    public function toJson(): ?string
    {
        return $this->value;
    }
}
