# Atlas.Transit

Moves values from Atlas persistence Records and RecordSets; to domain Entities,
Aggregates, and collections; and back again.

- Creates domain Entities and Aggregates with constructor parameter values taken
  from fields in a source Record; updates the source Record fields from the
  domain Entity and Aggregate properties; refreshes Entities with autoincrement
  values after source inserts.

- Creates domain collection members from Records in a RecordSet; updates Records
  in the RecordSet from the domain collection members.

- Allows for common case conversion between Records and domain objects (
  snake_case, camelCase, PascalCase); defaults to snake_case on the Record side
  and camelCase on the

- Allows for custom mapping of values between source Records and domain
  objects.

- Provides `select($domainClass) ... ->fetchDomain()` functionality for creating
  domain objects fluently from Atlas.Orm queries.

Atlas.Transit has some reasonable prerequisites:

- That you can build an entire domain Entity or Aggregate from the values in a
  single Record (i.e., both the Row and the Related for the Record).

- That domain Entities and Aggregates take constructor parameters for their
  creation, and that the constructor parameter names are identical to their
  internal property names.

- That domain collections take a single constructor parameter: an array
  of the domain object members in the collection.

- That domain collection objects are traversable/interable, and return the
  member objects when doing so.

## Example

```
<?php
// given a configured $atlas object ...
$transit = new \Atlas\Transit\Transit($atlas);

// ... use the FooMapper to create FooEntity objects ...
$transit->mapEntity(FooEntity::CLASS, FooMapper::CLASS);
$transit->mapCollection(FooEntityCollection::CLASS, FooMapper::CLASS);

// ... and use the BarMapper to create BarEntity objects
$transit->mapEntity(BarEntity::CLASS, BarMapper::CLASS);
$transit->mapCollection(BarEntityCollection::CLASS, BarMapper::CLASS);

// select records from the mappers to create entities and collections
$foo = $transit->select(FooEntity::CLASS)
    ->where('id = ?', 1)
    ->fetchDomain();

$bars = $transit->select(BarEntityCollection::CLASS)
    ->where('id IN (?)', [2, 3, 4])
    ->fetchDomain();

// do stuff to $foo and $bars

// then plan to save/update all of $bars ...
$transit->store($bars);

// ... and plan to delete $foo
$transit->discard($foo);

// finally, persist all the domain changes in Transit:
$success = $transit->persist();
if (! $success) {
    $exception = $transit->getTransaction()->getException();
    throw $exception;
}
```
