<?php

namespace Flat3\Lodata;

use Flat3\Lodata\Annotation\Capabilities;
use Flat3\Lodata\Annotation\Core;
use Flat3\Lodata\Annotation\Reference;
use Flat3\Lodata\Drivers\EloquentEntitySet;
use Flat3\Lodata\Helper\ObjectArray;
use Flat3\Lodata\Interfaces\IdentifierInterface;
use Flat3\Lodata\Interfaces\Operation\ActionInterface;
use Flat3\Lodata\Interfaces\Operation\FunctionInterface;
use Flat3\Lodata\Interfaces\ResourceInterface;
use Flat3\Lodata\Interfaces\ServiceInterface;

/**
 * Model
 * @link https://docs.oasis-open.org/odata/odata-csdl-xml/v4.01/odata-csdl-xml-v4.01.html#_Toc38530335
 * @package Flat3\Lodata
 */
class Model
{
    /**
     * The OData model
     * @var ObjectArray $model
     * @internal
     */
    protected $model;

    public function __construct()
    {
        $this->model = new ObjectArray();

        $this->model[] = new Core\V1\Reference();
        $this->model[] = new Core\V1\ConventionalIDs();
        $this->model[] = new Core\V1\DefaultNamespace();
        $this->model[] = new Core\V1\DereferencableIDs();
        $this->model[] = new Core\V1\ODataVersions();

        $this->model[] = new Capabilities\V1\Reference();
        $this->model[] = new Capabilities\V1\AsynchronousRequestsSupported();
        $this->model[] = new Capabilities\V1\CallbackSupported();
        $this->model[] = new Capabilities\V1\ConformanceLevel();
        $this->model[] = new Capabilities\V1\SupportedFormats();
    }

    /**
     * Add a named resource or type to the OData model
     * @param  IdentifierInterface  $item  Resource or type
     * @return IdentifierInterface Resource or type
     */
    public function add(IdentifierInterface $item): IdentifierInterface
    {
        $this->model->add($item);
        return $item;
    }

    /**
     * Get an entity type from the model
     * @param  string  $name  Entity type name
     * @return EntityType|null Entity type
     */
    public function getEntityType(string $name): ?EntityType
    {
        return $this->getEntityTypes()->get($name);
    }

    /**
     * Get a singleton from the model
     * @param  string  $name  Singleton name
     * @return Singleton|null Singleton
     */
    public function getSingleton(string $name): ?Singleton
    {
        $resource = $this->getResource($name);
        return $resource instanceof Singleton ? $resource : null;
    }

    /**
     * Get a resource from the model
     * @param  string  $name  Resource name
     * @return IdentifierInterface|null Resource
     */
    public function getResource(string $name): ?IdentifierInterface
    {
        return $this->getResources()->get($name);
    }

    /**
     * Get an entity set from the model
     * @param  string  $name  Entity set name
     * @return EntitySet|null Entity set
     */
    public function getEntitySet(string $name): ?EntitySet
    {
        $resource = $this->getResource($name);
        return $resource instanceof EntitySet ? $resource : null;
    }

    /**
     * Get an operation from the model
     * @param  string  $name  Operation name
     * @return Operation|null Operation
     */
    public function getOperation(string $name): ?Operation
    {
        $resource = $this->getResource($name);
        return $resource instanceof Operation ? $resource : null;
    }

    /**
     * Get a function from the model
     * @param  string  $name  Function name
     * @return FunctionInterface|null Function
     */
    public function getFunction(string $name): ?FunctionInterface
    {
        $resource = $this->getResource($name);
        return $resource instanceof FunctionInterface ? $resource : null;
    }

    /**
     * Get an action from the model
     * @param  string  $name  Action name
     * @return ActionInterface|null Action
     */
    public function getAction(string $name): ?ActionInterface
    {
        $resource = $this->getResource($name);
        return $resource instanceof ActionInterface ? $resource : null;
    }

    /**
     * Get the namespace of this model
     * @return string Namespace
     */
    public function getNamespace(): string
    {
        return config('lodata.namespace');
    }

    /**
     * Drop a named resource or type from the model
     * @param  string  $key  Resource or type
     * @return $this
     */
    public function drop(string $key): self
    {
        $this->model->drop($key);
        return $this;
    }

    /**
     * Get the entity types attached to the model
     * @return ObjectArray Entity types
     */
    public function getEntityTypes(): ObjectArray
    {
        return $this->model->sliceByClass(EntityType::class);
    }

    /**
     * Get the enumeration types attached to the model
     * @return ObjectArray Enumeration types
     */
    public function getEnumerationTypes(): ObjectArray
    {
        return $this->model->sliceByClass(EnumerationType::class);
    }

    /**
     * Get the resources attached to the model
     * @return ObjectArray Resources
     */
    public function getResources(): ObjectArray
    {
        return $this->model->sliceByClass(ResourceInterface::class);
    }

    /**
     * Get the services attached to the model
     * @return ObjectArray Services
     */
    public function getServices(): ObjectArray
    {
        return $this->model->sliceByClass(ServiceInterface::class);
    }

    /**
     * Get the annotations attached to the model
     * @return ObjectArray Annotations
     */
    public function getAnnotations(): ObjectArray
    {
        return $this->model->sliceByClass(Annotation::class);
    }

    /**
     * Get the annotation references attached to the model
     * @return ObjectArray References
     */
    public function getAnnotationReferences(): ObjectArray
    {
        return $this->model->sliceByClass(Reference::class);
    }

    /**
     * Discover the Eloquent model provided as a class name
     * @param  string  $class  Eloquent model class name
     * @return EloquentEntitySet Eloquent entity set
     */
    public function discoverEloquentModel(string $class): EloquentEntitySet
    {
        return EloquentEntitySet::discover($class);
    }

    /**
     * Get the REST endpoint of this OData model
     * @return string REST endpoint
     */
    public function getEndpoint(): string
    {
        return ServiceProvider::endpoint();
    }

    /**
     * Get the PowerBI discovery URL of this service
     * @return string URL
     */
    public function getPbidsUrl(): string
    {
        return ServiceProvider::endpoint().'_lodata/odata.pbids';
    }

    /**
     * Get the Office Data Connection URL of the provided entity set
     * @param  string  $set  Entity set name
     * @return string URL
     */
    public function getOdcUrl(string $set): string
    {
        return sprintf("%s_lodata/%s.odc", ServiceProvider::endpoint(), $set);
    }
}
