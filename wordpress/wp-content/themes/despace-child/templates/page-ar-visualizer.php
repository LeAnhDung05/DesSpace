<?php
/**
 * Furniva - AR Room Visualizer
 * Place furniture in your room using AR technology
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// Get products for AR placement
$ar_products = wc_get_products( array(
    'status'   => 'publish',
    'limit'    => 20,
    'orderby'  => 'popularity',
    'order'    => 'DESC',
    'meta_key' => '_ar_enabled',
    'meta_value' => 'yes',
) );

// If no AR-enabled products, use all products
if ( empty( $ar_products ) ) {
    $ar_products = wc_get_products( array(
        'status'   => 'publish',
        'limit'    => 20,
        'orderby'  => 'popularity',
        'order'    => 'DESC',
    ) );
}

$shop_url = wc_get_page_permalink( 'shop' );
?>

<div class="ar-visualizer-page">

    <!-- Hero Section -->
    <section class="ar-hero">
        <div class="ar-container">
            <nav class="ar-breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a>
                <span>/</span>
                <span><?php echo esc_html( furniva_t( 'AR Room Visualizer', 'Xem sản phẩm trong phòng' ) ); ?></span>
            </nav>

            <div class="ar-hero-content">
                <div class="ar-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                    AR TECHNOLOGY
                </div>
                <h1 class="ar-hero-title">
                    <?php echo esc_html( furniva_t( 'See It In Your Space', 'Xem Sản Phẩm Trong Không Gian Của Bạn' ) ); ?>
                </h1>
                <p class="ar-hero-subtitle">
                    <?php echo esc_html( furniva_t( 'Upload a photo of your room and place furniture to see how it fits. Perfect for visualizing size, style, and color before you buy.', 'Tải lên ảnh phòng của bạn và đặt nội thất vào để xem sản phẩm phù hợp như thế nào. Hoàn hảo để hình dung kích thước, phong cách và màu sắc trước khi mua.' ) ); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- AR Main Section -->
    <section class="ar-main-section">
        <div class="ar-container">
            <div class="ar-workspace">

                <!-- Left: Room Canvas -->
                <div class="ar-canvas-section">
                    <div class="ar-canvas-wrapper" id="arCanvasWrapper">

                        <!-- Upload Zone (Initial State) -->
                        <div class="ar-upload-zone" id="arUploadZone">
                            <div class="ar-upload-content">
                                <div class="ar-upload-icon">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                </div>
                                <h3><?php echo esc_html( furniva_t( 'Upload Your Room Photo', 'Tải Lên Ảnh Phòng Của Bạn' ) ); ?></h3>
                                <p><?php echo esc_html( furniva_t( 'Take a photo of your room or upload an existing image', 'Chụp ảnh phòng hoặc tải lên ảnh có sẵn' ) ); ?></p>
                                <div class="ar-upload-buttons">
                                    <label class="ar-btn ar-btn-primary" for="arFileInput">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="17 8 12 3 7 8"/>
                                            <line x1="12" y1="3" x2="12" y2="15"/>
                                        </svg>
                                        <span><?php echo esc_html( furniva_t( 'Choose File', 'Chọn File' ) ); ?></span>
                                        <input type="file" id="arFileInput" accept="image/*" hidden />
                                    </label>
                                    <button type="button" class="ar-btn ar-btn-secondary" id="arCameraBtn">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                        <span><?php echo esc_html( furniva_t( 'Take Photo', 'Chụp Ảnh' ) ); ?></span>
                                    </button>
                                </div>
                                <p class="ar-upload-hint">
                                    <?php echo esc_html( furniva_t( 'Supports JPG, PNG. Max 10MB.', 'Hỗ trợ JPG, PNG. Tối đa 10MB.' ) ); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Room Canvas -->
                        <div class="ar-room-canvas" id="arRoomCanvas" style="display: none;">
                            <img id="arRoomImage" class="ar-room-image" alt="Your room" />
                            <div class="ar-canvas-overlay" id="arCanvasOverlay">
                                <!-- Placed products will appear here -->
                            </div>

                            <!-- Canvas Controls -->
                            <div class="ar-canvas-controls">
                                <button type="button" class="ar-control-btn" id="arAddProductBtn" aria-label="<?php echo esc_attr( furniva_t( 'Add product', 'Thêm sản phẩm' ) ); ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                                <button type="button" class="ar-control-btn" id="arZoomInBtn" aria-label="<?php echo esc_attr( furniva_t( 'Zoom in', 'Phóng to' ) ); ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
                                    </svg>
                                </button>
                                <button type="button" class="ar-control-btn" id="arZoomOutBtn" aria-label="<?php echo esc_attr( furniva_t( 'Zoom out', 'Thu nhỏ' ) ); ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        <line x1="8" y1="11" x2="14" y2="11"/>
                                    </svg>
                                </button>
                                <button type="button" class="ar-control-btn" id="arResetBtn" aria-label="<?php echo esc_attr( furniva_t( 'Reset', 'Đặt lại' ) ); ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="1 4 1 10 7 10"/>
                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Change Photo Button -->
                            <button type="button" class="ar-change-photo-btn" id="arChangePhotoBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Change Photo', 'Đổi Ảnh' ) ); ?></span>
                            </button>
                        </div>

                        <!-- Camera Capture (Hidden by default) -->
                        <div class="ar-camera-section" id="arCameraSection" style="display: none;">
                            <video id="arVideo" autoplay playsinline></video>
                            <canvas id="arCanvas" style="display: none;"></canvas>
                            <div class="ar-camera-controls">
                                <button type="button" class="ar-btn ar-btn-secondary" id="arCancelCameraBtn">
                                    <?php echo esc_html( furniva_t( 'Cancel', 'Hủy' ) ); ?>
                                </button>
                                <button type="button" class="ar-btn ar-btn-primary ar-capture-btn" id="arCaptureBtn">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                    </svg>
                                </button>
                                <button type="button" class="ar-btn ar-btn-secondary" id="arFlipCameraBtn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <path d="M9 13a3 3 0 1 0 6 0 3 3 0 1 0-6 0"/>
                                        <path d="M23 12l-4-4v3H10v2h9v3l4-4z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Room Info -->
                    <div class="ar-room-info">
                        <h3><?php echo esc_html( furniva_t( 'Tips for best results', 'Mẹo để có kết quả tốt nhất' ) ); ?></h3>
                        <ul class="ar-tips-list">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Use a well-lit room with natural light', 'Sử dụng phòng có ánh sáng tự nhiên tốt' ) ); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Take photos from standing height', 'Chụp ảnh từ độ cao đứng' ) ); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Include floor space where furniture will go', 'Bao gồm không gian sàn nơi đặt đồ nội thất' ) ); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span><?php echo esc_html( furniva_t( 'Avoid cluttered backgrounds', 'Tránh nền quá lộn xộn' ) ); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right: Product Selection -->
                <div class="ar-products-section" id="arProductsSection">
                    <div class="ar-products-header">
                        <h2><?php echo esc_html( furniva_t( 'Select Products', 'Chọn Sản Phẩm' ) ); ?></h2>
                        <span class="ar-products-count" id="arProductsCount"><?php echo count( $ar_products ); ?> <?php echo esc_html( furniva_t( 'items', 'sản phẩm' ) ); ?></span>
                    </div>

                    <!-- Product Search -->
                    <div class="ar-product-search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="arProductSearch" placeholder="<?php echo esc_attr( furniva_t( 'Search products...', 'Tìm sản phẩm...' ) ); ?>" />
                    </div>

                    <!-- Product Categories -->
                    <div class="ar-product-categories">
                        <button type="button" class="ar-category-btn active" data-category="all">
                            <?php echo esc_html( furniva_t( 'All', 'Tất cả' ) ); ?>
                        </button>
                        <?php
                        $categories = get_terms( array(
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => true,
                            'parent'     => 0,
                        ) );
                        foreach ( array_slice( $categories, 0, 5 ) as $cat ) :
                        ?>
                        <button type="button" class="ar-category-btn" data-category="<?php echo esc_attr( $cat->slug ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Product Grid -->
                    <div class="ar-products-grid" id="arProductsGrid">
                        <?php foreach ( $ar_products as $product ) :
                            $product_id = $product->get_id();
                            $product_name = $product->get_name();
                            $product_price = $product->get_price_html();
                            $product_image = $product->get_image( 'woocommerce_thumbnail' );
                            $product_url = get_permalink( $product_id );

                            // Get product dimensions for AR scaling
                            $dimensions = $product->get_dimensions( false );
                            $width = isset( $dimensions['width'] ) ? floatval( $dimensions['width'] ) : 100;
                            $height = isset( $dimensions['height'] ) ? floatval( $dimensions['height'] ) : 80;
                            $length = isset( $dimensions['length'] ) ? floatval( $dimensions['length'] ) : 80;

                            // Get categories
                            $product_cats = get_the_terms( $product_id, 'product_cat' );
                            $cat_slugs = array();
                            if ( $product_cats ) {
                                foreach ( $product_cats as $cat ) {
                                    $cat_slugs[] = $cat->slug;
                                }
                            }
                        ?>
                        <div class="ar-product-card"
                             data-product-id="<?php echo esc_attr( $product_id ); ?>"
                             data-product-name="<?php echo esc_attr( $product_name ); ?>"
                             data-product-price="<?php echo esc_attr( $product->get_price() ); ?>"
                             data-product-image="<?php echo esc_url( wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ); ?>"
                             data-product-url="<?php echo esc_url( $product_url ); ?>"
                             data-product-width="<?php echo esc_attr( $width ); ?>"
                             data-product-height="<?php echo esc_attr( $height ); ?>"
                             data-product-length="<?php echo esc_attr( $length ); ?>"
                             data-categories="<?php echo esc_attr( implode( ',', $cat_slugs ) ); ?>">
                            <div class="ar-product-image">
                                <?php echo $product_image; ?>
                                <div class="ar-product-overlay">
                                    <span class="ar-add-label"><?php echo esc_html( furniva_t( 'Click to add', 'Nhấn để thêm' ) ); ?></span>
                                </div>
                            </div>
                            <div class="ar-product-info">
                                <h4><?php echo esc_html( $product_name ); ?></h4>
                                <div class="ar-product-meta">
                                    <span class="ar-product-price"><?php echo $product_price; ?></span>
                                    <span class="ar-product-size"><?php echo esc_html( $length . 'x' . $width . 'x' . $height . ' cm' ); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Bottom Actions -->
            <div class="ar-bottom-actions" id="arBottomActions" style="display: none;">
                <div class="ar-placed-info">
                    <span id="arPlacedCount">0</span> <?php echo esc_html( furniva_t( 'items placed', 'sản phẩm đã đặt' ) ); ?>
                </div>
                <div class="ar-action-buttons">
                    <button type="button" class="ar-btn ar-btn-secondary" id="arSaveLayoutBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Save Layout', 'Lưu Layout' ) ); ?></span>
                    </button>
                    <button type="button" class="ar-btn ar-btn-secondary" id="arShareBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Share', 'Chia sẻ' ) ); ?></span>
                    </button>
                    <button type="button" class="ar-btn ar-btn-primary" id="arCaptureBtn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <span><?php echo esc_html( furniva_t( 'Save Image', 'Lưu Ảnh' ) ); ?></span>
                    </button>
                </div>
            </div>

        </div>
    </section>

    <!-- Product Detail Modal -->
    <div class="ar-modal" id="arProductModal" style="display: none;">
        <div class="ar-modal-content">
            <button type="button" class="ar-modal-close" id="arModalClose">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="ar-modal-body">
                <div class="ar-modal-image">
                    <img id="arModalProductImage" src="" alt="" />
                </div>
                <div class="ar-modal-info">
                    <h3 id="arModalProductName"></h3>
                    <p class="ar-modal-price" id="arModalProductPrice"></p>
                    <p class="ar-modal-desc" id="arModalProductDesc"></p>
                    <div class="ar-modal-actions">
                        <a href="#" class="ar-btn ar-btn-primary" id="arModalViewProduct">
                            <?php echo esc_html( furniva_t( 'View Product', 'Xem Sản Phẩm' ) ); ?>
                        </a>
                        <button type="button" class="ar-btn ar-btn-secondary" id="arModalAddToCart">
                            <?php echo esc_html( furniva_t( 'Add to Cart', 'Thêm Vào Giỏ' ) ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Hidden Canvas for Image Generation -->
<canvas id="arExportCanvas" style="display: none;"></canvas>

<script>
window.addEventListener('DOMContentLoaded', function() {
    // AR Visualizer Module
    const ARVisualizer = {
        canvas: null,
        ctx: null,
        roomImage: null,
        placedProducts: [],
        selectedProduct: null,
        isDragging: false,
        dragOffset: { x: 0, y: 0 },
        scale: 1,
        currentStream: null,
        facingMode: 'environment',

        init: function() {
            this.canvas = document.getElementById('arRoomCanvas');
            this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
            this.bindEvents();
            this.initProductCards();
        },

        bindEvents: function() {
            // File upload
            const fileInput = document.getElementById('arFileInput');
            if (fileInput) {
                fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
            }

            // Camera
            const cameraBtn = document.getElementById('arCameraBtn');
            if (cameraBtn) {
                cameraBtn.addEventListener('click', () => this.startCamera());
            }

            const cancelCameraBtn = document.getElementById('arCancelCameraBtn');
            if (cancelCameraBtn) {
                cancelCameraBtn.addEventListener('click', () => this.stopCamera());
            }

            const captureBtn = document.getElementById('arCaptureBtn');
            if (captureBtn) {
                captureBtn.addEventListener('click', () => this.capturePhoto());
            }

            const flipCameraBtn = document.getElementById('arFlipCameraBtn');
            if (flipCameraBtn) {
                flipCameraBtn.addEventListener('click', () => this.flipCamera());
            }

            // Canvas controls
            const addProductBtn = document.getElementById('arAddProductBtn');
            if (addProductBtn) {
                addProductBtn.addEventListener('click', () => this.showProductPanel());
            }

            const zoomInBtn = document.getElementById('arZoomInBtn');
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', () => this.zoomIn());
            }

            const zoomOutBtn = document.getElementById('arZoomOutBtn');
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', () => this.zoomOut());
            }

            const resetBtn = document.getElementById('arResetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => this.resetCanvas());
            }

            const changePhotoBtn = document.getElementById('arChangePhotoBtn');
            if (changePhotoBtn) {
                changePhotoBtn.addEventListener('click', () => this.changePhoto());
            }

            // Bottom actions
            const saveLayoutBtn = document.getElementById('arSaveLayoutBtn');
            if (saveLayoutBtn) {
                saveLayoutBtn.addEventListener('click', () => this.saveLayout());
            }

            const captureResultBtn = document.getElementById('arCaptureBtn');
            if (captureResultBtn) {
                captureResultBtn.addEventListener('click', () => this.captureResult());
            }

            const shareBtn = document.getElementById('arShareBtn');
            if (shareBtn) {
                shareBtn.addEventListener('click', () => this.shareLayout());
            }

            // Product search
            const searchInput = document.getElementById('arProductSearch');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => this.filterProducts(e.target.value));
            }

            // Category filters
            document.querySelectorAll('.ar-category-btn').forEach(btn => {
                btn.addEventListener('click', (e) => this.filterByCategory(e.target.dataset.category));
            });

            // Modal
            const modalClose = document.getElementById('arModalClose');
            if (modalClose) {
                modalClose.addEventListener('click', () => this.closeModal());
            }

            // Canvas interactions
            if (this.canvas) {
                this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
                this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
                this.canvas.addEventListener('mouseup', () => this.handleMouseUp());
                this.canvas.addEventListener('mouseleave', () => this.handleMouseUp());

                // Touch events
                this.canvas.addEventListener('touchstart', (e) => this.handleTouchStart(e));
                this.canvas.addEventListener('touchmove', (e) => this.handleTouchMove(e));
                this.canvas.addEventListener('touchend', () => this.handleMouseUp());
            }
        },

        handleFileSelect: function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                if (file.size > 10 * 1024 * 1024) {
                    alert('<?php echo esc_js( furniva_t( 'File too large. Max 10MB.', 'File quá lớn. Tối đa 10MB.' ) ); ?>');
                    return;
                }

                const reader = new FileReader();
                reader.onload = (event) => {
                    this.loadRoomImage(event.target.result);
                };
                reader.readAsDataURL(file);
            }
        },

        startCamera: function() {
            const video = document.getElementById('arVideo');
            const cameraSection = document.getElementById('arCameraSection');
            const uploadZone = document.getElementById('arUploadZone');

            if (!video) return;

            navigator.mediaDevices.getUserMedia({
                video: { facingMode: this.facingMode, width: 1280, height: 720 }
            }).then(stream => {
                this.currentStream = stream;
                video.srcObject = stream;
                cameraSection.style.display = 'block';
                uploadZone.style.display = 'none';
            }).catch(err => {
                console.error('Camera error:', err);
                alert('<?php echo esc_js( furniva_t( 'Could not access camera. Please upload a photo instead.', 'Không thể truy cập camera. Vui lòng tải ảnh lên.' ) ); ?>');
            });
        },

        stopCamera: function() {
            const video = document.getElementById('arVideo');
            const cameraSection = document.getElementById('arCameraSection');
            const uploadZone = document.getElementById('arUploadZone');

            if (this.currentStream) {
                this.currentStream.getTracks().forEach(track => track.stop());
                this.currentStream = null;
            }

            cameraSection.style.display = 'none';
            uploadZone.style.display = 'flex';
        },

        flipCamera: function() {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            this.stopCamera();
            this.startCamera();
        },

        capturePhoto: function() {
            const video = document.getElementById('arVideo');
            const canvas = document.getElementById('arCanvas');

            if (!video || !canvas) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0);

            const imageData = canvas.toDataURL('image/jpeg', 0.9);
            this.stopCamera();
            this.loadRoomImage(imageData);
        },

        loadRoomImage: function(src) {
            const uploadZone = document.getElementById('arUploadZone');
            const roomCanvas = document.getElementById('arRoomCanvas');
            const roomImage = document.getElementById('arRoomImage');
            const bottomActions = document.getElementById('arBottomActions');
            const productsSection = document.getElementById('arProductsSection');

            this.roomImage = new Image();
            this.roomImage.onload = () => {
                uploadZone.style.display = 'none';
                roomCanvas.style.display = 'block';
                roomImage.src = src;
                bottomActions.style.display = 'flex';
                productsSection.classList.add('minimized');

                // Setup canvas dimensions
                this.setupCanvas();
            };
            this.roomImage.src = src;
        },

        setupCanvas: function() {
            const wrapper = document.getElementById('arCanvasWrapper');
            if (!wrapper) return;

            const rect = wrapper.getBoundingClientRect();
            this.canvasWidth = rect.width;
            this.canvasHeight = rect.height;
            this.scale = Math.min(
                this.canvasWidth / this.roomImage.width,
                this.canvasHeight / this.roomImage.height
            );
        },

        changePhoto: function() {
            const uploadZone = document.getElementById('arUploadZone');
            const roomCanvas = document.getElementById('arRoomCanvas');
            const bottomActions = document.getElementById('arBottomActions');
            const productsSection = document.getElementById('arProductsSection');

            uploadZone.style.display = 'flex';
            roomCanvas.style.display = 'none';
            bottomActions.style.display = 'none';
            productsSection.classList.remove('minimized');

            this.placedProducts = [];
            this.roomImage = null;
        },

        resetCanvas: function() {
            this.placedProducts = [];
            this.scale = 1;
            this.renderProducts();
            this.updatePlacedCount();
        },

        zoomIn: function() {
            this.scale = Math.min(this.scale + 0.1, 2);
            this.renderProducts();
        },

        zoomOut: function() {
            this.scale = Math.max(this.scale - 0.1, 0.5);
            this.renderProducts();
        },

        initProductCards: function() {
            const cards = document.querySelectorAll('.ar-product-card');
            cards.forEach(card => {
                card.addEventListener('click', () => {
                    this.addProductToCanvas(card);
                });

                card.addEventListener('dblclick', () => {
                    this.showProductModal(card);
                });
            });
        },

        addProductToCanvas: function(card) {
            const productData = {
                id: card.dataset.productId,
                name: card.dataset.productName,
                price: card.dataset.productPrice,
                image: card.dataset.productImage,
                url: card.dataset.productUrl,
                width: parseFloat(card.dataset.productWidth) || 100,
                height: parseFloat(card.dataset.productHeight) || 80,
                x: this.canvasWidth / 2,
                y: this.canvasHeight / 2,
                scale: 1,
                rotation: 0
            };

            this.placedProducts.push(productData);
            this.renderProducts();
            this.updatePlacedCount();
        },

        renderProducts: function() {
            const overlay = document.getElementById('arCanvasOverlay');
            if (!overlay) return;

            overlay.innerHTML = '';

            this.placedProducts.forEach((product, index) => {
                const productEl = document.createElement('div');
                productEl.className = 'ar-placed-product';
                productEl.dataset.index = index;

                const img = document.createElement('img');
                img.src = product.image;
                img.alt = product.name;
                img.draggable = false;

                // Calculate size based on real dimensions
                const baseSize = 120;
                const scaledWidth = baseSize * (product.width / 100) * this.scale * product.scale;
                const scaledHeight = baseSize * (product.height / 100) * this.scale * product.scale;

                productEl.style.cssText = `
                    left: ${product.x}px;
                    top: ${product.y}px;
                    width: ${scaledWidth}px;
                    height: ${scaledHeight}px;
                    transform: translate(-50%, -50%) rotate(${product.rotation}deg);
                `;

                productEl.appendChild(img);

                // Controls
                const controls = document.createElement('div');
                controls.className = 'ar-product-controls';
                controls.innerHTML = `
                    <button class="ar-control-sm" data-action="rotate-left" title="Rotate left">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10"/>
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                        </svg>
                    </button>
                    <button class="ar-control-sm" data-action="rotate-right" title="Rotate right">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>
                    </button>
                    <button class="ar-control-sm" data-action="scale-up" title="Scale up">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <button class="ar-control-sm" data-action="scale-down" title="Scale down">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <button class="ar-control-sm ar-remove" data-action="remove" title="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                `;

                productEl.appendChild(controls);

                // Product info tooltip
                const info = document.createElement('div');
                info.className = 'ar-product-info-tooltip';
                info.innerHTML = `<strong>${product.name}</strong><span>${formatPrice(product.price)}</span>`;
                productEl.appendChild(info);

                // Event listeners
                productEl.addEventListener('mousedown', (e) => this.startDrag(e, index));
                productEl.addEventListener('touchstart', (e) => this.startDrag(e, index));

                controls.querySelectorAll('button').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.handleProductAction(index, btn.dataset.action);
                    });
                });

                overlay.appendChild(productEl);
            });
        },

        startDrag: function(e, index) {
            e.preventDefault();
            this.selectedProduct = index;
            this.isDragging = true;

            const product = this.placedProducts[index];
            const overlay = document.getElementById('arCanvasOverlay');
            const rect = overlay.getBoundingClientRect();

            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            this.dragOffset = {
                x: clientX - rect.left - product.x,
                y: clientY - rect.top - product.y
            };

            // Highlight selected
            document.querySelectorAll('.ar-placed-product').forEach((el, i) => {
                el.classList.toggle('selected', i === index);
            });
        },

        handleMouseDown: function(e) {
            if (e.target.closest('.ar-placed-product')) return;
            this.selectedProduct = null;
            document.querySelectorAll('.ar-placed-product').forEach(el => {
                el.classList.remove('selected');
            });
        },

        handleMouseMove: function(e) {
            if (!this.isDragging || this.selectedProduct === null) return;

            const overlay = document.getElementById('arCanvasOverlay');
            const rect = overlay.getBoundingClientRect();

            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;

            const product = this.placedProducts[this.selectedProduct];
            product.x = Math.max(0, Math.min(clientX - rect.left - this.dragOffset.x, rect.width));
            product.y = Math.max(0, Math.min(clientY - rect.top - this.dragOffset.y, rect.height));

            this.renderProducts();
        },

        handleTouchStart: function(e) {
            if (e.target.closest('.ar-placed-product')) return;
            this.selectedProduct = null;
        },

        handleTouchMove: function(e) {
            if (!e.target.closest('.ar-placed-product')) return;
            this.handleMouseMove(e);
        },

        handleMouseUp: function() {
            this.isDragging = false;
        },

        handleProductAction: function(index, action) {
            const product = this.placedProducts[index];

            switch(action) {
                case 'rotate-left':
                    product.rotation -= 15;
                    break;
                case 'rotate-right':
                    product.rotation += 15;
                    break;
                case 'scale-up':
                    product.scale = Math.min(product.scale + 0.2, 3);
                    break;
                case 'scale-down':
                    product.scale = Math.max(product.scale - 0.2, 0.3);
                    break;
                case 'remove':
                    this.placedProducts.splice(index, 1);
                    this.updatePlacedCount();
                    break;
            }

            this.renderProducts();
        },

        updatePlacedCount: function() {
            const countEl = document.getElementById('arPlacedCount');
            if (countEl) {
                countEl.textContent = this.placedProducts.length;
            }
        },

        showProductPanel: function() {
            const productsSection = document.getElementById('arProductsSection');
            productsSection.classList.remove('minimized');
        },

        filterProducts: function(query) {
            const cards = document.querySelectorAll('.ar-product-card');
            const lowerQuery = query.toLowerCase();

            cards.forEach(card => {
                const name = card.dataset.productName.toLowerCase();
                const match = name.includes(lowerQuery);
                card.style.display = match ? 'block' : 'none';
            });
        },

        filterByCategory: function(category) {
            const cards = document.querySelectorAll('.ar-product-card');

            document.querySelectorAll('.ar-category-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.category === category);
            });

            cards.forEach(card => {
                if (category === 'all') {
                    card.style.display = 'block';
                } else {
                    const cats = card.dataset.categories.split(',');
                    card.style.display = cats.includes(category) ? 'block' : 'none';
                }
            });
        },

        showProductModal: function(card) {
            const modal = document.getElementById('arProductModal');
            const img = document.getElementById('arModalProductImage');
            const name = document.getElementById('arModalProductName');
            const price = document.getElementById('arModalProductPrice');
            const viewLink = document.getElementById('arModalViewProduct');

            img.src = card.dataset.productImage;
            name.textContent = card.dataset.productName;
            price.innerHTML = formatPrice(parseFloat(card.dataset.productPrice));
            viewLink.href = card.dataset.productUrl;

            modal.style.display = 'flex';
        },

        closeModal: function() {
            document.getElementById('arProductModal').style.display = 'none';
        },

        captureResult: function() {
            const wrapper = document.getElementById('arCanvasWrapper');
            const roomImage = document.getElementById('arRoomImage');

            if (!wrapper || !roomImage) return;

            // Create canvas
            const exportCanvas = document.getElementById('arExportCanvas');
            const ctx = exportCanvas.getContext('2d');

            exportCanvas.width = roomImage.naturalWidth;
            exportCanvas.height = roomImage.naturalHeight;

            // Draw room image
            ctx.drawImage(roomImage, 0, 0);

            // Calculate scale for placed products
            const scaleX = roomImage.naturalWidth / wrapper.offsetWidth;
            const scaleY = roomImage.naturalHeight / wrapper.offsetHeight;

            // Draw placed products
            this.placedProducts.forEach(product => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = product.image;

                if (img.complete) {
                    const x = product.x * scaleX;
                    const y = product.y * scaleY;
                    const width = (120 * (product.width / 100) * this.scale * product.scale) * scaleX;
                    const height = (120 * (product.height / 100) * this.scale * product.scale) * scaleY;

                    ctx.save();
                    ctx.translate(x, y);
                    ctx.rotate(product.rotation * Math.PI / 180);
                    ctx.drawImage(img, -width/2, -height/2, width, height);
                    ctx.restore();
                }
            });

            // Download
            const link = document.createElement('a');
            link.download = 'ar-room-design.jpg';
            link.href = exportCanvas.toDataURL('image/jpeg', 0.9);
            link.click();
        },

        saveLayout: function() {
            // Save layout to localStorage
            const layoutData = {
                products: this.placedProducts,
                scale: this.scale
            };
            localStorage.setItem('ar_layout', JSON.stringify(layoutData));

            // Show toast
            if (window.furnivaToast) {
                furnivaToast('<?php echo esc_js( furniva_t( 'Layout saved!', 'Đã lưu layout!' ) ); ?>');
            }
        },

        shareLayout: function() {
            this.captureResult();
        }
    };

    // Helper function
    function formatPrice(price) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(price * 25000); // Assuming USD to VND conversion
    }

    // Initialize
    ARVisualizer.init();

    // Expose globally
    window.ARVisualizer = ARVisualizer;
});
</script>

<style>
/* AR Visualizer Page Styles */
.ar-visualizer-page {
    background: #f7f4ef;
    min-height: 100vh;
    padding-bottom: 80px;
}

