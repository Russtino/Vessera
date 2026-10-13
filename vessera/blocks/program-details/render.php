<?php
/**
 * Front-end output for the Program Details block.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_post_id = Vessera_Blocks::context_post_id( $block, Vessera_Post_Types::PROGRAM );
if ( ! $vessera_post_id ) {
	return;
}

// Each credential with its details, e.g. "Associate of Applied Science: 4 semesters, 64 credits".
$vessera_credential_html = '';
$vessera_credentials     = Vessera_Blocks::program_credentials( $vessera_post_id );

if ( $vessera_credentials ) {
	$vessera_credential_html = '<ul class="vessera-details__list">';

	foreach ( $vessera_credentials as $vessera_credential ) {
		$vessera_parts = array();
		if ( '' !== $vessera_credential['length'] ) {
			$vessera_parts[] = $vessera_credential['length'];
		}
		if ( $vessera_credential['credits'] > 0 ) {
			$vessera_parts[] = sprintf(
				/* translators: %s: credit hours */
				_n( '%s credit', '%s credits', (int) ceil( $vessera_credential['credits'] ), 'vessera' ),
				Vessera_Blocks::format_credits( $vessera_credential['credits'] )
			);
		}

		$vessera_item = $vessera_parts
			? sprintf(
				/* translators: 1: credential type, 2: details such as "4 semesters, 64 credits" */
				__( '%1$s: %2$s', 'vessera' ),
				$vessera_credential['term']->name,
				implode( ', ', $vessera_parts )
			)
			: $vessera_credential['term']->name;

		$vessera_credential_html .= '<li>' . esc_html( $vessera_item );
		if ( '' !== $vessera_credential['description'] ) {
			$vessera_credential_html .= '<span class="vessera-details__description">' . esc_html( $vessera_credential['description'] ) . '</span>';
		}
		if ( $vessera_credential['pathways'] ) {
			$vessera_credential_html .= '<ul class="vessera-details__pathways" aria-label="' . esc_attr__( 'Pathways', 'vessera' ) . '">';
			foreach ( $vessera_credential['pathways'] as $vessera_pathway ) {
				$vessera_text = '' !== $vessera_pathway['description']
					/* translators: 1: pathway name, 2: pathway description */
					? sprintf( __( '%1$s pathway: %2$s', 'vessera' ), $vessera_pathway['name'], $vessera_pathway['description'] )
					/* translators: %s: pathway name */
					: sprintf( __( '%s pathway', 'vessera' ), $vessera_pathway['name'] );
				$vessera_credential_html .= '<li>' . esc_html( $vessera_text ) . '</li>';
			}
			$vessera_credential_html .= '</ul>';
		}
		$vessera_credential_html .= '</li>';
	}

	$vessera_credential_html .= '</ul>';
}

// "11.1006 · Computer Support Specialist", either part optional.
$vessera_cip = implode(
	' · ',
	array_filter(
		array(
			(string) get_post_meta( $vessera_post_id, '_vessera_cip_code', true ),
			(string) get_post_meta( $vessera_post_id, '_vessera_cip_title', true ),
		),
		'strlen'
	)
);

$vessera_notice = trim( (string) get_post_meta( $vessera_post_id, '_vessera_enrollment_notice', true ) );

// Label => HTML. Every value is escaped before it goes in; empty rows are skipped.
$vessera_rows = array(
	__( 'Credentials', 'vessera' ) => $vessera_credential_html,
	__( 'Start dates', 'vessera' ) => esc_html( (string) get_post_meta( $vessera_post_id, '_vessera_start_terms', true ) ),
	__( 'Delivery', 'vessera' )    => esc_html( Vessera_Blocks::term_list( $vessera_post_id, Vessera_Taxonomies::DELIVERY ) ),
	__( 'Department', 'vessera' )  => esc_html( Vessera_Blocks::term_list( $vessera_post_id, Vessera_Taxonomies::DEPARTMENT ) ),
	__( 'Career area', 'vessera' ) => esc_html( Vessera_Blocks::term_list( $vessera_post_id, Vessera_Taxonomies::CAREER ) ),
	__( 'CIP code', 'vessera' )    => esc_html( $vessera_cip ),
);

$vessera_rows = array_filter( $vessera_rows, 'strlen' );
if ( ! $vessera_rows && '' === $vessera_notice ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( '' !== $vessera_notice ) : ?>
		<div class="vessera-notice"><?php echo wpautop( esc_html( $vessera_notice ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then paragraphs added. ?></div>
	<?php endif; ?>
	<?php if ( $vessera_rows ) : ?>
	<dl class="vessera-details">
		<?php foreach ( $vessera_rows as $vessera_label => $vessera_html ) : ?>
			<div>
				<dt><?php echo esc_html( $vessera_label ); ?></dt>
				<dd><?php echo $vessera_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
	<?php endif; ?>
</div>
