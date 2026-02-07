// Orders Management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Process order button handler
    document.querySelectorAll('.process-order-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (!confirm('Mark this order as processing?')) {
                return;
            }
            
            fetch(`/admin/api/orders/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: 'processing' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to update order status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error updating order status: ' + error.message);
            });
        });
    });
    
    // Cancel order button handler
    document.querySelectorAll('.cancel-order-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                return;
            }
            
            fetch(`/admin/api/orders/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: 'cancelled' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to cancel order: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error cancelling order: ' + error.message);
            });
        });
    });
});
