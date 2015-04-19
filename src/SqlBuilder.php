<?php namespace Gjnorm;


class SqlBuilder {

    protected $sql;

    protected $table;

    public $material;

    protected $bindings = [];

    protected $selectSegments = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
    ];

    public function __construct($sqlMaterial=null, $helper=null)
    {
        $this->material = is_null($sqlMaterial) ? new SqlMaterial() : $sqlMaterial;
        $this->helper = is_null($helper) ? new SqlBuilderHelper($this->material->tablePrefix) : $helper;
    }

    public function buildInsert(array $values)
    {
        $firstValue = $values[0];
        $table = $this->buildTableName();
        if ( ! is_array($firstValue))
        {
            $values = array($values);
        }

        $columns = $this->helper->columnsToStr(array_keys($firstValue));
        $parameters = $this->parametersToStr($firstValue);
        $tempValues = array_fill(0, count($values), "({$parameters})");
        $parameters = implode(', ', $tempValues);

        $this->resetMaterial();

        return "INSERT INTO {$table} ({$columns}) values {$parameters}";
    }

    public function buildUpdate($values)
    {
        $from = $this->buildFrom();
        $columns = [];

        foreach ($values as $key=>$value)
        {
            $key = $this->helper->wrap($key);
            $value = $this->parameter($value);
            $columns[] = "{$key}={$value}";
        }

        $columns = implode(', ', $columns);
        if (isset($this->material->joins))
        {
            $joins = ''.$this->buildJoins();
        }
        else
        {
            $joins = '';
        }

        $wheres = $this->buildWheres();

        $sqlStr = "UPDATE {$from}{$joins} SET {$columns} {$wheres}";

        if (isset($this->material->orders))
        {
            $sqlStr = ' '.$this->buildOrderBy();
        }
        if (isset($this->material->limit))
        {
            $sqlStr = ' '.$this->buildLimit();
        }

        $this->resetMaterial();

        return rtrim($sqlStr);
    }

    public function buildDelete()
    {
        $table = $this->buildTableName();
        $from = $this->buildFrom();
        $where = $this->buildWheres();
        if (isset($this->material->joins))
        {
            $joins = ' '.$this->buildJoins();

            return rtrim("DELETE {$table} FROM {$table}{$joins} {$where}");
        }

        $this->resetMaterial();

        return rtrim("DELETE {$from} {$where}");
    }

    public function buildSelect()
    {
        $selectSql = $this->buildSelectSub($this->material);

        $this->resetMaterial();

        return $selectSql;
    }

    public function buildSelectSub(SqlMaterial $material)
    {
        if (is_null($material->columns)) $material->columns = ['*'];

        $sqlArray = [];
        foreach ($this->selectSegments as $segment)
        {
            if ( ! is_null($material->$segment))
            {
                $method = 'build'.ucfirst($segment);
                $sqlArray[$segment] = $this->$method($material, $material->$segment);
            }
        }

        $sqlStr = trim($this->helper->concatenate($sqlArray));
        if ($material->unions)
        {
            $unions = $this->buildUnions();
            $sqlStr = "({$sqlStr}) {$unions}";
        }

        return $sqlStr;
    }

    public function buildOrderBy()
    {
        $orders = array_map(
            function($order)
            {
                return $this->helper->wrap($order['columns']).' '.$order['direction'];
            },
            $this->material->orders
        );
        $orders = implode(',', $orders);

        return "ORDER BY {$orders}";
    }

    public function buildGroupBy()
    {
        $groups = $this->helper->columnsToStr($this->material->groups);

        return "GROUP BY {$groups}";
    }

    public function buildTableName()
    {
        return $this->helper->warpTable($this->material->tableName);
    }

    public function buildFrom()
    {
        $tableName = $this->buildTableName();

        return "FROM {$tableName}";
    }

    public function buildLimit()
    {
        $limit = intval($this->material->limit);

        return "LIMIT {$limit}";
    }

    protected function buildHavingBasic($having)
    {
        $column = $this->helper->wrap($having['column']);
        $this->addBindings($having['value']);
        $parameter = $this->parameter($having['value']);

        return "{$having['boolean']} {$column} {$having['operaion']} {$parameter}";
    }

    protected function buildHavingSql($having)
    {
        return "{$having['logic']} {$having['sql']}";
    }

    public function buildHavings()
    {
        $sqlArray = [];
        foreach ($this->material->havings as $having)
        {
            $type = ucfirst($having['type']);
            $method = "buildHaving{$type}";
            $sqlArray[] = $this->$method($having);
        }

        $sqlStr = implode(' ', $sqlArray);
        $sqlStr = $this->helper->removeLeadingLogic($sqlStr);

        return "HAVING {$sqlStr}";
    }

    public function buildColumns()
    {
        if ( ! is_null($this->material->aggregate)) return;

        $select = $this->material->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $columnsStr = $this->helper->columnsToStr($this->material->columns);

        return "{$select}{$columnsStr}";
    }

    public function buildUnions()
    {
        $sql = '';

        foreach ($this->material->unions as $union)
        {
            $sql .= $this->buildUnion($union);
        }

        return ltrim($sql);
    }

    public function buildUnion(array $union)
    {
        $keyword = $union['all'] ? 'UNION ALL ' : 'UNION ';
        $unionsStr = $this->buildSelectSub($union['material']);

        return "{$keyword}({$unionsStr})";
    }

    public function buildOffset()
    {
        $offset = intval($this->material->offset);

        return "OFFSET {$offset}";
    }

    public function buildJoins()
    {
        $sqlArray = [];
        foreach ($this->material->joins as $join)
        {
            $table = $this->helper->wrapTable($join['table']);

            $clausesSqlArray = [];
            foreach ($join['clause'] as $clause)
            {
                $clausesSqlArray[] = $this->buildJoinClause($clause);
            }

            $clausesSqlArray[0] = $this->helper->removeLeadingLogic($clausesSqlArray[0]);
            $clausesSqlStr = implode(',', $clausesSqlArray);
            $joinType = $clause['type'];

            $sqlArray[] = "{$joinType} join {$table} on {$clausesSqlStr}";
        }

        return implode(' ', $sqlArray);
    }

    protected function buildJoinClause(array $clause)
    {
        if ($this->helper->isExpression($clause['firstOperand']))
        {
            $expression = $clause['firstOperand']->getValue();
            $logic = $clause['operator'];

            return "{$logic} {$expression}";
        }

        $firstOperand = $this->helper->wrap($clause['firstOperand']);
        if ($clause['isParameter'])
        {
            $this->addBindings($clause['secondOperand']);
            $secondOperand = $this->parameter($clause['secondOperand']);
        }
        else
        {
            $secondOperand = $this->helper->wrap($clause['secondOperand']);
        }

        return "{$clause['logic']} {$firstOperand} {$clause['operator']} {$secondOperand}";
    }

    public function buildAggregate()
    {
        $column = $this->helper->columnsToStr($this->material->aggregate['columns']);

        if ($this->material->distinct and $column !== '*')
        {
            $column = 'DISTINCT '.$column;
        }

        $this->resetMaterial();

        return 'SELECT '.$this->material->aggregate['function']."({$column}) as aggregate";
    }

    public function buildTruncate()
    {
        return 'TRUNCATE TABLE '.$this->helper->warpTable($this->material->tableName);
    }

    protected function buildWhereBasic($where)
    {
        $value = $this->paramter($where['value']);

        return $this->helper->wrap($where['column'])." {$where['operator']} {$value}";
    }

    protected function buildWhereBetween($where)
    {
        $between = $where['not'] ? 'NOT BETWEEN' : 'BETWEEN';
        $valueOne = $this->parameter($where['valueOne']);
        $valueTwo = $this->parameter($where['valueTwo']);

        return $this->helper->wrap($where['column'])." {$between} {$valueOne} AND {$valueTwo}";
    }

    protected function buildWhereExists($where)
    {
        $selectSub = $this->buildSelectSub($where['material']);
        $exists = $where['not'] ? 'NOT EXISTS' : 'EXISTS';

        return "{$exists} ({$selectSub})";
    }

    protected function buildWhereIn($where)
    {
        $values = $this->parametersToStr($where['values']);
        $in = $where['not'] ? 'NOT IN' : 'IN';

        return $this->helper->wrap($where['column'])." {$in} ({$values})";
    }

    protected function buildWhereInSub($where)
    {
        $select = $this->buildSelectSub($where['material']);
        $in = $where['not'] ? 'NOT IN' : 'IN';

        return $this->helper->wrap($where['column'])." {$in} ({$select})";
    }

    protected function buildWhereNull($where)
    {
        $null = $where['not'] ? 'NOT NULL' : 'NULL';

        return $this->helper->wrap($where['column'])." IS {$null}";
    }

    protected function buildWhereDay($where)
    {
        return $this->buildWhereDate('DAY', $where);
    }

    protected function buildWhereMonth($where)
    {
        return $this->buildWhereDate('Month', $where);
    }

    protected function buildWhereYear($where)
    {
        return $this->buildWhereDate('YEAR', $where);
    }

    protected function buildWhereDate($type, $where)
    {
        $value = $this->parameter($where['value']);
        $column = $this->helper->wrap($where['column']);

        return "{$type}({$column}) {$where['operator']} {$value}";
    }

    protected function buildWhereSql($where)
    {
        return $where['sql'];
    }

    protected function buildWhereNested($where)
    {
        $whereSql = trim(substr($this->buildWheresWithParams($where['wheres']), 6));

        return "({$whereSql})";
    }

    protected function buildWhereSub($where)
    {
        $select = $this->buildSelectSub($where['material']);
        $column = $this->helper->wrap($where['column']);

        return "{$column} {$where['operator']} ({$select})";

    }

    public function buildWheres()
    {
        return $this->buildWheresWithParams($this->material->wheres);
    }

    public function buildWheresWithParams($wheres)
    {
        $sqlArray = [];
        if (is_null($wheres)) return '';

        foreach ($wheres as $where)
        {
            $method = "buildWhere{$where['type']}";
            $whereStr = $this->$method($where);
            $sql[] = "{$where['logic']} {$whereStr}";
        }

        if (count($sqlArray) > 0)
        {
            $sqlStr = implode(' ', $sqlArray);
            $sqlStr = $this->helper->removeLeadingLogic($sqlStr);

            return "WHERE {$sqlStr}";
        }

        return '';
    }

    protected function addBindings($values)
    {
        if (is_array($values))
        {
            $this->bindings = array_merge($this->bindings, $values);
        }
        else
        {
            $this->bindings[] = $values;
        }
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    public function parameter($value)
    {
        if ($this->isExpression($value))
        {
            return  $value->getValue();
        }

        $this->addBindings($value);

        return  '?';
    }

    public function parametersToStr($values)
    {
        return implode(', ', array_map(array($this, 'parameter'), $values));
    }

    public function resetMaterial()
    {
        $this->material->reset();
    }

} 