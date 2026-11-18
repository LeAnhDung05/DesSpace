<?php
/**
 * DeSpace single post.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

	while ( have_posts() ) :
		the_post();
		$blog_page_id = (int) get_option( 'page_for_posts' );
		$raw_words    = preg_split( '/\s+/u', trim( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ) );
		$read_mins    = max( 2, (int) ceil( ( is_array( $raw_words ) ? count( $raw_words ) : 240 ) / 180 ) );
		$read_text    = sprintf( furniva_t( '%d min read', '%d phút đọc' ), $read_mins );
		?>
		<div class="f-page-head f-article-breadcrumb-head">
			<div class="f-wrap">
				<nav class="f-breadcrumbs" aria-label="<?php echo esc_attr( furniva_t( 'Breadcrumb', 'Điều hướng' ) ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( furniva_t( 'Home', 'Trang chủ' ) ); ?></a><span class="sep">/</span>
					<a href="<?php echo esc_url( $blog_page_id ? get_permalink( $blog_page_id ) : home_url( '/blog/' ) ); ?>"><?php echo esc_html( furniva_t( 'The Journal', 'Tạp chí' ) ); ?></a><span class="sep">/</span>
					<span><?php the_title(); ?></span>
				</nav>
			</div>
		</div>
		<article class="f-single-post f-wrap">
			<header class="f-single-head">
			<div class="f-post-meta">
				<?php $cats = get_the_category(); ?>
				<?php if ( $cats ) : ?><span class="f-post-cat"><?php echo esc_html( furniva_term_label( $cats[0]->name, 'category' ) ); ?></span><?php endif; ?>
				<span><?php echo esc_html( get_the_date() ); ?></span>
				<span><?php echo esc_html( $read_text ); ?></span>
				<span><?php echo esc_html( furniva_t( 'by Studio DeSpace', 'bởi Studio DeSpace' ) ); ?></span>
			</div>
			<h1><?php the_title(); ?></h1>
			</header>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="f-post-hero"><?php the_post_thumbnail( 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
			<?php endif; ?>
			<div class="entry-content f-article-content">
				<?php the_content(); ?>
				<aside class="f-article-cta" aria-label="<?php echo esc_attr( furniva_t( 'Try 3D & AR in your home', 'Thử nghiệm 3D & AR trong nhà bạn' ) ); ?>">
					<div>
						<span class="f-kicker"><?php echo esc_html( furniva_t( 'DeSpace Interactive Studio', 'Trải nghiệm thực tế DeSpace' ) ); ?></span>
						<h3><?php echo esc_html( furniva_t( 'See every piece in interactive 3D or place it in your room at 1:1 scale.', 'Xem từng mẫu tủ kệ ở chế độ 3D đóng/mở cánh hoặc đặt thử AR tỷ lệ 1:1 ngay trong phòng.' ) ); ?></h3>
					</div>
					<div class="f-article-cta-actions">
						<a class="f-btn f-btn--primary" href="<?php echo esc_url( home_url( '/cua-hang/' ) ); ?>"><?php echo esc_html( furniva_t( 'Browse 3D Collection', 'Xem Bộ sưu tập 3D' ) ); ?></a>
						<a class="f-btn f-btn--outline" href="<?php echo esc_url( home_url( '/ar-room-visualizer/' ) ); ?>"><?php echo esc_html( furniva_t( 'Open AR Visualizer', 'Mở AR Room Visualizer' ) ); ?></a>
					</div>
				</aside>
			</div>

		<?php
		$current_id  = get_the_ID();
		$related_ids = get_posts( array(
			'post_type'      => 'post',
			'posts_per_page' => 3,
			'post__not_in'   => array( $current_id ),
			'category__in'   => wp_get_post_categories( $current_id ),
			'fields'         => 'ids',
		) );
		if ( count( $related_ids ) < 3 ) {
			$extra_ids = get_posts( array(
				'post_type'      => 'post',
				'posts_per_page' => 3 - count( $related_ids ),
				'post__not_in'   => array_merge( array( $current_id ), $related_ids ),
				'fields'         => 'ids',
			) );
			$related_ids = array_merge( $related_ids, $extra_ids );
		}
		if ( ! empty( $related_ids ) ) :
			$related = new WP_Query( array(
				'post_type' => 'post',
				'post__in'  => $related_ids,
				'orderby'   => 'post__in',
			) );
			?>
			<div class="f-related-posts">
				<h3><?php echo esc_html( furniva_t( 'Related articles', 'Bài viết liên quan' ) ); ?></h3>
				<div class="f-blog-grid">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<a class="f-blog-card" href="<?php the_permalink(); ?>">
							<div class="f-blog-img"><?php the_post_thumbnail( 'medium_large' ); ?></div>
							<div class="f-blog-meta"><span><?php echo esc_html( get_the_date() ); ?></span> · <span><?php echo esc_html( furniva_t( 'Studio DeSpace', 'Studio DeSpace' ) ); ?></span></div>
							<h3><?php the_title(); ?></h3>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			</div>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
