<?php

namespace Marti\Frontend;

/**
 * Loads and queries the systems catalogue JSON file.
 * The JSON is converted from the scraper's systems.csv + parts.csv.
 * 
 * Structure: { makes: { "Make": { models: { "Model": { variants: { ... } } } } }, parts: { ... } }
 */
class SystemsDataProvider
{
    private ?array $catalogue = null;
    private string $dataPath;

    public function __construct(string $dataPath)
    {
        $this->dataPath = rtrim($dataPath, '/');
    }

    private function load(): array
    {
        if ($this->catalogue !== null) {
            return $this->catalogue;
        }

        $file = $this->dataPath . '/systems_catalogue.json';
        if (!file_exists($file)) {
            error_log("SystemsDataProvider: catalogue file not found at {$file}");
            $this->catalogue = ['makes' => [], 'parts' => []];
            return $this->catalogue;
        }

        $json = file_get_contents($file);
        $this->catalogue = json_decode($json, true);
        if (!$this->catalogue) {
            error_log("SystemsDataProvider: failed to decode catalogue JSON");
            $this->catalogue = ['makes' => [], 'parts' => []];
        }

        return $this->catalogue;
    }

    public function getMakes(): array
    {
        $data = $this->load();
        $makes = array_keys($data['makes'] ?? []);
        sort($makes);
        return $makes;
    }

    public function getModels(string $make): array
    {
        $data = $this->load();
        $models = array_keys($data['makes'][$make]['models'] ?? []);
        sort($models);
        return $models;
    }

    public function getVariants(string $make, string $model): array
    {
        $data = $this->load();
        $variants = $data['makes'][$make]['models'][$model]['variants'] ?? [];
        $result = [];
        foreach ($variants as $desc => $info) {
            $result[$desc] = [
                'year_range' => $info['year_range'] ?? '',
                'system_count' => count($info['systems'] ?? []),
            ];
        }
        ksort($result);
        return $result;
    }

    public function getSystems(string $make, string $model, string $variant): array
    {
        $data = $this->load();
        return $data['makes'][$make]['models'][$model]['variants'][$variant]['systems'] ?? [];
    }

    public function getVariantInfo(string $make, string $model, string $variant): ?array
    {
        $data = $this->load();
        return $data['makes'][$make]['models'][$model]['variants'][$variant] ?? null;
    }

    public function getSystem(string $systemNumber): ?array
    {
        $data = $this->load();
        foreach ($data['makes'] as $make => $makeData) {
            foreach ($makeData['models'] as $model => $modelData) {
                foreach ($modelData['variants'] as $variant => $variantData) {
                    foreach ($variantData['systems'] as $system) {
                        if ($system['system_number'] === $systemNumber) {
                            return array_merge($system, [
                                'make' => $make,
                                'model' => $model,
                                'variant_desc' => $variant,
                                'year_range' => $variantData['year_range'] ?? '',
                            ]);
                        }
                    }
                }
            }
        }
        return null;
    }

    public function getPart(string $partNumber): ?array
    {
        $data = $this->load();
        return $data['parts'][$partNumber] ?? null;
    }

    public function getParts(array $partNumbers): array
    {
        $data = $this->load();
        $result = [];
        foreach ($partNumbers as $pn) {
            if (isset($data['parts'][$pn])) {
                $result[$pn] = $data['parts'][$pn];
            }
        }
        return $result;
    }

    public function isAvailable(): bool
    {
        $file = $this->dataPath . '/systems_catalogue.json';
        return file_exists($file);
    }

    /**
     * Find a part by SKU, using normalized matching (strips slashes, spaces, etc.)
     */
    public function findPartBySku(string $sku): ?array
    {
        // Direct match first
        $part = $this->getPart($sku);
        if ($part) return $part;

        // Normalized match
        $data = $this->load();
        $normalizedSku = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $sku));
        foreach ($data['parts'] as $pn => $part) {
            $normalizedPn = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $pn));
            if ($normalizedPn === $normalizedSku) {
                return $part;
            }
        }
        return null;
    }

    /**
     * Find all vehicles (make/model/variant) that a system fits
     */
    public function getSystemVehicles(string $systemNumber): array
    {
        $data = $this->load();
        $vehicles = [];
        foreach ($data['makes'] as $make => $makeData) {
            foreach ($makeData['models'] as $model => $modelData) {
                foreach ($modelData['variants'] as $variant => $variantData) {
                    foreach ($variantData['systems'] as $system) {
                        if ($system['system_number'] === $systemNumber) {
                            $vehicles[] = [
                                'make' => $make,
                                'model' => $model,
                                'variant' => $variant,
                                'year_range' => $variantData['year_range'] ?? '',
                            ];
                            break;
                        }
                    }
                }
            }
        }
        return $vehicles;
    }

    /**
     * Search systems by system number, make, model, or variant description
     */
    public function searchSystems(string $query, int $limit = 20): array
    {
        $data = $this->load();
        $query = strtolower(trim($query));
        if (!$query) return [];

        $results = [];
        $seen = [];
        foreach ($data['makes'] as $make => $makeData) {
            foreach ($makeData['models'] as $model => $modelData) {
                foreach ($modelData['variants'] as $variant => $variantData) {
                    foreach ($variantData['systems'] as $system) {
                        $sn = $system['system_number'];
                        if (isset($seen[$sn])) continue;
                        $haystack = strtolower($sn . ' ' . $make . ' ' . $model . ' ' . $variant);
                        if (str_contains($haystack, $query)) {
                            $seen[$sn] = true;
                            $results[] = array_merge($system, [
                                'make' => $make,
                                'model' => $model,
                                'variant_desc' => $variant,
                                'year_range' => $variantData['year_range'] ?? '',
                            ]);
                            if (count($results) >= $limit) return $results;
                        }
                    }
                }
            }
        }
        return $results;
    }
}
