<?php
/**
 * Fallback template.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="ds-page"><div class="ds-container">
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1><?php the_excerpt(); ?></article>
	<?php endwhile; the_posts_pagination(); else : ?>
		<h1>Chưa có nội dung</h1>
	<?php endif; ?>
</div></main>
<?php get_footer(); ?>
