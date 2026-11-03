<?php
/**
 * Plugin Name:       FK Bulk User Editor
 * Plugin URI:        https://futurekings.co.uk
 * Description:       Adds an "Add to Sites…" bulk action to Network Admin › Users, so you can add a batch of users to one or more sites in your network, with a chosen role, in a single step.
 * Version:           0.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            FutureKings
 * Author URI:        https://futurekings.co.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fk-bulk-user-editor
 * Domain Path:       /languages
 * Network:           true
 * Update URI:        https://futurekings.co.uk
 *
 * @package FK_Bulk_User_Editor
 */

/*
 * FK Bulk User Editor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 2 of the License, or (at your
 * option) any later version.
 *
 * FK Bulk User Editor is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FK_BULK_USER_EDITOR_VERSION', '0.0.1' );
define( 'FK_BULK_USER_EDITOR_FILE', __FILE__ );
define( 'FK_BULK_USER_EDITOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'FK_BULK_USER_EDITOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * This plugin only makes sense on a multisite network - it adds users to
 * *other* sites in the network, which doesn't exist as a concept otherwise.
 */
function fk_bulk_user_editor_activate() {
	if ( is_multisite() ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );
	wp_die(
		esc_html__( 'FK Bulk User Editor requires a WordPress multisite network and could not be activated.', 'fk-bulk-user-editor' ),
		esc_html__( 'Plugin activation error', 'fk-bulk-user-editor' ),
		array( 'back_link' => true )
	);
}
register_activation_hook( __FILE__, 'fk_bulk_user_editor_activate' );

if ( ! is_multisite() ) {
	return;
}

require_once FK_BULK_USER_EDITOR_DIR . 'includes/class-fk-bulk-user-editor.php';

FK_Bulk_User_Editor::instance();
