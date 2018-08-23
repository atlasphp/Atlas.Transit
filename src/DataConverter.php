<?php
namespace Atlas\Transit\DataConverter;

abstract class DataConverter
{
    public function fromRecordToEntity(array &$values) : void
    {
    }

    public function fromEntityToRecord($entity, array &$values) : void
    {
    }
}
