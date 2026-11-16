<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;
use plugin\boardstudio\app\service\PermissionIntegration;

final class IntegrateRolePermissions extends AbstractMigration
{
    private const BUSINESS = ['boardstudio_datasource','boardstudio_screen','boardstudio_screen_version','boardstudio_screen_token','boardstudio_query_template','boardstudio_retired_templates','boardstudio_dataset','boardstudio_dataset_version','boardstudio_resource_grant','boardstudio_release'];

    public function up(): void
    {
        $this->guard(function () {
            // Existing 2.1.1 installations already ran migration 9 with the two-file contract.
            $integration=\plugin\boardstudio\app\service\FrontendIntegration::host($this->getAdapter()->getAdapterType()==='pgsql');
            $integration->plan();
            foreach (['sa_system_menu','sa_system_role_menu','sa_system_role','sa_system_role_dept','sa_system_user','sa_system_user_role','sa_system_dept'] as $table) {
                if (!$this->hasTable($table)) throw new \RuntimeException('缺少系统权限表：'.$table);
            }
            if (!$this->table('sa_system_role')->hasColumn('data_scope')) throw new \RuntimeException('宿主缺少角色数据范围字段');
            $host = \plugin\saiadmin\basic\think\BaseLogic::class;
            foreach (['ALL_SCOPE'=>1,'CUSTOM_SCOPE'=>2,'SELF_DEPT_SCOPE'=>3,'DEPT_BELOW_SCOPE'=>4,'SELF_SCOPE'=>5] as $name=>$value) {
                if (defined($host.'::'.$name) && constant($host.'::'.$name) !== $value) throw new \RuntimeException('宿主 DataScope 枚举与已支持契约不符');
            }
            foreach (['boardstudio_screen','boardstudio_datasource','boardstudio_query_template','boardstudio_dataset'] as $name) {
                $table = $this->table($name);
                if (!$table->hasColumn('created_by')) throw new \RuntimeException('业务表缺少归属字段：'.$name);
                $index = $name.'_scope_owner_22';
                if (!$table->hasIndexByName($index)) $table->addIndex(['created_by'], ['name'=>$index])->update();
            }
            (new PermissionIntegration($this->getAdapter()->getConnection()))->apply();
            $integration->apply();
        });
    }

    public function down(): void
    {
        $this->guard(function () {
            foreach (self::BUSINESS as $name) {
                if (!$this->hasTable($name)) continue;
                $row = $this->fetchRow('SELECT COUNT(*) AS total FROM '.$this->getAdapter()->quoteTableName($name));
                if ((int)$row['total']) throw new \RuntimeException('插件仍有业务数据，拒绝清理权限：'.$name);
            }
            // Validate all managed files before removing any authorization state.
            $integration=\plugin\boardstudio\app\service\FrontendIntegration::host($this->getAdapter()->getAdapterType()==='pgsql');
            $integration->plan(true);
            $integration->remove();
            (new PermissionIntegration($this->getAdapter()->getConnection()))->remove();
            foreach (['boardstudio_screen','boardstudio_datasource','boardstudio_query_template','boardstudio_dataset'] as $name) {
                $index = $name.'_scope_owner_22';
                if ($this->hasTable($name) && $this->table($name)->hasIndexByName($index)) $this->table($name)->removeIndexByName($index)->update();
            }
        });
    }

    private function guard(callable $action): void
    {
        try { $action(); }
        catch (\Throwable $error) {
            $connection = $this->getAdapter()->getConnection();
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
}
