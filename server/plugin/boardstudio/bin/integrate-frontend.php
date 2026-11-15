#!/usr/bin/env php
<?php
/** Optional repair/preview utility. Normal installs execute migration 9 automatically. */
declare(strict_types=1);
require __DIR__.'/../app/service/FrontendIntegration.php';
$options = getopt('', ['frontend:', 'runtime:', 'apply', 'remove', 'help']);
if (isset($options['help']) || empty($options['frontend'])) {
    echo "Usage: php integrate-frontend.php --frontend /path/to/frontend [--runtime /path/to/runtime/boardstudio] [--apply] [--remove]\n";
    exit(isset($options['help']) ? 0 : 1);
}
$runtime = $options['runtime'] ?? dirname(__DIR__, 3).'/runtime/boardstudio';
$service = new \plugin\boardstudio\app\service\FrontendIntegration($options['frontend'], $runtime);
$remove = isset($options['remove']);
foreach ($service->plan($remove) as $change) echo ($remove ? 'Remove integration: ' : 'Integrate: ').$change['path']."\n";
if (isset($options['apply'])) {
    if ($remove) $service->remove(); else $service->apply();
    echo "Frontend integration completed\n";
} else echo "Preview only; pass --apply to write changes\n";
