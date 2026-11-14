<?php
/**
 * Small language helper for strings rendered by Furniva's custom theme.
 * TranslatePress still handles the saved post and product translation pairs.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function furniva_current_language() {
	$settings = get_option( 'trp_settings', array() );
	$allowed  = isset( $settings['translation-languages'] ) && is_array( $settings['translation-languages'] )
		? $settings['translation-languages']
		: array( 'vi' );

	if ( isset( $_REQUEST['language'] ) ) {
		$request_language = sanitize_text_field( wp_unslash( $_REQUEST['language'] ) );
		if ( in_array( $request_language, $allowed, true ) ) {
			return $request_language;
		}
	}

	global $TRP_LANGUAGE;
	if ( ! empty( $TRP_LANGUAGE ) && in_array( $TRP_LANGUAGE, $allowed, true ) ) {
		return $TRP_LANGUAGE;
	}

	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	if ( in_array( $locale, $allowed, true ) ) {
		return $locale;
	}

	return $settings['default-language'] ?? 'vi';
}

function furniva_t( $english, $vietnamese ) {
	$text = in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ? $english : $vietnamese;
	return str_ireplace( 'furniva', 'AXGNUD', $text );
}

/** Keep translated titles available when templates render WordPress objects directly. */
function furniva_english_titles() {
	return array(
		5 => 'Shop', 6 => 'Cart', 7 => 'Checkout', 8 => 'My account', 208 => 'Home', 209 => 'Blog',
		210 => 'About us', 211 => 'Contact', 212 => 'Shipping & Returns', 213 => 'FAQs', 247 => 'Wishlist',
		10 => 'Home', 11 => 'About DeSpace', 12 => 'Contact DeSpace',
		230 => 'How to Choose the Right Lounge Chair for Your Living Room',
		232 => '5 Ways to Style a Walnut Coffee Table',
		234 => 'Lighting Layers: How to Light a Living Room Properly',
		236 => 'Bed Frame Sizes Explained: Queen vs King',
		238 => 'Small Space, Big Style: Decorating Around a Statement Vase',
		240 => 'Caring for Oak: A 5-Minute Monthly Routine',
		// DeSpace 40 Core Luxury Products
		467 => 'DeSpace Regal 6-Drawer Dresser - Sonoma Oak',
		455 => 'DeSpace Sera 5-Drawer High Chest - Mocha Walnut',
		446 => 'DeSpace Iris 4-Drawer Bedroom Chest - Ice White',
		437 => 'DeSpace Iris 4-Drawer Bedroom Chest - Mocha Walnut',
		423 => 'DeSpace Avenbury 4-Drawer Sliding Barn Door Sideboard - Rustic Oak',
		412 => 'DeSpace Thyme 6-Tier Kitchen Pantry Tall Cabinet - Columbia Walnut',
		401 => 'DeSpace Spice 2-Door 6-Shelf Kitchen Cabinet - Sonoma Oak',
		393 => 'DeSpace Spice 2-Door 6-Shelf Kitchen Cabinet - Wenge Brown',
		382 => 'DeSpace Axis 4-Door Kitchen & Dry Food Pantry - Wenge Brown',
		371 => 'DeSpace Axis 4-Door Kitchen & Dry Food Pantry - Columbia Walnut',
		362 => 'DeSpace Shelvo Dust-Proof Glass Door Bookcase - Modern Oak',
		350 => 'DeSpace Nova 5-Tier Tall Bookshelf - Sonoma Oak',
		340 => 'DeSpace Nova 3-Tier Compact Bookshelf - Sonoma Oak',
		329 => 'DeSpace Nova 4-Tier Bookshelf with Security Lock - Sonoma Oak',
		320 => 'DeSpace Grace A-Frame Ladder Corner Bookshelf - Lome Cherry',
		308 => 'DeSpace Aura Display Cabinet & Open Bookshelf - Lome Cherry',
		301 => 'DeSpace Nexa 2-Door Wardrobe with Hanging Rail - Giant Wood Oak',
		289 => 'DeSpace Vault 2-Door Wardrobe with Hidden Security Drawer - Giant Wood',
		282 => 'DeSpace Nexa 2-Door Wardrobe with Hanging Rail - Lyon Walnut',
		271 => 'DeSpace Wardura 3-Door Wardrobe - Lome Cherry',
		260 => 'DeSpace Tower 4-Door Wardrobe - Columbia Walnut',
		249 => 'DeSpace Tower 4-Door Wardrobe - Wenge Brown',
		238 => 'DeSpace Skyline 3-Door Multi-Function Wardrobe & Shoe Cabinet - Wenge Brown',
		226 => 'DeSpace Zest Chic 2-Door Shoe Cabinet with Drawer - Wenge Brown',
		214 => 'DeSpace Zest Style 2-Door Shoe Cabinet with Full-Length Mirror - Wenge Brown',
		202 => 'DeSpace Minimal 2-Door Compact Shoe Cabinet - Wenge Brown',
		193 => 'DeSpace Minimal 3-Door Minimalist Shoe Cabinet - Wenge Brown',
		182 => 'DeSpace Minimal 3-Door Minimalist Shoe Cabinet - Sonoma Oak & White',
		173 => 'DeSpace Ever Fresh 3-Door Shoe Cabinet - Two-Tone Amber & Brown',
		161 => 'DeSpace Ever Fresh 2-Door Shoe Cabinet - Two-Tone Amber & Brown',
		149 => 'DeSpace Skyline 1.83m Tall Shoe Cabinet - Columbia Walnut',
		136 => 'DeSpace Skyline 6-Feet 4-Louvered Door Full-Height Shoe Cabinet - Wenge Brown',
		126 => 'DeSpace Fusion 6-Tier High Shoe Cabinet - Wenge Brown',
		115 => 'DeSpace Fusion 6-Tier High Shoe Cabinet - Sonoma Oak & White',
		104 => 'DeSpace Rune Deep Multi-Purpose Shoe Cabinet',
		91  => 'DeSpace Willowbrook Farmhouse Shoe Cabinet - Rustic Oak & White',
		81  => 'DeSpace Slate 2-Door Shoe Cabinet with Drawer - Milky Wenge',
		68  => 'DeSpace Slate 3-Door Shoe Cabinet with Drawer - Modern Slate Grey',
		57  => 'DeSpace Metro 2-Door Shoe Cabinet - Columbia Walnut',
		46  => 'DeSpace Metro 3-Door Shoe Cabinet - Linear Wood Grain'
	);
}

