<?php namespace Gjnorm;

use Gjnorm\Exception\ModelNotFoundException;
use PDO;
use PDOException;
use Gjnorm\Relation\BelongsTo;
use Gjnorm\Relation\BelongsToMany;
use Gjnorm\Relation\HasOne;
use Gjnorm\Relation\HasMany;

class Model{

    use EventTrait;
    use SoftDeleteTrait;

    public $pdo = null;

    protected $sql = '';

    protected $config = [];

    protected $prefix = '';

    protected $tableName = '';

    protected $builder = null;

    protected $createColumns = ['*'];

    protected $updateColumns = ['*'];

    protected $preprocessor;

    protected $timestamps = false;

    protected $material = null;

    protected $cacheMaterial = null;

    protected $primaryKeyName = 'id';

    protected $hiddenColumns = [];

    protected $lastSelectColumns = [];

    protected $attributes = [];

    protected $isHasAttribute = false;

    protected $fetchMode = PDO::FETCH_FUNC;

    protected $outputFormatRules = [];

    protected $inputFormatRules = [];

    protected $inputColumnMappingRules = [];

    protected $validationRules = [];

    protected $relations = [];

    protected $transactions = 0;

    protected $useSoftDelete = false;

    protected $softDeleteColumn = 'deleted_at';

