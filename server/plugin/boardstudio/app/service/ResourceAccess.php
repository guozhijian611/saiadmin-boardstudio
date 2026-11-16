<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use support\think\Db;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\app\model\system\SystemUserRole;

final class ResourceAccess
{
    /** SQL resource ACL. Admin callers must also apply RoleDataScope; players use explicit ACLs. */
    public function apply($query, string $type, array $actions, ?int $userId = null): mixed
    {
        $userId ??= RoleDataScope::actor();
        if ($userId === 1) return $query;
        $roles = $this->roles($userId);
        $grants = static function ($grantQuery) use ($type, $actions, $userId, $roles) {
            $grantQuery->table('boardstudio_resource_grant')->where('resource_type', $type)->whereIn('action', array_unique([...$actions, 'manage']))
            ->where(function ($subjects) use ($userId, $roles) {
                $subjects->where(function ($users) use ($userId) { $users->where('subject_type', 'user')->where('subject_id', $userId); })
                    ->whereOr(function ($groups) use ($roles) { $groups->where('subject_type', 'role')->whereIn('subject_id', $roles ?: [0]); });
            })->field('resource_id');
        };
        return $query->where(function ($resources) use ($userId, $grants) {
            $resources->where('created_by', $userId)->whereOr('id', 'in', $grants);
        });
    }

    public function allows(string $type, int $id, int $owner, string $action, ?int $userId = null): bool
    {
        $userId ??= (int) (getCurrentInfo()['id'] ?? 0);
        if ($userId <= 0) return false;
        if (!Db::table('sa_system_user')->where('id',$userId)->where('status',1)->whereNull('delete_time')->count()) return false;
        if ($userId === 1 || $owner === $userId) return true;
        $roles = $this->roles($userId);
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

    private function roles(int $userId): array
    {
        return array_map('intval', Db::table('sa_system_role')->whereIn('id', SystemUserRole::getRoleIds($userId))->where('status',1)->whereNull('delete_time')->column('id'));
    }
}
