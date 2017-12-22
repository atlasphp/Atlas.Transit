<?php
namespace Atlas\Transit\CaseConverter;

class CamelCaseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $this->converter = new CamelCase();
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
            ['fooBar', CamelCase::CLASS, 'fooBar'],
            ['fooBar', PascalCase::CLASS, 'FooBar'],
            ['fooBar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
