<?php
/**
 * DeSpace M3 Live UAT — Phase 6E: Real Email Capture & Verification via Mailpit.
 *
 * Runs against live WordPress, WooCommerce, and Mailpit SMTP.
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

use DeSpace\CheckoutOrders\Checkout\OrderMetaSaver;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not active.\n" );
	exit( 1 );
}

echo "========================================================================\n";
echo "   DeSpace M3 Phase 6E — REAL TRANSACTIONAL EMAIL CAPTURE (MAILPIT)     \n";
echo "========================================================================\n\n";

$mailpit_base = 'http://despace-mailpit:8025';

// 1. Purge Mailpit before test
$ch = curl_init( $mailpit_base . '/api/v1/messages' );
curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_exec( $ch );
curl_close( $ch );
echo "Step 1: Mailpit inbox purged.\n";

// 2. Create BACS Order with 2-tier Vietnam address and legacy address_2
$order_bacs = wc_create_order( array(
	'status'        => 'pending',
	'customer_id'   => 0,
	'created_via'   => 'uat_email_bacs',
) );
$order_bacs->set_payment_method( 'bacs' );
$order_bacs->set_payment_method_title( 'Chuyển khoản trực tiếp BACS' );
$order_bacs->set_billing_first_name( 'Lê' );
$order_bacs->set_billing_last_name( 'Thị Thử Nghiệm' );
$order_bacs->set_billing_email( 'customer.bacs@despace.test' );
$order_bacs->set_billing_phone( '0901122334' );
$order_bacs->set_billing_country( 'VN' );
$order_bacs->set_billing_state( '15' );
$order_bacs->set_billing_city( 'Phường Lào Cai' );
$order_bacs->set_billing_address_1( 'Số 88 Phố Hoàng Liên' );
$order_bacs->set_billing_address_2( 'Tầng 3 Căn hộ 302' ); // Legacy address_2
$order_bacs->set_shipping_address_1( 'Số 88 Phố Hoàng Liên' );
$order_bacs->set_shipping_address_2( 'Tầng 3 Căn hộ 302' );
$order_bacs->set_shipping_city( 'Phường Lào Cai' );
$order_bacs->set_shipping_state( '15' );
$order_bacs->set_shipping_country( 'VN' );

$simple = new \WC_Product_Simple();
$simple->set_name( 'Sofa Góc DeSpace Scandi' );
$simple->set_regular_price( '12500000' );
$simple->set_status( 'publish' );
$pid = $simple->save();

$item = new \WC_Order_Item_Product();
$item->set_product( $simple );
$item->set_quantity( 1 );
$item->set_subtotal( 12500000 );
$item->set_total( 12500000 );
$order_bacs->add_item( $item );
$order_bacs->set_total( 12500000 );

OrderMetaSaver::save_order_metadata( $order_bacs, array(
	'billing_despace_commune_code'  => '02647',
	'shipping_despace_commune_code' => '02647',
	'billing_state'                 => '15',
	'shipping_state'                => '15',
) );
$order_bacs->save();
$bacs_oid = $order_bacs->get_id();
echo "Step 2: Created BACS Order #{$bacs_oid} with VN 2-tier + legacy address_2.\n";

// 3. Create COD Order
$order_cod = wc_create_order( array(
	'status'        => 'pending',
	'customer_id'   => 0,
	'created_via'   => 'uat_email_cod',
) );
$order_cod->set_payment_method( 'cod' );
$order_cod->set_payment_method_title( 'Thanh toán khi nhận hàng (COD)' );
$order_cod->set_billing_first_name( 'Phạm' );
$order_cod->set_billing_last_name( 'Văn Giao Tận Tay' );
$order_cod->set_billing_email( 'customer.cod@despace.test' );
$order_cod->set_billing_phone( '0988001122' );
$order_cod->set_billing_country( 'VN' );
$order_cod->set_billing_state( '15' );
$order_cod->set_billing_city( 'Phường Lào Cai' );
$order_cod->set_billing_address_1( 'Số 15 Phố Cốc Lếu' );
$order_cod->set_billing_address_2( 'Tòa nhà A' );
$order_cod->add_item( $item );
$order_cod->set_total( 12500000 );

OrderMetaSaver::save_order_metadata( $order_cod, array(
	'billing_despace_commune_code' => '02647',
	'billing_state'                => '15',
) );
$order_cod->save();
$cod_oid = $order_cod->get_id();
echo "Step 3: Created COD Order #{$cod_oid}.\n";

// 4. Trigger Emails
$mailer = WC()->mailer();
$emails = $mailer->get_emails();

echo "Step 4: Dispatching WooCommerce Transactional Emails...\n";

// A. Customer On-Hold Order (BACS)
if ( isset( $emails['WC_Email_Customer_On_Hold_Order'] ) ) {
	$emails['WC_Email_Customer_On_Hold_Order']->trigger( $bacs_oid, $order_bacs );
	echo "  -> Triggered WC_Email_Customer_On_Hold_Order (BACS)\n";
}

// B. Customer Processing Order (COD)
if ( isset( $emails['WC_Email_Customer_Processing_Order'] ) ) {
	$emails['WC_Email_Customer_Processing_Order']->trigger( $cod_oid, $order_cod );
	echo "  -> Triggered WC_Email_Customer_Processing_Order (COD)\n";
}

// C. Customer Completed Order (BACS completed)
if ( isset( $emails['WC_Email_Customer_Completed_Order'] ) ) {
	$emails['WC_Email_Customer_Completed_Order']->trigger( $bacs_oid, $order_bacs );
	echo "  -> Triggered WC_Email_Customer_Completed_Order\n";
}

// D. New Order Admin
if ( isset( $emails['WC_Email_New_Order'] ) ) {
	$emails['WC_Email_New_Order']->trigger( $bacs_oid, $order_bacs );
	echo "  -> Triggered WC_Email_New_Order (Admin)\n";
}

// E. Customer New Account
if ( isset( $emails['WC_Email_Customer_New_Account'] ) ) {
	$user_id = wp_create_user( 'email_test_' . time(), 'Password@123', 'newuser@despace.test' );
	$emails['WC_Email_Customer_New_Account']->trigger( $user_id, 'Password@123', true );
	echo "  -> Triggered WC_Email_Customer_New_Account\n";
}

// F. Customer Reset Password
if ( isset( $emails['WC_Email_Customer_Reset_Password'] ) && isset( $user_id ) ) {
	$user = get_user_by( 'id', $user_id );
	$emails['WC_Email_Customer_Reset_Password']->trigger( $user->user_login, 'test_reset_key_12345' );
	echo "  -> Triggered WC_Email_Customer_Reset_Password\n";
}

// Allow background SMTP network send to complete
sleep( 2 );

// 5. Retrieve Messages from Mailpit API
$resp = file_get_contents( $mailpit_base . '/api/v1/messages' );
$mail_data = json_decode( $resp, true );
$total_messages = $mail_data['total'] ?? 0;
echo "\nStep 5: Mailpit Captured {$total_messages} messages.\n";

$bacs_hold_msg = null;
$cod_proc_msg  = null;

foreach ( array_reverse( $mail_data['messages'] ) as $m ) {
	$msg_id  = $m['ID'];
	$subject = $m['Subject'];
	$to      = $m['To'][0]['Address'] ?? '';
	echo "  -> Captured: [ID: $msg_id] To: $to | Subject: $subject\n";

	$full = json_decode( file_get_contents( "{$mailpit_base}/api/v1/message/{$msg_id}" ), true );
	$html = $full['HTML'] ?? '';
	$text = $full['Text'] ?? '';

	if ( 'customer.bacs@despace.test' === $to && null === $bacs_hold_msg ) {
		$bacs_hold_msg = array( 'id' => $msg_id, 'html' => $html, 'text' => $text, 'subject' => $subject );
	}
	if ( 'customer.cod@despace.test' === $to && null === $cod_proc_msg ) {
		$cod_proc_msg = array( 'id' => $msg_id, 'html' => $html, 'text' => $text, 'subject' => $subject );
	}
}

// 6. Verify Email Content Assertions
echo "\nStep 6: Verifying Email Invariants:\n";

$bacs_html = $bacs_hold_msg['html'] ?? '';
$bacs_text = $bacs_hold_msg['text'] ?? '';

$addr_needle   = 'Số 88 Phố Hoàng Liên';
$legacy_needle = 'Tầng 3 Căn hộ 302';
$region_needle = 'Phường Lào Cai';

$html_addr_count   = substr_count( $bacs_html, $addr_needle );
$html_legacy_count = substr_count( $bacs_html, $legacy_needle );
$html_region_count = substr_count( $bacs_html, $region_needle );

echo "  -> Occurrence of '$addr_needle' in BACS HTML: {$html_addr_count} (Expected: 1 or 2 for billing/shipping)\n";
echo "  -> Occurrence of '$legacy_needle' (legacy address_2) in BACS HTML: {$html_legacy_count} (Preserved: YES)\n";
echo "  -> Occurrence of '$region_needle' in BACS HTML: {$html_region_count} (2-tier verified: YES)\n";

// B. COD email should NOT leak BACS bank accounts
$cod_html = $cod_proc_msg['html'] ?? '';
$has_bank_in_cod = false !== stripos( $cod_html, 'Số tài khoản' ) || false !== stripos( $cod_html, 'IBAN' ) || false !== stripos( $cod_html, 'Chi nhánh ngân hàng' );
echo "  -> COD email exposes bank account details: " . ( $has_bank_in_cod ? 'LEAKED (BUG)' : 'NO (SAFE)' ) . "\n";

// Cleanup
$order_bacs->delete( true );
$order_cod->delete( true );
$simple->delete( true );
if ( isset( $user_id ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );
}

echo "\n========================================================================\n";
if ( $total_messages >= 6 && $html_addr_count >= 1 && $html_addr_count <= 2 && $html_legacy_count >= 1 && $html_region_count >= 1 && ! $has_bank_in_cod ) {
	echo ">>> PHASE 6E EMAIL CAPTURE & VERIFICATION: ALL ASSERTIONS PASSED (100%) <<<\n";
	echo "========================================================================\n";
} else {
	echo ">>> PHASE 6E EMAIL CAPTURE & VERIFICATION: FAILED <<<\n";
	echo "========================================================================\n";
	exit( 1 );
}
