<?php
declare(strict_types=1);
namespace plugin\saiadmin\exception { class ApiException extends \RuntimeException {} }
namespace {
require __DIR__.'/../../../server/plugin/boardstudio/app/service/RoleDataScope.php';
require __DIR__.'/../../../server/plugin/boardstudio/app/service/PermissionCatalog.php';
use plugin\boardstudio\app\service\RoleDataScope;
use plugin\boardstudio\app\service\PermissionCatalog;
function check(bool $ok): void { if (!$ok) throw new RuntimeException('Permission contract failed'); }
check(RoleDataScope::role([['id'=>20,'data_scope'=>1],['id'=>21,'data_scope'=>5]])['id']===21);
check(RoleDataScope::role([['id'=>20,'data_scope'=>2],['id'=>19,'data_scope'=>2]])['id']===19);
foreach ([[], [['id'=>1,'data_scope'=>0]], [['id'=>1,'data_scope'=>6]], [['id'=>1,'data_scope'=>1],['id'=>2,'data_scope'=>null]]] as $roles) {
    try { RoleDataScope::role($roles); throw new RuntimeException('Invalid role scope accepted'); }
    catch (\plugin\saiadmin\exception\ApiException) {}
}
$found=[];
foreach (glob(__DIR__.'/../../../server/plugin/boardstudio/app/admin/controller/*.php') as $file) {
    preg_match_all("/#\\[Permission\\('[^']*',\\s*'([^']*)'\\)\\]/", file_get_contents($file), $matches);
    foreach ($matches[1] as $slug) { check(isset(PermissionCatalog::ACTIONS[$slug])); $found[$slug]=true; }
}
check(count($found)===count(PermissionCatalog::ACTIONS));
$translations=json_decode(file_get_contents(__DIR__.'/../../../saiadmin-artd/src/views/plugin/boardstudio/i18n/permissions.json'),true,512,JSON_THROW_ON_ERROR);
foreach (PermissionCatalog::ACTIONS as $slug=>$action) {
    $key=substr(PermissionCatalog::title($slug),strlen('menus.boardstudio.actions.'));
    check(isset($translations[$key]['zh'],$translations[$key]['en']) && strlen(PermissionCatalog::title($slug))<=64);
}
echo "PASS role precedence, deterministic custom role, fail-closed invalid roles, complete action registry and bilingual titles\n";
}
