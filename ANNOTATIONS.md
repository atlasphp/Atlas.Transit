# Annotations

The primary point is to specify how Transit moves the data back and forth, not
how the domain object itself behaves.

Note that currently none of these can be inherited; you have to put them on each
class, not on a parent class/trait/interface.

## Existing

### Handler Type

Required for Transit to recognize the class as belonging to the Domain; all
other annotations are optional. Use one of the following:

```php
/**
 * @Atlas\Transit\Entity
 * @Atlas\Transit\Collection
 * @Atlas\Transit\Aggregate
 * @Atlas\Transit\ValueObject
 */
```

### Custom Mapper

Specify the Mapper class that works with an Entity or Collection.

```php
/**
 * @Atlas\Transit\Mapper App\DataSource\Content\Content
 */
```

An Aggregate always uses the Mapper specified by its Aggregate Root (Entity).

A Value Object does not use a Mapper.

### Aggregate Root Parameter

Specify which Aggregate constructor parameter is the Aggregate Root.

```php
/**
 * @Atlas\Transit\AggregateRoot $domainParameter
 */
```

### Custom Parameter-to-Field Names

Specify custom parameter-to-field names that fall outside the casing convention
on an Entity or Aggregate. The first value is the Entity or Aggregate
constructor parameter name; the second is the Record field name.

```php
/**
 * @Atlas\Transit\Parameter $domainParameter source_field
 * @Atlas\Transit\Parameter ...
 */
```

### Collection Member Classes

Specify the member class for Collections, on a per-record-type basis. The first
value is the Domain class name; the second is the matching Record class name.

```php
/**
 * @Atlas\Transit\Member App\Domain\Entity\Content
 * @Atlas\Transit\Member App\Domain\Entity\Page App\DataSource\Content\PageRecord
 * @Atlas\Transit\Member App\Domain\Entity\Post App\DataSource\Content\PostRecord
 * @Atlas\Transit\Member App\Domain\Entity\Video App\DataSource\Content\VideoRecord
 */
```

An empty Record value means "the default Record from the Mapper specified on
this Collection."


### Value Object Factory and Updater

Specify custom factory & updater methods for a Value Object.

```php
/**
 * @Atlas\Transit\Factory self::transitFactory()
 * @Atlas\Transit\Updater self::transitUpdater()
 */
```

Factory will be called statically: `function (object $record, string $field) : object`

Updater will be called statically: `function (object $domain, object $record, string $field) : void`

Note that you can pass any class name instead of `self` and the corresponding
method will still be called. The method can be protected or private and Transit
will still call it.

## Prospective Additions

### New Source Object

Specify which mapper method to use when creating a new source object for a new
domain object:

```php
/**
 * @Atlas\Transit\Source newPageRecord()
 */
```
