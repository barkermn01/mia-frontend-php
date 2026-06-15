<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\MiaClient;
use Marti\Frontend\View;
use Marti\Frontend\SystemsDataProvider;

class SearchController extends BaseController
{
    private SystemsDataProvider $systems;

    public function __construct(MiaClient $client, View $view)
    {
        parent::__construct($client, $view);
        $this->systems = new SystemsDataProvider(__DIR__ . '/../../../data');
    }

    public function index(): void
    {
        $query = trim($_GET['q'] ?? '');
        $products = ['items' => [], 'total' => 0];
        $systemResults = [];

        if ($query) {
            // Search products via API
            try {
                $products = $this->client->products->getProducts([
                    'search' => $query,
                    'limit' => 20,
                ]);
            } catch (\Exception $e) {
                error_log("Search products error: " . $e->getMessage());
            }

            // Search systems from catalogue
            $systemResults = $this->systems->searchSystems($query, 20);
        }

        $this->renderLayout('search', [
            'query' => $query,
            'products' => $products,
            'systemResults' => $systemResults,
        ], $query ? "Search: {$query}" : 'Search');
    }
}
