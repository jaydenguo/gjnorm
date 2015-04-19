<?php
function array_get(array $array, $optionName)
{
    return $array[$optionName];
}

function array_get_some(array $array, array $keys)
{
    $newArray = [];
    foreach ($keys as $key)
    {
        $newArray[$key] = $$array[$key];
    }

    return $newArray;
}

function array_add_prefix($array, $prefix)
{
    return array_map(function($value) use ($prefix)
    {
        return $prefix.$value;
    }, $array);
}

function array_key_add_prefix($array, $prefix)
{
    $newArray = [];
    foreach ($array as $key=>$value)
    {
        $newKey = $prefix.$key;
        $newArray[$newKey] = $value;
    }

    return $newArray;
}

function str_start_with($string, $testString)
{
    $startStr = substr($string, 0, count($testString));

    return $startStr == $testString ? true : false;
}

function dd()
{
    array_map(function($x) { var_dump($x); }, func_get_args()); die;
}