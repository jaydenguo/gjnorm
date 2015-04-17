<?php namespace Gjnorm;


trait SoftDeleteTrait {

    protected $forceDelete = false;

    protected $selectWithTrashed = false;

    public function forceDelete()
    {
        $this->forceDeleting = true;
        $this->delete();
        $this->forceDeleting = false;
    }

    protected function performSoftDelete()
    {
        $attributes = [$this->{$this->softDeleteColumn}=>time()];

        return $this->update($attributes);
    }

    public function restore()
    {
        if ($this->beforeRetore() === false)
        {
            return false;
        }

        $this->{$this->softDeleteColumn} = null;
        $this->exists = true;
        $rowCount = $this->save();

        $this->afterRestore();

        return $rowCount;
    }

    public function isTrashed()
    {
        return ! is_null($this->{$this->softDeleteColumn});
    }

    public function WhereOnlyTrashed()
    {
        $this->selectWithTrashed = true;
        return $this->whereNotNull($this->{$this->softDeleteColumn});
    }

    public function whereWithTrashed()
    {
        $this->selectWithTrashed = true;

        return $this;
    }

    protected function addConstraintsForSoftDeleteSelect()
    {
        if ($this->useSoftDelete)
        {
            if ( ! $this->selectWithTrashed)
            {
                $this->whereNull($this->{$this->softDeleteColumn});
            }
            else
            {
                $this->selectWithTrashed = false;
            }
        }
    }
} 