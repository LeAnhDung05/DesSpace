<?php

/**
 * DeSpace Child theme functions (Re-platformed from BTTTS_5 / DeSpace).
 *
 * @package DeSpace_Child
 */
if (! defined('ABSPATH')) exit;

define( 'DESPACE_VER', '3.1.0' );
define( 'FURNIVA_VER', DESPACE_VER );

require_once get_stylesheet_directory() . '/inc/template-tags.php';
require_once get_stylesheet_directory() . '/inc/i18n.php';
require_once get_stylesheet_directory() . '/inc/ar-visualizer-ajax.php';
require_once get_stylesheet_directory() . '/inc/ar-visualizer-integration.php';
require_once get_stylesheet_directory() . '/inc/despace-3d-ai-pipeline.php';
require_once get_stylesheet_directory() . '/inc/admin-3d-manager.php';
require_once get_stylesheet_directory() . '/inc/despace-bilingual-products.php';
require_once get_stylesheet_directory() . '/inc/despace-customer-auth.php';
require_once get_stylesheet_directory() . '/inc/auto-seeder.php';
require_once get_stylesheet_directory() . '/inc/contact-form.php';

// ---------------------------------------------------------------- setup
function furniva_setup()
{
	load_child_theme_textdomain('despace-child', get_stylesheet_directory() . '/languages');

	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('custom-logo', array(
		'height'      => 56,
		'width'       => 168,
		'flex-height' => true,
		'flex-width'  => true,
	));
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
	add_theme_support('woocommerce', array(
		'product_grid' => array('default_columns' => 4, 'min_columns' => 2, 'max_columns' => 4),
	));
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
	register_nav_menus(array(
		'primary'     => __('Primary Menu', 'despace-child'),
		'footer_shop' => __('Footer – Shop', 'despace-child'),
		'footer_help' => __('Footer – Customer Care', 'despace-child'),
	));
}
add_action('after_setup_theme', 'furniva_setup');

// ---------------------------------------------------------------- SEO & Meta tags
add_action('wp_head', function () {
	if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')) {
		return;
	}
	$desc = '';
	if (is_front_page()) {
		$desc = 'DeSpace — Thương hiệu nội thất cao cấp phong cách Scandinavian & Quiet Luxury. Trải nghiệm trực quan 3D/AR đặt sản phẩm trong phòng với tỷ lệ thực tế.';
	} elseif (is_page()) {
		$slug = get_post_field('post_name', get_the_ID());
		$descriptions = array(
			've-despace'                 => 'Về DeSpace — Không gian nội thất phong cách Scandinavian & Quiet Luxury kết hợp trải nghiệm 3D/AR (Mô phỏng đồ án).',
			'lien-he'                    => 'Liên hệ DeSpace — Gửi yêu cầu tư vấn sản phẩm nội thất và trải nghiệm không gian 3D/AR (Thông tin showroom mô phỏng phục vụ đồ án).',
			'cau-hoi-thuong-gap'         => 'Giải đáp các câu hỏi thường gặp về sản phẩm gỗ tự nhiên, công nghệ xem phòng 3D/AR, đặt hàng và chính sách khách hàng tại DeSpace (Kịch bản đồ án).',
			'chinh-sach-giao-hang'       => 'Chính sách giao hàng toàn quốc & lắp đặt hoàn thiện tận nhà từ DeSpace (Thông tin mô phỏng đồ án môn học).',
			'chinh-sach-doi-tra-bao-hanh' => 'Chính sách bảo hành và đổi trả sản phẩm nội thất DeSpace (Thông tin mô phỏng đồ án môn học).',
			'chinh-sach-bao-mat'         => 'Chính sách bảo mật quyền riêng tư của DeSpace — Bảo vệ dữ liệu cá nhân khách hàng, cam kết an toàn giao dịch trực tuyến (Mô phỏng đồ án).',
			'dieu-khoan-mua-ban'         => 'Điều khoản mua bán và sử dụng dịch vụ tại DeSpace — Minh bạch quyền lợi và trách nhiệm khách hàng (Mô phỏng đồ án).',
		);
		if (isset($descriptions[$slug])) {
			$desc = $descriptions[$slug];
		}
	} elseif (is_singular('product') && function_exists('wc_get_product')) {
		$product = wc_get_product(get_the_ID());
		if ($product) {
			$short = wp_strip_all_tags($product->get_short_description());
			$desc = $short ? $short : ($product->get_name() . ' — Nội thất cao cấp DeSpace hỗ trợ trải nghiệm 3D/AR đặt phòng thực tế.');
		}
	}

	if ($desc) {
		echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
	}
}, 1);

// ---------------------------------------------------------------- canonical alias redirects
add_action('template_redirect', function () {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	$path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');

	// Check if path has /en/ prefix
	$is_en = false;
	if (0 === strpos($path, 'en/') || 'en' === $path) {
		$is_en = true;
		$path = substr($path, 3); // remove 'en/'
	}

	$aliases = array(
		'about'            => 've-despace',
		'contact'          => 'lien-he',
		'faq'              => 'cau-hoi-thuong-gap',
		'faqs'             => 'cau-hoi-thuong-gap',
		'shipping-returns' => 'chinh-sach-giao-hang',
		'giao-hang-doi-tra' => 'chinh-sach-giao-hang',
		'refund_returns'   => 'chinh-sach-doi-tra-bao-hanh',
		'doi-tra-bao-hanh' => 'chinh-sach-doi-tra-bao-hanh',
		'privacy-policy'   => 'chinh-sach-bao-mat',
		'terms'            => 'dieu-khoan-mua-ban',
	);

	if (isset($aliases[$path])) {
		$target_slug = $aliases[$path];
		$target_url = home_url(($is_en ? '/en/' : '/') . $target_slug . '/');
		wp_safe_redirect($target_url, 301);
		exit;
	}
});

// ---------------------------------------------------------------- rewrite rules & safe WC page URLs
/**
 * Return a published WooCommerce page permalink or a safe canonical fallback URL
 * so trashed/unlinked WooCommerce page IDs never resolve to `/?page_id=N` and trigger
 * WordPress 404 permalink guessing into a random single product page.
 */
function despace_safe_wc_page_url( string $page_key, string $fallback_path = '/cua-hang/' ): string {
	$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $page_key ) : 0;
	if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
		$link = get_permalink( $page_id );
		if ( is_string( $link ) && '' !== $link && false === strpos( $link, '?page_id=' ) ) {
			return $link;
		}
	}
	return home_url( $fallback_path );
}

add_filter( 'woocommerce_get_shop_page_permalink', function( $url ) {
	if ( ! is_string( $url ) || '' === $url || false !== strpos( $url, '?page_id=' ) ) {
		return home_url( '/cua-hang/' );
	}
	return $url;
} );

// Intercept legacy trashed shop page query `/?page_id=5` cleanly before redirect_guess_404_permalink()
add_action( 'template_redirect', function() {
	if ( is_404() && isset( $_GET['page_id'] ) ) {
		$req_id  = absint( wp_unslash( $_GET['page_id'] ) );
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $req_id > 0 && ( $req_id === $shop_id || 5 === $req_id ) ) {
			wp_safe_redirect( home_url( '/cua-hang/' ), 301 );
			exit;
		}
	}
}, 1 );

add_action( 'init', function() {
	add_rewrite_rule( '^shop/?$', 'index.php?post_type=product', 'top' );
	add_rewrite_rule( '^shop/page/([0-9]{1,})/?$', 'index.php?post_type=product&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^cua-hang/?$', 'index.php?post_type=product', 'top' );
	add_rewrite_rule( '^cua-hang/page/([0-9]{1,})/?$', 'index.php?post_type=product&paged=$matches[1]', 'top' );
	add_rewrite_rule( '^cart/?$', 'index.php?pagename=gio-hang', 'top' );
	add_rewrite_rule( '^checkout/?$', 'index.php?pagename=thanh-toan', 'top' );
	add_rewrite_rule( '^my-account/?$', 'index.php?pagename=tai-khoan', 'top' );
	add_rewrite_rule( '^en/?$', 'index.php', 'top' );
	add_rewrite_rule( '^en/(.+)/?$', 'index.php?pagename=$matches[1]', 'top' );
	add_rewrite_rule( '^about/?$', 'index.php?pagename=ve-despace', 'top' );
	add_rewrite_rule( '^contact/?$', 'index.php?pagename=lien-he', 'top' );
	add_rewrite_rule( '^faqs?/?$', 'index.php?pagename=cau-hoi-thuong-gap', 'top' );
	add_rewrite_rule( '^shipping-returns/?$', 'index.php?pagename=chinh-sach-giao-hang', 'top' );
	add_rewrite_rule( '^giao-hang-doi-tra/?$', 'index.php?pagename=chinh-sach-giao-hang', 'top' );
	add_rewrite_rule( '^refund_returns/?$', 'index.php?pagename=chinh-sach-doi-tra-bao-hanh', 'top' );
	add_rewrite_rule( '^doi-tra-bao-hanh/?$', 'index.php?pagename=chinh-sach-doi-tra-bao-hanh', 'top' );
	add_rewrite_rule( '^privacy-policy/?$', 'index.php?pagename=chinh-sach-bao-mat', 'top' );
	add_rewrite_rule( '^terms/?$', 'index.php?pagename=dieu-khoan-mua-ban', 'top' );
} );

