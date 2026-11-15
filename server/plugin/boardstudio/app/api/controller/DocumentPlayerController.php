<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\api\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\boardstudio\app\model\Screen;
use plugin\boardstudio\app\model\ScreenToken;
use plugin\boardstudio\app\admin\logic\DatasetLogic;
use plugin\boardstudio\app\service\Responses;
use plugin\boardstudio\app\service\DocumentJson;
use plugin\boardstudio\app\service\PageParameters;
use plugin\boardstudio\app\service\RuntimeGuard;
use plugin\boardstudio\app\service\ResourceAccess;
use plugin\saiadmin\exception\ApiException;
use support\think\Db;
use support\Request;
use support\Response;

#[Apidoc\Group('Board Studio')]
#[Apidoc\Title('V2 大屏播放')]
final class DocumentPlayerController
{
    #[Apidoc\Title('读取发布文档')]
    #[Apidoc\Url('/app/boardstudio/api/document/{code}')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('token',type:'string',require:false,desc:'访问令牌')]
    #[Apidoc\Returned('document',type:'object',desc:'发布文档，不含查询定义和密钥')]
    public function read(Request $request,string $code): Response
    {
        return $this->respond(function() use($request,$code) {
            [$screen,$release,$document]=$this->context($request,$code);
            return ['name'=>$screen->name,'code'=>$screen->code,'releaseId'=>$release['id'],'hash'=>$release['content_hash'],
                'document'=>json_decode(DocumentJson::encode($document))];
        });
    }
    #[Apidoc\Title('读取发布数据绑定')]
    #[Apidoc\Url('/app/boardstudio/api/document-data')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('code',type:'string',require:true,desc:'大屏编码')]
    #[Apidoc\Param('bindingId',type:'string',require:true,desc:'发布文档内的数据绑定 ID')]
    #[Apidoc\Param('releaseId',type:'int',require:true,desc:'客户端加载的发布版本')]
    #[Apidoc\Param('parameters',type:'object',require:false,desc:'仅允许已声明的页面参数；数据集和绑定常量不能覆盖')]
    #[Apidoc\Returned('rows',type:'array',desc:'绑定结果')]
    public function data(Request $request): Response
    {
        return $this->respond(function() use($request) {
            [$screen,$release,$document]=$this->context($request,(string)$request->post('code'));
            if ((int)$request->post('releaseId')!==(int)$release['id']) throw new ApiException('发布版本已变更，请重新加载',409);
            $binding=$document['bindings'][(string)$request->post('bindingId')] ?? null;
            if (!$binding) throw new ApiException('发布版本中不存在此数据绑定',404);
            $resolver=new PageParameters();
            $binding=$resolver->binding($binding,$resolver->resolve($document,(array)$request->post('parameters',[])));
            // Client cannot choose a different dataset, version or privileged parameters.
            return (new DatasetLogic())->executeVersion((int)$binding['datasetId'],(int)$binding['datasetVersion'],(array)$binding['params'],(int)$screen->created_by,$screen->access_mode==='login'?(int)getCurrentInfo()['id']:null);
        });
    }
    private function context(Request $request,string $code): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/',$code)) throw new ApiException('大屏不存在',404);
        $screen=Screen::where(['code'=>$code,'status'=>1])->findOrEmpty();
        if ($screen->isEmpty() || !$screen->published_release_id) throw new ApiException('大屏尚未发布 V2 版本',404);
        $mode=(string)$screen->access_mode;
        if ($mode==='login') {
            $info=getCurrentInfo();
            if (!$info || ($info['plat'] ?? '')!=='saiadmin') throw new ApiException('请登录后访问',401);
            $account=\plugin\saiadmin\app\model\system\SystemUser::findOrEmpty((int)$info['id']);
            if($account->isEmpty() || (int)$account->status!==1)throw new ApiException('登录账号已失效',401);
            (new ResourceAccess())->assert('screen',(int)$screen->id,(int)$screen->created_by,'view',(int)$info['id']);
        } elseif ($mode==='token') {
            $token=(string)($request->header('x-boardstudio-token','') ?: $request->input('token',''));
            $record=ScreenToken::where(['screen_id'=>$screen->id,'token_hash'=>hash('sha256',$token),'status'=>1])->findOrEmpty();
            if (!$token || $record->isEmpty() || ($record->expire_time && strtotime((string)$record->expire_time)<=time())) throw new ApiException('访问令牌无效或已过期',401);
        } elseif ($mode!=='public') throw new ApiException('访问策略无效',403);
        $limited=(new RuntimeGuard())->assertAllowed($request,$screen,'data');
        if ($limited) throw new ApiException('请求过于频繁，请稍后重试',429);
        $release=Db::table('boardstudio_release')->where(['id'=>$screen->published_release_id,'screen_id'=>$screen->id])->find();
        if (!$release) throw new ApiException('发布版本不存在',404);
        return [$screen,$release,json_decode($release['document'],true,512,JSON_THROW_ON_ERROR)];
    }
    private function respond(callable $action): Response
    {
        try { return Responses::ok($action()); }
        catch (\InvalidArgumentException $error) { return Responses::fail($error->getMessage()); }
        catch (ApiException $error) { return Responses::fail($error->getMessage(),in_array($error->getCode(),[401,403,404,409,429],true)?$error->getCode():400); }
        catch (\Throwable) { return Responses::fail('数据暂时不可用，请联系管理员'); }
    }
}
