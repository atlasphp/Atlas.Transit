# DI Support

Will need some form of DI support for DataConverter, as well as Factory objects
(if they appear).

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

# Casing

Allow for "same case" on both sides. Optimization would be a "null case
converter" that does nothing at all, just returns the strings.

Also allow for inconsistent casing on either side; i.e., some columns in the
same table might be snake_case and others camelCase. Needed (among other things)
because Transit::refreshDomain() looks directly at the record, not the data
converter, for the autoinc value.

# DataConverter

Simple single-value value objects seem like they should be handle-able without
data conversion. The problem is not constructing the VO, but getting the value
back out of the VO later. Perhaps just reflect on property named for the first
parameter on the VO? (And typehinting to stdClass does a JSON encode/decode.)

# Factory

Allow specification of factories on handlers?

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

In a way, this can be handled via DataConverter: sets the constructor params,
etc. But does not call post-construction methods, etc., and does not return
different Domain classes from the same Record class.

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

# Identity Mapping

If we get (e.g.) two FooEntity objects from the same Record, then they can change
independently, and mapping back to the same Record means "last one wins".
Perhaps new() should return the same FooEntity for the same Record (or Row) each time?
That may mean that $storage should be on each Entity and Collection.

Or perhaps leave that to the Repository using the Transit ?