// ---------------------------------------------------------------- assets
function furniva_assets() {
	$deps = array();
	if ( file_exists( get_template_directory() . '/style.css' ) ) {
		wp_enqueue_style( 'astra', get_template_directory_uri() . '/style.css', array(), null );
		$deps[] = 'astra';
	}
	$main_style_path = get_stylesheet_directory() . '/style.css';
	$main_style_ver  = file_exists( $main_style_path ) ? (string) filemtime( $main_style_path ) : FURNIVA_VER;
	wp_enqueue_style( 'furniva', get_stylesheet_uri(), $deps, $main_style_ver );
	wp_enqueue_style( 'furniva-fonts', 'https://fonts.googleapis.com/css2?family=Caveat:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400..600;1,6..72,400..600&family=Playfair+Display:ital,wght@0,400..700;1,400..700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap', array(), null );

	// Animations CSS
	$animations_css = get_stylesheet_directory() . '/assets/css/animations.css';
	if (file_exists($animations_css)) {
		wp_enqueue_style(
			'furniva-animations',
			get_stylesheet_directory_uri() . '/assets/css/animations.css',
			array('furniva'),
			filemtime($animations_css)
		);
	}

	// Performance CSS
	$perf_css = get_stylesheet_directory() . '/assets/css/performance.css';
	if (file_exists($perf_css)) {
		wp_enqueue_style(
			'furniva-performance',
			get_stylesheet_directory_uri() . '/assets/css/performance.css',
			array('furniva'),
			filemtime($perf_css)
		);
	}

	// Unified Luxury Skeleton Screen CSS
	$skeleton_css = get_stylesheet_directory() . '/assets/css/skeleton.css';
	if ( file_exists( $skeleton_css ) ) {
		wp_enqueue_style(
			'despace-skeleton',
			get_stylesheet_directory_uri() . '/assets/css/skeleton.css',
			array( 'furniva' ),
			filemtime( $skeleton_css )
		);
	}

	// Accessibility CSS
	$a11y_css = get_stylesheet_directory() . '/assets/css/accessibility.css';
	if (file_exists($a11y_css)) {
		wp_enqueue_style(
			'furniva-a11y',
			get_stylesheet_directory_uri() . '/assets/css/accessibility.css',
			array('furniva'),
			filemtime($a11y_css)
		);
	}

	if (is_front_page()) {
		$homepage_css = get_stylesheet_directory() . '/assets/css/homepage-clone.css';
		if (file_exists($homepage_css)) {
			wp_enqueue_style(
				'furniva-homepage-clone',
				get_stylesheet_directory_uri() . '/assets/css/homepage-clone.css',
				array('furniva'),
				(string) filemtime($homepage_css) . '-' . (string) filesize($homepage_css)
			);
		}

		$bento_css = get_stylesheet_directory() . '/assets/css/bento-edit.css';
		if (file_exists($bento_css)) {
			wp_enqueue_style(
				'furniva-bento-edit',
				get_stylesheet_directory_uri() . '/assets/css/bento-edit.css',
				array('furniva'),
				(string) filemtime($bento_css) . '-' . (string) filesize($bento_css)
			);
		}

		$bento_js = get_stylesheet_directory() . '/assets/js/bento-edit.js';
		if (file_exists($bento_js)) {
			wp_enqueue_script(
				'furniva-bento-edit-js',
				get_stylesheet_directory_uri() . '/assets/js/bento-edit.js',
				array(),
				(string) filemtime($bento_js),
				true
			);
		}
	}

	// Single Product CSS
	if (is_singular('product')) {
		$single_css = get_stylesheet_directory() . '/assets/css/single-product.css';
		if (file_exists($single_css)) {
			wp_enqueue_style(
				'furniva-single-product',
				get_stylesheet_directory_uri() . '/assets/css/single-product.css',
				array('furniva'),
				filemtime($single_css)
			);
		}
	}

	// Wishlist & Cart Luxury Edition CSS - load on wishlist/cart/checkout pages
	if (is_page('wishlist') || is_page('cart') || is_page_template('page-wishlist.php') || is_page_template('page-cart.php') || (function_exists('is_cart') && (is_cart() || is_checkout())) || (function_exists('yith_wcwl_is_wishlist_page') && yith_wcwl_is_wishlist_page())) {
		$wishlist_cart_css = get_stylesheet_directory() . '/assets/css/wishlist-cart.css';
		if (file_exists($wishlist_cart_css)) {
			wp_enqueue_style(
				'furniva-wishlist-cart',
				get_stylesheet_directory_uri() . '/assets/css/wishlist-cart.css',
				array('furniva'),
				filemtime($wishlist_cart_css)
			);
		}
	}

	// My Account Luxury CSS
	if (function_exists('is_account_page') && is_account_page()) {
		$my_account_css = get_stylesheet_directory() . '/assets/css/my-account.css';
		if (file_exists($my_account_css)) {
			wp_enqueue_style(
				'furniva-my-account',
				get_stylesheet_directory_uri() . '/assets/css/my-account.css',
				array('furniva'),
				filemtime($my_account_css)
			);
		}
	}

	// AR Room Visualizer CSS
	if (is_page_template('page-ar-visualizer.php')) {
		$ar_css = get_stylesheet_directory() . '/assets/css/ar-visualizer.css';
		if (file_exists($ar_css)) {
			wp_enqueue_style(
				'furniva-ar-visualizer',
				get_stylesheet_directory_uri() . '/assets/css/ar-visualizer.css',
				array('furniva'),
				filemtime($ar_css)
			);
		}
	}

	// AR Integration CSS - only load on single product, visualizer, or wishlist/cart pages
	if (is_singular('product') || is_page_template('page-wishlist.php') || is_page_template('page-cart.php') || is_page_template('page-ar-visualizer.php')) {
		$ar_integration_css = get_stylesheet_directory() . '/assets/css/ar-integration.css';
		if (file_exists($ar_integration_css)) {
			wp_enqueue_style(
				'furniva-ar-integration',
				get_stylesheet_directory_uri() . '/assets/css/ar-integration.css',
				array('furniva'),
				filemtime($ar_integration_css)
			);
		}
	}

	// DeSpace Architectural 3D & AR Studio CSS & JS (Self-hosted <model-viewer> 3.5.0)
	// Lazy-loaded on hover/click by ax-room-visualizer.js to avoid render-blocking initial page loads (eager on dedicated AR Visualizer page).
	if ( is_page_template( 'templates/page-ar-visualizer.php' ) || is_page_template( 'page-ar-visualizer.php' ) ) {
		wp_enqueue_script(
			'google-model-viewer',
			get_stylesheet_directory_uri() . '/assets/vendor/model-viewer/model-viewer.min.js',
			array(),
			'3.5.0',
			true
		);
	}

	// Load 3D/AR Studio Modal CSS & JS across storefront so AI Concierge 3D/AR cards work from any page
	$ax_room_css = get_stylesheet_directory() . '/assets/css/ax-room-visualizer.css';
	if ( file_exists( $ax_room_css ) ) {
		wp_enqueue_style(
			'furniva-ax-room-visualizer',
			get_stylesheet_directory_uri() . '/assets/css/ax-room-visualizer.css',
			array( 'furniva' ),
			filemtime( $ax_room_css )
		);
	}

	// Do NOT globally enqueue dead despace-3d-visualizer.js (which eagerly injects 350KB model-viewer on every page).
	$ax_room_js = get_stylesheet_directory() . '/assets/js/ax-room-visualizer.js';
	if ( file_exists( $ax_room_js ) && ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) && ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) ) {
		wp_enqueue_script(
			'despace-ax-room-visualizer',
			get_stylesheet_directory_uri() . '/assets/js/ax-room-visualizer.js',
			array( 'jquery' ),
			filemtime( $ax_room_js ),
			true
		);
		wp_register_script( 'furniva-ax-room-visualizer', false, array( 'despace-ax-room-visualizer' ) );
		wp_enqueue_script( 'furniva-ax-room-visualizer' );
	}

	// Dequeue redundant / legacy styles & heavy product-only scripts on non-product pages
	wp_dequeue_style( 'woocommerce_prettyPhoto_css' );
	wp_dequeue_style( 'jquery-selectBox' );
	if ( ! is_singular( 'product' ) ) {
		wp_dequeue_style( 'photoswipe' );
		wp_dequeue_style( 'photoswipe-default-skin' );
		wp_dequeue_script( 'photoswipe' );
		wp_dequeue_script( 'photoswipe-ui-default' );
		wp_dequeue_script( 'zoom' );
		wp_dequeue_script( 'wc-single-product' );
	}
	if ( ! is_singular( 'product' ) && ( ! function_exists( 'is_shop' ) || ! is_shop() ) && ( ! function_exists( 'is_product_taxonomy' ) || ! is_product_taxonomy() ) && ! is_front_page() ) {
		wp_dequeue_script( 'wc-add-to-cart-variation' );
		wp_dequeue_script( 'yith-wcqv-frontend' );
		wp_dequeue_style( 'yith-quick-view' );
	}

	// Unified Luxury Skeleton Screen JS (loaded before main.js & store-assistant.js)
	$skeleton_js_path = get_stylesheet_directory() . '/assets/js/skeleton.js';
	$main_deps        = array( 'jquery' );
	if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
		$main_deps[] = 'wc-add-to-cart';
	}
	if ( file_exists( $skeleton_js_path ) ) {
		wp_enqueue_script(
			'despace-skeleton',
			get_stylesheet_directory_uri() . '/assets/js/skeleton.js',
			array(),
			filemtime( $skeleton_js_path ),
			true
		);
		$main_deps[] = 'despace-skeleton';
	}

	$main_js_path = get_stylesheet_directory() . '/assets/js/main.js';
	wp_enqueue_script( 'furniva', get_stylesheet_directory_uri() . '/assets/js/main.js', $main_deps, file_exists( $main_js_path ) ? (string) filemtime( $main_js_path ) : FURNIVA_VER, true );

	// Animations JS (runs after DOM ready)
	$animations_js = get_stylesheet_directory() . '/assets/js/animations.js';
	if (file_exists($animations_js)) {
		wp_enqueue_script(
			'furniva-animations',
			get_stylesheet_directory_uri() . '/assets/js/animations.js',
			array(),
			filemtime($animations_js),
			true
		);
	}

	// Performance JS
	$perf_js = get_stylesheet_directory() . '/assets/js/performance.js';
	if (file_exists($perf_js)) {
		wp_enqueue_script(
			'furniva-performance',
			get_stylesheet_directory_uri() . '/assets/js/performance.js',
			array(),
			filemtime($perf_js),
			true
		);
	}

	// Accessibility JS
	$a11y_js = get_stylesheet_directory() . '/assets/js/accessibility.js';
	if (file_exists($a11y_js)) {
		wp_enqueue_script(
			'furniva-a11y',
			get_stylesheet_directory_uri() . '/assets/js/accessibility.js',
			array(),
			filemtime($a11y_js),
			true
		);
	}

	$assistant_js_path = get_stylesheet_directory() . '/assets/js/store-assistant.js';
	$assistant_js_ver  = file_exists( $assistant_js_path ) ? (string) filemtime( $assistant_js_path ) : DESPACE_VER;
	wp_enqueue_script( 'despace-shopping-assistant', get_stylesheet_directory_uri() . '/assets/js/store-assistant.js', array( 'furniva' ), $assistant_js_ver, true );
	wp_register_script( 'axgnud-shopping-assistant', false, array( 'despace-shopping-assistant' ), $assistant_js_ver, true );
	wp_enqueue_script( 'axgnud-shopping-assistant' );

	// 2-Tier Vietnam Cascading Address Selector (Province -> Ward/Commune/Special Zone)
	if ( ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		$vn_regions_js = get_stylesheet_directory() . '/assets/js/vn-checkout-regions.js';
		if ( file_exists( $vn_regions_js ) ) {
			wp_enqueue_script(
				'despace-vn-checkout-regions',
				get_stylesheet_directory_uri() . '/assets/js/vn-checkout-regions.js',
				array( 'jquery', 'furniva' ),
				filemtime( $vn_regions_js ),
				true
			);
		}
	}
	$base_cat = '';
	if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
		$queried = get_queried_object();
		if ($queried && 'product_cat' === ($queried->taxonomy ?? '')) {
			$base_cat = $queried->slug;
		}
	}
	$wc_account_url  = despace_safe_wc_page_url( 'myaccount', '/tai-khoan/' );
	$despace_js_data = array(
		'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
		'restUrl'              => esc_url_raw( rest_url( 'despace/v1' ) ),
		'vendorUrl'            => get_stylesheet_directory_uri() . '/assets/vendor/',
		'checkoutUrl'          => despace_safe_wc_page_url( 'checkout', '/thanh-toan/' ),
		'cartUrl'              => despace_safe_wc_page_url( 'cart', '/gio-hang/' ),
		'shopUrl'              => despace_safe_wc_page_url( 'shop', '/cua-hang/' ),
		'language'             => despace_current_language(),
		'nonce'                => wp_create_nonce( 'furniva_filter' ),
		'contactNonce'         => wp_create_nonce( 'despace_contact_nonce' ),
		'baseCat'              => $base_cat,
		'isLoggedIn'           => is_user_logged_in(),
		'guestCheckoutEnabled' => 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'yes' ),
		'loginUrl'             => $wc_account_url,
		'registerUrl'          => add_query_arg( 'action', 'register', $wc_account_url ),
		'strings'     => array(
			'newsletterSuccess' => despace_t('Subscribed! Use code WELCOME10 for 10% off.', 'Đăng ký thành công! Dùng mã WELCOME10 để giảm 10%.'),
			'contactPreview'    => despace_t('Preview form', 'Biểu mẫu xem trước'),
			'contactThanks'     => despace_t('Thanks for reaching out.', 'Cảm ơn bạn đã liên hệ.'),
			'contactNotSent'    => despace_t('This preview does not send email yet. You can reach us at contact@despace.test (Course Simulation).', 'Biểu mẫu xem trước này chưa gửi email. Bạn có thể liên hệ với chúng tôi qua contact@despace.test (Mô phỏng đồ án).'),
			'adding'            => despace_t('Adding…', 'Đang thêm…'),
			'buyNow'            => despace_t('Buy Now', 'Mua ngay'),
			'couldNotAdd'       => despace_t('Could not add the product.', 'Không thể thêm sản phẩm vào giỏ.'),
			'connectionError'   => despace_t('Connection error, please retry.', 'Lỗi kết nối, vui lòng thử lại.'),
			'loadMore'          => despace_t('Load more products', 'Xem thêm sản phẩm'),
			'noResultsTitle'    => despace_t('No matching objects found', 'Không tìm thấy sản phẩm phù hợp'),
			'noResultsDesc'     => despace_t('We couldn’t find any items matching your selected criteria. Try adjusting or clearing your filters.', 'Không có sản phẩm nào khớp với tiêu chí bạn chọn. Hãy điều chỉnh hoặc xóa bộ lọc.'),
			'resetFilters'      => despace_t('Reset All Filters', 'Đặt lại bộ lọc'),
			'priceLabel'        => despace_t('Price', 'Giá'),
			'priceFrom'         => despace_t('From', 'Từ'),
			'priceTo'           => furniva_t('to', 'đến'),
			'inStock'           => furniva_t('In stock', 'Còn hàng'),
			'outOfStock'        => furniva_t('Out of stock', 'Hết hàng'),
			'selected'          => furniva_t('Selected', 'Đã chọn'),
			'save'              => furniva_t('Save', 'Tiết kiệm'),
			'filterGroups'      => array(
				'category' => furniva_t('Category', 'Danh mục'),
				'brand' => furniva_t('Brand', 'Thương hiệu'),
				'color' => furniva_t('Color', 'Màu sắc'),
				'size' => furniva_t('Size', 'Kích thước'),
				'material' => furniva_t('Material', 'Chất liệu'),
				'availability' => furniva_t('Availability', 'Tình trạng'),
				'has_3d' => furniva_t('Feature', 'Tính năng'),
				'price' => furniva_t('Price', 'Giá'),
				'search' => furniva_t('Search', 'Tìm kiếm'),
			),
			'searchLabel'       => furniva_t('Search', 'Tìm kiếm'),
			'has3dLabel'        => furniva_t('3D/AR Available', 'Xem được 3D/AR'),
			'priceRange'        => furniva_t('$%1$s – $%2$s', '$%1$s – $%2$s'),
			'itemRemoved'       => furniva_t('Item removed from cart.', 'Đã xóa sản phẩm khỏi giỏ hàng.'),
			'addedToCart'       => furniva_t('Added {product} to your cart! 🛍️', 'Đã thêm {product} vào giỏ hàng! 🛍️'),
			'genericItem'       => furniva_t('item', 'sản phẩm'),
			'copiedCode'        => furniva_t('Copied {code} to clipboard! ✂️', 'Đã sao chép mã {code}! ✂️'),
			'useCode'           => furniva_t('Use code {code} at checkout!', 'Dùng mã {code} khi thanh toán!'),
			'productLinkCopied' => furniva_t('Product link copied to clipboard! 🔗', 'Đã sao chép liên kết sản phẩm! 🔗'),
			'linkLabel'         => furniva_t('Link: ', 'Liên kết: '),
			'searchPlaceholder' => despace_t('Search products…', 'Tìm kiếm sản phẩm…'),
			'searchButton'      => despace_t('Search', 'Tìm kiếm'),
			'loadingMore'       => despace_t('Loading…', 'Đang tải…'),
			'filterErrorTitle'  => despace_t('Unable to load products', 'Không thể tải danh sách sản phẩm'),
			'filterErrorDesc'   => despace_t('A network error occurred while updating the catalog. Please try again.', 'Đã xảy ra lỗi kết nối khi lọc sản phẩm. Vui lòng thử lại.'),
			'filterRetry'       => despace_t('Try again', 'Thử lại'),
		),
		'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '₫',
		'policyUrls' => array(
			'shipping' => furniva_url('/chinh-sach-giao-hang/'),
			'returns'  => furniva_url('/chinh-sach-doi-tra-bao-hanh/'),
			'privacy'  => furniva_url('/chinh-sach-bao-mat/'),
			'terms'    => furniva_url('/dieu-khoan-mua-ban/'),
		),
		'policyStrings' => array(
			'heading'    => furniva_t('Additional Customer Rights & Policies:', 'Chính sách bổ trợ & Quyền lợi khách hàng DeSpace:'),
			'shipping'   => furniva_t('Shipping & Installation Policy', 'Chính sách giao hàng & lắp đặt'),
			'returns'    => furniva_t('Returns & Warranty Policy', 'Chính sách đổi trả & bảo hành'),
			'privacy'    => furniva_t('Privacy Policy', 'Chính sách bảo mật'),
			'terms'      => furniva_t('Terms of Sale', 'Điều khoản mua bán'),
			'disclaimer' => furniva_t('Educational project simulation for DeSpace luxury furniture.', 'Nội dung quy chuẩn phục vụ đồ án học tập/thực nghiệm DeSpace.'),
		),
		'wishlistReady' => despace_is_wishlist_backend_ready(),
		'wishlistNonce' => wp_create_nonce('furniva_wishlist'),
	);
	wp_localize_script('furniva', 'furnivaData', $despace_js_data);
	wp_localize_script('furniva', 'despaceData', $despace_js_data);
}
add_action('wp_enqueue_scripts', 'furniva_assets');

