# Factory

Allow specification of factories on handlers.

```
$transit->mapEntity(SpecialEntity::CLASS, SpecialMapper::CLASS)
    ->factory([SpecialEntityFactory::CLASS, 'newFromRecord']);

class SpecialEntityFactory
{
    public function newFromRecord($specialRecord)
    {
        return new SpecialEntity(...);
    }
}
```

# Bounded Context

Consider advising one Transit per Bounded Context. Each Bounded Context has its
own entities and aggregates and values. They can all use the same Atlas, though.

# "Through" Mappings

Perhaps need ...

    $transit->through($throughFieldName, $foreignFieldName)

... to specify association mapping tables? E.g.:

    $transit->mapEntity(ThreadEntity::CLASS)
        ->setDomainFromRecord([
            'tagsPropertyName' => 'tags'
        ])
        ->setRecordFromDomain([
            'tags' => $transit->mapThrough('tagsPropertyName', 'taggingsFieldName')
        ]);

Or should that be something Mapper::persist() does with "through" associations?

# Identity Mapping

If we get (e.g.) two FooEntity objects from the same Record, then they can change
independently, and mapping back to the same Record means "last one wins".
Perhaps new() should return the same FooEntity for the same Record (or Row) each time?
That may mean that $storage should be on each Entity and Collection.

Or perhaps leave that to the Repository using the Transit ?

# Value Objects

* * *

Given a record with fields ...

    foo_id
    datetime
    timezone
    email
    street
    city
    state
    zip
    json_obj -- serialized json
    rel_value -- related value record

... and a domain object with params/props:

    int $fooId
    DateTime $dtz // except PHP DateTimeImmutable is just ... trouble.
    Email $email
    Address $address
    JsonObject $jsonObj
    RelatedValue $relValue

* * *

4 types of VOs:

- Serialized (SVO) as a single scalar Record field (serialValue)
- Inline (IVO) as one or more scalar Record fields (inlineValue)
- Entity (EVO) as a entire Record (entityValue)

An SVO is straightforward: serialize and unserialize from a single field.

    $transit->mapSerializedValue(ValueObject::CLASS, Mapper::CLASS)
        ->setDomainFromSource([
            'domainProp' => 'recordField',
        ])
        ->setRecordFromDomain([
            'recordField' => 'domainProp',
        ])
        ->toDomainWith('json_decode')
        ->toRecordWith('json_encode');

    (The obvious alternative here is PHP serialize/unserialize, to store PHP
    objects themselves.)

    // or maybe:

    $transit->mapEntity(FooEntity::CLASS, FooMapper::CLASS, [
        'address' => $transit->inlineValue([
            // if all match, no need to map, and indeed no need to
            // specify inlineValue. but if not, domain => record
            'street' => 'addrLine1',
            'city' => 'city',
            'state' => 'region',
            'zip' => 'postcode'
        ]),
    ]);

    // to define default col mappings regardless of the record it's on:
    $transit->mapInlineValue(AddressValue::CLASS, [
        'street' => 'addrLine1',
        'city' => 'city',
        'state' => 'region',
        'zip' => 'postcode'
    ]);

    // to define a PHP serialized value:
    $transit->mapSerialValue(AnyValue::CLASS, [
        'domainProp' => 'record_field'
    ]);

    ...

An inline VO of a single field is the next easiest thing, because you know exactly
what the field name is and what the property name is.

An inline VO must be able to created from the Record it's embedded in, including all
dependent VOs needed to create the EVO. When creating, the Record itself is passed
to the VO creation system, and is reused for all dependent VOs.

An entity VO is essentially the same as an Entity, but as part of creation, the identity
value is reflection-injected into the matching property (instead of being presumed
to be passed into a constructor param). A significant problem here is that the
domain object being created may *not* be the one that will be passed back into
Transit for store/discard; VOs are immutable, so origin VO will not necessarily
be the one being stored/discarded, even though it has the same identity. That
means we'll need to track the identity value, rather than the Entity object
itself.

Really, I wonder if "unknown objects" should be presumed to be Value Objects?
But then you can't necessarily map from alternative record fields to value properties.

* * *

// so here's the next problem: how to "fix" values coming out of the
// database? what if the stored value is not the PHP constant? Or maybe
// set up your own datetime class in the domain that takes the persistence values.

