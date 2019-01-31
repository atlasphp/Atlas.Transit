# Atlas.Transit

Moves data from Atlas persistence Records and RecordSets; to domain Entities,
Aggregates, and collections; and back again.

- Creates domain Entities and Aggregates with constructor parameter values taken
  from fields in a source Record; updates the source Record fields from the
  domain Entity and Aggregate properties; refreshes Entities with autoincrement
  values after source inserts.

- Creates domain collection members from Records in a RecordSet; updates Records
  in the RecordSet from the domain collection members.

- Allows for common case conversion between Records and domain objects
  (`snake_case`, `camelCase`, `PascalCase`); defaults to `snake_case` on the
  Record side and `camelCase` on the domain side.

- Allows for custom conversion of values between source Records and domain
  objects.

- Provides `select($domainClass) ... ->fetchDomain()` functionality for creating
  domain objects fluently from Atlas.Orm queries.

Atlas.Transit depends on a number of conventions in the Domain implementation:

- That you can build an entire Entity or Aggregate from the values in a
  single Record (i.e., both the Row and the Related for the Record).

- That domain Entities and Aggregates take constructor parameters for their
  creation, and that the constructor parameter names are identical to their
  internal property names.

- That Aggregates have their Aggregate Root (i.e., their root Entity) as their
  first constructor parameter.

- That Collections use the member class name suffixed with 'Collection'.
  (NOTE: This is only so that Transit can find the mapper; if you want, you
  can specify the mapper with the `@Atlas\Transit\Source\Mapper` annotation.)

- That Collections take a single constructor parameter: an array of the member
  objects in the collection.

- That Collections are traversable/interable, and return the member objects when
  doing so.

Finally, unlike Atlas.Orm and its supporting packages, Atlas.Transit makes
some light use of annotations; this is to help keep the Domain layer as free
from the persistence layer as possible. Annotate your domain classes as follows
to help Transit identify their purpose in the domain:

- Entities are annotated with `@Atlas\Transit\Entity`
- Collections are annotated with `@Atlas\Transit\Collection`
- Aggregates are annotated with `@Atlas\Transit\Aggregate`
- Value Objects are annotated with `@Atlas\Transit\ValueObject`

Your entity classes are presumed by default to have the same names as your
persisence mapper classes. For example, a domain class named `Thread`
automatically uses a source mapper class named `Thread`. If your entity class
uses a different source mapper, add the fully-qualified mapper class name:

```php
/**
 * @Atlas\Transit\Entity App\DataSource\Other\Other
 */
```

## Example

```php
$transit = Transit::new(
    Atlas::new('sqlite::memory:'),
    'App\\DataSource\\', // data source namespace
);

// select records from the mappers to create entities and collections
$thread = $transit
    ->select(Thread::CLASS) // the domain class
    ->where('id = ', 1)
    ->fetchDomain();

$responses = $transit
    ->select(Responses::CLASS) // the domain class
    ->where('thread_id IN ', [2, 3, 4])
    ->fetchDomain();

// do stuff to $thread and $responses

// then plan to save/update all of $responses ...
$transit->store($responses);

// ... and plan to delete $thread
$transit->discard($thread);

// finally, persist all the domain changes in Transit
$success = $transit->persist();
```

## Value Objects

For embedded Value Objects, you need to implement the following methods in each
Value Object class to move data from and back into the source Record objects.
The example code is the minimum for a naive transit back-and-forth:

```php
/**
 * @Atlas\Transit\ValueObject
 */
class ...
{
    private static function __transitFromSource(object $record, string $field)
    {
        return new static($record->$field);
    }

    private function __transitIntoSource(object $record, string $field)
    {
        $record->$field = $this->$field;
    }
}
```

Note that Record-backed Value Objects are going to be very tricky.
