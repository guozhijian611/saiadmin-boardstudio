<?php
/** Destructive: only an explicitly disposable fixture database; never use on real data. */
declare(strict_types=1);
if(($argv[1]??'')!=='--disposable-fixture')throw new RuntimeException('Explicit disposable-fixture flag required');
require getcwd().'/vendor/autoload.php';require getcwd().'/support/bootstrap.php';
foreach(['boardstudio_screen_token','boardstudio_screen_version','boardstudio_query_template','boardstudio_datasource','boardstudio_retired_templates','boardstudio_screen','boardstudio_dataset_version','boardstudio_dataset','boardstudio_resource_grant','boardstudio_release'] as $table) {
    \support\think\Db::table($table)->delete(true);
}
echo "Cleared disposable plugin fixture rows only\n";
