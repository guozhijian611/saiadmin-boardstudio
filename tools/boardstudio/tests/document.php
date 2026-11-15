<?php
namespace plugin\saiadmin\exception { class ApiException extends \RuntimeException {} }
namespace {
require __DIR__.'/../../../server/plugin/boardstudio/app/service/DocumentValidator.php';
require __DIR__.'/../../../server/plugin/boardstudio/app/service/DocumentJson.php';
$validator = new \plugin\boardstudio\app\service\DocumentValidator();
$doc = json_decode(file_get_contents(__DIR__.'/fixtures/document.json'), true, 512, JSON_THROW_ON_ERROR);
assert($validator->validate($doc) === $doc);
$decoded = json_decode(\plugin\boardstudio\app\service\DocumentJson::encode($doc));
assert(is_object($decoded->bindings));
assert(is_object($decoded->parameters));
assert(is_object($decoded->nodes->status_matrix->widget->props));
assert(is_array($decoded->nodes->root->children));
$cases = [];
$v=$doc; $v['nodes']['root']['children'][]='root'; $cases[]=$v;
$v=$doc; $v['nodes']['trend']['placement']['width']=-1; $cases[]=$v;
$v=$doc; $v['nodes']['orphan']=$v['nodes']['trend']; $cases[]=$v;
$v=$doc; $v['nodes']['root']['layout']['columns']=0; $cases[]=$v;
$v=$doc; $v['nodes']['trend']['binding']=['instanceId'=>'missing']; $cases[]=$v;
foreach ($cases as $value) {
    try { $validator->validate($value); throw new \RuntimeException('Accepted invalid document'); }
    catch (\plugin\saiadmin\exception\ApiException) {}
}
echo "PASS PHP document tree, layout, binding and geometry validation\n";
}
