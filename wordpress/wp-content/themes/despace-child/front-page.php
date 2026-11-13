<?php
/**
 * Front page template.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main>
	<section class="ds-hero">
		<div class="ds-container">
			<div class="ds-hero-copy">
				<div class="ds-eyebrow">A quieter kind of luxury</div>
				<h1>Không gian sống,<br>chạm đến cảm xúc.</h1>
				<p>Nội thất tinh tuyển cho nhịp sống hiện đại. Đường nét giản lược, vật liệu tự nhiên và vẻ đẹp bền lâu.</p>
				<a class="ds-button" href="<?php echo esc_url( despace_staging_shop_url() ); ?>">Khám phá bộ sưu tập</a>
			</div>
			<div class="ds-hero-art" aria-hidden="true"></div>
		</div>
	</section>

	<section id="collections" class="ds-section">
		<div class="ds-container">
			<div class="ds-section-head">
				<div><div class="ds-eyebrow">Curated for your home</div><h2>Khám phá theo không gian</h2></div>
				<p class="ds-section-intro">Những lựa chọn được tuyển chọn để kết nối công năng, chất liệu và cá tính riêng của từng căn phòng.</p>
			</div>
			<div class="ds-category-grid">
				<a class="ds-category" href="<?php echo esc_url( add_query_arg( 'product_cat', 'phong-khach', despace_staging_shop_url() ) ); ?>"><strong>Phòng khách</strong></a>
				<a class="ds-category" href="<?php echo esc_url( add_query_arg( 'product_cat', 'phong-ngu', despace_staging_shop_url() ) ); ?>"><strong>Phòng ngủ</strong></a>
				<a class="ds-category" href="<?php echo esc_url( add_query_arg( 'product_cat', 'phong-an', despace_staging_shop_url() ) ); ?>"><strong>Phòng ăn</strong></a>
			</div>
		</div>
	</section>

	<section class="ds-section ds-products">
		<div class="ds-container">
			<div class="ds-section-head">
				<div><div class="ds-eyebrow">The selection</div><h2>Sản phẩm nổi bật</h2></div>
				<a class="ds-eyebrow" href="<?php echo esc_url( despace_staging_shop_url() ); ?>">Xem tất cả →</a>
			</div>
			<?php echo do_shortcode( '[products limit="8" columns="4" orderby="menu_order date" order="ASC"]' ); ?>
		</div>
	</section>

	<section class="ds-section" style="background:#eeede7">
		<div class="ds-container ds-section-head">
			<div><div class="ds-eyebrow">Design that stays</div><h2>Chất lượng trong từng chi tiết.</h2></div>
			<p class="ds-section-intro">Đây là bản staging MVP. Bạn có thể duyệt sản phẩm mẫu, tạo tài khoản, thêm giỏ hàng và đặt đơn thử để đánh giá luồng thương mại.</p>
		</div>
	</section>
</main>
<?php get_footer(); ?>