function furniva_translate_object_title( $title, $post_id ) {
	if ( ! in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
		return $title;
	}
	if ( 'product' === get_post_type( $post_id ) ) {
		$en_name = get_post_meta( $post_id, '_name_en', true );
		if ( ! empty( $en_name ) ) {
			return $en_name;
		}
	}
	$english_titles = furniva_english_titles();
	return isset( $english_titles[ $post_id ] ) ? $english_titles[ $post_id ] : $title;
}
add_filter( 'the_title', 'furniva_translate_object_title', 99, 2 );

function furniva_translate_document_title_parts( $parts ) {
	if ( is_front_page() ) {
		$parts['title']   = 'AXGNUD';
		$parts['tagline'] = furniva_t( 'Modern furniture for modern living', 'Nội thất hiện đại cho nhịp sống hiện đại' );
		return $parts;
	}
	if ( ! in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
		return $parts;
	}
	$post_id       = is_home() ? (int) get_option( 'page_for_posts' ) : get_queried_object_id();
	if ( 'product' === get_post_type( $post_id ) ) {
		$en_name = get_post_meta( $post_id, '_name_en', true );
		if ( ! empty( $en_name ) ) {
			$parts['title'] = $en_name;
			return $parts;
		}
	}
	$english_titles = furniva_english_titles();
	if ( isset( $english_titles[ $post_id ] ) ) {
		$parts['title'] = $english_titles[ $post_id ];
	}
	return $parts;
}
add_filter( 'document_title_parts', 'furniva_translate_document_title_parts', 99 );

function furniva_translate_product_title( $title, $product ) {
	if ( $product instanceof WC_Product ) {
		if ( in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
			$en_name = get_post_meta( $product->get_id(), '_name_en', true );
			if ( ! empty( $en_name ) ) {
				return $en_name;
			}
		}
		return furniva_translate_object_title( $title, $product->get_id() );
	}
	return $title;
}
add_filter( 'woocommerce_product_get_name', 'furniva_translate_product_title', 99, 2 );

