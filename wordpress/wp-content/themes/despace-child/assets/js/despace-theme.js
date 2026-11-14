/**
 * DeSpace Theme UI & 3D Modal Controller (ES6+)
 * Zero jQuery dependency.
 */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  // 1. Sticky Header Effect
  const header = document.querySelector('.ds-header');
  if (header) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 40) {
        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08)';
      } else {
        header.style.boxShadow = 'none';
      }
    }, { passive: true });
  }

  // 2. Global 3D Modal Controller
  const modal = document.getElementById('despace-3d-modal');
  const modalTitle = document.getElementById('despace-modal-product-title');
  const modelContainer = document.getElementById('despace-model-container');
  const arWrap = document.getElementById('despace-ar-action-wrap');
  const closeBtn = document.getElementById('despace-modal-close');
  const backdrop = document.getElementById('despace-modal-backdrop');

  const open3DModal = (productName, modelUrl, posterUrl, enableAr) => {
    if (!modal || !modelContainer) return;

    if (modalTitle) modalTitle.textContent = productName;

    // Build dynamic model-viewer element
    modelContainer.innerHTML = '';
    const viewer = document.createElement('model-viewer');
    viewer.setAttribute('src', modelUrl);
    if (posterUrl) viewer.setAttribute('poster', posterUrl);
    viewer.setAttribute('alt', productName);
    viewer.setAttribute('camera-controls', '');
    viewer.setAttribute('auto-rotate', '');
    viewer.setAttribute('shadow-intensity', '1.2');
    viewer.setAttribute('exposure', '1.05');
    viewer.setAttribute('loading', 'eager');
    viewer.style.width = '100%';
    viewer.style.height = '100%';

    if (enableAr === '1' || enableAr === true) {
      viewer.setAttribute('ar', '');
      viewer.setAttribute('ar-modes', 'webxr scene-viewer quick-look');

      const arBtn = document.createElement('button');
      arBtn.setAttribute('slot', 'ar-button');
      arBtn.className = 'despace-ar-btn';
      arBtn.innerHTML = '📱 Xem AR trong phòng';
      viewer.appendChild(arBtn);
    }

    modelContainer.appendChild(viewer);

    modal.style.display = 'flex';
    requestAnimationFrame(() => {
      modal.classList.add('is-active');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    });
  };

  const close3DModal = () => {
    if (!modal) return;
    modal.classList.remove('is-active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    setTimeout(() => {
      modal.style.display = 'none';
      if (modelContainer) modelContainer.innerHTML = '';
    }, 300);
  };

  // Delegate clicks on all .despace-btn-open-3d
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.despace-btn-open-3d');
    if (!btn) return;

    e.preventDefault();
    const modelUrl = btn.getAttribute('data-model');
    const posterUrl = btn.getAttribute('data-poster');
    const enableAr = btn.getAttribute('data-ar');
    const productName = btn.getAttribute('data-name') || 'Mô hình 3D DeSpace';

    if (modelUrl) {
      open3DModal(productName, modelUrl, posterUrl, enableAr);
    }
  });

  if (closeBtn) closeBtn.addEventListener('click', close3DModal);
  if (backdrop) backdrop.addEventListener('click', close3DModal);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
      close3DModal();
    }
  });
});
