<?php
/**
 * Created by PhpStorm.
 * User: jayden
 * Date: 2015/4/13
 * Time: 9:53
 */

namespace Gjnorm\Relation;


class HasMany extends HasOneOrMany{

    public function __construct($currentModel, $relatedModel, $localKey, $childKey)
    {
        parent::__construct($currentModel, $relatedModel, $localKey, $childKey);
    }
} 