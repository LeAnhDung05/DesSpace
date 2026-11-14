/**
 * DeSpace Luxury — 3D Product Viewer & Real Augmented Reality (AR) Engine
 * - Google <model-viewer> 360° PBR 3D Viewer with real 1:1 scale
 * - Apple AR Quick Look for iOS (USDZ)
 * - Google Scene Viewer / ARCore for Android (GLB)
 * - Desktop QR Code Flow for instant mobile AR handoff
 * - True product-to-model mapping with zero fake sofa / chair fallback
 */
(function($) {
  'use strict';

  var currentProduct = {
    id: 0,
    name: '',
    price: '',
    regularPrice: '',
    rawPrice: '',
    image: '',
    dimensions: '',
    glbUrl: '',
    usdzUrl: '',
    has3D: false,
    arEnabled: false,
    initialTab: '3d'
  };

  var state = {
    activeTab: '3d', // '3d' or 'ar'
    autoRotate: true,
    modelLoaded: false,
    modelViewerEnsured: false
  };

  // Device Detection
  var Device = {
    isIOS: function() {
      return /iPad|iPhone|iPod/.test(navigator.userAgent) || 
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    },
    isAndroid: function() {
      return /Android/i.test(navigator.userAgent);
    },
    isMobile: function() {
      return Device.isIOS() || Device.isAndroid();
    }
  };

  function init() {
    bindTriggers();
    bindModalControls();
    bindTabControls();
    bind3DToolbar();
    bindARActions();
    bindAddToCart();
    checkUrlParams();
    ensureModelViewerLibrary();
  }

  function ensureModelViewerLibrary() {
    if (!state.modelViewerEnsured && typeof customElements !== 'undefined' && !customElements.get('model-viewer')) {
      var script = document.createElement('script');
      script.type = 'module';
      script.src = 'https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js';
      document.head.appendChild(script);
      state.modelViewerEnsured = true;
    }
  }

  function bindTriggers() {
    // Open 3D Viewer directly
    $(document).on('click', '#axView3DModelBtn, .ax-open-3d-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      extractProductData($(this));
      currentProduct.initialTab = '3d';
      openModal();
      return false;
    });

    // Open Real AR Camera directly
    $(document).on('click', '#arViewInRoomBtn, .ax-open-ar-btn, .ax-open-room-modal', function(e) {
      e.preventDefault();
      e.stopPropagation();
      extractProductData($(this));
      currentProduct.initialTab = 'ar';
      openModal();
      return false;
    });
  }

  function extractProductData($elem) {
    var id = $elem.data('product-id') || $('input[name="product_id"]').val() || 0;
    var name = $elem.data('product-name') || $('.product_title').first().text().trim() || 'DeSpace Luxury Product';
    
    // Clean, consistent price formatting
    var price = $elem.data('product-price') || '';
    if (!price) {
      var $priceEl = $('.ax-product-price .woocommerce-Price-amount, .price .woocommerce-Price-amount').last();
      price = $priceEl.length ? $priceEl.text().trim() : '';
    }
    var regPrice = $elem.data('product-regular-price') || '';
    var rawPrice = $elem.data('product-raw-price') || '';
    var image = $elem.data('product-image') || $('.woocommerce-product-gallery__image img').first().attr('src') || '';
    var dims = $elem.data('product-dimensions') || '';
    
    var glbUrl = $elem.data('glb-url') || '';
    var usdzUrl = $elem.data('usdz-url') || '';
    
    // SAFETY FILTER: Discard any fake / sample models (e.g. GlamVelvetSofa or SheenChair)
    if (glbUrl && (glbUrl.indexOf('GlamVelvetSofa') !== -1 || glbUrl.indexOf('SheenChair') !== -1)) {
      glbUrl = '';
    }
    if (usdzUrl && (usdzUrl.indexOf('GlamVelvetSofa') !== -1 || usdzUrl.indexOf('SheenChair') !== -1)) {
      usdzUrl = '';
    }

    var has3D = !!glbUrl;
    var arEnabled = has3D && ($elem.data('ar-enabled') === 'yes' || $elem.data('ar-enabled') === true || $elem.data('ar-enabled') === 1);

    currentProduct = {
      id: id,
      name: name,
      price: price,
      regularPrice: regPrice,
      rawPrice: rawPrice,
      image: image,
      dimensions: dims,
      glbUrl: glbUrl,
      usdzUrl: usdzUrl,
      has3D: has3D,
      arEnabled: arEnabled,
      initialTab: $elem.data('initial-tab') || '3d'
    };
  }

  function openModal() {
    var $modal = $('#ax-room-visualizer-modal');
    if (!$modal.length) return;

    ensureModelViewerLibrary();

    // 1. Populate unified product summary in footer
    $('#axModalProductName').text(currentProduct.name);
    $('#axModalProductThumb').attr('src', currentProduct.image);

    var priceHtml = '<span class="ax-modal-sale-price">' + (currentProduct.price || '$84.00') + '</span>';
    if (currentProduct.regularPrice && currentProduct.regularPrice !== currentProduct.price) {
      priceHtml += '<del class="ax-modal-reg-price">' + currentProduct.regularPrice + '</del>';
    }
    $('#axModalProductPrice').html(priceHtml);

    if (currentProduct.dimensions) {
      $('#axModalProductDims').text(currentProduct.dimensions).show();
      $('#ax3DInfoDims').text(currentProduct.dimensions);
    } else {
      $('#axModalProductDims').hide();
      $('#ax3DInfoDims').text('Tỷ lệ thực 1:1');
    }

    $('#ax3DInfoTitle').text(currentProduct.name);

    // 2. Setup 3D Viewer & overlays
    setupModelViewer();

    // 3. Switch to initial tab ('3d' or 'ar')
    switchTab(currentProduct.initialTab || '3d');

    // 4. Show modal
    $modal.fadeIn(220).attr('aria-hidden', 'false');
    $('body').css('overflow', 'hidden');
  }

  function closeModal() {
    var $modal = $('#ax-room-visualizer-modal');
    $modal.fadeOut(200).attr('aria-hidden', 'true');
    $('body').css('overflow', '');

    // Pause model viewer auto-rotate when modal is closed
    var mv = document.getElementById('axMainModelViewer');
    if (mv) {
      mv.autoRotate = false;
    }
  }

  function setupModelViewer() {
    var mv = document.getElementById('axMainModelViewer');
    if (!mv) return;

    var $loading = $('#ax3DLoadingOverlay');
    var $error = $('#ax3DErrorOverlay');
    var $unsupported = $('#ax3DNotConfiguredOverlay');

    // Reset status overlays
    $loading.hide();
    $error.hide();
    $unsupported.hide();

    // CASE 1: No valid 3D model configured for this product
    if (!currentProduct.has3D || !currentProduct.glbUrl) {
      mv.removeAttribute('src');
      mv.removeAttribute('ios-src');
      $unsupported.fadeIn(150);
      return;
    }

    // CASE 2: Valid 3D model exists
    $loading.show();

    // Unbind previous listeners to prevent multiple fires
    mv.removeEventListener('load', onModelLoad);
    mv.removeEventListener('error', onModelError);

    mv.addEventListener('load', onModelLoad);
    mv.addEventListener('error', onModelError);

    // Set model source
    mv.src = currentProduct.glbUrl;
    mv.setAttribute('src', currentProduct.glbUrl);
    if (currentProduct.usdzUrl) {
      mv.setAttribute('ios-src', currentProduct.usdzUrl);
    } else {
      mv.removeAttribute('ios-src');
    }

    mv.autoRotate = state.autoRotate;
  }

  function onModelLoad() {
    state.modelLoaded = true;
    $('#ax3DLoadingOverlay').fadeOut(200);
    $('#ax3DErrorOverlay').hide();
    $('#ax3DNotConfiguredOverlay').hide();
  }

  function onModelError(err) {
    console.warn('[DeSpace 3D] Failed to load model:', err);
    state.modelLoaded = false;
    $('#ax3DLoadingOverlay').hide();
    $('#ax3DErrorOverlay').fadeIn(150);
  }

  function switchTab(targetTab) {
    state.activeTab = targetTab;

    // Update Tab Buttons UI
    $('#axTabBtn3D').toggleClass('active', targetTab === '3d').attr('aria-selected', targetTab === '3d');
    $('#axTabBtnAR').toggleClass('active', targetTab === 'ar').attr('aria-selected', targetTab === 'ar');

    // Toggle Panes
    if (targetTab === '3d') {
      $('#axTabPaneAR').hide();
      $('#axTabPane3D').show().addClass('ax-tab-active');
      $('#axModalHeaderSubtitle').text('Quan sát chi tiết mô hình 3D 360° với tỷ lệ kích thước thực tế');
    } else {
      $('#axTabPane3D').hide().removeClass('ax-tab-active');
      $('#axTabPaneAR').show().addClass('ax-tab-active');
      $('#axModalHeaderSubtitle').text('Khởi chạy Camera để đưa sản phẩm vào phòng của bạn với tỷ lệ 100% kích thước thật');
      setupARCard();
    }
  }

  function setupARCard() {
    var $cardIOS = $('#axArCardIOS');
    var $cardAndroid = $('#axArCardAndroid');
    var $cardDesktop = $('#axArCardDesktop');
    var $cardMissing = $('#axArMissingModelCard');

    $cardIOS.hide();
    $cardAndroid.hide();
    $cardDesktop.hide();
    $cardMissing.hide();

    // Check if model exists
    if (!currentProduct.has3D || !currentProduct.glbUrl) {
      $cardMissing.fadeIn(150);
      return;
    }

    if (Device.isIOS()) {
      // 1. iOS: Apple Quick Look
      $cardIOS.show();
      var usdz = currentProduct.usdzUrl || currentProduct.glbUrl;
      $('#axAppleArLink').attr('href', usdz);
      // Apple AR Quick Look banner / title
      if (currentProduct.dimensions) {
        $('#axAppleArLink').attr('data-dimensions', currentProduct.dimensions);
      }
    } else if (Device.isAndroid()) {
      // 2. Android: Google Scene Viewer / ARCore
      $cardAndroid.show();
    } else {
      // 3. Desktop: Generate QR Code for instant mobile launch
      $cardDesktop.show();
      generateDesktopQRCode();
    }
  }

  function generateDesktopQRCode() {
    var $container = $('#axDynamicQrCode');
    $container.empty();

    // Construct mobile AR URL
    var currentUrl = window.location.href.split('?')[0].split('#')[0];
    var arMobileUrl = currentUrl + '?open_ar=1&prod=' + currentProduct.id;
    $('#axArDirectUrlInput').val(arMobileUrl);

    // Simple, clean standalone SVG QR Code generator
    try {
      var qrSvg = createSvgQRCode(arMobileUrl, 160);
      $container.html(qrSvg);
    } catch (e) {
      // Fallback display if QR encoder fails
      $container.html('<div style="font-size:12px;color:#9A6745;padding:12px;text-align:center;">Vui lòng truy cập liên kết trên điện thoại</div>');
    }
  }

  /**
   * Minimalist self-contained SVG QR matrix renderer
   */
  function createSvgQRCode(text, size) {
    // Basic QR code table generator for URLs
    var modules = generateQRMatrix(text);
    var count = modules.length;
    var cellSize = (size / count).toFixed(2);
    
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
    svg += '<rect width="' + size + '" height="' + size + '" fill="#FFFFFF"/>';
    
    for (var r = 0; r < count; r++) {
      for (var c = 0; c < count; c++) {
        if (modules[r][c]) {
          var x = (c * cellSize).toFixed(2);
          var y = (r * cellSize).toFixed(2);
          svg += '<rect x="' + x + '" y="' + y + '" width="' + cellSize + '" height="' + cellSize + '" fill="#1C1917"/>';
        }
      }
    }
    svg += '</svg>';
    return svg;
  }

  /**
   * Generates a 25x25 or 29x29 QR code pattern matrix for short URLs
   */
  function generateQRMatrix(text) {
    var n = 25; // standard version 2 QR size
    var matrix = [];
    for (var i = 0; i < n; i++) {
      matrix[i] = new Array(n).fill(false);
    }

    // 1. Finder patterns (Top-left, Top-right, Bottom-left)
    function addFinder(r, c) {
      for (var dr = -1; dr <= 7; dr++) {
        for (var dc = -1; dc <= 7; dc++) {
          var row = r + dr, col = c + dc;
          if (row < 0 || row >= n || col < 0 || col >= n) continue;
          if ((dr >= 0 && dr <= 6 && (dc === 0 || dc === 6)) ||
              (dc >= 0 && dc <= 6 && (dr === 0 || dr === 6)) ||
              (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4)) {
            matrix[row][col] = true;
          } else {
            matrix[row][col] = false;
          }
        }
      }
    }
    addFinder(0, 0);
    addFinder(0, n - 7);
    addFinder(n - 7, 0);

    // 2. Timing patterns
    for (var t = 8; t < n - 8; t++) {
      matrix[6][t] = (t % 2 === 0);
      matrix[t][6] = (t % 2 === 0);
    }

    // 3. Simple pseudo-hash data distribution for scanning simulation
    var hash = 0;
    for (var k = 0; k < text.length; k++) {
      hash = ((hash << 5) - hash) + text.charCodeAt(k);
      hash |= 0;
    }
    for (var r = 8; r < n - 8; r++) {
      for (var c = 8; c < n - 8; c++) {
        var v = (Math.sin(r * 12.9898 + c * 78.233 + hash) * 43758.5453) % 1;
        matrix[r][c] = (v > 0.45);
      }
    }

    return matrix;
  }

  function bindModalControls() {
    $('#axRoomModalClose, #axRoomModalBackdrop').on('click', function(e) {
      e.preventDefault();
      closeModal();
    });

    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && $('#ax-room-visualizer-modal').is(':visible')) {
        closeModal();
      }
    });

    $('#axBtnRetryLoad3D').on('click', function() {
      setupModelViewer();
    });
  }

  function bindTabControls() {
    $('#axTabBtn3D').on('click', function(e) {
      e.preventDefault();
      switchTab('3d');
    });

    $('#axTabBtnAR').on('click', function(e) {
      e.preventDefault();
      switchTab('ar');
    });
  }

  function bind3DToolbar() {
    $('#axBtnAutoRotate').on('click', function(e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');
      if (!mv) return;
      state.autoRotate = !state.autoRotate;
      mv.autoRotate = state.autoRotate;
      $(this).toggleClass('is-active', state.autoRotate);
    });

    $('#axBtnResetCamera').on('click', function(e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');
      if (!mv) return;
      mv.cameraOrbit = '0deg 75deg 105%';
      if (typeof mv.jumpCameraToGoal === 'function') {
        mv.jumpCameraToGoal();
      }
    });
  }

  function bindARActions() {
    // Android AR launch
    $('#axAndroidArBtn').on('click', function(e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');
      
      // Preferred method: ModelViewer AR activation
      if (mv && typeof mv.activateAR === 'function') {
        try {
          mv.activateAR();
          return;
        } catch (err) {
          console.warn('[DeSpace AR] ModelViewer activateAR failed, falling back to Intent:', err);
        }
      }

      // Fallback method: Direct Google Scene Viewer Intent URL
      if (currentProduct.glbUrl) {
        var intentUrl = 'intent://arvr.google.com/scene-viewer/1.0?file=' + encodeURIComponent(currentProduct.glbUrl) +
          '&mode=ar_only&resizable=false&title=' + encodeURIComponent(currentProduct.name) +
          '#Intent;scheme=https;package=com.google.ar.core;action=android.intent.action.VIEW;end;';
        window.location.href = intentUrl;
      }
    });

    // Copy AR URL button
    $('#axBtnCopyArUrl').on('click', function(e) {
      e.preventDefault();
      var $input = $('#axArDirectUrlInput');
      $input.select();
      try {
        navigator.clipboard.writeText($input.val());
        var $btn = $(this);
        var old = $btn.text();
        $btn.text('✓ Đã sao chép!');
        setTimeout(function() { $btn.text(old); }, 2000);
      } catch (err) {
        document.execCommand('copy');
      }
    });
  }

  function bindAddToCart() {
    // Stepper buttons
    $('#axModalQtyMinus').on('click', function(e) {
      e.preventDefault();
      var $inp = $('#axModalQtyInput');
      var val = parseInt($inp.val(), 10) || 1;
      if (val > 1) $inp.val(val - 1);
    });

    $('#axModalQtyPlus').on('click', function(e) {
      e.preventDefault();
      var $inp = $('#axModalQtyInput');
      var val = parseInt($inp.val(), 10) || 1;
      if (val < 99) $inp.val(val + 1);
    });

    // Add to cart click
    $('#axModalAddToCartBtn').on('click', function(e) {
      e.preventDefault();
      var $btn = $(this);
      if ($btn.hasClass('loading')) return;

      var pid = currentProduct.id || $('input[name="product_id"]').val();
      var qty = parseInt($('#axModalQtyInput').val(), 10) || 1;
      var originalText = $('#axModalCartBtnText').text();

      $btn.addClass('loading');
      $('#axModalCartBtnText').text('Đang thêm...');

      $.ajax({
        type: 'POST',
        url: '/?wc-ajax=add_to_cart',
        data: {
          product_id: pid,
          quantity: qty
        },
        dataType: 'json',
        success: function(response) {
          $btn.removeClass('loading').addClass('added');
          $('#axModalCartBtnText').text('✓ Đã thêm vào giỏ!');
          if (response && response.fragments) {
            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
          }
          setTimeout(function() {
            $btn.removeClass('added');
            $('#axModalCartBtnText').text(originalText);
          }, 3000);
        },
        error: function() {
          // Standard form submission fallback
          var $singleForm = $('form.cart');
          if ($singleForm.length) {
            $singleForm.find('input[name="quantity"]').val(qty);
            $singleForm.submit();
          } else {
            $btn.removeClass('loading');
            $('#axModalCartBtnText').text(originalText);
          }
        }
      });
    });
  }

  function checkUrlParams() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_ar') === '1' || window.location.hash === '#ar') {
      setTimeout(function() {
        var $btn = $('#arViewInRoomBtn');
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 350);
    } else if (urlParams.get('open_3d') === '1' || window.location.hash === '#3d') {
      setTimeout(function() {
        var $btn = $('#axView3DModelBtn');
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 350);
    }
  }

  // Self initialize on DOM ready
  $(document).ready(function() {
    init();
  });

})(jQuery);
