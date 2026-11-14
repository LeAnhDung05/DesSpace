<?php
/**
 * DeSpace storefront footer, adapted to local WordPress and WooCommerce pages.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$shop_id = wc_get_page_id( 'shop' );
$shop_url = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
$blog_id = (int) get_option( 'page_for_posts' );
$blog_url = $blog_id ? get_permalink( $blog_id ) : home_url( '/blog/' );
$account_url = furniva_wc_active() ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url( '/my-account/' );
$wishlist_url = function_exists( 'YITH_WCWL' ) ? YITH_WCWL()->get_wishlist_url() : home_url( '/wishlist/' );
$wishlist_count = 0;
if ( function_exists( 'yith_wcwl_count_all_products' ) ) {
	$wishlist_count = yith_wcwl_count_all_products();
} elseif ( function_exists( 'yith_wcwl_count_products' ) ) {
	$wishlist_count = yith_wcwl_count_products();
} elseif ( function_exists( 'YITH_WCWL' ) ) {
	$wishlist_count = count( YITH_WCWL()->get_products() );
}
$footer_categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 5 ) );
if ( is_wp_error( $footer_categories ) ) $footer_categories = array();
$chat_products = function_exists( 'wc_get_products' ) ? wc_get_products( array(
	'status'  => 'publish',
	'limit'   => 30,
	'orderby' => 'popularity',
	'order'   => 'DESC',
) ) : array();
$chat_catalogue = array();
foreach ( $chat_products as $chat_product ) {
	$chat_terms = get_the_terms( $chat_product->get_id(), 'product_cat' );
	$chat_catalogue[] = array(
		'name'       => wp_strip_all_tags( $chat_product->get_name() ),
		'price'      => html_entity_decode( wp_strip_all_tags( $chat_product->get_price_html() ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
		'url'        => get_permalink( $chat_product->get_id() ),
		'image'      => wp_get_attachment_image_url( $chat_product->get_image_id(), 'woocommerce_thumbnail' ),
		'categories' => ( $chat_terms && ! is_wp_error( $chat_terms ) ) ? array_map( 'sanitize_title', wp_list_pluck( $chat_terms, 'slug' ) ) : array(),
	);
}
?>
<?php if ( ! is_front_page() ) : ?></main><?php else : ?></main><?php endif; ?>

<!-- DeSpace Core Brand Commitments / Trust Bar (From Official Brand Identity) -->
<section class="fv-trust-bar" aria-label="<?php echo esc_attr( furniva_t( 'DeSpace Commitments', 'Cam kết thương hiệu DeSpace' ) ); ?>">
	<div class="fv-wrap fv-trust-bar__inner">
		<div class="fv-trust-item">
			<span class="fv-trust-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l4 12 4-12-3-6"/><path d="M2 9h20"/></svg>
			</span>
			<div class="fv-trust-content">
				<strong><?php echo esc_html( furniva_t( 'Premium Craftsmanship', 'Sản phẩm cao cấp' ) ); ?></strong>
				<span><?php echo esc_html( furniva_t( 'Selected materials & lasting luxury value', 'Vật liệu tuyển chọn, chuẩn mực thượng lưu' ) ); ?></span>
			</div>
		</div>
		<div class="fv-trust-item">
			<span class="fv-trust-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
			</span>
			<div class="fv-trust-content">
				<strong><?php echo esc_html( furniva_t( 'Trusted Warranty', 'Bảo hành uy tín' ) ); ?></strong>
				<span><?php echo esc_html( furniva_t( 'Up to 5-year official framework warranty', 'Cam kết chính hãng, hỗ trợ trọn đời' ) ); ?></span>
			</div>
		</div>
		<div class="fv-trust-item">
			<span class="fv-trust-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
			</span>
			<div class="fv-trust-content">
				<strong><?php echo esc_html( furniva_t( 'Nationwide Delivery', 'Giao hàng toàn quốc' ) ); ?></strong>
				<span><?php echo esc_html( furniva_t( 'Safe white-glove setup at your home', 'Vận chuyển chu đáo, bài trí tận tâm' ) ); ?></span>
			</div>
		</div>
		<div class="fv-trust-item">
			<span class="fv-trust-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.5 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
			</span>
			<div class="fv-trust-content">
				<strong><?php echo esc_html( furniva_t( 'Sustainable Living', 'Không gian sống bền vững' ) ); ?></strong>
				<span><?php echo esc_html( furniva_t( 'Eco-conscious wood & lasting beauty', 'Gỗ tự nhiên bền bỉ cùng thời gian' ) ); ?></span>
			</div>
		</div>
	</div>
</section>

<footer class="fv-footer">
	<div class="fv-wrap fv-footer__grid">
		<div class="fv-footer__brand">
			<a class="fv-footer__brand-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="DeSpace — <?php echo esc_attr( furniva_t( 'Home', 'Trang chủ' ) ); ?>">
				<?php $logo_id = get_theme_mod( 'custom_logo' ); ?>
				<?php if ( $logo_id ) : ?>
					<?php echo wp_get_attachment_image( $logo_id, 'full', false, array( 'alt' => 'DeSpace' ) ); ?>
				<?php else : ?>
					<img class="fv-brand-logo-img fv-brand-logo-img--footer" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-logo-header.png' ); ?>" alt="DeSpace - Luxury Living, Lasting Value" width="168" height="56" />
				<?php endif; ?>
			</a>
			<p><?php echo esc_html( furniva_t( 'Thoughtful furniture for the moments that make a home.', 'Nội thất được chọn lọc cho những khoảnh khắc làm nên tổ ấm.' ) ); ?></p>
		</div>

		<div class="fv-footer__column">
			<h2><?php echo esc_html( furniva_t( 'Shop', 'Cửa hàng' ) ); ?></h2>
			<a href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'All products', 'Tất cả sản phẩm' ) ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'orderby', 'popularity', $shop_url ) ); ?>"><?php echo esc_html( furniva_t( 'Best sellers', 'Bán chạy' ) ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>"><?php echo esc_html( furniva_t( 'New arrivals', 'Sản phẩm mới' ) ); ?></a>
			<a href="<?php echo esc_url( $wishlist_url ); ?>"><?php echo esc_html( furniva_t( 'Saved items', 'Sản phẩm yêu thích' ) ); ?></a>
		</div>

		<div class="fv-footer__column">
			<h2><?php echo esc_html( furniva_t( 'Categories', 'Danh mục' ) ); ?></h2>
			<?php foreach ( $footer_categories as $category ) : if ( 'uncategorized' === $category->slug ) continue; ?>
				<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></a>
			<?php endforeach; ?>
		</div>

		<div class="fv-footer__column">
			<h2><?php echo esc_html( furniva_t( 'Customer care', 'Chăm sóc khách hàng' ) ); ?></h2>
			<a href="<?php echo esc_url( home_url( '/shipping-returns/' ) ); ?>"><?php echo esc_html( furniva_t( 'Shipping & returns', 'Giao hàng & đổi trả' ) ); ?></a>
			<a href="<?php echo esc_url( home_url( '/faqs/' ) ); ?>"><?php echo esc_html( furniva_t( 'FAQs', 'Giải đáp' ) ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( furniva_t( 'Contact', 'Liên hệ' ) ); ?></a>
			<a href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html( furniva_t( 'My account', 'Tài khoản' ) ); ?></a>
		</div>

		<div class="fv-footer__column fv-footer__owner">
			<h2><?php echo esc_html( furniva_t( 'Store owner & studio', 'Thông tin chủ cửa hàng' ) ); ?></h2>
			<div class="fv-footer__owner-card">
				<div class="fv-footer__owner-avatar" aria-hidden="true">
					<span>DS</span>
				</div>
				<div class="fv-footer__owner-profile">
					<strong class="fv-footer__owner-name">DeSpace</strong>
					<span class="fv-footer__owner-title"><?php echo esc_html( furniva_t( 'Founder & Design Director', 'Sáng lập & Giám đốc thiết kế' ) ); ?></span>
				</div>
			</div>
			<p class="fv-footer__owner-desc"><?php echo esc_html( furniva_t( 'Direct consultation for bespoke spaces & luxury interior craftsmanship.', 'Tư vấn trực tiếp không gian sống & hoàn thiện nội thất cao cấp.' ) ); ?></p>
			<ul class="fv-footer__owner-details">
				<li>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					<span><strong><?php echo esc_html( furniva_t( 'Showroom:', 'Showroom:' ) ); ?></strong> số 20 ngách 21 ngõ 367, Phố ngô xuân quản, Xã Gia Lâm, Hà Nội</span>
				</li>
				<li>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
					<span><strong>Hotline:</strong> <a href="tel:0332174590">0332 174 590</a></span>
				</li>
				<li>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
					<span><strong>Email:</strong> <a href="mailto:noithat@despace.vn">noithat@despace.vn</a></span>
				</li>
				<li>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					<span><strong><?php echo esc_html( furniva_t( 'Hours:', 'Giờ mở cửa:' ) ); ?></strong> 08:30 – 21:30 (T2 – CN)</span>
				</li>
			</ul>
			<div class="fv-footer__owner-channels">
				<a class="fv-owner-badge-link fv-owner-badge-link--zalo" href="https://zalo.me/0332174590" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( furniva_t( 'Contact DeSpace on Zalo', 'Liên hệ DeSpace qua Zalo' ) ); ?>">
					<svg class="fv-social-svg" viewBox="0 0 48 48" width="18" height="18" fill="none" aria-hidden="true">
						<rect width="48" height="48" rx="12" fill="#0068FF"/>
						<path d="M11 26.5C11 20.15 16.37 15 23 15C29.63 15 35 20.15 35 26.5C35 32.85 29.63 38 23 38C20.85 38 18.83 37.46 17.07 36.51L11.5 38L13.13 33.15C11.79 31.25 11 28.98 11 26.5Z" fill="white"/>
						<path fill="#0068FF" d="M19.49 24.272v-.45h1.347v6.322h-.77a.576.576 0 0 1-.577-.573v.001a3.27 3.27 0 0 1-1.938.632a3.284 3.284 0 0 1-3.284-3.282a3.284 3.284 0 0 1 3.284-3.282a3.27 3.27 0 0 1 1.937.632zM13.919 21.79v.205c0 .382-.051.694-.3 1.06l-.03.034a8 8 0 0 0-.242.285L9.024 28.8h4.895v.768a.576.576 0 0 1-.577.576H7v-.362c0-.444.11-.642.25-.848L11.858 23.23H7.192V21.79h6.727zm8.551 8.355a.48.48 0 0 1-.48-.48v-7.875h1.442v8.355h-1.442zM27.693 23.6c1.826 0 3.307 1.481 3.307 3.304c0 1.826-1.48 3.306-3.307 3.306c-1.826 0-3.306-1.48-3.306-3.306c0-1.824 1.48-3.304 3.306-3.304zm-10.141 5.253c1.068 0 1.933-.865 1.933-1.931c0-1.065-.865-1.93-1.933-1.93s-1.932.865-1.932 1.93c0 1.067.865 1.931 1.932 1.931zm10.141-.003c1.074 0 1.945-.87 1.945-1.945c0-1.073-.871-1.944-1.945-1.944c-1.075 0-1.945.871-1.945 1.944c0 1.075.87 1.945 1.945 1.945z"/>
					</svg>
					<span>Zalo</span>
				</a>
				<a class="fv-owner-badge-link fv-owner-badge-link--facebook" href="https://www.facebook.com/Anhdungx05/" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( furniva_t( 'Contact DeSpace on Facebook', 'Liên hệ DeSpace qua Facebook' ) ); ?>">
					<svg class="fv-social-svg" viewBox="0 0 48 48" width="18" height="18" fill="none" aria-hidden="true">
						<circle cx="24" cy="24" r="24" fill="#1877F2"/>
						<path d="M29.5 25.1l.8-5.3h-5.1v-3.4c0-1.5.7-2.9 3.1-2.9h2.4V9c-.4-.1-1.9-.3-3.7-.3-3.8 0-6.3 2.3-6.3 6.5v3.6h-4.7v5.3h4.7v12.8c.9.1 1.9.2 2.9.2s2-.1 2.9-.2V25.1h4.1z" fill="white"/>
					</svg>
					<span>Facebook</span>
				</a>
			</div>
		</div>

		<div class="fv-footer__column fv-footer__newsletter">
			<h2><?php echo esc_html( furniva_t( 'A note from DeSpace', 'Bản tin DeSpace' ) ); ?></h2>
			<p><?php echo esc_html( furniva_t( 'Occasional notes on new pieces, thoughtful spaces and studio news.', 'Cập nhật vừa đủ về sản phẩm mới, không gian sống và tin tức từ studio.' ) ); ?></p>
			<form id="furniva-newsletter">
				<input type="email" placeholder="<?php echo esc_attr( furniva_t( 'Your email address', 'Địa chỉ email của bạn' ) ); ?>" required />
				<button type="submit"><?php echo esc_html( furniva_t( 'Subscribe', 'Đăng ký' ) ); ?></button>
			</form>
			<div class="f-news-ok"><?php echo esc_html( furniva_t( 'Thanks for subscribing — check your inbox for the WELCOME10 code.', 'Cảm ơn bạn đã đăng ký — hãy kiểm tra email để nhận mã WELCOME10.' ) ); ?></div>
			<div class="f-news-note"><?php echo esc_html( furniva_t( 'By subscribing you agree to our privacy policy.', 'Khi đăng ký, bạn đồng ý với chính sách quyền riêng tư của chúng tôi.' ) ); ?></div>
		</div>
	</div>

	<div class="fv-wrap fv-footer__legal">
		<span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> DeSpace — <?php echo esc_html( furniva_t( 'All rights reserved.', 'Bảo lưu mọi quyền.' ) ); ?></span>
		<div class="f-payments" aria-label="<?php echo esc_attr( furniva_t( 'Available payment methods', 'Phương thức thanh toán' ) ); ?>">
			<!-- Official VISA Badge -->
			<span class="f-payment f-payment--visa" role="img" aria-label="VISA" title="VISA Card">
				<svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect width="50" height="32" rx="4" fill="#ffffff" stroke="#e3ded7"/>
					<text x="25" y="21" font-family="'Arial Black', -apple-system, sans-serif" font-weight="900" font-style="italic" font-size="15" fill="#1434CB" text-anchor="middle" letter-spacing="0.5">VISA</text>
				</svg>
			</span>
			<!-- Official MASTERCARD Badge -->
			<span class="f-payment f-payment--mastercard" role="img" aria-label="MASTERCARD" title="Mastercard">
				<svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect width="50" height="32" rx="4" fill="#ffffff" stroke="#e3ded7"/>
					<circle cx="19.5" cy="16" r="9" fill="#EB001B"/>
					<circle cx="30.5" cy="16" r="9" fill="#F79E1B"/>
					<path d="M25 9.4a9 9 0 0 1 0 13.2 9 9 0 0 1 0-13.2z" fill="#FF5F00"/>
				</svg>
			</span>
			<!-- Official AMEX Badge -->
			<span class="f-payment f-payment--amex" role="img" aria-label="AMEX" title="American Express">
				<svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect width="50" height="32" rx="4" fill="#006FCF" stroke="#005bb0"/>
					<path d="M9 18.5l2.6-6h2.2l2.6 6h-1.6l-.5-1.3H11.2l-.5 1.3H9zm2.6-2.5h2.2l-1.1-2.7h-.1l-1 2.7zm5.8 2.5V12.5h2.3l1.8 3.5 1.8-3.5h2.3v6h-1.4v-4.1l-2 3.8h-1.4l-2-3.8v4.1h-1.4zm9.8 0V12.5h5.5v1.3h-4v1h3.6v1.3h-3.6v1.1h4v1.3h-5.5zm6.8 0l2.5-3-2.3-3h1.9l1.4 1.9 1.4-1.9h1.9l-2.3 3 2.5 3h-2l-1.5-2-1.5 2H34z" fill="#ffffff"/>
				</svg>
			</span>
			<!-- Official PAYPAL Badge -->
			<span class="f-payment f-payment--paypal" role="img" aria-label="PAYPAL" title="PayPal">
				<svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect width="50" height="32" rx="4" fill="#ffffff" stroke="#e3ded7"/>
					<path d="M18.5 8.5h6.2c3.4 0 5.2 1.6 4.6 4.8-.5 2.6-2.5 4.2-5.4 4.2h-2.3l-1.3 6H17l3.5-15h-2z" fill="#003087"/>
					<path d="M22 11.5h5.8c2.8 0 4.6 1.4 4.1 4.2-.6 3.1-2.8 4.8-5.8 4.8H24l-1.2 5.5h-3.1l2.3-14.5z" fill="#0079C1"/>
				</svg>
			</span>
			<!-- Official COD Badge -->
			<span class="f-payment f-payment--cod" role="img" aria-label="COD" title="<?php echo esc_attr( furniva_t( 'Cash on delivery (COD)', 'Thanh toán khi nhận hàng (COD)' ) ); ?>">
				<svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect width="50" height="32" rx="4" fill="#2d221a" stroke="#48382c"/>
					<path d="M8 11.5h11v8.5H8z" stroke="#e3c39e" stroke-width="1.3" stroke-linecap="round"/>
					<circle cx="13.5" cy="15.8" r="2" fill="#e3c39e"/>
					<path d="M19 13.5h4l3 3.2v3.3h-7V13.5z" stroke="#e3c39e" stroke-width="1.3" stroke-linejoin="round"/>
					<circle cx="12" cy="20.5" r="1.5" fill="#e3c39e"/>
					<circle cx="23" cy="20.5" r="1.5" fill="#e3c39e"/>
					<text x="36" y="19" fill="#ffffff" font-size="8.5" font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif" font-weight="800" letter-spacing="0.5">COD</text>
				</svg>
			</span>
		</div>
	</div>
</footer>

<div class="f-toast" id="furniva-toast" role="status" aria-live="polite"></div>

<!-- Off-canvas WooCommerce cart drawer -->
<div class="f-drawer-overlay" id="furniva-drawer-overlay"></div>
<aside class="f-cart-drawer" id="furniva-cart-drawer" aria-label="<?php echo esc_attr( furniva_t( 'Shopping cart', 'Giỏ hàng' ) ); ?>">
	<div class="f-drawer-content" id="furniva-drawer-content"><?php echo furniva_render_cart_drawer_html(); ?></div>
</aside>

<nav class="fv-mobile-shortcuts" aria-label="<?php echo esc_attr( furniva_t( 'Mobile navigation', 'Điều hướng di động' ) ); ?>">
	<a class="<?php echo is_front_page() ? 'is-current' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="fv-shortcut-icon"><?php echo furniva_svg( 'home' ); ?></span><span><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></span>
	</a>
	<a class="<?php echo is_shop() || is_product_taxonomy() ? 'is-current' : ''; ?>" href="<?php echo esc_url( $shop_url ); ?>">
		<span class="fv-shortcut-icon"><?php echo furniva_svg( 'grid' ); ?></span><span><?php echo esc_html( furniva_t( 'Shop', 'Cửa hàng' ) ); ?></span>
	</a>
	<button id="furniva-bnav-search" type="button" aria-label="<?php echo esc_attr( furniva_t( 'Search', 'Tìm kiếm' ) ); ?>">
		<span class="fv-shortcut-icon"><?php echo furniva_svg( 'search' ); ?></span><span><?php echo esc_html( furniva_t( 'Search', 'Tìm kiếm' ) ); ?></span>
	</button>
	<a href="<?php echo esc_url( $wishlist_url ); ?>">
		<span class="fv-shortcut-icon"><?php echo furniva_svg( 'heart' ); ?><span class="fv-action-count fv-wishlist-count" <?php echo ( $wishlist_count > 0 ) ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $wishlist_count ); ?></span></span><span><?php echo esc_html( furniva_t( 'Saved', 'Yêu thích' ) ); ?></span>
	</a>
	<?php $bnav_cart_count = ( furniva_wc_active() && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; ?>
	<button class="f-bnav-cart-trigger" id="furniva-bnav-cart" type="button" aria-label="<?php echo esc_attr( furniva_t( 'Cart', 'Giỏ hàng' ) ); ?>">
		<span class="fv-shortcut-icon">
			<?php echo furniva_svg( 'cart' ); ?>
			<span class="f-bnav-badge f-cart-bnav-count" <?php echo ( $bnav_cart_count > 0 ) ? '' : 'style="display:none;"'; ?>><?php echo esc_html( $bnav_cart_count ); ?></span>
		</span>
		<span><?php echo esc_html( furniva_t( 'Cart', 'Giỏ hàng' ) ); ?></span>
	</button>
</nav>

<script type="application/json" id="axgnud-chat-catalogue"><?php echo wp_json_encode( $chat_catalogue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?></script>

<!-- Bottom-Right Floating AI Assistant & Contact Dock -->
<div class="ax-chat-dock" id="ax-chat-dock">
	<div class="ax-chat-links" aria-label="<?php echo esc_attr( furniva_t( 'Contact the shop owner', 'Liên hệ chủ cửa hàng' ) ); ?>">
		<a class="ax-chat-link ax-chat-link--zalo" href="https://zalo.me/0332174590" target="_blank" rel="noopener noreferrer" aria-label="Zalo: 0332174590" title="Chat Zalo (0332 174 590)">
			<svg viewBox="0 0 48 48" width="24" height="24" fill="none" aria-hidden="true">
				<rect width="48" height="48" rx="14" fill="#2d2017"/>
				<path d="M11 26.5C11 20.15 16.37 15 23 15C29.63 15 35 20.15 35 26.5C35 32.85 29.63 38 23 38C20.85 38 18.83 37.46 17.07 36.51L11.5 38L13.13 33.15C11.79 31.25 11 28.98 11 26.5Z" fill="#c5a059"/>
				<path fill="#2d2017" d="M19.49 24.272v-.45h1.347v6.322h-.77a.576.576 0 0 1-.577-.573v.001a3.27 3.27 0 0 1-1.938.632a3.284 3.284 0 0 1-3.284-3.282a3.284 3.284 0 0 1 3.284-3.282a3.27 3.27 0 0 1 1.937.632zM13.919 21.79v.205c0 .382-.051.694-.3 1.06l-.03.034a8 8 0 0 0-.242.285L9.024 28.8h4.895v.768a.576.576 0 0 1-.577.576H7v-.362c0-.444.11-.642.25-.848L11.858 23.23H7.192V21.79h6.727zm8.551 8.355a.48.48 0 0 1-.48-.48v-7.875h1.442v8.355h-1.442zM27.693 23.6c1.826 0 3.307 1.481 3.307 3.304c0 1.826-1.48 3.306-3.307 3.306c-1.826 0-3.306-1.48-3.306-3.306c0-1.824 1.48-3.304 3.306-3.304zm-10.141 5.253c1.068 0 1.933-.865 1.933-1.931c0-1.065-.865-1.93-1.933-1.93s-1.932.865-1.932 1.93c0 1.067.865 1.931 1.932 1.931zm10.141-.003c1.074 0 1.945-.87 1.945-1.945c0-1.073-.871-1.944-1.945-1.944c-1.075 0-1.945.871-1.945 1.944c0 1.075.87 1.945 1.945 1.945z"/>
			</svg>
		</a>
		<a class="ax-chat-link ax-chat-link--facebook" href="https://www.facebook.com/Anhdungx05/" target="_blank" rel="noopener noreferrer" aria-label="Facebook: Anh Dũng" title="Facebook Anh Dũng">
			<svg viewBox="0 0 48 48" width="24" height="24" fill="none" aria-hidden="true">
				<circle cx="24" cy="24" r="23" fill="#2d2017"/>
				<path d="M29.5 25.1l.8-5.3h-5.1v-3.4c0-1.5.7-2.9 3.1-2.9h2.4V9c-.4-.1-1.9-.3-3.7-.3-3.8 0-6.3 2.3-6.3 6.5v3.6h-4.7v5.3h4.7v12.8c.9.1 1.9.2 2.9.2s2-.1 2.9-.2V25.1h4.1z" fill="#c5a059"/>
			</svg>
		</a>
	</div>
	<section class="ax-chat-panel" id="ax-chat-panel" aria-labelledby="ax-chat-title" aria-hidden="true" hidden>
		<header class="ax-chat-panel__head">
			<img class="ax-chat-panel__avatar" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-mark.png' ); ?>" alt="DeSpace AI Assistant" width="40" height="40" />
			<div>
				<h2 id="ax-chat-title"><?php echo esc_html( furniva_t( 'DeSpace AI Assistant', 'Trợ lý AI DeSpace' ) ); ?></h2>
				<p><?php echo esc_html( furniva_t( 'Instant catalog styling & shopping guide', 'Tư vấn sản phẩm & phối không gian sống' ) ); ?></p>
			</div>
			<button class="ax-chat-close" type="button" aria-label="<?php echo esc_attr( furniva_t( 'Close chat', 'Đóng trò chuyện' ) ); ?>">×</button>
		</header>
		<div class="ax-chat-messages" id="ax-chat-messages" role="log" aria-live="polite">
			<div class="ax-chat-message ax-chat-message--assistant">
				<?php echo esc_html( furniva_t( 'Hello! I am DeSpace AI Assistant ✦. Tell me what room or furniture piece you are looking for, and I will recommend our best pieces.', 'Xin chào! Tôi là Trợ lý AI DeSpace ✦. Bạn đang tìm nội thất cho phòng khách, phòng ngủ hay phòng ăn? Tôi có thể gợi ý những mẫu thiết kế cao cấp phù hợp nhất cho không gian của bạn!' ) ); ?>
			</div>
		</div>
		<div class="ax-chat-prompts">
			<button type="button" data-chat-prompt="<?php echo esc_attr( furniva_t( 'sofa', 'sofa' ) ); ?>">Sofa & Ghế bành</button>
			<button type="button" data-chat-prompt="<?php echo esc_attr( furniva_t( 'chair', 'ghế' ) ); ?>">Ghế tựa & Ghế ăn</button>
			<button type="button" data-chat-prompt="<?php echo esc_attr( furniva_t( 'table', 'bàn' ) ); ?>">Bàn ăn & Bàn trà</button>
			<button type="button" data-chat-prompt="<?php echo esc_attr( furniva_t( 'lighting', 'đèn' ) ); ?>">Đèn chiếu sáng</button>
			<button type="button" data-chat-prompt="<?php echo esc_attr( furniva_t( 'bed', 'giường' ) ); ?>">Giường ngủ</button>
		</div>
		<form class="ax-chat-form" id="ax-chat-form">
			<label class="screen-reader-text" for="ax-chat-input"><?php echo esc_html( furniva_t( 'Ask the shopping assistant', 'Hỏi trợ lý mua sắm' ) ); ?></label>
			<input id="ax-chat-input" name="message" type="text" maxlength="240" autocomplete="off" placeholder="<?php echo esc_attr( furniva_t( 'Ask for recommendations, styles, prices…', 'Nhập câu hỏi, tìm kiếm sản phẩm…' ) ); ?>" />
			<button type="submit" class="ax-chat-send" aria-label="<?php echo esc_attr( furniva_t( 'Send message', 'Gửi câu hỏi' ) ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="22" y1="2" x2="11" y2="13"/>
					<polygon points="22 2 15 22 11 13 2 9 22 2"/>
				</svg>
			</button>
		</form>
		<p class="ax-chat-note"><?php echo esc_html( furniva_t( 'AI Assistant: live recommendations from DeSpace collection.', 'Trợ lý AI: gợi ý sản phẩm trực tiếp từ bộ sưu tập DeSpace.' ) ); ?></p>
	</section>
	<button class="ax-chat-toggle" type="button" id="ax-chat-toggle" aria-expanded="false" aria-controls="ax-chat-panel" aria-label="<?php echo esc_attr( furniva_t( 'Open shopping assistant', 'Mở trợ lý mua sắm' ) ); ?>" title="<?php echo esc_attr( furniva_t( 'Chat with shopping assistant', 'Trò chuyện với trợ lý mua sắm' ) ); ?>">
		<div class="ax-chat-mascot-wrapper">
			<img class="ax-chat-mascot-img" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/despace-mark.png' ); ?>" alt="DeSpace AI Assistant" width="48" height="48" />
		</div>
		<svg class="ax-chat-toggle__close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
			<path d="m6 6 12 12M18 6 6 18"/>
		</svg>
	</button>
</div>

<?php
// Global 3D Product & Real AR Interactive Studio Modal
get_template_part( 'template-parts/modal-room-visualizer' );
?>

<?php wp_footer(); ?>
</body>
</html>
