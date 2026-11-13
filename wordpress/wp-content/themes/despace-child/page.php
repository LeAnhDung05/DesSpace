<?php
/**
 * Default page template.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="ds-page">
	<div class="ds-container">
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="ds-eyebrow">DESPACE / INFORMATION</div>
			<h1><?php the_title(); ?></h1>
			<div class="entry-content"><?php the_content(); ?></div>
		<?php endwhile; ?>
	</div>
</main>
<?php get_footer(); ?>
