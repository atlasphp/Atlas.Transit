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
 */
```

Specify a custom Mapper class for an Entity or Collection:

```php
/**
 * @Atlas\Transit\(Entity|Collection)\Mapper App\DataSource\Content\Content
 */
```

(Aggregate classes always use the Mapper for their Root Entity.)

Specify custom parameter-to-field mappings that fall outside the casing
convention:

```php
/**
 * @Atlas\Transit\(Entity|Aggregate)\Parameter $domainParameter source_field
 * @Atlas\Transit\(Entity|Aggregate)\Parameter ...
 */
```

(This turns out to be especially necessary with explicit mappers, since the
Entity/Collection name does not match the Mapper name.)

## Prospective Additions

Specify which mapper method to use when creating a new source object for a new
Entity object:

```php
/**
 * @Atlas\Transit\Entity|NewRecordMethod newPageRecord()
 */
```

Specify default literal value(s) to use with newRecord():

```php
/**
 * @Atlas\Transit\Entity|NewRecord $type page
 * @Atlas\Transit\Entity|NewRecord ...
 */
```

Specify which Aggregate constructor parameter is the Aggregate Root:

```php
/**
 * @Atlas\Transit\Aggregate\Root $domainParameter
 */
```

Identify the domain class as a value object:

```php
/**
 * @Atlas\Transit\ValueObject
 */
```

Specify custom factory & updater methods for a value object.

```php
/**
 * @Atlas\Transit\ValueObject\Factory App\Domain\Value\MoneyConverter::fromSource()
 * @Atlas\Transit\ValueObject\Updater App\Domain\Value\MoneyConverter::intoSource()
 */
```

Presume `self::__transitFromSource()` and `self::__transitIntoSource()` as
initial custom forms.

