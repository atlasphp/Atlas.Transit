<?php
declare(strict_types=1);

namespace Atlas\Transit\DataSource\FakeAddress;

use Atlas\Table\Row;

class FakeAddressRow extends Row
{
    protected $cols = [
        'fake_address_id' => null,
        'fake_id' => null,
        'street' => null,
        'city' => null,
        'region' => null,
        'postcode' => null,
    ];
}
