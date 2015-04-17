<?php namespace Gjnorm;


class SqlBuilderHelper {

    protected $tablePrefix = '';

    public function __construct($tablePrefix='')
    {
        $this->tablePrefix = $tablePrefix;
    }

    public function warpTable($value)
    {
        if ($this->isExpression($value)) return $value->getValue();

        return $this->warp("{$this->tablePrefix}{$value}");
    }

    protected function wrapValue($value)
    {
        if ($value === '*') return $value;

        return '`'.str_replace('`', '``', $value).'`';
    }

    public function warp($value)
    {
        if ($this->isExpression($value)) return $value->getValue();

        //如果该值存在列的别名，我们会把该值分割成几部分，
        //这样我们就可以对各个部分进行分别处理
        if (strpos(strtolower($value), ' as ') !== false)
        {
            $sections = explode(' ', $value);

            return $this->warp($sections[0]).' as '.$this->warpValue($sections[2]);
        }

        //如果是带有表名的列名，我们会对表名和列表分别进行处理
        if (strpos($value, '.') !== false)
        {
            $sections = explode('.', $value);

            return $this->warpTable($sections[0]).'.'.$this->warpValue($sections[2]);
        }

        //否则进行普通处理
        return $this->warpValue($value);
    }

    public function isExpression($value)
    {
        return $value instanceof Expression;
    }

    public function wrapArray(array $values)
    {
        return array_map(array($this, 'wrap'), $values);
    }

    public function columnsToStr($columns=[])
    {
        return implode(', ', $this->wrapArray($columns));
    }

    public function removeLeadingLogic($value)
    {
        return preg_replace('/and |or /', '', $value, 1);
    }

    public function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function($value)
        {
            return (string) $value !== '';
        }));
    }
} 