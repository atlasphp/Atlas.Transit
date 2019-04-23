# Todo Items

## Reflection

Consider putting all reflections for all Domain classes into a single object.

Then for Handlers, pass the reflected information into them, instead of having
them do their own reflection.

## Casing

Allow for "same case" on both sides. Optimization would be a "null inflector"
that does nothing at all, just returns the strings.

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

## Vocabulary Notes

We think most broadly in terms of the domain (aggregate, entity, collection,
value object) and the source (mapper, record, recordset).

Domain objects have properties, parameters, and arguments; source objects
have fields. Or perhaps we talk in terms of "elements" ?

Want to keep away from the word "value" internally because it can be conflated
with Value Object; use $data for arrays and $datum for elements.
