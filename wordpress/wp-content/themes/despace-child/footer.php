<?php
/**
 * DeSpace Footer Template.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_id  = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
$shop_url = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
?>
</main><!-- #content -->

<!-- Trust & Commitments Bar -->
<section class="ds-trust-bar" aria-label="<?php esc_attr_e( 'Cam kết thương hiệu DeSpace', 'despace-child' ); ?>">
	<div class="ds-wrap">
		<div class="ds-trust-grid">
			<div class="ds-trust-item">
				<div class="ds-trust-icon">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<path d="M6 3h12l4 6-10 12L2 9z"></path>
						<path d="M11 3 8 9l4 12 4-12-3-6"></path>
						<path d="M2 9h20"></path>
					</svg>
				</div>
				<div>
					<strong><?php echo esc_html( despace_t( 'Artisan Craftsmanship', 'Vật liệu Tuyển chọn' ) ); ?></strong>
					<span><?php echo esc_html( despace_t( 'Carefully curated engineered & natural woods', 'Gỗ sồi, óc chó & kim loại cao cấp' ) ); ?></span>
				</div>
			</div>

			<div class="ds-trust-item">
				<div class="ds-trust-icon">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
						<polyline points="2 17 12 22 22 17"></polyline>
						<polyline points="2 12 12 17 22 12"></polyline>
					</svg>
				</div>
				<div>
					<strong><?php echo esc_html( despace_t( 'Interactive 3D & AR', 'Mô hình 3D & Thực tế AR' ) ); ?></strong>
					<span><?php echo esc_html( despace_t( 'Preview exact scale in your room via WebXR', 'Ướm thử vào không gian thực trước khi mua' ) ); ?></span>
				</div>
			</div>

			<div class="ds-trust-item">
				<div class="ds-trust-icon">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<rect x="1" y="3" width="15" height="13"></rect>
						<polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
						<circle cx="5.5" cy="18.5" r="2.5"></circle>
						<circle cx="18.5" cy="18.5" r="2.5"></circle>
					</svg>
				</div>
				<div>
					<strong><?php echo esc_html( despace_t( 'White-Glove Delivery', 'Giao & Lắp đặt Tận nơi' ) ); ?></strong>
					<span><?php echo esc_html( despace_t( 'Complimentary nationwide delivery on select items', 'Đội ngũ chuyên nghiệp, hỗ trợ toàn diện' ) ); ?></span>
				</div>
			</div>

			<div class="ds-trust-item">
				<div class="ds-trust-icon">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
						<path d="m9 12 2 2 4-4"></path>
					</svg>
				</div>
				<div>
					<strong><?php echo esc_html( despace_t( '5-Year Structural Warranty', 'Bảo hành 5 Năm' ) ); ?></strong>
					<span><?php echo esc_html( despace_t( 'Official guarantee & 30-day hassle-free exchange', 'Cam kết độ bền & hỗ trợ bảo trì dài hạn' ) ); ?></span>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Footer -->
<footer class="ds-footer">
	<div class="ds-wrap">
		<div class="ds-footer__grid">
			<!-- Col 1: Brand Info -->
			<div class="ds-footer__col">
				<div class="ds-brand" style="color:#FAF9F6; margin-bottom: 16px;">
					<span class="ds-brand__logo" style="background:#FAF9F6; color:#111315;">D</span>
					<span>DESPACE</span>
				</div>
				<p style="line-height:1.7; margin-bottom:20px; max-width:320px;">
					<?php echo esc_html( despace_t( 'DeSpace defines the harmony of luxury minimalism and architectural ergonomics, empowering modern lifestyles with 3D/AR spatial visualizer.', 'DeSpace kiến tạo không gian sống đương đại với chuẩn mực thiết kế tối giản, tinh tế, ứng dụng công nghệ 3D/AR để nâng tầm trải nghiệm nội thất cao cấp.' ) ); ?>
				</p>
				<div style="font-size:12px; color:var(--ds-accent);">
					Mã dự án: <strong>DESPACE-2026</strong> | PAIC
				</div>
			</div>

			<!-- Col 2: Categories -->
			<div class="ds-footer__col">
				<h4><?php echo esc_html( despace_t( 'Spaces', 'Không gian' ) ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( add_query_arg( 'category', 'shoe-racks-storage', $shop_url ) ); ?>"><?php echo esc_html( despace_t( 'Living & Hallway', 'Phòng khách & Tiền sảnh' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'category', 'wardrobes', $shop_url ) ); ?>"><?php echo esc_html( despace_t( 'Bedroom Suites', 'Phòng ngủ sang trọng' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'category', 'bookshelves', $shop_url ) ); ?>"><?php echo esc_html( despace_t( 'Study & Workspaces', 'Phòng làm việc & Thư viện' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'category', 'kitchen-cabinets', $shop_url ) ); ?>"><?php echo esc_html( despace_t( 'Kitchen & Dining', 'Phòng ăn & Bếp' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'has_3d', '1', $shop_url ) ); ?>"><?php echo esc_html( despace_t( '3D & AR Models', 'Sản phẩm 3D & AR' ) ); ?></a></li>
				</ul>
			</div>

			<!-- Col 3: Customer Care -->
			<div class="ds-footer__col">
				<h4><?php echo esc_html( despace_t( 'Customer Care', 'Dịch vụ' ) ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( despace_t( 'Showroom Consultation', 'Tư vấn bài trí không gian' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/warranty/' ) ); ?>"><?php echo esc_html( despace_t( '5-Year Warranty Policy', 'Chính sách bảo hành 5 năm' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>"><?php echo esc_html( despace_t( 'White-Glove Shipping', 'Vận chuyển & Lắp đặt tận nhà' ) ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>"><?php echo esc_html( despace_t( 'Order Tracking', 'Tra cứu trạng thái đơn hàng' ) ); ?></a></li>
				</ul>
			</div>

			<!-- Col 4: Showroom & Contact -->
			<div class="ds-footer__col">
				<h4><?php echo esc_html( despace_t( 'DeSpace Showrooms', 'Showroom DeSpace' ) ); ?></h4>
				<ul style="line-height:1.8;">
					<li><strong>Flagship:</strong> Tòa nhà PAIC, Hà Nội</li>
					<li><strong>Hotline:</strong> <a href="tel:19008888" style="color:var(--ds-accent);">1900 8888</a></li>
					<li><strong>Email:</strong> contact@despace.vn</li>
					<li><strong>Giờ mở cửa:</strong> 08:30 – 21:00 (Hàng ngày)</li>
				</ul>
			</div>
		</div>

		<!-- Copyright -->
		<div class="ds-footer__bottom">
			<div>
				&copy; <?php echo esc_html( date( 'Y' ) ); ?> DeSpace Furniture. Tất cả quyền được bảo lưu.
			</div>
			<div>
				Phát triển trên nền tảng WordPress + WooCommerce + DeSpace Core.
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
