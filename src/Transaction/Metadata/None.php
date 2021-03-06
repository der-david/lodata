<?php

namespace Flat3\Lodata\Transaction\Metadata;

use Flat3\Lodata\Transaction\Metadata;

/**
 * None
 * @package Flat3\Lodata\Transaction\Metadata
 */
final class None extends Metadata
{
    public const name = 'none';
    protected $requiredProperties = ['nextLink', 'count'];
}