$transit->mapEntity(FooEntity::CLASS, FooMapper::CLASS)
  ->setDomainFromSource([
    'dtz' => $transit->value([])
  ]);

$transit->mapValue(DateTimeZone::CLASS, FooMapper::CLASS);

$transit->mapValue(DateTime::CLASS, FooMapper::CLASS)
    ->setDomainFromSource(['time' => 'datetime'])
    ->setRecordFromDomain(['datetime' => 'time']);

// now: how do we extract the Value object primitives?
// tough with DateTime.
$transit->mapEntity(FooEntity::CLASS, FooMapper::CLASS)
    ->setDomainFromSource([
        'time' => 'datetime',
        'object' => 'timezone'
    ])
    ->setRecordFromDomain([
        'datetime' => $transit->call('format', 'Y-m-d H:i:s'),
        'timezone' => )
        'dtz' => function ($record) {
            return new DateTime(
                $record->datetime,
                new DateTimeZone($record->timezone)
            );
        },
    ])
    ->setRecordFromDomain([
        'datetime' => function ($foo) {
            return $foo->getDtz()->format('Y-m-d H:i:s'),
        },
        'timezone' => function ($domain) {
            return $foo->getDtz()->format('e'),
        }
    ]);

* * *

```
class Money
{
    private $amount;
    private $currency;

    public function __construct($amount, Currency $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }
}

class Currency
{
    public function __construct($type) // USD, GBP, BTC
    {
        $this->type = $type;
    }
}

class ProductEntity
{
    public function __construct(
        int $productId,
        string $name,
        Money $price
    ) {
        $this->productId = $productId;
        $this->name = $name;
        $this->price = $price;
    }
}

/*
"embedded value" might use a prefix on params: price_amount, price_currency ?
but even then it's tough with VO-in-VO constructs.
*/
```

* * *

With jsonValueObject and phpValueObject, does the unserialized value get passed
as a *param* to the Domain type, or is it itself the domain type, and not constructed?

Let's say this: if the unserialized value is the same type as the Domain element,
it is used directly; otherwise, it is used as the only param when constructing
that Domain element. (But how to re-serialize the value back to the Record? :-/)

Alternatively, skip serialized values for now. They'll work via closure only.

Or allow only phpValueObject() as the direct property -- easiest, most straightforward.

For Domain types that need to unserialize values, perhaps you should do that
unserializing in the constructor?

    class Json
    {
        public function __construct($string)
        {
            $this->object = json_decode($string);
        }

        public function __get($key)
        {
            return $this->object->$key;
        }

        public function __set($key, $val)
        {
            $this->object->$key = $val;
        }

        public function __isset($key)
        {
            return isset($this->object->$key);
        }

        public function __isset($key)
        {
            unset($this->object->$key);
        }

        public function encode()
        {
            return json_encode($this->object);
        }
    }

But then you need to automatically serialize it on the way to the DB.


* * *

PHP DateTime and DateTimeZone objects are just a little bit clunky for
named-parameter construction (which is something the authors probably never
envisioned or intended).

    DateTime($time, DateTimeZone $object)
    DateTimeZone($timezone)

Advise building your own extension with more reasonable names?

Even here, though, you are likely to have multiple DateTime fields in a
Record and therefore an entity, and across tables ...

    created_at
    created_at_zone
    updated_at
    updated_at_zone
    publish_at
    publish_at_zone

... so named parameters are tricky even in simple cases for DT VOs. And it's not
enough to specify the params sequentially, because then you can't map back to
the source from the domain.

* * *

