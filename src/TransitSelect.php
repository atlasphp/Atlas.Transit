<?php
namespace Atlas\Transit;

use Atlas\Orm\Mapper\MapperSelect;
use Atlas\Transit\Handler\Handler;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\SubselectInterface;

/**
 *
 * A Select object for Transit queries.
 *
 * @package atlas/transit
 *
 * @method array fetchRecord() Fetches a Record from the underlying Mapper.
 * @method array fetchRecords() Fetches an array of Records from the underlying Mapper.
 * @method array fetchRecordSet() Fetches a RecordSet from the underlying Mapper.
 *
 * @method array fetchAll() Fetches a sequential array of rows from the database; the rows are represented as associative arrays.
 * @method array fetchAssoc() Fetches an associative array of rows from the database; the rows are represented as associative arrays. The array of rows is keyed on the first column of each row.
 * @method array fetchCol() Fetches the first column of rows as a sequential array.
 * @method int fetchCount($col = '*') Given the existing SELECT, fetches a row count without any LIMIT or OFFSET.
 * @method array|null fetchOne() Fetches one row from the database as an associative array.
 * @method array fetchPairs() Fetches an associative array of rows as key-value pairs (first column is the key, second column is the value).
 * @method array RowInterface|null() fetchRow() Fetches a single Row object.
 * @method array fetchRows() Fetches an array of Row objects.
 * @method mixed fetchValue() Fetches the very first value (i.e., first column of the first row).
 * @method \Iterator yieldAll() Yields a sequential array of rows from the database; the rows are represented as associative arrays.
 * @method \Iterator yieldAssoc() Yields an associative array of rows from the database; the rows are represented as associative arrays. The array of rows is keyed on the first column of each row.
 * @method \Iterator yieldCol() Yields the first column of rows as a sequential array.
 * @method \Iterator yieldPairs() Yields an associative array of rows as key-value pairs (first column is the key, second column is the value).
 *
 * @method SelectInterface cols(array $cols) Adds columns to the query.
 * @method SelectInterface distinct($enable = true) Makes the select DISTINCT (or not).
 * @method SelectInterface forUpdate($enable = true) Makes the select FOR UPDATE (or not).
 * @method SelectInterface from(string $spec) Adds a FROM element to the query; quotes the table name automatically.
 * @method SelectInterface fromRaw(string $spec) Adds a raw unquoted FROM element to the query; useful for adding FROM elements that are functions.
 * @method SelectInterface fromSubSelect(string|\Aura\SqlQuery\Common\Select $spec, string $name) Adds an aliased sub-select to the query.
 * @method int getPaging() Gets the number of rows per page.
 * @method SelectInterface groupBy(array $spec) Adds grouping to the query.
 * @method SelectInterface having(string $cond) Adds a HAVING condition to the query by AND; if a value is passed as the second param, it will be quoted and replaced into the condition wherever a question-mark appears.
 * @method SelectInterface join(string $join, string $spec, string $cond = null) Adds a JOIN table and columns to the query.
 * @method SelectInterface joinSubSelect(string|\Aura\SqlQuery\Common\Select $join, string $spec, string $name, string $cond = null) Adds a JOIN to an aliased subselect and columns to the query.
 * @method SelectInterface orHaving(string $cond) Adds a HAVING condition to the query by AND; otherwise identical to `having()`.
 * @method SelectInterface page(int $page) Sets the limit and count by page number.
 * @method SelectInterface setPaging(int $paging) Sets the number of rows per page.
 * @method SelectInterface union() Takes the current select properties and retains them, then sets UNION for the next set of properties.
 * @method SelectInterface unionAll() Takes the current select properties and retains them, then sets UNION ALL for the next set of properties.
 */
class TransitSelect implements SubselectInterface
{
    /**
     *
     * A Transit to create and store domain objects.
     *
     * @var Transit
     *
     */
    protected $transit;

    /**
     *
     * The MapperSelect being decorated.
     *
     * @var MapperSelect
     *
     */
    protected $mapperSelect;

    /**
     *
     * The fetch method to user on the decorated Mapper.
     *
     * @var string
     *
     */
    protected $fetchMethod;

    /**
     *
     * The kind of domain class to return.
     *
     * @var string
     *
     */
    protected $domainClass;

    /**
     *
     * Constructor.
     *
     * @param Transit A Transit to create and store domain objects.
     *
     * @param MapperSelect $mapperSelect The MapperSelect being decorated.
     *
     * @param string $fetchMethod The fetch method to use on the underlying
     * MapperSelect.
     *
     * @param string $domainClass The kind of domain class to create/
     *
     */
    public function __construct(
        Transit $transit,
        MapperSelect $mapperSelect,
        string $fetchMethod,
        string $domainClass
    ) {
        $this->transit = $transit;
        $this->mapperSelect = $mapperSelect;
        $this->fetchMethod = $fetchMethod;
        $this->domainClass = $domainClass;
    }

    /**
     *
     * Decorates the underlying mapperSelect object's __toString() method so that
     * (string) casting works properly.
     *
     * @return string
     *
     */
    public function __toString() : string
    {
        return $this->mapperSelect->__toString();
    }

    /**
     *
     * Forwards method calls to the underlying mapperSelect object.
     *
     * @param string $method The call to the underlying mapperSelect object.
     *
     * @param array $params Params for the method call.
     *
     * @return mixed If the call returned the underlying mapperSelect object (a
     * fluent method call) return *this* object instead to emulate the fluency;
     * otherwise return the result as-is.
     *
     */
    public function __call(string $method, array $params)
    {
        $result = call_user_func_array([$this->mapperSelect, $method], $params);
        return ($result === $this->mapperSelect) ? $this : $result;
    }

    /**
     *
     * Clones objects used internally.
     *
     */
    public function __clone()
    {
        $this->mapperSelect = clone $this->mapperSelect;
    }

    /**
     *
     * Implements the SubSelect::getStatement() interface.
     *
     * @return string
     *
     */
    public function getStatement() : string
    {
        return $this->mapperSelect->getStatement();
    }

    /**
     *
     * Implements the SubSelect::getBindValues() interface.
     *
     * @return array
     *
     */
    public function getBindValues() : array
    {
        return $this->mapperSelect->getBindValues();
    }

    /**
     *
     * Returns a Record object from the Mapper.
     *
     * @return Domain object, or null.
     *
     */
    public function fetchDomain()
    {
        $method = $this->fetchMethod;
        $source = $this->$method();
        if (! $source) {
            return null;
        }
        return $this->transit->new($this->domainClass, $source);
    }
}