.ar-container {
    max-width: 1440px;
    margin: 0 auto;
    padding: 0 24px;
}

/* Hero */
.ar-hero {
    padding: 32px 0 24px;
    border-bottom: 1px solid #e5ddd2;
    margin-bottom: 32px;
}

.ar-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #6b6357;
    margin-bottom: 20px;
}

.ar-breadcrumb a {
    color: #6b6357;
    text-decoration: none;
}

.ar-breadcrumb a:hover {
    color: #191816;
}

.ar-hero-content {
    text-align: center;
}

.ar-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #c5a880 0%, #d4b896 100%);
    color: #191816;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.15em;
    padding: 8px 16px;
    border-radius: 20px;
    margin-bottom: 16px;
}

.ar-hero-title {
    font-family: "Newsreader", Georgia, serif;
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 500;
    color: #191816;
    margin: 0 0 12px;
    letter-spacing: -0.02em;
}

.ar-hero-subtitle {
    font-size: 15px;
    color: #6b6357;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Main Section */
.ar-main-section {
    padding: 0 0 40px;
}

.ar-workspace {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 32px;
    align-items: start;
}

/* Canvas Section */
.ar-canvas-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ar-canvas-wrapper {
    position: relative;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(43, 29, 22, 0.1);
    min-height: 500px;
}

/* Upload Zone */
.ar-upload-zone {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 500px;
    padding: 40px;
    background: linear-gradient(145deg, #faf8f5 0%, #f4f0eb 100%);
}

.ar-upload-content {
    text-align: center;
    max-width: 400px;
}

.ar-upload-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c5a880;
    box-shadow: 0 4px 20px rgba(197, 168, 128, 0.2);
}

.ar-upload-content h3 {
    font-family: "Newsreader", Georgia, serif;
    font-size: 1.4rem;
    color: #191816;
    margin: 0 0 8px;
}

.ar-upload-content p {
    font-size: 14px;
    color: #6b6357;
    margin: 0 0 24px;
}

.ar-upload-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-bottom: 16px;
}

