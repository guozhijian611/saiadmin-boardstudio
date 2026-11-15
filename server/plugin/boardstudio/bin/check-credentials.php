<?php
/** Run from the host server root: php plugin/boardstudio/bin/check-credentials.php */
declare(strict_types=1);
require dirname(__DIR__, 3) . '/vendor/autoload.php';
require dirname(__DIR__, 3) . '/support/bootstrap.php';
try {
    $vault = new \plugin\boardstudio\app\service\DatasourceCredentials();
    $count = 0;
    foreach (\support\think\Db::table('boardstudio_datasource')->cursor() as $row) {
        $stored = json_decode($row['config'], true, 512, JSON_THROW_ON_ERROR);
        if (!isset($stored['_boardstudio_encrypted'])) throw new \RuntimeException('仍有未加密的历史数据源，请执行插件迁移');
        $vault->open($stored);
        $count++;
    }
    echo 'PASS: ' . $count . " encrypted connection configurations readable\n";
} catch (\Throwable) {
    fwrite(STDERR, "FAIL: credential check failed; verify migration and restore the original key. No secrets displayed.\n");
    exit(1);
}
