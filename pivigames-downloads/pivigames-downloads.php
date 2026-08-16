<?php
/**
 * Plugin Name:       PiviGames Downloads
 * Plugin URI:        https://pivigame.com
 * Description:        Adds one or more download links to a post, each with Fuente de descarga (Download Source), Tamaño (Size) and a customizable "Get Now" button. Displayed in Spanish at the end of the post, below the system requirements. Built for the Hubber theme.
 * Version:           1.0.0
 * Author:            PiviGames
 * Author URI:        https://pivigame.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pivigames-downloads
 * Domain Path:       /languages
 *
 * @package PiviGames_Downloads
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIVIGAMES_DOWNLOADS_VERSION', '1.0.0' );
define( 'PIVIGAMES_DOWNLOADS_FILE', __FILE__ );
define( 'PIVIGAMES_DOWNLOADS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIVIGAMES_DOWNLOADS_URL', plugin_dir_url( __FILE__ ) );

require_once PIVIGAMES_DOWNLOADS_DIR . 'includes/class-pivigames-downloads.php';

/**
 * Boot the plugin.
 */
function pivigames_downloads() {
	return PiviGames_Downloads::instance();
}
pivigames_downloads();
