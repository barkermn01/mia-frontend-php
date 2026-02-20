/**
 * Shipping Method Form Management
 */

class ShippingFormManager {
    constructor() {
        this.regions = [];
        this.shippingMode = 'modern'; // Default to modern
        this.supportedCountries = {}; // Will be populated from settings
        this.init();
    }
    
    async init() {
        // Fetch shipping calculation mode from site settings FIRST
        await this.fetchShippingMode();
        
        // Fetch supported countries
        await this.fetchSupportedCountries();
        
        // Load initial regions if editing (after mode is loaded)
        if (window.ShippingFormData && window.ShippingFormData.regions) {
            let regionsData = window.ShippingFormData.regions;
            
            // If it's a string, parse it first
            if (typeof regionsData === 'string') {
                try {
                    regionsData = JSON.parse(regionsData);
                } catch (e) {
                    console.error('Failed to parse regions JSON:', e);
                    regionsData = [];
                }
            }
            
            // If regions is an object (keyed by country code), convert to array
            if (!Array.isArray(regionsData) && typeof regionsData === 'object' && regionsData !== null) {
                regionsData = Object.values(regionsData);
            }
            
            // Only process if we have valid data
            if (Array.isArray(regionsData) && regionsData.length > 0) {
                this.regions = regionsData.map(region => this.normalizeRegion(region));
            }
        }
        
        // Event listeners
        document.getElementById('add-region-btn').addEventListener('click', () => this.addRegion());
        document.getElementById('add-all-regions-btn').addEventListener('click', () => this.addAllRegions());
        document.getElementById('shipping-form').addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Render initial regions
        this.renderRegions();
    }
    
    async fetchShippingMode() {
        try {
            const adminPath = window.ShippingFormData?.adminPath || '';
            const response = await fetch(`${adminPath}/api/settings/shipping_calculation_mode`);
            
            if (response.ok) {
                const data = await response.json();
                this.shippingMode = data.value || 'modern';
            } else {
                this.shippingMode = 'modern';
            }
        } catch (error) {
            console.warn('Could not fetch shipping mode, defaulting to modern:', error);
            this.shippingMode = 'modern';
        }
    }
    
    async fetchSupportedCountries() {
        try {
            const adminPath = window.ShippingFormData?.adminPath || '';
            const response = await fetch(`${adminPath}/api/settings/Supported%20Countries`);
            
            if (response.ok) {
                const data = await response.json();
                if (data.value && typeof data.value === 'object') {
                    this.supportedCountries = data.value;
                } else {
                    // Fallback to default countries
                    this.supportedCountries = {
                        'GB': 'United Kingdom',
                        'US': 'United States',
                        'CA': 'Canada',
                        'AU': 'Australia',
                        'DE': 'Germany',
                        'FR': 'France'
                    };
                }
            } else {
                // Fallback to default countries
                this.supportedCountries = {
                    'GB': 'United Kingdom',
                    'US': 'United States',
                    'CA': 'Canada',
                    'AU': 'Australia',
                    'DE': 'Germany',
                    'FR': 'France'
                };
            }
        } catch (error) {
            console.warn('Could not fetch supported countries, using defaults:', error);
            this.supportedCountries = {
                'GB': 'United Kingdom',
                'US': 'United States',
                'CA': 'Canada',
                'AU': 'Australia',
                'DE': 'Germany',
                'FR': 'France'
            };
        }
    }
    
    normalizeRegion(region) {
        if (region.weightBrackets) {
            // Classic mode region
            return {
                countryCode: region.countryCode || '',
                countryName: region.countryName || '',
                weightBrackets: region.weightBrackets.map(b => ({
                    minKg: b.minKg || 0,
                    maxKg: b.maxKg || 0,
                    cost: b.cost ? (b.cost / 100).toFixed(2) : '0.00'
                })),
                freeShippingThreshold: region.freeShippingThreshold ? (region.freeShippingThreshold / 100).toFixed(2) : ''
            };
        } else {
            // Modern mode region
            return {
                countryCode: region.countryCode || '',
                countryName: region.countryName || '',
                baseCost: region.baseCost ? (region.baseCost / 100).toFixed(2) : '0.00',
                costPerKg: region.costPerKg ? (region.costPerKg / 100).toFixed(2) : '0.00',
                freeShippingThreshold: region.freeShippingThreshold ? (region.freeShippingThreshold / 100).toFixed(2) : ''
            };
        }
    }
    
