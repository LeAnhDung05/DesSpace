/**
 * THE DeSpace EDIT — Bento Grid Interactions
 */
document.addEventListener('DOMContentLoaded', function () {
  var bentoSection = document.getElementById('the-axgnud-edit');
  if (!bentoSection) return;

  var livingCard = bentoSection.querySelector('.fv-bento-card--living');
  if (!livingCard) return;

  var prevBtn = livingCard.querySelector('.fv-bento-nav-btn[aria-label="Previous"]');
  var nextBtn = livingCard.querySelector('.fv-bento-nav-btn[aria-label="Next"]');
  var counter = livingCard.querySelector('.fv-bento-card__counter');
  var progressBars = livingCard.querySelectorAll('.fv-bento-progress-bars span');

  var currentSlide = 0;
  var totalSlides = 4;

  function updateSlide(index) {
    currentSlide = (index + totalSlides) % totalSlides;
    if (counter) {
      var displayNum = String(currentSlide + 1).padStart(2, '0');
      counter.textContent = displayNum + ' / 06';
    }
    progressBars.forEach(function (bar, idx) {
      if (idx === currentSlide) {
        bar.classList.add('active');
      } else {
        bar.classList.remove('active');
      }
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
});
