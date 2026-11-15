<?php
require __DIR__.'/../../../server/plugin/boardstudio/app/service/DatasourceCredentials.php';
use plugin\boardstudio\app\service\DatasourceCredentials;
$key = base64_encode(random_bytes(32));
$vault = new DatasourceCredentials($key);
$config = ['host'=>'db','password'=>'sentinel-secret','headers'=>['Authorization'=>'private-header'],'params'=>['api_key'=>'private-query']];
$sealed = $vault->seal($config);
assert(!str_contains($sealed,'sentinel-secret') && !str_contains($sealed,'private-header'));
assert($vault->open($sealed) === $config);
assert($sealed !== $vault->seal($config));
$masked = $vault->mask($config);
assert(!str_contains(json_encode($masked),'sentinel-secret'));
assert($vault->merge($masked,$config)===$config);
assert($vault->merge(['password'=>''],$config)['password']==='sentinel-secret');
assert($vault->merge(['password'=>''],$config,true)['password']==='');
assert($vault->merge(['headers'=>[]],$config)['headers']===[]);
try {(new DatasourceCredentials(base64_encode(random_bytes(32))))->open($sealed);throw new Exception('Wrong key accepted');}catch(RuntimeException){}
$bad=json_decode($sealed,true);$bad['data']=base64_encode('tampered');
try {$vault->open($bad);throw new Exception('Tampering accepted');}catch(RuntimeException){}
try {$vault->merge(['headers'=>['Unknown'=>DatasourceCredentials::MASK]],$config);throw new Exception('Unknown masked field accepted');}catch(InvalidArgumentException){}
echo "PASS encrypted storage, random nonce, masking, preserve/clear, wrong key and tamper rejection\n";
