<?php
/**
 * DeSpace Page Template.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="ds-section" style="padding: 60px 0; min-height: 60vh;">
	<div class="ds-wrap" style="max-width: 980px;">
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header" style="margin-bottom: 30px;">
					<h1 class="entry-title" style="font-size: 38px;"><?php the_title(); ?></h1>
				</header>

				<div class="entry-content" style="line-height: 1.8; font-size: 16px;">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</div>

<?php
get_footer();
