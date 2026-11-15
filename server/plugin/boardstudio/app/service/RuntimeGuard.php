<?php

namespace plugin\boardstudio\app\service;

use plugin\boardstudio\app\model\Screen;
use support\Redis;
use support\Request;
use Throwable;

class RuntimeGuard
{
    public function __construct(private readonly RuntimeMetrics $metrics = new RuntimeMetrics())
    {
    }

    public function assertAllowed(Request $request, Screen $screen, string $action): ?array
    {
        $action = $this->normalizeAction($action);
        $screenId = (int) $screen->id;
        $owner = (int) ($screen->created_by ?? 0);
        $ip = $this->clientIp($request);

        $this->metrics->record('request', [
            'action' => $action,
            'screen' => (string) $screenId,
            'owner' => (string) $owner,
        ]);

        if (!$this->enabled()) {
            return null;
        }

        $rules = [
            ['scope' => 'ip', 'identity' => $ip],
            ['scope' => 'screen', 'identity' => (string) $screenId],
            ['scope' => 'owner', 'identity' => (string) max(0, $owner)],
        ];

        foreach ($rules as $rule) {
            $result = $this->hit($action, $rule['scope'], $rule['identity']);
            if ($result['allowed']) {
                continue;
            }

            $this->metrics->record('rate_limited', [
                'action' => $action,
                'scope' => $rule['scope'],
                'screen' => (string) $screenId,
                'owner' => (string) $owner,
            ]);

            return [
                'scope' => $rule['scope'],
                'limit' => $result['limit'],
                'window' => $result['window'],
                'retry_after' => $result['retry_after'],
            ];
        }

        return null;
    }

    private function hit(string $action, string $scope, string $identity): array
    {
        $window = $this->window($scope);
        $limit = $this->limit($scope);
        if ($limit <= 0) {
            return ['allowed' => true, 'limit' => $limit, 'window' => $window, 'retry_after' => 0];
        }

        $bucket = intdiv(time(), $window);
        $key = 'boardstudio:runtime:rate:' . $scope . ':' . $action . ':' . md5($identity) . ':' . $bucket;
        $count = $this->redisHit($key, $window);
        if ($count === null) {
            $count = $this->cacheHit($key, $window);
        }

        return [
            'allowed' => $count <= $limit,
            'limit' => $limit,
            'window' => $window,
            'retry_after' => max(1, ($bucket + 1) * $window - time()),
        ];
    }

    private function redisHit(string $key, int $window): ?int
    {
        try {
            $script = <<<'LUA'
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return current
LUA;
            return (int) Redis::eval($script, 1, $key, (string) ($window + 5));
        } catch (Throwable) {
            return null;
        }
    }

    private function cacheHit(string $key, int $window): int
    {
        // Process-safe local fallback; unlike cache get/set it cannot lose increments.
        $dir = runtime_path('boardstudio-rate');
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return PHP_INT_MAX;
        // One file per identity (not per time bucket) prevents unbounded bucket files.
        $stableKey = substr($key, 0, (int) strrpos($key, ':'));
        $file = @fopen($dir . '/' . hash('sha256', $stableKey) . '.json', 'c+');
        if (!$file) return PHP_INT_MAX;
        try {
            if (!flock($file, LOCK_EX)) return PHP_INT_MAX;
            $state = json_decode(stream_get_contents($file) ?: '{}', true);
            $bucket = intdiv(time(), $window);
            $count = ($state['bucket'] ?? -1) === $bucket ? (int) ($state['count'] ?? 0) + 1 : 1;
            rewind($file);
            ftruncate($file, 0);
            if (fwrite($file, json_encode(['bucket' => $bucket, 'count' => $count])) === false) return PHP_INT_MAX;
            fflush($file);
            return $count;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function clientIp(Request $request): string
    {
        $ip = $request->getRealIp();
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));
        return preg_match('/^[a-z0-9_]{1,30}$/', $action) ? $action : 'runtime';
    }

    private function window(string $scope): int
    {
        return max(1, (int) config("plugin.boardstudio.app.runtime.rate_limit.{$scope}.window", 60));
    }

    private function limit(string $scope): int
    {
        return (int) config("plugin.boardstudio.app.runtime.rate_limit.{$scope}.limit", 0);
    }

    private function enabled(): bool
    {
        return (bool) config('plugin.boardstudio.app.runtime.rate_limit.enabled', true);
    }
}
