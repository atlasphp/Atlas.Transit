# Annotations

The primary point is to specify how Transit moves the data back and forth, not
how the domain object itself behaves.

Specify the domain handler:

```php
/**
 * @Atlas\Transit\Domain\Entity
 * @Atlas\Transit\Domain\Collection
 * @Atlas\Transit\Domain\Aggregate
 */
```

Specify a custom Mapper class (only honored on Entities):

```php
/**
 * @Atlas\Transit\Source\Mapper App\DataSource\Content\Content
 */
```

## Prospective Additions

Specify custom field-to-property mappings that fall outside the casing
convention:

```php
/**
 * @Atlas\Transit\Source\Field source_field $domainParameter
 * @Atlas\Transit\Source\Field ...
 */
```

Specify which mapper method to use when creating a new source object for a new
domain object:

```php
/**
 * @Atlas\Transit\Source\New newPageRecord()
 */
```

Specify which Aggregate constructor parameter is the Aggregate Root:

```php
/**
 * @Atlas\Transit\Domain\AggregateRoot $domainParameter
 */
```

Identify the domain class as a value object:

```php
/**
 * @Atlas\Transit\Domain\ValueObject
 */
```

Specify custom factory & updater methods for a value object:

```php
/**
 * @Atlas\Transit\Domain\Factory App\Domain\Value\MoneyConverter::fromSource()
 * @Atlas\Transit\Source\Updater App\Domain\Value\MoneyConverter::intoSource()
 */
```
