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
            
            // Get customer profile if logged in (same as account page)
            $profile = null;
            if ($this->isLoggedIn()) {
                try {
                    $profile = $this->client->customer->getProfile();
                } catch (\Exception $e) {
                    error_log("Cart - Failed to load customer profile: " . $e->getMessage());
                }
            }
            
            // Determine shipping address source (customer profile or guest session)
            $shippingAddress = null;
            if ($profile && !empty($profile['shippingAddress']['country'])) {
                $shippingAddress = $profile['shippingAddress'];
            } elseif (!empty($_SESSION['guest_shipping_address']['country'])) {
                $shippingAddress = $_SESSION['guest_shipping_address'];
            }
            
            // Get shipping options if we have an address with country
            $shippingOptions = null;
            if ($shippingAddress) {
                try {
                    $shippingOptions = $this->client->shipping->getCartShippingOptions(
                        $this->cartId,
                        $shippingAddress['country']
                    );
                } catch (\Exception $e) {
                    error_log("Failed to get shipping options: " . $e->getMessage());
                }
            }
            
            // Get VAT rate from settings
            $vatRate = $this->getVatRate();
            
            // Check if checkout is enabled
            $checkoutEnabled = $this->isCheckoutEnabled();
            
            // Add cart.js for cart page functionality
            \Marti\Frontend\HtmlResources::getInstance()->addJsBody('/js/cart.js');
            
            $this->renderLayout('cart', [
                'cart' => $cart,
                'shippingOptions' => $shippingOptions,
                'profile' => $profile,
                'vatRate' => $vatRate,
                'checkoutEnabled' => $checkoutEnabled,
                'isLoggedIn' => $this->isLoggedIn(),
                'supportedCountries' => $this->getSupportedCountries()
            ], 'Shopping Cart - OxWinches');
            
        } catch (MiaException $e) {
            $this->showError("Failed to load cart: " . $e->getMessage());
        }
    }

    // API Methods
    public function apiGet(): void
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
            
            $html = $this->view->render('cart-sidebar', [
                'cart' => $cart,
                'vatRate' => $this->getVatRate()
            ]);
            
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
        
        if (!$sku) {
            echo json_encode(['success' => false, 'message' => 'SKU is required']);
            return;
        }

        try {
            $result = $this->client->cart->addToCart($this->cartId, $sku, $qty);
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
            
            echo json_encode([
                'success' => true,
                'addedItems' => $result['addedItems'] ?? 0,
                'totalItems' => $result['totalItems'] ?? 0,
                'skippedItems' => $result['skippedItems'] ?? [],
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (\Exception $e) {
            error_log("Load basket error: " . $e->getMessage());
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

    public function apiSaveGuestAddress(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate shipping address
            if (!isset($data['shippingAddress'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Shipping address is required'
                ]);
                return;
            }

            $address = $data['shippingAddress'];

            // Validate required fields
            $requiredFields = ['line1', 'city', 'postalCode', 'country'];
            foreach ($requiredFields as $field) {
                if (empty($address[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Shipping address {$field} is required"
                    ]);
                    return;
                }
            }

            // Trim all string values
            $guestAddress = [
                'line1' => trim($address['line1']),
                'city' => trim($address['city']),
                'postalCode' => trim($address['postalCode']),
                'country' => trim($address['country'])
            ];

            // Add optional fields if present
            if (!empty($address['name'])) {
                $guestAddress['name'] = trim($address['name']);
            }
            if (!empty($address['line2'])) {
                $guestAddress['line2'] = trim($address['line2']);
            }
            if (!empty($address['state'])) {
                $guestAddress['state'] = trim($address['state']);
            }
            if (!empty($address['phone'])) {
                $guestAddress['phone'] = trim($address['phone']);
            }

            // Save to session for guest checkout
            $_SESSION['guest_shipping_address'] = $guestAddress;

            echo json_encode(['success' => true, 'message' => 'Address saved for checkout']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
        } catch (\Exception $e) {
            // If setting doesn't exist or there's an error, use default
        }
        
        // Default to UK VAT rate of 20%
        return 0.20;
    }
    
    private function isCheckoutEnabled(): bool
    {
        try {
            $setting = $this->getSetting('checkout_enabled');
            
            if (isset($setting['value'])) {
                $value = strtolower(trim($setting['value']));
                // Check for disabled values
                if (in_array($value, ['no', 'false', 'disabled', '0'])) {
                    return false;
                }
            }
        } catch (\Exception $e) {
            // If setting doesn't exist, checkout is enabled by default
        }
        
        return true;
    }
}
