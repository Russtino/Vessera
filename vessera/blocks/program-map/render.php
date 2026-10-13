<?php
/**
 * Front-end output for the Program Map block.
 *
 * Shows the map for one credential, or for every credential that has a map.
 * When more than one is shown, each gets a heading with the credential name.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_post_id = Vessera_Blocks::context_post_id( $block, Vessera_Post_Types::PROGRAM );
if ( ! $vessera_post_id ) {
	return;
}

$vessera_only = isset( $attributes['credential'] ) ? (int) $attributes['credential'] : 0;

$vessera_maps = array_filter(
	Vessera_Blocks::program_credentials( $vessera_post_id ),
	function ( $credential ) use ( $vessera_only ) {
		return $credential['map'] && ( ! $vessera_only || $credential['term']->term_id === $vessera_only );
	}
);

if ( ! $vessera_maps ) {
	return;
}

// Load every course across the shown maps (choice options included) with a single query.
$vessera_ids = array();
foreach ( $vessera_maps as $vessera_credential ) {
	$vessera_ids = array_merge( $vessera_ids, Vessera_Catalog::map_course_ids( $vessera_credential['map'] ) );
}
$vessera_courses = Vessera_Blocks::get_courses( $vessera_ids );

/**
 * A course title, linked to its page when links are on.
 */
$vessera_course_link = function ( $course, $text ) use ( &$vessera_link_courses ) {
	return $vessera_link_courses
		? '<a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $text ) . '</a>'
		: esc_html( $text );
};

/**
 * One table row for a map entry: [ html, credits ], or null when its course
 * isn't published.
 */
$vessera_row = function ( $item ) use ( $vessera_courses, $vessera_course_link ) {
	if ( 'choice' === $item['type'] ) {
		$credits = isset( $item['credits'] ) ? (float) $item['credits'] : 0;
		// Each option is one course, or a group taken together: "BI 101 — Biology + BI 102 — Biology Lab".
		$options    = array();
		$has_groups = false;
		foreach ( Vessera_Catalog::option_sets( $item ) as $set ) {
			$links = array();
			foreach ( $set as $option_id ) {
				if ( isset( $vessera_courses[ $option_id ] ) ) {
					$links[] = $vessera_course_link( $vessera_courses[ $option_id ], Vessera_Blocks::course_label( $vessera_courses[ $option_id ] ) );
				}
			}
			// A group is only shown complete, so a missing lab never makes a lecture look like a whole option.
			if ( ! $links || count( $links ) !== count( $set ) ) {
				continue;
			}
			if ( count( $links ) > 1 ) {
				// Courses taken together are stacked as one option.
				$has_groups = true;
				$options[]  = '<ul class="vessera-program-map__group"><li>' . implode( '</li><li>', $links ) . '</li></ul>';
			} else {
				$options[] = $links[0];
			}
		}

		$label = '' !== (string) $item['label'] ? $item['label'] : __( 'Elective', 'vessera' );
		$html  = '<tr class="vessera-program-map__choice"><td class="vessera-program-map__code"></td><td>';
		$html .= '<span class="vessera-program-map__choice-label">' . esc_html( $label ) . '</span>';
		if ( $options ) {
			// One option per line, under a "Choose one:" label when there's a real choice.
			if ( count( $options ) > 1 ) {
				$html .= '<span class="vessera-program-map__choose">' . esc_html__( 'Choose one:', 'vessera' ) . '</span>';
			}
			$html .= '<ul class="vessera-program-map__options' . ( $has_groups ? ' has-groups' : '' ) . '"><li>' . implode( '</li><li>', $options ) . '</li></ul>';
		}
		$html .= '</td><td class="vessera-num">' . ( $credits > 0 ? esc_html( Vessera_Blocks::format_credits( $credits ) ) : '' ) . '</td></tr>';

		return array( $html, $credits );
	}

	$id = isset( $item['id'] ) ? (int) $item['id'] : 0;
	if ( ! isset( $vessera_courses[ $id ] ) ) {
		return null; // Deleted or unpublished.
	}

	$course  = $vessera_courses[ $id ];
	$credits = Vessera_Blocks::course_credits( $course->ID );
	$html    = '<tr><td class="vessera-program-map__code">' . esc_html( Vessera_Blocks::course_code( $course->ID ) ) . '</td>'
		. '<td>' . $vessera_course_link( $course, get_the_title( $course ) ) . '</td>'
		. '<td class="vessera-num">' . esc_html( Vessera_Blocks::format_credits( $credits ) ) . '</td></tr>';

	return array( $html, $credits );
};

/**
 * "15" or "15–16" for a set of totals.
 */
$vessera_range = function ( $totals ) {
	$min = min( $totals );
	$max = max( $totals );
	return $min === $max
		? Vessera_Blocks::format_credits( $min )
		/* translators: 1: lowest credit total, 2: highest credit total */
		: sprintf( __( '%1$s–%2$s', 'vessera' ), Vessera_Blocks::format_credits( $min ), Vessera_Blocks::format_credits( $max ) );
};

