<?php
namespace plugin\boardstudio\app\api\controller;
use support\Request;
use support\Response;
final class PlayerController
{
    public function index(Request $request): Response
    {
        $file = dirname(__DIR__, 3) . '/public/player/index.html';
        return is_file($file) ? new Response(200, ['Content-Type' => 'text/html; charset=utf-8', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store'], file_get_contents($file)) : new Response(503, [], '播放器未构建，请先执行 build-player');
    }
    public function asset(Request $request, string $file): Response
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.(js|css|woff2)$/', $file, $match)) return new Response(404);
        $path = dirname(__DIR__, 3) . '/public/player/assets/' . $file;
        if (!is_file($path)) return new Response(404);
        $types = ['js' => 'text/javascript', 'css' => 'text/css', 'woff2' => 'font/woff2'];
        return new Response(200, ['Content-Type' => $types[$match[1]], 'Cache-Control' => 'public, max-age=31536000, immutable'], file_get_contents($path));
    }
}
