<?php
/**
 * DeSpace Child Theme fallback template (index.php).
 *
 * Ensures archive, general search, and fallback routes render cleanly
 * within the DeSpace layout even when no more specific template matches.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$page_title = '';
if ( is_search() ) {
	$page_title = sprintf(
		/* translators: %s: search query */
		function_exists( 'furniva_t' ) ? furniva_t( 'Search results for: "%s"', 'Kết quả tìm kiếm cho: "%s"' ) : __( 'Search results for: "%s"', 'despace-child' ),
		get_search_query()
	);
} elseif ( is_archive() ) {
	$page_title = get_the_archive_title();
} else {
	$page_title = get_bloginfo( 'name' );
}
?>

<section class="f-wrap" style="padding: 48px 24px 72px;">
	<header style="margin-bottom: 32px;">
		<h1 style="font-family: var(--f-font-editorial, serif); font-size: clamp(1.75rem, 3vw, 2.5rem); margin: 0 0 10px; color: var(--despace-text, #262522);">
			<?php echo wp_kses_post( $page_title ); ?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> style="background: var(--despace-surface, #fff); border: 1px solid var(--despace-border, #E7E0D7); border-radius: var(--radius-card, 16px); overflow: hidden; display: flex; flex-direction: column;">
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php echo esc_url( function_exists( 'furniva_url' ) ? furniva_url( get_permalink() ) : get_permalink() ); ?>" style="display: block; aspect-ratio: 4/3; overflow: hidden;">
							<?php the_post_thumbnail( 'medium_large', array( 'style' => 'width:100%;height:100%;object-fit:cover;', 'loading' => 'lazy' ) ); ?>
						</a>
					<?php endif; ?>
					<div style="padding: 20px; display: flex; flex-direction: column; gap: 10px; flex: 1;">
						<h2 style="font-size: 1.1rem; margin: 0; line-height: 1.4;">
							<a href="<?php echo esc_url( function_exists( 'furniva_url' ) ? furniva_url( get_permalink() ) : get_permalink() ); ?>" style="color: var(--despace-text, #262522); text-decoration: none;">
								<?php the_title(); ?>
							</a>
						</h2>
						<div style="font-size: 0.9rem; color: var(--despace-muted, #69645D); line-height: 1.6;">
							<?php the_excerpt(); ?>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<div style="margin-top: 36px;">
			<?php the_posts_pagination(); ?>
		</div>
	<?php else : ?>
		<div class="f-no-results" style="padding: 48px 24px; text-align: center;">
			<h2 class="f-no-results-title">
				<?php echo esc_html( function_exists( 'furniva_t' ) ? furniva_t( 'No content found', 'Không tìm thấy nội dung phù hợp' ) : __( 'No content found', 'despace-child' ) ); ?>
			</h2>
			<p class="f-no-results-desc">
				<?php echo esc_html( function_exists( 'furniva_t' ) ? furniva_t( 'Try searching with another keyword or explore our furniture collection.', 'Hãy thử tìm kiếm với từ khóa khác hoặc khám phá bộ sưu tập nội thất của DeSpace.' ) : __( 'Try searching with another keyword or explore our furniture collection.', 'despace-child' ) ); ?>
			</p>
			<a class="f-btn f-btn--primary" href="<?php echo esc_url( function_exists( 'furniva_url' ) ? furniva_url( '/shop/' ) : home_url( '/shop/' ) ); ?>">
				<?php echo esc_html( function_exists( 'furniva_t' ) ? furniva_t( 'Explore Shop', 'Khám phá Cửa hàng' ) : __( 'Explore Shop', 'despace-child' ) ); ?>
			</a>
		</div>
	<?php endif; ?>
</section>

<?php
get_footer();
