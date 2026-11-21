<?php
/**
 * Dynamic Napas247 EMVCo VietQR Generator, Customer QR Payment Card,
 * SePay/Casso Auto-Settlement Webhook, and Admin 1-Click BACS Confirmation.
 *
 * Strictly reads configured bank accounts from WooCommerce's native BACS settings
 * (`woocommerce_bacs_accounts`) without hardcoding bank credentials, while keeping
 * `BacsInstructions` untouched for 100% M3 unit-test contract compatibility.
 *
 * @package DeSpace\CheckoutOrders\Payment
 */

namespace DeSpace\CheckoutOrders\Payment;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class VietQrPaymentExperience
 */
class VietQrPaymentExperience {

	private static bool $initialized = false;

	/**
	 * Standard Napas247 6-digit Bank BIN mapping for Vietnamese banks.
	 */
	private const BANK_BINS = array(
		'vietcombank' => '970436',
		'vcb'         => '970436',
		'techcombank' => '970407',
		'tcb'         => '970407',
		'mbbank'      => '970422',
		'mb'          => '970422',
		'quan doi'    => '970422',
		'acb'         => '970416',
		'a chau'      => '970416',
		'bidv'        => '970418',
		'vietinbank'  => '970415',
		'ctg'         => '970415',
		'icb'         => '970415',
		'vpbank'      => '970432',
		'tpbank'      => '970423',
		'sacombank'   => '970403',
		'stb'         => '970403',
		'vib'         => '970441',
		'hdbank'      => '970437',
		'agribank'    => '970405',
		'vbard'       => '970405',
		'ocb'         => '970448',
		'msb'         => '970426',
		'shb'         => '970443',
		'seabank'     => '970440',
		'lpbank'      => '970449',
		'eximbank'    => '970431',
	);

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// 1. Customer Thank-You & View-Order dynamic VietQR card
		add_action( 'woocommerce_thankyou_bacs', array( __CLASS__, 'render_customer_vietqr_card' ), 5 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'render_customer_vietqr_card' ), 5 );

		// 2. Admin 1-Click BACS Settlement Meta Box & AJAX Handler
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_admin_bacs_metabox' ), 20, 2 );
		add_action( 'wp_ajax_despace_admin_confirm_bacs', array( __CLASS__, 'ajax_admin_confirm_bacs' ) );

		// 3. REST Endpoints: Live Payment Status Polling & SePay/Casso Webhook
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Resolve 6-digit Napas BIN from bank name, BIC, or sort_code.
	 */
	public static function resolve_bank_bin( array $account ): string {
		foreach ( array( 'bic', 'sort_code' ) as $field ) {
			$raw = preg_replace( '/\D/', '', (string) ( $account[ $field ] ?? '' ) );
			if ( is_string( $raw ) && 6 === strlen( $raw ) && '9704' === substr( $raw, 0, 4 ) ) {
				return $raw;
			}
		}

		$bank_name = strtolower( trim( (string) ( $account['bank_name'] ?? '' ) ) );
		if ( function_exists( 'remove_accents' ) ) {
			$bank_name = strtolower( remove_accents( $bank_name ) );
		}

		foreach ( self::BANK_BINS as $keyword => $bin ) {
			if ( false !== strpos( $bank_name, $keyword ) ) {
				return $bin;
			}
		}

		return '';
	}

	/**
	 * Build standards-compliant EMVCo Napas247 Dynamic VietQR payload with CRC-16/CCITT-FALSE.
	 */
	public static function build_emvco_payload( string $bin, string $account_number, int $amount_vnd, string $memo ): string {
		$bin            = preg_replace( '/\D/', '', $bin );
		$account_number = preg_replace( '/[^A-Za-z0-9]/', '', $account_number );
		$clean_memo     = preg_replace( '/[^A-Za-z0-9 \-]/', '', $memo );
		$clean_memo     = substr( trim( (string) $clean_memo ), 0, 25 );

		$tlv = static function( string $id, string $value ): string {
			$len = str_pad( (string) strlen( $value ), 2, '0', STR_PAD_LEFT );
			return $id . $len . $value;
		};

		$beneficiary = $tlv( '00', $bin ) . $tlv( '01', $account_number );
		$tag38       = $tlv( '00', 'A000000727' ) . $tlv( '01', $beneficiary ) . $tlv( '02', 'QRIBFTTA' );

		$payload  = $tlv( '00', '01' );
		$payload .= $tlv( '01', $amount_vnd > 0 ? '12' : '11' );
		$payload .= $tlv( '38', $tag38 );
		$payload .= $tlv( '53', '704' );
		if ( $amount_vnd > 0 ) {
			$payload .= $tlv( '54', (string) $amount_vnd );
		}
		$payload .= $tlv( '58', 'VN' );
		if ( '' !== $clean_memo ) {
			$payload .= $tlv( '62', $tlv( '08', $clean_memo ) );
		}
		$payload .= '6304';

		$crc = self::crc16_ccitt_false( $payload );
		return $payload . $crc;
	}

	/**
	 * Compute CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) as 4 uppercase hex characters.
	 */
	public static function crc16_ccitt_false( string $data ): string {
		$crc = 0xFFFF;
		$len = strlen( $data );
		for ( $i = 0; $i < $len; $i++ ) {
			$crc ^= ( ord( $data[ $i ] ) << 8 );
			for ( $b = 0; $b < 8; $b++ ) {
				if ( $crc & 0x8000 ) {
					$crc = ( ( $crc << 1 ) ^ 0x1021 ) & 0xFFFF;
				} else {
					$crc = ( $crc << 1 ) & 0xFFFF;
				}
			}
		}
		return strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Render luxury VietQR payment card on Thank-You and My Account Order View pages.
	 */
	public static function render_customer_vietqr_card( $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = is_object( $order_id ) ? $order_id : wc_get_order( absint( $order_id ) );
		if ( ! $order || 'bacs' !== $order->get_payment_method() ) {
			return;
		}

		// Prevent duplicate render if hooked twice on same request
		static $rendered_orders = array();
		$oid = (int) $order->get_id();
		if ( isset( $rendered_orders[ $oid ] ) ) {
			return;
		}

		// Never synthesize order_key as fallback; let BacsInstructions enforce either supplied key or logged-in ownership.
		$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$instr     = BacsInstructions::get_order_instructions( $order, array( 'order_key' => $order_key ) );
		if ( is_wp_error( $instr ) || empty( $instr['accounts'] ) ) {
			return;
		}
		if ( method_exists( $order, 'get_currency' ) && 'VND' !== strtoupper( (string) $order->get_currency() ) ) {
			return;
		}

		$primary = $instr['accounts'][0];
		$bin     = self::resolve_bank_bin( $primary );
		$acc_no  = preg_replace( '/\s+/', '', (string) ( $primary['account_number'] ?? '' ) );
		if ( '' === $acc_no ) {
			return;
		}

		$rendered_orders[ $oid ] = true;

		$amount_vnd = max( 0, (int) round( (float) $order->get_total() ) );
		$memo       = 'DESPACE' . $oid;
		$acc_name   = (string) ( $primary['account_name'] ?? 'DESPACE LUXURY' );
		$bank_name  = (string) ( $primary['bank_name'] ?? 'Ngân hàng' );
		$is_paid    = (bool) $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed' ), true );

		$emvco_str = $bin ? self::build_emvco_payload( $bin, $acc_no, $amount_vnd, $memo ) : '';
		$vietqr_img = $bin
			? sprintf(
				'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
				rawurlencode( $bin ),
				rawurlencode( $acc_no ),
				$amount_vnd,
				rawurlencode( $memo ),
				rawurlencode( $acc_name )
			)
			: '';

		$poll_url = esc_url_raw( rest_url( 'despace/v1/orders/' . $oid . '/payment-status' ) );
		$poll_key = (string) $order->get_order_key();
		?>
		<section class="despace-vietqr-card" id="despace-vietqr-card" style="margin:24px 0; background:#FAF7F2; border:1.5px solid #D8C4AE; border-radius:18px; padding:24px; box-shadow:0 10px 30px rgba(28,25,23,0.06);">
			<div id="despace-vietqr-paid-banner" style="<?php echo $is_paid ? '' : 'display:none;'; ?> background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:16px 20px; border-radius:12px; font-weight:600; font-size:15px;">
				✓ Thanh toán chuyển khoản cho đơn hàng <strong>#<?php echo esc_html( (string) $oid ); ?></strong> đã được xác nhận thành công! Cảm ơn quý khách.
			</div>

			<?php if ( ! $is_paid ) : ?>
			<div id="despace-vietqr-pending-wrap" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:24px; align-items:center;">
				<?php if ( $vietqr_img ) : ?>
				<div style="text-align:center; background:#FFFFFF; padding:16px; border-radius:14px; border:1px solid #E7E0D7;">
					<div style="font-size:11px; font-weight:700; letter-spacing:0.12em; color:#9A6745; text-transform:uppercase; margin-bottom:8px;">
						QUÉT MÃ VIETQR NAPAS 24/7
					</div>
					<img src="<?php echo esc_url( $vietqr_img ); ?>" alt="VietQR Payment Code" style="width:220px; max-width:100%; height:auto; margin:0 auto; display:block; border-radius:8px;" />
					<div style="margin-top:8px; font-size:12px; color:#69645D;">
						Tự động điền sẵn Số tiền &amp; Nội dung
					</div>
				</div>
				<?php endif; ?>

				<div>
					<span style="display:inline-flex; align-items:center; gap:6px; background:#FEF3C7; color:#92400E; font-size:11.5px; font-weight:700; padding:4px 12px; border-radius:999px; margin-bottom:10px;">
						⏳ Đang chờ xác nhận chuyển khoản tự động...
					</span>
					<h3 style="margin:0 0 8px; font-family:'Newsreader',Georgia,serif; font-size:21px; color:#1C1917;">
						Chuyển Khoản Nhanh Qua Mã VietQR
					</h3>
					<p style="margin:0 0 14px; font-size:13.5px; color:#69645D; line-height:1.5;">
						Mở ứng dụng Ngân hàng bất kỳ trên điện thoại để quét mã QR bên cạnh, hoặc sao chép chính xác thông tin dưới đây:
					</p>

					<div style="display:grid; gap:8px; background:#FFFFFF; padding:14px; border-radius:12px; border:1px solid #E7E0D7; font-size:13.5px;">
						<div style="display:flex; justify-content:space-between; gap:8px;">
							<span style="color:#69645D;">Ngân hàng:</span>
							<strong style="color:#1C1917;"><?php echo esc_html( $bank_name ); ?></strong>
						</div>
						<div style="display:flex; justify-content:space-between; gap:8px;">
							<span style="color:#69645D;">Chủ tài khoản:</span>
							<strong style="color:#1C1917;"><?php echo esc_html( $acc_name ); ?></strong>
						</div>
						<div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
							<span style="color:#69645D;">Số tài khoản:</span>
							<span>
								<strong style="color:#1C1917; font-family:monospace; font-size:14.5px;"><?php echo esc_html( $acc_no ); ?></strong>
								<button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $acc_no ); ?>');this.textContent='Đã chép ✓';" style="margin-left:6px; padding:3px 8px; font-size:11px; border-radius:6px; background:#1C1917; color:#fff; border:none; cursor:pointer;">Sao chép</button>
							</span>
						</div>
						<div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
							<span style="color:#69645D;">Số tiền:</span>
							<span>
								<strong style="color:#9A6745; font-size:15px;"><?php echo esc_html( number_format( $amount_vnd, 0, ',', '.' ) ); ?> ₫</strong>
								<button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( (string) $amount_vnd ); ?>');this.textContent='Đã chép ✓';" style="margin-left:6px; padding:3px 8px; font-size:11px; border-radius:6px; background:#EFE9DE; color:#1C1917; border:none; cursor:pointer;">Sao chép</button>
							</span>
						</div>
						<div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
							<span style="color:#69645D;">Nội dung CK:</span>
							<span>
								<strong style="color:#B91C1C; font-family:monospace; font-size:14.5px;"><?php echo esc_html( $memo ); ?></strong>
								<button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $memo ); ?>');this.textContent='Đã chép ✓';" style="margin-left:6px; padding:3px 8px; font-size:11px; border-radius:6px; background:#1C1917; color:#fff; border:none; cursor:pointer;">Sao chép</button>
							</span>
						</div>
					</div>
					<?php if ( $emvco_str ) : ?>
					<input type="hidden" id="despace-emvco-vietqr" value="<?php echo esc_attr( $emvco_str ); ?>" />
					<?php endif; ?>
				</div>
			</div>
			<script>
			(function(){
				var pollUrl = <?php echo wp_json_encode( $poll_url ); ?>;
				var pollKey = <?php echo wp_json_encode( $poll_key ); ?>;
				var attempts = 0;
				var maxAttempts = 200; // Stop after ~15 minutes
				var timer = setInterval(function(){
					if (document.hidden) return;
					if (++attempts > maxAttempts) { clearInterval(timer); return; }
					fetch(pollUrl, {
						credentials: 'same-origin',
						headers: { 'X-Order-Key': pollKey }
					})
						.then(function(r){ return r.json(); })
						.then(function(d){
							if (d && d.is_paid) {
								clearInterval(timer);
								var pWrap = document.getElementById('despace-vietqr-pending-wrap');
								var paidB = document.getElementById('despace-vietqr-paid-banner');
								if (pWrap) pWrap.style.display = 'none';
								if (paidB) paidB.style.display = 'block';
							}
						})
						.catch(function(){});
				}, 4500);
			})();
			</script>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Register Admin Order Meta Box for 1-click BACS Confirmation.
	 */
	public static function register_admin_bacs_metabox( string $post_type, $post_or_order ): void {
		if ( ! PaymentStatusGuard::can_confirm_manual_payment() ) {
			return;
		}
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $post_type, $screens, true ) ) {
			return;
		}

		add_meta_box(
			'despace_bacs_confirm_box',
			__( 'Đối Soát & Xác Nhận Chuyển Khoản VietQR / BACS', 'despace-core' ),
			array( __CLASS__, 'render_admin_bacs_metabox' ),
			$post_type,
			'side',
			'high'
		);
	}

	public static function render_admin_bacs_metabox( $post_or_order ): void {
		$order = is_a( $post_or_order, 'WC_Order' )
			? $post_or_order
			: ( function_exists( 'wc_get_order' ) && isset( $post_or_order->ID ) ? wc_get_order( $post_or_order->ID ) : null );

		if ( ! $order || 'bacs' !== $order->get_payment_method() ) {
			echo '<p style="margin:0;color:#6b7280;font-size:12.5px;">Đơn hàng này không dùng phương thức chuyển khoản ngân hàng (BACS).</p>';
			return;
		}

		$oid    = (int) $order->get_id();
		$status = $order->get_status();
		if ( $order->is_paid() || 'on-hold' !== $status ) {
			echo '<p style="margin:0;color:#059669;font-weight:600;font-size:13px;">✓ Đơn hàng đã được đối soát thanh toán (Trạng thái: ' . esc_html( strtoupper( $status ) ) . ').</p>';
			return;
		}

		$nonce = wp_create_nonce( 'despace_confirm_bacs_payment_' . $oid );
		?>
		<div id="despace-admin-bacs-box">
			<p style="margin:0 0 8px;font-size:12.5px;color:#374151;">
				Nội dung CK chuẩn: <code>DESPACE<?php echo esc_html( (string) $oid ); ?></code><br>
				Số tiền cần nhận: <strong><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></strong>
			</p>
			<input type="text" id="despace-bacs-tx-ref" placeholder="Mã giao dịch ngân hàng (tùy chọn)" style="width:100%;margin-bottom:8px;" />
			<button type="button" class="button button-primary" id="despace-btn-confirm-bacs" style="width:100%;background:#059669;border-color:#059669;font-weight:600;">
				✓ Xác Nhận Đã Nhận Chuyển Khoản
			</button>
			<p id="despace-bacs-confirm-msg" style="margin:8px 0 0;font-size:12px;"></p>
		</div>
		<script>
		jQuery(function($){
			$('#despace-btn-confirm-bacs').on('click', function(){
				var $btn = $(this).prop('disabled', true).text('Đang xác nhận...');
				$.post(ajaxurl, {
					action: 'despace_admin_confirm_bacs',
					order_id: <?php echo (int) $oid; ?>,
					bank_reference: $('#despace-bacs-tx-ref').val(),
					nonce: <?php echo wp_json_encode( $nonce ); ?>
				}, function(res){
					if (res && res.success) {
						$('#despace-bacs-confirm-msg').css('color', '#059669').text('✓ Đã xác nhận thanh toán! Đang tải lại...');
						setTimeout(function(){ location.reload(); }, 900);
					} else {
						$btn.prop('disabled', false).text('✓ Xác Nhận Đã Nhận Chuyển Khoản');
						$('#despace-bacs-confirm-msg').css('color', '#DC2626').text((res && res.data && res.data.message) ? res.data.message : 'Lỗi xác nhận.');
					}
				});
			});
		});
		</script>
		<?php
	}

	public static function ajax_admin_confirm_bacs(): void {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$bank_ref = sanitize_text_field( wp_unslash( $_POST['bank_reference'] ?? '' ) );
		$nonce    = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

		$res = PaymentStatusGuard::confirm_bacs_payment_by_admin(
			$order_id,
			$bank_ref,
			array(
				'nonce'              => $nonce,
				'confirmed_received' => true,
			)
		);

		if ( is_wp_error( $res ) ) {
			$err_data   = $res->get_error_data();
			$err_status = ( is_array( $err_data ) && ! empty( $err_data['status'] ) ) ? (int) $err_data['status'] : 400;
			wp_send_json_error( array( 'message' => $res->get_error_message() ), $err_status );
		}

		wp_send_json_success( $res );
	}

	/**
	 * Register REST endpoints for order payment status polling & SePay/Casso webhook reconciliation.
	 */
	public static function register_rest_routes(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			'despace/v1',
			'/orders/(?P<id>\d+)/payment-status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_payment_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'despace/v1',
			'/webhooks/vietqr',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_handle_vietqr_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_get_payment_status( WP_REST_Request $request ): WP_REST_Response {
		$order_id = absint( $request->get_param( 'id' ) );
		$key      = sanitize_text_field( (string) ( $request->get_header( 'x-order-key' ) ?: $request->get_param( 'key' ) ?: '' ) );

		if ( ! $order_id || '' === $key || ! function_exists( 'wc_get_order' ) ) {
			$resp = new WP_REST_Response( array( 'code' => 'forbidden' ), 403 );
			$resp->header( 'Cache-Control', 'no-store, private' );
			return $resp;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $key ) ) {
			$resp = new WP_REST_Response( array( 'code' => 'forbidden' ), 403 );
			$resp->header( 'Cache-Control', 'no-store, private' );
			return $resp;
		}

		$is_paid = (bool) $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed' ), true );

		$resp = new WP_REST_Response(
			array(
				'order_id' => $order_id,
				'status'   => $order->get_status(),
				'is_paid'  => $is_paid,
			),
			200
		);
		$resp->header( 'Cache-Control', 'no-store, private' );
		return $resp;
	}

	/**
	 * Handle SePay / Casso / VietQR Incoming Bank Transfer Webhook.
	 */
	public static function rest_handle_vietqr_webhook( WP_REST_Request $request ): WP_REST_Response {
		$expected_secret = (string) get_option( 'despace_vietqr_webhook_secret', '' );
		if ( '' === $expected_secret ) {
			return new WP_REST_Response( array( 'code' => 'webhook_not_configured', 'message' => 'Configure despace_vietqr_webhook_secret in options first.' ), 403 );
		}

		$provided = (string) ( $request->get_header( 'x-despace-webhook-secret' ) ?: '' );
		if ( '' === $provided ) {
			$auth = (string) ( $request->get_header( 'authorization' ) ?: '' );
			if ( preg_match( '/^(?:Apikey|Bearer)\s+(.+)$/i', trim( $auth ), $m ) ) {
				$provided = trim( $m[1] );
			}
		}

		if ( '' === $provided || ! hash_equals( $expected_secret, $provided ) ) {
			return new WP_REST_Response( array( 'code' => 'unauthorized_webhook' ), 401 );
		}

		$json          = $request->get_json_params();
		$params        = is_array( $json ) && ! empty( $json ) ? $json : $request->get_params();
		$transfer_type = strtolower( trim( (string) ( $params['transferType'] ?? $request->get_param( 'transferType' ) ?? 'in' ) ) );
		if ( 'in' !== $transfer_type ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'ignored_non_incoming_transfer' ), 200 );
		}

		// Verify destination bank account matches configured merchant BACS account when supplied.
		$incoming_acc = preg_replace( '/\s+/', '', (string) ( $params['accountNumber'] ?? $params['subAccount'] ?? $request->get_param( 'accountNumber' ) ?? '' ) );
		if ( '' !== $incoming_acc ) {
			$configured_accounts = (array) get_option( 'woocommerce_bacs_accounts', array() );
			$valid_accounts      = array();
			foreach ( $configured_accounts as $acc ) {
				if ( is_array( $acc ) && ! empty( $acc['account_number'] ) ) {
					$valid_accounts[] = preg_replace( '/\s+/', '', (string) $acc['account_number'] );
				}
			}
			if ( ! empty( $valid_accounts ) && ! in_array( $incoming_acc, $valid_accounts, true ) ) {
				return new WP_REST_Response( array( 'success' => false, 'reason' => 'account_mismatch' ), 200 );
			}
		}

		$content = (string) ( $params['content'] ?? $params['description'] ?? $request->get_param( 'content' ) ?? '' );
		$amount  = (int) round( floatval( $params['transferAmount'] ?? $params['amount'] ?? $request->get_param( 'transferAmount' ) ?? 0 ) );
		$tx_ref  = sanitize_text_field( (string) ( $params['referenceCode'] ?? $params['id'] ?? $request->get_param( 'referenceCode' ) ?? '' ) );

		if ( $amount <= 0 ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'invalid_amount' ), 200 );
		}

		if ( ! preg_match( '/DESPACE[\-\s]*(\d+)/i', $content, $matches ) ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'no_order_code_matched' ), 200 );
		}

		// Transaction replay guard: reject already-processed bank reference IDs.
		if ( '' !== $tx_ref ) {
			$replay_key = 'despace_vietqr_tx_' . md5( $tx_ref );
			if ( function_exists( 'get_option' ) && get_option( $replay_key, false ) ) {
				return new WP_REST_Response( array( 'success' => true, 'already_settled' => true, 'reason' => 'duplicate_tx_ref' ), 200 );
			}
		} else {
			$tx_ref = 'VIETQR-WEBHOOK';
		}

		$order_id = absint( $matches[1] );
		$order    = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order || 'bacs' !== $order->get_payment_method() ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'order_not_found_or_not_bacs' ), 200 );
		}

		if ( method_exists( $order, 'get_currency' ) && 'VND' !== strtoupper( (string) $order->get_currency() ) ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'currency_mismatch' ), 200 );
		}

		if ( $order->is_paid() || 'on-hold' !== $order->get_status() ) {
			return new WP_REST_Response( array( 'success' => true, 'already_settled' => true, 'order_id' => $order_id ), 200 );
		}

		$expected_total = (int) round( (float) $order->get_total() );
		if ( $expected_total <= 0 || $amount < $expected_total ) {
			$order->add_order_note( sprintf( '[VietQR Webhook] Nhận chuyển khoản thiếu (%s ₫ / cần %s ₫, mã GD: %s). Giữ trạng thái chờ đối soát.', number_format( $amount ), number_format( $expected_total ), $tx_ref ) );
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'underpaid' ), 200 );
		}

		$lock = PaymentStatusGuard::acquire_bacs_lock( $order_id );
		if ( is_wp_error( $lock ) ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'locked' ), 409 );
		}

		$settled = false;
		try {
			$fresh = wc_get_order( $order_id );
			if ( $fresh && ! $fresh->is_paid() && 'on-hold' === $fresh->get_status() ) {
				$already_confirmed = (string) $fresh->get_meta( '_despace_bacs_confirmed_at', true );
				$fresh_total       = (int) round( (float) $fresh->get_total() );
				if ( '' === $already_confirmed && $fresh_total > 0 && $amount >= $fresh_total ) {
					$completed = $fresh->payment_complete( $tx_ref );
					if ( false !== $completed ) {
						$fresh->update_meta_data( '_despace_bacs_confirmed_at', gmdate( 'c' ) );
						$fresh->update_meta_data( '_despace_vietqr_tx_ref', $tx_ref );
						$fresh->add_order_note( sprintf( '[VietQR Auto-Settlement] Đã tự động xác nhận nhận đủ %s ₫ qua Webhook (Mã tham chiếu: %s).', number_format( $amount ), $tx_ref ) );
						$fresh->save();
						if ( 'VIETQR-WEBHOOK' !== $tx_ref && function_exists( 'add_option' ) ) {
							add_option( 'despace_vietqr_tx_' . md5( $tx_ref ), (string) $order_id, '', false );
						}
						$settled = true;
					}
				}
			}
		} finally {
			PaymentStatusGuard::release_bacs_lock( $lock );
		}

		return new WP_REST_Response( array( 'success' => $settled, 'settled' => $settled, 'order_id' => $order_id ), $settled ? 200 : 409 );
	}
}
