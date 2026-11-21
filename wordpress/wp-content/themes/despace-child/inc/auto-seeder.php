<?php
/**
 * DeSpace Local Auto Seeder & Database Synchronizer.
 *
 * Strictly gated to local/development environments with explicit opt-in
 * and POST-only execution to prevent accidental production database wipes.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeSpace_Auto_Seeder {

	/**
	 * Check whether auto-seeding is permitted in the current environment.
	 */
	public static function is_environment_allowed(): bool {
		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( ! in_array( $env, array( 'local', 'development' ), true ) ) {
			return false;
		}
		if ( defined( 'DESPACE_ENABLE_AUTO_SEEDER' ) ) {
			return (bool) DESPACE_ENABLE_AUTO_SEEDER;
		}
		return 'local' === $env;
	}

	public static function init() {
		if ( ! self::is_environment_allowed() ) {
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'admin_post_despace_run_auto_sync', array( __CLASS__, 'handle_auto_sync' ) );
	}

	/**
	 * Check if current local site needs database seeding.
	 */
	public static function needs_seeding(): bool {
		if ( ! self::is_environment_allowed() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$product_count = wp_count_posts( 'product' );
		$published     = isset( $product_count->publish ) ? intval( $product_count->publish ) : 0;

		return ( $published < 10 || isset( $_GET['despace_sync_prompt'] ) );
	}

	/**
	 * Render POST-protected seeding notice in WordPress Admin (local only).
	 */
	public static function render_admin_notice() {
		if ( ! self::needs_seeding() && ! isset( $_GET['synced'] ) ) {
			return;
		}

		if ( isset( $_GET['synced'] ) && '1' === $_GET['synced'] ) {
			?>
			<div class="notice notice-success is-dismissible" style="padding: 14px 18px; border-left: 4px solid #10b981; background: #ecfdf5;">
				<h3 style="margin: 0 0 6px 0; color: #065f46; font-size: 16px;">Đồng bộ cơ sở dữ liệu DeSpace (Local) thành công!</h3>
				<p style="margin: 0; color: #047857; font-size: 14px;">
					Toàn bộ <strong>40 sản phẩm chuẩn</strong> và thiết lập cửa hàng đã được nạp.
					<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="button button-secondary" style="margin-left: 10px; font-weight: 600;">Xem Trang Cửa Hàng</a>
				</p>
			</div>
			<?php
			return;
		}
		?>
		<div class="notice notice-warning" style="padding: 16px 20px; border-left: 4px solid #f59e0b; background: #fffbeb; margin-top: 20px;">
			<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
				<div>
					<h3 style="margin: 0 0 6px 0; color: #92400e; font-size: 16px;">
						<strong>DeSpace Local Auto-Setup: Chưa phát hiện sản phẩm mẫu</strong>
					</h3>
					<p style="margin: 0; color: #78350f; font-size: 14px;">
						Thao tác này chỉ khả dụng trên môi trường Local và sẽ ghi đè cơ sở dữ liệu hiện tại bằng bản seed ngoài webroot (<code>database/</code>).
					</p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('CẢNH BÁO: Thao tác này sẽ GHI ĐÈ toàn bộ cơ sở dữ liệu local hiện tại. Tiếp tục?');">
					<input type="hidden" name="action" value="despace_run_auto_sync" />
					<?php wp_nonce_field( 'despace_auto_sync_nonce' ); ?>
					<button type="submit" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 600;">
						Đồng bộ 40 sản phẩm (Local POST)
					</button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Execute the automated database synchronization (POST-only, local-only).
	 */
	public static function handle_auto_sync() {
		if ( ! self::is_environment_allowed() ) {
			wp_die( 'Chức năng Auto Seeder bị khóa ngoài môi trường local.', 403 );
		}

		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( 'Yêu cầu phương thức POST để thực thi đồng bộ cơ sở dữ liệu.', 405 );
		}

		check_admin_referer( 'despace_auto_sync_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bạn không có quyền thực hiện thao tác này.', 403 );
		}

		global $wpdb;

		$current_user  = wp_get_current_user();
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

		// Locate SQL seed file strictly outside public webroot (database/).
		$repo_root = dirname( get_stylesheet_directory(), 4 );
		$root_gz   = $repo_root . '/database/despace_seed_data.sql.gz';
		$root_sql  = $repo_root . '/database/despace_seed_data.sql';

		$sql_content = '';
		if ( file_exists( $root_gz ) ) {
			$raw = file_get_contents( $root_gz );
			if ( false !== $raw ) {
				$decoded     = gzdecode( $raw );
				$sql_content = false !== $decoded ? $decoded : '';
			}
		} elseif ( file_exists( $root_sql ) ) {
			$raw         = file_get_contents( $root_sql );
			$sql_content = false !== $raw ? $raw : '';
		}

		if ( '' === $sql_content ) {
			wp_die( 'Không tìm thấy tệp dữ liệu trong thư mục database/ ngoài webroot.' );
		}

		$mysqli = $wpdb->dbh;
		if ( ! ( $mysqli instanceof mysqli ) ) {
			wp_die( 'Yêu cầu kết nối MySQLi để thực thi nạp dữ liệu.' );
		}

		$mysqli->query( 'SET FOREIGN_KEY_CHECKS=0;' );

		if ( $mysqli->multi_query( $sql_content ) ) {
			do {
				if ( $result = $mysqli->store_result() ) {
					$result->free();
				}
				if ( $mysqli->errno ) {
					$err = $mysqli->error;
					$mysqli->query( 'SET FOREIGN_KEY_CHECKS=1;' );
					wp_die( 'Lỗi khi nạp SQL: ' . esc_html( $err ) );
				}
			} while ( $mysqli->more_results() && $mysqli->next_result() );
		} else {
			$err = $mysqli->error;
			$mysqli->query( 'SET FOREIGN_KEY_CHECKS=1;' );
			wp_die( 'Lỗi khi nạp SQL: ' . esc_html( $err ) );
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
						'user_login'   => 'Admin',
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

		// 6. Ensure site URLs and WooCommerce options are correct.
		update_option( 'siteurl', $current_url );
		update_option( 'home', $current_url );
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'despace_auto_seeded', 'yes' );

		$transient_like = '%' . $wpdb->esc_like( '_transient_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like ) );

		// Ensure informational & policy pages slugs and published status (preserving admin edits)
		if ( function_exists( 'despace_ensure_policy_pages' ) ) {
			despace_ensure_policy_pages( false );
		}

		flush_rewrite_rules( true );

		wp_safe_redirect( add_query_arg( 'synced', '1', admin_url( 'index.php' ) ) );
		exit;
	}
}

DeSpace_Auto_Seeder::init();
