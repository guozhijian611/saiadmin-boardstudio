<?php
namespace plugin\saiadmin\exception { class ApiException extends \RuntimeException {} }
namespace {
require __DIR__ . '/../../../server/plugin/boardstudio/app/service/LayoutValidator.php';
$validator = new \plugin\boardstudio\app\service\LayoutValidator();
$layout = ['canvas' => ['width' => 1920, 'height' => 1080], 'components' => [
    ['id' => 'w_1', 'rect' => ['x' => 0, 'y' => 0, 'w' => 200, 'h' => 100, 'z' => 1]],
]];
assert($validator->validate($layout) === $layout);
$invalid = [];
$invalid[] = array_replace($layout, ['components' => array_merge($layout['components'], $layout['components'])]);
$invalid[] = array_replace($layout, ['canvas' => ['width' => 999999, 'height' => 100]]);
$invalid[] = array_replace($layout, ['components' => [['id' => 'bad id', 'rect' => []]]]);
$bad = $layout; $bad['components'][0]['rect']['w'] = -1; $invalid[] = $bad;
$bad = $layout; $bad['components'][0]['rect']['x'] = 'NaN'; $invalid[] = $bad;
foreach ($invalid as $value) {
    try { $validator->validate($value); throw new \RuntimeException('Accepted invalid layout'); }
    catch (\plugin\saiadmin\exception\ApiException) {}
}
echo 'PASS valid geometry, duplicate IDs, canvas bounds, malformed IDs, negative dimensions, nonnumeric positions' . PHP_EOL;
}
