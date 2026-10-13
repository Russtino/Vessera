<?php
/**
 * Loads the block editor panels for programs and courses.
 */

defined( 'ABSPATH' ) || exit;

class Vessera_Editor {

	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, array( Vessera_Post_Types::PROGRAM, Vessera_Post_Types::COURSE ), true ) ) {
			return;
		}

		$asset_file = VESSERA_PLUGIN_DIR . 'build/editor.asset.php';

		// Build output is missing: run `npm run build`.
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vessera-editor',
			plugins_url( 'build/editor.js', VESSERA_PLUGIN_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'vessera-editor', 'vessera' );

		if ( file_exists( VESSERA_PLUGIN_DIR . 'build/editor.css' ) ) {
			wp_enqueue_style(
				'vessera-editor',
				plugins_url( 'build/editor.css', VESSERA_PLUGIN_FILE ),
				array( 'wp-components' ),
				$asset['version']
			);
		}
	}
}
