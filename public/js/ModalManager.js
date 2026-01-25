/**
 * Modal Manager
 * Handles z-index stacking and modal lifecycle management
 */

class ModalManager {
    constructor() {
        this.baseZIndex = 1000;
        this.modalStack = [];
        this.currentZIndex = this.baseZIndex;
    }

    /**
     * Show a modal and manage its z-index
     * @param {HTMLElement} modal - The modal element to show
     */
    showModal(modal) {
        if (!modal) return;

        // Remove from stack if already present
        const index = this.modalStack.indexOf(modal);
        if (index > -1) {
            this.modalStack.splice(index, 1);
        }

        // Add to stack
        this.modalStack.push(modal);
        this.currentZIndex += 10;

        // Apply z-index and show
        modal.style.zIndex = this.currentZIndex;
        modal.classList.remove('hidden');

        // Add escape key listener for this modal
        this.addEscapeListener(modal);

        // Prevent body scroll
        this.preventBodyScroll();
    }

    /**
     * Hide a modal and update z-index stack
     * @param {HTMLElement} modal - The modal element to hide
     */
    hideModal(modal) {
        if (!modal) return;

        // Remove from stack
        const index = this.modalStack.indexOf(modal);
        if (index > -1) {
            this.modalStack.splice(index, 1);
        }

        // Hide modal
        modal.classList.add('hidden');
        modal.style.zIndex = '';

        // Remove escape listener
        this.removeEscapeListener(modal);

        // Restore body scroll if no modals are open
        if (this.modalStack.length === 0) {
            this.restoreBodyScroll();
            this.currentZIndex = this.baseZIndex;
        }
    }

    /**
     * Check if a modal is currently visible
     * @param {HTMLElement} modal - The modal element to check
     * @returns {boolean}
     */
    isModalVisible(modal) {
        return modal && !modal.classList.contains('hidden');
    }

    /**
     * Get the currently active (top) modal
     * @returns {HTMLElement|null}
     */
    getActiveModal() {
        return this.modalStack.length > 0 ? this.modalStack[this.modalStack.length - 1] : null;
    }

    /**
     * Close the top modal
     */
    closeTopModal() {
        const activeModal = this.getActiveModal();
        if (activeModal) {
            this.hideModal(activeModal);
        }
    }

    /**
     * Close all modals
     */
    closeAllModals() {
        // Create a copy of the stack to avoid modification during iteration
        const modalsToClose = [...this.modalStack];
        modalsToClose.forEach(modal => this.hideModal(modal));
    }

    /**
     * Add escape key listener for a modal
     * @param {HTMLElement} modal - The modal element
     */
    addEscapeListener(modal) {
        const escapeHandler = (e) => {
            if (e.key === 'Escape' && this.getActiveModal() === modal) {
                this.hideModal(modal);
            }
        };

        // Store the handler on the modal for later removal
        modal._escapeHandler = escapeHandler;
        document.addEventListener('keydown', escapeHandler);
    }

    /**
     * Remove escape key listener for a modal
     * @param {HTMLElement} modal - The modal element
     */
    removeEscapeListener(modal) {
        if (modal._escapeHandler) {
            document.removeEventListener('keydown', modal._escapeHandler);
            delete modal._escapeHandler;
        }
    }

    /**
     * Prevent body scroll when modals are open
     */
    preventBodyScroll() {
        if (!document.body.classList.contains('modal-open')) {
            // Store current scroll position
            this.scrollPosition = window.pageYOffset;
            document.body.style.top = `-${this.scrollPosition}px`;
            document.body.classList.add('modal-open');
        }
    }

    /**
     * Restore body scroll when all modals are closed
     */
    restoreBodyScroll() {
        if (document.body.classList.contains('modal-open')) {
            document.body.classList.remove('modal-open');
            document.body.style.top = '';
            window.scrollTo(0, this.scrollPosition || 0);
        }
    }

    /**
     * Show a confirmation dialog
     * @param {Object} options - Dialog options
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Dialog message
     * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {string} options.confirmClass - Confirm button class ('primary' or 'danger', default: 'primary')
     * @param {Function} options.onConfirm - Callback when confirmed
     * @param {Function} options.onCancel - Callback when cancelled
     */
    confirm(options) {
        const {
            title = 'Confirm',
            message = 'Are you sure?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            confirmClass = 'primary',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;

        // Create modal HTML
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">${title}</h3>
                <p class="text-gray-600 mb-6">${message}</p>
                <div class="flex justify-end space-x-3">
                    <button class="cancel-btn px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        ${cancelText}
                    </button>
                    <button class="confirm-btn px-4 py-2 rounded-lg text-white transition-colors ${
                        confirmClass === 'danger' 
                            ? 'bg-red-600 hover:bg-red-700' 
                            : 'bg-blue-600 hover:bg-blue-700'
                    }">
                        ${confirmText}
                    </button>
                </div>
            </div>
        `;

        // Add to document
        document.body.appendChild(modal);

        // Get buttons
        const confirmBtn = modal.querySelector('.confirm-btn');
        const cancelBtn = modal.querySelector('.cancel-btn');

        // Handle confirm
        confirmBtn.addEventListener('click', () => {
            this.hideModal(modal);
            modal.remove();
            onConfirm();
        });

        // Handle cancel
        cancelBtn.addEventListener('click', () => {
            this.hideModal(modal);
            modal.remove();
            onCancel();
        });

        // Show modal
        this.showModal(modal);
    }

    /**
     * Show an alert dialog
     * @param {Object} options - Dialog options
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Dialog message
     * @param {string} options.type - Alert type ('info', 'success', 'warning', 'error', default: 'info')
     * @param {string} options.buttonText - Button text (default: 'OK')
     * @param {Function} options.onClose - Callback when closed
     */
    alert(options) {
        const {
            title = 'Alert',
            message = '',
            type = 'info',
            buttonText = 'OK',
            onClose = () => {}
        } = options;

        // Determine icon and color based on type
        let icon = '';
        let buttonClass = 'bg-blue-600 hover:bg-blue-700';
        
        switch (type) {
            case 'success':
                icon = '<i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>';
                buttonClass = 'bg-green-600 hover:bg-green-700';
                break;
            case 'warning':
                icon = '<i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>';
                buttonClass = 'bg-yellow-600 hover:bg-yellow-700';
                break;
            case 'error':
                icon = '<i class="fas fa-times-circle text-red-500 text-3xl mb-3"></i>';
                buttonClass = 'bg-red-600 hover:bg-red-700';
                break;
            case 'info':
            default:
                icon = '<i class="fas fa-info-circle text-blue-500 text-3xl mb-3"></i>';
                buttonClass = 'bg-blue-600 hover:bg-blue-700';
                break;
        }

        // Create modal HTML
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="text-center">
                    ${icon}
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">${title}</h3>
                    <p class="text-gray-600 mb-6">${message}</p>
                    <button class="ok-btn px-6 py-2 rounded-lg text-white transition-colors ${buttonClass}">
                        ${buttonText}
                    </button>
                </div>
            </div>
        `;

        // Add to document
        document.body.appendChild(modal);

        // Get button
        const okBtn = modal.querySelector('.ok-btn');

        // Handle close
        okBtn.addEventListener('click', () => {
            this.hideModal(modal);
            modal.remove();
            onClose();
        });

        // Show modal
        this.showModal(modal);
    }
}

// Add CSS for modal-open class if not already present
if (!document.getElementById('modal-manager-styles')) {
    const style = document.createElement('style');
    style.id = 'modal-manager-styles';
    style.textContent = `
        body.modal-open {
            position: fixed;
            width: 100%;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);
}