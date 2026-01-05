/**
 * Mia Store - Product Page JavaScript
 */

// Product page specific functionality
window.ProductPage = {
    selectedSku: '',
    
    // Initialize product page
    init() {
        // Initialize selected SKU based on variant setup
        const variantSelect = document.getElementById('variant-select');
        const singleVariantSku = document.getElementById('single-variant-sku');
        
        if (variantSelect) {
            // Multiple variants - select first option by default
            const firstOption = variantSelect.options[0];
            if (firstOption) {
                this.selectedSku = firstOption.value;
                this.updatePriceDisplay(firstOption.dataset.price);
            }
        } else if (singleVariantSku) {
            // Single variant - use hidden input value
            this.selectedSku = singleVariantSku.value;
        }
    },
    
    // Change main product image
    changeMainImage(imageUrl) {
        const mainImage = document.getElementById('main-image');
        if (mainImage) {
            mainImage.src = imageUrl;
        }
    },
    
    // Update variant information when selection changes
    updateVariantInfo(select) {
        const option = select.options[select.selectedIndex];
        const price = option.dataset.price;
        const stock = option.dataset.stock;
        this.selectedSku = option.value;
        
        // Update price display
        this.updatePriceDisplay(price);
        
        // Update add to cart button state
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) {
            addToCartBtn.disabled = !this.selectedSku;
        }
    },
    
    // Update price display
    updatePriceDisplay(priceData) {
        const priceElement = document.getElementById('current-price');
        if (priceElement && priceData) {
            let displayPrice = 0;
            let currency = 'GBP';
            
            // Handle both old and new price formats
            if (typeof priceData === 'string') {
                // Try to parse as JSON (new format)
                try {
                    const parsed = JSON.parse(priceData);
                    if (Array.isArray(parsed) && parsed[0]) {
                        displayPrice = parsed[0].unit / 100;
                        currency = parsed[0].currency;
                    } else if (parsed.unit) {
                        displayPrice = parsed.unit / 100;
                        currency = parsed.currency || 'GBP';
                    }
                } catch (e) {
                    // If parsing fails, treat as old format (number as string)
                    displayPrice = parseFloat(priceData) / 100;
                }
            } else if (typeof priceData === 'number') {
                // Old format (integer in pence)
                displayPrice = priceData / 100;
            } else if (Array.isArray(priceData) && priceData[0]) {
                // New format (array of currency objects)
                displayPrice = priceData[0].unit / 100;
                currency = priceData[0].currency;
            } else if (priceData.unit) {
                // New format (single currency object)
                displayPrice = priceData.unit / 100;
                currency = priceData.currency || 'GBP';
            }
            
            // Format with currency symbol
            const symbols = { 'GBP': '£', 'USD': '$', 'EUR': '€' };
            const symbol = symbols[currency] || currency;
            const formattedPrice = symbol + displayPrice.toFixed(2);
            
            priceElement.textContent = formattedPrice;
        }
    },
    
    // Change quantity with +/- buttons
    changeQuantity(delta) {
        const quantityInput = document.getElementById('quantity');
        if (!quantityInput) return;
        
        const currentValue = parseInt(quantityInput.value);
        const newValue = Math.max(1, Math.min(10, currentValue + delta));
        quantityInput.value = newValue;
    },
    
    // Add to cart from product page
    addToCartFromProduct() {
        // Get SKU from either dropdown or single variant input
        let sku = this.selectedSku;
        
        if (!sku) {
            const variantSelect = document.getElementById('variant-select');
            const singleVariantSku = document.getElementById('single-variant-sku');
            
            if (variantSelect && variantSelect.value) {
                sku = variantSelect.value;
            } else if (singleVariantSku) {
                sku = singleVariantSku.value;
            }
        }
        
        if (!sku) {
            if (window.MiaStore) {
                MiaStore.showToast('Please select a variant first', 'error');
            } else {
                alert('Please select a variant first');
            }
            return;
        }
        
        const quantityInput = document.getElementById('quantity');
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        
        if (addToCartBtn && window.addToCart) {
            addToCart(sku, quantity, addToCartBtn);
        }
    }
};

// Global functions for onclick handlers (product page)
window.changeMainImage = function(imageUrl) {
    ProductPage.changeMainImage(imageUrl);
};

window.updateVariantInfo = function(select) {
    ProductPage.updateVariantInfo(select);
};

window.changeQuantity = function(delta) {
    ProductPage.changeQuantity(delta);
};

window.addToCartFromProduct = function() {
    ProductPage.addToCartFromProduct();
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    ProductPage.init();
});