<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\boardstudio\app\admin\logic\DocumentLogic;
use plugin\boardstudio\app\validate\ScreenValidate;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

#[Apidoc\Group('Board Studio')]
#[Apidoc\Title('V2 大屏文档')]
final class DocumentController extends BaseController
{
    public function __construct()
    {
        $this->logic = new DocumentLogic();
        $this->validate = new ScreenValidate();
        parent::__construct();
    }

    #[Apidoc\Title('读取 V2 文档')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '大屏 ID')]
    #[Apidoc\Returned('document', type: 'object', desc: 'V2 文档；尚未转换时返回旧布局')]
    #[Permission('大屏读取', 'boardstudio:screen:read')]
    public function read(Request $request): Response
    {
        return $this->success($this->logic->document((int) $request->get('id', 0)));
    }

    #[Apidoc\Title('保存 V2 文档')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏 ID')]
    #[Apidoc\Param('revision', type: 'int', require: true, desc: '当前修订版本')]
    #[Apidoc\Param('document', type: 'object', require: true, desc: 'schemaVersion=2 文档')]
    #[Apidoc\Returned('revision', type: 'int', desc: '保存后的版本')]
    #[Permission('保存大屏布局', 'boardstudio:screen:saveLayout')]
    public function save(Request $request): Response
    {
        $data = $request->post();
        $this->validate('revision', $data);
        $document = $data['document'] ?? null;
        if (!is_array($document)) return $this->fail('文档格式不正确');
        return $this->success($this->logic->saveDocument((int) ($data['id'] ?? 0), (int) $data['revision'], $document));
    }
    #[Apidoc\Title('发布检查')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/check')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Param('id', type:'int', require:true, desc:'大屏 ID')]
    #[Apidoc\Param('revision', type:'int', require:false, desc:'修改所需修订号')]
    #[Apidoc\Returned('data', type:'object', desc:'发布检查结果')]
    #[Permission('发布检查', 'boardstudio:screen:read')]
    public function check(Request $request): Response { return $this->success($this->logic->publishCheck((int)$request->get('id'))); }

    #[Apidoc\Title('发布历史')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/releases')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Param('id', type:'int', require:true, desc:'大屏 ID')]
    #[Apidoc\Param('revision', type:'int', require:false, desc:'修改所需修订号')]
    #[Apidoc\Returned('data', type:'object', desc:'发布历史结果')]
    #[Permission('发布历史', 'boardstudio:screen:versions')]
    public function releases(Request $request): Response { return $this->success(['items'=>$this->logic->releases((int)$request->get('id'))]); }

    #[Apidoc\Title('发布 V2 文档')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/publish')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type:'int', require:true, desc:'大屏 ID')]
    #[Apidoc\Param('revision', type:'int', require:false, desc:'修改所需修订号')]
    #[Apidoc\Returned('data', type:'object', desc:'发布 V2 文档结果')]
    #[Permission('发布 V2 文档', 'boardstudio:screen:publish')]
    public function publish(Request $request): Response { $this->validate('publishV2',$request->post()); return $this->success($this->logic->publishDocument((int)$request->post('id'),(int)$request->post('revision'),(string)$request->post('accessMode'),(bool)$request->post('acceptWarnings',false))); }

    #[Apidoc\Title('切换发布版本')]
    #[Apidoc\Url('/app/boardstudio/admin/Document/activate')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type:'int', require:true, desc:'大屏 ID')]
    #[Apidoc\Param('revision', type:'int', require:false, desc:'修改所需修订号')]
    #[Apidoc\Returned('data', type:'object', desc:'切换发布版本结果')]
    #[Permission('切换发布版本', 'boardstudio:screen:publish')]
    public function activate(Request $request): Response { $this->validate('activateV2',$request->post()); return $this->success($this->logic->activate((int)$request->post('id'),(int)$request->post('releaseId'),(int)$request->post('revision'))); }

}
