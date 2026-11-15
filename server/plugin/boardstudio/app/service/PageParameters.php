<?php
namespace plugin\boardstudio\app\service;
use InvalidArgumentException;
final class PageParameters
{
    public function resolve(array $document,array $input=[]): array
    {
        $definitions=$document['parameters'] ?? [];
        foreach($definitions as $name=>&$definition) {
            $definition['required']=true;
            if(array_key_exists($name,$input)) {
                $value=$input[$name];
                if($definition['type']==='number' && !is_string($value) && !is_int($value) && !is_float($value)) throw new InvalidArgumentException('页面数字参数不合法');
                if($definition['type']==='string' && !is_string($value)) throw new InvalidArgumentException('页面文本参数不合法');
                if($definition['type']==='boolean' && !in_array($value,[true,false,'true','false',1,0,'1','0'],true)) throw new InvalidArgumentException('页面布尔参数不合法');
            }
        }
        unset($definition);
        return (new DatasetParameters())->resolve($definitions,$input);
    }
    public function binding(array $binding,array $values): array
    {
        foreach($binding['params'] as $key=>$value) {
            if(is_array($value)) {
                $name=$value['pageParameter'] ?? null;
                if(count($value)!==1 || !is_string($name) || !array_key_exists($name,$values)) throw new InvalidArgumentException('页面参数引用不合法');
                $binding['params'][$key]=$values[$name];
            }
        }
        return $binding;
    }
}
