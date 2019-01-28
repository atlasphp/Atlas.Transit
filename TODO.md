# Todo Items

## Transit Object

- Have TransitSelect extend MapperSelect, and configure Atlas to
  factory *that* instead of MapperSelect? Would provide "transparent"
  access to all select methods.

- Consider persist/delete/flush instead of store/discard/persist.

- Expose Atlas via `__call()` ? Would affect the store/flush/etc. naming.


## Casing

Allow for "same case" on both sides. Optimization would be a "null inflector"
that does nothing at all, just returns the strings.

Also allow for inconsistent casing on either side; i.e., some columns in the
same table might be snake_case and others camelCase. Needed (among other things)
because Transit::refreshDomain() looks directly at the record, not the data
converter, for the autoinc value. This may be an annotation.

They should be merged with the case-converted names, so that you only have to
specify the unusual/non-standard ones.

## Default Column Values

When a new Entity object is created, it may not have some values needed by
the mapper; e.g. single-table inheritance types. Need to be able to specify
default row/record values, probably by annotation.

Alternatively, should we be able to specify a method other than `newRecord()`
for creating the backing record for the Entity? Then the special logic for
setting up the new record can be placed there.

## Identity Mapping

If we get (e.g.) two FooEntity objects from the same Record, then they can
change independently, and mapping back to the same Record means "last one wins".
Perhaps new() should return the same FooEntity for the same Record (or Row) each
time? That may mean that $storage should be on each Entity and Collection.

Or perhaps leave that to the Repository using the Transit? Well, the problem
there is that you might want to identiy-map the Entity objects themselves, not
merely Aggregates.
