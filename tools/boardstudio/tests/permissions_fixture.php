<?php
/** CLI only, copied to a disposable lab by permissions_http.py; never packaged as application code. */
declare(strict_types=1);
namespace plugin\boardstudio\app\service {
    // Only the CLI probe uses this request identity substitute; the HTTP app never loads this file.
    function getCurrentInfo(): array|bool { return isset($GLOBALS['permissionActor']) ? ['id'=>$GLOBALS['permissionActor']] : \getCurrentInfo(); }
}
namespace {
if (($argv[1] ?? '') !== '--disposable-fixture') throw new RuntimeException('Disposable fixture flag required');
require getcwd().'/vendor/autoload.php'; require getcwd().'/support/bootstrap.php';
use support\think\Db;
use plugin\boardstudio\app\service\PermissionIntegration;
use plugin\boardstudio\app\service\DocumentJson;
use plugin\boardstudio\app\service\DatasourceCredentials;
use plugin\boardstudio\app\admin\logic\ScreenLogic;
use plugin\saiadmin\app\cache\UserInfoCache;
$file=getcwd().'/.boardstudio-permissions.json'; $mode=$argv[2] ?? 'setup';
function clearPermissions(): void {
    global $state;
    \plugin\saiadmin\app\cache\UserMenuCache::clearMenuCache();
    \support\think\Cache::tag(\plugin\saiadmin\app\cache\UserAuthCache::cacheConfig()['tag'])->clear();
    foreach ($state['users'] ?? [] as $id) UserInfoCache::clearUserInfo($id);
}
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
if (in_array($mode,['setup','reseed'],true)) {
    if ($mode==='setup' && is_file($file)) throw new RuntimeException('Use a fresh fixture state');
    Db::startTrans();
    try {
    if ($mode==='setup') {
    $tag=bin2hex(random_bytes(4)); $state=['prefix'=>'perm_'.$tag,'users'=>[],'roles'=>[],'tokens'=>[],'screens'=>[],'sources'=>[],'datasets'=>[],'queries'=>[]];
    $a=(int)Db::table('sa_system_dept')->insertGetId(['parent_id'=>0,'name'=>'perm_A_'.$tag,'level'=>'0,','status'=>1]);
    $sub=(int)Db::table('sa_system_dept')->insertGetId(['parent_id'=>$a,'name'=>'perm_child_'.$tag,'level'=>'0,'.$a.',','status'=>1]);
    $b=(int)Db::table('sa_system_dept')->insertGetId(['parent_id'=>0,'name'=>'perm_B_'.$tag,'level'=>'0,','status'=>1]);
    $state['departments']=[$a,$sub,$b];
    foreach (['actor','creators','menuOnly','actionOnly','none'] as $key) {
        $state['roles'][$key]=(int)Db::table('sa_system_role')->insertGetId(['name'=>'bs_'.$key.'_'.$tag[0], 'code'=>'bs_'.$key.'_'.$tag,'level'=>10,'data_scope'=>5,'status'=>1]);
    }
    $password=bin2hex(random_bytes(8)); $state['browserPassword']=$password;
    foreach (['actor'=>$a,'peer'=>$a,'child'=>$sub,'other'=>$b,'unbound'=>0,'noRole'=>$a,'menuOnly'=>$a,'actionOnly'=>$a,'none'=>$a] as $key=>$dept) {
        $username='bs_'.$tag.'_'.$key;
        $id=(int)Db::table('sa_system_user')->insertGetId(['username'=>$username,'password'=>password_hash($password,PASSWORD_DEFAULT),'realname'=>$key,'dept_id'=>$dept,'status'=>1]);
        $state['users'][$key]=$id; $state['usernames'][$key]=$username;
        if ($key!=='noRole') {
            $role=in_array($key,['actor','menuOnly','actionOnly','none'],true)?$key:'creators';
            Db::table('sa_system_user_role')->insert(['user_id'=>$id,'role_id'=>$state['roles'][$role]]);
        }
        $token=\Tinywan\Jwt\JwtToken::generateToken(['id'=>$id,'plat'=>'saiadmin','username'=>$username]);
        $state['tokens'][$key]=$token['access_token'];
    }
    } else {
        $state=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        foreach ($state['users'] as $key=>$id) check(Db::table('sa_system_user')->where('id',$id)->value('username')===$state['usernames'][$key],'Fixture account changed');
        foreach ($state['roles'] as $id) Db::table('sa_system_role')->where('id',$id)->update(['data_scope'=>5,'status'=>1]);
    }
    $menus=Db::table('sa_system_menu')->whereLike('remark','boardstudio:%')->where('status',1)->select()->toArray();
    foreach (['actor','creators','menuOnly','actionOnly'] as $key) foreach ($menus as $menu) {
        if ($key==='menuOnly' && (int)$menu['type']===3) continue;
        if ($key==='actionOnly' && (int)$menu['type']!==3) continue;
        $assignment=['role_id'=>$state['roles'][$key],'menu_id'=>$menu['id']];
        if (!Db::table('sa_system_role_menu')->where($assignment)->count()) Db::table('sa_system_role_menu')->insert($assignment);
    }
    $doc=['schemaVersion'=>2,'page'=>['width'=>800,'height'=>600,'sizing'=>'fixed','background'=>'#07111f'],'rootId'=>'root','nodes'=>[
        'root'=>['id'=>'root','kind'=>'container','name'=>'root','hidden'=>false,'locked'=>false,
        'placement'=>['x'=>0,'y'=>0,'width'=>800,'height'=>600,'zIndex'=>1,'grow'=>0,'shrink'=>1,'basis'=>'auto','columnSpan'=>1,'rowSpan'=>1],
        'appearance'=>['background'=>'transparent','color'=>'#ffffff','borderColor'=>'#000000','borderWidth'=>0,'borderRadius'=>0,'opacity'=>1],
        'children'=>[],'layout'=>['mode'=>'free','direction'=>'row','gap'=>0,'padding'=>0,'align'=>'stretch','justify'=>'start','wrap'=>false,'columns'=>3,'rowHeight'=>180]]], 'bindings'=>[], 'parameters'=>[]];
    $definition=['mode'=>'http','scope'=>['mode'=>'shared'],'fields'=>[['name'=>'value','label'=>'value','type'=>'number','nullable'=>false]],'query'=>['path'=>'/permission-data.json','method'=>'GET','response_path'=>'rows'],'parameters'=>(object)[], 'refreshSeconds'=>30,'cacheSeconds'=>30,'limit'=>500,'timeoutMs'=>5000];
    foreach (['actor','peer','child','other'] as $key) {
        $owner=$state['users'][$key]; $name=$state['prefix'].'_'.$key;
        $state['screens'][$key]=(int)Db::table('boardstudio_screen')->insertGetId(['name'=>$name,'code'=>$name,'width'=>800,'height'=>600,'status'=>2,'is_public'=>2,'created_by'=>$owner,'draft_document'=>DocumentJson::encode($doc)]);
        $config=(new DatasourceCredentials())->seal(['url'=>'http://backend:8787','method'=>'GET','headers'=>[],'params'=>[]]);
        $source=(int)Db::table('boardstudio_datasource')->insertGetId(['name'=>$name,'type'=>'http','config'=>$config,'status'=>1,'created_by'=>$owner]);
        $dataset=(int)Db::table('boardstudio_dataset')->insertGetId(['name'=>$name,'datasource_id'=>$source,'definition'=>json_encode($definition),'revision'=>1,'status'=>1,'created_by'=>$owner]);
        Db::table('boardstudio_dataset_version')->insert(['dataset_id'=>$dataset,'version'=>1,'datasource_id'=>$source,'definition'=>json_encode($definition),'created_by'=>$owner]);
        $state['sources'][$key]=$source; $state['datasets'][$key]=$dataset;
        $state['queries'][$key]=(int)Db::table('boardstudio_query_template')->insertGetId(['name'=>$name,'datasource_id'=>$source,'dataset_type'=>'http','config'=>'{}','status'=>1,'created_by'=>$owner]);
        foreach (['datasource'=>$source,'dataset'=>$dataset] as $type=>$id) Db::table('boardstudio_resource_grant')->insert(['resource_type'=>$type,'resource_id'=>$id,'subject_type'=>'user','subject_id'=>$state['users']['actor'],'action'=>'manage']);
    }
    $state['document']=$doc; $state['definition']=$definition;
    Db::commit();
    } catch (\Throwable $error) { Db::rollback(); throw $error; }
    file_put_contents($file,json_encode($state,JSON_THROW_ON_ERROR)); chmod($file,0600);
    clearPermissions(); echo "PASS disposable permission fixture created\n";
    exit;
}
$state=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if ($mode==='custom') {
    $role=$state['roles']['actor']; Db::table('sa_system_role_dept')->where('role_id',$role)->delete();
    if (($argv[3]??'')!=='empty') Db::table('sa_system_role_dept')->insert(['role_id'=>$role,'dept_id'=>$state['departments'][2]]);
    clearPermissions();
} elseif ($mode==='probe') {
    $role=$state['roles']['actor']; Db::table('sa_system_role')->where('id',$role)->update(['data_scope'=>5]);
    $logic=new ScreenLogic();
    $GLOBALS['permissionActor']=$state['users']['actor']; check($logic->read($state['screens']['actor'])->id==$state['screens']['actor'],'Own record denied');
    $GLOBALS['permissionActor']=$state['users']['peer'];
    try { $logic->read($state['screens']['actor']); throw new RuntimeException('Reused instance leaked preceding user'); } catch (\plugin\saiadmin\exception\ApiException) {}
    $GLOBALS['permissionActor']=$state['users']['noRole'];
    try { $logic->read($state['screens']['actor']); throw new RuntimeException('Empty roles allowed'); } catch (\plugin\saiadmin\exception\ApiException) {}
    $GLOBALS['permissionActor']=$state['users']['unbound'];
    Db::table('sa_system_role')->where('id',$state['roles']['creators'])->update(['data_scope'=>3]);
    check($logic->getAll($logic->search())===[],'Unbound department allowed');
    Db::table('sa_system_role')->where('id',$state['roles']['creators'])->update(['data_scope'=>5]);
    Db::table('sa_system_role')->where('id',$role)->update(['data_scope'=>0]);
    $GLOBALS['permissionActor']=$state['users']['actor'];
    try { $logic->read($state['screens']['actor']); throw new RuntimeException('Invalid role scope allowed'); } catch (\plugin\saiadmin\exception\ApiException) {}
    Db::table('sa_system_role')->where('id',$role)->update(['data_scope'=>5]);
    $snapshot=Db::table('sa_system_role_menu')->order('id')->select()->toArray();
    (new PermissionIntegration(Db::connect()->getPdo()))->apply();
    check(Db::table('sa_system_role_menu')->order('id')->select()->toArray()===$snapshot,'Repeated integration changed grants');
    echo "PASS real DB: reused instance, no roles, invalid scope, unbound department, idempotent permissions\n";
} elseif ($mode==='upgrade-check') {
    foreach (\plugin\boardstudio\app\service\PermissionCatalog::ACTIONS as $slug=>$action) {
        if ($action['legacy']===null) continue;
        $menu=Db::table('sa_system_menu')->where('slug',$slug)->value('id');
        check((bool)Db::table('sa_system_role_menu')->where(['role_id'=>$state['roles']['actor'],'menu_id'=>$menu])->count(),'Existing API authorization not migrated: '.$slug);
        check(!Db::table('sa_system_role_menu')->where(['role_id'=>$state['roles']['none'],'menu_id'=>$menu])->count(),'Unprivileged role received new right');
    }
    foreach ($state['screens'] as $key=>$id) check(Db::table('boardstudio_screen')->where('id',$id)->value('created_by')==$state['users'][$key],'Upgrade reassigned ownership');
    echo "PASS upgrade preserves creators and maps only existing role rights\n";
} elseif (in_array($mode,['ui-full','ui-readonly','ui-role-tree'],true)) {
    $role=$state['roles']['actor'];
    Db::table('sa_system_role_menu')->where('role_id',$role)->delete();
    $read=['boardstudio:screen:index','boardstudio:screen:read','boardstudio:dataset:index','boardstudio:datasource:index'];
    $menus=Db::table('sa_system_menu')->whereLike('remark','boardstudio:%')->where('status',1)->select()->toArray();
    $ids=[];
    foreach($menus as $menu) if($mode!=='ui-readonly'||(int)$menu['type']!==3||in_array($menu['slug'],$read,true)) $ids[]=(int)$menu['id'];
    if($mode==='ui-role-tree') {
        foreach (Db::table('sa_system_menu')->whereIn('slug',['core:role:index','core:role:menu'])->select()->toArray() as $menu) {
            $ids[]=(int)$menu['id'];$parent=(int)$menu['parent_id'];
            while($parent>0&&!in_array($parent,$ids,true)) {$ids[]=$parent;$parent=(int)Db::table('sa_system_menu')->where('id',$parent)->value('parent_id');}
        }
    }
    foreach(array_unique($ids) as $id)Db::table('sa_system_role_menu')->insert(['role_id'=>$role,'menu_id'=>$id]);
    Db::table('sa_system_role')->where('id',$role)->update(['data_scope'=>5,'level'=>$mode==='ui-role-tree'?90:10]);
    clearPermissions();echo 'PASS disposable browser fixture mode: '.$mode.PHP_EOL;
} else throw new RuntimeException('Unknown fixture command');
}
