<?php
/**
 * DeSpace Luxury — 3D Product Viewer & Real Augmented Reality (AR) Modal
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="ax-room-visualizer-modal" class="ax-room-modal" role="dialog" aria-modal="true" aria-labelledby="ax-room-modal-title" aria-hidden="true" style="display: none;">
	<div class="ax-room-modal-backdrop" id="axRoomModalBackdrop"></div>
	
	<div class="ax-room-modal-dialog">
		<!-- Modal Header -->
		<header class="ax-room-modal-header">
			<div class="ax-room-modal-head-left">
				<span class="ax-room-badge">DeSpace LUXURY ✦ 3D &amp; AR STUDIO</span>
				<h2 id="ax-room-modal-title" class="ax-room-modal-title">
					<?php echo esc_html( furniva_t( '3D & Augmented Reality Experience', 'Trải Nghiệm 3D & AR Thực Tế' ) ); ?>
				</h2>
				<p class="ax-room-modal-subtitle" id="axModalHeaderSubtitle">
					<?php echo esc_html( furniva_t( 'Explore high-fidelity 3D model or place in your room with real AR camera', 'Quan sát chi tiết mô hình 3D 360° hoặc đưa vào không gian thực qua Camera AR' ) ); ?>
				</p>
			</div>

			<!-- Main 2-Mode Tab Switcher -->
			<div class="ax-room-mode-switch" id="axMainModeTabs" role="tablist">
				<button type="button" class="ax-mode-tab active" id="axTabBtn3D" data-target-tab="3d" role="tab" aria-selected="true" aria-controls="axTabPane3D">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
						<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
						<line x1="12" y1="22.08" x2="12" y2="12"/>
					</svg>
					<span><?php echo esc_html( furniva_t( '3D Product Viewer', 'Mô Hình 3D 360°' ) ); ?></span>
				</button>
				<button type="button" class="ax-mode-tab" id="axTabBtnAR" data-target-tab="ar" role="tab" aria-selected="false" aria-controls="axTabPaneAR">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
						<circle cx="12" cy="13" r="4"/>
					</svg>
					<span><?php echo esc_html( furniva_t( 'Real AR Camera', 'Xem Trong Phòng (AR)' ) ); ?></span>
				</button>
			</div>

			<!-- Close Button -->
			<button type="button" class="ax-room-modal-close" id="axRoomModalClose" aria-label="<?php echo esc_attr( furniva_t( 'Close', 'Đóng' ) ); ?>">
				<span class="ax-close-icon" aria-hidden="true">&#x2715;</span>
			</button>
		</header>

		<!-- Modal Body: Main Tab Viewports -->
		<div class="ax-room-modal-body">
			
			<!-- ========================================================
			     TAB 1: 3D PRODUCT VIEWER (360° Orbit, PBR lighting)
			     ======================================================== -->
			<div class="ax-tab-pane ax-tab-active" id="axTabPane3D" role="tabpanel" aria-labelledby="axTabBtn3D">
				<div class="ax-3d-viewport-wrapper" id="ax3DViewportWrapper">
					
					<!-- Model Viewer Component -->
					<model-viewer id="axMainModelViewer" class="ax-main-model-viewer"
						alt="DeSpace 3D Product Model"
						camera-controls
						auto-rotate
						rotation-per-second="18deg"
						interpolation-decay="100"
						shadow-intensity="1.25"
						shadow-softness="0.7"
						exposure="1.05"
						tone-mapping="neutral"
						ar
						ar-modes="webxr scene-viewer quick-look"
						ar-scale="fixed"
						crossorigin="anonymous"
						interaction-prompt="auto"
						touch-action="pan-y">
						
						<button slot="ar-button" id="axHiddenMvArBtn" style="display:none;" aria-hidden="true"></button>
					</model-viewer>

					<!-- Articulated Interactive 3D Canvas (Three.js Raycast Click-to-Open Doors & Drawers) -->
					<div id="axInteractive3DStage" class="ax-interactive-3d-stage" style="display:none;" aria-label="<?php echo esc_attr( furniva_t( 'Interactive 3D Furniture Canvas', 'Khung mô hình 3D tương tác đóng mở' ) ); ?>"></div>

					<!-- Interactive Part Hover / Status Pill -->
					<div class="ax-3d-part-hint" id="ax3DPartHint" role="status" aria-live="polite" style="display:none;"></div>

					<!-- Accessible 1-Tap Part Chips (Synchronized with Direct 3D Click/Tap) -->
					<div class="ax-3d-part-pills" id="ax3DPartPills" role="group" aria-label="<?php echo esc_attr( furniva_t( 'Interactive Doors and Drawers', 'Đóng mở từng bộ phận tủ' ) ); ?>" style="display:none;"></div>

					<!-- Floating Dimension & Material Callout (Top Left) -->
					<div class="ax-3d-info-pill" id="ax3DInfoPill">
						<div class="ax-pill-row">
							<span class="ax-pill-dot"></span>
							<strong id="ax3DInfoTitle">Tủ giày 3 cánh DeSpace Metro</strong>
						</div>
						<div class="ax-pill-meta">
							<span id="ax3DInfoDims">Rộng 114 cm × Sâu 33 cm × Cao 76 cm</span>
							<span class="ax-pill-sep">•</span>
							<span id="ax3DInfoScale">Tỷ lệ thực 1:1</span>
						</div>
					</div>

					<!-- Floating Quick Controls (Top Right) -->
					<div class="ax-3d-quick-toolbar">
						<button type="button" class="ax-toolbar-btn ax-toolbar-btn-doors" id="axBtnToggleDoors" aria-pressed="false" style="display:none;" title="<?php echo esc_attr( furniva_t( 'Open or close all doors and drawers', 'Mở hoặc đóng toàn bộ cánh tủ và ngăn kéo' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M13 4h3a2 2 0 0 1 2 2v14"/>
								<path d="M2 20h3"/><path d="M13 20h9"/>
								<path d="M10 12v.01"/><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561z"/>
							</svg>
							<span id="axBtnToggleDoorsText"><?php echo esc_html( furniva_t( 'Open Doors', 'Mở hết cửa' ) ); ?></span>
						</button>
						<button type="button" class="ax-toolbar-btn is-active" id="axBtnAutoRotate" title="<?php echo esc_attr( furniva_t( 'Toggle Auto Rotate', 'Tự động xoay' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.6L21 8"/>
								<polyline points="21 3 21 8 16 8"/>
							</svg>
							<span>Auto-Rotate</span>
						</button>
						<button type="button" class="ax-toolbar-btn" id="axBtnResetCamera" title="<?php echo esc_attr( furniva_t( 'Reset Camera Angle', 'Góc nhìn mặc định' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<polyline points="1 4 1 10 7 10"/>
								<path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
							</svg>
							<span>Reset Angle</span>
						</button>
						<button type="button" class="ax-toolbar-btn ax-toolbar-btn-accent ax-trigger-live-cam" title="<?php echo esc_attr( furniva_t( 'Open Live AR Camera', 'Mở Camera AR Trực Tiếp' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
								<circle cx="12" cy="13" r="4"/>
							</svg>
							<span><?php echo esc_html( furniva_t( 'Live Camera AR', 'Xem Qua Camera' ) ); ?></span>
						</button>
					</div>

					<!-- Bottom Interaction Hints -->
					<div class="ax-3d-interaction-hints">
						<span class="ax-hint-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.6L21 8"/><polyline points="21 3 21 8 16 8"/></svg>
							Xoay 360° bằng chuột hoặc chạm
						</span>
						<span class="ax-hint-sep">•</span>
						<span class="ax-hint-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
							Cuộn / Vuốt để Phóng to / Thu nhỏ
						</span>
					</div>

					<!-- Status: Architectural 3D Skeleton Loading Overlay -->
					<div class="ax-status-overlay ax-overlay-loading ds-sk-3d-stage" id="ax3DLoadingOverlay" role="status" aria-live="polite">
						<div class="ds-sk-3d-stage__top-left" aria-hidden="true">
							<span class="ds-sk-bone ds-sk-bone--pill" style="width:74%;height:13px;"></span>
							<span class="ds-sk-bone ds-sk-bone--pill" style="width:52%;height:10px;--ds-sk-delay:90ms;"></span>
						</div>
						<div class="ds-sk-3d-stage__top-right" aria-hidden="true">
							<span class="ds-sk-bone ds-sk-bone--pill ds-sk-3d-stage__chip"></span>
							<span class="ds-sk-bone ds-sk-bone--pill ds-sk-3d-stage__chip" style="--ds-sk-delay:80ms;"></span>
							<span class="ds-sk-bone ds-sk-bone--deep ds-sk-bone--pill ds-sk-3d-stage__chip" style="--ds-sk-delay:160ms;"></span>
						</div>
						<div class="ds-sk-3d-stage__center">
							<div class="ds-sk-3d-pedestal" aria-hidden="true">
								<div class="ds-sk-3d-cube">
									<span class="ds-sk-bone ds-sk-3d-cube__panel"></span>
									<span class="ds-sk-bone ds-sk-3d-cube__panel" style="--ds-sk-delay:90ms;"></span>
									<span class="ds-sk-bone ds-sk-3d-cube__panel" style="--ds-sk-delay:180ms;"></span>
								</div>
								<span class="ds-sk-3d-floor-shadow"></span>
							</div>
							<progress class="ds-sk-progress" aria-label="<?php echo esc_attr( furniva_t( 'Loading 3D asset', 'Đang tải mô hình 3D' ) ); ?>"></progress>
							<p class="ax-status-text ds-sk-3d-caption"><?php echo esc_html( furniva_t( 'Preparing 1:1 3D Studio model…', 'Đang dựng mô hình 3D tỷ lệ 1:1…' ) ); ?></p>
						</div>
						<div class="ds-sk-3d-stage__bottom" aria-hidden="true">
							<span class="ds-sk-bone ds-sk-bone--pill ds-sk-3d-stage__bottom-line"></span>
						</div>
					</div>

					<!-- Status: Error Overlay -->
					<div class="ax-status-overlay ax-overlay-error" id="ax3DErrorOverlay" style="display:none;">
						<div class="ax-status-icon">⚠</div>
						<h3 class="ax-status-heading"><?php echo esc_html( furniva_t( 'Cannot Load 3D Model', 'Không Thể Tải Mô Hình 3D' ) ); ?></h3>
						<p class="ax-status-desc"><?php echo esc_html( furniva_t( 'Unable to load 3D asset file. Please check your network connection.', 'Không thể tải file mô hình 3D. Vui lòng kiểm tra lại kết nối mạng.' ) ); ?></p>
						<button type="button" class="ax-btn ax-btn-primary ax-retry-btn" id="axBtnRetryLoad3D">
							<?php echo esc_html( furniva_t( 'Retry', 'Thử Lại' ) ); ?>
						</button>
					</div>

					<!-- Status: Not Configured Overlay -->
					<div class="ax-status-overlay ax-overlay-unsupported" id="ax3DNotConfiguredOverlay" style="display:none;">
						<div class="ax-status-badge">DeSpace CRAFTSMANSHIP</div>
						<h3 class="ax-status-heading"><?php echo esc_html( furniva_t( '3D Model Coming Soon', 'Mô Hình 3D Đang Được Hoàn Thiện' ) ); ?></h3>
						<p class="ax-status-desc"><?php echo esc_html( furniva_t( 'The high-precision 3D and AR model for this design is currently being crafted by DeSpace 3D Studio.', 'Mô hình 3D và AR kích thước thực cho sản phẩm này đang được DeSpace Studio hoàn thiện.' ) ); ?></p>
					</div>
				</div>
			</div>

			<!-- ========================================================
			     TAB 2: AR ROOM PREVIEW (Live 3D Camera + Mobile QR / Native AR)
			     ======================================================== -->
			<div class="ax-tab-pane" id="axTabPaneAR" role="tabpanel" aria-labelledby="axTabBtnAR" style="display:none;">
				<div class="ax-ar-workspace">

					<!-- Sub-mode Switcher Bar: Toggle between QR/Device Card and Direct Live 3D Camera -->
					<div class="ax-ar-submode-bar" id="axArSubmodeBar">
						<button type="button" class="ax-ar-submode-btn is-active" id="axSubModeQrBtn" data-ar-view="handoff">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
							</svg>
							<span><?php echo esc_html( furniva_t( 'QR & Sensor AR', 'Quét QR & Cảm Biến AR' ) ); ?></span>
						</button>
						<button type="button" class="ax-ar-submode-btn" id="axSubModeLiveCamBtn" data-ar-view="live-camera">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
								<circle cx="12" cy="13" r="4"/>
							</svg>
							<span><?php echo esc_html( furniva_t( 'Live 3D Camera AR', 'Camera AR 3D Trực Tiếp' ) ); ?></span>
						</button>
					</div>

					<!-- ====================================================
					     LIVE 3D CAMERA AR STUDIO (Real-time WebRTC + 3D GLB)
					     ==================================================== -->
					<div class="ax-live-cam-card" id="axLiveCameraArCard" style="display:none;">
						<div class="ax-live-cam-viewport" id="axLiveCamViewport">
							<!-- Hardware-accelerated Live Camera Video Feed -->
							<video id="axLiveArVideo" class="ax-live-ar-video" autoplay playsinline muted></video>

							<!-- Optional Fallback Room Backdrop if Camera Permission Denied -->
							<img id="axLiveArFallbackRoom" class="ax-live-ar-fallback-bg" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/room-living.jpg' ); ?>" alt="Room Background" style="display:none;" />

							<!-- Ambient Lighting Filter Overlay -->
							<div id="axLiveArToneOverlay" class="ax-lighting-tone ax-tone-natural"></div>

							<!-- Floor Perspective Grid Reticle -->
							<div class="ax-live-ar-floor-grid" id="axLiveArFloorGrid"></div>

							<!-- GPU-Accelerated Free-Roam Draggable & Scalable 3D Model Container -->
							<div class="ax-live-ar-object-wrap" id="axLiveArObjectWrap">
								<div class="ax-live-ar-floor-reticle" id="axLiveArFloorReticle" aria-hidden="true"></div>
								<div class="ax-live-ar-floor-shadow" id="axLiveArFloorShadow"></div>
								<model-viewer id="axLiveArModelViewer" class="ax-live-ar-model-viewer"
									alt="DeSpace Live AR 3D Model"
									camera-controls
									interpolation-decay="85"
									shadow-intensity="1.5"
									shadow-softness="0.65"
									exposure="1.06"
									tone-mapping="neutral"
									interaction-prompt="none"
									crossorigin="anonymous"
									touch-action="none">
								</model-viewer>
							</div>

							<!-- Top-Left Live Status & Dimension Badge -->
							<div class="ax-3d-info-pill ax-live-ar-pill">
								<div class="ax-pill-row">
									<span class="ax-pill-dot ax-pill-dot-live" id="axLiveSpatialDot"></span>
									<strong id="axLiveArInfoTitle">LIVE AR 3D STUDIO</strong>
								</div>
								<div class="ax-pill-meta">
									<span id="axLiveArInfoDims">Tỷ lệ thực 1:1</span>
									<span class="ax-pill-sep">•</span>
									<span id="axLiveArScaleReadout">100%</span>
								</div>
							</div>

							<!-- Centered Live Floor & Obstacle Collision Alert HUD (Visible on Desktop & Mobile) -->
							<div class="ax-live-spatial-status" id="axLiveSpatialStatus" role="status" aria-live="polite">
								🔍 Đang quét mặt sàn...
							</div>

							<!-- Top-Right Gesture Mode Switcher (Rotate 360° vs Move on Floor) -->
							<div class="despace-stage-mode-bar" id="axLiveGestureBar">
								<button type="button" class="despace-stage-mode-btn is-active" id="axLiveModeRotateBtn" data-gesture="rotate">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.6L21 8"/><polyline points="21 3 21 8 16 8"/>
									</svg>
									<span>Xoay 3D 360°</span>
								</button>
								<button type="button" class="despace-stage-mode-btn" id="axLiveModeMoveBtn" data-gesture="move">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/>
									</svg>
									<span>Di Chuyển Vị Trí</span>
								</button>
							</div>

							<!-- Interactive Hint Banner -->
							<div class="ax-stage-hint" id="axLiveArHint">
								Vuốt trên sản phẩm để xoay 3D 360° • Chuyển sang "Di Chuyển Vị Trí" để kéo đặt trên sàn
							</div>
						</div>

						<!-- Live Camera Bottom Control Bar -->
						<div class="ax-room-controls-bar ax-live-cam-controls">
							<!-- Scale Control -->
							<div class="ax-control-group">
								<span class="ax-control-label">Kích thước:</span>
								<div class="ax-scale-controls">
									<button type="button" class="ax-scale-btn" id="axLiveScaleDownBtn" aria-label="Thu nhỏ">−</button>
									<input type="range" id="axLiveScaleSlider" min="50" max="180" value="100" step="2" aria-label="Tỷ lệ kích thước mô hình 3D" />
									<button type="button" class="ax-scale-btn" id="axLiveScaleUpBtn" aria-label="Phóng to">+</button>
									<span class="ax-scale-badge" id="axLiveScaleBadge">100%</span>
									<button type="button" class="ax-scale-reset-btn" id="axLiveResetTransformBtn" title="Đặt lại góc nhìn & vị trí" aria-label="Đặt lại vị trí">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
											<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
										</svg>
									</button>
								</div>
							</div>

							<!-- Lighting Tone -->
							<div class="ax-control-group">
								<span class="ax-control-label">Ánh sáng:</span>
								<div class="ax-btn-group">
									<button type="button" class="ax-pill-btn ax-live-tone-btn active" data-tone="natural" data-exposure="1.06">
										<span class="ax-tone-dot ax-dot-natural"></span> Tự nhiên
									</button>
									<button type="button" class="ax-pill-btn ax-live-tone-btn" data-tone="warm" data-exposure="1.22">
										<span class="ax-tone-dot ax-dot-warm"></span> Ấm áp
									</button>
									<button type="button" class="ax-pill-btn ax-live-tone-btn" data-tone="moody" data-exposure="0.86">
										<span class="ax-tone-dot ax-dot-moody"></span> Sắc nét
									</button>
								</div>
							</div>

							<!-- Camera Actions: Auto-Floor, Spatial Obstacle Scan, Toggle Doors, Flip, Upload Room Photo, Snapshot -->
							<div class="ax-control-group">
								<button type="button" class="ax-pill-btn" id="axLiveAutoFloorBtn" title="<?php echo esc_attr( furniva_t( 'Detect room floor & snap model onto floor', 'Quét mặt sàn & đặt sản phẩm xuống sàn nhà' ) ); ?>">
									<span>🎯 Đặt Xuống Sàn</span>
								</button>
								<button type="button" class="ax-pill-btn active" id="axLiveSpatialToggleBtn" aria-pressed="true" title="<?php echo esc_attr( furniva_t( 'Real-time terrain & obstacle collision warning', 'Bật/Tắt cảm biến nhận biết sàn & cảnh báo vật cản đỏ' ) ); ?>">
									<span>🛡️ Quét Vật Cản</span>
								</button>
								<button type="button" class="ax-pill-btn" id="axLiveToggleDoorsBtn" aria-pressed="false" style="display:none;" title="<?php echo esc_attr( furniva_t( 'Open / Close Cabinet Doors in AR', 'Mở / Đóng cánh tủ trong AR' ) ); ?>">
									<span>🚪 Mở Tủ</span>
								</button>
								<button type="button" class="ax-pill-btn" id="axLiveFlipCamBtn" title="Đổi camera Trước / Sau">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M20 11A8.1 8.1 0 0 0 4.5 9M4 5v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/>
									</svg>
									<span>Đổi Camera</span>
								</button>
								<label class="ax-pill-btn" for="axLiveRoomPhotoInput" style="cursor:pointer; margin:0;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
									</svg>
									<span>Ảnh Phòng</span>
									<input type="file" id="axLiveRoomPhotoInput" accept="image/*" hidden />
								</label>
								<button type="button" class="ax-pill-btn active" id="axLiveSnapshotBtn">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
									</svg>
									<span>Chụp Lưu Ảnh AR</span>
								</button>
							</div>
						</div>
					</div>
					
					<!-- ====================================================
					     DEVICE HANDOFF CARDS (iOS, Android, Desktop QR)
					     ==================================================== -->
					<div id="axArHandoffContainer">
						<!-- Device: iOS / iPadOS (Apple Quick Look + Live 3D Camera) -->
						<div class="ax-ar-device-card" id="axArCardIOS" style="display:none;">
							<div class="ax-ar-card-header">
								<div class="ax-device-icon-badge">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.37c.62-.75 1.04-1.8 1.01-2.87-1 .04-2.17.67-2.82 1.42-.57.65-1.07 1.71-1.01 2.76 1.11.09 2.2-.56 2.82-1.31z"/></svg>
									<span>Apple iOS AR Quick Look &amp; Live 3D Studio</span>
								</div>
								<h3 class="ax-ar-title"><?php echo esc_html( furniva_t( 'See It In Your Space on iPhone / iPad', 'Trải Nghiệm Đặt Sản Phẩm Trong Phòng Của Bạn' ) ); ?></h3>
								<p class="ax-ar-subtitle">
									<?php echo esc_html( furniva_t( 'Place the exact 3D furniture model in your room using Live 3D Camera or Apple AR Quick Look with 1:1 scale.', 'Đặt đúng chuẩn mô hình 3D vào không gian phòng thực tế qua Camera 3D Trực Tiếp hoặc cảm biến Apple AR Quick Look tỷ lệ 1:1.' ) ); ?>
								</p>
							</div>

							<!-- Dual Launch Actions: Live 3D Camera + Native Apple Quick Look (with mandatory <img> child) -->
							<div class="ax-ar-cta-area ax-ar-cta-dual">
								<button type="button" class="ax-btn ax-btn-ar-launch ax-trigger-live-cam">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
										<circle cx="12" cy="13" r="4"/>
									</svg>
									<span><?php echo esc_html( furniva_t( 'Open Live 3D Camera (Recommended)', 'Mở Camera AR 3D Trực Tiếp (Xoay 360°)' ) ); ?></span>
								</button>

								<a href="#" id="axAppleArLink" rel="ar" class="ax-btn ax-btn-ar-secondary">
									<img id="axAppleArHiddenImg" src="" alt="" style="width:1px;height:1px;opacity:0;position:absolute;pointer-events:none;" />
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
									</svg>
									<span><?php echo esc_html( furniva_t( 'Apple AR Quick Look (iOS Sensor)', 'Mở Apple AR Quick Look (USDZ)' ) ); ?></span>
								</a>
							</div>

							<!-- Step by Step Walkthrough -->
							<div class="ax-ar-steps-grid">
								<div class="ax-ar-step">
									<span class="ax-step-num">01</span>
									<h4>Mở Camera AR</h4>
									<p>Chọn Camera AR 3D Trực Tiếp hoặc Apple AR Quick Look trên Safari.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">02</span>
									<h4>Hướng Vào Không Gian</h4>
									<p>Hướng camera vào góc phòng hoặc mặt sàn nơi bạn muốn bài trí nội thất.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">03</span>
									<h4>Chuẩn Mô Hình 3D</h4>
									<p>Hiển thị chính xác 100% vân gỗ, tay nắm kim loại và kích thước của mô hình 3D.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">04</span>
									<h4>Xoay 360° &amp; Chụp Ảnh</h4>
									<p>Xoay đa góc độ 360°, kéo đặt vị trí mượt mà và chụp lưu lại phối cảnh.</p>
								</div>
							</div>
						</div>

						<!-- Device: Android (ARCore / Scene Viewer + Live 3D Camera) -->
						<div class="ax-ar-device-card" id="axArCardAndroid" style="display:none;">
							<div class="ax-ar-card-header">
								<div class="ax-device-icon-badge">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
									<span>Google Android ARCore &amp; Live 3D Studio</span>
								</div>
								<h3 class="ax-ar-title"><?php echo esc_html( furniva_t( 'See It In Your Space on Android', 'Xem Sản Phẩm Trong Không Gian Thực Trên Android' ) ); ?></h3>
								<p class="ax-ar-subtitle">
									<?php echo esc_html( furniva_t( 'Position the exact 3D model into your room using Live 3D Camera or Google ARCore Scene Viewer.', 'Đưa đúng mô hình 3D vào phòng của bạn qua Camera 3D Trực Tiếp hoặc Google ARCore Scene Viewer.' ) ); ?>
								</p>
							</div>

							<!-- Dual Launch Buttons -->
							<div class="ax-ar-cta-area ax-ar-cta-dual">
								<button type="button" class="ax-btn ax-btn-ar-launch ax-trigger-live-cam">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
										<circle cx="12" cy="13" r="4"/>
									</svg>
									<span><?php echo esc_html( furniva_t( 'Open Live 3D Camera (Recommended)', 'Mở Camera AR 3D Trực Tiếp (Xoay 360°)' ) ); ?></span>
								</button>

								<button type="button" id="axAndroidArBtn" class="ax-btn ax-btn-ar-secondary">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
									</svg>
									<span><?php echo esc_html( furniva_t( 'Google Scene Viewer (ARCore)', 'Mở Google Scene Viewer (ARCore)' ) ); ?></span>
								</button>
							</div>

							<!-- Step by Step Walkthrough -->
							<div class="ax-ar-steps-grid">
								<div class="ax-ar-step">
									<span class="ax-step-num">01</span>
									<h4>Khởi Chạy AR</h4>
									<p>Chọn Camera AR 3D Trực Tiếp hoặc Google Scene Viewer.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">02</span>
									<h4>Quét Mặt Phẳng</h4>
									<p>Hướng camera vào vị trí sàn nhà hoặc góc tường cần đặt nội thất.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">03</span>
									<h4>Đặt &amp; Căn Chỉnh</h4>
									<p>Mô hình 3D hiển thị đúng 100% chi tiết cấu trúc, vân gỗ và kích thước thực.</p>
								</div>
								<div class="ax-ar-step">
									<span class="ax-step-num">04</span>
									<h4>Xoay &amp; Chụp Lưu</h4>
									<p>Xoay 360° kiểm tra mọi góc nhìn và lưu ảnh phối cảnh ngay lập tức.</p>
								</div>
							</div>
						</div>

						<!-- Device: Desktop (Instant Mobile QR Code Flow + Direct Webcam AR) -->
						<div class="ax-ar-device-card" id="axArCardDesktop">
							<div class="ax-desktop-qr-layout">
								<div class="ax-qr-code-box">
									<div class="ax-qr-frame" id="axQrFrame">
										<!-- Standards-compliant ISO/IEC 18004 SVG QR Code mounted here -->
										<div id="axDynamicQrCode"></div>
									</div>
									<span class="ax-qr-badge" id="axQrModeBadge">SCAN TO OPEN MOBILE AR</span>

									<!-- Network Mode Switcher (Cloud HTTPS vs Local Wi-Fi LAN) -->
									<div class="ax-qr-net-switch" id="axQrNetSwitch" role="group" aria-label="Chế độ kết nối mã QR">
										<button type="button" class="ax-qr-net-btn is-active" data-net-mode="tunnel" id="axQrNetTunnelBtn">
											🌐 Mọi mạng (HTTPS)
										</button>
										<button type="button" class="ax-qr-net-btn" data-net-mode="lan" id="axQrNetLanBtn">
											📶 Cùng Wi-Fi (LAN)
										</button>
									</div>
									<p class="ax-qr-net-status" id="axQrNetStatus" aria-live="polite"></p>
								</div>

								<div class="ax-qr-info">
									<span class="ax-room-badge">TRẢI NGHIỆM AR THỰC TẾ TRÊN ĐIỆN THOẠI &amp; MÁY TÍNH</span>
									<h3 class="ax-qr-title">Quét Mã QR Để Mở Camera AR</h3>
									<p class="ax-qr-desc">
										Đưa camera điện thoại vào mã QR bên trái để mở trực tiếp mô hình 3D của sản phẩm này trong không gian phòng của bạn, hoặc trải nghiệm ngay qua Camera trên thiết bị hiện tại.
									</p>
									<ul class="ax-qr-checklist">
										<li>
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
											<span><strong>iPhone / iPad:</strong> Mở ứng dụng Camera mặc định hoặc Zalo, quét mã QR và chạm vào liên kết để mở <strong>Camera AR 3D</strong> hoặc <strong>Apple AR Quick Look</strong>.</span>
										</li>
										<li>
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
											<span><strong>Android:</strong> Dùng máy ảnh, Google Lens hoặc Zalo quét mã để mở <strong>Camera AR 3D</strong> hoặc <strong>Google Scene Viewer</strong>.</span>
										</li>
										<li>
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
											<span><strong>Đồng bộ 100% Model 3D:</strong> Hiển thị đúng chuẩn mô hình 3D PBR của sản phẩm với thao tác xoay 360° và kéo đặt mượt mà.</span>
										</li>
									</ul>

									<div class="ax-qr-copy-row">
										<input type="text" id="axArDirectUrlInput" readonly class="ax-ar-url-input" aria-label="Đường dẫn AR trên điện thoại" />
										<span class="ax-copy-wrap">
											<button type="button" class="ax-btn ax-btn-copy-url" id="axBtnCopyArUrl" command="--copy" commandfor="axArDirectUrlInput">
												<svg class="ax-copy-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
													<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
												</svg>
												<span id="axBtnCopyArText">Sao chép Link AR</span>
											</button>
											<span class="ax-copy-tip" id="axArCopyStatus" role="status"></span>
										</span>
									</div>

									<div class="ax-desktop-live-cta">
										<button type="button" class="ax-btn ax-btn-ar-launch ax-trigger-live-cam">
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
												<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
												<circle cx="12" cy="13" r="4"/>
											</svg>
											<span>Mở Camera AR 3D Trực Tiếp Ngay Trên Thiết Bị Này</span>
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- AR Not Supported / Missing Model Fallback Message -->
					<div class="ax-ar-unsupported-card" id="axArMissingModelCard" style="display:none;">
						<div class="ax-status-icon">📦</div>
						<h3>Chức Năng AR Đang Được Chuẩn Bị</h3>
						<p>Sản phẩm này hiện đang trong quá trình số hóa 3D đo đạc tỷ lệ chuẩn bởi DeSpace Studio. Vui lòng chuyển sang Tab "Mô Hình 3D" hoặc xem chi tiết ảnh sản phẩm.</p>
					</div>

				</div>
			</div>

		</div>

		<!-- Modal Footer: Unified Product Summary & Actions -->
		<footer class="ax-room-modal-footer">
			<div class="ax-modal-product-summary">
				<div class="ax-modal-product-thumb">
					<img id="axModalProductThumb" src="" alt="Thumbnail" />
				</div>
				<div class="ax-modal-product-meta">
					<h3 id="axModalProductName" class="ax-modal-prod-title">Product Name</h3>
					<div class="ax-modal-prod-sub">
						<span id="axModalProductPrice" class="ax-modal-prod-price">$0.00</span>
						<span id="axModalProductDims" class="ax-modal-prod-dims" style="display: none;"></span>
					</div>
				</div>
			</div>

			<div class="ax-modal-actions">
				<div class="ax-modal-qty-stepper">
					<button type="button" class="ax-modal-qty-btn" id="axModalQtyMinus" aria-label="Giảm số lượng">−</button>
					<input type="number" id="axModalQtyInput" value="1" min="1" max="99" aria-label="Số lượng" />
					<button type="button" class="ax-modal-qty-btn" id="axModalQtyPlus" aria-label="Tăng số lượng">+</button>
				</div>
				<button type="button" class="ax-btn ax-btn-primary ax-btn-modal-cart" id="axModalAddToCartBtn">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="9" cy="21" r="1.6"/>
						<circle cx="19" cy="21" r="1.6"/>
						<path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/>
					</svg>
					<span id="axModalCartBtnText"><?php echo esc_html( furniva_t( 'Add to cart', 'Thêm vào giỏ' ) ); ?></span>
				</button>
			</div>
		</footer>
	</div>
</div>
