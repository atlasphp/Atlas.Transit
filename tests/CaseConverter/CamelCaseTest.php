<?php
namespace Atlas\Transit\CaseConverter;

class CamelCaseTest extends ACaseTest
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
