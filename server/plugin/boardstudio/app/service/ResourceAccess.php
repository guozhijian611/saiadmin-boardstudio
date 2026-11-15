<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use support\think\Db;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\app\model\system\SystemUserRole;

final class ResourceAccess
{
    public function allows(string $type, int $id, int $owner, string $action, ?int $userId = null): bool
    {
        $userId ??= (int) (getCurrentInfo()['id'] ?? 0);
        if ($userId <= 0) return false;
        if ($userId === 1 || $owner === $userId) return true;
        $roles = SystemUserRole::getRoleIds($userId);
        $grants = Db::table('boardstudio_resource_grant')->where(['resource_type' => $type, 'resource_id' => $id])->whereIn('action', [$action, 'manage'])->select();
        foreach ($grants as $grant) {
            if ($grant['subject_type'] === 'user' && (int) $grant['subject_id'] === $userId) return true;
            if ($grant['subject_type'] === 'role' && in_array((int) $grant['subject_id'], array_map('intval', $roles), true)) return true;
        }
        return false;
    }
    public function assert(string $type, int $id, int $owner, string $action, ?int $userId = null): void
    {
        if (!$this->allows($type, $id, $owner, $action, $userId)) throw new ApiException('资源不存在或无权使用',403);
    }
}
