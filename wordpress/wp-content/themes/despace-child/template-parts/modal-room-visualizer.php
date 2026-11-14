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
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
						<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
						<line x1="12" y1="22.08" x2="12" y2="12"/>
					</svg>
					<span><?php echo esc_html( furniva_t( '3D Product Viewer', 'Mô Hình 3D 360°' ) ); ?></span>
				</button>
				<button type="button" class="ax-mode-tab" id="axTabBtnAR" data-target-tab="ar" role="tab" aria-selected="false" aria-controls="axTabPaneAR">
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
						shadow-intensity="1.2"
						shadow-softness="0.75"
						exposure="1.0"
						ar
						ar-modes="webxr scene-viewer quick-look"
						ar-scale="fixed"
						interaction-prompt="auto"
						touch-action="pan-y">
						
						<!-- Fallback / AR trigger button handled via tab 2 -->
						<button slot="ar-button" id="axHiddenMvArBtn" style="display:none;" aria-hidden="true"></button>
					</model-viewer>

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
						<button type="button" class="ax-toolbar-btn is-active" id="axBtnAutoRotate" title="<?php echo esc_attr( furniva_t( 'Toggle Auto Rotate', 'Tự động xoay' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.6L21 8"/>
								<polyline points="21 3 21 8 16 8"/>
							</svg>
							<span>Auto-Rotate</span>
						</button>
						<button type="button" class="ax-toolbar-btn" id="axBtnResetCamera" title="<?php echo esc_attr( furniva_t( 'Reset Camera Angle', 'Góc nhìn mặc định' ) ); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<polyline points="1 4 1 10 7 10"/>
								<path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
							</svg>
							<span>Reset Angle</span>
						</button>
					</div>

					<!-- Bottom Interaction Hints -->
					<div class="ax-3d-interaction-hints">
						<span class="ax-hint-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.85.99 6.57 2.6L21 8"/><polyline points="21 3 21 8 16 8"/></svg>
							Xoay 360° bằng chuột hoặc chạm
						</span>
						<span class="ax-hint-sep">•</span>
						<span class="ax-hint-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
							Cuộn / Vuốt để Phóng to / Thu nhỏ
						</span>
					</div>

					<!-- Status: Loading Overlay -->
					<div class="ax-status-overlay ax-overlay-loading" id="ax3DLoadingOverlay">
						<div class="ax-spinner"></div>
						<p class="ax-status-text"><?php echo esc_html( furniva_t( 'Loading 3D asset...', 'Đang tải mô hình 3D...' ) ); ?></p>
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

					<!-- Status: Not Configured Overlay (Clean luxury message, NO fake sofa!) -->
					<div class="ax-status-overlay ax-overlay-unsupported" id="ax3DNotConfiguredOverlay" style="display:none;">
						<div class="ax-status-badge">DeSpace CRAFTSMANSHIP</div>
						<h3 class="ax-status-heading"><?php echo esc_html( furniva_t( '3D Model Coming Soon', 'Mô Hình 3D Đang Được Hoàn Thiện' ) ); ?></h3>
						<p class="ax-status-desc"><?php echo esc_html( furniva_t( 'The high-precision 3D and AR model for this design is currently being crafted by DeSpace 3D Studio. Please refer to our multi-angle studio photographs in the meantime.', 'Mô hình 3D và AR kích thước thực cho sản phẩm này đang được DeSpace Studio hoàn thiện. Quý khách vui lòng tham khảo bộ ảnh đa góc độ chụp thực tế tại trang chi tiết.' ) ); ?></p>
					</div>
				</div>
			</div>

			<!-- ========================================================
			     TAB 2: AR ROOM PREVIEW (Real Camera Augmented Reality)
			     ======================================================== -->
			<div class="ax-tab-pane" id="axTabPaneAR" role="tabpanel" aria-labelledby="axTabBtnAR" style="display:none;">
				<div class="ax-ar-workspace">
					
					<!-- Device: iOS / iPadOS (Apple Quick Look) -->
					<div class="ax-ar-device-card" id="axArCardIOS" style="display:none;">
						<div class="ax-ar-card-header">
							<div class="ax-device-icon-badge">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.37c.62-.75 1.04-1.8 1.01-2.87-1 .04-2.17.67-2.82 1.42-.57.65-1.07 1.71-1.01 2.76 1.11.09 2.2-.56 2.82-1.31z"/></svg>
								<span>Apple iOS AR Quick Look</span>
							</div>
							<h3 class="ax-ar-title"><?php echo esc_html( furniva_t( 'See It In Your Space on iPhone / iPad', 'Trải Nghiệm Đặt Sản Phẩm Trong Phòng Của Bạn' ) ); ?></h3>
							<p class="ax-ar-subtitle">
								<?php echo esc_html( furniva_t( 'Uses Apple AR Quick Look to place the furniture on your floor with true-to-life 1:1 scale and realistic ambient lighting.', 'Sử dụng công nghệ Apple AR Quick Look để đưa sản phẩm lên mặt sàn thực tế với kích thước thật 100% và đổ bóng tự nhiên.' ) ); ?>
							</p>
						</div>

						<!-- Direct Native Apple Quick Look Link -->
						<div class="ax-ar-cta-area">
							<a href="#" id="axAppleArLink" rel="ar" class="ax-btn ax-btn-ar-launch">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
									<circle cx="12" cy="13" r="4"/>
								</svg>
								<span><?php echo esc_html( furniva_t( 'Launch AR Camera Now', 'Khởi Chạy Camera AR (Quick Look)' ) ); ?></span>
							</a>
						</div>

						<!-- Step by Step Walkthrough -->
						<div class="ax-ar-steps-grid">
							<div class="ax-ar-step">
								<span class="ax-step-num">01</span>
								<h4>Mở Camera AR</h4>
								<p>Chạm nút Khởi chạy để mở chế độ quét không gian trên Safari.</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">02</span>
								<h4>Quét Mặt Sàn</h4>
								<p>Hướng camera xuống sàn nhà và lia nhẹ điện thoại để nhận diện mặt phẳng.</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">03</span>
								<h4>Giữ Tỷ Lệ Thực</h4>
								<p>Sản phẩm xuất hiện đúng chuẩn kích thước thực tế (Rộng 114 × Sâu 33 × Cao 76 cm).</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">04</span>
								<h4>Di Chuyển &amp; Chụp</h4>
								<p>Dùng 1 ngón tay kéo vị trí, 2 ngón xoay góc và chụp ảnh lưu lại không gian.</p>
							</div>
						</div>
					</div>

					<!-- Device: Android (ARCore / Scene Viewer) -->
					<div class="ax-ar-device-card" id="axArCardAndroid" style="display:none;">
						<div class="ax-ar-card-header">
							<div class="ax-device-icon-badge">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
								<span>Google Android ARCore / Scene Viewer</span>
							</div>
							<h3 class="ax-ar-title"><?php echo esc_html( furniva_t( 'See It In Your Space on Android', 'Xem Sản Phẩm Trong Không Gian Thực Trên Android' ) ); ?></h3>
							<p class="ax-ar-subtitle">
								<?php echo esc_html( furniva_t( 'Uses Google ARCore Scene Viewer to seamlessly position this piece into your room.', 'Sử dụng Google ARCore và Scene Viewer để đặt sản phẩm vào không gian thực tế với kích thước chuẩn xác.' ) ); ?>
							</p>
						</div>

						<!-- Direct Google Scene Viewer Intent Button -->
						<div class="ax-ar-cta-area">
							<button type="button" id="axAndroidArBtn" class="ax-btn ax-btn-ar-launch">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
									<circle cx="12" cy="13" r="4"/>
								</svg>
								<span><?php echo esc_html( furniva_t( 'Launch AR Camera Now', 'Khởi Chạy Camera AR (Scene Viewer)' ) ); ?></span>
							</button>
						</div>

						<!-- Step by Step Walkthrough -->
						<div class="ax-ar-steps-grid">
							<div class="ax-ar-step">
								<span class="ax-step-num">01</span>
								<h4>Khởi Chạy AR</h4>
								<p>Bấm nút để kích hoạt tính năng Camera AR qua Google Scene Viewer.</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">02</span>
								<h4>Quét Mặt Phẳng</h4>
								<p>Di chuyển nhẹ nhàng điện thoại xung quanh sàn phòng để định vị bề mặt.</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">03</span>
								<h4>Đặt &amp; Căn Chỉnh</h4>
								<p>Chạm để đặt sản phẩm. Kích thước hiển thị đúng 100% kích thước thực tế.</p>
							</div>
							<div class="ax-ar-step">
								<span class="ax-step-num">04</span>
								<h4>Xoay &amp; Khám Phá</h4>
								<p>Xoay nhiều góc nhìn, kiểm tra khoảng cách và chụp ảnh phối cảnh không gian.</p>
							</div>
						</div>
					</div>

					<!-- Device: Desktop (Instant Mobile QR Code Flow) -->
					<div class="ax-ar-device-card" id="axArCardDesktop">
						<div class="ax-desktop-qr-layout">
							<div class="ax-qr-code-box">
								<div class="ax-qr-frame" id="axQrFrame">
									<!-- Dynamic Canvas / SVG QR Code will be mounted here -->
									<div id="axDynamicQrCode"></div>
								</div>
								<span class="ax-qr-badge">SCAN TO OPEN AR</span>
							</div>

							<div class="ax-qr-info">
								<span class="ax-room-badge">TRẢI NGHIỆM AR THỰC TẾ TRÊN ĐIỆN THOẠI</span>
								<h3 class="ax-qr-title">Quét Mã QR Để Mở Camera AR</h3>
								<p class="ax-qr-desc">
									Trải nghiệm Thực tế ảo Tăng cường (AR) sử dụng <strong>Camera và Cảm biến nhận diện không gian</strong> trên điện thoại thông minh để đưa sản phẩm trực tiếp vào phòng của bạn.
								</p>
								<ul class="ax-qr-checklist">
									<li>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
										<span><strong>iPhone / iPad:</strong> Mở ứng dụng Camera mặc định, quét mã QR và chạm vào liên kết màu vàng để mở AR Quick Look.</span>
									</li>
									<li>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
										<span><strong>Android:</strong> Dùng Google Lens hoặc ứng dụng Quét mã để mở trải nghiệm Google Scene Viewer.</span>
									</li>
									<li>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A6745" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
										<span><strong>Không cần cài đặt ứng dụng:</strong> Chạy trực tiếp trên trình duyệt Safari hoặc Chrome.</span>
									</li>
								</ul>

								<div class="ax-qr-copy-row">
									<input type="text" id="axArDirectUrlInput" readonly class="ax-ar-url-input" />
									<button type="button" class="ax-btn ax-btn-secondary" id="axBtnCopyArUrl">Sao chép Link AR</button>
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
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
