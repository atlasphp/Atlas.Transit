# Todo Items

## Casing

Allow for "same case" on both sides. Optimization would be a "null case
converter" that does nothing at all, just returns the strings.

Also allow for inconsistent casing on either side; i.e., some columns in the
same table might be snake_case and others camelCase. Needed (among other things)
because Transit::refreshDomain() looks directly at the record, not the data
converter, for the autoinc value. This may be an annotation, perhaps:

```php
/**
 * @Atlas\Transit\Params {"ctorParam" : "sourceCol", ...}
 */
```

They should be merged with the case-converted names, so that you only have to
specify the unusual/non-standard ones.

## Default Column Values

When a new Entity object is created, it may not have some values needed by
the mapper; e.g. single-table inheritance types. Need to be able to specify
default row/record values, probably by annotation, perhaps:

```php
/**
 * @Atlas\Transit\NewRecord {"type": "page", ...}
 * @Atlas\Transit\Fields {"type": "page", ...}
 * @Atlas\Transit\Values {"type": "page", ...}
 */
```

Alternatively, should we be able to specify a method other than `newRecord()`
for creating the backing record for the Entity? Then the special logic for
setting up the new record can be placed there.

```php
/**
 * @Atlas\Transit\NewRecord newPageRecord()
 */
```

Or it could be part of the Source annotation:

```php
/**
 * @Atlas\Transit\Source App\DataSource\Content\Content newPageRecord()
 */
```

That may set an expectation that you always get back Page records from the
Content mapper, though. Perhaps some default where-equals stuff, too, specifically
for STI records?


## Identity Mapping

If we get (e.g.) two FooEntity objects from the same Record, then they can change
independently, and mapping back to the same Record means "last one wins".
Perhaps new() should return the same FooEntity for the same Record (or Row) each time?
That may mean that $storage should be on each Entity and Collection.

Or perhaps leave that to the Repository using the Transit ?
