<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\admin\logic;

use plugin\saiadmin\exception\ApiException;

/** Explicit ownership policy: superadmin can manage all, other admins manage their own. */
abstract class BaseLogic extends \plugin\saiadmin\basic\think\BaseLogic
{
    public function userDataScope($query): mixed
    {
        $id = (int) (getCurrentInfo()['id'] ?? 0);
        if ($id <= 0) throw new ApiException('请先登录');
        return $id === 1 ? $query : $query->where('created_by', $id);
    }

    public function read($id): mixed
    {
        $record = $this->userDataScope($this->model->newQuery())->findOrEmpty($id);
        if ($record->isEmpty()) throw new ApiException('数据不存在或无权访问');
        return $record;
    }

    public function edit($id, array $data): mixed
    {
        return $this->read($id)->save($data);
    }

    public function search(array $searchWhere = []): mixed
    {
        return $this->userDataScope(parent::search($searchWhere));
    }

    public function destroy($ids): bool
    {
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));
        if (!$ids) return false;
        return (bool) $this->transaction(function () use ($ids) {
            // Verify the complete selection before deleting anything.
            $records = [];
            foreach ($ids as $id) $records[] = $this->read($id);
            foreach ($records as $record) $record->delete();
            return true;
        });
    }
}
