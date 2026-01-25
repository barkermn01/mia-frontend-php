/**
 * Site Settings Management
 */

class SettingsManager {
    constructor(adminPath) {
        this.adminPath = adminPath;
        this.modalManager = new ModalManager();
        this.toastEditor = null;
        this.currentImageUrl = '';
        this.listItems = [];
        this.propertySet = {};
        
        this.init();
    }
    
    init() {
        // Initialize form submission handler
        const form = document.getElementById('setting-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
        
        // Close modal when clicking outside
        const modal = document.getElementById('setting-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeSettingModal();
                }
            });
        }
        
        // Event delegation for buttons
        document.addEventListener('click', (e) => {
            // Add setting buttons
            if (e.target.closest('#add-setting-btn') || e.target.closest('#add-setting-btn-empty')) {
                this.showAddSettingModal();
            }
            
            // Edit setting buttons
            if (e.target.closest('.edit-setting-btn')) {
                const btn = e.target.closest('.edit-setting-btn');
                const name = btn.dataset.name;
                const setting = JSON.parse(btn.dataset.setting);
                this.editSetting(name, setting);
            }
            
            // Delete setting buttons
            if (e.target.closest('.delete-setting-btn')) {
                const btn = e.target.closest('.delete-setting-btn');
                const name = btn.dataset.name;
                this.deleteSetting(name);
            }
            
            // Close modal button
            if (e.target.closest('#close-modal-btn')) {
                this.closeSettingModal();
            }
        });
        
        // Setting type change handler
        const settingType = document.getElementById('setting_type');
        if (settingType) {
            settingType.addEventListener('change', () => this.updateValueField());
        }
        
        // Initialize value field
        this.updateValueField();
    }
    
    showAddSettingModal() {
        document.getElementById('modal-title').textContent = 'Add Setting';
        document.getElementById('setting-form').reset();
        document.getElementById('setting_name').readOnly = false;
        document.getElementById('setting-modal').classList.remove('hidden');
        this.currentImageUrl = '';
        this.listItems = [];
        this.propertySet = {};
        this.updateValueField();
    }
    
    editSetting(name, setting) {
        document.getElementById('modal-title').textContent = 'Edit Setting';
        document.getElementById('setting_name').value = name;
        document.getElementById('setting_name').readOnly = true;
        
        // Determine the UI type
        let uiType = setting.type;
        if (setting.type === 'json') {
            if (Array.isArray(setting.value)) {
                uiType = 'list';
                this.listItems = [...setting.value];
            } else if (typeof setting.value === 'object') {
                uiType = 'property_set';
                this.propertySet = {...setting.value};
            }
        }
        
        document.getElementById('setting_type').value = uiType;
        
        // Set value based on type
        if (setting.type === 'image') {
            this.currentImageUrl = setting.value;
        }
        
        this.updateValueField();
        
        // For markdown, set content after editor is initialized
        if (setting.type === 'markdown') {
            setTimeout(() => {
                if (this.toastEditor) {
                    this.toastEditor.setMarkdown(setting.value);
                }
            }, 100);
        } else if (setting.type === 'text') {
            document.getElementById('text_value').value = setting.value;
        }
        
        document.getElementById('setting-modal').classList.remove('hidden');
    }
    
    closeSettingModal() {
        document.getElementById('setting-modal').classList.add('hidden');
        if (this.toastEditor) {
            this.toastEditor.destroy();
            this.toastEditor = null;
        }
    }
    
    updateValueField() {
        const type = document.getElementById('setting_type').value;
        const valueField = document.getElementById('value-field');
        
        // Destroy existing editor if any
        if (this.toastEditor) {
            this.toastEditor.destroy();
            this.toastEditor = null;
        }
        
        let html = '';
        
        switch(type) {
            case 'text':
                html = `
                    <label class="block text-sm font-medium text-gray-700 mb-2">Value</label>
                    <input type="text" id="text_value" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Enter text value">
                `;
                break;
                
            case 'image':
                html = `
                    <label class="block text-sm font-medium text-gray-700 mb-2">Image</label>
                    <div class="space-y-3">
                        ${this.currentImageUrl ? `
                            <div class="relative border-2 border-gray-300 rounded-lg p-6 text-center group">
                                <img src="${this.currentImageUrl}" alt="Current image" class="max-h-48 mx-auto object-contain rounded-lg">
                                <button type="button" onclick="settingsManager.removeCurrentImage()" 
                                        class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        ` : `
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors cursor-pointer" onclick="document.getElementById('image_upload').click()">
                                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 mb-2">Drag and drop image here, or click to select</p>
                                <button type="button" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    Select Image
                                </button>
                                <p class="text-sm text-gray-500 mt-2">PNG, JPG, GIF up to 5MB</p>
                            </div>
                        `}
                        <input type="file" id="image_upload" accept="image/*" onchange="settingsManager.handleImageUpload(this)" class="hidden">
                        <div id="upload-progress" class="hidden">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div id="upload-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p class="text-xs text-gray-600 mt-2">Uploading...</p>
                        </div>
                    </div>
                `;
                break;
                
            case 'markdown':
                html = `
                    <label class="block text-sm font-medium text-gray-700 mb-2">Markdown Content</label>
                    <div id="markdown-editor"></div>
                    <p class="text-xs text-gray-500 mt-1">Use the editor to format your content</p>
                `;
                break;
                
            case 'list':
                html = `
                    <label class="block text-sm font-medium text-gray-700 mb-2">List Items</label>
                    <div id="list-container" class="space-y-2 mb-3">
                        ${this.listItems.map((item, index) => `
                            <div class="flex items-center space-x-2" data-index="${index}">
                                <button type="button" onclick="settingsManager.moveListItem(${index}, -1)" ${index === 0 ? 'disabled' : ''}
                                        class="text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button type="button" onclick="settingsManager.moveListItem(${index}, 1)" ${index === this.listItems.length - 1 ? 'disabled' : ''}
                                        class="text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                                <input type="text" value="${this.escapeHtml(item)}" onchange="settingsManager.updateListItem(${index}, this.value)"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" onclick="settingsManager.removeListItem(${index})"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" onclick="settingsManager.addListItem()" 
                            class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Add Item
                    </button>
                `;
                break;
                
            case 'property_set':
                html = `
                    <label class="block text-sm font-medium text-gray-700 mb-2">Properties</label>
                    <div id="property-container" class="space-y-2 mb-3">
                        ${Object.entries(this.propertySet).map(([key, value]) => `
                            <div class="flex items-center space-x-2">
                                <input type="text" value="${this.escapeHtml(key)}" onchange="settingsManager.updatePropertyKey('${this.escapeJs(key)}', this.value)"
                                    placeholder="Property Name (required)"
                                    class="w-1/3 px-3 py-2 border ${key.trim() === '' ? 'border-red-300 bg-red-50' : 'border-gray-300'} rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <input type="text" value="${this.escapeHtml(value)}" onchange="settingsManager.updatePropertyValue('${this.escapeJs(key)}', this.value)"
                                    placeholder="Value"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <button type="button" onclick="settingsManager.removeProperty('${this.escapeJs(key)}')"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" onclick="settingsManager.addProperty()" 
                            class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Add Property
                    </button>
                    <p class="text-xs text-gray-500 mt-2">All properties must have unique names before saving</p>
                `;
                break;
        }
        
        valueField.innerHTML = html;
        
        // Initialize Toast UI Editor for markdown
        if (type === 'markdown') {
            setTimeout(() => {
                this.toastEditor = new toastui.Editor({
                    el: document.querySelector('#markdown-editor'),
                    height: '500px',
                    initialEditType: 'markdown',
                    previewStyle: 'vertical',
                    hideModeSwitch: true,
                    usageStatistics: false,
                    initialValue: ''
                });
                
                // Apply custom markdown styles to the preview pane
                setTimeout(() => {
                    const editorElement = document.querySelector('#markdown-editor');
                    if (editorElement) {
                        const previewElement = editorElement.querySelector('.toastui-editor-md-preview');
                        if (previewElement) {
                            previewElement.classList.add('markdown-content');
                        }
                    }
                }, 100);
            }, 100);
        }
    }
    
    removeCurrentImage() {
        this.currentImageUrl = '';
        this.updateValueField();
    }
    
    async handleImageUpload(input) {
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const progressDiv = document.getElementById('upload-progress');
        const progressBar = document.getElementById('upload-progress-bar');
        
        try {
            progressDiv.classList.remove('hidden');
            progressBar.style.width = '30%';
            
            // Get upload token
            const tokenResponse = await fetch(`${this.adminPath}/api/upload-token`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    filename: file.name,
                    contentType: file.type,
                    maxSizeBytes: 5242880
                })
            });
            
            const tokenData = await tokenResponse.json();
            if (!tokenData.success) throw new Error(tokenData.error);
            
            progressBar.style.width = '60%';
            
            // Upload to our API endpoint
            const formData = new FormData();
            formData.append('image', file);
            formData.append('uploadToken', tokenData.data.uploadToken);
            
            const uploadResponse = await fetch(`${this.adminPath}/api/upload-image`, {
                method: 'POST',
                body: formData
            });
            
            const uploadData = await uploadResponse.json();
            if (!uploadData.success) throw new Error(uploadData.error);
            
            progressBar.style.width = '100%';
            this.currentImageUrl = uploadData.data.imageUrl;
            
            setTimeout(() => {
                progressDiv.classList.add('hidden');
                progressBar.style.width = '0%';
                this.updateValueField();
            }, 500);
            
        } catch (error) {
            if (window.modalManager) {
                window.modalManager.alert({
                    title: 'Upload Failed',
                    message: 'Image upload failed: ' + error.message,
                    type: 'error'
                });
            } else {
                alert('Image upload failed: ' + error.message);
            }
            progressDiv.classList.add('hidden');
            progressBar.style.width = '0%';
        }
    }
    
    // List management functions
    addListItem() {
        this.listItems.push('');
        this.updateValueField();
    }
    
    removeListItem(index) {
        this.listItems.splice(index, 1);
        this.updateValueField();
    }
    
    updateListItem(index, value) {
        this.listItems[index] = value;
    }
    
    moveListItem(index, direction) {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= this.listItems.length) return;
        
        [this.listItems[index], this.listItems[newIndex]] = [this.listItems[newIndex], this.listItems[index]];
        this.updateValueField();
    }
    
    // Property set management functions
    addProperty() {
        this.propertySet[''] = ''; // Empty key that must be filled
        this.updateValueField();
    }
    
    removeProperty(key) {
        delete this.propertySet[key];
        this.updateValueField();
    }
    
    updatePropertyKey(oldKey, newKey) {
        if (oldKey === newKey) return;
        
        // Trim the new key
        newKey = newKey.trim();
        
        // Check if new key already exists (and it's not the same property being renamed)
        if (newKey && this.propertySet.hasOwnProperty(newKey) && newKey !== oldKey) {
            this.modalManager.alert({
                title: 'Duplicate Property Name',
                message: `A property named "${newKey}" already exists. Please use a unique name.`,
                type: 'warning'
            });
            this.updateValueField();
            return;
        }
        
        this.propertySet[newKey] = this.propertySet[oldKey];
        delete this.propertySet[oldKey];
    }
    
    updatePropertyValue(key, value) {
        this.propertySet[key] = value;
    }
    
    validatePropertySet() {
        // Check for empty property names
        const emptyKeys = Object.keys(this.propertySet).filter(key => key.trim() === '');
        if (emptyKeys.length > 0) {
            this.modalManager.alert({
                title: 'Invalid Properties',
                message: 'All properties must have a name. Please provide names for all properties before saving.',
                type: 'warning'
            });
            return false;
        }
        
        // Check for duplicate property names (case-insensitive)
        const keys = Object.keys(this.propertySet).map(k => k.trim().toLowerCase());
        const duplicates = keys.filter((key, index) => keys.indexOf(key) !== index);
        
        if (duplicates.length > 0) {
            this.modalManager.alert({
                title: 'Duplicate Property Names',
                message: 'All property names must be unique. Please ensure each property has a different name.',
                type: 'warning'
            });
            return false;
        }
        
        return true;
    }
    
    handleFormSubmit(e) {
        e.preventDefault();
        
        const type = document.getElementById('setting_type').value;
        let value = '';
        let actualType = type;
        
        switch(type) {
            case 'text':
                value = document.getElementById('text_value').value;
                break;
            case 'image':
                if (!this.currentImageUrl) {
                    if (window.modalManager) {
                        window.modalManager.alert({
                            title: 'Validation Error',
                            message: 'Please upload an image',
                            type: 'warning'
                        });
                    } else {
                        alert('Please upload an image');
                    }
                    return;
                }
                value = this.currentImageUrl;
                break;
            case 'markdown':
                if (this.toastEditor) {
                    value = this.toastEditor.getMarkdown();
                }
                break;
            case 'list':
                value = JSON.stringify(this.listItems);
                actualType = 'json';
                break;
            case 'property_set':
                // Validate property set
                if (!this.validatePropertySet()) {
                    return;
                }
                value = JSON.stringify(this.propertySet);
                actualType = 'json';
                break;
        }
        
        document.getElementById('setting_value_hidden').value = value;
        document.getElementById('setting_type').value = actualType;
        
        e.target.submit();
    }
    
    deleteSetting(settingName) {
        this.modalManager.confirm({
            title: 'Delete Setting',
            message: `Are you sure you want to delete the setting "${settingName}"? This action cannot be undone.`,
            confirmText: 'Delete',
            confirmClass: 'danger',
            onConfirm: async () => {
                try {
                    const response = await fetch(`${this.adminPath}/api/settings/delete`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ settingName })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.href = `${this.adminPath}/settings?success=` + encodeURIComponent('Setting deleted successfully');
                    } else {
                        this.modalManager.alert({
                            title: 'Error',
                            message: data.message,
                            type: 'error'
                        });
                    }
                } catch (error) {
                    this.modalManager.alert({
                        title: 'Error',
                        message: 'Error deleting setting: ' + error.message,
                        type: 'error'
                    });
                }
            }
        });
    }
    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    escapeJs(text) {
        return text.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
}

// Global instance and functions
let settingsManager;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    const adminPath = window.MiaStoreConfig?.adminPath || '/admin';
    settingsManager = new SettingsManager(adminPath);
});

// Global functions for onclick handlers
function showAddSettingModal() {
    settingsManager.showAddSettingModal();
}

function editSetting(name, setting) {
    settingsManager.editSetting(name, setting);
}

function closeSettingModal() {
    settingsManager.closeSettingModal();
}

function deleteSetting(settingName) {
    settingsManager.deleteSetting(settingName);
}
