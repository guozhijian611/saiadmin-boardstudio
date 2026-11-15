<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\service;

use RuntimeException;
use support\Redis;
use support\think\Cache;
use Throwable;

/** Coalesce cache misses across workers without unbounded blocking or caching failures. */
final class DatasetCache
{
    public function remember(string $key, int $ttl, int $timeoutMs, callable $query): array
    {
        if ($ttl <= 0) return $query()+['cached'=>false];
        $cached=Cache::get($key);
        if (is_array($cached)) return $cached+['cached'=>true];
        $deadline=microtime(true)+(min(10000,max(100,$timeoutMs))+1000)/1000;
        $token=bin2hex(random_bytes(16));
        $lockKey='boardstudio:v2:lock:'.hash('sha256',$key);
        $lease=(int)ceil(min(10000,max(100,$timeoutMs))/1000)+5;
        $file=null;
        $redis=(bool)config('plugin.boardstudio.app.runtime.lock.redis',true);
        if($redis) {
            try { Redis::ping(); }
            catch(Throwable) { $redis=false; }
        }
        if(!$redis) {
            // Cross-host coalescing requires shared Redis; fallback is per filesystem.
            $dir=runtime_path('boardstudio-dataset-locks');
            if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('查询锁目录不可用');
            $file=@fopen($dir.'/'.hash('sha256',$key).'.lock','c');
            if(!$file)throw new RuntimeException('查询锁不可用');
        }
        try {
            do {
                $cached=Cache::get($key);
                if(is_array($cached))return $cached+['cached'=>true];
                $locked=$redis?(bool)Redis::set($lockKey,$token,'EX',$lease,'NX'):flock($file,LOCK_EX|LOCK_NB);
                if($locked) {
                    try {
                        // Another worker may have published between cache read and lock acquisition.
                        $cached=Cache::get($key);
                        if(is_array($cached))return $cached+['cached'=>true];
                        $result=$query();
                        Cache::set($key,$result,$ttl);
                        return $result+['cached'=>false];
                    } finally {
                        if($redis) {
                            try { Redis::eval("if redis.call('GET',KEYS[1])==ARGV[1] then return redis.call('DEL',KEYS[1]) end return 0",1,$lockKey,$token); }
                            catch(Throwable) { /* Lease expires if Redis disconnects. */ }
                        } else flock($file,LOCK_UN);
                    }
                }
                usleep(20000);
            } while(microtime(true)<$deadline);
            throw new RuntimeException('数据集查询繁忙，请稍后重试');
        } finally { if(is_resource($file))fclose($file); }
    }
}
