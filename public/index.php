<?php
/**
 * Mia Storefront - Main Entry Point
 * Simple PHP storefront using only Mia SDK and memcached
 */

// Suppress cURL warnings for PHP 8.4+ compatibility with Guzzle 7.x
error_reporting(E_ALL & ~E_WARNING);

require_once '../vendor/autoload.php';
require_once '../src/SessionConfig.php';
require_once '../src/FrontendRouter.php';
require_once '../src/AdminRouter.php';

use Marti\Frontend\SessionConfig;
use Marti\Frontend\FrontendRouter;
use Marti\Frontend\AdminRouter;

// Initialize session configuration (must be done before any session_start())
SessionConfig::initialize();

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, '"\'');
        
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Get the request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Get admin path from environment
$adminAddress = rtrim($_ENV['ADMIN_ADDRESS'] ?? 'admin/', '/');
$adminPath = '/' . $adminAddress;

// Check if this is an admin request
if (strpos($path, $adminPath) === 0) {
    // Handle admin request
    $admin = new AdminRouter();
    $admin->handleRequest();
} else {
    // Handle storefront request
    $client = new \Mia\SDK\MiaClient([
        'apiUrl' => $_ENV['MIA_API_URL'] ?? 'https://api.miaai.me',
        'siteId' => $_ENV['MIA_SITE_ID'] ?? null,
        'verify_ssl' => filter_var($_ENV['MIA_VERIFY_SSL'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'debug' => false
    ]);
    
    // Set auth token if available
    if (isset($_SESSION['auth_token'])) {
        $client->setAuthToken($_SESSION['auth_token']);
    }
    
    $storefront = new FrontendRouter(
        $client,
        new \Marti\Frontend\View(__DIR__ . '/../src/templates')
    );
    $storefront->handleRequest();
}
