<?php
declare(strict_types=1);

namespace Atlas\Transit;

use ReflectionClass;

class Reflections
{
    protected $bag = [];

    protected $sourceNamespace;

    public function __construct(string $sourceNamespace)
    {
        $this->sourceNamespace = rtrim($sourceNamespace, '\\');
    }

    public function get(string $domainClass)
    {
        if (! isset($this->bag[$domainClass])) {
            $this->set($domainClass);
        }

        return $this->bag[$domainClass]->transit;
    }

    protected function set(string $domainClass)
    {
        $r = new ReflectionClass($domainClass);
        $r->transit = (object) [
            'domainClass' => $domainClass,
            'type' => null,
        ];

        $this->bag[$domainClass] = $r;

        $r->transit->docComment = $r->getDocComment();

        if ($r->transit->docComment === false) {
            return null;
        }

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\(Entity|Aggregate|Collection)\b/m',
            $r->transit->docComment,
            $matches
        );

        if ($found !== 1) {
            return;
        }

        $r->transit->type = trim($matches[1]);

        $r->transit->parameters = $r->getConstructor()->getParameters();

        $method = 'set' . $r->transit->type;
        $this->$method($r);
    }

    protected function setEntity(ReflectionClass $r)
    {
        $this->setMapperClass($r);
    }

    protected function setCollection(ReflectionClass $r)
    {
        $this->setMapperClass($r);
    }

    protected function setAggregate(ReflectionClass $r)
    {
        $rootClass = $r->transit
            ->parameters[0]
            ->getClass()
            ->getName();

        $rootEntity = $this->get($rootClass);
        $r->transit->mapperClass = $rootEntity->mapperClass;
    }

    protected function setMapperClass(ReflectionClass $r) : void
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $r->transit->type . '\\\\Mapper\s+(.*)/m',
            $r->transit->docComment,
            $matches
        );

        if ($found === 1) {
            // explicit by annotation
            $r->transit->mapperClass = ltrim(trim($matches[1]), '\\');
            return;
        }

        // implicit by domain class
        $final = strrchr($r->transit->domainClass, '\\');
        if (
            $r->transit->type === 'Collection'
            && substr($final, -10) === 'Collection'
        ) {
            $final = substr($final, 0, -10);
        }

        $r->transit->mapperClass = $this->sourceNamespace . $final . $final;
    }
}
