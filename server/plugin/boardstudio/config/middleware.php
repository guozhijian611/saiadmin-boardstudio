<?php

use plugin\saiadmin\app\middleware\CheckAuth;
use plugin\saiadmin\app\middleware\CheckLogin;
use plugin\boardstudio\app\middleware\AuditLog;

return [
    'admin' => [
        CheckLogin::class,
        CheckAuth::class,
        AuditLog::class,
    ],
    'api' => [
    ],
];
