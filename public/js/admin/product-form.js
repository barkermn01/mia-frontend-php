/**
 * Product Form JavaScript Module
 * Handles all product form functionality including variants, images, and Toast UI Editor
 */

class ProductForm {
    constructor(config) {
        this.config = config;
        this.draggedElement = null;
        this.variants = [];
        this.toastEditor = null;
        
        // Initialize all components
        this.initializeImageUpload();
        this.initializeVariants();
        this.initializeFormSubmission();
        this.initializeDragAndDrop();
        this.initializeToastEditor();
        this.updateFormSubmissionForEditor();
        
        // Check existing images for square dimensions
        this.checkExistingImages();
        
        // Always update images input on load to include existing images
        this.updateImagesInput();
        
        // Load existing variants if provided
        if (config.variants && config.variants.length > 0) {
            this.variants = config.variants;
            this.updateVariantsInput();
        }
    }

    // ==================== IMAGE UPLOAD AND MANAGEMENT ====================

    checkExistingImages() {
        const gallery = document.getElementById('image-gallery');
        if (!gallery) {
            return;
        }
        
        const imageItems = gallery.querySelectorAll('.image-item');
        
        imageItems.forEach((item, index) => {
            const img = item.querySelector('img');
            if (!img) {
                return;
            }
            
            // Check if warning already exists
            if (item.querySelector('.not-square-warning')) {
                return;
            }
            
            // Add warning element
            const warning = document.createElement('div');
            warning.className = 'not-square-warning hidden absolute top-2 right-8 bg-gradient-to-r from-red-600 to-red-700 text-white text-xs px-2 py-1 rounded font-semibold shadow-lg z-10';
            warning.textContent = 'Image not Square';
            item.appendChild(warning);
            
            // Check dimensions when image loads
            const checkDimensions = () => {
                const width = img.naturalWidth;
                const height = img.naturalHeight;
                
                if (width && height) {
                    if (width !== height) {
                        warning.classList.remove('hidden');
                    }
                }
            };
            
            if (img.complete && img.naturalWidth > 0) {
                checkDimensions();
            } else {
                img.onload = checkDimensions;
            }
        });
    }

