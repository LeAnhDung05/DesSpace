/**
 * DeSpace Luxury — 3D Product Viewer & Real Augmented Reality (AR) Engine
 * - Google <model-viewer> 360° PBR 3D Viewer with real 1:1 scale
 * - Live 3D Camera AR Studio (60fps WebRTC Camera + Real 3D .glb Model Viewer)
 * - Apple AR Quick Look for iOS (64-byte aligned multi-material USDZ)
 * - Google Scene Viewer / ARCore for Android (GLB)
 * - Standards-compliant ISO/IEC 18004 Reed-Solomon QR Code Generator
 */
(function ($) {
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
    rafPending: false,
    liveDoorsOpen: false,
    // Spatial Terrain & Obstacle Detection state
    spatialScanEnabled: true,
    spatialTimer: null,
    detectedFloorY: 0.78,
    floorTopNorm: 0.48,
    floorRGB: null,
    floorEdgeBase: 14,
    isObstructed: false,
    obstructionCount: 0,
    clearCount: 0,
    autoSnapDone: false
  };

  var interactive3D = {
    threePromise: null,
    deps: null,
    renderer: null,
    scene: null,
    camera: null,
    controls: null,
    modelRoot: null,
    parts: [],
    partMap: new Map(),
    hoveredPart: null,
    active: false,
    loadToken: 0,
    rafId: 0,
    resizeObserver: null,
    defaultCamPos: null,
    defaultTarget: null,
    lastTime: 0
  };

  var copyTimers = new WeakMap();
  var lastCopyMessage = '';

  // Device Detection
  var Device = {
    isIOS: function () {
      return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    },
    isAndroid: function () {
      return /Android/i.test(navigator.userAgent);
    },
    isMobile: function () {
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
    $(document).on('click', '#axView3DModelBtn, .ax-open-3d-btn', function (e) {
      e.preventDefault();
      e.stopPropagation();
      extractProductData($(this));
      currentProduct.initialTab = '3d';
      openModal();
      return false;
    });

    // Open Real AR Camera directly
    $(document).on('click', '#arViewInRoomBtn, .ax-open-ar-btn, .ax-open-room-modal', function (e) {
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
    stopInteractive3DLoop();
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
    resetInteractive3DUI();

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
    loadInteractive3DStage(currentProduct.glbUrl);
  }

  function onModelLoad() {
    state.modelLoaded = true;
    $('#ax3DLoadingOverlay').fadeOut(180);
    $('#ax3DErrorOverlay').hide();
    $('#ax3DNotConfiguredOverlay').hide();
  }

  // =========================================================================
  // INTERACTIVE ARTICULATED 3D CONTROLLER (Three.js Raycast Doors & Drawers)
  // =========================================================================
  function loadThreeInteractiveDeps() {
    if (interactive3D.deps) {
      return Promise.resolve(interactive3D.deps);
    }
    if (!interactive3D.threePromise) {
      interactive3D.threePromise = Promise.all([
        import('https://esm.sh/three@0.160.0'),
        import('https://esm.sh/three@0.160.0/examples/jsm/controls/OrbitControls.js'),
        import('https://esm.sh/three@0.160.0/examples/jsm/loaders/GLTFLoader.js'),
        import('https://esm.sh/three@0.160.0/examples/jsm/environments/RoomEnvironment.js')
      ]).catch(function () {
        return Promise.all([
          import('https://cdn.jsdelivr.net/npm/three@0.160.0/+esm'),
          import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js/+esm'),
          import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/GLTFLoader.js/+esm'),
          import('https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/environments/RoomEnvironment.js/+esm')
        ]);
      }).then(function (mods) {
        interactive3D.deps = {
          THREE: mods[0],
          OrbitControls: mods[1].OrbitControls,
          GLTFLoader: mods[2].GLTFLoader,
          RoomEnvironment: mods[3].RoomEnvironment
        };
        return interactive3D.deps;
      }).catch(function (err) {
        interactive3D.threePromise = null;
        console.warn('[DeSpace Interactive 3D] Fallback to <model-viewer>:', err);
        return null;
      });
    }
    return interactive3D.threePromise;
  }

  function resetInteractive3DUI() {
    interactive3D.active = false;
    interactive3D.parts = [];
    interactive3D.partMap = new Map();
    interactive3D.hoveredPart = null;
    stopInteractive3DLoop();

    $('#axInteractive3DStage').hide();
    $('#axBtnToggleDoors').hide().removeClass('is-open').attr('aria-pressed', 'false');
    $('#axBtnToggleDoorsText').text('Mở hết cửa');
    $('#ax3DPartHint').hide().text('');
    $('#ax3DPartPills').hide().empty();

    var mv = document.getElementById('axMainModelViewer');
    if (mv) {
      mv.style.visibility = '';
    }
  }

  function stopInteractive3DLoop() {
    if (interactive3D.rafId) {
      cancelAnimationFrame(interactive3D.rafId);
      interactive3D.rafId = 0;
    }
  }

  function ensureInteractiveRenderer(deps) {
    var container = document.getElementById('axInteractive3DStage');
    if (!container) return false;
    var THREE = deps.THREE;

    if (!interactive3D.renderer) {
      var w = Math.max(container.clientWidth || 720, 320);
      var h = Math.max(container.clientHeight || 480, 320);

      var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
      renderer.setSize(w, h);
      renderer.outputColorSpace = THREE.SRGBColorSpace;
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.05;
      renderer.shadowMap.enabled = true;
      renderer.shadowMap.type = THREE.PCFSoftShadowMap;

      container.innerHTML = '';
      container.appendChild(renderer.domElement);

      var scene = new THREE.Scene();
      var pmrem = new THREE.PMREMGenerator(renderer);
      scene.environment = pmrem.fromScene(new deps.RoomEnvironment(), 0.04).texture;
      pmrem.dispose();

      var hemi = new THREE.HemisphereLight(0xfffaf2, 0xd8cfc4, 0.55);
      scene.add(hemi);

      var keyLight = new THREE.DirectionalLight(0xfff9ef, 1.35);
      keyLight.position.set(2.4, 3.8, 3.2);
      keyLight.castShadow = true;
      keyLight.shadow.mapSize.width = 1024;
      keyLight.shadow.mapSize.height = 1024;
      keyLight.shadow.bias = -0.0005;
      scene.add(keyLight);

      var fillLight = new THREE.DirectionalLight(0xf6eee4, 0.55);
      fillLight.position.set(-2.5, 2.0, -1.8);
      scene.add(fillLight);

      var shadowPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(8, 8),
        new THREE.ShadowMaterial({ opacity: 0.16 })
      );
      shadowPlane.rotation.x = -Math.PI / 2;
      shadowPlane.position.y = -0.001;
      shadowPlane.receiveShadow = true;
      scene.add(shadowPlane);

      var camera = new THREE.PerspectiveCamera(34, w / h, 0.05, 50);
      var controls = new deps.OrbitControls(camera, renderer.domElement);
      controls.enableDamping = true;
      controls.dampingFactor = 0.07;
      controls.minPolarAngle = 0.18;
      controls.maxPolarAngle = Math.PI * 0.49;
      controls.autoRotate = state.autoRotate;
      controls.autoRotateSpeed = 1.25;

      interactive3D.renderer = renderer;
      interactive3D.scene = scene;
      interactive3D.camera = camera;
      interactive3D.controls = controls;

      bindInteractiveStagePointerEvents(renderer.domElement, THREE);

      if (typeof ResizeObserver !== 'undefined') {
        interactive3D.resizeObserver = new ResizeObserver(function () {
          resizeInteractive3DStage();
        });
        interactive3D.resizeObserver.observe(container);
      }
    }
    return true;
  }

  function resizeInteractive3DStage() {
    if (!interactive3D.renderer || !interactive3D.camera) return;
    var container = document.getElementById('axInteractive3DStage');
    if (!container) return;
    var w = container.clientWidth;
    var h = container.clientHeight;
    if (w < 10 || h < 10) return;
    interactive3D.camera.aspect = w / h;
    interactive3D.camera.updateProjectionMatrix();
    interactive3D.renderer.setSize(w, h);
  }

  function disposeInteractiveModel() {
    if (interactive3D.modelRoot && interactive3D.scene) {
      interactive3D.scene.remove(interactive3D.modelRoot);
      interactive3D.modelRoot.traverse(function (obj) {
        if (obj.geometry) obj.geometry.dispose();
        if (obj.material) {
          var mats = Array.isArray(obj.material) ? obj.material : [obj.material];
          mats.forEach(function (m) {
            if (m.map) m.map.dispose();
            m.dispose();
          });
        }
      });
      interactive3D.modelRoot = null;
    }
  }

  function findInteractivePartFromObject(obj) {
    var cur = obj;
    while (cur) {
      if (interactive3D.partMap.has(cur)) {
        return interactive3D.partMap.get(cur);
      }
      cur = cur.parent;
    }
    return null;
  }

  function setHoveredPart(part) {
    if (interactive3D.hoveredPart === part) return;

    if (interactive3D.hoveredPart) {
      interactive3D.hoveredPart.meshes.forEach(function (m) {
        if (m.material && m.material.emissive) {
          m.material.emissive.setHex(0x000000);
          m.material.emissiveIntensity = 0;
        }
      });
    }

    interactive3D.hoveredPart = part;
    var canvas = interactive3D.renderer && interactive3D.renderer.domElement;
    if (part) {
      part.meshes.forEach(function (m) {
        if (m.material && m.material.emissive) {
          m.material.emissive.setHex(0x8c5b36);
          m.material.emissiveIntensity = 0.24;
        }
      });
      if (canvas) canvas.style.cursor = 'pointer';
      $('#ax3DPartHint').text('👆 Nhấn để ' + (part.isOpen ? 'đóng ' : 'mở ') + part.label);
    } else {
      if (canvas) canvas.style.cursor = 'grab';
      if (interactive3D.parts.length) {
        $('#ax3DPartHint').text('👆 Chạm trực tiếp vào cánh tủ hoặc ngăn kéo để đóng / mở');
      }
    }
  }

  function updatePartButtonsUI() {
    var allOpen = interactive3D.parts.length > 0 && interactive3D.parts.every(function (pt) { return pt.isOpen; });
    var anyOpen = interactive3D.parts.some(function (pt) { return pt.isOpen; });

    $('#axBtnToggleDoors')
      .toggleClass('is-open', anyOpen)
      .attr('aria-pressed', anyOpen ? 'true' : 'false');
    $('#axBtnToggleDoorsText').text(allOpen ? 'Đóng hết cửa' : (anyOpen ? 'Mở tất cả' : 'Mở hết cửa'));

    interactive3D.parts.forEach(function (pt) {
      if (pt.chipEl) {
        pt.chipEl.classList.toggle('is-open', pt.isOpen);
        pt.chipEl.setAttribute('aria-pressed', pt.isOpen ? 'true' : 'false');
      }
    });
  }

  function toggleInteractivePart(part, forceOpen) {
    if (!part) return;
    part.isOpen = (typeof forceOpen === 'boolean') ? forceOpen : !part.isOpen;
    part.target = part.isOpen ? 1 : 0;
    $('#ax3DPartHint').text((part.isOpen ? '🔓 Đang mở ' : '🔒 Đã đóng ') + part.label + ' — chạm lại để ' + (part.isOpen ? 'đóng' : 'mở'));
    updatePartButtonsUI();
  }

  function bindInteractiveStagePointerEvents(domEl, THREE) {
    var raycaster = new THREE.Raycaster();
    var pointerNDC = new THREE.Vector2();
    var downPos = null;

    function getNDC(clientX, clientY) {
      var rect = domEl.getBoundingClientRect();
      pointerNDC.x = ((clientX - rect.left) / rect.width) * 2 - 1;
      pointerNDC.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    }

    domEl.addEventListener('pointerdown', function (e) {
      downPos = { x: e.clientX, y: e.clientY };
    });

    domEl.addEventListener('pointermove', function (e) {
      if (!interactive3D.active || !interactive3D.modelRoot) return;
      if (e.buttons !== 0) return;
      getNDC(e.clientX, e.clientY);
      raycaster.setFromCamera(pointerNDC, interactive3D.camera);
      var hits = raycaster.intersectObject(interactive3D.modelRoot, true);
      var hitPart = hits.length ? findInteractivePartFromObject(hits[0].object) : null;
      setHoveredPart(hitPart);
    });

    domEl.addEventListener('pointerleave', function () {
      setHoveredPart(null);
    });

    domEl.addEventListener('pointerup', function (e) {
      if (!interactive3D.active || !interactive3D.modelRoot || !downPos) return;
      var dist = Math.hypot(e.clientX - downPos.x, e.clientY - downPos.y);
      downPos = null;
      // Ignore orbit drag gestures (> 7px pointer travel)
      if (dist > 7) return;

      getNDC(e.clientX, e.clientY);
      raycaster.setFromCamera(pointerNDC, interactive3D.camera);
      var hits = raycaster.intersectObject(interactive3D.modelRoot, true);
      if (!hits.length) return;

      var hitPart = findInteractivePartFromObject(hits[0].object);
      if (hitPart) {
        toggleInteractivePart(hitPart);
      }
    });
  }

  function startInteractive3DLoop() {
    if (interactive3D.rafId || !interactive3D.active) return;
    interactive3D.lastTime = performance.now();

    function tick(now) {
      if (!interactive3D.active) {
        interactive3D.rafId = 0;
        return;
      }
      var dt = Math.min(0.05, Math.max(0.001, (now - interactive3D.lastTime) / 1000));
      interactive3D.lastTime = now;
      var alpha = 1 - Math.exp(-9.5 * dt);

      interactive3D.parts.forEach(function (pt) {
        if (Math.abs(pt.progress - pt.target) < 0.0008) {
          pt.progress = pt.target;
        } else {
          pt.progress += (pt.target - pt.progress) * alpha;
        }
        var t = pt.progress;
        if (pt.type === 'hinge_door') {
          pt.node.rotation.y = pt.baseRotY + pt.openValue * t;
        } else if (pt.axis === 'x') {
          pt.node.position.x = pt.basePos.x + pt.openValue * t;
        } else if (pt.axis === 'y') {
          pt.node.position.y = pt.basePos.y + pt.openValue * t;
        } else {
          pt.node.position.z = pt.basePos.z + pt.openValue * t;
        }
      });

      if (interactive3D.controls) {
        interactive3D.controls.autoRotate = state.autoRotate && !interactive3D.hoveredPart;
        interactive3D.controls.update();
      }
      if (interactive3D.renderer && interactive3D.scene && interactive3D.camera) {
        interactive3D.renderer.render(interactive3D.scene, interactive3D.camera);
      }
      interactive3D.rafId = requestAnimationFrame(tick);
    }

    interactive3D.rafId = requestAnimationFrame(tick);
  }

  function inferPartDescriptor(node) {
    var extra = (node.userData && node.userData.despaceInteractive) ? node.userData : null;
    if (extra) {
      return {
        type: extra.type || 'hinge_door',
        label: extra.label || node.name,
        axis: extra.axis || 'y',
        openValue: typeof extra.openValue === 'number' ? extra.openValue : (extra.type === 'drawer' ? 0.22 : -1.62)
      };
    }
    var n = (node.name || '').toUpperCase();
    if (/^(DESPACE_DOOR_HINGE_L|DOOR_HINGE_L|DOOR_L)/.test(n)) {
      return { type: 'hinge_door', label: 'Cánh trái', axis: 'y', openValue: -1.62 };
    }
    if (/^(DESPACE_DOOR_HINGE_R|DOOR_HINGE_R|DOOR_R)/.test(n)) {
      return { type: 'hinge_door', label: 'Cánh phải', axis: 'y', openValue: 1.62 };
    }
    if (/^(DESPACE_DOOR_SLIDE|SLIDE_DOOR)/.test(n)) {
      return { type: 'slide_door', label: 'Cánh lùa', axis: 'x', openValue: 0.42 };
    }
    if (/^(DESPACE_DRAWER|DRAWER)/.test(n)) {
      return { type: 'drawer', label: 'Ngăn kéo', axis: 'z', openValue: 0.22 };
    }
    if (/^(DOOR)/.test(n)) {
      return { type: 'hinge_door', label: 'Cánh tủ', axis: 'y', openValue: -1.62 };
    }
    return null;
  }

  function loadInteractive3DStage(glbUrl) {
    var token = ++interactive3D.loadToken;
    loadThreeInteractiveDeps().then(function (deps) {
      if (!deps || token !== interactive3D.loadToken) return;
      if (!ensureInteractiveRenderer(deps)) return;

      var loader = new deps.GLTFLoader();
      loader.setCrossOrigin('anonymous');
      loader.load(
        glbUrl,
        function (gltf) {
          if (token !== interactive3D.loadToken) return;
          disposeInteractiveModel();
          interactive3D.parts = [];
          interactive3D.partMap = new Map();

          var root = gltf.scene;
          var discovered = [];

          root.traverse(function (obj) {
            if (obj.isMesh) {
              obj.castShadow = true;
              obj.receiveShadow = true;
            }
            var desc = inferPartDescriptor(obj);
            if (desc) {
              var partMeshes = [];
              obj.traverse(function (child) {
                if (child.isMesh && child.material) {
                  child.material = child.material.clone();
                  partMeshes.push(child);
                }
              });
              var partObj = {
                node: obj,
                label: desc.label,
                type: desc.type,
                axis: desc.axis,
                openValue: desc.openValue,
                basePos: obj.position.clone(),
                baseRotY: obj.rotation.y,
                isOpen: false,
                progress: 0,
                target: 0,
                meshes: partMeshes,
                chipEl: null
              };
              discovered.push(partObj);
              interactive3D.partMap.set(obj, partObj);
            }
          });

          interactive3D.parts = discovered;
          interactive3D.modelRoot = root;
          interactive3D.scene.add(root);

          // Fit camera framing cleanly to exact product bounding box on BOTH landscape & portrait viewports
          var THREE = deps.THREE;
          var box = new THREE.Box3().setFromObject(root);
          var size = box.getSize(new THREE.Vector3());
          var center = box.getCenter(new THREE.Vector3());
          var maxDim = Math.max(size.x, size.y, size.z, 0.3);
          var fovRad = (interactive3D.camera.fov * Math.PI) / 180;
          var aspect = Math.max(0.45, interactive3D.camera.aspect || 1.0);
          var vertDist = (Math.max(size.y, size.z) / (2 * Math.tan(fovRad / 2))) * 1.95;
          var horizFovRad = 2 * Math.atan(Math.tan(fovRad / 2) * aspect);
          var horizDist = ((size.x * 1.18) / (2 * Math.tan(horizFovRad / 2))) * 1.38;
          var camDist = Math.max(vertDist, horizDist, maxDim * 1.45);

          interactive3D.defaultTarget = center.clone();
          interactive3D.defaultCamPos = new THREE.Vector3(
            center.x + camDist * 0.28,
            center.y + size.y * 0.24,
            center.z + camDist * 0.96
          );
          interactive3D.camera.position.copy(interactive3D.defaultCamPos);
          interactive3D.controls.target.copy(interactive3D.defaultTarget);
          interactive3D.controls.minDistance = maxDim * 0.75;
          interactive3D.controls.maxDistance = camDist * 2.5;
          interactive3D.controls.update();

          interactive3D.active = true;
          var mv = document.getElementById('axMainModelViewer');
          if (mv) {
            mv.style.visibility = 'hidden';
          }
          $('#axInteractive3DStage').show();

          var $pills = $('#ax3DPartPills').empty();
          if (discovered.length) {
            discovered.forEach(function (pt, idx) {
              var btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'ax-part-chip';
              btn.setAttribute('aria-pressed', 'false');
              btn.textContent = pt.label || ('Phần ' + (idx + 1));
              btn.addEventListener('click', function () {
                toggleInteractivePart(pt);
              });
              pt.chipEl = btn;
              $pills.append(btn);
            });
            $('#axBtnToggleDoors').show();
            $('#ax3DPartPills').show();
            $('#ax3DPartHint').text('👆 Chạm trực tiếp vào cánh tủ hoặc ngăn kéo để đóng / mở').show();
          } else {
            $('#axBtnToggleDoors').hide();
            $('#ax3DPartPills').hide();
            $('#ax3DPartHint').hide();
          }

          resizeInteractive3DStage();
          startInteractive3DLoop();
          $('#ax3DLoadingOverlay').fadeOut(160);
        },
        undefined,
        function () {
          resetInteractive3DUI();
        }
      );
    });
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
      $('#axModalHeaderSubtitle').text('Quan sát chi tiết mô hình 3D 360° — chạm trực tiếp từng cánh cửa hoặc ngăn kéo để đóng/mở');
      if (interactive3D.modelRoot) {
        interactive3D.active = true;
        resizeInteractive3DStage();
        startInteractive3DLoop();
      }
    } else {
      stopInteractive3DLoop();
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
  // LIVE 3D CAMERA AR STUDIO + SPATIAL FLOOR & OBSTACLE DETECTION ENGINE
  // =========================================================================
  var spatialAnalyzer = {
    canvas: null,
    ctx: null,
    w: 160,
    h: 90,
    ensureCanvas: function () {
      if (!this.canvas) {
        this.canvas = document.createElement('canvas');
        this.canvas.width = this.w;
        this.canvas.height = this.h;
        this.ctx = this.canvas.getContext('2d', { willReadFrequently: true });
      }
      return this.ctx;
    },
    captureFrame: function () {
      var viewport = document.getElementById('axLiveCamViewport');
      var video = document.getElementById('axLiveArVideo');
      var fallbackImg = document.getElementById('axLiveArFallbackRoom');
      var src = (!state.usingFallbackRoom && video && video.readyState >= 2 && video.videoWidth > 0) ? video : fallbackImg;
      if (!viewport || !src) return null;
      var srcW = src.videoWidth || src.naturalWidth || 0;
      var srcH = src.videoHeight || src.naturalHeight || 0;
      if (!srcW || !srcH) return null;
      var vw = viewport.clientWidth || 960;
      var vh = viewport.clientHeight || 540;
      var scaleCover = Math.max(vw / srcW, vh / srcH);
      var drawW = srcW * scaleCover;
      var drawH = srcH * scaleCover;
      var offX = (vw - drawW) / 2;
      var offY = (vh - drawH) / 2;

      var ctx = this.ensureCanvas();
      var sx = this.w / vw;
      var sy = this.h / vh;
      try {
        ctx.clearRect(0, 0, this.w, this.h);
        ctx.drawImage(src, offX * sx, offY * sy, drawW * sx, drawH * sy);
        return ctx.getImageData(0, 0, this.w, this.h);
      } catch (e) {
        return null;
      }
    },
    scanFloorPlane: function (snapToFloor) {
      var img = this.captureFrame();
      var viewport = document.getElementById('axLiveCamViewport');
      if (!img || !viewport) return false;
      var d = img.data;
      var w = this.w;
      var h = this.h;

      var rowStats = [];
      for (var y = Math.floor(h * 0.45); y < h - 3; y++) {
        var edgeSum = 0;
        var sumR = 0, sumG = 0, sumB = 0;
        var count = 0;
        for (var x = Math.floor(w * 0.14); x < Math.floor(w * 0.86); x++) {
          var i = (y * w + x) * 4;
          var r = d[i], g = d[i + 1], b = d[i + 2];
          var rR = d[i + 4], gR = d[i + 5], bR = d[i + 6];
          var iDown = ((y + 1) * w + x) * 4;
          var gx = Math.abs(r - rR) + Math.abs(g - gR) + Math.abs(b - bR);
          var gy = Math.abs(r - d[iDown]) + Math.abs(g - d[iDown + 1]) + Math.abs(b - d[iDown + 2]);
          edgeSum += (gx + gy) / 6;
          sumR += r; sumG += g; sumB += b;
          count++;
        }
        var meanR = sumR / Math.max(1, count);
        var meanG = sumG / Math.max(1, count);
        var meanB = sumB / Math.max(1, count);
        var varSum = 0;
        for (var x2 = Math.floor(w * 0.14); x2 < Math.floor(w * 0.86); x2++) {
          var i2 = (y * w + x2) * 4;
          varSum += Math.abs(d[i2] - meanR) + Math.abs(d[i2 + 1] - meanG) + Math.abs(d[i2 + 2] - meanB);
        }
        rowStats.push({
          y: y,
          edge: edgeSum / Math.max(1, count),
          variance: (varSum / 3) / Math.max(1, count),
          rgb: [meanR, meanG, meanB]
        });
      }

      if (!rowStats.length) return false;
      // Pick the smoothest, lowest-clutter contiguous horizontal band in the lower half as the room floor
      var bestBand = rowStats[Math.floor(rowStats.length * 0.55)];
      var bestScore = Infinity;
      for (var k = 0; k < rowStats.length; k++) {
        var st = rowStats[k];
        var yBias = 1.0 - (st.y / h) * 0.35; // Favor genuine floor zone in lower camera half
        var score = (st.edge * 1.4 + st.variance * 0.55) * yBias;
        if (score < bestScore) {
          bestScore = score;
          bestBand = st;
        }
      }

      state.floorRGB = bestBand.rgb;
      state.floorEdgeBase = Math.max(8, Math.min(28, bestBand.edge));
      state.floorTopNorm = Math.max(0.42, Math.min(0.64, (bestBand.y / h) - 0.16));
      state.detectedFloorY = Math.max(0.64, Math.min(0.90, bestBand.y / h));

      if (snapToFloor) {
        // Find clearest X column along detected floor band (lowest edge + smallest color jump from floor)
        var bestColX = 0.5;
        var lowestColCost = Infinity;
        var yBandStart = Math.max(1, Math.floor(state.detectedFloorY * h) - 6);
        var yBandEnd = Math.min(h - 2, yBandStart + 10);
        for (var cx = 0.24; cx <= 0.76; cx += 0.04) {
          var colCost = 0;
          var x0 = Math.max(1, Math.floor((cx - 0.11) * w));
          var x1 = Math.min(w - 2, Math.floor((cx + 0.11) * w));
          var nPx = 0;
          for (var yy = yBandStart; yy <= yBandEnd; yy++) {
            for (var xx = x0; xx <= x1; xx++) {
              var idx = (yy * w + xx) * 4;
              var gx = Math.abs(d[idx] - d[idx + 4]) + Math.abs(d[idx + 1] - d[idx + 5]) + Math.abs(d[idx + 2] - d[idx + 6]);
              var cDiff = (Math.abs(d[idx] - state.floorRGB[0]) + Math.abs(d[idx + 1] - state.floorRGB[1]) + Math.abs(d[idx + 2] - state.floorRGB[2])) / 3;
              colCost += gx * 0.45 + cDiff * 0.7;
              nPx++;
            }
          }
          var avgCol = colCost / Math.max(1, nPx);
          if (avgCol < lowestColCost) {
            lowestColCost = avgCol;
            bestColX = cx;
          }
        }
        var vw = viewport.clientWidth || 960;
        var vh = viewport.clientHeight || 540;
        var objHalfH = 140 * state.liveScale; // Approx visual foot offset from center of 340px viewer
        state.livePos.x = (bestColX - 0.5) * vw;
        state.livePos.y = Math.min(vh * 0.36, Math.max(-vh * 0.05, state.detectedFloorY * vh - (vh * 0.50) - objHalfH + 56));
        scheduleLiveTransformUpdate();
      }
      return true;
    },
    evaluateFootprint: function () {
      if (!state.spatialScanEnabled) {
        updateSpatialUI(false, 'clear', 'Chế độ đặt tự do (Đã tắt cảm biến)');
        return;
      }
      var img = this.captureFrame();
      var viewport = document.getElementById('axLiveCamViewport');
      if (!img || !viewport) return;

      if (!state.floorRGB) {
        this.scanFloorPlane(false);
      }
      var vw = viewport.clientWidth || 960;
      var vh = viewport.clientHeight || 540;
      var depthFactor = getDepthPerspectiveScale(vh);
      var effScale = state.liveScale * depthFactor;

      // Cabinet center & base floor-contact rectangle normalized [0..1]
      var cxNorm = (vw * 0.5 + state.livePos.x) / vw;
      var footYNorm = ((vh * 0.5 + state.livePos.y) + 125 * effScale) / vh;
      var halfWNorm = Math.min(0.24, Math.max(0.08, (115 * effScale) / vw));

      // Check 1: Raised off floor plane (hovering on upper wall / mid-air / ceiling)
      if (footYNorm < state.floorTopNorm) {
        applyObstacleDecision(true, 'off_floor');
        return;
      }

      // Check 2: Analyze pixels across furniture base footprint + foreground body clearance
      var d = img.data;
      var w = this.w;
      var h = this.h;
      var x0 = Math.max(1, Math.floor((cxNorm - halfWNorm) * w));
      var x1 = Math.min(w - 2, Math.floor((cxNorm + halfWNorm) * w));
      var yBase = Math.max(2, Math.min(h - 2, Math.floor(footYNorm * h)));
      var yTop = Math.max(1, yBase - Math.max(6, Math.floor(h * 0.14)));

      var edgeAccum = 0;
      var colorDistAccum = 0;
      var highContrastPx = 0;
      var n = 0;
      var fR = state.floorRGB ? state.floorRGB[0] : 160;
      var fG = state.floorRGB ? state.floorRGB[1] : 145;
      var fB = state.floorRGB ? state.floorRGB[2] : 128;

      for (var yy = yTop; yy <= yBase; yy++) {
        for (var xx = x0; xx <= x1; xx++) {
          var idx = (yy * w + xx) * 4;
          var r = d[idx], g = d[idx + 1], b = d[idx + 2];
          var gx = (Math.abs(r - d[idx + 4]) + Math.abs(g - d[idx + 5]) + Math.abs(b - d[idx + 6])) / 3;
          var iDown = ((yy + 1) * w + xx) * 4;
          var gy = (Math.abs(r - d[iDown]) + Math.abs(g - d[iDown + 1]) + Math.abs(b - d[iDown + 2])) / 3;
          var gMag = gx + gy;
          edgeAccum += gMag;
          if (gMag > 26) highContrastPx++;

          var cDiff = (Math.abs(r - fR) + Math.abs(g - fG) + Math.abs(b - fB)) / 3;
          colorDistAccum += cDiff;
          n++;
        }
      }

      if (n < 8) {
        applyObstacleDecision(true, 'off_bounds');
        return;
      }

      var meanEdge = edgeAccum / n;
      var meanColorDiff = colorDistAccum / n;
      var clutterRatio = highContrastPx / n;

      // Obstacle triggered when strong object edges OR significant surface contrast vs floor plane appear
      var edgeThreshold = Math.max(24, state.floorEdgeBase * 1.95);
      var obstructed = (meanEdge > edgeThreshold && clutterRatio > 0.16) || meanColorDiff > 44 || clutterRatio > 0.30;
      applyObstacleDecision(obstructed, obstructed ? 'obstacle' : 'clear');
    }
  };

  function getDepthPerspectiveScale(vh) {
    if (!vh || vh <= 0) return 1.0;
    var normY = Math.max(-0.5, Math.min(0.5, state.livePos.y / vh));
    return Math.max(0.76, Math.min(1.24, 0.96 + normY * 0.42));
  }

  function applyObstacleDecision(obstructed, reason) {
    if (obstructed) {
      state.obstructionCount++;
      state.clearCount = 0;
      if (state.obstructionCount >= 2 || state.isDraggingLive) {
        state.isObstructed = true;
      }
    } else {
      state.clearCount++;
      state.obstructionCount = 0;
      if (state.clearCount >= 2 || state.isDraggingLive) {
        state.isObstructed = false;
      }
    }
    var msg = '✓ Mặt sàn trống — Đặt vừa vị trí này';
    if (state.isObstructed) {
      msg = (reason === 'off_floor')
        ? '⚠ Đang ngoài mặt sàn — Kéo xuống nền nhà'
        : '⚠ Gặp vật cản phía trước — Kéo ra khoảng sàn trống';
    }
    updateSpatialUI(state.isObstructed, reason, msg);
  }

  function updateSpatialUI(obstructed, reason, message) {
    var $wrap = $('#axLiveArObjectWrap');
    var $mv = $('#axLiveArModelViewer');
    var $status = $('#axLiveSpatialStatus');
    var $dot = $('#axLiveSpatialDot');

    $wrap.toggleClass('is-obstructed', !!obstructed).toggleClass('is-floor-clear', !obstructed);
    $mv.toggleClass('is-obstructed', !!obstructed);
    $dot.toggleClass('is-obstructed', !!obstructed);
    $status.removeClass('is-obstructed is-scanning').toggleClass('is-obstructed', !!obstructed).text(message);
  }

  function startSpatialLoop() {
    stopSpatialLoop();
    state.floorRGB = null;
    state.obstructionCount = 0;
    state.clearCount = 0;
    state.spatialTimer = setInterval(function () {
      if (state.activeTab === 'ar' && state.arSubView === 'live-camera') {
        spatialAnalyzer.evaluateFootprint();
      }
    }, 130);
  }

  function stopSpatialLoop() {
    if (state.spatialTimer) {
      clearInterval(state.spatialTimer);
      state.spatialTimer = null;
    }
  }

  function startLiveCameraAR() {
    var liveMv = document.getElementById('axLiveArModelViewer');
    state.liveDoorsOpen = false;
    state.autoSnapDone = false;
    $('#axLiveToggleDoorsBtn').hide().removeClass('active').attr('aria-pressed', 'false').find('span').text('🚪 Mở Tủ');

    if (liveMv && currentProduct.glbUrl) {
      if (liveMv.getAttribute('src') !== currentProduct.glbUrl) {
        liveMv.src = currentProduct.glbUrl;
        liveMv.setAttribute('src', currentProduct.glbUrl);
      }
      if (currentProduct.usdzUrl) {
        liveMv.setAttribute('ios-src', currentProduct.usdzUrl);
      }
      var syncLiveAnimBtn = function () {
        var anims = liveMv.availableAnimations || [];
        if (anims.length > 0) {
          liveMv.animationName = anims[0];
          liveMv.pause();
          liveMv.currentTime = 0;
          $('#axLiveToggleDoorsBtn').show();
        }
      };
      liveMv.removeEventListener('load', liveMv._despaceAnimLoad || function () { });
      liveMv._despaceAnimLoad = syncLiveAnimBtn;
      liveMv.addEventListener('load', syncLiveAnimBtn);
      if (liveMv.loaded) {
        syncLiveAnimBtn();
      }
    }

    resetLiveTransform();
    setLiveGestureMode('rotate');
    $('#axLiveSpatialStatus').removeClass('is-obstructed').addClass('is-scanning').text('🔍 Đang nhận diện mặt sàn nhà...');

    var video = document.getElementById('axLiveArVideo');
    var $fallbackBg = $('#axLiveArFallbackRoom');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      enableFallbackRoomBackdrop();
      startSpatialLoop();
      setTimeout(function () { spatialAnalyzer.scanFloorPlane(true); }, 350);
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
      .then(function (stream) {
        state.cameraStream = stream;
        state.usingFallbackRoom = false;
        $fallbackBg.hide();
        if (video) {
          video.srcObject = stream;
          video.style.display = 'block';
          video.play().catch(function () { });
          video.onloadeddata = function () {
            startSpatialLoop();
            setTimeout(function () {
              if (!state.autoSnapDone) {
                state.autoSnapDone = true;
                spatialAnalyzer.scanFloorPlane(true);
              }
            }, 650);
          };
        } else {
          startSpatialLoop();
        }
      })
      .catch(function (err) {
        console.warn('[DeSpace Live AR] Camera stream fallback to room backdrop:', err);
        enableFallbackRoomBackdrop();
        startSpatialLoop();
        setTimeout(function () { spatialAnalyzer.scanFloorPlane(true); }, 350);
      });
  }

  function enableFallbackRoomBackdrop() {
    state.usingFallbackRoom = true;
    $('#axLiveArVideo').hide();
    $('#axLiveArFallbackRoom').show();
  }

  function stopLiveCameraStream() {
    stopSpatialLoop();
    if (state.cameraStream) {
      state.cameraStream.getTracks().forEach(function (track) {
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
      $('#axLiveArHint').text('Chế độ Xoay 3D: Vuốt lên tủ để xoay 360° • Kéo vùng trống hoặc giữ Shift để di chuyển tự do');
    } else {
      if (liveMv) {
        liveMv.removeAttribute('camera-controls');
        liveMv.style.pointerEvents = 'none';
      }
      $wrap.addClass('is-move-mode');
      $('#axLiveArHint').text('Di Chuyển Tự Do: Kéo mô hình khắp phòng — Tự động báo ĐỎ nếu chạm vật cản');
    }
  }

  function scheduleLiveTransformUpdate() {
    if (state.rafPending) return;
    state.rafPending = true;
    requestAnimationFrame(function () {
      state.rafPending = false;
      var viewport = document.getElementById('axLiveCamViewport');
      var vh = viewport ? viewport.clientHeight : 540;
      var effScale = state.liveScale * getDepthPerspectiveScale(vh);
      var wrap = document.getElementById('axLiveArObjectWrap');
      if (wrap) {
        wrap.style.transform = 'translate3d(' + state.livePos.x.toFixed(1) + 'px, ' + state.livePos.y.toFixed(1) + 'px, 0) scale(' + effScale.toFixed(3) + ')';
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
    $(document).on('click', '.ax-trigger-live-cam', function (e) {
      e.preventDefault();
      if (state.activeTab !== 'ar') {
        switchTab('ar');
      }
      switchArSubView('live-camera');
    });

    // Sub-mode switcher buttons
    $('#axSubModeQrBtn').on('click', function (e) {
      e.preventDefault();
      switchArSubView('handoff');
    });

    $('#axSubModeLiveCamBtn').on('click', function (e) {
      e.preventDefault();
      switchArSubView('live-camera');
    });

    // Gesture mode toggle (Rotate 360° vs Move on Floor)
    $('#axLiveModeRotateBtn').on('click', function (e) {
      e.preventDefault();
      setLiveGestureMode('rotate');
    });

    $('#axLiveModeMoveBtn').on('click', function (e) {
      e.preventDefault();
      setLiveGestureMode('move');
    });

    // Free-roam dragging across the ENTIRE viewport (works in Move mode AND anywhere outside model / with Shift in Rotate mode)
    var viewport = document.getElementById('axLiveCamViewport');
    if (viewport) {
      viewport.addEventListener('pointerdown', function (e) {
        if (e.target.closest('.despace-stage-mode-bar, .ax-live-ar-pill')) return;
        var clickedModel = !!e.target.closest('#axLiveArModelViewer');
        if (state.gestureMode === 'rotate' && clickedModel && !e.shiftKey) return;
        state.isDraggingLive = true;
        state.dragStart = { x: e.clientX, y: e.clientY };
        state.posStart = { x: state.livePos.x, y: state.livePos.y };
        $('#axLiveArObjectWrap').addClass('is-dragging');
        if (viewport.setPointerCapture) {
          try { viewport.setPointerCapture(e.pointerId); } catch (err) { }
        }
      });

      viewport.addEventListener('pointermove', function (e) {
        if (!state.isDraggingLive) return;
        var dx = e.clientX - state.dragStart.x;
        var dy = e.clientY - state.dragStart.y;
        var maxOffsetX = viewport.clientWidth * 0.56;
        var maxOffsetY = viewport.clientHeight * 0.50;
        state.livePos.x = Math.max(-maxOffsetX, Math.min(maxOffsetX, state.posStart.x + dx));
        state.livePos.y = Math.max(-maxOffsetY, Math.min(maxOffsetY, state.posStart.y + dy));
        scheduleLiveTransformUpdate();
        spatialAnalyzer.evaluateFootprint();
      });

      var endDrag = function () {
        if (!state.isDraggingLive) return;
        state.isDraggingLive = false;
        $('#axLiveArObjectWrap').removeClass('is-dragging');
        spatialAnalyzer.evaluateFootprint();
      };
      viewport.addEventListener('pointerup', endDrag);
      viewport.addEventListener('pointercancel', endDrag);

      // 2-finger pinch-to-zoom on touch screens
      viewport.addEventListener('touchstart', function (e) {
        if (e.touches && e.touches.length === 2) {
          var dx = e.touches[0].clientX - e.touches[1].clientX;
          var dy = e.touches[0].clientY - e.touches[1].clientY;
          state.pinchStartDist = Math.hypot(dx, dy);
          state.pinchStartScale = state.liveScale;
        }
      }, { passive: true });

      viewport.addEventListener('touchmove', function (e) {
        if (e.touches && e.touches.length === 2 && state.pinchStartDist > 10) {
          var dx = e.touches[0].clientX - e.touches[1].clientX;
          var dy = e.touches[0].clientY - e.touches[1].clientY;
          var dist = Math.hypot(dx, dy);
          var ratio = dist / state.pinchStartDist;
          state.liveScale = Math.max(0.5, Math.min(1.8, state.pinchStartScale * ratio));
          scheduleLiveTransformUpdate();
          spatialAnalyzer.evaluateFootprint();
        }
      }, { passive: true });
    }

    // Re-scan room floor & snap furniture onto detected floor surface
    $('#axLiveAutoFloorBtn').on('click', function (e) {
      e.preventDefault();
      spatialAnalyzer.scanFloorPlane(true);
      spatialAnalyzer.evaluateFootprint();
    });

    // Toggle real-time obstacle scanning & red collision alert
    $('#axLiveSpatialToggleBtn').on('click', function (e) {
      e.preventDefault();
      state.spatialScanEnabled = !state.spatialScanEnabled;
      $(this).toggleClass('active', state.spatialScanEnabled).attr('aria-pressed', state.spatialScanEnabled ? 'true' : 'false');
      spatialAnalyzer.evaluateFootprint();
    });

    // Scale controls
    $('#axLiveScaleSlider').on('input', function () {
      var val = parseInt($(this).val(), 10) || 100;
      state.liveScale = Math.max(0.5, Math.min(1.8, val / 100));
      scheduleLiveTransformUpdate();
    });

    $('#axLiveScaleDownBtn').on('click', function (e) {
      e.preventDefault();
      state.liveScale = Math.max(0.5, state.liveScale - 0.1);
      scheduleLiveTransformUpdate();
    });

    $('#axLiveScaleUpBtn').on('click', function (e) {
      e.preventDefault();
      state.liveScale = Math.min(1.8, state.liveScale + 0.1);
      scheduleLiveTransformUpdate();
    });

    $('#axLiveResetTransformBtn').on('click', function (e) {
      e.preventDefault();
      resetLiveTransform();
    });

    // Lighting tone presets
    $('.ax-live-tone-btn').on('click', function (e) {
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
    $('#axLiveFlipCamBtn').on('click', function (e) {
      e.preventDefault();
      state.facingMode = (state.facingMode === 'environment') ? 'user' : 'environment';
      startLiveCameraAR();
    });

    // Custom room photo upload into Live 3D AR stage
    $('#axLiveRoomPhotoInput').on('change', function (e) {
      var file = e.target.files && e.target.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        stopLiveCameraStream();
        state.usingFallbackRoom = true;
        $('#axLiveArVideo').hide();
        $('#axLiveArFallbackRoom').attr('src', ev.target.result).show();
      };
      reader.readAsDataURL(file);
    });

    // Open/Close All Doors in Live Camera AR (<model-viewer> DeSpace_OpenAll animation)
    $('#axLiveToggleDoorsBtn').on('click', function (e) {
      e.preventDefault();
      var liveMv = document.getElementById('axLiveArModelViewer');
      if (!liveMv) return;
      var anims = liveMv.availableAnimations || [];
      if (!anims.length) return;
      liveMv.animationName = anims[0];
      state.liveDoorsOpen = !state.liveDoorsOpen;
      liveMv.timeScale = state.liveDoorsOpen ? 1 : -1;
      if (typeof liveMv.play === 'function') {
        liveMv.play({ repetitions: 1 });
      }
      $(this).toggleClass('active', state.liveDoorsOpen).attr('aria-pressed', state.liveDoorsOpen ? 'true' : 'false');
      $(this).find('span').text(state.liveDoorsOpen ? '🔒 Đóng Tủ' : '🚪 Mở Tủ');
    });

    // Snapshot capture (composite live video/room + 3D WebGL model-viewer)
    $('#axLiveSnapshotBtn').on('click', function (e) {
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
        await new Promise(function (resolve) {
          var mvImg = new Image();
          mvImg.onload = function () {
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

    var net = window.DESPACE_AR_NETWORK || {};
    var $status = $('#axQrNetStatus');
    var arMobileUrl = buildMobileArUrl();
    $('#axArDirectUrlInput').val(arMobileUrl);

    try {
      var qrSvg = createSvgQRCode(arMobileUrl, 180);
      $container.html(qrSvg);
    } catch (e) {
      console.error('[DeSpace QR] Encoder error:', e);
      $container.html('<div style="font-size:12px;color:#9A6745;padding:12px;text-align:center;">Vui lòng sao chép liên kết bên dưới để mở trên điện thoại</div>');
    }

    // Live health verification when using Cloudflare HTTPS tunnel; auto-fallback to LAN IP if tunnel is dead
    if (state.qrNetMode === 'tunnel' && net.tunnelBaseUrl && typeof fetch === 'function') {
      $status.removeClass('is-ok is-warn').text('Đang kiểm tra kết nối Tunnel HTTPS...');
      var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, 2500) : null;
      fetch(net.tunnelBaseUrl.replace(/\/$/, '') + '/wp-json/despace/v1/desktop-3d/health', {
        mode: 'cors',
        signal: ctrl ? ctrl.signal : undefined
      }).then(function (resp) {
        if (timer) clearTimeout(timer);
        if (resp && resp.ok) {
          $status.removeClass('is-warn').addClass('is-ok').text('✓ Tunnel HTTPS sẵn sàng — Mở AR từ mọi mạng');
        } else {
          throw new Error('HTTP ' + (resp ? resp.status : 'error'));
        }
      }).catch(function () {
        if (timer) clearTimeout(timer);
        if (state.qrNetMode === 'tunnel' && net.lanBaseUrl) {
          state.qrNetMode = 'lan';
          $('.ax-qr-net-btn').removeClass('is-active');
          $('#axQrNetLanBtn').addClass('is-active');
          var lanUrl = buildMobileArUrl();
          $('#axArDirectUrlInput').val(lanUrl);
          try { $container.html(createSvgQRCode(lanUrl, 180)); } catch (err) { }
          $status.removeClass('is-ok').addClass('is-warn').text('⚠ Tunnel tạm tắt — Đã tự chuyển sang Wi-Fi LAN');
        }
      });
    } else if (net.lanBaseUrl) {
      $status.removeClass('is-warn').addClass('is-ok').text('📶 Chế độ Wi-Fi nội bộ: điện thoại & máy tính dùng chung Wi-Fi');
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
      [1, 26, 7, 1, []],
      [2, 44, 10, 1, [6, 18]],
      [3, 70, 15, 1, [6, 22]],
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
    $('#axRoomModalClose, #axRoomModalBackdrop').on('click', function (e) {
      e.preventDefault();
      closeModal();
    });

    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && $('#ax-room-visualizer-modal').is(':visible')) {
        closeModal();
      }
    });

    $('#axBtnRetryLoad3D').on('click', function () {
      setupModelViewer();
    });
  }

  function bindTabControls() {
    $('#axTabBtn3D').on('click', function (e) {
      e.preventDefault();
      switchTab('3d');
    });

    $('#axTabBtnAR').on('click', function (e) {
      e.preventDefault();
      switchTab('ar');
    });
  }

  function bind3DToolbar() {
    $('#axBtnToggleDoors').on('click', function (e) {
      e.preventDefault();
      if (!interactive3D.parts.length) return;
      var allOpen = interactive3D.parts.every(function (pt) { return pt.isOpen; });
      var targetOpen = !allOpen;
      interactive3D.parts.forEach(function (pt, idx) {
        setTimeout(function () {
          toggleInteractivePart(pt, targetOpen);
        }, idx * 55);
      });
    });

    $('#axBtnAutoRotate').on('click', function (e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');
      state.autoRotate = !state.autoRotate;
      if (mv) {
        mv.autoRotate = state.autoRotate;
      }
      if (interactive3D.controls) {
        interactive3D.controls.autoRotate = state.autoRotate;
      }
      $(this).toggleClass('is-active', state.autoRotate);
    });

    $('#axBtnResetCamera').on('click', function (e) {
      e.preventDefault();
      var mv = document.getElementById('axMainModelViewer');
      if (mv) {
        mv.cameraOrbit = '0deg 75deg 105%';
        if (typeof mv.jumpCameraToGoal === 'function') {
          mv.jumpCameraToGoal();
        }
      }
      if (interactive3D.camera && interactive3D.controls && interactive3D.defaultCamPos && interactive3D.defaultTarget) {
        interactive3D.camera.position.copy(interactive3D.defaultCamPos);
        interactive3D.controls.target.copy(interactive3D.defaultTarget);
        interactive3D.controls.update();
      }
    });
  }

  function bindARActions() {
    // QR Network Mode Switcher (Cloud HTTPS vs Local LAN IP)
    $(document).on('click', '.ax-qr-net-btn', function (e) {
      e.preventDefault();
      var mode = $(this).data('net-mode') || 'tunnel';
      state.qrNetMode = mode;
      $('.ax-qr-net-btn').removeClass('is-active');
      $(this).addClass('is-active');
      generateDesktopQRCode();
    });

    // Android AR launch
    $('#axAndroidArBtn').on('click', function (e) {
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
    requestAnimationFrame(function () { tip.textContent = message; });
    copyTimers.set(button, setTimeout(function () {
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
    document.addEventListener('command', function (event) {
      if (event.command && event.command.indexOf('--copy') === 0) {
        performCopy(event.source, event.target);
      }
    }, true);

    if (!('CommandEvent' in window)) {
      document.addEventListener('click', function (event) {
        var button = event.target.closest('button[command^="--copy"]');
        if (!button) return;
        var target = document.getElementById(button.getAttribute('commandfor'));
        if (target) performCopy(button, target);
      });
    }
  }

  function bindAddToCart() {
    $('#axModalQtyMinus').on('click', function (e) {
      e.preventDefault();
      var $inp = $('#axModalQtyInput');
      var val = parseInt($inp.val(), 10) || 1;
      if (val > 1) $inp.val(val - 1);
    });

    $('#axModalQtyPlus').on('click', function (e) {
      e.preventDefault();
      var $inp = $('#axModalQtyInput');
      var val = parseInt($inp.val(), 10) || 1;
      if (val < 99) $inp.val(val + 1);
    });

    $('#axModalAddToCartBtn').on('click', function (e) {
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
        success: function (response) {
          if (response && response.error) {
            $btn.removeClass('loading');
            $('#axModalCartBtnText').text(originalText);
            if (response.product_url) {
              window.location.href = response.product_url;
            }
            return;
          }
          $btn.removeClass('loading').addClass('added');
          $('#axModalCartBtnText').text('✓ Đã thêm vào giỏ!');
          if (response && response.fragments) {
            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
          }
          setTimeout(function () {
            $btn.removeClass('added');
            $('#axModalCartBtnText').text(originalText);
          }, 3000);
        },
        error: function () {
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
      var prodId = urlParams.get('prod') || '';
      if (Device.isMobile()) {
        state.arSubView = 'live-camera';
      }
      setTimeout(function () {
        var $btn = $('#arViewInRoomBtn').first();
        if (!$btn.length && prodId) {
          $btn = $('.ax-open-ar-btn[data-product-id="' + prodId + '"], .ax-open-room-modal[data-product-id="' + prodId + '"]').first();
        }
        if (!$btn.length) {
          $btn = $('.ax-open-ar-btn, .ax-open-room-modal').first();
        }
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 320);
    } else if (urlParams.get('open_3d') === '1' || window.location.hash === '#3d') {
      setTimeout(function () {
        var $btn = $('#axView3DModelBtn').first();
        if ($btn.length) {
          $btn.trigger('click');
        }
      }, 300);
    }
  }

  $(document).ready(function () {
    init();
  });

})(jQuery);
