<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\Exceptions\MiaException;
use Mia\SDK\Exceptions\NotFoundException;
use Marti\Frontend\HtmlResources;

class ProductController extends BaseController
{
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $selectedFilters = $_GET['filters'] ?? [];
            $sort = $_GET['sort'] ?? '';
            
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
            
            $products = $this->getProducts($filters);
            
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
            
            $categories = $this->getCategories($categoryFilters);
            
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
            $title .= ' - OxWinches';
            
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
                'sort' => $sort
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
            $categories = $this->client->products->getCategories(array_merge(['status' => 'active'], $filters));
            $allCategories = $categories['categories'] ?? [];
            
            $primaryCategories = [];
            $filterGroups = [];
            
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
            
            // Add product-specific resources
            HtmlResources::getInstance()->setTitle(htmlspecialchars($product['title']) . ' - OxWinches');
            HtmlResources::getInstance()->setDescription(strip_tags($product['description'] ?? ''));
            HtmlResources::getInstance()->addJsBody('/js/product.js'); // Product JS needs to be after DOM and config
            
            $content = $this->view->render('product', [
                'product' => $product,
                'variants' => $variants['items'] ?? [],
                'vatRate' => $this->getVatRate()
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => htmlspecialchars($product['title']) . ' - OxWinches',
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
