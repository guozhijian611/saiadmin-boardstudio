<?php

use plugin\saiadmin\app\middleware\CheckLogin;
use plugin\boardstudio\app\middleware\AuditLog;

return [
    'admin' => [
        CheckLogin::class,
        \plugin\boardstudio\app\middleware\RequirePermission::class,
        AuditLog::class,
    ],
    'api' => [
    ],
];
