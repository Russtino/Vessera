<?php
/**
 * Front-end output for the Program List block (outcomes, careers, or certifications).
 * Renders nothing, heading included, when the list is empty.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_post_id = Vessera_Blocks::context_post_id( $block, Vessera_Post_Types::PROGRAM );
if ( ! $vessera_post_id ) {
	return;
}

$vessera_type = isset( $attributes['list'] ) ? $attributes['list'] : 'outcomes';

// Groups of items, each with an optional label: [ [ label, items ], ... ].
$vessera_groups = array();

switch ( $vessera_type ) {
	case 'careers':
		$vessera_default = __( 'Career opportunities', 'vessera' );
		$vessera_groups[] = array( '', Vessera_Blocks::get_list( $vessera_post_id, '_vessera_careers' ) );
		break;

	case 'certifications':
		$vessera_default  = __( 'Industry certifications', 'vessera' );
		$vessera_earned   = Vessera_Blocks::get_list( $vessera_post_id, '_vessera_certifications' );
		$vessera_optional = Vessera_Blocks::get_list( $vessera_post_id, '_vessera_certifications_optional' );

		// Labels are only needed to tell the two groups apart.
		if ( $vessera_earned ) {
			$vessera_groups[] = array( $vessera_optional ? __( 'Graduates earn:', 'vessera' ) : '', $vessera_earned );
		}
		if ( $vessera_optional ) {
			$vessera_groups[] = array( $vessera_earned ? __( 'Graduates can also earn:', 'vessera' ) : __( 'Graduates can earn:', 'vessera' ), $vessera_optional );
		}
		break;

	default:
		$vessera_default  = __( 'Program outcomes', 'vessera' );
		$vessera_groups[] = array( '', Vessera_Blocks::get_list( $vessera_post_id, '_vessera_outcomes' ) );
}

$vessera_groups = array_filter(
	$vessera_groups,
	function ( $group ) {
		return ! empty( $group[1] );
	}
);

if ( ! $vessera_groups ) {
	return;
}

$vessera_heading = trim( isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '' );
$vessera_heading = '' !== $vessera_heading ? $vessera_heading : $vessera_default;
$vessera_tag     = 'h' . min( 6, max( 2, isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 2 ) );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'is-list-' . sanitize_html_class( $vessera_type ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<<?php echo $vessera_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="vessera-program-list__heading"><?php echo esc_html( $vessera_heading ); ?></<?php echo $vessera_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php foreach ( $vessera_groups as $vessera_group ) : ?>
		<?php if ( '' !== $vessera_group[0] ) : ?>
			<p class="vessera-program-list__label"><?php echo esc_html( $vessera_group[0] ); ?></p>
		<?php endif; ?>
		<ul class="vessera-program-list__items">
			<?php foreach ( $vessera_group[1] as $vessera_item ) : ?>
				<li><?php echo esc_html( $vessera_item ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
</div>