// Load Google <model-viewer> as ESM module & defer non-critical frontend theme scripts
add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
	if ( 'google-model-viewer' === $handle ) {
		return '<script type="module" src="' . esc_url( $src ) . '"></script>' . "\n";
	}
	$defer_handles = array(
		'furniva-animations',
		'furniva-performance',
		'furniva-a11y',
		'furniva-bento-edit-js',
		'despace-shopping-assistant',
		'despace-skeleton',
	);
	if ( ! is_admin() && in_array( $handle, $defer_handles, true ) && false === strpos( $tag, ' defer' ) ) {
		return str_replace( ' src=', ' defer src=', $tag );
	}
	return $tag;
}, 10, 3 );

add_filter('script_loader_tag', function ($tag, $handle, $src) {
	if ('furniva-ax-3d-visualizer' === $handle) {
		return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
	}
	return $tag;
}, 10, 3);

// Disable WordPress emoji script & styles on storefront to trim critical rendering path
add_action( 'init', function() {
	if ( is_admin() ) return;
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );

function furniva_dequeue_unneeded_styles() {
	// Dequeue (do not deregister) legacy styles so plugin dependency graphs (e.g. yith-wcwl-main) remain valid in WP 6.9+.
	wp_dequeue_style( 'woocommerce_prettyPhoto_css' );
	wp_dequeue_style( 'jquery-selectBox' );
	if ( ! is_singular( 'product' ) ) {
		wp_dequeue_style( 'photoswipe' );
		wp_dequeue_style( 'photoswipe-default-skin' );
		wp_dequeue_script( 'photoswipe' );
		wp_dequeue_script( 'photoswipe-ui-default' );
		wp_dequeue_script( 'zoom' );
		wp_dequeue_script( 'wc-single-product' );
	}
	if ( ! is_singular( 'product' ) && ( ! function_exists( 'is_shop' ) || ! is_shop() ) && ( ! function_exists( 'is_product_taxonomy' ) || ! is_product_taxonomy() ) && ! is_front_page() ) {
		wp_dequeue_script( 'wc-add-to-cart-variation' );
		wp_dequeue_script( 'yith-wcqv-frontend' );
		wp_dequeue_style( 'yith-quick-view' );
	}
}
add_action('wp_enqueue_scripts', 'furniva_dequeue_unneeded_styles', 999);

add_filter('woocommerce_catalog_orderby', function ($options) {
	$labels = array(
		'menu_order' => furniva_t('Default sorting', 'Sắp xếp mặc định'),
		'popularity' => furniva_t('Sort by popularity', 'Sắp xếp theo độ phổ biến'),
		'rating'     => furniva_t('Sort by average rating', 'Sắp xếp theo đánh giá'),
		'date'       => furniva_t('Sort by latest', 'Sắp xếp theo mới nhất'),
		'price'      => furniva_t('Sort by price: low to high', 'Giá: thấp đến cao'),
		'price-desc' => furniva_t('Sort by price: high to low', 'Giá: cao đến thấp'),
	);
	foreach ($labels as $key => $label) {
		if (isset($options[$key])) {
			$options[$key] = $label;
		}
	}
	return $options;
});

add_filter('woocommerce_breadcrumb_defaults', function ($defaults) {
	$defaults['home'] = furniva_t('Home', 'Trang chủ');
	return $defaults;
});

// Astra ships its own header/footer markup helpers that conflict with ours
function furniva_remove_astra_markup()
{
	remove_action('astra_header', 'astra_header_markup');
	remove_action('astra_footer', 'astra_footer_markup');
}
add_action('wp', 'furniva_remove_astra_markup');

// Keep content wrappers predictable on WooCommerce pages
add_filter('body_class', function ($classes) {
	$classes[] = 'furniva';
	return $classes;
});

// ---------------------------------------------------------------- helpers
function despace_svg($name)
{
	$icons = array(
		'cart'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.6"/><circle cx="19" cy="21" r="1.6"/><path d="M2.5 3h2l2.4 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6l1.4-8H6"/></svg>',
		'heart'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>',
		'eye'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
		'user'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
		'bag'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
		'truck'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="5" width="14" height="12" rx="2"/><path d="M15 9h4l4 4v4h-8z"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>',
		'refresh'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/></svg>',
		'shield'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
		'plus'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
		'burger'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
		'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
		'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><line x1="17.5" y1="6.5" x2="17.5" y2="6.5" stroke-linecap="round" stroke-width="3"/></svg>',
		'youtube'  => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22.5 6.4a3 3 0 0 0-2.1-2.1C18.5 3.8 12 3.8 12 3.8s-6.5 0-8.4.5A3 3 0 0 0 1.5 6.4 31 31 0 0 0 1 12a31 31 0 0 0 .5 5.6 3 3 0 0 0 2.1 2.1c1.9.5 8.4.5 8.4.5s6.5 0 8.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 23 12a31 31 0 0 0-.5-5.6zM9.8 15.4V8.6L15.9 12z"/></svg>',
		'pin'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
		'phone'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3A19.5 19.5 0 0 1 5.2 13 19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 1.9z"/></svg>',
		'mail'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>',
		'trash'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
		'close'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		'share'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
		'arrow-up' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
		'copy'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
		'check'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
		'home'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
		'search'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
		'grid'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
	);
	return isset($icons[$name]) ? $icons[$name] : '';
}

function furniva_svg($name)
{
	return despace_svg($name);
}

function despace_wc_active()
{
	return class_exists('WooCommerce');
}

function furniva_wc_active()
{
	return despace_wc_active();
}

// ---------------------------------------------------------------- Auto-clean ghost cart totals
function furniva_prevent_ghost_cart_totals($cart)
{
	if (! $cart) {
		return;
	}
	if ($cart->is_empty()) {
		if ((float) $cart->get_total('edit') > 0 || (float) $cart->get_subtotal() > 0) {
			$cart->empty_cart(false);
		}
	} else {
		$has_invalid = false;
		foreach ($cart->get_cart() as $key => $item) {
			if (empty($item['data']) || ! $item['data']->exists() || 'trash' === $item['data']->get_status()) {
				$cart->remove_cart_item($key);
				$has_invalid = true;
			}
		}
		if ($has_invalid) {
			$cart->calculate_totals();
		}
	}
}
add_action('woocommerce_cart_loaded_from_session', 'furniva_prevent_ghost_cart_totals', 20);

// ---------------------------------------------------------------- cart fragment (AJAX cart count)
function furniva_cart_fragment($fragments)
{
	ob_start();
	furniva_cart_icon_html();
	$fragments['.f-cart-js'] = ob_get_clean();
	$count = (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
	$display = ($count > 0) ? '' : 'style="display:none;"';
	$fragments['span.f-cart-bnav-count'] = '<span class="f-bnav-badge f-cart-bnav-count" ' . $display . '>' . esc_html($count) . '</span>';
	return $fragments;
}
add_filter('woocommerce_add_to_cart_fragments', 'furniva_cart_fragment');

function furniva_cart_icon_html()
{
	if (! furniva_wc_active() || ! WC()->cart) {
		return;
	}
	$count = WC()->cart->get_cart_contents_count();
	if ($count === 0 && ((float) WC()->cart->get_total('edit') > 0 || (float) WC()->cart->get_subtotal() > 0)) {
		WC()->cart->empty_cart(false);
	}
	$total = WC()->cart->get_cart_total();
?>
	<a class="f-cart-btn f-cart-js" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="<?php echo esc_attr(furniva_t('Cart', 'Giỏ hàng')); ?>">
		<span class="f-icon-btn"><?php echo furniva_svg('cart'); ?><span class="f-badge"><?php echo esc_html($count); ?></span></span>
		<span class="f-cart-total"><?php echo wp_kses_post($total); ?></span>
	</a>
<?php
}

// ---------------------------------------------------------------- AJAX shop filter
function furniva_ajax_filter()
{
	check_ajax_referer('furniva_filter', 'nonce');

	$paged = max(1, absint($_POST['paged'] ?? 1));
	$per_page = 12;

	$tax_query  = array('relation' => 'AND');
	$meta_query = array();

	// Category
	$cats = array_filter(array_map('sanitize_title', (array) ($_POST['category'] ?? array())));
	if ($cats) {
		$tax_query[] = array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cats);
	}

	// Attributes & brand taxonomy (brand / color / size / material)
	foreach (array('brand' => 'product_brand', 'color' => 'pa_color', 'size' => 'pa_size', 'material' => 'pa_material') as $key => $tax) {
		if (! taxonomy_exists($tax)) {
			continue;
		}
		$terms = array_filter(array_map('sanitize_title', (array) ($_POST[$key] ?? array())));
		if ($terms) {
			$tax_query[] = array('taxonomy' => $tax, 'field' => 'slug', 'terms' => $terms);
		}
	}

	// Availability: in stock / on sale
	$availability = array_map('sanitize_text_field', (array) ($_POST['availability'] ?? array()));
	if (in_array('instock', $availability, true)) {
		$meta_query[] = array('key' => '_stock_status', 'value' => 'instock');
	}

	// 3D / AR feature filter
	if (! empty($_POST['has_3d'])) {
		$meta_query[] = array(
			'key'     => '_despace_glb_attachment_id',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
	}

	$orderby = sanitize_text_field($_POST['orderby'] ?? 'menu_order');

	$query_args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'tax_query'      => $tax_query,
	);

	// Search keyword filter (by product name or SKU)
	$search_term = sanitize_text_field($_POST['s'] ?? $_POST['q'] ?? '');
	if (! empty($search_term)) {
		global $wpdb;
		$sku_product_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END AS product_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s",
			'%' . $wpdb->esc_like($search_term) . '%'
		));

		$title_query = new WP_Query(array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			's'              => $search_term,
		));

		$matched_ids = array_map('intval', array_unique(array_merge((array) $sku_product_ids, (array) $title_query->posts)));
		$matched_ids = array_filter($matched_ids, function ($id) {
			return $id > 0;
		});
		if (! empty($matched_ids)) {
			$query_args['post__in'] = $matched_ids;
		} else {
			$query_args['post__in'] = array(0); // force empty result
		}
	}

	switch ($orderby) {
		case 'price-desc':
			$query_args['orderby']  = array('meta_value_num' => 'DESC', 'ID' => 'DESC');
			$query_args['meta_key'] = '_price';
			break;
		case 'price':
			$query_args['orderby']  = array('meta_value_num' => 'ASC', 'ID' => 'ASC');
			$query_args['meta_key'] = '_price';
			break;
		case 'date':
			$query_args['orderby'] = array('date' => 'DESC', 'ID' => 'DESC');
			break;
		case 'popularity':
			$query_args['orderby']  = array('meta_value_num' => 'DESC', 'ID' => 'DESC');
			$query_args['meta_key'] = 'total_sales';
			break;
		case 'rating':
			$query_args['orderby']  = array('meta_value_num' => 'DESC', 'ID' => 'DESC');
			$query_args['meta_key'] = '_wc_average_rating';
			break;
		default:
			$query_args['orderby'] = array('menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC');
			break;
	}

	if ($meta_query) {
		$query_args['meta_query'] = $meta_query;
	}

	// On sale filter uses WooCommerce's cached sale ids
	$on_sale_only = in_array('onsale', $availability, true);
	if ($on_sale_only) {
		$sale_ids = wc_get_product_ids_on_sale();
		if (empty($sale_ids)) {
			$sale_ids = array(0);
		}
		if (isset($query_args['post__in'])) {
			$intersected = array_values(array_intersect($query_args['post__in'], $sale_ids));
			$query_args['post__in'] = ! empty($intersected) ? $intersected : array(0);
		} else {
			$query_args['post__in'] = $sale_ids;
		}
	}

	// Price range (validated & sanitized; ignores empty strings and auto-swaps inverted bounds)
	$raw_min = isset( $_POST['price_min'] ) ? trim( (string) wp_unslash( $_POST['price_min'] ) ) : ( isset( $_POST['min_price'] ) ? trim( (string) wp_unslash( $_POST['min_price'] ) ) : '' );
	$raw_max = isset( $_POST['price_max'] ) ? trim( (string) wp_unslash( $_POST['price_max'] ) ) : ( isset( $_POST['max_price'] ) ? trim( (string) wp_unslash( $_POST['max_price'] ) ) : '' );
	$min     = ( '' !== $raw_min && is_numeric( $raw_min ) ) ? max( 0.0, floatval( $raw_min ) ) : null;
	$max     = ( '' !== $raw_max && is_numeric( $raw_max ) ) ? max( 0.0, floatval( $raw_max ) ) : null;
	if ( null !== $min && null !== $max && $min > $max ) {
		$tmp = $min;
		$min = $max;
		$max = $tmp;
	}
	if ( null !== $min || null !== $max ) {
		$price_query = array( 'relation' => 'AND' );
		if ( null !== $min ) {
			$price_query[] = array( 'key' => '_price', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( null !== $max ) {
			$price_query[] = array( 'key' => '_price', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC' );
		}
		$meta_query[] = $price_query;
		$query_args['meta_query'] = $meta_query;
	}

	// Respect a base category (current archive) or one passed from JS
	$base = sanitize_title( wp_unslash( $_POST['base'] ?? '' ) );
	if ( empty( $cats ) && $base ) {
		$query_args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $base );
	}

	$wp_query = new WP_Query($query_args);
	$total    = (int) $wp_query->found_posts;
	$from     = $total ? (($paged - 1) * $per_page + 1) : 0;
	$to       = min($paged * $per_page, $total);

	ob_start();
	if ($wp_query->have_posts()) {
		echo '<ul class="f-products columns-4">';
		while ($wp_query->have_posts()) {
			$wp_query->the_post();
			wc_get_template_part('content', 'product');
		}
		echo '</ul>';
		wp_reset_postdata();
	} else {
		echo '<div class="f-no-results">' .
			'<div class="f-no-results-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg></div>' .
			'<h3 class="f-no-results-title">' . esc_html(furniva_t('No matching objects found', 'Không tìm thấy sản phẩm phù hợp')) . '</h3>' .
			'<p class="f-no-results-desc">' . esc_html(furniva_t('We couldn’t find any items matching your selected criteria. Try adjusting or clearing your filters.', 'Không có sản phẩm nào khớp với tiêu chí bạn chọn. Hãy điều chỉnh hoặc xóa bộ lọc.')) . '</p>' .
			'<button type="button" class="f-btn f-btn--primary f-no-results-reset" id="furniva-no-results-reset">' . esc_html(furniva_t('Reset All Filters', 'Đặt lại bộ lọc')) . '</button>' .
			'</div>';
	}
	$grid = ob_get_clean();

	wp_send_json_success(array(
		'grid'  => $grid,
		'found' => $total,
		'text'  => sprintf(furniva_t('%d products found', '%d sản phẩm được tìm thấy'), $total),
		'range' => $total ? ('en' === substr(furniva_current_language(), 0, 2) ? sprintf('Showing %d–%d of %d', $from, $to, $total) : sprintf('Đang hiển thị %d–%d trong số %d', $from, $to, $total)) : '',
		'more'  => $to < $total,
		'next'  => $to < $total ? $paged + 1 : null,
	));
}
add_action('wp_ajax_furniva_filter', 'furniva_ajax_filter');
add_action('wp_ajax_nopriv_furniva_filter', 'furniva_ajax_filter');
add_action('wp_ajax_despace_filter', 'furniva_ajax_filter');
add_action('wp_ajax_nopriv_despace_filter', 'furniva_ajax_filter');
add_filter('loop_shop_per_page', function () {
	return 12;
}, 20);

// ---------------------------------------------------------------- AJAX Buy Now
function furniva_ajax_buy_now() {
	check_ajax_referer( 'furniva_filter', 'nonce' );
	$data         = wp_unslash( $_POST );
	$product_id   = absint( $data['add-to-cart'] ?? 0 );
	$variation_id = absint( $data['variation_id'] ?? 0 );
	$qty          = wc_stock_amount( $data['quantity'] ?? 1 );

	if ( ! $product_id || ! wc_get_product( $variation_id ?: $product_id ) ) {
		wp_send_json_error( furniva_t( 'Product not found.', 'Không tìm thấy sản phẩm.' ) );
	}

	if ( ! is_user_logged_in() && 'yes' !== get_option( 'woocommerce_enable_guest_checkout', 'yes' ) ) {
		$login_url = add_query_arg(
			array(
				'action'      => 'login',
				'redirect_to' => urlencode( wc_get_checkout_url() ),
			),
			despace_safe_wc_page_url( 'myaccount', '/tai-khoan/' )
		);
		wp_send_json_error( array(
			'require_login' => true,
			'redirect'      => $login_url,
			'message'       => furniva_t( 'Please sign in to proceed with your order.', 'Quý khách vui lòng đăng nhập để tiến hành mua hàng.' ),
		) );
	}

	$attributes = array();
	foreach ( $data as $key => $value ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'attribute_' ) ) {
			$attributes[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
		}
	}

	$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $qty, $variation_id, $attributes );
	if ( ! $passed ) {
		wp_send_json_error( furniva_t( 'Please choose product options first.', 'Vui lòng chọn các tùy chọn sản phẩm trước.' ) );
	}

	if ( ! function_exists( 'WC' ) ) {
		wp_send_json_error( furniva_t( 'Cart is currently unavailable.', 'Giỏ hàng hiện không khả dụng.' ) );
	}
	if ( is_null( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	if ( is_null( WC()->cart ) ) {
		wp_send_json_error( furniva_t( 'Cart is currently unavailable.', 'Giỏ hàng hiện không khả dụng.' ) );
	}

	$added = WC()->cart->add_to_cart( $product_id, max( 1, $qty ), $variation_id, $attributes );
	if ( ! $added ) {
		wp_send_json_error( furniva_t( 'Could not add the product to the cart.', 'Không thể thêm sản phẩm vào giỏ hàng.' ) );
	}
	WC()->cart->calculate_totals();
	wp_send_json_success(array('redirect' => wc_get_checkout_url()));
}
add_action('wp_ajax_furniva_buy_now', 'furniva_ajax_buy_now');
add_action('wp_ajax_nopriv_furniva_buy_now', 'furniva_ajax_buy_now');
add_action('wp_ajax_despace_buy_now', 'furniva_ajax_buy_now');
add_action('wp_ajax_nopriv_despace_buy_now', 'furniva_ajax_buy_now');

// ---------------------------------------------------------------- AJAX cart state (sync header with block cart)
function furniva_ajax_cart_state()
{
	check_ajax_referer('furniva_filter', 'nonce');
	ob_start();
	furniva_cart_icon_html();
	wp_send_json_success(array('html' => ob_get_clean()));
}
add_action('wp_ajax_furniva_cart_state', 'furniva_ajax_cart_state');
add_action('wp_ajax_nopriv_furniva_cart_state', 'furniva_ajax_cart_state');
add_action('wp_ajax_despace_cart_state', 'furniva_ajax_cart_state');
add_action('wp_ajax_nopriv_despace_cart_state', 'furniva_ajax_cart_state');

// ---------------------------------------------------------------- single product: Buy Now
function furniva_buy_now_button()
{
	echo '<button type="button" class="f-buy-now" id="furniva-buy-now">' . esc_html(furniva_t('Buy Now', 'Mua ngay')) . '</button>';
}
add_action('woocommerce_after_add_to_cart_button', 'furniva_buy_now_button');

// quantity stepper buttons markup
function furniva_qty_buttons()
{
	echo '<div class="f-stepper"><button type="button" class="f-qty-minus" aria-label="' . esc_attr(furniva_t('Decrease quantity', 'Giảm số lượng')) . '">−</button>';
}
function furniva_qty_buttons_after()
{
	echo '<button type="button" class="f-qty-plus" aria-label="' . esc_attr(furniva_t('Increase quantity', 'Tăng số lượng')) . '">+</button></div>';
}
add_action('woocommerce_before_add_to_cart_quantity', 'furniva_qty_buttons');
add_action('woocommerce_after_add_to_cart_quantity', 'furniva_qty_buttons_after');

// hide default quantity input styling conflicts
add_filter('woocommerce_quantity_input_classes', function ($classes) {
	$classes[] = 'f-qty-input';
	return $classes;
});

// ---------------------------------------------------------------- misc
// Price display: keep decimals clean
add_filter('woocommerce_price_format', function ($format, $currency_pos) {
	if (function_exists('despace_is_english_mode') && despace_is_english_mode()) {
		return '%1$s%2$s';
	}
	return '%2$s&nbsp;%1$s';
}, 20, 2);
add_filter('pre_option_woocommerce_currency_pos', function () {
	return (function_exists('despace_is_english_mode') && despace_is_english_mode()) ? 'left' : 'right_space';
});

// Add "New" badge to genuinely new products (limit to top 3 newest)
function furniva_is_new_product($product_id)
{
	static $new_ids = null;
	if (null === $new_ids) {
		$recent = get_posts(array(
			'post_type'      => 'product',
			'posts_per_page' => 3,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'post_status'    => 'publish',
		));
		$new_ids = is_array($recent) ? $recent : array();
	}
	return in_array((int) $product_id, $new_ids, true);
}

// ---------------------------------------------------------------- Mini-Cart Drawer
function furniva_render_cart_drawer_html()
{
	if (! furniva_wc_active() || ! WC()->cart) {
		return '<div class="f-drawer-empty"><span class="f-drawer-empty-icon">🛋️</span><h4>' . esc_html(furniva_t('Your cart is empty', 'Giỏ hàng đang trống')) . '</h4></div>';
	}

	$cart       = WC()->cart;
	$items      = $cart->get_cart();
	$item_count = $cart->get_cart_contents_count();
	$subtotal   = (float) $cart->get_subtotal();
	$threshold  = 5000000.0;
	$diff       = max( 0.0, $threshold - $subtotal );
	$percent    = min( 100, round( ( $subtotal / $threshold ) * 100 ) );

	ob_start();
?>
	<div class="f-drawer-header">
		<h3><?php echo esc_html(furniva_t('Your Cart', 'Giỏ hàng của bạn')); ?> <span class="f-drawer-count">(<?php echo esc_html($item_count); ?>)</span></h3>
		<button type="button" class="f-drawer-close" id="furniva-drawer-close" aria-label="<?php echo esc_attr(furniva_t('Close cart', 'Đóng giỏ hàng')); ?>">
			<?php echo furniva_svg('close'); ?>
		</button>
	</div>

	<?php if ($item_count > 0) : ?>
		<!-- Freeship progress bar -->
		<div class="f-drawer-freeship">
			<div class="f-freeship-msg">
				<?php if ( $diff > 0 ) : ?>
					<?php echo esc_html( furniva_t( 'Add', 'Mua thêm' ) ); ?> <strong><?php echo wp_kses_post( wc_price( $diff ) ); ?></strong> <?php echo esc_html( furniva_t( 'more for', 'để được' ) ); ?> <strong><?php echo esc_html( furniva_t( 'FREE Shipping!', 'MIỄN PHÍ VẬN CHUYỂN!' ) ); ?></strong>
				<?php else : ?>
					<span style="color:var(--f-green)">🎉 <?php echo esc_html(furniva_t('You qualify for', 'Bạn được hưởng')); ?> <strong><?php echo esc_html(furniva_t('FREE Shipping!', 'MIỄN PHÍ VẬN CHUYỂN!')); ?></strong></span>
				<?php endif; ?>
			</div>
			<div class="f-freeship-bar">
				<div class="f-freeship-fill" style="width:<?php echo esc_attr($percent); ?>%"></div>
			</div>
		</div>

		<!-- Cart item list -->
		<div class="f-drawer-items">
			<?php
			foreach ($items as $cart_item_key => $cart_item) :
				$_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
				$product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

				if (! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0) {
					continue;
				}

				$product_name      = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
				$product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
				$thumbnail         = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(array(72, 72)), $cart_item, $cart_item_key);
				$product_price     = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
				$quantity          = $cart_item['quantity'];
			?>
				<div class="f-drawer-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
					<div class="f-drawer-item-img">
						<?php echo $thumbnail; ?>
					</div>
					<div class="f-drawer-item-info">
						<a class="f-drawer-item-title" href="<?php echo esc_url($product_permalink); ?>">
							<?php echo wp_kses_post($product_name); ?>
						</a>
						<?php if (! empty($cart_item['variation'])) : ?>
							<div class="f-drawer-item-meta">
								<?php echo wc_get_formatted_cart_item_data($cart_item); ?>
							</div>
						<?php endif; ?>
						<div class="f-drawer-item-price">
							<?php echo wp_kses_post($product_price); ?>
						</div>
						<div class="f-drawer-item-actions">
							<div class="f-drawer-stepper">
								<button type="button" class="f-drawer-minus" data-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php echo esc_attr(furniva_t('Decrease quantity', 'Giảm số lượng')); ?>">−</button>
								<span class="f-drawer-qty"><?php echo esc_html($quantity); ?></span>
								<button type="button" class="f-drawer-plus" data-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php echo esc_attr(furniva_t('Increase quantity', 'Tăng số lượng')); ?>">+</button>
							</div>
							<button type="button" class="f-drawer-remove" data-key="<?php echo esc_attr($cart_item_key); ?>" aria-label="<?php echo esc_attr(furniva_t('Remove item', 'Xóa sản phẩm')); ?>" title="<?php echo esc_attr(furniva_t('Remove', 'Xóa')); ?>">
								<?php echo furniva_svg('trash'); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Drawer Footer -->
		<div class="f-drawer-footer">
			<div class="f-drawer-subtotal">
				<span><?php echo esc_html(furniva_t('Subtotal', 'Tạm tính')); ?></span>
				<strong class="f-drawer-subtotal-val"><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
			</div>
			<div class="f-drawer-note"><?php echo esc_html(furniva_t('Taxes and shipping calculated at checkout.', 'Thuế và phí vận chuyển được tính khi thanh toán.')); ?></div>
			<div class="f-drawer-btns">
				<a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="f-btn f-btn--ghost"><?php echo esc_html(furniva_t('View Cart', 'Xem giỏ hàng')); ?></a>
				<a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="f-btn f-btn--primary"><?php echo esc_html(furniva_t('Checkout', 'Thanh toán')); ?> →</a>
			</div>
		</div>
	<?php else : ?>
		<div class="f-drawer-empty">
			<span class="f-drawer-empty-icon">🛋️</span>
			<h4><?php echo esc_html(furniva_t('Your cart is empty', 'Giỏ hàng đang trống')); ?></h4>
			<p><?php echo esc_html(furniva_t('Looks like you haven’t added anything to your cart yet.', 'Có vẻ như bạn chưa thêm sản phẩm nào vào giỏ hàng.')); ?></p>
			<a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="f-btn f-btn--accent" id="furniva-drawer-shop-btn">
				<?php echo esc_html(furniva_t('Explore products', 'Khám phá sản phẩm')); ?> →
			</a>
		</div>
	<?php endif; ?>
<?php
	return ob_get_clean();
}

