<?php
require __DIR__.'/../../../server/plugin/boardstudio/app/service/DatasetSql.php';
require __DIR__.'/../../../server/plugin/boardstudio/app/service/DatasetParameters.php';
use plugin\boardstudio\app\service\DatasetSql;
use plugin\boardstudio\app\service\DatasetParameters;
$compiler=new DatasetSql();
$schema=['orders'=>['id','customer_id','amount'],'customers'=>['id','region']];
$config=['table'=>'orders','joins'=>[['type'=>'LEFT','table'=>'customers','left'=>'t0.customer_id','right'=>'t1.id']],
    'columns'=>[['field'=>'t1.region','alias'=>'region'],['field'=>'t0.amount','aggregate'=>'SUM','alias'=>'total']],
    'conditions'=>[['field'=>'t1.region','operator'=>'=','parameter'=>'region']], 'groupBy'=>['t1.region'],'orderBy'=>[['field'=>'total','direction'=>'DESC']]];
[$sql,$bindings]=$compiler->visual($config,$schema,['region'=>"east' OR 1=1"]);
assert(str_contains($sql,'LEFT JOIN `customers` AS `t1`'));
assert(str_contains($sql,'SUM(`t0`.`amount`) AS `total`'));
assert(!str_contains($sql,'OR 1=1'));
assert($bindings===["east' OR 1=1"]);
[$sql,$bindings]=$compiler->custom('SELECT DATE(created_at) AS day, COUNT(*) AS n FROM orders WHERE region = :region GROUP BY DATE(created_at)', ['region'=>'east']);
assert($bindings===['east']); assert(str_contains($sql,'?'));
$invalid=['SELECT 1; DROP TABLE orders','DELETE FROM orders','SELECT SLEEP(20)','SELECT `SLEEP`(20)','SELECT mysql.some_function(1)','SELECT * FROM (SELECT * FROM orders) x','SELECT 1 UNION SELECT 2','SELECT 1 INTO OUTFILE \'/tmp/data\'','SELECT 1 -- comment','SELECT @secret','SELECT /* comment */ 1'];
foreach($invalid as $sql) {
    try {$compiler->custom($sql,[]);throw new RuntimeException('Accepted dangerous SQL: '.$sql);}
    catch(InvalidArgumentException) {}
}
$p=new DatasetParameters();
assert($p->resolve(['n'=>['type'=>'number','required'=>true]],['n'=>'12'])===['n'=>12]);
foreach([[],['n'=>'wrong'],['n'=>1,'unknown'=>2]] as $values) {
    try {$p->resolve(['n'=>['type'=>'number','required'=>true]],$values);throw new RuntimeException('Accepted invalid parameter');}
    catch(InvalidArgumentException) {}
}
echo "PASS relational joins, SQL bindings, restricted SQL grammar and typed parameters\n";

require __DIR__.'/../../../server/plugin/boardstudio/app/service/PageParameters.php';
$resolver=new \plugin\boardstudio\app\service\PageParameters();
$document=['parameters'=>['customer'=>['type'=>'number','default'=>1]]];
$binding=['params'=>['customer'=>['pageParameter'=>'customer']]];
assert($resolver->binding($binding,$resolver->resolve($document,['customer'=>'2']))['params']['customer']===2);
try {$resolver->resolve($document,['admin'=>1]);throw new RuntimeException('Accepted undeclared parameter');}catch(InvalidArgumentException){}
echo "PASS server-side page parameter resolution\n";

$compiler=new \plugin\boardstudio\app\service\DatasetSql();
[$sql,$values]=$compiler->custom('SELECT SUM(amount) AS total FROM orders t0 WHERE region = :region OR status = :status GROUP BY region ORDER BY total LIMIT :limit',['region'=>'east','status'=>1,'limit'=>20],['field'=>'t0.owner_id','value'=>42]);
assert(str_contains($sql,'WHERE ( region = ? OR status = ? ) AND `t0`.`owner_id` = ? GROUP'));
assert($values===['east',1,42,20]);
[$sql,$values]=$compiler->visual(['table'=>'orders','columns'=>[['field'=>'t0.amount','aggregate'=>'SUM','alias'=>'total']]],['orders'=>['amount','owner_id']],[],['field'=>'t0.owner_id','value'=>42]);
assert(str_contains($sql,'WHERE `t0`.`owner_id` = ?') && $values===[42]);
echo "PASS mandatory pre-aggregation viewer scope and SQL binding order\n";

require __DIR__.'/../../../server/plugin/boardstudio/app/service/DatasetScope.php';
$scopeValidator=new \plugin\boardstudio\app\service\DatasetScope();
assert($scopeValidator->validate(['mode'=>'sql'])===['mode'=>'shared']);
assert($scopeValidator->validate(['mode'=>'sql','scope'=>['mode'=>'viewer','identity'=>'department','field'=>'t0.dept_id']])['identity']==='department');
foreach([
 ['mode'=>'http','scope'=>['mode'=>'viewer','identity'=>'user','field'=>'t0.id']],
 ['mode'=>'sql','scope'=>['mode'=>'viewer','identity'=>'tenant','field'=>'t0.id']],
 ['mode'=>'sql','scope'=>['mode'=>'viewer','identity'=>'user','field'=>'t0.id OR 1=1']],
] as $definition){try{$scopeValidator->validate($definition);throw new RuntimeException('invalid scope accepted');}catch(InvalidArgumentException){}}
echo "PASS scope configuration rejects unsupported identity and unsafe field\n";

require __DIR__.'/../../../server/plugin/boardstudio/app/service/DatasetFields.php';
$f=new \plugin\boardstudio\app\service\DatasetFields();
$fields=[['name'=>'total','label'=>'合计','type'=>'number','nullable'=>false],['name'=>'active','label'=>'有效','type'=>'boolean','nullable'=>true]];
assert($f->apply([['total'=>'12345678901234567890.01','active'=>'1','secret'=>'omit']],$fields)===[['total'=>'12345678901234567890.01','active'=>true]]);
assert($f->apply([],$fields)===[]);
foreach([[['total'=>null,'active'=>true]],[['total'=>true,'active'=>true]],[['total'=>'bad','active'=>false]],[['total'=>1]],[['total'=>1,'active'=>'yes']]] as $rows){try{$f->apply($rows,$fields);throw new RuntimeException('Accepted invalid contract row');}catch(InvalidArgumentException){}}
try{$f->validate([$fields[0],$fields[0]]);throw new RuntimeException('Accepted duplicate field');}catch(InvalidArgumentException){}
try{$f->mapping(['valueField'=>'active'],$fields);throw new RuntimeException('Accepted nonnumeric value mapping');}catch(InvalidArgumentException){}
try{$f->mapping(['tableFields'=>['missing']],$fields);throw new RuntimeException('Accepted missing table mapping');}catch(InvalidArgumentException){}
$f->mapping(['valueField'=>'total'],$fields);
echo "PASS output contract projection, precision, nulls, types and component mapping\n";
