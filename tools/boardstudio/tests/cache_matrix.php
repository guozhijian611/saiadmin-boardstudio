<?php
/** From a disposable SaiAdmin server root, run after copying cache_probe.php next to this file. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';require getcwd().'/support/bootstrap.php';
$probe=__DIR__.'/cache_probe.php';
$prefix='cache-matrix-'.bin2hex(random_bytes(8));
$counter=runtime_path($prefix.'.txt');
function startProbe(string $key,string $counter,string $mode=''):array {
    global $probe;
    $process=proc_open([PHP_BINARY,$probe,$key,$counter,$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes,getcwd());
    if(!is_resource($process))throw new RuntimeException('Could not start probe');
    return [$process,$pipes];
}
function finishProbe(array $job):string {
    [$process,$pipes]=$job;
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0)throw new RuntimeException('Probe failed: '.$error);
    return trim($output);
}
function check(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
try {
    $jobs=[];
    for($i=0;$i<8;$i++)$jobs[]=startProbe($prefix,$counter);
    $results=array_map(fn($job)=>json_decode(finishProbe($job),true,512,JSON_THROW_ON_ERROR),$jobs);
    check(count(file($counter))===1,'Concurrent miss executed more than once');
    check(count(array_filter($results,fn($r)=>$r['cached']===false))===1,'Expected one producer');
    foreach($results as $r)check($r['rows']===[['total'=>42]],'Different consumer result');
    $failureKey=$prefix.'-recover';
    check(finishProbe(startProbe($failureKey,$counter,'fail'))==='expected failure','Failure was cached');
    $recovered=json_decode(finishProbe(startProbe($failureKey,$counter)),true,512,JSON_THROW_ON_ERROR);
    check($recovered['cached']===false,'Failure did not release lock');
    for($i=0;$i<2;$i++) {
        $r=json_decode(finishProbe(startProbe($prefix.'-fresh',$counter,'nocache')),true,512,JSON_THROW_ON_ERROR);
        check($r['cached']===false,'Cache-disabled request reused result');
    }
    check(count(file($counter))===5,'Unexpected query count');
    echo "PASS 8-process single execution, identical consumers, failure releases lock, cache=0 stays fresh\n";
} finally {if(is_file($counter))unlink($counter);}