$vessera_maps          = array_values( $vessera_maps );
// Semesters per row: 0 = as many as fit, otherwise 1–4.
$vessera_columns = isset( $attributes['columns'] ) ? min( 4, max( 0, (int) $attributes['columns'] ) ) : 0;
$vessera_link_courses  = ! empty( $attributes['linkCourses'] );
$vessera_show_totals   = ! empty( $attributes['showTermTotals'] );
$vessera_show_headings = count( $vessera_maps ) > 1;
$vessera_heading_tag   = 'h' . min( 6, max( 2, isset( $attributes['headingLevel'] ) ? (int) $attributes['headingLevel'] : 3 ) );

// Tabs: the tab bar is rendered hidden and switched on by the view script,
// so without JavaScript the maps simply stack under their headings.
$vessera_tabs = $vessera_show_headings && ( ! isset( $attributes['display'] ) || 'stacked' !== $attributes['display'] );
$vessera_uid  = wp_unique_id( 'vessera-map-' );

$vessera_wrapper = get_block_wrapper_attributes(
	$vessera_tabs ? array( 'class' => 'vessera-program-map--tabs' ) : array()
);
?>
<div <?php echo $vessera_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>>
	<?php if ( $vessera_tabs ) : ?>
		<div class="vessera-program-map__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Credentials', 'vessera' ); ?>" hidden>
			<?php foreach ( $vessera_maps as $vessera_i => $vessera_credential ) : ?>
				<button
					type="button"
					role="tab"
					class="vessera-program-map__tab"
					id="<?php echo esc_attr( $vessera_uid . '-tab-' . $vessera_i ); ?>"
					aria-controls="<?php echo esc_attr( $vessera_uid . '-panel-' . $vessera_i ); ?>"
					aria-selected="<?php echo 0 === $vessera_i ? 'true' : 'false'; ?>"
					tabindex="<?php echo 0 === $vessera_i ? '0' : '-1'; ?>"
				><?php echo esc_html( $vessera_credential['term']->name ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php
	foreach ( $vessera_maps as $vessera_i => $vessera_credential ) :
		$vessera_pathways = $vessera_credential['pathways'];
		$vessera_pw_ids   = wp_list_pluck( $vessera_pathways, 'id' );
		$vessera_pw_names = wp_list_pluck( $vessera_pathways, 'name', 'id' );

		// Program totals: one per pathway, or a single '' total without pathways.
		$vessera_grand = $vessera_pw_ids ? array_fill_keys( $vessera_pw_ids, 0 ) : array( '' => 0 );
		?>
		<div class="vessera-program-map__credential" id="<?php echo esc_attr( $vessera_uid . '-panel-' . $vessera_i ); ?>">
			<?php if ( $vessera_show_headings ) : ?>
				<<?php echo $vessera_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- h2–h6 only. ?> class="vessera-program-map__heading"><?php echo esc_html( $vessera_credential['term']->name ); ?></<?php echo $vessera_heading_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php endif; ?>

			<?php if ( '' !== $vessera_credential['description'] ) : ?>
				<p class="vessera-program-map__description"><?php echo esc_html( $vessera_credential['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( $vessera_pathways ) : ?>
				<?php
				$vessera_descriptions = array( '' => '' );
				foreach ( $vessera_pathways as $vessera_pathway ) {
					$vessera_descriptions[ $vessera_pathway['id'] ] = $vessera_pathway['description'];
				}
				?>
				<?php // Pathway switcher: shown by the view script; without it, every pathway is listed. ?>
				<div class="vessera-pathways" hidden data-descriptions="<?php echo esc_attr( wp_json_encode( $vessera_descriptions ) ); ?>">
					<span class="vessera-pathways__label" id="<?php echo esc_attr( $vessera_uid . '-pw-' . $vessera_i ); ?>"><?php esc_html_e( 'Pathway:', 'vessera' ); ?></span>
					<span class="vessera-pathways__buttons" role="group" aria-labelledby="<?php echo esc_attr( $vessera_uid . '-pw-' . $vessera_i ); ?>">
						<button type="button" class="vessera-pathways__button" data-pathway="" aria-pressed="true"><?php esc_html_e( 'All', 'vessera' ); ?></button>
						<?php foreach ( $vessera_pathways as $vessera_pathway ) : ?>
							<button type="button" class="vessera-pathways__button" data-pathway="<?php echo esc_attr( $vessera_pathway['id'] ); ?>" aria-pressed="false"><?php echo esc_html( $vessera_pathway['name'] ); ?></button>
						<?php endforeach; ?>
					</span>
					<p class="vessera-pathways__description" hidden></p>
				</div>
			<?php endif; ?>

			<div class="vessera-program-map__terms"<?php echo $vessera_columns ? ' data-columns="' . esc_attr( $vessera_columns ) . '"' : ''; ?>>
				<?php
				foreach ( $vessera_credential['map'] as $vessera_index => $vessera_term ) :
					// Rows grouped as shared ('') and per pathway. Unknown pathways count as shared.
					$vessera_groups = array_merge( array( '' => array() ), array_fill_keys( $vessera_pw_ids, array() ) );
					$vessera_sums   = array_merge( array( '' => 0 ), array_fill_keys( $vessera_pw_ids, 0 ) );

					foreach ( Vessera_Catalog::term_items( $vessera_term ) as $vessera_item ) {
						$vessera_pw = isset( $vessera_item['pathway'] ) && in_array( $vessera_item['pathway'], $vessera_pw_ids, true ) ? $vessera_item['pathway'] : '';
						$vessera_r  = $vessera_row( $vessera_item );
						if ( $vessera_r ) {
							$vessera_groups[ $vessera_pw ][] = $vessera_r[0];
							$vessera_sums[ $vessera_pw ]    += $vessera_r[1];
						}
					}

					// Term total per pathway (shared + that pathway's own).
					$vessera_term_totals = array();
					if ( $vessera_pw_ids ) {
						foreach ( $vessera_pw_ids as $vessera_id ) {
							$vessera_term_totals[ $vessera_id ] = $vessera_sums[''] + $vessera_sums[ $vessera_id ];
						}
					} else {
						$vessera_term_totals[''] = $vessera_sums[''];
					}
					foreach ( $vessera_term_totals as $vessera_id => $vessera_total ) {
						$vessera_grand[ $vessera_id ] += $vessera_total;
					}

					$vessera_has_rows = (bool) array_filter( $vessera_groups );
					$vessera_term_label = ! empty( $vessera_term['label'] )
						? $vessera_term['label']
						/* translators: %d: term number */
						: sprintf( __( 'Term %d', 'vessera' ), $vessera_index + 1 );
					?>
					<table class="vessera-program-map__term">
						<caption><?php echo esc_html( $vessera_term_label ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Course', 'vessera' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Title', 'vessera' ); ?></th>
								<th scope="col" class="vessera-num"><?php esc_html_e( 'Credits', 'vessera' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php echo implode( '', $vessera_groups[''] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows escaped when built. ?>
							<?php if ( ! $vessera_has_rows ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No courses listed.', 'vessera' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
						<?php foreach ( $vessera_pw_ids as $vessera_id ) : ?>
							<?php if ( $vessera_groups[ $vessera_id ] ) : ?>
								<tbody class="vessera-program-map__pathway" data-pathway="<?php echo esc_attr( $vessera_id ); ?>">
									<tr class="vessera-program-map__pathway-heading">
										<th colspan="3" scope="colgroup">
											<?php
											/* translators: %s: pathway name, e.g. Coding */
											echo esc_html( sprintf( __( '%s pathway', 'vessera' ), $vessera_pw_names[ $vessera_id ] ) );
											?>
										</th>
									</tr>
									<?php echo implode( '', $vessera_groups[ $vessera_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows escaped when built. ?>
								</tbody>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( $vessera_show_totals ) : ?>
							<?php
							// Text for "All" ('') and for each pathway; the switcher swaps them.
							$vessera_texts = array( '' => $vessera_range( $vessera_term_totals ) );
							foreach ( $vessera_pw_ids as $vessera_id ) {
								$vessera_texts[ $vessera_id ] = Vessera_Blocks::format_credits( $vessera_term_totals[ $vessera_id ] );
							}
							?>
							<tfoot>
								<tr>
									<th scope="row" colspan="2"><?php esc_html_e( 'Term total', 'vessera' ); ?></th>
									<td class="vessera-num" data-totals="<?php echo esc_attr( wp_json_encode( $vessera_texts ) ); ?>"><?php echo esc_html( $vessera_texts[''] ); ?></td>
								</tr>
							</tfoot>
						<?php endif; ?>
					</table>
				<?php endforeach; ?>
			</div>

			<?php
			// "Total: 62 credits", or per pathway: "Total credits: Coding 62 · Networking 63".
			if ( $vessera_pw_ids ) {
				$vessera_parts = array();
				$vessera_texts = array();
				foreach ( $vessera_pw_ids as $vessera_id ) {
					/* translators: 1: pathway name, 2: credit hours */
					$vessera_parts[]                 = sprintf( __( '%1$s %2$s', 'vessera' ), $vessera_pw_names[ $vessera_id ], Vessera_Blocks::format_credits( $vessera_grand[ $vessera_id ] ) );
					/* translators: 1: pathway name, 2: credit hours */
					$vessera_texts[ $vessera_id ] = sprintf( __( 'Total, %1$s pathway: %2$s credits', 'vessera' ), $vessera_pw_names[ $vessera_id ], Vessera_Blocks::format_credits( $vessera_grand[ $vessera_id ] ) );
				}
				/* translators: %s: per-pathway totals, e.g. "Coding 62 · Networking 63" */
				$vessera_texts[''] = sprintf( __( 'Total credits: %s', 'vessera' ), implode( ' · ', $vessera_parts ) );
			} else {
				/* translators: %s: total credit hours */
				$vessera_texts = array( '' => sprintf( __( 'Total: %s credits', 'vessera' ), Vessera_Blocks::format_credits( $vessera_grand[''] ) ) );
			}
			?>
			<p class="vessera-program-map__total" data-totals="<?php echo esc_attr( wp_json_encode( $vessera_texts ) ); ?>"><?php echo esc_html( $vessera_texts[''] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
