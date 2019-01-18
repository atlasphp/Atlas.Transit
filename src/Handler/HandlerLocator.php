<?php
namespace Atlas\Transit\Handler;

use Atlas\Orm\Atlas;
use Atlas\Transit\CaseConverter;
use Atlas\Transit\Casing\CamelCase;
use Atlas\Transit\Casing\SnakeCase;
use Atlas\Transit\Exception;
use ReflectionClass;
use SplObjectStorage;

class HandlerLocator
{
    protected $instances = [];

    protected $sourceNamespace;

    protected $caseConverter;

    protected $storage;

    protected $valueObjectHandler;

    public function __construct(
        Atlas $atlas,
        string $sourceNamespace,
        CaseConverter $caseConverter
    ) {
        $this->atlas = $atlas;
        $this->sourceNamespace = rtrim($sourceNamespace, '\\');
        $this->caseConverter = $caseConverter;
        $this->storage = new SplObjectStorage();
        $this->valueObjectHandler = new ValueObjectHandler($caseConverter);
    }

    public function getStorage() : SplObjectStorage
    {
        return $this->storage;
    }

    public function getOrThrow($spec) : Handler
    {
        if (is_object($spec)) {
            $spec = get_class($spec);
        }

        $handler = $this->getByClass($spec);

        if ($handler === null) {
            throw new Exception("No handler for class '$spec'.");
        }

        return $handler;
    }

    public function get($spec) : ?Handler
    {
        if (is_object($spec)) {
            $spec = get_class($spec);
        }

        return $this->getByClass($spec);
    }

    protected function getByClass(string $domainClass) : ?Handler
    {
        if (! class_exists($domainClass)) {
            throw new Exception("Domain class '{$domainClass}' does not exist.");
        }

        if (! array_key_exists($domainClass, $this->instances)) {
            $this->instances[$domainClass] = $this->newHandler($domainClass);
        }

        return $this->instances[$domainClass];
    }

    protected function newHandler(string $domainClass) : ?Handler
    {
        $rclass = new ReflectionClass($domainClass);
        $rdoc = $rclass->getDocComment();
        if ($rdoc === false) {
            return null;
        }

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Domain\\\\(Entity|Aggregate|Collection)\b/m',
            $rdoc,
            $matches
        );

        if ($found !== 1) {
            return null;
        }

        switch (trim($matches[1])) {
            case 'Entity':
                return $this->newEntity($domainClass, $rdoc);
            case 'Collection':
                return $this->newCollection($domainClass, $rdoc);
            case 'Aggregate':
                return $this->newAggregate($domainClass, $rdoc, $rclass);
        }

        // @todo the annotation is there, but it is unrecognized; throw exception

        return null;
    }

    protected function newEntity(string $domainClass, string $rdoc) : EntityHandler
    {
        $mapperClass = $this->getMapperClassForEntity($domainClass, $rdoc);
        $mapper = $this->atlas->mapper($mapperClass);

        return new EntityHandler(
            $domainClass,
            $mapper,
            $this,
            $this->storage,
            $this->caseConverter,
            $this->valueObjectHandler
        );
    }

    protected function newCollection(string $domainClass, string $rdoc) : CollectionHandler
    {
        $spec = $domainClass;
        if (substr($domainClass, -10) === 'Collection') {
            $spec = substr($domainClass, 0, -10);
        }

        $mapperClass = $this->getMapperClassForEntity($spec, $rdoc);
        $mapper = $this->atlas->mapper($mapperClass);

        return new CollectionHandler(
            $domainClass,
            $mapper,
            $this,
            $this->storage
        );
    }

    protected function newAggregate(string $domainClass, string $rdoc, ReflectionClass $rclass) : AggregateHandler
    {
        $rootClass = $rclass
            ->getMethod('__construct')
            ->getParameters()[0]
            ->getClass()
            ->getName();

        $mapperClass = $this->getMapperClassForEntity($rootClass, $rdoc);
        $mapper = $this->atlas->mapper($mapperClass);

        return new AggregateHandler(
            $domainClass,
            $mapper,
            $this,
            $this->storage,
            $this->caseConverter,
            $this->valueObjectHandler
        );
    }

    protected function getMapperClassForEntity(string $domainClass, string $rdoc) : string
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Source\\\\Mapper\s+(.*)/m',
            $rdoc,
            $matches
        );

        if ($found === 1) {
            // strip leading backslashes after stripping all whitespace
            return ltrim(trim($matches[1]), '\\');
        }

        $final = strrchr($domainClass, '\\');
        $class = $this->sourceNamespace . $final . $final;
        return $class;
    }
}
