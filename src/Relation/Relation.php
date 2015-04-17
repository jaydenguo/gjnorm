<?php namespace Gjnorm\Relation;


abstract class Relation {

    protected $relatedModel;

    protected $currentModel;

    public function __construct($currentModel, $relatedModel)
    {
        $this->relatedModel = new $relatedModel;

        $this->addConstraints();
    }

    public function getRelated()
    {
        return $this->relatedModel;
    }

    abstract public function addConstraints();
    abstract public function addConstraintsForRelationQuery();
} 