<?php

namespace Flat3\Lodata\Drivers\SQL;

use Flat3\Lodata\DeclaredProperty;
use Flat3\Lodata\Exception\Protocol\InternalServerErrorException;
use Flat3\Lodata\Interfaces\EntitySet\FilterInterface;
use Flat3\Lodata\Interfaces\EntitySet\SearchInterface;

/**
 * SQL Where
 * @package Flat3\Lodata\Drivers\SQL
 */
trait SQLWhere
{
    use SQLParameters;

    /**
     * The where clause
     * @var string $where
     * @internal
     */
    protected $where = '';

    /**
     * Add a statement to the where clause
     * @param  string  $where  Where clause
     */
    protected function addWhere(string $where): void
    {
        $this->where .= ' '.$where;
    }

    /**
     * Generate where clauses for filter and search parameters
     */
    protected function generateWhere(): void
    {
        $this->where = '';

        if ($this instanceof FilterInterface) {
            $filter = $this->transaction->getFilter();
            if ($filter->hasValue()) {
                $this->whereMaybeAnd();

                $filter->applyQuery($this);
            }
        }

        if ($this instanceof SearchInterface) {
            $search = $this->transaction->getSearch();
            if ($search->hasValue()) {
                if (!$this->getType()->getDeclaredProperties()->filter(function ($property) {
                    return $property->isSearchable();
                })->hasEntries()) {
                    throw new InternalServerErrorException(
                        'query_no_searchable_properties',
                        'The provided query had no properties marked searchable'
                    );
                }

                $this->whereMaybeAnd();
                $search->applyQuery($this);
            }
        }
    }

    /**
     * Attach an AND statement to the where clause if required
     */
    protected function whereMaybeAnd(): void
    {
        if ($this->where) {
            $this->addWhere('AND');
        }
    }
}
