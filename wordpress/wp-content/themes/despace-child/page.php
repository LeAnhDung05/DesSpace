<?php
/**
 * DeSpace static page template.
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
		if ( in_array( $page_slug, array( 'about', 've-despace', 've-chung-toi' ), true ) ) :
			$story_img_uri = get_stylesheet_directory_uri() . '/assets/images/room-living.jpg';
			?>
			<section class="f-about-metrics f-wrap" aria-label="<?php echo esc_attr( furniva_t( 'DeSpace in numbers', 'DeSpace qua những con số' ) ); ?>">
				<div class="f-about-metric">
					<strong>2016</strong>
					<span><?php echo esc_html( furniva_t( 'Founded in Hanoi Atelier', 'Thành lập Studio tại Hà Nội' ) ); ?></span>
					<p><?php echo esc_html( furniva_t( 'A design studio built around honest proportions and calm architectural timber.', 'Từ xưởng thiết kế nhỏ theo đuổi tỷ lệ chuẩn mực và ngôn ngữ nội thất cát ấm.' ) ); ?></p>
				</div>
				<div class="f-about-metric">
					<strong>1:1</strong>
					<span><?php echo esc_html( furniva_t( 'True-scale 3D & AR Models', 'Mô hình 3D & AR tỷ lệ thực' ) ); ?></span>
					<p><?php echo esc_html( furniva_t( 'Every cabinet, shelf and drawer is digitized with exact millimeter clearance.', 'Mỗi hệ tủ, giá sách và ngăn kéo đều được số hóa chuẩn xác từng milimet.' ) ); ?></p>
				</div>
				<div class="f-about-metric">
					<strong><?php echo esc_html( function_exists( 'despace_get_3d_product_ids' ) ? (string) count( despace_get_3d_product_ids() ) : '40' ); ?></strong>
					<span><?php echo esc_html( furniva_t( 'Curated Living Modules', 'Thiết kế nội thất lưu trữ' ) ); ?></span>
					<p><?php echo esc_html( furniva_t( 'Tailored across shoe cabinets, dressers, bookcases and modular kitchens.', 'Phủ trọn lối vào, phòng khách, phòng ngủ và khu bếp căn hộ hiện đại.' ) ); ?></p>
				</div>
				<div class="f-about-metric">
					<strong><?php echo esc_html( furniva_t( '100%', 'Đồng kiểm' ) ); ?></strong>
					<span><?php echo esc_html( furniva_t( 'White-Glove Delivery Inspection', 'Kiểm tra kết cấu khi bàn giao' ) ); ?></span>
					<p><?php echo esc_html( furniva_t( 'Direct delivery, assembly check and structural care per DeSpace policy.', 'Hỗ trợ đồng kiểm hoàn thiện, căn chỉnh phụ kiện theo chính sách DeSpace.' ) ); ?></p>
				</div>
			</section>

			<section class="f-about-story f-wrap" aria-labelledby="f-about-story-title">
				<div class="f-about-story-media">
					<img src="<?php echo esc_url( $story_img_uri ); ?>" alt="<?php echo esc_attr( furniva_t( 'DeSpace Warm Sand Living Room Studio', 'Không gian mẫu Warm Sand của DeSpace' ) ); ?>" loading="lazy" />
				</div>
				<div class="f-about-story-body">
					<span class="f-kicker"><?php echo esc_html( furniva_t( 'Craft & Digital Twin', 'Quy trình Chế tác & 3D/AR' ) ); ?></span>
					<h2 id="f-about-story-title"><?php echo esc_html( furniva_t( 'From workshop joinery to previewing inside your room.', 'Từ bản vẽ mộng gỗ đến trải nghiệm đặt thử ngay tại phòng khách.' ) ); ?></h2>
					<p class="f-about-story-lead"><?php echo esc_html( furniva_t( 'Buying storage furniture often fails between showroom scale and apartment reality. We built our own 3D & WebAR pipeline so you inspect door swings, wood grain and sunlight before ordering.', 'Nội thất tốt không chỉ đẹp ở phòng trưng bày mà phải vừa vặn khi đặt sát bức tường nhà bạn. Tại DeSpace, mỗi mẫu mới đều đi qua bốn bước kiểm định khép kín.' ) ); ?></p>
					<ol class="f-about-steps">
						<li>
							<span>01</span>
							<div>
								<h3><?php echo esc_html( furniva_t( 'Urban ergonomics study', 'Đo đạc công thái học căn hộ Việt' ) ); ?></h3>
								<p><?php echo esc_html( furniva_t( 'Calibrated heights (75–180 cm) and slim hallway depths (32–36 cm) for compact corridors and natural light.', 'Tối ưu chiều sâu lối đi 32–36 cm và tầm với thao tác 75–180 cm phù hợp vóc dáng người Việt và mặt bằng chung cư.' ) ); ?></p>
							</div>
						</li>
						<li>
							<span>02</span>
							<div>
								<h3><?php echo esc_html( furniva_t( 'Precision CNC & hand-rubbed edges', 'Gia công CNC chính xác & bo cạnh thủ công' ) ); ?></h3>
								<p><?php echo esc_html( furniva_t( 'Moisture-resistant cores sealed cleanly along every edge to withstand northern humid seasons.', 'Ván lõi xanh chống ẩm ép áp suất cao, xử lý nẹp cạnh liền mạch chống bong tróc trong mùa nồm ẩm.' ) ); ?></p>
							</div>
						</li>
						<li>
							<span>03</span>
							<div>
								<h3><?php echo esc_html( furniva_t( 'Articulated 3D PBR & AR export', 'Số hóa 3D PBR đóng mở từng cánh tủ' ) ); ?></h3>
								<p><?php echo esc_html( furniva_t( 'Click-to-open cabinet doors and drawers in 360° viewer plus Quick Look / WebXR placement on mobile.', 'Tương tác mở từng cánh tủ, kéo từng ngăn trong trình xem 360° và đặt thử AR 1:1 trực tiếp bằng camera điện thoại.' ) ); ?></p>
							</div>
						</li>
						<li>
							<span>04</span>
							<div>
								<h3><?php echo esc_html( furniva_t( 'White-glove delivery & assembly', 'Giao lắp hoàn chỉnh & cân chỉnh bản lề' ) ); ?></h3>
								<p><?php echo esc_html( furniva_t( 'Scheduled in-room assembly, laser leveling and packaging haul-away by our in-house craftsmen.', 'Đội ngũ kỹ thuật viên DeSpace vận chuyển, cân bằng thước thủy tại nhà và thu dọn bao bì sạch sẽ.' ) ); ?></p>
							</div>
						</li>
					</ol>
					<div class="f-about-actions">
						<a class="f-btn f-btn--primary" href="<?php echo esc_url( home_url( '/ar-room-visualizer/' ) ); ?>"><?php echo esc_html( furniva_t( 'Launch AR Visualizer', 'Mở AR Room Visualizer' ) ); ?></a>
						<a class="f-btn f-btn--outline" href="<?php echo esc_url( home_url( '/cua-hang/' ) ); ?>"><?php echo esc_html( furniva_t( 'Explore 3D Catalogue', 'Khám phá Cửa hàng 3D' ) ); ?></a>
					</div>
				</div>
			</section>

			<section class="f-about-materials f-wrap" aria-labelledby="f-about-mat-title">
				<div class="f-about-mat-head">
					<span class="f-kicker"><?php echo esc_html( furniva_t( 'Material Library', 'Thư viện Vật liệu' ) ); ?></span>
					<h2 id="f-about-mat-title"><?php echo esc_html( furniva_t( 'Honest textures selected for longevity.', 'Bốn lớp vật liệu trung thực bền bỉ theo năm tháng.' ) ); ?></h2>
					<p><?php echo esc_html( furniva_t( 'We balance matte wood grains, breathable fabrics and architectural metal hardware so pieces patina gracefully without visual clutter.', 'Sự kết hợp giữa vân gỗ sần ấm, sợi vải trung tính và kim loại sơn mờ tạo nên tổng thể hài hòa dưới mọi góc sáng.' ) ); ?></p>
				</div>
				<div class="f-about-mat-grid">
					<article class="f-about-mat-card">
						<span class="f-mat-swatch f-mat-swatch--oak" aria-hidden="true"></span>
						<span class="f-mat-tag">FSC® Certified · E1</span>
						<h3><?php echo esc_html( furniva_t( 'Linear Walnut & Sonoma Oak', 'Vân gỗ Óc Chó Linear & Sồi Sonoma' ) ); ?></h3>
						<p><?php echo esc_html( furniva_t( 'Low-formaldehyde engineered cores dressed in synchronized linear wood grain with matte anti-scratch coating.', 'Cốt gỗ đạt chuẩn phát thải thấp E1 phủ bề mặt vân sần đồng bộ, chống trầy xước và hạn chế bám dấu vân tay.' ) ); ?></p>
					</article>
					<article class="f-about-mat-card">
						<span class="f-mat-swatch f-mat-swatch--linen" aria-hidden="true"></span>
						<span class="f-mat-tag">OEKO-TEX® Standard 100</span>
						<h3><?php echo esc_html( furniva_t( 'Woven Linen & Bouclé', 'Vải Linen dệt & Bouclé cát ấm' ) ); ?></h3>
						<p><?php echo esc_html( furniva_t( 'Breathable natural fibers certified free from harmful substances, softened for tropical humidity and family homes.', 'Chất liệu dệt thoáng khí, an toàn cho làn da nhạy cảm và thân thiện với trẻ nhỏ trong căn hộ gia đình.' ) ); ?></p>
					</article>
					<article class="f-about-mat-card">
						<span class="f-mat-swatch f-mat-swatch--steel" aria-hidden="true"></span>
						<span class="f-mat-tag">Recycled Steel · Matte Black</span>
						<h3><?php echo esc_html( furniva_t( 'Powder-coated Steel Frames', 'Thép sơn tĩnh điện nhám' ) ); ?></h3>
						<p><?php echo esc_html( furniva_t( 'High-rigidity base legs and slim Linear pulls baked at 200°C for rust resistance and silent visual contrast.', 'Hệ khung chân và tay nắm thanh mảnh được xử lý sơn tĩnh điện hai lớp chống oxy hóa và bền màu dài lâu.' ) ); ?></p>
					</article>
					<article class="f-about-mat-card">
						<span class="f-mat-swatch f-mat-swatch--brass" aria-hidden="true"></span>
						<span class="f-mat-tag">30,000+ Open Cycles</span>
						<h3><?php echo esc_html( furniva_t( 'Soft-close Dampening Hardware', 'Bản lề & ray giảm chấn 30.000 chu kỳ' ) ); ?></h3>
						<p><?php echo esc_html( furniva_t( 'Smooth hydraulic hinges and full-extension steel ball-bearing runners keep cabinet doors whisper-quiet.', 'Cơ cấu đóng mở êm ái triệt tiêu tiếng va đập ban đêm, đồng bộ cùng mô phỏng đóng mở trên trình xem 3D.' ) ); ?></p>
					</article>
				</div>
			</section>

			<section class="f-about-values f-wrap" aria-labelledby="f-about-values-title">
				<div class="f-about-values-head">
				<span class="f-kicker"><?php echo esc_html( furniva_t( 'A considered approach', 'Triết lý thiết kế' ) ); ?></span>
				<h2 id="f-about-values-title"><?php echo esc_html( furniva_t( 'Made for the way life unfolds.', 'Được tạo nên cho nhịp sống thường ngày.' ) ); ?></h2>
				</div>
				<div class="f-about-values-grid">
					<article><span>01</span><h3><?php echo esc_html( furniva_t( 'Studio DeSpace', 'Xưởng chế tác DeSpace' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'Every piece begins in our design studio, where proportion, comfort and everyday use shape the design.', 'Mỗi sản phẩm bắt đầu từ studio của chúng tôi, nơi tỷ lệ, sự thoải mái và nhu cầu sử dụng hằng ngày định hình thiết kế.' ) ); ?></p></article>
					<article><span>02</span><h3><?php echo esc_html( furniva_t( 'Materials with purpose', 'Chất liệu được chọn có chủ đích' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'FSC-certified woods, OEKO-TEX fabrics and recycled steel guide our material choices.', 'Gỗ đạt chứng nhận FSC, vải đạt chuẩn OEKO-TEX và thép tái chế là nền tảng cho lựa chọn vật liệu của chúng tôi.' ) ); ?></p></article>
					<article><span>03</span><h3><?php echo esc_html( furniva_t( 'Tested in real homes', 'Thử nghiệm trong không gian sống thực tế' ) ); ?></h3><p><?php echo esc_html( furniva_t( 'We prototype and refine each design in lived-in spaces before it joins the collection.', 'Chúng tôi làm mẫu và hoàn thiện từng thiết kế trong những không gian có người sử dụng trước khi đưa vào bộ sưu tập.' ) ); ?></p></article>
				</div>
			</section>

			<section class="f-about-showroom f-wrap" aria-labelledby="f-about-showroom-title">
				<div class="f-about-showroom-box">
					<div class="f-about-showroom-copy">
						<span class="f-kicker"><?php echo esc_html( furniva_t( 'Hanoi Flagship Studio', 'Showroom & Tư vấn Kiến trúc 1-1' ) ); ?></span>
						<h2 id="f-about-showroom-title"><?php echo esc_html( furniva_t( 'Touch real wood samples & review your floor plan in Gia Lam.', 'Trực tiếp chạm mẫu gỗ & đo phối cảnh mặt bằng tại Showroom Gia Lâm.' ) ); ?></h2>
						<p><?php echo esc_html( furniva_t( 'Bring your apartment layout or dimensions and sit with our design advisors to match finishes, storage capacity and AR previews tailored to your home.', 'Mang theo mặt bằng căn hộ hoặc kích thước hốc tường của bạn để cùng kiến trúc sư DeSpace lựa chọn chất liệu hoàn thiện và xem mô hình 3D phối cảnh riêng.' ) ); ?></p>
						<ul class="f-about-showroom-meta">
							<li><strong><?php echo esc_html( furniva_t( 'Showroom:', 'Showroom:' ) ); ?></strong> Số 20, ngách 21, ngõ 367 Ngô Xuân Quảng, Trâu Quỳ, Gia Lâm, Hà Nội</li>
							<li><strong><?php echo esc_html( furniva_t( 'Hours:', 'Giờ mở cửa:' ) ); ?></strong> 08:30 – 20:00 (<?php echo esc_html( furniva_t( 'Mon – Sun', 'Thứ 2 – Chủ nhật' ) ); ?>)</li>
							<li><strong>Hotline:</strong> <a href="tel:0332174590">0332 174 590</a> · <a href="mailto:vuquangdu03@gmail.com">vuquangdu03@gmail.com</a></li>
						</ul>
					</div>
					<div class="f-about-showroom-actions">
						<a class="f-btn f-btn--primary" href="<?php echo esc_url( home_url( '/lien-he/' ) ); ?>"><?php echo esc_html( furniva_t( 'Book a studio visit', 'Đặt lịch tham quan Studio' ) ); ?></a>
						<a class="f-btn f-btn--outline" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><?php echo esc_html( furniva_t( 'Read The Journal', 'Đọc Tạp chí thiết kế' ) ); ?></a>
					</div>
				</div>
			</section>
			<?php
		elseif ( in_array( $page_slug, array( 'contact', 'lien-he' ), true ) ) :
			$raw_contact_content = (string) get_post_field( 'post_content', get_the_ID() );
			if ( function_exists( 'despace_render_contact_form_html' ) && false === strpos( $raw_contact_content, '[despace_contact_form' ) && false === stripos( $raw_contact_content, '<form' ) ) :
				?>
				<section class="f-wrap f-contact-form-section" aria-labelledby="f-contact-form-heading" style="margin: 32px auto;">
					<h2 id="f-contact-form-heading" style="margin-bottom: 16px;"><?php echo esc_html( furniva_t( 'Send a consultation inquiry', 'Gửi yêu cầu tư vấn' ) ); ?></h2>
					<?php echo despace_render_contact_form_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
				<?php
			endif;
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
