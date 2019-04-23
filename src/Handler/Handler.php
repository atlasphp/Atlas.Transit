<?php
declare(strict_types=1);

namespace Atlas\Transit\Handler;

use Atlas\Mapper\Mapper;
use Atlas\Transit\Exception;
use Atlas\Transit\Transit;
use Atlas\Transit\Reflection\Reflection;
use SplObjectStorage;

abstract class Handler
{
    protected $reflection;

    protected $handlerLocator;

    public function __construct(
        Reflection $reflection,
        HandlerLocator $handlerLocator
    ) {
        $this->reflection = $reflection;
        $this->handlerLocator = $handlerLocator;
    }
}
