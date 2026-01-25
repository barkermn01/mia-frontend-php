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

        // Configure PHP to use Memcached for sessions
        ini_set('session.save_handler', 'memcached');
        ini_set('session.save_path', $memcachedHost . ':' . $memcachedPort);
        
        // Optional: Set session cookie parameters
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        
        // Set session lifetime (24 hours)
        ini_set('session.gc_maxlifetime', '86400');
        ini_set('session.cookie_lifetime', '86400');

        self::$initialized = true;
    }
}
