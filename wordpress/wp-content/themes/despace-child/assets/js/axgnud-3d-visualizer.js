/**
 * AXGNUD Studio — 3D Model-Viewer & Smart Cutout Engine for Room Visualizer
 * Provides procedural PBR 3D furniture mesh generator & HTML5 transparent cutout engine.
 */

// 0. Polyfill an toàn cho WebXR trong môi trường HTTP / Insecure / Headless
if (typeof navigator !== 'undefined' && !navigator.xr) {
  try {
    navigator.xr = {
      isSessionSupported: function() { return Promise.resolve(false); },
      requestSession: function() { return Promise.reject(new Error('WebXR not available')); },
      addEventListener: function() {},
      removeEventListener: function() {}
    };
  } catch (e) {}
}

// 1. Nạp thư viện Google <model-viewer> nếu chưa có
if (typeof customElements !== 'undefined' && !customElements.get('model-viewer')) {
  var mvScript = document.createElement('script');
  mvScript.type = 'module';
  mvScript.src = 'https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js';
  document.head.appendChild(mvScript);
}

// Global cached instances & generated GLB blobs
var _threeModule = null;
var _gltfExporterClass = null;
var _blobCache = {};
var _cutoutCache = {};

/**
 * Lazy loader cho Three.js và GLTFExporter qua CDN ESM
 */
async function loadThree() {
  if (_threeModule && _gltfExporterClass) {
    return { THREE: _threeModule, GLTFExporter: _gltfExporterClass };
  }
  try {
    var threeMod = await import('https://esm.sh/three@0.160.0');
    var exporterMod = await import('https://esm.sh/three@0.160.0/examples/jsm/exporters/GLTFExporter.js');
    _threeModule = threeMod;
    _gltfExporterClass = exporterMod.GLTFExporter;
    return { THREE: _threeModule, GLTFExporter: _gltfExporterClass };
  } catch (err) {
    console.warn('[AXGNUD 3D] Failed to load Three.js via CDN:', err);
    return null;
  }
}

/**
 * 2. Thuật toán HTML5 Canvas tách bỏ nền tường & sàn vuông của ảnh chụp sản phẩm
 */
async function createTransparentCutout(imgUrl) {
  if (!imgUrl) return '';
  if (_cutoutCache[imgUrl]) {
    return _cutoutCache[imgUrl];
  }

  return new Promise(function(resolve) {
    var img = new Image();
    // Only set crossOrigin if URL is truly from an external domain
    if (typeof window !== 'undefined' && imgUrl.indexOf('http') === 0 && imgUrl.indexOf(window.location.host) === -1) {
      img.crossOrigin = 'anonymous';
    }
    img.onload = function() {
      try {
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        var w = canvas.width = img.naturalWidth || 600;
        var h = canvas.height = img.naturalHeight || 600;
        ctx.drawImage(img, 0, 0, w, h);

        var imgData = ctx.getImageData(0, 0, w, h);
        var data = imgData.data;
        var visited = new Uint8Array(w * h);

        // Lấy mẫu màu nền ở viền ảnh
        var getRGB = function(x, y) {
          var idx = (y * w + x) * 4;
          return [data[idx], data[idx + 1], data[idx + 2]];
        };

        var colorDist = function(r1, g1, b1, r2, g2, b2) {
          return Math.sqrt(Math.pow(r1 - r2, 2) + Math.pow(g1 - g2, 2) + Math.pow(b1 - b2, 2));
        };

        // Flood-fill từ 4 cạnh biên vào trong
        var queue = [];
        for (var x = 0; x < w; x++) {
          var topC = getRGB(x, 0);
          var btmC = getRGB(x, h - 1);
          queue.push([x, 0, topC[0], topC[1], topC[2]]);
          queue.push([x, h - 1, btmC[0], btmC[1], btmC[2]]);
        }
        for (var y = 0; y < h; y++) {
          var leftC = getRGB(0, y);
          var rightC = getRGB(w - 1, y);
          queue.push([0, y, leftC[0], leftC[1], leftC[2]]);
          queue.push([w - 1, y, rightC[0], rightC[1], rightC[2]]);
        }

        var stepThreshold = 20; // Độ lệch màu tối đa giữa các pixel nền liền kề
        var seedThreshold = 48; // Độ lệch tối đa so với màu biên ban đầu

        var head = 0;
        while (head < queue.length) {
          var item = queue[head++];
          var cx = item[0], cy = item[1], sr = item[2], sg = item[3], sb = item[4];
          var pos = cy * w + cx;
          if (visited[pos]) continue;
          visited[pos] = 1;

          var idx = pos * 4;
          var r = data[idx], g = data[idx + 1], b = data[idx + 2];

          // Làm trong suốt pixel nền
          data[idx + 3] = 0;

          var neighbors = [
            [cx + 1, cy], [cx - 1, cy], [cx, cy + 1], [cx, cy - 1]
          ];
          for (var i = 0; i < neighbors.length; i++) {
            var nx = neighbors[i][0];
            var ny = neighbors[i][1];
            if (nx >= 0 && nx < w && ny >= 0 && ny < h) {
              var nPos = ny * w + nx;
              if (!visited[nPos]) {
                var nIdx = nPos * 4;
                var nr = data[nIdx], ng = data[nIdx + 1], nb = data[nIdx + 2];
                if (
                  colorDist(r, g, b, nr, ng, nb) < stepThreshold &&
                  colorDist(sr, sg, sb, nr, ng, nb) < seedThreshold
                ) {
                  queue.push([nx, ny, sr, sg, sb]);
                }
              }
            }
          }
        }

        ctx.putImageData(imgData, 0, 0);
        var resultUrl = canvas.toDataURL('image/png');
        _cutoutCache[imgUrl] = resultUrl;
        resolve(resultUrl);
      } catch (err) {
        console.warn('[AXGNUD Cutout] Fallback to original image:', err);
        resolve(imgUrl);
      }
    };
    img.onerror = function() {
      resolve(imgUrl);
    };
    img.src = imgUrl;
  });
}

