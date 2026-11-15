<?php

declare(strict_types=1);
namespace plugin\boardstudio\app\middleware;
use plugin\saiadmin\app\model\system\SystemOperLog;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** Plugin audit records metadata only; connection and dataset bodies may contain custom secrets. */
final class AuditLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() !== 'GET') {
            $info = getCurrentInfo();
            SystemOperLog::create([
                'username' => mb_substr((string)($info['username'] ?? ''),0,20), 'method' => $request->method(),
                'router' => $request->path(), 'service_name' => 'Board Studio 资源操作',
                'app' => 'boardstudio', 'ip' => $request->getRealIp(), 'ip_location' => '',
                'request_data' => json_encode(['id' => (int) $request->input('id', 0), 'content' => '[已省略敏感请求内容]'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $handler($request);
    }
}
