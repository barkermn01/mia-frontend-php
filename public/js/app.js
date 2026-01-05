/**
 * Mia Store - Main JavaScript Application
 */

// Global app object
window.MiaStore = {
    cartCount: 0,
    isLoggedIn: false,
    
    // Initialize the application
    init(config = {}) {
        this.cartCount = config.cartCount || 0;
        this.isLoggedIn = config.isLoggedIn || false;
        
        // Initialize event listeners
        this.initEventListeners();
    },
    
    // Initialize all event listeners
    initEventListeners() {
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', this.toggleMobileMenu);
        }

        // Cart sidebar functionality
        const cartToggle = document.getElementById('cart-toggle');
        const cartClose = document.getElementById('cart-close');
        const cartOverlay = document.getElementById('cart-overlay');

        if (cartToggle) cartToggle.addEventListener('click', this.openCart);
        if (cartClose) cartClose.addEventListener('click', this.closeCart);
        if (cartOverlay) cartOverlay.addEventListener('click', this.closeCart);
    },
    
    // Mobile menu toggle
    toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    },
    
    // Show toast notification
    showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast bg-${type === 'success' ? 'green' : 'red'}-500 text-white px-6 py-3 rounded-lg shadow-lg`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} mr-2"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        container.appendChild(toast);
        
        // Show toast
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    },
    
    // Update cart count
    updateCartCount(count) {
        this.cartCount = count;
        const countElement = document.getElementById('cart-count');
        if (!countElement) return;
        
        if (count > 0) {
            countElement.textContent = count;
            countElement.classList.remove('hidden');
            countElement.classList.add('cart-animation');
            setTimeout(() => countElement.classList.remove('cart-animation'), 300);
        } else {
            countElement.classList.add('hidden');
        }
    },
    
    // AJAX helper
    async request(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.json();
    },
    
    // Cart sidebar functions
    openCart() {
        const cartSidebar = document.getElementById('cart-sidebar');
        const cartPanel = document.getElementById('cart-panel');
        
        if (cartSidebar && cartPanel) {
            cartSidebar.classList.remove('hidden');
            setTimeout(() => {
                cartPanel.classList.remove('translate-x-full');
            }, 10);
            MiaStore.loadCartContent();
        }
    },
    
    closeCart() {
        const cartSidebar = document.getElementById('cart-sidebar');
        const cartPanel = document.getElementById('cart-panel');
        
        if (cartPanel) {
            cartPanel.classList.add('translate-x-full');
            setTimeout(() => {
                if (cartSidebar) {
                    cartSidebar.classList.add('hidden');
                }
            }, 300);
        }
    },
    
    // Load cart content via AJAX
    async loadCartContent() {
        const cartContent = document.getElementById('cart-content');
        if (!cartContent) return;
        
        try {
            const response = await this.request('/api/cart');
            cartContent.innerHTML = response.html;
        } catch (error) {
            console.error('Failed to load cart:', error);
            cartContent.innerHTML = '<p class="text-red-500">Failed to load cart</p>';
        }
    }
};

// Cart functions (global scope for onclick handlers)
window.addToCart = async function(sku, qty = 1, buttonElement = null) {
    try {
        // Get button element from parameter or event target
        const button = buttonElement || event?.target;
        if (!button) {
            throw new Error('Button element not found');
        }
        
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        button.disabled = true;

        const response = await MiaStore.request('/api/cart/add', {
            method: 'POST',
            body: JSON.stringify({ sku, qty })
        });

        if (response.success) {
            MiaStore.updateCartCount(response.cartCount);
            MiaStore.showToast('Item added to cart!');
        } else {
            MiaStore.showToast(response.message || 'Failed to add item', 'error');
        }
        
        // Reset button state
        button.innerHTML = originalText;
        button.disabled = false;
    } catch (error) {
        console.error('Add to cart error:', error);
        MiaStore.showToast('Failed to add item to cart', 'error');
        
        // Reset button state on error
        const button = buttonElement || event?.target;
        if (button) {
            button.innerHTML = button.innerHTML.replace('<i class="fas fa-spinner fa-spin"></i> Adding...', '<i class="fas fa-cart-plus"></i>');
            button.disabled = false;
        }
    }
};

window.updateCartItem = async function(itemId, qty) {
    try {
        const response = await MiaStore.request('/api/cart/update', {
            method: 'POST',
            body: JSON.stringify({ itemId, qty })
        });

        if (response.success) {
            MiaStore.updateCartCount(response.cartCount);
            MiaStore.loadCartContent(); // Refresh cart display
            MiaStore.showToast('Cart updated!');
        } else {
            MiaStore.showToast(response.message || 'Failed to update cart', 'error');
        }
    } catch (error) {
        console.error('Update cart error:', error);
        MiaStore.showToast('Failed to update cart', 'error');
    }
};

window.removeCartItem = async function(itemId) {
    if (!confirm('Remove this item from cart?')) return;
    
    try {
        const response = await MiaStore.request('/api/cart/remove', {
            method: 'POST',
            body: JSON.stringify({ itemId })
        });

        if (response.success) {
            MiaStore.updateCartCount(response.cartCount);
            MiaStore.loadCartContent(); // Refresh cart display
            MiaStore.showToast('Item removed from cart!');
        } else {
            MiaStore.showToast(response.message || 'Failed to remove item', 'error');
        }
    } catch (error) {
        console.error('Remove cart error:', error);
        MiaStore.showToast('Failed to remove item', 'error');
    }
};

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Configuration will be set by the PHP template
    if (window.MiaStoreConfig) {
        MiaStore.init(window.MiaStoreConfig);
    }
});

// Generic Tooltip System
window.TooltipSystem = {
    activeTooltip: null,
    
    init() {
        // Initialize tooltip event listeners
        document.addEventListener('mouseover', this.handleMouseOver.bind(this));
        document.addEventListener('mouseout', this.handleMouseOut.bind(this));
        document.addEventListener('scroll', this.hideActiveTooltip.bind(this));
        window.addEventListener('resize', this.hideActiveTooltip.bind(this));
    },
    
    handleMouseOver(event) {
        const trigger = event.target.closest('[data-tooltip]');
        if (!trigger) return;
        
        const tooltipSelector = trigger.getAttribute('data-tooltip');
        const tooltip = document.querySelector(tooltipSelector);
        if (!tooltip) return;
        
        this.showTooltip(trigger, tooltip);
    },
    
    handleMouseOut(event) {
        const trigger = event.target.closest('[data-tooltip]');
        if (!trigger) return;
        
        // Check if we're moving to the tooltip itself
        const relatedTarget = event.relatedTarget;
        if (relatedTarget && (relatedTarget === this.activeTooltip || this.activeTooltip?.contains(relatedTarget))) {
            return;
        }
        
        this.hideActiveTooltip();
    },
    
    showTooltip(trigger, tooltip) {
        // Hide any existing tooltip
        this.hideActiveTooltip();
        
        // Position the tooltip relative to the trigger
        this.positionTooltip(trigger, tooltip);
        
        // Show the tooltip
        tooltip.classList.add('show');
        this.activeTooltip = tooltip;
        
        // Add mouseout listener to tooltip itself
        tooltip.addEventListener('mouseleave', () => {
            this.hideActiveTooltip();
        });
    },
    
    positionTooltip(trigger, tooltip) {
        const triggerRect = trigger.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Reset positioning classes
        tooltip.classList.remove('tooltip-top', 'tooltip-bottom', 'tooltip-left', 'tooltip-right');
        
        // Default position: top
        let position = 'top';
        let left = triggerRect.left + (triggerRect.width / 2);
        let top = triggerRect.top - 8; // 8px margin
        
        // Check if tooltip fits above
        if (triggerRect.top < tooltipRect.height + 16) {
            // Not enough space above, try below
            position = 'bottom';
            top = triggerRect.bottom + 8;
        }
        
        // Check if tooltip fits horizontally
        const tooltipWidth = 300; // max-width from CSS
        if (left - (tooltipWidth / 2) < 8) {
            // Too far left, align to left edge
            left = 8 + (tooltipWidth / 2);
        } else if (left + (tooltipWidth / 2) > viewportWidth - 8) {
            // Too far right, align to right edge
            left = viewportWidth - 8 - (tooltipWidth / 2);
        }
        
        // Apply positioning
        tooltip.style.position = 'fixed';
        tooltip.style.left = `${left}px`;
        tooltip.style.transform = 'translateX(-50%)';
        
        if (position === 'top') {
            tooltip.style.top = `${top}px`;
            tooltip.style.transform += ' translateY(-100%)';
            tooltip.classList.add('tooltip-top');
        } else {
            tooltip.style.top = `${top}px`;
            tooltip.classList.add('tooltip-bottom');
        }
    },
    
    hideActiveTooltip() {
        if (this.activeTooltip) {
            this.activeTooltip.classList.remove('show');
            this.activeTooltip = null;
        }
    }
};

// Initialize tooltip system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    TooltipSystem.init();
    
    // Initialize hero carousel
    HeroCarousel.init();
});

// Hero Carousel System
window.HeroCarousel = {
    currentSlide: 0,
    slides: [],
    indicators: [],
    autoPlayInterval: null,
    autoPlayDelay: 4000, // 4 seconds
    
    init() {
        const carousel = document.getElementById('hero-carousel');
        if (!carousel) return;
        
        this.slides = carousel.querySelectorAll('.carousel-slide');
        this.indicators = carousel.querySelectorAll('.carousel-indicator');
        
        if (this.slides.length === 0) return;
        
        // Set up event listeners
        this.setupEventListeners(carousel);
        
        // Start auto-play
        this.startAutoPlay();
        
        // Pause auto-play on hover
        carousel.addEventListener('mouseenter', () => this.stopAutoPlay());
        carousel.addEventListener('mouseleave', () => this.startAutoPlay());
    },
    
    setupEventListeners(carousel) {
        // Navigation buttons
        const prevBtn = carousel.querySelector('.carousel-prev');
        const nextBtn = carousel.querySelector('.carousel-next');
        
        if (prevBtn) prevBtn.addEventListener('click', () => this.prevSlide());
        if (nextBtn) nextBtn.addEventListener('click', () => this.nextSlide());
        
        // Indicator buttons
        this.indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => this.goToSlide(index));
        });
    },
    
    goToSlide(slideIndex) {
        if (slideIndex < 0 || slideIndex >= this.slides.length) return;
        
        // Hide current slide
        this.slides[this.currentSlide].classList.remove('active');
        this.indicators[this.currentSlide].classList.remove('active');
        
        // Show new slide
        this.currentSlide = slideIndex;
        this.slides[this.currentSlide].classList.add('active');
        this.indicators[this.currentSlide].classList.add('active');
    },
    
    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.slides.length;
        this.goToSlide(nextIndex);
    },
    
    prevSlide() {
        const prevIndex = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
        this.goToSlide(prevIndex);
    },
    
    startAutoPlay() {
        this.stopAutoPlay(); // Clear any existing interval
        this.autoPlayInterval = setInterval(() => {
            this.nextSlide();
        }, this.autoPlayDelay);
    },
    
    stopAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }
};