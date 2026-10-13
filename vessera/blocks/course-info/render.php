<?php
/**
 * Front-end output for the Course Info block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_post_id = Vessera_Blocks::context_post_id( $block, Vessera_Post_Types::COURSE );
if ( ! $vessera_post_id ) {
	return;
}

$vessera_link    = ! empty( $attributes['linkPrerequisites'] );
$vessera_code    = Vessera_Blocks::course_code( $vessera_post_id );
$vessera_credits = Vessera_Blocks::course_credits( $vessera_post_id );
$vessera_notes   = (string) get_post_meta( $vessera_post_id, '_vessera_prerequisite_notes', true );
$vessera_prereqs = Vessera_Blocks::get_courses( get_post_meta( $vessera_post_id, '_vessera_prerequisites', true ) );

// Build the prerequisite list; each item is already escaped.
$vessera_prereq_items = array();
foreach ( $vessera_prereqs as $vessera_prereq ) {
	$vessera_label          = esc_html( Vessera_Blocks::course_label( $vessera_prereq ) );
	$vessera_prereq_items[] = $vessera_link
		? '<a href="' . esc_url( get_permalink( $vessera_prereq ) ) . '">' . $vessera_label . '</a>'
		: $vessera_label;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<dl class="vessera-details">
		<?php if ( $vessera_code ) : ?>
			<div>
				<dt><?php esc_html_e( 'Course code', 'vessera' ); ?></dt>
				<dd><?php echo esc_html( $vessera_code ); ?></dd>
			</div>
		<?php endif; ?>
		<?php if ( $vessera_credits > 0 ) : ?>
			<div>
				<dt><?php esc_html_e( 'Credit hours', 'vessera' ); ?></dt>
				<dd><?php echo esc_html( Vessera_Blocks::format_credits( $vessera_credits ) ); ?></dd>
			</div>
		<?php endif; ?>
		<?php
		$vessera_hours = array(
			'_vessera_lecture_hours' => __( 'Lecture hours', 'vessera' ),
			'_vessera_lab_hours'     => __( 'Lab hours', 'vessera' ),
			'_vessera_contact_hours' => __( 'Contact hours', 'vessera' ),
		);
		foreach ( $vessera_hours as $vessera_key => $vessera_label ) :
			$vessera_value = (float) get_post_meta( $vessera_post_id, $vessera_key, true );
			if ( $vessera_value <= 0 ) {
				continue;
			}
			?>
			<div>
				<dt><?php echo esc_html( $vessera_label ); ?></dt>
				<dd><?php echo esc_html( Vessera_Blocks::format_credits( $vessera_value ) ); ?></dd>
			</div>
		<?php endforeach; ?>
		<div>
			<dt><?php esc_html_e( 'Prerequisites', 'vessera' ); ?></dt>
			<dd>
				<?php if ( $vessera_prereq_items ) : ?>
					<ul class="vessera-details__list">
						<?php foreach ( $vessera_prereq_items as $vessera_item ) : ?>
							<li><?php echo $vessera_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $vessera_notes ) : ?>
					<p class="vessera-details__note"><?php echo esc_html( $vessera_notes ); ?></p>
				<?php endif; ?>
				<?php
				if ( ! $vessera_prereq_items && ! $vessera_notes ) {
					esc_html_e( 'None', 'vessera' );
				}
				?>
			</dd>
		</div>
	</dl>
</div>
