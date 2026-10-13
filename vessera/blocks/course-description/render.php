<?php
/**
 * Front-end output for the Course Description block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_post_id = Vessera_Blocks::context_post_id( $block, Vessera_Post_Types::COURSE );
if ( ! $vessera_post_id ) {
	return;
}

$vessera_description = trim( (string) get_post_meta( $vessera_post_id, '_vessera_description', true ) );
if ( '' === $vessera_description ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php echo wpautop( esc_html( $vessera_description ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then paragraphs added. ?>
</div>
