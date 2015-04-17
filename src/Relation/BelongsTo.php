<?php namespace Gjnorm\Relation;


use Mockery\Exception;

class BelongsTo extends Relation{

    protected $relatedKey;
    protected $currentKey;

    public function __construct($currentModel, $relatedModel, $currentKey, $relatedKey)
    {
        $this->relatedKey = $relatedKey;
        $this->currentKey = $currentKey;

        parent::__construct($currentModel, $relatedModel);
    }

    public function addConstraints()
    {
        $secondOperand = $this->currentModel->{$this->currentKey};
        $this->relatedModel->where($this->relatedKey, '=', $secondOperand);

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