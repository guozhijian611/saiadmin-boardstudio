<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;
use Phinx\Migration\AbstractMigration;

final class AddV2Datasets extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('boardstudio_dataset')) {
            $table = $this->table('boardstudio_dataset');
            $table->addColumn('datasource_id', 'biginteger')->addColumn('name', 'string', ['limit'=>60])
                ->addColumn('definition', 'json')->addColumn('revision', 'integer', ['default'=>1])
                ->addColumn('status', 'integer', ['default'=>1]);
            $this->audit($table); $table->create();
        }
        if (!$this->hasTable('boardstudio_dataset_version')) {
            $table = $this->table('boardstudio_dataset_version');
            $table->addColumn('dataset_id', 'biginteger')->addColumn('version', 'integer')
                ->addColumn('datasource_id', 'biginteger')->addColumn('definition', 'json');
            $this->audit($table); $table->addIndex(['dataset_id','version'], ['unique'=>true])->create();
        }
        if (!$this->hasTable('boardstudio_resource_grant')) {
            $this->table('boardstudio_resource_grant')->addColumn('resource_type','string',['limit'=>20])
                ->addColumn('resource_id','biginteger')->addColumn('subject_type','string',['limit'=>10])
                ->addColumn('subject_id','biginteger')->addColumn('action','string',['limit'=>20])
                ->addIndex(['resource_type','resource_id','subject_type','subject_id','action'],['unique'=>true])->create();
        }
        // Explicitly convert legacy query definitions without changing legacy rows.
        foreach ($this->fetchAll('SELECT * FROM boardstudio_query_template') as $row) {
            if ($this->fetchRow('SELECT id FROM boardstudio_dataset WHERE id='.(int)$row['id'])) continue;
            $definition = json_encode(['mode'=>'legacy','query'=>['dataset_type'=>$row['dataset_type'],'config'=>json_decode($row['config'] ?: '{}',true)],'parameters'=>new \stdClass(),'refreshSeconds'=>30,'cacheSeconds'=>0,'limit'=>500,'timeoutMs'=>5000]);
            $data = ['datasource_id'=>$row['datasource_id'],'definition'=>$definition,'created_by'=>$row['created_by'],'create_time'=>$row['create_time']];
            $this->table('boardstudio_dataset')->insert($data+['id'=>$row['id'],'name'=>$row['name'],'revision'=>1,'status'=>$row['status']])->saveData();
            $this->table('boardstudio_dataset_version')->insert($data+['dataset_id'=>$row['id'],'version'=>1])->saveData();
        }
        // PostgreSQL sequences do not advance after explicit legacy IDs.
        if ($this->getAdapter()->getAdapterType() === 'pgsql') $this->execute("SELECT setval(pg_get_serial_sequence('boardstudio_dataset','id'), COALESCE((SELECT MAX(id) FROM boardstudio_dataset), 1), EXISTS(SELECT 1 FROM boardstudio_dataset))");
        $this->getUpdateBuilder()->update('sa_system_menu')->set(['name'=>'数据集','component'=>'/plugin/boardstudio/dataset/index'])->where(['code'=>'BoardStudioQueryTemplate','remark'=>'boardstudio:install:1'])->execute();
    }
    private function audit($table): void
    {
        foreach (['created_by','updated_by'] as $name) $table->addColumn($name,'integer',['null'=>true]);
        foreach (['create_time','update_time','delete_time'] as $name) $table->addColumn($name,'datetime',['null'=>true]);
    }
    public function down(): void
    {
        try { $this->rollbackSchema(); } catch (\Throwable $error) {
            $connection = $this->getAdapter()->getConnection();
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
    private function rollbackSchema(): void
    {
        // Dataset versions may be referenced by published documents. Never silently discard them.
        foreach (['boardstudio_dataset','boardstudio_dataset_version','boardstudio_resource_grant'] as $name) {
            $row=$this->fetchRow('SELECT COUNT(*) AS total FROM '.$name);
            if ((int)$row['total']) throw new \RuntimeException('请先导出并清空 V2 数据集及授权再回滚');
        }
        foreach (['boardstudio_resource_grant','boardstudio_dataset_version','boardstudio_dataset'] as $name) $this->table($name)->drop()->save();
        $this->getUpdateBuilder()->update('sa_system_menu')->set(['name'=>'查询模板','component'=>'/plugin/boardstudio/query-template/index'])->where(['code'=>'BoardStudioQueryTemplate','remark'=>'boardstudio:install:1'])->execute();
    }
}
