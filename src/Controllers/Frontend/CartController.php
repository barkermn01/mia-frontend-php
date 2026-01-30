<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\Exceptions\MiaException;

class CartController extends BaseController
{
    public function show(): void
    {
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            
            // Enrich cart items with product data
            if (!empty($cart['items'])) {
                foreach ($cart['items'] as &$item) {
                    try {
                        $product = $this->client->products->getProduct($item['productId']);
                        $item['productTitle'] = $product['title'] ?? $item['sku'];
                        $item['title'] = $product['title'] ?? $item['sku'];
                        $item['image'] = !empty($product['images']) ? $product['images'][0] : null;
                    } catch (\Exception $e) {
                        error_log("Failed to enrich cart item {$item['sku']}: " . $e->getMessage());
                        $item['productTitle'] = $item['sku'];
                        $item['title'] = $item['sku'];
                    }
                }
                unset($item);
            }
            
            // Get shipping options
            $shippingOptions = null;
            $customer = $this->getCustomer();
            if ($customer && !empty($customer['shippingAddress']['country'])) {
                try {
                    $shippingOptions = $this->client->shipping->getCartShippingOptions(
                        $this->cartId,
                        $customer['shippingAddress']['country']
                    );
                    error_log("Shipping options fetched for country {$customer['shippingAddress']['country']}: " . json_encode($shippingOptions));
                } catch (\Exception $e) {
                    error_log("Failed to get shipping options: " . $e->getMessage());
                }
            }
            
            // Get VAT rate from settings
            $vatRate = $this->getVatRate();
            
            $this->renderLayout('cart', [
                'cart' => $cart,
                'shippingOptions' => $shippingOptions,
                'customer' => $customer,
                'vatRate' => $vatRate
            ], 'Shopping Cart - OxWinches');
            
        } catch (MiaException $e) {
            $this->showError("Failed to load cart: " . $e->getMessage());
        }
    }

    // API Methods
    public function apiGet(): void
    {
        error_log("API GET Cart - Session ID: " . session_id());
        error_log("API GET Cart - Session cart_id: " . ($_SESSION['cart_id'] ?? 'NOT SET'));
        error_log("API GET Cart - Controller cartId: " . ($this->cartId ?? 'NULL'));
        
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            
            // Enrich cart items with product data
            if (!empty($cart['items'])) {
                foreach ($cart['items'] as &$item) {
                    try {
                        $product = $this->client->products->getProduct($item['productId']);
                        $item['productTitle'] = $product['title'] ?? $item['sku'];
                        $item['title'] = $product['title'] ?? $item['sku'];
                        $item['image'] = !empty($product['images']) ? $product['images'][0] : null;
                        
                        // Find the specific variant by SKU to get the display name
                        // Only show variant if product has multiple variants
                        $item['variantName'] = null;
                        if (!empty($product['variants']) && count($product['variants']) > 1) {
                            foreach ($product['variants'] as $variant) {
                                if ($variant['sku'] === $item['sku']) {
                                    $item['variantName'] = $variant['presentableName'] ?? null;
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to enrich cart item {$item['sku']}: " . $e->getMessage());
                        $item['productTitle'] = $item['sku'];
                        $item['title'] = $item['sku'];
                    }
                }
                unset($item);
            }
            
            error_log("Cart data: " . json_encode($cart));
            $html = $this->view->render('cart-sidebar', ['cart' => $cart]);
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            error_log("Cart API error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiAdd(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $sku = $input['sku'] ?? '';
        $qty = (int)($input['qty'] ?? 1);
        
        error_log("Add to cart request - SKU: $sku, Qty: $qty, Cart ID: " . $this->cartId);
        
        if (!$sku) {
            echo json_encode(['success' => false, 'message' => 'SKU is required']);
            return;
        }

        try {
            $result = $this->client->cart->addToCart($this->cartId, $sku, $qty);
            error_log("Add to cart success: " . json_encode($result));
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            error_log("Add to cart error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiUpdate(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['itemId'] ?? '';
        $qty = (int)($input['qty'] ?? 0);
        
        if (!$itemId) {
            echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            return;
        }

        try {
            if ($qty > 0) {
                $this->client->cart->updateCartItem($this->cartId, $itemId, $qty);
            } else {
                $this->client->cart->removeCartItem($this->cartId, $itemId);
            }
            
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiRemove(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['itemId'] ?? '';
        
        if (!$itemId) {
            echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            return;
        }

        try {
            $this->client->cart->removeCartItem($this->cartId, $itemId);
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiSaveBasket(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->client->cart->saveBasket([
                'name' => $data['name'],
                'cartId' => $this->cartId
            ]);
            echo json_encode(['success' => true, 'basket' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiLoadBasket(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->client->cart->loadSavedBasket($data['basketName'], [
                'cartId' => $this->cartId
            ]);
            echo json_encode(['success' => true, 'cart' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiDeleteBasket(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $this->client->cart->deleteSavedBasket($data['basketName']);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    private function getVatRate(): float
    {
        try {
            // Get VAT rate setting (no siteId needed, SDK handles it)
            $setting = $this->client->siteSettings->getSetting('vat_rate');
            
            if (isset($setting['value']) && is_numeric($setting['value'])) {
                // Return as decimal (e.g., 0.20 for 20%)
                return (float)$setting['value'] / 100;
            }
        } catch (\Exception $e) {
            // If setting doesn't exist or there's an error, use default
        }
        
        // Default to UK VAT rate of 20%
        return 0.20;
    }
}
