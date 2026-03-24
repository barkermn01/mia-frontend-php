/**
 * Cart Page JavaScript
 * Handles cart-specific functionality including shipping, address management, and basket saving
 */

// Cart page data (will be set by template)
window.CartPage = {
    subtotalExVat: 0,
    totalVat: 0,
    
    init(data) {
        this.subtotalExVat = data.subtotalExVat || 0;
        this.totalVat = data.totalVat || 0;
        
        // Override global cart functions to reload page instead of using AJAX
        this.overrideCartFunctions();
        
        // Initialize shipping address form
        this.initAddressForm();
    },
    
    overrideCartFunctions() {
        // Override updateCartItem to reload page
        window.updateCartItem = async function(itemId, qty) {
            try {
                const response = await MiaStore.request('/api/cart/update', {
                    method: 'POST',
                    body: JSON.stringify({ itemId, qty })
                });

                if (response.success) {
                    // Reload page immediately to show updated cart
                    location.reload();
                } else {
                    MiaStore.showToast(response.message || 'Failed to update cart', 'error');
                }
            } catch (error) {
                console.error('Update cart error:', error);
                MiaStore.showToast('Failed to update cart', 'error');
            }
        };

        // Override removeCartItem to reload page
        window.removeCartItem = async function(itemId) {
            if (!confirm('Remove this item from cart?')) return;
            
            try {
                const response = await MiaStore.request('/api/cart/remove', {
                    method: 'POST',
                    body: JSON.stringify({ itemId })
                });

                if (response.success) {
                    // Reload page immediately to show updated cart
                    location.reload();
                } else {
                    MiaStore.showToast(response.message || 'Failed to remove item', 'error');
                }
            } catch (error) {
                console.error('Remove cart error:', error);
                MiaStore.showToast('Failed to remove item', 'error');
            }
        };
    },
    
    initAddressForm() {
        const form = document.getElementById('shipping-address-form');
        if (form) {
            form.addEventListener('submit', this.handleAddressSubmit.bind(this));
        }
        
        // Initialize guest email form
        const emailForm = document.getElementById('guest-email-form');
        if (emailForm) {
            emailForm.addEventListener('submit', this.handleEmailSubmit.bind(this));
        }
    },
    
    async handleAddressSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const address = {
            line1: formData.get('line1'),
            line2: formData.get('line2'),
            city: formData.get('city'),
            state: formData.get('state'),
            postalCode: formData.get('postalCode'),
            country: formData.get('country'),
            phone: formData.get('phone')
        };
        
        // Add name for guest users
        const isLoggedIn = window.MiaStoreConfig?.isLoggedIn || false;
        if (!isLoggedIn) {
            address.name = formData.get('name');
        }
        
        try {
            if (isLoggedIn) {
                // Logged in: Save to customer profile
                const response = await MiaStore.request('/api/customer/update-shipping-address', {
                    method: 'POST',
                    body: JSON.stringify({ shippingAddress: address })
                });
                
                if (response.success) {
                    MiaStore.showToast('Address saved successfully!');
                    // Reload page to show updated address and shipping options
                    window.location.reload();
                } else {
                    MiaStore.showToast(response.message || 'Failed to save address', 'error');
                }
            } else {
                // Guest: Save to session
                const response = await MiaStore.request('/api/cart/save-guest-address', {
                    method: 'POST',
                    body: JSON.stringify({ shippingAddress: address })
                });
                
                if (response.success) {
                    MiaStore.showToast('Address saved for checkout!');
                    // Reload page to show shipping options
                    window.location.reload();
                } else {
                    MiaStore.showToast(response.message || 'Failed to save address', 'error');
                }
            }
        } catch (error) {
            console.error('Save address error:', error);
            MiaStore.showToast('Failed to save address', 'error');
        }
    },
    
    async handleEmailSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const email = formData.get('email');
        
        // Validate email format
        if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            MiaStore.showToast('Please enter a valid email address', 'error');
            return;
        }
        
        try {
            const response = await MiaStore.request('/api/cart/save-guest-email', {
                method: 'POST',
                body: JSON.stringify({ email })
            });
            
            if (response.success) {
                MiaStore.showToast('Email saved successfully!');
                // Reload page to show saved email
                window.location.reload();
            } else {
                MiaStore.showToast(response.message || 'Failed to save email', 'error');
            }
        } catch (error) {
            console.error('Save email error:', error);
            MiaStore.showToast('Failed to save email', 'error');
        }
    }
};

// Global functions for onclick handlers
window.updateShippingCost = function() {
    const select = document.getElementById('shipping-method-select');
    const selectedOption = select.options[select.selectedIndex];
    const shippingCost = parseInt(selectedOption.dataset.cost);
    
    // Update shipping cost display
    const shippingDisplay = document.getElementById('shipping-cost-display');
    if (shippingCost === 0) {
        shippingDisplay.textContent = 'Free';
        shippingDisplay.className = 'font-medium text-green-600';
    } else {
        shippingDisplay.textContent = '£' + (shippingCost / 100).toFixed(2);
        shippingDisplay.className = 'font-medium';
    }
    
    // Update total
    const total = CartPage.subtotalExVat + CartPage.totalVat + shippingCost;
    document.getElementById('total-display').textContent = '£' + (total / 100).toFixed(2);
};

window.saveBasket = async function() {
    const basketName = document.getElementById('basket-name').value.trim();
    if (!basketName) {
        MiaStore.showToast('Please enter a basket name', 'error');
        return;
    }
    
    try {
        const response = await MiaStore.request('/api/cart/save-basket', {
            method: 'POST',
            body: JSON.stringify({ name: basketName })
        });
        
        if (response.success) {
            MiaStore.showToast('Basket saved successfully!');
            document.getElementById('basket-name').value = '';
        } else {
            MiaStore.showToast(response.message || 'Failed to save basket', 'error');
        }
    } catch (error) {
        console.error('Save basket error:', error);
        MiaStore.showToast('Failed to save basket', 'error');
    }
};

window.showAddressForm = function() {
    document.getElementById('saved-address').classList.add('hidden');
    document.getElementById('address-form').classList.remove('hidden');
};

window.hideAddressForm = function() {
    document.getElementById('saved-address').classList.remove('hidden');
    document.getElementById('address-form').classList.add('hidden');
};

window.showEmailForm = function() {
    document.getElementById('saved-email').classList.add('hidden');
    document.getElementById('email-form').classList.remove('hidden');
};

window.hideEmailForm = function() {
    document.getElementById('saved-email').classList.remove('hidden');
    document.getElementById('email-form').classList.add('hidden');
};
