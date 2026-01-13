/**
 * Admin Products JavaScript
 * Handles product management functionality including custom delete modal
 */

class AdminProducts {
    constructor(config) {
        this.config = config;
        this.currentProductId = null;
        this.modalManager = new ModalManager();
        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
    }

    createModal() {
        // Create modal HTML
        const modalHTML = `
            <div id="delete-modal" class="fixed inset-0 hidden">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
                
                <!-- Modal -->
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                    Confirm Delete
                                </h3>
                                <button class="modal-close text-gray-400 hover:text-gray-600 transition-colors">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Body -->
                        <div class="px-6 py-4">
                            <p class="text-gray-700 mb-4">
                                Are you sure you want to delete this product? This action cannot be undone and will permanently remove:
                            </p>
                            <ul class="text-sm text-gray-600 mb-4 pl-4">
                                <li class="flex items-center mb-1">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    The product and all its variants
                                </li>
                                <li class="flex items-center mb-1">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    All associated images and data
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    Any order history references
                                </li>
                            </ul>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-red-800 text-sm font-medium">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    This action is permanent and cannot be reversed.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button class="modal-cancel px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button class="modal-confirm px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors">
                                <i class="fas fa-trash mr-2"></i>Delete Product
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    bindEvents() {
        // Bind delete buttons
        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('[data-product-id]');
            if (deleteBtn && deleteBtn.classList.contains('delete-product-btn')) {
                e.preventDefault();
                const productId = deleteBtn.dataset.productId;
                this.deleteProduct(productId);
            }

            // Bind stock management buttons
            const stockBtn = e.target.closest('[data-product-id]');
            if (stockBtn && stockBtn.classList.contains('manage-stock-btn')) {
                e.preventDefault();
                const productId = stockBtn.dataset.productId;
                this.manageStock(productId);
            }
        });

        // Bind modal events
        const modal = document.getElementById('delete-modal');
        if (modal) {
            // Close modal events
            modal.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal-close') || 
                    e.target.closest('.modal-close') ||
                    e.target === modal.querySelector('.fixed.inset-0.bg-black')) {
                    this.closeModal();
                }
            });

            // Cancel button
            modal.querySelector('.modal-cancel').addEventListener('click', () => {
                this.closeModal();
            });

            // Confirm button
            modal.querySelector('.modal-confirm').addEventListener('click', () => {
                this.confirmDelete();
            });
        }

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isModalOpen()) {
                this.closeModal();
            }
        });
    }

    deleteProduct(productId) {
        this.currentProductId = productId;
        this.openModal();
    }

    openModal() {
        const modal = document.getElementById('delete-modal');
        if (modal) {
            this.modalManager.showModal(modal);
            // Focus the cancel button for accessibility
            setTimeout(() => {
                const cancelBtn = modal.querySelector('.modal-cancel');
                if (cancelBtn) cancelBtn.focus();
            }, 100);
        }
    }

    closeModal() {
        const modal = document.getElementById('delete-modal');
        if (modal) {
            this.modalManager.hideModal(modal);
            this.currentProductId = null;
        }
    }

    isModalOpen() {
        const modal = document.getElementById('delete-modal');
        return modal && this.modalManager.isModalVisible(modal);
    }

    async confirmDelete() {
        if (!this.currentProductId) return;

        const confirmBtn = document.querySelector('.modal-confirm');
        const originalContent = confirmBtn.innerHTML;
        
        // Show loading state
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
        confirmBtn.disabled = true;

        try {
            const response = await fetch(`${this.config.adminPath}/products/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ productId: this.currentProductId })
            });

            const data = await response.json();

            if (data.success) {
                // Show success state briefly
                confirmBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Deleted!';
                confirmBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
                confirmBtn.classList.add('bg-green-600');
                
                // Close modal and show loading overlay
                setTimeout(() => {
                    this.closeModal();
                    this.showLoadingOverlay();
                    // Reload page after showing loading
                    setTimeout(() => location.reload(), 500);
                }, 800);
            } else {
                throw new Error(data.message || 'Failed to delete product');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showErrorMessage('Failed to delete product: ' + error.message);
            
            // Restore button state
            confirmBtn.innerHTML = originalContent;
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('bg-green-600');
            confirmBtn.classList.add('bg-red-600', 'hover:bg-red-700');
        }
    }

    showLoadingOverlay() {
        // Create loading overlay
        const loadingHTML = `
            <div id="loading-overlay" class="fixed inset-0 z-50 bg-white bg-opacity-95 flex items-center justify-center">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                    <div class="text-lg font-medium text-gray-900 mb-2">Product Deleted Successfully</div>
                    <div class="text-sm text-gray-600">Refreshing product list...</div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
    }

    // ==================== STOCK MANAGEMENT ====================

    async manageStock(productId) {
        this.currentProductId = productId;
        
        try {
            // Show loading state
            this.showStockLoadingModal();
            
            // Fetch variants and stock data
            const response = await fetch(`${this.config.adminPath}/api/stock/variants?productId=${encodeURIComponent(productId)}`);
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to load stock information');
            }
            
            this.showStockModal(data.variants);
        } catch (error) {
            console.error('Stock management error:', error);
            this.showErrorMessage('Failed to load stock information: ' + error.message);
            this.closeStockModal();
        }
    }

    showStockLoadingModal() {
        // Remove existing stock modal if any
        const existingModal = document.getElementById('stock-modal');
        if (existingModal) {
            this.modalManager.hideModal(existingModal);
            existingModal.remove();
        }

        const loadingHTML = `
            <div id="stock-modal" class="fixed inset-0">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
                
                <!-- Modal -->
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full transform transition-all">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-warehouse text-purple-500 mr-2"></i>
                                Manage Stock
                            </h3>
                        </div>
                        <div class="px-6 py-8 text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mb-4"></div>
                            <div class="text-gray-600">Loading stock information...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
        const modal = document.getElementById('stock-modal');
        this.modalManager.showModal(modal);
    }

    showStockModal(variants) {
        const variantsHTML = variants.map(variant => {
            const stock = variant.stockInfo || variant.stock || {};
            const available = stock.available || 0;
            const reserved = stock.reserved || 0;
            const allocated = stock.allocated || 0;
            const unlimited = stock.unlimited || false;
            const inventoryType = stock.inventoryType || 'physical';
            
            let stockStatus = '';
            let stockClass = '';
            
            if (unlimited || inventoryType === 'digital') {
                stockStatus = 'Unlimited';
                stockClass = 'text-green-600 bg-green-50';
            } else if (available > 0) {
                stockStatus = `${available} Available`;
                stockClass = available > 10 ? 'text-green-600 bg-green-50' : 'text-yellow-600 bg-yellow-50';
            } else {
                stockStatus = 'Out of Stock';
                stockClass = 'text-red-600 bg-red-50';
            }

            return `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="font-medium text-gray-900">${this.escapeHtml(variant.presentableName || variant.sku)}</div>
                            <div class="text-sm text-gray-500">SKU: ${this.escapeHtml(variant.sku)}</div>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded-full ${stockClass}">
                            ${stockStatus}
                        </span>
                    </div>
                    
                    ${!unlimited && inventoryType !== 'digital' ? `
                        <div class="grid grid-cols-3 gap-4 text-sm mb-4">
                            <div class="text-center">
                                <div class="text-gray-500">Available</div>
                                <div class="font-semibold text-lg">${available}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-gray-500">Reserved</div>
                                <div class="font-semibold text-lg">${reserved}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-gray-500">Allocated</div>
                                <div class="font-semibold text-lg">${allocated}</div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button class="stock-add-btn flex-1 bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition-colors" 
                                    data-sku="${this.escapeHtml(variant.sku)}">
                                <i class="fas fa-plus mr-1"></i>Add Stock
                            </button>
                            <button class="stock-remove-btn flex-1 bg-red-600 text-white px-3 py-2 rounded text-sm hover:bg-red-700 transition-colors" 
                                    data-sku="${this.escapeHtml(variant.sku)}" ${available <= 0 ? 'disabled' : ''}>
                                <i class="fas fa-minus mr-1"></i>Remove Stock
                            </button>
                        </div>
                    ` : `
                        <div class="text-center text-gray-500 text-sm py-4">
                            ${inventoryType === 'digital' ? 'Digital products have unlimited stock' : 'This variant has unlimited stock'}
                        </div>
                    `}
                </div>
            `;
        }).join('');

        const modalHTML = `
            <div id="stock-modal" class="fixed inset-0">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
                
                <!-- Modal -->
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden transform transition-all">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-warehouse text-purple-500 mr-2"></i>
                                Manage Stock
                            </h3>
                            <button class="stock-modal-close text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <!-- Body -->
                        <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                            <div class="space-y-4">
                                ${variantsHTML}
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                            <button class="stock-modal-close px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                <i class="fas fa-times mr-2"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove loading modal and add stock modal
        const existingModal = document.getElementById('stock-modal');
        if (existingModal) {
            this.modalManager.hideModal(existingModal);
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modal = document.getElementById('stock-modal');
        this.modalManager.showModal(modal);
        this.bindStockModalEvents();
    }

    bindStockModalEvents() {
        const modal = document.getElementById('stock-modal');
        if (!modal) return;

        // Close modal events
        modal.addEventListener('click', (e) => {
            if (e.target.classList.contains('stock-modal-close') || 
                e.target.closest('.stock-modal-close') ||
                e.target === modal.querySelector('.fixed.inset-0.bg-black')) {
                this.closeStockModal();
            }
        });

        // Stock adjustment buttons
        modal.addEventListener('click', (e) => {
            if (e.target.classList.contains('stock-add-btn') || e.target.closest('.stock-add-btn')) {
                const btn = e.target.closest('.stock-add-btn');
                const sku = btn.dataset.sku;
                this.showStockAdjustmentModal(sku, 'add');
            }
            
            if (e.target.classList.contains('stock-remove-btn') || e.target.closest('.stock-remove-btn')) {
                const btn = e.target.closest('.stock-remove-btn');
                if (!btn.disabled) {
                    const sku = btn.dataset.sku;
                    this.showStockAdjustmentModal(sku, 'remove');
                }
            }
        });
    }

    showStockAdjustmentModal(sku, type) {
        const isAdd = type === 'add';
        const title = isAdd ? 'Add Stock' : 'Remove Stock';
        const buttonText = isAdd ? 'Add Stock' : 'Remove Stock';
        const buttonClass = isAdd ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700';
        const icon = isAdd ? 'fa-plus' : 'fa-minus';

        const adjustmentHTML = `
            <div id="stock-adjustment-modal" class="fixed inset-0">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
                
                <!-- Modal -->
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas ${icon} ${isAdd ? 'text-green-500' : 'text-red-500'} mr-2"></i>
                                ${title}
                            </h3>
                        </div>
                        
                        <!-- Body -->
                        <div class="px-6 py-4">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                                <input type="text" value="${this.escapeHtml(sku)}" disabled 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                <input type="number" id="stock-quantity" min="1" value="1" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
                                <input type="text" id="stock-reason" placeholder="e.g., Restock, Inventory count, Damaged goods, etc." 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                            <button class="adjustment-cancel px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button class="adjustment-confirm px-4 py-2 ${buttonClass} text-white rounded-lg transition-colors" 
                                    data-sku="${this.escapeHtml(sku)}" data-type="${type}">
                                <i class="fas ${icon} mr-2"></i>${buttonText}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', adjustmentHTML);
        const modal = document.getElementById('stock-adjustment-modal');
        this.modalManager.showModal(modal);
        this.bindStockAdjustmentEvents();
        
        // Focus quantity input
        setTimeout(() => {
            const quantityInput = document.getElementById('stock-quantity');
            if (quantityInput) {
                quantityInput.focus();
                quantityInput.select();
            }
        }, 100);
    }

    bindStockAdjustmentEvents() {
        const modal = document.getElementById('stock-adjustment-modal');
        if (!modal) return;

        // Close events
        modal.addEventListener('click', (e) => {
            if (e.target.classList.contains('adjustment-cancel') || 
                e.target.closest('.adjustment-cancel') ||
                e.target === modal.querySelector('.fixed.inset-0.bg-black')) {
                this.closeStockAdjustmentModal();
            }
        });

        // Confirm button
        modal.querySelector('.adjustment-confirm').addEventListener('click', (e) => {
            const btn = e.target.closest('.adjustment-confirm');
            const sku = btn.dataset.sku;
            const type = btn.dataset.type;
            const quantity = parseInt(document.getElementById('stock-quantity').value) || 0;
            const reason = document.getElementById('stock-reason').value.trim();
            
            if (quantity <= 0) {
                this.showErrorMessage('Quantity must be greater than 0');
                return;
            }
            
            this.adjustStock(sku, type === 'add' ? quantity : -quantity, reason);
        });

        // Enter key support
        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                modal.querySelector('.adjustment-confirm').click();
            }
        });
    }

    async adjustStock(sku, adjustment, reason) {
        const confirmBtn = document.querySelector('.adjustment-confirm');
        const originalContent = confirmBtn.innerHTML;
        
        // Show loading state
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adjusting...';
        confirmBtn.disabled = true;

        try {
            const response = await fetch(`${this.config.adminPath}/api/stock/adjust`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    sku: sku,
                    adjustment: adjustment,
                    reason: reason || 'Manual adjustment via admin panel'
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to adjust stock');
            }

            // Show success
            confirmBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Success!';
            confirmBtn.classList.remove('bg-green-600', 'hover:bg-green-700', 'bg-red-600', 'hover:bg-red-700');
            confirmBtn.classList.add('bg-green-600');

            // Close adjustment modal and refresh stock modal
            setTimeout(() => {
                this.closeStockAdjustmentModal();
                this.showSuccessMessage(`Stock ${adjustment > 0 ? 'added' : 'removed'} successfully`);
                // Refresh the stock modal
                this.manageStock(this.currentProductId);
            }, 800);

        } catch (error) {
            console.error('Stock adjustment error:', error);
            this.showErrorMessage('Failed to adjust stock: ' + error.message);
            
            // Restore button state
            confirmBtn.innerHTML = originalContent;
            confirmBtn.disabled = false;
        }
    }

    closeStockModal() {
        const modal = document.getElementById('stock-modal');
        if (modal) {
            this.modalManager.hideModal(modal);
            modal.remove();
        }
    }

    closeStockAdjustmentModal() {
        const modal = document.getElementById('stock-adjustment-modal');
        if (modal) {
            this.modalManager.hideModal(modal);
            modal.remove();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showSuccessMessage(message) {
        this.showToast(message, 'success');
    }

    showErrorMessage(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        // Create toast container if it doesn't exist
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }

        // Create toast
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
        const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
        
        toast.className = `${bgColor} text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 max-w-sm`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-3"></i>
                <span class="flex-1">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        container.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);

        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }, 300);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get config from global variable set by PHP
    const config = window.AdminProductsConfig || { adminPath: '/admin' };
    new AdminProducts(config);
});