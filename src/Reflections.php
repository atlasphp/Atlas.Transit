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

    public function get(string $class)
    {
        if (! isset($this->bag[$class])) {
            $this->set($class);
        }

        return $this->bag[$class];
    }

    protected function set(string $domainClass)
    {
        $r = (object) [
            'domainClass' => $domainClass,
            'type' => null,
        ];

        $this->bag[$domainClass] = $r;

        $rclass = new ReflectionClass($domainClass);
        $rdoc = $rclass->getDocComment();

        if ($rdoc === false) {
            return null;
        }

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\(Entity|Aggregate|Collection)\b/m',
            $rdoc,
            $matches
        );

        if ($found !== 1) {
            return;
        }

        $r->docComment = $rdoc;
        $r->constructor = $rclass->getConstructor();

        $r->type = trim($matches[1]);
        $method = 'set' . $r->type;
        $this->$method($r);
    }

    protected function setEntity(object $r)
    {
        $this->setMapperClass($r);
    }

    protected function setCollection(object $r)
    {
        $this->setMapperClass($r);
    }

    protected function setAggregate(object $r)
    {
        $rootClass = $r->constructor
            ->getParameters()[0]
            ->getClass()
            ->getName();

        $rootEntity = $this->get($rootClass);
        $r->mapperClass = $rootEntity->mapperClass;
    }

    protected function setMapperClass(object $r) : void
    {
        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\' . $r->type . '\\\\Mapper\s+(.*)/m',
            $r->docComment,
            $matches
        );

        if ($found === 1) {
            // explicit by annotation
            $r->mapperClass = ltrim(trim($matches[1]), '\\');
            return;
        }

        // implicit by domain class
        $final = strrchr($r->domainClass, '\\');
        if (
            $r->type === 'Collection'
            && substr($final, -10) === 'Collection'
        ) {
            $final = substr($final, 0, -10);
        }

        $r->mapperClass = $this->sourceNamespace . $final . $final;
    }
}