function furniva_ajax_get_cart_drawer()
{
	check_ajax_referer('furniva_filter', 'nonce');
	$count = (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
	if ($count === 0 && WC()->cart && ((float) WC()->cart->get_total('edit') > 0 || (float) WC()->cart->get_subtotal() > 0)) {
		WC()->cart->empty_cart(false);
	}
	wp_send_json_success(array(
		'html'  => furniva_render_cart_drawer_html(),
		'count' => $count,
		'total' => (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_total() : wc_price(0),
	));
}
add_action('wp_ajax_furniva_get_cart_drawer', 'furniva_ajax_get_cart_drawer');
add_action('wp_ajax_nopriv_furniva_get_cart_drawer', 'furniva_ajax_get_cart_drawer');
add_action('wp_ajax_despace_get_cart_drawer', 'furniva_ajax_get_cart_drawer');
add_action('wp_ajax_nopriv_despace_get_cart_drawer', 'furniva_ajax_get_cart_drawer');

function furniva_ajax_update_drawer_qty()
{
	check_ajax_referer('furniva_filter', 'nonce');
	$key = sanitize_text_field($_POST['cart_key'] ?? '');
	$qty = isset($_POST['quantity']) ? max(0, (int) $_POST['quantity']) : 0;

	if ($key && WC()->cart) {
		if ($qty === 0) {
			WC()->cart->remove_cart_item($key);
		} else {
			WC()->cart->set_quantity($key, $qty, true);
		}
		WC()->cart->calculate_totals();
		if (WC()->cart->is_empty()) {
			WC()->cart->empty_cart(false);
		}
	}
	$count = (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
	wp_send_json_success(array(
		'html'  => furniva_render_cart_drawer_html(),
		'count' => $count,
		'total' => (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_total() : wc_price(0),
	));
}
add_action('wp_ajax_furniva_update_drawer_qty', 'furniva_ajax_update_drawer_qty');
add_action('wp_ajax_nopriv_furniva_update_drawer_qty', 'furniva_ajax_update_drawer_qty');
add_action('wp_ajax_despace_update_drawer_qty', 'furniva_ajax_update_drawer_qty');
add_action('wp_ajax_nopriv_despace_update_drawer_qty', 'furniva_ajax_update_drawer_qty');

function furniva_ajax_remove_drawer_item()
{
	check_ajax_referer('furniva_filter', 'nonce');
	$key = sanitize_text_field($_POST['cart_key'] ?? '');
	if ($key && WC()->cart) {
		WC()->cart->remove_cart_item($key);
		WC()->cart->calculate_totals();
		if (WC()->cart->is_empty()) {
			WC()->cart->empty_cart(false);
		}
	}
	$count = (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
	wp_send_json_success(array(
		'html'  => furniva_render_cart_drawer_html(),
		'count' => $count,
		'total' => (furniva_wc_active() && WC()->cart) ? WC()->cart->get_cart_total() : wc_price(0),
	));
}
add_action('wp_ajax_furniva_remove_drawer_item', 'furniva_ajax_remove_drawer_item');
add_action('wp_ajax_nopriv_furniva_remove_drawer_item', 'furniva_ajax_remove_drawer_item');
add_action('wp_ajax_despace_remove_drawer_item', 'furniva_ajax_remove_drawer_item');
add_action('wp_ajax_nopriv_despace_remove_drawer_item', 'furniva_ajax_remove_drawer_item');

// ---------------------------------------------------------------- SEO: Schema.org JSON-LD
function furniva_product_schema_jsonld()
{
	if (! is_singular('product')) {
		return;
	}
	$product = wc_get_product(get_the_ID());
	if (! $product) {
		return;
	}

	$schema = array(
		'@context'    => 'https://schema.org/',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( $product->get_name() ),
		'image'       => wp_get_attachment_url( $product->get_image_id() ),
		'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
		'sku'         => wp_strip_all_tags( $product->get_sku() ?: 'FUR-' . $product->get_id() ),
		'brand'       => array(
			'@type' => 'Brand',
			'name'  => wp_strip_all_tags( $product->get_attribute( 'Brand' ) ?: 'DeSpace' ),
		),
		'offers'      => array(
			'@type'         => 'Offer',
			'url'           => get_permalink($product->get_id()),
			'priceCurrency' => get_woocommerce_currency(),
			'price'         => $product->get_price(),
			'itemCondition' => 'https://schema.org/NewCondition',
			'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
		),
	);

	if ($product->get_rating_count() > 0) {
		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $product->get_average_rating(),
			'reviewCount' => $product->get_review_count(),
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}
add_action('wp_head', 'furniva_product_schema_jsonld');

// ---------------------------------------------------------------- Sticky Add to Cart Bar
function furniva_sticky_single_bar()
{
	if (! is_singular('product')) {
		return;
	}
	$product = wc_get_product(get_the_ID());
	if (! $product) {
		return;
	}
	$img_url = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail')
		?: wp_get_attachment_image_url($product->get_image_id(), 'full');
?>
	<div class="f-sticky-bar" id="furniva-sticky-bar" aria-label="<?php echo esc_attr(furniva_t('Quick add to cart', 'Mua nhanh')); ?>">
		<div class="f-sticky-bar-inner">
			<div class="f-sticky-bar-left">
				<?php if ($img_url) : ?>
					<div class="f-sticky-img-box">
						<img src="<?php echo esc_url($img_url); ?>" alt="" class="f-sticky-img" width="44" height="44" loading="lazy" />
					</div>
				<?php endif; ?>
				<div class="f-sticky-meta">
					<strong title="<?php echo esc_attr($product->get_name()); ?>"><?php echo esc_html($product->get_name()); ?></strong>
					<span class="f-sticky-price"><?php echo wp_kses_post(wc_price($product->get_price())); ?></span>
				</div>
			</div>
			<div class="f-sticky-bar-right">
				<?php if ($product->is_in_stock()) : ?>
					<button type="button" class="f-btn f-btn--primary f-sticky-add-btn" data-product_id="<?php echo esc_attr($product->get_id()); ?>">
						<?php echo furniva_svg('cart'); ?> <span><?php echo esc_html(furniva_t('Add to Cart', 'Thêm vào giỏ')); ?></span>
					</button>
				<?php else : ?>
					<button type="button" class="f-btn f-btn--disabled" disabled>
						<?php echo esc_html(furniva_t('Out of stock', 'Hết hàng')); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php
}
add_action('wp_footer', 'furniva_sticky_single_bar');

// ---------------------------------------------------------------- Wishlist & Cart AJAX Handlers
function furniva_verify_cart_or_filter_nonce() {
	$sec = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
	$non = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( $sec && ( wp_verify_nonce( $sec, 'furniva_cart' ) || wp_verify_nonce( $sec, 'furniva_wishlist' ) || wp_verify_nonce( $sec, 'furniva_filter' ) ) ) {
		return true;
	}
	if ( $non && ( wp_verify_nonce( $non, 'furniva_filter' ) || wp_verify_nonce( $non, 'furniva_cart' ) || wp_verify_nonce( $non, 'furniva_wishlist' ) ) ) {
		return true;
	}
	return false;
}

function furniva_ajax_update_cart_qty() {
	if ( ! furniva_verify_cart_or_filter_nonce() ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
	}
	$cart_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? $_POST['cart_key'] ?? '' ) );
	$quantity = max( 0, intval( $_POST['quantity'] ?? $_POST['qty'] ?? 1 ) );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'Cart not available' ) );
	}

	if ( ! empty( $cart_key ) && isset( WC()->cart->get_cart()[ $cart_key ] ) ) {
		if ( $quantity === 0 ) {
			WC()->cart->remove_cart_item( $cart_key );
		} else {
			WC()->cart->set_quantity( $cart_key, $quantity );
		}
		WC()->cart->calculate_totals();
	}

	$subtotal   = (float) WC()->cart->get_subtotal();
	$item_count = (int) WC()->cart->get_cart_contents_count();
	$threshold  = 5000000.00;
	$remaining  = max( 0.00, $threshold - $subtotal );
	$percent    = min( 100.0, ( $subtotal / $threshold ) * 100.0 );

	wp_send_json_success( array(
		'subtotal'       => $subtotal,
		'subtotal_html'  => wp_kses_post( wc_price( $subtotal ) ),
		'item_count'     => $item_count,
		'remaining'      => $remaining,
		'remaining_html' => wp_kses_post( wc_price( $remaining ) ),
		'percent'        => round( $percent, 1 ),
		'is_free'        => ( $remaining <= 0.00 ),
	) );
}
add_action('wp_ajax_furniva_cart_update_qty', 'furniva_ajax_update_cart_qty');
add_action('wp_ajax_nopriv_furniva_cart_update_qty', 'furniva_ajax_update_cart_qty');
add_action('wp_ajax_despace_cart_update_qty', 'furniva_ajax_update_cart_qty');
add_action('wp_ajax_nopriv_despace_cart_update_qty', 'furniva_ajax_update_cart_qty');

function furniva_ajax_remove_cart_item() {
	if ( ! furniva_verify_cart_or_filter_nonce() ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
	}
	$cart_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? $_POST['cart_key'] ?? '' ) );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'Cart not available' ) );
	}

	if ( ! empty( $cart_key ) && isset( WC()->cart->get_cart()[ $cart_key ] ) ) {
		WC()->cart->remove_cart_item( $cart_key );
		WC()->cart->calculate_totals();
	}

	$subtotal   = (float) WC()->cart->get_subtotal();
	$item_count = (int) WC()->cart->get_cart_contents_count();
	$threshold  = 5000000.00;
	$remaining  = max( 0.00, $threshold - $subtotal );
	$percent    = min( 100.0, ( $subtotal / $threshold ) * 100.0 );

	wp_send_json_success( array(
		'subtotal'       => $subtotal,
		'subtotal_html'  => wp_kses_post( wc_price( $subtotal ) ),
		'item_count'     => $item_count,
		'remaining'      => $remaining,
		'remaining_html' => wp_kses_post( wc_price( $remaining ) ),
		'percent'        => round( $percent, 1 ),
		'is_free'        => ( $remaining <= 0.00 ),
	) );
}
add_action('wp_ajax_furniva_cart_remove_item', 'furniva_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_furniva_cart_remove_item', 'furniva_ajax_remove_cart_item');
add_action('wp_ajax_despace_cart_remove_item', 'furniva_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_despace_cart_remove_item', 'furniva_ajax_remove_cart_item');

