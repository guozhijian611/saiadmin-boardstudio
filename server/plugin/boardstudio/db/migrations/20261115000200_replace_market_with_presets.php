<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;

/** Keep old user templates as an archive, without exposing market endpoints. */
final class ReplaceMarketWithPresets extends AbstractMigration
{
    private const OWNER = 'boardstudio:install:1';

    public function up(): void
    {
        try {
            if ($this->hasTable('boardstudio_market_item')) {
                $this->table('boardstudio_market_item')->rename('boardstudio_retired_templates')->update();
            }
            $this->getUpdateBuilder()->update('sa_system_menu')->set([
                'name' => '预设模板', 'code' => 'BoardStudioPresets', 'path' => 'presets',
                'component' => '/plugin/boardstudio/presets/index',
            ])->where(['code' => 'BoardStudioMarketItem', 'remark' => self::OWNER])->execute();
            $this->getUpdateBuilder()->update('sa_system_menu')->set(['status' => 2, 'is_hidden' => 1])
                ->where(['remark' => self::OWNER])->where(['slug LIKE' => 'boardstudio:market_item:%'])->execute();
        } catch (\Throwable $error) {
            $this->rollbackConnection();
            throw $error;
        }
    }

    public function down(): void
    {
        try {
            // Refuse the entire uninstall before renaming the archive or restoring menus.
            foreach (['boardstudio_retired_templates', 'boardstudio_screen', 'boardstudio_screen_version', 'boardstudio_screen_token', 'boardstudio_datasource', 'boardstudio_query_template'] as $table) {
                if (!$this->hasTable($table)) continue;
                $row = $this->fetchRow('SELECT COUNT(*) AS total FROM ' . $this->getAdapter()->quoteTableName($table));
                if ((int) $row['total'] > 0) throw new \RuntimeException('请先导出并清空 Board Studio 数据再卸载：' . $table);
            }
            if ($this->hasTable('boardstudio_retired_templates')) {
                $this->table('boardstudio_retired_templates')->rename('boardstudio_market_item')->update();
            }
            $this->getUpdateBuilder()->update('sa_system_menu')->set([
                'name' => '模板市场', 'code' => 'BoardStudioMarketItem', 'path' => 'market',
                'component' => '/plugin/boardstudio/market/index',
            ])->where(['code' => 'BoardStudioPresets', 'remark' => self::OWNER])->execute();
            $this->getUpdateBuilder()->update('sa_system_menu')->set(['status' => 1, 'is_hidden' => 2])
                ->where(['remark' => self::OWNER])->where(['slug LIKE' => 'boardstudio:market_item:%'])->execute();
        } catch (\Throwable $error) {
            $this->rollbackConnection();
            throw $error;
        }
    }

    private function rollbackConnection(): void
    {
        $connection = $this->getAdapter()->getConnection();
        if ($connection->inTransaction()) $connection->rollBack();
    }
}
