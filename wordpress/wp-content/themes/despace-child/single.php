<?php
/**
 * Furniva single post.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) :
	the_post();
	$blog_page_id = (int) get_option( 'page_for_posts' );
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
			<span><?php echo esc_html( furniva_t( 'by', 'bởi' ) ); ?> <?php echo esc_html( get_the_author() ); ?></span>
		</div>
		<h1><?php the_title(); ?></h1>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="f-post-hero"><?php the_post_thumbnail( 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
		<?php endif; ?>
		<div class="entry-content f-article-content">
			<?php the_content(); ?>
		</div>

		<?php
		$related = new WP_Query( array(
			'post_type'      => 'post',
			'posts_per_page' => 3,
			'post__not_in'   => array( get_the_ID() ),
			'category__in'   => wp_get_post_categories( get_the_ID() ),
		) );
		if ( $related->have_posts() ) :
			?>
			<div class="f-related-posts">
				<h3><?php echo esc_html( furniva_t( 'Related articles', 'Bài viết liên quan' ) ); ?></h3>
				<div class="f-blog-grid">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<a class="f-blog-card" href="<?php the_permalink(); ?>">
							<div class="f-blog-img"><?php the_post_thumbnail( 'medium_large' ); ?></div>
							<div class="f-blog-meta"><span><?php echo esc_html( get_the_date() ); ?></span> · <span><?php echo esc_html( get_the_author() ); ?></span></div>
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
