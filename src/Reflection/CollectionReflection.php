<?php
declare(strict_types=1);

namespace Atlas\Transit\Reflection;

use Atlas\Transit\Inflector\Inflector;
use ReflectionClass;

class CollectionReflection extends Reflection
{
    public $type = 'Collection';
    public $mapperClass;

    public function __construct(
        ReflectionClass $r,
        ReflectionLocator $reflectionLocator
    ) {
        parent::__construct($r, $reflectionLocator);

        $found = preg_match(
            '/^\s*\*\s*@Atlas\\\\Transit\\\\Collection[ \t]+(.*)/m',
            $this->docComment,
            $matches
        );

        if ($found === 1) {
            // explicit by annotation
            $this->mapperClass = ltrim(trim($matches[1]), '\\');
            return;
        }

        // implicit by domain class
        $final = strrchr($this->domainClass, '\\');
        if (substr($final, -10) === 'Collection') {
            $final = substr($final, 0, -10);
        }

        $this->mapperClass = $reflectionLocator->getSourceNamespace() . $final . $final;
    }
}
