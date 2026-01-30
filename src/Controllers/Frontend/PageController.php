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
            
            $this->renderLayout('home', [
                'products' => $products
            ], 'OxWinches - Premium Winches & Recovery Equipment');
        } catch (\Exception $e) {
            error_log("Failed to load products for homepage: " . $e->getMessage());
            // Show homepage without products
            $this->renderLayout('home', [
                'products' => ['items' => [], 'total' => 0]
            ], 'OxWinches - Premium Winches & Recovery Equipment');
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
