<?php

namespace Flat3\Lodata\Drivers;

use Flat3\Lodata\Controller\Transaction;
use Flat3\Lodata\DeclaredProperty;
use Flat3\Lodata\Drivers\SQL\SQLConnection;
use Flat3\Lodata\Drivers\SQL\SQLFilter;
use Flat3\Lodata\Drivers\SQL\SQLLimits;
use Flat3\Lodata\Drivers\SQL\SQLOrderBy;
use Flat3\Lodata\Drivers\SQL\SQLSchema;
use Flat3\Lodata\Drivers\SQL\SQLSearch;
use Flat3\Lodata\Drivers\SQL\SQLWhere;
use Flat3\Lodata\Entity;
use Flat3\Lodata\EntitySet;
use Flat3\Lodata\EntityType;
use Flat3\Lodata\Exception\Protocol\InternalServerErrorException;
use Flat3\Lodata\Helper\ObjectArray;
use Flat3\Lodata\Helper\PropertyValue;
use Flat3\Lodata\Interfaces\EntitySet\CountInterface;
use Flat3\Lodata\Interfaces\EntitySet\CreateInterface;
use Flat3\Lodata\Interfaces\EntitySet\DeleteInterface;
use Flat3\Lodata\Interfaces\EntitySet\ExpandInterface;
use Flat3\Lodata\Interfaces\EntitySet\FilterInterface;
use Flat3\Lodata\Interfaces\EntitySet\OrderByInterface;
use Flat3\Lodata\Interfaces\EntitySet\PaginationInterface;
use Flat3\Lodata\Interfaces\EntitySet\QueryInterface;
use Flat3\Lodata\Interfaces\EntitySet\ReadInterface;
use Flat3\Lodata\Interfaces\EntitySet\SearchInterface;
use Flat3\Lodata\Interfaces\EntitySet\UpdateInterface;
use Flat3\Lodata\NavigationProperty;
use Flat3\Lodata\Property;
use Flat3\Lodata\ReferentialConstraint;
use PDO;
use PDOException;
use PDOStatement;

/**
 * SQL Entity Set
 * @package Flat3\Lodata\Drivers
 */
class SQLEntitySet extends EntitySet implements SearchInterface, FilterInterface, CountInterface, OrderByInterface, PaginationInterface, QueryInterface, ReadInterface, CreateInterface, UpdateInterface, DeleteInterface, ExpandInterface
{
    use SQLConnection;
    use SQLFilter;
    use SQLOrderBy;
    use SQLSearch;
    use SQLLimits;
    use SQLSchema;
    use SQLWhere {
        generateWhere as protected sqlGenerateWhere;
    }

    /**
     * Mapping of OData properties to source identifiers
     * @var ObjectArray $sourceMap
     * @internal
     */
    protected $sourceMap;

    /**
     * Database table for this entity sett
     * @var string $table
     * @internal
     */
    private $table;

    public function __construct(string $name, EntityType $entityType)
    {
        parent::__construct($name, $entityType);
        $this->sourceMap = new ObjectArray();
    }

    /**
     * Get the table name used by this entity set
     * @return string Table name
     */
    public function getTable(): string
    {
        return $this->table ?: $this->identifier->getName();
    }

    /**
     * Set the table name used by this entity set
     * @param  string  $table  Table name
     * @return $this
     */
    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Convert the provided entity type property to a qualified database field
     * @param  Property  $property  Property
     * @return string Qualified field name
     */
    protected function propertyToField(Property $property): string
    {
        return sprintf('%s.`%s`', $this->getTable(), $this->getPropertySourceName($property));
    }

    /**
     * Get the underlying database field name for the given entity type property
     * @param  Property  $property  Property
     * @return string Field name
     */
    public function getPropertySourceName(Property $property): string
    {
        return $this->sourceMap[$property] ?? $property->getName();
    }

    /**
     * Read an SQL record
     * @param  PropertyValue  $key  Key
     * @return Entity|null Entity
     */
    public function read(PropertyValue $key): ?Entity
    {
        $this->resetParameters();
        $columns = $this->getColumnsToQuery();
        $query = sprintf(
            'SELECT %s FROM %s WHERE %s=?',
            $columns,
            $this->getTable(),
            $this->propertyToField($key->getProperty())
        );
        $this->addParameter($key->getPrimitiveValue()->get());
        $stmt = $this->pdoSelect($query);
        $this->bindParameters($stmt);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (false === $result) {
            return null;
        }

        return $this->assocToEntity($result);
    }