function furniva_ajax_remove_cart_items() {
	if ( ! furniva_verify_cart_or_filter_nonce() ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'Cart not available' ) );
	}

	$raw_keys = isset( $_POST['cart_keys'] ) ? (array) wp_unslash( $_POST['cart_keys'] ) : array();
	$removed  = 0;
	foreach ( $raw_keys as $raw_k ) {
		$ck = sanitize_text_field( (string) $raw_k );
		if ( '' !== $ck && isset( WC()->cart->get_cart()[ $ck ] ) ) {
			WC()->cart->remove_cart_item( $ck );
			$removed++;
		}
	}
	if ( $removed > 0 ) {
		WC()->cart->calculate_totals();
	}

	$subtotal   = (float) WC()->cart->get_subtotal();
	$item_count = (int) WC()->cart->get_cart_contents_count();
	$threshold  = 5000000.00;
	$remaining  = max( 0.00, $threshold - $subtotal );
	$percent    = min( 100.0, ( $subtotal / $threshold ) * 100.0 );

	wp_send_json_success( array(
		'removed'        => $removed,
		'subtotal'       => $subtotal,
		'subtotal_html'  => wp_kses_post( wc_price( $subtotal ) ),
		'item_count'     => $item_count,
		'remaining'      => $remaining,
		'remaining_html' => wp_kses_post( wc_price( $remaining ) ),
		'percent'        => round( $percent, 1 ),
		'is_free'        => ( $remaining <= 0.00 ),
	) );
}
add_action( 'wp_ajax_furniva_cart_remove_items', 'furniva_ajax_remove_cart_items' );
add_action( 'wp_ajax_nopriv_furniva_cart_remove_items', 'furniva_ajax_remove_cart_items' );

