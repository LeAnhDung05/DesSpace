<?php
/**
 * Furniva blog index (posts page) — featured post + grid + sidebar.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

furniva_page_head( furniva_t( 'The Journal', 'Tạp chí' ), furniva_t( 'Blog', 'Bài viết' ) );
?>
<div class="f-blog-intro f-wrap">
	<p><?php echo esc_html( furniva_t( 'Thoughtful ideas for furnishing a calmer, more considered home.', 'Những ý tưởng chọn nội thất để tạo nên một không gian sống an yên và có chủ đích.' ) ); ?></p>
</div>
<div class="f-wrap f-blog-wrap">
	<div class="f-blog-layout">
		<section class="f-blog-main" aria-label="<?php echo esc_attr( furniva_t( 'Latest articles', 'Bài viết mới nhất' ) ); ?>">
			<?php
			$paged = isset( $_GET['journal_page'] )
				? max( 1, absint( wp_unslash( $_GET['journal_page'] ) ) )
				: max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) );
			$blog  = new WP_Query( array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'paged'          => $paged,
			) );
			$first = true;
			if ( $blog->have_posts() ) :
				while ( $blog->have_posts() ) :
					$blog->the_post();
					$cats = get_the_category();
					if ( $first && $paged <= 1 ) :
						?>
						<a class="f-featured-post" href="<?php the_permalink(); ?>">
							<div class="f-post-img"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
							<div class="f-feat-body">
								<div class="f-post-meta">
								<?php if ( $cats ) : ?><span class="f-post-cat"><?php echo esc_html( furniva_term_label( $cats[0]->name, 'category' ) ); ?></span><?php endif; ?>
									<span><?php echo esc_html( get_the_date() ); ?></span>
									<span><?php echo esc_html( get_the_author() ); ?></span>
								</div>
								<h2><?php the_title(); ?></h2>
								<p><?php echo esc_html( furniva_post_excerpt( get_the_ID(), 30 ) ); ?></p>
								<span class="f-readmore"><?php echo esc_html( furniva_t( 'Read article', 'Đọc bài viết' ) ); ?> →</span>
							</div>
						</a>
						<?php
						$first = false;
						continue;
					endif;
					?>
					<article class="f-post-card">
						<a class="f-post-img" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?></a>
						<div class="f-post-meta">
						<?php if ( $cats ) : ?><span class="f-post-cat"><?php echo esc_html( furniva_term_label( $cats[0]->name, 'category' ) ); ?></span><?php endif; ?>
							<span><?php echo esc_html( get_the_date() ); ?></span>
							<span><?php echo esc_html( get_the_author() ); ?></span>
						</div>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( furniva_post_excerpt( get_the_ID(), 26 ) ); ?></p>
						<a class="f-readmore" href="<?php the_permalink(); ?>"><?php echo esc_html( furniva_t( 'Read more', 'Đọc tiếp' ) ); ?></a>
					</article>
					<?php
				endwhile;
				?>
				<?php if ( $blog->max_num_pages > 1 ) : ?>
					<nav class="f-blog-pagination" aria-label="<?php echo esc_attr( furniva_t( 'Blog pages', 'Các trang bài viết' ) ); ?>">
						<?php
						$pagination_base = add_query_arg( 'journal_page', 999999999, get_permalink( get_option( 'page_for_posts' ) ) );
						echo paginate_links( array(
							'total'     => $blog->max_num_pages,
							'current'   => $paged,
							'base'      => str_replace( '999999999', '%#%', $pagination_base ),
							'format'    => '',
							'prev_text' => '←',
							'next_text' => '→',
							'type'      => 'plain',
						) ); ?>
					</nav>
				<?php endif; ?>
				<?php
			else :
				echo '<div class="f-blog-empty"><h2>' . esc_html( furniva_t( 'No articles yet', 'Chưa có bài viết' ) ) . '</h2><p>' . esc_html( furniva_t( 'Our journal is being prepared. Please check back soon.', 'Tạp chí đang được chuẩn bị. Mời bạn quay lại sau.' ) ) . '</p></div>';
			endif;
			wp_reset_postdata();
			?>
		</section>

		<aside class="f-sidebar">
			<div class="f-widget">
				<h4><?php echo esc_html( furniva_t( 'Search', 'Tìm kiếm' ) ); ?></h4>
				<form class="f-search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<input type="search" name="s" placeholder="<?php echo esc_attr( furniva_t( 'Search articles…', 'Tìm bài viết…' ) ); ?>" />
					<button class="f-btn f-btn--primary" type="submit" style="padding:10px 16px"><?php echo esc_html( furniva_t( 'Search', 'Tìm' ) ); ?></button>
					<input type="hidden" name="post_type" value="post" />
				</form>
			</div>
			<div class="f-widget">
				<h4><?php echo esc_html( furniva_t( 'Categories', 'Danh mục' ) ); ?></h4>
				<ul>
					<?php foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) : ?>
						<li><a href="<?php echo esc_url( get_category_link( $c ) ); ?>"><?php echo esc_html( furniva_term_label( $c->name, 'category' ) ); ?> <span>(<?php echo (int) $c->count; ?>)</span></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="f-widget">
				<h4><?php echo esc_html( furniva_t( 'Popular products', 'Sản phẩm nổi bật' ) ); ?></h4>
				<?php echo do_shortcode( '[best_selling_products limit="3" columns="1" class="f-products"]' ); ?>
			</div>
			<div class="f-widget">
				<h4><?php echo esc_html( furniva_t( 'Tags', 'Thẻ' ) ); ?></h4>
				<div class="f-tag-cloud"><?php wp_tag_cloud( array( 'smallest' => 12, 'largest' => 12, 'unit' => 'px' ) ); ?></div>
			</div>
		</aside>
	</div>
</div>

<?php get_footer(); ?>
