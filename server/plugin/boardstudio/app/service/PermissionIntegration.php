<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use PDO;
use plugin\saiadmin\app\cache\ReflectionCache;
use plugin\saiadmin\app\cache\UserAuthCache;
use plugin\saiadmin\app\cache\UserMenuCache;
use support\think\Cache;

final class PermissionIntegration
{
    public const OWNER = 'boardstudio:permissions:2.2.0';

    public function __construct(private readonly PDO $db) {}

    public function apply(): void
    {
        // Preflight every natural key and parent before claiming or changing any node.
        $parents = []; $existing = [];
        foreach (PermissionCatalog::ACTIONS as $slug => $item) {
            $parent = $this->one('SELECT * FROM sa_system_menu WHERE code = ?', [$item['parent']]);
            if (!$parent || !str_starts_with((string)$parent['remark'], 'boardstudio:')) throw new \RuntimeException('插件父菜单不存在或归属不符：'.$item['parent']);
            $parents[$slug] = (int)$parent['id'];
            $node = $this->one('SELECT * FROM sa_system_menu WHERE slug = ?', [$slug]);
            if ($node && !str_starts_with((string)$node['remark'], 'boardstudio:')) throw new \RuntimeException('权限编码已被其它模块占用：'.$slug);
            $existing[$slug] = $node;
        }
        $owned = !$this->db->inTransaction();
        if ($owned) $this->db->beginTransaction();
        try {
            $created = false;
            foreach (PermissionCatalog::ACTIONS as $slug => $item) {
                $node = $existing[$slug];
                $missing = !$node;
                if (!$node) {
                    $created = true;
                    $this->run('INSERT INTO sa_system_menu (parent_id,name,slug,type,status,remark,created_by,updated_by,create_time,update_time) VALUES (?,?,?,3,1,?,1,1,?,?)',
                        [$parents[$slug], PermissionCatalog::title($slug), $slug, self::OWNER, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
                    $node = $this->one('SELECT * FROM sa_system_menu WHERE slug = ?', [$slug]);
                } elseif ($node['name'] === $item['name'] || in_array($node['name'], ['大屏运行统计','查询模板选项','数据源选项'], true)) {
                    $this->run('UPDATE sa_system_menu SET name = ? WHERE id = ?', [PermissionCatalog::title($slug), $node['id']]);
                }
                // Preserve existing assignments; grant new points only to roles holding the former API right.
                if ($missing && $item['legacy'] !== null) {
                    $legacy = $this->one('SELECT id FROM sa_system_menu WHERE slug = ?', [$item['legacy']]);
                    if ($legacy) $this->copyAssignments((int)$legacy['id'], (int)$node['id']);
                }
            }
            $layout = $this->one('SELECT id FROM sa_system_menu WHERE slug = ?', ['boardstudio:screen:saveLayout']);
            if ($created && $layout) foreach (['BoardStudioDesigner','BoardStudioEditor'] as $code) {
                $route = $this->one('SELECT id FROM sa_system_menu WHERE code = ?', [$code]);
                if ($route) $this->copyAssignments((int)$layout['id'], (int)$route['id']);
            }
            // Parameterized hidden routes must not become a role's default landing page.
            foreach (['BoardStudioDesigner','BoardStudioEditor'] as $code) {
                $route = $this->one('SELECT id,sort,remark FROM sa_system_menu WHERE code = ?', [$code]);
                if ($route && str_starts_with((string)$route['remark'],'boardstudio:') && in_array((int)$route['sort'],[80,100],true))
                    $this->run('UPDATE sa_system_menu SET sort = 0 WHERE id = ?', [$route['id']]);
            }
            $screenMenu = $this->one('SELECT id,sort,remark FROM sa_system_menu WHERE code = ?', ['BoardStudioScreen']);
            if ($screenMenu && str_starts_with((string)$screenMenu['remark'],'boardstudio:') && (int)$screenMenu['sort']===80)
                $this->run('UPDATE sa_system_menu SET sort = 90 WHERE id = ?', [$screenMenu['id']]);
            if ($owned) $this->db->commit();
            self::clearCaches();
        } catch (\Throwable $error) {
            if ($owned && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** Caller preflights all business tables before touching authorization state. */
    public function remove(): void
    {
        $ids = $this->run("SELECT id FROM sa_system_menu WHERE remark LIKE 'boardstudio:%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) $this->run('DELETE FROM sa_system_role_menu WHERE menu_id = ?', [$id]);
        $this->run('DELETE FROM sa_system_menu WHERE remark = ?', [self::OWNER]);
        self::clearCaches();
    }

    public static function clearCaches(): void
    {
        UserMenuCache::clearMenuCache();
        Cache::tag(UserAuthCache::cacheConfig()['tag'])->clear();
        ReflectionCache::clear();
    }

    private function copyAssignments(int $from, int $to): void
    {
        foreach ($this->run('SELECT role_id FROM sa_system_role_menu WHERE menu_id = ?', [$from])->fetchAll(PDO::FETCH_COLUMN) as $role) {
            if (!$this->one('SELECT id FROM sa_system_role_menu WHERE role_id = ? AND menu_id = ?', [$role, $to]))
                $this->run('INSERT INTO sa_system_role_menu (role_id,menu_id) VALUES (?,?)', [$role,$to]);
        }
    }

    private function one(string $sql, array $values): ?array
    {
        $rows = $this->run($sql, $values)->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) throw new \RuntimeException('权限或菜单编码重复，请先核对现有记录');
        return $rows[0] ?? null;
    }

    private function run(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($values);
        return $statement;
    }
}
