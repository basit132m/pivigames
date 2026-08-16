<?php
/**
 * Plugin Name:       PiviGames Specs
 * Plugin URI:        https://pivigame.com
 * Description:        Adds a "Información Técnica" (Technical Information) table and a "Requisitos del Sistema" (System Requirements) section to posts. Data is entered per post from the admin panel (bilingual labels) and displayed on the front-end in Spanish. Built for the Hubber theme.
 * Version:           1.0.0
 * Author:            PiviGames
 * Author URI:        https://pivigame.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pivigames-specs
 * Domain Path:       /languages
 *
 * @package PiviGames_Specs
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIVIGAMES_SPECS_VERSION', '1.0.0' );
define( 'PIVIGAMES_SPECS_FILE', __FILE__ );
define( 'PIVIGAMES_SPECS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIVIGAMES_SPECS_URL', plugin_dir_url( __FILE__ ) );

require_once PIVIGAMES_SPECS_DIR . 'includes/class-pivigames-specs.php';

/**
 * Boot the plugin.
 */
function pivigames_specs() {
	return PiviGames_Specs::instance();
}
pivigames_specs();
