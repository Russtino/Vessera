<?php
/**
 * One-time conversion of content made with Catalogist, this plugin's former
 * name, so sites that used it keep their programs, courses and settings.
 *
 * Runs on activation (before default terms are created) and, if Catalogist
 * data turns up later, once on the next admin page load.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Migrate {

	const DONE   = 'vessera_migrated_catalogist';
	const NOTICE = 'vessera_migration_notice';

	const POST_TYPES = array(
		'catalogist_program'  => 'vessera_program',
		'catalogist_course'   => 'vessera_course',
		'catalogist_template' => 'vessera_template',
	);

	const TAXONOMIES = array(
		'catalogist_credential'  => 'vessera_credential',
		'catalogist_department'  => 'vessera_department',
		'catalogist_career_area' => 'vessera_career_area',
		'catalogist_delivery'    => 'vessera_delivery',
	);

	const OPTIONS = array(
		'catalogist_programs_page'           => 'vessera_programs_page',
		'catalogist_auto_course_info'        => 'vessera_auto_course_info',
		'catalogist_auto_course_description' => 'vessera_auto_course_description',
		'catalogist_delete_data'             => 'vessera_delete_data',
	);

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Convert Catalogist content if there is any and it hasn't been done yet.
	 */
	public static function maybe_run() {
		if ( get_option( self::DONE ) ) {
			return;
		}
		if ( self::has_old_data() ) {
			$count = self::run();
			update_option( self::NOTICE, $count, false );
		}
		update_option( self::DONE, 1, false );
	}

	private static function has_old_data() {
		global $wpdb;

		$types = array_keys( self::POST_TYPES );
		$taxes = array_keys( self::TAXONOMIES );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders -- one-time check of a former plugin's data; placeholders are built to match.
		$posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} WHERE post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') LIMIT 1',
				$types
			)
		);
		$terms = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->term_taxonomy} WHERE taxonomy IN (" . implode( ',', array_fill( 0, count( $taxes ), '%s' ) ) . ') LIMIT 1',
				$taxes
			)
		);
		// phpcs:enable

		return $posts || $terms || 'vessera-unset' !== get_option( 'catalogist_programs_page', 'vessera-unset' );
	}

	/**
	 * Move everything over. Returns the number of programs and courses converted.
	 */
	private static function run() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- a one-time bulk conversion; caches are flushed at the end.

		// Programs, courses and map templates.
		$count = 0;
		foreach ( self::POST_TYPES as $old => $new ) {
			$count += (int) $wpdb->update( $wpdb->posts, array( 'post_type' => $new ), array( 'post_type' => $old ) );
		}

		// Their fields: _catalogist_* becomes _vessera_*.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = CONCAT( '_vessera_', SUBSTRING( meta_key, %d ) ) WHERE meta_key LIKE %s",
				strlen( '_catalogist_' ) + 1,
				$wpdb->esc_like( '_catalogist_' ) . '%'
			)
		);

		// Department course prefixes.
		$wpdb->update( $wpdb->termmeta, array( 'meta_key' => 'vessera_course_prefixes' ), array( 'meta_key' => 'catalogist_course_prefixes' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery

		// Credential types, departments, career areas and delivery modes.
		$merged = self::move_terms();

		// Program credentials refer to credential terms by ID; follow any merges.
		if ( $merged ) {
			self::remap_credentials( $merged );
		}

		// Blocks saved in pages, programs, templates and patterns.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_content = REPLACE( post_content, %s, %s ) WHERE post_content LIKE %s",
				'wp:catalogist/',
				'wp:vessera/',
				'%' . $wpdb->esc_like( 'wp:catalogist/' ) . '%'
			)
		);

		// Site Editor templates such as "Single item: Program" (single-catalogist_program).
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->posts} SET post_name = REPLACE( post_name, %s, %s ) WHERE post_type IN ( 'wp_template', 'wp_template_part' ) AND post_name LIKE %s",
				'catalogist_',
				'vessera_',
				'%' . $wpdb->esc_like( 'catalogist_' ) . '%'
			)
		);

		// Menu items that link to program or course archives, or to terms.
		foreach ( array_merge( self::POST_TYPES, self::TAXONOMIES ) as $old => $new ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_key' => '_menu_item_object', 'meta_value' => $old ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		// phpcs:enable

		// Settings. Passing a default to get_option() bypasses the defaults from
		// register_setting(), which would otherwise make an unsaved Vessera
		// setting look already chosen when activating from the Plugins screen.
		$unset = 'vessera-unset';
		foreach ( self::OPTIONS as $old => $new ) {
			$value = get_option( $old, $unset );
			if ( $unset !== $value && $unset === get_option( $new, $unset ) ) {
				update_option( $new, $value );
			}
			delete_option( $old );
		}
		delete_option( 'catalogist_flush_rewrite_rules' );

		wp_cache_flush();
		update_option( 'vessera_flush_rewrite_rules', 1, false );

		return $count;
	}

	/**
	 * Rename each old term's taxonomy. If Vessera already has a term with the
	 * same slug (for example a default credential type), the old term is
	 * merged into it. Returns old term ID => new term ID for merged terms.
	 */
	private static function move_terms() {
		global $wpdb;

		$merged = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time bulk conversion.
		foreach ( self::TAXONOMIES as $old_tax => $new_tax ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tt.term_taxonomy_id, tt.term_id, t.slug FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s",
					$old_tax
				)
			);

			foreach ( $rows as $row ) {
				$existing = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT tt.term_taxonomy_id, tt.term_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND t.slug = %s",
						$new_tax,
						$row->slug
					)
				);

				if ( ! $existing ) {
					$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $new_tax ), array( 'term_taxonomy_id' => $row->term_taxonomy_id ) );
					continue;
				}

				// Merge: move assignments, keep any term meta the target lacks, remove the old term.
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->term_relationships} ( object_id, term_taxonomy_id, term_order ) SELECT object_id, %d, term_order FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
						$existing->term_taxonomy_id,
						$row->term_taxonomy_id
					)
				);
				$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $row->term_taxonomy_id ) );

				$target_keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->termmeta} WHERE term_id = %d", $existing->term_id ) );
				$old_meta    = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key FROM {$wpdb->termmeta} WHERE term_id = %d", $row->term_id ) );
				foreach ( $old_meta as $meta ) {
					if ( in_array( $meta->meta_key, $target_keys, true ) ) {
						$wpdb->delete( $wpdb->termmeta, array( 'meta_id' => $meta->meta_id ) );
					} else {
						$wpdb->update( $wpdb->termmeta, array( 'term_id' => $existing->term_id ), array( 'meta_id' => $meta->meta_id ) );
					}
				}

				$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => $existing->term_id ), array( 'parent' => $row->term_id ) );
				$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $row->term_taxonomy_id ) );
				$wpdb->delete( $wpdb->terms, array( 'term_id' => $row->term_id ) );

				$merged[ (int) $row->term_id ] = (int) $existing->term_id;
			}

			// Recount now that the terms belong to Vessera's taxonomies.
			$tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $new_tax ) );
			if ( $tt_ids && taxonomy_exists( $new_tax ) ) {
				wp_update_term_count_now( array_map( 'intval', $tt_ids ), $new_tax );
			}
		}
		// phpcs:enable

		return $merged;
	}

	/**
	 * Point program credentials at merged credential terms.
	 */
	private static function remap_credentials( $merged ) {
		$programs = get_posts(
			array(
				'post_type'      => 'vessera_program',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $programs as $program_id ) {
			$credentials = get_post_meta( $program_id, '_vessera_credentials', true );
			if ( ! is_array( $credentials ) ) {
				continue;
			}
			$changed = false;
			foreach ( $credentials as $i => $entry ) {
				$id = isset( $entry['credential'] ) ? (int) $entry['credential'] : 0;
				if ( isset( $merged[ $id ] ) ) {
					$credentials[ $i ]['credential'] = $merged[ $id ];
					$changed                         = true;
				}
			}
			if ( $changed ) {
				update_post_meta( $program_id, '_vessera_credentials', $credentials );
			}
		}
	}

	/**
	 * Tell the administrator once that their Catalogist content was converted.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$count = get_option( self::NOTICE );
		if ( false === $count ) {
			return;
		}
		delete_option( self::NOTICE );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of programs, courses and map templates converted */
						_n(
							'Vessera converted %d program, course or map template made with Catalogist, along with its settings. Catalogist can now be deactivated and deleted.',
							'Vessera converted %d programs, courses and map templates made with Catalogist, along with their settings. Catalogist can now be deactivated and deleted.',
							(int) $count,
							'vessera'
						),
						(int) $count
					)
				);
				?>
			</p>
		</div>
		<?php
	}
}
