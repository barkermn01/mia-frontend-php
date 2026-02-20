/**
 * Shipping Methods Management
 */

class ShippingManager {
    constructor() {
        this.modalManager = new ModalManager();
        this.currentMode = 'modern';
        this.init();
    }
    
    init() {
        // Load current shipping mode
        this.loadShippingMode();
        
        // Toggle mode section
        const toggleBtn = document.getElementById('toggle-mode-section');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleModeSection());
        }
        
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
        
        // Mode change listeners
        const modeRadios = document.querySelectorAll('input[name="shipping_mode"]');
        modeRadios.forEach(radio => {
            radio.addEventListener('change', () => this.onModeChange());
        });
        
        // Save mode button
        const saveModeBtn = document.getElementById('save-mode-btn');
        if (saveModeBtn) {
            saveModeBtn.addEventListener('click', () => this.saveShippingMode());
        }
    }
    
    toggleModeSection() {
        const content = document.getElementById('mode-section-content');
        const icon = document.getElementById('mode-section-icon');
        
        if (content && icon) {
            content.classList.toggle('hidden');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
    }
    
    async loadShippingMode() {
        try {
            const adminPath = window.location.pathname.split('/shipping')[0];
            const response = await fetch(`${adminPath}/api/settings/shipping_calculation_mode`);
            
            if (response.ok) {
                const data = await response.json();
                this.currentMode = data.value || 'modern';
            } else {
                this.currentMode = 'modern';
            }
        } catch (error) {
            console.warn('Could not fetch shipping mode, defaulting to modern:', error);
            this.currentMode = 'modern';
        }
        
        // Update UI
        this.updateModeUI();
    }
    
    updateModeUI() {
        const modernRadio = document.getElementById('mode-modern');
        const classicRadio = document.getElementById('mode-classic');
        const currentModeDisplay = document.getElementById('current-mode-display-compact');
        const saveModeBtn = document.getElementById('save-mode-btn');
        
        if (modernRadio) modernRadio.checked = this.currentMode === 'modern';
        if (classicRadio) classicRadio.checked = this.currentMode === 'classic';
        
        if (currentModeDisplay) {
            currentModeDisplay.textContent = this.currentMode === 'modern' ? 'Modern (Cost per kg)' : 'Classic (Weight Brackets)';
        }
        
        // Update label styling
        const modernLabel = document.getElementById('mode-modern-label');
        const classicLabel = document.getElementById('mode-classic-label');
        
        if (modernLabel && classicLabel) {
            if (this.currentMode === 'modern') {
                modernLabel.classList.add('border-blue-500', 'bg-blue-50');
                modernLabel.classList.remove('border-gray-300');
                classicLabel.classList.remove('border-blue-500', 'bg-blue-50');
                classicLabel.classList.add('border-gray-300');
            } else {
                classicLabel.classList.add('border-blue-500', 'bg-blue-50');
                classicLabel.classList.remove('border-gray-300');
                modernLabel.classList.remove('border-blue-500', 'bg-blue-50');
                modernLabel.classList.add('border-gray-300');
            }
        }
        
        // Disable save button initially
        if (saveModeBtn) {
            saveModeBtn.disabled = true;
        }
    }
    
    onModeChange() {
        const selectedMode = document.querySelector('input[name="shipping_mode"]:checked')?.value;
        const saveModeBtn = document.getElementById('save-mode-btn');
        
        // Enable save button if mode changed
        if (saveModeBtn && selectedMode && selectedMode !== this.currentMode) {
            saveModeBtn.disabled = false;
        } else if (saveModeBtn) {
            saveModeBtn.disabled = true;
        }
        
        // Update label styling
        const modernLabel = document.getElementById('mode-modern-label');
        const classicLabel = document.getElementById('mode-classic-label');
        
        if (modernLabel && classicLabel) {
            modernLabel.classList.remove('border-blue-500', 'bg-blue-50');
            modernLabel.classList.add('border-gray-300');
            classicLabel.classList.remove('border-blue-500', 'bg-blue-50');
            classicLabel.classList.add('border-gray-300');
            
            if (selectedMode === 'modern') {
                modernLabel.classList.add('border-blue-500', 'bg-blue-50');
                modernLabel.classList.remove('border-gray-300');
            } else if (selectedMode === 'classic') {
                classicLabel.classList.add('border-blue-500', 'bg-blue-50');
                classicLabel.classList.remove('border-gray-300');
            }
        }
    }
    
    async saveShippingMode() {
        const selectedMode = document.querySelector('input[name="shipping_mode"]:checked')?.value;
        
        if (!selectedMode) {
            this.modalManager.alert({
                title: 'Error',
                message: 'Please select a shipping mode',
                type: 'error'
            });
            return;
        }
        
        if (selectedMode === this.currentMode) {
            return;
        }
        
        // Confirm mode change
        this.modalManager.confirm({
            title: 'Change Shipping Mode',
            message: `Are you sure you want to switch to ${selectedMode === 'modern' ? 'Modern' : 'Classic'} mode? You will need to reconfigure regions for existing shipping methods.`,
            confirmText: 'Change Mode',
            confirmClass: 'warning',
            onConfirm: async () => {
                try {
                    const adminPath = window.location.pathname.split('/shipping')[0];
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('setting_name', 'shipping_calculation_mode');
                    formData.append('setting_type', 'text');
                    formData.append('setting_value', selectedMode);
                    
                    const response = await fetch(`${adminPath}/settings`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Check if redirect happened (success)
                    if (response.redirected || response.ok) {
                        // Update current mode
                        this.currentMode = selectedMode;
                        
                        // Update UI immediately
                        const currentModeDisplay = document.getElementById('current-mode-display-compact');
                        if (currentModeDisplay) {
                            currentModeDisplay.textContent = selectedMode === 'modern' ? 'Modern (Cost per kg)' : 'Classic (Weight Brackets)';
                        }
                        
                        // Disable save button
                        const saveModeBtn = document.getElementById('save-mode-btn');
                        if (saveModeBtn) {
                            saveModeBtn.disabled = true;
                        }
                        
                        // Collapse the section
                        const content = document.getElementById('mode-section-content');
                        const icon = document.getElementById('mode-section-icon');
                        if (content && icon) {
                            content.classList.add('hidden');
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                        }
                        
                        this.modalManager.alert({
                            title: 'Success',
                            message: 'Shipping mode updated successfully. Please reconfigure regions for your shipping methods.',
                            type: 'success'
                        });
                    } else {
                        const text = await response.text();
                        this.modalManager.alert({
                            title: 'Error',
                            message: 'Failed to update shipping mode',
                            type: 'error'
                        });
                    }
                } catch (error) {
                    this.modalManager.alert({
                        title: 'Error',
                        message: 'Error updating shipping mode: ' + error.message,
                        type: 'error'
                    });
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
                        if (window.modalManager) {
                            window.modalManager.alert({
                                title: 'Error',
                                message: data.message,
                                type: 'error'
                            });
                        } else {
                            alert('Error: ' + data.message);
                        }
                    }
                } catch (error) {
                    if (window.modalManager) {
                        window.modalManager.alert({
                            title: 'Error',
                            message: 'Error deleting shipping method: ' + error.message,
                            type: 'error'
                        });
                    } else {
                        alert('Error deleting shipping method: ' + error.message);
                    }
                }
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new ShippingManager();
});
