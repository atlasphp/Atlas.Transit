<?php
namespace Atlas\Transit\CaseConverter;

abstract class ACaseTest extends \PHPUnit\Framework\TestCase
{
    public function newConverter(string $domainCaseClass)
    {
        $recordCaseClass = substr(get_class($this), 0, -4);
        return new CaseConverter(
            new $recordCaseClass(),
            new $domainCaseClass()
        );
    }

    /**
     * @dataProvider provide
     */
    public function test($source, $domainCaseClass, $domain)
    {
        $converter = $this->newConverter($domainCaseClass);
        $actual = $converter->fromRecordToDomain($source);
        $this->assertSame($domain, $actual);
        $actual = $converter->fromDomainToRecord($domain);
        $this->assertSame($source, $actual);
    }

    abstract public function provide();
}
