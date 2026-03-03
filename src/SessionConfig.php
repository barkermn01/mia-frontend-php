<?php

namespace Marti\Frontend;

class SessionConfig
{
    private static $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        // Get Memcached configuration from environment variables
        // Defaults to localhost for local development when no environment variable is set
        $memcachedHost = $_ENV['MEMCACHED_HOST'] ?? 'localhost';
        $memcachedPort = $_ENV['MEMCACHED_PORT'] ?? '11211';

        // Try to connect to Memcached to verify it's available
        $memcached = new \Memcached();
        $memcached->addServer($memcachedHost, (int)$memcachedPort);
        
        // Test the connection
        $memcached->set('test_connection', 'test', 10);
        $testResult = $memcached->get('test_connection');
        
        if ($testResult === 'test') {
            // Configure PHP to use Memcached for sessions
            ini_set('session.save_handler', 'memcached');
            ini_set('session.save_path', $memcachedHost . ':' . $memcachedPort);
        } else {
            error_log("SessionConfig: Memcached connection failed, falling back to file-based sessions");
            error_log("SessionConfig: Memcached error: " . $memcached->getResultMessage());
            
            // Fall back to file-based sessions
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', '/tmp');
        }
        
        // Optional: Set session cookie parameters
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        
        // Set session lifetime (24 hours)
        ini_set('session.gc_maxlifetime', '86400');
        ini_set('session.cookie_lifetime', '86400');

        // Start the session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$initialized = true;
    }
}