    /**
     * Count the number of records matching the query
     * @return int Count
     */
    public function count(): int
    {
        $this->resetParameters();

        $query = $this->pdoSelect($this->getRowCountQueryString());
        return $query->fetchColumn();
    }

    /**
     * Generate a PDO-compatible SQL query that modifies the database
     * @param  string  $queryString  Query string
     * @return int|null Affected row ID
     */
    private function pdoModify(string $queryString): ?int
    {
        $dbh = $this->getHandle();

        try {
            $stmt = $dbh->prepare($queryString);
            $this->bindParameters($stmt);
            $stmt->execute();
        } catch (PDOException $e) {
            throw new InternalServerErrorException('query_error',
                sprintf('The executed query returned an error: %s', $e->getMessage()));
        }

        return $dbh->lastInsertId();
    }

    /**
     * Generate a PDO-compatible SQL query that selects from the database
     * @param  string  $queryString  Query string
     * @return PDOStatement PDO statement handle
     */
    private function pdoSelect(string $queryString): PDOStatement
    {
        $dbh = $this->getHandle();

        try {
            $stmt = $dbh->prepare($queryString);
            $this->bindParameters($stmt);
            $stmt->execute();
        } catch (PDOException $e) {
            throw new InternalServerErrorException(
                'query_error',
                sprintf('The executed query returned an error: %s', $e->getMessage())
            );
        }

        return $stmt;
    }

    /**
     * Apply the generated bind parameters to the provided PDO statement
     * @param  PDOStatement  $stmt  PDO statement handle
     */
    protected function bindParameters(PDOStatement $stmt)
    {
        $parameters = $this->parameters;
        if (!$parameters) {
            return;
        }

        foreach ($this->parameters as $position => $value) {
            $stmt->bindValue($position + 1, $value);
        }
    }

    /**
     * Get a version of the query string that counts the total number of rows in the collection
     * @return string Query string
     */
    public function getRowCountQueryString(): string
    {
        $this->resetParameters();
        $queryString = sprintf('SELECT COUNT(*) FROM %s', $this->getTable());

        $this->generateWhere();

        if ($this->where) {
            $queryString .= sprintf(' WHERE%s', $this->where);
        }

        return $queryString;
    }

    /**
     * Convert an associative array record to an OData entity
     * @param  array  $row  Record
     * @return Entity Entity
     */
    public function assocToEntity(array $row): Entity
    {
        $entity = $this->newEntity();

        $key = $this->getType()->getKey();
        $propertyValue = $entity->newPropertyValue();
        $propertyValue->setProperty($key);
        $propertyValue->setValue($key->getType()->instance($row[$key->getName()]));
        $entity->addProperty($propertyValue);

        foreach ($row as $id => $value) {
            $entity[$id] = $value;
        }

        return $entity;
    }

    /**
     * Run a PDO query and return the results
     * @return array Result buffer
     */
    public function query(): array
    {
        $stmt = $this->pdoSelect($this->getSetResultQueryString());

        $results = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = $this->assocToEntity($row);
        }

