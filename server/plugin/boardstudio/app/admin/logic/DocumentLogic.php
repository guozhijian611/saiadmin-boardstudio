<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\admin\logic;

use plugin\boardstudio\app\model\Screen;
use plugin\boardstudio\app\service\DocumentValidator;
use plugin\boardstudio\app\service\DocumentJson;
use support\think\Db;
use plugin\boardstudio\app\service\ReleaseService;
use plugin\saiadmin\exception\ApiException;

final class DocumentLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new Screen();
    }

    public function document(int $id): array
    {
        $screen = $this->read($id);
        $document = json_decode((string) ($screen->getData('draft_document') ?? ''));
        return ['id' => $screen->id, 'name' => $screen->name, 'revision' => (int) $screen->revision,
            'code' => $screen->code, 'accessMode' => $screen->access_mode, 'publishedReleaseId' => $screen->published_release_id,
            'document' => $document, 'legacy_layout' => $document ? null : $screen->draft_layout];
    }

    public function saveDocument(int $id, int $revision, array $document): array
    {
        $document = (new DocumentValidator())->validate($document);
        return $this->transaction(function () use ($id, $revision, $document) {
            $screen = $this->userDataScope($this->model->newQuery())->lock(true)->findOrEmpty($id);
            if ($screen->isEmpty()) throw new ApiException('大屏不存在或无权访问');
            if ((int) $screen->revision !== $revision) throw new ApiException('大屏已被其他窗口修改，请重新加载', 409);
            foreach ($document['bindings'] as $binding) {
                (new DatasetLogic())->accessible((int)$binding['datasetId'],'use');
                (new DatasetLogic())->accessible((int)$binding['datasetId'],'use',(int)$screen->created_by);
            }
            $screen->save(['draft_document' => DocumentJson::encode($document), 'revision' => $revision + 1]);
            return ['revision' => $revision + 1];
        });
    }
    public function publishCheck(int $id): array
    {
        $screen=$this->read($id);
        $document=json_decode((string)$screen->getData('draft_document'),true);
        if (!$document) throw new ApiException('请先保存 V2 文档');
        $check=(new ReleaseService())->check($document,(int)$screen->created_by);
        unset($check['document']);
        return $check;
    }
    public function publishDocument(int $id,int $revision,string $mode,bool $acceptWarnings): array
    {
        return $this->transaction(function() use($id,$revision,$mode,$acceptWarnings) {
            $this->read($id);
            $screen=Screen::where('id',$id)->lock(true)->findOrEmpty();
            return (new ReleaseService())->publish($screen,$revision,$mode,$acceptWarnings);
        });
    }
    public function releases(int $id): array
    {
        $this->read($id);
        return Db::table('boardstudio_release')->where('screen_id',$id)->field('id,revision,content_hash,create_time,created_by')->order('id','desc')->limit(100)->select()->toArray();
    }
    public function activate(int $id,int $releaseId,int $revision): array
    {
        return $this->transaction(function() use($id,$releaseId,$revision) {
            $this->read($id);
            $screen=Screen::where('id',$id)->lock(true)->findOrEmpty();
            if ((int)$screen->revision!==$revision) throw new ApiException('草稿已变化，请重新加载',409);
            $release=Db::table('boardstudio_release')->where(['id'=>$releaseId,'screen_id'=>$id])->find();
            if (!$release) throw new ApiException('发布版本不存在');
            $check=(new ReleaseService())->check(json_decode($release['document'],true),(int)$screen->created_by,(string)$screen->access_mode);
            if ($check['errors']) throw new ApiException(implode('；',$check['errors']));
            $screen->save(['published_release_id'=>$releaseId,'status'=>1,'revision'=>$revision+1]);
            return ['revision'=>$revision+1];
        });
    }

}
