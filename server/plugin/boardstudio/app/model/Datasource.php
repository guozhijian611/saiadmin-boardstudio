<?php

namespace plugin\boardstudio\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Datasource extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'boardstudio_datasource';

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchTypeAttr($query, $value): void
    {
        $query->where('type', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getConfigAttr(mixed $value, array $data = []): array
    {
        return (new \plugin\boardstudio\app\service\DatasourceCredentials())->open($value ?? $data['config'] ?? []);
    }
    public function setConfigAttr(mixed $value): string
    {
        if (is_string($value)) $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new \InvalidArgumentException('连接配置必须为对象');
        return (new \plugin\boardstudio\app\service\DatasourceCredentials())->seal($value);
    }
    public function toArray(): array
    {
        $data = parent::toArray();
        if (isset($data['config'])) $data['config'] = (new \plugin\boardstudio\app\service\DatasourceCredentials())->mask($data['config']);
        return $data;
    }
}
