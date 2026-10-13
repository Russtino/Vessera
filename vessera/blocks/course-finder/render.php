<?php
/**
 * Front-end output for the Course Finder block.
 *
 * Every published course is rendered as a table row, so the full list works
 * without JavaScript. The shared finder script (the same one the Program
 * Finder uses) reveals the form and filters the rows in the browser.
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vessera_courses = get_posts(
	array(
		'post_type'      => Vessera_Post_Types::COURSE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	)
);

if ( ! $vessera_courses ) {
	return;
}

$vessera_program_index = Vessera_Catalog::course_program_index();
$vessera_department_options = array(); // slug => name
$vessera_program_options    = array(); // slug => title
$vessera_rows               = array();

foreach ( $vessera_courses as $vessera_course ) {
	$vessera_code   = Vessera_Blocks::course_code( $vessera_course->ID );
	$vessera_title  = get_the_title( $vessera_course );
	$vessera_search = array( $vessera_code, $vessera_title );

	$vessera_dept_names = array();
	$vessera_dept_slugs = array();
	$vessera_terms      = get_the_terms( $vessera_course, Vessera_Taxonomies::DEPARTMENT );
	if ( $vessera_terms && ! is_wp_error( $vessera_terms ) ) {
		foreach ( $vessera_terms as $vessera_term ) {
			$vessera_dept_names[]                            = $vessera_term->name;
			$vessera_dept_slugs[]                            = $vessera_term->slug;
			$vessera_department_options[ $vessera_term->slug ] = $vessera_term->name;
		}
	}

	$vessera_program_slugs = array();
	foreach ( isset( $vessera_program_index[ $vessera_course->ID ] ) ? $vessera_program_index[ $vessera_course->ID ] : array() as $vessera_program_id ) {
		$vessera_slug                          = get_post_field( 'post_name', $vessera_program_id );
		$vessera_program_slugs[]               = $vessera_slug;
		$vessera_program_options[ $vessera_slug ] = get_the_title( $vessera_program_id );
	}

	$vessera_rows[] = array(
		'post'        => $vessera_course,
		'code'        => $vessera_code,
		'title'       => $vessera_title,
		'credits'     => Vessera_Blocks::course_credits( $vessera_course->ID ),
		'departments' => implode( ', ', $vessera_dept_names ),
		'data'        => array(
			'department' => implode( ' ', $vessera_dept_slugs ),
			'program'    => implode( ' ', $vessera_program_slugs ),
		),
		// Codes are also searchable without the space: "cpd153".
		'search'      => mb_strtolower(
			remove_accents( implode( ' ', array_merge( $vessera_search, $vessera_dept_names, array( str_replace( ' ', '', $vessera_code ) ) ) ) )
		),
	);
}

// Order by course code (natural: CPD 90 before CPD 153), then title.
usort(
	$vessera_rows,
	function ( $a, $b ) {
		return strnatcasecmp( $a['code'], $b['code'] ) ?: strnatcasecmp( $a['title'], $b['title'] );
	}
);

$vessera_uid     = wp_unique_id( 'vessera-course-finder-' );
$vessera_count   = count( $vessera_rows );
$vessera_filters = array();

if ( ! empty( $attributes['showDepartmentFilter'] ) && count( $vessera_department_options ) > 1 ) {
	asort( $vessera_department_options, SORT_NATURAL | SORT_FLAG_CASE );
	$vessera_filters['department'] = array( __( 'Department', 'vessera' ), $vessera_department_options );
}
if ( ! empty( $attributes['showProgramFilter'] ) && count( $vessera_program_options ) > 0 ) {
	asort( $vessera_program_options, SORT_NATURAL | SORT_FLAG_CASE );
	$vessera_filters['program'] = array( __( 'Program', 'vessera' ), $vessera_program_options );
}

$vessera_has_form  = ! empty( $attributes['showSearch'] ) || $vessera_filters;
$vessera_show_dept = ! empty( $attributes['showDepartmentColumn'] );
$vessera_link      = ! empty( $attributes['linkCourses'] );
?>
<div
	<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?>
	data-count-one="<?php /* translators: %d: number of results (one) */ echo esc_attr__( '%d course found', 'vessera' ); ?>"
	data-count-other="<?php /* translators: %d: number of results */ echo esc_attr__( '%d courses found', 'vessera' ); ?>"
>
	<?php if ( $vessera_has_form ) : ?>
		<form class="vessera-finder__form" role="search" aria-label="<?php esc_attr_e( 'Find a course', 'vessera' ); ?>" hidden>
			<?php if ( ! empty( $attributes['showSearch'] ) ) : ?>
				<div class="vessera-finder__field vessera-finder__field--search">
					<label for="<?php echo esc_attr( $vessera_uid . '-q' ); ?>"><?php esc_html_e( 'Search by course code or title', 'vessera' ); ?></label>
					<input type="search" id="<?php echo esc_attr( $vessera_uid . '-q' ); ?>" name="course" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php foreach ( $vessera_filters as $vessera_param => $vessera_filter ) : ?>
				<div class="vessera-finder__field">
					<label for="<?php echo esc_attr( $vessera_uid . '-' . $vessera_param ); ?>"><?php echo esc_html( $vessera_filter[0] ); ?></label>
					<select id="<?php echo esc_attr( $vessera_uid . '-' . $vessera_param ); ?>" name="<?php echo esc_attr( $vessera_param ); ?>">
						<option value=""><?php esc_html_e( 'All', 'vessera' ); ?></option>
						<?php foreach ( $vessera_filter[1] as $vessera_slug => $vessera_name ) : ?>
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
		/* translators: %d: number of courses */
		echo esc_html( sprintf( _n( '%d course', '%d courses', $vessera_count, 'vessera' ), $vessera_count ) );
		?>
	</p>

	<div class="vessera-finder__results vessera-course-finder__scroll">
		<table class="vessera-course-finder__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Code', 'vessera' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Title', 'vessera' ); ?></th>
					<th scope="col" class="vessera-num"><?php esc_html_e( 'Credits', 'vessera' ); ?></th>
					<?php if ( $vessera_show_dept ) : ?>
						<th scope="col"><?php esc_html_e( 'Department', 'vessera' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vessera_rows as $vessera_row ) : ?>
					<tr
						class="vessera-finder__item"
						data-search="<?php echo esc_attr( $vessera_row['search'] ); ?>"
						data-department="<?php echo esc_attr( $vessera_row['data']['department'] ); ?>"
						data-program="<?php echo esc_attr( $vessera_row['data']['program'] ); ?>"
					>
						<td class="vessera-course-finder__code"><?php echo esc_html( $vessera_row['code'] ); ?></td>
						<td>
							<?php if ( $vessera_link ) : ?>
								<a href="<?php echo esc_url( get_permalink( $vessera_row['post'] ) ); ?>"><?php echo esc_html( $vessera_row['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $vessera_row['title'] ); ?>
							<?php endif; ?>
						</td>
						<td class="vessera-num"><?php echo $vessera_row['credits'] > 0 ? esc_html( Vessera_Blocks::format_credits( $vessera_row['credits'] ) ) : ''; ?></td>
						<?php if ( $vessera_show_dept ) : ?>
							<td><?php echo esc_html( $vessera_row['departments'] ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="vessera-finder__empty" hidden>
		<p><?php esc_html_e( 'No courses match your search.', 'vessera' ); ?></p>
		<button type="button" class="vessera-finder__reset"><?php esc_html_e( 'Clear filters', 'vessera' ); ?></button>
	</div>
</div>
