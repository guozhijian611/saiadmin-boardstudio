<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;
use Phinx\Migration\AbstractMigration;
final class LocalizeMenuTitles extends AbstractMigration
{
    private const TITLES = [
        'BoardStudio' => ['Board Studio', 'root'],
        'BoardStudioScreen' => ['大屏管理', 'screens'],
        'BoardStudioPresets' => ['预设模板', 'presets'],
        'BoardStudioDesigner' => ['V2 设计器', 'designer'],
        'BoardStudioDataset' => ['数据集', 'datasets'],
        'BoardStudioDatasource' => ['数据源管理', 'sources'],
        'BoardStudioQueryTemplate' => ['数据集', 'datasets'],
        'BoardStudioEditor' => ['大屏编辑器', 'editor'],
    ];
    public function up(): void
    {
        foreach (self::TITLES as $code => [$old, $key]) {
            $this->getUpdateBuilder()->update('sa_system_menu')->set(['name' => 'menus.boardstudio.'.$key])->where(['code' => $code, 'name' => $old])->execute();
        }
    }
    public function down(): void
    {
        try {
            foreach (['boardstudio_datasource','boardstudio_screen','boardstudio_screen_version','boardstudio_screen_token','boardstudio_query_template','boardstudio_retired_templates','boardstudio_dataset','boardstudio_dataset_version','boardstudio_resource_grant','boardstudio_release'] as $table) {
                if (!$this->hasTable($table)) continue;
                $row=$this->fetchRow('SELECT COUNT(*) AS total FROM '.$table);
                if((int)$row['total'])throw new \RuntimeException('插件仍有业务数据，拒绝卸载或回滚；请先备份并清空：'.$table);
            }
        } catch (\Throwable $error) {
            $connection=$this->getAdapter()->getConnection();
            if($connection->inTransaction())$connection->rollBack();
            throw $error;
        }
        foreach (self::TITLES as $code => [$old, $key]) {
            $this->getUpdateBuilder()->update('sa_system_menu')->set(['name' => $old])->where(['code' => $code, 'name' => 'menus.boardstudio.'.$key])->execute();
        }
    }
}