    initializeImageUpload() {
        const uploadArea = document.getElementById('upload-area');
        const imageInput = document.getElementById('image-input');
        const selectBtn = document.getElementById('select-images-btn');
        
        if (!uploadArea || !imageInput || !selectBtn) return;
        
        // Function to open file dialog
        const openFileDialog = () => {
            imageInput.click();
        };
        
        // Button click handler - just open dialog, no propagation issues
        selectBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openFileDialog();
        });
        
        // Upload area click handler - only if NOT clicking on button
        uploadArea.addEventListener('click', (e) => {
            // Only trigger if clicking directly on upload area, not on button
            if (e.target === uploadArea || (e.target.closest('#upload-area') && !e.target.closest('#select-images-btn'))) {
                openFileDialog();
            }
        });
        
        // File input change
        imageInput.addEventListener('change', (e) => this.handleFileSelect(e));
        
        // Drag and drop on upload area
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-blue-400', 'bg-blue-50');
        });
        
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-blue-400', 'bg-blue-50');
            const files = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'));
            if (files.length > 0) {
                this.uploadFiles(files);
            }
        });
    }

    initializeDragAndDrop() {
        const gallery = document.getElementById('image-gallery');
        if (!gallery) return;

        gallery.addEventListener('dragstart', (e) => this.handleDragStart(e));
        gallery.addEventListener('dragover', (e) => this.handleDragOver(e));
        gallery.addEventListener('drop', (e) => this.handleDrop(e));
        gallery.addEventListener('dragend', (e) => this.handleDragEnd(e));
    }

    handleDragStart(e) {
        if (!e.target.closest('.image-item')) return;
        
        this.draggedElement = e.target.closest('.image-item');
        this.draggedElement.classList.add('opacity-50');
        e.dataTransfer.effectAllowed = 'move';
    }

    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const afterElement = this.getDragAfterElement(e.clientY);
        const gallery = document.getElementById('image-gallery');
        
        if (afterElement == null) {
            gallery.appendChild(this.draggedElement);
        } else {
            gallery.insertBefore(this.draggedElement, afterElement);
        }
    }

    handleDrop(e) {
        e.preventDefault();
        this.updateImageOrder();
    }

    handleDragEnd(e) {
        if (this.draggedElement) {
            this.draggedElement.classList.remove('opacity-50');
            this.draggedElement = null;
        }
    }

    getDragAfterElement(y) {
        const gallery = document.getElementById('image-gallery');
        const draggableElements = [...gallery.querySelectorAll('.image-item:not(.opacity-50)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    updateImageOrder() {
        const gallery = document.getElementById('image-gallery');
        const imageItems = gallery.querySelectorAll('.image-item');
        
        imageItems.forEach((item, index) => {
            const orderNumber = item.querySelector('.absolute.bottom-2.left-2');
            if (orderNumber) {
                orderNumber.textContent = index + 1;
            }
        });
        
        this.updateImagesInput();
    }

    async handleFileSelect(e) {
        const files = Array.from(e.target.files).filter(file => file.type.startsWith('image/'));
        if (files.length > 0) {
            await this.uploadFiles(files);
        }
        // Clear the input so the same file can be selected again
        e.target.value = '';
    }

    async uploadFiles(files) {
        const progressContainer = document.getElementById('upload-progress');
        const progressBar = document.getElementById('progress-bar');
        const statusText = document.getElementById('upload-status');
        
        progressContainer.classList.remove('hidden');
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const progress = ((i + 1) / files.length) * 100;
            
            progressBar.style.width = progress + '%';
            statusText.textContent = `Uploading ${i + 1} of ${files.length}...`;
            
            try {
                const imageUrl = await this.uploadSingleFile(file);
                this.addImageToGallery(imageUrl);
            } catch (error) {
                console.error('Upload failed:', error);
                if (window.modalManager) {
                    window.modalManager.alert({
                        title: 'Upload Failed',
                        message: `Failed to upload ${file.name}: ${error.message}`,
                        type: 'error'
                    });
                } else {
                    alert(`Failed to upload ${file.name}: ${error.message}`);
                }
            }
        }
        
        progressContainer.classList.add('hidden');
        this.updateImagesInput();
        // Clear file input
        document.getElementById('image-input').value = '';
    }

    async uploadSingleFile(file) {
        // Generate upload token
        const tokenResponse = await fetch(this.config.adminPath + '/api/upload-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filename: file.name,
                contentType: file.type,
                maxSizeBytes: 5242880 // 5MB
            })
        });

        if (!tokenResponse.ok) {
            const errorText = await tokenResponse.text();
            let errorMessage = 'Failed to generate upload token';
            try {
                const error = JSON.parse(errorText);
                errorMessage = error.error || errorMessage;
            } catch (e) {
                errorMessage = errorText || errorMessage;
            }
            throw new Error(errorMessage);
        }

        const tokenData = await tokenResponse.json();

        // Upload file
        const formData = new FormData();
        formData.append('image', file);
        formData.append('uploadToken', tokenData.data.uploadToken);

        const uploadResponse = await fetch(this.config.adminPath + '/api/upload-image', {
            method: 'POST',
            body: formData
        });

        if (!uploadResponse.ok) {
            const errorText = await uploadResponse.text();
            let errorMessage = 'API endpoint not found';
            try {
                const error = JSON.parse(errorText);
                errorMessage = error.error || errorMessage;
            } catch (e) {
                errorMessage = errorText || errorMessage;
            }
            throw new Error(errorMessage);
        }

        const uploadData = await uploadResponse.json();
        return uploadData.data.imageUrl;
    }


    addImageToGallery(imageUrl) {
        const gallery = document.getElementById('image-gallery');
        const imageCount = gallery.querySelectorAll('.image-item').length;
        
        const imageDiv = document.createElement('div');
        imageDiv.className = 'image-item relative group cursor-move';
        imageDiv.setAttribute('data-url', imageUrl);
        imageDiv.setAttribute('draggable', 'true');
        
        imageDiv.innerHTML = `
            <img src="${imageUrl}" alt="Product image" class="w-full h-32 object-cover rounded-lg border border-gray-200 group-hover:border-blue-400 transition-colors">
            
            <!-- Drag Handle -->
            <div class="absolute top-2 left-2 bg-black bg-opacity-50 text-white rounded p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-grip-vertical text-xs"></i>
            </div>
            
            <!-- Order Number -->
            <div class="absolute bottom-2 left-2 bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">
                ${imageCount + 1}
            </div>
            
            <!-- Remove Button -->
            <button type="button" onclick="productForm.removeImage(this)" class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-700 opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        gallery.appendChild(imageDiv);
    }

    removeImage(button) {
        const imageItem = button.closest('.image-item');
        imageItem.remove();
        this.updateImageOrder();
    }

    updateImagesInput() {
        const gallery = document.getElementById('image-gallery');
        const imageItems = gallery.querySelectorAll('.image-item');
        const imageUrls = [];
        
        imageItems.forEach(item => {
            const imageUrl = item.getAttribute('data-url');
            if (imageUrl) {
                imageUrls.push(imageUrl);
            }
        });
        
        document.getElementById('images-data').value = JSON.stringify(imageUrls);
    }

    // ==================== VARIANT MANAGEMENT ====================

    initializeVariants() {
        document.getElementById('add-variant-btn').addEventListener('click', () => this.addVariant());
        
        // Add event listeners to existing variant inputs
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('variant-sku') || 
                e.target.classList.contains('variant-price') ||
                e.target.classList.contains('variant-cost') ||
                e.target.classList.contains('variant-rrp') ||
                e.target.classList.contains('variant-name') ||
                e.target.classList.contains('attribute-key') ||
                e.target.classList.contains('attribute-value')) {
                this.updateVariantsInput();
            }
        });
        
        // Setup VAT calculation for existing variants
        const existingVariants = document.querySelectorAll('.variant-item');
        existingVariants.forEach(variant => this.setupVatCalculation(variant));
    }

    setupVatCalculation(variantElement) {
        const priceExVat = variantElement.querySelector('.variant-price');
        const priceIncVat = variantElement.querySelector('.variant-price-vat');
        const rrpExVat = variantElement.querySelector('.variant-rrp');
        const rrpIncVat = variantElement.querySelector('.variant-rrp-vat');
        
        // Get VAT rate from config (default to 20% UK VAT)
        const vatRate = (window.MiaStoreConfig && window.MiaStoreConfig.vatRate) || 20;
        const vatMultiplier = 1 + (vatRate / 100);
        
        // Helper function to validate and format decimal input
        const validateDecimalInput = (input) => {
            input.addEventListener('input', (e) => {
                // Allow only numbers, decimal point, and basic editing
                let value = e.target.value;
                // Remove any non-numeric characters except decimal point
                value = value.replace(/[^\d.]/g, '');
                // Ensure only one decimal point
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts[0] + '.' + parts.slice(1).join('');
                }
                e.target.value = value;
            });
        };
        
        // Calculate inc-VAT when ex-VAT changes
        if (priceExVat && priceIncVat) {
            // Add input validation for inc-VAT field
            validateDecimalInput(priceIncVat);
            
            // Initialize inc-VAT value if ex-VAT has a value
            const currentExVat = parseFloat(priceExVat.value);
            if (!isNaN(currentExVat) && currentExVat > 0) {
                priceIncVat.value = (currentExVat * vatMultiplier).toFixed(2);
            } else {
                priceIncVat.value = '0.00';
            }
            
            priceExVat.addEventListener('input', (e) => {
                const exVat = parseFloat(e.target.value) || 0;
                priceIncVat.value = (exVat * vatMultiplier).toFixed(2);
            });
            
            // Calculate ex-VAT when inc-VAT changes
            priceIncVat.addEventListener('input', (e) => {
                const incVat = parseFloat(e.target.value) || 0;
                priceExVat.value = (incVat / vatMultiplier).toFixed(2);
            });
        }
        
        // Same for RRP
        if (rrpExVat && rrpIncVat) {
            // Add input validation for inc-VAT field
            validateDecimalInput(rrpIncVat);
            
            // Initialize inc-VAT value if ex-VAT has a value
            const currentRrpExVat = parseFloat(rrpExVat.value);
            if (!isNaN(currentRrpExVat) && currentRrpExVat > 0) {
                rrpIncVat.value = (currentRrpExVat * vatMultiplier).toFixed(2);
            } else {
                rrpIncVat.value = '0.00';
            }
            
            rrpExVat.addEventListener('input', (e) => {
                const exVat = parseFloat(e.target.value) || 0;
                rrpIncVat.value = (exVat * vatMultiplier).toFixed(2);
            });
            
            // Calculate ex-VAT when inc-VAT changes
            rrpIncVat.addEventListener('input', (e) => {
                const incVat = parseFloat(e.target.value) || 0;
                rrpExVat.value = (incVat / vatMultiplier).toFixed(2);
            });
        }
    }

    addVariant() {
        const container = document.getElementById('variants-container');
        const variantCount = container.children.length + 1;
        
        const variantDiv = document.createElement('div');
        variantDiv.className = 'variant-item border border-gray-200 rounded-lg p-4';
        
        variantDiv.innerHTML = `
            <!-- No UUID for new variants -->
            <input type="hidden" class="variant-uuid" value="">
            <div class="flex items-center justify-between mb-4">
                <h5 class="font-medium text-gray-900">Variant ${variantCount}</h5>
                <button type="button" onclick="productForm.removeVariant(this)" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" class="variant-name w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Large Red">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SKU <span class="text-red-500">*</span></label>
                    <input type="text" class="variant-sku w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter unique SKU" required>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <!-- Selling Price with VAT -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selling Price <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Ex-VAT (£)</label>
                            <input type="number" class="variant-price w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Inc-VAT (£)</label>
                            <input type="text" class="variant-price-vat w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50" placeholder="0.00" value="0.00" inputmode="decimal">
                        </div>
                    </div>
                </div>
                
                <!-- Cost Price (no VAT) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cost Price <span class="text-red-500">*</span></label>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Your Cost (£)</label>
                        <input type="number" class="variant-cost w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" step="0.01" min="0" placeholder="0.00" required>
                    </div>
                </div>
                
                <!-- RRP with VAT -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">RRP <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Ex-VAT (£)</label>
                            <input type="number" class="variant-rrp w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Inc-VAT (£)</label>
                            <input type="text" class="variant-rrp-vat w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50" placeholder="0.00" value="0.00" inputmode="decimal">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Variant Attributes -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Attributes</label>
                <div class="variant-attributes space-y-2">
                    <!-- Attributes will be added dynamically -->
                </div>
                <button type="button" onclick="productForm.addAttribute(this)" class="mt-2 text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-plus mr-1"></i>Add Attribute
                </button>
            </div>
        `;
        
        container.appendChild(variantDiv);
        
        // Setup VAT calculation for the new variant
        this.setupVatCalculation(variantDiv);
        
        this.updateVariantsInput();
    }

    addAttribute(button) {
        const variantItem = button.closest('.variant-item');
        const attributesContainer = variantItem.querySelector('.variant-attributes');
        
        const attributeDiv = document.createElement('div');
        attributeDiv.className = 'flex items-center space-x-2 attribute-row';
        
        attributeDiv.innerHTML = `
            <input type="text" class="attribute-key flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Attribute name (e.g., Size, Color)">
            <input type="text" class="attribute-value flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Attribute value (e.g., Large, Red)">
            <button type="button" onclick="productForm.removeAttribute(this)" class="text-red-600 hover:text-red-800 px-2">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        attributesContainer.appendChild(attributeDiv);
        this.updateVariantsInput();
    }

    removeAttribute(button) {
        const attributeRow = button.closest('.attribute-row');
        attributeRow.remove();
        this.updateVariantsInput();
    }

    removeVariant(button) {
        const variantItem = button.closest('.variant-item');
        const container = document.getElementById('variants-container');
        
        // Don't allow removing the last variant
        if (container.children.length <= 1) {
            if (window.modalManager) {
                window.modalManager.alert({
                    title: 'Cannot Remove Variant',
                    message: 'A product must have at least one variant',
                    type: 'warning'
                });
            } else {
                alert('A product must have at least one variant');
            }
            return;
        }
        
        variantItem.remove();
        
        // Renumber variants
        const variantItems = container.querySelectorAll('.variant-item');
        variantItems.forEach((item, index) => {
            item.querySelector('h5').textContent = `Variant ${index + 1}`;
        });
        
        this.updateVariantsInput();
    }

    collectVariantsData() {
        const container = document.getElementById('variants-container');
        const variants = [];
        
        const variantItems = container.querySelectorAll('.variant-item');
        variantItems.forEach((item, index) => {
            const uuid = item.querySelector('.variant-uuid').value.trim();
            const sku = item.querySelector('.variant-sku').value.trim();
            const priceInput = item.querySelector('.variant-price');
            const costInput = item.querySelector('.variant-cost');
            const rrpInput = item.querySelector('.variant-rrp');
            const name = item.querySelector('.variant-name').value.trim();
            
            const price = priceInput ? parseFloat(priceInput.value) || 0 : 0;
            const cost = costInput ? parseFloat(costInput.value) || 0 : 0;
            const rrp = rrpInput ? parseFloat(rrpInput.value) || 0 : 0;
            
            // Collect attributes
            const attributes = {};
            const attributeRows = item.querySelectorAll('.attribute-row');
            attributeRows.forEach(row => {
                const key = row.querySelector('.attribute-key').value.trim();
                const value = row.querySelector('.attribute-value').value.trim();
                if (key && value) {
                    attributes[key] = value;
                }
            });
            
            if (sku && price > 0 && cost > 0 && rrp > 0) {
                const variant = {
                    sku: sku,
                    price: price,
                    cost: cost,
                    rrp: rrp,
                    attributes: attributes
                };
                
                // Add UUID if it exists (for existing variants)
                if (uuid) {
                    variant.uuid = uuid;
                }
                
                // Add presentable name if provided
                if (name) {
                    variant.presentableName = name;
                }
                
                variants.push(variant);
            } else {
                console.warn(`Variant ${index + 1} (${sku}) skipped - missing required fields: sku=${!!sku}, price=${price}, cost=${cost}, rrp=${rrp}`);
            }
        });
        
        return variants;
    }

    updateVariantsInput() {
        const variantsData = this.collectVariantsData();
        document.getElementById('variants-data').value = JSON.stringify(variantsData);
    }

    // ==================== TOAST UI EDITOR ====================

    initializeToastEditor() {
        // Wait for Toast UI Editor to be loaded
        if (typeof toastui === 'undefined' || !toastui.Editor) {
            setTimeout(() => this.initializeToastEditor(), 500);
            return;
        }
        
        const editorElement = document.getElementById('description-editor');
        const hiddenInput = document.getElementById('description');
        
        if (!editorElement) {
            return;
        }
        
        // Get initial content
        const initialContent = hiddenInput.value || '';
        
        try {
            // Initialize Toast UI Editor with simplified configuration
            this.toastEditor = new toastui.Editor({
                el: editorElement,
                height: '400px',
                initialEditType: 'markdown', // Start in markdown mode only
                previewStyle: 'vertical', // Show preview on the side
                initialValue: initialContent,
                theme: 'default',
                usageStatistics: false,
                hideModeSwitch: true, // Hide the mode switch button to prevent WYSIWYG access
                toolbarItems: [
                    ['heading', 'bold', 'italic', 'strike'],
                    ['hr', 'quote'],
                    ['ul', 'ol', 'task', 'indent', 'outdent'],
                    ['table', 'image', 'link'],
                    ['code', 'codeblock']
                ],
                events: {
                    change: () => {
                        // Update hidden input when content changes
                        if (this.toastEditor && hiddenInput) {
                            hiddenInput.value = this.toastEditor.getMarkdown();
                        }
                    }
                }
            });
            
            // Add custom CSS for better integration
            setTimeout(() => {
                const editorContainer = editorElement.querySelector('.toastui-editor');
                if (editorContainer) {
                    editorContainer.style.border = '1px solid #d1d5db';
                    editorContainer.style.borderRadius = '0.5rem';
                }
                
                // Apply custom markdown styles to the preview pane
                const previewElement = editorElement.querySelector('.toastui-editor-md-preview');
                if (previewElement) {
                    previewElement.classList.add('markdown-content');
                }
                
                // Also apply to WYSIWYG mode content area
                const wysiwygElement = editorElement.querySelector('.toastui-editor-contents');
                if (wysiwygElement) {
                    wysiwygElement.classList.add('markdown-content');
                }
            }, 100);
            
        } catch (error) {
            console.error('Failed to initialize Toast UI Editor:', error);
        }
    }

    // Update form submission to get content from Toast editor
    updateFormSubmissionForEditor() {
        const form = document.querySelector('form');
        
        form.addEventListener('submit', () => {
            // Update hidden input with current editor content
            if (this.toastEditor) {
                document.getElementById('description').value = this.toastEditor.getMarkdown();
            }
        });
    }

    // ==================== FORM SUBMISSION ====================

    initializeFormSubmission() {
        document.querySelector('form').addEventListener('submit', (e) => {
            // Validate title
            const title = document.getElementById('title').value.trim();
            if (!title) {
                e.preventDefault();
                if (window.modalManager) {
                    window.modalManager.alert({
                        title: 'Validation Error',
                        message: 'Product title is required',
                        type: 'error'
                    });
                } else {
                    alert('Product title is required');
                }
                document.getElementById('title').focus();
                return;
            }
            
            // Collect and validate variants
            const variantsData = this.collectVariantsData();
            if (variantsData.length === 0) {
                e.preventDefault();
                if (window.modalManager) {
                    window.modalManager.alert({
                        title: 'Validation Error',
                        message: 'At least one variant with SKU and price is required',
                        type: 'error'
                    });
                } else {
                    alert('At least one variant with SKU and price is required');
                }
                return;
            }
            
            // Update hidden inputs
            document.getElementById('variants-data').value = JSON.stringify(variantsData);
            this.updateImagesInput();
        });
    }
}

// Global instance variable
let productForm = null;

/**
 * Initialize the product form with configuration
 * @param {Object} config - Configuration object containing form data and settings
 */
function initializeProductForm(config) {
    productForm = new ProductForm(config);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal manager
    if (typeof ModalManager !== 'undefined') {
        window.modalManager = new ModalManager();
    }
    
    // Initialize product form if configuration is available
    if (window.MiaStoreConfig && window.MiaStoreConfig.productFormData) {
        const config = {
            adminPath: window.MiaStoreConfig.adminPath,
            isEdit: window.MiaStoreConfig.productFormData.isEdit,
            vatRate: window.MiaStoreConfig.vatRate,
            variants: window.MiaStoreConfig.productFormData.variants || []
        };
        
        initializeProductForm(config);
    }
});
