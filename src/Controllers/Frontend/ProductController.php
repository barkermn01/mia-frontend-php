<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\Exceptions\MiaException;
use Mia\SDK\Exceptions\NotFoundException;
use Marti\Frontend\HtmlResources;

class ProductController extends BaseController
{
    public function index(): void
    {
        error_log("=== ProductController::index() CALLED ===");
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $selectedFilters = $_GET['filters'] ?? [];
            $sort = $_GET['sort'] ?? '';
            
            error_log("About to fetch filter mode setting...");
            // Get filter mode setting
            $filterMode = $this->getSetting('OPERATION:filter_mode')['value'] ?? 'Faceted';
            error_log("Filter mode: " . $filterMode);
            
            // Convert single category to filters array for backward compatibility
            if ($category && !$selectedFilters) {
                $selectedFilters = [$category];
            }
            
            $filters = [
                'page' => $page,
                'limit' => 24
            ];
            
            if ($search) {
                $filters['search'] = $search;
            }
            
            if ($sort) {
                // Parse sort format: "field:order" -> separate sortBy and sortOrder
                $sortParts = explode(':', $sort);
                if (count($sortParts) === 2) {
                    $filters['sortBy'] = $sortParts[0];
                    $filters['sortOrder'] = $sortParts[1];
                }
            }
            
            // Handle multiple filters with AND logic (comma-separated)
            if ($selectedFilters) {
                if (is_array($selectedFilters)) {
                    // Multiple filters - combine them with commas for AND logic
                    $filters['category'] = implode(',', $selectedFilters);
                } else {
                    // Single filter
                    $filters['category'] = $selectedFilters;
                }
            }
            
            error_log("About to call getProducts()...");
            $products = $this->getProducts($filters);
            error_log("Products fetched: " . ($products['total'] ?? 0) . " total");
            
            // Get categories for sidebar - pass current filters to get updated counts
            $categoryFilters = [];
            if ($selectedFilters) {
                if (is_array($selectedFilters)) {
                    $categoryFilters['category'] = implode(',', $selectedFilters);
                } else {
                    $categoryFilters['category'] = $selectedFilters;
                }
            }
            if ($search) {
                $categoryFilters['search'] = $search;
            }
            
            error_log("About to call getCategories() with filters: " . json_encode($categoryFilters));
            $categories = $this->getCategories($categoryFilters);
            error_log("Categories fetched - primary: " . count($categories['primary'] ?? []) . ", filter groups: " . count($categories['filters'] ?? []));
            
            // Set page-specific resources
            $title = 'Products';
            if ($search) $title .= ' - Search: ' . htmlspecialchars($search);
            if ($selectedFilters) {
                $filterNames = array_map(function($filter) {
                    // Show just the value part for filter categories
                    if (strpos($filter, ':') !== false) {
                        return explode(':', $filter, 2)[1];
                    }
                    return $filter;
                }, is_array($selectedFilters) ? $selectedFilters : [$selectedFilters]);
                $title .= ' - Filters: ' . htmlspecialchars(implode(', ', $filterNames));
            }
            
            HtmlResources::getInstance()->setTitle($title);
            HtmlResources::getInstance()->setDescription('Browse our collection of products. Find exactly what you\'re looking for.');
            HtmlResources::getInstance()->setKeywords('products, shop, browse, ' . ($selectedFilters ? htmlspecialchars(implode(', ', is_array($selectedFilters) ? $selectedFilters : [$selectedFilters])) : 'all categories'));
            
            $content = $this->view->render('products', [
                'products' => $products,
                'categories' => $categories,
                'search' => $search,
                'category' => $category, // Keep for backward compatibility
                'selectedFilters' => is_array($selectedFilters) ? $selectedFilters : ($selectedFilters ? [$selectedFilters] : []),
                'page' => $page,
                'baseUrl' => '/products',
                'vatRate' => $this->getVatRate(),
                'sort' => $sort,
                'filterMode' => $filterMode
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => $title,
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn(),
                'menuCategories' => $this->getMenuCategories()
            ]);
            
        } catch (MiaException $e) {
            error_log("Failed to load products: " . $e->getMessage());
            $this->showError("Failed to load products: " . $e->getMessage());
        }
    }
    
    private function getProducts(array $filters = []): array
    {
        try {
            $products = $this->client->products->getProducts($filters);
            return $products;
        } catch (MiaException $e) {
            error_log("Failed to fetch products: " . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }
    
    private function getCategories(array $filters = []): array
    {
        try {
            $apiFilters = array_merge(['status' => 'active'], $filters);
            error_log("getCategories() - Sending to API: " . json_encode($apiFilters));
            
            $categories = $this->client->products->getCategories($apiFilters);
            
            error_log("Raw API response: " . json_encode($categories));
            
            $allCategories = $categories['categories'] ?? [];
            
            // DEBUG: Log what we're getting from the API
            error_log("=== DEBUG getCategories ===");
            error_log("Total categories from API: " . count($allCategories));
            error_log("First 10 categories:");
            foreach (array_slice($allCategories, 0, 10) as $cat) {
                error_log("  - Name: " . $cat['name'] . " | Count: " . $cat['count']);
            }
            error_log("=========================");
            
            $primaryCategories = [];
            $filterGroups = [];
            
            // Get filter mode to determine how to process categories
            $filterMode = $this->getSetting('OPERATION:filter_mode')['value'] ?? 'Faceted';
            $isHierarchical = ($filterMode === 'Hierarchical');
            
            error_log("Filter mode: " . $filterMode);
            
            if ($isHierarchical) {
                // Hierarchical mode - all categories are hierarchical paths without colons
                // Group them all under "Vehicle" for display
                $filterGroups['Vehicle'] = [];
                
                foreach ($allCategories as $category) {
                    $filterGroups['Vehicle'][] = [
                        'name' => $category['name'],
                        'fullName' => $category['name'],
                        'count' => $category['count'],
                        'depth' => substr_count($category['name'], ',')
                    ];
                }
            } else {
                // Faceted mode - categories should have colons like "Vehicle: Alfa Romeo"
                foreach ($allCategories as $category) {
                    if (strpos($category['name'], ':') === false) {
                        // Primary category (no colon)
                        $primaryCategories[] = $category;
                    } else {
                        // Filter category (has colon) - group by key
                        $parts = explode(':', $category['name'], 2);
                        $key = trim($parts[0]);
                        $value = trim($parts[1]);
                        
                        if (!isset($filterGroups[$key])) {
                            $filterGroups[$key] = [];
                        }
                        
                        $filterGroups[$key][] = [
                            'name' => $value,
                            'fullName' => $category['name'],
                            'count' => $category['count']
                        ];
                    }
                }
            }
            
            error_log("Filter groups created: " . count($filterGroups));
            foreach ($filterGroups as $key => $values) {
                error_log("  Group '$key': " . count($values) . " items");
                foreach (array_slice($values, 0, 5) as $v) {
                    error_log("    - " . $v['name'] . " (depth: " . ($v['depth'] ?? 'N/A') . ")");
                }
            }
            
            return [
                'primary' => $primaryCategories,
                'filters' => $filterGroups
            ];
        } catch (MiaException $e) {
            error_log("Failed to fetch categories: " . $e->getMessage());
            return ['primary' => [], 'filters' => []];
        }
    }
    
    private function getVatRate(): float
    {
        try {
            // Get VAT rate setting (no siteId needed, SDK handles it)
            $setting = $this->getSetting('vat_rate');
            
            if (isset($setting['value']) && is_numeric($setting['value'])) {
                // Return as decimal (e.g., 0.20 for 20%)
                return (float)$setting['value'] / 100;
            }
        } catch (MiaException $e) {
            // If setting doesn't exist or there's an error, use default
        }
        
        // Default to UK VAT rate of 20%
        return 0.20;
    }

    public function show(): void
    {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            $this->show404();
            return;
        }
        
        try {
            $product = $this->client->products->getProduct($id);
            
            $variants = $this->client->products->getProductVariants($id);
            
            // If configurator is enabled, look up backorder ETAs from the systems catalogue
            $backorderEtas = [];
            $configuratorEnabled = $this->getSetting('configurator_enabled');
            if ($configuratorEnabled && ($configuratorEnabled['value'] ?? '') === 'true') {
                $systems = new \Marti\Frontend\SystemsDataProvider(__DIR__ . '/../../../data');
                if ($systems->isAvailable()) {
                    foreach ($variants['items'] ?? [] as $variant) {
                        $sku = $variant['sku'] ?? '';
                        if (!$sku) continue;
                        $part = $systems->findPartBySku($sku);
                        if ($part && !empty($part['due_before'])) {
                            $backorderEtas[$sku] = $part['due_before'];
                        }
                    }
                }
            }
            
            // Add product-specific resources
            HtmlResources::getInstance()->setTitle(htmlspecialchars($product['title']));
            HtmlResources::getInstance()->setDescription(strip_tags($product['description'] ?? ''));
            HtmlResources::getInstance()->addJsBody('/js/product.js'); // Product JS needs to be after DOM and config
            
            $content = $this->view->render('product', [
                'product' => $product,
                'variants' => $variants['items'] ?? [],
                'vatRate' => $this->getVatRate(),
                'backorderEtas' => $backorderEtas
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => htmlspecialchars($product['title']),
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn(),
                'menuCategories' => $this->getMenuCategories()
            ]);
        } catch (NotFoundException $e) {
            error_log("Product not found: " . $e->getMessage());
            $this->show404();
        } catch (MiaException $e) {
            error_log("MiaException occurred: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $this->showError("Failed to load product: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("General exception occurred: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $this->showError("Failed to load product: " . $e->getMessage());
        }
    }
    
    public function apiGetProduct(string $productId): void
    {
        try {
            $product = $this->client->products->getProduct($productId);
            echo json_encode($product);
        } catch (NotFoundException $e) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
