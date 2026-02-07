<?php

namespace Marti\Frontend\Controllers\Frontend;

class PageController extends BaseController
{
    public function home(): void
    {
        try {
            // Fetch featured products for homepage
            $products = $this->client->products->getProducts([
                'limit' => 8,
                'page' => 1
            ]);
            
            // Fetch homepage categories setting (controlled by site admins)
            $homepageCategories = null;
            try {
                $setting = $this->client->siteSettings->getSetting('homepage_categories');
                if (isset($setting['value'])) {
                    // Decode if it's a JSON string
                    if (is_string($setting['value'])) {
                        $decoded = json_decode($setting['value'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $homepageCategories = $decoded;
                        }
                    } elseif (is_array($setting['value'])) {
                        $homepageCategories = $setting['value'];
                    }
                    
                    // Filter out empty category names
                    if (is_array($homepageCategories)) {
                        $homepageCategories = array_filter($homepageCategories, function($cat) {
                            return !empty(trim($cat));
                        });
                        // Re-index array after filtering
                        $homepageCategories = array_values($homepageCategories);
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to load homepage_categories setting: " . $e->getMessage());
            }
            
            // Fetch category metadata for each category
            $categoryMetadata = [];
            if (is_array($homepageCategories)) {
                foreach ($homepageCategories as $category) {
                    $categoryKey = str_replace(' ', '_', $category);
                    $categoryMetadata[$category] = [
                        'image' => $this->getCategorySetting("cat_{$categoryKey}_image", '/images/logo-small.png'),
                        'tagline' => $this->getCategorySetting("cat_{$categoryKey}_tagline", 'View our selection'),
                        'display_text' => $this->getCategorySetting("cat_{$categoryKey}_display_text", $category)
                    ];
                }
            }
            
            $this->renderLayout('home', [
                'products' => $products,
                'homepageCategories' => $homepageCategories,
                'categoryMetadata' => $categoryMetadata
            ], 'OxWinches - Premium Winches & Recovery Equipment');
        } catch (\Exception $e) {
            error_log("Failed to load products for homepage: " . $e->getMessage());
            // Show homepage without products
            $this->renderLayout('home', [
                'products' => ['items' => [], 'total' => 0],
                'homepageCategories' => null,
                'categoryMetadata' => []
            ], 'OxWinches - Premium Winches & Recovery Equipment');
        }
    }

    public function category(string $categorySlug): void
    {
        try {
            // Decode and convert hyphens back to spaces
            $categoryName = str_replace('-', ' ', rawurldecode($categorySlug));
            
            // Validate category name is not empty
            if (empty(trim($categoryName))) {
                error_log("Empty category name provided");
                $this->show404();
                return;
            }
            
            // Get page from query string
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            
            // Fetch products filtered by tag
            $products = $this->client->products->getProducts([
                'limit' => 12,
                'page' => $page,
                'tag' => $categoryName
            ]);
            
            // Get categories and filters for the sidebar (pass tag to get filtered counts)
            $categories = $this->getProductCategories(['tag' => $categoryName]);
            
            $this->renderLayout('products', [
                'products' => $products,
                'currentPage' => $page,
                'page' => $page,
                'categories' => $categories,
                'selectedFilters' => [],
                'search' => '',
                'categoryFilter' => $categoryName,
                'categorySlug' => $categorySlug,
                'baseUrl' => '/category/' . $categorySlug
            ], htmlspecialchars($categoryName) . ' - OxWinches');
        } catch (\Exception $e) {
            error_log("Failed to load category products: " . $e->getMessage());
            $this->show404();
        }
    }

    public function categories(): void
    {
        try {
            // Get all tags (categories)
            $tagsData = $this->client->products->getTags(['status' => 'active']);
            $tags = $tagsData['tags'] ?? [];
            
            // Filter out empty tags
            $tags = array_filter($tags, function($tag) {
                return !empty(trim($tag['name']));
            });
            
            // Get excluded tags setting
            $excludedTags = [];
            try {
                $setting = $this->client->siteSettings->getSetting('exclude_tags_from_categories');
                if (isset($setting['value'])) {
                    // Decode if it's a JSON string
                    if (is_string($setting['value'])) {
                        $decoded = json_decode($setting['value'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $excludedTags = $decoded;
                        }
                    } elseif (is_array($setting['value'])) {
                        $excludedTags = $setting['value'];
                    }
                    
                    // Filter out empty values and trim
                    if (is_array($excludedTags)) {
                        $excludedTags = array_filter(array_map('trim', $excludedTags), function($tag) {
                            return !empty($tag);
                        });
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to load exclude_tags_from_categories setting: " . $e->getMessage());
            }
            
            // Filter out excluded tags
            if (!empty($excludedTags)) {
                $tags = array_filter($tags, function($tag) use ($excludedTags) {
                    return !in_array($tag['name'], $excludedTags);
                });
            }
            
            // Fetch category metadata for each category
            $categoryMetadata = [];
            foreach ($tags as $tag) {
                $category = $tag['name'];
                $categoryKey = str_replace(' ', '_', $category);
                $categoryMetadata[$category] = [
                    'image' => $this->getCategorySetting("cat_{$categoryKey}_image", '/images/logo-small.png'),
                    'tagline' => $this->getCategorySetting("cat_{$categoryKey}_tagline", $tag['count'] . ' ' . ($tag['count'] === 1 ? 'product' : 'products')),
                    'display_text' => $this->getCategorySetting("cat_{$categoryKey}_display_text", $category)
                ];
            }
            
            $this->renderLayout('categories', [
                'categories' => $tags,
                'categoryMetadata' => $categoryMetadata
            ], 'Categories - OxWinches');
        } catch (\Exception $e) {
            error_log("Failed to load categories: " . $e->getMessage());
            $this->show404();
        }
    }
    
    private function getCategorySetting(string $key, string $default): string
    {
        try {
            $setting = $this->client->siteSettings->getSetting($key);
            if (isset($setting['value']) && !empty(trim($setting['value']))) {
                return $setting['value'];
            }
        } catch (\Exception $e) {
            // Setting doesn't exist, use default
        }
        return $default;
    }
    
    private function getProductCategories(array $apiFilters = []): array
    {
        try {
            // Build filters for the API call
            $filters = ['status' => 'active'];
            
            // If we have a tag filter, pass it to get filtered counts
            if (!empty($apiFilters['tag'])) {
                $filters['tag'] = $apiFilters['tag'];
            }
            
            $categories = $this->client->products->getCategories($filters);
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
        } catch (\Exception $e) {
            error_log("Failed to fetch categories: " . $e->getMessage());
            return ['primary' => [], 'filters' => []];
        }
    }

    public function about(): void
    {
        $this->renderLayout('about', [], 'About Us - OxWinches');
    }

    public function contact(): void
    {
        $this->renderLayout('contact', [], 'Contact Us - OxWinches');
    }

    public function help(): void
    {
        $this->renderLayout('help', [], 'Help & Support - OxWinches');
    }

    public function privacy(): void
    {
        $this->renderLayout('privacy', [], 'Privacy Policy - OxWinches');
    }

    public function terms(): void
    {
        $this->renderLayout('terms', [], 'Terms & Conditions - OxWinches');
    }

    public function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => '404 Not Found - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    public function apiGetCategories(): void
    {
        try {
            $categories = $this->client->categories->getCategories();
            echo json_encode($categories);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
