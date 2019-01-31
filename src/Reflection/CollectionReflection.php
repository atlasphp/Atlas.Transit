<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class CollectionReflection extends MappedReflection
{
    protected $type = 'Collection';
    protected $memberClass;
    protected $memberClasses = [];

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);
        $this->setMapperClass($reflectionLocator);
        $this->setMemberClasses();
    }

    protected function setMapperClass(ReflectionLocator $reflectionLocator) : void
    {
        $this->mapperClass = $this->getAnnotatedMaperClass();
        if ($this->mapperClass === null) {
            $final = strrchr($this->domainClass, '\\');
            if (substr($final, -10) === 'Collection') {
                $final = substr($final, 0, -10);
            }
            $this->mapperClass = $reflectionLocator->getSourceNamespace() . $final . $final;
        }
    }

    protected function setMemberClasses()
    {
        preg_match_all(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Member[ \t]+?(.*?)([ \t](.*))?\s/m',
            $this->docComment,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            $this->setMemberClassesImplicitly();
            return;
        }

        foreach ($matches as $match) {
            $recordClass = $match[3] ?? $this->mapperClass . 'Record';
            $this->memberClasses[$recordClass] = $match[1];
        }
    }

    protected function setMemberClassesImplicitly()
    {
        if (substr($this->domainClass, -10) !== 'Collection') {
            throw new \Exception("Cannot determine member class for {$this->domainClass}.");
        }

        $recordClass = $this->mapperClass . 'Record';
        $domainClass = substr($this->domainClass, 0, -10);
        $this->memberClasses = [$recordClass => $domainClass];
    }
}
