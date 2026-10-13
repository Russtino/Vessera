<?php
/**
 * Registers taxonomies used to classify programs and courses.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Taxonomies {

	const CREDENTIAL = 'vessera_credential';
	const DEPARTMENT = 'vessera_department';
	const CAREER     = 'vessera_career_area';
	const DELIVERY   = 'vessera_delivery';

	public static function register() {
		$program = Vessera_Post_Types::PROGRAM;
		$course  = Vessera_Post_Types::COURSE;

		// Credential type: AAS, Technical Certificate, etc.
		self::add(
			self::CREDENTIAL,
			array( $program ),
			'credentials',
			array(
				'name'                     => __( 'Credential Types', 'vessera' ),
				'singular_name'            => __( 'Credential Type', 'vessera' ),
				'all_items'                => __( 'All Credential Types', 'vessera' ),
				'edit_item'                => __( 'Edit Credential Type', 'vessera' ),
				'add_new_item'             => __( 'Add New Credential Type', 'vessera' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Credential Type', 'vessera' ),
				'update_item'              => __( 'Update Credential Type', 'vessera' ),
				'new_item_name'            => __( 'New Credential Type Name', 'vessera' ),
				'search_items'             => __( 'Search Credential Types', 'vessera' ),
				'parent_item'              => __( 'Parent Credential Type', 'vessera' ),
				'parent_item_colon'        => __( 'Parent Credential Type:', 'vessera' ),
				'parent_field_description' => __( 'Optional. Nest a credential type under another to group them.', 'vessera' ),
				'not_found'                => __( 'No credential types found.', 'vessera' ),
				'no_terms'                 => __( 'No credential types', 'vessera' ),
				'back_to_items'            => __( '&larr; Go to Credential Types', 'vessera' ),
				'items_list'               => __( 'Credential Types list', 'vessera' ),
				'items_list_navigation'    => __( 'Credential Types list navigation', 'vessera' ),
				'filter_by_item'           => __( 'Filter by credential type', 'vessera' ),
				'item_link'                => __( 'Credential Type Link', 'vessera' ),
				'item_link_description'    => __( 'A link to a credential type.', 'vessera' ),
			)
		);

		// Department or division. Shared by programs and courses.
		self::add(
			self::DEPARTMENT,
			array( $program, $course ),
			'departments',
			array(
				'name'                     => __( 'Departments', 'vessera' ),
				'singular_name'            => __( 'Department', 'vessera' ),
				'all_items'                => __( 'All Departments', 'vessera' ),
				'edit_item'                => __( 'Edit Department', 'vessera' ),
				'add_new_item'             => __( 'Add New Department', 'vessera' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Department', 'vessera' ),
				'update_item'              => __( 'Update Department', 'vessera' ),
				'new_item_name'            => __( 'New Department Name', 'vessera' ),
				'search_items'             => __( 'Search Departments', 'vessera' ),
				'parent_item'              => __( 'Parent Department', 'vessera' ),
				'parent_item_colon'        => __( 'Parent Department:', 'vessera' ),
				'parent_field_description' => __( 'Optional. Nest a department under another, such as a division.', 'vessera' ),
				'not_found'                => __( 'No departments found.', 'vessera' ),
				'no_terms'                 => __( 'No departments', 'vessera' ),
				'back_to_items'            => __( '&larr; Go to Departments', 'vessera' ),
				'items_list'               => __( 'Departments list', 'vessera' ),
				'items_list_navigation'    => __( 'Departments list navigation', 'vessera' ),
				'filter_by_item'           => __( 'Filter by department', 'vessera' ),
				'item_link'                => __( 'Department Link', 'vessera' ),
				'item_link_description'    => __( 'A link to a department.', 'vessera' ),
			)
		);

		// Career area / career cluster, used by the program finder.
		self::add(
			self::CAREER,
			array( $program ),
			'career-areas',
			array(
				'name'                     => __( 'Career Areas', 'vessera' ),
				'singular_name'            => __( 'Career Area', 'vessera' ),
				'all_items'                => __( 'All Career Areas', 'vessera' ),
				'edit_item'                => __( 'Edit Career Area', 'vessera' ),
				'add_new_item'             => __( 'Add New Career Area', 'vessera' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Career Area', 'vessera' ),
				'update_item'              => __( 'Update Career Area', 'vessera' ),
				'new_item_name'            => __( 'New Career Area Name', 'vessera' ),
				'search_items'             => __( 'Search Career Areas', 'vessera' ),
				'parent_item'              => __( 'Parent Career Area', 'vessera' ),
				'parent_item_colon'        => __( 'Parent Career Area:', 'vessera' ),
				'parent_field_description' => __( 'Optional. Nest a career area under a broader one, such as a career cluster.', 'vessera' ),
				'not_found'                => __( 'No career areas found.', 'vessera' ),
				'no_terms'                 => __( 'No career areas', 'vessera' ),
				'back_to_items'            => __( '&larr; Go to Career Areas', 'vessera' ),
				'items_list'               => __( 'Career Areas list', 'vessera' ),
				'items_list_navigation'    => __( 'Career Areas list navigation', 'vessera' ),
				'filter_by_item'           => __( 'Filter by career area', 'vessera' ),
				'item_link'                => __( 'Career Area Link', 'vessera' ),
				'item_link_description'    => __( 'A link to a career area.', 'vessera' ),
			)
		);

		// Delivery mode: On Campus, Online, Hybrid.
		self::add(
			self::DELIVERY,
			array( $program ),
			'delivery',
			array(
				'name'                     => __( 'Delivery Modes', 'vessera' ),
				'singular_name'            => __( 'Delivery Mode', 'vessera' ),
				'all_items'                => __( 'All Delivery Modes', 'vessera' ),
				'edit_item'                => __( 'Edit Delivery Mode', 'vessera' ),
				'add_new_item'             => __( 'Add New Delivery Mode', 'vessera' ),
				// The rest replace WordPress's generic "Category" wording on the term screens.
				'view_item'                => __( 'View Delivery Mode', 'vessera' ),
				'update_item'              => __( 'Update Delivery Mode', 'vessera' ),
				'new_item_name'            => __( 'New Delivery Mode Name', 'vessera' ),
				'search_items'             => __( 'Search Delivery Modes', 'vessera' ),
				'parent_item'              => __( 'Parent Delivery Mode', 'vessera' ),
				'parent_item_colon'        => __( 'Parent Delivery Mode:', 'vessera' ),
				'parent_field_description' => __( 'Optional. Nest a delivery mode under another to group them.', 'vessera' ),
				'not_found'                => __( 'No delivery modes found.', 'vessera' ),
				'no_terms'                 => __( 'No delivery modes', 'vessera' ),
				'back_to_items'            => __( '&larr; Go to Delivery Modes', 'vessera' ),
				'items_list'               => __( 'Delivery Modes list', 'vessera' ),
				'items_list_navigation'    => __( 'Delivery Modes list navigation', 'vessera' ),
				'filter_by_item'           => __( 'Filter by delivery mode', 'vessera' ),
				'item_link'                => __( 'Delivery Mode Link', 'vessera' ),
				'item_link_description'    => __( 'A link to a delivery mode.', 'vessera' ),
			)
		);
	}

	/**
	 * Shared registration settings. Hierarchical gives editors a checkbox list
	 * instead of free-form tags, which keeps terms consistent.
	 */
	private static function add( $taxonomy, $post_types, $slug, $labels ) {
		register_taxonomy(
			$taxonomy,
			$post_types,
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => $slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Seed common terms on activation. Existing terms are never overwritten.
	 */
	public static function insert_default_terms() {
		$defaults = array(
			self::CREDENTIAL => apply_filters(
				'vessera_default_credential_terms',
				array( 'Associate of Applied Science', 'Associate of Science', 'Technical Certificate', 'Certificate' )
			),
			self::DELIVERY   => apply_filters(
				'vessera_default_delivery_terms',
				array( 'On Campus', 'Online', 'Hybrid' )
			),
		);

		foreach ( $defaults as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}
	}
}
