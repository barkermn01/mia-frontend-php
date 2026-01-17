/**
 * Shipping Methods Management
 */

class ShippingManager {
    constructor() {
        this.modalManager = new ModalManager();
        this.init();
    }
    
    init() {
        // Event delegation for delete buttons and toggle regions
        document.addEventListener('click', (e) => {
            // Delete button
            if (e.target.closest('.delete-shipping-btn')) {
                const btn = e.target.closest('.delete-shipping-btn');
                const methodId = btn.dataset.methodId;
                const methodName = btn.dataset.methodName;
                this.deleteShippingMethod(methodId, methodName);
                return;
            }
            
            // Toggle regions - check if clicked element or its parent is the toggle button
            const toggleBtn = e.target.closest('.toggle-regions-btn');
            if (toggleBtn) {
                e.preventDefault();
                const content = toggleBtn.nextElementSibling;
                const icon = toggleBtn.querySelector('i');
                
                if (content && icon) {
                    content.classList.toggle('hidden');
                    icon.classList.toggle('fa-chevron-down');
                    icon.classList.toggle('fa-chevron-up');
                }
            }
        });
    }
    
    deleteShippingMethod(methodId, methodName) {
        this.modalManager.confirm({
            title: 'Delete Shipping Method',
            message: `Are you sure you want to delete "${methodName}"? This action cannot be undone.`,
            confirmText: 'Delete',
            confirmClass: 'danger',
            onConfirm: async () => {
                try {
                    const adminPath = window.location.pathname.split('/shipping')[0];
                    const response = await fetch(`${adminPath}/shipping/delete`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ methodId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = `${adminPath}/shipping?success=` + encodeURIComponent('Shipping method deleted successfully');
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (error) {
                    alert('Error deleting shipping method: ' + error.message);
                }
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new ShippingManager();
});
