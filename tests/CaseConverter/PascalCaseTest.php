<?php
namespace Atlas\Transit\CaseConverter;

class PascalCaseTest extends ACaseTest
{
    public function provide()
    {
        return [
            ['FooBar', CamelCase::CLASS, 'fooBar'],
            ['FooBar', PascalCase::CLASS, 'FooBar'],
            ['FooBar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
