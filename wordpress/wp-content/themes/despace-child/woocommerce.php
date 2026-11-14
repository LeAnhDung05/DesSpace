<?php
/**
 * WooCommerce Template Wrapper.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="ds-section" style="padding-top: 40px; min-height: 60vh;">
	<div class="ds-wrap">
		<?php if ( is_shop() || is_product_taxonomy() ) : ?>
			<div class="ds-catalog-header" style="margin-bottom: 30px; border-bottom: 1px solid var(--ds-border); padding-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 15px;">
				<div>
					<span class="ds-section-tag"><?php echo esc_html( despace_t( 'DeSpace Collection', 'Bộ Sưu Tập Nội Thất' ) ); ?></span>
					<h1 style="font-size: 36px; margin: 0;"><?php woocommerce_page_title(); ?></h1>
				</div>
				<div class="ds-quick-filters" style="display: flex; gap: 10px; align-items: center;">
					<a href="<?php echo esc_url( add_query_arg( 'has_3d', '1' ) ); ?>" class="ds-btn <?php echo ( isset( $_GET['has_3d'] ) && '1' === $_GET['has_3d'] ) ? 'ds-btn--primary' : 'ds-btn--outline'; ?>" style="padding: 8px 16px; font-size: 13px; color: var(--ds-dark) !important; border-color: var(--ds-border);">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;">
							<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
						</svg>
						<?php echo esc_html( despace_t( 'Only 3D & AR Items', 'Chỉ sản phẩm 3D / AR' ) ); ?>
					</a>
					<?php if ( isset( $_GET['has_3d'] ) ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'has_3d' ) ); ?>" style="font-size: 12px; color: var(--ds-text-muted); text-decoration: underline;">
							✕ Xóa bộ lọc
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="ds-woocommerce-content">
			<?php woocommerce_content(); ?>
		</div>
	</div>
</div>

<?php
get_footer();
