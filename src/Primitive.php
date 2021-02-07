<?php

namespace Flat3\Lodata;

use Flat3\Lodata\Controller\Response;
use Flat3\Lodata\Controller\Transaction;
use Flat3\Lodata\Exception\Protocol\NoContentException;
use Flat3\Lodata\Helper\Laravel;
use Flat3\Lodata\Interfaces\ContextInterface;
use Flat3\Lodata\Interfaces\EmitInterface;
use Flat3\Lodata\Interfaces\IdentifierInterface;
use Flat3\Lodata\Interfaces\Operation\ArgumentInterface;
use Flat3\Lodata\Interfaces\PipeInterface;
use Flat3\Lodata\Interfaces\ResourceInterface;
use Illuminate\Support\Str;

/**
 * Primitive
 * @link https://docs.oasis-open.org/odata/odata-csdl-xml/v4.01/odata-csdl-xml-v4.01.html#_Toc38530338
 * @package Flat3\Lodata
 */
abstract class Primitive implements ResourceInterface, ContextInterface, IdentifierInterface, ArgumentInterface, EmitInterface, PipeInterface
{
    /**
     * The OData type name of this primitive
     */
    const identifier = 'Edm.None';

    /**
     * Whether the value can be made null
     * @var bool $nullable
     * @internal
     */
    protected $nullable = true;

    /**
     * Internal representation of the value
     * @var ?mixed $value
     * @internal
     */
    protected $value;

    public function __construct($value = null, bool $nullable = true)
    {
        $this->nullable = $nullable;
        $this->set($value);
    }

    /**
     * Set the value of this primitive
     * @param  mixed  $value  Value
     * @return Primitive
     */
    abstract public function set($value);

    /**
     * Generate a new primitive of this type
     * @param  mixed|null  $value  Value
     * @param  bool|null  $nullable  Whether this instance of the primitive supports null
     * @return Primitive
     * @codeCoverageIgnore
     */
    public static function factory($value = null, ?bool $nullable = true): self
    {
        if ($value instanceof Primitive) {
            return $value;
        }

        /** @phpstan-ignore-next-line */
        return new static($value, $nullable);
    }

    /**
     * Get the internal representation of the value
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Get the value in a format suitable for an OData URL
     * @return string
     */
    abstract public function toUrl(): string;

    /**
     * Get the value in a format suitable for JSON encoding in IEEE754 mode
     * @return mixed
     */
    public function toJsonIeee754()
    {
        $value = $this->toJson();

        return null === $value ? null : (string) $value;
    }

    /**
     * Get the value in a format suitable for JSON encoding
     * @return mixed
     */
    abstract public function toJson();

    /**
     * Return null or an "empty" value if this type cannot be made null
     * @param  mixed  $value  The input value
     * @return mixed The coerced value
     */
    public function maybeNull($value)
    {
        if (null === $value) {
            return $this->nullable ? null : $this->getEmpty();
        }

        return $value;
    }

    /**
     * Get whether this value can be made null
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Set whether this value can be made null
     * @param  bool  $nullable
     * @return $this
     */
    public function setNullable(bool $nullable): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    /**
     * Get the "empty" representation of this type if it cannot be made null
     * @return mixed Empty value
     */
    protected function getEmpty()
    {
        return '';
    }

    /**
     * Get the name of this primitive type
     * @return string Name
     */
    public function getName(): string
    {
        return Str::afterLast($this->getIdentifier(), '.');
    }

    /**
     * Get the namespace of this primitive type
     * @return string Namespace
     */
    public function getNamespace(): string
    {
        return Laravel::beforeLast($this->getIdentifier(), '.');
    }

    /**
     * Get the resolved name of this primitive type
     * @param  string  $namespace  Namespace
     * @return string Name
     */
    public function getResolvedName(string $namespace): string
    {
        if ($this->getNamespace() === $namespace) {
            return $this->getName();
        }

        return $this->getIdentifier();
    }

    /**
     * Get the resource URL of this primitive type
     * @param  Transaction  $transaction  Related transaction
     * @return string Resource URL
     */
    public function getResourceUrl(Transaction $transaction): string
    {
        return $transaction->getResourceUrl().$this->getName().'()';
    }

    /**
     * Get the fully qualified identifier of this primitive type
     * @return string Identifier
     */
    public function getIdentifier(): string
    {
        return $this::identifier;
    }

    /**
     * Get the context URL of this primitive type
     * @param  Transaction  $transaction  Related transaction
     * @return string Context URL
     */
    public function getContextUrl(Transaction $transaction): string
    {
        return $transaction->getContextUrl().'#'.$this->getIdentifier();
    }

    public function emit(Transaction $transaction): void
    {
        $transaction->outputJsonValue($this);
    }

    public function response(Transaction $transaction, ?ContextInterface $context = null): Response
    {
        if (null === $this->get()) {
            throw new NoContentException('null_value');
        }

        $context = $context ?: $this;

        $metadata = $transaction->getMetadata()->getContainer();

        $metadata['context'] = $context->getContextUrl($transaction);

        return $transaction->getResponse()->setResourceCallback($this, function () use ($transaction, $metadata) {
            $transaction->outputJsonObjectStart();

            if ($metadata->hasMetadata()) {
                $transaction->outputJsonKV($metadata->getMetadata());
                $transaction->outputJsonSeparator();
            }

            $transaction->outputJsonKey('value');
            $this->emit($transaction);

            $transaction->outputJsonObjectEnd();
        });
    }

    public static function pipe(
        Transaction $transaction,
        string $currentSegment,
        ?string $nextSegment,
        ?PipeInterface $argument
    ): ?PipeInterface {
        return $argument;
    }

    /**
     * @return string
     * @internal
     */
    public function __toString()
    {
        return (string) $this->value;
    }
}
