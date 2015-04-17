<?php namespace Gjnorm;


class SqlMaterial {

    public $tablePrefix;
    public $tableName;
    public $orders;
    public $groups;
    public $columns;
    public $wheres = [];
    public $aggregate;
    public $distinct = false;
    public $joins = [];
    public $from;
    public $havings;
    public $offset;
    public $unions;

    public function reset()
    {
        $this->orders = null;
        $this->$groups = null;
        $this->$columns = null;
        $this->$wheres = [];
        $this->$aggregate = null;
        $this->$distinct = false;
        $this->$joins = [];
        $this->$from = null;
        $this->$havings = null;
        $this->$offset = null;
        $this->$unions = null;
    }
} 