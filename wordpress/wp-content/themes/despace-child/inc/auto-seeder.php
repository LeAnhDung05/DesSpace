<?php
/**
 * DeSpace 1-Click Auto Seeder & Database Synchronizer.
 *
 * Automatically detects empty product catalog and provides 1-click sync
 * for team members running LocalWP, Docker, or fresh environments.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Auto_Seeder {

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend_banner' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_sync_button' ), 90 );
		add_action( 'admin_post_despace_run_auto_sync', array( __CLASS__, 'handle_auto_sync' ) );
	}

	/**
	 * Check if current site needs database seeding.
	 */
	public static function needs_seeding() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check if products count is less than 10
		$product_count = wp_count_posts( 'product' );
		$published = isset( $product_count->publish ) ? intval( $product_count->publish ) : 0;

		return ( $published < 10 || isset( $_GET['despace_sync_prompt'] ) );
	}

	/**
	 * Render 1-click button on top admin bar.
	 */
	public static function add_admin_bar_sync_button( $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sync_url = wp_nonce_url( admin_url( 'admin-post.php?action=despace_run_auto_sync' ), 'despace_auto_sync_nonce' );
		$admin_bar->add_node( array(
			'id'    => 'despace-sync-db-btn',
			'title' => '<span style="background: #2563eb; color: #ffffff; padding: 2px 10px; border-radius: 4px; font-weight: 700; font-size: 12px; display: inline-block;">⚡ Đồng bộ Data DeSpace</span>',
			'href'  => $sync_url,
			'meta'  => array(
				'title'   => 'Nạp 40 sản phẩm và cấu hình DeSpace tự động',
				'onclick' => "return confirm('Bạn có chắc muốn đồng bộ 40 sản phẩm chuẩn và database DeSpace vào trang này?');",
			),
		) );
	}

	/**
	 * Render notice in WordPress Admin.
	 */
	public static function render_admin_notice() {
		if ( ! self::needs_seeding() && ! isset( $_GET['synced'] ) ) {
			return;
		}

		if ( isset( $_GET['synced'] ) && $_GET['synced'] === '1' ) {
			?>
			<div class="notice notice-success is-dismissible" style="padding: 14px 18px; border-left: 4px solid #10b981; background: #ecfdf5;">
				<h3 style="margin: 0 0 6px 0; color: #065f46; font-size: 16px;">🎉 Đồng bộ cơ sở dữ liệu DeSpace thành công 100%!</h3>
				<p style="margin: 0; color: #047857; font-size: 14px;">
					Toàn bộ <strong>40 sản phẩm chuẩn</strong>, 5 danh mục, bảng mã màu vân gỗ và thiết lập cửa hàng đã được nạp thành công. 
					<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="button button-secondary" style="margin-left: 10px; font-weight: 600;">👉 Xem Trang Cửa Hàng</a>
				</p>
			</div>
			<?php
			return;
		}

		$sync_url = wp_nonce_url( admin_url( 'admin-post.php?action=despace_run_auto_sync' ), 'despace_auto_sync_nonce' );
		?>
		<div class="notice notice-warning" style="padding: 16px 20px; border-left: 4px solid #f59e0b; background: #fffbeb; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-top: 20px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
				<div>
					<h3 style="margin: 0 0 6px 0; color: #92400e; font-size: 16px; display: flex; align-items: center; gap: 8px;">
						<span>🚀</span> <strong>DeSpace Auto-Setup: Chưa phát hiện sản phẩm mẫu (0 sản phẩm)</strong>
					</h3>
					<p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.5;">
						Dự án vừa kéo từ Git chưa có cơ sở dữ liệu sản phẩm. Bấm nút bên dưới để <strong>tự động nạp 40 sản phẩm, 5 danh mục, bảng màu vân gỗ</strong> chỉ trong 5 giây!
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url( $sync_url ); ?>" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; padding: 6px 18px; font-size: 14px; font-weight: 600; height: auto;" onclick="this.innerHTML='⏳ Đang đồng bộ... vui lòng chờ 5-10 giây'; this.style.pointerEvents='none';">
						⚡ BẤM ĐỒNG BỘ 40 SẢN PHẨM NGAY (1 CLICK)
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render floating banner on frontend if admin is logged in and catalog is empty.
	 */
	public static function render_frontend_banner() {
		if ( ! self::needs_seeding() ) {
			return;
		}

		$sync_url = wp_nonce_url( admin_url( 'admin-post.php?action=despace_run_auto_sync' ), 'despace_auto_sync_nonce' );
		?>
		<div id="despace-auto-sync-banner" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999; max-width: 480px; background: #1e1b4b; color: #ffffff; padding: 18px 22px; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); border: 2px solid #818cf8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 8px;">
				<div style="font-weight: 700; font-size: 15px; color: #a5b4fc; display: flex; align-items: center; gap: 6px;">
					<span>⚡</span> <span>DeSpace Auto-Setup</span>
				</div>
				<button onclick="document.getElementById('despace-auto-sync-banner').style.display='none'" style="background: transparent; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
			</div>
			<p style="font-size: 13px; line-height: 1.5; color: #e2e8f0; margin: 0 0 14px 0;">
				Cơ sở dữ liệu đang có <strong>0 sản phẩm</strong>. Bấm nút dưới để nạp tự động 40 sản phẩm chuẩn và bỏ chế độ Coming Soon!
			</p>
			<a href="<?php echo esc_url( $sync_url ); ?>" style="display: block; text-align: center; background: #4f46e5; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; transition: background 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'" onclick="this.innerHTML='⏳ Đang nạp dữ liệu... vui lòng chờ'; this.style.pointerEvents='none';">
				🚀 Đồng bộ 40 sản phẩm DeSpace ngay (1-Click)
			</a>
		</div>
		<?php
	}

	/**
	 * Execute the automated database synchronization.
	 */
	public static function handle_auto_sync() {
		check_admin_referer( 'despace_auto_sync_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bạn không có quyền thực hiện thao tác này.' );
		}

		global $wpdb;

		// 1. Preserve current user info to prevent being logged out
		$current_user = wp_get_current_user();
		$current_login = $current_user->user_login;
		$current_pass  = $current_user->user_pass;
		$current_email = $current_user->user_email;
		$current_url   = home_url();
		$fixed_admin   = get_user_by( 'login', 'Admin' );
		$fixed_admin_row = $fixed_admin ? $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->users} WHERE ID = %d", $fixed_admin->ID ),
			ARRAY_A
		) : null;
		if ( is_array( $fixed_admin_row ) ) {
			$fixed_admin_row['user_login'] = 'Admin';
		}

		// 2. Locate SQL seed file
		$gz_file = get_stylesheet_directory() . '/inc/data/despace_seed_data.sql.gz';
		$sql_file = get_stylesheet_directory() . '/inc/data/despace_seed_data.sql';
		$root_sql = dirname( get_stylesheet_directory(), 4 ) . '/database/despace_seed_data.sql';

		$sql_content = '';

		if ( file_exists( $gz_file ) ) {
			$raw = file_get_contents( $gz_file );
			$sql_content = gzdecode( $raw );
		} elseif ( file_exists( $sql_file ) ) {
			$sql_content = file_get_contents( $sql_file );
		} elseif ( file_exists( $root_sql ) ) {
			$sql_content = file_get_contents( $root_sql );
		}

		if ( empty( $sql_content ) ) {
			wp_die( 'Không tìm thấy tệp dữ liệu despace_seed_data.sql.gz trong inc/data/.' );
		}

		// 3. Replace development URL with local URL
		if ( ! empty( $current_url ) && $current_url !== 'http://localhost:8090' ) {
			$sql_content = str_replace( 'http://localhost:8090', $current_url, $sql_content );
		}

		// 4. Execute multi_query
		$mysqli = $wpdb->dbh;
		if ( ! ( $mysqli instanceof mysqli ) ) {
			wp_die( 'Yêu cầu kết nối MySQLi để thực thi nạp dữ liệu.' );
		}

		// Disable foreign keys and autocommit for lightning-fast import
		$mysqli->query( 'SET FOREIGN_KEY_CHECKS=0;' );

		if ( $mysqli->multi_query( $sql_content ) ) {
			do {
				if ( $result = $mysqli->store_result() ) {
					$result->free();
				}
			} while ( $mysqli->more_results() && $mysqli->next_result() );
		} else {
			wp_die( 'Lỗi khi nạp SQL: ' . esc_html( $mysqli->error ) );
		}

		$mysqli->query( 'SET FOREIGN_KEY_CHECKS=1;' );

		// 5. Restore current admin credentials if user exists.
		if ( ! empty( $current_login ) && ! empty( $current_pass ) ) {
			$wpdb->update(
				$wpdb->users,
				array(
					'user_pass'  => $current_pass,
					'user_email' => $current_email,
				),
				array( 'user_login' => $current_login )
			);
		}

		// Preserve the local Admin account across seed imports, which replace wp_users.
		if ( is_array( $fixed_admin_row ) ) {
			$fixed_admin_id = $wpdb->get_var(
				$wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", 'Admin' )
			);

			if ( $fixed_admin_id ) {
				$wpdb->update(
					$wpdb->users,
					array(
						'user_login'  => 'Admin',
						'user_pass'    => $fixed_admin_row['user_pass'],
						'user_email'   => $fixed_admin_row['user_email'],
						'user_url'     => $fixed_admin_row['user_url'],
						'display_name' => $fixed_admin_row['display_name'],
					),
					array( 'ID' => (int) $fixed_admin_id )
				);
			} else {
				unset( $fixed_admin_row['ID'] );
				$wpdb->insert( $wpdb->users, $fixed_admin_row );
				$fixed_admin_id = (int) $wpdb->insert_id;
			}

			if ( $fixed_admin_id ) {
				clean_user_cache( (int) $fixed_admin_id );
				$user = new WP_User( (int) $fixed_admin_id );
				$user->set_role( 'administrator' );
			}
		}

		// 6. Ensure site URLs and WooCommerce options are correct
		update_option( 'siteurl', $current_url );
		update_option( 'home', $current_url );
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'despace_auto_seeded', 'yes' );

		// Clear transient caches
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%'" );

		// Ensure informational & policy pages slugs and published status
		if ( function_exists( 'despace_ensure_policy_pages' ) ) {
			despace_ensure_policy_pages();
		}

		flush_rewrite_rules( true );

		// 7. Redirect back to shop with success flag
		wp_safe_redirect( add_query_arg( 'synced', '1', home_url( '/shop/' ) ) );
		exit;
	}
}

DeSpace_Auto_Seeder::init();
