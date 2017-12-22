<?php
namespace Atlas\Transit;

use Atlas\Orm\Table\AbstractTable;

/**
 * @inheritdoc
 */
class FakeTable extends AbstractTable
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getColNames()
    {
        return [
        ];
    }

    /**
     * @inheritdoc
     */
    public function getCols()
    {
        return [
        ];
    }

    /**
     * @inheritdoc
     */
    public function getPrimaryKey()
    {
        return [
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAutoinc()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getColDefaults()
    {
        return [
        ];
    }
}
