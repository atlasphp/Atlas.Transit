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

- That the Domain objects be in an Entity or Aggregate namespace, under the
  same "parent" Domain namespace, and that the Mappers map 1:1 with Entity
  classes.

    ```
    App/
        Domain/
            Aggregate/
                Discussion/
                    Discussion.php
            Entity/
                Thread/
                    Thread.php
                    ThreadCollection.php
                Reply/
                    Reply.php
                    ReplyCollection.php
        DataSource/
            Thread/
                Thread.php # mapper
                ThreadRecord.php
                ThreadRecordSet.php
    ```

- That you can build an entire domain Entity or Aggregate from the values in a
  single Record (i.e., both the Row and the Related for the Record).

- That domain Entities and Aggregates take constructor parameters for their
  creation, and that the constructor parameter names are identical to their
  internal property names.

- That Aggregate objects have their Aggregate Root (Entity) as their first
  constructor parameter.

- That Entity collections use the Entity name suffixed with 'Collection'.

- That Entity collections take a single constructor parameter: an array
  of the Entities in the collection.

- That Entity collection objects are traversable/interable, and return the
  member Entities when doing so.


## Example

```php
$transit = Transit::new(
    Atlas::new('sqlite::memory:'),
    'App\\DataSource\\',
    'App\\Domain\\'
    // source casing class name
    // domain casing class name
);

// select records from the mappers to create entities and collections
$thread = $transit
    ->select(Thread::CLASS)
    ->where('id = ', 1)
    ->fetchDomain();

$replies = $transit
    ->select(ReplyCollection::CLASS)
    ->where('thread_id IN ', [2, 3, 4])
    ->fetchDomain();

// do stuff to $thread and $replies

// then plan to save/update all of $replies ...
$transit->store($replies);

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
private static function __transitFromSource(object $record, string $field)
{
    return new static($record->$field);
}

private function __transitIntoSource(object $record, string $field)
{
    $record->$field = $this->$field;
}
```

Note that Record-backed Value Objects are going to be very tricky.
