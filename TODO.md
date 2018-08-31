# DI Support

Will need some form of DI support for DataConverter, as well as Factory objects
(if they appear).

# Autoinc Refresh

Only set the Entity value if the row was inserted, and *then* be sure to convert
based on the parameter type, not the property type.

    $handler->getAutoincParameter()->getType()
    $handler->getAutoincProperty()

That might even help support objects as identifiers (a la FooIdentity).

# Factory

Allow specification of factories on handlers.

```
$transit->mapEntity(SpecialEntity::CLASS, SpecialMapper::CLASS)
    ->factory([SpecialEntityFactory::CLASS, 'newFromRecord']);

class SpecialEntityFactory
{
    public function newFromRecord($specialRecord)
    {
        return new SpecialEntity(...);
    }
}
```

# Bounded Context

Consider advising one Transit per Bounded Context. Each Bounded Context has its
own entities and aggregates and values. They can all use the same Atlas, though.

Alternatively, consider a dictionary of Domain namespaces to Mapper namespaces,
a la:

```php
$this->transit = new Transit(
    $this->atlas,
    'Atlas\\Transit\\Domain\\',
    'Atlas\\Testing\\DataSource\\',
    // $defaultCaseConverter
);

$transit->addDomainNamespace(
    'App\Domain\Context\Foo\\',
    // $otherDataSourceNamespace,
    // $otherCaseConverter
);
```

# "Through" Mappings

Perhaps need ...

    $transit->through($throughFieldName, $foreignFieldName)

... to specify association mapping tables? E.g.:

    $transit->mapEntity(ThreadEntity::CLASS)
        ->setDomainFromRecord([
            'tagsPropertyName' => 'tags'
        ])
        ->setRecordFromDomain([
            'tags' => $transit->mapThrough('tagsPropertyName', 'taggingsFieldName')
        ]);

Or should that be something Mapper::persist() does with "through" associations?

# Identity Mapping

If we get (e.g.) two FooEntity objects from the same Record, then they can change
independently, and mapping back to the same Record means "last one wins".
Perhaps new() should return the same FooEntity for the same Record (or Row) each time?
That may mean that $storage should be on each Entity and Collection.

Or perhaps leave that to the Repository using the Transit ?
