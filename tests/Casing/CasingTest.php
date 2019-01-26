<?php
declare(strict_types=1);

namespace Atlas\Transit\Casing;

use Atlas\Transit\Inflector;

abstract class CasingTest extends \PHPUnit\Framework\TestCase
{
    public function newInflector(string $domainCasingClass)
    {
        $recordCasingClass = substr(get_class($this), 0, -4);
        return new Inflector(
            new $recordCasingClass(),
            new $domainCasingClass()
        );
    }

    /**
     * @dataProvider provide
     */
    public function test($source, $domainCasingClass, $domain)
    {
        $converter = $this->newInflector($domainCasingClass);
        $actual = $converter->fromSourceToDomain($source);
        $this->assertSame($domain, $actual);
        $actual = $converter->fromDomainToSource($domain);
        $this->assertSame($source, $actual);
    }

    abstract public function provide();
}
