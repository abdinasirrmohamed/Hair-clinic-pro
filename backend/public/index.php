<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Normalize clean API URLs for subdirectory installs
|--------------------------------------------------------------------------
|
| In local XAMPP-style installs this repository can be served from a nested
| folder while Apache internally rewrites /api/* to this front controller.
| Symfony's request parser otherwise keeps the outer folder in PATH_INFO
| (for example: hair/Hair-clinic-pro/api/bootstrap). Strip everything before
| /api so Laravel's normal API routes continue to match.
|
*/
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    $query = '';
    $queryPosition = strpos($uri, '?');

    if ($queryPosition !== false) {
        $query = substr($uri, $queryPosition);
        $uri = substr($uri, 0, $queryPosition);
    }

    if (preg_match('#/api(?:/|$)#', $uri, $match, PREG_OFFSET_CAPTURE)) {
        $_SERVER['REQUEST_URI'] = substr($uri, $match[0][1]) . $query;
    }
}

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
