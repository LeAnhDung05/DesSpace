<?php
/**
 * Furniva static page template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) :
	the_post();
	$page_slug = get_post_field( 'post_name', get_the_ID() );

	// Bespoke My Account page layout
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		echo '<div class="ax-account-page-wrap">';
		the_content();
		echo '</div>';
	} else {
		furniva_page_head( get_the_title(), get_the_title() );

		// WooCommerce endpoint pages (cart/checkout) need a constrained container
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			echo '<div class="f-wrap f-wc-content">';
			the_content();
			echo '</div>';
		} else {
		echo '<div class="entry-content f-page-content f-page-content--' . esc_attr( sanitize_html_class( $page_slug ) ) . '">';
		the_content();
		if ( 'about' === $page_slug ) :
			?>
			<section class="f-about-values f-wrap" aria-labelledby="f-about-values-title">
				<div class="f-about-values-head">
				<span class="f-kicker"><?php echo esc_html( furniva_t( 'A considered approach', 'Triết lý thiết kế' ) ); ?></span>
				<h2 id="f-about-values-title"><?php echo esc_html( furniva_t( 'Made for the way life unfolds.', 'Được tạo nên cho nhịp sống thường ngày.' ) ); ?></h2>
				</div>
				<div class="f-about-values-grid">
					<article><span>01</span><h3><?php echo esc_html( furniva_t( 'Designed in New York', 'Thiết kế tại New York' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Every piece begins in our studio, where proportion, comfort and everyday use shape the design.', 'Mỗi sản phẩm bắt đầu từ studio của chúng tôi, nơi tỷ lệ, sự thoải mái và nhu cầu sử dụng hằng ngày định hình thiết kế.' ) ); ?></p></article>
					<article><span>02</span><h3><?php echo esc_html( furniva_t( 'Materials with purpose', 'Chất liệu được chọn có chủ đích' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'FSC-certified woods, OEKO-TEX fabrics and recycled steel guide our material choices.', 'Gỗ đạt chứng nhận FSC, vải đạt chuẩn OEKO-TEX và thép tái chế là nền tảng cho lựa chọn vật liệu của chúng tôi.' ) ); ?></p></article>
					<article><span>03</span><h3><?php echo esc_html( furniva_t( 'Tested in real homes', 'Thử nghiệm trong không gian sống thực tế' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'We prototype and refine each design in lived-in spaces before it joins the collection.', 'Chúng tôi làm mẫu và hoàn thiện từng thiết kế trong những không gian có người sử dụng trước khi đưa vào bộ sưu tập.' ) ); ?></p></article>
				</div>
			</section>
			<?php
		elseif ( 'contact' === $page_slug ) :
			?>
			<section class="f-contact-help f-wrap" aria-label="<?php echo esc_attr( furniva_t( 'Customer care', 'Chăm sóc khách hàng' ) ); ?>">
				<div><span>01</span><h3><?php echo esc_html( furniva_t( 'Order support', 'Hỗ trợ đơn hàng' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Help with delivery, order status and returns.', 'Hỗ trợ thông tin giao hàng, tình trạng đơn và đổi trả.' ) ); ?></p></div>
				<div><span>02</span><h3><?php echo esc_html( furniva_t( 'Product advice', 'Tư vấn sản phẩm' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Talk with us about dimensions, finishes and materials.', 'Trao đổi với chúng tôi về kích thước, lớp hoàn thiện và chất liệu.' ) ); ?></p></div>
				<div><span>03</span><h3><?php echo esc_html( furniva_t( 'A thoughtful reply', 'Phản hồi tận tâm' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Our team usually responds within one business day.', 'Đội ngũ thường phản hồi trong vòng một ngày làm việc.' ) ); ?></p></div>
			</section>
			<?php
		endif;
		echo '</div>';
	}
}
endwhile;

get_footer();