        return $results;
    }

    /**
     * Generate the where clause for this query
     */
    public function generateWhere(): void
    {
        $this->sqlGenerateWhere();

        if (!$this->expansionPropertyValue) {
            return;
        }

        $key = $this->resolveExpansionKey();
        $this->whereMaybeAnd();
        $this->addWhere($this->propertyToField($key->getProperty()).' = ?');
        $this->addParameter($key->getPrimitiveValue()->get());
    }

    /**
     * Get the query string representing the query result
     * @return string Query string
     */
    public function getSetResultQueryString(): string
    {
        $this->resetParameters();
        $columns = $this->getColumnsToQuery();

        $query = sprintf('SELECT %s FROM %s', $columns, $this->getTable());

        $this->generateWhere();

        if ($this->where) {
            $query .= sprintf(' WHERE%s', $this->where);
        }

        $query .= $this->generateOrderBy();
        $query .= $this->generateLimits();

        return $query;
    }

    /**
     * Determine the list of columns to include in the query result
     * @return string Columns
     */
    protected function getColumnsToQuery(): string
    {
        $select = $this->transaction->getSelect();

        $properties = $select->getSelectedProperties($this)->sliceByClass(DeclaredProperty::class);

        $key = $this->getType()->getKey();

        if (!$properties[$key]) {
            $properties[] = $key;
        }

        return $this->propertiesToColumns($properties);
    }

    /**
     * Convert the provided entity type property list to a list of SQL fields
     * @param  ObjectArray  $properties  Properties
     * @return string SQL fields
     */
    protected function propertiesToColumns(ObjectArray $properties): string
    {
        $columns = [];

        foreach ($properties as $property) {
            $columns[] = $this->propertyToColumn($property);
        }

        $columns = implode(', ', $columns);

        if (!$columns) {
            throw new InternalServerErrorException(
                'empty_property_set',
                'There are no properties to return in this query'
            );
        }

        return $columns;
    }

    /**
     * Apply an SQL cast based on the provided property type
     * @param  Property  $property  Property
     * @return string SQL field with cast
     */
    protected function propertyToColumn(Property $property): string
    {
        $column = $this->propertyToField($property);

        return sprintf('%s AS %s', $column, $property->getName());
    }

    /**
     * Create a new record
     * @return Entity Entity
     */
    public function create(): Entity
    {
        $entity = $this->newEntity();
        $entity->fromArray($this->transaction->getBody());

        $type = $this->getType();
        $properties = $type->getDeclaredProperties()->pick($entity->getPropertyValues()->keys());
        $propertyValues = $entity->getPropertyValues();

        $fields = [];

        if ($this->expansionPropertyValue) {
            /** @var NavigationProperty $navigationProperty */
            $navigationProperty = $this->expansionPropertyValue->getProperty();

            /** @var ReferentialConstraint $constraint */
            foreach ($navigationProperty->getConstraints() as $constraint) {
                $referencedProperty = $constraint->getReferencedProperty();
                $fields[] = $this->getPropertySourceName($referencedProperty);
                $this->addParameter($this->expansionPropertyValue->getEntity()->getEntityId()->getPrimitiveValue()->get());
            }
        }

        /** @var Property $property */
        foreach ($properties as $property) {
            $fields[] = $this->getPropertySourceName($property);
            $this->addParameter($propertyValues->get($property->getName())->getValue()->get());
        }

        $fieldsList = implode(',', $fields);
        $valuesList = implode(',', array_fill(0, count($fields), '?'));

        $query = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->getTable(), $fieldsList, $valuesList);
        $id = $this->pdoModify($query);
        if ($id) {
            $entity->setEntityId($id);
        }

        $key = $entity->getEntityId();
        $key->getPrimitiveValue()->set($id);

        return $this->read($key);
    }

    /**
     * Update an existing record
     * @param  PropertyValue  $key  Key
     * @return Entity Entity
     */
    public function update(PropertyValue $key): Entity
    {
        $this->resetParameters();
        $entity = $this->newEntity();
        $entity->fromArray($this->transaction->getBody());

        $type = $this->getType();
        $properties = $type->getDeclaredProperties()->pick($entity->getPropertyValues()->keys());
        $primitives = $entity->getPropertyValues();

        $fields = [];

        /** @var Property $property */
        foreach ($properties as $property) {
            $fields[] = sprintf('%s=?', $this->getPropertySourceName($property));
            $this->addParameter($primitives->get($property->getName())->getValue()->get());
        }
        $fields = implode(',', $fields);

        $this->addParameter($key->getPrimitiveValue()->get());

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s=?',
            $this->getTable(),
            $fields,
            $this->propertyToField($type->getKey())
        );

        $this->pdoModify($query);

        return $this->read($key);
    }

    /**
     * Delete a record
     * @param  PropertyValue  $key  Key
     */
    public function delete(PropertyValue $key)
    {
        $this->resetParameters();
        $type = $this->getType();

        $this->addParameter($key->getPrimitiveValue()->get());

        $query = sprintf(
            'DELETE FROM %s WHERE %s=?',
            $this->getTable(),
            $this->getPropertySourceName($type->getKey())
        );

        $this->pdoModify($query);
    }

    /**
     * Set the transaction that applies to this entity set instance, and validate the transaction request
     * @param  Transaction  $transaction  Transaction
     * @return $this
     */
    public function setTransaction(Transaction $transaction): EntitySet
    {
        parent::setTransaction($transaction);
        $this->getSetResultQueryString();

        return $this;
    }
}
