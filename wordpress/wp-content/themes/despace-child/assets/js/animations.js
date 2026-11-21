/**
 * DeSpace Animations JavaScript
 * Handles scroll reveal, page transitions, and enhanced interactions
 */

(function() {
    'use strict';

    /* ============================================================
       1. Page Load Animation
       ============================================================ */

    function initPageLoad() {
        // Add loaded class to body after fonts and critical resources
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    document.body.classList.add('fv-loaded');
                }, 100);
            });
        } else {
            setTimeout(function() {
                document.body.classList.add('fv-loaded');
            }, 100);
        }
    }

    /* ============================================================
       2. Scroll Reveal Observer
       ============================================================ */

    function initScrollReveal() {
        // Check for reduced motion preference
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            // Make all elements visible immediately
            document.querySelectorAll('.fv-reveal, .fv-stagger').forEach(function(el) {
                el.classList.add('fv-visible');
            });
            return;
        }

        // Intersection Observer options - positive bottom margin reveals content smoothly before it enters viewport
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px 50px 0px',
            threshold: 0.05
        };

        // Single IntersectionObserver for both .fv-reveal and .fv-stagger (zero unthrottled scroll/resize reflow)
        if ('IntersectionObserver' in window) {
            const revealObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fv-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fv-reveal, .fv-stagger').forEach(function(el) {
                revealObserver.observe(el);
            });
        } else {
            document.querySelectorAll('.fv-reveal, .fv-stagger').forEach(function(el) {
                el.classList.add('fv-visible');
            });
        }

        // Safety fallback: reveal all elements after 1.5s so content is never stuck hidden
        setTimeout(function() {
            document.querySelectorAll('.fv-reveal:not(.fv-visible), .fv-stagger:not(.fv-visible)').forEach(function(el) {
                el.classList.add('fv-visible');
            });
        }, 1500);
    }

    /* ============================================================
       3. Parallax Effects
       ============================================================ */

    function initParallax() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const parallaxElements = document.querySelectorAll('.fv-parallax');

        if (parallaxElements.length === 0) return;

        let ticking = false;

        function updateParallax() {
            const scrollY = window.pageYOffset;

            parallaxElements.forEach(function(el) {
                const speed = parseFloat(el.dataset.parallaxSpeed) || 0.3;
                const offset = scrollY * speed;
                el.style.transform = 'translateY(' + offset + 'px)';
            });

            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        }, { passive: true });
    }

    /* ============================================================
       4. Counter Animation
       ============================================================ */

    function animateCounter(element, target, duration) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;

        function update() {
            current += increment;
            if (current < target) {
                element.textContent = Math.floor(current);
                requestAnimationFrame(update);
            } else {
                element.textContent = target;
            }
        }

        update();
    }

    function initCounterAnimation() {
        const counters = document.querySelectorAll('.fv-counter');

        if (counters.length === 0) return;

        const counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.dataset.count, 10) || parseInt(el.textContent, 10);
                    el.textContent = '0';
                    animateCounter(el, target, 1500);
                    counterObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(function(counter) {
            counterObserver.observe(counter);
        });
    }

    /* ============================================================
       5. Image Reveal Animation
       ============================================================ */

    function initImageReveal() {
        const images = document.querySelectorAll('.fv-img-reveal');

        images.forEach(function(img) {
            img.parentElement.classList.add('fv-img-wrapper');
        });

        const imgObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }
                    img.addEventListener('load', function() {
                        img.parentElement.classList.add('fv-img-loaded');
                    });
                    imgObserver.unobserve(img);
                }
            });
        }, { threshold: 0.1, rootMargin: '50px' });

        images.forEach(function(img) {
            imgObserver.observe(img);
        });
    }

    /* ============================================================
       6. Smooth Scroll for Anchor Links
       ============================================================ */

    function initSmoothScroll() {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') return;

                let target = null;
                try {
                    target = document.querySelector(href);
                } catch (err) {
                    return;
                }
                if (target) {
                    e.preventDefault();
                    const headerHeight = document.querySelector('.fv-header')?.offsetHeight || 0;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: reduced ? 'auto' : 'smooth'
                    });
                }
            });
        });
    }

    /* ============================================================
       8. Magnetic Button Effect
       ============================================================ */

    function initMagneticButtons() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const magneticBtns = document.querySelectorAll('.fv-magnetic');

        magneticBtns.forEach(function(btn) {
            let rafId = 0;
            btn.addEventListener('mousemove', function(e) {
                if (rafId) return;
                rafId = window.requestAnimationFrame(function() {
                    rafId = 0;
                    const rect = btn.getBoundingClientRect();
                    const x = e.clientX - rect.left - rect.width / 2;
                    const y = e.clientY - rect.top - rect.height / 2;
                    btn.style.transform = 'translate(' + x * 0.2 + 'px, ' + y * 0.2 + 'px)';
                });
            });

            btn.addEventListener('mouseleave', function() {
                btn.style.transform = 'translate(0, 0)';
            });
        });
    }

    /* ============================================================
       9. Tilt Effect for Cards
       ============================================================ */

    function initTiltEffect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

        const tiltCards = document.querySelectorAll('.fv-tilt');

        tiltCards.forEach(function(card) {
            let rafId = 0;
            card.addEventListener('mousemove', function(e) {
                if (rafId) return;
                rafId = window.requestAnimationFrame(function() {
                    rafId = 0;
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    const rotateX = (y - centerY) / 20;
                    const rotateY = (centerX - x) / 20;
                    card.style.transform = 'perspective(1000px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) scale(1.02)';
                });
            });

            card.addEventListener('mouseleave', function() {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1)';
            });
        });
    }

    /* ============================================================
       10. Text Split Animation (respects reduced motion, screen readers & Vietnamese graphemes)
       ============================================================ */

    function initTextAnimation() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const animatedTexts = document.querySelectorAll('.fv-text-animate');
        if (!animatedTexts.length) return;

        animatedTexts.forEach(function(el) {
            const text = el.textContent || '';
            if (!el.getAttribute('aria-label')) {
                el.setAttribute('aria-label', text.trim());
            }
            const words = text.split(/(\s+)/);
            el.textContent = '';

            words.forEach(function(word, i) {
                if (/^\s+$/.test(word)) {
                    el.appendChild(document.createTextNode(word));
                    return;
                }
                const span = document.createElement('span');
                span.setAttribute('aria-hidden', 'true');
                span.textContent = word;
                span.style.display = 'inline-block';
                span.style.opacity = '0';
                span.style.transform = 'translateY(14px)';
                span.style.transition = 'opacity 0.45s ease, transform 0.45s ease';
                span.style.transitionDelay = (i * 35) + 'ms';
                el.appendChild(span);
            });
        });

        const textObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const spans = entry.target.querySelectorAll('span');
                    spans.forEach(function(span) {
                        span.style.opacity = '1';
                        span.style.transform = 'translateY(0)';
                    });
                    textObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        animatedTexts.forEach(function(el) {
            textObserver.observe(el);
        });
    }

    /* ============================================================
       11. Lazy Load with Fade (always adds .fv-loaded/.loaded so images stay visible)
       ============================================================ */

    function initLazyLoad() {
        const markLoaded = function(img) {
            img.classList.add('fv-loaded', 'loaded');
        };
        if ('loading' in HTMLImageElement.prototype) {
            document.querySelectorAll('img[data-src]').forEach(function(img) {
                if (!img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
                img.addEventListener('load', function() { markLoaded(img); }, { once: true });
                img.src = img.dataset.src;
                if (img.complete && img.naturalWidth > 0) markLoaded(img);
            });
        } else {
            const lazyImages = document.querySelectorAll('img[data-src]');
            const lazyObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.addEventListener('load', function() { markLoaded(img); }, { once: true });
                        img.src = img.dataset.src;
                        if (img.complete && img.naturalWidth > 0) markLoaded(img);
                        lazyObserver.unobserve(img);
                    }
                });
            }, { rootMargin: '50px' });

            lazyImages.forEach(function(img) {
                lazyObserver.observe(img);
            });
        }
    }

    /* ============================================================
       12. Hover Sound Effects (Optional)
       ============================================================ */

    function initHoverSounds() {
        // This is disabled by default for better UX
        // Enable only if needed: const hoverSound = new Audio('hover.mp3');
    }

    /* ============================================================
       Initialize All Animations
       ============================================================ */

    function init() {
        initPageLoad();
        initScrollReveal();
        initParallax();
        initCounterAnimation();
        initImageReveal();
        initSmoothScroll();
        initMagneticButtons();
        initTiltEffect();
        initTextAnimation();
        initLazyLoad();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
