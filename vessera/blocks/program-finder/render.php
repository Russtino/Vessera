<?php
/**
 * Front-end output for the Program Finder block.
 *
 * Every published program is rendered as a card, so the full list works
 * without JavaScript. The view script reveals the search form and filters
 * the cards in the browser, keeping the current filters in the URL.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_programs = get_posts(
	array(
		'post_type'      => Vessera_Post_Types::PROGRAM,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

if ( ! $vessera_programs ) {
	return;
}

// URL parameter => taxonomy, label, and the attribute that switches the filter on.
$vessera_filters = array(
	'credential' => array( Vessera_Taxonomies::CREDENTIAL, __( 'Credential', 'vessera' ), 'showCredentialFilter' ),
	'career'     => array( Vessera_Taxonomies::CAREER, __( 'Career area', 'vessera' ), 'showCareerFilter' ),
	'delivery'   => array( Vessera_Taxonomies::DELIVERY, __( 'Delivery', 'vessera' ), 'showDeliveryFilter' ),
	'department' => array( Vessera_Taxonomies::DEPARTMENT, __( 'Department', 'vessera' ), 'showDepartmentFilter' ),
);

$vessera_options = array_fill_keys( array_keys( $vessera_filters ), array() ); // param => [ slug => name ]
$vessera_cards   = array();

foreach ( $vessera_programs as $vessera_program ) {
	$vessera_data   = array();
	$vessera_search = array( get_the_title( $vessera_program ) );

	foreach ( $vessera_filters as $vessera_param => $vessera_filter ) {
		$vessera_terms = get_the_terms( $vessera_program, $vessera_filter[0] );
		$vessera_slugs = array();

		if ( $vessera_terms && ! is_wp_error( $vessera_terms ) ) {
			foreach ( $vessera_terms as $vessera_term ) {
				$vessera_slugs[]                                 = $vessera_term->slug;
				$vessera_options[ $vessera_param ][ $vessera_term->slug ] = $vessera_term->name;
				$vessera_search[]                                = $vessera_term->name;
			}
		}
		$vessera_data[ $vessera_param ] = implode( ' ', $vessera_slugs );
	}

	$vessera_excerpt  = Vessera_Blocks::program_summary( $vessera_program );
	$vessera_search[] = $vessera_excerpt;

	$vessera_cards[] = array(
		'post'        => $vessera_program,
		'excerpt'     => $vessera_excerpt,
		'credentials' => Vessera_Blocks::program_credentials( $vessera_program->ID ),
		'notice'      => trim( (string) get_post_meta( $vessera_program->ID, '_vessera_enrollment_notice', true ) ),
		'data'        => $vessera_data,
		// Lowercase, accent-free text the script matches search words against.
		'search'      => mb_strtolower( remove_accents( wp_strip_all_tags( implode( ' ', $vessera_search ) ) ) ),
	);
}

$vessera_uid         = wp_unique_id( 'vessera-finder-' );
$vessera_heading_tag = 'h' . min( 6, max( 2, (int) $attributes['headingLevel'] ) );
$vessera_count       = count( $vessera_cards );

// A filter is only useful with at least two choices.
$vessera_active_filters = array();
foreach ( $vessera_filters as $vessera_param => $vessera_filter ) {
	if ( ! empty( $attributes[ $vessera_filter[2] ] ) && count( $vessera_options[ $vessera_param ] ) > 1 ) {
		asort( $vessera_options[ $vessera_param ], SORT_NATURAL | SORT_FLAG_CASE );
		$vessera_active_filters[ $vessera_param ] = $vessera_filter[1];
	}
}
$vessera_has_form = ! empty( $attributes['showSearch'] ) || $vessera_active_filters;
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-count-one="<?php /* translators: %d: number of results (one) */ echo esc_attr__( '%d program found', 'vessera' ); ?>"
	data-count-other="<?php /* translators: %d: number of results */ echo esc_attr__( '%d programs found', 'vessera' ); ?>"
>
	<?php if ( $vessera_has_form ) : ?>
		<form class="vessera-finder__form" role="search" aria-label="<?php esc_attr_e( 'Find a program', 'vessera' ); ?>" hidden>
			<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
				<div class="vessera-finder__field vessera-finder__field--search">
					<label for="<?php echo esc_attr( $vessera_uid . '-q' ); ?>"><?php esc_html_e( 'Search programs', 'vessera' ); ?></label>
					<input type="search" id="<?php echo esc_attr( $vessera_uid . '-q' ); ?>" name="q" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php foreach ( $vessera_active_filters as $vessera_param => $vessera_label ) : ?>
				<div class="vessera-finder__field">
					<label for="<?php echo esc_attr( $vessera_uid . '-' . $vessera_param ); ?>"><?php echo esc_html( $vessera_label ); ?></label>
					<select id="<?php echo esc_attr( $vessera_uid . '-' . $vessera_param ); ?>" name="<?php echo esc_attr( $vessera_param ); ?>">
						<option value=""><?php esc_html_e( 'All', 'vessera' ); ?></option>
						<?php foreach ( $vessera_options[ $vessera_param ] as $vessera_slug => $vessera_name ) : ?>
							<option value="<?php echo esc_attr( $vessera_slug ); ?>"><?php echo esc_html( $vessera_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<div class="vessera-finder__field vessera-finder__field--reset">
				<button type="reset" class="vessera-finder__reset"><?php esc_html_e( 'Clear filters', 'vessera' ); ?></button>
			</div>
		</form>
	<?php endif; ?>

	<p class="vessera-finder__status" role="status">
		<?php
		/* translators: %d: number of programs */
		echo esc_html( sprintf( _n( '%d program', '%d programs', $vessera_count, 'vessera' ), $vessera_count ) );
		?>
	</p>

	<ul class="vessera-finder__results" role="list">
		<?php foreach ( $vessera_cards as $vessera_card ) : ?>
			<li
				class="vessera-finder__item"
				data-search="<?php echo esc_attr( $vessera_card['search'] ); ?>"
				<?php foreach ( $vessera_card['data'] as $vessera_param => $vessera_slugs ) : ?>
					data-<?php echo esc_attr( $vessera_param ); ?>="<?php echo esc_attr( $vessera_slugs ); ?>"
				<?php endforeach; ?>
			>
				<article class="vessera-finder__card">
					<<?php echo $vessera_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="vessera-finder__title">
						<a href="<?php echo esc_url( get_permalink( $vessera_card['post'] ) ); ?>"><?php echo esc_html( get_the_title( $vessera_card['post'] ) ); ?></a>
					</<?php echo $vessera_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

					<?php if ( $vessera_card['credentials'] ) : ?>
						<ul class="vessera-finder__credentials">
							<?php foreach ( $vessera_card['credentials'] as $vessera_credential ) : ?>
								<?php
								// "Associate of Applied Science · 4 semesters · 64 credits"
								$vessera_parts = array( $vessera_credential['term']->name );
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
								?>
								<li><?php echo esc_html( implode( ' · ', $vessera_parts ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( '' !== $vessera_card['notice'] ) : ?>
						<p class="vessera-finder__notice"><?php echo esc_html( $vessera_card['notice'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $attributes['showExcerpt'] ) && $vessera_card['excerpt'] ) : ?>
						<p class="vessera-finder__excerpt"><?php echo esc_html( $vessera_card['excerpt'] ); ?></p>
					<?php endif; ?>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="vessera-finder__empty" hidden>
		<p><?php esc_html_e( 'No programs match your search.', 'vessera' ); ?></p>
		<button type="button" class="vessera-finder__reset"><?php esc_html_e( 'Clear filters', 'vessera' ); ?></button>
	</div>
</div>
