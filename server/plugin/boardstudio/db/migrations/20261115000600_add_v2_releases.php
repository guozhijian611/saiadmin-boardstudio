<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;
use Phinx\Migration\AbstractMigration;

final class AddV2Releases extends AbstractMigration
{
    public function up(): void
    {
        $table=$this->table('boardstudio_screen');
        if (!$table->hasColumn('published_release_id')) $table->addColumn('published_release_id','biginteger',['null'=>true])->update();
        if (!$table->hasColumn('access_mode')) $table->addColumn('access_mode','string',['limit'=>12,'default'=>'token'])->update();
        if (!$this->hasTable('boardstudio_release')) {
            $this->table('boardstudio_release')->addColumn('screen_id','biginteger')->addColumn('revision','integer')
                ->addColumn('document','json')->addColumn('content_hash','string',['limit'=>64])
                ->addColumn('created_by','integer')->addColumn('create_time','datetime')
                ->addIndex(['screen_id','id'])->create();
        }
    }
    public function down(): void
    {
        try {
            $row=$this->fetchRow('SELECT COUNT(*) AS total FROM boardstudio_release');
            if ((int)$row['total']) throw new \RuntimeException('请先导出并清空 V2 发布版本再回滚');
            $this->table('boardstudio_release')->drop()->save();
            $this->table('boardstudio_screen')->removeColumn('published_release_id')->removeColumn('access_mode')->update();
        } catch (\Throwable $error) {
            $connection=$this->getAdapter()->getConnection();
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
}
