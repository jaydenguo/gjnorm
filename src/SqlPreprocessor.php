<?php namespace Gjnorm;


class SqlPreprocessor {

    protected $material;

    public function __construct($material=null)
    {
        $this->material = is_null($material) ? new SqlMaterial() : $material;
    }

    public function getMaterial()
    {
        return $this->material;
    }

    public function setMaterial(SqlMaterial $material)
    {
        $this->material = $material;
    }

    public function processTableName($tableName)
    {
        $this->material->tableName = trim($tableName);
    }

    public function processGroupBy($groups)
    {
        if (is_string($groups))
        {
            $groups = implode(',', $groups);
            $groups = array_map(function($value)
            {
                return trim($value);
            }, $groups);
        }

        $this->material->groups = array_merge($this->material->groups, $groups);
    }

    public function processHaving($firstOperand, $operator, $secondOperand, $logic)
    {
        $type = 'basic';

        $this->material->havings[] = compact('type', 'firstOperand', 'operator', 'secondOperand', 'logic');
    }

    public function processHavingSql($sql, $logic)
    {
        $type = 'sql';

        $this->material->havings[] = compact('type', 'sql', 'logic');
    }

    public function processLimit($limit)
    {
        $this->material->limit = $limit;
    }

    public function processOffset($offset)
    {
        $this->material->offset = $offset;
    }

    public function processOrderBy($orders)
    {
        if (is_string($orders))
        {
            $orders = implode(',', $orders);
            $orders = array_map(function($value)
            {
                return trim($value);
            }, $orders);
        }

        $this->material->orders = array_merge($this->material->$orders, $orders);
    }

    public function processUnion(SqlMaterial $material, $all)
    {
        $union = [];
        $union['all'] = $all;
        $union['material'] = $material;
        $this->material->unions[] = $union;
    }

    public function processDistinct($column)
    {
        $this->material->distinct = true;
        $this->processColumns($column);
    }

    public function processJoin($table, $firstOperand, $operator, $secondOperand, $logic, $type)
    {
        if ( ! empty($firstOperand) and empty($operator))
        {
            $join['table'] = $table;
            $join['type'] = $firstOperand;
            $join['clause'] = [];
            $this->material->joins[$table] = $join;
            return ;
        }

        $join['table'] = $table;
        $join['type'] = $type;
        $clause['firstOperand'] = $firstOperand;
        $clause['operator'] = $operator;
        $clause['secondOperand'] = $secondOperand;
        $clause['logic'] = $logic;
        $join['clause'] = $clause;
        $this->material->joins[$table] = $join;
    }

    public function processJoinClause($firstOperand, $operator, $secondOperand='', $logic='AND')
    {
        $tableName = end(array_keys($this->material->joins));
        if ($firstOperand instanceof Expression)
        {
            $clause['firstOperand'] = $firstOperand;
            $clause['logic'] = $logic;
            $this->material->joins[$tableName]['clause'][] = $clause;
            return ;
        }

        $clause['firstOperand'] = $firstOperand;
        $clause['operator'] = $operator;
        $clause['secondOperand'] = $secondOperand;
        $clause['logic'] = $logic;

        $this->material->joins[$tableName]['clause'][] = $clause;
    }

    public function processColumns($columns)
    {
        $this->material->columns = $columns;
    }

    public function filterAttributes($attributes, $columns)
    {
        $cols = key($attributes);
        $cols = $this->filterColumns($cols, $columns);
        $attributes = array_get_some($attributes, $cols);

        return $attributes;
    }

    public function filterColumns($cols, $columns)
    {
        return array_filter($cols, function($value) use ($columns)
        {
            if ($columns === ['*'])
            {
                return true;
            }

            return in_array($value, $columns);
        });
    }

    public function processWhereBasic($column, $operator, $value, $logic)
    {
        $where['type'] = 'Basic';
        $where['column'] = $column;
        $where['operator'] = $operator;
        $where['value'] = $value;
        $where['logic'] = $logic;

        $this->material->wheres[] = $where;
    }

    public function processWhereNested($wheres, $logic)
    {
        $where['type'] = 'Nested';
        $where['wheres'] = $wheres;
        $where['logic'] = $logic;

        $this->material->wheres[] = $where;
    }

    public function processWhereSql($sql, $logic)
    {
        $where['type'] = 'Sql';
        $where['logic'] = $logic;
        $where['sql'] = $sql;

        $this->material->wheres[] = $where;
    }

    public function processWhereBetween($column, $valueOne, $valueTwo, $logic, $not)
    {
        $where['type'] = 'Between';
        $where['not'] = $not;
        $where['logic'] = $logic;
        $where['column'] = $column;
        $where['valueOne'] = $valueOne;
        $where['valueTwo'] = $valueTwo;

        $this->material->wheres[] = $where;
    }

    public function processWhereIn($column, $values, $logic, $not)
    {
        $where['type'] = 'In';
        $where['not'] = $not;
        $where['logic'] = $logic;
        $where['column'] = $column;
        $where['values'] = $values;

        $this->material->wheres[] = $where;
    }

    public function processWhereInSub($column, $material, $logic, $not)
    {
        $where['type'] = 'InSub';
        $where['not'] = $not;
        $where['logic'] = $logic;
        $where['column'] = $column;
        $where['material'] = $material;

        $this->material->wheres[] = $where;
    }

    public function processWhereExists($material, $logic, $not)
    {
        $where['type'] = 'Exists';
        $where['logic'] = $logic;
        $where['not'] = $not;
        $where['material'] = $material;

        $this->material->wheres[] = $where;
    }

    public function processWhereNull($column, $logic, $not)
    {
        $where['type'] = 'Null';
        $where['not'] = $not;
        $where['logic'] = $logic;
        $where['column'] = $column;

        $this->material->wheres[] = $where;
    }

    public function processWhereDate($type, $column, $operator, $value, $logic)
    {
        $where['type'] = $type;
        $where['column'] = $column;
        $where['operator'] = $operator;
        $where['value'] = $value;
        $where['logic'] = $logic;

        $this->material->wheres[] = $where;
    }

    public function processAggregate($function, $columns)
    {
        $aggregate['function'] = $function;
        $aggregate['columns'] = $columns;

        $this->material->aggregate = $aggregate;
    }
} 