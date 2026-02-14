// Account Page JavaScript

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Make functions globally available
    window.showEditProfileDialog = showEditProfileDialog;
    window.showEditAddressDialog = showEditAddressDialog;
    window.loadBasket = loadBasket;
    window.viewBasket = viewBasket;
    window.deleteBasket = deleteBasket;
});

function showEditProfileDialog() {
    // Get profile data from MiaStoreConfig
    const profileData = window.MiaStoreConfig?.profileData || {};
    
    const modalManager = new ModalManager();
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Profile</h3>
            <form id="edit-profile-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" id="profile-first-name" value="${profileData.firstName || ''}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" id="profile-last-name" value="${profileData.lastName || ''}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone (Optional)</label>
                    <input type="tel" id="profile-phone" value="${profileData.phone || ''}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </form>
            <div class="flex justify-end space-x-3 mt-6">
                <button class="cancel-btn px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button class="save-btn px-4 py-2 rounded-lg text-white transition-colors bg-blue-600 hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    const saveBtn = modal.querySelector('.save-btn');
    const cancelBtn = modal.querySelector('.cancel-btn');
    
    saveBtn.addEventListener('click', async () => {
        const firstName = document.getElementById('profile-first-name').value.trim();
        const lastName = document.getElementById('profile-last-name').value.trim();
        const phone = document.getElementById('profile-phone').value.trim();
        
        if (!firstName || !lastName) {
            const alertManager = new ModalManager();
            alertManager.alert({
                type: 'error',
                title: 'Validation Error',
                message: 'First name and last name are required'
            });
            return;
        }
        
        try {
            const response = await fetch('/api/customer/update-profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    firstName,
                    lastName,
                    phone: phone || undefined
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                modalManager.hideModal(modal);
                modal.remove();
                window.location.reload();
            } else {
                const alertManager = new ModalManager();
                alertManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to update profile: ' + (result.message || 'Unknown error')
                });
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            const alertManager = new ModalManager();
            alertManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Failed to update profile: ' + error.message
            });
        }
    });
    
    cancelBtn.addEventListener('click', () => {
        modalManager.hideModal(modal);
        modal.remove();
    });
    
    modalManager.showModal(modal);
}

function showEditAddressDialog() {
    // Get delivery address from MiaStoreConfig
    const deliveryAddress = window.MiaStoreConfig?.deliveryAddress || null;
    
    const modalManager = new ModalManager();
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 overflow-y-auto';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 my-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">${deliveryAddress ? 'Edit' : 'Add'} Delivery Address</h3>
            <form id="edit-address-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 1 *</label>
                    <input type="text" id="address-line1" value="${deliveryAddress?.line1 || ''}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                    <input type="text" id="address-line2" value="${deliveryAddress?.line2 || ''}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                    <input type="text" id="address-city" value="${deliveryAddress?.city || ''}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">State/County</label>
                    <input type="text" id="address-state" value="${deliveryAddress?.state || ''}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code *</label>
                    <input type="text" id="address-postal-code" value="${deliveryAddress?.postalCode || ''}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                    <input type="text" id="address-country" value="${deliveryAddress?.country || 'GB'}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </form>
            <div class="flex justify-end space-x-3 mt-6">
                <button class="cancel-btn px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button class="save-btn px-4 py-2 rounded-lg text-white transition-colors bg-blue-600 hover:bg-blue-700">
                    Save Address
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    const saveBtn = modal.querySelector('.save-btn');
    const cancelBtn = modal.querySelector('.cancel-btn');
    
    saveBtn.addEventListener('click', async () => {
        const line1 = document.getElementById('address-line1').value.trim();
        const line2 = document.getElementById('address-line2').value.trim();
        const city = document.getElementById('address-city').value.trim();
        const state = document.getElementById('address-state').value.trim();
        const postalCode = document.getElementById('address-postal-code').value.trim();
        const country = document.getElementById('address-country').value.trim();
        
        if (!line1 || !city || !postalCode || !country) {
            const alertManager = new ModalManager();
            alertManager.alert({
                type: 'error',
                title: 'Validation Error',
                message: 'Please fill in all required fields (marked with *)'
            });
            return;
        }
        
        const shippingAddress = {
            line1,
            city,
            postalCode,
            country
        };
        
        if (line2) shippingAddress.line2 = line2;
        if (state) shippingAddress.state = state;
        
        try {
            const response = await fetch('/api/customer/update-shipping-address', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    shippingAddress
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                modalManager.hideModal(modal);
                modal.remove();
                window.location.reload();
            } else {
                const alertManager = new ModalManager();
                alertManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to update address: ' + (result.message || 'Unknown error')
                });
            }
        } catch (error) {
            console.error('Error updating address:', error);
            const alertManager = new ModalManager();
            alertManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Failed to update address: ' + error.message
            });
        }
    });
    
    cancelBtn.addEventListener('click', () => {
        modalManager.hideModal(modal);
        modal.remove();
    });
    
    modalManager.showModal(modal);
}

async function loadBasket(basketName) {
    try {
        const response = await MiaStore.request('/api/cart/load-basket', {
            method: 'POST',
            body: JSON.stringify({ basketName })
        });
        
        if (response.success) {
            const { addedItems, totalItems, skippedItems } = response;
            
            // Show appropriate message based on results
            if (skippedItems && skippedItems.length > 0) {
                // Partial success - some items couldn't be loaded
                const skippedCount = skippedItems.length;
                const skippedDetails = skippedItems.map(item => `${item.sku}: ${item.reason}`).join(', ');
                MiaStore.showToast(
                    `Basket loaded: ${addedItems} of ${totalItems} items added. ${skippedCount} skipped (${skippedDetails})`,
                    'warning'
                );
            } else if (addedItems === 0) {
                // No items could be loaded
                MiaStore.showToast('No items could be loaded from this basket', 'error');
            } else {
                // Full success
                MiaStore.showToast(`Basket loaded successfully! ${addedItems} item${addedItems > 1 ? 's' : ''} added to cart`);
            }
            
            // Refresh the cart to show loaded items
            MiaStore.updateCartCount(response.cartCount);
            MiaStore.loadCartContent();
        } else {
            MiaStore.showToast(response.message || 'Failed to load basket', 'error');
        }
    } catch (error) {
        console.error('Load basket error:', error);
        MiaStore.showToast('Failed to load basket', 'error');
    }
}

