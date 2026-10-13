<?php
/**
 * Plugin Name:       Vessera – Program & Course Catalog for Colleges
 * Description:       Program and course catalog for colleges: credentials, semester-by-semester program maps, course pages, and searchable finders.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Russtino
 * Author URI:        https://profiles.wordpress.org/russtino/
 * Plugin URI:        https://russtino.github.io/Vessera/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vessera
 */

defined( 'ABSPATH' ) || exit;

define( 'VESSERA_VERSION', '1.0.0' );
define( 'VESSERA_PLUGIN_FILE', __FILE__ );
define( 'VESSERA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-post-types.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-taxonomies.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-meta.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-editor.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-blocks.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-settings.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-import.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-departments.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-catalog.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-admin-columns.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-templates.php';
require_once VESSERA_PLUGIN_DIR . 'includes/class-vessera-migrate.php';

add_action( 'init', array( 'Vessera_Post_Types', 'register' ) );
add_action( 'init', array( 'Vessera_Taxonomies', 'register' ) );
add_action( 'init', array( 'Vessera_Meta', 'register' ) );

Vessera_Editor::init();
Vessera_Blocks::init();
Vessera_Settings::init();
Vessera_Import::init();
Vessera_Departments::init();
Vessera_Admin_Columns::init();
Vessera_Templates::init();
Vessera_Migrate::init();

register_activation_hook( __FILE__, 'vessera_activate' );
register_deactivation_hook( __FILE__, 'vessera_deactivate' );

/**
 * Register content types, seed default terms, and refresh permalinks.
 */
function vessera_activate() {
	Vessera_Post_Types::register();
	Vessera_Taxonomies::register();
	// Convert any content made with Catalogist (this plugin's former name)
	// first, so its credential types aren't duplicated by the defaults.
	Vessera_Migrate::maybe_run();
	Vessera_Taxonomies::insert_default_terms();
	flush_rewrite_rules();
}

/**
 * Remove our rewrite rules. Content is left untouched.
 */
function vessera_deactivate() {
	unregister_post_type( Vessera_Post_Types::PROGRAM );
	unregister_post_type( Vessera_Post_Types::COURSE );
	flush_rewrite_rules();
}
