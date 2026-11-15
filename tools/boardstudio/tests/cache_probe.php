<?php
/** Run inside an isolated SaiAdmin server directory: php cache_probe.php KEY COUNTER [fail|nocache] */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
require getcwd().'/support/bootstrap.php';
if($argc<3)throw new InvalidArgumentException('Usage: cache_probe.php key counter [fail|nocache]');
$mode=$argv[3]??'';
try {
    $result=(new \plugin\boardstudio\app\service\DatasetCache())->remember('boardstudio:test:'.$argv[1],$mode==='nocache'?0:10,1000,function()use($argv,$mode){
        file_put_contents($argv[2],"query\n",FILE_APPEND|LOCK_EX);
        usleep(300000);
        if($mode==='fail')throw new RuntimeException('fixture failure');
        return ['rows'=>[['total'=>42]]];
    });
    echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $error) {
    if($mode!=='fail')throw $error;
    echo "expected failure\n";
}
