<?php
/**
 * Map templates: reusable sets of terms, courses and choice slots (for example,
 * an associate degree's general education requirements). Applying a template
 * copies it into a credential's map, where it can then be changed freely.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Templates {

	const POST_TYPE = 'vessera_template';
	const META      = '_vessera_template_map';
	const NONCE     = 'vessera_template_map_nonce';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Map Templates', 'vessera' ),
					'singular_name'      => __( 'Map Template', 'vessera' ),
					'all_items'          => __( 'Map Templates', 'vessera' ),
					'add_new_item'       => __( 'Add New Map Template', 'vessera' ),
					'edit_item'          => __( 'Edit Map Template', 'vessera' ),
					'new_item'           => __( 'New Map Template', 'vessera' ),
					'search_items'       => __( 'Search Map Templates', 'vessera' ),
					'not_found'          => __( 'No map templates found.', 'vessera' ),
					'not_found_in_trash' => __( 'No map templates found in Trash.', 'vessera' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Vessera_Post_Types::PROGRAM,
				// REST lets the program editor list templates; without 'editor'
				// support the template screen itself uses the simple classic form.
				'show_in_rest' => true,
				'supports'     => array( 'title', 'custom-fields' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( 'Vessera_Meta', 'sanitize_program_map' ),
				'auth_callback'     => array( 'Vessera_Meta', 'can_edit' ),
				'show_in_rest'      => array( 'schema' => Vessera_Meta::map_schema() ),
			)
		);
	}

	public static function add_meta_box() {
		add_meta_box(
			'vessera-template-map',
			__( 'Template map', 'vessera' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		$map = get_post_meta( $post->ID, self::META, true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<?php esc_html_e( 'Build the terms, courses and choice slots this template adds, such as an associate degree’s general education requirements. In a program’s map editor, “Apply a template” copies these into the matching terms: the template’s first term goes into the map’s first term, and so on. After that, each program’s copy can be changed on its own; changing the template later doesn’t affect maps it was already applied to.', 'vessera' ); ?>
		</p>
		<input type="hidden" name="vessera_template_map" id="vessera-template-map-input" value="<?php echo esc_attr( wp_json_encode( is_array( $map ) ? $map : array() ) ); ?>">
		<div id="vessera-template-root"><noscript><?php esc_html_e( 'Editing a map template requires JavaScript.', 'vessera' ); ?></noscript></div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST['vessera_template_map'], $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitized field by field by the map sanitizer.
		$map = json_decode( wp_unslash( $_POST['vessera_template_map'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_post_meta( $post_id, self::META, wp_slash( Vessera_Meta::sanitize_program_map( is_array( $map ) ? $map : array() ) ) );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$asset_file = VESSERA_PLUGIN_DIR . 'build/template.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'vessera-template', plugins_url( 'build/template.js', VESSERA_PLUGIN_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'vessera-template', 'vessera' );
		wp_enqueue_style( 'vessera-template', plugins_url( 'build/template.css', VESSERA_PLUGIN_FILE ), array( 'wp-components' ), $asset['version'] );
	}
}
