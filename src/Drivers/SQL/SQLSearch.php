<?php

namespace Flat3\Lodata\Drivers\SQL;

use Flat3\Lodata\DeclaredProperty;
use Flat3\Lodata\EntityType;
use Flat3\Lodata\Expression\Event;
use Flat3\Lodata\Expression\Event\EndGroup;
use Flat3\Lodata\Expression\Event\Literal;
use Flat3\Lodata\Expression\Event\Operator;
use Flat3\Lodata\Expression\Event\StartGroup;
use Flat3\Lodata\Expression\Node\Operator\Comparison\And_;
use Flat3\Lodata\Expression\Node\Operator\Comparison\Not_;
use Flat3\Lodata\Expression\Node\Operator\Comparison\Or_;

/**
 * SQL Search
 * @package Flat3\Lodata\Drivers\SQL
 */
trait SQLSearch
{
    /**
     * Generate SQL clauses for the search query option
     * @param  Event  $event  Search event
     * @return bool|null
     */
    public function search(Event $event): ?bool
    {
        switch (true) {
            case $event instanceof StartGroup:
                $this->addWhere('(');

                return true;

            case $event instanceof EndGroup:
                $this->addWhere(')');

                return true;

            case $event instanceof Operator:
                $node = $event->getNode();

                switch (true) {
                    case $node instanceof Or_:
                        $this->addWhere('OR');

                        return true;

                    case $node instanceof And_:
                        $this->addWhere('AND');

                        return true;

                    case $node instanceof Not_:
                        $this->addWhere('NOT');

                        return true;
                }
                break;

            case $event instanceof Literal:
                $properties = [];

                /** @var EntityType $type */
                $type = $this->getType();

                /** @var DeclaredProperty $property */
                foreach ($type->getDeclaredProperties() as $property) {
                    if (!$property->isSearchable()) {
                        continue;
                    }

                    $properties[] = $property;
                }

                $properties = array_map(function ($property) use ($event) {
                    $this->addParameter('%'.$event->getValue().'%');

                    return $this->propertyToField($property).' LIKE ?';
                }, $properties);

                $this->addWhere('( '.implode(' OR ', $properties).' )');

                return true;
        }

        return false;
    }
}
