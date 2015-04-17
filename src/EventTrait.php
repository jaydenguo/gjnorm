<?php namespace Gjnorm;


trait EventTrait {

    public function beforeCreate(){}

    public function afterCreate(){}

    public function beforeUpdate(){}

    public function afterUpdate(){}

    public function beforeSave(){}

    public function afterSave(){}

    public function beforeDelete(){}

    public function afterDelete(){}

    public function beforeRestore(){}

    public function afterRestore(){}

    public function begunTransaction(){}

    public function afterCommit(){}

    public function afterRollback(){}
}