.ar-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
    border: none;
}

.ar-btn-primary {
    background: #191816;
    color: white;
}

.ar-btn-primary:hover {
    background: #2b2520;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(25, 24, 22, 0.25);
}

.ar-btn-secondary {
    background: white;
    color: #191816;
    border: 1.5px solid #d8c4ae;
}

.ar-btn-secondary:hover {
    border-color: #191816;
    background: #faf8f5;
}

.ar-upload-hint {
    font-size: 12px;
    color: #8a7a65;
}

/* Room Canvas */
.ar-room-canvas {
    position: relative;
    width: 100%;
    min-height: 500px;
}

.ar-room-image {
    width: 100%;
    height: auto;
    display: block;
}

.ar-canvas-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}

/* Placed Products */
.ar-placed-product {
    position: absolute;
    cursor: move;
    pointer-events: auto;
    transition: box-shadow 0.2s;
}

.ar-placed-product:hover,
.ar-placed-product.selected {
    z-index: 100;
}

.ar-placed-product:hover img,
.ar-placed-product.selected img {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
}

.ar-placed-product img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    pointer-events: none;
}

.ar-product-controls {
    position: absolute;
    top: -40px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 4px;
    background: white;
    padding: 4px;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
}

.ar-placed-product:hover .ar-product-controls,
.ar-placed-product.selected .ar-product-controls {
    opacity: 1;
    visibility: visible;
}

