<?php


namespace Driver\SQL\Condition;


use Driver\SQL\Query\Select;

class Exists extends Condition
{
    private Select $subQuery;

    public function __construct(Select $subQuery)
    {
        $this->subQuery = $subQuery;
    }

    public function getSubQuery(): Select
    {
        return $this->subQuery;
    }
}