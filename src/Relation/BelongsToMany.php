<?php namespace Gjnorm\Relation;


use Gjnorm\Expression;
use Gjnorm\Model;
use Mockery\Exception;

class BelongsToMany extends Relation{

    protected $middleTable;
    protected $pivot;
    protected $currentForeignKey;
    protected $relatedForeignKey;
    protected $currentKey;
    protected $relatedKey;

    public function __construct($currentModel, $relatedModel, $middleTable, $currentForeignKey, $relatedForeignKey, $currentKey, $relatedKey, $middleModel=null)
    {
        $this->middleTable = $middleTable;
        $this->currentForeignKey = $currentForeignKey;
        $this->relatedForeignKey = $relatedForeignKey;
        $this->currentKey = $currentKey;
        $this->relatedKey = $relatedKey;

        if (is_null($middleModel))
        {
            $this->pivot = (new Model())->table($middleTable);
        }
        else
        {
            $this->pivot = $middleModel;
        }

        parent::__construct($currentModel, $relatedModel);
        $this->addConstraintsForPivot();
    }

    public function getPivot()
    {
        return $this->pivot;
    }

    public function addConstraints()
    {
        $this->joinMiddleTable();

        $firstOperand = $this->middleTable.'.'.$this->currentForeignKey;
        $secondOperand = $this->currentModel->{$this->currentKey};
        $this->relatedModel->where($firstOperand, '=', $secondOperand);
    }

    public function addConstraintsForPivot()
    {
        $this->pivot->where($this->currentForeignKey, '=', $this->currentModel->{$this->currentKey});
    }

    public function addConstraintsForRelationQuery()
    {
        $this->joinMiddleTable();

        $firstOperand = $this->middleTable.'.'.$this->currentForeignKey;
        $secondOperand = $this->currentModel->getFullTableName.'.'.$this->currentKey;

        $this->relatedModel->where($firstOperand, '=', $secondOperand)->columns(new Expression('count(*)'));
    }

    protected function joinMiddleTable()
    {
        $relatedTable = $this->relatedModel->getFullTableName();
        $firstOperand = $this->middleTable.'.'.$this->relatedForeignKey;
        $secondOperand = $relatedTable.'.'.$this->relatedKey;

        $this->relatedModel->join($this->middleTable, $firstOperand, $secondOperand);
    }

    public function attach($ids, array $attributes=[])
    {
        $records = [];
        if (is_array($ids))
        {
            foreach ($ids as $key=>$value)
            {
                $records[] = $this->parseAttachValue($key, $value, $attributes);
            }
        }
        else
        {
            $records[] = $this->createRecord($this->parseAttachId($ids), $attributes);
        }

        return $this->pivot->insert($records);
    }

    protected function parseAttachId($ids)
    {
        if ($ids instanceof Model)
        {
            $ids = [$ids->getPrimaryKey()];
        }
        elseif (is_int($ids))
        {
            $ids = [$ids];
        }

        return $ids;
    }

    protected function parseAttachValue($key, $value, $attributes)
    {
        if (is_array($value))
        {
            return $this->createRecord($key, array_merge($value, $attributes));
        }

        return  $this->createRecord($this->parseAttachId($value), $attributes);
    }

    protected function createRecord($id, $attributes)
    {
        $record[$this->currentForeignKey] = $this->currentModel->{$this->currentKey};
        $record[$this->relatedForeignKey] = $id;

        return array_merge($record, $attributes);
    }

    public function detach($ids=[])
    {
        if ( ! empty($ids))
        {
            if ($ids instanceof Model) $ids = (array) $ids->getPrimaryKey();
            $ids = (array)$ids;

            if (count($ids) > 0)
            {
                $this->pivot->whereIn($this->relatedForeignKey, (array) $ids);
            }
        }

        return $this->pivot->delete();
    }
}