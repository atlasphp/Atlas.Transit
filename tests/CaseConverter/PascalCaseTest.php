<?php
namespace Atlas\Transit\CaseConverter;

class PascalCaseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $this->converter = new PascalCase();
    }

    /**
     * @dataProvider provideConvert
     */
    public function testConvert($name, $class, $expect)
    {
        $target = new $class();
        $actual = $this->converter->convert($name, $target);
        $this->assertSame($expect, $actual);
    }

    public function provideConvert()
    {
        return [
            ['FooBar', CamelCase::CLASS, 'fooBar'],
            ['FooBar', PascalCase::CLASS, 'FooBar'],
            ['FooBar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
