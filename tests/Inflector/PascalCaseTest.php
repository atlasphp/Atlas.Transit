<?php
declare(strict_types=1);

namespace Atlas\Transit\Inflector;

class PascalCaseTest extends CasingTest
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
