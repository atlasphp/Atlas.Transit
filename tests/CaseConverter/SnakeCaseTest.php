<?php
namespace Atlas\Transit\CaseConverter;

class SnakeCaseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $this->converter = new SnakeCase();
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
            ['foo_bar', CamelCase::CLASS, 'fooBar'],
            ['foo_bar', PascalCase::CLASS, 'FooBar'],
            ['foo_bar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