/** Translate product text returned by WooCommerce Store API blocks (cart/checkout). */
function furniva_translate_product_html( $content ) {
	if ( ! is_string( $content ) || '' === $content || ! in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) || ! class_exists( 'TRP_Translate_Press' ) ) {
		return $content;
	}
	preg_match_all( '/>([^<>]+)</u', $content, $matches );
	$strings = array();
	foreach ( $matches[1] as $raw_text ) {
		$text = preg_replace( '/^\s+|\s+$/u', '', html_entity_decode( $raw_text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' !== $text ) {
			$strings[] = $text;
		}
	}
	$strings = array_values( array_unique( $strings ) );
	if ( empty( $strings ) ) {
		return $content;
	}
	$trp      = TRP_Translate_Press::get_trp_instance();
	$query    = $trp->get_component( 'query' );
	$matches  = $query->get_existing_translations( $strings, 'en_US' );
	if ( empty( $matches ) ) {
		return $content;
	}
	return preg_replace_callback( '/>([^<>]+)</u', static function ( $match ) use ( $matches ) {
		$decoded = html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$core    = preg_replace( '/^\s+|\s+$/u', '', $decoded );
		if ( empty( $matches[ $core ]->translated ) ) {
			return $match[0];
		}
		preg_match( '/^\s*/u', $decoded, $leading );
		preg_match( '/\s*$/u', $decoded, $trailing );
		$text = ( $leading[0] ?? '' ) . $matches[ $core ]->translated . ( $trailing[0] ?? '' );
		return '>' . htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' ) . '<';
	}, $content );
}
add_filter( 'woocommerce_product_get_short_description', function( $desc, $product = null ) {
	if ( in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) && $product instanceof WC_Product ) {
		$en_desc = get_post_meta( $product->get_id(), '_short_description_en', true );
		if ( ! empty( $en_desc ) ) return $en_desc;
	}
	return furniva_translate_product_html( $desc );
}, 99, 2 );

add_filter( 'woocommerce_product_get_description', function( $desc, $product = null ) {
	if ( in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) && $product instanceof WC_Product ) {
		$en_desc = get_post_meta( $product->get_id(), '_description_en', true );
		if ( ! empty( $en_desc ) ) return $en_desc;
	}
	return furniva_translate_product_html( $desc );
}, 99, 2 );

function furniva_translate_shipping_title( $title, $method = null ) {
	$labels = array(
		'Standard Shipping'    => array( 'Standard Shipping', 'Giao hàng tiêu chuẩn' ),
		'Giao hàng tiêu chuẩn' => array( 'Standard Shipping', 'Giao hàng tiêu chuẩn' ),
		'Free Shipping'        => array( 'Free Shipping', 'Miễn phí vận chuyển' ),
		'Miễn phí vận chuyển'  => array( 'Free Shipping', 'Miễn phí vận chuyển' ),
	);
	$plain_title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	foreach ( $labels as $source => $pair ) {
		if ( 0 !== strpos( $plain_title, $source ) ) {
			continue;
		}
		$suffix = substr( $plain_title, strlen( $source ) );
		if ( '' !== $suffix && 0 !== strpos( $suffix, ':' ) ) {
			continue;
		}
		return furniva_t( $pair[0], $pair[1] ) . $suffix;
	}
	return $title;
}
add_filter( 'woocommerce_shipping_method_title', 'furniva_translate_shipping_title', 99, 2 );
add_filter( 'woocommerce_shipping_rate_label', 'furniva_translate_shipping_title', 99, 2 );

function furniva_translate_payment_title( $title, $gateway_id ) {
	$labels = array(
		'bacs' => array( 'Bank Transfer (BACS)', 'Chuyển khoản ngân hàng' ),
		'cod'  => array( 'Cash on Delivery', 'Thanh toán khi nhận hàng' ),
	);
	if ( isset( $labels[ $gateway_id ] ) && $title === $labels[ $gateway_id ][0] ) {
		return furniva_t( $labels[ $gateway_id ][0], $labels[ $gateway_id ][1] );
	}
	return $title;
}
add_filter( 'woocommerce_gateway_title', 'furniva_translate_payment_title', 99, 2 );

