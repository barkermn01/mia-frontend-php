/**
 * Admin Site Admins JavaScript
 * Handles site admin management functionality including delete modal
 */

class AdminSiteAdmins {
    constructor(config) {
        this.config = config;
        this.currentAdminId = null;
        this.currentAdminName = null;
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
            <div id="delete-admin-modal" class="fixed inset-0 hidden">
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
                                Are you sure you want to delete the site admin <strong id="admin-name-placeholder"></strong>? 
                                This action cannot be undone and will permanently remove:
                            </p>
                            <ul class="text-sm text-gray-600 mb-4 pl-4">
                                <li class="flex items-center mb-1">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    The admin user account
                                </li>
                                <li class="flex items-center mb-1">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    All associated permissions
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-dot-circle text-red-400 mr-2 text-xs"></i>
                                    Access to the admin panel
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
                                <i class="fas fa-trash mr-2"></i>Delete Admin
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
            const deleteBtn = e.target.closest('[data-admin-id]');
            if (deleteBtn && deleteBtn.classList.contains('delete-admin-btn')) {
                e.preventDefault();
                const adminId = deleteBtn.dataset.adminId;
                const adminName = deleteBtn.dataset.adminName;
                this.deleteAdmin(adminId, adminName);
            }
        });

        // Bind modal events
        const modal = document.getElementById('delete-admin-modal');
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

    deleteAdmin(adminId, adminName) {
        this.currentAdminId = adminId;
        this.currentAdminName = adminName;
        
        // Update the admin name in the modal
        const namePlaceholder = document.getElementById('admin-name-placeholder');
        if (namePlaceholder) {
            namePlaceholder.textContent = adminName;
        }
        
        this.openModal();
    }

    openModal() {
        const modal = document.getElementById('delete-admin-modal');
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
        const modal = document.getElementById('delete-admin-modal');
        if (modal) {
            this.modalManager.hideModal(modal);
            this.currentAdminId = null;
            this.currentAdminName = null;
        }
    }

    isModalOpen() {
        const modal = document.getElementById('delete-admin-modal');
        return modal && this.modalManager.isModalVisible(modal);
    }

    async confirmDelete() {
        if (!this.currentAdminId) return;

        const confirmBtn = document.querySelector('.modal-confirm');
        const originalContent = confirmBtn.innerHTML;
        
        // Show loading state
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
        confirmBtn.disabled = true;

        try {
            const response = await fetch(`${this.config.adminPath}/api/site-admins/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ adminId: this.currentAdminId })
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
                throw new Error(data.error || 'Failed to delete admin');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showErrorMessage('Failed to delete admin: ' + error.message);
            
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
                    <div class="text-lg font-medium text-gray-900 mb-2">Admin Deleted Successfully</div>
                    <div class="text-sm text-gray-600">Refreshing admin list...</div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
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
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get config from global variable set by PHP
    const config = window.AdminSiteAdminsConfig || { adminPath: '/admin' };
    new AdminSiteAdmins(config);
});