function furniva_ajax_wishlist_add_to_cart() {
	if ( ! furniva_verify_cart_or_filter_nonce() ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
	}
	if ( ! is_user_logged_in() && 'yes' !== get_option( 'woocommerce_enable_guest_checkout', 'yes' ) ) {
		wp_send_json_error( array(
			'require_login' => true,
			'redirect'      => add_query_arg( 'action', 'login', despace_safe_wc_page_url( 'myaccount', '/tai-khoan/' ) ),
			'message'       => furniva_t( 'Please sign in to add wishlist items to your cart.', 'Quý khách vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.' ),
		), 401 );
	}

	$product_id = intval( $_POST['product_id'] ?? 0 );
	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => 'Invalid product' ) );
	}

	$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 1 );
	if ( ! $passed ) {
		wp_send_json_error( array(
			'message' => furniva_t( 'Please sign in or check product availability before adding to cart.', 'Vui lòng đăng nhập hoặc kiểm tra tình trạng sản phẩm trước khi thêm vào giỏ.' ),
		), 400 );
	}

	$cart_count = 0;
	$added      = false;
	if ( function_exists( 'WC' ) ) {
		if ( is_null( WC()->session ) ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}
		if ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( is_null( WC()->customer ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}
		if ( is_null( WC()->cart ) ) {
			WC()->cart = new WC_Cart();
		}
		$added = WC()->cart->add_to_cart( $product_id, 1 );
		if ( $added ) {
			WC()->cart->calculate_totals();
			$cart_count = WC()->cart->get_cart_contents_count();
		}
	}

	if ( ! $added ) {
		wp_send_json_error( array(
			'message' => furniva_t( 'Could not add this product to your cart.', 'Không thể thêm sản phẩm này vào giỏ hàng.' ),
		), 400 );
	}

	wp_send_json_success(array(
		'cart_count' => $cart_count,
		'message'    => furniva_t('Added to your cart successfully!', 'Đã thêm vào giỏ hàng thành công!'),
	));
}
add_action('wp_ajax_furniva_wishlist_add_to_cart', 'furniva_ajax_wishlist_add_to_cart');
add_action('wp_ajax_nopriv_furniva_wishlist_add_to_cart', 'furniva_ajax_wishlist_add_to_cart');
add_action('wp_ajax_despace_wishlist_add_to_cart', 'furniva_ajax_wishlist_add_to_cart');
add_action('wp_ajax_nopriv_despace_wishlist_add_to_cart', 'furniva_ajax_wishlist_add_to_cart');

