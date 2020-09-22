<?php

namespace Flat3\OData\Property;

use Flat3\OData\EntityType;
use Flat3\OData\Exception\ConfigurationException;
use Flat3\OData\ObjectArray;
use Flat3\OData\Property;

class Navigation extends Property
{
    /** @var self $partner */
    protected $partner;

    /** @var ObjectArray $constraints */
    protected $constraints;

    protected $expandable = true;

    public function __construct($identifier, EntityType $type)
    {
        if (!$type->getKey()) {
            throw new ConfigurationException('The specified entity type must have a key defined');
        }

        parent::__construct($identifier, $type);

        $this->constraints = new ObjectArray();
    }

    public function isExpandable(): bool
    {
        return $this->expandable;
    }

    public function setExpandable(bool $expandable): self
    {
        $this->expandable = $expandable;

        return $this;
    }

    public function getPartner(): ?self
    {
        return $this->partner;
    }

    public function setPartner(self $partner): self
    {
        $this->partner = $partner;

        return $this;
    }

    public function addConstraint(Constraint $constraint): self
    {
        $this->constraints[] = $constraint;

        return $this;
    }

    public function getConstraints(): ObjectArray
    {
        return $this->constraints;
    }
}