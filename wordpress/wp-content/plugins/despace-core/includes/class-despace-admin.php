<?php
/**
 * DeSpace Admin Interface, Dashboard, and Inventory Logs View.
 *
 * @package DeSpace_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
	}

	/**
	 * Register Admin Menus.
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( 'DeSpace Management', 'despace-core' ),
			__( 'DeSpace Core', 'despace-core' ),
			'manage_woocommerce',
			'despace-hub',
			array( $this, 'render_dashboard_page' ),
			'dashicons-layout',
			56
		);

		add_submenu_page(
			'despace-hub',
			__( 'Tổng quan hệ thống', 'despace-core' ),
			__( 'Tổng quan', 'despace-core' ),
			'manage_woocommerce',
			'despace-hub',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'despace-hub',
			__( 'Nhật ký biến động tồn kho', 'despace-core' ),
			__( 'Nhật ký kho (Audit Logs)', 'despace-core' ),
			'manage_woocommerce',
			'despace-inventory-logs',
			array( $this, 'render_inventory_logs_page' )
		);
	}

	/**
	 * Render System Dashboard Page.
	 */
	public function render_dashboard_page() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'despace_inventory_logs';
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;
		$log_count = $table_exists ? $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) : 0;
		$prod_3d_count = count( get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => '_despace_glb_attachment_id', 'compare' => 'EXISTS' ),
				array( 'key' => '_despace_glb_url', 'compare' => 'EXISTS' ),
				array( 'key' => '_axgnud_model_glb', 'compare' => 'EXISTS' ),
			),
		) ) );

		?>
		<div class="wrap">
			<h1 style="display:flex;align-items:center;gap:10px;">
				<span style="background:#111315;color:#FAF9F6;padding:4px 10px;border-radius:4px;font-size:16px;border:1px solid #C5A880;">DeSpace</span>
				<?php esc_html_e( 'Trung tâm Quản trị DeSpace Core', 'despace-core' ); ?>
			</h1>
			<p style="color:#666;">
				<?php esc_html_e( 'Kiến trúc chuẩn hóa theo tài liệu DeSpace SADD v1.2 (WordPress + WooCommerce + DeSpace Core Plugin + DeSpace Child Theme + 3D/AR model-viewer).', 'despace-core' ); ?>
			</p>

			<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:20px;margin-top:25px;">
				<div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="margin-top:0;color:#111315;">📦 Quản lý Sản phẩm & 3D</h3>
					<p style="font-size:28px;font-weight:700;color:#C5A880;margin:10px 0;"><?php echo esc_html( $prod_3d_count ); ?> <span style="font-size:14px;color:#888;font-weight:normal;">sản phẩm có 3D/AR</span></p>
					<p style="font-size:13px;color:#666;">Tích hợp Google model-viewer v4.4.0 với định dạng .glb chuẩn hóa.</p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="button button-secondary">Quản lý sản phẩm &rarr;</a>
				</div>

				<div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="margin-top:0;color:#111315;">📋 Bảng Nhật ký Tồn kho</h3>
					<p style="font-size:28px;font-weight:700;color:#2271b1;margin:10px 0;"><?php echo esc_html( $log_count ); ?> <span style="font-size:14px;color:#888;font-weight:normal;">sự kiện ghi vết</span></p>
					<p style="font-size:13px;color:#666;">Bảng dữ liệu riêng <code><?php echo esc_html( $table_name ); ?></code> truy vết biến động kho.</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=despace-inventory-logs' ) ); ?>" class="button button-primary">Xem nhật ký kho &rarr;</a>
				</div>

				<div style="background:#fff;border:1px solid #e2e4e7;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="margin-top:0;color:#111315;">🚀 Trạng thái Kỹ thuật</h3>
					<ul style="font-size:13px;color:#555;line-height:1.8;padding-left:18px;margin:10px 0;">
						<li><strong>PHP:</strong> <?php echo esc_html( phpversion() ); ?></li>
						<li><strong>WordPress:</strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></li>
						<li><strong>WooCommerce:</strong> <?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : 'Chưa bật'; ?></li>
						<li><strong>Bảng Nhật ký:</strong> <?php echo $table_exists ? '<span style="color:green;">✓ Hoạt động</span>' : '<span style="color:red;">Chưa tạo</span>'; ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Inventory Logs Screen.
	 */
	public function render_inventory_logs_page() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'despace_inventory_logs';
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_name} ORDER BY log_id DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$total_pages = ceil( $total / $per_page );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Nhật ký Biến động Kho (DeSpace Inventory Audit Logs)', 'despace-core' ); ?></h1>
			<p style="color:#666;">
				<?php esc_html_e( 'Truy vết biến động tồn kho chi tiết khi có đơn hàng, hoàn trả, hoặc điều chỉnh theo Section 6.3 SADD.', 'despace-core' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped posts" style="margin-top:15px;">
				<thead>
					<tr>
						<th style="width:60px;">ID</th>
						<th style="width:140px;">Thời gian</th>
						<th>Sản phẩm / Biến thể</th>
						<th style="width:90px;">Đơn hàng</th>
						<th style="width:90px;">Loại sự kiện</th>
						<th style="width:70px;text-align:center;">Delta</th>
						<th style="width:80px;text-align:center;">Tồn sau</th>
						<th>Ghi chú</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="8" style="text-align:center;padding:25px;color:#888;">
								<?php esc_html_e( 'Chưa có bản ghi biến động tồn kho nào được ghi nhận.', 'despace-core' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : 
							$prod = wc_get_product( $log->product_id );
							$prod_title = $prod ? $prod->get_name() : sprintf( '#%d (Đã xóa)', $log->product_id );
						?>
							<tr>
								<td>#<?php echo esc_html( $log->log_id ); ?></td>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td>
									<strong><a href="<?php echo esc_url( get_edit_post_link( $log->product_id ) ); ?>"><?php echo esc_html( $prod_title ); ?></a></strong>
									<?php if ( $log->variation_id ) : ?>
										<br/><small style="color:#666;">Biến thể: #<?php echo esc_html( $log->variation_id ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $log->order_id ) : ?>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log->order_id . '&action=edit' ) ); ?>">#<?php echo esc_html( $log->order_id ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<span class="badge" style="background:#eef;padding:3px 6px;border-radius:3px;font-size:11px;font-weight:600;">
										<?php echo esc_html( strtoupper( $log->event_type ) ); ?>
									</span>
								</td>
								<td style="text-align:center;font-weight:bold;color:<?php echo $log->quantity_delta < 0 ? '#d63638' : '#00a32a'; ?>;">
									<?php echo $log->quantity_delta > 0 ? '+' . esc_html( $log->quantity_delta ) : esc_html( $log->quantity_delta ); ?>
								</td>
								<td style="text-align:center;font-weight:bold;">
									<?php echo null !== $log->stock_after ? esc_html( $log->stock_after ) : '—'; ?>
								</td>
								<td><small><?php echo esc_html( $log->note ); ?></small></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top:15px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( esc_html__( '%d bản ghi', 'despace-core' ), $total ); ?></span>
						<span class="pagination-links">
							<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
								<a class="button <?php echo $i === $page ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
							<?php endfor; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
