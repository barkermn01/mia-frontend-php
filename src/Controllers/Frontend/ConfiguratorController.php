<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\MiaClient;
use Marti\Frontend\View;
use Marti\Frontend\SystemsDataProvider;

class ConfiguratorController extends BaseController
{
    private SystemsDataProvider $systems;

    public function __construct(MiaClient $client, View $view)
    {
        parent::__construct($client, $view);
        $this->systems = new SystemsDataProvider(__DIR__ . '/../../../data');
    }

    private function isEnabled(): bool
    {
        $setting = $this->getSetting('configurator_enabled');
        if ($setting && isset($setting['value'])) {
            $val = $setting['value'];
            return $val === true || $val === 'true' || $val === '1';
        }
        return false;
    }

    public function index(): void
    {
        if (!$this->isEnabled()) {
            $this->show404();
            return;
        }

        $make = $_GET['make'] ?? null;
        $model = $_GET['model'] ?? null;
        $variant = $_GET['variant'] ?? null;
        $variantSlug = $_GET['variantSlug'] ?? null;

        $makes = $this->systems->getMakes();
        $models = $make ? $this->systems->getModels($make) : [];
        $variants = ($make && $model) ? $this->systems->getVariants($make, $model) : [];

        // Resolve variant from slug if needed
        if (!$variant && $variantSlug && !empty($variants)) {
            $normalizedSlug = strtolower($variantSlug);
            foreach (array_keys($variants) as $vName) {
                if (self::slugifyVariant($vName) === $normalizedSlug) {
                    $variant = $vName;
                    break;
                }
            }
        }

        $systems = ($make && $model && $variant) ? $this->systems->getSystems($make, $model, $variant) : [];
        $variantInfo = ($make && $model && $variant) ? $this->systems->getVariantInfo($make, $model, $variant) : null;

        $this->renderLayout('configurator', [
            'makes' => $makes,
            'models' => $models,
            'variants' => $variants,
            'systems' => $systems,
            'variantInfo' => $variantInfo,
            'selectedMake' => $make,
            'selectedModel' => $model,
            'selectedVariant' => $variant,
        ], 'Exhaust Configurator - ' . ($make ? htmlspecialchars($make) : 'Select Your Vehicle'));
    }

    public function showSystem(): void
    {
        if (!$this->isEnabled()) {
            $this->show404();
            return;
        }

        $systemNumber = $_GET['system'] ?? null;
        if (!$systemNumber) {
            $this->show404();
            return;
        }

        $system = $this->systems->getSystem($systemNumber);
        if (!$system) {
            $this->show404();
            return;
        }

        $parts = $this->systems->getParts($system['part_numbers']);

        // Try to match parts to products in the store for live pricing and add-to-cart
        $productMap = [];
        try {
            // Build the normalized SKU for each part (matching what the sync script creates)
            $searchedSkus = [];
            foreach ($system['part_numbers'] as $pn) {
                $normalizedSku = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $pn));
                if (isset($searchedSkus[$normalizedSku])) continue;
                $searchedSkus[$normalizedSku] = $pn;

                try {
                    $results = $this->client->products->getProducts(['sku' => $normalizedSku, 'limit' => 5]);
                    if (!empty($results['items'])) {
                        foreach ($results['items'] as $product) {
                            foreach ($product['variants'] ?? [] as $v) {
                                $sku = $v['sku'] ?? '';
                                $matchedPn = $this->findMatchingPartNumber($sku, array_keys($parts));
                                if ($matchedPn && !isset($productMap[$matchedPn])) {
                                    $productMap[$matchedPn] = [
                                        'productId' => $product['id'],
                                        'slug' => $product['slug'] ?? '',
                                        'title' => $product['title'] ?? '',
                                        'sku' => $sku,
                                        'price' => $v['price'] ?? null,
                                        'stock' => $v['stock'] ?? null,
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Individual search failed, continue with others
                }
            }
        } catch (\Exception $e) {
            error_log("ConfiguratorController: failed to load products for system {$systemNumber}: " . $e->getMessage());
        }

        $vehicles = $this->systems->getSystemVehicles($systemNumber);

        $this->renderLayout('system-detail', [
            'system' => $system,
            'parts' => $parts,
            'productMap' => $productMap,
            'vatRate' => $this->getVatRate(),
            'vehicles' => $vehicles,
        ], "System {$systemNumber} - " . htmlspecialchars($system['make'] . ' ' . $system['model']));
    }

    public function apiGetOptions(): void
    {
        header('Content-Type: application/json');
        $type = $_GET['type'] ?? '';
        $make = $_GET['make'] ?? '';
        $model = $_GET['model'] ?? '';
        $variant = $_GET['variant'] ?? '';

        switch ($type) {
            case 'makes':
                echo json_encode(['items' => $this->systems->getMakes()]);
                break;
            case 'models':
                echo json_encode(['items' => $this->systems->getModels($make)]);
                break;
            case 'variants':
                echo json_encode(['items' => $this->systems->getVariants($make, $model)]);
                break;
            case 'systems':
                echo json_encode(['items' => $this->systems->getSystems($make, $model, $variant)]);
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid type']);
        }
    }

    private function findMatchingPartNumber(string $sku, array $partNumbers): ?string
    {
        if (in_array($sku, $partNumbers)) {
            return $sku;
        }
        // Normalize for comparison: strip all non-alphanumeric chars and compare case-insensitively
        $normalize = function(string $s): string {
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $s));
        };
        $normalizedSku = $normalize($sku);
        foreach ($partNumbers as $pn) {
            if ($normalize($pn) === $normalizedSku) {
                return $pn;
            }
        }
        return null;
    }

    private function getVatRate(): float
    {
        try {
            $setting = $this->getSetting('vat_rate');
            if (isset($setting['value']) && is_numeric($setting['value'])) {
                return (float)$setting['value'] / 100;
            }
        } catch (\Exception $e) {}
        return 0.20;
    }

    public static function slugifyVariant(string $variant): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $variant));
        return trim($slug, '-');
    }
}
