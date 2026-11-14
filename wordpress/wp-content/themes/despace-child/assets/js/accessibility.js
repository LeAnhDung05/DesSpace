/**
 * Furniva Accessibility Enhancement
 * - ARIA labels for icons
 * - Focus states
 * - Keyboard navigation
 * - Screen reader announcements
 */

(function() {
    'use strict';

    /* ============================================================
       1. Add ARIA Labels to Icon-Only Buttons
       ============================================================ */

    function addIconLabels() {
        // Navigation icons
        const iconButtons = {
            // Cart buttons
            '.f-cart-btn svg': 'View shopping cart',
            '.f-cart-js svg': 'View shopping cart',
            '.ax-btn-cart svg': 'Add to shopping cart',

            // Wishlist buttons
            '.f-card-wish-btn svg': 'Add to wishlist',
            '.ax-wishlist-btn svg': 'Add to wishlist',
            '.f-pdp-wish-btn svg': 'Add to wishlist',

            // Social icons
            '.fv-social-icon svg': 'Share on social media',
            '.f-social-icon svg': 'Share on social media',

            // Navigation
            '.fv-burger svg': 'Open menu',
            '#furniva-burger svg': 'Open navigation menu',

            // Close buttons
            '.f-drawer-close svg': 'Close drawer',
            '.ax-drawer-close svg': 'Close panel',
            '.furniva-drawer-close svg': 'Close panel',

            // Search
            '.fv-search-glyph': 'Search',
            '.fv-concierge__search button': 'Submit search',

            // Product actions
            '.f-quick-view-btn svg': 'Quick view product',
            '.ax-gallery-zoom svg': 'Zoom image',
            '.ax-gallery-nav svg': 'Navigate gallery',
        };

        // Wishlist button labels
        document.querySelectorAll('.f-card-wish-btn, .ax-wishlist-btn').forEach(btn => {
            const svg = btn.querySelector('svg');
            if (svg && !btn.getAttribute('aria-label')) {
                const isAdded = btn.classList.contains('added') || btn.getAttribute('aria-label')?.includes('Remove');
                btn.setAttribute('aria-label', isAdded ? 'Remove from wishlist' : 'Add to wishlist');
            }
        });

        // Filter buttons
        document.querySelectorAll('.f-filter-btn, .fv-filter-toggle').forEach(btn => {
            if (!btn.getAttribute('aria-label')) {
                btn.setAttribute('aria-label', 'Open filters');
            }
        });

        // Sort buttons
        document.querySelectorAll('.f-sort-btn, .woocommerce-ordering select').forEach(el => {
            if (el.tagName === 'SELECT' && !el.getAttribute('aria-label')) {
                el.setAttribute('aria-label', 'Sort products');
            }
        });
    }

    /* ============================================================
       2. Color Swatch Accessibility
       ============================================================ */

    function enhanceColorSwatches() {
        document.querySelectorAll('.ax-color-swatch, [data-color]').forEach(swatch => {
            // Add aria-label from title if missing
            if (!swatch.getAttribute('aria-label')) {
                const title = swatch.getAttribute('title') || swatch.dataset.color || '';
                swatch.setAttribute('aria-label', 'Color: ' + title);
            }

            // Add role="radio" for radio group behavior
            const group = swatch.closest('.ax-color-swatches, [role="radiogroup"]');
            if (group) {
                if (!group.getAttribute('role')) {
                    group.setAttribute('role', 'radiogroup');
                    group.setAttribute('aria-label', 'Color options');
                }
                swatch.setAttribute('role', 'radio');
                swatch.setAttribute('tabindex', swatch.classList.contains('active') ? '0' : '-1');
            }
        });

        // Handle keyboard navigation for color swatches
        document.querySelectorAll('.ax-color-swatches, [role="radiogroup"]').forEach(group => {
            group.addEventListener('keydown', function(e) {
                const swatches = Array.from(this.querySelectorAll('[role="radio"]'));
                const currentIndex = swatches.findIndex(s => s === document.activeElement);

                if (currentIndex === -1) return;

                let newIndex = currentIndex;

                switch(e.key) {
                    case 'ArrowRight':
                    case 'ArrowDown':
                        newIndex = (currentIndex + 1) % swatches.length;
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                        newIndex = (currentIndex - 1 + swatches.length) % swatches.length;
                        break;
                    case 'Home':
                        newIndex = 0;
                        break;
                    case 'End':
                        newIndex = swatches.length - 1;
                        break;
                    default:
                        return;
                }

                e.preventDefault();
                swatches[newIndex].click();
                swatches[newIndex].focus();
            });
        });
    }

    /* ============================================================
       3. Enhanced Focus States
       ============================================================ */

    function enhanceFocusStyles() {
        // Add focus-visible styles for keyboard navigation
        const style = document.createElement('style');
        style.textContent = `
            /* Skip to content link */
            a.skip-link,
            .skip-link {
                position: fixed !important;
                top: -120px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                background: #191816;
                color: white;
                padding: 10px 20px;
                z-index: 999999 !important;
                transition: top 0.3s;
                text-decoration: none !important;
                font-weight: 600;
                display: inline-block !important;
                height: auto !important;
                margin: 0 !important;
                line-height: normal !important;
            }
            a.skip-link:focus,
            .skip-link:focus {
                top: 12px !important;
            }

            /* Enhanced focus for keyboard users */
            :focus {
                outline: none;
            }

            :focus-visible {
                outline: 2px solid #c5a880;
                outline-offset: 2px;
            }

            /* Focus for interactive elements */
            button:focus-visible,
            a:focus-visible,
            input:focus-visible,
            select:focus-visible,
            textarea:focus-visible,
            [tabindex]:focus-visible {
                outline: 2px solid #c5a880;
                outline-offset: 2px;
            }

            /* Focus for form elements */
            input:focus-visible,
            select:focus-visible,
            textarea:focus-visible {
                border-color: #c5a880 !important;
                box-shadow: 0 0 0 3px rgba(197, 168, 128, 0.2) !important;
            }

            /* Focus for buttons */
            button:focus-visible,
            .f-btn:focus-visible,
            .ax-btn:focus-visible {
                box-shadow: 0 0 0 3px rgba(197, 168, 128, 0.3);
            }

            /* Focus for links */
            a:focus-visible {
                text-decoration: underline;
            }

            /* Focus for checkboxes/radios */
            input[type="checkbox"]:focus-visible,
            input[type="radio"]:focus-visible {
                box-shadow: 0 0 0 3px rgba(197, 168, 128, 0.3);
            }

            /* Color swatch focus */
            .ax-color-swatch:focus-visible,
            [role="radio"]:focus-visible {
                outline-offset: 3px;
            }

            /* Product card focus */
            .f-product-card:focus-within,
            .ax-product-card:focus-within {
                box-shadow: 0 0 0 3px rgba(197, 168, 128, 0.3);
            }
        `;
        document.head.appendChild(style);
    }

    /* ============================================================
       4. Skip Navigation Link
       ============================================================ */

    function addSkipLink() {
        if (document.querySelector('.skip-link')) return;
        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'skip-link no-underline';
        skipLink.style.position = 'fixed';
        skipLink.style.top = '-120px';
        skipLink.textContent = 'Skip to main content';
        document.body.insertBefore(skipLink, document.body.firstChild);
    }

    /* ============================================================
       5. Live Region for Dynamic Content
       ============================================================ */

    function addLiveRegions() {
        // Cart updates
        const cartLive = document.createElement('div');
        cartLive.setAttribute('role', 'status');
        cartLive.setAttribute('aria-live', 'polite');
        cartLive.setAttribute('aria-atomic', 'true');
        cartLive.className = 'sr-only';
        cartLive.id = 'cart-live-region';
        document.body.appendChild(cartLive);

        // Filter updates
        const filterLive = document.createElement('div');
        filterLive.setAttribute('role', 'status');
        filterLive.setAttribute('aria-live', 'polite');
        filterLive.className = 'sr-only';
        filterLive.id = 'filter-live-region';
        document.body.appendChild(filterLive);
    }

    /* ============================================================
       6. Keyboard Navigation Enhancements
       ============================================================ */

    function enhanceKeyboardNav() {
        // Escape key closes modals/drawers
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close mini cart
                const cart = document.querySelector('.f-cart-drawer.is-open, .ax-mini-cart.is-open');
                if (cart) {
                    cart.classList.remove('is-open');
                    const overlay = document.querySelector('.f-cart-drawer__overlay.is-open, .ax-mini-cart-overlay.is-open');
                    if (overlay) overlay.classList.remove('is-open');
                    document.body.classList.remove('drawer-open');
                }

                // Close filter drawer
                const filter = document.querySelector('.f-filter-drawer.is-open, .ax-filter-drawer.is-open');
                if (filter) {
                    filter.classList.remove('is-open');
                }

                // Close chat panel
                const chat = document.querySelector('.ax-chat-panel:not([hidden])');
                if (chat) {
                    chat.hidden = true;
                    chat.classList.remove('is-open');
                    const toggle = document.getElementById('ax-chat-toggle');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });

        // Enter/Space activates buttons
        document.querySelectorAll('[role="button"]').forEach(btn => {
            btn.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // Tab trap in modals
        document.querySelectorAll('.ax-drawer, .f-cart-drawer, .fancybox-modal').forEach(modal => {
            modal.addEventListener('keydown', function(e) {
                if (e.key !== 'Tab') return;

                const focusable = this.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
        });
    }

    /* ============================================================
       7. Announce to Screen Readers
       ============================================================ */

    function announce(message, region = 'cart-live-region') {
        const liveRegion = document.getElementById(region);
        if (liveRegion) {
            liveRegion.textContent = '';
            setTimeout(() => {
                liveRegion.textContent = message;
            }, 100);
        }
    }

    // Make announce globally available
    window.furnivaAnnounce = announce;

    /* ============================================================
       8. Reduced Motion Detection
       ============================================================ */

    function handleReducedMotion() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        function handleChange() {
            document.documentElement.classList.toggle('reduced-motion', prefersReducedMotion.matches);
        }

        handleChange();
        prefersReducedMotion.addEventListener('change', handleChange);
    }

    /* ============================================================
       9. Image Alt Text Validation
       ============================================================ */

    function validateImageAlt() {
        const images = document.querySelectorAll('img:not([alt])');
        if (images.length > 0) {
            console.warn('Furniva A11y: Found ' + images.length + ' images without alt text');
            images.forEach(img => {
                img.setAttribute('alt', img.getAttribute('title') || 'Image');
            });
        }
    }

    /* ============================================================
       Initialize Accessibility
       ============================================================ */

    function init() {
        addSkipLink();
        addLiveRegions();
        addIconLabels();
        enhanceColorSwatches();
        enhanceFocusStyles();
        enhanceKeyboardNav();
        handleReducedMotion();
        validateImageAlt();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
