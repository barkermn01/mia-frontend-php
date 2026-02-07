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
                this.updateStockInfo(firstOption.dataset.stock);
            }
        } else if (singleVariantSku) {
            // Single variant - use hidden input value
            this.selectedSku = singleVariantSku.value;
            
            // For single variants, get stock info from the page data
            const stockDisplayElement = document.querySelector('#stock-display span');
            if (stockDisplayElement) {
                // Check if it shows "Out of Stock" to determine initial button state
                const isOutOfStock = stockDisplayElement.textContent.includes('Out of Stock');
                this.updateAddToCartButton(!isOutOfStock);
            }
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
        
        // Update stock display and add to cart button state
        this.updateStockInfo(stock);
    },
    
    // Update stock information and button state
    updateStockInfo(stockData) {
        let stockInfo = {};
        
        // Parse stock data
        if (typeof stockData === 'string') {
            try {
                stockInfo = JSON.parse(stockData);
            } catch (e) {
                console.warn('Failed to parse stock data:', stockData);
                stockInfo = {};
            }
        } else if (typeof stockData === 'object') {
            stockInfo = stockData || {};
        }
        
        const available = stockInfo.available || 0;
        const unlimited = stockInfo.unlimited || false;
        const inventoryType = stockInfo.inventoryType || 'physical';
        
        // Determine if in stock
        const isInStock = unlimited || inventoryType === 'digital' || available > 0;
        
        // Update stock display
        this.updateStockDisplay(stockInfo, isInStock);
        
        // Update add to cart button
        this.updateAddToCartButton(isInStock);
    },
    
    // Update stock display text
    updateStockDisplay(stockInfo, isInStock) {
        const stockElement = document.getElementById('stock-display');
        if (!stockElement) return;
        
        const available = stockInfo.available || 0;
        const unlimited = stockInfo.unlimited || false;
        const inventoryType = stockInfo.inventoryType || 'physical';
        
        let stockText = '';
        let stockClass = '';
        let icon = '';
        
        if (unlimited || inventoryType === 'digital') {
            stockText = 'In Stock';
            stockClass = 'text-green-600';
            icon = 'check-circle';
        } else if (available > 0) {
            if (available <= 5) {
                stockText = `Only ${available} left`;
            } else {
                stockText = `${available} available`;
            }
            stockClass = 'text-green-600';
            icon = 'check-circle';
        } else {
            stockText = 'Out of Stock';
            stockClass = 'text-red-600';
            icon = 'times-circle';
        }
        
        stockElement.innerHTML = `<span class="${stockClass}">
            <i class="fas fa-${icon} mr-1"></i>
            ${stockText}
        </span>`;
    },
    
    // Update add to cart button state
    updateAddToCartButton(isInStock) {
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (!addToCartBtn) {
            // Button doesn't exist (probably out of stock single variant)
            return;
        }
        
        if (isInStock) {
            addToCartBtn.disabled = false;
            addToCartBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            addToCartBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            addToCartBtn.innerHTML = '<i class="fas fa-cart-plus mr-2"></i>Add to Cart';
        } else {
            addToCartBtn.disabled = true;
            addToCartBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            addToCartBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            addToCartBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Out of Stock';
        }
    },
    
    // Update price display
    updatePriceDisplay(priceData) {
        const priceElement = document.getElementById('current-price');
        const exVatElement = priceElement ? priceElement.parentElement.nextElementSibling : null;
        
        if (priceElement && priceData) {
            let displayPrice = 0;
            let currency = 'GBP';
            let rrp = 0;
            
            // Handle both old and new price formats
            if (typeof priceData === 'string') {
                // Try to parse as JSON (new format)
                try {
                    const parsed = JSON.parse(priceData);
                    if (Array.isArray(parsed) && parsed[0]) {
                        displayPrice = parsed[0].unit / 100;
                        currency = parsed[0].currency;
                        rrp = parsed[0].rrp ? parsed[0].rrp / 100 : 0;
                    } else if (parsed.unit) {
                        displayPrice = parsed.unit / 100;
                        currency = parsed.currency || 'GBP';
                        rrp = parsed.rrp ? parsed.rrp / 100 : 0;
                    } else if (typeof parsed === 'number') {
                        // Simple number as string
                        displayPrice = parsed / 100;
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
                rrp = priceData[0].rrp ? priceData[0].rrp / 100 : 0;
            } else if (priceData && priceData.unit) {
                // New format (single currency object)
                displayPrice = priceData.unit / 100;
                currency = priceData.currency || 'GBP';
                rrp = priceData.rrp ? priceData.rrp / 100 : 0;
            }
            
            // Get VAT rate from window config or default to 20%
            const vatRate = window.MiaStoreConfig?.vatRate || 0.20;
            const priceIncVat = displayPrice * (1 + vatRate);
            
            // Format with currency symbol
            const symbols = { 'GBP': '£', 'USD': '$', 'EUR': '€' };
            const symbol = symbols[currency] || currency;
            const formattedPriceIncVat = symbol + priceIncVat.toFixed(2);
            const formattedPriceExVat = symbol + displayPrice.toFixed(2);
            
            priceElement.textContent = formattedPriceIncVat;
            
            // Update ex-VAT display if element exists
            if (exVatElement && exVatElement.classList.contains('text-sm')) {
                exVatElement.innerHTML = `(ex-VAT ${formattedPriceExVat})`;
                
                // Update or add RRP savings display
                let rrpElement = document.getElementById('rrp-savings');
                let savingsBadge = document.getElementById('savings-badge');
                
                if (rrp > 0 && displayPrice > 0 && rrp > displayPrice) {
                    const savingsPercent = ((rrp - displayPrice) / rrp) * 100;
                    const rrpIncVat = rrp * (1 + vatRate);
                    const formattedRrpIncVat = symbol + rrpIncVat.toFixed(2);
                    const formattedRrpExVat = symbol + rrp.toFixed(2);
                    
                    if (rrpElement) {
                        rrpElement.innerHTML = `RRP ${formattedRrpIncVat} (ex-VAT ${formattedRrpExVat})`;
                    } else {
                        // Create the RRP element if it doesn't exist
                        rrpElement = document.createElement('div');
                        rrpElement.id = 'rrp-savings';
                        rrpElement.className = 'text-sm text-gray-600 mt-1';
                        rrpElement.innerHTML = `RRP ${formattedRrpIncVat} (ex-VAT ${formattedRrpExVat})`;
                        exVatElement.parentElement.appendChild(rrpElement);
                    }
                    
                    // Update or create savings badge only if savings >= 0.5%
                    if (savingsPercent >= 0.5) {
                        const roundedPercent = Math.round(savingsPercent);
                        if (savingsBadge) {
                            savingsBadge.querySelector('.text-lg').textContent = roundedPercent + '%';
                        } else {
                            // Create the badge if it doesn't exist - insert at beginning of flex container
                            savingsBadge = document.createElement('div');
                            savingsBadge.id = 'savings-badge';
                            savingsBadge.className = 'bg-red-600 text-white rounded-full w-20 h-20 flex flex-col items-center justify-center shadow-lg transform rotate-12 flex-shrink-0';
                            savingsBadge.innerHTML = `
                                <i class="fas fa-star text-yellow-300 text-xs mb-1"></i>
                                <span class="text-xs font-bold">SAVE</span>
                                <span class="text-lg font-bold">${roundedPercent}%</span>
                            `;
                            // Add to the flex container before the price div
                            const priceContainer = priceElement.closest('.flex.items-center');
                            if (priceContainer) {
                                priceContainer.insertBefore(savingsBadge, priceContainer.firstChild);
                            }
                        }
                    } else if (savingsBadge) {
                        // Remove badge if savings < 0.5%
                        savingsBadge.remove();
                    }
                } else {
                    // Remove RRP element and badge if no savings
                    if (rrpElement) {
                        rrpElement.remove();
                    }
                    if (savingsBadge) {
                        savingsBadge.remove();
                    }
                }
            }
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
            if (window.MiaStore && window.MiaStore.showToast) {
                MiaStore.showToast('Please select a variant first', 'error');
            } else if (window.modalManager) {
                window.modalManager.alert({
                    title: 'Selection Required',
                    message: 'Please select a variant first',
                    type: 'warning'
                });
            } else {
                alert('Please select a variant first');
            }
            return;
        }
        
        const quantityInput = document.getElementById('quantity');
        const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        
        // Check if button exists and is not disabled
        if (addToCartBtn && !addToCartBtn.disabled && window.addToCart) {
            addToCart(sku, quantity, addToCartBtn);
        } else if (!addToCartBtn) {
            // Button doesn't exist, probably out of stock
            if (window.MiaStore && window.MiaStore.showToast) {
                MiaStore.showToast('This product is currently out of stock', 'error');
            } else if (window.modalManager) {
                window.modalManager.alert({
                    title: 'Out of Stock',
                    message: 'This product is currently out of stock',
                    type: 'warning'
                });
            } else {
                alert('This product is currently out of stock');
            }
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