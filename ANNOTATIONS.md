# Annotations

The primary point is to specify how Transit moves the data back and forth, not
how the domain object itself behaves.

Note that currently none of these can be inherited; you have to put them on each
class, not on a parent class/trait/interface.

## Existing

Specify the domain handler:

```php
/**
 * @Atlas\Transit\Entity
 * @Atlas\Transit\Collection
 * @Atlas\Transit\Aggregate
 * @Atlas\Transit\ValueObject
 */
```

Specify a custom Mapper class for an Entity or Collection:

```php
/**
 * @Atlas\Transit\(Entity|Collection) App\DataSource\Content\Content
 */
```

(Aggregate classes always use the Mapper for their Root Entity; Value Objects
do not use a Mapper.)

Specify custom parameter-to-field mappings that fall outside the casing
convention:

```php
/**
 * @Atlas\Transit\Parameter $domainParameter source_field
 * @Atlas\Transit\Parameter ...
 */
```

Specify which Aggregate constructor parameter is the Aggregate Root:

```php
/**
 * @Atlas\Transit\AggregateRoot $domainParameter
 */
```


## Prospective Additions

### -

Specify the member class for collections, on a per-record-type basis.

```php
/**
 * @Atlas\Transit\Member App\Domain\Entity\Content
 * @Atlas\Transit\Member App\Domain\Entity\Page App\DataSource\Content\PageRecord
 * @Atlas\Transit\Member App\Domain\Entity\Post App\DataSource\Content\PostRecord
 * @Atlas\Transit\Member App\Domain\Entity\Video App\DataSource\Content\VideoRecord
 */
```

An empty Member record annotation means "any record".

Should the record portion presume the $sourceNamespace? A la:

```php
/**
 * @Atlas\Transit\Member App\Domain\Entity\Content
 * @Atlas\Transit\Member App\Domain\Entity\Page Content\PageRecord
 * @Atlas\Transit\Member App\Domain\Entity\Post Content\PostRecord
 * @Atlas\Transit\Member App\Domain\Entity\Video Content\VideoRecord
 */
```

That would make it easier to switch source namespaces ... though how often would
that happen anyway?

### -

Specify which mapper method to use when creating a new source object for a new
domain object:

```php
/**
 * @Atlas\Transit\NewSource newPageRecord()
 */
```

### -

Specify custom factory & updater methods for a value object.

```php
/**
 * @Atlas\Transit\ValueObject\Factory App\Domain\Value\MoneyConverter::fromSource()
 * @Atlas\Transit\ValueObject\Updater App\Domain\Value\MoneyConverter::intoSource()
 */
```

Presume `self::__transitFromSource()` and `self::__transitIntoSource()` as
initial custom forms.

fromSource() must always be static, function ($record, $field).

intoSource() on self may be instance, function ($record, $field).
otherwise must be static, function ($valueObject, $record, $field).
