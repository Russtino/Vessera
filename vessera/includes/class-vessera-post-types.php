<?php
/**
 * Registers the Program and Course post types.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Post_Types {

	const PROGRAM = 'vessera_program';
	const COURSE  = 'vessera_course';

	public static function register() {
		// With a Programs page chosen, turn off the built-in archive so the page
		// owns its address, and nest program URLs under the page's path.
		$programs_page = Vessera_Settings::programs_page_id();
		$program_slug  = $programs_page ? get_page_uri( $programs_page ) : 'programs';

		register_post_type(
			self::PROGRAM,
			array(
				'labels'        => array(
					'name'               => __( 'Programs', 'vessera' ),
					'singular_name'      => __( 'Program', 'vessera' ),
					'menu_name'          => __( 'Vessera', 'vessera' ),
					'all_items'          => __( 'All Programs', 'vessera' ),
					'add_new_item'       => __( 'Add New Program', 'vessera' ),
					'edit_item'          => __( 'Edit Program', 'vessera' ),
					'new_item'           => __( 'New Program', 'vessera' ),
					'view_item'          => __( 'View Program', 'vessera' ),
					'search_items'       => __( 'Search Programs', 'vessera' ),
					'not_found'          => __( 'No programs found.', 'vessera' ),
					'not_found_in_trash' => __( 'No programs found in Trash.', 'vessera' ),
				),
				'public'        => true,
				'has_archive'   => ! $programs_page,
				'show_in_rest'  => true,
				'menu_position' => 20,
				'menu_icon'     => 'dashicons-welcome-learn-more',
				'rewrite'       => array(
					'slug'       => apply_filters( 'vessera_program_slug', $program_slug ),
					'with_front' => false,
				),
				// 'custom-fields' is required for registered meta to appear in the REST API.
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				// Starting layout for new programs.
				'template'      => array(
					array( 'core/paragraph', array( 'placeholder' => __( 'Program overview…', 'vessera' ) ) ),
					array( 'vessera/program-details' ),
					array( 'core/heading', array( 'content' => __( 'Program Map', 'vessera' ) ) ),
					array( 'vessera/program-map' ),
					// Each list hides itself, heading included, until it has items.
					array( 'vessera/program-list', array( 'list' => 'outcomes' ) ),
					array( 'vessera/program-list', array( 'list' => 'careers' ) ),
					array( 'vessera/program-list', array( 'list' => 'certifications' ) ),
				),
			)
		);

		register_post_type(
			self::COURSE,
			array(
				'labels'       => array(
					'name'               => __( 'Courses', 'vessera' ),
					'singular_name'      => __( 'Course', 'vessera' ),
					'all_items'          => __( 'Courses', 'vessera' ),
					'add_new_item'       => __( 'Add New Course', 'vessera' ),
					'edit_item'          => __( 'Edit Course', 'vessera' ),
					'new_item'           => __( 'New Course', 'vessera' ),
					'view_item'          => __( 'View Course', 'vessera' ),
					'search_items'       => __( 'Search Courses', 'vessera' ),
					'not_found'          => __( 'No courses found.', 'vessera' ),
					'not_found_in_trash' => __( 'No courses found in Trash.', 'vessera' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				// Nest Courses under the Vessera menu.
				'show_in_menu' => 'edit.php?post_type=' . self::PROGRAM,
				'rewrite'      => array(
					'slug'       => apply_filters( 'vessera_course_slug', 'courses' ),
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
				// Starting layout for new courses.
				'template'     => array(
					array( 'vessera/course-info' ),
					array( 'vessera/course-description' ),
				),
			)
		);
	}
}
