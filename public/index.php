<?php
/**
 * Mia Storefront - Main Entry Point
 * Simple PHP storefront using only Mia SDK and memcached
 */

// Suppress cURL warnings for PHP 8.4+ compatibility with Guzzle 7.x
error_reporting(E_ALL & ~E_WARNING);

require_once '../vendor/autoload.php';
require_once '../src/SessionConfig.php';
require_once '../src/Storefront.php';
require_once '../src/AdminRouter.php';

use Marti\Frontend\SessionConfig;
use Marti\Frontend\Storefront;
use Marti\Frontend\AdminRouter;

// Initialize session configuration (must be done before any session_start())
SessionConfig::initialize();

// Get the request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Load environment variables to get admin path
$envFile = __DIR__ . '/../.env';
$adminAddress = 'systemAdmin'; // Default admin path

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'ADMIN_ADDRESS=') === 0) {
            $adminAddress = trim(str_replace('ADMIN_ADDRESS=', '', $line), '"\'');
            $adminAddress = rtrim($adminAddress, '/');
            break;
        }
    }
}

$adminPath = '/' . $adminAddress;

// Check if this is an admin request
if (strpos($path, $adminPath) === 0) {
    // Handle admin request
    $admin = new AdminRouter();
    $admin->handleRequest();
} else {
    // Handle storefront request
    $storefront = new Storefront();
    $storefront->handleRequest();
}