<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Dependencies are not installed.\n";
	echo "Run: composer install\n";
	echo "If install fails, enable MongoDB extension in PHP first.\n";
	exit(1);
}

require_once $autoloadPath;

use App\Bootstrap;

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$app = new Bootstrap();
$app->run();
