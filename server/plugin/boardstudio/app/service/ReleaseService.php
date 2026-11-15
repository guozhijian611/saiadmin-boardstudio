<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use plugin\boardstudio\app\admin\logic\DatasetLogic;
use plugin\boardstudio\app\model\DatasetVersion;
use plugin\boardstudio\app\model\Screen;
use plugin\saiadmin\exception\ApiException;
use support\think\Db;

final class ReleaseService
{
    public function check(array $document, int $owner, ?string $accessMode = null): array
    {
        (new DocumentValidator())->validate($document);
        $errors=[]; $warnings=[];
        $supported=['art-bar-chart','art-line-chart','art-h-bar-chart','art-dual-bar-compare-chart','art-k-line-chart','art-gauge-chart','art-funnel-chart','art-heatmap-chart','art-ring-chart','art-radar-chart','art-scatter-chart','stat-number','data-table','progress-rank','status-matrix','image-carousel','event-timeline','alarm-list','geo-point-map','decor-border','decor-flow-border','decor-scanline','decor-title','decor-divider'];
        $used=[];
        foreach ($document['nodes'] as $node) {
            if ($node['kind']!=='widget') continue;
            $type=$node['widget']['type'];
            if (!in_array($type,$supported,true)) $errors[]='未知组件：'.$node['name'];
            if (isset($node['binding'])) $used[$node['binding']['instanceId']]=true;
            elseif (!str_starts_with($type,'decor-') && $type!=='image-carousel') $warnings[]='组件仍使用示例数据：'.$node['name'];
        }
        foreach ($document['bindings'] as $key=>&$binding) {
            if (!isset($used[$key])) { unset($document['bindings'][$key]); continue; }
            try {
                $logic=new DatasetLogic();
                $dataset=$logic->accessible((int)$binding['datasetId'],'use',$owner);
                if ((int)$dataset->status!==1) throw new ApiException('数据集已停用');
                $version=(int)($binding['datasetVersion'] ?? $dataset->revision);
                $record=DatasetVersion::where(['dataset_id'=>$dataset->id,'version'=>$version])->findOrEmpty();
                if ($record->isEmpty()) throw new ApiException('数据集版本不存在');
                $scope=(new DatasetScope())->validate($record->definition);
                if($scope['mode']==='viewer' && $accessMode!==null && $accessMode!=='login')throw new ApiException('观看者范围数据集只能发布为登录模式');
                $source=$logic->source((int)$record->datasource_id,false);
                (new ResourceAccess())->assert('datasource',(int)$source->id,(int)$source->created_by,'use',(int)$dataset->created_by);
                $resolved=(new PageParameters())->binding($binding,(new PageParameters())->resolve($document));
                (new DatasetParameters())->resolve($record->definition['parameters'] ?? [],$resolved['params']);
                foreach($document['nodes'] as $node) {
                    if(($node['binding']['instanceId'] ?? null)===$key) (new DatasetFields())->mapping($node['binding']['mapping'] ?? [],$record->definition['fields'] ?? []);
                }
                $binding['datasetVersion']=$version;
            } catch (\InvalidArgumentException|ApiException $error) { $errors[]='数据绑定 '.$key.'：'.$error->getMessage(); }
        }
        unset($binding);
        return ['errors'=>$errors,'warnings'=>$warnings,'document'=>$document];
    }
    public function publish(Screen $screen, int $revision, string $mode, bool $acceptWarnings): array
    {
        if (!in_array($mode,['public','login','token'],true)) throw new ApiException('访问策略不正确');
        if ((int)$screen->revision!==$revision) throw new ApiException('草稿已变化，请重新加载',409);
        $document=json_decode((string)$screen->getData('draft_document'),true);
        if (!$document) throw new ApiException('请先保存 V2 文档');
        $check=$this->check($document,(int)$screen->created_by,$mode);
        if ($check['errors']) throw new ApiException(implode('；',$check['errors']));
        if ($check['warnings'] && !$acceptWarnings) throw new ApiException('发布包含示例数据，请先确认发布检查结果');
        $json=DocumentJson::encode($check['document']);
        $id=Db::table('boardstudio_release')->insertGetId(['screen_id'=>$screen->id,'revision'=>$revision,'document'=>$json,
            'content_hash'=>hash('sha256',$json),'created_by'=>(int)getCurrentInfo()['id'],'create_time'=>date('Y-m-d H:i:s')]);
        $screen->save(['published_release_id'=>$id,'access_mode'=>$mode,'status'=>1,'revision'=>$revision+1]);
        return ['releaseId'=>(int)$id,'revision'=>$revision+1,'hash'=>hash('sha256',$json)];
    }
}
