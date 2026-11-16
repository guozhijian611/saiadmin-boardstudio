<?php
/** From an isolated SaiAdmin server root after real ZIP install and dependency installation. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';require getcwd().'/support/bootstrap.php';
use support\think\Db;
$tables=['boardstudio_screen','boardstudio_screen_version','boardstudio_screen_token','boardstudio_datasource','boardstudio_query_template','boardstudio_retired_templates','boardstudio_dataset','boardstudio_dataset_version','boardstudio_resource_grant','boardstudio_release'];
foreach($tables as $table)Db::table($table)->count();
$versions=Db::table('phinxlog_boardstudio')->column('version');
if(count($versions)!==10)throw new RuntimeException('Expected ten migrations');
foreach(['BoardStudio','BoardStudioDesigner','BoardStudioPresets','BoardStudioQueryTemplate'] as $code) {
 if(Db::table('sa_system_menu')->where('code',$code)->count()!==1)throw new RuntimeException('Missing or duplicate menu: '.$code);
}
if(Db::table('sa_system_menu')->whereLike('slug','boardstudio:market_item:%')->where('status',1)->count())throw new RuntimeException('Market permission still active');
if(!is_file(getcwd().'/runtime/boardstudio-credentials.key'))throw new RuntimeException('Missing credential key');
(new \plugin\boardstudio\app\service\DatasourceCredentials())->initialize();
(new \plugin\saipackage\app\logic\InstallLogic('boardstudio'))->dependentInstallComplete('npm');
$state=(new \plugin\saipackage\app\logic\InstallLogic('boardstudio'))->getInfo();
if((int)$state['state']!==\plugin\saipackage\app\logic\InstallLogic::INSTALLED)throw new RuntimeException('Dependencies not completed');
foreach (\plugin\boardstudio\app\service\PermissionCatalog::ACTIONS as $slug=>$item) {
 if(Db::table('sa_system_menu')->where('slug',$slug)->where('status',1)->count()!==1)throw new RuntimeException('Missing or duplicate permission: '.$slug);
}
echo 'PASS schema, ten migrations, unique menus and action points, retired market, key initialization, installed state '. $state['state']."\n";