/** Translate the settings consumed directly by WooCommerce Checkout Blocks. */
function furniva_translate_payment_settings( $settings, $gateway_id ) {
	if ( ! is_array( $settings ) || in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
		return $settings;
	}
	$translations = array(
		'bacs' => array(
			'title'       => array( 'Bank Transfer (BACS)', 'Chuyển khoản ngân hàng' ),
			'description' => array(
				'Make your payment directly into our bank account. Your order will be shipped once funds have cleared.',
				'Chuyển khoản trực tiếp vào tài khoản ngân hàng của chúng tôi. Đơn hàng sẽ được gửi sau khi thanh toán được xác nhận.',
			),
		),
		'cod' => array(
			'title'        => array( 'Cash on Delivery', 'Thanh toán khi nhận hàng' ),
			'description'  => array( 'Pay with cash upon delivery.', 'Thanh toán bằng tiền mặt khi nhận hàng.' ),
			'instructions' => array( 'Please have the exact amount ready for our delivery staff.', 'Vui lòng chuẩn bị tiền mặt đúng số tiền để thanh toán cho nhân viên giao hàng.' ),
		),
	);
	if ( empty( $translations[ $gateway_id ] ) ) {
		return $settings;
	}
	foreach ( $translations[ $gateway_id ] as $key => $pair ) {
		if ( isset( $settings[ $key ] ) && $settings[ $key ] === $pair[0] ) {
			$settings[ $key ] = $pair[1];
		}
	}
	return $settings;
}
add_filter( 'option_woocommerce_bacs_settings', static function ( $settings ) {
	return furniva_translate_payment_settings( $settings, 'bacs' );
} );
add_filter( 'option_woocommerce_cod_settings', static function ( $settings ) {
	return furniva_translate_payment_settings( $settings, 'cod' );
} );

function furniva_translate_payment_description( $description, $gateway_id ) {
	$bank_transfer_description = 'Make your payment directly into our bank account. Your order will be shipped once funds have cleared.';
	if ( 'bacs' === $gateway_id && $bank_transfer_description === wp_strip_all_tags( $description ) ) {
		return furniva_t( $bank_transfer_description, 'Chuyển khoản trực tiếp vào tài khoản ngân hàng của chúng tôi. Đơn hàng sẽ được gửi sau khi thanh toán được xác nhận.' );
	}
	return $description;
}
add_filter( 'woocommerce_gateway_description', 'furniva_translate_payment_description', 99, 2 );

function furniva_translate_menu_item_title( $title ) {
	$translations = array(
		'Trang chủ' => 'Home', 'Cửa hàng' => 'Shop', 'Tạp chí' => 'Blog', 'Giới thiệu' => 'About',
		'Liên hệ' => 'Contact', 'Giao hàng & Đổi trả' => 'Shipping & Returns', 'Giải đáp' => 'FAQs',
		'Tài khoản' => 'My Account', 'Tài khoản của tôi' => 'My Account',
		'Tủ giày & Lưu trữ' => 'Shoe Racks & Storage',
		'Tủ quần áo' => 'Wardrobes',
		'Kệ sách & Tủ trưng bày' => 'Bookshelves',
		'Tủ bếp & Tủ đa năng' => 'Kitchen Cabinets',
		'Tủ ngăn kéo & Tủ Sideboard' => 'Chest of Drawers & Sideboards',
		'Giường ngủ' => 'Beds', 'Ghế' => 'Chairs', 'Đồ trang trí' => 'Decoration', 'Đèn chiếu sáng' => 'Lighting',
		'Sofa' => 'Sofas', 'Bàn' => 'Tables',
	);
	$plain_title = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	if ( isset( $translations[ $plain_title ] ) ) {
		return furniva_t( $translations[ $plain_title ], $plain_title );
	}
	return $title;
}
add_filter( 'nav_menu_item_title', 'furniva_translate_menu_item_title', 99, 1 );

add_filter( 'woocommerce_page_title', function( $title ) {
	if ( in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
		return furniva_term_label( $title, 'product_cat' );
	}
	return $title;
}, 99 );

function furniva_translate_attribute_label( $label, $name ) {
	$labels = array(
		'pa_brand' => array( 'Brand', 'Thương hiệu' ), 'brand' => array( 'Brand', 'Thương hiệu' ),
		'pa_color' => array( 'Color', 'Màu sắc' ), 'color' => array( 'Color', 'Màu sắc' ),
		'pa_size' => array( 'Size', 'Kích thước' ), 'size' => array( 'Size', 'Kích thước' ),
	);
	$key = strtolower( (string) $name );
	if ( isset( $labels[ $key ] ) || isset( $labels[ strtolower( (string) $label ) ] ) ) {
		$pair = $labels[ $key ] ?? $labels[ strtolower( (string) $label ) ];
		return furniva_t( $pair[0], $pair[1] );
	}
	return $label;
}
add_filter( 'woocommerce_attribute_label', 'furniva_translate_attribute_label', 99, 2 );

