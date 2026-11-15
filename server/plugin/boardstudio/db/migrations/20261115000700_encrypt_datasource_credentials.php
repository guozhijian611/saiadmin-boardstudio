<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;
use Phinx\Migration\AbstractMigration;
use plugin\boardstudio\app\service\DatasourceCredentials;

final class EncryptDatasourceCredentials extends AbstractMigration
{
    public function up(): void
    {
        $credentials = new DatasourceCredentials();
        $rows = $this->fetchAll('SELECT id, config FROM boardstudio_datasource');
        // Existing ciphertext means a missing key must be restored, never regenerated.
        foreach ($rows as $row) {
            $config = json_decode($row['config'], true, 512, JSON_THROW_ON_ERROR);
            if (isset($config['_boardstudio_encrypted'])) $credentials->open($config);
        }
        $credentials->initialize();
        $connection = $this->getAdapter()->getConnection();
        $statement = $connection->prepare('UPDATE boardstudio_datasource SET config = :config WHERE id = :id');
        foreach ($rows as $row) {
            $config = json_decode($row['config'], true, 512, JSON_THROW_ON_ERROR);
            if (!isset($config['_boardstudio_encrypted'])) $statement->execute(['config' => $credentials->seal($config), 'id' => $row['id']]);
        }
    }
    public function down(): void
    {
        try {
            // Uninstall rolls migrations down newest-first. Refuse before changing
            // any schema when any plugin data still exists, including draft-only screens.
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
        // Key is intentionally retained for backups; no filesystem deletion during rollback.
    }
}
