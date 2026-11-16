<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\admin\logic;

use plugin\saiadmin\exception\ApiException;
use plugin\boardstudio\app\service\RoleDataScope;

/** Role row scope is mandatory for admin CRUD; resource sharing adds its own gate. */
abstract class BaseLogic extends \plugin\saiadmin\basic\think\BaseLogic
{
    protected bool $scope = true;

    public function userDataScope($query): mixed
    {
        return (new RoleDataScope())->apply($query);
    }

    public function add(array $data): mixed
    {
        $this->userDataScope($this->model->newQuery());
        $data = $this->writable($data);
        $data['created_by'] = RoleDataScope::actor();
        return parent::add($data);
    }

    public function read($id): mixed
    {
        $record = $this->userDataScope($this->model->newQuery())->findOrEmpty($id);
        if ($record->isEmpty()) throw new ApiException('数据不存在或无权访问');
        return $record;
    }

    public function edit($id, array $data): mixed
    {
        return $this->transaction(function () use ($id, $data) {
            $record = $this->userDataScope($this->model->newQuery())->lock(true)->findOrEmpty($id);
            if ($record->isEmpty()) throw new ApiException('数据不存在或无权访问');
            return $record->save($this->writable($data));
        });
    }

    public function search(array $searchWhere = []): mixed
    {
        // getList/getAll apply the scope once; custom terminals must explicitly apply it.
        return parent::search($searchWhere);
    }

    public function getList($query): mixed
    {
        $query = $this->ordered($this->userDataScope($query));
        $request = request();
        if ($request && $request->input('saiType', 'list') === 'all') return $query->select()->toArray();
        $limit = max(1, min(500, (int)($request ? $request->input('limit', 10) : 10)));
        $page = max(1, (int)($request ? $request->input('page', 1) : 1));
        return $query->paginate($limit, false, ['page' => $page])->toArray();
    }

    public function getAll($query): mixed
    {
        return $this->ordered($this->userDataScope($query))->select()->toArray();
    }

    private function ordered($query): mixed
    {
        $request = request();
        $field = (string)($request ? $request->input('orderField', '') : '');
        $field = $field ?: ($this->orderField ?: $this->model->getPk());
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) throw new ApiException('排序字段无效');
        $direction = strtoupper((string)($request ? $request->input('orderType', $this->orderType) : $this->orderType));
        if (!in_array($direction, ['ASC','DESC'], true)) throw new ApiException('排序方向无效');
        return $query->order($field, $direction);
    }

    public function destroy($ids): bool
    {
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));
        if (!$ids) return false;
        return (bool) $this->transaction(function () use ($ids) {
            // Verify the complete selection before deleting anything.
            $records = [];
            foreach ($ids as $id) {
                $record = $this->userDataScope($this->model->newQuery())->lock(true)->findOrEmpty($id);
                if ($record->isEmpty()) throw new ApiException('数据不存在或无权访问');
                $records[] = $record;
            }
            foreach ($records as $record) $record->delete();
            return true;
        });
    }

    private function writable(array $data): array
    {
        foreach (['id','created_by','updated_by','create_time','update_time','delete_time'] as $field) unset($data[$field]);
        return $data;
    }
}