.ar-control-sm {
    width: 28px;
    height: 28px;
    border: none;
    background: #f7f4ef;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b6357;
    transition: all 0.2s;
}

.ar-control-sm:hover {
    background: #191816;
    color: white;
}

.ar-control-sm.ar-remove:hover {
    background: #bf5b50;
}

.ar-product-info-tooltip {
    position: absolute;
    bottom: -32px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(25, 24, 22, 0.9);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.ar-placed-product:hover .ar-product-info-tooltip {
    opacity: 1;
    visibility: visible;
}

.ar-product-info-tooltip strong {
    font-weight: 600;
}

.ar-product-info-tooltip span {
    color: #c5a880;
}

/* Canvas Controls */
.ar-canvas-controls {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ar-control-btn {
    width: 44px;
    height: 44px;
    border: none;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #191816;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
}

.ar-control-btn:hover {
    background: white;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.ar-change-photo-btn {
    position: absolute;
    bottom: 16px;
    right: 16px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #191816;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
}

.ar-change-photo-btn:hover {
    background: white;
}

/* Camera Section */
.ar-camera-section {
    position: relative;
    background: #191816;
}

.ar-camera-section video {
    width: 100%;
    display: block;
}

.ar-camera-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 20px;
}

.ar-capture-btn {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: white;
    border: 4px solid #c5a880;
    padding: 0;
}

.ar-capture-btn:hover {
    transform: scale(1.05);
}

/* Room Info */
.ar-room-info {
    background: white;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(43, 29, 22, 0.06);
}

.ar-room-info h3 {
    font-family: "Newsreader", Georgia, serif;
    font-size: 1.1rem;
    color: #191816;
    margin: 0 0 16px;
}

.ar-tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ar-tips-list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f0ebe4;
}

.ar-tips-list li:last-child {
    border-bottom: none;
}

.ar-tips-list svg {
    color: #6f806c;
    flex-shrink: 0;
    margin-top: 2px;
}

.ar-tips-list span {
    font-size: 13px;
    color: #6b6357;
    line-height: 1.5;
}

/* Products Section */
.ar-products-section {
    background: white;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(43, 29, 22, 0.06);
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    position: sticky;
    top: 100px;
    transition: all 0.3s ease;
}

.ar-products-section.minimized {
    max-height: 60px;
    overflow: hidden;
    padding: 12px 24px;
}

.ar-products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.ar-products-header h2 {
    font-family: "Newsreader", Georgia, serif;
    font-size: 1.2rem;
    color: #191816;
    margin: 0;
}

.ar-products-count {
    font-size: 12px;
    color: #8a7a65;
    background: #f7f4ef;
    padding: 4px 10px;
    border-radius: 12px;
}

.ar-product-search {
    position: relative;
    margin-bottom: 16px;
}

.ar-product-search svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #8a7a65;
}

