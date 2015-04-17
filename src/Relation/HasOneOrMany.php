<?php
/**
 * Created by PhpStorm.
 * User: jayden
 * Date: 2015/4/13
 * Time: 9:52
 */

namespace Gjnorm\Relation;


class HasOneOrMany extends Relation{

    protected $relatedKey;

    protected $currentKey;

    public function __construct($currentModel, $relatedModel, $currentKey, $relatedKey)
    {
        $this->currentKey = $currentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($currentModel, $relatedModel);
    }

    public function addConstraints()
    {
        $this->relatedModel->where($this->relatedKey, '=', $this->currentModel->{$this->currentKey});

        $this->relatedModel->setAttribute($this->relatedKey, $this->currentModel->{$this->currentKey});
    }

    public function addConstraintsForRelationQuery()
    {
        $relatedTable = $this->relatedModel->getFullTableName();
        $currentTable = $this->currentModel->getFullTableName();
        $firstOperand = $relatedTable.'.'.$this->relatedKey;
        $secondOperand = $currentTable.'.'.$this->currentKey;

        $this->relatedModel->where($firstOperand, '=', $secondOperand)->columns(new Exception('COUNT(*)'));
    }
} 