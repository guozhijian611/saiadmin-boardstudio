<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;

final class AddV2Document extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('boardstudio_screen');
        if (!$table->hasColumn('draft_document')) $table->addColumn('draft_document', 'json', ['null' => true])->update();
        $parent = $this->fetchRow("SELECT id FROM sa_system_menu WHERE code = 'BoardStudio' AND remark = 'boardstudio:install:1'");
        $exists = $this->fetchRow("SELECT id FROM sa_system_menu WHERE code = 'BoardStudioDesigner'");
        if ($parent && !$exists) {
            $this->table('sa_system_menu')->insert([
                'parent_id' => $parent['id'], 'name' => 'V2 设计器', 'code' => 'BoardStudioDesigner',
                'path' => 'designer/:id', 'component' => '/plugin/boardstudio/designer/[id]',
                'type' => 2, 'status' => 1, 'is_hidden' => 1, 'is_full_page' => 1,
                'remark' => 'boardstudio:v2:document',
            ])->saveData();
        }
    }

    public function down(): void
    {
        $row = $this->fetchRow('SELECT COUNT(*) AS total FROM boardstudio_screen WHERE draft_document IS NOT NULL');
        if ((int) $row['total'] > 0) throw new \RuntimeException('请先导出 V2 文档再回滚');
        $this->getDeleteBuilder()->delete('sa_system_menu')->where(['code' => 'BoardStudioDesigner', 'remark' => 'boardstudio:v2:document'])->execute();
        $table = $this->table('boardstudio_screen');
        if ($table->hasColumn('draft_document')) $table->removeColumn('draft_document')->update();
    }
}
