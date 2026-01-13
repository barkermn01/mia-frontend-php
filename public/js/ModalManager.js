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
        this.hideModal(modal);

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