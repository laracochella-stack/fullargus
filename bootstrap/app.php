<?php

declare(strict_types=1);

use App\Support\NotificacionesSession;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('argus_session');
    session_start();
}

$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$timezone = $_ENV['APP_TIMEZONE']
    ?? $_SERVER['APP_TIMEZONE']
    ?? getenv('APP_TIMEZONE')
    ?? 'America/Guatemala';

if (!@date_default_timezone_set($timezone)) {
    date_default_timezone_set('UTC');
}

NotificacionesSession::asegurarEstado();