.ar-product-search input {
    width: 100%;
    padding: 12px 12px 12px 42px;
    border: 1.5px solid #e5ddd2;
    border-radius: 10px;
    font-size: 13px;
    background: #faf8f5;
    transition: border-color 0.2s;
}

.ar-product-search input:focus {
    border-color: #c5a880;
    outline: none;
    background: white;
}

.ar-product-categories {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 12px;
    margin-bottom: 16px;
    border-bottom: 1px solid #f0ebe4;
}

.ar-category-btn {
    padding: 8px 14px;
    border: none;
    background: #f7f4ef;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    color: #6b6357;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
}

.ar-category-btn:hover {
    background: #f0ebe4;
}

.ar-category-btn.active {
    background: #191816;
    color: white;
}

.ar-products-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.ar-product-card {
    background: #faf8f5;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.25s ease;
}

.ar-product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(43, 29, 22, 0.12);
}

.ar-product-image {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
}

.ar-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.ar-product-card:hover .ar-product-image img {
    transform: scale(1.05);
}

.ar-product-overlay {
    position: absolute;
    inset: 0;
    background: rgba(25, 24, 22, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
}

.ar-product-card:hover .ar-product-overlay {
    opacity: 1;
}

.ar-add-label {
    background: white;
    color: #191816;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.ar-product-info {
    padding: 12px;
}

.ar-product-info h4 {
    font-size: 12px;
    font-weight: 600;
    color: #191816;
    margin: 0 0 6px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.ar-product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ar-product-price {
    font-size: 12px;
    font-weight: 700;
    color: #191816;
}

.ar-product-size {
    font-size: 10px;
    color: #8a7a65;
}

/* Bottom Actions */
.ar-bottom-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 16px 24px;
    border-radius: 16px;
    margin-top: 20px;
    box-shadow: 0 4px 16px rgba(43, 29, 22, 0.06);
}

.ar-placed-info {
    font-size: 14px;
    color: #6b6357;
}

.ar-placed-info span {
    font-weight: 700;
    color: #191816;
    font-size: 18px;
}

.ar-action-buttons {
    display: flex;
    gap: 12px;
}

/* Modal */
.ar-modal {
    position: fixed;
    inset: 0;
    background: rgba(25, 24, 22, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
    backdrop-filter: blur(8px);
}

.ar-modal-content {
    background: white;
    border-radius: 20px;
    max-width: 600px;
    width: 100%;
    position: relative;
    animation: ar-modal-in 0.3s ease;
}

@keyframes ar-modal-in {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.ar-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 40px;
    height: 40px;
    border: none;
    background: #f7f4ef;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #191816;
    transition: all 0.2s;
    z-index: 10;
}

.ar-modal-close:hover {
    background: #191816;
    color: white;
}

.ar-modal-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.ar-modal-image {
    background: #faf8f5;
    padding: 40px;
}

.ar-modal-image img {
    width: 100%;
    height: auto;
}

.ar-modal-info {
    padding: 40px;
    display: flex;
    flex-direction: column;
}

.ar-modal-info h3 {
    font-family: "Newsreader", Georgia, serif;
    font-size: 1.4rem;
    color: #191816;
    margin: 0 0 8px;
}

.ar-modal-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: #c5a880;
    margin: 0 0 16px;
}

.ar-modal-desc {
    font-size: 14px;
    color: #6b6357;
    line-height: 1.6;
    flex: 1;
}

.ar-modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
    .ar-workspace {
        grid-template-columns: 1fr;
    }

    .ar-products-section {
        position: static;
        max-height: none;
    }

    .ar-products-section.minimized {
        max-height: none;
    }
}

@media (max-width: 640px) {
    .ar-container {
        padding: 0 16px;
    }

    .ar-upload-buttons {
        flex-direction: column;
    }

    .ar-products-grid {
        grid-template-columns: 1fr;
    }

    .ar-bottom-actions {
        flex-direction: column;
        gap: 16px;
    }

    .ar-modal-body {
        grid-template-columns: 1fr;
    }

    .ar-modal-image {
        padding: 24px;
    }

    .ar-modal-info {
        padding: 24px;
    }
}
</style>

<?php get_footer(); ?>
