// Orders Management JavaScript

class OrdersManager {
    constructor() {
        this.modalManager = new ModalManager();
        this.init();
    }
    
    init() {
        // Process order button handler
        document.querySelectorAll('.process-order-btn').forEach(button => {
            button.addEventListener('click', () => {
                const orderId = button.dataset.orderId;
                
                this.modalManager.confirm({
                    title: 'Process Order',
                    message: 'Mark this order as processing?',
                    confirmText: 'Process',
                    confirmClass: 'primary',
                    onConfirm: () => this.processOrder(orderId)
                });
            });
        });
        
        // Cancel order button handler
        document.querySelectorAll('.cancel-order-btn').forEach(button => {
            button.addEventListener('click', () => {
                const orderId = button.dataset.orderId;
                
                this.modalManager.confirm({
                    title: 'Cancel Order',
                    message: 'Are you sure you want to cancel this order? This action cannot be undone.',
                    confirmText: 'Cancel Order',
                    cancelText: 'Keep Order',
                    confirmClass: 'danger',
                    onConfirm: () => this.cancelOrder(orderId)
                });
            });
        });
        
        // Ship order button handler
        document.querySelectorAll('.ship-order-btn').forEach(button => {
            button.addEventListener('click', () => {
                const orderId = button.dataset.orderId;
                this.showShipOrderDialog(orderId);
            });
        });
        
        // Complete order button handler
        document.querySelectorAll('.complete-order-btn').forEach(button => {
            button.addEventListener('click', () => {
                const orderId = button.dataset.orderId;
                
                this.modalManager.confirm({
                    title: 'Complete Order',
                    message: 'Mark this order as completed?',
                    confirmText: 'Complete',
                    confirmClass: 'primary',
                    onConfirm: () => this.completeOrder(orderId)
                });
            });
        });
    }
    
    processOrder(orderId) {
        console.log('Processing order:', orderId);
        
        fetch('/admin/api/orders/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ orderId: orderId })
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Get the response text first to see what we're actually getting
            return response.text().then(text => {
                console.log('Response text:', text);
                
                if (!response.ok) {
                    // Try to parse as JSON, but if it fails, use the text
                    try {
                        const data = JSON.parse(text);
                        throw new Error(data.message || 'Failed to update order status');
                    } catch (e) {
                        throw new Error('Server error: ' + text.substring(0, 200));
                    }
                }
                
                // Parse successful response
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                window.location.reload();
            } else {
                this.modalManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to update order status: ' + (data.message || 'Unknown error')
                });
            }
        })
        .catch(error => {
            console.error('Error updating order status:', error);
            this.modalManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Error updating order status: ' + error.message
            });
        });
    }
    
    cancelOrder(orderId) {
        fetch('/admin/api/orders/cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ orderId: orderId })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to cancel order');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                this.modalManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to cancel order: ' + (data.message || 'Unknown error')
                });
            }
        })
        .catch(error => {
            console.error('Error cancelling order:', error);
            this.modalManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Error cancelling order: ' + error.message
            });
        });
    }
    
    showShipOrderDialog(orderId) {
        const formHtml = `
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                    <input type="text" id="ship-tracking-number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., TRK123456789">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Carrier</label>
                    <input type="text" id="ship-carrier" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Royal Mail, DPD, UPS">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tracking URL</label>
                    <input type="url" id="ship-tracking-url" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="https://track.carrier.com/...">
                </div>
            </div>
        `;
        
        // Create modal manually to have access to inputs before removal
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ship Order</h3>
                <div class="mb-6">${formHtml}</div>
                <div class="flex justify-end space-x-3">
                    <button class="cancel-btn px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button class="confirm-btn px-4 py-2 rounded-lg text-white transition-colors bg-blue-600 hover:bg-blue-700">
                        Mark as Shipped
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const confirmBtn = modal.querySelector('.confirm-btn');
        const cancelBtn = modal.querySelector('.cancel-btn');

        confirmBtn.addEventListener('click', () => {
            // Get values BEFORE removing modal
            const trackingNumber = modal.querySelector('#ship-tracking-number').value.trim();
            const carrier = modal.querySelector('#ship-carrier').value.trim();
            const trackingUrl = modal.querySelector('#ship-tracking-url').value.trim();
            
            this.modalManager.hideModal(modal);
            modal.remove();
            
            this.shipOrder(orderId, trackingNumber, carrier, trackingUrl);
        });

        cancelBtn.addEventListener('click', () => {
            this.modalManager.hideModal(modal);
            modal.remove();
        });

        this.modalManager.showModal(modal);
    }
    
    shipOrder(orderId, trackingNumber, carrier, trackingUrl) {
        const payload = { orderId: orderId };
        
        if (trackingNumber) payload.trackingNumber = trackingNumber;
        if (carrier) payload.carrier = carrier;
        if (trackingUrl) payload.trackingUrl = trackingUrl;
        
        fetch('/admin/api/orders/ship', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to mark order as shipped');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                this.modalManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to mark order as shipped: ' + (data.message || 'Unknown error')
                });
            }
        })
        .catch(error => {
            console.error('Error marking order as shipped:', error);
            this.modalManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Error marking order as shipped: ' + error.message
            });
        });
    }
    
    completeOrder(orderId) {
        fetch('/admin/api/orders/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ orderId: orderId })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to mark order as completed');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                this.modalManager.alert({
                    type: 'error',
                    title: 'Error',
                    message: 'Failed to mark order as completed: ' + (data.message || 'Unknown error')
                });
            }
        })
        .catch(error => {
            console.error('Error marking order as completed:', error);
            this.modalManager.alert({
                type: 'error',
                title: 'Error',
                message: 'Error marking order as completed: ' + error.message
            });
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.ordersManager = new OrdersManager();
});
