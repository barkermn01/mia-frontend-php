<?php

namespace Marti\Frontend;

/**
 * Settings Cache Manager
 * 
 * Provides caching layer for site settings using Memcached.
 * Reduces API calls by caching setting data with automatic invalidation on updates.
 */
class SettingsCache
{
    private ?\Memcached $memcached = null;
    private bool $isAvailable = false;
    private string $siteId;
    private const CACHE_PREFIX = 'settings';
    private const DEFAULT_TTL = 86400; // 24 hours (1 day)
    private const NOT_FOUND_MARKER = '__NOT_FOUND__';

    public function __construct(string $siteId)
    {
        $this->siteId = $siteId;
        $this->initializeMemcached();
    }

    /**
     * Initialize Memcached connection
     */
    private function initializeMemcached(): void
    {
        try {
            $memcachedHost = $_ENV['MEMCACHED_HOST'] ?? 'localhost';
            $memcachedPort = $_ENV['MEMCACHED_PORT'] ?? '11211';

            $this->memcached = new \Memcached();
            $this->memcached->addServer($memcachedHost, (int)$memcachedPort);

            // Test connection
            $this->memcached->set('test_settings_cache', 'test', 10);
            $testResult = $this->memcached->get('test_settings_cache');

            if ($testResult === 'test') {
                $this->isAvailable = true;
            } else {
                error_log("SettingsCache: Memcached connection test failed");
                $this->isAvailable = false;
            }
        } catch (\Exception $e) {
            error_log("SettingsCache: Failed to initialize Memcached - " . $e->getMessage());
            $this->isAvailable = false;
        }
    }

    /**
     * Generate cache key for a setting
     */
    private function getCacheKey(string $settingName): string
    {
        return self::CACHE_PREFIX . ':' . $this->siteId . ':' . $settingName;
    }

    /**
     * Get a setting from cache
     * 
     * @param string $settingName The setting name
     * @return array|false|null Setting data, false if not found marker, null if not cached
     */
    public function get(string $settingName)
    {
        if (!$this->isAvailable || !$this->memcached) {
            return null;
        }

        try {
            $cacheKey = $this->getCacheKey($settingName);
            $cached = $this->memcached->get($cacheKey);

            if ($cached !== false) {
                // Check if this is a "not found" marker
                if ($cached === self::NOT_FOUND_MARKER) {
                    return false; // Return false to indicate "setting doesn't exist"
                }
                
                return $cached;
            }

            return null;
        } catch (\Exception $e) {
            error_log("SettingsCache: Error getting setting '{$settingName}' - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store a setting in cache
     * 
     * @param string $settingName The setting name
     * @param array $settingData Complete setting data from API
     * @param int $ttl Time to live in seconds (default: 24 hours)
     * @return bool Success status
     */
    public function set(string $settingName, array $settingData, int $ttl = self::DEFAULT_TTL): bool
    {
        if (!$this->isAvailable || !$this->memcached) {
            return false;
        }

        try {
            $cacheKey = $this->getCacheKey($settingName);
            return $this->memcached->set($cacheKey, $settingData, $ttl);
        } catch (\Exception $e) {
            error_log("SettingsCache: Error setting cache for '{$settingName}' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store a "not found" marker in cache to avoid repeated API calls
     * 
     * @param string $settingName The setting name
     * @return bool Success status
     */
    public function setNotFound(string $settingName): bool
    {
        if (!$this->isAvailable || !$this->memcached) {
            return false;
        }

        try {
            $cacheKey = $this->getCacheKey($settingName);
            return $this->memcached->set($cacheKey, self::NOT_FOUND_MARKER, self::DEFAULT_TTL);
        } catch (\Exception $e) {
            error_log("SettingsCache: Error setting not found cache for '{$settingName}' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a setting from cache (invalidate)
     * 
     * @param string $settingName The setting name
     * @return bool Success status
     */
    public function delete(string $settingName): bool
    {
        if (!$this->isAvailable || !$this->memcached) {
            return false;
        }

        try {
            $cacheKey = $this->getCacheKey($settingName);
            return $this->memcached->delete($cacheKey);
        } catch (\Exception $e) {
            error_log("SettingsCache: Error deleting cache for '{$settingName}' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all settings cache for this site
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        if (!$this->isAvailable || !$this->memcached) {
            return false;
        }

        try {
            // Note: This is a simple implementation that flushes all cache
            // In production, you might want to track keys or use a different approach
            return $this->memcached->flush();
        } catch (\Exception $e) {
            error_log("SettingsCache: Error clearing cache - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if cache is available
     * 
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }
}
