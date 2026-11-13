<?php
/**
 * Footer template.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
?>
<footer class="ds-footer">
	<div class="ds-container">
		<div class="ds-footer-grid">
			<div><div class="ds-logo">DESPACE<small>FURNITURE & LIVING</small></div><p>Không gian sống được tạo nên từ những lựa chọn bền vững, chất liệu tinh tế và thiết kế vượt thời gian.</p></div>
			<div><h3>Khám phá</h3><p><a href="<?php echo esc_url( despace_staging_shop_url() ); ?>">Sản phẩm</a></p><p><a href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">Tài khoản</a></p></div>
			<div><h3>Hỗ trợ</h3><p><a href="<?php echo esc_url( home_url( '/order-tracking/' ) ); ?>">Tra cứu đơn hàng</a></p><p><a href="<?php echo esc_url( home_url( '/cart/' ) ); ?>">Giỏ hàng</a></p></div>
		</div>
		<div class="ds-footer-bottom"><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> DESPACE. STAGING PREVIEW.</span><span>Phiên bản thử nghiệm · Không sử dụng cho giao dịch thật</span></div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
