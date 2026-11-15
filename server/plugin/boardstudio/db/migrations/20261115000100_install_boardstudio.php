<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;
use RuntimeException;

final class InstallBoardstudio extends AbstractMigration
{
    private const OWNER = 'boardstudio:install:1';
    private const TABLES = ['boardstudio_datasource', 'boardstudio_screen', 'boardstudio_query_template', 'boardstudio_screen_version', 'boardstudio_screen_token', 'boardstudio_market_item'];

    public function up(): void
    {
        try { $this->installSchema(); } catch (\Throwable $error) {
            $this->releaseFailedTransaction();
            throw $error;
        }
    }

    private function installSchema(): void
    {
        // Reject occupied tables instead of claiming unrelated existing data.
        foreach (self::TABLES as $name) {
            if ($this->hasTable($name)) {
                throw new RuntimeException('表已存在，请核对未完成安装：' . $name);
            }
        }
        $table = $this->table('boardstudio_datasource');
        $table->addColumn('name', 'string', ['null' => false, 'limit' => 60, 'default' => '']);
        $table->addColumn('type', 'string', ['null' => false, 'limit' => 10, 'default' => 'mysql']);
        $table->addColumn('config', 'json', ['null' => true]);
        $table->addColumn('cache_ttl', 'integer', ['null' => false, 'default' => 0]);
        $table->addColumn('last_error', 'text', ['null' => true]);
        $table->addColumn('status', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addIndex(['type', 'status'], ['unique' => false]);
        $table->addIndex(['create_time'], ['unique' => false]);
        $table->create();
        $table = $this->table('boardstudio_screen');
        $table->addColumn('code', 'string', ['null' => false, 'limit' => 32, 'default' => '']);
        $table->addColumn('name', 'string', ['null' => false, 'limit' => 60, 'default' => '']);
        $table->addColumn('width', 'integer', ['null' => false, 'default' => 1920]);
        $table->addColumn('height', 'integer', ['null' => false, 'default' => 1080]);
        $table->addColumn('bg_config', 'json', ['null' => true]);
        $table->addColumn('is_public', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('access_token', 'string', ['null' => true, 'limit' => 64]);
        $table->addColumn('draft_layout', 'json', ['null' => true]);
        $table->addColumn('layout', 'json', ['null' => true]);
        $table->addColumn('status', 'integer', ['null' => false, 'default' => 2]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addColumn('revision', 'integer', ['null' => false, 'default' => 0]);
        $table->addIndex(['code'], ['unique' => true]);
        $table->addIndex(['status', 'is_public'], ['unique' => false]);
        $table->addIndex(['create_time'], ['unique' => false]);
        $table->create();
        $table = $this->table('boardstudio_query_template');
        $table->addColumn('datasource_id', 'biginteger', ['null' => false]);
        $table->addColumn('name', 'string', ['null' => false, 'limit' => 60, 'default' => '']);
        $table->addColumn('dataset_type', 'string', ['null' => false, 'limit' => 20, 'default' => 'table_raw']);
        $table->addColumn('config', 'json', ['null' => true]);
        $table->addColumn('status', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addIndex(['datasource_id'], ['unique' => false]);
        $table->addIndex(['dataset_type', 'status'], ['unique' => false]);
        $table->addIndex(['create_time'], ['unique' => false]);
        $table->create();
        $table = $this->table('boardstudio_screen_version');
        $table->addColumn('screen_id', 'biginteger', ['null' => false]);
        $table->addColumn('version_no', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('source', 'string', ['null' => false, 'limit' => 32, 'default' => 'save_layout']);
        $table->addColumn('title', 'string', ['null' => false, 'limit' => 120, 'default' => '']);
        $table->addColumn('width', 'integer', ['null' => false, 'default' => 1920]);
        $table->addColumn('height', 'integer', ['null' => false, 'default' => 1080]);
        $table->addColumn('bg_config', 'json', ['null' => true]);
        $table->addColumn('layout', 'json', ['null' => true]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addIndex(['screen_id', 'version_no'], ['unique' => true]);
        $table->addIndex(['screen_id', 'create_time'], ['unique' => false]);
        $table->create();
        $table = $this->table('boardstudio_screen_token');
        $table->addColumn('screen_id', 'biginteger', ['null' => false]);
        $table->addColumn('name', 'string', ['null' => false, 'limit' => 80, 'default' => '']);
        $table->addColumn('token_prefix', 'string', ['null' => false, 'limit' => 16, 'default' => '']);
        $table->addColumn('token_hash', 'string', ['null' => false, 'limit' => 64]);
        $table->addColumn('last_used_time', 'datetime', ['null' => true]);
        $table->addColumn('expire_time', 'datetime', ['null' => true]);
        $table->addColumn('status', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addIndex(['token_hash'], ['unique' => true]);
        $table->addIndex(['screen_id', 'status'], ['unique' => false]);
        $table->addIndex(['expire_time'], ['unique' => false]);
        $table->create();
        $table = $this->table('boardstudio_market_item');
        $table->addColumn('type', 'string', ['null' => false, 'limit' => 20, 'default' => 'screen']);
        $table->addColumn('name', 'string', ['null' => false, 'limit' => 80, 'default' => '']);
        $table->addColumn('category', 'string', ['null' => false, 'limit' => 60, 'default' => '']);
        $table->addColumn('description', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('cover_image', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('content', 'json', ['null' => true]);
        $table->addColumn('component_count', 'integer', ['null' => false, 'default' => 0]);
        $table->addColumn('is_public', 'integer', ['null' => false, 'default' => 2]);
        $table->addColumn('status', 'integer', ['null' => false, 'default' => 1]);
        $table->addColumn('remark', 'string', ['null' => false, 'limit' => 255, 'default' => '']);
        $table->addColumn('created_by', 'integer', ['null' => true]);
        $table->addColumn('updated_by', 'integer', ['null' => true]);
        $table->addColumn('create_time', 'datetime', ['null' => true]);
        $table->addColumn('update_time', 'datetime', ['null' => true]);
        $table->addColumn('delete_time', 'datetime', ['null' => true]);
        $table->addIndex(['type', 'status'], ['unique' => false]);
        $table->addIndex(['is_public', 'status'], ['unique' => false]);
        $table->addIndex(['category'], ['unique' => false]);
        $table->addIndex(['create_time'], ['unique' => false]);
        $table->create();

        $ids = [];
        $ids['BoardStudio'] = $this->menu(['name' => 'Board Studio', 'code' => 'BoardStudio', 'path' => '/boardstudio', 'component' => '', 'type' => 2, 'is_hidden' => 2, 'is_full_page' => 2] + ['parent_id' => 0]);
        $ids['BoardStudioScreen'] = $this->menu(['name' => '大屏管理', 'code' => 'BoardStudioScreen', 'path' => 'screen', 'component' => '/plugin/boardstudio/screen/index', 'type' => 2, 'is_hidden' => 2, 'is_full_page' => 2] + ['parent_id' => $ids['BoardStudio']]);
        $ids['BoardStudioDatasource'] = $this->menu(['name' => '数据源管理', 'code' => 'BoardStudioDatasource', 'path' => 'datasource', 'component' => '/plugin/boardstudio/datasource/index', 'type' => 2, 'is_hidden' => 2, 'is_full_page' => 2] + ['parent_id' => $ids['BoardStudio']]);
        $ids['BoardStudioQueryTemplate'] = $this->menu(['name' => '查询模板', 'code' => 'BoardStudioQueryTemplate', 'path' => 'query-template', 'component' => '/plugin/boardstudio/query-template/index', 'type' => 2, 'is_hidden' => 2, 'is_full_page' => 2] + ['parent_id' => $ids['BoardStudio']]);
        $ids['BoardStudioMarketItem'] = $this->menu(['name' => '模板市场', 'code' => 'BoardStudioMarketItem', 'path' => 'market', 'component' => '/plugin/boardstudio/market/index', 'type' => 2, 'is_hidden' => 2, 'is_full_page' => 2] + ['parent_id' => $ids['BoardStudio']]);
        $ids['BoardStudioEditor'] = $this->menu(['name' => '大屏编辑器', 'code' => 'BoardStudioEditor', 'path' => 'editor/:id', 'component' => '/plugin/boardstudio/editor/[id]', 'type' => 2, 'is_hidden' => 1, 'is_full_page' => 1] + ['parent_id' => $ids['BoardStudio']]);
        $this->menu(['name' => '数据源表结构', 'slug' => 'boardstudio:datasource:index', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '数据源读取', 'slug' => 'boardstudio:datasource:read', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '数据源添加', 'slug' => 'boardstudio:datasource:save', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '数据源修改', 'slug' => 'boardstudio:datasource:update', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '数据源删除', 'slug' => 'boardstudio:datasource:destroy', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '数据源状态', 'slug' => 'boardstudio:datasource:changeStatus', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '测试数据源', 'slug' => 'boardstudio:datasource:test', 'type' => 3, 'parent_id' => $ids['BoardStudioDatasource']]);
        $this->menu(['name' => '模板市场列表', 'slug' => 'boardstudio:market_item:index', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '模板市场读取', 'slug' => 'boardstudio:market_item:read', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '模板市场添加', 'slug' => 'boardstudio:market_item:save', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '模板市场修改', 'slug' => 'boardstudio:market_item:update', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '模板市场删除', 'slug' => 'boardstudio:market_item:destroy', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '模板市场状态', 'slug' => 'boardstudio:market_item:changeStatus', 'type' => 3, 'parent_id' => $ids['BoardStudioMarketItem']]);
        $this->menu(['name' => '大屏运行统计', 'slug' => 'boardstudio:screen:index', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏读取', 'slug' => 'boardstudio:screen:read', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏添加', 'slug' => 'boardstudio:screen:save', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏修改', 'slug' => 'boardstudio:screen:update', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏删除', 'slug' => 'boardstudio:screen:destroy', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏状态', 'slug' => 'boardstudio:screen:changeStatus', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '保存大屏布局', 'slug' => 'boardstudio:screen:saveLayout', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '发布大屏', 'slug' => 'boardstudio:screen:publish', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '复制大屏', 'slug' => 'boardstudio:screen:copy', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '从数据表生成大屏', 'slug' => 'boardstudio:screen:generateFromTable', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏版本列表', 'slug' => 'boardstudio:screen:versions', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '恢复大屏版本', 'slug' => 'boardstudio:screen:restoreVersion', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '删除大屏版本', 'slug' => 'boardstudio:screen:deleteVersion', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏访问令牌列表', 'slug' => 'boardstudio:screen:tokens', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '创建大屏访问令牌', 'slug' => 'boardstudio:screen:createToken', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '重置大屏访问令牌', 'slug' => 'boardstudio:screen:resetToken', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '大屏访问令牌状态', 'slug' => 'boardstudio:screen:changeTokenStatus', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '删除大屏访问令牌', 'slug' => 'boardstudio:screen:deleteToken', 'type' => 3, 'parent_id' => $ids['BoardStudioScreen']]);
        $this->menu(['name' => '查询模板选项', 'slug' => 'boardstudio:query_template:index', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '查询模板读取', 'slug' => 'boardstudio:query_template:read', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '查询模板添加', 'slug' => 'boardstudio:query_template:save', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '查询模板修改', 'slug' => 'boardstudio:query_template:update', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '查询模板删除', 'slug' => 'boardstudio:query_template:destroy', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '查询模板状态', 'slug' => 'boardstudio:query_template:changeStatus', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);
        $this->menu(['name' => '预览查询模板', 'slug' => 'boardstudio:query_template:preview', 'type' => 3, 'parent_id' => $ids['BoardStudioQueryTemplate']]);

    }

    public function down(): void
    {
        try { $this->uninstallSchema(); } catch (\Throwable $error) {
            $this->releaseFailedTransaction();
            throw $error;
        }
    }

    private function releaseFailedTransaction(): void
    {
        // Phinx 0.16 in-process failures otherwise retain PostgreSQL read locks.
        $connection = $this->getAdapter()->getConnection();
        if ($connection->inTransaction()) $connection->rollBack();
    }

    private function uninstallSchema(): void
    {
        // Preflight every table before changing any schema or menu.
        foreach (self::TABLES as $name) {
            if ($this->hasTable($name)) {
                $row = $this->fetchRow('SELECT COUNT(*) AS total FROM ' . $this->getAdapter()->quoteTableName($name));
                if ((int) $row['total'] > 0) {
                    throw new RuntimeException('请先导出并清空 Board Studio 数据再卸载：' . $name);
                }
            }
        }
        $this->getDeleteBuilder()->delete('sa_system_menu')->where(['remark' => self::OWNER])->execute();
        foreach (array_reverse(self::TABLES) as $name) {
            if ($this->hasTable($name)) $this->table($name)->drop()->save();
        }
    }

    private function menu(array $row): int
    {
        $query = $this->getSelectBuilder()->select('id')->from('sa_system_menu');
        $match = !empty($row['slug']) ? ['slug' => $row['slug']] : ['code' => $row['code']];
        $existing = $query->where($match)->execute()->fetch('assoc');
        if ($existing) throw new RuntimeException('菜单编码已存在：' . json_encode($match));
        $row += [
            'parent_id' => 0, 'name' => '', 'code' => '', 'slug' => '', 'type' => 2,
            'path' => '', 'component' => '', 'icon' => 'ri:dashboard-3-line', 'sort' => 80,
            'link_url' => '', 'is_iframe' => 2, 'is_keep_alive' => 2, 'is_hidden' => 2,
            'is_fixed_tab' => 2, 'is_full_page' => 2, 'generate_id' => 0, 'status' => 1,
            'remark' => self::OWNER, 'created_by' => 1, 'updated_by' => 1,
            'create_time' => date('Y-m-d H:i:s'), 'update_time' => date('Y-m-d H:i:s'),
        ];
        $this->table('sa_system_menu')->insert($row)->saveData();
        return (int) $this->getSelectBuilder()->select('id')->from('sa_system_menu')->where($match)->execute()->fetch('assoc')['id'];
    }
}
