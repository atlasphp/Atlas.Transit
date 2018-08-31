<?php
namespace Atlas\Transit\Casing;

use Atlas\Transit\CaseConverter;

abstract class CasingTest extends \PHPUnit\Framework\TestCase
{
    public function newCaseConverter(string $domainCasingClass)
    {
        $recordCasingClass = substr(get_class($this), 0, -4);
        return new CaseConverter(
            new $recordCasingClass(),
            new $domainCasingClass()
        );
    }

    /**
     * @dataProvider provide
     */
    public function test($source, $domainCasingClass, $domain)
    {
        $converter = $this->newCaseConverter($domainCasingClass);
        $actual = $converter->fromSourceToDomain($source);
        $this->assertSame($domain, $actual);
        $actual = $converter->fromDomainToSource($domain);
        $this->assertSame($source, $actual);
    }

    abstract public function provide();
}
