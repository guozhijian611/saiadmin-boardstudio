<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\admin\logic;

use plugin\boardstudio\app\model\Dataset;
use plugin\boardstudio\app\model\DatasetVersion;
use plugin\boardstudio\app\model\Datasource;
use plugin\boardstudio\app\model\Screen;
use plugin\boardstudio\app\service\DatasetExecutor;
use plugin\boardstudio\app\service\DatasetParameters;
use plugin\boardstudio\app\service\DatasetSql;
use plugin\boardstudio\app\service\DatasetScope;
use plugin\boardstudio\app\service\ResourceAccess;
use plugin\saiadmin\exception\ApiException;
use support\think\Db;
use plugin\boardstudio\app\service\DatasetCache;

final class DatasetLogic extends BaseLogic
{
    protected bool $scope = true;
    public function __construct() { $this->model = new Dataset(); }

    public function accessible(int $id, string $action = 'use', ?int $userId = null): Dataset
    {
        $dataset = Dataset::findOrEmpty($id);
        if ($dataset->isEmpty()) throw new ApiException('数据集不存在或无权访问',403);
        (new ResourceAccess())->assert('dataset', $id, (int)$dataset->created_by, $action, $userId);
        return $dataset;
    }
    public function available(): array
    {
        $access = new ResourceAccess();
        $result = [];
        foreach (Dataset::order('id','desc')->select() as $dataset) {
            $permissions=[];
            foreach(['use','edit','manage'] as $action) $permissions[$action]=$access->allows('dataset',(int)$dataset->id,(int)$dataset->created_by,$action);
            if (!in_array(true,$permissions,true)) continue;
            $row=$dataset->toArray();
            if (!$permissions['edit'] && !$permissions['manage']) $row['definition']['query']=(object)[];
            $row['permissions']=$permissions;
            $result[]=$row;
        }
        return $result;
    }
    public function source(int $id, bool $authoring = true): Datasource
    {
        $source = Datasource::findOrEmpty($id);
        if ($source->isEmpty() || (int)$source->status !== 1) throw new ApiException('数据源不存在或已停用',403);
        if ($authoring) (new ResourceAccess())->assert('datasource',$id,(int)$source->created_by,'use');
        return $source;
    }
    public function saveDefinition(array $input): array
    {
        try { return $this->saveDefinitionInternal($input); }
        catch (\InvalidArgumentException $error) { throw new ApiException($error->getMessage()); }
        catch (\PDOException) { throw new ApiException('查询执行失败，请检查字段、权限和数据源状态'); }
        catch (\RuntimeException $error) { if ($error instanceof ApiException) throw $error; throw new ApiException('数据源请求失败，请检查连接与响应配置'); }
    }
    private function saveDefinitionInternal(array $input): array
    {
        $id=(int)($input['id'] ?? 0);
        $definition=$this->validateDefinition($input['definition'] ?? []);
        $source=$this->source((int)($input['datasource_id'] ?? 0));
        if (($definition['mode']==='http') !== ($source->type==='http')) throw new ApiException('数据源与查询模式不匹配');
        return $this->transaction(function() use($input,$id,$definition,$source) {
            if ($id) {
                $this->accessible($id,'edit');
                $dataset=Dataset::where('id',$id)->lock(true)->findOrEmpty();
                if ((int)$dataset->revision !== (int)($input['revision'] ?? 0)) throw new ApiException('数据集已被修改，请重新加载',409);
            } else $dataset=new Dataset();
            $revision=$id ? (int)$dataset->revision+1 : 1;
            $dataset->save(['name'=>trim($input['name']), 'datasource_id'=>$source->id,'definition'=>$definition,'revision'=>$revision,
                'status'=>(int)($input['status'] ?? 1), 'created_by'=>$id ? $dataset->created_by : (int)getCurrentInfo()['id']]);
            DatasetVersion::create(['dataset_id'=>$dataset->id,'version'=>$revision,'datasource_id'=>$source->id,
                'definition'=>$definition,'created_by'=>getCurrentInfo()['id']]);
            return ['id'=>(int)$dataset->id,'revision'=>$revision];
        });
    }
    public function validateDefinition(array $definition): array
    {
        if (strlen(json_encode($definition))>100000) throw new ApiException('数据集定义过大');
        if (!in_array($definition['mode'] ?? '',['visual','sql','http'],true)) throw new ApiException('请选择数据集模式');
        $definition['scope']=(new DatasetScope())->validate($definition);
        $definition['fields']=(new \plugin\boardstudio\app\service\DatasetFields())->validate($definition['fields'] ?? []);
        $definition += ['parameters'=>[], 'query'=>[], 'refreshSeconds'=>30,'cacheSeconds'=>15,'limit'=>500,'timeoutMs'=>5000];
        if (!is_array($definition['parameters']) || !is_array($definition['query'])) throw new ApiException('数据集定义不合法');
        $samples = [];
        foreach ($definition['parameters'] as $key => $parameter) $samples[$key] = $parameter['default'] ?? match($parameter['type'] ?? 'string') { 'number' => 0, 'boolean' => false, default => '' };
        (new DatasetParameters())->resolve($definition['parameters'],$samples);
        if ($definition['mode']==='sql') {
            $params=[];
            foreach ($definition['parameters'] as $key=>$parameter) $params[$key]=$parameter['default'] ?? null;
            (new DatasetSql())->custom((string)($definition['query']['sql'] ?? ''),$params);
        }
        foreach (['refreshSeconds'=>[10,3600],'cacheSeconds'=>[0,3600],'limit'=>[1,5000],'timeoutMs'=>[100,10000]] as $key=>$range) {
            $value=$definition[$key];
            if (!is_int($value) || $value<$range[0] || $value>$range[1]) throw new ApiException('数据集参数超出范围：'.$key);
        }
        return $definition;
    }
    public function preview(array $input): array
    {
        try { return $this->previewInternal($input); }
        catch (\InvalidArgumentException $error) { throw new ApiException($error->getMessage()); }
        catch (\PDOException) { throw new ApiException('查询执行失败，请检查字段、权限和数据源状态'); }
        catch (\RuntimeException $error) { if ($error instanceof ApiException) throw $error; throw new ApiException('数据源请求失败，请检查连接与响应配置'); }
    }
    private function previewInternal(array $input): array
    {
        $source=$this->source((int)($input['datasource_id'] ?? 0));
        $definition=$this->validateDefinition($input['definition'] ?? []);
        return (new DatasetExecutor())->execute($source,$definition,(array)($input['params'] ?? []),(new DatasetScope())->resolve($definition,(int)getCurrentInfo()['id']));
    }
    public function executeVersion(int $id, ?int $version, array $params, ?int $userId = null, ?int $viewerId = null): array
    {
        try { return $this->executeVersionInternal($id, $version, $params, $userId, $viewerId); }
        catch (\InvalidArgumentException $error) { throw new ApiException($error->getMessage()); }
        catch (\PDOException) { throw new ApiException('查询执行失败，请检查字段、权限和数据源状态'); }
        catch (\RuntimeException $error) { if ($error instanceof ApiException) throw $error; throw new ApiException('数据源请求失败，请检查连接与响应配置'); }
    }
    private function executeVersionInternal(int $id, ?int $version, array $params, ?int $userId = null, ?int $viewerId = null): array
    {
        $dataset=$this->accessible($id,'use',$userId);
        if ((int)$dataset->status!==1) throw new ApiException('数据集已停用',403);
        $version ??= (int)$dataset->revision;
        $record=DatasetVersion::where(['dataset_id'=>$id,'version'=>$version])->findOrEmpty();
        if ($record->isEmpty()) throw new ApiException('数据集版本不存在',404);
        $source=$this->source((int)$record->datasource_id,false);
        (new ResourceAccess())->assert('datasource',(int)$source->id,(int)$source->created_by,'use',(int)$dataset->created_by);
        $definition=$record->definition;
        $rowScope=(new DatasetScope())->resolve($definition,$viewerId ?? ($userId===null?(int)(getCurrentInfo()['id'] ?? 0):null));
        $params=(new DatasetParameters())->resolve($definition['parameters'] ?? [],$params);
        ksort($params);
        $scope=$userId ?? (int)getCurrentInfo()['id'];
        $key='boardstudio:v2:dataset:'.hash('sha256',json_encode([$id,$version,$params,$scope,$rowScope,$source->update_time]));
        $ttl=(int)($definition['cacheSeconds'] ?? 0);
        return (new DatasetCache())->remember($key,$ttl,(int)($definition['timeoutMs'] ?? 5000),
            fn() => (new DatasetExecutor())->execute($source,$definition,$params,$rowScope));
    }
    public function grants(string $type, int $id): array
    {
        if ($type==='dataset') $record=$this->accessible($id,'manage');
        elseif ($type==='datasource') {
            $record=$this->source($id,false);
            (new ResourceAccess())->assert($type,$id,(int)$record->created_by,'manage');
        } elseif ($type==='screen') {
            $record=Screen::findOrEmpty($id);
            if($record->isEmpty())throw new ApiException('资源不存在或无权使用');
            (new ResourceAccess())->assert($type,$id,(int)$record->created_by,'manage');
        } else throw new ApiException('未知资源类型');
        return Db::table('boardstudio_resource_grant')->where(['resource_type'=>$type,'resource_id'=>$id])->select()->toArray();
    }
    public function setGrant(array $input): void
    {
        $type=(string)($input['resource_type'] ?? ''); $id=(int)($input['resource_id'] ?? 0);
        $this->grants($type,$id);
        $subject=(string)($input['subject_type'] ?? ''); $subjectId=(int)($input['subject_id'] ?? 0);
        $action=(string)($input['action'] ?? '');
        if (!in_array($subject,['user','role'],true) || $subjectId<=0 || !in_array($action,$type==='screen'?['view','manage']:($type==='datasource'?['use','manage']:['use','edit','manage']),true)) throw new ApiException('授权参数不合法');
        $row=['resource_type'=>$type,'resource_id'=>$id,'subject_type'=>$subject,'subject_id'=>$subjectId,'action'=>$action];
        if (!empty($input['remove'])) Db::table('boardstudio_resource_grant')->where($row)->delete();
        elseif (!Db::table('boardstudio_resource_grant')->where($row)->count()) Db::table('boardstudio_resource_grant')->insert($row);
    }
}