    addRegion() {
        if (this.shippingMode === 'classic') {
            this.regions.push({
                countryCode: '',
                countryName: '',
                weightBrackets: [
                    { minKg: 0, maxKg: 1, cost: '5.00' },
                    { minKg: 1, maxKg: 2, cost: '7.50' },
                    { minKg: 2, maxKg: 5, cost: '12.00' }
                ],
                freeShippingThreshold: ''
            });
        } else {
            this.regions.push({
                countryCode: '',
                countryName: '',
                baseCost: '0.00',
                costPerKg: '0.00',
                freeShippingThreshold: ''
            });
        }
        this.renderRegions();
    }
    
    addAllRegions() {
        // Get list of country codes already added
        const existingCodes = this.regions.map(r => r.countryCode);
        
        // Track which regions will be added
        const addedRegions = [];
        
        // Add regions for all supported countries that aren't already added
        Object.entries(this.supportedCountries).forEach(([code, name]) => {
            if (!existingCodes.includes(code)) {
                if (this.shippingMode === 'classic') {
                    this.regions.push({
                        countryCode: code,
                        countryName: name,
                        weightBrackets: [
                            { minKg: 0, maxKg: 1, cost: '5.00' },
                            { minKg: 1, maxKg: 2, cost: '7.50' },
                            { minKg: 2, maxKg: 5, cost: '12.00' }
                        ],
                        freeShippingThreshold: ''
                    });
                } else {
                    this.regions.push({
                        countryCode: code,
                        countryName: name,
                        baseCost: '5.00',
                        costPerKg: '2.00',
                        freeShippingThreshold: ''
                    });
                }
                addedRegions.push(name);
            }
        });
        
        // Show appropriate message based on what was added
        if (addedRegions.length > 0) {
            this.renderRegions();
            
            // Build list of added regions
            const regionList = addedRegions.length <= 5 
                ? addedRegions.join(', ')
                : `${addedRegions.slice(0, 5).join(', ')} and ${addedRegions.length - 5} more`;
            
            if (typeof ModalManager !== 'undefined' && window.modalManager) {
                window.modalManager.alert({
                    title: 'Regions Added',
                    message: `Added ${addedRegions.length} region${addedRegions.length > 1 ? 's' : ''}: ${regionList}. Please configure pricing for each region.`,
                    type: 'success'
                });
            }
        } else {
            // No regions were missing
            if (typeof ModalManager !== 'undefined' && window.modalManager) {
                window.modalManager.alert({
                    title: 'No Missing Regions',
                    message: 'All supported regions have already been added to this shipping method.',
                    type: 'info'
                });
            }
        }
    }
    
    removeRegion(index) {
        this.regions.splice(index, 1);
        this.renderRegions();
    }
    
    updateRegion(index, field, value) {
        this.regions[index][field] = value;
    }
    
    addWeightBracket(regionIndex) {
        if (!this.regions[regionIndex].weightBrackets) {
            this.regions[regionIndex].weightBrackets = [];
        }
        const brackets = this.regions[regionIndex].weightBrackets;
        const lastBracket = brackets[brackets.length - 1];
        const newMinKg = lastBracket ? lastBracket.maxKg : 0;
        
        brackets.push({
            minKg: newMinKg,
            maxKg: newMinKg + 5,
            cost: '0.00'
        });
        this.renderRegions();
    }
    
    removeWeightBracket(regionIndex, bracketIndex) {
        this.regions[regionIndex].weightBrackets.splice(bracketIndex, 1);
        this.renderRegions();
    }
    
    updateWeightBracket(regionIndex, bracketIndex, field, value) {
        this.regions[regionIndex].weightBrackets[bracketIndex][field] = parseFloat(value) || 0;
    }
    
    toggleUnlimitedWeight(regionIndex, bracketIndex, isUnlimited) {
        if (isUnlimited) {
            this.regions[regionIndex].weightBrackets[bracketIndex].maxKg = -1;
        } else {
            // Set to a default value when unchecking
            const minKg = this.regions[regionIndex].weightBrackets[bracketIndex].minKg || 0;
            this.regions[regionIndex].weightBrackets[bracketIndex].maxKg = minKg + 5;
        }
        this.renderRegions();
    }
    
