<?php
namespace Atlas\Transit\Casing;

class CamelCaseTest extends CasingTest
{
    public function provide()
    {
        return [
            ['fooBar', CamelCase::CLASS, 'fooBar'],
            ['fooBar', PascalCase::CLASS, 'FooBar'],
            ['fooBar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