```
Class [ <internal:date> class DateTimeImmutable implements DateTimeInterface ] {

  - Constants [0] {
  }

  - Static properties [0] {
  }

  - Static methods [4] {
    Method [ <internal:date> static public method __set_state ] {
    }

    Method [ <internal:date> static public method createFromFormat ] {

      - Parameters [3] {
        Parameter #0 [ <required> $format ]
        Parameter #1 [ <required> $time ]
        Parameter #2 [ <optional> $object ]
      }
    }

    Method [ <internal:date> static public method getLastErrors ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date> static public method createFromMutable ] {

      - Parameters [1] {
        Parameter #0 [ <required> $DateTime ]
      }
    }
  }

  - Properties [0] {
  }

  - Methods [15] {
    Method [ <internal:date, ctor> public method __construct ] {

      - Parameters [2] {
        Parameter #0 [ <optional> $time ]
        Parameter #1 [ <optional> $object ]
      }
    }

    Method [ <internal:date, prototype DateTimeInterface> public method __wakeup ] {
    }

    Method [ <internal:date, prototype DateTimeInterface> public method format ] {

      - Parameters [1] {
        Parameter #0 [ <required> $format ]
      }
    }

    Method [ <internal:date, prototype DateTimeInterface> public method getTimezone ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date, prototype DateTimeInterface> public method getOffset ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date, prototype DateTimeInterface> public method getTimestamp ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date, prototype DateTimeInterface> public method diff ] {

      - Parameters [2] {
        Parameter #0 [ <required> $object ]
        Parameter #1 [ <optional> $absolute ]
      }
    }

    Method [ <internal:date> public method modify ] {

      - Parameters [1] {
        Parameter #0 [ <required> $modify ]
      }
    }

    Method [ <internal:date> public method add ] {

      - Parameters [1] {
        Parameter #0 [ <required> $interval ]
      }
    }

    Method [ <internal:date> public method sub ] {

      - Parameters [1] {
        Parameter #0 [ <required> $interval ]
      }
    }

    Method [ <internal:date> public method setTimezone ] {

      - Parameters [1] {
        Parameter #0 [ <required> $timezone ]
      }
    }

    Method [ <internal:date> public method setTime ] {

      - Parameters [4] {
        Parameter #0 [ <required> $hour ]
        Parameter #1 [ <required> $minute ]
        Parameter #2 [ <optional> $second ]
        Parameter #3 [ <optional> $microseconds ]
      }
    }

    Method [ <internal:date> public method setDate ] {

      - Parameters [3] {
        Parameter #0 [ <required> $year ]
        Parameter #1 [ <required> $month ]
        Parameter #2 [ <required> $day ]
      }
    }

    Method [ <internal:date> public method setISODate ] {

      - Parameters [3] {
        Parameter #0 [ <required> $year ]
        Parameter #1 [ <required> $week ]
        Parameter #2 [ <optional> $day ]
      }
    }

    Method [ <internal:date> public method setTimestamp ] {

      - Parameters [1] {
        Parameter #0 [ <required> $unixtimestamp ]
      }
    }
  }
}
Class [ <internal:date> class DateTimeZone ] {

  - Constants [14] {
    Constant [ public integer AFRICA ] { 1 }
    Constant [ public integer AMERICA ] { 2 }
    Constant [ public integer ANTARCTICA ] { 4 }
    Constant [ public integer ARCTIC ] { 8 }
    Constant [ public integer ASIA ] { 16 }
    Constant [ public integer ATLANTIC ] { 32 }
    Constant [ public integer AUSTRALIA ] { 64 }
    Constant [ public integer EUROPE ] { 128 }
    Constant [ public integer INDIAN ] { 256 }
    Constant [ public integer PACIFIC ] { 512 }
    Constant [ public integer UTC ] { 1024 }
    Constant [ public integer ALL ] { 2047 }
    Constant [ public integer ALL_WITH_BC ] { 4095 }
    Constant [ public integer PER_COUNTRY ] { 4096 }
  }

  - Static properties [0] {
  }

  - Static methods [3] {
    Method [ <internal:date> static public method __set_state ] {
    }

    Method [ <internal:date> static public method listAbbreviations ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date> static public method listIdentifiers ] {

      - Parameters [2] {
        Parameter #0 [ <optional> $what ]
        Parameter #1 [ <optional> $country ]
      }
    }
  }

  - Properties [0] {
  }

  - Methods [6] {
    Method [ <internal:date, ctor> public method __construct ] {

      - Parameters [1] {
        Parameter #0 [ <required> $timezone ]
      }
    }

    Method [ <internal:date> public method __wakeup ] {
    }

    Method [ <internal:date> public method getName ] {

      - Parameters [0] {
      }
    }

    Method [ <internal:date> public method getOffset ] {

      - Parameters [1] {
        Parameter #0 [ <required> $object ]
      }
    }

    Method [ <internal:date> public method getTransitions ] {

      - Parameters [2] {
        Parameter #0 [ <optional> $timestamp_begin ]
        Parameter #1 [ <optional> $timestamp_end ]
      }
    }

    Method [ <internal:date> public method getLocation ] {

      - Parameters [0] {
      }
    }
  }
}
```

