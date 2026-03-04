<?php

namespace Marti\Frontend;

class View
{
    private $templatePath;
    private $data = [];
    private $settingsGetter = null;

    public function __construct(string $templatePath)
    {
        $this->templatePath = rtrim($templatePath, '/');
    }
    
    /**
     * Set the settings getter callback (from BaseController)
     */
    public function setSettingsGetter(?callable $settingsGetter): void
    {
        $this->settingsGetter = $settingsGetter;
    }
    
    /**
     * Get a setting value with optional fallback
     * Uses the controller's getSetting() method which handles caching and API calls
     */
    public function setting(string $name, $default = null)
    {
        if (!$this->settingsGetter) {
            return $default;
        }
        
        $setting = call_user_func($this->settingsGetter, $name);
        
        if ($setting === null) {
            return $default;
        }
        
        return $setting['value'] ?? $default;
    }

    public function assign(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function assignArray(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    public function render(string $template, array $data = []): string
    {
        // Merge data and add view instance
        $templateData = array_merge($this->data, $data, ['view' => $this]);

        // Start output buffering
        ob_start();
        
        // Extract variables for template scope
        extract($templateData, EXTR_SKIP);
        
        // Include template file
        $templateFile = $this->templatePath . '/' . $template . '.phtml';
        if (!file_exists($templateFile)) {
            throw new \Exception("Template file not found: {$templateFile}");
        }
        
        include $templateFile;
        
        // Get rendered content
        $content = ob_get_clean();
        
        return $content;
    }

    public function renderLayout(string $layout, string $content, array $data = []): string
    {
        $layoutData = array_merge($this->data, $data, ['content' => $content]);
        return $this->render($layout, $layoutData); // Layouts use same cache setting
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render('partials/' . $template, $data); // Partials use same cache setting
    }

    /**
     * Get display price from price data (handles both old and new formats)
     */
    public function getDisplayPrice($priceData): float
    {
        if (is_array($priceData)) {
            // New multi-currency format: [{"currency": "GBP", "unit": 1999}]
            if (isset($priceData[0]['unit'])) {
                return $priceData[0]['unit'] / 100;
            }
            // Handle single currency object format: {"currency": "GBP", "unit": 1999}
            if (isset($priceData['unit'])) {
                return $priceData['unit'] / 100;
            }
        } elseif (is_numeric($priceData)) {
            // Legacy format: 1999 (integer in pence)
            return $priceData / 100;
        }
        return 0.0;
    }

    /**
     * Get currency from price data
     */
    public function getCurrency($priceData): string
    {
        if (is_array($priceData)) {
            // New multi-currency format
            if (isset($priceData[0]['currency'])) {
                return $priceData[0]['currency'];
            }
            // Handle single currency object format
            if (isset($priceData['currency'])) {
                return $priceData['currency'];
            }
        }
        return 'GBP'; // default
    }

    /**
     * Format price with currency symbol
     */
    public function formatPrice($priceData): string
    {
        $price = $this->getDisplayPrice($priceData);
        $currency = $this->getCurrency($priceData);
        
        // Simple currency symbol mapping
        $symbols = [
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($price, 2);
    }

    /**
     * Get variant presentable name
     */
    public function getVariantName($variant): string
    {
        // Use new presentableName field if available
        if (isset($variant['presentableName']) && !empty($variant['presentableName'])) {
            return $variant['presentableName'];
        }
        
        // Fallback to building name from attributes
        if (isset($variant['attributes']) && is_array($variant['attributes'])) {
            $parts = [];
            foreach ($variant['attributes'] as $key => $value) {
                if (!empty($value)) {
                    $parts[] = ucfirst($value);
                }
            }
            if (!empty($parts)) {
                return implode(' - ', $parts);
            }
        }
        
        // Final fallback
        return $variant['sku'] ?? 'Default';
    }

    /**
     * Get stock display text
     */
    public function getStockDisplay($variant): string
    {
        if (!isset($variant['stock'])) {
            return 'Stock info unavailable';
        }
        
        $stock = $variant['stock'];
        $available = $stock['available'] ?? 0;
        $isUnlimited = $stock['unlimited'] ?? false;
        $inventoryType = $stock['inventoryType'] ?? 'physical';
        
        if ($isUnlimited || $inventoryType === 'digital') {
            return 'In Stock';
        } elseif ($available > 0) {
            return $available . ' available';
        } else {
            return 'Out of Stock';
        }
    }

    /**
     * Check if variant is in stock
     */
    public function isInStock($variant): bool
    {
        if (!isset($variant['stock'])) {
            return false;
        }
        
        $stock = $variant['stock'];
        $available = $stock['available'] ?? 0;
        $isUnlimited = $stock['unlimited'] ?? false;
        $inventoryType = $stock['inventoryType'] ?? 'physical';
        
        return $isUnlimited || $inventoryType === 'digital' || $available > 0;
    }

    /**
     * Get country display name from country code
     * Looks up the country code in the supportedCountries array
     */
    public function getCountryName(string $countryCode, array $supportedCountries): string
    {
        return $supportedCountries[$countryCode] ?? $countryCode;
    }
}