function furniva_ajax_wishlist_remove()
{
	check_ajax_referer('furniva_wishlist', 'security');
	$product_id = intval($_POST['product_id'] ?? 0);
	if ($product_id) {
		if (function_exists('yith_wcwl_wishlists')) {
			try {
				yith_wcwl_wishlists()->remove_item(array('product_id' => $product_id));
			} catch (Exception $e) {
				if (function_exists('YITH_WCWL')) {
					YITH_WCWL()->remove(array('remove_from_wishlist' => $product_id));
				}
			}
		} elseif (function_exists('YITH_WCWL')) {
			YITH_WCWL()->remove(array('remove_from_wishlist' => $product_id));
		}
	}
	$count = 0;
	if (function_exists('yith_wcwl_count_all_products')) {
		$count = yith_wcwl_count_all_products();
	} elseif (function_exists('yith_wcwl_count_products')) {
		$count = yith_wcwl_count_products();
	} elseif (function_exists('YITH_WCWL')) {
		$count = count(YITH_WCWL()->get_products());
	}
	wp_send_json_success(array(
		'count'   => $count,
		'message' => furniva_t('Item removed from wishlist.', 'Đã xóa sản phẩm khỏi danh sách yêu thích.'),
	));
}
add_action('wp_ajax_furniva_wishlist_remove', 'furniva_ajax_wishlist_remove');
add_action('wp_ajax_nopriv_furniva_wishlist_remove', 'furniva_ajax_wishlist_remove');
add_action('wp_ajax_despace_wishlist_remove', 'furniva_ajax_wishlist_remove');
add_action('wp_ajax_nopriv_despace_wishlist_remove', 'furniva_ajax_wishlist_remove');