async function viewBasket(basketName, items) {
    // Show loading modal first
    showModal('<div class="p-6 text-center"><i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i><p>Loading basket details...</p></div>');
    
    // Fetch product details for each item to get images
    const enrichedItems = await Promise.all(items.map(async (item) => {
        try {
            const response = await fetch(`/api/products/${item.productId}`);
            if (response.ok) {
                const product = await response.json();
                // Generate SEO-friendly URL
                const slug = product.slug || (product.title || item.sku).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                const productUrl = `/product/${item.productId}/${slug}`;
                return {
                    ...item,
                    image: product.images && product.images.length > 0 ? product.images[0] : null,
                    productUrl: productUrl
                };
            }
        } catch (error) {
            console.error(`Failed to fetch product ${item.productId}:`, error);
        }
        // Fallback URL if API call fails
        const fallbackSlug = (item.name || item.sku).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        return {
            ...item,
            productUrl: `/product/${item.productId}/${fallbackSlug}`
        };
    }));
    
    // Create modal content showing basket items with stock status
    const itemsList = enrichedItems.map(item => {
        // Check stock status
        const inStock = item.inStock !== false && item.inStock !== 0;
        const stockInfo = item.stock || {};
        
        let stockBadge = '';
        if (stockInfo.unlimited || stockInfo.inventoryType === 'digital') {
            stockBadge = '<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">In Stock</span>';
        } else if (inStock && stockInfo.available > 10) {
            stockBadge = '<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">In Stock</span>';
        } else if (inStock && stockInfo.available > 0) {
            stockBadge = `<span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Low Stock (${stockInfo.available})</span>`;
        } else if (inStock) {
            // Has stock info but no available count - assume in stock
            stockBadge = '<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">In Stock</span>';
        } else {
            stockBadge = '<span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Out of Stock</span>';
        }
        
        // Calculate VAT (assuming 20% VAT rate)
        const vatRate = 0.20;
        const priceExVat = item.price;
        const vat = Math.round(priceExVat * vatRate);
        const priceIncVat = priceExVat + vat;
        
        const imageHtml = item.image 
            ? `<a href="${item.productUrl}"><img src="${item.image}" alt="${item.name || item.sku}" class="w-16 h-16 object-cover rounded hover:opacity-80 transition-opacity"></a>`
            : `<a href="${item.productUrl}"><div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center hover:bg-gray-300 transition-colors"><i class="fas fa-image text-gray-400"></i></div></a>`;
        
        return `
            <div class="flex items-center space-x-4 py-3 border-b border-gray-200">
                ${imageHtml}
                <div class="flex-1 min-w-0">
                    <a href="${item.productUrl}" class="font-medium text-gray-900 hover:text-blue-600 transition-colors">${item.name || item.sku}</a>
                    ${item.variantName ? `<div class="text-sm text-gray-600">${item.variantName}</div>` : ''}
                    <div class="text-sm text-gray-600">SKU: ${item.sku} • Qty: ${item.qty}</div>
                    <div class="mt-1">${stockBadge}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-red-600">£${(priceIncVat / 100).toFixed(2)}</div>
                    <div class="text-xs text-gray-500">£${(priceExVat / 100).toFixed(2)} ex VAT</div>
                    <div class="text-xs text-gray-400">each</div>
                </div>
            </div>
        `;
    }).join('');
    
    const hasOutOfStock = enrichedItems.some(item => item.inStock === false || item.inStock === 0);
    const warningMessage = hasOutOfStock 
        ? '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-4"><i class="fas fa-exclamation-triangle mr-2"></i>Some items are out of stock and will not be added to your cart.</div>'
        : '';
    
    const modalContent = `
        <div class="p-6">
            <h3 class="text-xl font-semibold mb-4">Saved Basket: ${basketName}</h3>
            ${warningMessage}
            <div class="max-h-96 overflow-y-auto">
                ${itemsList}
            </div>
            <div class="mt-6 flex space-x-3">
                <button onclick="loadBasket('${basketName}'); closeModal();" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-shopping-cart mr-2"></i>Load Basket
                </button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
            </div>
        </div>
    `;
    
    // Update modal with enriched content
    showModal(modalContent);
}

function showModal(content) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'basket-modal';
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    overlay.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            ${content}
        </div>
    `;
    document.body.appendChild(overlay);
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeModal();
        }
    });
}

function closeModal() {
    const modal = document.getElementById('basket-modal');
    if (modal) {
        modal.remove();
    }
}

async function deleteBasket(basketName) {
    if (!confirm('Are you sure you want to delete this saved basket?')) {
        return;
    }
    
    try {
        const response = await MiaStore.request('/api/cart/delete-basket', {
            method: 'POST',
            body: JSON.stringify({ basketName })
        });
        
        if (response.success) {
            MiaStore.showToast('Basket deleted successfully!');
            location.reload(); // Refresh to update the list
        } else {
            MiaStore.showToast(response.message || 'Failed to delete basket', 'error');
        }
    } catch (error) {
        console.error('Delete basket error:', error);
        MiaStore.showToast('Failed to delete basket', 'error');
    }
}
