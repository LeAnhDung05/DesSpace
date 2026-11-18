<?php
/**
 * DeSpace blog index (posts page) — featured post + grid + sidebar.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$blog_page_id = (int) get_option( 'page_for_posts' );
$journal_url  = $blog_page_id ? get_permalink( $blog_page_id ) : home_url( '/blog/' );
$active_cat   = is_category() ? get_queried_object() : null;
$active_tag   = is_tag() ? get_queried_object() : null;
$search_q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

if ( $active_cat ) {
	furniva_page_head( furniva_term_label( $active_cat->name, 'category' ), furniva_t( 'The Journal', 'Tạp chí' ) );
} elseif ( $active_tag ) {
	furniva_page_head( '#' . $active_tag->name, furniva_t( 'The Journal', 'Tạp chí' ) );
} else {
	furniva_page_head( furniva_t( 'The Journal', 'Tạp chí' ), furniva_t( 'Blog', 'Bài viết' ) );
}

$blog_intro = $active_cat && ! empty( $active_cat->description )
	? $active_cat->description
	: furniva_t( 'Thoughtful ideas on proportion, sustainable craft and 3D/AR spatial planning for a calmer home.', 'Những bài viết chuyên sâu về tỷ lệ không gian, vật liệu bền vững và ứng dụng 3D/AR trong thiết kế tổ ấm hiện đại.' );

$all_cats = get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
?>
<div class="f-blog-intro f-wrap">
	<p><?php echo esc_html( $blog_intro ); ?></p>
	<?php if ( ! empty( $all_cats ) ) : ?>
		<nav class="f-blog-filterbar" aria-label="<?php echo esc_attr( furniva_t( 'Journal categories', 'Danh mục tạp chí' ) ); ?>">
			<a class="f-blog-pill <?php echo ( ! $active_cat && ! $active_tag ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $journal_url ); ?>">
				<?php echo esc_html( furniva_t( 'All stories', 'Tất cả bài viết' ) ); ?>
			</a>
			<?php foreach ( $all_cats as $cat_item ) : ?>
				<a class="f-blog-pill <?php echo ( $active_cat && (int) $active_cat->term_id === (int) $cat_item->term_id ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_category_link( $cat_item ) ); ?>">
					<?php echo esc_html( furniva_term_label( $cat_item->name, 'category' ) ); ?>
					<span class="f-blog-pill-count"><?php echo (int) $cat_item->count; ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</div>
<div class="f-wrap f-blog-wrap">
	<div class="f-blog-layout">
		<section class="f-blog-main" aria-label="<?php echo esc_attr( furniva_t( 'Latest articles', 'Bài viết mới nhất' ) ); ?>">
			<?php
			$paged = isset( $_GET['journal_page'] )
				? max( 1, absint( wp_unslash( $_GET['journal_page'] ) ) )
				: max( 1, get_query_var( 'paged' ) ?: get_query_var( 'page' ) );
			$query_args = array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 7,
				'paged'          => $paged,
			);
			if ( $active_cat ) {
				$query_args['cat'] = (int) $active_cat->term_id;
			}
			if ( $active_tag ) {
				$query_args['tag_id'] = (int) $active_tag->term_id;
			}
			if ( '' !== $search_q ) {
				$query_args['s'] = $search_q;
			}
			$blog  = new WP_Query( $query_args );
			$first = true;
			if ( $blog->have_posts() ) :
				while ( $blog->have_posts() ) :
					$blog->the_post();
					$cats      = get_the_category();
					$raw_words = preg_split( '/\s+/u', trim( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ) ) );
					$read_mins = max( 2, (int) ceil( ( is_array( $raw_words ) ? count( $raw_words ) : 240 ) / 180 ) );
					$read_text = sprintf( furniva_t( '%d min read', '%d phút đọc' ), $read_mins );
					if ( $first && $paged <= 1 ) :
						?>
						<a class="f-featured-post" href="<?php the_permalink(); ?>">
							<div class="f-post-img"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></div>
							<div class="f-feat-body">
								<div class="f-post-meta">
								<?php if ( $cats ) : ?><span class="f-post-cat"><?php echo esc_html( furniva_term_label( $cats[0]->name, 'category' ) ); ?></span><?php endif; ?>
									<span><?php echo esc_html( get_the_date() ); ?></span>
									<span><?php echo esc_html( $read_text ); ?></span>
								</div>
								<h2><?php the_title(); ?></h2>
								<p><?php echo esc_html( furniva_post_excerpt( get_the_ID(), 32 ) ); ?></p>
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
							<span><?php echo esc_html( $read_text ); ?></span>
						</div>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( furniva_post_excerpt( get_the_ID(), 26 ) ); ?></p>
						<a class="f-readmore" href="<?php the_permalink(); ?>"><?php echo esc_html( furniva_t( 'Read more', 'Đọc tiếp' ) ); ?> →</a>
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
