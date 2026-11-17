/**
 * DeSpace Luxury — 3D Product Viewer & Real Augmented Reality (AR) Engine
 * - Google <model-viewer> 360° PBR 3D Viewer with real 1:1 scale
 * - Live 3D Camera AR Studio (60fps WebRTC Camera + Real 3D .glb Model Viewer)
 * - Apple AR Quick Look for iOS (64-byte aligned multi-material USDZ)
 * - Google Scene Viewer / ARCore for Android (GLB)
 * - Standards-compliant ISO/IEC 18004 Reed-Solomon QR Code Generator
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
    arSubView: 'handoff', // 'handoff' or 'live-camera'
    qrNetMode: 'tunnel', // 'tunnel' or 'lan'
    autoRotate: true,
    modelLoaded: false,
    modelViewerEnsured: false,
    // Live 3D Camera AR state
    cameraStream: null,
    facingMode: 'environment',
    usingFallbackRoom: false,
    gestureMode: 'rotate', // 'rotate' or 'move'
    livePos: { x: 0, y: 0 },
    liveScale: 1.0,
    isDraggingLive: false,
    dragStart: { x: 0, y: 0 },
    posStart: { x: 0, y: 0 },
    pinchStartDist: 0,
    pinchStartScale: 1.0,
    rafPending: false
  };

  var copyTimers = new WeakMap();
  var lastCopyMessage = '';

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
    // Default QR network mode: prefer tunnel if configured, else LAN
    var net = window.DESPACE_AR_NETWORK || {};
    if (!net.tunnelBaseUrl && net.lanBaseUrl) {
      state.qrNetMode = 'lan';
    }

    bindTriggers();
    bindModalControls();
    bindTabControls();
    bind3DToolbar();
    bindARActions();
    bindLiveCameraAR();
    bindCopyButton();
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
    var $fallbackData = $('#ar-product-data');
    var id = $elem.data('product-id') || ($fallbackData.length ? $fallbackData.data('product-id') : 0) || $('input[name="product_id"]').val() || 0;
    var name = $elem.data('product-name') || ($fallbackData.length ? $fallbackData.data('product-name') : '') || $('.product_title').first().text().trim() || 'DeSpace Luxury Product';

    var price = $elem.data('product-price') || ($fallbackData.length ? $fallbackData.data('product-price') : '') || '';
    if (!price) {
      var $priceEl = $('.ax-product-price .woocommerce-Price-amount, .price .woocommerce-Price-amount').last();
      price = $priceEl.length ? $priceEl.text().trim() : '';
    }
    var regPrice = $elem.data('product-regular-price') || '';
    var rawPrice = $elem.data('product-raw-price') || '';
    var image = $elem.data('product-image') || ($fallbackData.length ? $fallbackData.data('product-image') : '') || $('.woocommerce-product-gallery__image img').first().attr('src') || '';
    var dims = $elem.data('product-dimensions') || '';

    var glbUrl = $elem.data('glb-url') || ($fallbackData.length ? $fallbackData.data('glb-url') : '') || '';
    var usdzUrl = $elem.data('usdz-url') || ($fallbackData.length ? $fallbackData.data('usdz-url') : '') || '';

    // Rewrite localhost in model URLs if accessed from LAN IP or tunnel
    glbUrl = normalizeAssetUrl(glbUrl);
    usdzUrl = normalizeAssetUrl(usdzUrl);
    image = normalizeAssetUrl(image);

    // SAFETY FILTER: Discard any fake / sample models
    if (glbUrl && (glbUrl.indexOf('GlamVelvetSofa') !== -1 || glbUrl.indexOf('SheenChair') !== -1)) {
      glbUrl = '';
    }
    if (usdzUrl && (usdzUrl.indexOf('GlamVelvetSofa') !== -1 || usdzUrl.indexOf('SheenChair') !== -1)) {
      usdzUrl = '';
    }

    var has3D = !!glbUrl;
    var arEnabled = has3D && ($elem.data('ar-enabled') === 'yes' || $elem.data('ar-enabled') === true || $elem.data('ar-enabled') === 1 || !!usdzUrl);

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

  function normalizeAssetUrl(url) {
    if (!url || typeof window === 'undefined') return url;
    var currentOrigin = window.location.origin;
    if (currentOrigin && currentOrigin.indexOf('localhost') === -1 && url.indexOf('localhost') !== -1) {
      return url.replace(/^https?:\/\/localhost(?::\d+)?/i, currentOrigin);
    }
    return url;
  }

  function openModal() {
    var $modal = $('#ax-room-visualizer-modal');
    if (!$modal.length) return;

    ensureModelViewerLibrary();

    // 1. Populate unified product summary in footer
    $('#axModalProductName').text(currentProduct.name);
    $('#axModalProductThumb').attr('src', currentProduct.image);

    var priceHtml = '<span class="ax-modal-sale-price">' + (currentProduct.price || '') + '</span>';
    if (currentProduct.regularPrice && currentProduct.regularPrice !== currentProduct.price) {
      priceHtml += '<del class="ax-modal-reg-price">' + currentProduct.regularPrice + '</del>';
    }
    $('#axModalProductPrice').html(priceHtml);

    if (currentProduct.dimensions) {
      $('#axModalProductDims').text(currentProduct.dimensions).show();
      $('#ax3DInfoDims').text(currentProduct.dimensions);
      $('#axLiveArInfoDims').text(currentProduct.dimensions);
    } else {
      $('#axModalProductDims').hide();
      $('#ax3DInfoDims').text('Tỷ lệ thực 1:1');
      $('#axLiveArInfoDims').text('Tỷ lệ thực 1:1');
    }

    $('#ax3DInfoTitle').text(currentProduct.name);
    $('#axLiveArInfoTitle').text(currentProduct.name);

    // 2. Setup 3D Viewer & overlays
    setupModelViewer();

    // 3. Switch to initial tab ('3d' or 'ar')
    switchTab(currentProduct.initialTab || '3d');

    // 4. Show modal
    $modal.fadeIn(200).attr('aria-hidden', 'false');
    $('body').css('overflow', 'hidden');
  }

  function closeModal() {
    stopLiveCameraStream();
    var $modal = $('#ax-room-visualizer-modal');
    $modal.fadeOut(180).attr('aria-hidden', 'true');
    $('body').css('overflow', '');

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

    $loading.hide();
    $error.hide();
    $unsupported.hide();

    if (!currentProduct.has3D || !currentProduct.glbUrl) {
      mv.removeAttribute('src');
      mv.removeAttribute('ios-src');
      $unsupported.fadeIn(150);
      return;
    }

    $loading.show();

    mv.removeEventListener('load', onModelLoad);
    mv.removeEventListener('error', onModelError);

    mv.addEventListener('load', onModelLoad);
    mv.addEventListener('error', onModelError);

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
    $('#ax3DLoadingOverlay').fadeOut(180);
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

    $('#axTabBtn3D').toggleClass('active', targetTab === '3d').attr('aria-selected', targetTab === '3d');
    $('#axTabBtnAR').toggleClass('active', targetTab === 'ar').attr('aria-selected', targetTab === 'ar');

    if (targetTab === '3d') {
      stopLiveCameraStream();
      $('#axTabPaneAR').hide().removeClass('ax-tab-active');
      $('#axTabPane3D').show().addClass('ax-tab-active');
      $('#axModalHeaderSubtitle').text('Quan sát chi tiết mô hình 3D 360° với tỷ lệ kích thước thực tế');
    } else {
      $('#axTabPane3D').hide().removeClass('ax-tab-active');
      $('#axTabPaneAR').show().addClass('ax-tab-active');
      $('#axModalHeaderSubtitle').text('Khởi chạy Camera để đưa đúng mô hình 3D vào phòng của bạn với tỷ lệ 1:1 mượt mà');
      setupARCard();
    }
  }

  function setupARCard() {
    var $cardIOS = $('#axArCardIOS');
    var $cardAndroid = $('#axArCardAndroid');
    var $cardDesktop = $('#axArCardDesktop');
    var $cardMissing = $('#axArMissingModelCard');
    var $submodeBar = $('#axArSubmodeBar');
    var $handoffContainer = $('#axArHandoffContainer');
    var $liveCamCard = $('#axLiveCameraArCard');

    $cardIOS.hide();
    $cardAndroid.hide();
    $cardDesktop.hide();
    $cardMissing.hide();

    if (!currentProduct.has3D || !currentProduct.glbUrl) {
      $submodeBar.hide();
      $liveCamCard.hide();
      $cardMissing.fadeIn(150);
      return;
    }

    $submodeBar.show();

    // Update iOS Quick Look link (must have <img> as first child for Safari rel="ar")
    var usdz = currentProduct.usdzUrl || currentProduct.glbUrl;
    $('#axAppleArLink').attr('href', usdz);
    if (currentProduct.image) {
      $('#axAppleArHiddenImg').attr('src', currentProduct.image);
    }

    if (Device.isIOS()) {
      $cardIOS.show();
    } else if (Device.isAndroid()) {
      $cardAndroid.show();
    } else {
      $cardDesktop.show();
      generateDesktopQRCode();
    }

    if (state.arSubView === 'live-camera') {
      switchArSubView('live-camera');
    } else {
      switchArSubView('handoff');
    }
  }

  function switchArSubView(viewMode) {
    state.arSubView = viewMode;
    $('#axSubModeQrBtn').toggleClass('is-active', viewMode === 'handoff');
    $('#axSubModeLiveCamBtn').toggleClass('is-active', viewMode === 'live-camera');

    if (viewMode === 'live-camera') {
      $('#axArHandoffContainer').hide();
      $('#axLiveCameraArCard').fadeIn(180);
      startLiveCameraAR();
    } else {
      stopLiveCameraStream();
      $('#axLiveCameraArCard').hide();
      $('#axArHandoffContainer').fadeIn(180);
    }
  }

  // =========================================================================
  // LIVE 3D CAMERA AR STUDIO (60FPS WEBRTC + EXACT 3D GLB MODEL VIEWER)
  // =========================================================================
  function startLiveCameraAR() {
    var liveMv = document.getElementById('axLiveArModelViewer');
    if (liveMv && currentProduct.glbUrl) {
      if (liveMv.getAttribute('src') !== currentProduct.glbUrl) {
        liveMv.src = currentProduct.glbUrl;
        liveMv.setAttribute('src', currentProduct.glbUrl);
      }
      if (currentProduct.usdzUrl) {
        liveMv.setAttribute('ios-src', currentProduct.usdzUrl);
      }
    }

    resetLiveTransform();
    setLiveGestureMode('rotate');

    var video = document.getElementById('axLiveArVideo');
    var $fallbackBg = $('#axLiveArFallbackRoom');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      enableFallbackRoomBackdrop();
      return;
    }

    stopLiveCameraStream();

    var constraints = {
      audio: false,
      video: {
        facingMode: { ideal: state.facingMode },
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        frameRate: { ideal: 60, min: 30 }
      }
    };

    navigator.mediaDevices.getUserMedia(constraints)
      .then(function(stream) {
        state.cameraStream = stream;
        state.usingFallbackRoom = false;
        $fallbackBg.hide();
        if (video) {
          video.srcObject = stream;
          video.style.display = 'block';
          video.play().catch(function() {});
        }
      })
      .catch(function(err) {
        console.warn('[DeSpace Live AR] Camera stream fallback to room backdrop:', err);
        enableFallbackRoomBackdrop();
      });
  }

  function enableFallbackRoomBackdrop() {
    state.usingFallbackRoom = true;
    $('#axLiveArVideo').hide();
    $('#axLiveArFallbackRoom').show();
  }

  function stopLiveCameraStream() {
    if (state.cameraStream) {
      state.cameraStream.getTracks().forEach(function(track) {
        track.stop();
      });
      state.cameraStream = null;
    }
    var video = document.getElementById('axLiveArVideo');
    if (video) {
      video.srcObject = null;
    }
  }

  function setLiveGestureMode(mode) {
    state.gestureMode = mode;
    $('#axLiveModeRotateBtn').toggleClass('is-active', mode === 'rotate');
    $('#axLiveModeMoveBtn').toggleClass('is-active', mode === 'move');

    var liveMv = document.getElementById('axLiveArModelViewer');
    var $wrap = $('#axLiveArObjectWrap');

    if (mode === 'rotate') {
      if (liveMv) {
        liveMv.setAttribute('camera-controls', '');
        liveMv.style.pointerEvents = 'auto';
      }
      $wrap.removeClass('is-move-mode');
      $('#axLiveArHint').text('Chế độ Xoay 3D 360°: Vuốt trực tiếp lên sản phẩm để xoay mọi góc độ • Cuộn/Chụm 2 ngón để thu phóng');
    } else {
      if (liveMv) {
        liveMv.removeAttribute('camera-controls');
        liveMv.style.pointerEvents = 'none';
      }
      $wrap.addClass('is-move-mode');
      $('#axLiveArHint').text('Chế độ Di Chuyển: Kéo sản phẩm bằng 1 ngón tay / chuột để đặt vào vị trí mong muốn trong phòng');
    }
  }

  function scheduleLiveTransformUpdate() {
    if (state.rafPending) return;
    state.rafPending = true;
    requestAnimationFrame(function() {
      state.rafPending = false;
      var wrap = document.getElementById('axLiveArObjectWrap');
      if (wrap) {
        wrap.style.transform = 'translate3d(' + state.livePos.x.toFixed(1) + 'px, ' + state.livePos.y.toFixed(1) + 'px, 0) scale(' + state.liveScale.toFixed(3) + ')';
      }
      var pct = Math.round(state.liveScale * 100) + '%';
      $('#axLiveScaleBadge').text(pct);
      $('#axLiveArScaleReadout').text(pct);
      $('#axLiveScaleSlider').val(Math.round(state.liveScale * 100));
    });
  }

  function resetLiveTransform() {
    state.livePos = { x: 0, y: 0 };
    state.liveScale = 1.0;
    scheduleLiveTransformUpdate();

    var liveMv = document.getElementById('axLiveArModelViewer');
    if (liveMv) {
      liveMv.cameraOrbit = '0deg 78deg 105%';
      if (typeof liveMv.jumpCameraToGoal === 'function') {
        liveMv.jumpCameraToGoal();
      }
    }
  }

  function bindLiveCameraAR() {
    // Open Live Camera AR from any trigger button
    $(document).on('click', '.ax-trigger-live-cam', function(e) {
      e.preventDefault();
      if (state.activeTab !== 'ar') {
        switchTab('ar');
      }
      switchArSubView('live-camera');
    });

    // Sub-mode switcher buttons
    $('#axSubModeQrBtn').on('click', function(e) {
      e.preventDefault();
      switchArSubView('handoff');
    });

    $('#axSubModeLiveCamBtn').on('click', function(e) {
      e.preventDefault();
      switchArSubView('live-camera');
    });

    // Gesture mode toggle (Rotate 360° vs Move on Floor)
    $('#axLiveModeRotateBtn').on('click', function(e) {
      e.preventDefault();
      setLiveGestureMode('rotate');
    });

    $('#axLiveModeMoveBtn').on('click', function(e) {
      e.preventDefault();
      setLiveGestureMode('move');
    });

    // Dragging on floor in 'move' mode (pointer events for unified 60fps touch + mouse)
    var viewport = document.getElementById('axLiveCamViewport');
    if (viewport) {
      viewport.addEventListener('pointerdown', function(e) {
        if (state.gestureMode !== 'move') return;
        if (e.target.closest('.despace-stage-mode-bar, .ax-live-ar-pill')) return;
        state.isDraggingLive = true;
        state.dragStart = { x: e.clientX, y: e.clientY };
        state.posStart = { x: state.livePos.x, y: state.livePos.y };
        $('#axLiveArObjectWrap').addClass('is-dragging');
        if (viewport.setPointerCapture) {
          try { viewport.setPointerCapture(e.pointerId); } catch (err) {}
        }
      });

      viewport.addEventListener('pointermove', function(e) {
        if (!state.isDraggingLive) return;
        var dx = e.clientX - state.dragStart.x;
        var dy = e.clientY - state.dragStart.y;
        var maxOffsetX = viewport.clientWidth * 0.42;
        var maxOffsetY = viewport.clientHeight * 0.42;
        state.livePos.x = Math.max(-maxOffsetX, Math.min(maxOffsetX, state.posStart.x + dx));
        state.livePos.y = Math.max(-maxOffsetY, Math.min(maxOffsetY, state.posStart.y + dy));
        scheduleLiveTransformUpdate();
      });

      var endDrag = function() {
        if (!state.isDraggingLive) return;
        state.isDraggingLive = false;
        $('#axLiveArObjectWrap').removeClass('is-dragging');
      };
      viewport.addEventListener('pointerup', endDrag);
      viewport.addEventListener('pointercancel', endDrag);

      // 2-finger pinch-to-zoom on touch screens
      viewport.addEventListener('touchstart', function(e) {
        if (e.touches && e.touches.length === 2) {
          var dx = e.touches[0].clientX - e.touches[1].clientX;
          var dy = e.touches[0].clientY - e.touches[1].clientY;
          state.pinchStartDist = Math.hypot(dx, dy);
          state.pinchStartScale = state.liveScale;
        }
      }, { passive: true });

      viewport.addEventListener('touchmove', function(e) {
        if (e.touches && e.touches.length === 2 && state.pinchStartDist > 10) {
          var dx = e.touches[0].clientX - e.touches[1].clientX;
          var dy = e.touches[0].clientY - e.touches[1].clientY;
          var dist = Math.hypot(dx, dy);
          var ratio = dist / state.pinchStartDist;
          state.liveScale = Math.max(0.5, Math.min(1.8, state.pinchStartScale * ratio));
          scheduleLiveTransformUpdate();
        }
      }, { passive: true });
    }

    // Scale controls
    $('#axLiveScaleSlider').on('input', function() {
      var val = parseInt($(this).val(), 10) || 100;
      state.liveScale = Math.max(0.5, Math.min(1.8, val / 100));
      scheduleLiveTransformUpdate();
    });

    $('#axLiveScaleDownBtn').on('click', function(e) {
      e.preventDefault();
      state.liveScale = Math.max(0.5, state.liveScale - 0.1);
      scheduleLiveTransformUpdate();
    });

    $('#axLiveScaleUpBtn').on('click', function(e) {
      e.preventDefault();
      state.liveScale = Math.min(1.8, state.liveScale + 0.1);
      scheduleLiveTransformUpdate();
    });

    $('#axLiveResetTransformBtn').on('click', function(e) {
      e.preventDefault();
      resetLiveTransform();
    });

    // Lighting tone presets
    $('.ax-live-tone-btn').on('click', function(e) {
      e.preventDefault();
      var $btn = $(this);
      var tone = $btn.data('tone') || 'natural';
      var exp = parseFloat($btn.data('exposure')) || 1.06;

      $('.ax-live-tone-btn').removeClass('active');
      $btn.addClass('active');

      $('#axLiveArToneOverlay')
        .removeClass('ax-tone-natural ax-tone-warm ax-tone-moody')
        .addClass('ax-tone-' + tone);

      var liveMv = document.getElementById('axLiveArModelViewer');
      if (liveMv) {
        liveMv.exposure = exp;
      }
    });

    // Flip camera (front / rear)
    $('#axLiveFlipCamBtn').on('click', function(e) {
      e.preventDefault();
      state.facingMode = (state.facingMode === 'environment') ? 'user' : 'environment';
      startLiveCameraAR();
    });

    // Custom room photo upload into Live 3D AR stage
    $('#axLiveRoomPhotoInput').on('change', function(e) {
      var file = e.target.files && e.target.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      var reader = new FileReader();
      reader.onload = function(ev) {
        stopLiveCameraStream();
        state.usingFallbackRoom = true;
        $('#axLiveArVideo').hide();
        $('#axLiveArFallbackRoom').attr('src', ev.target.result).show();
      };
      reader.readAsDataURL(file);
    });

    // Snapshot capture (composite live video/room + 3D WebGL model-viewer)
    $('#axLiveSnapshotBtn').on('click', function(e) {
      e.preventDefault();
      captureLiveArSnapshot();
    });
  }

  async function captureLiveArSnapshot() {
    var viewport = document.getElementById('axLiveCamViewport');
    var video = document.getElementById('axLiveArVideo');
    var fallbackImg = document.getElementById('axLiveArFallbackRoom');
    var liveMv = document.getElementById('axLiveArModelViewer');
    if (!viewport) return;

    var vw = viewport.clientWidth || 960;
    var vh = viewport.clientHeight || 540;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    var canvas = document.createElement('canvas');
    canvas.width = vw * dpr;
    canvas.height = vh * dpr;
    var ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    // 1. Draw camera video or room photo background (object-fit: cover math)
    var bgSource = (!state.usingFallbackRoom && video && video.videoWidth > 0) ? video : fallbackImg;
    var srcW = (bgSource && (bgSource.videoWidth || bgSource.naturalWidth)) || vw;
    var srcH = (bgSource && (bgSource.videoHeight || bgSource.naturalHeight)) || vh;

    if (bgSource && srcW > 0 && srcH > 0) {
      var scaleCover = Math.max(vw / srcW, vh / srcH);
      var drawW = srcW * scaleCover;
      var drawH = srcH * scaleCover;
      var offsetX = (vw - drawW) / 2;
      var offsetY = (vh - drawH) / 2;
      try {
        ctx.drawImage(bgSource, offsetX, offsetY, drawW, drawH);
      } catch (err) {
        ctx.fillStyle = '#EAE0D5';
        ctx.fillRect(0, 0, vw, vh);
      }
    } else {
      ctx.fillStyle = '#EAE0D5';
      ctx.fillRect(0, 0, vw, vh);
    }

    // 2. Draw floor contact shadow & 3D WebGL Model Viewer snapshot
    var mvSize = 360 * state.liveScale;
    var centerX = (vw / 2) + state.livePos.x;
    var centerY = (vh / 2) + state.livePos.y + 20;

    // Contact shadow ellipse
    ctx.save();
    ctx.fillStyle = 'rgba(15, 10, 6, 0.36)';
    ctx.beginPath();
    ctx.ellipse(centerX, centerY + mvSize * 0.34, mvSize * 0.32, mvSize * 0.06, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    if (liveMv && typeof liveMv.toDataURL === 'function') {
      try {
        var mvDataUrl = liveMv.toDataURL('image/png');
        await new Promise(function(resolve) {
          var mvImg = new Image();
          mvImg.onload = function() {
            ctx.drawImage(mvImg, centerX - mvSize / 2, centerY - mvSize / 2, mvSize, mvSize);
            resolve();
          };
          mvImg.onerror = resolve;
          mvImg.src = mvDataUrl;
        });
      } catch (err) {
        console.warn('[DeSpace AR Snapshot] Could not capture WebGL canvas:', err);
      }
    }

    // 3. Draw luxury DeSpace watermark badge
    ctx.save();
    ctx.fillStyle = 'rgba(28, 25, 23, 0.82)';
    ctx.fillRect(16, vh - 52, Math.min(vw - 32, 420), 36);
    ctx.fillStyle = '#FAF7F2';
    ctx.font = '600 13px sans-serif';
    ctx.fillText('DeSpace AR 3D • ' + currentProduct.name + (currentProduct.dimensions ? ' (' + currentProduct.dimensions + ')' : ''), 28, vh - 30);
    ctx.restore();

    var link = document.createElement('a');
    link.download = 'despace-ar-' + (currentProduct.id || '3d') + '.jpg';
    link.href = canvas.toDataURL('image/jpeg', 0.92);
    link.click();
  }

  // =========================================================================
  // STANDARDS-COMPLIANT ISO/IEC 18004 QR CODE GENERATOR & MOBILE URL BUILDER
  // =========================================================================
  function buildMobileArUrl() {
    var net = window.DESPACE_AR_NETWORK || {};
    var tunnelBase = net.tunnelBaseUrl || '';
    var lanBase = net.lanBaseUrl || 'http://192.168.1.123:8090';

    // Update switcher button labels
    if (lanBase) {
      var lanHost = lanBase.replace(/^https?:\/\//, '');
      $('#axQrNetLanBtn').text('📶 Cùng Wi-Fi (' + lanHost + ')');
    }
    if (!tunnelBase) {
      $('#axQrNetTunnelBtn').hide();
      state.qrNetMode = 'lan';
      $('#axQrNetLanBtn').addClass('is-active');
    } else {
      $('#axQrNetTunnelBtn').show();
    }

    var baseOrigin = (state.qrNetMode === 'tunnel' && tunnelBase) ? tunnelBase : lanBase;
    if (!baseOrigin) {
      baseOrigin = window.location.origin;
    }

    // Use compact canonical WordPress product link so the QR code has large, fast-scanning modules
    if (currentProduct.id && parseInt(currentProduct.id, 10) > 0) {
      return baseOrigin.replace(/\/$/, '') + '/?p=' + currentProduct.id + '&open_ar=1&prod=' + currentProduct.id;
    }

    var path = window.location.pathname || '/';
    return baseOrigin.replace(/\/$/, '') + path + '?open_ar=1';
  }

  function generateDesktopQRCode() {
    var $container = $('#axDynamicQrCode');
    if (!$container.length) return;
    $container.empty();

    var arMobileUrl = buildMobileArUrl();
    $('#axArDirectUrlInput').val(arMobileUrl);

    try {
      var qrSvg = createSvgQRCode(arMobileUrl, 180);
      $container.html(qrSvg);
    } catch (e) {
      console.error('[DeSpace QR] Encoder error:', e);
      $container.html('<div style="font-size:12px;color:#9A6745;padding:12px;text-align:center;">Vui lòng sao chép liên kết bên dưới để mở trên điện thoại</div>');
    }
  }

  /**
   * Renders a valid ISO/IEC 18004 QR Code matrix to a crisp SVG with a 4-module quiet zone.
   */
  function createSvgQRCode(text, size) {
    var modules = generateQRMatrixISO(text);
    var count = modules.length;
    var quiet = 4;
    var total = count + quiet * 2;

    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '" width="' + size + '" height="' + size + '" shape-rendering="crispEdges" role="img" aria-label="Mã QR mở Camera AR trên điện thoại">';
    svg += '<rect width="' + total + '" height="' + total + '" fill="#FFFFFF"/>';
    svg += '<path fill="#1C1917" d="';

    var pathData = '';
    for (var r = 0; r < count; r++) {
      for (var c = 0; c < count; c++) {
        if (modules[r][c]) {
          pathData += 'M' + (c + quiet) + ',' + (r + quiet) + 'h1v1h-1z';
        }
      }
    }
    svg += pathData + '"/></svg>';
    return svg;
  }

  /**
   * Pure JS ISO/IEC 18004 Reed-Solomon QR Code Matrix Generator
   * Supports Byte Mode, Error Correction Level L, Versions 1 to 10 (up to 271 bytes)
   */
  function generateQRMatrixISO(text) {
    var utf8 = unescape(encodeURIComponent(text));
    var len = utf8.length;

    var V_TABLE = [
      null,
      [1,  26,  7, 1, []],
      [2,  44, 10, 1, [6, 18]],
      [3,  70, 15, 1, [6, 22]],
      [4, 100, 20, 1, [6, 26]],
      [5, 134, 26, 1, [6, 30]],
      [6, 172, 18, 2, [6, 34]],
      [7, 196, 20, 2, [6, 22, 38]],
      [8, 242, 24, 2, [6, 24, 42]],
      [9, 292, 30, 2, [6, 26, 46]],
      [10, 346, 18, 4, [6, 28, 50]]
    ];

    var ver = 10;
    for (var v = 1; v <= 10; v++) {
      var totalDataCw = V_TABLE[v][1] - V_TABLE[v][2] * V_TABLE[v][3];
      var headerBits = (v <= 9) ? 12 : 20;
      var maxBytes = Math.floor((totalDataCw * 8 - headerBits) / 8);
      if (len <= maxBytes) {
        ver = v;
        break;
      }
    }

    var info = V_TABLE[ver];
    var totalCw = info[1], ecCwPerBlock = info[2], numBlocks = info[3], align = info[4];
    var dataCwCount = totalCw - ecCwPerBlock * numBlocks;

    var bits = [];
    function pushBits(val, n) {
      for (var i = n - 1; i >= 0; i--) bits.push((val >> i) & 1);
    }
    pushBits(4, 4); // Byte mode (0100)
    pushBits(len, ver <= 9 ? 8 : 16);
    for (var i = 0; i < len; i++) pushBits(utf8.charCodeAt(i), 8);

    var maxDataBits = dataCwCount * 8;
    var term = Math.min(4, maxDataBits - bits.length);
    for (var t = 0; t < term; t++) bits.push(0);
    while (bits.length % 8 !== 0) bits.push(0);

    var dataBytes = [];
    for (var bIdx = 0; bIdx < bits.length; bIdx += 8) {
      var byteVal = 0;
      for (var bit = 0; bit < 8; bit++) byteVal = (byteVal << 1) | bits[bIdx + bit];
      dataBytes.push(byteVal);
    }
    var padBytes = [0xEC, 0x11], pIdx = 0;
    while (dataBytes.length < dataCwCount) {
      dataBytes.push(padBytes[pIdx % 2]);
      pIdx++;
    }

    // Galois Field GF(256) with primitive polynomial 0x11D
    var gfExp = new Array(512), gfLog = new Array(256);
    var x = 1;
    for (var e = 0; e < 255; e++) {
      gfExp[e] = x;
      gfLog[x] = e;
      x <<= 1;
      if (x & 0x100) x ^= 0x11D;
    }
    for (var e2 = 255; e2 < 512; e2++) gfExp[e2] = gfExp[e2 - 255];

    function gfMul(a, b) {
      if (a === 0 || b === 0) return 0;
      return gfExp[gfLog[a] + gfLog[b]];
    }

    function rsGeneratorPoly(degree) {
      var g = [1];
      for (var d = 0; d < degree; d++) {
        var next = new Array(g.length + 1).fill(0);
        var root = gfExp[d];
        for (var j = 0; j < g.length; j++) {
          next[j] ^= g[j];
          next[j + 1] ^= gfMul(g[j], root);
        }
        g = next;
      }
      return g;
    }

    function rsEncode(data, ecLen) {
      var gen = rsGeneratorPoly(ecLen);
      var msg = data.concat(new Array(ecLen).fill(0));
      for (var m = 0; m < data.length; m++) {
        var coef = msg[m];
        if (coef !== 0) {
          for (var j = 0; j < gen.length; j++) {
            msg[m + j] ^= gfMul(gen[j], coef);
          }
        }
      }
      return msg.slice(data.length);
    }

    var dataBlocks = [], ecBlocks = [];
    var baseBlockSize = Math.floor(dataCwCount / numBlocks);
    var extraBlocks = dataCwCount % numBlocks;
    var offset = 0;
    for (var b = 0; b < numBlocks; b++) {
      var bLen = baseBlockSize + (b >= numBlocks - extraBlocks ? 1 : 0);
      var block = dataBytes.slice(offset, offset + bLen);
      offset += bLen;
      dataBlocks.push(block);
      ecBlocks.push(rsEncode(block, ecCwPerBlock));
    }

    var finalCw = [];
    var maxBlockLen = baseBlockSize + (extraBlocks > 0 ? 1 : 0);
    for (var cIdx = 0; cIdx < maxBlockLen; cIdx++) {
      for (var b1 = 0; b1 < numBlocks; b1++) {
        if (cIdx < dataBlocks[b1].length) finalCw.push(dataBlocks[b1][cIdx]);
      }
    }
    for (var eIdx = 0; eIdx < ecCwPerBlock; eIdx++) {
      for (var b2 = 0; b2 < numBlocks; b2++) {
        finalCw.push(ecBlocks[b2][eIdx]);
      }
    }

    var size = ver * 4 + 17;
    var mat = [], isFunc = [];
    for (var r = 0; r < size; r++) {
      mat.push(new Array(size).fill(false));
      isFunc.push(new Array(size).fill(false));
    }

    function setFunc(r, c, val) {
      if (r >= 0 && r < size && c >= 0 && c < size) {
        mat[r][c] = !!val;
        isFunc[r][c] = true;
      }
    }

    function placeFinder(row, col) {
      for (var dr = -1; dr <= 7; dr++) {
        for (var dc = -1; dc <= 7; dc++) {
          var r = row + dr, c = col + dc;
          if (r < 0 || r >= size || c < 0 || c >= size) continue;
          var dark = (dr >= 0 && dr <= 6 && (dc === 0 || dc === 6)) ||
                     (dc >= 0 && dc <= 6 && (dr === 0 || dr === 6)) ||
                     (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4);
          setFunc(r, c, dark);
        }
      }
    }
    placeFinder(0, 0);
    placeFinder(0, size - 7);
    placeFinder(size - 7, 0);

    for (var tm = 8; tm < size - 8; tm++) {
      setFunc(6, tm, tm % 2 === 0);
      setFunc(tm, 6, tm % 2 === 0);
    }

    for (var ai = 0; ai < align.length; ai++) {
      for (var aj = 0; aj < align.length; aj++) {
        var ar = align[ai], ac = align[aj];
        if ((ar <= 8 && ac <= 8) || (ar <= 8 && ac >= size - 8) || (ar >= size - 8 && ac <= 8)) continue;
        for (var dr = -2; dr <= 2; dr++) {
          for (var dc = -2; dc <= 2; dc++) {
            setFunc(ar + dr, ac + dc, Math.max(Math.abs(dr), Math.abs(dc)) !== 1);
          }
        }
      }
    }

    for (var fi = 0; fi < 9; fi++) {
      if (!isFunc[8][fi]) setFunc(8, fi, false);
      if (!isFunc[fi][8]) setFunc(fi, 8, false);
    }
    for (var fi2 = 0; fi2 < 8; fi2++) {
      if (!isFunc[8][size - 1 - fi2]) setFunc(8, size - 1 - fi2, false);
      if (!isFunc[size - 1 - fi2][8]) setFunc(size - 1 - fi2, 8, false);
    }
    setFunc(size - 8, 8, true);

    if (ver >= 7) {
      var vRem = ver;
      for (var vi = 0; vi < 12; vi++) vRem = (vRem << 1) ^ ((vRem >> 11) * 0x1F25);
      var vBits = (ver << 12) | vRem;
      for (var vi2 = 0; vi2 < 18; vi2++) {
        var bit = ((vBits >> vi2) & 1) !== 0;
        var va = size - 11 + (vi2 % 3), vb = Math.floor(vi2 / 3);
        setFunc(va, vb, bit);
        setFunc(vb, va, bit);
      }
    }

    var allBits = [];
    for (var cw = 0; cw < finalCw.length; cw++) {
      for (var sh = 7; sh >= 0; sh--) allBits.push((finalCw[cw] >> sh) & 1);
    }
    var bitIdx = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      var upward = ((right + 1) & 2) === 0;
      for (var vert = 0; vert < size; vert++) {
        var row = upward ? (size - 1 - vert) : vert;
        for (var lr = 0; lr < 2; lr++) {
          var col = right - lr;
          if (!isFunc[row][col]) {
            mat[row][col] = bitIdx < allBits.length ? (allBits[bitIdx++] === 1) : false;
          }
        }
      }
    }

    var mask = 0;
    for (var mr = 0; mr < size; mr++) {
      for (var mc = 0; mc < size; mc++) {
        if (!isFunc[mr][mc] && ((mr + mc) % 2 === 0)) mat[mr][mc] = !mat[mr][mc];
      }
    }

    var fmtData = (1 << 3) | mask;
    var rem = fmtData;
    for (var f = 0; f < 10; f++) rem = (rem << 1) ^ ((rem >> 9) * 0x537);
    var fmtBits = ((fmtData << 10) | rem) ^ 0x5412;

    for (var k = 0; k <= 5; k++) mat[8][k] = ((fmtBits >> k) & 1) !== 0;
    mat[8][7] = ((fmtBits >> 6) & 1) !== 0;
    mat[8][8] = ((fmtBits >> 7) & 1) !== 0;
    mat[7][8] = ((fmtBits >> 8) & 1) !== 0;
    for (var k2 = 9; k2 < 15; k2++) mat[14 - k2][8] = ((fmtBits >> k2) & 1) !== 0;

    for (var k3 = 0; k3 < 8; k3++) mat[8][size - 1 - k3] = ((fmtBits >> k3) & 1) !== 0;
    for (var k4 = 8; k4 < 15; k4++) mat[size - 15 + k4][8] = ((fmtBits >> k4) & 1) !== 0;
    mat[size - 8][8] = true;

    return mat;
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
    // QR Network Mode Switcher (Cloud HTTPS vs Local LAN IP)
    $(document).on('click', '.ax-qr-net-btn', function(e) {
      e.preventDefault();
      var mode = $(this).data('net-mode') || 'tunnel';
      state.qrNetMode = mode;
      $('.ax-qr-net-btn').removeClass('is-active');
      $(this).addClass('is-active');
      generateDesktopQRCode();
    });

    // Android AR launch
    $('#axAndroidArBtn').on('click', function(e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');

      if (mv && typeof mv.activateAR === 'function') {
        try {
          mv.activateAR();
          return;
        } catch (err) {
          console.warn('[DeSpace AR] ModelViewer activateAR fallback to Intent:', err);
        }
      }

      if (currentProduct.glbUrl) {
        var intentUrl = 'intent://arvr.google.com/scene-viewer/1.0?file=' + encodeURIComponent(currentProduct.glbUrl) +
          '&mode=ar_only&resizable=false&title=' + encodeURIComponent(currentProduct.name) +
          '#Intent;scheme=https;package=com.google.ar.core;action=android.intent.action.VIEW;end;';
        window.location.href = intentUrl;
      }
    });
  }

  // Modern Web Guidance compliant Copy Button (Invoker Commands + Async Clipboard + legacyCopy fallback)
  function legacyCopy(text) {
    var field = Object.assign(document.createElement('textarea'), { value: text });
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;inset-block-start:0;opacity:0';
    document.body.appendChild(field);
    field.select();
    try { return document.execCommand('copy'); } finally { field.remove(); }
  }

  function reportCopy(button, ok) {
    var tip = button.nextElementSibling;
    if (!tip) return;
    clearTimeout(copyTimers.get(button));
    button.removeAttribute('data-copied');
    button.removeAttribute('data-failed');
    var message = ok ? 'Đã sao chép!' : 'Không thể sao chép';
    if (message === lastCopyMessage) message += '\u00A0';
    lastCopyMessage = message;
    tip.textContent = '';
    void button.offsetWidth;
    button.setAttribute(ok ? 'data-copied' : 'data-failed', '');
    var $textSpan = $(button).find('#axBtnCopyArText');
    if ($textSpan.length) {
      $textSpan.text(ok ? '✓ Đã sao chép!' : 'Sao chép Link AR');
    }
    requestAnimationFrame(function() { tip.textContent = message; });
    copyTimers.set(button, setTimeout(function() {
      button.removeAttribute('data-copied');
      button.removeAttribute('data-failed');
      tip.textContent = '';
      if ($textSpan.length) {
        $textSpan.text('Sao chép Link AR');
      }
    }, 2200));
  }

  async function performCopy(button, target) {
    var text = target.matches('input, textarea') ? target.value : target.innerText.trim();
    if (!text) return;
    try {
      try {
        await navigator.clipboard.writeText(text);
      } catch (err) {
        if (!legacyCopy(text)) throw new Error('Copy failed');
      }
      reportCopy(button, true);
    } catch (err) {
      reportCopy(button, false);
    }
  }

  function bindCopyButton() {
    document.addEventListener('command', function(event) {
      if (event.command && event.command.indexOf('--copy') === 0) {
        performCopy(event.source, event.target);
      }
    }, true);

    if (!('CommandEvent' in window)) {
      document.addEventListener('click', function(event) {
        var button = event.target.closest('button[command^="--copy"]');
        if (!button) return;
        var target = document.getElementById(button.getAttribute('commandfor'));
        if (target) performCopy(button, target);
      });
    }
  }

  function bindAddToCart() {
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
          if (response && response.error) {
            $btn.removeClass('loading');
            $('#axModalCartBtnText').text(originalText);
            if (typeof furnivaData !== 'undefined' && !furnivaData.isLoggedIn && furnivaData.loginUrl) {
              window.location.href = furnivaData.loginUrl;
            } else if (response.product_url) {
              window.location.href = response.product_url;
            }
            return;
          }
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
      // On mobile when scanning QR code, open directly into Live 3D Camera or AR card
      if (Device.isMobile()) {
        state.arSubView = 'live-camera';
      }
      setTimeout(function() {
        var $btn = $('#arViewInRoomBtn').first();
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 300);
    } else if (urlParams.get('open_3d') === '1' || window.location.hash === '#3d') {
      setTimeout(function() {
        var $btn = $('#axView3DModelBtn').first();
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 300);
    }
  }

  $(document).ready(function() {
    init();
  });

})(jQuery);
