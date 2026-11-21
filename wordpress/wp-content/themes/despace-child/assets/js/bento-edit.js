/**
 * THE DeSpace EDIT — Bento Grid Real Carousel Interactions
 * Syncs background room photo, heading, subtitle, category CTA link,
 * progress bars, counter, keyboard arrows, and respects reduced motion.
 */
document.addEventListener('DOMContentLoaded', function () {
  var bentoSection = document.getElementById('the-despace-edit') || document.getElementById('the-axgnud-edit');
  if (!bentoSection) return;

  var livingCard = bentoSection.querySelector('.fv-bento-card--living');
  if (!livingCard) return;

  var prevBtn = livingCard.querySelector('.fv-bento-nav-btn[aria-label="Previous"]');
  var nextBtn = livingCard.querySelector('.fv-bento-nav-btn[aria-label="Next"]');
  var counter = livingCard.querySelector('.fv-bento-card__counter');
  var progressBars = livingCard.querySelectorAll('.fv-bento-progress-bars span');
  var bgLayer = livingCard.querySelector('.fv-bento-card__bg');
  var tagEl = livingCard.querySelector('.fv-bento-card__tag');
  var titleEl = livingCard.querySelector('.fv-bento-card__title');
  var subcatSpans = livingCard.querySelectorAll('.fv-bento-card__subcats span');
  var ctaLink = livingCard.querySelector('.fv-bento-pill-btn');

  var slides = [];
  var rawSlides = livingCard.getAttribute('data-bento-slides');
  if (rawSlides) {
    try {
      var parsed = JSON.parse(rawSlides);
      if (Array.isArray(parsed) && parsed.length) {
        slides = parsed;
      }
    } catch (e) {
      slides = [];
    }
  }

  var currentSlide = 0;
  var totalSlides = slides.length || progressBars.length || 4;
  var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

  function applySlideContent(slide) {
    if (!slide) return;
    if (slide.image) {
      livingCard.style.setProperty('--bento-bg', "url('" + slide.image.replace(/'/g, "\\'") + "')");
    }
    if (tagEl && slide.tag) tagEl.textContent = slide.tag;
    if (titleEl && slide.title) titleEl.textContent = slide.title;
    if (subcatSpans.length >= 1 && slide.sub1) subcatSpans[0].textContent = slide.sub1;
    if (subcatSpans.length >= 2 && slide.sub2) subcatSpans[1].textContent = slide.sub2;
    if (ctaLink && slide.href) ctaLink.setAttribute('href', slide.href);
  }

  function updateSlide(index) {
    currentSlide = ((index % totalSlides) + totalSlides) % totalSlides;
    var slide = slides[currentSlide] || null;
    var preferReduced = reducedMotionQuery && reducedMotionQuery.matches;

    if (slide) {
      if (bgLayer && !preferReduced) {
        bgLayer.classList.add('is-switching');
        window.setTimeout(function () {
          applySlideContent(slide);
          bgLayer.classList.remove('is-switching');
        }, 120);
      } else {
        applySlideContent(slide);
      }
    }

    if (counter) {
      var displayNum = String(currentSlide + 1).padStart(2, '0');
      var totalNum = String(totalSlides).padStart(2, '0');
      counter.textContent = displayNum + ' / ' + totalNum;
    }

    progressBars.forEach(function (bar, idx) {
      bar.classList.toggle('active', idx === currentSlide);
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      updateSlide(currentSlide - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      updateSlide(currentSlide + 1);
    });
  }

  // Keyboard navigation (ArrowLeft / ArrowRight) when focused inside the carousel card
  livingCard.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      updateSlide(currentSlide - 1);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      updateSlide(currentSlide + 1);
    }
  });
});
