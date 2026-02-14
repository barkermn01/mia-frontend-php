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
                error_log("Checkout failed: Cart is empty (cartId: {$this->cartId})");
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
                
                error_log("Creating Stripe session for cartId: {$this->cartId}, returnUrl: {$returnUrl}");
                
                // Build request data
                $requestData = [
                    'cartId' => $this->cartId,
                    'returnUrl' => $returnUrl
                ];
                
                // Add customer email if available
                if ($customer && !empty($customer['email'])) {
                    $requestData['customerEmail'] = $customer['email'];
                }
                
                // Add shipping address - check customer profile first, then guest session
                $shippingAddress = null;
                $customerName = '';
                
                if ($this->isLoggedIn()) {
                    // Logged in: Get full profile for shipping address and name
                    try {
                        $profile = $this->client->customer->getProfile();
                        if (!empty($profile['shippingAddress'])) {
                            $shippingAddress = $profile['shippingAddress'];
                        }
                        // Get customer name from profile
                        $customerName = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
                    } catch (\Exception $e) {
                        error_log("Failed to get customer profile: " . $e->getMessage());
                    }
                } elseif (!empty($_SESSION['guest_shipping_address'])) {
                    // Guest: Get from session
                    $shippingAddress = $_SESSION['guest_shipping_address'];
                    // For guest, name should be in the address
                    $customerName = $shippingAddress['name'] ?? '';
                }
                
                // Validate shipping address is present and has required fields
                if (!$shippingAddress || 
                    empty($shippingAddress['line1']) || 
                    empty($shippingAddress['city']) || 
                    empty($shippingAddress['postalCode']) || 
                    empty($shippingAddress['country'])) {
                    error_log("Checkout blocked: Missing or incomplete shipping address");
                    $_SESSION['checkout_error'] = 'Please provide a complete shipping address before checkout.';
                    $this->redirect('/cart');
                    return;
                }
                
                // Validate customer name is present
                if (empty($customerName)) {
                    error_log("Checkout blocked: Missing customer name");
                    $_SESSION['checkout_error'] = 'Please provide your name before checkout.';
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
                
                $requestData['shippingAddress'] = $validatedAddress;
                
                // Create Stripe session
                $session = $this->client->checkout->createStripeSession($requestData);
                
                error_log("Stripe session created successfully, redirecting to: {$session['url']}");
                
                // Redirect to Stripe
                header("Location: {$session['url']}");
                exit;
                
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $errorClass = get_class($e);
                error_log("Stripe checkout error [{$errorClass}]: {$errorMessage}");
                error_log("Stack trace: " . $e->getTraceAsString());
                
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
            error_log("Stack trace: " . $e->getTraceAsString());
            $_SESSION['checkout_error'] = "Failed to load checkout. Please try again.";
            $this->redirect('/cart');
        } catch (\Exception $e) {
            error_log("Checkout failed [Unexpected]: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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
                
                // Note: Order items from the API don't include productId or full product titles
                // They only have SKU and name (which is often just the SKU)
                // This is a backend limitation - the order should store full product details
                
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
            'error' => $error
        ]);
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => $status === 'success' ? 'Order Complete - OxWinches' : 'Checkout - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }
}
