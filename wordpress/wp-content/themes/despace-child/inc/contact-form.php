<?php
/**
 * DeSpace Contact Form Handler & Showroom Configuration
 *
 * Implements configurable contact settings, server-side validation,
 * nonce verification, honeypot spam protection, rate limiting,
 * safe email dispatch, and inquiry logging.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get verified contact & showroom information with fallback defaults.
 *
 * @return array
 */
function despace_get_contact_info() {
	$defaults = array(
		'address'       => 'Tòa nhà DeSpace, Số 123 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội (Mô phỏng đồ án)',
		'address_note'  => 'Không gian trưng bày trải nghiệm nội thất và tư vấn thiết kế 3D trực tiếp (Dữ liệu mô phỏng đồ án).',
		'hotline'       => '(024) 0000 0000 (Mô phỏng đồ án)',
		'hotline_note'  => 'Tổng đài tư vấn kích thước và phối cảnh 3D (Mô phỏng đồ án).',
		'email'         => 'contact@despace.test',
		'email_note'    => 'Gửi mặt bằng căn hộ, bản vẽ CAD để nhận dự toán trong 24h (Mô phỏng đồ án).',
		'hours'         => '08:30 – 21:30 (Thứ Hai – Chủ Nhật)',
		'hours_note'    => 'Mở cửa tất cả các ngày trong tuần, kể cả ngày lễ.',
		'parking_note'  => 'Bãi đỗ xe ô tô & xe máy an ninh rộng rãi miễn phí, đồ uống tiếp đón cao cấp, iPad Pro trải nghiệm AR 3D thực tế ảo.',
		'founder_title' => 'Sáng lập & Giám đốc thiết kế',
		'founder_desc'  => 'Tư vấn trực tiếp không gian sống & hoàn thiện nội thất cao cấp (Mô phỏng đồ án)',
		'zalo_url'      => '',
		'facebook_url'  => '',
		'maps_url'      => 'https://maps.google.com/?q=Hà+Nội',
	);

	$saved = get_option( 'despace_contact_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Handle AJAX contact form submission
 */
function despace_ajax_contact_submit() {
	// 1. Nonce verification
	$nonce = $_POST['nonce'] ?? '';
	if ( ! wp_verify_nonce( $nonce, 'despace_contact_nonce' ) ) {
		wp_send_json_error( array(
			'message' => furniva_t( 'Your session has expired. Please refresh the page and try again.', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.' ),
		), 403 );
	}

	// 2. Honeypot check (anti-spam bot trap)
	if ( ! empty( $_POST['despace_hp'] ) ) {
		// Bot detected: respond with generic success silently
		wp_send_json_success( array(
			'status'  => 'silent',
			'message' => furniva_t( 'Your inquiry has been received.', 'Yêu cầu của bạn đã được ghi nhận.' ),
		) );
	}

	// 3. Rate limiting (per IP transient: 45s cooldown)
	$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' );
	$rate_limit_key = 'despace_contact_rl_' . md5( $ip );
	if ( get_transient( $rate_limit_key ) ) {
		wp_send_json_error( array(
			'message' => furniva_t( 'You submitted an inquiry recently. Please wait a moment before sending another.', 'Bạn vừa gửi yêu cầu gần đây. Vui lòng đợi 1 phút trước khi gửi lại.' ),
		), 429 );
	}

	// 4. Input sanitization & validation
	$name    = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
	$space   = sanitize_text_field( wp_unslash( $_POST['space_type'] ?? '' ) );
	$service = sanitize_text_field( wp_unslash( $_POST['service_type'] ?? '' ) );
	$visit   = sanitize_text_field( wp_unslash( $_POST['visit_time'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ?? '' ) );

	$errors = array();

	if ( mb_strlen( trim( $name ) ) < 2 ) {
		$errors[] = furniva_t( 'Please enter your full name (minimum 2 characters).', 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).' );
	}

	$digits = preg_replace( '/\D/', '', $phone );
	if ( strlen( $digits ) < 9 || strlen( $digits ) > 12 ) {
		$errors[] = furniva_t( 'Please enter a valid phone number (9 to 11 digits).', 'Vui lòng nhập số điện thoại hợp lệ (9 – 11 chữ số).' );
	}

	if ( ! empty( $_POST['contact_email'] ) && ! is_email( $email ) ) {
		$errors[] = furniva_t( 'Invalid email address format.', 'Địa chỉ email không đúng định dạng.' );
	}

	if ( mb_strlen( trim( $message ) ) < 3 ) {
		$errors[] = furniva_t( 'Please provide a message or details regarding your consultation request.', 'Vui lòng nhập lời nhắn hoặc ghi chú yêu cầu tư vấn.' );
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( array(
			'message' => implode( ' ', $errors ),
		), 400 );
	}

	// 5. Store inquiry securely in WordPress DB (no lead is ever lost)
	$inquiries = get_option( 'despace_contact_inquiries', array() );
	if ( ! is_array( $inquiries ) ) {
		$inquiries = array();
	}
	$entry = array(
		'id'         => uniqid( 'inq_' ),
		'created_at' => current_time( 'mysql' ),
		'name'       => $name,
		'phone'      => $phone,
		'email'      => $email,
		'space'      => $space,
		'service'    => $service,
		'visit'      => $visit,
		'message'    => $message,
		'ip'         => $ip,
	);
	array_unshift( $inquiries, $entry );
	if ( count( $inquiries ) > 100 ) {
		$inquiries = array_slice( $inquiries, 0, 100 );
	}
	update_option( 'despace_contact_inquiries', $inquiries, false );

	// 6. Safe email notification dispatch
	$info = despace_get_contact_info();
	$to = get_option( 'admin_email' );
	$subject = '[DeSpace] Đăng ký tư vấn: ' . $name . ' (' . $phone . ')';
	$body = "YÊU CẦU ĐẶT LỊCH TƯ VẤN SHOWROOM / THIẾT KẾ DESPACE\n";
	$body .= "--------------------------------------------------\n";
	$body .= "Họ và tên:        " . $name . "\n";
	$body .= "Số điện thoại:    " . $phone . "\n";
	$body .= "Email:            " . ( $email ? $email : '(Chưa cung cấp)' ) . "\n";
	$body .= "Loại không gian:  " . ( $space ? $space : '(Chưa chọn)' ) . "\n";
	$body .= "Dịch vụ:          " . ( $service ? $service : '(Mặc định)' ) . "\n";
	$body .= "Thời gian hẹn:    " . ( $visit ? $visit : '(Chưa đặt)' ) . "\n";
	$body .= "Thời gian gửi:    " . current_time( 'd/m/Y H:i:s' ) . "\n";
	$body .= "\nLời nhắn chi tiết:\n" . $message . "\n";

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $email ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}

	$mail_sent = false;
	try {
		$mail_sent = @wp_mail( $to, $subject, $body, $headers );
	} catch ( Throwable $t ) {
		$mail_sent = false;
	}

	// Set rate limit cooldown for 45 seconds
	set_transient( $rate_limit_key, 1, 45 );

	// 7. Clear, user-friendly response without false claims or leaking internal errors
	if ( $mail_sent ) {
		wp_send_json_success( array(
			'status'  => 'sent',
			'message' => furniva_t( 'Thank you! Your consultation request has been sent successfully. The DeSpace interior team will contact you within 24 working hours.', 'Cảm ơn bạn! Yêu cầu tư vấn đã được gửi thành công. Đội ngũ thiết kế DeSpace sẽ liên hệ với bạn trong vòng 24 giờ làm việc.' ),
		) );
	} else {
		// Mail transport failed or not configured locally: inform customer clearly that inquiry is logged in the system
		wp_send_json_success( array(
			'status'  => 'recorded',
			'message' => sprintf(
				furniva_t(
					'Thank you! Your consultation request has been recorded in the DeSpace system. Our design specialist will reach out to you via phone/Zalo (%s) as soon as possible.',
					'Cảm ơn bạn! Yêu cầu tư vấn của bạn đã được ghi nhận trên hệ thống DeSpace. Chuyên viên thiết kế sẽ chủ động liên hệ lại qua số điện thoại/Zalo (%s) trong thời gian sớm nhất.'
				),
				esc_html( $phone )
			),
		) );
	}
}
add_action( 'wp_ajax_despace_contact_submit', 'despace_ajax_contact_submit' );
add_action( 'wp_ajax_nopriv_despace_contact_submit', 'despace_ajax_contact_submit' );

/**
 * Render contact form markup
 *
 * @return string HTML
 */
function despace_render_contact_form_html() {
	$info = despace_get_contact_info();
	$nonce = wp_create_nonce( 'despace_contact_nonce' );

	ob_start();
	?>
	<form id="despace-contact-form" class="f-contact-form-ajax" method="POST" novalidate>
		<input type="hidden" name="action" value="despace_contact_submit" />
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />

		<!-- Anti-spam Honeypot Trap (invisible to real human users) -->
		<div style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;" aria-hidden="true">
			<label for="despace_hp">Bỏ trống trường này nếu bạn là người dùng thật:</label>
			<input type="text" id="despace_hp" name="despace_hp" tabindex="-1" autocomplete="off" />
		</div>

		<div class="ds-form-row">
			<label class="f-contact-field">
				<span>Họ và tên <strong class="req" style="color:#BF5B50;">*</strong></span>
				<input type="text" name="contact_name" id="contact_name" required placeholder="Ví dụ: Nguyễn Văn A" autocomplete="name" />
			</label>
			<label class="f-contact-field">
				<span>Số điện thoại <strong class="req" style="color:#BF5B50;">*</strong></span>
				<input type="tel" name="contact_phone" id="contact_phone" required placeholder="Nhập số điện thoại liên hệ" autocomplete="tel" />
			</label>
		</div>

		<div class="ds-form-row">
			<label class="f-contact-field">
				<span>Email nhận phản hồi</span>
				<input type="email" name="contact_email" id="contact_email" placeholder="example@domain.com" autocomplete="email" />
			</label>
			<label class="f-contact-field">
				<span>Loại hình không gian</span>
				<select name="space_type" id="space_type" class="ds-select">
					<option value="">-- Chọn loại hình không gian --</option>
					<option value="Căn hộ chung cư">Căn hộ chung cư</option>
					<option value="Biệt thự / Liền kề">Biệt thự / Liền kề</option>
					<option value="Nhà phố">Nhà phố</option>
					<option value="Showroom / Văn phòng">Showroom / Văn phòng</option>
					<option value="Mua lẻ sản phẩm nội thất">Mua lẻ sản phẩm nội thất</option>
				</select>
			</label>
		</div>

		<div class="ds-form-row">
			<label class="f-contact-field">
				<span>Dịch vụ mong muốn</span>
				<select name="service_type" id="service_type" class="ds-select">
					<option value="Trải nghiệm Showroom & 3D/AR">Trải nghiệm Showroom & 3D/AR</option>
					<option value="Khảo sát & đo đạc tại nhà">Khảo sát & đo đạc tại nhà</option>
					<option value="Đóng đồ theo kích thước thiết kế riêng">Đóng đồ theo kích thước thiết kế riêng</option>
					<option value="Thiết kế & thi công nội thất trọn gói">Thiết kế & thi công nội thất trọn gói</option>
				</select>
			</label>
			<label class="f-contact-field">
				<span>Thời gian dự kiến ghé thăm</span>
				<input type="text" name="visit_time" id="visit_time" placeholder="Ví dụ: Thứ 7 tuần này, 10:00 sáng" />
			</label>
		</div>

		<label class="f-contact-field">
			<span>Lời nhắn & yêu cầu chi tiết <strong class="req" style="color:#BF5B50;">*</strong></span>
			<textarea name="contact_message" id="contact_message" rows="4" required placeholder="Chia sẻ với chúng tôi về nhu cầu không gian sống, kích thước hoặc mẫu nội thất bạn quan tâm..."></textarea>
		</label>

		<div id="despace-contact-status" class="ds-contact-status" role="status" aria-live="polite" style="display:none;margin-top:12px;padding:12px 16px;border-radius:6px;font-size:13px;line-height:1.6;"></div>

		<button class="f-btn f-btn--primary ds-submit-btn" type="submit" id="despace-contact-submit-btn">
			<span class="btn-text">Gửi Đăng Ký Tư Vấn Trực Tiếp</span>
		</button>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'despace_contact_form', 'despace_render_contact_form_html' );

/**
 * Register admin menu to view/configure showroom & contact info and view leads.
 */
add_action( 'admin_menu', function() {
	add_options_page(
		'Cấu hình Showroom & Liên hệ',
		'DeSpace Showroom',
		'manage_options',
		'despace-contact-settings',
		'despace_render_contact_admin_page'
	);
} );

/**
 * Render admin options page
 */
function despace_render_contact_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$info = despace_get_contact_info();
	$message = '';

	if ( isset( $_POST['despace_save_contact'] ) && check_admin_referer( 'despace_save_contact_action' ) ) {
		$new_settings = array(
			'address'      => sanitize_text_field( $_POST['address'] ?? '' ),
			'address_note' => sanitize_text_field( $_POST['address_note'] ?? '' ),
			'hotline'      => sanitize_text_field( $_POST['hotline'] ?? '' ),
			'hotline_note' => sanitize_text_field( $_POST['hotline_note'] ?? '' ),
			'email'        => sanitize_email( $_POST['email'] ?? '' ),
			'email_note'   => sanitize_text_field( $_POST['email_note'] ?? '' ),
			'hours'        => sanitize_text_field( $_POST['hours'] ?? '' ),
			'hours_note'   => sanitize_text_field( $_POST['hours_note'] ?? '' ),
			'parking_note' => sanitize_text_field( $_POST['parking_note'] ?? '' ),
		);
		update_option( 'despace_contact_settings', $new_settings );
		$info = despace_get_contact_info();
		$message = 'Cập nhật cấu hình showroom thành công!';
	}

	$inquiries = get_option( 'despace_contact_inquiries', array() );
	?>
	<div class="wrap">
		<h1>Cấu hình Showroom & Thông tin Liên hệ DeSpace</h1>
		<?php if ( $message ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endif; ?>

		<div style="display:flex;gap:30px;flex-wrap:wrap;margin-top:20px;">
			<div style="flex:1;min-width:320px;max-width:600px;background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:6px;">
				<h2>Thông tin Showroom DeSpace</h2>
				<form method="POST">
					<?php wp_nonce_field( 'despace_save_contact_action' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="address">Địa chỉ Showroom</label></th>
							<td><input name="address" type="text" id="address" value="<?php echo esc_attr( $info['address'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hotline">Hotline</label></th>
							<td><input name="hotline" type="text" id="hotline" value="<?php echo esc_attr( $info['hotline'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="email">Email</label></th>
							<td><input name="email" type="email" id="email" value="<?php echo esc_attr( $info['email'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hours">Giờ mở cửa</label></th>
							<td><input name="hours" type="text" id="hours" value="<?php echo esc_attr( $info['hours'] ); ?>" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="parking_note">Tiện ích đón tiếp</label></th>
							<td><textarea name="parking_note" id="parking_note" rows="3" class="large-text"><?php echo esc_textarea( $info['parking_note'] ); ?></textarea></td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="despace_save_contact" class="button button-primary" value="Lưu Cấu Hình" />
					</p>
				</form>
			</div>

			<div style="flex:1;min-width:320px;background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:6px;">
				<h2>Danh sách đăng ký tư vấn gần đây (<?php echo count( (array) $inquiries ); ?>)</h2>
				<?php if ( empty( $inquiries ) ) : ?>
					<p>Chưa có yêu cầu đăng ký nào.</p>
				<?php else : ?>
					<div style="max-height:450px;overflow-y:auto;">
						<table class="widefat fixed striped">
							<thead>
								<tr>
									<th>Thời gian</th>
									<th>Họ tên</th>
									<th>Số ĐT</th>
									<th>Dịch vụ</th>
									<th>Lời nhắn</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( (array) $inquiries, 0, 20 ) as $inq ) : ?>
									<tr>
										<td><small><?php echo esc_html( $inq['created_at'] ?? '' ); ?></small></td>
										<td><strong><?php echo esc_html( $inq['name'] ?? '' ); ?></strong></td>
										<td><?php echo esc_html( $inq['phone'] ?? '' ); ?></td>
										<td><small><?php echo esc_html( $inq['service'] ?? '' ); ?></small></td>
										<td><small><?php echo esc_html( $inq['message'] ?? '' ); ?></small></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Display informational policy links at WooCommerce Checkout (both Classic and Blocks compatible)
 */
function despace_render_checkout_policy_notice() {
	static $rendered = false;
	if ( $rendered ) {
		return;
	}
	$rendered     = true;
	$shipping_url = furniva_url( '/chinh-sach-giao-hang/' );
	$returns_url  = furniva_url( '/chinh-sach-doi-tra-bao-hanh/' );
	$privacy_url  = furniva_url( '/chinh-sach-bao-mat/' );
	$terms_url    = furniva_url( '/dieu-khoan-mua-ban/' );
	?>
	<div class="ds-checkout-policy-notice" style="margin: 20px 0; padding: 16px 20px; background: #faf7f2; border: 1px solid #e7e0d7; border-radius: 8px; font-size: 13px; color: #69645D; line-height: 1.65;">
		<span style="display:block; font-weight: 600; color: #1c1917; margin-bottom: 6px; font-size: 13.5px;">
			✦ <?php echo esc_html( furniva_t( 'DeSpace Service Commitments & Customer Rights:', 'Cam kết dịch vụ & Quyền lợi khách hàng DeSpace:' ) ); ?>
		</span>
		<span>
			<?php echo esc_html( furniva_t( 'Your purchase is protected by our', 'Đơn hàng được bảo hộ và hỗ trợ bởi' ) ); ?>
			<a href="<?php echo esc_url( $shipping_url ); ?>" target="_blank" rel="noopener" style="color: #9A6745; text-decoration: underline; font-weight: 500;"><?php echo esc_html( furniva_t( 'Shipping & Installation Policy', 'Chính sách giao hàng & lắp đặt' ) ); ?></a>,
			<a href="<?php echo esc_url( $returns_url ); ?>" target="_blank" rel="noopener" style="color: #9A6745; text-decoration: underline; font-weight: 500;"><?php echo esc_html( furniva_t( 'Returns & Warranty Policy', 'Chính sách đổi trả & bảo hành' ) ); ?></a>,
			<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener" style="color: #9A6745; text-decoration: underline; font-weight: 500;"><?php echo esc_html( furniva_t( 'Privacy Policy', 'Chính sách bảo mật' ) ); ?></a>,
			<?php echo esc_html( furniva_t( 'and', 'và' ) ); ?>
			<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener" style="color: #9A6745; text-decoration: underline; font-weight: 500;"><?php echo esc_html( furniva_t( 'Terms of Service', 'Điều khoản mua bán' ) ); ?></a>.
		</span>
		<div style="margin-top: 8px; font-size: 12px; color: #8c827a; font-style: italic;">
			<?php echo esc_html( furniva_t( 'Note: Educational project simulation for DeSpace luxury furniture store.', 'Lưu ý: Nội dung quy chuẩn phục vụ đồ án học tập/thực nghiệm DeSpace.' ) ); ?>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_review_order_before_submit', 'despace_render_checkout_policy_notice', 9 );

/**
 * Automatically mark policy and informational pages as customized by an administrator
 * when saved in the WordPress admin editor.
 */
add_action( 'save_post_page', function( $post_id, $post, $update ) {
	if ( ! $update || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( is_admin() && current_user_can( 'edit_page', $post_id ) ) {
		update_post_meta( $post_id, '_despace_admin_edited', '1' );
	}
}, 10, 3 );

/**
 * Ensure canonical slugs and published status for informational & policy pages.
 * Safe for administrator customizations: will NEVER overwrite post content if an admin has edited it in WordPress.
 *
 * @param bool $force_overwrite Optional. Set to true only if explicitly forcing a fresh factory restore. Default false.
 */
function despace_ensure_policy_pages( $force_overwrite = false ) {
	global $wpdb;

	$data_file = get_stylesheet_directory() . '/inc/data/policy-pages-data.php';
	if ( ! file_exists( $data_file ) ) {
		return;
	}

	$canonical_pages = require $data_file;
	if ( ! is_array( $canonical_pages ) ) {
		return;
	}

	foreach ( $canonical_pages as $id => $data ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_name, post_title, post_content, post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );
		if ( ! $row ) {
			// Page is completely missing from database: create it safely
			$wpdb->insert(
				$wpdb->posts,
				array(
					'ID'           => $id,
					'post_title'   => $data['title'],
					'post_name'    => $data['slug'],
					'post_content' => $data['content'],
					'post_status'  => $data['status'],
					'post_type'    => 'page',
					'post_date'    => current_time( 'mysql' ),
					'post_date_gmt'=> current_time( 'mysql', 1 ),
					'comment_status' => 'closed',
					'ping_status'  => 'closed',
				)
			);
			clean_post_cache( $id );
			continue;
		}

		$is_admin_edited       = ( '1' === (string) get_post_meta( $id, '_despace_admin_edited', true ) );
		$plain_text            = trim( wp_strip_all_tags( (string) $row->post_content ) );
		$has_content           = ! empty( $plain_text );
		$is_stub               = mb_strlen( $plain_text ) < 200;
		$is_wc_sample          = ( false !== strpos( (string) $row->post_content, 'This is a sample page.' ) );
		$has_legacy_unverified   = ( false !== strpos( (string) $row->post_content, 'Học viện Nông nghiệp' ) || false !== strpos( (string) $row->post_content, 'Hotline Tư Vấn 24/7' ) );
		$has_unverified_warranty = ( false !== stripos( (string) $row->post_content, '3 năm' ) || false !== stripos( (string) $row->post_content, '5 năm' ) || false !== stripos( (string) $row->post_content, '3-year' ) || false !== stripos( (string) $row->post_content, '5-year' ) );
		$has_broken_contact_form = ( 12 === (int) $id && false !== strpos( (string) $row->post_content, 'ds-booking-form-box' ) && false === strpos( (string) $row->post_content, '[despace_contact_form' ) && false === stripos( (string) $row->post_content, '<form' ) );
		$has_mojibake            = ( false !== strpos( $row->post_content, 'ChÃ' ) || false !== strpos( $row->post_content, 'Ã¡' ) );

		// Always ensure canonical slug and post_type = page
		$update_data = array(
			'post_name' => $data['slug'],
			'post_type' => 'page',
		);

		// Do not overwrite post_status if administrator customized or drafted the page in WP Admin
		if ( ! $is_admin_edited ) {
			$update_data['post_status'] = $data['status'];
		}

		// Only restore template content if forcing, or if content is empty, stub, WC sample, legacy unverified seed, unverified warranty duration, broken stripped form, or mojibake
		if ( ! $is_admin_edited && ( $force_overwrite || ! $has_content || $is_stub || $is_wc_sample || $has_legacy_unverified || $has_unverified_warranty || $has_broken_contact_form || $has_mojibake ) ) {
			$update_data['post_title']   = $data['title'];
			$update_data['post_content'] = $data['content'];
		}

		$wpdb->update(
			$wpdb->posts,
			$update_data,
			array( 'ID' => $id )
		);
		clean_post_cache( $id );
	}
}


