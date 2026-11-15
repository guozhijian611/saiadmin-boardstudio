<?php
namespace plugin\boardstudio\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\boardstudio\app\admin\logic\DatasetLogic;
use plugin\boardstudio\app\model\Datasource;
use plugin\boardstudio\app\service\ResourceAccess;
use plugin\boardstudio\app\service\DataSourceExecutor;
use plugin\boardstudio\app\validate\DatasetValidate;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

#[Apidoc\Group('Board Studio')]
#[Apidoc\Title('V2 数据集')]
final class DatasetController extends BaseController
{
    public function __construct() { $this->logic=new DatasetLogic(); $this->validate=new DatasetValidate(); parent::__construct(); }

    #[Apidoc\Title('可用数据集')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('items',type:'array',desc:'有权使用的数据集')]
    #[Permission('数据集列表','boardstudio:query_template:index')]
    public function index(Request $request): Response { return $this->success(['items'=>$this->logic->available()]); }

    #[Apidoc\Title('保存并创建数据集版本')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('name',type:'string',require:true,desc:'名称')]
    #[Apidoc\Param('datasource_id',type:'int',require:true,desc:'数据源 ID')]
    #[Apidoc\Param('definition',type:'object',require:true,desc:'查询、参数、输出字段契约与刷新策略；fields 为 name/label/type/nullable 数组')]
    #[Apidoc\Param('revision',type:'int',require:false,desc:'更新所需修订号')]
    #[Apidoc\Returned('revision',type:'int',desc:'保存后的修订号')]
    #[Permission('保存数据集','boardstudio:query_template:save')]
    public function save(Request $request): Response { $data=$request->post(); $this->validate('save',$data); return $this->success($this->logic->saveDefinition($data)); }

    #[Apidoc\Title('预览数据集查询')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/preview')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('definition',type:'object',require:true,desc:'查询定义')]
    #[Apidoc\Param('datasource_id',type:'int',require:true,desc:'数据源 ID')]
    #[Apidoc\Param('params',type:'object',require:false,desc:'预览参数')]
    #[Apidoc\Returned('rows',type:'array',desc:'有界查询结果')]
    #[Permission('预览数据集','boardstudio:query_template:preview')]
    public function preview(Request $request): Response { $data=$request->post(); $this->validate('preview',$data); return $this->success($this->logic->preview($data)); }

    #[Apidoc\Title('执行已保存数据集')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/execute')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id',type:'int',require:true,desc:'数据集 ID')]
    #[Apidoc\Param('version',type:'int',require:false,desc:'版本，默认最新')]
    #[Apidoc\Param('params',type:'object',require:false,desc:'参数')]
    #[Apidoc\Returned('rows',type:'array',desc:'结果')]
    #[Permission('执行数据集','boardstudio:query_template:preview')]
    public function execute(Request $request): Response { return $this->success($this->logic->executeVersion((int)$request->post('id'),$request->post('version')===null?null:(int)$request->post('version'),(array)$request->post('params',[]))); }

    #[Apidoc\Title('可用连接及结构')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/sources')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id',type:'int',require:false,desc:'读取指定数据源表结构')]
    #[Apidoc\Query('table',type:'string',require:false,desc:'读取指定表字段')]
    #[Apidoc\Returned('items',type:'array',desc:'不含凭据的数据源')]
    #[Permission('数据源结构','boardstudio:datasource:index')]
    public function sources(Request $request): Response {
        $id=(int)$request->get('id',0);
        if ($id) return $this->success((new DataSourceExecutor())->schema($this->logic->source($id),(string)$request->get('table','')));
        $items=[];$access=new ResourceAccess();
        foreach (Datasource::where('status',1)->select() as $source) if ($access->allows('datasource',(int)$source->id,(int)$source->created_by,'use')) $items[]=['id'=>$source->id,'name'=>$source->name,'type'=>$source->type];
        return $this->success(['items'=>$items]);
    }

    #[Apidoc\Title('资源授权列表')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/grants')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('resource_type',type:'string',require:true,desc:'dataset/datasource/screen')]
    #[Apidoc\Query('resource_id',type:'int',require:true,desc:'资源 ID')]
    #[Apidoc\Returned('items',type:'array',desc:'授权记录')]
    #[Permission('管理数据集授权','boardstudio:query_template:update')]
    public function grants(Request $request): Response { return $this->success(['items'=>$this->logic->grants((string)$request->get('resource_type'),(int)$request->get('resource_id'))]); }

    #[Apidoc\Title('更新资源授权')]
    #[Apidoc\Url('/app/boardstudio/admin/Dataset/grant')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('resource_type',type:'string',require:true,desc:'dataset/datasource/screen')]
    #[Apidoc\Param('resource_id',type:'int',require:true,desc:'资源 ID')]
    #[Apidoc\Param('subject_type',type:'string',require:true,desc:'user/role')]
    #[Apidoc\Param('subject_id',type:'int',require:true,desc:'用户或角色 ID')]
    #[Apidoc\Param('action',type:'string',require:true,desc:'数据集 use/edit/manage；数据源 use/manage；大屏 view/manage')]
    #[Apidoc\Param('remove',type:'boolean',require:false,desc:'撤销授权')]
    #[Apidoc\Returned('message',type:'string',desc:'处理结果')]
    #[Permission('管理数据集授权','boardstudio:query_template:update')]
    public function grant(Request $request): Response { $this->logic->setGrant($request->post()); return $this->success('授权已更新'); }
}