/**
 * 3. Dựng mô hình 3D PBR (.glb) theo thời gian thực cho từng dòng sản phẩm
 */
async function generateProceduralFurnitureGLB(productType, hexColor) {
  productType = productType || 'cabinet';
  hexColor = hexColor || '#8b5a2b';
  var cacheKey = (productType || 'cabinet').toLowerCase() + '_' + hexColor;
  if (_blobCache[cacheKey]) {
    return _blobCache[cacheKey];
  }

  var libs = await loadThree();
  if (!libs) return null;
  var THREE = libs.THREE;
  var GLTFExporter = libs.GLTFExporter;

  var scene = new THREE.Scene();
  var group = new THREE.Group();

  var mainMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color(hexColor),
    roughness: 0.42,
    metalness: 0.08
  });

  var woodMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color('#8b5a2b'),
    roughness: 0.55,
    metalness: 0.05
  });

  var darkMetalMat = new THREE.MeshStandardMaterial({
    color: new THREE.Color('#22201d'),
    roughness: 0.28,
    metalness: 0.85
  });

  var type = (productType || '').toLowerCase();

  if (type.includes('cabinet') || type.includes('rack') || type.includes('storage') || type.includes('tu') || type.includes('shelf') || type.includes('metro')) {
    // Tủ giày 3 cánh / Tủ đồ lưu trữ AXGNUD Metro PBR (1.14m W x 0.33m D x 0.76m H)
    var cabBody = new THREE.Mesh(new THREE.BoxGeometry(1.14, 0.68, 0.33), woodMat);
    cabBody.position.set(0, 0.42, 0);
    group.add(cabBody);

    // 3 Cánh cửa trước
    var doorW = 0.37;
    for (var d = 0; d < 3; d++) {
      var dx = -0.38 + d * doorW;
      var door = new THREE.Mesh(new THREE.BoxGeometry(doorW - 0.01, 0.65, 0.018), woodMat);
      door.position.set(dx, 0.42, 0.174);
      group.add(door);

      // Tay nắm kim loại
      var handle = new THREE.Mesh(new THREE.BoxGeometry(0.012, 0.12, 0.015), darkMetalMat);
      handle.position.set(dx + (d < 2 ? 0.14 : -0.14), 0.45, 0.19);
      group.add(handle);
    }

    // 4 Chân đế gỗ
    var cabLegs = [[-0.51, -0.13], [0.51, -0.13], [-0.51, 0.13], [0.51, 0.13], [0, 0]];
    cabLegs.forEach(function(coord) {
      var leg = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.015, 0.08, 16), darkMetalMat);
      leg.position.set(coord[0], 0.04, coord[1]);
      group.add(leg);
    });
  } else if (type.includes('sofa') || type.includes('couch')) {
    // Sofa 3D PBR
    var base = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.28, 0.85), mainMat);
    base.position.y = 0.28;
    var back = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.48, 0.22), mainMat);
    back.position.set(0, 0.62, -0.31);
    var armL = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.42, 0.85), mainMat);
    armL.position.set(-0.86, 0.45, 0);
    var armR = armL.clone();
    armR.position.x = 0.86;
    group.add(base, back, armL, armR);

    var legs = [[-0.8, -0.32], [0.8, -0.32], [-0.8, 0.32], [0.8, 0.32]];
    legs.forEach(function(coord) {
      var leg = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.018, 0.16, 16), woodMat);
      leg.position.set(coord[0], 0.08, coord[1]);
      group.add(leg);
    });
  } else if (type.includes('table') || type.includes('desk')) {
    // Bàn ăn / Bàn làm việc 3D
    var topGeo = new THREE.CylinderGeometry(0.65, 0.65, 0.05, 40);
    var top = new THREE.Mesh(topGeo, mainMat);
    top.position.y = 0.72;
    var baseGeo = new THREE.CylinderGeometry(0.18, 0.32, 0.7, 32);
    var tableBase = new THREE.Mesh(baseGeo, mainMat);
    tableBase.position.y = 0.35;
    group.add(top, tableBase);
  } else if (type.includes('lamp') || type.includes('light')) {
    // Đèn bàn / Đèn sàn 3D phát sáng
    var stand = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.16, 0.38, 32), mainMat);
    stand.position.y = 0.19;
    var shadeMat = new THREE.MeshStandardMaterial({
      color: new THREE.Color('#fffaf0'),
      roughness: 0.2,
      emissive: new THREE.Color('#ffedd5'),
      emissiveIntensity: 0.4
    });
    var shade = new THREE.Mesh(new THREE.SphereGeometry(0.28, 32, 24, 0, Math.PI * 2, 0, Math.PI * 0.62), shadeMat);
    shade.position.y = 0.36;
    group.add(stand, shade);
  } else if (type.includes('bed')) {
    // Giường ngủ 3D
    var mattress = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.3, 2.0), mainMat);
    mattress.position.set(0, 0.25, 0);
    var headboard = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.8, 0.15), woodMat);
    headboard.position.set(0, 0.5, -0.95);
    group.add(mattress, headboard);
  } else {
    // Mặc định: Sculpted Modern Lounge Chair (Masters Lounge Chair)
    // Mặt ghế uốn cong
    var seatGeo = new THREE.CylinderGeometry(0.28, 0.26, 0.045, 32);
    seatGeo.scale(1.15, 1, 1.08);
    var seat = new THREE.Mesh(seatGeo, mainMat);
    seat.position.set(0, 0.45, 0.02);
    group.add(seat);

    // 4 chân ghế thon dần xuống sàn
    var legCoords = [
      [-0.24, -0.21, 0.06, -0.05],
      [0.24, -0.21, -0.06, -0.05],
      [-0.25, 0.22, 0.06, 0.06],
      [0.25, 0.22, -0.06, 0.06]
    ];
    legCoords.forEach(function(c) {
      var leg = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.013, 0.46, 20), mainMat);
      leg.position.set(c[0], 0.23, c[1]);
      leg.rotation.z = c[2];
      leg.rotation.x = c[3];
      group.add(leg);
    });

    // 3 vòng cung lưng ghế uốn lượn đan chéo đặc trưng
    var createBackArc = function(heightPeak, widthSpread, zOffset, tilt) {
      var curve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-0.27, 0.45, 0.15),
        new THREE.Vector3(-widthSpread, 0.64, -0.08 + zOffset),
        new THREE.Vector3(-widthSpread * 0.65, heightPeak, -0.22 + zOffset),
        new THREE.Vector3(0, heightPeak - 0.03, -0.25 + zOffset),
        new THREE.Vector3(widthSpread * 0.65, heightPeak, -0.22 + zOffset),
        new THREE.Vector3(widthSpread, 0.64, -0.08 + zOffset),
        new THREE.Vector3(0.27, 0.45, 0.15)
      ]);
      var tubeGeo = new THREE.TubeGeometry(curve, 64, 0.017, 16, false);
      var mesh = new THREE.Mesh(tubeGeo, mainMat);
      mesh.rotation.x = tilt;
      return mesh;
    };

    group.add(
      createBackArc(0.88, 0.31, 0.0, -0.04),
      createBackArc(0.81, 0.28, 0.02, 0.02),
      createBackArc(0.93, 0.25, -0.02, -0.08)
    );
  }

  scene.add(group);

  return new Promise(function(resolve) {
    var exporter = new GLTFExporter();
    exporter.parse(
      scene,
      function(gltfBuffer) {
        var blob = new Blob([gltfBuffer], { type: 'model/gltf-binary' });
        var blobUrl = URL.createObjectURL(blob);
        _blobCache[cacheKey] = blobUrl;
        resolve(blobUrl);
      },
      function(error) {
        console.warn('[AXGNUD 3D] GLTFExporter error:', error);
        resolve(null);
      },
      { binary: true }
    );
  });
}

// Gắn vào window để ax-room-visualizer.js dễ dàng truy xuất
if (typeof window !== 'undefined') {
  window.AXGNUD_3D = {
    createTransparentCutout: createTransparentCutout,
    generateProceduralFurnitureGLB: generateProceduralFurnitureGLB,
    loadThree: loadThree
  };
}
