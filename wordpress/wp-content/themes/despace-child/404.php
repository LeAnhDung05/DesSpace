<?php
/**
 * 404 Template.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="ds-section" style="padding: 100px 0; text-align: center; min-height: 60vh;">
	<div class="ds-wrap" style="max-width: 600px;">
		<span class="ds-section-tag">404 ERROR</span>
		<h1 style="font-size: 56px; margin: 10px 0 20px;">Không Tìm Thấy Trang</h1>
		<p style="color: var(--ds-text-muted); font-size: 16px; margin-bottom: 30px;">
			Trang bạn đang tìm kiếm có thể đã bị đổi tên, di chuyển hoặc không còn tồn tại trong hệ thống DeSpace.
		</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ds-btn ds-btn--primary">
			&larr; Quay lại Trang chủ
		</a>
	</div>
</div>

<?php
get_footer();
