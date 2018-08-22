<?php
namespace Atlas\Transit\CaseConverter;

class SnakeCaseTest extends ACaseTest
{
    public function provide()
    {
        return [
            ['foo_bar', CamelCase::CLASS, 'fooBar'],
            ['foo_bar', PascalCase::CLASS, 'FooBar'],
            ['foo_bar', SnakeCase::CLASS, 'foo_bar'],
        ];
    }
}
