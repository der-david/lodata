<?php

namespace Flat3\Lodata\Transaction\Option;

use Flat3\Lodata\EntitySet;
use Flat3\Lodata\Expression\Parser\Filter as Parser;
use Flat3\Lodata\Transaction\Option;

/**
 * Filter
 * @link http://docs.oasis-open.org/odata/odata/v4.01/odata-v4.01-part2-url-conventions.html#sec_SystemQueryOptionfilter
 * @package Flat3\Lodata\Transaction\Option
 */
class Filter extends Option
{
    public const param = 'filter';

    public function applyQuery(EntitySet $entitySet): void
    {
        if (!$this->hasValue()) {
            return;
        }

        $parser = new Parser($entitySet, $this->transaction);
        $tree = $parser->generateTree($this->getValue());
        $tree->compute();
    }
}
