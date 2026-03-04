<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\Exceptions\MiaException;

class CheckoutController extends BaseController
{
    public function showCheckout(): void
    {
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            
            // Check if cart is empty
            if (empty($cart['items'])) {
                $_SESSION['checkout_error'] = 'Your cart is empty.';
                $this->redirect('/cart');
                return;
            }
            
            // Get customer data if logged in
            $customer = $this->isLoggedIn() ? $this->getCustomer() : null;
            
            // Create Stripe checkout session and redirect
            try {
                // Build return URLs
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $returnUrl = "{$protocol}://{$host}/checkout/complete";
                
                // Build request data
                $requestData = [
                    'cartId' => $this->cartId,
                    'returnUrl' => $returnUrl
                ];
                
                // Add customer info ONLY if logged in
                if ($customer && $this->isLoggedIn()) {
                    if (!empty($customer['email'])) {
                        $requestData['customerEmail'] = $customer['email'];
                    }
                    if (!empty($customer['id'])) {
                        $requestData['customerId'] = $customer['id'];
                    }
                }
                
                // NOTE: For guest checkout, do NOT send customerEmail or customerId
                // Backend will set customerId to "guest" automatically
                
                // Add shipping address - check customer profile first, then guest session
                $shippingAddress = null;
                $customerName = '';
                $customerPhone = '';
                
                if ($this->isLoggedIn()) {
                    // Logged in: Get full profile for shipping address, name, and phone
                    try {
                        $profile = $this->client->customer->getProfile();
                        if (!empty($profile['shippingAddress'])) {
                            $shippingAddress = $profile['shippingAddress'];
                        }
                        // Get customer name from profile
                        $customerName = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
                        // Get customer phone from profile
                        $customerPhone = $profile['phone'] ?? '';
                    } catch (\Exception $e) {
                        error_log("Failed to get customer profile: " . $e->getMessage());
                    }
                } elseif (!empty($_SESSION['guest_shipping_address'])) {
                    // Guest: Get from session
                    $shippingAddress = $_SESSION['guest_shipping_address'];
                    // For guest, name and phone should be in the address
                    $customerName = $shippingAddress['name'] ?? '';
                    $customerPhone = $shippingAddress['phone'] ?? '';
                }
                
                // Validate shipping address is present and has required fields
                if (!$shippingAddress || 
                    empty($shippingAddress['line1']) || 
                    empty($shippingAddress['city']) || 
                    empty($shippingAddress['postalCode']) || 
                    empty($shippingAddress['country'])) {
                    $_SESSION['checkout_error'] = 'Please provide a complete shipping address before checkout.';
                    $this->redirect('/cart');
                    return;
                }
                
                // Validate customer name is present
                if (empty($customerName)) {
                    $_SESSION['checkout_error'] = 'Please provide your name before checkout.';
                    $this->redirect('/cart');
                    return;
                }
                
                // Validate customer phone is present (required for shipping)
                if (empty($customerPhone)) {
                    $_SESSION['checkout_error'] = 'Please provide your phone number before checkout.';
                    $this->redirect('/cart');
                    return;
                }
                
                // Build validated address with name
                $validatedAddress = [
                    'name' => trim($customerName),
                    'line1' => trim($shippingAddress['line1']),
                    'city' => trim($shippingAddress['city']),
                    'postalCode' => trim($shippingAddress['postalCode']),
                    'country' => trim($shippingAddress['country'])
                ];
                
                // Add optional fields if present
                if (!empty($shippingAddress['line2'])) {
                    $validatedAddress['line2'] = trim($shippingAddress['line2']);
                }
                if (!empty($shippingAddress['state'])) {
                    $validatedAddress['state'] = trim($shippingAddress['state']);
                }
                // Add phone (required) - prefer from profile for logged-in users, otherwise from address
                $validatedAddress['phone'] = trim($customerPhone);
                
                $requestData['shippingAddress'] = $validatedAddress;
                
                // Create Stripe session
                $session = $this->client->checkout->createStripeSession($requestData);
                
                // Redirect to Stripe
                header("Location: {$session['url']}");
                exit;
                
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $errorClass = get_class($e);
                error_log("Stripe checkout error [{$errorClass}]: {$errorMessage}");
                
                // Check for specific errors and redirect back to cart with user-friendly error
                if (strpos($errorMessage, 'stripe_not_configured') !== false) {
                    $_SESSION['checkout_error'] = 'Payment processing is not configured. Please contact support.';
                } elseif (strpos($errorMessage, 'Cart not found') !== false) {
                    $_SESSION['checkout_error'] = 'Your cart could not be found. Please try again.';
                } elseif (strpos($errorMessage, 'insufficient_permissions') !== false || strpos($errorMessage, '403') !== false) {
                    $_SESSION['checkout_error'] = 'Checkout is temporarily unavailable. Please contact support.';
                } elseif (strpos($errorMessage, 'unauthorized') !== false || strpos($errorMessage, '401') !== false) {
                    $_SESSION['checkout_error'] = 'Authentication error. Please try logging in again.';
                } elseif (strpos($errorMessage, '404') !== false) {
                    $_SESSION['checkout_error'] = 'Checkout service unavailable. Please contact support.';
                } else {
                    $_SESSION['checkout_error'] = 'Unable to process checkout. Please try again.';
                }
                
                $this->redirect('/cart');
                return;
            }
        } catch (MiaException $e) {
            error_log("Checkout failed [MiaException]: " . $e->getMessage());
            $_SESSION['checkout_error'] = "Failed to load checkout. Please try again.";
            $this->redirect('/cart');
        } catch (\Exception $e) {
            error_log("Checkout failed [Unexpected]: " . $e->getMessage());
            $_SESSION['checkout_error'] = "Unable to process checkout. Please try again.";
            $this->redirect('/cart');
        }
    }

    public function showCheckoutComplete(): void
    {
        $status = $_GET['status'] ?? null;
        $orderId = $_GET['orderId'] ?? null;
        $cartId = $_GET['cartId'] ?? null;
        
        $order = null;
        $error = null;
        
        if ($status === 'success' && $orderId) {
            try {
                // Fetch the order details
                $order = $this->client->orders->getOrder($orderId);
                
                // Enrich order items with product details (images, full titles)
                if (!empty($order['items'])) {
                    foreach ($order['items'] as &$item) {
                        if (!empty($item['productId'])) {
                            try {
                                $product = $this->client->products->getProduct($item['productId']);
                                
                                // Add product image
                                if (!empty($product['images']) && is_array($product['images'])) {
                                    $item['image'] = $product['images'][0];
                                }
                                
                                // Add full product title if not already present
                                if (empty($item['name']) || $item['name'] === $item['sku']) {
                                    $item['name'] = $product['title'] ?? $item['name'];
                                }
                            } catch (\Exception $e) {
                                error_log("Failed to fetch product {$item['productId']}: " . $e->getMessage());
                                // Continue without product details
                            }
                        }
                    }
                    unset($item); // Break reference
                }
                
                // Clear cart from session
                unset($_SESSION['cart_id']);
                $this->cartId = null;
                
            } catch (\Exception $e) {
                error_log("Failed to fetch order: " . $e->getMessage());
                $error = "Unable to retrieve order details. Please check your order history.";
            }
        }
        
        $content = $this->view->render('checkout-complete', [
            'order' => $order,
            'error' => $error,
            'supportedCountries' => $this->getSupportedCountries()
        ]);
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => $status === 'success' ? 'Order Complete' : 'Checkout',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }
}
