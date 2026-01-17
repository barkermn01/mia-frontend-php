<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class StockController extends BaseController
{
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $filters['search'] = $search;
            }

            $products = $this->client->products->getProducts($filters);
            
            // Fetch variants and stock for each product
            $productsWithStock = [];
            foreach ($products['items'] ?? [] as $product) {
                try {
                    $variants = $this->client->products->getProductVariants($product['id']);
                    $product['variants'] = $variants['items'] ?? [];
                    
                    // Fetch stock for each variant
                    foreach ($product['variants'] as &$variant) {
                        try {
                            $stock = $this->client->stock->getStock($variant['sku']);
                            $variant['stock'] = $stock;
                        } catch (\Exception $e) {
                            $variant['stock'] = [
                                'available' => 0,
                                'unlimited' => false,
                                'inventoryType' => 'physical'
                            ];
                        }
                    }
                } catch (MiaException $e) {
                    $product['variants'] = [];
                }
                $productsWithStock[] = $product;
            }
            
            $content = $this->view->render('stock', [
                'products' => $productsWithStock,
                'total' => $products['total'] ?? 0,
                'page' => $page,
                'search' => $search,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Stock Management - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load stock: " . $e->getMessage());
        }
    }

    public function handleUpdate(): void
    {
        try {
            $updates = $_POST['stock_updates'] ?? [];
            
            if (empty($updates)) {
                throw new \Exception('No stock updates provided');
            }

            $successCount = 0;
            $errors = [];

            foreach ($updates as $sku => $update) {
                try {
                    $delta = (int)($update['delta'] ?? 0);
                    $reason = $update['reason'] ?? 'Manual adjustment';
                    
                    if ($delta !== 0) {
                        $this->client->stock->adjustStock([
                            'sku' => $sku,
                            'delta' => $delta,
                            'reason' => $reason
                        ]);
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "SKU {$sku}: " . $e->getMessage();
                }
            }

            $message = "Successfully updated {$successCount} stock level(s)";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            $this->redirect('/stock?page=' . ($_POST['page'] ?? 1), $message);
        } catch (\Exception $e) {
            $this->redirect('/stock?page=' . ($_POST['page'] ?? 1), $e->getMessage(), true);
        }
    }
}