function furniva_ajax_wishlist_clear()
{
	check_ajax_referer('furniva_wishlist', 'security');
	if (class_exists('YITH_WCWL_Wishlist_Factory')) {
		$wishlist = YITH_WCWL_Wishlist_Factory::get_current_wishlist();
		if ($wishlist && method_exists($wishlist, 'get_items')) {
			$items = $wishlist->get_items();
			foreach ($items as $item) {
				$pid = is_object($item) && method_exists($item, 'get_product_id') ? $item->get_product_id() : (is_array($item) ? ($item['prod_id'] ?? $item['product_id'] ?? 0) : 0);
				if ($pid && function_exists('yith_wcwl_wishlists')) {
					try {
						yith_wcwl_wishlists()->remove_item(array('product_id' => $pid));
					} catch (Exception $e) {
					}
				}
			}
		}
	} elseif (function_exists('YITH_WCWL')) {
		$wishlist = YITH_WCWL()->get_wishlists(array('is_default' => true));
		if (! empty($wishlist)) {
			$wishlist_id = $wishlist[0]['ID'];
			$items = YITH_WCWL()->get_products(array('wishlist_id' => $wishlist_id));
			foreach ($items as $item) {
				$prod_id = isset($item['prod_id']) ? $item['prod_id'] : (isset($item['product_id']) ? $item['product_id'] : 0);
				if ($prod_id) {
					YITH_WCWL()->remove(array('remove_from_wishlist' => $prod_id));
				}
			}
		}
	}
	wp_send_json_success(array(
		'count'   => 0,
		'message' => furniva_t('Wishlist cleared.', 'Đã xóa tất cả sản phẩm yêu thích.'),
	));
}
add_action('wp_ajax_furniva_wishlist_clear', 'furniva_ajax_wishlist_clear');
add_action('wp_ajax_nopriv_furniva_wishlist_clear', 'furniva_ajax_wishlist_clear');
add_action('wp_ajax_despace_wishlist_clear', 'furniva_ajax_wishlist_clear');
add_action('wp_ajax_nopriv_despace_wishlist_clear', 'furniva_ajax_wishlist_clear');

function despace_is_wishlist_backend_ready() {
	return function_exists( 'yith_wcwl_wishlists' ) || function_exists( 'YITH_WCWL' );
}

function despace_is_product_in_wishlist( $product_id ) {
	$product_id = intval( $product_id );
	if ( $product_id <= 0 || ! despace_is_wishlist_backend_ready() ) {
		return false;
	}
	if ( function_exists( 'yith_wcwl_wishlists' ) ) {
		try {
			return (bool) yith_wcwl_wishlists()->is_product_in_wishlist( $product_id );
		} catch ( Exception $e ) {
			if ( function_exists( 'YITH_WCWL' ) && method_exists( YITH_WCWL(), 'is_product_in_wishlist' ) ) {
				return (bool) YITH_WCWL()->is_product_in_wishlist( $product_id );
			}
		}
	} elseif ( function_exists( 'YITH_WCWL' ) && method_exists( YITH_WCWL(), 'is_product_in_wishlist' ) ) {
		return (bool) YITH_WCWL()->is_product_in_wishlist( $product_id );
	}
	return false;
}

function furniva_ajax_wishlist_toggle() {
	if ( ! despace_is_wishlist_backend_ready() ) {
		wp_send_json_error( array(
			'code'    => 'wishlist_backend_unavailable',
			'message' => furniva_t(
				'Wishlist is temporarily unavailable because the YITH WooCommerce Wishlist plugin is not installed.',
				'Tính năng yêu thích hiện chưa sẵn sàng do hệ thống chưa cài đặt plugin YITH WooCommerce Wishlist.'
			),
		), 503 );
	}

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array(
			'require_login' => true,
			'redirect'      => add_query_arg( 'action', 'login', despace_safe_wc_page_url( 'myaccount', '/tai-khoan/' ) ),
			'message'       => furniva_t( 'Please sign in to save items to your wishlist.', 'Quý khách vui lòng đăng nhập để lưu sản phẩm vào danh sách yêu thích.' ),
		), 401 );
	}

	if ( ! furniva_verify_cart_or_filter_nonce() ) {
		wp_send_json_error( array(
			'code'    => 'invalid_nonce',
			'message' => furniva_t( 'Session expired. Please reload the page and try again.', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.' ),
		), 403 );
	}

	$product_id = intval($_POST['product_id'] ?? 0);
	if (! $product_id || (function_exists('wc_get_product') && ! wc_get_product($product_id))) {
		wp_send_json_error(array(
			'code'    => 'invalid_product',
			'message' => furniva_t('Invalid product.', 'Sản phẩm không hợp lệ.'),
		), 400);
	}

	$is_in_wishlist = false;

	if (function_exists('yith_wcwl_wishlists')) {
		try {
			if (yith_wcwl_wishlists()->is_product_in_wishlist($product_id)) {
				yith_wcwl_wishlists()->remove_item(array('product_id' => $product_id));
				$is_in_wishlist = false;
			} else {
				yith_wcwl_wishlists()->add_item(array('product_id' => $product_id));
				$is_in_wishlist = true;
			}
		} catch (Exception $e) {
			if (function_exists('YITH_WCWL')) {
				if (YITH_WCWL()->is_product_in_wishlist($product_id)) {
					YITH_WCWL()->remove(array('remove_from_wishlist' => $product_id));
					$is_in_wishlist = false;
				} else {
					YITH_WCWL()->add(array('add_to_wishlist' => $product_id));
					$is_in_wishlist = true;
				}
			}
		}
	} elseif (function_exists('YITH_WCWL')) {
		if (YITH_WCWL()->is_product_in_wishlist($product_id)) {
			YITH_WCWL()->remove(array('remove_from_wishlist' => $product_id));
			$is_in_wishlist = false;
		} else {
			YITH_WCWL()->add(array('add_to_wishlist' => $product_id));
			$is_in_wishlist = true;
		}
	}

	$count = 0;
	if (function_exists('yith_wcwl_count_all_products')) {
		$count = yith_wcwl_count_all_products();
	} elseif (function_exists('yith_wcwl_count_products')) {
		$count = yith_wcwl_count_products();
	} elseif (function_exists('YITH_WCWL')) {
		$count = count(YITH_WCWL()->get_products());
	}

	wp_send_json_success(array(
		'added'       => $is_in_wishlist,
		'count'       => $count,
		'message'     => $is_in_wishlist ? furniva_t('Added to your wishlist ♥', 'Đã thêm vào danh sách yêu thích ♥') : furniva_t('Removed from wishlist', 'Đã xóa khỏi danh sách yêu thích'),
		'button_text' => $is_in_wishlist ? furniva_t('Saved to wishlist ♥', 'Đã lưu yêu thích ♥') : furniva_t('Add to wishlist', 'Thêm yêu thích'),
		'aria_label'  => $is_in_wishlist ? furniva_t('Remove from wishlist', 'Bỏ khỏi danh sách yêu thích') : furniva_t('Add to wishlist', 'Thêm vào danh sách yêu thích'),
	));
}
add_action('wp_ajax_furniva_wishlist_toggle', 'furniva_ajax_wishlist_toggle');
add_action('wp_ajax_nopriv_furniva_wishlist_toggle', 'furniva_ajax_wishlist_toggle');
add_action('wp_ajax_despace_wishlist_toggle', 'furniva_ajax_wishlist_toggle');
add_action('wp_ajax_nopriv_despace_wishlist_toggle', 'furniva_ajax_wishlist_toggle');

function furniva_ajax_get_wishlist_count()
{
	$count = 0;
	if (function_exists('yith_wcwl_count_all_products')) {
		$count = yith_wcwl_count_all_products();
	} elseif (function_exists('yith_wcwl_count_products')) {
		$count = yith_wcwl_count_products();
	} elseif (function_exists('YITH_WCWL')) {
		$count = count(YITH_WCWL()->get_products());
	}
	wp_send_json_success(array('count' => $count));
}
add_action('wp_ajax_furniva_get_wishlist_count', 'furniva_ajax_get_wishlist_count');
add_action('wp_ajax_nopriv_furniva_get_wishlist_count', 'furniva_ajax_get_wishlist_count');
add_action('wp_ajax_despace_get_wishlist_count', 'furniva_ajax_get_wishlist_count');
add_action('wp_ajax_nopriv_despace_get_wishlist_count', 'furniva_ajax_get_wishlist_count');

/**
 * Prevent YITH WooCommerce Wishlist 4.19.0 React bundle from firing guaranteed-401
 * REST requests (/wp-json/yith/wishlist/v1/lists/) on every page load for logged-out guests.
 * Theme product cards use .f-card-wishlist-btn + furniva_wishlist_toggle (which requires login).
 */
add_action('wp_enqueue_scripts', function () {
	if (! is_user_logged_in()) {
		wp_dequeue_script('yith-wcwl-add-to-wishlist');
	}
}, 100);

// ---------------------------------------------------------------- My Account Customizations
function despace_luxury_account_menu_items($items)
{
	// Remove downloads (DeSpace sells luxury physical furniture)
	unset($items['downloads']);

	$custom_items = array();
	foreach ($items as $endpoint => $label) {
		if ('dashboard' === $endpoint) {
			$custom_items[$endpoint] = despace_t('Overview', 'Tổng quan');
		} elseif ('orders' === $endpoint) {
			$custom_items[$endpoint] = despace_t('Order History', 'Lịch sử đơn hàng');
		} elseif ('edit-address' === $endpoint) {
			$custom_items[$endpoint] = despace_t('Addresses', 'Sổ địa chỉ nhận hàng');
		} elseif ('edit-account' === $endpoint) {
			$custom_items[$endpoint] = despace_t('Account & Security', 'Thông tin & Mật khẩu');
		} elseif ('customer-logout' === $endpoint) {
			// Add Wishlist item before logout
			$custom_items['wishlist'] = despace_t('My Wishlist', 'Sản phẩm yêu thích');
			$custom_items[$endpoint]  = despace_t('Sign Out', 'Đăng xuất');
		} else {
			$custom_items[$endpoint]  = $label;
		}
	}
	return $custom_items;
}
function axgnud_luxury_account_menu_items($items)
{
	return despace_luxury_account_menu_items($items);
}
add_filter('woocommerce_account_menu_items', 'despace_luxury_account_menu_items', 99);

function despace_account_endpoint_url($url, $endpoint, $value, $permalink)
{
	if ('wishlist' === $endpoint) {
		return home_url('/wishlist/');
	}
	return $url;
}
function axgnud_account_endpoint_url($url, $endpoint, $value, $permalink)
{
	return despace_account_endpoint_url($url, $endpoint, $value, $permalink);
}
add_filter('woocommerce_get_endpoint_url', 'despace_account_endpoint_url', 10, 4);
