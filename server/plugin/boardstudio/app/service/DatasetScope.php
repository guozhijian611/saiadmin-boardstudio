<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;
use InvalidArgumentException;
use plugin\saiadmin\app\model\system\SystemUser;

/** Scope values come from the current account record, never the request's business filters. */
final class DatasetScope
{
    public function validate(array $definition): array
    {
        $scope=$definition['scope'] ?? ['mode'=>'shared'];
        if(!is_array($scope) || !in_array($scope['mode'] ?? '',['shared','viewer'],true)) throw new InvalidArgumentException('数据范围模式不合法');
        if($scope['mode']==='shared')return ['mode'=>'shared'];
        if(!in_array($scope['identity'] ?? '',['user','department'],true))throw new InvalidArgumentException('请选择用户或部门范围');
        if(!in_array($definition['mode'] ?? '',['visual','sql'],true))throw new InvalidArgumentException('观看者行级范围目前仅支持 SQL 和可视化数据集');
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}\.[A-Za-z_][A-Za-z0-9_]{0,63}$/',$scope['field'] ?? ''))throw new InvalidArgumentException('范围字段须包含表别名，如 t0.owner_id');
        return ['mode'=>'viewer','identity'=>$scope['identity'],'field'=>$scope['field']];
    }
    public function resolve(array $definition, ?int $viewer): ?array
    {
        $scope=$this->validate($definition);
        if($scope['mode']==='shared')return null;
        if(!$viewer)throw new InvalidArgumentException('此数据集需要登录观看者身份');
        $user=SystemUser::findOrEmpty($viewer);
        if($user->isEmpty() || (int)$user->status!==1)throw new InvalidArgumentException('观看者账号不可用');
        $value=$scope['identity']==='user'?(int)$user->id:(int)$user->dept_id;
        if($value<=0)throw new InvalidArgumentException('观看者没有有效的范围身份');
        return ['field'=>$scope['field'],'value'=>$value,'identity'=>$scope['identity']];
    }
}
