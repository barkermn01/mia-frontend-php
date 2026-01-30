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
        // Defaults to localhost for local development
        $memcachedHost = getenv('MEMCACHED_HOST') ?: 'localhost';
        $memcachedPort = getenv('MEMCACHED_PORT') ?: '11211';

        error_log("SessionConfig: Attempting to use Memcached at {$memcachedHost}:{$memcachedPort}");

        // Try to connect to Memcached to verify it's available
        $memcached = new \Memcached();
        $memcached->addServer($memcachedHost, (int)$memcachedPort);
        
        // Test the connection
        $memcached->set('test_connection', 'test', 10);
        $testResult = $memcached->get('test_connection');
        
        if ($testResult === 'test') {
            error_log("SessionConfig: Memcached connection successful");
            
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
            error_log("SessionConfig: Session started with ID: " . session_id());
        }

        self::$initialized = true;
    }
}
