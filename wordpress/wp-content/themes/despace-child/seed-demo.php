<?php
/**
 * Idempotent WooCommerce demo data for local/staging only.
 * Run: wp eval-file wp-content/themes/despace-child/seed-demo.php
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WordPress and WooCommerce must be active before seeding DeSpace demo data.' );
}

update_option( 'woocommerce_currency', 'VND' );
update_option( 'woocommerce_default_country', 'VN' );
update_option( 'woocommerce_store_address', '01 Đường Mẫu' );
update_option( 'woocommerce_store_city', 'Lào Cai' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
update_option( 'woocommerce_enable_coupons', 'yes' );
update_option( 'woocommerce_calc_taxes', 'no' );

$pages = array(
	'home'          => array( 'Trang chủ', '' ),
	'shop'          => array( 'Sản phẩm', '' ),
	'cart'          => array( 'Giỏ hàng', '[woocommerce_cart]' ),
	'checkout'      => array( 'Thanh toán', '[woocommerce_checkout]' ),
	'my-account'    => array( 'Tài khoản', '[woocommerce_my_account]' ),
	'order-tracking'=> array( 'Tra cứu đơn hàng', '[despace_order_tracking]' ),
);
foreach ( $pages as $slug => $page_data ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$args = array(
		'post_title'   => $page_data[0],
		'post_name'    => $slug,
		'post_content' => $page_data[1],
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $page ) {
		$args['ID'] = $page->ID;
		$page_id = wp_update_post( $args, true );
	} else {
		$page_id = wp_insert_post( $args, true );
	}
	if ( is_wp_error( $page_id ) ) {
		WP_CLI::warning( 'Cannot create page ' . $slug . ': ' . $page_id->get_error_message() );
		continue;
	}
	if ( 'shop' === $slug ) update_option( 'woocommerce_shop_page_id', $page_id );
	if ( 'cart' === $slug ) update_option( 'woocommerce_cart_page_id', $page_id );
	if ( 'checkout' === $slug ) update_option( 'woocommerce_checkout_page_id', $page_id );
	if ( 'my-account' === $slug ) update_option( 'woocommerce_myaccount_page_id', $page_id );
	if ( 'home' === $slug ) update_option( 'page_on_front', $page_id );
}
update_option( 'show_on_front', 'page' );

$categories = array(
	'phong-khach' => 'Phòng khách',
	'phong-ngu'   => 'Phòng ngủ',
	'phong-an'    => 'Phòng ăn',
);
$term_ids = array();
foreach ( $categories as $slug => $name ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term ) {
		$created = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		if ( ! is_wp_error( $created ) ) $term_ids[ $slug ] = (int) $created['term_id'];
	} else {
		$term_ids[ $slug ] = (int) $term->term_id;
	}
}

$products = array(
	array( 'Ghe Lounge Aster', 'Ghế thư giãn với phom dáng mềm mại, phù hợp góc đọc sách và không gian tiếp khách.', '12900000', 12, 'phong-khach' ),
	array( 'Sofa Modular Noma', 'Sofa module tối giản, linh hoạt cho căn hộ và phòng khách hiện đại.', '32900000', 5, 'phong-khach' ),
	array( 'Ban Ca Phe Forma', 'Bàn cà phê mặt bàn rộng, chân trụ vững chắc và tông màu trung tính.', '6900000', 18, 'phong-khach' ),
	array( 'Giuong Platform Sora', 'Giường platform thiết kế thấp, nhấn mạnh tỷ lệ và vật liệu tự nhiên.', '24900000', 4, 'phong-ngu' ),
	array( 'Tu Dau Giuong Kiyo', 'Tủ đầu giường nhỏ gọn với ngăn kéo lưu trữ kín đáo.', '4900000', 20, 'phong-ngu' ),
	array( 'Ban An Oakline', 'Bàn ăn gỗ phong cách đương đại, dành cho những bữa cơm quây quần.', '18900000', 7, 'phong-an' ),
	array( 'Ghe An Taper', 'Ghế ăn đường nét thanh thoát, phối hợp cùng bàn ăn gỗ hoặc đá.', '3200000', 30, 'phong-an' ),
	array( 'Den San Arc', 'Đèn sàn dáng vòng cung tạo điểm nhấn ánh sáng cho phòng khách.', '5600000', 9, 'phong-khach' ),
);

$created_count = 0;
foreach ( $products as $data ) {
	$name = $data[0];
	$existing = get_page_by_title( $name, OBJECT, 'product' );
	if ( $existing ) {
		$product = wc_get_product( $existing->ID );
	} else {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
	}
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_description( $data[1] );
	$product->set_short_description( $data[1] );
	$product->set_regular_price( $data[2] );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( (int) $data[3] );
	$product->set_stock_status( 'instock' );
	$product->set_virtual( false );
	$product->set_weight( '8' );
	$id = $product->save();
	if ( isset( $term_ids[ $data[4] ] ) ) {
		wp_set_object_terms( $id, array( $term_ids[ $data[4] ] ), 'product_cat' );
	}
	$created_count++;
}

$zone = null;
foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
	if ( 'Vietnam - DeSpace Staging' === $zone_data['zone_name'] ) {
		$zone = new WC_Shipping_Zone( (int) $zone_data['id'] );
		break;
	}
}
if ( ! $zone ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'Vietnam - DeSpace Staging' );
	$zone->set_zone_order( 1 );
	$zone->add_location( 'VN', 'country' );
	$zone->save();
	$instance_id = $zone->add_shipping_method( 'flat_rate' );
	if ( $instance_id ) {
		update_option( 'woocommerce_flat_rate_' . $instance_id . '_settings', array(
			'enabled' => 'yes',
			'title' => 'Giao hàng tiêu chuẩn',
			'tax_status' => 'none',
			'cost' => '50000',
			'calculation_type' => 'per_order',
		) );
	}
}

foreach ( array( 'cod', 'bacs' ) as $gateway ) {
	$settings = get_option( 'woocommerce_' . $gateway . '_settings', array() );
	$settings['enabled'] = 'yes';
	if ( 'cod' === $gateway ) {
		$settings['title'] = 'Thanh toán khi nhận hàng (COD)';
		$settings['description'] = 'Thanh toán khi nhận sản phẩm. Chỉ dùng cho đơn thử nghiệm.';
	} else {
		$settings['title'] = 'Chuyển khoản ngân hàng';
		$settings['description'] = 'Đơn chuyển khoản sẽ chờ quản trị viên xác nhận đã nhận tiền.';
		$settings['instructions'] = 'Đây là môi trường staging. Không chuyển tiền thật.';
		$settings['account_details'] = array();
	}
	update_option( 'woocommerce_' . $gateway . '_settings', $settings );
}

if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
	WC()->payment_gateways()->init();
}
WP_CLI::success( sprintf( 'DeSpace staging seed complete: %d products, demo pages, VND currency, COD/BACS and Vietnam flat-rate shipping.', $created_count ) );
