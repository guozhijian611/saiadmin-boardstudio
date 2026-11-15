<?php
namespace plugin\boardstudio\app\validate;
use plugin\saiadmin\basic\BaseValidate;
final class DatasetValidate extends BaseValidate
{
    protected $rule=['name'=>'require|max:60','datasource_id'=>'require|integer|gt:0','definition'=>'require|array','revision'=>'integer|egt:0','status'=>'in:1,2'];
    protected $scene=['save'=>['name','datasource_id','definition','revision','status'],'preview'=>['datasource_id','definition']];
}