function furniva_term_label( $name, $taxonomy = '' ) {
	$name = html_entity_decode( (string) $name, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$translations = array(
		'product_cat' => array(
			'Tủ giày & Lưu trữ' => 'Shoe Racks & Storage',
			'Tủ quần áo' => 'Wardrobes',
			'Kệ sách & Tủ trưng bày' => 'Bookshelves',
			'Tủ bếp & Tủ đa năng' => 'Kitchen Cabinets',
			'Tủ ngăn kéo & Tủ Sideboard' => 'Chest of Drawers & Sideboards',
			'Shoe Racks & Storage' => 'Tủ giày & Lưu trữ',
			'Wardrobes' => 'Tủ quần áo',
			'Bookshelves' => 'Kệ sách & Tủ trưng bày',
			'Kitchen Cabinets' => 'Tủ bếp & Tủ đa năng',
			'Chest of Drawers & Sideboards' => 'Tủ ngăn kéo & Tủ Sideboard',
			'Giường ngủ' => 'Beds', 'Ghế' => 'Chairs', 'Đồ trang trí' => 'Decoration',
			'Đèn chiếu sáng' => 'Lighting', 'Sofa' => 'Sofas', 'Bàn' => 'Tables', 'Khác' => 'Uncategorized',
		),
		'category' => array(
			'Chăm sóc & Bảo dưỡng' => 'Care & Maintenance', 'Cảm hứng thiết kế' => 'Design Inspiration',
			'Cẩm nang nội thất' => 'Furniture Guides', 'Khác' => 'Uncategorized',
		),
		'product_tag' => array(
			'Ấm cúng' => 'Cozy', 'Vải' => 'Fabric', 'Thủ công' => 'Handcrafted', 'Da' => 'Leather',
			'Kim loại' => 'Metal', 'Tối giản' => 'Minimal', 'Hiện đại' => 'Modern', 'Gỗ sồi' => 'Oak',
			'Cao cấp' => 'Premium', 'Bắc Âu' => 'Scandinavian', 'Nhung' => 'Velvet', 'Gỗ' => 'Wood',
		),
		'pa_color' => array(
			'Nâu Wenge' => 'Wenge', 'Wenge' => 'Wenge',
			'Gỗ sồi Sonoma' => 'Sonoma Oak', 'Sonoma Oak' => 'Sonoma Oak',
			'Gỗ óc chó Columbia' => 'Columbia Walnut', 'Columbia Walnut' => 'Columbia Walnut',
			'Gỗ anh đào Lome Cherry' => 'Lome Cherry', 'Lome Cherry' => 'Lome Cherry',
			'Gỗ óc chó Mocha' => 'Mocha Walnut', 'Mocha Walnut' => 'Mocha Walnut',
			'Gỗ Giant Wood' => 'Giant Wood', 'Giant Wood' => 'Giant Wood',
			'Gỗ sồi Sonoma & Trắng' => 'Sonoma Oak & White', 'Sonoma Oak & White' => 'Sonoma Oak & White',
			'Gỗ sồi Rustic & Trắng' => 'Rustic Oak & White', 'Rustic Oak & White' => 'Rustic Oak & White',
			'Gỗ sồi Rustic' => 'Rustic Oak', 'Rustic Oak' => 'Rustic Oak',
			'Gỗ sồi Modern Oak' => 'Modern Oak', 'Modern Oak' => 'Modern Oak',
			'Trắng Ice White' => 'Ice White', 'Ice White' => 'Ice White',
			'Xám' => 'Gray', 'Gray' => 'Gray',
			'Vân gỗ Linear' => 'Linear', 'Linear' => 'Linear',
			'Gỗ óc chó Lyon' => 'Lyon Walnut', 'Lyon Walnut' => 'Lyon Walnut',
			'Nâu Milky Wenge' => 'Milky Wenge', 'Milky Wenge' => 'Milky Wenge',
			'Vàng & Nâu Two-Tone' => 'Yellow & Brown', 'Yellow & Brown' => 'Yellow & Brown',
			'Hổ phách' => 'Amber', 'Be' => 'Beige', 'Đen' => 'Black', 'Nâu' => 'Brown',
			'Xanh lá' => 'Green', 'Vàng mù tạt' => 'Mustard', 'Tự nhiên' => 'Natural',
			'Xanh navy' => 'Navy', 'Hồng' => 'Pink', 'Óc chó' => 'Walnut', 'Trắng' => 'White',
		),
		'pa_size' => array( '2 chỗ' => '2-Seat', '3 chỗ' => '3-Seat', 'King' => 'King', 'Queen' => 'Queen' ),
	);
	if ( in_array( furniva_current_language(), array( 'en', 'en_US', 'en_GB' ), true ) ) {
		return $translations[ $taxonomy ][ $name ] ?? $name;
	} else {
		$vi_map = array(
			'Wenge' => 'Nâu Wenge',
			'Sonoma Oak' => 'Gỗ sồi Sonoma',
			'Columbia Walnut' => 'Gỗ óc chó Columbia',
			'Lome Cherry' => 'Gỗ anh đào Lome Cherry',
			'Mocha Walnut' => 'Gỗ óc chó Mocha',
			'Giant Wood' => 'Gỗ Giant Wood',
			'Sonoma Oak & White' => 'Gỗ sồi Sonoma & Trắng',
			'Yellow & Brown' => 'Vàng & Nâu Two-Tone',
			'Linear' => 'Vân gỗ Linear',
			'Gray' => 'Xám',
			'Milky Wenge' => 'Nâu Milky Wenge',
			'Rustic Oak & White' => 'Gỗ sồi Rustic & Trắng',
			'Rustic Oak' => 'Gỗ sồi Rustic',
			'Lyon Walnut' => 'Gỗ óc chó Lyon',
			'Modern Oak' => 'Gỗ sồi Modern Oak',
			'Ice White' => 'Trắng Ice White',
		);
		if ( ( 'pa_color' === $taxonomy || 'pa_finish' === $taxonomy ) && isset( $vi_map[ $name ] ) ) {
			return $vi_map[ $name ];
		}
	}
	return $name;
}

function furniva_translate_plugin_label( $translation, $text, $domain ) {
	if ( 'ajax-search-for-woocommerce' === $domain && 'Products search' === $text ) {
		return furniva_t( 'Products search', 'Tìm kiếm sản phẩm' );
	}
	if ( 'yith-woocommerce-wishlist' === $domain ) {
		$labels = array(
			'My wishlist' => array( 'My wishlist', 'Danh sách yêu thích' ),
			'My wishlist on %s' => array( 'My wishlist on %s', 'Danh sách yêu thích trên %s' ),
			'Product name' => array( 'Product name', 'Tên sản phẩm' ),
			'Unit price' => array( 'Unit price', 'Đơn giá' ),
			'Stock status' => array( 'Stock status', 'Tình trạng hàng' ),
			'No products added to the wishlist' => array( 'No products added to the wishlist', 'Chưa có sản phẩm trong danh sách yêu thích' ),
		);
		if ( isset( $labels[ $text ] ) ) {
			return furniva_t( $labels[ $text ][0], $labels[ $text ][1] );
		}
	}
	if ( in_array( $domain, array( 'woocommerce', 'woocommerce-blocks' ), true ) && 'Delivery' === $text ) {
		return furniva_t( 'Delivery', 'Vận chuyển' );
	}
	return $translation;
}
add_filter( 'gettext', 'furniva_translate_plugin_label', 20, 3 );

/** Correct a mistranslated WooCommerce Blocks label in the official Vietnamese script catalog. */
function furniva_translate_woocommerce_script_catalog( $translations, $file, $handle, $domain ) {
	if ( ! is_string( $translations ) || ! in_array( furniva_current_language(), array( 'vi', 'vi_VN' ), true ) || ! in_array( $domain, array( 'woocommerce', 'woocommerce-blocks' ), true ) ) {
		return $translations;
	}
	$catalog = json_decode( $translations, true );
	if ( ! isset( $catalog['locale_data']['messages']['Delivery'][0] ) ) {
		return $translations;
	}
	$catalog['locale_data']['messages']['Delivery'][0] = 'Vận chuyển';
	return wp_json_encode( $catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
add_filter( 'load_script_translations', 'furniva_translate_woocommerce_script_catalog', 99, 4 );

function furniva_translate_wishlist_option( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	$labels = array(
		'My wishlist' => array( 'My wishlist', 'Danh sách yêu thích' ),
		'Add to wishlist' => array( 'Add to wishlist', 'Thêm vào yêu thích' ),
		'Product added!' => array( 'Product added!', 'Đã thêm sản phẩm vào danh sách yêu thích!' ),
		'Remove from list' => array( 'Remove from list', 'Xóa khỏi danh sách' ),
		'Browse wishlist' => array( 'Browse wishlist', 'Xem danh sách yêu thích' ),
		'The product is already in your wishlist!' => array( 'The product is already in your wishlist!', 'Sản phẩm đã có trong danh sách yêu thích.' ),
	);
	if ( isset( $labels[ $value ] ) ) {
		return furniva_t( $labels[ $value ][0], $labels[ $value ][1] );
	}
	return $value;
}
foreach ( array( 'wishlist_title', 'add_to_wishlist_text', 'product_added_text', 'remove_from_wishlist_text', 'browse_wishlist_text', 'already_in_wishlist_text' ) as $wishlist_option ) {
	add_filter( 'option_yith_wcwl_' . $wishlist_option, 'furniva_translate_wishlist_option' );
}

function furniva_product_terms_html( $product_id, $taxonomy ) {
	$terms = get_the_terms( $product_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}
	$links = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		if ( ! is_wp_error( $url ) ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( furniva_term_label( $term->name, $taxonomy ) ) . '</a>';
		}
	}
	return implode( ', ', $links );
}

function furniva_post_excerpt( $post_id, $words = 26 ) {
	$summaries = array(
		230 => array(
			'A lounge chair is one of the most used seats at home. Measure seat height, floor space and walking clearance before choosing a silhouette and finish.',
			'Ghế thư giãn là một trong những chỗ ngồi được sử dụng nhiều nhất trong nhà. Hãy cân nhắc chiều cao mặt ghế, diện tích đặt và lối đi trước khi chọn kiểu dáng, màu hoàn thiện.',
		),
		232 => array(
			'A walnut coffee table can anchor a living room. Style it with three objects at varied heights, leave breathing room, and add a living accent.',
			'Bàn trà gỗ óc chó là điểm nhấn trầm lắng của phòng khách. Hãy kết hợp đồ vật có chiều cao khác nhau, chừa khoảng trống và thêm một điểm xanh.',
		),
		234 => array(
			'Layer ambient, task and accent lighting to make a room feel considered. Start with a soft base, add focused light where needed, then finish with a warm accent.',
			'Một căn phòng đẹp thường có ba lớp ánh sáng: ánh sáng nền, ánh sáng phục vụ công việc và ánh sáng tạo điểm nhấn. Hãy kết hợp hài hòa để không gian thêm dễ chịu.',
		),
		236 => array(
			'Choosing between Queen and King depends on room dimensions and sleeping habits. Compare mattress measurements, walking clearance and the frame’s footprint before buying.',
			'Chọn giường Queen hay King phụ thuộc vào kích thước phòng ngủ và thói quen nghỉ ngơi. Hãy so sánh kích thước đệm, lối đi và diện tích khung giường trước khi chọn.',
		),
		238 => array(
			'When space is limited, one sculptural object can have more impact than several small pieces. Start with a statement vase, then balance it with breathing room.',
			'Khi diện tích hạn chế, một vật phẩm có hình khối ấn tượng đôi khi đủ tạo điểm nhấn như năm món đồ nhỏ. Hãy chọn một món chủ đạo và chừa khoảng trống hài hòa.',
		),
		240 => array(
			'Oak rewards gentle, regular care. A few minutes of dusting with the grain and wiping spills promptly helps protect the surface and avoid costly restoration.',
			'Nội thất gỗ sồi nguyên khối sẽ bền đẹp hơn nếu được chăm sóc nhẹ nhàng và đều đặn. Lau bụi theo chiều vân gỗ, xử lý vết bẩn sớm và dành ít phút mỗi tháng để bảo dưỡng.',
		),
	);
	if ( isset( $summaries[ $post_id ] ) ) {
		return wp_trim_words( furniva_t( $summaries[ $post_id ][0], $summaries[ $post_id ][1] ), $words );
	}
	return wp_trim_words( get_the_excerpt( $post_id ), $words );
}

/**
 * Direct 2-language switcher toggle [ VI | EN ].
 * Uses TranslatePress URL converter when present, with clean fallback.
 * Guarantees 100% clickable navigation between Vietnamese and English.
 */
function furniva_language_switcher_html() {
	$current_lang = furniva_current_language();
	$is_vi        = in_array( $current_lang, array( 'vi', 'vi_VN' ), true );

	$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8088';
	$uri  = $_SERVER['REQUEST_URI'] ?? '/';
	$current_url = esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri );

	$vi_url = home_url( '/' );
	$en_url = home_url( '/en/' );

	if ( class_exists( 'TRP_Translate_Press' ) ) {
		$trp           = TRP_Translate_Press::get_trp_instance();
		$url_converter = $trp ? $trp->get_component( 'url_converter' ) : null;
		if ( $url_converter && method_exists( $url_converter, 'get_url_for_language' ) ) {
			$vi_url = $url_converter->get_url_for_language( 'vi', $current_url );
			$en_url = $url_converter->get_url_for_language( 'en_US', $current_url );
		}
	}

	// Fallback if TRP didn't produce distinct URLs
	if ( $vi_url === $en_url || empty( $en_url ) ) {
		$path = wp_parse_url( $current_url, PHP_URL_PATH ) ?: '/';
		$query = wp_parse_url( $current_url, PHP_URL_QUERY );
		$qs = $query ? '?' . $query : '';
		if ( 0 === strpos( $path, '/en/' ) || '/en' === $path ) {
			$en_url  = $current_url;
			$vi_path = preg_replace( '#^/en(/|$)#', '/', $path );
			$vi_url  = home_url( $vi_path . $qs );
		} else {
			$vi_url  = $current_url;
			$en_url  = home_url( '/en' . ( '/' === $path ? '/' : $path ) . $qs );
		}
	}

	ob_start();
	?>
	<div class="fv-lang-switcher" role="navigation" aria-label="<?php echo esc_attr( furniva_t( 'Language selector', 'Chọn ngôn ngữ' ) ); ?>">
		<a href="<?php echo esc_url( $vi_url ); ?>" class="fv-lang-btn <?php echo $is_vi ? 'is-active' : ''; ?>" aria-current="<?php echo $is_vi ? 'true' : 'false'; ?>" title="Tiếng Việt">
			<span class="fv-lang-code">VI</span>
		</a>
		<span class="fv-lang-sep" aria-hidden="true">/</span>
		<a href="<?php echo esc_url( $en_url ); ?>" class="fv-lang-btn <?php echo ! $is_vi ? 'is-active' : ''; ?>" aria-current="<?php echo ! $is_vi ? 'true' : 'false'; ?>" title="English">
			<span class="fv-lang-code">EN</span>
		</a>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Currency Switcher for English Experience (USD)
 * When language is set to English, seamlessly convert VND to USD (Rate: 1 USD = 25,000 VND).
 */
function despace_is_english_mode() {
	if ( function_exists( 'furniva_current_language' ) ) {
		$lang = furniva_current_language();
		if ( in_array( $lang, array( 'en', 'en_US', 'en_GB' ), true ) ) {
			return true;
		}
	}
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	return ( 0 === strpos( $uri, '/en/' ) || '/en' === $uri );
}

add_filter( 'woocommerce_currency', function( $currency ) {
	if ( despace_is_english_mode() ) {
		return 'USD';
	}
	return $currency;
}, 99 );

add_filter( 'woocommerce_currency_symbol', function( $symbol, $currency ) {
	if ( 'USD' === $currency || despace_is_english_mode() ) {
		return '$';
	}
	return $symbol;
}, 99, 2 );

add_filter( 'wc_price_args', function( $args ) {
	if ( despace_is_english_mode() ) {
		$args['currency']           = 'USD';
		$args['decimal_separator']  = '.';
		$args['thousand_separator'] = ',';
		$args['decimals']           = 2;
		$args['price_format']       = '%1$s%2$s';
	}
	return $args;
}, 99 );

add_filter( 'raw_woocommerce_price', function( $price ) {
	if ( despace_is_english_mode() && is_numeric( $price ) && $price > 1000 ) {
		// Convert VND to USD with clean rounding (Rate: 25,000 VND / USD)
		return round( (float) $price / 25000, 2 );
	}
	return $price;
}, 99 );


