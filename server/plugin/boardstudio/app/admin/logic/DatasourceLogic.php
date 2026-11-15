<?php

namespace plugin\boardstudio\app\admin\logic;

use InvalidArgumentException;
use plugin\saiadmin\exception\ApiException;
use plugin\boardstudio\app\model\Datasource;
use plugin\boardstudio\app\service\DataSourceExecutor;

class DatasourceLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new Datasource();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function userDataScope($query): mixed
    {
        $actor=(int)(getCurrentInfo()['id'] ?? 0);
        if($actor<=0)throw new ApiException('请先登录');
        if($actor===1)return $query;
        $access=new \plugin\boardstudio\app\service\ResourceAccess();
        $ids=[];
        foreach(Datasource::field('id,created_by')->select() as $source) if($access->allows('datasource',(int)$source->id,(int)$source->created_by,'manage',$actor))$ids[]=$source->id;
        return $query->whereIn('id',$ids ?: [0]);
    }

    public function add(array $data): mixed
    {
        return parent::add($this->sanitizePayload($this->prepareConfiguration($data)));
    }

    public function edit($id, array $data): mixed
    {
        $stored = $this->read($id);
        $data = $this->prepareConfiguration($data, $stored);
        return parent::edit($id, $this->sanitizePayload($data));
    }

    public function prepareConfiguration(array $data, ?Datasource $stored = null): array
    {
        $config = $data['config'] ?? [];
        if (!is_array($config)) throw new ApiException('连接配置必须为对象');
        if ($stored && (string) $stored->type !== (string) ($data['type'] ?? '')) throw new ApiException('请新建数据源以更换连接类型');
        try {
            $data['config'] = (new \plugin\boardstudio\app\service\DatasourceCredentials())->merge($config, $stored?->config ?? [], !empty($data['clear_password']));
        } catch (\InvalidArgumentException $error) { throw new ApiException($error->getMessage()); }
        unset($data['clear_password']);
        return $data;
    }

    public function enabledOptions(): array
    {
        $query = Datasource::where('status', 1);
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        return $query
            ->field('id,name,type')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    public function enabled(int $id): Datasource
    {
        $datasource = $this->read($id);
        if ((int) $datasource->status !== 1) {
            throw new ApiException('数据源不存在或已停用');
        }

        return $datasource;
    }

    private function sanitizePayload(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time'], $data['last_error']);

        $data['type'] = (string) ($data['type'] ?? '');
        try {
            $data['config'] = (new DataSourceExecutor())->normalizeDatasourceConfig(
                $data['type'],
                $data['config'] ?? []
            );
        } catch (InvalidArgumentException $exception) {
            throw new ApiException($exception->getMessage());
        }

        $data['cache_ttl'] = min(86400, max(0, (int) ($data['cache_ttl'] ?? 0)));
        return $data;
    }
}
