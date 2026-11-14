<?php
/**
 * DeSpace Editorial Homepage.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$shop_id  = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
$shop_url = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );

// Fetch curated products for homepage
$featured_products = function_exists( 'wc_get_products' ) ? wc_get_products( array(
	'status'   => 'publish',
	'limit'    => 8,
	'orderby'  => 'popularity',
	'order'    => 'DESC',
) ) : array();

// Fetch one 3D product for interactive showcase banner
$showcase_product_id = 390; // Default curated 3D product
$data_3d = class_exists( 'DeSpace_3D' ) ? DeSpace_3D::get_product_3d_data( $showcase_product_id ) : array( 'has_3d' => false );
?>

<!-- Hero Section -->
<section class="ds-hero">
	<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/hero-scandinavian.jpg' ); ?>" class="ds-hero__bg" alt="DeSpace Luxury Interior" />
	<div class="ds-wrap">
		<div class="ds-hero__content">
			<span class="ds-hero__tag">
				<span style="color:var(--ds-accent);">✦</span>
				<?php echo esc_html( despace_t( 'New Collection 2026', 'Bộ sưu tập Đương đại 2026' ) ); ?>
			</span>
			<h1>
				<?php echo esc_html( despace_t( 'Architectural Harmony for Elevated Living', 'Kiến tạo Chuẩn mực Không gian Sống Đương đại' ) ); ?>
			</h1>
			<p>
				<?php echo esc_html( despace_t( 'Experience Scandinavian minimalism fused with contemporary luxury. Explore realistic 3D and AR spatial scale before making every choice.', 'Sự kết hợp hoàn hảo giữa tinh thần tối giản Bắc Âu và nét sang trọng hiện đại. Trải nghiệm mô hình 3D và ướm thử thực tế tăng cường (AR) trong chính căn phòng của bạn.' ) ); ?>
			</p>
			<div class="ds-hero__cta">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="ds-btn ds-btn--primary">
					<?php echo esc_html( despace_t( 'Explore Collection', 'Khám phá Bộ sưu tập' ) ); ?> &rarr;
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'has_3d', '1', $shop_url ) ); ?>" class="ds-btn ds-btn--outline">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;">
						<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
					</svg>
					<?php echo esc_html( despace_t( '3D & AR Showroom', 'Trải nghiệm 3D / AR' ) ); ?>
				</a>
			</div>
		</div>
	</div>
</section>

<!-- Category Spaces Bento -->
<section class="ds-section" id="spaces">
	<div class="ds-wrap">
		<div class="ds-section-header">
			<span class="ds-section-tag"><?php echo esc_html( despace_t( 'Curated Spaces', 'Không gian nội thất' ) ); ?></span>
			<h2 class="ds-section-title"><?php echo esc_html( despace_t( 'Designed for Every Room', 'Thiết kế theo Không gian Sống' ) ); ?></h2>
			<p class="ds-section-subtitle"><?php echo esc_html( despace_t( 'Harmonious compositions engineered for comfort, storage, and enduring aesthetic elegance.', 'Mỗi thiết kế là một điểm nhấn kiến trúc, đem đến sự tiện nghi và vẻ đẹp bền vững theo thời gian.' ) ); ?></p>
		</div>

		<div class="ds-category-grid">
			<!-- Room 1 -->
			<a href="<?php echo esc_url( add_query_arg( 'category', 'shoe-racks-storage', $shop_url ) ); ?>" class="ds-cat-card">
				<div class="ds-cat-card__img-wrap">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-living.jpg' ); ?>" class="ds-cat-card__img" alt="Phòng khách & Tiền sảnh" />
				</div>
				<div class="ds-cat-card__content">
					<h3 class="ds-cat-card__title"><?php echo esc_html( despace_t( 'Living & Hallway', 'Phòng khách & Tiền sảnh' ) ); ?></h3>
					<span class="ds-cat-card__count"><?php echo esc_html( despace_t( 'Tủ giày, Tủ đa năng & Sofa', 'Tủ giày, Tủ đa năng & Sofa' ) ); ?></span>
				</div>
			</a>

			<!-- Room 2 -->
			<a href="<?php echo esc_url( add_query_arg( 'category', 'wardrobes', $shop_url ) ); ?>" class="ds-cat-card">
				<div class="ds-cat-card__img-wrap">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-bedroom.jpg' ); ?>" class="ds-cat-card__img" alt="Phòng ngủ sang trọng" />
				</div>
				<div class="ds-cat-card__content">
					<h3 class="ds-cat-card__title"><?php echo esc_html( despace_t( 'Bedroom Suites', 'Phòng ngủ Sang trọng' ) ); ?></h3>
					<span class="ds-cat-card__count"><?php echo esc_html( despace_t( 'Tủ quần áo 2-4 cánh & Giường', 'Tủ quần áo 2-4 cánh & Giường' ) ); ?></span>
				</div>
			</a>

			<!-- Room 3 -->
			<a href="<?php echo esc_url( add_query_arg( 'category', 'bookshelves', $shop_url ) ); ?>" class="ds-cat-card">
				<div class="ds-cat-card__img-wrap">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-workspace.jpg' ); ?>" class="ds-cat-card__img" alt="Phòng làm việc" />
				</div>
				<div class="ds-cat-card__content">
					<h3 class="ds-cat-card__title"><?php echo esc_html( despace_t( 'Study & Workspaces', 'Phòng làm việc & Thư viện' ) ); ?></h3>
					<span class="ds-cat-card__count"><?php echo esc_html( despace_t( 'Kệ sách góc, Tủ tài liệu', 'Kệ sách góc, Tủ tài liệu' ) ); ?></span>
				</div>
			</a>

			<!-- Room 4 -->
			<a href="<?php echo esc_url( add_query_arg( 'category', 'kitchen-cabinets', $shop_url ) ); ?>" class="ds-cat-card">
				<div class="ds-cat-card__img-wrap">
					<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-dining.jpg' ); ?>" class="ds-cat-card__img" alt="Phòng ăn & Bếp" />
				</div>
				<div class="ds-cat-card__content">
					<h3 class="ds-cat-card__title"><?php echo esc_html( despace_t( 'Dining & Kitchen', 'Phòng ăn & Tủ bếp' ) ); ?></h3>
					<span class="ds-cat-card__count"><?php echo esc_html( despace_t( 'Tủ bếp khô, Tủ Sideboard', 'Tủ bếp khô, Tủ Sideboard' ) ); ?></span>
				</div>
			</a>
		</div>
	</div>
</section>

<!-- 3D Interactive Showcase Section -->
<section class="ds-section" style="padding-top:0;">
	<div class="ds-wrap">
		<div class="ds-3d-showcase">
			<div class="ds-3d-showcase__content">
				<span class="ds-badge-3d">INNOVATIVE SPATIAL TECH</span>
				<h2><?php echo esc_html( despace_t( 'Experience Furniture in True 3D & AR Scale', 'Trải nghiệm Tương tác 3D & AR Không Gian Thật' ) ); ?></h2>
				<p>
					<?php echo esc_html( despace_t( 'No more guesswork. Rotate products 360 degrees, inspect fine material textures, and project exact dimensions directly into your home using WebXR.', 'Không còn băn khoăn về kích thước hay màu sắc. Xoay 360°, phóng to từng chi tiết góc cạnh và chiếu mô hình vào phòng thực tế qua điện thoại với công nghệ WebXR & Scene Viewer.' ) ); ?>
				</p>
				<ul class="ds-3d-features-list">
					<li><span>✓</span> Định dạng GLB tối ưu hóa nén nhẹ, hiển thị mượt mà trên mọi trình duyệt.</li>
					<li><span>✓</span> Kích hoạt chế độ AR thực tế ảo trên cả thiết bị Android và iOS.</li>
					<li><span>✓</span> Hiển thị thông số tỷ lệ chính xác 1:1 trong không gian phòng.</li>
				</ul>
				<a href="<?php echo esc_url( add_query_arg( 'has_3d', '1', $shop_url ) ); ?>" class="ds-btn ds-btn--primary">
					<?php echo esc_html( despace_t( 'View All 3D Furniture', 'Xem Tất Cả Sản Phẩm 3D' ) ); ?> &rarr;
				</a>
			</div>

			<div class="ds-3d-showcase__viewer-box" style="height:440px; border-radius:12px; overflow:hidden; background:#1C1F23; position:relative;">
				<?php if ( $data_3d['has_3d'] ) : ?>
					<model-viewer
						src="<?php echo esc_url( $data_3d['model_url'] ); ?>"
						<?php if ( ! empty( $data_3d['poster_url'] ) ) : ?>poster="<?php echo esc_url( $data_3d['poster_url'] ); ?>"<?php endif; ?>
						alt="DeSpace 3D Furniture"
						loading="lazy"
						camera-controls
						auto-rotate
						shadow-intensity="1.2"
						style="width:100%;height:100%;outline:none;background:#181A1D;">
						<button slot="ar-button" class="despace-ar-btn" style="position:absolute;bottom:20px;right:20px;">
							📱 Xem trong AR
						</button>
					</model-viewer>
				<?php else : ?>
					<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#888;">
						<span>3D Model-viewer đang sẵn sàng...</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<!-- Curated Products Grid -->
<section class="ds-section" style="background:#FFFFFF; border-top:1px solid var(--ds-border);">
	<div class="ds-wrap">
		<div class="ds-section-header">
			<span class="ds-section-tag"><?php echo esc_html( despace_t( 'Signature Design', 'Thiết kế biểu tượng' ) ); ?></span>
			<h2 class="ds-section-title"><?php echo esc_html( despace_t( 'Featured Collection', 'Sản phẩm Tuyển chọn' ) ); ?></h2>
			<p class="ds-section-subtitle"><?php echo esc_html( despace_t( 'The finest artisanal creations selected for high functionality and timeless aesthetics.', 'Những mẫu thiết kế tiêu biểu được khách hàng tin tưởng và lựa chọn nhiều nhất.' ) ); ?></p>
		</div>

		<div class="ds-product-grid">
			<?php if ( ! empty( $featured_products ) ) : ?>
				<?php foreach ( $featured_products as $product ) : 
					if ( ! $product instanceof WC_Product ) continue;
					$p_id = $product->get_id();
					$p_3d = class_exists( 'DeSpace_3D' ) ? DeSpace_3D::get_product_3d_data( $p_id ) : array( 'has_3d' => false );
					$img_id = $product->get_image_id();
					$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
					$cats = get_the_terms( $p_id, 'product_cat' );
					$cat_name = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : 'DeSpace';
				?>
					<div class="ds-card">
						<div class="ds-card__thumb">
							<a href="<?php echo esc_url( get_permalink( $p_id ) ); ?>">
								<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy" />
							</a>
							<?php if ( $p_3d['has_3d'] ) : ?>
								<span class="ds-badge-3d-tag">
									<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
										<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
									</svg>
									3D / AR
								</span>
							<?php endif; ?>
						</div>
						<div class="ds-card__body">
							<div class="ds-card__category"><?php echo esc_html( $cat_name ); ?></div>
							<h3 class="ds-card__title">
								<a href="<?php echo esc_url( get_permalink( $p_id ) ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</a>
							</h3>
							<div class="ds-card__price-row">
								<?php echo wp_kses_post( $product->get_price_html() ); ?>
							</div>
							<div class="ds-card__actions">
								<?php if ( $p_3d['has_3d'] ) : ?>
									<button type="button" class="ds-btn-view-3d-card despace-btn-open-3d" data-product-id="<?php echo esc_attr( $p_id ); ?>" data-model="<?php echo esc_url( $p_3d['model_url'] ); ?>" data-poster="<?php echo esc_url( $p_3d['poster_url'] ); ?>" data-ar="<?php echo $p_3d['enable_ar'] ? '1' : '0'; ?>" data-name="<?php echo esc_attr( $product->get_name() ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
										</svg>
										Xem 3D
									</button>
								<?php endif; ?>
								<a href="<?php echo esc_url( get_permalink( $p_id ) ); ?>" class="ds-btn-view-3d-card" style="background:var(--ds-dark);color:#FAF9F6;border-color:var(--ds-dark);">
									Chi tiết &rarr;
								</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div style="text-align:center; margin-top:50px;">
			<a href="<?php echo esc_url( $shop_url ); ?>" class="ds-btn ds-btn--primary" style="padding:16px 36px;">
				<?php echo esc_html( despace_t( 'View Complete Catalog (40 Items)', 'Xem Toàn Bộ Danh Mục (40 Sản Phẩm)' ) ); ?> &rarr;
			</a>
		</div>
	</div>
</section>

<!-- Brand Story -->
<section class="ds-section" id="story">
	<div class="ds-wrap">
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;">
			<div>
				<span class="ds-section-tag"><?php echo esc_html( despace_t( 'About DeSpace', 'Triết lý Thương hiệu' ) ); ?></span>
				<h2 style="font-size:38px;margin-bottom:20px;"><?php echo esc_html( despace_t( 'A Commitment to Ergonomics, Material Integrity & Modern Space', 'Chuẩn Mực Thiết Kế, Vật Liệu Bền Vững & Không Gian Sống' ) ); ?></h2>
				<p style="color:var(--ds-text-muted);line-height:1.8;margin-bottom:20px;">
					<?php echo esc_html( despace_t( 'Founded with the mission to redefine residential spaces, DeSpace crafts every piece from engineered and solid timbers with structural guarantees. We merge artisan finishing with immersive 3D/AR engineering.', 'DeSpace được thành lập với mục tiêu kiến tạo nên những không gian sống chuẩn mực, tiện nghi và đậm tính nghệ thuật. Từng chi tiết cạnh vát, phụ kiện ray trượt và liên kết mộng được hoàn thiện tỉ mỉ, kết hợp cùng công nghệ 3D/AR tiên tiến giúp bạn tối ưu hóa không gian một cách trực quan nhất.' ) ); ?>
				</p>
				<div style="display:flex;gap:30px;margin-top:30px;">
					<div>
						<strong style="font-size:32px;color:var(--ds-accent-dark);display:block;font-family:var(--ds-font-serif);">100%</strong>
						<span style="font-size:13px;color:var(--ds-text-muted);">Gỗ chuẩn hóa chứng nhận</span>
					</div>
					<div>
						<strong style="font-size:32px;color:var(--ds-accent-dark);display:block;font-family:var(--ds-font-serif);">05 Năm</strong>
						<span style="font-size:13px;color:var(--ds-text-muted);">Bảo hành kết cấu toàn diện</span>
					</div>
					<div>
						<strong style="font-size:32px;color:var(--ds-accent-dark);display:block;font-family:var(--ds-font-serif);">1:1</strong>
						<span style="font-size:13px;color:var(--ds-text-muted);">Tỷ lệ AR trong không gian</span>
					</div>
				</div>
			</div>
			<div>
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/room-living.jpg' ); ?>" alt="DeSpace Philosophy" style="border-radius:var(--ds-radius-md);box-shadow:var(--ds-shadow-lg);" />
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
