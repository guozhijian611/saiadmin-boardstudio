<?php
/** Capture/verify authorization ownership around an empty uninstall in a disposable lab. */
declare(strict_types=1);
if (($argv[1]??'')!=='--disposable-fixture') throw new RuntimeException('Disposable fixture flag required');
require getcwd().'/vendor/autoload.php'; require getcwd().'/support/bootstrap.php';
use support\think\Db;
$file=getcwd().'/.boardstudio-uninstall-permissions.json';
$shared=static fn(): array => [
    'roles'=>Db::table('sa_system_role')->order('id')->select()->toArray(),
    'departments'=>Db::table('sa_system_dept')->order('id')->select()->toArray(),
    'menus'=>Db::table('sa_system_menu')->where(function($query) { $query->whereNull('remark')->whereOr('remark','NOT LIKE','boardstudio:%'); })->order('id')->select()->toArray(),
];
if (($argv[2]??'')==='capture') {
    file_put_contents($file,json_encode(['menus'=>Db::table('sa_system_menu')->whereLike('remark','boardstudio:%')->column('id'),'shared'=>$shared()],JSON_THROW_ON_ERROR)); chmod($file,0600);
} elseif (($argv[2]??'')==='verify') {
    $before=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    if (Db::table('sa_system_role_menu')->whereIn('menu_id',$before['menus'])->count()) throw new RuntimeException('Plugin role-menu links survived uninstall');
    if ($shared()!==$before['shared']) throw new RuntimeException('Shared authorization resources changed during uninstall');
    echo "PASS uninstall removes own role-menu links and preserves system roles, departments and shared menus\n";
} else throw new RuntimeException('Choose capture or verify');
