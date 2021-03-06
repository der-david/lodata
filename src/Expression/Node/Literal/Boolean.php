<?php

namespace Flat3\Lodata\Expression\Node\Literal;

use Flat3\Lodata\Expression\Node\Literal;
use Flat3\Lodata\Helper\Constants;

/**
 * Boolean
 * @package Flat3\Lodata\Expression\Node\Literal
 */
class Boolean extends Literal
{
    public function getValue(): bool
    {
        return Constants::TRUE === $this->value;
    }
}
