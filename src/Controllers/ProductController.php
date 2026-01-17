<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;
use Marti\Frontend\HtmlResources;

class ProductController extends BaseController
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
            
            // Fetch variants for each product
            $productsWithVariants = [];
            foreach ($products['items'] ?? [] as $product) {
                try {
                    $variants = $this->client->products->getProductVariants($product['id']);
                    $product['variants'] = $variants['items'] ?? [];
                } catch (MiaException $e) {
                    $product['variants'] = [];
                }
                $productsWithVariants[] = $product;
            }
            
            $content = $this->view->render('products', [
                'products' => $productsWithVariants,
                'total' => $products['total'] ?? 0,
                'page' => $page,
                'search' => $search,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Products - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load products: " . $e->getMessage());
        }
    }

    public function showAdd(): void
    {
        try {
            // Add Toast UI Editor resources
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addCss('/css/markdown.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $content = $this->view->render('product-form', [
                'product' => null,
                'isEdit' => false,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load add product form: " . $e->getMessage());
        }
    }

    public function showEdit(): void
    {
        $productId = $_GET['id'] ?? '';
        if (!$productId) {
            $this->showError("Product ID is required");
            return;
        }

        try {
            // Add Toast UI Editor resources
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addCss('/css/markdown.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $product = $this->client->products->getProduct($productId);
            $variants = $this->client->products->getProductVariants($productId);
            
            $content = $this->view->render('product-form', [
                'product' => $product,
                'variants' => $variants['items'] ?? [],
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
            
        } catch (MiaException $e) {
            $this->showError("Failed to load product: " . $e->getMessage());
        }
    }

    public function handleAdd(): void
    {
        try {
            $title = $_POST['title'] ?? '';
            
            $data = [
                'title' => $title,
                'slug' => $this->generateSlug($title),
                'shortDescription' => $_POST['short_description'] ?? '',
                'description' => $_POST['description'] ?? '',
                'categories' => !empty($_POST['category']) ? array_map('trim', explode(',', $_POST['category'])) : [],
                'tags' => !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [],
                'status' => $_POST['status'] ?? 'active'
            ];

            // Add weight if provided
            if (!empty($_POST['weight']) && is_numeric($_POST['weight'])) {
                $data['weight'] = (float)$_POST['weight'];
            }

            // Validate required fields
            if (empty($data['title'])) {
                throw new \Exception('Product title is required');
            }
            
            if (empty($data['shortDescription'])) {
                throw new \Exception('Short description is required');
            }

            // Handle images
            $images = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $images[] = [
                            'url' => $imageUrl,
                            'alt' => $data['title']
                        ];
                    }
                }
            }
            
            if (!empty($images)) {
                $data['images'] = $images;
            }

            // Handle variants
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variantData = json_decode($_POST['variants'], true);
                if (is_array($variantData)) {
                    foreach ($variantData as $variant) {
                        if (!empty($variant['sku']) && !empty($variant['price'])) {
                            $processedVariant = [
                                'sku' => $variant['sku'],
                                'price' => round($variant['price'] * 100),
                                'attributes' => $variant['attributes'] ?? []
                            ];
                            
                            if (!empty($variant['presentableName'])) {
                                $processedVariant['presentableName'] = $variant['presentableName'];
                            }
                            
                            $variants[] = $processedVariant;
                        }
                    }
                }
            }

            $result = $this->client->products->createProduct($data);
            
            // Handle variants separately if we have any
            if (!empty($variants) && isset($result['id'])) {
                foreach ($variants as $variant) {
                    try {
                        $this->client->products->createVariant($result['id'], $variant);
                    } catch (\Exception $e) {
                        // Log but continue
                    }
                }
            }
            
            $this->redirect('/products', 'Product created successfully');
        } catch (\Exception $e) {
            // Render form with error
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $formData = $_POST;
            $uploadedImages = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $uploadedImages[] = ['url' => $imageUrl];
                    }
                }
            }
            $formData['images'] = $uploadedImages;
            
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variants = json_decode($_POST['variants'], true) ?: [];
            }
            
            $content = $this->view->render('product-form', [
                'product' => $formData,
                'variants' => $variants,
                'isEdit' => false,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    public function handleEdit(): void
    {
        $productId = $_POST['product_id'] ?? '';
        if (!$productId) {
            $this->showError("Product ID is required");
            return;
        }

        try {
            $data = [
                'title' => $_POST['title'] ?? '',
                'shortDescription' => $_POST['short_description'] ?? '',
                'description' => $_POST['description'] ?? '',
                'categories' => !empty($_POST['category']) ? array_map('trim', explode(',', $_POST['category'])) : [],
                'tags' => !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [],
                'status' => $_POST['status'] ?? 'active'
            ];

            if (!empty($_POST['weight']) && is_numeric($_POST['weight'])) {
                $data['weight'] = (float)$_POST['weight'];
            }

            if (empty($data['title']) || empty($data['shortDescription'])) {
                throw new \Exception('Title and short description are required');
            }

            // Handle images
            $images = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $images[] = ['url' => $imageUrl, 'alt' => $data['title']];
                    }
                }
            }
            
            if (!empty($images)) {
                $data['images'] = $images;
            }

            // Handle variants (similar logic as add)
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variantData = json_decode($_POST['variants'], true);
                if (is_array($variantData)) {
                    foreach ($variantData as $variant) {
                        if (!empty($variant['sku']) && !empty($variant['price'])) {
                            $processedVariant = [
                                'sku' => $variant['sku'],
                                'price' => round($variant['price'] * 100),
                                'attributes' => $variant['attributes'] ?? []
                            ];
                            
                            if (!empty($variant['uuid'])) {
                                $processedVariant['uuid'] = $variant['uuid'];
                            }
                            
                            if (!empty($variant['presentableName'])) {
                                $processedVariant['presentableName'] = $variant['presentableName'];
                            }
                            
                            $variants[] = $processedVariant;
                        }
                    }
                }
            }

            $this->client->products->updateProduct($productId, $data);
            
            // Manage variants
            if (!empty($variants)) {
                try {
                    $existingVariants = $this->client->products->getProductVariants($productId);
                    $existingVariantsByUuid = [];
                    $existingVariantsBySku = [];
                    
                    foreach ($existingVariants['items'] ?? [] as $existing) {
                        $variantId = $existing['uuid'] ?? $existing['id'] ?? null;
                        if ($variantId) {
                            $existingVariantsByUuid[$variantId] = $existing;
                        }
                        if (isset($existing['sku'])) {
                            $existingVariantsBySku[$existing['sku']] = $existing;
                        }
                    }
                    
                    $keptVariantUuids = [];
                    
                    foreach ($variants as $variant) {
                        $uuid = $variant['uuid'] ?? null;
                        $sku = $variant['sku'];
                        
                        if ($uuid && isset($existingVariantsByUuid[$uuid])) {
                            $this->client->products->updateVariant($productId, $uuid, $variant);
                            $keptVariantUuids[] = $uuid;
                        } elseif (isset($existingVariantsBySku[$sku])) {
                            $existing = $existingVariantsBySku[$sku];
                            $existingId = $existing['uuid'] ?? $existing['id'] ?? null;
                            if ($existingId) {
                                $this->client->products->updateVariant($productId, $existingId, $variant);
                                $keptVariantUuids[] = $existingId;
                            }
                        } else {
                            $newVariant = $this->client->products->createVariant($productId, $variant);
                            if (isset($newVariant['uuid']) || isset($newVariant['id'])) {
                                $keptVariantUuids[] = $newVariant['uuid'] ?? $newVariant['id'];
                            }
                        }
                    }
                    
                    foreach ($existingVariantsByUuid as $existingUuid => $existing) {
                        if (!in_array($existingUuid, $keptVariantUuids)) {
                            try {
                                $this->client->products->deleteVariant($productId, $existingUuid);
                            } catch (\Exception $e) {
                                // Log but continue
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log but continue
                }
            }
            
            $this->redirect('/products', 'Product updated successfully');
        } catch (\Exception $e) {
            // Render form with error (similar to add)
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $formData = $_POST;
            $uploadedImages = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $uploadedImages[] = ['url' => $imageUrl, 'alt' => $formData['title'] ?? ''];
                    }
                }
            }
            $formData['images'] = $uploadedImages;
            
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variants = json_decode($_POST['variants'], true) ?: [];
            }
            
            $content = $this->view->render('product-form', [
                'product' => $formData,
                'error' => $e->getMessage(),
                'variants' => $variants,
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    public function handleDelete(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['productId'] ?? '';
            
            if (!$productId) {
                $this->jsonResponse(['success' => false, 'message' => 'Product ID is required'], 400);
            }

            $this->client->products->deleteProduct($productId);
            $this->jsonResponse(['success' => true, 'message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $timestamp = date('ymd-His');
        return $slug . '-' . $timestamp;
    }
}
