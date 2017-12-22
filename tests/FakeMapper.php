<?php
namespace Atlas\Transit;

use Atlas\Orm\Mapper\MapperEventsInterface;
use Atlas\Orm\Mapper\MapperInterface;
use Atlas\Orm\Mapper\MapperSelect;
use Atlas\Orm\Mapper\Record;
use Atlas\Orm\Mapper\RecordInterface;
use Atlas\Orm\Mapper\RecordSet;
use Atlas\Orm\Mapper\RecordSetInterface;
use Atlas\Orm\Mapper\Related;
use Atlas\Orm\Relationship\RelationshipInterface;
use Atlas\Orm\Relationship\Relationships;
use Atlas\Orm\Table\Row;
use Atlas\Orm\Table\RowInterface;
use Atlas\Orm\Table\TableInterface;
use SplObjectStorage;

class FakeMapper implements MapperInterface
{
    protected $table;

    public function __construct(
        TableInterface $table,
        Relationships $relationships,
        MapperEventsInterface $events
    ) {
        $this->table = $table;
    }

    static public function getTableClass() : string
    {
        static $tableClass;
        if (! $tableClass) {
            $tableClass = substr(get_called_class(), 0, -6) . 'Table';
        }
        return $tableClass;
    }

    public function getTable() : TableInterface
    {
        return $this->table;
    }

    public function fetchRecord($primaryVal, array $with = []) : ?RecordInterface {}

    public function fetchRecordBy(array $whereEquals, array $with = []) : ?RecordInterface {}

    public function fetchRecords(array $primaryVals, array $with = []) : array {}

    public function fetchRecordsBy(array $whereEquals, array $with = []) : array {}

    public function fetchRecordSet(array $primaryVals, array $with = []) : RecordSetInterface {}

    public function fetchRecordSetBy(array $whereEquals, array $with = []) : RecordSetInterface {}

    public function select(array $whereEquals = []) : MapperSelect {}

    public function insert(RecordInterface $record) : bool {}

    public function update(RecordInterface $record) : bool {}

    public function delete(RecordInterface $record) : bool {}

    public function persist(RecordInterface $record, SplObjectStorage $tracker = null) : bool
    {
        return true;
    }

    public function newRecord(array $cols = []) : RecordInterface
    {
        $row = new Row($cols);
        $related = new Related([]);
        return new Record(get_class($this), $row, $related);
    }

    public function newRecordSet(array $records = []) : RecordSetInterface
    {
        return new RecordSet($records, function ($records) {
            return $this->newRecord($rowData);
        });
    }

    public function turnRowIntoRecord(RowInterface $row, array $with = []) : RecordInterface {}

    public function turnRowsIntoRecords(array $rows, array $with = []) : array {}
}
