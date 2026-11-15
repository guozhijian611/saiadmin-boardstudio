<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use InvalidArgumentException;

/** Optional versioned output contract; undeclared legacy datasets keep inferred fields. */
final class DatasetFields
{
    public function validate(mixed $fields): array
    {
        if (!is_array($fields) || !array_is_list($fields) || count($fields)>100) throw new InvalidArgumentException('输出字段须为最多 100 项的列表');
        $names=[];
        foreach($fields as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $field['name'] ?? '') || isset($names[$field['name']])) throw new InvalidArgumentException('输出字段名称不合法或重复');
            if (!in_array($field['type'] ?? '', ['string','number','boolean','object'],true) || !is_bool($field['nullable'] ?? null)) throw new InvalidArgumentException('输出字段类型或可空设置不合法');
            if (!is_string($field['label'] ?? '') || mb_strlen($field['label'] ?? '')>60) throw new InvalidArgumentException('输出字段标题过长');
            $names[$field['name']]=true;
        }
        return array_map(fn($f)=>array_intersect_key($f,array_flip(['name','label','type','nullable'])),$fields);
    }

    public function apply(array $rows, array $fields): array
    {
        $fields=$this->validate($fields);
        if (!$fields) return $rows;
        $output=[];
        foreach($rows as $row) {
            if(!is_array($row))throw new InvalidArgumentException('查询结果行须为对象');
            $clean=[];
            foreach($fields as $field) {
                $name=$field['name'];
                if(!array_key_exists($name,$row))throw new InvalidArgumentException('查询结果缺少声明字段：'.$name);
                $value=$row[$name];
                if($value===null) {
                    if(!$field['nullable'])throw new InvalidArgumentException('查询结果字段不允许为空：'.$name);
                } elseif($field['type']==='number') {
                    if(is_bool($value)||!is_numeric($value)||!is_finite((float)$value))throw new InvalidArgumentException('查询结果字段须为数字：'.$name);
                    // Keep database DECIMAL strings intact to avoid silent precision loss.
                } elseif($field['type']==='string') {
                    if(!is_string($value))throw new InvalidArgumentException('查询结果字段须为文本：'.$name);
                } elseif($field['type']==='boolean') {
                    if(in_array($value,[0,1,'0','1'],true))$value=(bool)(int)$value;
                    elseif(!is_bool($value))throw new InvalidArgumentException('查询结果字段须为布尔值：'.$name);
                } elseif(!is_array($value)&&!is_object($value))throw new InvalidArgumentException('查询结果字段须为对象或数组：'.$name);
                $clean[$name]=$value;
            }
            $output[]=$clean;
        }
        return $output;
    }

    public function mapping(array $mapping, array $fields): void
    {
        if(!$fields)return; // Backward-compatible inferred output.
        $names=array_column($this->validate($fields),null,'name');
        if(!is_array($mapping['tableFields'] ?? []) || !is_array($mapping['tableColumns'] ?? []))throw new InvalidArgumentException('表格映射格式不合法');
        foreach(['labelField','valueField'] as $key) {
            $name=$mapping[$key] ?? '';
            if($name!==''&&!isset($names[$name]))throw new InvalidArgumentException('组件映射字段不在数据集输出中：'.$name);
            if($key==='valueField' && $name!=='' && $names[$name]['type']!=='number')throw new InvalidArgumentException('组件数值映射必须选择数字字段');
        }
        foreach(array_merge($mapping['tableFields']??[],array_column($mapping['tableColumns']??[],'field')) as $name) {
            if(!isset($names[$name]))throw new InvalidArgumentException('表格映射字段不在数据集输出中：'.$name);
        }
    }
}
