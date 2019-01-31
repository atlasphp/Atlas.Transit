<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class EntityReflection extends ParameterReflection
{
    protected $type = 'Entity';
    protected $autoincColumn;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->setMapperClass($reflectionLocator);
        $tableClass = $this->mapperClass . 'Table';
        $this->autoincColumn = $tableClass::AUTOINC_COLUMN;
    }

    protected function setMapperClass(ReflectionLocator $reflectionLocator) : void
    {
        $this->mapperClass = $this->getAnnotatedMaperClass();
        if ($this->mapperClass === null) {
            $final = strrchr($this->domainClass, '\\');
            $this->mapperClass = $reflectionLocator->getSourceNamespace() . $final . $final;
        }

    }
}
