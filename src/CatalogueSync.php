<?php

namespace Marti\Frontend;

/**
 * Syncs systems_catalogue.json from S3.
 * - Downloads on first request if file is missing
 * - Checks S3 once per day for updates
 */
class CatalogueSync
{
    private string $dataPath;
    private string $bucket;
    private string $key;
    private string $region;
    private string $lastCheckFile;

    public function __construct(string $dataPath)
    {
        $this->dataPath = rtrim($dataPath, '/');
        $this->bucket = getenv('CATALOGUE_S3_BUCKET') ?: 'mia-catalogue-sync';
        $this->key = 'systems_catalogue.json';
        $this->region = getenv('AWS_REGION') ?: 'eu-west-2';
        $this->lastCheckFile = $this->dataPath . '/.catalogue_last_check';
    }

    /**
     * Ensure the catalogue file is present and up to date.
     * Call this early in the request lifecycle.
     */
    public function ensureCatalogue(): void
    {
        $cataloguePath = $this->dataPath . '/systems_catalogue.json';

        // If file doesn't exist, must download
        if (!file_exists($cataloguePath)) {
            $this->downloadFromS3($cataloguePath);
            return;
        }

        // Check if we've already checked today
        if ($this->hasCheckedToday()) {
            return;
        }

        // Check S3 for newer version
        $this->checkAndUpdate($cataloguePath);
    }

    private function hasCheckedToday(): bool
    {
        if (!file_exists($this->lastCheckFile)) {
            return false;
        }
        $lastCheck = (int)file_get_contents($this->lastCheckFile);
        return date('Y-m-d', $lastCheck) === date('Y-m-d');
    }

    private function markChecked(): void
    {
        file_put_contents($this->lastCheckFile, (string)time());
    }

    private function checkAndUpdate(string $cataloguePath): void
    {
        try {
            $localModified = filemtime($cataloguePath);
            $s3Modified = $this->getS3LastModified();

            if ($s3Modified && $s3Modified > $localModified) {
                error_log("CatalogueSync: S3 has newer catalogue, downloading...");
                $this->downloadFromS3($cataloguePath);
            }

            $this->markChecked();
        } catch (\Exception $e) {
            error_log("CatalogueSync: check failed - " . $e->getMessage());
            $this->markChecked(); // Don't retry on every request
        }
    }

    private function getS3LastModified(): ?int
    {
        $url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$this->key}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($httpCode !== 200) {
            return null;
        }

        if (preg_match('/Last-Modified:\s*(.+)/i', $response, $m)) {
            return strtotime(trim($m[1]));
        }

        return null;
    }

    private function downloadFromS3(string $cataloguePath): void
    {
        $url = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$this->key}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($httpCode !== 200 || !$body) {
            error_log("CatalogueSync: download failed (HTTP {$httpCode})");
            return;
        }

        // Validate it's valid JSON before writing
        $decoded = json_decode($body, true);
        if (!$decoded || !isset($decoded['makes'])) {
            error_log("CatalogueSync: downloaded file is not valid catalogue JSON");
            return;
        }

        // Ensure directory exists
        $dir = dirname($cataloguePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cataloguePath, $body);
        error_log("CatalogueSync: catalogue updated from S3 (" . strlen($body) . " bytes)");
        $this->markChecked();
    }
}
