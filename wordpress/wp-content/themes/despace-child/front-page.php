<?php
/**
 * Furniva homepage — editorial storefront using the current WordPress catalogue.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$shop_id  = wc_get_page_id( 'shop' );
$shop_url = $shop_id > 0 ? get_permalink( $shop_id ) : home_url( '/shop/' );
$contact  = get_page_by_path( 'contact' );
$contact_url = $contact ? get_permalink( $contact ) : home_url( '/contact/' );
$hero_url = get_stylesheet_directory_uri() . '/assets/images/hero-scandinavian.jpg';

$home_categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );
if ( is_wp_error( $home_categories ) ) {
	$home_categories = array();
}
$home_categories = array_values( array_filter( $home_categories, static function ( $term ) {
	return 'uncategorized' !== $term->slug && (int) $term->count > 0;
} ) );
$category_order = array( 'shoe-racks-storage', 'wardrobes', 'bookshelves', 'kitchen-cabinets', 'chest-of-drawers-sideboards' );
usort( $home_categories, static function ( $a, $b ) use ( $category_order ) {
	$a_pos = array_search( $a->slug, $category_order, true );
	$b_pos = array_search( $b->slug, $category_order, true );
	$a_pos = false === $a_pos ? 99 : $a_pos;
	$b_pos = false === $b_pos ? 99 : $b_pos;
	return $a_pos === $b_pos ? strcasecmp( $a->name, $b->name ) : $a_pos - $b_pos;
} );

$fallback_category_images = array(
	'shoe-racks-storage'          => 'prod-sofa.jpg',
	'wardrobes'                   => 'prod-armchair.jpg',
	'bookshelves'                 => 'prod-table.jpg',
	'kitchen-cabinets'            => 'prod-bed.jpg',
	'chest-of-drawers-sideboards' => 'prod-table.jpg',
);
$category_image_url = static function ( $term ) use ( $fallback_category_images ) {
	$thumbnail_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
	if ( $thumbnail_id ) {
		$image = wp_get_attachment_image_url( $thumbnail_id, 'large' );
		if ( $image ) return $image;
	}
	$file = $fallback_category_images[ $term->slug ] ?? 'hero-scandinavian.jpg';
	return get_stylesheet_directory_uri() . '/assets/images/' . $file;
};

$home_category_by_slug = array();
foreach ( $home_categories as $category ) {
	$home_category_by_slug[ $category->slug ] = $category;
}

$promo_categories = array();
foreach ( array( 'shoe-racks-storage', 'wardrobes', 'bookshelves' ) as $slug ) {
	if ( isset( $home_category_by_slug[ $slug ] ) ) $promo_categories[] = $home_category_by_slug[ $slug ];
}

$room_sets = array(
	array( 'key' => 'entryway', 'label' => array( 'Entryway & Living', 'Sảnh & Phòng khách' ), 'cover' => 'shoe-racks-storage', 'categories' => array( 'shoe-racks-storage', 'chest-of-drawers-sideboards' ) ),
	array( 'key' => 'bedroom',  'label' => array( 'Bedroom', 'Phòng ngủ' ), 'cover' => 'wardrobes', 'categories' => array( 'wardrobes', 'chest-of-drawers-sideboards' ) ),
	array( 'key' => 'study',    'label' => array( 'Study & Kitchen', 'Phòng làm việc & Bếp' ), 'cover' => 'bookshelves', 'categories' => array( 'bookshelves', 'kitchen-cabinets' ) ),
);
foreach ( $room_sets as &$room_set ) {
	$room_set['image'] = isset( $home_category_by_slug[ $room_set['cover'] ] )
		? $category_image_url( $home_category_by_slug[ $room_set['cover'] ] )
		: $hero_url;
	$room_set['products'] = array();
	$room_set['product_ids'] = array();
	foreach ( $room_set['categories'] as $category_slug ) {
		$room_products = wc_get_products( array(
			'status'   => 'publish',
			'limit'    => 2,
			'orderby'  => 'popularity',
			'order'    => 'DESC',
			'category' => array( $category_slug ),
		) );
		foreach ( $room_products as $room_product ) {
			if ( isset( $room_set['product_ids'][ $room_product->get_id() ] ) ) continue;
			$room_set['products'][] = $room_product;
			$room_set['product_ids'][ $room_product->get_id() ] = true;
			if ( count( $room_set['products'] ) >= 4 ) break 2;
		}
	}
	unset( $room_set['product_ids'] );
}
unset( $room_set );

$real_home_products = wc_get_products( array(
	'status'  => 'publish',
	'limit'   => 5,
	'orderby' => 'popularity',
	'order'   => 'DESC',
) );

$hero_products = array(
	array( 'slug' => 'shoe-racks-storage', 'image' => get_stylesheet_directory_uri() . '/assets/images/prod-sofa.jpg', 'label' => array( 'Living & Hallway', 'Sảnh & Phòng khách' ) ),
	array( 'slug' => 'wardrobes', 'image' => get_stylesheet_directory_uri() . '/assets/images/prod-bed.jpg', 'label' => array( 'Bedroom Sanctuary', 'Chốn nghỉ ngơi dịu êm' ) ),
);
?>

<div class="furniva fv-home">
	<main class="fv-home-main" id="main-content">
		<section class="fv-concierge fv-reveal fv-reveal-up" aria-labelledby="fv-concierge-title">
			<div class="fv-wrap fv-concierge__inner">
				<div class="fv-concierge__copy">
					<p class="fv-concierge__hello"><?php echo esc_html( furniva_t( 'A more considered home', 'Không gian sống được chăm chút' ) ); ?></p>
					<h2 id="fv-concierge-title"><?php echo esc_html( furniva_t( 'What are you furnishing today?', 'Hôm nay bạn muốn làm mới không gian nào?' ) ); ?></h2>
				</div>
				<form class="fv-concierge__search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
					<label class="sr-only" for="fv-home-search"><?php echo esc_html( furniva_t( 'Search products', 'Tìm sản phẩm' ) ); ?></label>
					<span class="fv-search-glyph" aria-hidden="true">⌕</span>
					<input id="fv-home-search" name="s" type="search" placeholder="<?php echo esc_attr( furniva_t( 'Search furniture and home accents…', 'Tìm nội thất và đồ trang trí…' ) ); ?>" />
					<input type="hidden" name="post_type" value="product" />
					<button type="submit" aria-label="<?php echo esc_attr( furniva_t( 'Search', 'Tìm kiếm' ) ); ?>">→</button>
				</form>
			</div>
			<?php if ( $home_categories ) : ?>
				<nav class="fv-start-chips fv-wrap" aria-label="<?php echo esc_attr( furniva_t( 'Shop by category', 'Mua sắm theo danh mục' ) ); ?>">
					<?php foreach ( $home_categories as $category ) : ?>
						<a class="fv-start-chip" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
							<span class="fv-start-chip__image"><img src="<?php echo esc_url( $category_image_url( $category ) ); ?>" alt="" loading="lazy" /></span>
							<span><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</section>

		<section class="fv-featured-hero fv-reveal fv-reveal-fade" aria-label="<?php echo esc_attr( furniva_t( 'Featured collection', 'Bộ sưu tập nổi bật' ) ); ?>">
			<div class="fv-featured-hero__grid fv-wrap">
				<div class="fv-featured-hero__stage">
					<a href="<?php echo esc_url( $shop_url ); ?>" aria-label="<?php echo esc_attr( furniva_t( 'Explore the AXGNUD collection', 'Khám phá bộ sưu tập AXGNUD' ) ); ?>">
						<img src="<?php echo esc_url( $hero_url ); ?>" alt="<?php echo esc_attr( furniva_t( 'Warm Scandinavian living room with natural wood furniture', 'Không gian phòng khách Bắc Âu ấm áp với nội thất gỗ tự nhiên' ) ); ?>" loading="eager" fetchpriority="high" />
						<span class="fv-home-hero-copy">
							<span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Designed for the everyday', 'Thiết kế cho nhịp sống mỗi ngày' ) ); ?></span>
							<h1 class="fv-home-hero-title"><?php echo wp_kses_post( furniva_t( 'Quiet form.<br>Lasting presence.', 'Đường nét tĩnh lặng.<br>Vẻ đẹp bền lâu.' ) ); ?></h1>
							<span><?php echo esc_html( furniva_t( 'Thoughtful furniture for a home that feels like you.', 'Nội thất được chọn lọc để ngôi nhà mang dấu ấn riêng của bạn.' ) ); ?></span>
							<span class="fv-home-hero-cta"><?php echo esc_html( furniva_t( 'Explore the collection', 'Khám phá bộ sưu tập' ) ); ?> <span aria-hidden="true">↗</span></span>
						</span>
					</a>
				</div>
				<div class="fv-featured-hero__aside" aria-label="<?php echo esc_attr( furniva_t( 'Explore more spaces', 'Khám phá thêm không gian' ) ); ?>">
					<?php foreach ( $hero_products as $index => $feature ) :
						$term = get_term_by( 'slug', $feature['slug'], 'product_cat' );
						$link = $term && ! is_wp_error( $term ) ? get_term_link( $term ) : $shop_url;
					?>
						<a href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( furniva_t( $feature['label'][0], $feature['label'][1] ) ); ?>">
							<img src="<?php echo esc_url( $feature['image'] ); ?>" alt="" loading="lazy" />
							<span class="fv-home-feature-caption"><?php echo esc_html( furniva_t( $feature['label'][0], $feature['label'][1] ) ); ?> <span class="fv-home-feature-arrow" aria-hidden="true">↗</span></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<section class="fv-promise fv-wrap fv-reveal fv-reveal-up" aria-labelledby="fv-promise-title">
			<div class="fv-promise__heading"><h2 id="fv-promise-title"><?php echo esc_html( furniva_t( 'The AXGNUD Promise', 'Cam kết từ AXGNUD' ) ); ?></h2><span><?php echo esc_html( furniva_t( 'Thoughtful by nature', 'Chu đáo từ những điều nhỏ' ) ); ?></span></div>
			<div class="fv-promise__grid">
				<article class="fv-promise__item"><span class="fv-promise__icon" aria-hidden="true">✧</span><h3><?php echo esc_html( furniva_t( 'Complimentary delivery', 'Giao hàng miễn phí' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'On orders over $500', 'Cho đơn hàng trên $500' ) ); ?></p></article>
				<article class="fv-promise__item"><span class="fv-promise__icon" aria-hidden="true">◇</span><h3><?php echo esc_html( furniva_t( '5-year craft warranty', 'Bảo hành chế tác 5 năm' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Made to be lived with', 'An tâm đồng hành dài lâu' ) ); ?></p></article>
				<article class="fv-promise__item"><span class="fv-promise__icon" aria-hidden="true">⌂</span><h3><?php echo esc_html( furniva_t( 'Considered materials', 'Chất liệu được chọn lọc' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Natural textures, honest finishes', 'Vân gỗ và bề mặt giàu cảm xúc' ) ); ?></p></article>
				<article class="fv-promise__item"><span class="fv-promise__icon" aria-hidden="true">♡</span><h3><?php echo esc_html( furniva_t( 'Here to help', 'Luôn sẵn sàng hỗ trợ' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Guidance for choosing with care', 'Tư vấn để bạn chọn đúng điều mình cần' ) ); ?></p></article>
			</div>
		</section>

		<section class="fv-diwali-banner fv-wrap fv-reveal fv-reveal-fade" id="furniva-welcome-offer" aria-label="<?php echo esc_attr( furniva_t( 'Welcome offer', 'Ưu đãi chào mừng' ) ); ?>">
			<span class="fv-diwali-banner__lamp" aria-hidden="true">✧</span>
			<div class="fv-diwali-banner__copy">
				<p><?php echo esc_html( furniva_t( 'An AXGNUD welcome', 'ƯU ĐÃI CHÀO MỪNG AXGNUD' ) ); ?></p>
				<h2><?php echo esc_html( furniva_t( 'A more thoughtful home starts here.', 'Bắt đầu một không gian sống thật có gu.' ) ); ?></h2>
				<span><?php echo esc_html( furniva_t( 'Take 10% off with code WELCOME10.', 'Giảm 10% với mã WELCOME10.' ) ); ?></span>
			</div>
			<button class="fv-diwali-banner__button f-copy-coupon" type="button" data-code="WELCOME10"><?php echo esc_html( furniva_t( 'Copy WELCOME10', 'Sao chép WELCOME10' ) ); ?> <span aria-hidden="true">↗</span></button>
		</section>

		<section class="fv-section fv-wrap fv-home-product-section fv-reveal fv-reveal-up" aria-labelledby="fv-best-title">
			<div class="fv-section-heading"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Well-loved pieces', 'Được yêu thích' ) ); ?></span><h2 id="fv-best-title"><?php echo esc_html( furniva_t( 'Best sellers', 'Sản phẩm bán chạy' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'A few favourites that feel right at home.', 'Những thiết kế được nhiều khách hàng yêu mến.' ) ); ?></p></div><a class="fv-text-link" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Shop all pieces', 'Xem tất cả sản phẩm' ) ); ?> <span aria-hidden="true">→</span></a></div>
			<?php echo do_shortcode( '[best_selling_products limit="4" columns="4" class="f-products"]' ); ?>
		</section>

		<?php if ( $promo_categories ) : ?>
			<section class="fv-section fv-wrap fv-home-promo-rail fv-reveal fv-reveal-up" aria-label="<?php echo esc_attr( furniva_t( 'Explore the collection', 'Khám phá bộ sưu tập' ) ); ?>">
				<div class="fv-promo-rail">
					<?php foreach ( $promo_categories as $index => $category ) : ?>
						<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" aria-label="<?php echo esc_attr( sprintf( furniva_t( 'Explore %s', 'Khám phá %s' ), furniva_term_label( $category->name, 'product_cat' ) ) ); ?>">
							<img src="<?php echo esc_url( $category_image_url( $category ) ); ?>" alt="" loading="lazy" />
							<span class="fv-promo-caption"><small><?php echo esc_html( furniva_t( 'Explore the collection', 'Khám phá bộ sưu tập' ) ); ?></small><strong><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></strong></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $home_categories ) : ?>
			<section class="fv-section fv-wrap fv-home-collections fv-reveal fv-reveal-up" aria-labelledby="fv-collections-title">
				<div class="fv-section-heading fv-section-heading--stacked"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Find your room', 'Chọn theo không gian' ) ); ?></span><h2 id="fv-collections-title"><?php echo esc_html( furniva_t( 'A considered home, room by room.', 'Từng góc nhà, đều đáng được chăm chút.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Start with the room you return to most.', 'Bắt đầu từ không gian bạn yêu thích nhất.' ) ); ?></p></div></div>
				<div class="fv-room-rail">
					<?php foreach ( $home_categories as $category ) : ?>
						<?php
						$room_recommendations = wc_get_products( array(
							'status'   => 'publish',
							'limit'    => 3,
							'orderby'  => 'popularity',
							'order'    => 'DESC',
							'category' => array( $category->slug ),
						) );
						$recommendation_id = 'fv-room-recommendations-' . absint( $category->term_id );
						?>
						<article class="fv-room-card" data-room-card>
							<a class="fv-room-tile" href="<?php echo esc_url( get_term_link( $category ) ); ?>">
								<img src="<?php echo esc_url( $category_image_url( $category ) ); ?>" alt="" loading="lazy" />
								<span class="fv-room-tile__scrim" aria-hidden="true"></span>
								<span class="fv-room-tile__copy"><strong><?php echo esc_html( furniva_term_label( $category->name, 'product_cat' ) ); ?></strong><span><?php echo esc_html( sprintf( furniva_t( '%d pieces', '%d sản phẩm' ), (int) $category->count ) ); ?> <span aria-hidden="true">↗</span></span></span>
							</a>
							<?php if ( $room_recommendations ) : ?>
								<button class="fv-room-card__trigger" type="button" data-room-recommend-trigger aria-expanded="false" aria-controls="<?php echo esc_attr( $recommendation_id ); ?>" aria-label="<?php echo esc_attr( sprintf( furniva_t( 'Show popular %s', 'Xem sản phẩm nổi bật: %s' ), furniva_term_label( $category->name, 'product_cat' ) ) ); ?>">
									<span aria-hidden="true">✦</span><?php echo esc_html( furniva_t( 'Popular', 'Nổi bật' ) ); ?>
								</button>
								<div class="fv-room-card__recommendations" id="<?php echo esc_attr( $recommendation_id ); ?>" aria-label="<?php echo esc_attr( sprintf( furniva_t( 'Popular in %s', 'Sản phẩm nổi bật trong %s' ), furniva_term_label( $category->name, 'product_cat' ) ) ); ?>" aria-hidden="true">
									<div class="fv-room-card__recommend-heading"><span><?php echo esc_html( furniva_t( 'Popular in this collection', 'Được yêu thích trong danh mục' ) ); ?></span><a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( furniva_t( 'View all', 'Xem tất cả' ) ); ?> <span aria-hidden="true">↗</span></a></div>
									<?php foreach ( $room_recommendations as $room_product ) : ?>
										<a class="fv-room-card__product" href="<?php echo esc_url( get_permalink( $room_product->get_id() ) ); ?>">
											<span class="fv-room-card__product-image"><?php echo wp_kses_post( $room_product->get_image( 'woocommerce_thumbnail' ) ); ?></span>
											<span class="fv-room-card__product-copy"><strong><?php echo esc_html( $room_product->get_name() ); ?></strong><span><?php echo wp_kses_post( $room_product->get_price_html() ); ?></span></span>
											<span class="fv-room-card__product-arrow" aria-hidden="true">↗</span>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="fv-section fv-wrap fv-lookbook fv-reveal fv-reveal-up" aria-labelledby="fv-look-title" data-fv-lookbook>
			<div class="fv-lookbook__header">
				<div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Shop the look', 'Chọn theo không gian' ) ); ?></span><h2 id="fv-look-title"><?php echo esc_html( furniva_t( 'Every piece, considered together.', 'Từng món đồ, hài hòa trong cùng không gian.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Choose a room to explore products from the current collection.', 'Chọn một không gian để khám phá sản phẩm trong bộ sưu tập hiện tại.' ) ); ?></p></div>
				<div class="fv-lookbook__controls">
					<div class="fv-look-tabs" role="tablist" aria-label="<?php echo esc_attr( furniva_t( 'Choose a room', 'Chọn không gian' ) ); ?>">
						<?php foreach ( $room_sets as $index => $room_set ) : ?>
							<button id="fv-look-tab-<?php echo esc_attr( $room_set['key'] ); ?>" role="tab" type="button" data-fv-look-tab aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-controls="fv-look-panel-<?php echo esc_attr( $room_set['key'] ); ?>" tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>"><?php echo esc_html( furniva_t( $room_set['label'][0], $room_set['label'][1] ) ); ?></button>
						<?php endforeach; ?>
					</div>
					<div class="fv-lookbook__arrows" aria-label="<?php echo esc_attr( furniva_t( 'Browse rooms', 'Duyệt không gian' ) ); ?>">
						<button type="button" data-fv-look-step="-1" aria-label="<?php echo esc_attr( furniva_t( 'Previous room', 'Không gian trước' ) ); ?>">‹</button>
						<button type="button" data-fv-look-step="1" aria-label="<?php echo esc_attr( furniva_t( 'Next room', 'Không gian tiếp theo' ) ); ?>">›</button>
					</div>
				</div>
			</div>
			<?php
			$look_positions = array( array( 29, 48 ), array( 54, 62 ), array( 77, 48 ), array( 72, 75 ) );
			foreach ( $room_sets as $room_index => $room_set ) :
				$room_term = $home_category_by_slug[ $room_set['cover'] ] ?? null;
				$room_link = $room_term ? get_term_link( $room_term ) : $shop_url;
			?>
				<div class="fv-lookbook__panel" id="fv-look-panel-<?php echo esc_attr( $room_set['key'] ); ?>" role="tabpanel" aria-labelledby="fv-look-tab-<?php echo esc_attr( $room_set['key'] ); ?>" data-fv-look-panel <?php echo 0 === $room_index ? '' : 'hidden'; ?> tabindex="0">
					<div class="fv-look-stage">
						<img src="<?php echo esc_url( $room_set['image'] ); ?>" alt="<?php echo esc_attr( sprintf( furniva_t( '%s furniture collection', 'Bộ sưu tập nội thất %s' ), furniva_t( $room_set['label'][0], $room_set['label'][1] ) ) ); ?>" loading="lazy" />
						<?php foreach ( array_slice( $room_set['products'], 0, 4 ) as $product_index => $room_product ) :
							$position = $look_positions[ $product_index ] ?? array( 50, 50 );
							$popover_id = 'fv-look-popover-' . $room_set['key'] . '-' . $product_index;
							$product_image = $room_product->get_image_id() ? wp_get_attachment_image_url( $room_product->get_image_id(), 'woocommerce_thumbnail' ) : $room_set['image'];
						?>
							<button class="fv-look-spot" type="button" style="left:<?php echo esc_attr( $position[0] ); ?>%;top:<?php echo esc_attr( $position[1] ); ?>%" data-fv-look-spot aria-label="<?php echo esc_attr( $room_product->get_name() ); ?>" aria-controls="<?php echo esc_attr( $popover_id ); ?>" aria-pressed="false"><span aria-hidden="true"></span></button>
							<aside class="fv-look-popover" id="<?php echo esc_attr( $popover_id ); ?>" style="left:<?php echo esc_attr( $position[0] ); ?>%;top:<?php echo esc_attr( $position[1] ); ?>%" data-fv-look-popover hidden>
								<img src="<?php echo esc_url( $product_image ); ?>" alt="" loading="lazy" />
								<div><strong><?php echo esc_html( $room_product->get_name() ); ?></strong><span><?php echo wp_kses_post( $room_product->get_price_html() ); ?></span><a href="<?php echo esc_url( get_permalink( $room_product->get_id() ) ); ?>"><?php echo esc_html( furniva_t( 'View product', 'Xem sản phẩm' ) ); ?> <span aria-hidden="true">→</span></a></div>
							</aside>
						<?php endforeach; ?>
					</div>
					<div class="fv-lookbook__detail">
						<span class="fv-lookbook__room-name"><?php echo esc_html( furniva_t( $room_set['label'][0], $room_set['label'][1] ) ); ?></span>
						<div class="fv-lookbook__items">
							<?php foreach ( array_slice( $room_set['products'], 0, 4 ) as $room_product ) : ?>
								<a class="fv-look-item" href="<?php echo esc_url( get_permalink( $room_product->get_id() ) ); ?>">
									<?php echo $room_product->get_image( 'woocommerce_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
									<span><strong><?php echo esc_html( $room_product->get_name() ); ?></strong><small><?php echo wp_kses_post( $room_product->get_price_html() ); ?></small></span><span aria-hidden="true">↗</span>
								</a>
							<?php endforeach; ?>
						</div>
						<div class="fv-lookbook__total"><span><?php echo esc_html( furniva_t( 'Explore this room', 'Khám phá không gian' ) ); ?></span><a class="fv-text-link" href="<?php echo esc_url( $room_link ); ?>"><?php echo esc_html( furniva_t( 'See all', 'Xem tất cả' ) ); ?> →</a></div>
					</div>
				</div>
			<?php endforeach; ?>
		</section>

		<section class="fv-section fv-wrap fv-home-new" aria-labelledby="fv-new-title">
			<div class="fv-section-heading"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Just arrived', 'Vừa lên kệ' ) ); ?></span><h2 id="fv-new-title"><?php echo wp_kses_post( furniva_t( 'Fresh from the <em>workshop.</em>', 'Thiết kế mới từ <em>xưởng chế tác.</em>' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Discover the latest additions to the collection.', 'Khám phá những thiết kế mới nhất trong bộ sưu tập.' ) ); ?></p></div><a class="fv-text-link" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Discover what is new', 'Khám phá sản phẩm mới' ) ); ?> <span aria-hidden="true">→</span></a></div>
			<?php echo do_shortcode( '[recent_products limit="4" columns="4" class="f-products"]' ); ?>
		</section>

		<!-- THE AXGNUD EDIT Bento Grid Section -->
		<section class="fv-bento-section fv-wrap fv-reveal fv-reveal-up" id="the-axgnud-edit" aria-labelledby="fv-bento-title">
			<div class="fv-bento-header">
				<!-- Left Column: Eyebrow + Title + Subtitle -->
				<div class="fv-bento-header__left">
					<div class="fv-bento-eyebrow-wrap">
						<span class="fv-bento-eyebrow"><?php echo esc_html( furniva_t( 'THE AXGNUD EDIT', 'THE AXGNUD EDIT' ) ); ?></span>
						<span class="fv-bento-eyebrow-line" aria-hidden="true"></span>
					</div>
					<h2 id="fv-bento-title" class="fv-bento-title"><?php echo wp_kses_post( furniva_t( "Những lựa chọn tạo nên<br>một ngôi nhà có gu.", "Những lựa chọn tạo nên<br>một ngôi nhà có gu." ) ); ?></h2>
					<p class="fv-bento-desc"><?php echo wp_kses_post( furniva_t( "Khám phá các bộ sưu tập được tuyển chọn,<br>truyền cảm hứng cho không gian sống hiện đại và tinh tế hơn.", "Khám phá các bộ sưu tập được tuyển chọn,<br>truyền cảm hứng cho không gian sống hiện đại và tinh tế hơn." ) ); ?></p>
				</div>

				<!-- Middle Column: 4 commitments with thin SVG icons in #7A6855 and 3 dividers #DFD8CE -->
				<div class="fv-bento-header__middle">
					<div class="fv-bento-commitments">
						<!-- Item 1: Diamond -->
						<div class="fv-bento-commit-item">
							<div class="fv-bento-commit-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="#7A6855" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M6 3h12l4 7-10 11L2 10l4-7z"/>
									<path d="M2 10h20"/>
									<path d="M12 21L8 10l4-7 4 7-4 11z"/>
								</svg>
							</div>
							<div class="fv-bento-commit-text">
								<span><?php echo esc_html( furniva_t( 'Thiết kế', 'Thiết kế' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'tuyển chọn', 'tuyển chọn' ) ); ?></span>
							</div>
						</div>

						<span class="fv-bento-commit-divider" aria-hidden="true"></span>

						<!-- Item 2: House -->
						<div class="fv-bento-commit-item">
							<div class="fv-bento-commit-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="#7A6855" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5z"/>
								</svg>
							</div>
							<div class="fv-bento-commit-text">
								<span><?php echo esc_html( furniva_t( 'Phù hợp', 'Phù hợp' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'mọi không gian', 'mọi không gian' ) ); ?></span>
							</div>
						</div>

						<span class="fv-bento-commit-divider" aria-hidden="true"></span>

						<!-- Item 3: Leaf -->
						<div class="fv-bento-commit-item">
							<div class="fv-bento-commit-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="#7A6855" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M11 20A7 7 0 0 1 4 13C4 7 11 3 20 3c0 9-4 16-11 16z"/>
									<path d="M4 13c7 0 13-3 16-10"/>
									<path d="M2 22l7-7"/>
								</svg>
							</div>
							<div class="fv-bento-commit-text">
								<span><?php echo esc_html( furniva_t( 'Vật liệu', 'Vật liệu' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'bền vững', 'bền vững' ) ); ?></span>
							</div>
						</div>

						<span class="fv-bento-commit-divider" aria-hidden="true"></span>

						<!-- Item 4: Heart -->
						<div class="fv-bento-commit-item">
							<div class="fv-bento-commit-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none" stroke="#7A6855" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
								</svg>
							</div>
							<div class="fv-bento-commit-text">
								<span><?php echo esc_html( furniva_t( 'Nâng tầm', 'Nâng tầm' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'cuộc sống', 'cuộc sống' ) ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Right Column: Handwriting Cursive Signature with curved underline -->
				<div class="fv-bento-header__right">
					<div class="fv-bento-signature">
						<span class="fv-bento-sig-line">Good design</span>
						<span class="fv-bento-sig-line">Better living</span>
						<svg class="fv-bento-sig-underline" viewBox="0 0 118 14" fill="none" stroke="#9C8974" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
							<path d="M2 11c30-8 62-10 114 1"/>
						</svg>
					</div>
				</div>
			</div>

			<!-- Bento Grid: 6 cards -->
			<div class="fv-bento-grid">
				<!-- Upper Block: Left 7 cols (Card 1), Right 5 cols (Card 2 + Card 3 stacked) -->
				<div class="fv-bento-row fv-bento-row--upper">
					<!-- Card 1 - Phòng Khách (7 cols, 520px) -->
					<article class="fv-bento-card fv-bento-card--living" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-living.jpg' ); ?>');">
						<div class="fv-bento-card__bg" aria-hidden="true"></div>
						<div class="fv-bento-card__scrim" aria-hidden="true"></div>
						<div class="fv-bento-card__content">
							<span class="fv-bento-card__tag"><?php echo esc_html( furniva_t( 'ROOM', 'PHÒNG' ) ); ?></span>
							<h3 class="fv-bento-card__title"><?php echo esc_html( furniva_t( 'Living', 'Khách' ) ); ?></h3>
							<span class="fv-bento-card__rule" aria-hidden="true"></span>
							<div class="fv-bento-card__subcats">
								<span><?php echo esc_html( furniva_t( 'Sofa · Bàn trà · Ghế thư giãn', 'Sofa · Bàn trà · Ghế thư giãn' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Kệ tivi · Nội thất phòng khách', 'Kệ tivi · Nội thất phòng khách' ) ); ?></span>
							</div>
							<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
								<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
							</a>
						</div>
						<div class="fv-bento-card__footer">
							<div class="fv-bento-card__progress">
								<span class="fv-bento-card__counter">01 / 06</span>
								<div class="fv-bento-progress-bars" aria-hidden="true">
									<span class="active"></span>
									<span></span>
									<span></span>
									<span></span>
								</div>
							</div>
							<div class="fv-bento-card__nav" aria-label="<?php echo esc_attr( furniva_t( 'Slide navigation', 'Điều hướng ảnh' ) ); ?>">
								<button type="button" class="fv-bento-nav-btn" aria-label="Previous">&lt;</button>
								<button type="button" class="fv-bento-nav-btn" aria-label="Next">&gt;</button>
							</div>
						</div>
					</article>

					<!-- Right Stack: Card 2 (Ăn) & Card 3 (Ngủ) (5 cols, each 250px) -->
					<div class="fv-bento-col-stack">
						<!-- Card 2 - Phòng Ăn -->
						<article class="fv-bento-card fv-bento-card--dining" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-dining.jpg' ); ?>');">
							<div class="fv-bento-card__bg" aria-hidden="true"></div>
							<div class="fv-bento-card__scrim" aria-hidden="true"></div>
							<div class="fv-bento-card__content">
								<span class="fv-bento-card__tag"><?php echo esc_html( furniva_t( 'ROOM', 'PHÒNG' ) ); ?></span>
								<h3 class="fv-bento-card__title"><?php echo esc_html( furniva_t( 'Dining', 'Ăn' ) ); ?></h3>
								<div class="fv-bento-card__subcats">
									<span><?php echo esc_html( furniva_t( 'Bàn ăn · Ghế ăn', 'Bàn ăn · Ghế ăn' ) ); ?></span>
									<span><?php echo esc_html( furniva_t( 'Tủ lưu trữ · Đèn thả', 'Tủ lưu trữ · Đèn thả' ) ); ?></span>
								</div>
								<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
									<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
								</a>
							</div>
						</article>

						<!-- Card 3 - Phòng Ngủ -->
						<article class="fv-bento-card fv-bento-card--bedroom" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-bedroom.jpg' ); ?>');">
							<div class="fv-bento-card__bg" aria-hidden="true"></div>
							<div class="fv-bento-card__scrim" aria-hidden="true"></div>
							<div class="fv-bento-card__content">
								<span class="fv-bento-card__tag"><?php echo esc_html( furniva_t( 'ROOM', 'PHÒNG' ) ); ?></span>
								<h3 class="fv-bento-card__title"><?php echo esc_html( furniva_t( 'Bedroom', 'Ngủ' ) ); ?></h3>
								<div class="fv-bento-card__subcats">
									<span><?php echo esc_html( furniva_t( 'Giường · Táp đầu giường', 'Giường · Táp đầu giường' ) ); ?></span>
									<span><?php echo esc_html( furniva_t( 'Tủ quần áo · Nội thất phòng ngủ', 'Tủ quần áo · Nội thất phòng ngủ' ) ); ?></span>
								</div>
								<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
									<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
								</a>
							</div>
						</article>
					</div>
				</div>

				<!-- Lower Block: 3 equal cols (Card 4, Card 5, Card 6) (4 cols each, height 260px) -->
				<div class="fv-bento-row fv-bento-row--lower">
					<!-- Card 4 - Góc Làm việc -->
					<article class="fv-bento-card fv-bento-card--workspace" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-workspace.jpg' ); ?>');">
						<div class="fv-bento-card__bg" aria-hidden="true"></div>
						<div class="fv-bento-card__scrim" aria-hidden="true"></div>
						<div class="fv-bento-card__content">
							<h3 class="fv-bento-card__title fv-bento-card__title--split">
								<span><?php echo esc_html( furniva_t( 'Góc', 'Góc' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Làm việc', 'Làm việc' ) ); ?></span>
							</h3>
							<div class="fv-bento-card__subcats">
								<span><?php echo esc_html( furniva_t( 'Bàn làm việc · Ghế văn phòng', 'Bàn làm việc · Ghế văn phòng' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Tủ lưu trữ · Phụ kiện', 'Tủ lưu trữ · Phụ kiện' ) ); ?></span>
							</div>
							<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
								<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
							</a>
						</div>
					</article>

					<!-- Card 5 - Ánh Sáng -->
					<article class="fv-bento-card fv-bento-card--lighting" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-lighting.jpg' ); ?>');">
						<div class="fv-bento-card__bg" aria-hidden="true"></div>
						<div class="fv-bento-card__scrim" aria-hidden="true"></div>
						<div class="fv-bento-card__content">
							<h3 class="fv-bento-card__title fv-bento-card__title--split">
								<span><?php echo esc_html( furniva_t( 'Ánh', 'Ánh' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Sáng', 'Sáng' ) ); ?></span>
							</h3>
							<div class="fv-bento-card__subcats">
								<span><?php echo esc_html( furniva_t( 'Đèn bàn · Đèn sàn', 'Đèn bàn · Đèn sàn' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Đèn thả · Đèn tường', 'Đèn thả · Đèn tường' ) ); ?></span>
							</div>
							<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
								<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
							</a>
						</div>
					</article>

					<!-- Card 6 - Trang Trí -->
					<article class="fv-bento-card fv-bento-card--decor" style="--bento-bg: url('<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/bento/bento-decor.jpg' ); ?>');">
						<div class="fv-bento-card__bg" aria-hidden="true"></div>
						<div class="fv-bento-card__scrim" aria-hidden="true"></div>
						<div class="fv-bento-card__content">
							<h3 class="fv-bento-card__title fv-bento-card__title--split">
								<span><?php echo esc_html( furniva_t( 'Trang', 'Trang' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Trí', 'Trí' ) ); ?></span>
							</h3>
							<div class="fv-bento-card__subcats">
								<span><?php echo esc_html( furniva_t( 'Tranh · Gương · Bình hoa', 'Tranh · Gương · Bình hoa' ) ); ?></span>
								<span><?php echo esc_html( furniva_t( 'Đồ trang trí · Phụ kiện', 'Đồ trang trí · Phụ kiện' ) ); ?></span>
							</div>
							<a class="fv-bento-pill-btn" href="<?php echo esc_url( $shop_url ); ?>">
								<?php echo esc_html( furniva_t( 'Khám phá ngay', 'Khám phá ngay' ) ); ?> <span class="fv-btn-arrow" aria-hidden="true">→</span>
							</a>
						</div>
					</article>
				</div>
			</div>
		</section>

		<section class="fv-trust-section fv-wrap fv-reveal fv-reveal-up" aria-labelledby="fv-trust-title">
			<div class="fv-packing-photo">
				<img src="<?php echo esc_url( $hero_url ); ?>" alt="<?php echo esc_attr( furniva_t( 'Warm and natural AXGNUD living room', 'Không gian phòng khách AXGNUD ấm áp, gần gũi thiên nhiên' ) ); ?>" loading="lazy" />
				<span class="fv-packing-photo__caption"><span aria-hidden="true">✧</span><?php echo esc_html( furniva_t( 'Thoughtful details for everyday living', 'Chăm chút cho nhịp sống mỗi ngày' ) ); ?></span>
			</div>
			<div class="fv-trust-copy"><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Here to help', 'AXGNUD đồng hành cùng bạn' ) ); ?></span><h2 id="fv-trust-title"><?php echo esc_html( furniva_t( 'A little more clarity, before you choose.', 'Thêm thông tin rõ ràng trước khi bạn chọn.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Find product details, order information and a direct way to reach our team.', 'Xem thông tin sản phẩm, đơn hàng và cách liên hệ trực tiếp với đội ngũ AXGNUD.' ) ); ?></p></div>
			<div class="fv-faq" aria-labelledby="fv-faq-title">
				<h3 id="fv-faq-title"><?php echo esc_html( furniva_t( 'Frequently asked', 'Câu hỏi thường gặp' ) ); ?></h3>
				<?php
				$home_faq = array(
					array( 'How do I choose the right piece?', 'Làm sao để chọn sản phẩm phù hợp?', 'Open a product page to review its photos, description and available details. If you need help deciding, our team can help.', 'Mở trang sản phẩm để xem ảnh, mô tả và các thông tin hiện có. Nếu cần tư vấn trước khi chọn, đội ngũ AXGNUD sẵn sàng hỗ trợ.', 'Browse the collection', 'Khám phá bộ sưu tập', $shop_url ),
					array( 'Where can I find delivery information?', 'Xem thông tin giao hàng ở đâu?', 'Delivery options and details are shown during checkout. You can also contact us with your location and the product you are considering.', 'Các lựa chọn và thông tin giao hàng sẽ hiển thị trong quá trình thanh toán. Bạn cũng có thể liên hệ và cho chúng tôi biết khu vực cùng sản phẩm đang quan tâm.', 'Contact our team', 'Liên hệ đội ngũ AXGNUD', $contact_url ),
					array( 'How can I ask about an order?', 'Tôi có thể hỏi về đơn hàng bằng cách nào?', 'Send us your order number through the contact page and our team will help you find the right information.', 'Gửi mã đơn hàng qua trang liên hệ, đội ngũ AXGNUD sẽ hỗ trợ bạn tra cứu thông tin phù hợp.', 'Get in touch', 'Gửi lời nhắn', $contact_url ),
					array( 'Can I check payment and return terms first?', 'Tôi có thể xem điều khoản thanh toán và đổi trả trước không?', 'Please review the current information on the relevant store pages or contact us before placing your order.', 'Vui lòng xem thông tin hiện hành trên các trang chính sách của cửa hàng hoặc liên hệ với chúng tôi trước khi đặt hàng.', 'Read store information', 'Xem thông tin cửa hàng', home_url( '/shipping-returns/' ) ),
				);
				foreach ( $home_faq as $faq_index => $faq ) :
					$question_id = 'fv-faq-question-' . $faq_index;
					$answer_id = 'fv-faq-answer-' . $faq_index;
				?>
					<div class="fv-faq__item">
						<h4><button id="<?php echo esc_attr( $question_id ); ?>" type="button" data-fv-faq-trigger aria-expanded="<?php echo 0 === $faq_index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $answer_id ); ?>"><span class="fv-faq__icon" aria-hidden="true"><?php echo 0 === $faq_index ? '◇' : '✧'; ?></span><span><?php echo esc_html( furniva_t( $faq[0], $faq[1] ) ); ?></span><span class="fv-faq__toggle" aria-hidden="true"><?php echo 0 === $faq_index ? '−' : '+'; ?></span></button></h4>
						<div class="fv-faq__answer" id="<?php echo esc_attr( $answer_id ); ?>" role="region" aria-labelledby="<?php echo esc_attr( $question_id ); ?>" <?php echo 0 === $faq_index ? '' : 'hidden'; ?>><p><?php echo esc_html( furniva_t( $faq[2], $faq[3] ) ); ?></p><a href="<?php echo esc_url( $faq[6] ); ?>"><?php echo esc_html( furniva_t( $faq[4], $faq[5] ) ); ?> <span aria-hidden="true">→</span></a></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<?php
		$latest_posts = new WP_Query( array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );
		if ( $latest_posts->have_posts() ) :
		?>
			<section class="fv-section fv-wrap fv-home-journal fv-reveal fv-reveal-up" aria-labelledby="fv-journal-title">
				<div class="fv-section-heading"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Ideas & inspiration', 'Ý tưởng và cảm hứng' ) ); ?></span><h2 id="fv-journal-title"><?php echo esc_html( furniva_t( 'A thoughtful life at home.', 'Cảm hứng cho cuộc sống tại nhà.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Stories, practical guides and inspiration from our journal.', 'Câu chuyện, hướng dẫn hữu ích và ý tưởng từ tạp chí AXGNUD.' ) ); ?></p></div><a class="fv-text-link" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog/' ) ); ?>"><?php echo esc_html( furniva_t( 'Read the journal', 'Đọc tạp chí' ) ); ?> <span aria-hidden="true">→</span></a></div>
				<div class="fv-home-journal-grid fv-stagger">
					<?php while ( $latest_posts->have_posts() ) : $latest_posts->the_post(); ?>
						<article class="fv-home-article">
							<a class="fv-home-article__image" href="<?php the_permalink(); ?>"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); } else { ?><img src="<?php echo esc_url( $hero_url ); ?>" alt="" loading="lazy" /><?php } ?></a>
							<div class="fv-home-article__meta"><span><?php echo esc_html( get_the_date() ); ?></span><?php $article_categories = get_the_category(); if ( $article_categories ) : ?><span><?php echo esc_html( furniva_term_label( $article_categories[0]->name, 'category' ) ); ?></span><?php endif; ?></div>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<p><?php echo esc_html( furniva_post_excerpt( get_the_ID(), 22 ) ); ?></p>
							<a class="fv-home-article__link" href="<?php the_permalink(); ?>"><?php echo esc_html( furniva_t( 'Read the story', 'Đọc bài viết' ) ); ?> <span aria-hidden="true">→</span></a>
						</article>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$home_reviews = get_comments( array(
			'status'    => 'approve',
			'type'      => 'review',
			'post_type' => 'product',
			'number'    => 6,
			'orderby'   => 'comment_date_gmt',
			'order'     => 'DESC',
		) );
		if ( $home_reviews ) :
		?>
			<section class="fv-section fv-wrap fv-home-reviews fv-reveal fv-reveal-up" aria-labelledby="fv-reviews-title">
				<div class="fv-section-heading"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Kind words', 'Lời thương mến' ) ); ?></span><h2 id="fv-reviews-title"><?php echo esc_html( furniva_t( 'Chosen for the moments that matter.', 'Được chọn cho những khoảnh khắc đáng nhớ.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Notes shared by customers about the pieces they brought home.', 'Chia sẻ của khách hàng về những món đồ đã chọn cho ngôi nhà.' ) ); ?></p></div></div>
				<div class="fv-review-rail fv-stagger">
					<?php foreach ( $home_reviews as $review ) :
						$product = wc_get_product( $review->comment_post_ID );
						$rating  = (int) get_comment_meta( $review->comment_ID, 'rating', true );
					?>
						<article class="fv-review-card">
							<div class="fv-review-card__top"><span class="fv-stars" aria-label="<?php echo esc_attr( sprintf( furniva_t( '%d out of 5 stars', '%d trên 5 sao' ), $rating ) ); ?>"><?php echo esc_html( str_repeat( '★', max( 0, min( 5, $rating ) ) ) . str_repeat( '☆', max( 0, 5 - min( 5, $rating ) ) ) ); ?></span><time datetime="<?php echo esc_attr( get_comment_date( 'c', $review ) ); ?>"><?php echo esc_html( get_comment_date( '', $review ) ); ?></time></div>
							<div class="fv-review-card__body"><div><?php if ( $product ) : ?><h3><?php echo esc_html( $product->get_name() ); ?></h3><?php endif; ?><blockquote><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $review->comment_content ), 32 ) ); ?></blockquote></div><?php if ( $product ) : ?><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>"><?php echo $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></a><?php endif; ?></div>
							<div class="fv-review-card__foot"><span class="fv-review-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $review->comment_author, 0, 1 ) ); ?></span><div><strong><?php echo esc_html( $review->comment_author ); ?></strong><span><?php echo esc_html( furniva_t( 'Verified customer', 'Khách hàng đã mua' ) ); ?></span></div><span class="fv-verified">✓ <?php echo esc_html( furniva_t( 'Verified', 'Đã xác thực' ) ); ?></span></div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $real_home_products ) : ?>
			<section class="fv-section fv-wrap" aria-labelledby="fv-edit-title">
				<div class="fv-section-heading"><div><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'The AXGNUD edit', 'Tuyển chọn từ AXGNUD' ) ); ?></span><h2 id="fv-edit-title"><?php echo esc_html( furniva_t( 'Details that make a room.', 'Những chi tiết làm nên không gian.' ) ); ?></h2></div><a class="fv-text-link" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Explore all pieces', 'Khám phá mọi sản phẩm' ) ); ?> <span aria-hidden="true">→</span></a></div>
				<div class="fv-social-rail">
					<?php foreach ( array_slice( $real_home_products, 0, 5 ) as $edit_product ) : ?>
						<a class="fv-social-card" href="<?php echo esc_url( get_permalink( $edit_product->get_id() ) ); ?>">
							<?php echo $edit_product->get_image( 'woocommerce_single', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
							<span><?php echo esc_html( $edit_product->get_name() ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="fv-closing fv-reveal fv-reveal-up" aria-labelledby="fv-closing-title"><div class="fv-wrap"><span class="fv-eyebrow"><?php echo esc_html( furniva_t( 'Make yourself at home', 'Chào mừng bạn về nhà' ) ); ?></span><h2 id="fv-closing-title"><?php echo esc_html( furniva_t( 'Find the pieces that feel like yours.', 'Tìm thấy những món đồ thật sự thuộc về bạn.' ) ); ?></h2><p><?php echo esc_html( furniva_t( 'Explore the collection or talk with our team about finding the right fit.', 'Khám phá bộ sưu tập hoặc trò chuyện cùng chúng tôi để tìm lựa chọn phù hợp.' ) ); ?></p><div><a class="fv-button fv-button--primary" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( furniva_t( 'Shop all products', 'Xem tất cả sản phẩm' ) ); ?></a><a class="fv-button fv-button--secondary" href="<?php echo esc_url( $contact_url ); ?>"><?php echo esc_html( furniva_t( 'Talk to our team', 'Liên hệ với chúng tôi' ) ); ?></a></div></div></section>
	</main>
</div>

<?php get_footer(); ?>
