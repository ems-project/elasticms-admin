<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

// The check is to ensure we don't use .env in production
if (!isset($_SERVER['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
    }
    (new Dotenv())->load(__DIR__.'/../.env');
}

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? !\in_array($env, ['prod', 'redis', 'db']));

if ($debug) {
    umask(0000);

    Debug::enable();
}

$forwardedProtoHeader = $_SERVER['HTTP_CUSTOM_FORWARDED_PROTO'] ?? null;
if (\is_string($forwardedProtoHeader) && null !== $_SERVER[$forwardedProtoHeader] ?? null) {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = $_SERVER[$forwardedProtoHeader];
}

$forwardedPortHeader = $_SERVER['HTTP_CUSTOM_FORWARDED_PORT'] ?? null;
if (\is_string($forwardedPortHeader) && null !== $_SERVER[$forwardedPortHeader] ?? null) {
    $_SERVER['HTTP_X_FORWARDED_PORT'] = $_SERVER[$forwardedPortHeader];
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
