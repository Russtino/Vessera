<?php
/**
 * Courses list screen: code, credits and program columns, sorting by code,
 * and filters for department and program.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Admin_Columns {

	const PROGRAM_FILTER = 'vessera_program_filter';

	public static function init() {
		$course = Vessera_Post_Types::COURSE;

		add_filter( "manage_{$course}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$course}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$course}_sortable_columns", array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_query' ) );
	}

	/**
	 * The program filter's value: a program ID, 'none', or 0 for all programs.
	 */
	private static function selected_program() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$value = isset( $_GET[ self::PROGRAM_FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::PROGRAM_FILTER ] ) ) : '';
		return 'none' === $value ? 'none' : absint( $value );
	}

	private static function program_statuses() {
		return array( 'publish', 'draft', 'pending', 'future', 'private' );
	}

	private static function is_course_list( $query = null ) {
		global $typenow;

		if ( ! is_admin() || Vessera_Post_Types::COURSE !== $typenow ) {
			return false;
		}
		return ! $query || $query->is_main_query();
	}

	// ---- Columns ------------------------------------------------------------

	public static function columns( $columns ) {
		$ordered = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$ordered['vessera_code'] = __( 'Code', 'vessera' );
			}
			$ordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$ordered['vessera_credits']  = __( 'Credits', 'vessera' );
				$ordered['vessera_programs'] = __( 'Programs', 'vessera' );
			}
		}
		return $ordered;
	}

	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'vessera_code':
				echo esc_html( Vessera_Blocks::course_code( $post_id ) );
				break;

			case 'vessera_credits':
				$credits = Vessera_Blocks::course_credits( $post_id );
				echo $credits > 0 ? esc_html( Vessera_Blocks::format_credits( $credits ) ) : '—';
				break;

			case 'vessera_programs':
				$index = Vessera_Catalog::course_program_index( self::program_statuses() );
				$links = array();

				foreach ( isset( $index[ $post_id ] ) ? $index[ $post_id ] : array() as $program_id ) {
					$url     = add_query_arg(
						array(
							'post_type'          => Vessera_Post_Types::COURSE,
							self::PROGRAM_FILTER => $program_id,
						),
						admin_url( 'edit.php' )
					);
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $program_id ) ) . '</a>';
				}

				echo $links ? implode( ', ', $links ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['vessera_code'] = 'vessera_code';
		return $columns;
	}

	// ---- Filters ------------------------------------------------------------

	public static function filters( $post_type ) {
		if ( Vessera_Post_Types::COURSE !== $post_type ) {
			return;
		}

		$department = get_taxonomy( Vessera_Taxonomies::DEPARTMENT );

		// The taxonomy's query var filters the list with no extra code.
		echo '<label class="screen-reader-text" for="vessera-filter-department">' . esc_html__( 'Filter by department', 'vessera' ) . '</label>';
		wp_dropdown_categories(
			array(
				'taxonomy'        => Vessera_Taxonomies::DEPARTMENT,
				'name'            => $department->query_var,
				'id'              => 'vessera-filter-department',
				'value_field'     => 'slug',
				'selected'        => get_query_var( $department->query_var ),
				'show_option_all' => __( 'All departments', 'vessera' ),
				'hierarchical'    => true,
				'hide_empty'      => false,
				'orderby'         => 'name',
			)
		);

		$programs = get_posts(
			array(
				'post_type'      => Vessera_Post_Types::PROGRAM,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$selected = self::selected_program();
		?>
		<label class="screen-reader-text" for="vessera-filter-program"><?php esc_html_e( 'Filter by program', 'vessera' ); ?></label>
		<select name="<?php echo esc_attr( self::PROGRAM_FILTER ); ?>" id="vessera-filter-program">
			<option value="0"><?php esc_html_e( 'All programs', 'vessera' ); ?></option>
			<option value="none" <?php selected( $selected, 'none' ); ?>><?php esc_html_e( 'Not in any program', 'vessera' ); ?></option>
			<?php foreach ( $programs as $program ) : ?>
				<option value="<?php echo esc_attr( $program->ID ); ?>" <?php selected( $selected, $program->ID ); ?>>
					<?php echo esc_html( get_the_title( $program ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function apply_query( $query ) {
		if ( ! self::is_course_list( $query ) ) {
			return;
		}

		$selected = self::selected_program();

		if ( 'none' === $selected ) {
			// Courses that appear in no program map, draft programs included.
			$used = array_keys( Vessera_Catalog::course_program_index( self::program_statuses() ) );
			if ( $used ) {
				$query->set( 'post__not_in', $used );
			}
		} elseif ( $selected ) {
			$ids = Vessera_Catalog::program_course_ids( $selected );
			$query->set( 'post__in', $ids ? $ids : array( 0 ) ); // array( 0 ) = no results.
		}

		if ( 'vessera_code' === $query->get( 'orderby' ) ) {
			// Named clauses keep courses without a code in the list.
			$query->set(
				'meta_query',
				array(
					'relation'  => 'OR',
					'vessera_code'  => array(
						'key'     => '_vessera_course_code',
						'compare' => 'EXISTS',
					),
					'vessera_nocode' => array(
						'key'     => '_vessera_course_code',
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'vessera_code' );
		}
	}
}
