<?php
declare(strict_types=1);

namespace Atlas\Transit;

class FakeTransit extends Transit
{
    public function getHandlerLocator()
    {
        return $this->handlerLocator;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    public function getPlan()
    {
        return $this->plan;
    }
}
