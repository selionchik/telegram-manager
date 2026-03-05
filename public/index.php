<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Принудительно отключаем IPC для веб-окружения
putenv('MADELINE_IPC_DISABLE=1');
define('MADELINE_IPC_DISABLE', true);

// Явно указываем корневую директорию
putenv('MADELINE_ROOT_DIR=' . __DIR__);
putenv('MADELINE_WEB_BASE=/mtproto/public');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