    protected $perPage = 15;

    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
    );

    public function __construct($attributes=[], $material=null, $builder=null, $preprocessor=null, $config=[])
    {
        $this->setManyAttributes($attributes);
        $this->material = is_null($material) ? new SqlMaterial() : $material;
        $this->builder = is_null($builder) ? new SqlBuilder($this->material) : $builder;
        $this->preprocessor = is_null($preprocessor) ? new SqlPreprocessor($this->material) : $preprocessor;
        if (empty($config))
        {
            $this->config = include('../config/database.php');
        }
        $this->config = $config;
        if (empty($this->prefix))
        {
            $this->prefix = $this->getConfig('prefix');
        }
        if ($this->timestamps)
        {
            array_merge(
                $this->outputFormatRules,
                [
                    'created_at'=>[$this,'getCreatedAt'],
                    'updated_at'=>[$this, 'getUpdatedAt']
                ]
            );
        }
        $this->tableName = $this->getTableName();
        $this->init();
    }

    public function init()
    {
        $this->connect();
        $this->initConfig();
    }

    public function connect()
    {
        $dbHost = $this->getConfig('host');
        $dbName = $this->getConfig('name');
        $dbUser = $this->getConfig('user');
        $dbPassword = $this->getConfig('password');
        $dbPort = $this->getConfig('port');
        try
        {
            $dsn = 'mysql:dbhost='.$dbHost.';dbname='.$dbName.';dbport='.$dbPort;
            $this->pdo = new PDO($dsn, $dbUser, $dbPassword);
        }
        catch (PDOException $e)
        {
            throw $e;
        }
    }

    protected function initConfig(){
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
    }

    public function getPdo(){
        return $this->pdo;
    }

    protected function query($sql)
    {
        return $this->pdo->query($sql);
    }

    public function getCreatedAt($value)
    {
        return date('Y-m-d H:i:s', $value);
    }

    public function getUpdatedAt($value)
    {
        return date('Y-m-d H:i:s', $value);
    }

    public function __call($method, $parameters)
    {
        if (array_key_exists($method, $this->relations))
        {
            return $this->relations[$method];
        }
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes))
        {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations))
        {
            return $this->relations[$key]->getRelated();
        }
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function setManyAttributes(array $attributes)
    {
        foreach ($attributes as $key=>$value)
        {
            $this->setAttribute($key, $value);
        }
    }

    public function create(array $attributes=[])
    {
        if ($this->beforeCreate() === false)
        {
            return false;
        }

        if ( ! is_array($attributes[0]))
        {
            if ($this->timestamps)
            {
                $attributes['created_at'] = time();
            }
            $attributes = $this->preprocessor->filterAttributes($attributes, $this->createColumns);
            $attributes = $this->mapColumns($attributes);
            $this->setManyAttributes($attributes);
            $attributes = $this->formatAttributes($this->attributes);
            $this->setManyAttributes($attributes);
        }
        else
        {
            foreach ($attributes as $key=>$value)
            {
                if ($this->timestamps)
                {
                    $value['created_at'] = time();
                }

                $value = $this->preprocessor->filterAttributes($value, $this->createColumns);
                $attributes[$key] = $this->mapColumns($value);
                $attributes[$key] = $this->formatAttributes($attributes[$key]);
            }
        }

        if (count($attributes) > 0)
        {
            $sql = $this->builder->buildCreate($attributes);
            $bindings = $this->builder->getBindings();
            $statement = $this->pdo->prepare($sql)->execute($bindings);
            $this->afterCreate();

            return $statement->rowCount();
        }

        return false;
    }

    public function increment($column, $step=1)
    {
        $column = [$column=>"{$column} + {$step}"];

        return $this->update($column);
    }

    public function decrement($column, $step=1)
    {
        $column = [$column=>"{$column} - {$step}"];

        return $this->update($column);
    }

    public function update(array $attributes=[])
    {
        if ($this->beforeUpdate() === false)
        {
            return false;
        }
        if ($this->timestamps)
        {
            $attributes['updated_at'] = time();
        }
        $attributes = $this->preprocessor->filterAttributes($attributes, $this->updateColumns);
        $attributes = $this->mapColumns($attributes);
        $this->setManyAttributes($attributes);
        $attributes = $this->formatAttributes($this->attributes);
        $this->setManyAttributes($attributes);

        if (count($attributes) > 0)
        {
            $sql = $this->builder->buildUpdate($attributes);
            $bindings = $this->builder->getBindings();
            $statement = $this->pdo->prepare($sql)->execute($bindings);
            $this->afterUpdate();

            return $statement->rowCount();
        }

        return false;
    }

    protected function formatAttributes(array $attributes)
    {
        $newAttributes = [];

        foreach ($attributes as $key=>$value)
        {
            if (array_key_exists($key, $this->inputFormatRules))
            {
                $newAttributes[$key] = call_user_func($this->inputFormatRules[$key], $value);
            }
        }

        return $newAttributes;
    }

    protected function mapColumns($attributes)
    {
        foreach ($this->inputColumnMappingRules as $key=>$value)
        {
            if (isset($attributes[$key]))
            {
                $temp = $attributes[$key];
                $attributes[$value] = $temp;
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    public function save(array $attributes)
    {
        if ($this->beforeSave() === false)
        {
            return false;
        }

        if ($this->isHasAttribute)
        {
            $saved = $this->update($attributes);
        }
        else
        {
            $saved = $this->create($attributes);
        }

        if ($saved) $this->afterSave();

        return $saved;
    }

    public function select($columns='')
    {
        if ( ! empty($columns))
        {
            $this->preprocessor->processColumns($columns);
        }

        $this->addConstraintsForSoftDeleteSelect();

        $sql = $this->builder->buildSelect();
        $bindings = $this->builder->getBindings();
        $statement = $this->pdo->prepare($sql)->execute($bindings);

        if ($this->isJoin())
        {
            $resultSet = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        else
        {
            $resultSet = $statement->fetchAll(PDO::FETCH_FUNC, [$this, 'filterResult']);
            $resultSet = $this->stdClassToModel($resultSet);

            if ($this->material->columns === ['*'])
            {
                $this->lastSelectColumns = $this->getAllColumnNames();
            }
            else
            {
                $this->lastSelectColumns = $this->material->columns;
            }
        }

        if (isset($this->cacheMaterial['once']))
        {
            $this->material = clone $this->cacheMaterial['material'];
        }

        return $resultSet;
    }

    public function stdClassToModel($resultSet)
    {
        $models = [];
        foreach ($resultSet as $row)
        {
            $model = $this->newModel($row, true);
            $models[] = $model;
        }

        return $models;
    }

    public function findOrFail($id, $columns=['*'])
    {
        $model = $this->find($id, $columns);
        if (is_null($model))
        {
            throw (new ModelNotFoundException())->setModel(get_called_class());
        }

        return $model;
    }

    public function find($id, $columns=['*'])
    {
        return $this->first($id, $columns);
    }

    public function first($id='', $columns=['*'])
    {
        $resultSet = $this->where($this->primaryKeyName, $id)->limit(1)->select($columns);

        if (count($resultSet) <= 0)
        {
            return null;
        }

        return $resultSet[0];
    }

    public function limit($limit, $offset=0)
    {
        $this->offset($offset);
        $this->preprocessor->processLimit($limit);

        return $this;
    }

    public function offset($offset)
    {
        $this->preprocessor->processOffset($offset);

        return $this;
    }

    public function delete($id='')
    {
        if (is_null($this->primaryKeyName))
        {
            throw new \Exception ('模型没有定义主键！');
        }

        if ($this->beforeDelete() === false)
        {
            return false;
        }

        if ( ! empty($id))
        {
            $this->where($this->primaryKeyName, $id);
        }

        $rowCount = $this->performDelete();

        $this->afterDelete();

        return $rowCount;
    }

    public function performDelete()
    {
        if ($this->forceDelete or ! $this->useSoftDelte)
        {
            $rowCount = $this->performForceDelete();
        }
        else
        {
            $rowCount = $this->performSoftDelete();
        }

        return $rowCount;
    }

    public function performForceDelete()
    {
        $sql = $this->builder->buildDelete();
        $bindings = $this->builder->getBindings();
        $statement = $this->pdo->prepare($sql)->execute($bindings);

        return $statement->rowCount();
    }

    public function orderBy($orders)
    {
        $this->preprocessor->preprocessOrderBy($orders);

        return $this;
    }

    public function groupBy($groups)
    {
        $this->preprocessor->preprocessGroupBy($groups);

        return $this;
    }

    public function table($tableName)
    {
        $this->preprocessor->proprocessTableName($tableName);
    }

    public function having($firstOperand, $operator, $secondOperand='', $logic='AND')
    {
        if ( ! in_array($operator, $this->operators))
        {
            $secondOperand = $operator;
            $operator = '=';
        }

        $this->preprocessor->processHaving($firstOperand, $operator, $secondOperand, $logic);

        return $this;
    }

    public function orHaving($firstOperand, $operator, $secondOperand='')
    {
        return $this->having($firstOperand, $operator, $secondOperand, 'OR');
    }

    public function havingSql($sql, $logic='AND')
    {
        $this->preprocessor->processHavingOrign($sql, $logic);

        return $this;
    }

    public function orHavingSql($sql)
    {
        return $this->havingSql($sql, 'OR');
    }

    public function union(Model $model, $all=false)
    {
        $material = $model->getFetchMode();
        $this->preprocessor->processUnion($material, $all);

        return $this;
    }

    public function distinct($column)
    {
        $this->preprocessor->processDistinct($column);

        return $this;
    }

    public function leftJoin($tableName, $key='', $foreignKey='')
    {
        return $this->join($tableName, $key, $foreignKey, 'LEFT');
    }

    public function rightJoin($tableName, $key='', $foreignKey='')
    {
        return $this->join($tableName, $key, $foreignKey, 'RIGHT');
    }

    public function join($tableName, $key='', $foreignKey='', $type='INNER')
    {
        if (!empty($key) and empty($foreignKey))
        {
            $type = $key;

            $this->preprocessor->processJoin($tableName,$type, '', '', '', '');
        }
        else
        {
            $this->preprocessor->processJoin($tableName,$key, '=', $foreignKey, 'AND',$type);
        }

        return $this;
    }

    public function on($firstOperand, $operator, $secondOperand='', $logic='AND')
    {
        $this->preprocessor->processJoinClause($firstOperand, $operator, $secondOperand, $logic);

        return $this;
    }

    public function hasOne($relatedModelName, $currentKey='', $relatedKey='', $name='')
    {
        $relatedModel = (new $relatedModelName);
        if (empty($currentKey))
        {
            $currentKey = $this->getPrimaryKeyName();
        }
        if (empty($relatedKey))
        {
            $table = $relatedModel->getTableName();
            $relatedKey = "{$table}_id";
        }
        if (empty($name))
        {
            $name = $relatedModelName;
        }

        $this->relations[$name] = new HasOne($this, $relatedModel, $currentKey, $relatedKey);
    }

    public function hasMany($relatedModelName, $currentKey='', $relatedKey='', $name='')
    {
        $relatedModel = (new $relatedModelName);
        if (empty($currentKey))
        {
            $currentKey = $this->getPrimaryKeyName();
        }
        if (empty($relatedKey))
        {
            $table = $relatedModel->getTableName();
            $relatedKey = "{$table}_id";
        }
        if (empty($name))
        {
            $name = $relatedModelName;
        }

        $this->relations[$name] = new HasMany($this, $relatedModel, $currentKey, $relatedKey);
    }

    public function belongsTo($relatedModelName, $currentKey='', $relatedKey='', $name='')
    {
        $relatedModel = (new $relatedModelName);
        if (empty($currentKey))
        {
            $table = $relatedModel->getTableName();
            $currentKey = "{$table}_id";
        }
        if (empty($relatedKey))
        {
            $relatedKey = $relatedModel->getPrimaryKeyName();
        }
        if (empty($name))
        {
            $name = $relatedModelName;
        }

        $this->relations[$name] = new BelongsTo($this, $relatedModel, $currentKey, $relatedKey);
    }

    public function belongsToMany($relatedModelName, $middleTable='', $currentForeignKey='', $relatedForeignKey='', $name='', $currentKey='', $relatedKey='', $middleModel=null)
    {
        $relatedModel = (new $relatedModelName);
        if (empty($middleTable))
        {
            $middleTable = $this->getTableName().'_'.$relatedModel->getTableName();
        }
        if (empty($currentForeignKey))
        {
            $currentForeignKey = $this->getTableName().'_id';
        }
        if (empty($relatedForeignKey))
        {
            $relatedForeignKey = $relatedModel->getTableName().'_id';
        }
        if (empty($name))
        {
            $name = $relatedModelName;
        }
        if (empty($currentKey))
        {
            $currentKey = $this->getFullTableName().'.'.$this->getPrimaryKeyName();
        }
        if (empty($relatedKey))
        {
            $relatedKey = $relatedModel->getFullTableName().'.'.$relatedModel->getPrimaryKeyName();
        }

        $this->relations[$name] = new BelongsToMany($this, $relatedModel, $middleTable, $currentForeignKey, $relatedForeignKey, $currentKey, $relatedKey, $middleModel);
    }

    public function quote($str)
    {
        return $this->pdo->quote($str);
    }

    public function getConfig($optionName)
    {
        return array_get($this->config, $optionName);
    }

    public function getPrimaryKeyName()
    {
        return $this->primaryKeyName;
    }

    public function setPrimaryKeyName($name)
    {
        $this->primaryKeyName = $name;
    }

    public function getMaterial()
    {
        return $this->material;
    }

    public function setMaterial($material)
    {
        $this->material = $material;
    }

    public function newModel($attributes=[], $isHasAttribute=false)
    {
        $model = new static($attributes);
        $model->isHasAttribute = $isHasAttribute;

        return $model;
    }

    public function filterResult()
    {
        $columns = func_get_args();
        $columnsWithName = [];
        foreach ($columns as $key=>$value)
        {
            $columnName = $this->lastSelectColumns[$key];
            $callable = $this->outputFormatRules[$columnName];
            $value = call_user_func($callable, $value);
            $columnsWithName[$columnName] = $value;
        }

        return $columnsWithName;
    }

    public function getAllColumnNames()
    {
        $sql = "SHOW COLUMNS FROM {$this->tableName}";
        $resultSet = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
        foreach ($resultSet as $key=>$value)
        {
            $columns[] = $value['field'];
        }

        return $columns;
    }

    protected function isJoin()
    {
        return empty($this->material->joins) ? false : true;
    }

    public function getTableName()
    {
        if ( empty($this->tableName))
        {
            $this->tableName = strtolower(get_class($this));
        }

        return $this->tableName;
    }

    public function getFullTableName()
    {
        return "{$this->prefix}{$this->tableName}";
    }

    public function getPrimaryKey()
    {
        $primaryKeyName = $this->getPrimaryKeyName();

        return $this->{$primaryKeyName};
    }

    public function orWhere($firstOperand, $operator='', $secondOperand='')
    {
        return $this->where($firstOperand, $operator, $secondOperand, 'OR');
    }

    public function where($firstOperand, $operator='', $secondOperand='', $logic='AND')
    {
        if ($firstOperand instanceof Expression and empty($operator))
        {
            return $this->whereSql($firstOperand->getValue(), $logic);
        }

        if ($firstOperand instanceof Closure)
        {
            return $this->whereNested($firstOperand, $logic);
        }

        if ($secondOperand instanceof Closure)
        {
            return $this->whereSub($firstOperand, $operator, $secondOperand, $logic);
        }

        if ( ! in_array($operator, $this->operators))
        {
            $secondOperand = $operator;
            $operator = '=';
        }

        $this->preprocessor->processWhereBasic($firstOperand, $operator, $secondOperand, $logic);

        return $this;
    }

    public function orWhereSql($sql)
    {
        return $this->whereSql($sql, 'OR');
    }

    public function whereSql($sql, $logic='AND')
    {
        $this->preprocessor->processWhereSql($sql, $logic);

        return $this;
    }

    public function orWhereNested($callback)
    {
        return $this->whereNested($callback, 'OR');
    }

    public function whereNested($callback, $logic='AND')
    {
        $newModel = $this->newModel();
        call_user_func($callback, $newModel);
        $this->preprocessor->processWhereNested($newModel->getMaterial()->wheres, $logic);

        return $this;
    }

    public function orWhereNotBetween($column, $valueOne, $valueTwo)
    {
        return $this->orWhereBetween($column, $valueOne, $valueTwo, 'OR', true);
    }

    public function whereNotBetween($column, $valueOne, $valueTwo, $logic='AND')
    {
        return $this->whereBetween($column, $valueOne, $valueTwo, $logic, true);
    }

    public function orWhereBetween($column, $valueOne, $valueTwo, $not=false)
    {
        return $this->whereBetween($column, $valueOne, $valueTwo, 'OR', $not);
    }

    public function whereBetween($column, $valueOne, $valueTwo, $logic='AND', $not=false)
    {
        $this->preprocessor->processWhereBetween($column, $valueOne, $valueTwo, $logic, $not);

        return $this;
    }

    public function orWhereNotIn($column, $values)
    {
        return $this->orWhereIn($column, $values, true);
    }

    public function whereNotIn ($column, $values, $logic='AND')
    {
        return $this->whereIn($column, $values, $logic, true);
    }

    public function orWhereIn($column, $values, $not=false)
    {
        return $this->whereIn($column, $values, 'OR', $not);
    }

    public function whereIn($column, $values, $logic='AND', $not=false)
    {
        if ($values instanceof Closure)
        {
            return $this->whereInSub($column, $values, $logic, $not);
        }

        $this->preprocessor->processWhereIn($column, $values, $logic, $not);

        return $this;
    }

    public function orWhereNotInSub($column, Closure $callback)
    {
        return $this->orWhereInSub($column, $callback, true);
    }

    public function whereNotInSub($columns, Closure $callback, $logic)
    {
        return $this->whereInSub($columns, $callback, $logic, true);
    }

    public function orWhereInSub($column, Closure $callback, $not)
    {
        return $this->whereInSub($column, $callback, 'OR', $not);
    }

    public function whereInSub($column, Closure $callback, $logic, $not)
    {
        $newModel = $this->newModel();
        call_user_func($callback, $newModel);

        $this->preprocessor->processWhereInSub($column, $newModel->getMaterial(), $logic, $not);

        return $this;
    }

    public function orWhereNotExists(Closure $callback)
    {
        return $this->orWhereExists($callback, true);
    }

    public function whereNotExists(Closure $callback, $logic='AND')
    {
        return $this->whereExists($callback, $logic, true);
    }

    public function orWhereExists(Closure $callback, $not=false)
    {
        return $this->whereExists($callback, 'OR', $not);
    }

    public function whereExists(Closure $callback, $logic='AND', $not=false)
    {
        $newModel = $this->newModel();
        call_user_func($callback, $newModel);

        $this->preprocessor->processWhereExists($newModel->getMaterial(), $logic, $not);

        return $this;
    }

    public function orWhereNotNull($column)
    {
        return $this->orWhereNull($column, true);
    }

    public function whereNotNull($column, $logic='AND')
    {
        return $this->whereNull($column, $logic, true);
    }

    public function orWhereNull($column, $not=false)
    {
        return $this->whereNull($column, 'OR', $not);
    }

    public function whereNull($column, $logic='AND', $not=false)
    {
        $this->preprocessor->processWhereNull($column, $logic, $not);

        return $this;
    }

    public function whereDay($column, $operator, $value, $logic='AND')
    {
        $this->preprocessor->processWhereDate('Day', $column, $operator, $value, $logic);
    }

    public function whereMonth($column, $operator, $value, $logic='AND')
    {
        $this->preprocessor->processWhereDate('Month', $column, $operator, $value, $logic);
    }

    public function whereYear($column, $operator, $value, $logic='AND')
    {
        $this->preprocessor->processWhereDate('Year', $column, $operator, $value, $logic);
    }

    public function orWhereHas($relatedName, $callback=null)
    {
        return $this->whereHas($relatedName, $callback, 'OR');
    }

    public function whereHas($relatedName, $callback=null, $logic='AND')
    {
        $relation = $this->{$relatedName}();
        $relation->addConstraintsForRelationQuery();
        $relatedModel = $relation->getRelated();
        if ($callback) call_user_func($callback, $relatedModel);

        $this->where($this->sql('('.$relatedModel->toSelectSql().')'), '>=', 1, $logic);

        return $this;
    }

    public function sql($sql)
    {
        return new Expression($sql);
    }

    public function toSelectSql()
    {
        return $this->builder->buildSelect();
    }

    public function transaction(Closure $callback)
    {
        $this->beginTransaction();

        try
        {
            $result = call_user_func($callback, $this);

            $this->commit();
        }
        catch (\Exception $e)
        {
            $this->rollBack();

            throw $e;
        }

        return $result;
    }

    public function beginTransaction()
    {
        ++$this->transactions;

        if ($this->transactions == 1)
        {
            $this->pdo->beginTransaction();
        }

        $this->begunTransaction();
    }

    public function commit()
    {
        if ($this->transactions == 1) $this->pdo->commit();

        --$this->transactions;

        $this->afterCommit();
    }

    public function rollback()
    {
        if ($this->transactions == 1)
        {
            $this->transactions = 0;

            $this->pdo->rollBack();
        }
        else
        {
            --$this->transactions;
        }

        $this->afterRollback();
    }

    public function getTransactionNumber()
    {
        return $this->transactions;
    }

    public function count($columns = '*')
    {
        if ( ! is_array($columns))
        {
            $columns = array($columns);
        }

        return (int) $this->aggregate(__FUNCTION__, $columns);
    }

    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    public function sum($column)
    {
        $result = $this->aggregate(__FUNCTION__, array($column));

        return $result ?: 0;
    }

    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    public function aggregate($function, $columns=['*'])
    {
        $this->preprocessor->processAggregate($function, $columns);
        $result = $this->select($columns);
        if (isset($result[0]))
        {
            $result = array_change_key_case((array) $result[0]);

            return $result['aggregate'];
        }

        return null;
    }

    public function toArray($hiddenColumns=[])
    {
        if ( ! is_array($hiddenColumns)) $hiddenColumns = array($hiddenColumns);

        return array_diff_key($this->attributes, array_flip($hiddenColumns));
    }

    public function toJson($hiddenColumns=[])
    {
        return json_encode($this->toArray($hiddenColumns));
    }

    public function paginate($currentPage=0, $perPage=null)
    {
        $perPage = is_null($perPage) ? $this->perPage : $perPage;
        $this->snapshotQueryOnce();
        $totalCount = $this->count();

        $totalPage = ceil($totalCount/$perPage);

        $offset = $currentPage*$perPage;
        $resultSet = $this->limit($perPage)->offset($offset)->select();

        return ['totalCount'=>$totalCount, 'totalPage'=>$totalPage, 'resultSet'=>$resultSet];
    }

    public function snapshotQuery()
    {
        $this->cacheMaterial = clone $this->material;
    }

    public function snapshotQueryOnce()
    {
        $this->cacheMaterial['once'] = true;
        $this->cacheMaterial['material'] = clone $this->material;
    }

    public function recoverQuery()
    {
        $this->material = clone $this->cacheMaterial;
    }

}