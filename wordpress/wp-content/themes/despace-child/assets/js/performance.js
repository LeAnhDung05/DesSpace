/**
 * DeSpace Performance Optimization
 * - Lazy loading images
 * - WebP detection and fallback
 * - Image preload for LCP
 */

(function() {
    'use strict';

    /* ============================================================
       1. Native Lazy Loading Enhancement
       ============================================================ */

    function initNativeLazyLoad() {
        // Add loading="lazy" to all images below the fold
        const images = document.querySelectorAll('img:not([loading])');

        // Only add lazy to images that aren't in the first viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (!img.hasAttribute('loading')) {
                        img.setAttribute('loading', 'lazy');
                    }
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: '100px' });

        // Add to images without lazy loading
        images.forEach(img => {
            if (!img.hasAttribute('loading') &&
                !img.classList.contains('wp-post-image') &&
                !img.hasAttribute('fetchpriority')) {
                observer.observe(img);
            }
        });
    }

    /* ============================================================
       2. LCP (Largest Contentful Paint) Optimization
       ============================================================ */

    function preloadLCPImage() {
        // Preload the hero/first product image
        const lcpImage = document.querySelector(
            '.ax-gallery-main img, ' +
            '.ax-product-gallery img, ' +
            '.fv-hero img, ' +
            '.f-product-gallery img, ' +
            'article.product img, ' +
            '.f-product-card img'
        );

        if (lcpImage && lcpImage.src) {
            const preload = document.createElement('link');
            preload.rel = 'preload';
            preload.as = 'image';
            preload.href = lcpImage.src;
            preload.fetchPriority = 'high';
            document.head.appendChild(preload);
        }
    }

    /* ============================================================
       3. WebP Detection and Fallback
       ============================================================ */

    function detectWebPSupport() {
        const webpData = 'data:image/webp;base64,UklGRiQAAABXRUJQVlA4IBQAAAAwAQCdASoCAAEADQOQO3kIHQKAApA3pAF7JsIAAAA';

        function checkWebP(callback) {
            const img = new Image();
            img.onload = function() {
                callback(img.width === 1);
            };
            img.onerror = function() {
                callback(false);
            };
            img.src = webpData;
        }

        checkWebP(function(support) {
            document.documentElement.classList.add(support ? 'webp-supported' : 'webp-unsupported');
        });
    }

    /* ============================================================
       4. Decode Images Asynchronously
       ============================================================ */

    function addImageDecoding() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            if (!img.hasAttribute('decoding')) {
                img.setAttribute('decoding', 'async');
            }
        });
    }

    /* ============================================================
       5. Responsive Images srcset Enhancement
       ============================================================ */

    function enhanceResponsiveImages() {
        // WordPress and WooCommerce already generate validated srcset attributes
        // based on actual existing attachment metadata sizes. Do not fabricate
        // non-existent -150x150 / -600x600 filenames in JS.
    }

    /* ============================================================
       6. Intersection Observer for Animation Images
       ============================================================ */

    function initAnimationLazyLoad() {
        const animatedImages = document.querySelectorAll('.fv-animate-img');

        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.add('loaded');
                            img.addEventListener('load', () => {
                                img.classList.add('fv-img-loaded');
                            });
                        }
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '50px' });

            animatedImages.forEach(img => {
                imageObserver.observe(img);
            });
        } else {
            // Fallback: load all images immediately
            animatedImages.forEach(img => {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                }
            });
        }
    }

    /* ============================================================
       7. Skeleton Loading Enhancement
       ============================================================ */

    function showSkeletons() {
        const gridContainers = document.querySelectorAll(
            '.f-products, ' +
            '.woocommerce ul.products, ' +
            '.ax-related-grid'
        );

        gridContainers.forEach(container => {
            if (!container.querySelector('.skeleton-container')) {
                const skeletonCount = container.dataset.columns || 4;
                const skeletonHTML = Array.from({length: skeletonCount}, () =>
                    `<div class="f-product-card-skeleton">
                        <div class="skeleton-image"></div>
                        <div class="skeleton-text"></div>
                        <div class="skeleton-text short"></div>
                    </div>`
                ).join('');

                container.insertAdjacentHTML('beforebegin',
                    `<div class="skeleton-container" style="display:none;">${skeletonHTML}</div>`
                );
            }
        });
    }

    /* ============================================================
       Initialize Performance Optimizations
       ============================================================ */

    function init() {
        detectWebPSupport();
        addImageDecoding();

        if (document.readyState === 'complete') {
            preloadLCPImage();
            initNativeLazyLoad();
            initAnimationLazyLoad();
        } else {
            window.addEventListener('load', () => {
                preloadLCPImage();
                initNativeLazyLoad();
                initAnimationLazyLoad();
            });
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
