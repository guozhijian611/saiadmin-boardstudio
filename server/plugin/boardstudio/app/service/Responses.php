<?php
namespace plugin\boardstudio\app\service;
use support\Response;
final class Responses
{
    public static function ok(array $data = []): Response
    {
        return json(['code' => 200, 'message' => 'success', 'data' => $data]);
    }
    public static function fail(string $message, int $code = 400): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => null]);
    }
}
