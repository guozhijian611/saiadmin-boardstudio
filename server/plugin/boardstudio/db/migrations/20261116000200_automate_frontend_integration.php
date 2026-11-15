<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;
use plugin\boardstudio\app\service\FrontendIntegration;

/** saipackage copies the payload before running these migrations on install and upgrade. */
final class AutomateFrontendIntegration extends AbstractMigration
{
    public function up(): void
    {
        try {
            FrontendIntegration::host()->apply();
        } catch (\Throwable $error) {
            $connection = $this->getAdapter()->getConnection();
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }

    public function down(): void
    {
        // Newest migration rolls back first. Refuse before changing either host files or schema.
        try {
            foreach (['boardstudio_datasource','boardstudio_screen','boardstudio_screen_version','boardstudio_screen_token','boardstudio_query_template','boardstudio_retired_templates','boardstudio_dataset','boardstudio_dataset_version','boardstudio_resource_grant','boardstudio_release'] as $table) {
                if (!$this->hasTable($table)) continue;
                $row = $this->fetchRow('SELECT COUNT(*) AS total FROM '.$table);
                if ((int) $row['total']) throw new \RuntimeException('插件仍有业务数据，拒绝卸载或回滚；请先备份并清空：'.$table);
            }
            FrontendIntegration::host()->remove();
        } catch (\Throwable $error) {
            $connection = $this->getAdapter()->getConnection();
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
}