    renderRegions() {
        const container = document.getElementById('regions-container');
        
        // Update "Add All Regions" button text
        const addAllBtn = document.getElementById('add-all-regions-btn');
        if (addAllBtn) {
            if (this.regions.length > 0) {
                addAllBtn.innerHTML = '<i class="fas fa-globe mr-2"></i>Add Missing Regions';
            } else {
                addAllBtn.innerHTML = '<i class="fas fa-globe mr-2"></i>Add All Regions';
            }
        }
        
        if (this.regions.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-globe text-3xl mb-2"></i>
                    <p>No regions configured. Click "Add Region" to get started.</p>
                    <p class="text-xs mt-2">Mode: <strong>${this.shippingMode === 'classic' ? 'Classic (Weight Brackets)' : 'Modern (Cost per kg)'}</strong></p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = this.regions.map((region, index) => {
            if (this.shippingMode === 'classic') {
                return this.renderClassicRegion(region, index);
            } else {
                return this.renderModernRegion(region, index);
            }
        }).join('');
    }
    
    renderModernRegion(region, index) {
        const countryOptions = Object.entries(this.supportedCountries).map(([code, name]) => {
            const selected = region.countryCode === code ? 'selected' : '';
            return `<option value="${code}" ${selected}>${this.escapeHtml(name)}</option>`;
        }).join('');
        
        return `
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h5 class="font-medium text-gray-900">Region ${index + 1} <span class="text-xs text-gray-500">(Modern Mode)</span></h5>
                    <button type="button" onclick="shippingForm.removeRegion(${index})" 
                            class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Region <span class="text-red-500">*</span>
                        </label>
                        <select onchange="shippingForm.updateRegion(${index}, 'countryCode', this.value); shippingForm.updateRegion(${index}, 'countryName', this.options[this.selectedIndex].text)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select a region...</option>
                            ${countryOptions}
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select from configured supported countries</p>
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
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Free Shipping Threshold (£)</label>
                        <input type="number" value="${region.freeShippingThreshold}" step="0.01" min="0"
                               onchange="shippingForm.updateRegion(${index}, 'freeShippingThreshold', this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., 50.00">
                        <p class="text-xs text-gray-500 mt-1">Cart total for free shipping (leave empty for no free shipping)</p>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderClassicRegion(region, index) {
        const countryOptions = Object.entries(this.supportedCountries).map(([code, name]) => {
            const selected = region.countryCode === code ? 'selected' : '';
            return `<option value="${code}" ${selected}>${this.escapeHtml(name)}</option>`;
        }).join('');
        
        const bracketsHtml = (region.weightBrackets || []).map((bracket, bIndex) => {
            const isUnlimited = bracket.maxKg === -1 || bracket.maxKg === '-1';
            const maxKgValue = isUnlimited ? '' : bracket.maxKg;
            
            return `
            <tr>
                <td class="px-3 py-2">
                    <input type="number" value="${bracket.minKg}" step="0.1" min="0"
                           onchange="shippingForm.updateWeightBracket(${index}, ${bIndex}, 'minKg', this.value)"
                           class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                </td>
                <td class="px-3 py-2">
                    <div class="flex items-center space-x-2">
                        <input type="number" value="${maxKgValue}" step="0.1" min="0"
                               id="maxKg_${index}_${bIndex}"
                               onchange="shippingForm.updateWeightBracket(${index}, ${bIndex}, 'maxKg', this.value)"
                               class="flex-1 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               ${isUnlimited ? 'disabled' : 'required'}
                               placeholder="${isUnlimited ? 'Unlimited' : ''}">
                        <label class="flex items-center text-xs whitespace-nowrap">
                            <input type="checkbox" 
                                   ${isUnlimited ? 'checked' : ''}
                                   onchange="shippingForm.toggleUnlimitedWeight(${index}, ${bIndex}, this.checked)"
                                   class="mr-1">
                            Unlimited
                        </label>
                    </div>
                </td>
                <td class="px-3 py-2">
                    <input type="number" value="${bracket.cost}" step="0.01" min="0"
                           onchange="shippingForm.updateWeightBracket(${index}, ${bIndex}, 'cost', this.value)"
                           class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="0.00" required>
                </td>
                <td class="px-3 py-2 text-center">
                    <button type="button" onclick="shippingForm.removeWeightBracket(${index}, ${bIndex})"
                            class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
        
        return `
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h5 class="font-medium text-gray-900">Region ${index + 1} <span class="text-xs text-gray-500">(Classic Mode)</span></h5>
                    <button type="button" onclick="shippingForm.removeRegion(${index})" 
                            class="text-red-600 hover:text-red-800">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Region <span class="text-red-500">*</span>
                    </label>
                    <select onchange="shippingForm.updateRegion(${index}, 'countryCode', this.value); shippingForm.updateRegion(${index}, 'countryName', this.options[this.selectedIndex].text)"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select a region...</option>
                        ${countryOptions}
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select from configured supported countries</p>
                </div>
                
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            Weight Brackets <span class="text-red-500">*</span>
                        </label>
                        <button type="button" onclick="shippingForm.addWeightBracket(${index})"
                                class="text-sm bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                            <i class="fas fa-plus mr-1"></i>Add Bracket
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-300 rounded-lg">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Min Kg</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Max Kg</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Cost (£)</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-700">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${bracketsHtml || '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No brackets added</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Define shipping costs for different weight ranges. Check "Unlimited" for the final bracket with no upper limit.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Free Shipping Threshold (£)</label>
                    <input type="number" value="${region.freeShippingThreshold}" step="0.01" min="0"
                           onchange="shippingForm.updateRegion(${index}, 'freeShippingThreshold', this.value)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., 50.00">
                    <p class="text-xs text-gray-500 mt-1">Cart total for free shipping (leave empty for no free shipping)</p>
                </div>
            </div>
        `;
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        // Validate regions
        if (this.regions.length === 0) {
            if (typeof ModalManager !== 'undefined' && window.modalManager) {
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
            if (!region.countryCode) {
                if (typeof ModalManager !== 'undefined' && window.modalManager) {
                    window.modalManager.alert({
                        title: 'Validation Error',
                        message: `Region ${i + 1}: Region code is required`,
                        type: 'warning'
                    });
                } else {
                    alert(`Region ${i + 1}: Region code is required`);
                }
                return;
            }
            
            // Validate region code format
            if (!/^[A-Z]{2}(-[A-Z0-9]+)?$/i.test(region.countryCode)) {
                if (typeof ModalManager !== 'undefined' && window.modalManager) {
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
            
            // Mode-specific validation
            if (this.shippingMode === 'classic') {
                if (!region.weightBrackets || region.weightBrackets.length === 0) {
                    if (typeof ModalManager !== 'undefined' && window.modalManager) {
                        window.modalManager.alert({
                            title: 'Validation Error',
                            message: `Region ${i + 1}: At least one weight bracket is required`,
                            type: 'warning'
                        });
                    } else {
                        alert(`Region ${i + 1}: At least one weight bracket is required`);
                    }
                    return;
                }
            } else {
                if (!region.baseCost) {
                    if (typeof ModalManager !== 'undefined' && window.modalManager) {
                        window.modalManager.alert({
                            title: 'Validation Error',
                            message: `Region ${i + 1}: Base cost is required`,
                            type: 'warning'
                        });
                    } else {
                        alert(`Region ${i + 1}: Base cost is required`);
                    }
                    return;
                }
            }
        }
        
        // Convert regions data to backend format (pounds to pence)
        const regionsForBackend = this.regions.map(region => {
            const converted = {
                countryCode: region.countryCode,
                countryName: region.countryName || ''
            };
            
            if (this.shippingMode === 'classic') {
                // Classic mode: weightBrackets with cost in pence
                converted.weightBrackets = region.weightBrackets.map(bracket => ({
                    minKg: parseFloat(bracket.minKg) || 0,
                    maxKg: parseFloat(bracket.maxKg) || 0,
                    cost: Math.round((parseFloat(bracket.cost) || 0) * 100) // £5.00 -> 500
                }));
            } else {
                // Modern mode: baseCost and costPerKg in pence
                converted.baseCost = Math.round((parseFloat(region.baseCost) || 0) * 100);
                converted.costPerKg = Math.round((parseFloat(region.costPerKg) || 0) * 100);
            }
            
            // Free shipping threshold in pence (both modes)
            if (region.freeShippingThreshold) {
                converted.freeShippingThreshold = Math.round(parseFloat(region.freeShippingThreshold) * 100);
            }
            
            return converted;
        });
        
        // Set regions data
        document.getElementById('regions-data').value = JSON.stringify(regionsForBackend);
        
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
    // Wait a bit for ModalManager to be initialized
    setTimeout(() => {
        shippingForm = new ShippingFormManager();
    }, 100);
});
