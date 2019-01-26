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

## Prospective Additions

Specify custom parameter-to-field mappings that fall outside the casing
convention:

```php
/**
 * @Atlas\Transit\(Entity|Aggregate)\Parameter $domainParameter source_field
 * @Atlas\Transit\(Entity|Aggregate)\Parameter ...
 */
```

Specify which mapper method to use when creating a new source object for a new
domain object:

```php
/**
 * @Atlas\Transit\(Entity|Collection)\Mapper\New newPageRecord()
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
 * @Atlas\Transit\ValueObject\Updater App\Domain\Value\MoneyConverter->intoSource()
 */
```

Presume `self::__transitFromSource()` and `self::__transitIntoSource()` as initial
custom forms.
