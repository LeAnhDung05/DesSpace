<?php
/**
 * WooCommerce layout wrapper.
 *
 * @package DeSpaceStaging
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="ds-page">
	<div class="ds-container">
		<?php woocommerce_content(); ?>
	</div>
</main>
<?php get_footer(); ?>
