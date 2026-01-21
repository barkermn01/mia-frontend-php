/**
 * Shipping Method Form Management
 */

class ShippingFormManager {
    constructor() {
        this.regions = [];
        this.init();
    }
    
    init() {
        // Load initial regions if editing
        if (window.ShippingFormData && window.ShippingFormData.regions) {
            this.regions = window.ShippingFormData.regions.map(region => ({
                countryCode: region.countryCode || '',
                countryName: region.countryName || '',
                baseCost: region.baseCost ? (region.baseCost / 100).toFixed(2) : '0.00',
                costPerKg: region.costPerKg ? (region.costPerKg / 100).toFixed(2) : '0.00',
                freeShippingThreshold: region.freeShippingThreshold ? (region.freeShippingThreshold / 100).toFixed(2) : ''
            }));
        }
        
        // Event listeners
        document.getElementById('add-region-btn').addEventListener('click', () => this.addRegion());
        document.getElementById('shipping-form').addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Render initial regions
        this.renderRegions();
    }
    
    addRegion() {
        this.regions.push({
            countryCode: '',
            countryName: '',
            baseCost: '0.00',
            costPerKg: '0.00',
            freeShippingThreshold: ''
        });
        this.renderRegions();
    }
    
    removeRegion(index) {
        this.regions.splice(index, 1);
        this.renderRegions();
    }
    
    updateRegion(index, field, value) {
        this.regions[index][field] = value;
    }
    
    renderRegions() {
        const container = document.getElementById('regions-container');
        
        if (this.regions.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-globe text-3xl mb-2"></i>
                    <p>No regions configured. Click "Add Region" to get started.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.regions.map((region, index) => `
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h5 class="font-medium text-gray-900">Region ${index + 1}</h5>
                    <button type="button" onclick="shippingForm.removeRegion(${index})" 
                            class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Region Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" value="${this.escapeHtml(region.countryCode)}" 
                               onchange="shippingForm.updateRegion(${index}, 'countryCode', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., GB, GB-M, GB-H, GB-I" required>
                        <p class="text-xs text-gray-500 mt-1">ISO code or custom region (e.g., GB-M for Mainland)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Country Name</label>
                        <input type="text" value="${this.escapeHtml(region.countryName)}" 
                               onchange="shippingForm.updateRegion(${index}, 'countryName', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., United Kingdom">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Base Cost (£) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" value="${region.baseCost}" step="0.01" min="0"
                               onchange="shippingForm.updateRegion(${index}, 'baseCost', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., 4.99" required>
                        <p class="text-xs text-gray-500 mt-1">Fixed base shipping cost</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cost Per Kg (£)</label>
                        <input type="number" value="${region.costPerKg}" step="0.01" min="0"
                               onchange="shippingForm.updateRegion(${index}, 'costPerKg', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., 1.00">
                        <p class="text-xs text-gray-500 mt-1">Additional cost per kilogram</p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Free Shipping Threshold (£)</label>
                        <input type="number" value="${region.freeShippingThreshold}" step="0.01" min="0"
                               onchange="shippingForm.updateRegion(${index}, 'freeShippingThreshold', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., 50.00">
                        <p class="text-xs text-gray-500 mt-1">Cart total for free shipping (leave empty for no free shipping)</p>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        // Validate regions
        if (this.regions.length === 0) {
            if (window.modalManager) {
                window.modalManager.alert({
                    title: 'Validation Error',
                    message: 'Please add at least one region',
                    type: 'warning'
                });
            } else {
                alert('Please add at least one region');
            }
            return;
        }
        
        for (let i = 0; i < this.regions.length; i++) {
            const region = this.regions[i];
            if (!region.countryCode || !region.baseCost) {
                if (window.modalManager) {
                    window.modalManager.alert({
                        title: 'Validation Error',
                        message: `Region ${i + 1}: Region code and base cost are required`,
                        type: 'warning'
                    });
                } else {
                    alert(`Region ${i + 1}: Region code and base cost are required`);
                }
                return;
            }
            
            // Validate region code format (ISO or custom like GB-M)
            if (!/^[A-Z]{2}(-[A-Z0-9]+)?$/i.test(region.countryCode)) {
                if (window.modalManager) {
                    window.modalManager.alert({
                        title: 'Validation Error',
                        message: `Region ${i + 1}: Region code must be ISO format (e.g., GB, US) or custom (e.g., GB-M, GB-H)`,
                        type: 'warning'
                    });
                } else {
                    alert(`Region ${i + 1}: Region code must be ISO format (e.g., GB, US) or custom (e.g., GB-M, GB-H)`);
                }
                return;
            }
        }
        
        // Set regions data
        document.getElementById('regions-data').value = JSON.stringify(this.regions);
        
        // Submit form
        e.target.submit();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global instance
let shippingForm;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    shippingForm = new ShippingFormManager();
});
